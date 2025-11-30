<?php
/**
 * Work Order Item Entity
 *
 * @package FA\Modules\AdvancedManufacturing
 */

namespace FA\Modules\AdvancedManufacturing;

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