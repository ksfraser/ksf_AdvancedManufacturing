# Advanced Manufacturing Module for FrontAccounting

A comprehensive manufacturing execution system (MES) module that provides advanced production planning, scheduling, and execution capabilities based on webERP manufacturing functionality.

## Features

- **Production Planning**: Advanced production planning and scheduling
- **Work Order Management**: Comprehensive work order tracking and management
- **Routing Management**: Production routing and operation sequencing
- **Capacity Planning**: Production capacity analysis and planning
- **Quality Integration**: Integration with quality control processes
- **Cost Tracking**: Real-time production cost monitoring
- **Performance Metrics**: Production efficiency and performance analytics
- **Bill of Materials**: Multi-level BOM management and analysis
- **Material Requirements**: Work order material issuing and tracking
- **Production Completion**: Finished goods receiving and work order closure

## Architecture

### Core Components

- **WorkOrderService.php**: Main work order management service
- **BOMService.php**: Bill of Materials management service
- **Entities.php**: Entity classes (WorkOrder, WorkOrderItem, BOM, WorkCentre)
- **Events.php**: PSR-14 compatible event classes
- **ManufacturingException.php**: Module-specific exceptions

### Key Services

```php
// Work Order Management
$woService = new WorkOrderService($db, $events, $logger, $inventory, $gl);
$workOrder = $woService->createWorkOrder('WIDGET001', 'MAIN', 100, new DateTime('2025-12-01'));
$woService->issueMaterials(1, ['COMP001' => 50, 'COMP002' => 25], 'MAIN');
$woService->receiveFinishedGoods(1, 100, 'MAIN');

// BOM Management
$bomService = new BOMService($db, $events, $logger, $inventory);
$bom = $bomService->createBOMEntry('WIDGET001', 'COMP001', 2.5);
$bomStructure = $bomService->getMultiLevelBOM('WIDGET001');
```

## Requirements

- FrontAccounting 2.4+
- PHP 8.0+
- Manufacturing industry focus
- PSR-14 Event Dispatcher
- Doctrine DBAL
- PSR-3 Logger

## Installation

1. Ensure the module directory is in `modules/AdvancedManufacturing/`
2. The module will auto-register through FA's module system
3. Required database tables are created automatically during first use

## Usage

### Creating Work Orders

```php
// Get service instance
$woService = $fa->getModule('AdvancedManufacturing')->getWorkOrderService();

// Create a work order
$workOrder = $woService->createWorkOrder(
    'FG001',        // Finished good item
    'MAIN',         // Location
    100,            // Quantity
    new DateTime('2025-12-01'), // Required by
    [
        'reference' => 'WO-2025-001',
        'remark' => 'Urgent production run'
    ]
);
```

### Managing BOMs

```php
// Get BOM service
$bomService = $fa->getModule('AdvancedManufacturing')->getBOMService();

// Create BOM entries
$bomService->createBOMEntry('FG001', 'RM001', 2.0, [
    'workCentre' => 'ASSEMBLY',
    'autoIssue' => true
]);

$bomService->createBOMEntry('FG001', 'RM002', 1.5, [
    'sequence' => 2,
    'remark' => 'Secondary component'
]);

// Get BOM structure
$bom = $bomService->getBOM('FG001');
$multiLevel = $bomService->getMultiLevelBOM('FG001');
```

### Material Issuing and Production

```php
// Issue materials to work order
$woService->issueMaterials(1, [
    'RM001' => 200,  // 100 units * 2.0 per unit
    'RM002' => 150   // 100 units * 1.5 per unit
], 'MAIN');

// Receive finished goods
$woService->receiveFinishedGoods(1, 100, 'MAIN');
```

## Database Tables

The module integrates with existing FA tables:

- `workorders`: Work order headers
- `woitems`: Work order components and quantities
- `bom`: Bill of Materials structure
- `workcentres`: Production work centres
- `stockmaster`: Item master data
- `stockmoves`: Stock movements for issues/receipts

## Integration Points

### Events
- `WorkOrderCreatedEvent`: Fired when work order is created
- `MaterialsIssuedEvent`: Fired when materials are issued
- `FinishedGoodsReceivedEvent`: Fired when finished goods are received
- `BOMCreatedEvent`: Fired when BOM entry is created

### Services
- Integrates with InventoryService for stock movements
- Uses GLService for cost accounting
- Leverages FA's event system for extensibility

## Work Order Lifecycle

1. **Creation**: Work order created with required quantity and date
2. **BOM Loading**: Components automatically loaded from BOM
3. **Material Issuing**: Components issued to production
4. **Production**: Manufacturing process
5. **Completion**: Finished goods received
6. **Closure**: Work order closed when complete

## BOM Management

### Single Level BOM
- Direct components for an assembly
- Quantity per parent item
- Work centre assignments
- Auto-issue flags

### Multi-Level BOM
- Complete product structure
- All sub-assemblies and components
- Level-by-level analysis
- MRP integration ready

## Future Enhancements

- Integration with MRP module for requirements planning
- Advanced scheduling algorithms with capacity constraints
- Real-time production monitoring and OEE calculations
- IoT device integration for automated data collection
- Predictive maintenance integration
- Advanced costing with labour and overhead allocation
- Production routing and operation management
- Quality control integration at operation level