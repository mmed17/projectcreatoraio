<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\BackgroundJob;

use OCA\ProjectCreatorAIO\BackgroundJob\ShareProjectWhiteboardInTalkJob;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Service\ProjectTalkIntegrationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareProjectWhiteboardInTalkJobTest extends TestCase
{
    public function testRunSharesWhiteboardWhenPayloadMatchesProject(): void
    {
        $timeFactory = $this->createMock(ITimeFactory::class);
        $projectMapper = $this->createMock(ProjectMapper::class);
        $userManager = $this->createMock(IUserManager::class);
        $talkService = $this->createMock(ProjectTalkIntegrationService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $project = new Project();
        $project->setTalkConversationToken('room-token');
        $project->setWhiteBoardId('42');

        $user = $this->createMock(IUser::class);

        $projectMapper->expects($this->once())
            ->method('find')
            ->with(99)
            ->willReturn($project);
        $userManager->expects($this->once())
            ->method('get')
            ->with('owner')
            ->willReturn($user);
        $talkService->expects($this->once())
            ->method('shareFileInConversation')
            ->with('room-token', 42, $user);

        $job = new class($timeFactory, $projectMapper, $userManager, $talkService, $logger) extends ShareProjectWhiteboardInTalkJob {
            public function runPublic(array $argument): void
            {
                $this->run($argument);
            }
        };

        $job->runPublic([
            'projectId' => 99,
            'conversationToken' => 'room-token',
            'whiteboardFileId' => 42,
            'actorUserId' => 'owner',
        ]);
    }

    public function testRunSkipsWhenPayloadDoesNotMatchProjectState(): void
    {
        $timeFactory = $this->createMock(ITimeFactory::class);
        $projectMapper = $this->createMock(ProjectMapper::class);
        $userManager = $this->createMock(IUserManager::class);
        $talkService = $this->createMock(ProjectTalkIntegrationService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $project = new Project();
        $project->setTalkConversationToken('different-token');
        $project->setWhiteBoardId('42');

        $projectMapper->expects($this->once())
            ->method('find')
            ->with(99)
            ->willReturn($project);
        $userManager->expects($this->never())->method('get');
        $talkService->expects($this->never())->method('shareFileInConversation');

        $job = new class($timeFactory, $projectMapper, $userManager, $talkService, $logger) extends ShareProjectWhiteboardInTalkJob {
            public function runPublic(array $argument): void
            {
                $this->run($argument);
            }
        };

        $job->runPublic([
            'projectId' => 99,
            'conversationToken' => 'room-token',
            'whiteboardFileId' => 42,
            'actorUserId' => 'owner',
        ]);
    }
}
