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
use OCA\Provisioning_API\Db\Plan;
use OCP\IDBConnection;
use OCP\IUserManager;

class ProjectService {
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
    ) {}

    /**
     * The main public method to create a complete project.
     * It orchestrates all the necessary steps and handles rollbacks.
     */
    public function createProject(
        string $name,
        string $number,
        int    $type,
        array  $members,
        string $address,
        string $description
    ): Project {
        $createdCircle = null;
        $createdBoard  = null;
        $createdFolders = [];
        
        try {
            $owner = $this->userSession->getUser();

            $organization = $this->organizationMapper->findByUserId($owner->getUID());
            $subscription = $this->subscriptionMapper->findByOrganizationId($organization->getId());

            $plan  = $this->planMapper->find($subscription->getPlanId());
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

            $project = $this->projectMapper->createProject(
                $organization,
                $name, 
                $number, 
                $type, 
                $address, 
                $description,
                $owner->getUID(),
                $createdCircle->getSingleId(),
                $createdBoard->getId(),
                $createdFolders["shared"]["id"],
                $createdFolders["shared"]["name"],
                $createdFolders["private"],
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
    private function createCircleForProject(string $projectName, array $members, IUser $owner): Circle {
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

    private function createGroupForMembers(array $members, string $projectName): IGroup {
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
        foreach($members as $memberId) {
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
    private function createBoardForProject(string $projectName, IUser $owner, string $circleId): Board {
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
        string $projectName, array $members, IUser $owner, IGroup $group, Plan $plan
    ): array {
        $ownerFolder = $this->rootFolder->getUserFolder($owner->getUID());
        
        // Create shared folders 
        $sharedFolderName = $this->getUniqueFolderName(
            $projectName, 
            'Shared Files', 
            $ownerFolder
        );
        
        $groupFolderId = $this->folderManager->createFolder($sharedFolderName);

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
            'shared' => [
                'id' => $groupFolderId,
                'name' => $sharedFolderName
            ],
            'private' => $privateFolders, 
        ];
    }

    private function getUniqueFolderName(string $projectName, string $suffix, Folder $folder): string {
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
        ?Board  $board, 
        ?Circle $circle, 
        ?array  $folders
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
    public function getProjectFiles(int $projectId): array {
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
    
    public function findProjectByBoard(int $boardId) {
        return $this->projectMapper->findByBoardId($boardId);
    }

}