<?php

namespace OCA\ProjectCreatorAIO\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use DateTime;
use JsonSerializable;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getLabel()
 * @method void setLabel(string $label)
 * @method DateTime|null getStartDate()
 * @method void setStartDate(?DateTime $startDate)
 * @method DateTime|null getEndDate()
 * @method void setEndDate(?DateTime $endDate)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method int getOrderIndex()
 * @method void setOrderIndex(int $orderIndex)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @method string|null getSystemKey()
 * @method void setSystemKey(?string $systemKey)
 */
class TimelineItem extends Entity implements JsonSerializable
{
    protected $projectId;
    protected $label;
    protected $startDate;
    protected $endDate;
    protected $color;
    protected $orderIndex;
    protected $systemKey;
    protected $createdAt;
    protected $updatedAt;

    public function __construct()
    {
        $this->addType('projectId', Types::INTEGER);
        $this->addType('orderIndex', Types::INTEGER);
        $this->addType('startDate', Types::DATE);
        $this->addType('endDate', Types::DATE);
        $this->addType('systemKey', Types::STRING);
        $this->addType('createdAt', Types::DATETIME);
        $this->addType('updatedAt', Types::DATETIME);
    }


    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'projectId' => $this->getProjectId(),
            'label' => $this->getLabel(),
            'startDate' => $this->startDate instanceof DateTime
                ? $this->startDate->format('Y-m-d')
                : $this->startDate,
            'endDate' => $this->endDate instanceof DateTime
                ? $this->endDate->format('Y-m-d')
                : $this->endDate,
            'color' => $this->getColor(),
            'orderIndex' => $this->getOrderIndex(),
            'systemKey' => $this->getSystemKey(),
            'createdAt' => $this->createdAt instanceof DateTime
                ? $this->createdAt->format('Y-m-d H:i:s')
                : $this->createdAt,
            'updatedAt' => $this->updatedAt instanceof DateTime
                ? $this->updatedAt->format('Y-m-d H:i:s')
                : $this->updatedAt,
        ];
    }
}
