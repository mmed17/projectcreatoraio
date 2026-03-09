<?php
namespace OCA\ProjectCreatorAIO\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Db\DoesNotExistException;

class PrivateFolderLinkMapper extends QBMapper {
    public const TABLE_NAME = 'proj_private_folders';

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, PrivateFolderLink::class);
    }

    /**
     * Creates a link between a project, a user, and their private folder.
     */
    public function createLink(int $projectId, string $userId, int $folderId, string $folderPath): PrivateFolderLink {
        $link = new PrivateFolderLink();
        $link->setProjectId($projectId);
        $link->setUserId($userId);
        $link->setFolderId($folderId);
        $link->setFolderPath($folderPath);
        return $this->insert($link);
    }

    /**
     * Finds the private folder for a specific user within a project.
     */
    public function findByProjectAndUser(int $projectId, string $userId): ?PrivateFolderLink {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        try {
            return $this->findEntity($qb);
        } catch(DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Finds the private folder for a specific project.
     */
    public function findByProject(int $projectId): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        
        try {
            return $this->findEntities($qb);
        } catch(DoesNotExistException $e) {
            return null;
        }
    }

    public function deleteByProject(int $projectId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }
}
