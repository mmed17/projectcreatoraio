<?php
namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use JsonSerializable;

class Project extends Entity implements JsonSerializable {
    public $id;

    protected string|null $name        = null;
    protected string|null $label       = null;
    protected string|null $number      = null;
    protected int|null    $type        = null;
    protected string|null $address     = null;
    protected string|null $description = null;
    protected string|null $ownerId     = null;
    protected string|null $circleId    = null;
    protected string|null $boardId     = null;
    protected int|null    $folderId    = null;
    public    string|null $folderPath  = null;
    protected int|null    $status      = null;
    protected int|null    $organizationId = null;
    protected DateTime|null $createdAt = null;
    protected DateTime|null $updatedAt = null;

    public function __construct() {
        $this->addType('name',        Types::STRING);
        $this->addType('label',       Types::STRING);
        $this->addType('number',      Types::STRING);
        $this->addType('type',        Types::INTEGER);
        $this->addType('address',     Types::STRING);
        $this->addType('description', Types::STRING);
        $this->addType('ownerId',     Types::STRING);
        $this->addType('circleId',    Types::STRING);
        $this->addType('boardId',     Types::STRING);
        $this->addType('folderId',    Types::INTEGER);
        $this->addType('folderPath',  Types::STRING);
        $this->addType('status',      Types::INTEGER);
        $this->addType('organization_id', Types::INTEGER);
        $this->addType('createdAt',   Types::DATETIME);
        $this->addType('updatedAt',   Types::DATETIME);
    }

    public function jsonSerialize(): array {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'label'       => $this->name,
            'number'      => $this->number,
            'type'        => $this->type,
            'address'     => $this->address,
            'description' => $this->description,
            'ownerId'     => $this->ownerId,
            'circleId'    => $this->circleId,
            'boardId'     => $this->boardId,
            'folderId'    => $this->folderId,
            'folderPath'  => $this->folderPath,
            'status'      => $this->status,
            'organization_id' => $this->organizationId,
            'createdAt'   => $this->createdAt,
            'updatedAt'   => $this->updatedAt
        ];
    }
}