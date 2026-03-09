<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use DateTimeInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProjectDigestCursorMapper extends QBMapper {
	public const TABLE_NAME = 'project_digest_cursors';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME, ProjectDigestCursor::class);
	}

	public function findByProjectAndUser(int $projectId, string $userUid): ?ProjectDigestCursor {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function advanceCursor(int $projectId, string $userUid, int $lastEventId, ?DateTimeInterface $lastSentAt = null): ProjectDigestCursor {
		$cursor = $this->findByProjectAndUser($projectId, $userUid) ?? new ProjectDigestCursor();
		$cursor->setProjectId($projectId);
		$cursor->setUserUid($userUid);
		$cursor->setLastEventId($lastEventId);
		$cursor->setLastSentAt($lastSentAt instanceof DateTimeInterface ? DateTime::createFromInterface($lastSentAt) : new DateTime());

		if ($cursor->getId() === null) {
			return $this->insert($cursor);
		}

		return $this->update($cursor);
	}

	public function deleteByProject(int $projectId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
