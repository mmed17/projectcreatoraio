<?php

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class DeckPermissionTemplate extends Entity implements JsonSerializable
{
	public $id;

	protected ?int $organizationId = null;
	protected ?string $name = null;
	protected ?string $createdBy = null;
	protected ?string $templateJson = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct()
	{
		$this->addType('organizationId', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('createdBy', Types::STRING);
		$this->addType('templateJson', Types::TEXT);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}

	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'organizationId' => $this->organizationId,
			'name' => $this->name,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt ? $this->createdAt->format('c') : null,
			'updatedAt' => $this->updatedAt ? $this->updatedAt->format('c') : null,
		];
	}
}
