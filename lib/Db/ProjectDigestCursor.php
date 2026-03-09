<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class ProjectDigestCursor extends Entity implements JsonSerializable {
	public $id;

	protected ?int $projectId = null;
	protected ?string $userUid = null;
	protected ?int $lastEventId = null;
	protected ?DateTime $lastSentAt = null;

	public function __construct() {
		$this->addType('projectId', Types::INTEGER);
		$this->addType('userUid', Types::STRING);
		$this->addType('lastEventId', Types::INTEGER);
		$this->addType('lastSentAt', Types::DATETIME);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'userUid' => $this->userUid,
			'lastEventId' => $this->lastEventId,
			'lastSentAt' => $this->lastSentAt?->format('Y-m-d H:i:s'),
		];
	}
}
