<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Whether the Warehouse (Inventory) module is installed and active.
 */
function opsdesk_is_warehouse_module_active()
{
    $CI = &get_instance();

    return isset($CI->app_modules) && $CI->app_modules->is_active('warehouse');
}

/**
 * Resolve the canonical SKU for a tblitems product row.
 *
 * Priority: commodity_code (warehouse) → sku_code → ITEM-{id}
 *
 * @param array|object $item
 * @return string
 */
function opsdesk_resolve_product_sku($item)
{
    if (is_object($item)) {
        $item = (array) $item;
    }

    $commodity_code = trim($item['commodity_code'] ?? '');
    if ($commodity_code !== '') {
        return $commodity_code;
    }

    $sku_code = trim($item['sku_code'] ?? '');
    if ($sku_code !== '') {
        return $sku_code;
    }

    $id = (int) ($item['id'] ?? $item['itemid'] ?? 0);
    if ($id > 0) {
        return 'ITEM-' . $id;
    }

    return '';
}

/**
 * Build a human-readable product label for dropdowns and tables.
 *
 * @param array|object $item
 * @return string
 */
function opsdesk_get_product_label($item)
{
    if (is_object($item)) {
        $item = (array) $item;
    }

    $sku     = opsdesk_resolve_product_sku($item);
    $name    = trim($item['commodity_name'] ?? '');
    $desc    = trim($item['description'] ?? '');

    if ($name === '') {
        $name = $desc;
    }

    if ($sku !== '' && $name !== '' && stripos($name, $sku) === false) {
        return $sku . ' — ' . $name;
    }

    if ($name !== '') {
        return $name;
    }

    return $sku !== '' ? $sku : _l('opsdesk_unknown_product');
}

/**
 * Subtext for selectpicker (group, barcode, stock hint).
 *
 * @param array $item
 * @return string
 */
function opsdesk_get_product_subtext($item)
{
    $parts = [];

    if (!empty($item['group_name'])) {
        $parts[] = $item['group_name'];
    }

    if (!empty($item['commodity_barcode'])) {
        $parts[] = $item['commodity_barcode'];
    }

    if (isset($item['warehouse_stock']) && $item['warehouse_stock'] !== null) {
        $parts[] = _l('opsdesk_stock') . ': ' . app_format_number($item['warehouse_stock']);
    }

    return implode(' · ', $parts);
}

/**
 * Unified product list for OpsDesk dropdowns.
 *
 * Reads from tblitems (shared by Perfex invoices and the Warehouse module).
 * When Warehouse is active, enriches rows with summed stock from tblinventory_manage
 * and applies the same parent-variant exclusion the Warehouse module uses.
 *
 * @param array $options
 * @return array
 */
function opsdesk_get_products_for_dropdown($options = [])
{
    $CI = &get_instance();

    $active_only        = $options['active_only'] ?? true;
    $exclude_variations = $options['exclude_variation_parents'] ?? true;
    $inventory_only     = $options['inventory_capable_only'] ?? false;

    $CI->db->select(
        db_prefix() . 'items.id as itemid,' .
        db_prefix() . 'items.id,' .
        db_prefix() . 'items.description,' .
        db_prefix() . 'items.commodity_code,' .
        db_prefix() . 'items.commodity_name,' .
        db_prefix() . 'items.sku_code,' .
        db_prefix() . 'items.commodity_barcode,' .
        db_prefix() . 'items.can_be_inventory,' .
        db_prefix() . 'items.active,' .
        db_prefix() . 'items_groups.name as group_name'
    );
    $CI->db->from(db_prefix() . 'items');
    $CI->db->join(
        db_prefix() . 'items_groups',
        db_prefix() . 'items_groups.id = ' . db_prefix() . 'items.group_id',
        'left'
    );

    if ($active_only) {
        $CI->db->where(db_prefix() . 'items.active', 1);
    }

    if ($exclude_variations) {
        $CI->db->where(
            db_prefix() . 'items.id NOT IN (SELECT DISTINCT parent_id FROM ' . db_prefix() . 'items WHERE parent_id IS NOT NULL AND parent_id != 0)',
            null,
            false
        );
    }

    if ($inventory_only) {
        $CI->db->where(db_prefix() . 'items.can_be_inventory', 'can_be_inventory');
    }

    $CI->db->order_by(db_prefix() . 'items.commodity_code', 'ASC');
    $CI->db->order_by(db_prefix() . 'items.description', 'ASC');

    $products = $CI->db->get()->result_array();

    if (opsdesk_is_warehouse_module_active() && $CI->db->table_exists(db_prefix() . 'inventory_manage')) {
        $stock_map = opsdesk_get_warehouse_stock_map();
        foreach ($products as &$product) {
            $id = (int) $product['itemid'];
            $product['warehouse_stock'] = $stock_map[$id] ?? 0.0;
            $product['sku']             = opsdesk_resolve_product_sku($product);
            $product['label']           = opsdesk_get_product_label($product);
            $product['subtext']         = opsdesk_get_product_subtext($product);
        }
        unset($product);
    } else {
        foreach ($products as &$product) {
            $product['warehouse_stock'] = null;
            $product['sku']             = opsdesk_resolve_product_sku($product);
            $product['label']           = opsdesk_get_product_label($product);
            $product['subtext']         = opsdesk_get_product_subtext($product);
        }
        unset($product);
    }

    return $products;
}

/**
 * Fetch a single product row by tblitems.id.
 *
 * @param int $product_item_id
 * @return array|null
 */
function opsdesk_get_product_by_id($product_item_id)
{
    if (!is_numeric($product_item_id)) {
        return null;
    }

    $CI = &get_instance();

    $CI->db->select(
        db_prefix() . 'items.id as itemid,' .
        db_prefix() . 'items.id,' .
        db_prefix() . 'items.description,' .
        db_prefix() . 'items.commodity_code,' .
        db_prefix() . 'items.commodity_name,' .
        db_prefix() . 'items.sku_code,' .
        db_prefix() . 'items.commodity_barcode,' .
        db_prefix() . 'items.can_be_inventory'
    );
    $CI->db->from(db_prefix() . 'items');
    $CI->db->where(db_prefix() . 'items.id', (int) $product_item_id);

    $row = $CI->db->get()->row_array();

    if (!$row) {
        return null;
    }

    $row['sku']   = opsdesk_resolve_product_sku($row);
    $row['label'] = opsdesk_get_product_label($row);

    return $row;
}

/**
 * Sum warehouse stock per commodity_id from tblinventory_manage.
 *
 * @return array<int, float> commodity_id => total stock
 */
function opsdesk_get_warehouse_stock_map()
{
    $CI = &get_instance();

    if (!$CI->db->table_exists(db_prefix() . 'inventory_manage')) {
        return [];
    }

    $CI->db->select('commodity_id, SUM(CAST(inventory_number AS DECIMAL(15,4))) as total_stock', false);
    $CI->db->from(db_prefix() . 'inventory_manage');
    $CI->db->group_by('commodity_id');

    $rows = $CI->db->get()->result_array();
    $map  = [];

    foreach ($rows as $row) {
        $map[(int) $row['commodity_id']] = (float) $row['total_stock'];
    }

    return $map;
}

/**
 * Total warehouse stock for one commodity (tblitems.id).
 *
 * @param int $commodity_id
 * @return float
 */
function opsdesk_get_warehouse_stock_total($commodity_id)
{
    if (!is_numeric($commodity_id)) {
        return 0.0;
    }

    $map = opsdesk_get_warehouse_stock_map();

    return $map[(int) $commodity_id] ?? 0.0;
}

/**
 * Deduct stock from warehouse inventory_manage rows (FIFO by id).
 *
 * @param int   $commodity_id
 * @param float $quantity
 * @return bool
 */
function opsdesk_deduct_warehouse_stock($commodity_id, $quantity)
{
    if (!opsdesk_is_warehouse_module_active() || $quantity <= 0 || !is_numeric($commodity_id)) {
        return false;
    }

    $CI = &get_instance();

    if (!$CI->db->table_exists(db_prefix() . 'inventory_manage')) {
        return false;
    }

    $remaining = (float) $quantity;

    $CI->db->select('id, inventory_number');
    $CI->db->from(db_prefix() . 'inventory_manage');
    $CI->db->where('commodity_id', (int) $commodity_id);
    $CI->db->order_by('id', 'ASC');
    $rows = $CI->db->get()->result_array();

    foreach ($rows as $row) {
        if ($remaining <= 0) {
            break;
        }

        $current = (float) $row['inventory_number'];
        if ($current <= 0) {
            continue;
        }

        $deduct = min($current, $remaining);
        $new_qty = $current - $deduct;

        $CI->db->where('id', (int) $row['id']);
        $CI->db->update(db_prefix() . 'inventory_manage', [
            'inventory_number' => $new_qty,
        ]);

        $remaining -= $deduct;
    }

    return $remaining <= 0;
}

/**
 * Valid packing type keys.
 *
 * @return array
 */
function opsdesk_get_packing_types()
{
    return [
        'box'             => _l('opsdesk_packing_box'),
        'separate'        => _l('opsdesk_packing_separate'),
        'print_then_pack' => _l('opsdesk_packing_print_then_pack'),
        'print_then_ship' => _l('opsdesk_packing_print_then_ship'),
    ];
}

/**
 * Human label for packing type.
 *
 * @param string $type
 * @return string
 */
function opsdesk_get_packing_type_label($type)
{
    $types = opsdesk_get_packing_types();

    return $types[$type] ?? $type;
}

function opsdesk_get_order_statuses($active_only = true)
{
    $CI = &get_instance();
    $CI->load->model('warehouse/warehouse_model');

    $rows = $CI->warehouse_model->get_product_statuses();
    $statuses = [];

    if (!empty($rows)) {
        foreach ($rows as $row) {
            if ($active_only && empty($row['is_active'])) {
                continue;
            }

            $status_key = trim((string) ($row['status_key'] ?? ''));
            if ($status_key === '') {
                continue;
            }

            $statuses[] = [
                'status_key'    => $status_key,
                'name'          => trim((string) ($row['name'] ?? '')),
                'description'   => trim((string) ($row['description'] ?? '')),
                'display_order' => (int) ($row['display_order'] ?? 0),
            ];
        }
    }

    if (empty($statuses)) {
        $statuses = [
            ['status_key' => 'pending', 'name' => _l('opsdesk_order_status_pending'), 'description' => '', 'display_order' => 1],
            ['status_key' => 'in_progress', 'name' => _l('opsdesk_order_status_in_progress'), 'description' => '', 'display_order' => 2],
            ['status_key' => 'packed', 'name' => _l('opsdesk_order_status_packed'), 'description' => '', 'display_order' => 3],
            ['status_key' => 'shipped', 'name' => _l('opsdesk_order_status_shipped'), 'description' => '', 'display_order' => 4],
            ['status_key' => 'completed', 'name' => _l('opsdesk_order_status_completed'), 'description' => '', 'display_order' => 5],
            ['status_key' => 'cancelled', 'name' => _l('opsdesk_order_status_cancelled'), 'description' => '', 'display_order' => 6],
        ];
    }

    return $statuses;
}

function opsdesk_get_order_status_option_keys($include_cancelled = false)
{
    $statuses = opsdesk_get_order_statuses(true);
    $keys = [];

    foreach ($statuses as $status) {
        if (!$include_cancelled && ($status['status_key'] === 'cancelled')) {
            continue;
        }

        $keys[] = $status['status_key'];
    }

    return $keys;
}

/**
 * CSS label class for order status badge.
 *
 * @param string $status
 * @return string
 */
function opsdesk_get_order_status_class($status)
{
    $status_key = strtolower(trim((string) $status));
    if ($status_key === '') {
        return 'label-default';
    }

    $statuses = opsdesk_get_order_statuses(true);
    $palette = ['label-warning', 'label-info', 'label-primary', 'label-default', 'label-success', 'label-danger'];

    foreach ($statuses as $item) {
        if (strtolower((string) $item['status_key']) === $status_key) {
            $display_order = isset($item['display_order']) && (int) $item['display_order'] > 0
                ? (int) $item['display_order']
                : 1;

            return $palette[($display_order - 1) % count($palette)] ?? 'label-default';
        }
    }

    return 'label-default';
}

/**
 * Human label for order status.
 *
 * @param string $status
 * @return string
 */
function opsdesk_get_order_status_label($status)
{
    $status_key = trim((string) $status);
    if ($status_key === '') {
        return '';
    }

    foreach (opsdesk_get_order_statuses(true) as $item) {
        if (strtolower((string) $item['status_key']) === strtolower($status_key)) {
            return $item['name'] !== '' ? $item['name'] : _l('opsdesk_order_status_' . $status_key);
        }
    }

    $translated = _l('opsdesk_order_status_' . $status_key);

    return $status_key;
}

/**
 * Whether staff can view orders globally.
 *
 * @return bool
 */
function opsdesk_can_view_all_orders()
{
    return staff_can('view', 'opsdesk_orders')
        || has_permission('opsdesk_orders', '', 'view')
        || is_admin();
}

/**
 * Whether staff can view any orders (own or global).
 *
 * @return bool
 */
function opsdesk_can_view_orders()
{
    return opsdesk_can_view_all_orders()
        || staff_can('view_own', 'opsdesk_orders')
        || has_permission('opsdesk_orders', '', 'view_own');
}

/**
 * Whether staff can create orders.
 *
 * @return bool
 */
function opsdesk_can_create_orders()
{
    return staff_can('create', 'opsdesk_orders')
        || has_permission('opsdesk_orders', '', 'create')
        || is_admin();
}

/**
 * Whether staff can edit orders (status updates).
 *
 * @return bool
 */
function opsdesk_can_edit_orders()
{
    return staff_can('edit', 'opsdesk_orders')
        || has_permission('opsdesk_orders', '', 'edit')
        || is_admin();
}

/**
 * Next allowed order statuses for UI.
 *
 * @param string $status
 * @return array
 */
function opsdesk_get_next_order_statuses($status)
{
    $statuses = opsdesk_get_order_status_option_keys(false);
    $index = array_search($status, $statuses, true);

    if ($index === false) {
        $map = [
            'pending'     => ['in_progress', 'cancelled'],
            'in_progress' => ['packed', 'cancelled'],
            'packed'      => ['shipped', 'cancelled'],
            'shipped'     => ['completed'],
        ];

        return $map[$status] ?? [];
    }

    return array_values(array_slice($statuses, $index + 1));
}

function opsdesk_get_default_next_status($status)
{
    $next = opsdesk_get_next_order_statuses($status);

    return $next[0] ?? '';
}
