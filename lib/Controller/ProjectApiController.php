<?php
namespace OCA\Projectcreatoraio\Controller;

use OCA\ProjectCreatorAIO\Service\ProjectService;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\Organization\Db\UserMapper as OrganizationUserMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Http\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IRequest;
use Throwable;

class ProjectApiController extends Controller
{
    public const APP_ID = 'projectcreatoraio';

    public function __construct(
        string $appName,
        IRequest $request,
        protected IUserSession $userSession,
        protected ProjectMapper $projectMapper,
        protected ProjectService $projectService,
        private IGroupManager $iGroupManager,
        private OrganizationUserMapper $organizationUserMapper,
    ) {
        parent::__construct($appName, $request);
        $this->request = $request;
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function get(int $projectId)
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);
        return $project;
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function create(
        string $name,
        string $number,
        int $type,
        array $members = [],
        string $groupId = '',
        ?int $organizationId = null,
        string $description = '',
    ): DataResponse {

        if ($organizationId === null && $groupId !== '' && ctype_digit($groupId)) {
            $organizationId = (int) $groupId;
        }

        try {
            $project = $this->projectService->createProject(
                $name,
                $number,
                $type,
                $members,
                $description,
                $organizationId,
            );

            return new DataResponse([
                'message' => 'Project created successfully',
                'projectId' => $project->getId(),
            ]);

        } catch (Throwable $e) {
            return new DataResponse([
                'message' => 'Failed to create project: ' . $e->getMessage()
            ], 500);
        }
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function searchUsers(
        string $search = '',
        ?int $organizationId = null,
        int $limit = 25,
        int $offset = 0,
    ): DataResponse {
        $users = $this->projectService->searchUsers($search, $organizationId, $limit, $offset);
        return new DataResponse(['users' => $users]);
    }


    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function list(): DataResponse
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        $isAdmin = $this->iGroupManager->isAdmin($currentUser->getUID());
        if ($isAdmin) {
            $results = $this->projectMapper->list();
        } else {
            $membership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
            if ($membership === null) {
                throw new OCSForbiddenException('You are not assigned to an organization');
            }

            if ($membership['role'] === 'admin') {
                $results = $this->projectMapper->findByOrganizationId((int) $membership['organization_id']);
            } else {
                $results = $this->projectMapper->findByUserIdAndOrganizationId(
                    $currentUser->getUID(),
                    (int) $membership['organization_id'],
                );
            }
        }

        return new DataResponse($results);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function context(): DataResponse
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        $userId = $currentUser->getUID();
        $isGlobalAdmin = $this->iGroupManager->isAdmin($userId);
        $membership = $this->organizationUserMapper->getOrganizationMembership($userId);

        return new DataResponse([
            'userId' => $userId,
            'isGlobalAdmin' => $isGlobalAdmin,
            'organizationRole' => $membership['role'] ?? null,
            'organizationId' => isset($membership['organization_id']) ? (int) $membership['organization_id'] : null,
        ]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function getProjectFiles(int $projectId): DataResponse
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);
        $files = $this->projectService->getProjectFiles($projectId);
        return new DataResponse(['files' => $files]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function getByBoardId(int $boardId): DataResponse
    {
        $project = $this->projectMapper->findByBoardId($boardId);
        if ($project === null) {
            throw new OCSNotFoundException("Project not found for board $boardId");
        }

        $this->assertCanAccessProject($project);
        return new DataResponse($project);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function listByUser(string $userId): DataResponse
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        $isGlobalAdmin = $this->iGroupManager->isAdmin($currentUser->getUID());
        if (!$isGlobalAdmin) {
            $currentMembership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
            if ($currentMembership === null) {
                throw new OCSForbiddenException('You are not assigned to an organization');
            }

            if ($currentMembership['role'] !== 'admin') {
                if ($currentUser->getUID() !== $userId) {
                    throw new OCSForbiddenException('Members can only view their own projects');
                }

                $projects = $this->projectMapper->findByUserIdAndOrganizationId(
                    $userId,
                    (int) $currentMembership['organization_id'],
                );

                return new DataResponse($projects);
            }

            $targetMembership = $this->organizationUserMapper->getOrganizationMembership($userId);
            if ($targetMembership === null || (int) $targetMembership['organization_id'] !== (int) $currentMembership['organization_id']) {
                throw new OCSNotFoundException('User not found in your organization');
            }

            $projects = $this->projectMapper->findByUserIdAndOrganizationId(
                $userId,
                (int) $currentMembership['organization_id'],
            );

            return new DataResponse($projects);
        }

        $projects = $this->projectMapper->findByUserId($userId);
        return new DataResponse($projects);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function update(
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
        ?int $status = null
    ): DataResponse {
        $existingProject = $this->projectMapper->find($id);
        if ($existingProject === null) {
            throw new OCSNotFoundException("Project with ID $id not found");
        }

        $this->assertCanManageProject($existingProject);

        $updatedProject = $this->projectService->updateProjectDetails(
            $id,
            $name,
            $number,
            $type,
            $description,
            $client_name,
            $client_role,
            $client_phone,
            $client_email,
            $client_address,
            $loc_street,
            $loc_city,
            $loc_zip,
            $external_ref,
            $status,
        );
        return new DataResponse($updatedProject);
    }

    private function assertCanAccessProject(Project $project): void
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        if ($this->iGroupManager->isAdmin($currentUser->getUID())) {
            return;
        }

        $membership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
        if ($membership === null) {
            throw new OCSForbiddenException('You are not assigned to an organization');
        }

        if ((int) $membership['organization_id'] !== (int) $project->getOrganizationId()) {
            throw new OCSNotFoundException('Project not found');
        }

        if ($membership['role'] === 'admin') {
            return;
        }

        if (!$this->isProjectGroupMember($currentUser->getUID(), $project->getProjectGroupGid())) {
            throw new OCSNotFoundException('Project not found');
        }
    }

    private function assertCanManageProject(Project $project): void
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        if ($this->iGroupManager->isAdmin($currentUser->getUID())) {
            return;
        }

        $membership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
        if ($membership === null || $membership['role'] !== 'admin') {
            throw new OCSForbiddenException('Only organization admins can manage projects');
        }

        if ((int) $membership['organization_id'] !== (int) $project->getOrganizationId()) {
            throw new OCSNotFoundException('Project not found');
        }
    }

    private function isProjectGroupMember(string $userId, string $projectGroupGid): bool
    {
        if ($projectGroupGid === '') {
            return false;
        }

        return $this->iGroupManager->isInGroup($userId, $projectGroupGid);
    }
}
