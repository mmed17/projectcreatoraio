<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectActivityEventMapper;
use OCA\ProjectCreatorAIO\Db\ProjectNote;
use OCA\ProjectCreatorAIO\Db\TimelineItem;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class ProjectActivityService {
	public const EVENT_PROJECT_CREATED = 'project_created';
	public const EVENT_MEMBER_ADDED = 'member_added';
	public const EVENT_WHITEBOARD_UPDATED = 'whiteboard_updated';
	public const EVENT_PROJECT_NOTES_UPDATED = 'project_notes_updated';
	public const EVENT_NOTE_CREATED = 'note_created';
	public const EVENT_NOTE_UPDATED = 'note_updated';
	public const EVENT_NOTE_DELETED = 'note_deleted';
	public const EVENT_TIMELINE_ITEM_CREATED = 'timeline_item_created';
	public const EVENT_TIMELINE_ITEM_UPDATED = 'timeline_item_updated';
	public const EVENT_TIMELINE_ITEM_DELETED = 'timeline_item_deleted';
	public const EVENT_TIMELINE_REORDERED = 'timeline_reordered';

	public function __construct(
		private readonly ProjectActivityEventMapper $eventMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	public function recordProjectCreated(Project $project, ?IUser $actor = null): void {
		$this->recordProjectEvent($project, self::EVENT_PROJECT_CREATED, $actor);
	}

	public function recordMemberAdded(Project $project, IUser $member, ?IUser $actor = null): void {
		$this->recordProjectEvent($project, self::EVENT_MEMBER_ADDED, $actor, [
			'memberUid' => $member->getUID(),
			'memberDisplayName' => $this->getUserDisplayName($member),
		]);
	}

	public function recordWhiteboardUpdated(Project $project, IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_WHITEBOARD_UPDATED, $actor);
	}

	public function recordProjectNotesUpdated(Project $project, IUser $actor, bool $publicUpdated, bool $privateUpdated): void {
		if (!$publicUpdated && !$privateUpdated) {
			return;
		}

		$this->recordProjectEvent($project, self::EVENT_PROJECT_NOTES_UPDATED, $actor, [
			'publicUpdated' => $publicUpdated,
			'privateUpdated' => $privateUpdated,
		]);
	}

	public function recordNoteCreated(Project $project, ProjectNote $note, IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_NOTE_CREATED, $actor, $this->buildNotePayload($note));
	}

	public function recordNoteUpdated(Project $project, ProjectNote $note, IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_NOTE_UPDATED, $actor, $this->buildNotePayload($note));
	}

	public function recordNoteDeleted(Project $project, ProjectNote $note, IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_NOTE_DELETED, $actor, $this->buildNotePayload($note));
	}

	public function recordTimelineItemCreated(Project $project, TimelineItem $item, ?IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_TIMELINE_ITEM_CREATED, $actor, $this->buildTimelinePayload($item));
	}

	public function recordTimelineItemUpdated(Project $project, TimelineItem $item, ?IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_TIMELINE_ITEM_UPDATED, $actor, $this->buildTimelinePayload($item));
	}

	public function recordTimelineItemDeleted(Project $project, TimelineItem $item, ?IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_TIMELINE_ITEM_DELETED, $actor, $this->buildTimelinePayload($item));
	}

	public function recordTimelineReordered(Project $project, int $count, ?IUser $actor): void {
		$this->recordProjectEvent($project, self::EVENT_TIMELINE_REORDERED, $actor, [
			'count' => $count,
		]);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function recordProjectEvent(Project $project, string $eventType, ?IUser $actor = null, array $payload = []): void {
		$projectId = (int) ($project->getId() ?? 0);
		if ($projectId <= 0) {
			return;
		}

		$actorUid = $actor?->getUID();
		$actorDisplayName = $actor !== null ? $this->getUserDisplayName($actor) : null;
		$payload['projectName'] = trim((string) ($project->getName() ?? ''));

		try {
			$this->eventMapper->createEvent($projectId, $eventType, $actorUid, $actorDisplayName, $payload);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to record project activity event', [
				'exception' => $e,
				'projectId' => $projectId,
				'eventType' => $eventType,
			]);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildNotePayload(ProjectNote $note): array {
		return [
			'noteId' => (int) ($note->getId() ?? 0),
			'title' => trim((string) ($note->getTitle() ?? '')),
			'visibility' => trim((string) ($note->getVisibility() ?? 'public')),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildTimelinePayload(TimelineItem $item): array {
		return [
			'itemId' => (int) ($item->getId() ?? 0),
			'label' => trim((string) ($item->getLabel() ?? '')),
			'itemType' => trim((string) ($item->getItemType() ?? 'phase')),
		];
	}

	private function getUserDisplayName(IUser $user): string {
		$displayName = trim((string) ($user->getDisplayName() ?? ''));
		return $displayName !== '' ? $displayName : (string) $user->getUID();
	}
}
