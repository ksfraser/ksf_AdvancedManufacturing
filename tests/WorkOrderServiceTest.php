<?php
/**
 * Advanced Manufacturing Module Tests
 */

namespace FA\Modules\AdvancedManufacturing\Tests;

use PHPUnit\Framework\TestCase;
use FA\Modules\AdvancedManufacturing\WorkOrderService;
use FA\Modules\AdvancedManufacturing\WorkOrder;
use FA\Modules\AdvancedManufacturing\ManufacturingException;
use FA\Database\DBALInterface;
use FA\Events\EventDispatcherInterface;
use FA\Services\InventoryService;
use FA\Services\GLService;
use Psr\Log\LoggerInterface;

/**
 * Work Order Service Test Suite
 */
class WorkOrderServiceTest extends TestCase
{
    private $db;
    private $events;
    private $logger;
    private $inventory;
    private $gl;
    private WorkOrderService $woService;

    protected function setUp(): void
    {
        $this->db = $this->createMock(DBALInterface::class);
        $this->events = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->inventory = $this->createMock(InventoryService::class);
        $this->gl = $this->createMock(GLService::class);

        $this->woService = new WorkOrderService(
            $this->db,
            $this->events,
            $this->logger,
            $this->inventory,
            $this->gl
        );
    }

    public function testCreateWorkOrderValidatesManufacturedItem()
    {
        $this->db->method('fetchAssoc')
            ->willReturnOnConsecutiveCalls(
                ['mbflag' => 'M'], // Item exists and is manufactured
                ['next_wo' => 1]   // Next work order number
            );

        $this->db->expects($this->once())
            ->method('executeUpdate')
            ->with($this->stringContains('INSERT INTO workorders'));

        $workOrder = $this->woService->createWorkOrder(
            'TEST001',
            'MAIN',
            100,
            new \DateTime('2025-12-01')
        );

        $this->assertInstanceOf(WorkOrder::class, $workOrder);
        $this->assertEquals(1, $workOrder->getWoNumber());
        $this->assertEquals('TEST001', $workOrder->getStockId());
    }

    public function testCreateWorkOrderThrowsExceptionForNonManufacturedItem()
    {
        $this->db->method('fetchAssoc')
            ->willReturn(['mbflag' => 'B']); // Item exists but is bought

        $this->expectException(ManufacturingException::class);
        $this->expectExceptionMessage('is not a manufactured item');

        $this->woService->createWorkOrder(
            'TEST001',
            'MAIN',
            100,
            new \DateTime('2025-12-01')
        );
    }

    public function testGetWorkOrderReturnsWorkOrderObject()
    {
        $mockData = [
            'wo' => 1,
            'stockid' => 'TEST001',
            'loccode' => 'MAIN',
            'requiredby' => '2025-12-01',
            'qtyreqd' => 100.0,
            'startdate' => '2025-11-01',
            'reference' => 'WO-001',
            'remark' => 'Test work order',
            'closed' => 0,
            'description' => 'Test Item'
        ];

        $this->db->method('fetchAssoc')
            ->willReturn($mockData);

        $this->db->method('fetchAll')
            ->willReturn([]); // No items

        $workOrder = $this->woService->getWorkOrder(1);

        $this->assertInstanceOf(WorkOrder::class, $workOrder);
        $this->assertEquals(1, $workOrder->getWoNumber());
        $this->assertEquals('TEST001', $workOrder->getStockId());
        $this->assertEquals('WO-001', $workOrder->getReference());
    }

    public function testGetWorkOrderThrowsExceptionForNonExistentWorkOrder()
    {
        $this->db->method('fetchAssoc')
            ->willReturn(null);

        $this->expectException(ManufacturingException::class);
        $this->expectExceptionMessage('Work order 999 not found');

        $this->woService->getWorkOrder(999);
    }
}