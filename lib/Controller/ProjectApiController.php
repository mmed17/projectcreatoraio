<?php
namespace OCA\Projectcreatoraio\Controller;

use OCA\ProjectCreatorAIO\Service\ProjectService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\NoCSRFRequired;
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
        private IGroupManager $iGroupManager
    ) {
        parent::__construct($appName, $request);
        $this->request = $request;
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return bool
     */
    public function get(int $projectId)
    {
        return $this->projectMapper->find($projectId);
    }

    /**
     * Create a new project
     * @NoCSRFRequired
     */
    public function create(
        string $name,
        string $number,
        int $type,
        array $members,
        string $groupId = '',
        string $description = '',
        ?string $date_start = null,
        ?string $date_end = null,
    ): DataResponse {

        try {
            $project = $this->projectService->createProject(
                $name,
                $number,
                $type,
                $members,
                $description,
                $groupId,
                $date_start,
                $date_end
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


    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return DataResponse
     */
    public function list(): DataResponse
    {
        $currentUser = $this->userSession->getUser();
        $isAdmin = $this->iGroupManager->isInGroup($currentUser->getUID(), 'admin');
        if ($isAdmin) {
            $results = $this->projectMapper->list();
        } else {
            $results = $this->projectMapper->findByUserId($currentUser->getUID());
        }
        return new DataResponse($results);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return DataResponse
     */
    public function getProjectFiles(int $projectId): DataResponse
    {
        $files = $this->projectService->getProjectFiles($projectId);
        return new DataResponse(['files' => $files]);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return DataResponse
     */
    public function getProjectByCircleId(string $circleId): DataResponse
    {
        $project = $this->projectMapper->findByCircleId($circleId);
        return new DataResponse([
            'project' => $project
        ]);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return DataResponse
     */
    public function getByBoardId(int $boardId): DataResponse
    {
        $project = $this->projectMapper->findByBoardId($boardId);
        if ($project === null) {
            throw new OCSNotFoundException("Project not found for board $boardId");
        }
        return new DataResponse($project);
    }

    /**
     * Get all projects for a specific user.
     *
     * @NoCSRFRequired
     * @AdminRequired
     *
     * @param string $userId The user ID to fetch projects for.
     * @return DataResponse
     * @throws OCSForbiddenException if the current user is not an admin
     * @throws OCSNotFoundException if the specified user does not exist
     */
    public function listByUser(string $userId): DataResponse
    {
        $projects = $this->projectMapper->findByUserId($userId);
        return new DataResponse($projects);
    }

    /**
     * Update project details
     *
     * @NoCSRFRequired
     * @AdminRequired
     *
     * @param int $id The Project ID (from route)
     * * // Project Details
     * @param string|null $name
     * @param string|null $number
     * @param int|null $type
     * @param string|null $description
     * * // Client Info
     * @param string|null $client_name
     * @param string|null $client_role
     * @param string|null $client_phone
     * @param string|null $client_email
     * @param string|null $client_address
     * * // Location Info
     * @param string|null $loc_street
     * @param string|null $loc_city
     * @param string|null $loc_zip
     * @param string|null $external_ref
     * * // Timeline
     * @param string|null $date_start
     * @param string|null $date_end
     * @param int|null $status
     * * @return DataResponse
     */
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
        ?string $date_start = null,
        ?string $date_end = null,
        ?int $status = null
    ): DataResponse {
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
            $date_start,
            $date_end,
            $status,
        );
        return new DataResponse($updatedProject);
    }
}