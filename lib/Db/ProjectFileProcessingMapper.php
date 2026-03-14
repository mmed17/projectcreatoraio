<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProjectFileProcessingMapper extends QBMapper
{
    public const TABLE_NAME = 'project_file_processing';
    private const SELECT_COLUMNS = [
        'id',
        'project_id',
        'organization_id',
        'file_id',
        'file_path',
        'file_name',
        'mime_type',
        'document_type_id',
        'ocr_status',
        'extracted_json',
        'error_message',
        'processed_at',
        'created_at',
        'updated_at',
    ];

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE_NAME, ProjectFileProcessing::class);
    }

    public function find(int $id): ?ProjectFileProcessing
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(...self::SELECT_COLUMNS)
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function findByProjectAndFileId(int $projectId, int $fileId): ?ProjectFileProcessing
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(...self::SELECT_COLUMNS)
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function countByDocumentTypeId(int $documentTypeId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->selectAlias($qb->createFunction('COUNT(*)'), 'record_count')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('document_type_id', $qb->createNamedParameter($documentTypeId, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $value = $result->fetchOne();
        $result->closeCursor();

        return max(0, (int) $value);
    }

    /**
     * @return ProjectFileProcessing[]
     */
    public function findByFileId(int $fileId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(...self::SELECT_COLUMNS)
            ->from($this->getTableName())
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

        return $this->findEntities($qb);
    }

    /**
     * @return ProjectFileProcessing[]
     */
    public function findProcessable(int $limit = 5): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(...self::SELECT_COLUMNS)
            ->from($this->getTableName())
            ->where(
                $qb->expr()->in(
                    'ocr_status',
                    $qb->createNamedParameter(['pending', 'stale'], IQueryBuilder::PARAM_STR_ARRAY)
                )
            )
            ->orderBy('updated_at', 'ASC')
            ->setMaxResults(max(1, $limit));

        return $this->findEntities($qb);
    }

    public function createRecord(
        int $projectId,
        int $organizationId,
        int $fileId,
        string $filePath,
        string $fileName,
        string $mimeType,
        OrganizationDocumentType $documentType,
    ): ProjectFileProcessing {
        $record = new ProjectFileProcessing();
        $record->setProjectId($projectId);
        $record->setOrganizationId($organizationId);
        $record->setFileId($fileId);
        $record->setFilePath($filePath);
        $record->setFileName($fileName);
        $record->setMimeType($mimeType);
        $record->setDocumentTypeId($documentType->getId());
        $record->setOcrStatus('pending');

        $now = new DateTime();
        $record->setCreatedAt($now);
        $record->setUpdatedAt($now);

        return $this->insert($record);
    }

    public function saveRecord(ProjectFileProcessing $record): ProjectFileProcessing
    {
        $record->setUpdatedAt(new DateTime());
        return $this->update($record);
    }
}
