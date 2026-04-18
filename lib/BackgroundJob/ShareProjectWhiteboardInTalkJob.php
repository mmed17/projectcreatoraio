<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\BackgroundJob;

use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Service\ProjectTalkIntegrationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ShareProjectWhiteboardInTalkJob extends QueuedJob
{
    public function __construct(
        ITimeFactory $time,
        private readonly ProjectMapper $projectMapper,
        private readonly IUserManager $userManager,
        private readonly ProjectTalkIntegrationService $projectTalkIntegrationService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);
    }

    protected function run($argument): void
    {
        $projectId = (int) ($argument['projectId'] ?? 0);
        $conversationToken = trim((string) ($argument['conversationToken'] ?? ''));
        $whiteboardFileId = (int) ($argument['whiteboardFileId'] ?? 0);
        $actorUserId = trim((string) ($argument['actorUserId'] ?? ''));

        if ($projectId <= 0 || $conversationToken === '' || $whiteboardFileId <= 0 || $actorUserId === '') {
            $this->logger->warning('Skipping Talk whiteboard share job due to invalid payload', [
                'argument' => $argument,
            ]);
            return;
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            return;
        }

        if (trim((string) $project->getTalkConversationToken()) !== $conversationToken) {
            return;
        }

        if ((int) ($project->getWhiteBoardId() ?? 0) !== $whiteboardFileId) {
            return;
        }

        $actor = $this->userManager->get($actorUserId);
        if (!$actor instanceof IUser) {
            $this->logger->warning('Skipping Talk whiteboard share job because actor was not found', [
                'projectId' => $projectId,
                'actorUserId' => $actorUserId,
            ]);
            return;
        }

        try {
            $this->projectTalkIntegrationService->shareFileInConversation(
                $conversationToken,
                $whiteboardFileId,
                $actor,
                $project->getFolderPath(),
                $project->getName(),
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Failed queued project whiteboard share in Talk conversation', [
                'projectId' => $projectId,
                'whiteboardFileId' => $whiteboardFileId,
                'conversationToken' => $conversationToken,
                'actorUserId' => $actorUserId,
                'exception' => $e,
            ]);
        }
    }
}
