<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class OrganizationDocumentType extends Entity implements JsonSerializable
{
    protected ?int $organizationId = null;
    protected ?string $name = null;
    protected ?string $fieldsJson = null;
    protected ?int $isActive = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function __construct()
    {
        $this->addType('organizationId', Types::INTEGER);
        $this->addType('name', Types::STRING);
        $this->addType('fieldsJson', Types::TEXT);
        $this->addType('isActive', Types::SMALLINT);
        $this->addType('createdAt', Types::DATETIME);
        $this->addType('updatedAt', Types::DATETIME);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'fields' => $this->decodeFieldsJson(),
            'is_active' => (bool) $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getFields(): array
    {
        return $this->decodeFieldsJson();
    }

    private function decodeFieldsJson(): array
    {
        $raw = trim((string) $this->fieldsJson);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
