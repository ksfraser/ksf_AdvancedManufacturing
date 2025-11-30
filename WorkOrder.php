<?php
/**
 * Work Order Entity
 *
 * @package FA\Modules\AdvancedManufacturing
 */

namespace FA\Modules\AdvancedManufacturing;

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