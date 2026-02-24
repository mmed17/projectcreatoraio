<?php

namespace OCA\ProjectCreatorAIO\Service;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Db\ProjectNoteMapper;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCA\Deck\Service\BoardService;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Constants;
use OCP\IUserSession;
use OCP\Files\Folder;
use OCP\IUser;
use OCA\Deck\Db\Board;
use OCA\ProjectCreatorAIO\Service\DeckDefaultCardsService;
use Throwable;
use Exception;
use OCA\Organization\Db\OrganizationMapper;
use OCA\Organization\Db\Organization;
use OCA\Organization\Db\UserMapper as OrganizationUserMapper;
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
use OCP\Files\File;
use OCA\Deck\Db\ChangeHelper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ProjectService
{
    private const CV_FIELD_OBJECT_OWNERSHIP = 'cv_object_ownership';
    private const CV_FIELD_TRACE_OWNERSHIP = 'cv_trace_ownership';
    private const CV_FIELD_BUILDING_TYPE = 'cv_building_type';
    private const CV_FIELD_AVP_LOCATION = 'cv_avp_location';

    /** @var array<string, array<int, int>>|null */
    private ?array $cardVisibilityShowMap = null;

    public function __construct(
        protected IUserSession $userSession,
        protected IShareManager $shareManager,
        protected BoardService $boardService,
        private readonly DeckDefaultCardsService $deckDefaultCardsService,
        protected IRootFolder $rootFolder,
        protected ProjectMapper $projectMapper,
        protected ProjectNoteMapper $noteMapper,
        protected FileTreeService $fileTreeService,
        protected OrganizationMapper $organizationMapper,
        protected OrganizationUserMapper $organizationUserMapper,
        protected SubscriptionMapper $subscriptionMapper,
        protected PlanMapper $planMapper,
        protected IGroupManager $groupManager,
        protected FolderManager $folderManager,
        protected IDBConnection $db,
        protected IUserManager $userManager,
        private readonly FolderStorageManager $folderStorageManager,
        private readonly ChangeHelper $changeHelper,
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
        ?int $organizationId = null,
        ?string $clientName = null,
        ?string $clientRole = null,
        ?string $clientPhone = null,
        ?string $clientEmail = null,
        ?string $clientAddress = null,
        ?string $locStreet = null,
        ?string $locCity = null,
        ?string $locZip = null,
        ?int $requiredPreparationWeeks = null,
    ): Project {

        $createdBoard = null;
        $createdGroup = null;
        $createdFolders = [];

        try {
            $owner = $this->userSession->getUser();
            if ($owner === null) {
                throw new OCSException('You must be logged in to create a project.');
            }

            $organization = $this->resolveOrganizationForCurrentUser($owner->getUID(), $organizationId, false);
            $this->assertUsersBelongToOrganization(array_merge($members, [$owner->getUID()]), $organization->getId());

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

            $group = $this->createGroupForMembers(
                array_merge($members, [$owner->getUID()])
            );
            $createdGroup = $group;

            $createdBoard = $this->createBoardForProject(
                $name,
                $owner,
                $group->getGID()
            );

            $createdFolders = $this->createFoldersForProject(
                $name,
                $members,
                $owner,
                $group,
                $plan
            );

            $createdWhiteBoardId = $this->createWhiteboardFile(
                $createdFolders['shared']['name'],
                $name,
                $createdFolders['shared']['group_folder_id']
            );

            if ($createdWhiteBoardId <= 0) {
                throw new OCSException('Whiteboard file creation failed.');
            }

            $whiteBoardId = (string) $createdWhiteBoardId;

            $project = $this->projectMapper->createProject(
                $organization,
                $name,
                $number,
                $type,
                $description,
                $owner->getUID(),
                $createdBoard->getId(),
                $group->getGID(),
                $createdFolders['shared']['id'],
                $createdFolders['shared']['name'],
                $createdFolders['private'],
                $whiteBoardId,
                $requiredPreparationWeeks,
                $clientName,
                $clientRole,
                $clientPhone,
                $clientEmail,
                $clientAddress,
                $locStreet,
                $locCity,
                $locZip,
            );

            $this->deckDefaultCardsService->seedForProjectType(
                $type,
                $createdBoard,
                $owner,
            );

            // On creation, conditional sets start hidden by default until the
            // questionnaire is explicitly saved.
            $this->applyCardVisibilityToDeckCards(
                $project,
                $this->extractCardVisibilityAnswers($project),
            );

            return $project;

        } catch (Throwable $e) {

            $this->cleanupResources(
                $createdBoard,
                $createdGroup,
                $createdFolders['all'] ?? [],
                $createdFolders['shared']['group_folder_id'] ?? null,
            );

            throw $e;
        }
    }

    /**
     * Search users constrained to one organization.
     * Admins can specify any organization ID, non-admins are restricted to their own organization.
     *
     * @return array<int, array{id: string, user: string, label: string, displayName: string, subname: string}>
     */
    public function searchUsers(
        string $search,
        ?int $organizationId = null,
        int $limit = 25,
        int $offset = 0,
    ): array {
        $search = trim($search);
        if ($search == '') {
            return [];
        }

        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new OCSException('You must be logged in to search users.');
        }

        $organization = $this->resolveOrganizationForCurrentUser($user->getUID(), $organizationId, false);

        $qb = $this->db->getQueryBuilder();
        $qb->select('user_uid')
            ->from('organization_members')
            ->where(
                $qb->expr()->eq('organization_id', $qb->createNamedParameter($organization->getId(), \PDO::PARAM_INT))
            )
            ->andWhere(
                $qb->expr()->iLike('user_uid', $qb->createNamedParameter('%' . $search . '%'))
            )
            ->orderBy('user_uid', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->setFirstResult(max(0, $offset));

        $result = $qb->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();

        $users = [];
        foreach ($rows as $row) {
            $uid = (string) ($row['user_uid'] ?? '');
            if ($uid === '') {
                continue;
            }

            $nextcloudUser = $this->userManager->get($uid);
            if ($nextcloudUser === null) {
                continue;
            }

            $displayName = $nextcloudUser->getDisplayName() ?: $uid;
            $email = $nextcloudUser->getEMailAddress() ?: '';

            $users[] = [
                'id' => $uid,
                'user' => $uid,
                'label' => $displayName,
                'displayName' => $displayName,
                'subname' => $email,
            ];
        }

        return $users;
    }

    /**
     * @return array<int, array{id: string, displayName: string, email: string, isOwner: bool}>
     */
    public function getProjectMembers(int $projectId): array
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSException("Project with ID $projectId not found", 404);
        }

        $ownerId = trim((string) ($project->getOwnerId() ?? ''));
        $groupGid = trim((string) ($project->getProjectGroupGid() ?? ''));

        $memberIds = $groupGid !== ''
            ? $this->getMemberUserIdsByGroup($groupGid)
            : [];

        if ($ownerId !== '' && !in_array($ownerId, $memberIds, true)) {
            $memberIds[] = $ownerId;
        }

        $members = [];
        foreach ($memberIds as $memberId) {
            $user = $this->userManager->get($memberId);
            if ($user === null) {
                continue;
            }

            $members[] = $this->formatProjectMember($user, $ownerId);
        }

        usort($members, static function (array $a, array $b): int {
            if ($a['isOwner'] !== $b['isOwner']) {
                return $a['isOwner'] ? -1 : 1;
            }

            return strcasecmp($a['displayName'], $b['displayName']);
        });

        return $members;
    }

    /**
     * Adds an organization member to the project group and provisions a private folder link.
     *
     * @return array{added: bool, alreadyMember: bool, member: array{id: string, displayName: string, email: string, isOwner: bool}}
     */
    public function addMemberToProject(int $projectId, string $userId): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new OCSException('A user ID is required to add a project member.', 400);
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSException("Project with ID $projectId not found", 404);
        }

        $groupGid = trim((string) ($project->getProjectGroupGid() ?? ''));
        if ($groupGid === '') {
            throw new OCSException('This project cannot accept members because the member group is not configured.', 500);
        }

        $memberOrganization = $this->organizationUserMapper->getOrganizationMembership($userId);
        if ($memberOrganization === null || (int) $memberOrganization['organization_id'] !== (int) $project->getOrganizationId()) {
            throw new OCSException('User does not belong to this organization.', 403);
        }

        $user = $this->userManager->get($userId);
        if ($user === null) {
            throw new OCSException(sprintf('User "%s" does not exist.', $userId), 404);
        }

        $alreadyMember = $this->groupManager->isInGroup($userId, $groupGid);
        $group = null;
        $addedToGroup = false;

        if (!$alreadyMember) {
            $group = $this->groupManager->get($groupGid);
            if ($group === null) {
                throw new OCSException('Project member group not found.', 404);
            }

            $group->addUser($user);
            $addedToGroup = true;
        }

        try {
            $this->ensurePrivateFolderForMember($project, $userId);
        } catch (Throwable $e) {
            if ($addedToGroup && $group !== null) {
                $group->removeUser($user);
            }

            throw $e;
        }

        $ownerId = trim((string) ($project->getOwnerId() ?? ''));

        return [
            'added' => !$alreadyMember,
            'alreadyMember' => $alreadyMember,
            'member' => $this->formatProjectMember($user, $ownerId),
        ];
    }

    /**
     * @return string[]
     */
    private function getMemberUserIdsByGroup(string $groupGid): array
    {
        if ($groupGid === '') {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('uid')
            ->from('group_user')
            ->where(
                $qb->expr()->eq('gid', $qb->createNamedParameter($groupGid))
            )
            ->orderBy('uid', 'ASC');

        $result = $qb->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();

        $memberIds = [];
        $seen = [];
        foreach ($rows as $row) {
            $uid = (string) ($row['uid'] ?? '');
            if ($uid !== '' && !isset($seen[$uid])) {
                $seen[$uid] = true;
                $memberIds[] = $uid;
            }
        }

        return $memberIds;
    }

    private function ensurePrivateFolderForMember(Project $project, string $userId): void
    {
        $projectId = (int) ($project->getId() ?? 0);
        if ($projectId <= 0) {
            throw new OCSException('Invalid project while creating private folder.', 500);
        }

        $existingLink = $this->projectMapper->findPrivateFolderForUser($projectId, $userId);
        if ($existingLink !== null) {
            return;
        }

        try {
            $userFolder = $this->rootFolder->getUserFolder($userId);
            $projectName = trim((string) ($project->getName() ?? ''));
            if ($projectName === '') {
                $projectName = 'Project';
            }

            $privateFolderName = $this->getUniqueFolderName($projectName, 'Private Files', $userFolder);
            $privateFolder = $userFolder->newFolder($privateFolderName);

            $this->projectMapper->createPrivateFolderLink(
                $projectId,
                $userId,
                (int) $privateFolder->getId(),
                $privateFolder->getPath(),
            );
        } catch (Throwable $e) {
            throw new OCSException('Unable to provision private files for invited member.', 500);
        }
    }

    /**
     * @return array{id: string, displayName: string, email: string, isOwner: bool}
     */
    private function formatProjectMember(IUser $user, string $ownerId): array
    {
        $userId = $user->getUID();

        return [
            'id' => $userId,
            'displayName' => $user->getDisplayName() ?: $userId,
            'email' => $user->getEMailAddress() ?: '',
            'isOwner' => $ownerId !== '' && $userId === $ownerId,
        ];
    }

    private function resolveOrganizationForCurrentUser(
        string $userId,
        ?int $organizationId = null,
        bool $mustBeOrgAdmin = true,
    ): Organization
    {
        $isAdmin = $this->groupManager->isInGroup($userId, 'admin');

        if ($isAdmin) {
            if ($organizationId === null) {
                throw new OCSException('An organization ID is required for admins.');
            }

            $organization = $this->organizationMapper->find($organizationId);
            if ($organization === null) {
                throw new OCSException('The selected organization does not exist.');
            }

            return $organization;
        }

        $membership = $this->organizationUserMapper->getOrganizationMembership($userId);
        if ($membership === null) {
            throw new OCSException('No organization is assigned to your user account.');
        }

        if ($mustBeOrgAdmin && $membership['role'] !== 'admin') {
            throw new OCSException('Only organization admins can create projects.');
        }

        $resolvedOrganizationId = (int) $membership['organization_id'];

        if ($organizationId !== null && $organizationId !== $resolvedOrganizationId) {
            throw new OCSException('You can only manage projects for your own organization.');
        }

        $organization = $this->organizationMapper->find($resolvedOrganizationId);
        if ($organization === null) {
            throw new OCSException('No organization is assigned to your user account.');
        }

        return $organization;
    }

    /**
     * @param string[] $userIds
     */
    private function assertUsersBelongToOrganization(array $userIds, int $organizationId): void
    {
        foreach ($userIds as $userId) {
            $membership = $this->organizationUserMapper->getOrganizationMembership((string) $userId);
            if ($membership === null || (int) $membership['organization_id'] !== $organizationId) {
                throw new OCSException(sprintf(
                    'User "%s" does not belong to the selected organization.',
                    (string) $userId,
                ));
            }
        }
    }

    private function createGroupForMembers(array $members): IGroup
    {
        $projectGroupName = $this->generateProjectGroupId();

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

    private function generateProjectGroupId(): string
    {
        $prefix = 'proj-';

        while (true) {
            $groupId = $prefix . bin2hex(random_bytes(8));
            if (!$this->groupManager->groupExists($groupId)) {
                return $groupId;
            }
        }
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
    private function createBoardForProject(string $projectName, IUser $owner, string $projectGroupGid): Board
    {
        $color = strtoupper(sprintf('%06X', random_int(0, 0xFFFFFF)));
        $board = $this->boardService->create("{$projectName} - Main Board", $owner->getUID(), $color);

        $this->boardService->addAcl(
            $board->getId(),
            IShare::TYPE_GROUP,
            $projectGroupGid,
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
        $allCreatedFolders = [];

        // Create shared folders
        $sharedFolderName = $this->getUniqueFolderName(
            $projectName,
            'Shared Files',
            $ownerFolder
        );

        $groupFolderId = $this->folderManager->createFolder($sharedFolderName);
        $folder = $this->folderManager->getFolder($groupFolderId);
        $storageId = $folder->storageId;
        $rootId = $folder->rootId;

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
            'all' => $allCreatedFolders,
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
        ?IGroup $group,
        ?array $folders,
        ?int $groupFolderId = null,
    ): void {
        if ($groupFolderId !== null && $groupFolderId > 0) {
            try {
                $groupFolder = $this->folderManager->getFolder($groupFolderId);
                if ($groupFolder !== null) {
                    $this->folderStorageManager->deleteStoragesForFolder($groupFolder);
                    $this->folderManager->removeFolder($groupFolderId);
                }
            } catch (Throwable $e) {
                error_log('Failed to cleanup group folder: ' . $e->getMessage());
            }
        }

        if (!empty($folders)) {
            foreach ($folders as $folder) {
                if ($folder !== null && $folder->isDeletable()) {
                    $folder->delete();
                }
            }
        }

        // if ($board !== null) {
        //     $this->boardService->delete($board->getId());
        // }

        if ($group !== null) {
            $group->delete();
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

    /**
     * Returns project notes stored as files.
     *
     * public note:  <project shared folder>/Public Notes/public-note.md
     * private note: <user private project folder>/private-note.md (per-user)
     *
     * @return array{public: string, private: string, private_available: bool}
     */
    public function getProjectNotes(int $projectId): array
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSException('Authentication required');
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSException("Project with ID $projectId not found", 404);
        }

        $userFolder = $this->rootFolder->getUserFolder($currentUser->getUID());

        $projectRoot = null;
        try {
            $node = $userFolder->get($project->getFolderPath());
            if ($node instanceof Folder) {
                $projectRoot = $node;
            }
        } catch (NotFoundException $e) {
            $projectRoot = null;
        }

        $public = $projectRoot instanceof Folder
            ? $this->readOrCreateNoteFile($projectRoot, 'Public Notes', 'public-note.md')
            : '';

        $privateFolder = $this->resolvePrivateFolderForCurrentUser($userFolder, $projectId, $currentUser->getUID());
        if ($privateFolder === null) {
            return [
                'public' => $public,
                'private' => '',
                'private_available' => false,
            ];
        }

        // Legacy fallback: older versions stored "private" note in the shared folder.
        $privateNoteFileName = 'private-note.md';
        if (!$privateFolder->nodeExists($privateNoteFileName) && $projectRoot instanceof Folder) {
            $legacy = $this->readLegacySharedPrivateNote($projectRoot);
            if ($legacy !== '') {
                $this->writeOrCreateFile($privateFolder, $privateNoteFileName, $legacy);
            }
        }

        $private = $this->readOrCreateFile($privateFolder, $privateNoteFileName);

        return [
            'public' => $public,
            'private' => $private,
            'private_available' => true,
        ];
    }

    /**
     * Updates project notes.
     *
     * @return array{public: string, private: string, private_available: bool}
     */
    public function updateProjectNotes(int $projectId, ?string $publicNote = null, ?string $privateNote = null): array
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSException('Authentication required');
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSException("Project with ID $projectId not found", 404);
        }

        $userFolder = $this->rootFolder->getUserFolder($currentUser->getUID());

        if ($publicNote !== null) {
            $projectRoot = $userFolder->get($project->getFolderPath());
            if (!$projectRoot instanceof Folder) {
                throw new OCSException('Project shared folder not found', 404);
            }
            $this->writeOrCreateNoteFile($projectRoot, 'Public Notes', 'public-note.md', $publicNote);
        }

        if ($privateNote !== null) {
            $privateFolder = $this->resolvePrivateFolderForCurrentUser($userFolder, $projectId, $currentUser->getUID());
            if ($privateFolder === null) {
                throw new OCSException('Private note is not available for this user', 403);
            }
            $this->writeOrCreateFile($privateFolder, 'private-note.md', $privateNote);
        }

        return $this->getProjectNotes($projectId);
    }

    /**
     * Check if user has a private folder for this project
     */
    public function hasPrivateFolderForUser(int $projectId, string $userId): bool
    {
        $link = $this->projectMapper->findPrivateFolderForUser($projectId, $userId);
        return $link !== null;
    }

    /**
     * Get list of all notes for a project
     * Returns public notes and private notes owned by the user
     * 
     * @return array{public: array, private: array, private_available: bool}
     */
    public function getProjectNotesList(int $projectId, string $userId): array
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSException("Project with ID $projectId not found", 404);
        }

        // Get all public notes
        $publicNotes = $this->noteMapper->findPublicByProject($projectId);

        // Get private notes for this user
        $privateNotes = $this->noteMapper->findPrivateByProjectAndUser($projectId, $userId);

        // Check if user has private folder available
        $hasPrivateFolder = $this->hasPrivateFolderForUser($projectId, $userId);

        return [
            'public' => array_map(fn($note) => $note->jsonSerialize(), $publicNotes),
            'private' => array_map(fn($note) => $note->jsonSerialize(), $privateNotes),
            'private_available' => $hasPrivateFolder,
        ];
    }

    private function resolvePrivateFolderForCurrentUser(Folder $userFolder, int $projectId, string $userId): ?Folder
    {
        $link = $this->projectMapper->findPrivateFolderForUser($projectId, $userId);
        if ($link === null) {
            return null;
        }

        $folderId = (int) ($link->getFolderId() ?? 0);
        if ($folderId <= 0) {
            return null;
        }

        $node = $userFolder->getFirstNodeById($folderId);
        return $node instanceof Folder ? $node : null;
    }

    private function readLegacySharedPrivateNote(Folder $projectRoot): string
    {
        $legacyFolderName = 'Private Notes';
        $legacyFileName = 'private-note.md';

        if (!$projectRoot->nodeExists($legacyFolderName)) {
            return '';
        }

        $legacyFolder = $projectRoot->get($legacyFolderName);
        if (!$legacyFolder instanceof Folder) {
            return '';
        }

        if (!$legacyFolder->nodeExists($legacyFileName)) {
            return '';
        }

        $node = $legacyFolder->get($legacyFileName);
        if (!$node instanceof File) {
            return '';
        }

        $content = $node->getContent();
        return is_string($content) ? $content : '';
    }

    private function readOrCreateNoteFile(Folder $projectRoot, string $notesFolderName, string $noteFileName): string
    {
        $notesFolder = null;
        if ($projectRoot->nodeExists($notesFolderName)) {
            $notesFolder = $projectRoot->get($notesFolderName);
            if (!$notesFolder instanceof Folder) {
                throw new OCSException(sprintf('%s exists but is not a folder', $notesFolderName), 500);
            }
        } else {
            $notesFolder = $projectRoot->newFolder($notesFolderName);
        }

        if (!$notesFolder->nodeExists($noteFileName)) {
            $noteFile = $notesFolder->newFile($noteFileName);
            $noteFile->putContent('');
            return '';
        }

        $node = $notesFolder->get($noteFileName);
        if (!$node instanceof File) {
            throw new OCSException(sprintf('%s exists but is not a file', $noteFileName), 500);
        }

        $content = $node->getContent();
        return is_string($content) ? $content : '';
    }

    private function writeOrCreateNoteFile(Folder $projectRoot, string $notesFolderName, string $noteFileName, string $content): void
    {
        $notesFolder = null;
        if ($projectRoot->nodeExists($notesFolderName)) {
            $notesFolder = $projectRoot->get($notesFolderName);
            if (!$notesFolder instanceof Folder) {
                throw new OCSException(sprintf('%s exists but is not a folder', $notesFolderName), 500);
            }
        } else {
            $notesFolder = $projectRoot->newFolder($notesFolderName);
        }

        $this->writeOrCreateFile($notesFolder, $noteFileName, $content);
    }

    private function readOrCreateFile(Folder $folder, string $fileName): string
    {
        if (!$folder->nodeExists($fileName)) {
            $file = $folder->newFile($fileName);
            $file->putContent('');
            return '';
        }

        $node = $folder->get($fileName);
        if (!$node instanceof File) {
            throw new OCSException(sprintf('%s exists but is not a file', $fileName), 500);
        }

        $content = $node->getContent();
        return is_string($content) ? $content : '';
    }

    private function writeOrCreateFile(Folder $folder, string $fileName, string $content): void
    {
        if (!$folder->nodeExists($fileName)) {
            $file = $folder->newFile($fileName);
            $file->putContent($content);
            return;
        }

        $node = $folder->get($fileName);
        if (!$node instanceof File) {
            throw new OCSException(sprintf('%s exists but is not a file', $fileName), 500);
        }

        $node->putContent($content);
    }

    public function findProjectByBoard(int $boardId)
    {
        return $this->projectMapper->findByBoardId($boardId);
    }

    /**
     * @return array{
     *   project_id: int,
     *   project_type: int,
     *   questions: array<int, array{field: string, category: string, question: string, options: array<int, array{label: string, show: int}>>>,
     *   answers: array{cv_object_ownership: ?int, cv_trace_ownership: ?int, cv_building_type: ?int, cv_avp_location: ?int},
     *   enabled_sets: int[]
     * }
     */
    public function getProjectCardVisibility(int $projectId): array
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSException("Project with ID $projectId not found", 404);
        }

        $projectType = (int) ($project->getType() ?? -1);
        $answers = $this->extractCardVisibilityAnswers($project);

        return [
            'project_id' => (int) $project->getId(),
            'project_type' => $projectType,
            'questions' => $this->getCardVisibilityQuestions(),
            'answers' => $answers,
            'enabled_sets' => $this->getEnabledCardVisibilitySets($answers),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   project_id: int,
     *   project_type: int,
     *   questions: array<int, array{field: string, category: string, question: string, options: array<int, array{label: string, show: int}>>>,
     *   answers: array{cv_object_ownership: ?int, cv_trace_ownership: ?int, cv_building_type: ?int, cv_avp_location: ?int},
     *   enabled_sets: int[],
     *   deck_cards_updated: int
     * }
     */
    public function updateProjectCardVisibility(int $projectId, array $payload): array
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSException("Project with ID $projectId not found", 404);
        }

        $projectType = (int) ($project->getType() ?? -1);
        if ($projectType !== ProjectTypeDeckDefaults::TYPE_COMBI) {
            throw new OCSException('Form configuration is only available for Combi projects.', 400);
        }

        if (array_key_exists(self::CV_FIELD_OBJECT_OWNERSHIP, $payload)) {
            $project->setCvObjectOwnership(
                $this->normalizeCardVisibilityAnswer($payload[self::CV_FIELD_OBJECT_OWNERSHIP], self::CV_FIELD_OBJECT_OWNERSHIP)
            );
        }

        if (array_key_exists(self::CV_FIELD_TRACE_OWNERSHIP, $payload)) {
            $project->setCvTraceOwnership(
                $this->normalizeCardVisibilityAnswer($payload[self::CV_FIELD_TRACE_OWNERSHIP], self::CV_FIELD_TRACE_OWNERSHIP)
            );
        }

        if (array_key_exists(self::CV_FIELD_BUILDING_TYPE, $payload)) {
            $project->setCvBuildingType(
                $this->normalizeCardVisibilityAnswer($payload[self::CV_FIELD_BUILDING_TYPE], self::CV_FIELD_BUILDING_TYPE)
            );
        }

        if (array_key_exists(self::CV_FIELD_AVP_LOCATION, $payload)) {
            $project->setCvAvpLocation(
                $this->normalizeCardVisibilityAnswer($payload[self::CV_FIELD_AVP_LOCATION], self::CV_FIELD_AVP_LOCATION)
            );
        }

        $project = $this->projectMapper->updateProjectDetails($project);

        $answers = $this->extractCardVisibilityAnswers($project);
        $updatedCount = $this->applyCardVisibilityToDeckCards($project, $answers);

        return [
            'project_id' => (int) $project->getId(),
            'project_type' => $projectType,
            'questions' => $this->getCardVisibilityQuestions(),
            'answers' => $answers,
            'enabled_sets' => $this->getEnabledCardVisibilitySets($answers),
            'deck_cards_updated' => $updatedCount,
        ];
    }

    /**
     * @return array<int, array{field: string, category: string, question: string, options: array<int, array{label: string, value: int, show: int}>>>
     */
    private function getCardVisibilityQuestions(): array
    {
        return [
            [
                'field' => self::CV_FIELD_OBJECT_OWNERSHIP,
                'category' => 'Eigendoms situatie te realiseren object',
                'question' => 'Eigendoms situatie te realiseren object (antwoord met ja op de situatie die van toepassing is).',
                'options' => [
                    [
                        'label' => 'Het object(en) komt op eigen grond te staan en de gevel grenst direct aan gemeentegrond.',
                        'value' => 201,
                        'show' => 0,
                    ],
                    [
                        'label' => 'Het object komt op eigen grond te staan maar de gevel grenst niet direct aan gemeentegrond.',
                        'value' => 202,
                        'show' => 2,
                    ],
                    [
                        'label' => 'Het object komt op eigen grond te staan en de grond wordt overgedragen aan de gemeente.',
                        'value' => 203,
                        'show' => 2,
                    ],
                    [
                        'label' => 'Het object komt op openbare grond te staan.',
                        'value' => 204,
                        'show' => 0,
                    ],
                    [
                        'label' => 'Ik weet het nog niet.',
                        'value' => 205,
                        'show' => 2,
                    ],
                ],
            ],
            [
                'field' => self::CV_FIELD_TRACE_OWNERSHIP,
                'category' => 'Eigendoms situatie kabel en leidingen tracé',
                'question' => 'Eigendoms situatie kabel en leidingen tracé (antwoord met ja op de situatie die van toepassing is).',
                'options' => [
                    [
                        'label' => 'Het vrije tracé komt in eigen grond te liggen.',
                        'value' => 211,
                        'show' => 2,
                    ],
                    [
                        'label' => 'Het vrije tracé komt in openbare grond te liggen.',
                        'value' => 212,
                        'show' => 0,
                    ],
                    [
                        'label' => 'Het vrije tracé komt zowel in eigen grond als in openbare grond te liggen.',
                        'value' => 213,
                        'show' => 2,
                    ],
                    [
                        'label' => 'Ik weet het nog niet.',
                        'value' => 214,
                        'show' => 2,
                    ],
                ],
            ],
            [
                'field' => self::CV_FIELD_BUILDING_TYPE,
                'category' => 'Grondgebonden woningen/ hoogbouw/ bedrijfsunits',
                'question' => 'Grondgebonden woningen/ hoogbouw/ bedrijfsunits (antwoord met ja op de situatie die van toepassing is).',
                'options' => [
                    [
                        'label' => 'U realiseert grondgebonden woningen.',
                        'value' => 121,
                        'show' => 0,
                    ],
                    [
                        'label' => 'U realiseert appartementen.',
                        'value' => 122,
                        'show' => 1,
                    ],
                    [
                        'label' => 'U realiseert zowel grondgebonden woningen als appartementen.',
                        'value' => 123,
                        'show' => 1,
                    ],
                    [
                        'label' => 'U realiseert bedrijfsunits.',
                        'value' => 124,
                        'show' => 0,
                    ],
                ],
            ],
            [
                'field' => self::CV_FIELD_AVP_LOCATION,
                'category' => 'AVP Locatie',
                'question' => 'AVP locatie (antwoord met ja op de situatie die van toepassing is).',
                'options' => [
                    [
                        'label' => 'Ik heb nog niet nagedacht over een mogelijke AVP.',
                        'value' => 221,
                        'show' => 2,
                    ],
                    [
                        'label' => 'Ik realiseer grondgebonden woningen en/of hoogbouw en/of bedrijfsunits. Er is rekening gehouden met een AVP op eigen grond.',
                        'value' => 222,
                        'show' => 0,
                    ],
                    [
                        'label' => 'Ik realiseer grondgebonden woningen en/of hoogbouw en/of bedrijfsunits. Er is geen rekening gehouden met een AVP op eigen grond.',
                        'value' => 223,
                        'show' => 2,
                    ],
                    [
                        'label' => 'Bij hoogbouw is de eis dat het AVP inpandig wordt opgenomen en daar is geen rekening mee gehouden.',
                        'value' => 224,
                        'show' => 2,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function getCardVisibilityShowMap(): array
    {
        if ($this->cardVisibilityShowMap !== null) {
            return $this->cardVisibilityShowMap;
        }

        $map = [];
        foreach ($this->getCardVisibilityQuestions() as $question) {
            $field = (string) ($question['field'] ?? '');
            if ($field === '') {
                continue;
            }

            $options = $question['options'] ?? [];
            if (!is_array($options)) {
                continue;
            }

            foreach ($options as $option) {
                if (!is_array($option)) {
                    continue;
                }

                $value = (int) ($option['value'] ?? 0);
                $show = (int) ($option['show'] ?? 0);
                $map[$field][$value] = $show;
            }
        }

        $this->cardVisibilityShowMap = $map;
        return $map;
    }

    /**
     * @param array<string, array<int, int>> $showMap
     */
    private function resolveCardVisibilityShow(string $field, ?int $value, array $showMap): ?int
    {
        if ($value === null) {
            return null;
        }

        if (in_array($value, [0, 1, 2], true)) {
            return $value;
        }

        return $showMap[$field][$value] ?? 0;
    }

    /**
     * @return array{cv_object_ownership: ?int, cv_trace_ownership: ?int, cv_building_type: ?int, cv_avp_location: ?int}
     */
    private function extractCardVisibilityAnswers(Project $project): array
    {
        return [
            self::CV_FIELD_OBJECT_OWNERSHIP => $this->normalizeCardVisibilityAnswer($project->getCvObjectOwnership(), self::CV_FIELD_OBJECT_OWNERSHIP, true),
            self::CV_FIELD_TRACE_OWNERSHIP => $this->normalizeCardVisibilityAnswer($project->getCvTraceOwnership(), self::CV_FIELD_TRACE_OWNERSHIP, true),
            self::CV_FIELD_BUILDING_TYPE => $this->normalizeCardVisibilityAnswer($project->getCvBuildingType(), self::CV_FIELD_BUILDING_TYPE, true),
            self::CV_FIELD_AVP_LOCATION => $this->normalizeCardVisibilityAnswer($project->getCvAvpLocation(), self::CV_FIELD_AVP_LOCATION, true),
        ];
    }

    /**
     * @param array{cv_object_ownership: ?int, cv_trace_ownership: ?int, cv_building_type: ?int, cv_avp_location: ?int} $answers
     * @return int[]
     */
    private function getEnabledCardVisibilitySets(array $answers): array
    {
        $showMap = $this->getCardVisibilityShowMap();
        $enabled = [];
        foreach ($answers as $field => $answer) {
            $show = $this->resolveCardVisibilityShow((string) $field, $answer, $showMap);
            if ($show === 1) {
                $enabled[1] = true;
            } elseif ($show === 2) {
                $enabled[2] = true;
            }
        }

        $sets = array_keys($enabled);
        sort($sets);
        return array_values($sets);
    }

    /**
     * @param mixed $value
     */
    private function normalizeCardVisibilityAnswer(mixed $value, string $field, bool $allowNull = false): ?int
    {
        if ($value === null) {
            return $allowNull ? null : 0;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return $allowNull ? null : 0;
            }
            if (is_numeric($value)) {
                $value = (int) $value;
            }
        }

        $showMap = $this->getCardVisibilityShowMap();
        $allowed = array_keys($showMap[$field] ?? []);
        $allowed[] = 0;
        $allowed[] = 1;
        $allowed[] = 2;
        $allowed = array_values(array_unique($allowed));

        if (!is_int($value) || !in_array($value, $allowed, true)) {
            throw new OCSException(sprintf('Invalid value for %s. Allowed values: null, 0, 1, 2 or one of the configured option values.', $field), 400);
        }

        return $value;
    }

    /**
     * @param array{cv_object_ownership: ?int, cv_trace_ownership: ?int, cv_building_type: ?int, cv_avp_location: ?int} $answers
     */
    private function applyCardVisibilityToDeckCards(Project $project, array $answers): int
    {
        $boardId = $this->parseIntOrZero($project->getBoardId());
        if ($boardId <= 0) {
            return 0;
        }

        $projectType = (int) ($project->getType() ?? -1);
        if ($projectType !== ProjectTypeDeckDefaults::TYPE_COMBI) {
            return 0;
        }

        $enabledSets = $this->getEnabledCardVisibilitySets($answers);
        $isSet1Enabled = in_array(1, $enabledSets, true);
        $isSet2Enabled = in_array(2, $enabledSets, true);

        $nextPriorityCards = ProjectTypeDeckDefaults::getNextPriorityCards($projectType);
        $processStepCards = ProjectTypeDeckDefaults::getProcessStepCards($projectType);
        $defaultTitles = array_values(array_unique(array_filter(array_map(
            static fn(array $item): string => (string) ($item['title'] ?? ''),
            array_merge($nextPriorityCards, $processStepCards)
        ))));

        $set1Titles = ProjectTypeDeckDefaults::getConditionalSet1Titles();
        $set2Titles = ProjectTypeDeckDefaults::getConditionalSet2Titles();
        $aliasesByCanonical = ProjectTypeDeckDefaults::getCardTitleAliases();

        $allManagedTitles = [];
        $canonicalByNormalizedTitle = [];
        $groupByCanonicalTitle = [];

        foreach ($defaultTitles as $title) {
            $group = 'always';
            if (in_array($title, $set1Titles, true)) {
                $group = 'set1';
            } elseif (in_array($title, $set2Titles, true)) {
                $group = 'set2';
            }

            $groupByCanonicalTitle[$title] = $group;

            $aliases = $aliasesByCanonical[$title] ?? [$title];
            if (!in_array($title, $aliases, true)) {
                $aliases[] = $title;
            }

            foreach ($aliases as $alias) {
                $alias = trim((string) $alias);
                if ($alias === '') {
                    continue;
                }
                $allManagedTitles[] = $alias;
                $canonicalByNormalizedTitle[strtolower($alias)] = $title;
            }
        }

        if ($allManagedTitles === []) {
            return 0;
        }

        $allManagedTitles = array_values(array_unique($allManagedTitles));

        $qb = $this->db->getQueryBuilder();
        $qb->select('c.id', 'c.title', 'c.archived')
            ->from('deck_cards', 'c')
            ->innerJoin('c', 'deck_stacks', 's', 'c.stack_id = s.id')
            ->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('s.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->in('c.title', $qb->createNamedParameter($allManagedTitles, IQueryBuilder::PARAM_STR_ARRAY)));

        $result = $qb->executeQuery();

        $updated = 0;
        while ($row = $result->fetch()) {
            $cardId = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? ''));
            if ($cardId <= 0 || $title === '') {
                continue;
            }

            $canonicalTitle = $canonicalByNormalizedTitle[strtolower($title)] ?? null;
            if ($canonicalTitle === null) {
                continue;
            }

            $group = $groupByCanonicalTitle[$canonicalTitle] ?? 'always';
            $targetArchived = false;
            if ($group === 'set1') {
                $targetArchived = !$isSet1Enabled;
            } elseif ($group === 'set2') {
                $targetArchived = !$isSet2Enabled;
            }

            $isArchived = (bool) ($row['archived'] ?? false);
            if ($isArchived === $targetArchived) {
                continue;
            }

            $update = $this->db->getQueryBuilder();
            $update->update('deck_cards')
                ->set('archived', $update->createNamedParameter($targetArchived, IQueryBuilder::PARAM_BOOL))
                ->where($update->expr()->eq('id', $update->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
                ->executeStatement();

            $this->changeHelper->cardChanged($cardId, true);
            $updated++;
        }
        $result->closeCursor();

        return $updated;
    }

    private function parseIntOrZero(?string $value): int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || !ctype_digit($value)) {
            return 0;
        }

        return (int) $value;
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
        ?int $status = null,
        ?int $required_preparation_weeks = null
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

        if ($status !== null)
            $project->setStatus($status);

        if ($required_preparation_weeks !== null) {
            $project->setRequiredPreparationWeeks(max(0, (int) $required_preparation_weeks));
        }

        // 3. Save via Mapper
        $updatedProject = $this->projectMapper->updateProjectDetails($project);
        return $updatedProject;

    }

    /**
     * Creates a .whiteboard file in the specified shared folder.
     * Optimized to avoid slow file scanning by using direct filecache insertion.
     */
    private function createWhiteboardFile(string $folderName, string $projectName, int $groupFolderId = 0): int
    {
        $fileName = $projectName . '.whiteboard';

        if ($groupFolderId <= 0) {
            throw new Exception("Missing GroupFolder id for shared folder {$folderName}");
        }

        $groupFolder = $this->folderManager->getFolder($groupFolderId);
        if ($groupFolder === null) {
            throw new Exception("GroupFolder {$groupFolderId} not found for shared folder {$folderName}");
        }

        $storage = $this->folderStorageManager->getBaseStorageForFolder(
            $groupFolderId,
            $groupFolder->useSeparateStorage(),
            $groupFolder,
            null,
            false,
            'files'
        );

        $cache = $storage->getCache();
        $existingId = $cache->getId($fileName);
        if ($existingId !== -1) {
            return (int) $existingId;
        }

        $initialWhiteboardContent = '{"elements":[],"scrollToContent":true}';

        if ($storage->file_put_contents($fileName, $initialWhiteboardContent) === false) {
            throw new Exception("Unable to write whiteboard file {$fileName} in GroupFolder {$groupFolderId}");
        }

        $storage->getScanner()->scan($fileName);
        $createdId = $cache->getId($fileName);

        if ($createdId === -1) {
            throw new Exception("Whiteboard file {$fileName} was written but not found in filecache");
        }

        return (int) $createdId;
    }
}
