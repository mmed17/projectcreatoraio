<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use DateTimeInterface;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProjectActivityEventMapper extends QBMapper {
	public const TABLE_NAME = 'project_activity_events';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME, ProjectActivityEvent::class);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function createEvent(
		int $projectId,
		string $eventType,
		?string $actorUid,
		?string $actorDisplayName,
		array $payload = [],
		?DateTimeInterface $occurredAt = null,
	): ProjectActivityEvent {
		$event = new ProjectActivityEvent();
		$event->setProjectId($projectId);
		$event->setEventType($eventType);
		$event->setActorUid($actorUid);
		$event->setActorDisplayName($actorDisplayName);
		$event->setPayloadArray($payload);
		$event->setOccurredAt($occurredAt instanceof DateTimeInterface ? DateTime::createFromInterface($occurredAt) : new DateTime());

		return $this->insert($event);
	}

	/**
	 * @return ProjectActivityEvent[]
	 */
	public function findForDigest(int $projectId, int $afterEventId = 0, ?DateTimeInterface $notBefore = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gt('id', $qb->createNamedParameter($afterEventId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');

		if ($notBefore instanceof DateTimeInterface) {
			$qb->andWhere($qb->expr()->gte('occurred_at', $qb->createNamedParameter(DateTime::createFromInterface($notBefore), IQueryBuilder::PARAM_DATETIME_MUTABLE)));
		}

		return $this->findEntities($qb);
	}

	public function deleteByProject(int $projectId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
