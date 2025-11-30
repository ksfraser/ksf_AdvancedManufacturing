<?php
/**
 * BOM (Bill of Materials) Entity
 *
 * @package FA\Modules\AdvancedManufacturing
 */

namespace FA\Modules\AdvancedManufacturing;

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