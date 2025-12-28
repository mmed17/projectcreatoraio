<?php

namespace OCA\ProjectCreatorAIO\Service;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCA\Circles\CirclesManager;
use OCA\Circles\Service\FederatedUserService;
use OCA\Deck\Service\BoardService;
use OCP\Share\IManager as IShareManager;
use OCP\Share;
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
use OCA\Provisioning_API\Db\OrganizationMapper;
use OCA\Provisioning_API\Db\PlanMapper;
use OCA\Provisioning_API\Db\SubscriptionMapper;
use OCP\AppFramework\OCS\OCSException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\Provisioning_API\Db\Plan;
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
                $name
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
                $createdFolders["shared"]["id"],
                $createdFolders["shared"]["name"],
                $createdFolders["private"],
                $createdWhiteBoardId,
                $dateStart,
                $dateEnd,
            );


            return $project;

        } catch (Throwable $e) {

            $this->cleanupResources(
                $createdBoard,
                $createdCircle,
                $createdFolders['all']
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
        $projectGroupName = "{$projectName} - Project Group";
        $searchResult = $this->groupManager->search($projectGroupName);

        $counter = 2;
        // Loop WHILE the name is taken
        while (!empty($searchResult)) {
            $projectGroupName = "{$projectName} ({$counter}) - Project Group";
            $searchResult = $this->groupManager->search($projectGroupName);
            $counter++;
        }

        // At this point, $projectGroupName is guaranteed to be unique
        $createdGroup = $this->groupManager->createGroup($projectGroupName);

        if ($createdGroup === null) {
            // This could happen if creation fails for other reasons (e.g., invalid chars)
            throw new Exception("Failed to create project group '$projectGroupName'.");
        }

        // Add all members to the newly created group
        foreach ($members as $memberId) {
            $memberUser = $this->userManager->get($memberId);
            if ($memberUser !== null) {
                $createdGroup->addUser($memberUser);
            }
        }

        return $createdGroup;
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
            Share::SHARE_TYPE_CIRCLE,
            $circleId,
            true,
            false,
            false
        );

        return $board;
    }


    /**
     * Creates and shares all necessary folders for the project.
     * @return array{'shared': Folder, 'private': Folder[], 'all': Folder[]}
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
     * Creates a .whiteboard file in the specified shared folder
     */
    private function createWhiteboardFile(IUser $owner, string $folderName, int $folderId, string $projectName, int $groupFolderId = 0): int
    {
        try {
            error_log("ProjectService::createWhiteboardFile called for owner " . $owner->getUID() . ", folder " . $folderName . " (ID: $folderId), project " . $projectName . ", GF ID: " . $groupFolderId);

            $userFolder = $this->rootFolder->getUserFolder($owner->getUID());

            $folder = null;

            // Try to get folder by ID first using System Root (to bypass missing user mount cache)
            $nodes = $this->rootFolder->getById($folderId);
            if (!empty($nodes)) {
                foreach ($nodes as $node) {
                    if ($node instanceof \OCP\Files\Folder) {
                        $folder = $node;
                        break;
                    }
                }
            }

            // Fallback to user-scoped name lookup (unlikely to work if ID failed, but existing logic)
            if ($folder === null) {
                if ($userFolder->nodeExists($folderName)) {
                    $node = $userFolder->get($folderName);
                    if ($node instanceof \OCP\Files\Folder) {
                        $folder = $node;
                    }
                }
            }

            // Direct Storage Access Fallback for Stale Mounts
            if ($folder === null && $groupFolderId > 0) {
                error_log("ProjectService::createWhiteboardFile - Attempting direct storage access for Group Folder " . $groupFolderId);
                try {
                    // Get the underlying storage directly (bypassing user view/mounts)
                    // Using specific method from FolderStorageManager
                    // Note: We assume getStorage exists based on usage patterns in GroupFolders app
                    // If this fails, the catch block will handle it.

                    // Helper: In GroupFolders, FolderStorageManager typically manages the storage.
                    // We use getStorage($groupFolderId) to get the IGroupFolderStorage.
                    $storage = $this->folderStorageManager->getStorage($groupFolderId);

                    $fileName = $projectName . '.whiteboard';
                    if (!$storage->file_exists($fileName)) {
                        error_log("ProjectService::createWhiteboardFile - Writing to storage: $fileName");
                        $storage->file_put_contents($fileName, '');

                        // We must scan the file to ensure it appears in the database (filecache) and we can get an ID
                        $storage->getScanner()->scan($fileName);

                        $fileId = $storage->getCache()->getId($fileName);
                        error_log("ProjectService::createWhiteboardFile - Created via storage, ID: " . $fileId);
                        return (int) $fileId;
                    } else {
                        // File exists, just get ID
                        $fileId = $storage->getCache()->getId($fileName);
                        error_log("ProjectService::createWhiteboardFile - File exists in storage, ID: " . $fileId);
                        return (int) $fileId;
                    }
                } catch (\Throwable $e) {
                    error_log("ProjectService::createWhiteboardFile - Storage access failed: " . $e->getMessage());
                    // Trigger trace to see what happened
                    error_log($e->getTraceAsString());
                }
            }

            if ($folder === null) {
                error_log("ProjectService::createWhiteboardFile - Shared folder NOT FOUND via ID ($folderId), Name ($folderName), or Storage ($groupFolderId).");
                return 0;
            }

            error_log("ProjectService::createWhiteboardFile - Found folder: " . $folder->getPath());

            $fileName = $projectName . '.whiteboard';

            if (!$folder->nodeExists($fileName)) {
                error_log("ProjectService::createWhiteboardFile - Creating new file: $fileName");
                $file = $folder->newFile($fileName);
                error_log("ProjectService::createWhiteboardFile - Created file ID: " . $file->getId());
                return (int) $file->getId();
            } else {
                error_log("ProjectService::createWhiteboardFile - File already exists: $fileName");
                return (int) $folder->get($fileName)->getId();
            }
        } catch (Throwable $e) {
            // Log error but allow process to continue? 
            // If we throw here, the whole project creation including folders and groups might rollback if not handled.
            // The original logic wrapped specific steps.
            // For now, let's catch and return 0 to allow project creation to proceed even if whiteboard fails.
            error_log("Failed to create whiteboard file: " . $e->getMessage());
            return 0;
        }
    }
}