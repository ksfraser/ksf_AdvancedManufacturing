<?php
/**
 * Work Centre Entity
 *
 * @package FA\Modules\AdvancedManufacturing
 */

namespace FA\Modules\AdvancedManufacturing;

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
    private float $labourCost;
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