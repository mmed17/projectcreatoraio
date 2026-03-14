<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use DateTime;
use OCA\ProjectCreatorAIO\Db\OrganizationDocumentTypeMapper;
use OCA\ProjectCreatorAIO\Db\OrganizationDocumentType;
use OCA\ProjectCreatorAIO\Db\ProjectFileProcessing;
use OCA\ProjectCreatorAIO\Db\ProjectFileProcessingMapper;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;
use Throwable;

class FileProcessingPipelineService
{
    public function __construct(
        private readonly ProjectFileProcessingMapper $processingMapper,
        private readonly OrganizationDocumentTypeMapper $documentTypeMapper,
        private readonly IRootFolder $rootFolder,
        private readonly DocumentExtractionService $documentExtractionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function processPending(int $limit = 5): int
    {
        $processed = 0;
        foreach ($this->processingMapper->findProcessable($limit) as $record) {
            $this->processRecord($record);
            $processed++;
        }

        return $processed;
    }

    public function processRecord(ProjectFileProcessing $record): ProjectFileProcessing
    {
        $record = $this->markExtractionStarted($record);

        try {
            $file = $this->resolveFileNode((int) $record->getFileId());
            $documentType = $this->resolveDocumentType($record);

            try {
                $extractionResult = $this->documentExtractionService->extractFromFile($file, $documentType->getFields());
            } catch (Throwable $e) {
                return $this->markExtractionFailed($record, $e);
            }

            $record->setFileName((string) $file->getName());
            $record->setMimeType((string) $file->getMimeType());

            return $this->markExtractionCompleted($record, $extractionResult->getFields());
        } catch (Throwable $e) {
            return $this->markPipelineFailed($record, $e);
        }
    }

    public function markFileAsStale(int $fileId): void
    {
        foreach ($this->processingMapper->findByFileId($fileId) as $record) {
            $record->setOcrStatus('stale');
            $record->setErrorMessage(null);
            $record->setProcessedAt(null);
            $this->processingMapper->saveRecord($record);
        }
    }

    private function resolveFileNode(int $fileId): File
    {
        $nodes = $this->rootFolder->getById($fileId);
        foreach ($nodes as $node) {
            if ($node instanceof File) {
                return $node;
            }
        }

        throw new OCSException('OCR source file no longer exists.', 404);
    }

    private function resolveDocumentType(ProjectFileProcessing $record): OrganizationDocumentType
    {
        $documentTypeId = (int) ($record->getDocumentTypeId() ?? 0);
        $documentType = $this->documentTypeMapper->find($documentTypeId);
        if ($documentType === null) {
            throw new OCSNotFoundException('Document type definition not found.');
        }

        return $documentType;
    }

    private function markExtractionStarted(ProjectFileProcessing $record): ProjectFileProcessing
    {
        $this->setStatuses($record, 'processing');
        $record->setErrorMessage(null);
        $record->setProcessedAt(null);

        return $this->processingMapper->saveRecord($record);
    }

    /**
     * @param array<string, array<string, mixed>> $parsed
     */
    private function markExtractionCompleted(ProjectFileProcessing $record, array $parsed): ProjectFileProcessing
    {
        $record->setExtractedJson(json_encode($parsed, JSON_UNESCAPED_SLASHES));
        $this->setStatuses($record, 'done');
        $record->setErrorMessage(null);
        $record->setProcessedAt(new DateTime());

        return $this->processingMapper->saveRecord($record);
    }

    private function markExtractionFailed(ProjectFileProcessing $record, Throwable $e): ProjectFileProcessing
    {
        $this->setStatuses($record, 'failed');

        return $this->storeFailure($record, 'Project document extraction failed', $e);
    }

    private function markPipelineFailed(ProjectFileProcessing $record, Throwable $e): ProjectFileProcessing
    {
        $this->setStatuses($record, 'failed');

        return $this->storeFailure($record, 'Project document extraction pipeline failed', $e);
    }

    private function setStatuses(ProjectFileProcessing $record, string $status): void
    {
        $record->setOcrStatus($status);
    }

    private function storeFailure(ProjectFileProcessing $record, string $logMessage, Throwable $e): ProjectFileProcessing
    {
        $record->setErrorMessage($this->truncateMessage($e->getMessage()));
        $record->setProcessedAt(new DateTime());
        $failedRecord = $this->processingMapper->saveRecord($record);

        $this->logger->warning($logMessage, [
            'app' => 'projectcreatoraio',
            'fileId' => $record->getFileId(),
            'projectId' => $record->getProjectId(),
            'exception' => $e,
        ]);

        return $failedRecord;
    }

    private function truncateMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'OCR processing failed.';
        }

        return substr($message, 0, 2000);
    }
}
