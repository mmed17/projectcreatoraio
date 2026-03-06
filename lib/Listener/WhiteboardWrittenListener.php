<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Listener;

use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Service\ProjectNotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\IUserSession;

/** @template-implements IEventListener<NodeWrittenEvent> */
class WhiteboardWrittenListener implements IEventListener {
	public function __construct(
		private readonly ProjectMapper $projectMapper,
		private readonly ProjectNotificationService $projectNotificationService,
		private readonly IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeWrittenEvent) {
			return;
		}

		$node = $event->getNode();
		if (!$node instanceof File) {
			return;
		}

		$fileId = (int) $node->getId();
		if ($fileId <= 0) {
			return;
		}

		$project = $this->projectMapper->findByWhiteBoardId($fileId);
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		if ($actor === null) {
			return;
		}

		$this->projectNotificationService->notifyWhiteboardUpdated($project, $actor);
	}
}
