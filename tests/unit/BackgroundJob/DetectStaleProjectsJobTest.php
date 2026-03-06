<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\BackgroundJob;

use OCA\ProjectCreatorAIO\BackgroundJob\DetectStaleProjectsJob;
use OCA\ProjectCreatorAIO\Service\ProjectDeckActivityService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

final class DetectStaleProjectsJobTest extends TestCase {
	public function testRunProcessesStaleProjects(): void {
		$timeFactory = $this->createMock(ITimeFactory::class);
		$service = $this->createMock(ProjectDeckActivityService::class);
		$service->expects($this->once())->method('processStaleProjects');

		$job = new class($timeFactory, $service) extends DetectStaleProjectsJob {
			public function runPublic(): void {
				$this->run(null);
			}
		};

		$job->runPublic();
	}
}
