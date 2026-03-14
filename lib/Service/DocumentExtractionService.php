<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use OCA\ProjectCreatorAIO\Service\Extraction\DocumentExtractionResult;
use OCA\ProjectCreatorAIO\Service\Extraction\DocumentFilePayloadFactory;
use OCP\Files\File;

class DocumentExtractionService
{
    public function __construct(
        private readonly DocumentFilePayloadFactory $payloadFactory,
        private readonly PythonDocumentExtractionClient $documentExtractionClient,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $fieldDefinitions
     */
    public function extractFromFile(File $file, array $fieldDefinitions): DocumentExtractionResult
    {
        return $this->documentExtractionClient->extract($this->payloadFactory->fromFile($file), $fieldDefinitions);
    }
}
