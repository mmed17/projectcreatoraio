<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Listener;

use OCA\ProjectCreatorAIO\Service\FileProcessingPipelineService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;

/** @template-implements IEventListener<NodeWrittenEvent> */
class FileProcessingWrittenListener implements IEventListener
{
    public function __construct(
        private readonly FileProcessingPipelineService $pipelineService,
    ) {
    }

    public function handle(Event $event): void
    {
        if (!$event instanceof NodeWrittenEvent) {
            return;
        }

        $node = $event->getNode();
        if (!$node instanceof File) {
            return;
        }

        $fileId = (int) $node->getId();
        if ($fileId <= 0) {
            return;
        }

        $this->pipelineService->markFileAsStale($fileId);
    }
}
