<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\Listener;

use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Listener\WhiteboardWrittenListener;
use OCA\ProjectCreatorAIO\Service\ProjectActivityService;
use OCA\ProjectCreatorAIO\Service\ProjectNotificationService;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class WhiteboardWrittenListenerTest extends TestCase {
	public function testHandleNotifiesWhenProjectWhiteboardIsWritten(): void {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(321);

		$project = new Project();
		$project->setId(42);
		$project->setName('Alpha');

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->expects($this->once())
			->method('findByWhiteBoardId')
			->with(321)
			->willReturn($project);

		$actor = $this->createMock(IUser::class);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($actor);

		$projectNotificationService = $this->createMock(ProjectNotificationService::class);
		$projectNotificationService->expects($this->once())
			->method('notifyWhiteboardUpdated')
			->with($project, $actor);
		$projectActivityService = $this->createMock(ProjectActivityService::class);
		$projectActivityService->expects($this->once())
			->method('recordWhiteboardUpdated')
			->with($project, $actor);

		$listener = new WhiteboardWrittenListener($projectMapper, $projectNotificationService, $projectActivityService, $userSession);
		$listener->handle(new NodeWrittenEvent($file));
	}

	public function testHandleSkipsUnknownFiles(): void {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(321);

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('findByWhiteBoardId')->willReturn(null);

		$userSession = $this->createMock(IUserSession::class);
		$projectNotificationService = $this->createMock(ProjectNotificationService::class);
		$projectNotificationService->expects($this->never())->method('notifyWhiteboardUpdated');
		$projectActivityService = $this->createMock(ProjectActivityService::class);
		$projectActivityService->expects($this->never())->method('recordWhiteboardUpdated');

		$listener = new WhiteboardWrittenListener($projectMapper, $projectNotificationService, $projectActivityService, $userSession);
		$listener->handle(new NodeWrittenEvent($file));
	}
}
