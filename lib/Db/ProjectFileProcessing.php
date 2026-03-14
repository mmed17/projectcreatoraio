<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class ProjectFileProcessing extends Entity implements JsonSerializable
{
    protected ?int $projectId = null;
    protected ?int $organizationId = null;
    protected ?int $fileId = null;
    protected ?string $filePath = null;
    protected ?string $fileName = null;
    protected ?string $mimeType = null;
    protected ?int $documentTypeId = null;
    protected ?string $ocrStatus = null;
    protected ?string $extractedJson = null;
    protected ?string $errorMessage = null;
    protected ?DateTime $processedAt = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function __construct()
    {
        $this->addType('projectId', Types::INTEGER);
        $this->addType('organizationId', Types::INTEGER);
        $this->addType('fileId', Types::INTEGER);
        $this->addType('filePath', Types::STRING);
        $this->addType('fileName', Types::STRING);
        $this->addType('mimeType', Types::STRING);
        $this->addType('documentTypeId', Types::INTEGER);
        $this->addType('ocrStatus', Types::STRING);
        $this->addType('extractedJson', Types::TEXT);
        $this->addType('errorMessage', Types::TEXT);
        $this->addType('processedAt', Types::DATETIME);
        $this->addType('createdAt', Types::DATETIME);
        $this->addType('updatedAt', Types::DATETIME);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'project_id' => $this->projectId,
            'organization_id' => $this->organizationId,
            'file_id' => $this->fileId,
            'file_path' => $this->filePath,
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'document_type_id' => $this->documentTypeId,
            'ocr_status' => $this->ocrStatus,
            'extracted' => $this->decodeExtractedJson(),
            'error_message' => $this->errorMessage,
            'processed_at' => $this->processedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    private function decodeExtractedJson(): array
    {
        $raw = trim((string) $this->extractedJson);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
