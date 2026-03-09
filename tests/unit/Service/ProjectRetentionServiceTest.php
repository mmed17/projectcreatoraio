<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\Service;

use OCA\Deck\Db\BoardMapper;
use OCA\ProjectCreatorAIO\Db\PrivateFolderLinkMapper;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectActivityEventMapper;
use OCA\ProjectCreatorAIO\Db\ProjectDigestCursorMapper;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Db\ProjectNoteMapper;
use OCA\ProjectCreatorAIO\Db\TimelineItemMapper;
use OCA\ProjectCreatorAIO\Service\ProjectRetentionService;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProjectRetentionServiceTest extends TestCase
{
	public function testPurgeArchivedProjectsDryRunDoesNotDelete(): void
	{
		$project = new Project();
		$project->setId(42);
		$project->setName('Alpha');
		$project->setArchivedAt(new \DateTime('2024-01-01 00:00:00'));

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->expects($this->once())
			->method('findArchivedBefore')
			->willReturn([$project]);
		$projectMapper->expects($this->never())->method('deleteProject');

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('info');

		$service = new ProjectRetentionService(
			$projectMapper,
			$this->createMock(ProjectNoteMapper::class),
			$this->createMock(TimelineItemMapper::class),
			$this->createMock(PrivateFolderLinkMapper::class),
			$this->createMock(ProjectActivityEventMapper::class),
			$this->createMock(ProjectDigestCursorMapper::class),
			$this->createMock(BoardMapper::class),
			$this->createMock(FolderManager::class),
			$this->createMock(FolderStorageManager::class),
			$this->createMock(IRootFolder::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(IDBConnection::class),
			$logger,
		);

		$result = $service->purgeArchivedProjects(true, 10);

		$this->assertSame([
			'processed' => 1,
			'purged' => 0,
			'dryRun' => true,
		], $result);
	}
}
