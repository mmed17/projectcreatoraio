<?php

namespace OCA\ProjectCreatorAIO\Service;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCA\Circles\CirclesManager;
use OCA\Circles\Service\FederatedUserService;
use OCA\Deck\Service\BoardService;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Constants;
use OCP\IUserSession;
use OCP\Files\Folder;
use OCP\IUser;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\Deck\Db\Board;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use Throwable;
use Exception;
use OCA\Organization\Db\OrganizationMapper;
use OCA\Organization\Db\PlanMapper;
use OCA\Organization\Db\SubscriptionMapper;
use OCP\AppFramework\OCS\OCSException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\Organization\Db\Plan;
use OCP\IDBConnection;
use OCP\IUserManager;

class ProjectService
{
    public function __construct(
        protected IUserSession $userSession,
        protected CirclesManager $circlesManager,
        protected IShareManager $shareManager,
        protected BoardService $boardService,
        protected IRootFolder $rootFolder,
        protected FederatedUserService $federatedUserService,
        protected ProjectMapper $projectMapper,
        protected FileTreeService $fileTreeService,
        protected OrganizationMapper $organizationMapper,
        protected SubscriptionMapper $subscriptionMapper,
        protected PlanMapper $planMapper,
        protected IGroupManager $groupManager,
        protected FolderManager $folderManager,
        protected IDBConnection $db,
        protected IUserManager $userManager,
        private readonly FolderStorageManager $folderStorageManager,
    ) {
    }

    /**
     * The main public method to create a complete project.
     * It orchestrates all the necessary steps and handles rollbacks.
     */
    public function createProject(
        string $name,
        string $number,
        int $type,
        array $members,
        string $description,
        string $groupId,
        ?string $dateStart = null,
        ?string $dateEnd = null,
    ): Project {

        $createdCircle = null;
        $createdBoard = null;
        $createdFolders = [];

        try {
            $owner = $this->userSession->getUser();
            // check if the owner is an admin
            $isAdmin = $this->groupManager->isInGroup($owner->getUID(), 'admin');

            if ($isAdmin) {
                $organization = $this->organizationMapper->findByGroupId($groupId);
            } else {
                $organization = $this->organizationMapper->findByUserId($owner->getUID());
            }

            if (!$organization) {
                throw new OCSException('An organization must be found to create a project.');
            }

            $subscription = $this->subscriptionMapper->findByOrganizationId($organization->getId());

            $plan = $this->planMapper->find($subscription->getPlanId());
            $count = $this->organizationMapper->getProjectsCount($organization->getId());

            if ($count >= $plan->getMaxProjects()) {
                throw new OCSException(sprintf(
                    "The maximum number of projects allowed for this plan (%d) has been reached. " .
                    "You currently have %d projects. Please upgrade your plan to create additional projects.",
                    $plan->getMaxProjects(),
                    $count
                ));
            }

            $createdCircle = $this->createCircleForProject(
                $name,
                $members,
                $owner
            );

            $createdBoard = $this->createBoardForProject(
                $name,
                $owner,
                $createdCircle->getSingleId()
            );

            $group = $this->createGroupForMembers(
                array_merge($members, [$owner->getUID()]),
                $name
            );

            $createdFolders = $this->createFoldersForProject(
                $name,
                $members,
                $owner,
                $group,
                $plan
            );

            $createdWhiteBoardId = $this->createWhiteboardFile(
                $owner,
                $createdFolders['shared']['name'],
                $createdFolders['shared']['id'],
                $name,
                $createdFolders['shared']['group_folder_id']
            );

            $project = $this->projectMapper->createProject(
                $organization,
                $name,
                $number,
                $type,
                $description,
                $owner->getUID(),
                $createdCircle->getSingleId(),
                $createdBoard->getId(),
                $createdFolders['shared']['group_folder_id'],
                $createdFolders['shared']['name'],
                $createdFolders['private'],
                $createdWhiteBoardId,
                $dateStart,
                $dateEnd,
            );


            return $project;

        } catch (Throwable $e) {

            $this->cleanupResources(
                $createdBoard,
                $createdCircle,
                $createdFolders ?? []
            );

            throw new Exception($e, 500);
        }
    }

    /**
     * Creates and populates a circle for the project.
     */
    private function createCircleForProject(string $projectName, array $members, IUser $owner): Circle
    {
        $this->circlesManager->startSession();

        $circleMembers = array_filter(
            $members,
            fn($memberId) => $memberId !== $owner->getUID()
        );

        $circle = $this->circlesManager->createCircle("{$projectName} - Team", null, false, false);

        foreach ($circleMembers as $memberId) {
            $federatedUser = $this->federatedUserService->getLocalFederatedUser($memberId, true, true);
            $this->circlesManager->addMember($circle->getSingleId(), $federatedUser);
        }

        return $circle;
    }

    private function createGroupForMembers(array $members, string $projectName): IGroup
    {
        // Generate a unique group name using timestamp to avoid slow search loop
        $timestamp = time();
        $projectGroupName = "{$projectName} - Project Group - {$timestamp}";

        // Ensure uniqueness (fallback, should rarely be needed)
        if ($this->groupManager->groupExists($projectGroupName)) {
            $projectGroupName = "{$projectName} - Project Group - {$timestamp}-" . random_int(1000, 9999);
        }

        // Create the group
        $createdGroup = $this->groupManager->createGroup($projectGroupName);

        if ($createdGroup === null) {
            throw new Exception("Failed to create project group '$projectGroupName'.");
        }

        // Batch insert users directly to database (bypasses slow event dispatching)
        $gid = $createdGroup->getGID();
        $this->batchAddUsersToGroup($members, $gid);

        return $createdGroup;
    }

    /**
     * Batch add users to a group using direct database insertion.
     * This bypasses event dispatching for performance (useful for bulk operations).
     */
    private function batchAddUsersToGroup(array $userIds, string $gid): void
    {
        foreach ($userIds as $uid) {
            // Verify user exists
            if ($this->userManager->get($uid) === null) {
                continue;
            }

            // Direct insert - check if already in group first
            $qb = $this->db->getQueryBuilder();
            $qb->select('uid')
                ->from('group_user')
                ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
                ->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)));

            $result = $qb->executeQuery();
            $exists = $result->fetch();
            $result->closeCursor();

            if (!$exists) {
                $insert = $this->db->getQueryBuilder();
                $insert->insert('group_user')
                    ->setValue('uid', $insert->createNamedParameter($uid))
                    ->setValue('gid', $insert->createNamedParameter($gid))
                    ->executeStatement();
            }
        }
    }

    /**
     * Creates and shares a Deck board for the project.
     */
    private function createBoardForProject(string $projectName, IUser $owner, string $circleId): Board
    {
        $color = strtoupper(sprintf('%06X', random_int(0, 0xFFFFFF)));
        $board = $this->boardService->create("{$projectName} - Main Board", $owner->getUID(), $color);

        $this->boardService->addAcl(
            $board->getId(),
            IShare::TYPE_CIRCLE,
            $circleId,
            true,
            false,
            false
        );

        return $board;
    }


    /**
     * Creates and shares all necessary folders for the project.
     * @return array{shared: array{id: int, name: string, group_folder_id: int}, private: array<array{userId: string, folderId: int, path: string}>}
     */
    private function createFoldersForProject(
        string $projectName,
        array $members,
        IUser $owner,
        IGroup $group,
        Plan $plan
    ): array {
        $ownerFolder = $this->rootFolder->getUserFolder($owner->getUID());

        // Create shared folders 
        $sharedFolderName = $this->getUniqueFolderName(
            $projectName,
            'Shared Files',
            $ownerFolder
        );

        $groupFolderId = $this->folderManager->createFolder($sharedFolderName);
        ['storage_id' => $storageId, 'root_id' => $rootId] = $this->folderStorageManager->getRootAndStorageIdForFolder(
            $groupFolderId
        );

        $this->folderManager->addApplicableGroup($groupFolderId, $group->getGID());
        $this->folderManager->setFolderQuota($groupFolderId, $plan->getSharedStoragePerProject());
        $this->folderManager->setGroupPermissions(
            $groupFolderId,
            $group->getGID(),
            Constants::PERMISSION_ALL
        );

        // Create private folders for each member
        $privateFolders = [];
        $allMembers = array_merge($members, [$owner->getUID()]);

        foreach ($allMembers as $memberId) {
            // Get the specific member's root folder
            $memberFolder = $this->rootFolder->getUserFolder($memberId);
            $privateFolderName = $this->getUniqueFolderName(
                $projectName,
                "Private Files",
                $memberFolder
            );

            $privateFolder = $memberFolder->newFolder($privateFolderName);

            $allCreatedFolders[] = $privateFolder;
            $privateFolders[] = [
                'userId' => $memberId,
                'folderId' => $privateFolder->getId(),
                'path' => $privateFolder->getPath(),
            ];
        }

        return [
            'shared' => ['id' => $rootId, 'name' => $sharedFolderName, 'group_folder_id' => $groupFolderId],
            'private' => $privateFolders,
        ];
    }

    private function getUniqueFolderName(string $projectName, string $suffix, Folder $folder): string
    {
        $folderName = "{$projectName} - {$suffix}";

        if (!$folder->nodeExists($folderName)) {
            return $folderName;
        }

        $counter = 2;
        while (true) {
            $folderName = "{$projectName} ({$counter}) - {$suffix}";
            if (!$folder->nodeExists($folderName)) {
                return $folderName;
            }
            $counter++;
        }
    }

    private function cleanupResources(
        ?Board $board,
        ?Circle $circle,
        ?array $folders
    ): void {
        $user = $this->userSession->getUser();

        if (!empty($folders)) {
            foreach ($folders as $folder) {
                if ($folder !== null && $folder->isDeletable()) {
                    $folder->delete();
                }
            }
        }

        if ($board !== null) {
            $this->boardService->delete($board->getId());
        }

        if ($circle !== null) {
            $federatedUser = $this->circlesManager->getFederatedUser(
                $user->getUID(),
                Member::TYPE_USER
            );

            $this->circlesManager->startSession($federatedUser);
            $this->circlesManager->destroyCircle($circle->getSingleId());
        }
    }

    /**
     * Finds the project folder and delegates tree-building to the FileTreeService.
     */
    public function getProjectFiles(int $projectId): array
    {
        $currentUser = $this->userSession->getUser();

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new Exception("Project with ID $projectId not found.");
        }

        $userFolder = $this->rootFolder->getUserFolder($currentUser->getUID());
        $sharedFiles = $userFolder->get($project->getFolderPath());

        if (empty($sharedFiles)) {
            throw new NotFoundException("Project folder node not found on the filesystem.");
        }

        $sharedFilesTree = $this->fileTreeService->buildTree($sharedFiles);

        $privateFolderLinks = [];
        $privateFilesTrees = [];

        $link = $this->projectMapper->findPrivateFolderForUser(
            $projectId,
            $currentUser->getUID()
        );
        if ($link !== null) {
            $privateFolderLinks[] = $link;
        }

        error_log("privateFolderLinks  : " . print_r($privateFolderLinks, true));

        foreach ($privateFolderLinks as $link) {
            try {
                $path = basename($link->getFolderPath());
                $privateFolderNode = $userFolder->get($path);
                $privateFilesTrees[] = $this->fileTreeService->buildTree($privateFolderNode);
            } catch (NotFoundException $e) {
                continue;
            }
        }

        return [
            'shared' => [$sharedFilesTree],
            'private' => $privateFilesTrees
        ];
    }

    public function findProjectByBoard(int $boardId)
    {
        return $this->projectMapper->findByBoardId($boardId);
    }

    public function updateProjectDetails(
        int $id,
        ?string $name = null,
        ?string $number = null,
        ?int $type = null,
        ?string $description = null,
        ?string $client_name = null,
        ?string $client_role = null,
        ?string $client_phone = null,
        ?string $client_email = null,
        ?string $client_address = null,
        ?string $loc_street = null,
        ?string $loc_city = null,
        ?string $loc_zip = null,
        ?string $external_ref = null,
        ?string $date_start = null,
        ?string $date_end = null,
        ?int $status = null
    ) {
        // 1. Fetch the existing project
        $project = $this->projectMapper->find($id);
        if ($project === null) {
            throw new OCSException("Project Not Found", 404);
        }

        // 2. Update Fields (Only if sent in request)
        if ($name !== null)
            $project->setName($name);
        if ($number !== null)
            $project->setNumber($number);
        if ($type !== null)
            $project->setType($type);
        if ($description !== null)
            $project->setDescription($description);

        // Client
        if ($client_name !== null)
            $project->setClientName($client_name);
        if ($client_role !== null)
            $project->setClientRole($client_role);
        if ($client_phone !== null)
            $project->setClientPhone($client_phone);
        if ($client_email !== null)
            $project->setClientEmail($client_email);
        if ($client_address !== null)
            $project->setClientAddress($client_address);

        // Location
        if ($loc_street !== null)
            $project->setLocStreet($loc_street);
        if ($loc_city !== null)
            $project->setLocCity($loc_city);
        if ($loc_zip !== null)
            $project->setLocZip($loc_zip);
        if ($external_ref !== null)
            $project->setExternalRef($external_ref);

        // Timeline (Convert string dates to DateTime objects)
        if ($date_start !== null) {
            $project->setDateStart(empty($date_start) ? null : new \DateTime($date_start));
        }
        if ($date_end !== null) {
            $project->setDateEnd(empty($date_end) ? null : new \DateTime($date_end));
        }
        if ($status !== null)
            $project->setStatus($status);

        // 3. Save via Mapper
        $updatedProject = $this->projectMapper->updateProjectDetails($project);
        return $updatedProject;

    }

    /**
     * Creates a .whiteboard file in the specified shared folder.
     * Optimized to avoid slow file scanning by using direct filecache insertion.
     */
    private function createWhiteboardFile(IUser $owner, string $folderName, int $folderId, string $projectName, int $groupFolderId = 0): int
    {
        try {
            $fileName = $projectName . '.whiteboard';

            // Method 1: Try using Nextcloud Files API (fastest, auto-registers in filecache)
            $userFolder = $this->rootFolder->getUserFolder($owner->getUID());

            // Try to get folder by ID first
            $folder = null;
            $nodes = $this->rootFolder->getById($folderId);
            foreach ($nodes as $node) {
                if ($node instanceof \OCP\Files\Folder) {
                    $folder = $node;
                    break;
                }
            }

            // Fallback to name lookup
            if ($folder === null && $userFolder->nodeExists($folderName)) {
                $node = $userFolder->get($folderName);
                if ($node instanceof \OCP\Files\Folder) {
                    $folder = $node;
                }
            }

            // If we found the folder via Files API, use it (this auto-registers, no scanning needed)
            if ($folder !== null) {
                if ($folder->nodeExists($fileName)) {
                    return (int) $folder->get($fileName)->getId();
                }
                $file = $folder->newFile($fileName);
                return (int) $file->getId();
            }

            // Method 2: Direct storage + direct filecache insertion (no scanning!)
            if ($groupFolderId > 0) {
                $storage = $this->folderStorageManager->getStorage($groupFolderId);
                $cache = $storage->getCache();

                // Check if file already exists in cache
                $existingId = $cache->getId($fileName);
                if ($existingId !== -1) {
                    return (int) $existingId;
                }

                // Write the file to storage
                $storage->file_put_contents($fileName, '');

                // Get file metadata for direct cache insertion (INSTEAD of scanning)
                $mtime = time();
                $stat = $storage->stat($fileName);
                $size = $stat['size'] ?? 0;
                $mimetype = 'application/octet-stream';

                // Get parent folder ID from cache
                $parentId = $cache->getId('');
                if ($parentId === -1) {
                    $parentId = $folderId;
                }

                // Direct insert into filecache (much faster than scanning!)
                $this->db->beginTransaction();
                try {
                    $query = $this->db->getQueryBuilder();
                    $query->insert('filecache')
                        ->values([
                            'path' => $query->createNamedParameter($fileName),
                            'path_hash' => $query->createNamedParameter(md5($fileName)),
                            'parent' => $query->createNamedParameter($parentId, \PDO::PARAM_INT),
                            'name' => $query->createNamedParameter($fileName),
                            'mimetype' => $query->createNamedParameter(
                                $this->getMimetypeId($mimetype)
                            ),
                            'mimepart' => $query->createNamedParameter(
                                $this->getMimetypeId('application')
                            ),
                            'size' => $query->createNamedParameter($size, \PDO::PARAM_INT),
                            'mtime' => $query->createNamedParameter($mtime, \PDO::PARAM_INT),
                            'storage_mtime' => $query->createNamedParameter($mtime, \PDO::PARAM_INT),
                            'storage' => $query->createNamedParameter(
                                $this->getStorageNumericId($storage),
                                \PDO::PARAM_INT
                            ),
                            'permissions' => $query->createNamedParameter(27, \PDO::PARAM_INT), // PERMISSION_ALL
                            'etag' => $query->createNamedParameter(md5($mtime . $size)),
                        ]);
                    $query->executeStatement();

                    $fileId = $this->db->lastInsertId('*PREFIX*filecache');
                    $this->db->commit();

                    return (int) $fileId;
                } catch (\Throwable $e) {
                    $this->db->rollBack();
                    error_log("Direct filecache insert failed: " . $e->getMessage());

                    // Fallback: try to get ID from cache (might have been created by race condition)
                    $existingId = $cache->getId($fileName);
                    if ($existingId !== -1) {
                        return (int) $existingId;
                    }
                }
            }

            // If all methods fail, return 0 (project can still be created without whiteboard)
            error_log("ProjectService::createWhiteboardFile - All methods failed for folder $folderName");
            return 0;

        } catch (Throwable $e) {
            error_log("Failed to create whiteboard file: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get mimetype ID from mimetypes table, create if not exists
     */
    private function getMimetypeId(string $mimetype): int
    {
        $query = $this->db->getQueryBuilder();
        $query->select('id')
            ->from('mimetypes')
            ->where($query->expr()->eq('mimetype', $query->createNamedParameter($mimetype)));

        $result = $query->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        if ($row) {
            return (int) $row['id'];
        }

        // Insert new mimetype
        $insert = $this->db->getQueryBuilder();
        $insert->insert('mimetypes')
            ->values(['mimetype' => $insert->createNamedParameter($mimetype)]);
        $insert->executeStatement();

        return (int) $this->db->lastInsertId('*PREFIX*mimetypes');
    }

    /**
     * Get numeric storage ID for filecache
     */
    private function getStorageNumericId($storage): int
    {
        $storageId = $storage->getId();

        $query = $this->db->getQueryBuilder();
        $query->select('numeric_id')
            ->from('storages')
            ->where($query->expr()->eq('id', $query->createNamedParameter($storageId)));

        $result = $query->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return $row ? (int) $row['numeric_id'] : 0;
    }
}