<?php

namespace OCA\ProjectCreatorAIO\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;

class TimelineItemMapper extends QBMapper
{
    public const TABLE_NAME = "project_timeline_items";

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE_NAME, TimelineItem::class);
    }

    /**
     * Find all timeline items for a project
     * @param int $projectId
     * @return TimelineItem[]
     */
    public function findByProject(int $projectId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->orderBy('order_index', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find a single timeline item by ID
     * @param int $id
     * @return TimelineItem|null
     */
    public function find(int $id): ?TimelineItem
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Create a new timeline item
     */
    public function createItem(
        int $projectId,
        string $label,
        ?string $startDate,
        ?string $endDate,
        string $color,
        int $orderIndex,
        ?string $systemKey = null
    ): TimelineItem {
        $item = new TimelineItem();
        $item->setProjectId($projectId);
        $item->setLabel($label);

        if ($startDate !== null) {
            $item->setStartDate(new DateTime($startDate));
        }
        if ($endDate !== null) {
            $item->setEndDate(new DateTime($endDate));
        }

        $item->setColor($color);
        $item->setOrderIndex($orderIndex);

        if ($systemKey !== null && trim($systemKey) !== '') {
            $item->setSystemKey($systemKey);
        }

        $now = new DateTime();
        $item->setCreatedAt($now);
        $item->setUpdatedAt($now);

        return $this->insert($item);
    }

    public function findByProjectAndSystemKey(int $projectId, string $systemKey): ?TimelineItem
    {
        $systemKey = trim($systemKey);
        if ($systemKey === '') {
            return null;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('system_key', $qb->createNamedParameter($systemKey)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Update an existing timeline item
     */
    public function updateItem(TimelineItem $item): TimelineItem
    {
        $item->setUpdatedAt(new DateTime());
        return $this->update($item);
    }

    /**
     * Delete all timeline items for a project
     * @param int $projectId
     */
    public function deleteByProject(int $projectId): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /**
     * Get the next order index for a project
     */
    public function getNextOrderIndex(int $projectId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->max('order_index'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        $next = ((int) ($row['MAX(order_index)'] ?? 0)) + 1;
        return max(3, $next);
    }
}
