<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use DateInterval;
use DateTime;
use DateTimeInterface;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use Psr\Log\LoggerInterface;

class ProjectDeckActivityService {
	public const STATUS_ARCHIVED = 0;
	public const STATUS_ACTIVE = 1;
	public const STATUS_STALE = 2;
	private const STALE_AFTER_DAYS = 90;

	public function __construct(
		private readonly ProjectMapper $projectMapper,
		private readonly ProjectNotificationService $projectNotificationService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function recordCardMoveByBoardId(int $boardId, ?DateTimeInterface $movedAt = null): void {
		if ($boardId <= 0) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$this->recordCardMove($project, $movedAt);
	}

	public function recordCardMove(Project $project, ?DateTimeInterface $movedAt = null): void {
		$moveTime = $this->toMutableDateTime($movedAt ?? new DateTime());
		$project->setLastDeckMoveAt($moveTime);
		$project->setStaleNotifiedAt(null);

		if ((int) ($project->getStatus() ?? self::STATUS_ACTIVE) === self::STATUS_STALE) {
			$project->setStatus(self::STATUS_ACTIVE);
		}

		$this->persistProjectDetails($project);
	}

	public function processStaleProjects(?DateTimeInterface $now = null): void {
		$currentTime = $this->toMutableDateTime($now ?? new DateTime());
		$cutoff = (clone $currentTime)->sub(new DateInterval('P' . self::STALE_AFTER_DAYS . 'D'));

		foreach ($this->projectMapper->listDeckTrackedProjects() as $project) {
			try {
				$this->processProject($project, $currentTime, $cutoff);
			} catch (\Throwable $e) {
				$this->logger->error('Failed to process project stale deck activity state', [
					'exception' => $e,
					'projectId' => $project->getId(),
				]);
			}
		}
	}

	private function processProject(Project $project, DateTime $now, DateTime $cutoff): void {
		$status = (int) ($project->getStatus() ?? self::STATUS_ACTIVE);
		if ($status === self::STATUS_ARCHIVED) {
			return;
		}

		$anchor = $project->getLastDeckMoveAt() ?? $project->getCreatedAt();
		if (!$anchor instanceof DateTimeInterface) {
			return;
		}

		$anchorDate = $this->toMutableDateTime($anchor);
		if ($anchorDate > $cutoff) {
			if ($status === self::STATUS_STALE) {
				$project->setStatus(self::STATUS_ACTIVE);
				$project->setStaleNotifiedAt(null);
				$this->persistProjectDetails($project);
			}
			return;
		}

		$needsUpdate = false;
		if ($status !== self::STATUS_STALE) {
			$project->setStatus(self::STATUS_STALE);
			$needsUpdate = true;
		}

		if ($project->getStaleNotifiedAt() === null) {
			$project->setStaleNotifiedAt(clone $now);
			$needsUpdate = true;
			$this->projectNotificationService->notifyDeckStale($project);
		}

		if ($needsUpdate) {
			$this->persistProjectDetails($project);
		}
	}

	private function toMutableDateTime(DateTimeInterface $dateTime): DateTime {
		if ($dateTime instanceof DateTime) {
			return clone $dateTime;
		}

		return DateTime::createFromInterface($dateTime);
	}

	private function persistProjectDetails(Project $project): void {
		try {
			$this->projectMapper->updateProjectDetails($project);
		} catch (\Throwable $e) {
			if (!$this->isMissingDeckActivityColumnError($e)) {
				throw $e;
			}

			$this->logger->warning('Skipping project deck activity persistence until schema migration is applied', [
				'exception' => $e,
				'projectId' => $project->getId(),
			]);
		}
	}

	private function isMissingDeckActivityColumnError(\Throwable $e): bool {
		$message = $e->getMessage();

		return str_contains($message, 'last_deck_move_at')
			|| str_contains($message, 'stale_notified_at');
	}
}
