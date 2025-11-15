<?php
namespace OCA\Projectcreatoraio\Controller;

use OCA\ProjectCreatorAIO\Service\ProjectService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\NoCSRFRequired;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Http\OCS\OCSForbiddenException; 
use OCP\IUserSession;
use OCP\IRequest;
use Throwable;

class ProjectApiController extends Controller {
    public const APP_ID = 'projectcreatoraio';

    public function __construct(
        string $appName,
        IRequest $request,
        protected IUserSession   $userSession,
        protected ProjectMapper  $projectMapper,
        protected ProjectService $projectService,
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
    public function get(int $projectId) {
        return $this->projectMapper->find($projectId);
    }

     /**
     * Create a new project
     * @NoCSRFRequired
     */
    public function create(
        string $name,
        string $number,
        int    $type,
        array  $members,
        string $groupId = '',
        string $address = '',
        string $description = '',
    ): DataResponse {

        try {
            $project = $this->projectService->createProject(
                $name, 
                $number, 
                $type, 
                $members, 
                $address, 
                $description,
                $groupId
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
    public function list(): DataResponse {
        $currentUser = $this->userSession->getUser();
        $results = $this->projectMapper->findByUserId($currentUser->getUID());
        return new DataResponse($results);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return DataResponse
     */
    public function getProjectFiles(int $projectId): DataResponse {
        $files = $this->projectService->getProjectFiles($projectId);
        return new DataResponse(['files' => $files]);
    }
    
    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return bool
     */
    public function updateProjectStatus(int $projectId, int $status): void {
        $this->projectMapper->updateProjectStatus($projectId, $status);
    }
    
    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     *  @return DataResponse
     */
    public function getProjectByCircleId(string $circleId): DataResponse {
        $project = $this->projectMapper->findByCircleId($circleId);
        return new DataResponse([
            'project' => $project
        ]);
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
    public function listByUser(string $userId): DataResponse {
        $projects = $this->projectMapper->findByUserId($userId);
        return new DataResponse($projects);
    }
}