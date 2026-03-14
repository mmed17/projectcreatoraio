<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\BackgroundJob;

use OCA\ProjectCreatorAIO\Service\FileProcessingPipelineService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class ProcessPendingFileProcessingJob extends TimedJob
{
    public function __construct(
        ITimeFactory $time,
        private readonly FileProcessingPipelineService $pipelineService,
    ) {
        parent::__construct($time);

        $this->setInterval(60 * 5);
        $this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
    }

    protected function run($argument): void
    {
        $this->pipelineService->processPending(5);
    }
}
