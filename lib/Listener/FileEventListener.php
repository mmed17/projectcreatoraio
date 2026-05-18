<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Listener;

use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Service\ProjectActivityService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IUserSession;

/** @template-implements IEventListener<Event> */
class FileEventListener implements IEventListener {
	/** @var array<int, true> */
	private array $whiteboardFileIds = [];

	public function __construct(
		private readonly ProjectMapper $projectMapper,
		private readonly ProjectActivityService $projectActivityService,
		private readonly IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		match (true) {
			$event instanceof NodeCreatedEvent => $this->handleCreated($event),
			$event instanceof NodeWrittenEvent => $this->handleWritten($event),
			$event instanceof NodeDeletedEvent => $this->handleDeleted($event),
			$event instanceof NodeRenamedEvent => $this->handleRenamed($event),
			$event instanceof NodeCopiedEvent => $this->handleCopied($event),
			default => null,
		};
	}

	private function handleCreated(NodeCreatedEvent $event): void {
		$node = $event->getNode();
		if ($this->isIgnoredFile($node)) {
			return;
		}

		$project = $this->findProjectByNodePath($node->getPath());
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$eventType = $node instanceof Folder
			? ProjectActivityService::EVENT_FOLDER_CREATED
			: ProjectActivityService::EVENT_FILE_CREATED;

		$payload = $node instanceof Folder
			? ['folderName' => $node->getName()]
			: ['fileName' => $node->getName(), 'mimeType' => $node->getMimeType()];

		$this->projectActivityService->record($project, $eventType, ProjectActivityService::SOURCE_FILES, $actor, $payload);
	}

	private function handleWritten(NodeWrittenEvent $event): void {
		$node = $event->getNode();
		if (!$node instanceof File) {
			return;
		}

		if ($this->isWhiteboardFile($node)) {
			return;
		}

		if ($this->isIgnoredFile($node)) {
			return;
		}

		$project = $this->findProjectByNodePath($node->getPath());
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, ProjectActivityService::EVENT_FILE_UPDATED, ProjectActivityService::SOURCE_FILES, $actor, [
			'fileName' => $node->getName(),
			'fileSize' => $node->getSize(),
			'mimeType' => $node->getMimeType(),
		]);
	}

	private function handleDeleted(NodeDeletedEvent $event): void {
		$node = $event->getNode();
		if ($this->isIgnoredFile($node)) {
			return;
		}

		$project = $this->findProjectByNodePath($node->getPath());
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$eventType = $node instanceof Folder
			? ProjectActivityService::EVENT_FOLDER_DELETED
			: ProjectActivityService::EVENT_FILE_DELETED;

		$payload = $node instanceof Folder
			? ['folderName' => $node->getName()]
			: ['fileName' => $node->getName()];

		$this->projectActivityService->record($project, $eventType, ProjectActivityService::SOURCE_FILES, $actor, $payload);
	}

	private function handleRenamed(NodeRenamedEvent $event): void {
		$target = $event->getTarget();
		if ($this->isIgnoredFile($target)) {
			return;
		}

		$source = $event->getSource();
		$project = $this->findProjectByNodePath($target->getPath());
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();

		$this->projectActivityService->record($project, ProjectActivityService::EVENT_FILE_RENAMED, ProjectActivityService::SOURCE_FILES, $actor, [
			'fileName' => $target->getName(),
			'oldName' => $source->getName(),
			'newName' => $target->getName(),
		]);
	}

	private function handleCopied(NodeCopiedEvent $event): void {
		$target = $event->getTarget();
		if ($this->isIgnoredFile($target)) {
			return;
		}

		$project = $this->findProjectByNodePath($target->getPath());
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, ProjectActivityService::EVENT_FILE_COPIED, ProjectActivityService::SOURCE_FILES, $actor, [
			'fileName' => $target->getName(),
		]);
	}

	private function isIgnoredFile($node): bool {
		$name = $node->getName();
		if (!is_string($name)) {
			return true;
		}

		if (str_starts_with($name, '.')) {
			return true;
		}

		if (str_ends_with($name, '.part')) {
			return true;
		}

		return false;
	}

	private function isWhiteboardFile($node): bool {
		$fileId = (int) $node->getId();
		if ($fileId <= 0) {
			return false;
		}

		if (!isset($this->whiteboardFileIds[$fileId])) {
			$project = $this->projectMapper->findByWhiteBoardId($fileId);
			$this->whiteboardFileIds[$fileId] = $project !== null;
		}

		return $this->whiteboardFileIds[$fileId];
	}

	private function findProjectByNodePath(string $nodePath): ?\OCA\ProjectCreatorAIO\Db\Project {
		$nodePath = '/' . trim($nodePath, '/');

		if (preg_match('#/__groupfolders/(\d+)(/.*)?$#', $nodePath, $m)) {
			$folderId = (int) $m[1];
			if ($folderId > 0) {
				return $this->projectMapper->findByFolderId($folderId);
			}
		}

		$pathParts = explode('/', trim($nodePath, '/'));
		$userId = $pathParts[0] ?? null;
		if ($userId === null || $userId === '' || $userId === '__groupfolders') {
			return null;
		}

		$projects = $this->projectMapper->findByUserId($userId);
		$folderIds = [];
		foreach ($projects as $project) {
			$pid = $project->getFolderId();
			if ($pid !== null && $pid > 0) {
				$folderIds[$pid] = $project;
			}
		}

		if (!empty($folderIds)) {
			foreach ($folderIds as $fid => $project) {
				if (str_contains($nodePath, "/__groupfolders/$fid")) {
					return $project;
				}
			}
		}

		$folderPath = null;
		foreach ($projects as $project) {
			$fp = trim((string) $project->getFolderPath());
			if ($fp !== '' && str_contains($nodePath, $fp)) {
				$folderPath = $fp;
				break;
			}
		}

		if ($folderPath !== null) {
			foreach ($projects as $project) {
				$fp = trim((string) $project->getFolderPath());
				if ($fp === $folderPath) {
					return $project;
				}
			}
		}

		return null;
	}
}
