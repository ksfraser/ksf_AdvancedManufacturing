<?php
/**
 * FrontAccounting Advanced Manufacturing Module - BOM Service
 *
 * Bill of Materials management service.
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
use Psr\Log\LoggerInterface;

/**
 * BOM Service
 *
 * Handles Bill of Materials creation, management, and analysis
 */
class BOMService
{
    private DBALInterface $db;
    private EventDispatcherInterface $events;
    private LoggerInterface $logger;
    private InventoryService $inventoryService;

    public function __construct(
        DBALInterface $db,
        EventDispatcherInterface $events,
        LoggerInterface $logger,
        InventoryService $inventoryService
    ) {
        $this->db = $db;
        $this->events = $events;
        $this->logger = $logger;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Create a new BOM entry
     *
     * @param string $parent Parent item (manufactured item)
     * @param string $component Component item
     * @param float $quantity Quantity required
     * @param array $options Additional options
     * @return BOM The created BOM entry
     * @throws ManufacturingException
     */
    public function createBOMEntry(
        string $parent,
        string $component,
        float $quantity,
        array $options = []
    ): BOM {
        $this->logger->info('Creating BOM entry', [
            'parent' => $parent,
            'component' => $component,
            'quantity' => $quantity
        ]);

        // Validate items exist
        $this->validateItemExists($parent);
        $this->validateItemExists($component);

        // Validate parent is manufactured
        $this->validateManufacturedItem($parent);

        // Get next sequence number
        $sequence = $this->getNextSequence($parent);

        // Create BOM entity
        $bom = new BOM($parent, $component, $quantity, $sequence);

        // Set optional fields
        if (isset($options['workCentre'])) {
            $bom->setWorkCentre($options['workCentre']);
        }
        if (isset($options['effectiveAfter'])) {
            $bom->setEffectiveAfter($options['effectiveAfter']);
        }
        if (isset($options['effectiveTo'])) {
            $bom->setEffectiveTo($options['effectiveTo']);
        }
        if (isset($options['autoIssue'])) {
            $bom->setAutoIssue($options['autoIssue']);
        }
        if (isset($options['remark'])) {
            $bom->setRemark($options['remark']);
        }

        // Save to database
        $this->saveBOMEntry($bom);

        $this->events->dispatch(new BOMCreatedEvent($bom));

        $this->logger->info('BOM entry created successfully', [
            'parent' => $parent,
            'component' => $component
        ]);

        return $bom;
    }

    /**
     * Get BOM for a manufactured item
     *
     * @param string $parent Parent item
     * @param string $effectiveDate Optional effective date
     * @return BOM[]
     */
    public function getBOM(string $parent, ?string $effectiveDate = null): array
    {
        $date = $effectiveDate ?? date('Y-m-d');

        $sql = "SELECT * FROM bom
                WHERE parent = ?
                AND effectiveafter <= ?
                AND effectiveto >= ?
                ORDER BY sequence";

        $results = $this->db->fetchAll($sql, [$parent, $date, $date]);

        $bomEntries = [];
        foreach ($results as $result) {
            $bom = new BOM(
                $result['parent'],
                $result['component'],
                (float)$result['quantity'],
                (int)$result['sequence']
            );

            if ($result['workcentre']) {
                $bom->setWorkCentre($result['workcentre']);
            }
            $bom->setEffectiveAfter($result['effectiveafter']);
            $bom->setEffectiveTo($result['effectiveto']);
            $bom->setAutoIssue((bool)$result['autoissue']);
            if ($result['remark']) {
                $bom->setRemark($result['remark']);
            }

            $bomEntries[] = $bom;
        }

        return $bomEntries;
    }

    /**
     * Get multi-level BOM structure
     *
     * @param string $parent Top-level parent item
     * @param string $effectiveDate Optional effective date
     * @return array Multi-level BOM structure
     */
    public function getMultiLevelBOM(string $parent, ?string $effectiveDate = null): array
    {
        $date = $effectiveDate ?? date('Y-m-d');

        // Create temporary table for levels
        $this->createBOMLevelsTable($parent, $date);

        $sql = "SELECT l.level, b.parent, b.component, b.quantity, b.sequence,
                       b.workcentre, b.effectiveafter, b.effectiveto, b.autoissue, b.remark
                FROM bom b
                INNER JOIN bomlevels l ON b.parent = l.parent AND b.component = l.component
                WHERE l.toplevel = ?
                ORDER BY l.level, b.parent, b.sequence";

        $results = $this->db->fetchAll($sql, [$parent]);

        // Clean up temporary table
        $this->db->executeUpdate("DROP TEMPORARY TABLE IF EXISTS bomlevels");

        return $results;
    }

    /**
     * Update BOM entry
     *
     * @param string $parent Parent item
     * @param string $component Component item
     * @param array $updates Fields to update
     * @throws ManufacturingException
     */
    public function updateBOMEntry(string $parent, string $component, array $updates): void
    {
        $this->logger->info('Updating BOM entry', [
            'parent' => $parent,
            'component' => $component,
            'updates' => array_keys($updates)
        ]);

        // Check if entry exists
        if (!$this->bomEntryExists($parent, $component)) {
            throw new ManufacturingException("BOM entry not found: {$parent} -> {$component}");
        }

        $setParts = [];
        $params = [];

        foreach ($updates as $field => $value) {
            $setParts[] = "{$field} = ?";
            $params[] = $value;
        }

        $params[] = $parent;
        $params[] = $component;

        $sql = "UPDATE bom SET " . implode(', ', $setParts) . " WHERE parent = ? AND component = ?";
        $this->db->executeUpdate($sql, $params);

        $this->logger->info('BOM entry updated successfully');
    }

    /**
     * Delete BOM entry
     *
     * @param string $parent Parent item
     * @param string $component Component item
     * @throws ManufacturingException
     */
    public function deleteBOMEntry(string $parent, string $component): void
    {
        $this->logger->info('Deleting BOM entry', [
            'parent' => $parent,
            'component' => $component
        ]);

        // Check if entry exists
        if (!$this->bomEntryExists($parent, $component)) {
            throw new ManufacturingException("BOM entry not found: {$parent} -> {$component}");
        }

        $sql = "DELETE FROM bom WHERE parent = ? AND component = ?";
        $this->db->executeUpdate($sql, [$parent, $component]);

        $this->logger->info('BOM entry deleted successfully');
    }

    /**
     * Get where-used information for a component
     *
     * @param string $component Component item
     * @return array List of parent items that use this component
     */
    public function getWhereUsed(string $component): array
    {
        $sql = "SELECT parent, quantity, sequence, effectiveafter, effectiveto
                FROM bom
                WHERE component = ?
                ORDER BY parent";

        return $this->db->fetchAll($sql, [$component]);
    }

    /**
     * Validate that an item exists
     *
     * @param string $stockId
     * @throws ManufacturingException
     */
    private function validateItemExists(string $stockId): void
    {
        $sql = "SELECT stockid FROM stockmaster WHERE stockid = ?";
        $result = $this->db->fetchAssoc($sql, [$stockId]);

        if (!$result) {
            throw new ManufacturingException("Item {$stockId} not found");
        }
    }

    /**
     * Validate that an item is manufactured
     *
     * @param string $stockId
     * @throws ManufacturingException
     */
    private function validateManufacturedItem(string $stockId): void
    {
        $sql = "SELECT mbflag FROM stockmaster WHERE stockid = ?";
        $result = $this->db->fetchAssoc($sql, [$stockId]);

        if ($result['mbflag'] !== 'M') {
            throw new ManufacturingException("Item {$stockId} is not a manufactured item");
        }
    }

    /**
     * Get next sequence number for BOM entries
     *
     * @param string $parent Parent item
     * @return int
     */
    private function getNextSequence(string $parent): int
    {
        $sql = "SELECT MAX(sequence) + 1 as next_seq FROM bom WHERE parent = ?";
        $result = $this->db->fetchAssoc($sql, [$parent]);

        return $result['next_seq'] ?? 1;
    }

    /**
     * Save BOM entry to database
     *
     * @param BOM $bom
     */
    private function saveBOMEntry(BOM $bom): void
    {
        $sql = "INSERT INTO bom (
                    parent, component, quantity, sequence, workcentre,
                    effectiveafter, effectiveto, autoissue, remark
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->executeUpdate($sql, [
            $bom->getParent(),
            $bom->getComponent(),
            $bom->getQuantity(),
            $bom->getSequence(),
            $bom->getWorkCentre(),
            $bom->getEffectiveAfter(),
            $bom->getEffectiveTo(),
            $bom->isAutoIssue() ? 1 : 0,
            $bom->getRemark()
        ]);
    }

    /**
     * Check if BOM entry exists
     *
     * @param string $parent
     * @param string $component
     * @return bool
     */
    private function bomEntryExists(string $parent, string $component): bool
    {
        $sql = "SELECT COUNT(*) as count FROM bom WHERE parent = ? AND component = ?";
        $result = $this->db->fetchAssoc($sql, [$parent, $component]);

        return $result['count'] > 0;
    }

    /**
     * Create temporary table for multi-level BOM levels
     *
     * @param string $parent
     * @param string $date
     */
    private function createBOMLevelsTable(string $parent, string $date): void
    {
        // Drop if exists
        $this->db->executeUpdate("DROP TEMPORARY TABLE IF EXISTS bomlevels");

        // Create levels table
        $this->db->executeUpdate("CREATE TEMPORARY TABLE bomlevels (
            toplevel varchar(20),
            parent varchar(20),
            component varchar(20),
            level int,
            PRIMARY KEY (toplevel, parent, component)
        )");

        // Find top level assemblies
        $sql = "INSERT INTO bomlevels (toplevel, parent, component, level)
                SELECT ?, component, component, 1
                FROM bom
                WHERE parent = ?
                AND effectiveafter <= ?
                AND effectiveto >= ?";

        $this->db->executeUpdate($sql, [$parent, $parent, $date, $date]);

        // Build levels iteratively
        $level = 2;
        $componentsFound = true;

        while ($componentsFound) {
            $sql = "INSERT INTO bomlevels (toplevel, parent, component, level)
                    SELECT bl.toplevel, b.parent, b.component, ?
                    FROM bom b
                    INNER JOIN bomlevels bl ON b.parent = bl.component
                    WHERE bl.level = ?
                    AND b.effectiveafter <= ?
                    AND b.effectiveto >= ?
                    AND NOT EXISTS (
                        SELECT 1 FROM bomlevels bl2
                        WHERE bl2.toplevel = bl.toplevel
                        AND bl2.parent = b.parent
                        AND bl2.component = b.component
                    )";

            $rowsAffected = $this->db->executeUpdate($sql, [$level, $level - 1, $date, $date]);

            if ($rowsAffected == 0) {
                $componentsFound = false;
            }

            $level++;
        }
    }
}