<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<DeckPermissionTemplate>
 */
class DeckPermissionTemplateMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		// Keep table name short to satisfy Oracle's 30-char limit (including prefix).
		parent::__construct($db, 'pc_deck_perm_tpl', DeckPermissionTemplate::class);
	}

	/**
	 * @return DeckPermissionTemplate[]
	 */
	public function findByOrganization(int $organizationId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function find(int $id): DeckPermissionTemplate
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function findByOrganizationAndName(int $organizationId, string $name): ?DeckPermissionTemplate
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (\Throwable $e) {
			return null;
		}
	}
}
