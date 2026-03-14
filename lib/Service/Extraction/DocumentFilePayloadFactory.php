<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service\Extraction;

use OCP\AppFramework\OCS\OCSException;
use OCP\Files\File;

class DocumentFilePayloadFactory
{
    public function fromFile(File $file): DocumentFilePayload
    {
        $content = $file->getContent();
        if (!is_string($content)) {
            throw new OCSException('Unable to read the file for OCR processing.', 500);
        }

        return new DocumentFilePayload(
            (string) $file->getName(),
            strtolower(trim((string) $file->getMimeType())),
            $content,
        );
    }
}
