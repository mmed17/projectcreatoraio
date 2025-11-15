<?php

namespace OCA\ProjectCreatorAIO\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use DateTime;
use OCA\Provisioning_API\Db\Organization;
use OCP\AppFramework\Db\DoesNotExistException;

class ProjectMapper extends QBMapper {
    public const TABLE_NAME = "custom_projects";
    public function __construct(
        IDBConnection $db,
        private PrivateFolderLinkMapper $linkMapper
    ) {
        parent::__construct($db, self::TABLE_NAME, Project::class);
    }

    public function createProject(
        Organization $organization,
        string $name,
        string $number,
        int    $type,
        string $address,
        string $description,
        string $ownerId,
        string $circleId,
        string $boardId,
        int    $folderId,
        string $folderPath,
        array  $privateFolders,
    ) {

		$project = new Project();
        
        $project->setName($name);
        $project->setNumber($number);
        $project->setType($type);
        $project->setAddress($address);
        $project->setDescription($description);
        $project->setOwnerId($ownerId);
        $project->setCircleId($circleId);
        $project->setBoardId($boardId);
        $project->setFolderId($folderId);
        $project->setFolderPath($folderPath);
        $project->setOrganizationId($organization->getId());

        $now = new DateTime();
        $project->setCreatedAt($now);
        $project->setUpdatedAt($now);

		$insertedProject = $this->insert($project);

        foreach ($privateFolders as $privateFolder) {
            $this->linkMapper->createLink(
                $insertedProject->getId(),
                $privateFolder['userId'],
                $privateFolder['folderId'],
                $privateFolder['path']
            );
        }

        return $insertedProject;
	}

    public function find(int $id) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        
        try {
            return $this->findEntity($qb);
        } catch(DoesNotExistException $e) {
            return null;
        }
	}

    public function search(string $name, int $limit, int $offset) {
        $qb = $this->db->getQueryBuilder();

        $searchTerm = strtolower($name);
        $qb->select('*')
            ->from(self::TABLE_NAME)
            ->where('LOWER(name) LIKE ' . $qb->createNamedParameter('%' . $searchTerm . '%'));
        
        return $this->findEntities($qb);
    }

    public function list(int $limit = null, int $offset = null) {
        $qb = $this->db->getQueryBuilder();
        
        $qb->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        
        return $this->findEntities($qb);
    }


    public function findByUserId(string $userId): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('p.*')
            ->from(self::TABLE_NAME, 'p')
            ->innerJoin(
                'p',
                'circles_member',
                'm',
                'p.circle_id = m.circle_id'
            )
            ->where(
                $qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId))
            )
            ->orderBy('p.created_at', 'DESC');

        return $this->findEntities($qb);
    }

    public function findByCircleId(string $circleId) {
        $qb = $this->db->getQueryBuilder();
        $qb->select('p.*')
           ->from(self::TABLE_NAME, 'p')
           ->where($qb->expr()->eq('p.circle_id', $qb->createNamedParameter($circleId)));
        return $this->findEntity($qb);
    }

    /**
     * Updates the status of a given project.
     *
     * @param int $projectId The ID of the project to update.
     * @param int $status The new status to set.
     */
    public function updateProjectStatus(int $projectId, int $status): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update(self::TABLE_NAME)
            ->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        $qb->execute();
    }

    /**
     * Finds a project by its associated board_id.
     *
     * @param int $boardId The ID of the board.
     * @return Project|null The found project entity or null if not found.
     */
    public function findByBoardId($boardId) {        
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from(self::TABLE_NAME)
           ->where(
               $qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT))
           );
        try {
            $row = $qb->execute()->fetch();
            return ($row === false) 
                ? null 
                : Project::fromRow($row);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function findByCardId(int $cardId): ?Project {
        $qb = $this->db->getQueryBuilder();
        $qb->select('p.*')
            ->from(self::TABLE_NAME, 'p')
            ->innerJoin('p', 'deck_stacks', 's', 'p.board_id = s.board_id')
            ->innerJoin('s', 'deck_cards', 'c', 's.id = c.stack_id')
            ->where($qb->expr()->eq('c.id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function findPrivateFolderForUser(int $projectId, string $userId): ?PrivateFolderLink {
        return $this->linkMapper->findByProjectAndUser($projectId, $userId);
    }

    /**
     * @return PrivateFolderLink[]
     */
    public function findAllPrivateFoldersByProject(int $projectId): array {
        return $this->linkMapper->findByProject($projectId);
    }
}