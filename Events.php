<?php
/**
 * FrontAccounting Advanced Manufacturing Module Events
 *
 * PSR-14 compatible events for manufacturing operations.
 *
 * @package FA\Modules\AdvancedManufacturing
 * @version 1.0.0
 * @author FrontAccounting Team
 * @license GPL-3.0
 */

namespace FA\Modules\AdvancedManufacturing;

use FA\Events\Event;

/**
 * Work Order Created Event
 */
class WorkOrderCreatedEvent extends Event
{
    private WorkOrder $workOrder;

    public function __construct(WorkOrder $workOrder)
    {
        $this->workOrder = $workOrder;
    }

    public function getWorkOrder(): WorkOrder
    {
        return $this->workOrder;
    }
}

/**
 * Materials Issued Event
 */
class MaterialsIssuedEvent extends Event
{
    private WorkOrder $workOrder;
    private array $materials;

    public function __construct(WorkOrder $workOrder, array $materials)
    {
        $this->workOrder = $workOrder;
        $this->materials = $materials;
    }

    public function getWorkOrder(): WorkOrder
    {
        return $this->workOrder;
    }

    public function getMaterials(): array
    {
        return $this->materials;
    }
}

/**
 * Finished Goods Received Event
 */
class FinishedGoodsReceivedEvent extends Event
{
    private WorkOrder $workOrder;
    private float $quantity;

    public function __construct(WorkOrder $workOrder, float $quantity)
    {
        $this->workOrder = $workOrder;
        $this->quantity = $quantity;
    }

    public function getWorkOrder(): WorkOrder
    {
        return $this->workOrder;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }
}

/**
 * BOM Created Event
 */
class BOMCreatedEvent extends Event
{
    private BOM $bom;

    public function __construct(BOM $bom)
    {
        $this->bom = $bom;
    }

    public function getBOM(): BOM
    {
        return $this->bom;
    }
}

/**
 * Work Centre Created Event
 */
class WorkCentreCreatedEvent extends Event
{
    private WorkCentre $workCentre;

    public function __construct(WorkCentre $workCentre)
    {
        $this->workCentre = $workCentre;
    }

    public function getWorkCentre(): WorkCentre
    {
        return $this->workCentre;
    }
}