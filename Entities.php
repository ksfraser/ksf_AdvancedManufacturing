<?php
/**
 * FrontAccounting Advanced Manufacturing Module
 *
 * Comprehensive manufacturing execution system with work orders, BOMs, and production tracking.
 *
 * @package FA\Modules\AdvancedManufacturing
 * @version 1.0.0
 * @author FrontAccounting Team
 * @license GPL-3.0
 */

namespace FA\Modules\AdvancedManufacturing;

use FA\Events\EventDispatcherInterface;
use FA\Database\DBALInterface;
use FA\Services\InventoryService;
use FA\Services\GLService;
use FA\Exceptions\ManufacturingException;
use Psr\Log\LoggerInterface;

/**
 * Work Order Entity
 */
class WorkOrder
{
    private int $woNumber;
    private string $stockId;
    private string $location;
    private \DateTime $requiredBy;
    private \DateTime $startDate;
    private float $quantity;
    private string $reference;
    private string $remark;
    private bool $closed;
    private array $items = [];

    public function __construct(
        int $woNumber,
        string $stockId,
        string $location,
        \DateTime $requiredBy,
        float $quantity
    ) {
        $this->woNumber = $woNumber;
        $this->stockId = $stockId;
        $this->location = $location;
        $this->requiredBy = $requiredBy;
        $this->quantity = $quantity;
        $this->startDate = new \DateTime();
        $this->closed = false;
    }

    public function getWoNumber(): int { return $this->woNumber; }
    public function getStockId(): string { return $this->stockId; }
    public function getLocation(): string { return $this->location; }
    public function getRequiredBy(): \DateTime { return $this->requiredBy; }
    public function getStartDate(): \DateTime { return $this->startDate; }
    public function getQuantity(): float { return $this->quantity; }
    public function getReference(): string { return $this->reference ?? ''; }
    public function getRemark(): string { return $this->remark ?? ''; }
    public function isClosed(): bool { return $this->closed; }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function setRemark(string $remark): self
    {
        $this->remark = $remark;
        return $this;
    }

    public function setStartDate(\DateTime $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function close(): self
    {
        $this->closed = true;
        return $this;
    }

    public function addItem(WorkOrderItem $item): self
    {
        $this->items[$item->getStockId()] = $item;
        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getItem(string $stockId): ?WorkOrderItem
    {
        return $this->items[$stockId] ?? null;
    }
}

/**
 * Work Order Item Entity
 */
class WorkOrderItem
{
    private int $wo;
    private string $stockId;
    private float $quantityRequired;
    private float $quantityReceived;
    private float $quantityIssued;
    private float $standardCost;
    private string $nextLot;
    private string $nextLotSerial;

    public function __construct(
        int $wo,
        string $stockId,
        float $quantityRequired,
        float $standardCost = 0.0
    ) {
        $this->wo = $wo;
        $this->stockId = $stockId;
        $this->quantityRequired = $quantityRequired;
        $this->quantityReceived = 0.0;
        $this->quantityIssued = 0.0;
        $this->standardCost = $standardCost;
    }

    public function getWo(): int { return $this->wo; }
    public function getStockId(): string { return $this->stockId; }
    public function getQuantityRequired(): float { return $this->quantityRequired; }
    public function getQuantityReceived(): float { return $this->quantityReceived; }
    public function getQuantityIssued(): float { return $this->quantityIssued; }
    public function getStandardCost(): float { return $this->standardCost; }
    public function getNextLot(): string { return $this->nextLot ?? ''; }
    public function getNextLotSerial(): string { return $this->nextLotSerial ?? ''; }

    public function setQuantityReceived(float $quantity): self
    {
        $this->quantityReceived = $quantity;
        return $this;
    }

    public function setQuantityIssued(float $quantity): self
    {
        $this->quantityIssued = $quantity;
        return $this;
    }

    public function setNextLot(string $lot): self
    {
        $this->nextLot = $lot;
        return $this;
    }

    public function setNextLotSerial(string $serial): self
    {
        $this->nextLotSerial = $serial;
        return $this;
    }

    public function getQuantityOutstanding(): float
    {
        return $this->quantityRequired - $this->quantityReceived;
    }

    public function isComplete(): bool
    {
        return $this->quantityReceived >= $this->quantityRequired;
    }
}

/**
 * BOM (Bill of Materials) Entity
 */
class BOM
{
    private string $parent;
    private string $component;
    private float $quantity;
    private int $sequence;
    private string $workCentre;
    private string $effectiveAfter;
    private string $effectiveTo;
    private bool $autoIssue;
    private string $remark;

    public function __construct(
        string $parent,
        string $component,
        float $quantity,
        int $sequence = 1
    ) {
        $this->parent = $parent;
        $this->component = $component;
        $this->quantity = $quantity;
        $this->sequence = $sequence;
        $this->effectiveAfter = date('Y-m-d');
        $this->effectiveTo = '9999-12-31';
        $this->autoIssue = false;
    }

    public function getParent(): string { return $this->parent; }
    public function getComponent(): string { return $this->component; }
    public function getQuantity(): float { return $this->quantity; }
    public function getSequence(): int { return $this->sequence; }
    public function getWorkCentre(): string { return $this->workCentre ?? ''; }
    public function getEffectiveAfter(): string { return $this->effectiveAfter; }
    public function getEffectiveTo(): string { return $this->effectiveTo; }
    public function isAutoIssue(): bool { return $this->autoIssue; }
    public function getRemark(): string { return $this->remark ?? ''; }

    public function setWorkCentre(string $workCentre): self
    {
        $this->workCentre = $workCentre;
        return $this;
    }

    public function setEffectiveAfter(string $date): self
    {
        $this->effectiveAfter = $date;
        return $this;
    }

    public function setEffectiveTo(string $date): self
    {
        $this->effectiveTo = $date;
        return $this;
    }

    public function setAutoIssue(bool $autoIssue): self
    {
        $this->autoIssue = $autoIssue;
        return $this;
    }

    public function setRemark(string $remark): self
    {
        $this->remark = $remark;
        return $this;
    }
}

/**
 * Work Centre Entity
 */
class WorkCentre
{
    private string $code;
    private string $description;
    private string $location;
    private float $capacity;
    private float $setupTime;
    private float $ labourCost;
    private float $overheadCost;
    private string $calendar;

    public function __construct(string $code, string $description, string $location)
    {
        $this->code = $code;
        $this->description = $description;
        $this->location = $location;
        $this->capacity = 0.0;
        $this->setupTime = 0.0;
        $this->labourCost = 0.0;
        $this->overheadCost = 0.0;
    }

    public function getCode(): string { return $this->code; }
    public function getDescription(): string { return $this->description; }
    public function getLocation(): string { return $this->location; }
    public function getCapacity(): float { return $this->capacity; }
    public function getSetupTime(): float { return $this->setupTime; }
    public function getLabourCost(): float { return $this->labourCost; }
    public function getOverheadCost(): float { return $this->overheadCost; }
    public function getCalendar(): string { return $this->calendar ?? ''; }

    public function setCapacity(float $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function setSetupTime(float $time): self
    {
        $this->setupTime = $time;
        return $this;
    }

    public function setLabourCost(float $cost): self
    {
        $this->labourCost = $cost;
        return $this;
    }

    public function setOverheadCost(float $cost): self
    {
        $this->overheadCost = $cost;
        return $this;
    }

    public function setCalendar(string $calendar): self
    {
        $this->calendar = $calendar;
        return $this;
    }
}