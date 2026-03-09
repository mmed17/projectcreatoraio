<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\BackgroundJob;

use OCA\ProjectCreatorAIO\BackgroundJob\SendProjectDigestJob;
use OCA\ProjectCreatorAIO\Service\ProjectDigestService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

final class SendProjectDigestJobTest extends TestCase {
	public function testRunSendsDailyDigests(): void {
		$timeFactory = $this->createMock(ITimeFactory::class);
		$service = $this->createMock(ProjectDigestService::class);
		$service->expects($this->once())->method('sendDailyDigests');

		$job = new class($timeFactory, $service) extends SendProjectDigestJob {
			public function runPublic(): void {
				$this->run(null);
			}
		};

		$job->runPublic();
	}
}
