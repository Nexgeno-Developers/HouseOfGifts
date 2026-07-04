<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Opsdesk extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('opsdesk/opsdesk');
        $this->load->model('opsdesk/opsdesk_combos_model');
        $this->load->model('opsdesk/opsdesk_inventory_model');
        $this->load->model('opsdesk/opsdesk_orders_model');
    }

    /**
     * Default route — redirect to inventory viewer.
     */
    public function index()
    {
        redirect(admin_url('opsdesk/inventory'));
    }

    /**
     * Sales inventory viewer.
     */
    public function inventory()
    {
        if (!has_permission('opsdesk', '', 'view') && !staff_can('view', 'opsdesk')) {
            access_denied('opsdesk');
        }

        $data['title']    = _l('opsdesk_inventory_viewer');
        $data['combos']   = $this->opsdesk_combos_model->get('', true);
        $data['products'] = opsdesk_get_products_for_dropdown([
            'active_only' => true,
            'exclude_variation_parents' => true,
        ]);

        $this->app_scripts->add(
            'opsdesk-inventory-js',
            module_dir_url(OPSDESK_MODULE_NAME, 'assets/js/opsdesk_inventory.js')
        );

        $this->load->view('inventory_viewer', $data);
    }

    /**
     * AJAX: get available products for editor.
     */
    public function ajax_availability()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!has_permission('opsdesk', '', 'view') && !staff_can('view', 'opsdesk')) {
            ajax_access_denied();
        }

        $action = $this->input->post('action');

        if ($action === 'get_available_products') {
            $this->get_available_products_for_editor();
            return;
        }

        if ($action === 'get_product_details') {
            $this->get_product_details_for_editor();
            return;
        }

        $combo_id        = (int) $this->input->post('combo_id');
        $order_quantity  = (float) $this->input->post('order_quantity');

        if ($combo_id <= 0 || $order_quantity <= 0) {
            echo json_encode([
                'success' => false,
                'message' => _l('opsdesk_invalid_request'),
            ]);
            die;
        }

        $combo = $this->opsdesk_combos_model->get($combo_id);
        if (!$combo || (int) $combo->status !== 1) {
            echo json_encode([
                'success' => false,
                'message' => _l('opsdesk_combo_not_found'),
            ]);
            die;
        }

        $result = $this->opsdesk_combos_model->check_availability($combo_id, $order_quantity);

        echo json_encode([
            'success' => true,
            'data'    => $result,
        ]);
        die;
    }

    /**
     * Helper: fetch available products for adding to combo.
     */
    private function get_available_products_for_editor()
    {
        $products = opsdesk_get_products_for_dropdown([
            'active_only' => true,
            'exclude_variation_parents' => true,
        ]);

        $formatted = [];
        foreach ($products as $product) {
            $formatted[] = [
                'id'      => (int) $product['id'],
                'label'   => $product['label'],
                'subtext' => $product['subtext'] ?? '',
            ];
        }

        echo json_encode([
            'success' => true,
            'products' => $formatted,
        ]);
        die;
    }

    /**
     * Helper: fetch product details including availability.
     */
    private function get_product_details_for_editor()
    {
        $product_id = (int) $this->input->post('product_id');

        if ($product_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => _l('opsdesk_invalid_request'),
            ]);
            die;
        }

        $product = opsdesk_get_product_by_id($product_id);
        if (!$product) {
            echo json_encode([
                'success' => false,
                'message' => _l('opsdesk_invalid_request'),
            ]);
            die;
        }

        $order_quantity = (float) $this->input->post('order_quantity') ?: 1;

        $available_stock = $this->opsdesk_inventory_model->get_available_for_combo_item(
            $product['sku'],
            $product_id
        );

        echo json_encode([
            'success' => true,
            'product' => [
                'product_item_id' => $product_id,
                'sku' => $product['sku'],
                'product_name' => $product['label'],
                'quantity_per_unit' => 1.0,
                'available_stock' => $available_stock,
            ],
        ]);
        die;
    }

    /**
     * AJAX: seed random stock quantities for testing.
     */
    public function seed_random_stock()
    {
        if (!has_permission('opsdesk', '', 'view') && !staff_can('view', 'opsdesk')) {
            ajax_access_denied();
        }

        $updated_count = 0;

        // Update OpsDesk inventory table
        $inventory_items = $this->opsdesk_inventory_model->get();
        foreach ($inventory_items as $item) {
            $random_qty = mt_rand(10, 500);
            $this->opsdesk_inventory_model->update([
                'quantity_available' => $random_qty,
            ], $item['id']);
            $updated_count++;
        }

        // If Warehouse module is active, update warehouse inventory too
        if (opsdesk_is_warehouse_module_active() && $this->db->table_exists(db_prefix() . 'inventory_manage')) {
            $this->db->select(db_prefix() . 'inventory_manage.id, ' . db_prefix() . 'inventory_manage.commodity_id');
            $result = $this->db->get(db_prefix() . 'inventory_manage');
            
            if ($result->num_rows() > 0) {
                foreach ($result->result_array() as $warehouse_item) {
                    $random_qty = mt_rand(10, 500);
                    $this->db->where('id', (int) $warehouse_item['id']);
                    $this->db->update(db_prefix() . 'inventory_manage', [
                        'inventory_number' => $random_qty,
                    ]);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => _l('opsdesk_stock_seeded', $updated_count),
            'updated' => $updated_count,
        ]);
        die;
    }

    /**
     * Admin: list all combos.
     */
    public function combos()
    {
        if (!has_permission('opsdesk', '', 'view') && !staff_can('view', 'opsdesk')) {
            access_denied('opsdesk');
        }

        $data['title']  = _l('opsdesk_combos');
        $data['combos'] = $this->opsdesk_combos_model->get();

        $this->load->view('combos_manage', $data);
    }

    /**
     * Admin: create or edit a combo and its items.
     *
     * @param string|int $id
     */
    public function combo($id = '')
    {
        if (!has_permission('opsdesk', '', 'view') && !staff_can('view', 'opsdesk')) {
            access_denied('opsdesk');
        }

        if ($this->input->post()) {
            if ($id === '' || $id === null) {
                if (!has_permission('opsdesk', '', 'create') && !staff_can('create', 'opsdesk')) {
                    access_denied('opsdesk');
                }

                $new_id = $this->opsdesk_combos_model->add($this->input->post());
                if ($new_id) {
                    set_alert('success', _l('added_successfully', _l('opsdesk_combo')));
                    redirect(admin_url('opsdesk/combo/' . $new_id));
                }
            } else {
                if (!has_permission('opsdesk', '', 'edit') && !staff_can('edit', 'opsdesk')) {
                    access_denied('opsdesk');
                }

                $success = $this->opsdesk_combos_model->update($this->input->post(), $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('opsdesk_combo')));
                }
                redirect(admin_url('opsdesk/combo/' . $id));
            }
        }

        if (is_numeric($id)) {
            $combo = $this->opsdesk_combos_model->get($id);
            if (!$combo) {
                show_404();
            }
            $data['combo']       = $combo;
            $data['combo_items'] = $this->opsdesk_combos_model->get_combo_items($id);
            $title               = _l('opsdesk_edit_combo');
        } else {
            if (!has_permission('opsdesk', '', 'create') && !staff_can('create', 'opsdesk')) {
                access_denied('opsdesk');
            }
            $data['combo_items'] = [];
            $title               = _l('opsdesk_new_combo');
        }

        $data['products'] = opsdesk_get_products_for_dropdown();
        $data['title']    = $title;

        $this->load->view('combo', $data);
    }

    /**
     * Admin: add combo item (POST).
     *
     * @param int $combo_id
     */
    public function add_combo_item($combo_id)
    {
        if (!has_permission('opsdesk', '', 'create') && !has_permission('opsdesk', '', 'edit')
            && !staff_can('create', 'opsdesk') && !staff_can('edit', 'opsdesk')) {
            access_denied('opsdesk');
        }

        if (!$this->input->post() || !is_numeric($combo_id)) {
            redirect(admin_url('opsdesk/combos'));
        }

        $combo = $this->opsdesk_combos_model->get($combo_id);
        if (!$combo) {
            show_404();
        }

        $post = $this->input->post();
        $post['combo_id'] = (int) $combo_id;

        $item_id = $this->opsdesk_combos_model->add_combo_item($post);

        if ($item_id) {
            if (!empty($post['product_item_id']) && is_numeric($post['product_item_id'])) {
                $this->opsdesk_inventory_model->sync_from_product((int) $post['product_item_id'], $post);
            }
            set_alert('success', _l('opsdesk_combo_item_added'));
        } else {
            set_alert('warning', _l('opsdesk_combo_item_add_failed'));
        }

        redirect(admin_url('opsdesk/combo/' . $combo_id));
    }

    /**
     * Admin: delete combo item.
     *
     * @param int $combo_id
     * @param int $item_id
     */
    public function delete_combo_item($combo_id, $item_id)
    {
        if (!has_permission('opsdesk', '', 'delete') && !staff_can('delete', 'opsdesk')) {
            access_denied('opsdesk');
        }

        if (!is_numeric($combo_id) || !is_numeric($item_id)) {
            redirect(admin_url('opsdesk/combos'));
        }

        $combo = $this->opsdesk_combos_model->get($combo_id);
        $item  = $this->opsdesk_combos_model->get_combo_item($item_id);

        if (!$combo || !$item || (int) $item->combo_id !== (int) $combo_id) {
            show_404();
        }

        if ($this->opsdesk_combos_model->delete_combo_item($item_id)) {
            set_alert('success', _l('deleted', _l('opsdesk_combo_item')));
        }

        redirect(admin_url('opsdesk/combo/' . $combo_id));
    }

    /**
     * Admin: delete combo.
     *
     * @param int $id
     */
    public function delete_combo($id)
    {
        if (!has_permission('opsdesk', '', 'delete') && !staff_can('delete', 'opsdesk')) {
            access_denied('opsdesk');
        }

        if (!is_numeric($id)) {
            redirect(admin_url('opsdesk/combos'));
        }

        if ($this->opsdesk_combos_model->delete($id)) {
            set_alert('success', _l('deleted', _l('opsdesk_combo')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('opsdesk_combo')));
        }

        redirect(admin_url('opsdesk/combos'));
    }

    /**
     * Admin: view single combo inventory breakdown (static).
     *
     * @param int $id
     */
    public function combo_inventory($id)
    {
        if (!has_permission('opsdesk', '', 'view') && !staff_can('view', 'opsdesk')) {
            access_denied('opsdesk');
        }

        if (!is_numeric($id)) {
            redirect(admin_url('opsdesk/combos'));
        }

        $combo = $this->opsdesk_combos_model->get($id);
        if (!$combo) {
            show_404();
        }

        $order_qty = (float) $this->input->get('qty');
        if ($order_qty <= 0) {
            $order_qty = 1;
        }

        $data['combo']        = $combo;
        $data['order_qty']    = $order_qty;
        $data['availability'] = $this->opsdesk_combos_model->check_availability($id, $order_qty);
        $data['title']        = _l('opsdesk_combo_inventory_breakdown');

        $this->load->view('combo_inventory', $data);
    }

    /**
     * Orders list.
     */
    public function orders()
    {
        if (!opsdesk_can_view_orders()) {
            access_denied('opsdesk_orders');
        }

        $status_filter = $this->input->get('status');
        $own_only      = !opsdesk_can_view_all_orders();

        $data['title']         = _l('opsdesk_all_orders');
        $data['orders']        = $this->opsdesk_orders_model->get('', [
            'own_only' => $own_only,
            'staff_id' => get_staff_user_id(),
            'status'   => $status_filter,
        ]);
        $data['status_filter'] = $status_filter;
        $data['can_edit']      = opsdesk_can_edit_orders();
        $data['can_create']    = opsdesk_can_create_orders();
        $data['global_view']   = !$own_only;

        $this->load->view('orders_list', $data);
    }

    /**
     * New order form or order detail.
     *
     * @param string|int $id
     */
    public function order($id = '')
    {
        if (is_numeric($id)) {
            $this->order_detail((int) $id);

            return;
        }

        if (!opsdesk_can_create_orders()) {
            access_denied('opsdesk_orders');
        }

        $prefill = $this->parse_order_prefill();

        $data['title']         = _l('opsdesk_new_order');
        $data['combos']        = $this->opsdesk_combos_model->get('', true);
        $data['products']      = opsdesk_get_products_for_dropdown([
            'active_only' => true,
            'exclude_variation_parents' => true,
        ]);
        $data['packing_types'] = opsdesk_get_packing_types();
        $data['prefill']       = $prefill;

        $this->app_scripts->add(
            'opsdesk-orders-js',
            module_dir_url(OPSDESK_MODULE_NAME, 'assets/js/opsdesk_orders.js')
        );

        $this->load->view('order_form', $data);
    }

    /**
     * Order detail page.
     *
     * @param int $id
     */
    private function order_detail($id)
    {
        if (!opsdesk_can_view_orders()) {
            access_denied('opsdesk_orders');
        }

        $own_only = !opsdesk_can_view_all_orders();
        $order    = $this->opsdesk_orders_model->get($id, [
            'own_only' => $own_only,
            'staff_id' => get_staff_user_id(),
        ]);

        if (!$order) {
            show_404();
        }

        $data['title']          = _l('opsdesk_order') . ' #' . $id;
        $data['order']          = $order;
        $data['packing_types']  = opsdesk_get_packing_types();
        $data['can_edit']       = opsdesk_can_edit_orders();
        $data['can_cancel_own'] = (int) $order->created_by === (int) get_staff_user_id()
            && $order->status === 'pending';
        $data['can_cancel_any'] = $data['can_edit']
            && in_array($order->status, ['pending', 'in_progress', 'packed'], true);
        $data['next_statuses']  = $this->opsdesk_orders_model->get_next_statuses($order->status);

        $this->load->view('order_detail', $data);
    }

    /**
     * POST: create order with stock reservation.
     */
    public function save_order()
    {
        if (!opsdesk_can_create_orders()) {
            access_denied('opsdesk_orders');
        }

        if (!$this->input->post()) {
            redirect(admin_url('opsdesk/order'));
        }

        $combo_id     = (int) $this->input->post('combo_id');
        $quantity     = (int) $this->input->post('quantity');
        $packing_type = trim($this->input->post('packing_type') ?? '');
        $packing_types = array_keys(opsdesk_get_packing_types());

        if ($combo_id <= 0 || $quantity < 1 || !in_array($packing_type, $packing_types, true)) {
            set_alert('warning', _l('opsdesk_invalid_request'));
            redirect(admin_url('opsdesk/order'));
        }

        $overrides = $this->parse_order_overrides_from_post();
        $built     = $this->opsdesk_orders_model->build_order_items($combo_id, $quantity, $overrides);

        if (!$built['success']) {
            set_alert('warning', $built['message']);
            redirect(admin_url('opsdesk/order'));
        }

        $combo = $built['combo'];
        $result = $this->opsdesk_orders_model->create_order_with_reservation([
            'combo_id'     => $combo_id,
            'combo_name'   => $combo->name,
            'quantity'     => $quantity,
            'packing_type' => $packing_type,
            'notes'        => trim($this->input->post('notes') ?? ''),
            'created_by'   => get_staff_user_id(),
        ], $built['items']);

        if (!$result['success']) {
            set_alert('warning', $result['message']);
            redirect(admin_url('opsdesk/order?combo_id=' . $combo_id . '&quantity=' . $quantity));
        }

        set_alert('success', _l('opsdesk_order_created', $result['order_id']));
        redirect(admin_url('opsdesk/order/' . $result['order_id']));
    }

    /**
     * POST: update order status (Operations/Admin).
     */
    public function update_order_status()
    {
        if (!opsdesk_can_edit_orders()) {
            if ($this->input->is_ajax_request()) {
                ajax_access_denied();
            }
            access_denied('opsdesk_orders');
        }

        $order_id   = (int) $this->input->post('order_id');
        $new_status = trim($this->input->post('status') ?? '');
        $extra      = [];

        if ($this->input->post('packing_type')) {
            $extra['packing_type'] = trim($this->input->post('packing_type'));
        }

        if ($this->input->post('notes')) {
            $extra['notes'] = trim($this->input->post('notes'));
        }

        $result = $this->opsdesk_orders_model->update_status(
            $order_id,
            $new_status,
            get_staff_user_id(),
            $extra
        );

        if ($this->input->is_ajax_request()) {
            echo json_encode($result);
            die;
        }

        if ($result['success']) {
            set_alert('success', _l('updated_successfully', _l('opsdesk_order')));
        } else {
            set_alert('warning', $result['message']);
        }

        redirect(admin_url('opsdesk/order/' . $order_id));
    }

    /**
     * POST: cancel order.
     *
     * @param int $id
     */
    public function cancel_order($id)
    {
        if (!opsdesk_can_view_orders()) {
            access_denied('opsdesk_orders');
        }

        if (!is_numeric($id)) {
            redirect(admin_url('opsdesk/orders'));
        }

        $can_edit_global = opsdesk_can_edit_orders();
        $result          = $this->opsdesk_orders_model->cancel_order(
            (int) $id,
            get_staff_user_id(),
            $can_edit_global
        );

        if ($this->input->is_ajax_request()) {
            echo json_encode($result);
            die;
        }

        if ($result['success']) {
            set_alert('success', _l('updated_successfully', _l('opsdesk_order')));
        } else {
            set_alert('warning', $result['message']);
        }

        redirect(admin_url('opsdesk/order/' . $id));
    }

    /**
     * AJAX: real-time stock check for order form.
     */
    public function ajax_order_stock_check()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!opsdesk_can_create_orders()) {
            ajax_access_denied();
        }

        $combo_id = (int) $this->input->post('combo_id');
        $quantity = (int) $this->input->post('quantity');

        if ($combo_id <= 0 || $quantity < 1) {
            echo json_encode(['success' => false, 'message' => _l('opsdesk_invalid_request')]);
            die;
        }

        $overrides = $this->parse_order_overrides_from_post();
        $built     = $this->opsdesk_orders_model->build_order_items($combo_id, $quantity, $overrides);

        if (!$built['success']) {
            echo json_encode(['success' => false, 'message' => $built['message']]);
            die;
        }

        $check = $this->opsdesk_orders_model->check_items_stock($built['items'], $quantity);

        echo json_encode([
            'success' => true,
            'data'    => array_merge($check, [
                'combo_id'       => $combo_id,
                'order_quantity' => $quantity,
            ]),
        ]);
        die;
    }

    /**
     * Parse pre-fill query params for new order form.
     *
     * @return array
     */
    private function parse_order_prefill()
    {
        $prefill = [
            'combo_id'       => (int) $this->input->get('combo_id'),
            'quantity'       => max(1, (int) $this->input->get('quantity')),
            'substitutions'  => [],
            'removed'        => [],
            'added'          => [],
            'quantities'     => [],
        ];

        $items_json = $this->input->get('items');
        if ($items_json) {
            $decoded = json_decode($items_json, true);
            if (is_array($decoded)) {
                if (isset($decoded['substitutions']) && is_array($decoded['substitutions'])) {
                    $prefill['substitutions'] = $decoded['substitutions'];
                }
                if (isset($decoded['removed']) && is_array($decoded['removed'])) {
                    $prefill['removed'] = $decoded['removed'];
                }
                if (isset($decoded['added']) && is_array($decoded['added'])) {
                    $prefill['added'] = $decoded['added'];
                }
                if (isset($decoded['quantities']) && is_array($decoded['quantities'])) {
                    $prefill['quantities'] = $decoded['quantities'];
                }
            }
        }

        return $prefill;
    }

    /**
     * Parse substitution/override payload from POST.
     *
     * @return array
     */
    private function parse_order_overrides_from_post()
    {
        $overrides = [
            'substitutions' => [],
            'removed'       => [],
            'added'         => [],
            'quantities'    => [],
        ];

        $raw = $this->input->post('order_overrides');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $overrides = array_merge($overrides, $decoded);
            }
        }

        return $overrides;
    }
}
