<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Opsdesk_orders_model extends App_Model
{
    private $table_orders;
    private $table_items;
    private $table_order_combos;
    private $table_log;
    private $table_inventory;
    private $table_combos;

    public function __construct()
    {
        parent::__construct();
        $CI = &get_instance();
        $CI->load->helper(OPSDESK_MODULE_NAME . '/opsdesk');
        $this->load->model('opsdesk/opsdesk_combos_model');
        $this->load->model('opsdesk/opsdesk_inventory_model');
        $this->load->model('opsdesk/opsdesk_transport_mediums_model');

        $prefix = db_prefix();
        $this->table_orders       = $prefix . 'opsdesk_orders';
        $this->table_items        = $prefix . 'opsdesk_order_items';
        $this->table_order_combos = $prefix . 'opsdesk_order_combos';
        $this->table_log          = $prefix . 'opsdesk_order_status_log';
        $this->table_inventory    = $prefix . 'opsdesk_inventory';
        $this->table_combos       = $prefix . 'opsdesk_combos';
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
                $order['combos']        = $this->get_order_combos((int) $order['id']);
                if (empty($order['combos']) && !empty($order['combo_id'])) {
                    $order['combos'] = [
                        [
                            'id'          => 0,
                            'order_id'    => (int) $order['id'],
                            'combo_id'    => (int) $order['combo_id'],
                            'combo_name'  => $order['combo_name'],
                            'quantity'    => (float) $order['quantity'],
                            'combo_image' => $order['combo_image'] ?? null,
                        ]
                    ];
                }
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
     * Build order line items from single combo definition (backward compatibility).
     *
     * @param int   $combo_id
     * @param int|float $quantity
     * @param array $overrides
     * @return array{success:bool,message?:string,items?:array,combo?:object}
     */
    public function build_order_items($combo_id, $quantity, $overrides = [])
    {
        $combos_data = [
            ['combo_id' => (int) $combo_id, 'quantity' => (float) $quantity]
        ];
        $built = $this->build_multi_order_items($combos_data, [], $overrides);
        if (!$built['success']) {
            return $built;
        }

        $built['combo'] = $built['combos'][0]['combo'] ?? null;

        return $built;
    }

    /**
     * Build order line items from multiple combos and standalone products.
     *
     * @param array $combos_data   List of ['combo_id' => int, 'quantity' => float]
     * @param array $products_data List of ['product_id' => int, 'quantity' => float, 'sku' => string, 'product_name' => string]
     * @param array $overrides     Substitutions, removed items, added items, quantities
     * @return array{success:bool,message?:string,items?:array,combos?:array,products?:array}
     */
    public function build_multi_order_items($combos_data = [], $products_data = [], $overrides = [])
    {
        $substitutions = $overrides['substitutions'] ?? [];
        $removed       = array_map('strval', $overrides['removed'] ?? []);
        $added         = $overrides['added'] ?? [];
        $items         = [];
        $processed_combos   = [];
        $processed_products = [];

        // 1. Process Combos
        if (!empty($combos_data) && is_array($combos_data)) {
            foreach ($combos_data as $c_idx => $c_input) {
                $combo_id  = (int) ($c_input['combo_id'] ?? 0);
                $combo_qty = (float) ($c_input['quantity'] ?? 0);

                if ($combo_id <= 0 || $combo_qty <= 0) {
                    continue;
                }

                $combo = $this->opsdesk_combos_model->get($combo_id);
                if (!$combo || (int) $combo->status !== 1) {
                    continue;
                }

                $processed_combos[] = [
                    'combo_index' => $c_idx,
                    'combo_id'    => $combo_id,
                    'combo_name'  => $combo->name,
                    'quantity'    => $combo_qty,
                    'combo'       => $combo,
                ];

                $combo_items = $this->opsdesk_combos_model->get_combo_items($combo_id);

                foreach ($combo_items as $combo_item) {
                    $combo_item_id = (string) $combo_item['id'];
                    $item_key_indexed = $c_idx . '_' . $combo_item_id;

                    // Skip if removed by index-specific key or combo_item_id
                    if (in_array($item_key_indexed, $removed, true) || in_array($combo_item_id, $removed, true)) {
                        continue;
                    }

                    $qty_per_unit = (float) $combo_item['quantity_per_unit'];
                    $required_qty = $qty_per_unit * $combo_qty;

                    if (isset($overrides['quantities'][$item_key_indexed])) {
                        $required_qty = (float) $overrides['quantities'][$item_key_indexed];
                        if ($combo_qty > 0) {
                            $qty_per_unit = $required_qty / $combo_qty;
                        }
                    } elseif (isset($overrides['quantities'][$combo_item_id])) {
                        $required_qty = (float) $overrides['quantities'][$combo_item_id];
                        if ($combo_qty > 0) {
                            $qty_per_unit = $required_qty / $combo_qty;
                        }
                    }

                    $product_id   = $combo_item['product_item_id'] ? (int) $combo_item['product_item_id'] : null;
                    $sku          = $combo_item['sku'];
                    $product_name = $combo_item['product_name'];
                    $is_sub       = 0;
                    $original_id  = null;

                    // Substitution check
                    $sub_id = $substitutions[$item_key_indexed] ?? ($substitutions[$combo_item_id] ?? null);
                    if ($sub_id && is_numeric($sub_id)) {
                        $sub_product = opsdesk_get_product_by_id((int) $sub_id);
                        if ($sub_product) {
                            $product_id   = (int) $sub_product['id'];
                            $sku          = $sub_product['sku'];
                            $product_name = $sub_product['label'];
                            $is_sub       = 1;
                            $original_id  = (int) $combo_item['id'];
                        }
                    }

                    $items[] = [
                        'key'               => $item_key_indexed,
                        'combo_id'          => $combo_id,
                        'combo_index'       => $c_idx,
                        'combo_name'        => $combo->name,
                        'combo_item_id'     => (int) $combo_item['id'],
                        'product_item_id'   => $product_id,
                        'sku'               => $sku,
                        'product_name'      => $product_name,
                        'quantity_per_unit' => $qty_per_unit,
                        'quantity_reserved' => $required_qty,
                        'quantity_ordered'  => $required_qty,
                        'is_substitution'   => $is_sub,
                        'original_item_id'  => $original_id,
                        'source_type'       => 'combo',
                        'source_name'       => $combo->name,
                    ];
                }
            }
        }

        // 2. Process Standalone Inventory Products
        if (!empty($products_data) && is_array($products_data)) {
            foreach ($products_data as $p_idx => $p_input) {
                $p_qty = (float) ($p_input['quantity'] ?? 0);
                if ($p_qty <= 0) {
                    continue;
                }

                $product_id = !empty($p_input['product_id'])
                    ? (int) $p_input['product_id']
                    : (!empty($p_input['product_item_id']) ? (int) $p_input['product_item_id'] : null);
                $sku    = trim($p_input['sku'] ?? '');
                $p_name = trim($p_input['product_name'] ?? '');

                if (empty($sku) && !empty($p_input['inventory_id'])) {
                    $inv_row = $this->opsdesk_inventory_model->get((int) $p_input['inventory_id']);
                    if ($inv_row) {
                        $sku        = is_object($inv_row) ? $inv_row->sku : ($inv_row['sku'] ?? '');
                        $product_id = is_object($inv_row) ? (int) ($inv_row->product_item_id ?? 0) : (int) ($inv_row['product_item_id'] ?? 0);
                    }
                }

                if ($product_id > 0 && (empty($sku) || empty($p_name))) {
                    $product = opsdesk_get_product_by_id($product_id);
                    if ($product) {
                        $sku    = $product['sku'] ?: $sku;
                        $p_name = $product['label'] ?: $p_name;
                    }
                }

                if (empty($sku)) {
                    continue;
                }

                $processed_products[] = [
                    'product_id'   => $product_id,
                    'sku'          => $sku,
                    'product_name' => $p_name ?: $sku,
                    'quantity'     => $p_qty,
                ];

                $items[] = [
                    'key'               => 'standalone_' . $p_idx . '_' . $sku,
                    'combo_id'          => null,
                    'combo_index'       => null,
                    'combo_name'        => null,
                    'combo_item_id'     => null,
                    'product_item_id'   => $product_id,
                    'sku'               => $sku,
                    'product_name'      => $p_name ?: $sku,
                    'quantity_per_unit' => $p_qty,
                    'quantity_reserved' => $p_qty,
                    'quantity_ordered'  => $p_qty,
                    'is_substitution'   => 0,
                    'original_item_id'  => null,
                    'source_type'       => 'standalone',
                    'source_name'       => _l('opsdesk_source_standalone'),
                ];
            }
        }

        // 3. Process Overrides: Added items (manual additions)
        if (!empty($added) && is_array($added)) {
            foreach ($added as $a_idx => $added_item) {
                if (empty($added_item['sku'])) {
                    continue;
                }
                $added_sku = trim($added_item['sku']);
                $req_qty   = isset($added_item['required_quantity'])
                    ? (float) $added_item['required_quantity']
                    : ((float) ($added_item['quantity_per_unit'] ?? 1.0));
                if ($req_qty <= 0) {
                    $req_qty = 1.0;
                }

                $items[] = [
                    'key'               => 'added_' . $a_idx . '_' . $added_sku,
                    'combo_id'          => null,
                    'combo_index'       => null,
                    'combo_name'        => null,
                    'combo_item_id'     => null,
                    'product_item_id'   => !empty($added_item['product_item_id']) ? (int) $added_item['product_item_id'] : null,
                    'sku'               => $added_sku,
                    'product_name'      => trim($added_item['product_name'] ?? $added_sku),
                    'quantity_per_unit' => $req_qty,
                    'quantity_reserved' => $req_qty,
                    'is_substitution'   => !empty($added_item['is_substitution']) ? 1 : 0,
                    'original_item_id'  => !empty($added_item['original_item_id']) ? (int) $added_item['original_item_id'] : null,
                    'source_type'       => 'standalone',
                    'source_name'       => _l('opsdesk_source_standalone'),
                ];
            }
        }

        if (count($items) === 0) {
            return ['success' => false, 'message' => _l('opsdesk_no_order_items')];
        }

        return [
            'success'  => true,
            'items'    => $items,
            'combos'   => $processed_combos,
            'products' => $processed_products,
        ];
    }

    /**
     * Check stock for proposed order items, aggregating requirements per SKU.
     *
     * @param array $order_items
     * @param float $quantity
     * @return array
     */
    public function check_items_stock($order_items, $quantity = 1.0)
    {
        // 1. Aggregate total requirement across all order items for each SKU
        $sku_totals = [];
        foreach ($order_items as $item) {
            $sku = $item['sku'];
            $req = isset($item['quantity_reserved'])
                ? (float) $item['quantity_reserved']
                : ((float) $item['quantity_per_unit'] * (float) $quantity);

            if (!isset($sku_totals[$sku])) {
                $sku_totals[$sku] = 0.0;
            }
            $sku_totals[$sku] += $req;
        }

        // 2. Query available stock for each distinct SKU
        $sku_available = [];
        foreach (array_keys($sku_totals) as $sku) {
            $sku_available[$sku] = (float) $this->opsdesk_inventory_model->get_available_for_combo_item($sku, null);
        }

        // 3. Build component stock evaluation
        $components     = [];
        $is_fulfillable = true;

        foreach ($order_items as $idx => $item) {
            $sku = $item['sku'];
            $req = isset($item['quantity_reserved'])
                ? (float) $item['quantity_reserved']
                : ((float) $item['quantity_per_unit'] * (float) $quantity);
            $available    = $sku_available[$sku] ?? 0.0;
            $total_needed = $sku_totals[$sku] ?? $req;

            // Sufficient only if available stock covers the entire order's demand for this SKU
            $sufficient = $available >= $total_needed;
            if (!$sufficient) {
                $is_fulfillable = false;
            }

            $components[] = [
                'key'                => $item['key'] ?? ($item['combo_item_id'] ? ('item_' . $item['combo_item_id']) : ('sku_' . $sku)),
                'combo_item_id'      => $item['combo_item_id'] ?? null,
                'combo_id'           => $item['combo_id'] ?? null,
                'combo_name'         => $item['combo_name'] ?? null,
                'sku'                => $item['sku'],
                'product_name'       => $item['product_name'],
                'product_item_id'    => $item['product_item_id'] ?? null,
                'quantity_per_unit'  => (float) $item['quantity_per_unit'],
                'required_quantity'  => $req,
                'total_sku_needed'   => $total_needed,
                'available_stock'    => $available,
                'is_sufficient'      => $sufficient,
                'is_substitution'    => !empty($item['is_substitution']),
                'source_type'        => $item['source_type'] ?? 'combo',
                'source_name'        => $item['source_name'] ?? ($item['combo_name'] ?? _l('opsdesk_combo')),
            ];
        }

        return [
            'is_fulfillable' => $is_fulfillable && count($components) > 0,
            'components'     => $components,
            'sku_totals'     => $sku_totals,
        ];
    }

    /**
     * Create order and reserve stock atomically across multiple combos and standalone products.
     *
     * @param array $order_data
     * @param array $order_items
     * @param array $combos_list
     * @return array
     */
    public function create_order_with_reservation($order_data, $order_items, $combos_list = [])
    {
        $quantity = (float) ($order_data['quantity'] ?? 0);
        if ($quantity < 1 || empty($order_items)) {
            return ['success' => false, 'message' => _l('opsdesk_invalid_request')];
        }

        $this->db->trans_begin();

        try {
            // Ensure an inventory row exists for each SKU inside the transaction
            foreach ($order_items as $item) {
                $this->ensure_inventory_row($item['sku'], $item['product_item_id'] ?? null);
            }

            // Lock inventory rows FOR UPDATE to prevent race conditions
            $distinct_skus = array_unique(array_column($order_items, 'sku'));
            foreach ($distinct_skus as $sku) {
                $this->db->query(
                    'SELECT id, quantity_available, quantity_reserved
                     FROM ' . $this->table_inventory . '
                     WHERE sku = ? FOR UPDATE',
                    [$sku]
                );
            }

            // Aggregate requirement per SKU and verify availability
            $sku_totals = [];
            foreach ($order_items as $item) {
                $sku = $item['sku'];
                $req = isset($item['quantity_reserved'])
                    ? (float) $item['quantity_reserved']
                    : ((float) $item['quantity_per_unit'] * $quantity);

                if (!isset($sku_totals[$sku])) {
                    $sku_totals[$sku] = 0.0;
                }
                $sku_totals[$sku] += $req;
            }

            foreach ($sku_totals as $sku => $required_qty) {
                $inv = $this->opsdesk_inventory_model->get_by_sku($sku);
                if (!$inv) {
                    $this->db->trans_rollback();

                    return ['success' => false, 'message' => _l('opsdesk_stock_no_longer_available', $sku)];
                }

                $net_available = $this->opsdesk_inventory_model->get_available_for_combo_item($sku);

                if ($net_available < $required_qty && !opsdesk_bypass_stock_check()) {
                    $this->db->trans_rollback();

                    return ['success' => false, 'message' => _l('opsdesk_stock_no_longer_available', $sku)];
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

            // Insert into tblopsdesk_order_combos
            $combo_db_ids = [];
            if (!empty($combos_list) && is_array($combos_list)) {
                foreach ($combos_list as $c_idx => $c_item) {
                    $this->db->insert($this->table_order_combos, [
                        'order_id'   => $order_id,
                        'combo_id'   => (int) $c_item['combo_id'],
                        'combo_name' => $c_item['combo_name'] ?? '',
                        'quantity'   => (float) $c_item['quantity'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $combo_db_ids[$c_idx] = (int) $this->db->insert_id();
                }
            }

            // Insert line items and reserve stock
            foreach ($order_items as $item) {
                $reserved = isset($item['quantity_reserved'])
                    ? (float) $item['quantity_reserved']
                    : ((float) $item['quantity_per_unit'] * $quantity);

                $order_combo_id = null;
                if (isset($item['combo_index']) && isset($combo_db_ids[$item['combo_index']])) {
                    $order_combo_id = $combo_db_ids[$item['combo_index']];
                }

                $this->db->insert($this->table_items, [
                    'order_id'          => $order_id,
                    'order_combo_id'    => $order_combo_id,
                    'product_item_id'   => $item['product_item_id'] ?? null,
                    'sku'               => $item['sku'],
                    'product_name'      => $item['product_name'],
                    'quantity_per_unit' => $item['quantity_per_unit'],
                    'quantity_reserved' => $reserved,
                    'is_substitution'   => !empty($item['is_substitution']) ? 1 : 0,
                    'original_item_id'  => $item['original_item_id'] ?? null,
                    'source_type'       => $item['source_type'] ?? 'combo',
                    'source_name'       => $item['source_name'] ?? null,
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
     * Get combos attached to an order.
     *
     * @param int $order_id
     * @return array
     */
    public function get_order_combos($order_id)
    {
        $this->db->select(
            $this->table_order_combos . '.*, ' .
            $this->table_combos . '.image as combo_image'
        );
        $this->db->from($this->table_order_combos);
        $this->db->join(
            $this->table_combos,
            $this->table_combos . '.id = ' . $this->table_order_combos . '.combo_id',
            'left'
        );
        $this->db->where($this->table_order_combos . '.order_id', (int) $order_id);
        $this->db->order_by($this->table_order_combos . '.id', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * Attach photo gallery (combos + standalone products + components) to a list of orders.
     *
     * @param array $orders (by reference)
     * @return void
     */
    public function attach_order_galleries(&$orders)
    {
        if (empty($orders) || !is_array($orders)) {
            return;
        }

        $order_ids = array_filter(array_column($orders, 'id'));
        if (empty($order_ids)) {
            return;
        }

        // 1. Fetch combos for all orders
        $combos_by_order = [];
        $this->db->select(
            $this->table_order_combos . '.order_id, ' .
            $this->table_order_combos . '.combo_id, ' .
            $this->table_order_combos . '.combo_name, ' .
            $this->table_combos . '.image as combo_image'
        );
        $this->db->from($this->table_order_combos);
        $this->db->join(
            $this->table_combos,
            $this->table_combos . '.id = ' . $this->table_order_combos . '.combo_id',
            'left'
        );
        $this->db->where_in($this->table_order_combos . '.order_id', $order_ids);
        $this->db->order_by($this->table_order_combos . '.id', 'ASC');
        $combo_rows = $this->db->get()->result_array();

        foreach ($combo_rows as $c) {
            $combos_by_order[(int) $c['order_id']][] = $c;
        }

        // 2. Fetch line items for all orders
        $items_by_order = [];
        $product_item_ids = [];
        $this->db->select('id, order_id, product_item_id, sku, product_name, source_type, source_name');
        $this->db->from($this->table_items);
        $this->db->where_in('order_id', $order_ids);
        $this->db->order_by('id', 'ASC');
        $item_rows = $this->db->get()->result_array();

        foreach ($item_rows as $it) {
            $items_by_order[(int) $it['order_id']][] = $it;
            if (!empty($it['product_item_id'])) {
                $product_item_ids[] = (int) $it['product_item_id'];
            }
        }
        $product_item_ids = array_unique(array_filter($product_item_ids));

        // 3. Batch load product files from tblfiles
        $product_files = [];
        if (!empty($product_item_ids)) {
            $this->db->select('rel_id, file_name, filetype');
            $this->db->from(db_prefix() . 'files');
            $this->db->where('rel_type', 'commodity_item_file');
            $this->db->where_in('rel_id', $product_item_ids);
            $this->db->order_by('id', 'ASC');
            $file_rows = $this->db->get()->result_array();
            foreach ($file_rows as $f) {
                if (!isset($product_files[(int) $f['rel_id']])) {
                    $product_files[(int) $f['rel_id']] = $f['file_name'];
                }
            }
        }

        $placeholder = module_dir_url(OPSDESK_MODULE_NAME, 'assets/images/combo-placeholder.svg');

        // 4. Assemble gallery per order
        foreach ($orders as &$order) {
            $oid = (int) ($order['id'] ?? 0);
            $gallery = [];
            $seen_urls = [];

            // A. Combos
            $order_combos = $combos_by_order[$oid] ?? [];
            if (empty($order_combos) && !empty($order['combo_id']) && !empty($order['combo_image'])) {
                $order_combos[] = [
                    'combo_id'    => (int) $order['combo_id'],
                    'combo_name'  => $order['combo_name'] ?? 'Combo',
                    'combo_image' => $order['combo_image'],
                ];
            }

            foreach ($order_combos as $c) {
                if (!empty($c['combo_image'])) {
                    $url = opsdesk_combo_image_url($c['combo_image']);
                    if ($url && $url !== $placeholder && !isset($seen_urls[$url])) {
                        $seen_urls[$url] = true;
                        $gallery[] = [
                            'url'   => $url,
                            'title' => 'Combo: ' . ($c['combo_name'] ?: 'Package'),
                            'type'  => 'combo',
                            'badge' => 'Combo',
                            'sku'   => '',
                        ];
                    }
                }
            }

            // B. Products & Components
            $order_items = $items_by_order[$oid] ?? [];
            foreach ($order_items as $it) {
                $pid = (int) ($it['product_item_id'] ?? 0);
                $sku = $it['sku'] ?? '';
                $url = null;

                // Check preloaded files
                if ($pid > 0 && isset($product_files[$pid])) {
                    $fn = $product_files[$pid];
                    $p_warehouse = FCPATH . 'modules/warehouse/uploads/item_img/' . $pid . '/' . $fn;
                    if (file_exists($p_warehouse)) {
                        $url = base_url('modules/warehouse/uploads/item_img/' . $pid . '/' . rawurlencode($fn));
                    }
                }

                // Fallback to helper function
                if (!$url) {
                    $url = opsdesk_get_product_image_url($pid, $sku);
                }

                if ($url && !isset($seen_urls[$url])) {
                    $seen_urls[$url] = true;
                    $is_standalone = (!empty($it['source_type']) && $it['source_type'] === 'standalone');
                    $gallery[] = [
                        'url'   => $url,
                        'title' => $it['product_name'] ?: $sku,
                        'type'  => $is_standalone ? 'standalone' : 'component',
                        'badge' => $is_standalone ? 'Product' : 'Component',
                        'sku'   => $sku,
                    ];
                }
            }

            $order['gallery_images'] = $gallery;
            $order['gallery_count']  = count($gallery);
            $order['primary_image']  = !empty($gallery) ? $gallery[0]['url'] : '';
        }
        unset($order);
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
            db_prefix() . 'clients.company as customer_name,' .
            $this->table_combos . '.image as combo_image',
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
        $this->db->join(
            $this->table_combos,
            $this->table_combos . '.id = ' . $this->table_orders . '.combo_id',
            'left'
        );
    }
}
