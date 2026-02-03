<?php

namespace OCA\ProjectCreatorAIO\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCA\ProjectCreatorAIO\Db\TimelineItemMapper;
use OCA\ProjectCreatorAIO\Db\TimelineItem;
use DateTime;

class TimelineApiController extends Controller
{
    private TimelineItemMapper $mapper;
    private ?string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        TimelineItemMapper $mapper,
        ?string $userId
    ) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->userId = $userId;
    }

    /**
     * @NoAdminRequired
     * @param int $projectId
     * @return JSONResponse
     */
    public function index(int $projectId): JSONResponse
    {
        try {
            $items = $this->mapper->findByProject($projectId);
            return new JSONResponse($items);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     * @param int $projectId
     * @param string $label
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string $color
     * @return JSONResponse
     */
    public function create(
        int $projectId,
        string $label,
        ?string $startDate = null,
        ?string $endDate = null,
        string $color = '#3b82f6'
    ): JSONResponse {
        try {
            $orderIndex = $this->mapper->getNextOrderIndex($projectId);

            $item = $this->mapper->createItem(
                $projectId,
                $label,
                $startDate,
                $endDate,
                $color,
                $orderIndex
            );

            return new JSONResponse($item, Http::STATUS_CREATED);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     * @param int $projectId
     * @param int $id
     * @param string|null $label
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $color
     * @param int|null $orderIndex
     * @return JSONResponse
     */
    public function update(
        int $projectId,
        int $id,
        ?string $label = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $color = null,
        ?int $orderIndex = null
    ): JSONResponse {
        try {
            $item = $this->mapper->find($id);

            if ($item === null) {
                return new JSONResponse(
                    ['error' => 'Timeline item not found'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Verify item belongs to project
            if ($item->getProjectId() !== $projectId) {
                return new JSONResponse(
                    ['error' => 'Item does not belong to this project'],
                    Http::STATUS_FORBIDDEN
                );
            }

            if ($label !== null) {
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
            if ($orderIndex !== null) {
                $item->setOrderIndex($orderIndex);
            }

            $updated = $this->mapper->updateItem($item);
            return new JSONResponse($updated);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     * @param int $projectId
     * @param int $id
     * @return JSONResponse
     */
    public function destroy(int $projectId, int $id): JSONResponse
    {
        try {
            $item = $this->mapper->find($id);

            if ($item === null) {
                return new JSONResponse(
                    ['error' => 'Timeline item not found'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Verify item belongs to project
            if ($item->getProjectId() !== $projectId) {
                return new JSONResponse(
                    ['error' => 'Item does not belong to this project'],
                    Http::STATUS_FORBIDDEN
                );
            }

            $this->mapper->delete($item);
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
