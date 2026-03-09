<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\BackgroundJob;

use OCA\ProjectCreatorAIO\Service\ProjectRetentionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class PurgeArchivedProjectsJob extends TimedJob
{
	public function __construct(
		ITimeFactory $time,
		private readonly ProjectRetentionService $projectRetentionService,
	) {
		parent::__construct($time);

		$this->setInterval(60 * 60 * 24);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void
	{
		$this->projectRetentionService->purgeArchivedProjects(false);
	}
}
