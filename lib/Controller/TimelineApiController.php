<?php

namespace OCA\ProjectCreatorAIO\Controller;

use DateTime;
use OCA\Organization\Db\UserMapper as OrganizationUserMapper;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Db\TimelineItemMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class TimelineApiController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private TimelineItemMapper $mapper,
        private ProjectMapper $projectMapper,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
        private OrganizationUserMapper $organizationUserMapper,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function index(int $projectId): JSONResponse
    {
        try {
            $project = $this->requireProject($projectId);
            $this->assertCanAccessProject($project);

            $items = $this->mapper->findByProject($projectId);
            return new JSONResponse($items);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(
        int $projectId,
        string $label,
        ?string $startDate = null,
        ?string $endDate = null,
        string $color = '#3b82f6',
    ): JSONResponse {
        try {
            $project = $this->requireProject($projectId);
            $this->assertCanManageTimelineProject($project);

            $item = $this->mapper->createItem(
                $projectId,
                $label,
                $startDate,
                $endDate,
                $color,
                $this->mapper->getNextOrderIndex($projectId),
            );

            return new JSONResponse($item, Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function update(
        int $projectId,
        int $id,
        ?string $label = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $color = null,
        ?int $orderIndex = null,
    ): JSONResponse {
        try {
            $project = $this->requireProject($projectId);
            $this->assertCanManageTimelineProject($project);

            $item = $this->mapper->find($id);
            if ($item === null) {
                return new JSONResponse(['error' => 'Timeline item not found'], Http::STATUS_NOT_FOUND);
            }

            if ($item->getProjectId() !== $projectId) {
                return new JSONResponse(['error' => 'Item does not belong to this project'], Http::STATUS_FORBIDDEN);
            }

            $isSystemItem = (string) ($item->getSystemKey() ?? '') !== '';

            if (!$isSystemItem && $label !== null) {
                $item->setLabel($label);
            }
            if ($startDate !== null) {
                $item->setStartDate(new DateTime($startDate));
            }
            if ($endDate !== null) {
                $item->setEndDate(new DateTime($endDate));
            }
            if ($color !== null) {
                $item->setColor($color);
            }
            if (!$isSystemItem && $orderIndex !== null) {
                if (in_array($orderIndex, [0, 1, 2], true)) {
                    return new JSONResponse(['error' => 'Order index is reserved for system timeline items'], Http::STATUS_FORBIDDEN);
                }
                $item->setOrderIndex($orderIndex);
            }

            return new JSONResponse($this->mapper->updateItem($item));
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $projectId, int $id): JSONResponse
    {
        try {
            $project = $this->requireProject($projectId);
            $this->assertCanManageTimelineProject($project);

            $item = $this->mapper->find($id);
            if ($item === null) {
                return new JSONResponse(['error' => 'Timeline item not found'], Http::STATUS_NOT_FOUND);
            }

            if ($item->getProjectId() !== $projectId) {
                return new JSONResponse(['error' => 'Item does not belong to this project'], Http::STATUS_FORBIDDEN);
            }

            if ((string) ($item->getSystemKey() ?? '') !== '') {
                return new JSONResponse(['error' => 'System timeline items cannot be deleted'], Http::STATUS_FORBIDDEN);
            }

            $this->mapper->delete($item);
            return new JSONResponse(['success' => true]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function requireProject(int $projectId): Project
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException('Project not found');
        }

        return $project;
    }

    private function assertCanAccessProject(Project $project): void
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        if ($this->groupManager->isAdmin($currentUser->getUID())) {
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

        if (!$this->isProjectGroupMember($currentUser->getUID(), (string) $project->getProjectGroupGid())) {
            throw new OCSNotFoundException('Project not found');
        }
    }

    private function assertCanManageTimelineProject(Project $project): void
    {
        $this->assertCanAccessProject($project);
    }

    private function isProjectGroupMember(string $userId, string $projectGroupGid): bool
    {
        if ($projectGroupGid === '') {
            return false;
        }

        return $this->groupManager->isInGroup($userId, $projectGroupGid);
    }

    private function errorResponse(\Throwable $e): JSONResponse
    {
        if ($e instanceof OCSForbiddenException) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }

        if ($e instanceof OCSNotFoundException) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
}
