<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\BackgroundJob;

use OCA\ProjectCreatorAIO\BackgroundJob\PurgeArchivedProjectsJob;
use OCA\ProjectCreatorAIO\Service\ProjectRetentionService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

final class PurgeArchivedProjectsJobTest extends TestCase
{
	public function testRunPurgesArchivedProjects(): void
	{
		$timeFactory = $this->createMock(ITimeFactory::class);
		$service = $this->createMock(ProjectRetentionService::class);
		$service->expects($this->once())->method('purgeArchivedProjects')->with(false);

		$job = new class($timeFactory, $service) extends PurgeArchivedProjectsJob {
			public function runPublic(): void
			{
				$this->run(null);
			}
		};

		$job->runPublic();
	}
}
