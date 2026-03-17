<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use DateTime;
use OCA\ProjectCreatorAIO\Db\OrganizationDocumentType;
use OCA\ProjectCreatorAIO\Db\OrganizationDocumentTypeMapper;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectFileProcessing;
use OCA\ProjectCreatorAIO\Db\ProjectFileProcessingMapper;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\Files\File;
use OCP\Files\IRootFolder;

class OcrDocumentService
{
    private const SUPPORTED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
    ];

    public function __construct(
        private readonly OrganizationDocumentTypeMapper $documentTypeMapper,
        private readonly ProjectFileProcessingMapper $processingMapper,
        private readonly ProjectMapper $projectMapper,
        private readonly IRootFolder $rootFolder,
    ) {
    }

    public function listOrganizationDocumentTypes(int $organizationId, bool $includeInactive = true): array
    {
        return $this->documentTypeMapper->findByOrganization($organizationId, $includeInactive);
    }

    public function createOrganizationDocumentType(int $organizationId, string $name, array $fields, bool $isActive = true): OrganizationDocumentType
    {
        $normalizedName = $this->normalizeName($name);
        $normalizedFields = $this->normalizeFields($fields);

        if ($this->documentTypeMapper->findByOrganizationAndName($organizationId, $normalizedName) !== null) {
            throw new OCSException('A document type with this name already exists for the organization.', 400);
        }

        return $this->documentTypeMapper->createType(
            $organizationId,
            $normalizedName,
            $normalizedFields,
            $isActive,
        );
    }

    public function updateOrganizationDocumentType(
        int $organizationId,
        int $documentTypeId,
        ?string $name = null,
        ?array $fields = null,
        ?bool $isActive = null,
    ): OrganizationDocumentType {
        $documentType = $this->requireOrganizationDocumentType($organizationId, $documentTypeId);

        if ($name !== null) {
            $normalizedName = $this->normalizeName($name);
            $existingDocumentType = $this->documentTypeMapper->findByOrganizationAndName($organizationId, $normalizedName);
            if ($existingDocumentType !== null && (int) $existingDocumentType->getId() !== (int) $documentType->getId()) {
                throw new OCSException('A document type with this name already exists for the organization.', 400);
            }
            $documentType->setName($normalizedName);
        }
        if ($fields !== null) {
            $documentType->setFieldsJson(json_encode($this->normalizeFields($fields), JSON_UNESCAPED_SLASHES));
        }
        if ($isActive !== null) {
            $documentType->setIsActive($isActive ? 1 : 0);
        }

        return $this->documentTypeMapper->saveType($documentType);
    }

    public function deleteOrganizationDocumentType(int $organizationId, int $documentTypeId): void
    {
        $documentType = $this->requireOrganizationDocumentType($organizationId, $documentTypeId);
        $references = $this->processingMapper->countByDocumentTypeId($documentTypeId);
        if ($references > 0) {
            throw new OCSException('This document type is still used by OCR records and cannot be deleted.', 409);
        }

        $this->documentTypeMapper->deleteType($documentType);
    }

    public function getProjectFileProcessing(int $projectId, int $fileId): ?ProjectFileProcessing
    {
        return $this->processingMapper->findByProjectAndFileId($projectId, $fileId);
    }

    public function assignDocumentTypeToFile(Project $project, string $userId, int $fileId, int $documentTypeId): ProjectFileProcessing
    {
        $documentType = $this->requireOrganizationDocumentType((int) $project->getOrganizationId(), $documentTypeId);
        if ($documentType === null) {
            throw new OCSNotFoundException('Document type not found for this organization.');
        }
        if ((int) $documentType->getIsActive() !== 1) {
            throw new OCSException('This OCR document type is inactive.', 400);
        }

        $file = $this->resolveProjectFile($project, $userId, $fileId);
        $mimeType = trim((string) $file->getMimeType());
        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            throw new OCSException('Only supported OCR file types can be processed (PDF, JPEG, PNG, DOCX, XLSX, XLS).', 400);
        }

        $userFolder = $this->rootFolder->getUserFolder($userId);
        $relativePath = (string) $userFolder->getRelativePath($file->getPath());
        $filePath = $relativePath !== '' ? $relativePath : (string) $file->getPath();

        $existingRecord = $this->processingMapper->findByProjectAndFileId((int) $project->getId(), $fileId);
        if ($existingRecord === null) {
            return $this->processingMapper->createRecord(
                (int) $project->getId(),
                (int) $project->getOrganizationId(),
                $fileId,
                $filePath,
                (string) $file->getName(),
                $mimeType,
                $documentType,
            );
        }

        return $this->resetRecordForProcessing(
            $existingRecord,
            $project,
            $filePath,
            (string) $file->getName(),
            $mimeType,
            $documentType->getId(),
        );
    }

    public function queueFileReprocess(Project $project, string $userId, int $fileId): ProjectFileProcessing
    {
        $existingRecord = $this->processingMapper->findByProjectAndFileId((int) $project->getId(), $fileId);
        if ($existingRecord === null || $existingRecord->getDocumentTypeId() === null) {
            throw new OCSNotFoundException('OCR processing record not found for this file.');
        }

        $documentType = $this->requireOrganizationDocumentType(
            (int) $project->getOrganizationId(),
            (int) $existingRecord->getDocumentTypeId(),
        );

        $file = $this->resolveProjectFile($project, $userId, $fileId);
        $mimeType = trim((string) $file->getMimeType());
        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            throw new OCSException('Only supported OCR file types can be processed (PDF, JPEG, PNG, DOCX, XLSX, XLS).', 400);
        }

        $userFolder = $this->rootFolder->getUserFolder($userId);
        $relativePath = (string) $userFolder->getRelativePath($file->getPath());
        $filePath = $relativePath !== '' ? $relativePath : (string) $file->getPath();

        return $this->resetRecordForProcessing(
            $existingRecord,
            $project,
            $filePath,
            (string) $file->getName(),
            $mimeType,
            $documentType->getId(),
        );
    }

    public function updateExtractedFields(Project $project, int $fileId, array $fields): ProjectFileProcessing
    {
        $record = $this->processingMapper->findByProjectAndFileId((int) $project->getId(), $fileId);
        if ($record === null || $record->getDocumentTypeId() === null) {
            throw new OCSNotFoundException('OCR processing record not found for this file.');
        }

        $documentType = $this->requireOrganizationDocumentType(
            (int) $project->getOrganizationId(),
            (int) $record->getDocumentTypeId(),
        );

        $normalizedExtracted = $this->normalizeExtractedFields($documentType->getFields(), $fields);

        $record->setExtractedJson(json_encode($normalizedExtracted, JSON_UNESCAPED_SLASHES));
        $record->setOcrStatus('done');
        $record->setErrorMessage(null);
        $record->setProcessedAt(new DateTime());

        return $this->processingMapper->saveRecord($record);
    }

    public function requireOrganizationDocumentType(int $organizationId, int $documentTypeId): OrganizationDocumentType
    {
        $documentType = $this->documentTypeMapper->find($documentTypeId);
        if ($documentType === null || (int) $documentType->getOrganizationId() !== $organizationId) {
            throw new OCSNotFoundException('Document type not found.');
        }

        return $documentType;
    }

    private function resolveProjectFile(Project $project, string $userId, int $fileId): File
    {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        $node = $userFolder->getFirstNodeById($fileId);
        if (!$node instanceof File) {
            throw new OCSNotFoundException('File not found.');
        }

        $relativePath = trim((string) $userFolder->getRelativePath($node->getPath()), '/');
        if ($relativePath === '') {
            throw new OCSNotFoundException('File not found in project scope.');
        }

        $sharedRoot = trim((string) $project->getFolderPath(), '/');
        if ($this->pathMatchesRoot($relativePath, $sharedRoot)) {
            return $node;
        }

        $privateLink = $this->projectMapper->findPrivateFolderForUser((int) $project->getId(), $userId);
        if ($privateLink !== null) {
            $privateRoot = trim((string) $userFolder->getRelativePath((string) $privateLink->getFolderPath()), '/');
            if ($privateRoot === '') {
                $privateRoot = trim(basename((string) $privateLink->getFolderPath()), '/');
            }
            if ($this->pathMatchesRoot($relativePath, $privateRoot)) {
                return $node;
            }
        }

        throw new OCSNotFoundException('File not found in project scope.');
    }

    private function pathMatchesRoot(string $relativePath, string $rootPath): bool
    {
        $relativePath = trim($relativePath, '/');
        $rootPath = trim($rootPath, '/');

        if ($relativePath === '' || $rootPath === '') {
            return false;
        }

        return $relativePath === $rootPath || str_starts_with($relativePath, $rootPath . '/');
    }

    private function resetRecordForProcessing(
        ProjectFileProcessing $record,
        Project $project,
        string $filePath,
        string $fileName,
        string $mimeType,
        int $documentTypeId,
    ): ProjectFileProcessing {
        $record->setOrganizationId((int) $project->getOrganizationId());
        $record->setFilePath($filePath);
        $record->setFileName($fileName);
        $record->setMimeType($mimeType);
        $record->setDocumentTypeId($documentTypeId);
        $record->setOcrStatus('pending');
        $record->setExtractedJson(null);
        $record->setErrorMessage(null);
        $record->setProcessedAt(null);

        return $this->processingMapper->saveRecord($record);
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new OCSException('Document type name is required.', 400);
        }

        return substr($name, 0, 255);
    }

    private function normalizeFields(array $fields): array
    {
        $normalizedFields = [];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $normalizedFields[] = [
                    'name' => $this->normalizeFieldName($field),
                ];
                continue;
            }

            if (!is_array($field)) {
                throw new OCSException('Each extraction field must be a string or an object.', 400);
            }

            $name = (string) ($field['name'] ?? $field['label'] ?? $field['key'] ?? '');

            $normalizedFields[] = [
                'name' => $this->normalizeFieldName($name),
            ];
        }

        if ($normalizedFields === []) {
            throw new OCSException('At least one extraction field is required.', 400);
        }

        $seen = [];
        foreach ($normalizedFields as $field) {
            if (isset($seen[$field['name']])) {
                throw new OCSException(sprintf('Duplicate extraction field name "%s".', $field['name']), 400);
            }
            $seen[$field['name']] = true;
        }

        return array_values($normalizedFields);
    }

    private function normalizeFieldName(string $fieldName): string
    {
        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            throw new OCSException('Extraction field name is required.', 400);
        }

        return substr($fieldName, 0, 255);
    }

    /**
     * @param array<int, array<string, mixed>> $documentTypeFields
     * @param array<mixed> $submittedFields
     * @return array<string, array{name:string,value:?string,confidence:string}>
     */
    private function normalizeExtractedFields(array $documentTypeFields, array $submittedFields): array
    {
        $allowedFieldNames = [];
        foreach ($documentTypeFields as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name !== '') {
                $allowedFieldNames[] = $name;
            }
        }

        if ($allowedFieldNames === []) {
            throw new OCSException('Document type has no extraction fields.', 400);
        }

        $submittedValues = [];
        foreach ($submittedFields as $key => $rawValue) {
            $fieldName = '';
            $value = $rawValue;

            if (is_array($rawValue) && array_key_exists('name', $rawValue)) {
                $fieldName = trim((string) $rawValue['name']);
                $value = $rawValue['value'] ?? null;
            } elseif (is_string($key)) {
                $fieldName = trim($key);
                if (is_array($rawValue) && array_key_exists('value', $rawValue)) {
                    $value = $rawValue['value'];
                }
            }

            if ($fieldName === '') {
                continue;
            }

            $submittedValues[$fieldName] = $this->normalizeExtractedValue($value);
        }

        $normalized = [];
        foreach (array_values(array_unique($allowedFieldNames)) as $fieldName) {
            $value = $submittedValues[$fieldName] ?? null;
            $normalized[$fieldName] = [
                'name' => $fieldName,
                'value' => $value,
                'confidence' => $value === null ? 'low' : 'manual',
            ];
        }

        return $normalized;
    }

    private function normalizeExtractedValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 4000);
    }
}
