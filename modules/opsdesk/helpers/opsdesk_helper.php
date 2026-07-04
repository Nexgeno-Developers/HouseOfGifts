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

/**
 * CSS label class for order status badge.
 *
 * @param string $status
 * @return string
 */
function opsdesk_get_order_status_class($status)
{
    $map = [
        'pending'     => 'label-warning',
        'in_progress' => 'label-info',
        'packed'      => 'label-primary',
        'shipped'     => 'label-default',
        'completed'   => 'label-success',
        'cancelled'   => 'label-danger',
    ];

    return $map[$status] ?? 'label-default';
}

/**
 * Human label for order status.
 *
 * @param string $status
 * @return string
 */
function opsdesk_get_order_status_label($status)
{
    $key = 'opsdesk_order_status_' . $status;

    return _l($key);
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
    $map = [
        'pending'     => ['in_progress', 'cancelled'],
        'in_progress' => ['packed', 'cancelled'],
        'packed'      => ['shipped', 'cancelled'],
        'shipped'     => ['completed'],
    ];

    return $map[$status] ?? [];
}
