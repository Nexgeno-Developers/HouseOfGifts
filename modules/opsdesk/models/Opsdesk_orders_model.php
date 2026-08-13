<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Opsdesk_orders_model extends App_Model
{
    private $table_orders;
    private $table_items;
    private $table_log;
    private $table_inventory;

    public function __construct()
    {
        parent::__construct();
        $CI = &get_instance();
        $CI->load->helper(OPSDESK_MODULE_NAME . '/opsdesk');
        $this->load->model('opsdesk/opsdesk_combos_model');
        $this->load->model('opsdesk/opsdesk_inventory_model');
        $this->load->model('opsdesk/opsdesk_transport_mediums_model');

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
        $priority   = $options['priority'] ?? null;
        $delivery_sort = $options['sort_by_delivery_date'] ?? null;

        if (is_numeric($id)) {
            $this->db->where($this->table_orders . '.id', (int) $id);
            $this->apply_list_joins();

            if ($own_only && $staff_id) {
                $this->db->where($this->table_orders . '.created_by', $staff_id);
            }

            $order = $this->db->get($this->table_orders)->row_array();

            if ($order) {
                $order['items']         = $this->get_order_items((int) $order['id']);
                $order['status_log']    = $this->get_status_log((int) $order['id']);
                $order['creator_name']  = get_staff_full_name((int) $order['created_by']);
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

        if ($priority !== null && $priority !== '' && $priority !== 'all') {
            $this->db->where($this->table_orders . '.priority', (int) $priority);
        }

         if ($delivery_sort === 'asc') {
             $this->db->order_by($this->table_orders . '.delivery_date IS NULL', 'ASC', FALSE);
             $this->db->order_by($this->table_orders . '.delivery_date', 'ASC');
             $this->db->order_by($this->table_orders . '.priority', 'DESC');
             $this->db->order_by($this->table_orders . '.created_at', 'DESC');
         } elseif ($delivery_sort === 'desc') {
             $this->db->order_by($this->table_orders . '.delivery_date IS NULL', 'ASC', FALSE);
             $this->db->order_by($this->table_orders . '.delivery_date', 'DESC');
             $this->db->order_by($this->table_orders . '.priority', 'DESC');
             $this->db->order_by($this->table_orders . '.created_at', 'DESC');
        } else {
            $this->db->order_by($this->table_orders . '.priority', 'DESC');
            $this->db->order_by($this->table_orders . '.created_at', 'DESC');
        }

        return $this->db->get($this->table_orders)->result_array();
    }

    /**
     * Count orders grouped by status (respecting the same own_only/staff_id
     * scope used by get()).
     *
     * @param array $options
     * @return array status_key => count
     */
    public function count_by_status($options = [])
    {
        $staff_id = isset($options['staff_id']) ? (int) $options['staff_id'] : null;
        $own_only = !empty($options['own_only']);

        $this->db->select($this->table_orders . '.status, COUNT(*) as total');
        $this->apply_list_joins();

        if ($own_only && $staff_id) {
            $this->db->where($this->table_orders . '.created_by', $staff_id);
        }

        $this->db->group_by($this->table_orders . '.status');

        $rows = $this->db->get($this->table_orders)->result_array();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
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
        $seen_skus     = [];

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

             $seen_skus[$sku] = true;
         }

        foreach ($added as $added_item) {
            if (empty($added_item['sku'])) {
                continue;
            }

            $added_sku = trim($added_item['sku']);

            // Skip if this SKU was already added or matches an existing combo item.
            if (isset($seen_skus[$added_sku])) {
                continue;
            }
            $seen_skus[$added_sku] = true;

            $qty_per_unit = isset($added_item['quantity_per_unit'])
                ? (float) $added_item['quantity_per_unit']
                : 1.0;

            if (isset($added_item['required_quantity']) && $quantity > 0) {
                $qty_per_unit = (float) $added_item['required_quantity'] / $quantity;
            }

            $items[] = [
                'product_item_id'   => !empty($added_item['product_item_id']) ? (int) $added_item['product_item_id'] : null,
                'sku'               => $added_sku,
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
        $quantity = (float) ($order_data['quantity'] ?? 0);
        if ($quantity < 1 || empty($order_items)) {
            return ['success' => false, 'message' => _l('opsdesk_invalid_request')];
        }

        $this->db->trans_begin();

        try {
            // Ensure an inventory row exists for each SKU inside the
            // transaction so a later rollback also undoes any newly created row.
            foreach ($order_items as $item) {
                $this->ensure_inventory_row($item['sku'], $item['product_item_id'] ?? null);
            }

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

                if ($net_available < $required && !opsdesk_bypass_stock_check()) {
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

              if ($this->db->trans_commit() === false) {
                  $this->db->trans_rollback();

                  return ['success' => false, 'message' => _l('opsdesk_order_create_failed')];
              }

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
            if ((int) $order['created_by'] !== (int) $staff_id) {
                return ['success' => false, 'message' => _l('access_denied')];
            }

            if ($order['status'] !== 'pending') {
                return ['success' => false, 'message' => _l('opsdesk_order_cannot_cancel')];
            }
        } else {
            if (!in_array($order['status'], ['pending', 'in_progress', 'packed'], true)) {
                return ['success' => false, 'message' => _l('opsdesk_order_cannot_cancel')];
            }
        }

        $this->db->trans_begin();

        try {
            foreach ($order['items'] as $item) {
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

            $this->log_status_change((int) $order_id, $order['status'], 'cancelled', (int) $staff_id);

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();

                return ['success' => false, 'message' => _l('opsdesk_order_cancel_failed')];
            }

             if ($this->db->trans_commit() === false) {
                 return ['success' => false, 'message' => _l('opsdesk_order_cancel_failed')];
             }

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
        $current    = $order['status'];

        // Guard against persisting a status key longer than the column can
        // hold. opsdesk_orders.status is VARCHAR(100), matching
        // opsdesk_product_statuses.status_key (VARCHAR(100)); reject anything
        // larger so a custom key is never silently truncated into a mismatch.
        if (mb_strlen($new_status) > 100) {
            return ['success' => false, 'message' => _l('opsdesk_invalid_status_transition')];
        }

        if ($new_status === $current) {
            return ['success' => true];
        }

        if ($new_status === 'cancelled') {
            return $this->cancel_order($order_id, $staff_id, true);
        }

        // Allow any status transition (not just forward). Validation for
        // specific statuses (e.g., completed) is handled below.
        if (!$this->is_valid_transition($current, $new_status)) {
            return ['success' => false, 'message' => _l('opsdesk_invalid_status_transition')];
        }

        // Counted-by must be set before the order can ship (and still required
        // if Completed is chosen without going through Shipped).
        if (in_array($new_status, ['shipped', 'completed'], true)) {
            $count_by = !empty($extra['count_by']) ? (int) $extra['count_by'] : (int) ($order['count_by'] ?? 0);
            if ($count_by <= 0) {
                return ['success' => false, 'message' => _l('opsdesk_count_by_required_for_completion')];
            }
        }

        // Validate completion requirements.
        if ($new_status === 'completed') {
            if (empty($order['payment_file'])) {
                return ['success' => false, 'message' => _l('opsdesk_payment_required_for_completion')];
            }
            // lr_copy, carton_photo, carton_count are required for completion.
            // Allow completion if the order already has the file, or the user
            // uploads one in this submission.
            if (empty($order['lr_copy']) && empty($extra['lr_copy'])) {
                return ['success' => false, 'message' => _l('opsdesk_lr_copy_required_for_completion')];
            }
            if (empty(opsdesk_parse_carton_photos($order['carton_photo'] ?? '')) && empty($extra['carton_photo'])) {
                return ['success' => false, 'message' => _l('opsdesk_carton_photo_required_for_completion')];
            }
            if (empty($order['carton_count']) && empty($extra['carton_count'])) {
                return ['success' => false, 'message' => _l('opsdesk_carton_count_required_for_completion')];
            }
        }

        // Validate acceptance (pending -> in_progress) requires packed_by
        if ($current === 'pending' && $new_status === 'in_progress') {
            if (empty($extra['packed_by'])) {
                return ['success' => false, 'message' => _l('opsdesk_packed_by_required')];
            }
        }

        $this->db->trans_begin();

        try {
             if ($new_status === 'shipped') {
                 // Validate that quantity_reserved is sufficient before
                 // deducting — GREATEST(0, ...) below would silently
                 // over-deduct if reserved exceeds available.
                 foreach ($order['items'] as $item) {
                     $qty = (float) $item['quantity_reserved'];
                     $inv = $this->opsdesk_inventory_model->get_by_sku($item['sku']);
                     $reserved = 0.0;
                     if (is_object($inv) && isset($inv->quantity_reserved)) {
                         $reserved = (float) $inv->quantity_reserved;
                     } elseif (is_array($inv) && isset($inv['quantity_reserved'])) {
                         $reserved = (float) $inv['quantity_reserved'];
                     }
                     if (!$inv || $reserved < $qty) {
                         $this->db->trans_rollback();

                         return ['success' => false, 'message' => _l('opsdesk_insufficient_reserved', $item['sku'])];
                     }
                 }

                 foreach ($order['items'] as $item) {
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

            if (!empty($extra['packed_by'])) {
                $update['packed_by'] = (int) $extra['packed_by'];
            }

             if (!empty($extra['count_by'])) {
                 $update['count_by'] = (int) $extra['count_by'];
             }

             if (array_key_exists('delivery_date', $extra)) {
                 $update['delivery_date'] = $extra['delivery_date'];
             }

             if (array_key_exists('transport_medium_id', $extra)) {
                 $update['transport_medium_id'] = (int) $extra['transport_medium_id'];
             }

             // Handle completion fields
            if ($new_status === 'completed') {
                if (!empty($extra['lr_copy'])) {
                    $update['lr_copy'] = $extra['lr_copy'];
                }
                if (!empty($extra['carton_photo'])) {
                    $photos   = opsdesk_parse_carton_photos($order['carton_photo'] ?? '');
                    $photos[] = $extra['carton_photo'];
                    $update['carton_photo'] = opsdesk_encode_carton_photos($photos);
                }
                if (!empty($extra['carton_count'])) {
                    $update['carton_count'] = (int) $extra['carton_count'];
                }
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

             if ($this->db->trans_commit() === false) {
                 return ['success' => false, 'message' => _l('opsdesk_status_update_failed')];
             }

             log_activity('OpsDesk Order Status Updated [ID:' . $order_id . ', Status:' . $new_status . ']');

            return ['success' => true];
        } catch (Throwable $e) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Assign / reassign the staff member responsible for packing an order.
     *
     * Persists packed_by, count_by, and carton_count independently of any
     * status change and logs the assignment in the status history.
     *
     * @param int      $order_id
     * @param int      $staff_id    the newly assigned packer (0 = unassign)
     * @param int      $by_staff_id the staff performing the assignment
     * @param array    $extra       optional count_by / carton_count
     * @return array{success:bool,message?:string}
     */
    public function assign($order_id, $staff_id, $by_staff_id, $extra = [])
    {
        return $this->save_staff_assignment(
            $order_id,
            $staff_id,
            $extra['count_by'] ?? null,
            $extra['carton_count'] ?? null,
            $by_staff_id,
            array_key_exists('count_by', $extra),
            array_key_exists('carton_count', $extra)
        );
    }

    /**
     * Save packer, counted-by staff, and carton count for an order.
     *
     * @param int         $order_id
     * @param int         $packed_by
     * @param mixed       $count_by
     * @param mixed       $carton_count
     * @param int         $by_staff_id
     * @param bool        $save_count_by
     * @param bool        $save_carton_count
     * @return array{success:bool,message?:string}
     */
    public function save_staff_assignment(
        $order_id,
        $packed_by,
        $count_by,
        $carton_count,
        $by_staff_id,
        $save_count_by = true,
        $save_carton_count = true
    ) {
        $order = $this->get($order_id);
        if (!$order) {
            return ['success' => false, 'message' => _l('opsdesk_order_not_found')];
        }

        $packed_by = (int) $packed_by;
        $update    = [
            'packed_by'  => $packed_by > 0 ? $packed_by : null,
            'updated_by' => (int) $by_staff_id,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($save_count_by) {
            $count_by_id = (int) $count_by;
            $update['count_by'] = $count_by_id > 0 ? $count_by_id : null;
        }

        if ($save_carton_count) {
            if ($carton_count === '' || $carton_count === null || $carton_count === false) {
                $update['carton_count'] = null;
            } else {
                $carton = (int) $carton_count;
                $update['carton_count'] = $carton > 0 ? $carton : null;
            }
        }

        $this->db->where('id', (int) $order_id);
        $this->db->update($this->table_orders, $update);

        if ($this->db->affected_rows() > 0) {
            $assigned_name = $packed_by > 0 ? get_staff_full_name($packed_by) : _l('opsdesk_unassigned');
            $this->log_status_change(
                (int) $order_id,
                $order['status'],
                $order['status'],
                (int) $by_staff_id,
                _l('opsdesk_assigned_note', [$assigned_name])
            );

            log_activity('OpsDesk Order Assigned [ID:' . $order_id . ', Packed By:' . $packed_by . ']');
        }

        return ['success' => true];
    }

    /**
     * Update the priority flag of an order and log the change.
     *
     * @param int $order_id
     * @param int $priority
     * @param int $staff_id
     * @return array{success:bool,message?:string}
     */
    public function update_priority($order_id, $priority, $staff_id)
    {
        $order = $this->get($order_id);
        if (!$order) {
            return ['success' => false, 'message' => _l('opsdesk_order_not_found')];
        }

        // Clamp to a valid priority value (0 = Normal, 1 = High).
        $priority = (int) $priority;
        if (!in_array($priority, [0, 1], true)) {
            $priority = 0;
        }

        $old_priority = (int) $order['priority'];
        if ($old_priority === $priority) {
            return ['success' => true];
        }

        $old_label = $old_priority === 1 ? _l('opsdesk_priority_high') : _l('opsdesk_priority_normal');
        $new_label = $priority === 1 ? _l('opsdesk_priority_high') : _l('opsdesk_priority_normal');
        $note      = _l('opsdesk_priority_changed_note', [$old_label, $new_label]);

        $this->db->where('id', (int) $order_id);
        $this->db->update($this->table_orders, [
            'priority'   => $priority,
            'updated_by' => (int) $staff_id,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($this->db->affected_rows() > 0) {
            $this->log_status_change((int) $order_id, $order['status'], $order['status'], (int) $staff_id, $note);
        }

        return ['success' => true];
    }

    /**
     * Update the payment_file for an order.
     *
     * @param int    $order_id
     * @param string $file_name
     * @return bool
     */
    public function update_payment_file($order_id, $file_name)
    {
        return $this->update_order_file($order_id, 'payment_file', $file_name);
    }

    /**
     * Store an uploaded file name on an order attachment column.
     *
     * @param int    $order_id
     * @param string $field     payment_file|lr_copy|carton_photo
     * @param string $file_name
     * @return bool
     */
    public function update_order_file($order_id, $field, $file_name)
    {
        $allowed = ['payment_file', 'lr_copy', 'carton_photo'];
        if (!is_numeric($order_id) || empty($file_name) || !in_array($field, $allowed, true)) {
            return false;
        }

        if ($field === 'carton_photo') {
            return $this->append_carton_photos($order_id, [$file_name]);
        }

        $this->db->where('id', (int) $order_id);
        $this->db->update($this->table_orders, [
            $field       => $file_name,
            'updated_by' => get_staff_user_id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($this->db->affected_rows() > 0) {
            log_activity('OpsDesk Order File Updated [ID:' . $order_id . ', Field:' . $field . ']');

            return true;
        }

        return false;
    }

    /**
     * Append carton photo filenames to an order (does not replace existing).
     *
     * @param int   $order_id
     * @param array $new_files
     * @return bool
     */
    public function append_carton_photos($order_id, $new_files)
    {
        if (!is_numeric($order_id) || empty($new_files)) {
            return false;
        }

        $order = $this->get($order_id);
        if (!$order) {
            return false;
        }

        $merged = array_merge(
            opsdesk_parse_carton_photos($order['carton_photo'] ?? ''),
            (array) $new_files
        );
        $encoded = opsdesk_encode_carton_photos($merged);

        $this->db->where('id', (int) $order_id);
        $this->db->update($this->table_orders, [
            'carton_photo' => $encoded,
            'updated_by'   => get_staff_user_id(),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        log_activity('OpsDesk Order Carton Photos Updated [ID:' . $order_id . ']');

        return true;
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

        if (!in_array($order['status'], ['cancelled', 'completed'], true)) {
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
         $this->db->order_by($this->table_log . '.created_at', 'DESC');

        return $this->db->get()->result_array();
    }

    /**
     * @param string|null $from
     * @param string      $to
     * @return bool
     */
    public function is_valid_transition($from, $to)
    {
        if ($from === $to) {
            return true;
        }

        // Cancellation is always allowed from any non-cancelled status.
        if ($to === 'cancelled') {
            return true;
        }

        $statuses = opsdesk_get_order_statuses(true);
        $keys = array_column($statuses, 'status_key');

        $fromIdx = array_search($from, $keys, true);
        $toIdx   = array_search($to, $keys, true);

        if ($fromIdx === false || $toIdx === false) {
            return false;
        }

        // Forward-only progression by configured display order.
        return $toIdx > $fromIdx;
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

        return [];
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
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as creator_name,' .
            db_prefix() . 'clients.company as customer_name',
            false
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . $this->table_orders . '.created_by',
            'left'
        );
        $this->db->join(
            db_prefix() . 'clients',
            db_prefix() . 'clients.userid = ' . $this->table_orders . '.customer_id',
            'left'
        );
    }
}
