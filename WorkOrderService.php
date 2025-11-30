<?php
/**
 * FrontAccounting Advanced Manufacturing Module - Work Order Service
 *
 * Comprehensive work order management service based on webERP functionality.
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
 * Work Order Service
 *
 * Handles work order creation, management, material issuing, and production completion
 */
class WorkOrderService
{
    private DBALInterface $db;
    private EventDispatcherInterface $events;
    private LoggerInterface $logger;
    private InventoryService $inventoryService;
    private GLService $glService;

    public function __construct(
        DBALInterface $db,
        EventDispatcherInterface $events,
        LoggerInterface $logger,
        InventoryService $inventoryService,
        GLService $glService
    ) {
        $this->db = $db;
        $this->events = $events;
        $this->logger = $logger;
        $this->inventoryService = $inventoryService;
        $this->glService = $glService;
    }

    /**
     * Create a new work order
     *
     * @param string $stockId The manufactured item
     * @param string $location Location code
     * @param float $quantity Quantity to produce
     * @param \DateTime $requiredBy Required completion date
     * @param array $options Additional options
     * @return WorkOrder The created work order
     * @throws ManufacturingException
     */
    public function createWorkOrder(
        string $stockId,
        string $location,
        float $quantity,
        \DateTime $requiredBy,
        array $options = []
    ): WorkOrder {
        $this->logger->info('Creating work order', [
            'stockId' => $stockId,
            'location' => $location,
            'quantity' => $quantity
        ]);

        // Validate the item can be manufactured
        $this->validateManufacturedItem($stockId);

        // Get next work order number
        $woNumber = $this->getNextWorkOrderNumber();

        // Create work order entity
        $workOrder = new WorkOrder(
            $woNumber,
            $stockId,
            $location,
            $requiredBy,
            $quantity
        );

        // Set optional fields
        if (isset($options['reference'])) {
            $workOrder->setReference($options['reference']);
        }
        if (isset($options['remark'])) {
            $workOrder->setRemark($options['remark']);
        }
        if (isset($options['startDate'])) {
            $workOrder->setStartDate($options['startDate']);
        }

        // Save to database
        $this->saveWorkOrder($workOrder);

        // Create work order items from BOM
        $this->createWorkOrderItems($workOrder);

        $this->events->dispatch(new WorkOrderCreatedEvent($workOrder));

        $this->logger->info('Work order created successfully', ['woNumber' => $woNumber]);

        return $workOrder;
    }

    /**
     * Issue materials to a work order
     *
     * @param int $woNumber Work order number
     * @param array $materials Array of ['stockId' => quantity] to issue
     * @param string $location Location code
     * @throws ManufacturingException
     */
    public function issueMaterials(int $woNumber, array $materials, string $location): void
    {
        $this->logger->info('Issuing materials to work order', [
            'woNumber' => $woNumber,
            'materials' => $materials
        ]);

        $workOrder = $this->getWorkOrder($woNumber);

        if ($workOrder->isClosed()) {
            throw new ManufacturingException("Cannot issue materials to closed work order {$woNumber}");
        }

        $this->db->beginTransaction();

        try {
            foreach ($materials as $stockId => $quantity) {
                $this->issueMaterialToWorkOrder($woNumber, $stockId, $quantity, $location);
            }

            $this->db->commit();

            $this->events->dispatch(new MaterialsIssuedEvent($workOrder, $materials));

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Receive finished goods from work order
     *
     * @param int $woNumber Work order number
     * @param float $quantity Quantity to receive
     * @param string $location Location code
     * @param array $options Additional options (batch, serial, etc.)
     * @throws ManufacturingException
     */
    public function receiveFinishedGoods(
        int $woNumber,
        float $quantity,
        string $location,
        array $options = []
    ): void {
        $this->logger->info('Receiving finished goods from work order', [
            'woNumber' => $woNumber,
            'quantity' => $quantity
        ]);

        $workOrder = $this->getWorkOrder($woNumber);

        if ($workOrder->isClosed()) {
            throw new ManufacturingException("Cannot receive goods from closed work order {$woNumber}");
        }

        // Update work order item with received quantity
        $this->updateReceivedQuantity($woNumber, $workOrder->getStockId(), $quantity);

        // Add stock to inventory
        $this->inventoryService->addStockMovement(
            $workOrder->getStockId(),
            $location,
            'WO-' . $woNumber,
            $quantity,
            0, // cost will be calculated
            'Work Order Receipt',
            null,
            null,
            true // auto cost
        );

        // Check if work order is complete
        if ($this->isWorkOrderComplete($woNumber)) {
            $this->closeWorkOrder($woNumber);
        }

        $this->events->dispatch(new FinishedGoodsReceivedEvent($workOrder, $quantity));
    }

    /**
     * Get work order details
     *
     * @param int $woNumber Work order number
     * @return WorkOrder
     * @throws ManufacturingException
     */
    public function getWorkOrder(int $woNumber): WorkOrder
    {
        $sql = "SELECT wo.*, stockmaster.description
                FROM workorders wo
                INNER JOIN stockmaster ON wo.stockid = stockmaster.stockid
                WHERE wo.wo = ?";

        $result = $this->db->fetchAssoc($sql, [$woNumber]);

        if (!$result) {
            throw new ManufacturingException("Work order {$woNumber} not found");
        }

        $workOrder = new WorkOrder(
            (int)$result['wo'],
            $result['stockid'],
            $result['loccode'],
            new \DateTime($result['requiredby']),
            (float)$result['qtyreqd']
        );

        $workOrder->setStartDate(new \DateTime($result['startdate']));
        if ($result['reference']) {
            $workOrder->setReference($result['reference']);
        }
        if ($result['remark']) {
            $workOrder->setRemark($result['remark']);
        }
        if ($result['closed'] == '1') {
            $workOrder->close();
        }

        // Load work order items
        $this->loadWorkOrderItems($workOrder);

        return $workOrder;
    }

    /**
     * Get work orders by status/location
     *
     * @param array $filters Filters (status, location, stockId, etc.)
     * @return WorkOrder[]
     */
    public function getWorkOrders(array $filters = []): array
    {
        $sql = "SELECT wo.*, stockmaster.description
                FROM workorders wo
                INNER JOIN stockmaster ON wo.stockid = stockmaster.stockid
                WHERE 1=1";

        $params = [];
        $conditions = [];

        if (isset($filters['location'])) {
            $conditions[] = "wo.loccode = ?";
            $params[] = $filters['location'];
        }

        if (isset($filters['stockId'])) {
            $conditions[] = "wo.stockid = ?";
            $params[] = $filters['stockId'];
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'open') {
                $conditions[] = "wo.closed = 0";
            } elseif ($filters['status'] === 'closed') {
                $conditions[] = "wo.closed = 1";
            }
        }

        if (isset($filters['requiredBy'])) {
            $conditions[] = "wo.requiredby <= ?";
            $params[] = $filters['requiredBy'];
        }

        $sql .= " " . implode(" AND ", $conditions);
        $sql .= " ORDER BY wo.wo DESC";

        $results = $this->db->fetchAll($sql, $params);

        $workOrders = [];
        foreach ($results as $result) {
            $workOrder = new WorkOrder(
                (int)$result['wo'],
                $result['stockid'],
                $result['loccode'],
                new \DateTime($result['requiredby']),
                (float)$result['qtyreqd']
            );

            $workOrder->setStartDate(new \DateTime($result['startdate']));
            if ($result['reference']) {
                $workOrder->setReference($result['reference']);
            }
            if ($result['remark']) {
                $workOrder->setRemark($result['remark']);
            }
            if ($result['closed'] == '1') {
                $workOrder->close();
            }

            $workOrders[] = $workOrder;
        }

        return $workOrders;
    }

    /**
     * Validate that an item can be manufactured
     *
     * @param string $stockId
     * @throws ManufacturingException
     */
    private function validateManufacturedItem(string $stockId): void
    {
        $sql = "SELECT mbflag FROM stockmaster WHERE stockid = ?";
        $result = $this->db->fetchAssoc($sql, [$stockId]);

        if (!$result) {
            throw new ManufacturingException("Item {$stockId} not found");
        }

        if ($result['mbflag'] !== 'M') {
            throw new ManufacturingException("Item {$stockId} is not a manufactured item");
        }
    }

    /**
     * Get next work order number
     *
     * @return int
     */
    private function getNextWorkOrderNumber(): int
    {
        $sql = "SELECT MAX(wo) + 1 as next_wo FROM workorders";
        $result = $this->db->fetchAssoc($sql);

        return $result['next_wo'] ?? 1;
    }

    /**
     * Save work order to database
     *
     * @param WorkOrder $workOrder
     */
    private function saveWorkOrder(WorkOrder $workOrder): void
    {
        $sql = "INSERT INTO workorders (
                    wo, loccode, requiredby, startdate, stockid, qtyreqd,
                    reference, remark, closed
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->executeUpdate($sql, [
            $workOrder->getWoNumber(),
            $workOrder->getLocation(),
            $workOrder->getRequiredBy()->format('Y-m-d'),
            $workOrder->getStartDate()->format('Y-m-d'),
            $workOrder->getStockId(),
            $workOrder->getQuantity(),
            $workOrder->getReference(),
            $workOrder->getRemark(),
            $workOrder->isClosed() ? 1 : 0
        ]);
    }

    /**
     * Create work order items from BOM
     *
     * @param WorkOrder $workOrder
     */
    private function createWorkOrderItems(WorkOrder $workOrder): void
    {
        $bomItems = $this->getBOMItems($workOrder->getStockId());

        foreach ($bomItems as $bomItem) {
            $requiredQty = $bomItem['quantity'] * $workOrder->getQuantity();

            $sql = "INSERT INTO woitems (
                        wo, stockid, qtyreqd, qtyrecd, qtyissued, stdcost
                    ) VALUES (?, ?, ?, 0, 0, ?)";

            $this->db->executeUpdate($sql, [
                $workOrder->getWoNumber(),
                $bomItem['component'],
                $requiredQty,
                $bomItem['standardcost'] ?? 0
            ]);
        }
    }

    /**
     * Get BOM items for a manufactured item
     *
     * @param string $stockId
     * @return array
     */
    private function getBOMItems(string $stockId): array
    {
        $sql = "SELECT bom.component,
                       bom.quantity,
                       stockmaster.materialcost + stockmaster.labourcost + stockmaster.overheadcost as standardcost
                FROM bom
                INNER JOIN stockmaster ON bom.component = stockmaster.stockid
                WHERE bom.parent = ?
                AND bom.effectiveafter <= CURDATE()
                AND bom.effectiveto >= CURDATE()
                ORDER BY bom.sequence";

        return $this->db->fetchAll($sql, [$stockId]);
    }

    /**
     * Load work order items into work order entity
     *
     * @param WorkOrder $workOrder
     */
    private function loadWorkOrderItems(WorkOrder $workOrder): void
    {
        $sql = "SELECT * FROM woitems WHERE wo = ?";
        $results = $this->db->fetchAll($sql, [$workOrder->getWoNumber()]);

        foreach ($results as $result) {
            $item = new WorkOrderItem(
                (int)$result['wo'],
                $result['stockid'],
                (float)$result['qtyreqd'],
                (float)$result['stdcost']
            );

            $item->setQuantityReceived((float)$result['qtyrecd']);
            $item->setQuantityIssued((float)$result['qtyissued']);

            $workOrder->addItem($item);
        }
    }

    /**
     * Issue material to work order
     *
     * @param int $woNumber
     * @param string $stockId
     * @param float $quantity
     * @param string $location
     */
    private function issueMaterialToWorkOrder(
        int $woNumber,
        string $stockId,
        float $quantity,
        string $location
    ): void {
        // Update woitems
        $sql = "UPDATE woitems SET qtyissued = qtyissued + ? WHERE wo = ? AND stockid = ?";
        $this->db->executeUpdate($sql, [$quantity, $woNumber, $stockId]);

        // Create stock movement
        $this->inventoryService->addStockMovement(
            $stockId,
            $location,
            'WO-' . $woNumber,
            -$quantity, // negative for issue
            0, // cost will be calculated
            'Work Order Issue',
            null,
            null,
            true // auto cost
        );
    }

    /**
     * Update received quantity for finished goods
     *
     * @param int $woNumber
     * @param string $stockId
     * @param float $quantity
     */
    private function updateReceivedQuantity(int $woNumber, string $stockId, float $quantity): void
    {
        $sql = "UPDATE woitems SET qtyrecd = qtyrecd + ? WHERE wo = ? AND stockid = ?";
        $this->db->executeUpdate($sql, [$quantity, $woNumber, $stockId]);
    }

    /**
     * Check if work order is complete
     *
     * @param int $woNumber
     * @return bool
     */
    private function isWorkOrderComplete(int $woNumber): bool
    {
        $sql = "SELECT COUNT(*) as incomplete FROM woitems
                WHERE wo = ? AND qtyrecd < qtyreqd";

        $result = $this->db->fetchAssoc($sql, [$woNumber]);

        return $result['incomplete'] == 0;
    }

    /**
     * Close work order
     *
     * @param int $woNumber
     */
    private function closeWorkOrder(int $woNumber): void
    {
        $sql = "UPDATE workorders SET closed = 1 WHERE wo = ?";
        $this->db->executeUpdate($sql, [$woNumber]);

        $this->logger->info('Work order closed', ['woNumber' => $woNumber]);
    }
}