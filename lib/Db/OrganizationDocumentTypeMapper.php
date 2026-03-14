<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class OrganizationDocumentTypeMapper extends QBMapper
{
    public const TABLE_NAME = 'project_ocr_doc_types';

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE_NAME, OrganizationDocumentType::class);
    }

    public function find(int $id): ?OrganizationDocumentType
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

    public function findByOrganization(int $organizationId, bool $includeInactive = true): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
            ->orderBy('name', 'ASC');

        if (!$includeInactive) {
            $qb->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
        }

        return $this->findEntities($qb);
    }

    public function findByOrganizationAndName(int $organizationId, string $name, bool $onlyActive = false): ?OrganizationDocumentType
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)));

        if ($onlyActive) {
            $qb->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
        }

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function createType(int $organizationId, string $name, array $fields, bool $isActive = true): OrganizationDocumentType
    {
        $type = new OrganizationDocumentType();
        $type->setOrganizationId($organizationId);
        $type->setName($name);
        $type->setFieldsJson(json_encode($fields, JSON_UNESCAPED_SLASHES));
        $type->setIsActive($isActive ? 1 : 0);

        $now = new DateTime();
        $type->setCreatedAt($now);
        $type->setUpdatedAt($now);

        return $this->insert($type);
    }

    public function saveType(OrganizationDocumentType $type): OrganizationDocumentType
    {
        $type->setUpdatedAt(new DateTime());
        return $this->update($type);
    }

    public function deleteType(OrganizationDocumentType $type): void
    {
        $this->delete($type);
    }
}
