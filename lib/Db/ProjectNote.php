<?php
namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use JsonSerializable;

class ProjectNote extends Entity implements JsonSerializable {
    public $id;

    protected ?int $projectId = null;
    protected ?string $userId = null;
    protected ?string $title = null;
    protected ?string $content = null;
    protected ?string $visibility = null; // 'public' or 'private'
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function __construct() {
        $this->addType('projectId', Types::INTEGER);
        $this->addType('userId', Types::STRING);
        $this->addType('title', Types::STRING);
        $this->addType('content', Types::TEXT);
        $this->addType('visibility', Types::STRING);
        $this->addType('createdAt', Types::DATETIME);
        $this->addType('updatedAt', Types::DATETIME);
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'projectId' => $this->projectId,
            'userId' => $this->userId,
            'title' => $this->title,
            'content' => $this->content,
            'visibility' => $this->visibility,
            'createdAt' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updatedAt' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
