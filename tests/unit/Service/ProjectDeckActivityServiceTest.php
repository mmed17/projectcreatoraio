<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\Service;

use DateInterval;
use DateTime;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Service\ProjectDeckActivityService;
use OCA\ProjectCreatorAIO\Service\ProjectNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProjectDeckActivityServiceTest extends TestCase {
	public function testRecordCardMoveByBoardIdReturnsStaleProjectToActive(): void {
		$project = new Project();
		$project->setId(42);
		$project->setBoardId('15');
		$project->setStatus(ProjectDeckActivityService::STATUS_STALE);
		$project->setStaleNotifiedAt(new DateTime('-1 day'));

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('findByBoardId')->with(15)->willReturn($project);
		$projectMapper->expects($this->once())->method('updateProjectDetails')->with($project);

		$notificationService = $this->createMock(ProjectNotificationService::class);
		$logger = $this->createMock(LoggerInterface::class);

		$service = new ProjectDeckActivityService($projectMapper, $notificationService, $logger);
		$service->recordCardMoveByBoardId(15, new DateTime('2026-03-06 12:00:00'));

		$this->assertSame(ProjectDeckActivityService::STATUS_ACTIVE, $project->getStatus());
		$this->assertNull($project->getStaleNotifiedAt());
		$this->assertInstanceOf(DateTime::class, $project->getLastDeckMoveAt());
	}

	public function testProcessStaleProjectsMarksOldProjectsStaleOnce(): void {
		$project = new Project();
		$project->setId(42);
		$project->setBoardId('15');
		$project->setName('Alpha');
		$project->setStatus(ProjectDeckActivityService::STATUS_ACTIVE);
		$project->setCreatedAt(new DateTime('2025-10-01 09:00:00'));

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('listDeckTrackedProjects')->willReturn([$project]);
		$projectMapper->expects($this->once())->method('updateProjectDetails')->with($project);

		$notificationService = $this->createMock(ProjectNotificationService::class);
		$notificationService->expects($this->once())->method('notifyDeckStale')->with($project);

		$logger = $this->createMock(LoggerInterface::class);

		$service = new ProjectDeckActivityService($projectMapper, $notificationService, $logger);
		$service->processStaleProjects(new DateTime('2026-03-06 12:00:00'));

		$this->assertSame(ProjectDeckActivityService::STATUS_STALE, $project->getStatus());
		$this->assertInstanceOf(DateTime::class, $project->getStaleNotifiedAt());
	}

	public function testProcessStaleProjectsSkipsAlreadyNotifiedStaleProjects(): void {
		$project = new Project();
		$project->setId(42);
		$project->setBoardId('15');
		$project->setStatus(ProjectDeckActivityService::STATUS_STALE);
		$project->setCreatedAt(new DateTime('2025-10-01 09:00:00'));
		$project->setStaleNotifiedAt(new DateTime('2026-03-01 10:00:00'));

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('listDeckTrackedProjects')->willReturn([$project]);
		$projectMapper->expects($this->never())->method('updateProjectDetails');

		$notificationService = $this->createMock(ProjectNotificationService::class);
		$notificationService->expects($this->never())->method('notifyDeckStale');

		$logger = $this->createMock(LoggerInterface::class);

		$service = new ProjectDeckActivityService($projectMapper, $notificationService, $logger);
		$service->processStaleProjects(new DateTime('2026-03-06 12:00:00'));
	}
}
