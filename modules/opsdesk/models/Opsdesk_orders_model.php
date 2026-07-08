<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Opsdesk_orders_model extends App_Model
{
    private $table_orders;
    private $table_items;
    private $table_log;
    private $table_inventory;

    /** @var array<string, array<string>> */
    private $valid_transitions = [
        'pending'     => ['in_progress', 'cancelled'],
        'in_progress' => ['packed', 'cancelled'],
        'packed'      => ['shipped', 'cancelled'],
        'shipped'     => ['completed'],
    ];

    public function __construct()
    {
        parent::__construct();
        $CI = &get_instance();
        $CI->load->helper(OPSDESK_MODULE_NAME . '/opsdesk');
        $this->load->model('opsdesk/opsdesk_combos_model');
        $this->load->model('opsdesk/opsdesk_inventory_model');

        $prefix = db_prefix();
        $this->table_orders    = $prefix . 'opsdesk_orders';
        $this->table_items     = $prefix . 'opsdesk_order_items';
        $this->table_log       = $prefix . 'opsdesk_order_status_log';
        $this->table_inventory = $prefix . 'opsdesk_inventory';
    }

    /**
     * Get order(s) with optional ownership scope.
     *
     * @param int|string $id
     * @param array      $options
     * @return object|array|null
     */
    public function get($id = '', $options = [])
    {
        $staff_id   = isset($options['staff_id']) ? (int) $options['staff_id'] : null;
        $own_only   = !empty($options['own_only']);
        $status     = $options['status'] ?? null;

        if (is_numeric($id)) {
            $this->db->where($this->table_orders . '.id', (int) $id);
            $this->apply_list_joins();

            if ($own_only && $staff_id) {
                $this->db->where($this->table_orders . '.created_by', $staff_id);
            }

            $order = $this->db->get($this->table_orders)->row();

            if ($order) {
                $order->items         = $this->get_order_items((int) $order->id);
                $order->status_log    = $this->get_status_log((int) $order->id);
                $order->creator_name  = get_staff_full_name((int) $order->created_by);
            }

            return $order;
        }

        $this->apply_list_joins();

        if ($own_only && $staff_id) {
            $this->db->where($this->table_orders . '.created_by', $staff_id);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $this->db->where($this->table_orders . '.status', $status);
        }

        $this->db->order_by($this->table_orders . '.created_at', 'DESC');

        return $this->db->get($this->table_orders)->result_array();
    }

    /**
     * Build order line items from combo definition and optional overrides.
     *
     * @param int   $combo_id
     * @param int   $quantity
     * @param array $overrides
     * @return array{success:bool,message?:string,items?:array}
     */
    public function build_order_items($combo_id, $quantity, $overrides = [])
    {
        $combo = $this->opsdesk_combos_model->get($combo_id);
        if (!$combo || (int) $combo->status !== 1) {
            return ['success' => false, 'message' => _l('opsdesk_combo_not_found')];
        }

        if ($quantity < 1) {
            return ['success' => false, 'message' => _l('opsdesk_invalid_request')];
        }

        $combo_items   = $this->opsdesk_combos_model->get_combo_items($combo_id);
        $substitutions = $overrides['substitutions'] ?? [];
        $removed       = array_map('strval', $overrides['removed'] ?? []);
        $added         = $overrides['added'] ?? [];
        $items         = [];

        foreach ($combo_items as $combo_item) {
            $combo_item_id = (string) $combo_item['id'];

            if (in_array($combo_item_id, $removed, true)) {
                continue;
            }

            $qty_per_unit = (float) $combo_item['quantity_per_unit'];
            if (isset($overrides['quantities'][$combo_item_id])) {
                $required_total = (float) $overrides['quantities'][$combo_item_id];
                if ($quantity > 0) {
                    $qty_per_unit = $required_total / $quantity;
                }
            }

            $product_id   = $combo_item['product_item_id'] ? (int) $combo_item['product_item_id'] : null;
            $sku          = $combo_item['sku'];
            $product_name = $combo_item['product_name'];
            $is_sub       = 0;
            $original_id  = null;

            if (isset($substitutions[$combo_item_id]) && is_numeric($substitutions[$combo_item_id])) {
                $sub_product = opsdesk_get_product_by_id((int) $substitutions[$combo_item_id]);
                if (!$sub_product) {
                    return ['success' => false, 'message' => _l('opsdesk_invalid_substitution')];
                }

                $product_id   = (int) $sub_product['id'];
                $sku          = $sub_product['sku'];
                $product_name = $sub_product['label'];
                $is_sub       = 1;
                $original_id  = (int) $combo_item['id'];
            }

            $items[] = [
                'combo_item_id'     => (int) $combo_item['id'],
                'product_item_id'   => $product_id,
                'sku'               => $sku,
                'product_name'      => $product_name,
                'quantity_per_unit' => $qty_per_unit,
                'is_substitution'   => $is_sub,
                'original_item_id'  => $original_id,
            ];
        }

        foreach ($added as $added_item) {
            if (empty($added_item['sku'])) {
                continue;
            }

            $qty_per_unit = isset($added_item['quantity_per_unit'])
                ? (float) $added_item['quantity_per_unit']
                : 1.0;

            if (isset($added_item['required_quantity']) && $quantity > 0) {
                $qty_per_unit = (float) $added_item['required_quantity'] / $quantity;
            }

            $items[] = [
                'product_item_id'   => !empty($added_item['product_item_id']) ? (int) $added_item['product_item_id'] : null,
                'sku'               => trim($added_item['sku']),
                'product_name'      => trim($added_item['product_name'] ?? $added_item['sku']),
                'quantity_per_unit' => $qty_per_unit,
                'is_substitution'   => !empty($added_item['is_substitution']) ? 1 : 0,
                'original_item_id'  => !empty($added_item['original_item_id']) ? (int) $added_item['original_item_id'] : null,
            ];
        }

        if (count($items) === 0) {
            return ['success' => false, 'message' => _l('opsdesk_no_order_items')];
        }

        return ['success' => true, 'items' => $items, 'combo' => $combo];
    }

    /**
     * Check stock for proposed order items.
     *
     * @param array $order_items
     * @param int   $quantity
     * @return array
     */
    public function check_items_stock($order_items, $quantity)
    {
        $components     = [];
        $is_fulfillable = true;

        foreach ($order_items as $item) {
            $required  = (float) $item['quantity_per_unit'] * (float) $quantity;
            $available = $this->opsdesk_inventory_model->get_available_for_combo_item(
                $item['sku'],
                $item['product_item_id'] ?? null
            );
            $sufficient = $available >= $required;

            if (!$sufficient) {
                $is_fulfillable = false;
            }

            $components[] = [
                'combo_item_id'      => $item['combo_item_id'] ?? null,
                'sku'                => $item['sku'],
                'product_name'       => $item['product_name'],
                'product_item_id'    => $item['product_item_id'] ?? null,
                'quantity_per_unit'  => (float) $item['quantity_per_unit'],
                'required_quantity'  => $required,
                'available_stock'    => $available,
                'is_sufficient'      => $sufficient,
                'is_substitution'    => !empty($item['is_substitution']),
            ];
        }

        return [
            'is_fulfillable' => $is_fulfillable && count($components) > 0,
            'components'     => $components,
        ];
    }

    /**
     * Create order and reserve stock atomically.
     *
     * @param array $order_data
     * @param array $order_items
     * @return array
     */
    public function create_order_with_reservation($order_data, $order_items)
    {
        $quantity = (int) ($order_data['quantity'] ?? 0);
        if ($quantity < 1 || empty($order_items)) {
            return ['success' => false, 'message' => _l('opsdesk_invalid_request')];
        }

        $stock_check = $this->check_items_stock($order_items, $quantity);
        if (!$stock_check['is_fulfillable']) {
            return ['success' => false, 'message' => _l('opsdesk_stock_insufficient')];
        }

        foreach ($order_items as $item) {
            $this->ensure_inventory_row($item['sku'], $item['product_item_id'] ?? null);
        }

        $this->db->trans_begin();

        try {
            foreach ($order_items as $item) {
                $this->db->query(
                    'SELECT id, quantity_available, quantity_reserved
                     FROM ' . $this->table_inventory . '
                     WHERE sku = ? FOR UPDATE',
                    [$item['sku']]
                );
            }

            foreach ($order_items as $item) {
                $inv = $this->opsdesk_inventory_model->get_by_sku($item['sku']);
                if (!$inv) {
                    $this->db->trans_rollback();

                    return ['success' => false, 'message' => _l('opsdesk_stock_no_longer_available', $item['sku'])];
                }

                $net_available = $this->opsdesk_inventory_model->get_available_for_combo_item(
                    $item['sku'],
                    $item['product_item_id'] ?? null
                );
                $required = (float) $item['quantity_per_unit'] * $quantity;

                if ($net_available < $required) {
                    $this->db->trans_rollback();

                    return ['success' => false, 'message' => _l('opsdesk_stock_no_longer_available', $item['sku'])];
                }
            }

            $order_data['created_at'] = date('Y-m-d H:i:s');
            $order_data['updated_at'] = date('Y-m-d H:i:s');
            $order_data['status']     = 'pending';

            $this->db->insert($this->table_orders, $order_data);
            $order_id = (int) $this->db->insert_id();

            if (!$order_id) {
                $this->db->trans_rollback();

                return ['success' => false, 'message' => _l('opsdesk_order_create_failed')];
            }

            foreach ($order_items as $item) {
                $reserved = (float) $item['quantity_per_unit'] * $quantity;

                $this->db->insert($this->table_items, [
                    'order_id'          => $order_id,
                    'product_item_id'   => $item['product_item_id'] ?? null,
                    'sku'               => $item['sku'],
                    'product_name'      => $item['product_name'],
                    'quantity_per_unit' => $item['quantity_per_unit'],
                    'quantity_reserved' => $reserved,
                    'is_substitution'   => !empty($item['is_substitution']) ? 1 : 0,
                    'original_item_id'  => $item['original_item_id'] ?? null,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);

                $this->db->query(
                    'UPDATE ' . $this->table_inventory . '
                     SET quantity_reserved = quantity_reserved + ?
                     WHERE sku = ?',
                    [$reserved, $item['sku']]
                );
            }

            $this->log_status_change($order_id, null, 'pending', (int) $order_data['created_by']);

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();

                return ['success' => false, 'message' => _l('opsdesk_order_create_failed')];
            }

            $this->db->trans_commit();

            log_activity('OpsDesk Order Created [ID:' . $order_id . ']');

            return ['success' => true, 'order_id' => $order_id];
        } catch (Exception $e) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('opsdesk_transaction_failed') . ' ' . $e->getMessage()];
        }
    }

    /**
     * Cancel order and release reserved stock.
     *
     * @param int $order_id
     * @param int $staff_id
     * @param bool $is_global_cancel
     * @return array
     */
    public function cancel_order($order_id, $staff_id, $is_global_cancel = false)
    {
        $order = $this->get($order_id);
        if (!$order) {
            return ['success' => false, 'message' => _l('opsdesk_order_not_found')];
        }

        if (!$is_global_cancel) {
            if ((int) $order->created_by !== (int) $staff_id) {
                return ['success' => false, 'message' => _l('access_denied')];
            }

            if ($order->status !== 'pending') {
                return ['success' => false, 'message' => _l('opsdesk_order_cannot_cancel')];
            }
        } else {
            if (!in_array($order->status, ['pending', 'in_progress', 'packed'], true)) {
                return ['success' => false, 'message' => _l('opsdesk_order_cannot_cancel')];
            }
        }

        $this->db->trans_begin();

        try {
            foreach ($order->items as $item) {
                $this->db->query(
                    'UPDATE ' . $this->table_inventory . '
                     SET quantity_reserved = GREATEST(0, quantity_reserved - ?)
                     WHERE sku = ?',
                    [(float) $item['quantity_reserved'], $item['sku']]
                );
            }

            $this->db->where('id', (int) $order_id);
            $this->db->update($this->table_orders, [
                'status'       => 'cancelled',
                'cancelled_by' => (int) $staff_id,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'updated_by'   => (int) $staff_id,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $this->log_status_change((int) $order_id, $order->status, 'cancelled', (int) $staff_id);

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();

                return ['success' => false, 'message' => _l('opsdesk_order_cancel_failed')];
            }

            $this->db->trans_commit();

            log_activity('OpsDesk Order Cancelled [ID:' . $order_id . ']');

            return ['success' => true];
        } catch (Exception $e) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update order status with validation and stock effects.
     *
     * @param int    $order_id
     * @param string $new_status
     * @param int    $staff_id
     * @param array  $extra
     * @return array
     */
    public function update_status($order_id, $new_status, $staff_id, $extra = [])
    {
        $order = $this->get($order_id);
        if (!$order) {
            return ['success' => false, 'message' => _l('opsdesk_order_not_found')];
        }

        $new_status = trim($new_status);
        $current    = $order->status;

        if ($new_status === $current) {
            return ['success' => true];
        }

        if ($new_status === 'cancelled') {
            return $this->cancel_order($order_id, $staff_id, true);
        }

        if (!$this->is_valid_transition($current, $new_status)) {
            return ['success' => false, 'message' => _l('opsdesk_invalid_status_transition')];
        }

        $this->db->trans_begin();

        try {
            if ($new_status === 'shipped') {
                foreach ($order->items as $item) {
                    $qty = (float) $item['quantity_reserved'];

                    $this->db->query(
                        'UPDATE ' . $this->table_inventory . '
                         SET quantity_available = GREATEST(0, quantity_available - ?),
                             quantity_reserved = GREATEST(0, quantity_reserved - ?)
                         WHERE sku = ?',
                        [$qty, $qty, $item['sku']]
                    );

                    if (!empty($item['product_item_id'])) {
                        opsdesk_deduct_warehouse_stock((int) $item['product_item_id'], $qty);
                    }
                }
            }

            $update = [
                'status'     => $new_status,
                'updated_by' => (int) $staff_id,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if (!empty($extra['packing_type']) && in_array($current, ['pending', 'in_progress'], true)) {
                $update['packing_type'] = $extra['packing_type'];
            }

            $this->db->where('id', (int) $order_id);
            $this->db->update($this->table_orders, $update);

            $this->log_status_change(
                (int) $order_id,
                $current,
                $new_status,
                (int) $staff_id,
                $extra['notes'] ?? null
            );

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();

                return ['success' => false, 'message' => _l('opsdesk_status_update_failed')];
            }

            $this->db->trans_commit();

            log_activity('OpsDesk Order Status Updated [ID:' . $order_id . ', Status:' . $new_status . ']');

            return ['success' => true];
        } catch (Exception $e) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete order record (admin only, no stock side-effects expected).
     *
     * @param int $order_id
     * @return bool
     */
    public function delete($order_id)
    {
        if (!is_numeric($order_id)) {
            return false;
        }

        $order = $this->get($order_id);
        if (!$order) {
            return false;
        }

        if (!in_array($order->status, ['cancelled', 'completed'], true)) {
            return false;
        }

        $this->db->where('id', (int) $order_id);
        $this->db->delete($this->table_orders);

        return $this->db->affected_rows() > 0;
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function get_order_items($order_id)
    {
        $this->db->where('order_id', (int) $order_id);
        $this->db->order_by('id', 'ASC');

        return $this->db->get($this->table_items)->result_array();
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function get_status_log($order_id)
    {
        $this->db->select($this->table_log . '.*, CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name', false);
        $this->db->from($this->table_log);
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . $this->table_log . '.changed_by', 'left');
        $this->db->where($this->table_log . '.order_id', (int) $order_id);
        $this->db->order_by($this->table_log . '.created_at', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * @param string|null $from
     * @param string      $to
     * @return bool
     */
    public function is_valid_transition($from, $to)
    {
        if ($from === null) {
            return $to === 'pending';
        }

        $configured = opsdesk_get_order_status_option_keys(true);
        if (in_array($to, $configured, true) || $to === 'cancelled') {
            return true;
        }

        return isset($this->valid_transitions[$from])
            && in_array($to, $this->valid_transitions[$from], true);
    }

    /**
     * Next allowed statuses for UI.
     *
     * @param string $current
     * @return array
     */
    public function get_next_statuses($current)
    {
        $configured = opsdesk_get_order_status_option_keys(false);
        $index = array_search($current, $configured, true);

        if ($index !== false) {
            return array_values(array_slice($configured, $index + 1));
        }

        return $this->valid_transitions[$current] ?? [];
    }

    /**
     * @param int         $order_id
     * @param string|null $from
     * @param string      $to
     * @param int         $staff_id
     * @param string|null $notes
     * @return void
     */
    private function log_status_change($order_id, $from, $to, $staff_id, $notes = null)
    {
        $this->db->insert($this->table_log, [
            'order_id'    => (int) $order_id,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => (int) $staff_id,
            'notes'       => $notes,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param string   $sku
     * @param int|null $product_item_id
     * @return void
     */
    private function ensure_inventory_row($sku, $product_item_id = null)
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }

        if ($this->opsdesk_inventory_model->get_by_sku($sku)) {
            return;
        }

        if ($product_item_id) {
            $this->opsdesk_inventory_model->sync_from_product((int) $product_item_id, ['sku' => $sku]);

            return;
        }

        $this->opsdesk_inventory_model->add([
            'sku'                => $sku,
            'quantity_available' => 0,
            'quantity_reserved'  => 0,
        ]);
    }

    /**
     * @return void
     */
    private function apply_list_joins()
    {
        $this->db->select(
            $this->table_orders . '.*,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as creator_name',
            false
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . $this->table_orders . '.created_by',
            'left'
        );
    }
}
