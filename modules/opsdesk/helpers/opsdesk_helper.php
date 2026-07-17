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
 * Priority: commodity_code (warehouse) â†’ sku_code â†’ ITEM-{id}
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
        return $sku . ' â€” ' . $name;
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

    return implode(' Â· ', $parts);
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
 * Valid packing types as a key => label map.
 *
 * Reads from the manageable opsdesk_packing_types table when it exists and
 * has rows; otherwise falls back to the hardcoded defaults so the order
 * form, validation and labels keep working before the migration runs.
 *
 * @param bool $active_only
 * @return array
 */
function opsdesk_get_packing_types($active_only = false)
{
    $CI = &get_instance();

    $defaults = [
        'box'             => _l('opsdesk_packing_box'),
        'separate'        => _l('opsdesk_packing_separate'),
        'print_then_pack' => _l('opsdesk_packing_print_then_pack'),
        'print_then_ship' => _l('opsdesk_packing_print_then_ship'),
    ];

    if (!isset($CI->db) || !$CI->db->table_exists(db_prefix() . 'opsdesk_packing_types')) {
        return $defaults;
    }

    $CI->db->order_by('display_order', 'ASC');
    $CI->db->order_by('id', 'ASC');

    if ($active_only) {
        $CI->db->where('is_active', 1);
    }

    $rows = $CI->db->get(db_prefix() . 'opsdesk_packing_types')->result_array();

    if (empty($rows)) {
        return $defaults;
    }

    $types = [];
    foreach ($rows as $row) {
        $types[trim((string) $row['type_key'])] = trim((string) $row['name']);
    }

    return $types;
}

/**
 * Human label for packing type.
 *
 * @param string $type
 * @return string
 */
function opsdesk_get_packing_type_label($type)
{
    $types = opsdesk_get_packing_types(true);

    return $types[$type] ?? $type;
}

function opsdesk_get_order_statuses($active_only = true)
{
    $CI = &get_instance();
    $CI->load->model('opsdesk/opsdesk_product_statuses_model');

    $rows = $CI->opsdesk_product_statuses_model->get();
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

/**
 * Whether the current staff member may manage OpsDesk settings
 * (product/order statuses, etc.).
 *
 * @return bool
 */
function opsdesk_can_manage_settings()
{
    return staff_can('create', 'opsdesk')
        || staff_can('edit', 'opsdesk')
        || has_permission('opsdesk', '', 'create')
        || has_permission('opsdesk', '', 'edit')
        || is_admin();
}

/**
 * Absolute filesystem path for OpsDesk uploads.
 *
 * @return string
 */
function opsdesk_upload_path()
{
    return FCPATH . 'uploads/opsdesk/';
}

/**
 * Public URL for an OpsDesk stored file (value stored in DB).
 *
 * @param string $stored
 * @return string
 */
function opsdesk_file_url($stored)
{
    if (empty($stored)) {
        return '';
    }

    return base_url('uploads/opsdesk/' . $stored);
}

/**
 * Allowed upload extensions (PDF, images, common office docs).
 *
 * @return array
 */
function opsdesk_allowed_upload_types()
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
}

/**
 * Handle a single file upload to the OpsDesk upload directory.
 *
 * @param string $field
 * @return array{success:bool,file?:string,message?:string}
 */
function opsdesk_handle_upload($field)
{
    $CI = &get_instance();

    if (!isset($_FILES[$field]) || empty($_FILES[$field]['name'])) {
        return ['success' => false, 'message' => _l('opsdesk_upload_failed')];
    }

    $path = opsdesk_upload_path();
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    $config = [
        'upload_path'   => $path,
        'allowed_types' => implode('|', opsdesk_allowed_upload_types()),
        'max_size'      => file_upload_max_size() / 1024,
        'encrypt_name'  => true,
    ];

    $CI->load->library('upload');
    $CI->upload->initialize($config);

    if (!$CI->upload->do_upload($field)) {
        return ['success' => false, 'message' => $CI->upload->display_errors('', '')];
    }

    $data = $CI->upload->data();

    return ['success' => true, 'file' => $data['file_name']];
}

/**
 * Search CRM clients for the customer picker.
 *
 * @param string $q
 * @param int    $limit
 * @return array
 */
function opsdesk_search_clients($q = '', $limit = 50)
{
    $CI = &get_instance();

    $CI->db->select(db_prefix() . 'clients.userid as id, ' . db_prefix() . 'clients.company, ' . db_prefix() . 'clients.city, ' . db_prefix() . 'clients.phonenumber');
    $CI->db->from(db_prefix() . 'clients');

    if ($q !== '') {
        $q = $CI->db->escape_like_str($q);
        $CI->db->group_start();
        $CI->db->like(db_prefix() . 'clients.company', $q);
        $CI->db->or_like(db_prefix() . 'clients.city', $q);
        $CI->db->or_like(db_prefix() . 'clients.phonenumber', $q);
        $CI->db->group_end();
    }

    $CI->db->order_by(db_prefix() . 'clients.company', 'ASC');
    // $CI->db->limit((int) $limit);

    return $CI->db->get()->result_array();
}

/**
 * Active staff members for assignment dropdowns.
 *
 * @return array
 */
function opsdesk_get_staff_members()
{
    $CI = &get_instance();

    $CI->db->select('staffid, CONCAT(firstname, " ", lastname) as full_name', false);
    $CI->db->from(db_prefix() . 'staff');
    $CI->db->where(db_prefix() . 'staff.active', 1);
    $CI->db->order_by('full_name', 'ASC');

    return $CI->db->get()->result_array();
}

/**
 * Full name of an assigned staff member.
 *
 * @param int|null $staff_id
 * @return string
 */
function opsdesk_get_assigned_name($staff_id)
{
    if (empty($staff_id)) {
        return '';
    }

    return get_staff_full_name((int) $staff_id);
}

/**
 * Human label for an order priority value.
 *
 * @param int $priority
 * @return string
 */
function opsdesk_get_priority_label($priority)
{
    return (int) $priority === 1 ? _l('opsdesk_priority_high') : _l('opsdesk_priority_normal');
}

/**
 * HTML badge for an order priority value (empty for Normal).
 *
 * @param int $priority
 * @return string
 */
function opsdesk_get_priority_badge($priority)
{
    if ((int) $priority !== 1) {
        return '';
    }

    return '<span class="label label-danger">' . e(_l('opsdesk_priority_high_badge')) . '</span>';
}

/**
 * Staff members who should receive Operations notifications.
 *
 * Admins plus anyone with view permission on opsdesk_orders.
 * `staff_model->get('', true)` returns arrays (`$s['staffid']`).
 *
 * @return array<int, array{staffid:int,firstname:string,lastname:string}>
 */
function opsdesk_get_operations_staff()
{
    $CI = &get_instance();

    if (!isset($CI->staff_model)) {
        $CI->load->model('staff_model');
    }

    $staff = $CI->staff_model->get('', true);
    $ops   = [];

    foreach ($staff as $s) {
        if (!isset($s['staffid'])) {
            continue;
        }

        if (is_admin($s['staffid']) || staff_can('view', 'opsdesk_orders', $s['staffid'])) {
            $ops[] = [
                'staffid'   => (int) $s['staffid'],
                'firstname' => $s['firstname'] ?? '',
                'lastname'  => $s['lastname'] ?? '',
            ];
        }
    }

    return $ops;
}

/**
 * Notify Operations staff (and admins) about a newly placed order.
 *
 * @param int $order_id
 * @param int $created_by
 * @return void
 */
function opsdesk_notify_new_order($order_id, $created_by)
{
    $CI = &get_instance();

    if (!isset($CI->opsdesk_orders_model)) {
        $CI->load->model('opsdesk/opsdesk_orders_model');
    }

    $order = $CI->opsdesk_orders_model->get($order_id);
    if (!$order) {
        return;
    }

    $creator = get_staff_full_name((int) $created_by);
    $prefix  = (int) $order->priority === 1 ? _l('opsdesk_priority_high_prefix') : '';
    $message = $prefix . _l('opsdesk_notify_new_order', [
        (int) $order->id,
        e($order->combo_name),
        (int) $order->quantity,
        e($creator),
    ]);

    foreach (opsdesk_get_operations_staff() as $staff) {
        if ((int) $staff['staffid'] === (int) $created_by) {
            continue;
        }

        add_notification([
            'description' => $message,
            'touserid'    => (int) $staff['staffid'],
            'link'        => 'admin/opsdesk/order/' . (int) $order->id,
            'fromcompany' => 1,
        ]);
    }
}

/**
 * Notify the order creator about a status change.
 *
 * @param int    $order_id
 * @param string $new_status
 * @param int    $changed_by
 * @return void
 */
function opsdesk_notify_status_change($order_id, $new_status, $changed_by)
{
    $CI = &get_instance();

    if (!isset($CI->opsdesk_orders_model)) {
        $CI->load->model('opsdesk/opsdesk_orders_model');
    }

    $order = $CI->opsdesk_orders_model->get($order_id);
    if (!$order) {
        return;
    }

    $created_by = (int) $order->created_by;
    if ($created_by === (int) $changed_by) {
        return;
    }

    $prefix       = (int) $order->priority === 1 ? _l('opsdesk_priority_high_prefix') : '';
    $status_label = ucfirst(str_replace('_', ' ', $new_status));
    $message      = $prefix . _l('opsdesk_notify_status_updated', [
        (int) $order->id,
        e($order->combo_name),
        e($status_label),
    ]);

    add_notification([
        'description' => $message,
        'touserid'    => $created_by,
        'link'        => 'admin/opsdesk/order/' . (int) $order->id,
        'fromcompany' => 1,
    ]);
}

/**
 * Notify the relevant party about a cancellation.
 *
 * If cancelled by the creator, notify Operations/Admin.
 * If cancelled by Operations/Admin, notify the creator.
 *
 * @param int $order_id
 * @param int $cancelled_by
 * @return void
 */
function opsdesk_notify_cancellation($order_id, $cancelled_by)
{
    $CI = &get_instance();

    if (!isset($CI->opsdesk_orders_model)) {
        $CI->load->model('opsdesk/opsdesk_orders_model');
    }

    $order = $CI->opsdesk_orders_model->get($order_id);
    if (!$order) {
        return;
    }

    $created_by    = (int) $order->created_by;
    $creator_name  = get_staff_full_name($created_by);
    $canceller_name = get_staff_full_name((int) $cancelled_by);

    $prefix = (int) $order->priority === 1 ? _l('opsdesk_priority_high_prefix') : '';

    if ($created_by === (int) $cancelled_by) {
        $message = $prefix . _l('opsdesk_notify_cancelled_by_sales', [
            (int) $order->id,
            e($order->combo_name),
            e($creator_name),
        ]);

        foreach (opsdesk_get_operations_staff() as $staff) {
            if ((int) $staff['staffid'] === $created_by) {
                continue;
            }

            add_notification([
                'description' => $message,
                'touserid'    => (int) $staff['staffid'],
                'link'        => 'admin/opsdesk/order/' . (int) $order->id,
                'fromcompany' => 1,
            ]);
        }

        return;
    }

    $message = $prefix . _l('opsdesk_notify_cancelled_by_ops', [
        (int) $order->id,
        e($order->combo_name),
        e($canceller_name),
    ]);

    add_notification([
        'description' => $message,
        'touserid'    => $created_by,
        'link'        => 'admin/opsdesk/order/' . (int) $order->id,
        'fromcompany' => 1,
    ]);
}
