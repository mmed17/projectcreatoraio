<?php
namespace OCA\ProjectCreatorAIO\Db;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use JsonSerializable;

class Project extends Entity implements JsonSerializable {
    public $id;

    // 1. Project Details
    protected string|null $name        = null;
    protected string|null $label       = null;
    protected string|null $number      = null;
    protected int|null    $type        = null;
    protected string|null $description = null;

    // 2. Client Info
    protected string|null $clientName    = null;
    protected string|null $clientRole    = null;
    protected string|null $clientPhone   = null;
    protected string|null $clientEmail   = null;
    protected string|null $clientAddress = null; // Client Specific Address

    // 3. Location Info
    protected string|null $locStreet   = null;
    protected string|null $locCity     = null;
    protected string|null $locZip      = null;
    protected string|null $externalRef = null;

    // System Fields
    protected string|null $ownerId     = null;
    protected string|null $boardId     = null;
    protected string|null $projectGroupGid = null;
    protected int|null    $folderId    = null;
    public    string|null $folderPath  = null;
    protected int|null    $status      = null;
    protected int|null    $organizationId = null;
    protected string|null    $whiteBoardId = null;
    protected int|null $requiredPreparationWeeks = null;
    protected int|null $cvObjectOwnership = null;
    protected int|null $cvTraceOwnership = null;
    protected int|null $cvBuildingType = null;
    protected int|null $cvAvpLocation = null;
    protected DateTime|null $createdAt = null;
    protected DateTime|null $updatedAt = null;

    public function __construct() {
        $this->addType('name',        Types::STRING);
        $this->addType('label',       Types::STRING);
        $this->addType('number',      Types::STRING);
        $this->addType('type',        Types::INTEGER);
        $this->addType('description', Types::STRING);
        
        // Client
        $this->addType('clientName',    Types::STRING);
        $this->addType('clientRole',    Types::STRING);
        $this->addType('clientPhone',   Types::STRING);
        $this->addType('clientEmail',   Types::STRING);
        $this->addType('clientAddress', Types::STRING);

        // Location
        $this->addType('locStreet',   Types::STRING);
        $this->addType('locCity',     Types::STRING);
        $this->addType('locZip',      Types::STRING);
        $this->addType('externalRef', Types::STRING);

        // System
        $this->addType('ownerId',     Types::STRING);
        $this->addType('boardId',     Types::STRING);
        $this->addType('project_group_gid', Types::STRING);
        $this->addType('folderId',    Types::INTEGER);
        $this->addType('folderPath',  Types::STRING);
        $this->addType('status',      Types::INTEGER);
        $this->addType('organization_id', Types::INTEGER);
        $this->addType('white_board_id', Types::STRING);
        $this->addType('requiredPreparationWeeks', Types::INTEGER);
        $this->addType('cvObjectOwnership', Types::SMALLINT);
        $this->addType('cvTraceOwnership', Types::SMALLINT);
        $this->addType('cvBuildingType', Types::SMALLINT);
        $this->addType('cvAvpLocation', Types::SMALLINT);
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
            'description' => $this->description,
            
            // Client
            'client_name'    => $this->clientName,
            'client_role'    => $this->clientRole,
            'client_phone'   => $this->clientPhone,
            'client_email'   => $this->clientEmail,
            'client_address' => $this->clientAddress,

            // Location
            'loc_street'   => $this->locStreet,
            'loc_city'     => $this->locCity,
            'loc_zip'      => $this->locZip,
            'external_ref' => $this->externalRef,

            'ownerId'    => $this->ownerId,
            'boardId'    => $this->boardId,
            'project_group_gid' => $this->projectGroupGid,
            'folderId'   => $this->folderId,
            'folderPath' => $this->folderPath,
            'status'     => $this->status,
            'organization_id' => $this->organizationId,
            'white_board_id' => $this->whiteBoardId,
            'required_preparation_weeks' => $this->requiredPreparationWeeks,
            'cv_object_ownership' => $this->cvObjectOwnership,
            'cv_trace_ownership' => $this->cvTraceOwnership,
            'cv_building_type' => $this->cvBuildingType,
            'cv_avp_location' => $this->cvAvpLocation,
            'createdAt'  => $this->createdAt,
            'updatedAt'  => $this->updatedAt
        ];
    }
}
