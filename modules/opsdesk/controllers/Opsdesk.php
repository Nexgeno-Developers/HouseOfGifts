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
$this->load->model('opsdesk/opsdesk_product_statuses_model');
         $this->load->model('opsdesk/opsdesk_packing_types_model');
         $this->load->model('opsdesk/opsdesk_transport_mediums_model');
     }

    /**
     * Default route — redirect to inventory viewer.
     */
    public function index()
    {
        redirect(admin_url('opsdesk/inventory'));
    }

    /**
     * AJAX: search CRM clients for the customer picker.
     */
    public function clients()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!opsdesk_can_create_orders() && !opsdesk_can_view_orders()) {
            ajax_access_denied();
        }

        $q = $this->input->post('q') ?: '';
        $clients = opsdesk_search_clients($q, 50);

        echo json_encode(['success' => true, 'clients' => $clients]);
        die;
    }

    /**
     * Settings — product/order statuses and other module configuration.
     */
    public function settings($group = 'product_statuses')
    {
        if (!has_permission('opsdesk', '', 'view') && !staff_can('view', 'opsdesk') && !is_admin()) {
            access_denied('opsdesk');
        }

        $data['group']  = $group;
        $data['title']  = _l('opsdesk_settings');
        $data['tabs']   = ['product_statuses', 'packing_types', 'transport_mediums', 'inventory'];

        // Only allow known tabs; fall back to the default to avoid loading a
        // non-existent view via a crafted URL segment.
        if (!in_array($group, $data['tabs'], true)) {
            $group = 'product_statuses';
            $data['group'] = $group;
        }

        if ($group === 'product_statuses') {
            $data['product_statuses'] = $this->opsdesk_product_statuses_model->get();
        }

        if ($group === 'packing_types') {
            $data['packing_types'] = $this->opsdesk_packing_types_model->get();
        }

        if ($group === 'transport_mediums') {
            $data['transport_mediums'] = $this->opsdesk_transport_mediums_model->get();
        }

        if ($group === 'inventory') {
            $data['bypass_stock_check'] = opsdesk_bypass_stock_check();
        }

        $data['tab_view'] = 'includes/' . $group;

        $this->app_scripts->add(
            'opsdesk-settings-js',
            opsdesk_asset_url('assets/js/opsdesk_settings.js')
        );

        $this->load->view('settings', $data);
    }

    /**
     * POST: save inventory settings (stock-check bypass).
     */
    public function save_inventory_settings()
    {
        if (!opsdesk_can_manage_settings()) {
            access_denied('opsdesk');
        }

        // Keyed on the request method, not on a non-empty payload: an
        // unchecked switch can leave the POST body completely empty.
        if ($this->input->method() === 'post') {
            $enabled = $this->input->post('opsdesk_bypass_stock_check') == '1' ? '1' : '0';
            update_option('opsdesk_bypass_stock_check', $enabled);
            set_alert('success', _l('updated_successfully', _l('settings')));
        }

        redirect(admin_url('opsdesk/settings/inventory'));
    }

    /**
     * AJAX/POST: save (add/update) a product status from the Settings tab.
     */
    public function product_status_setting($id = '')
    {
        if (!opsdesk_can_manage_settings()) {
            access_denied('opsdesk');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $data['is_active']    = isset($data['is_active']) ? 1 : 0;
            $data['display_order'] = (int) ($data['display_order'] ?? 0);

            if (!$this->input->post('id')) {
                $mess = $this->opsdesk_product_statuses_model->add($data);

                if (is_numeric($mess) && (int) $mess > 0) {
                    set_alert('success', _l('added_successfully', _l('opsdesk_product_status')));
                } elseif ($mess === 'duplicate_order') {
                    set_alert('warning', _l('opsdesk_product_status_display_order_in_use'));
                } elseif ($mess === 'duplicate_key') {
                    set_alert('warning', _l('opsdesk_product_status_key_in_use'));
                } else {
                    set_alert('warning', _l('opsdesk_problem_adding'));
                }

                redirect(admin_url('opsdesk/settings/product_statuses'));
            }

            $pid   = (int) $data['id'];
            unset($data['id']);
            $success = $this->opsdesk_product_statuses_model->update($data, $pid);

            if ($success === true) {
                set_alert('success', _l('updated_successfully', _l('opsdesk_product_status')));
            } elseif ($success === 'duplicate_order') {
                set_alert('warning', _l('opsdesk_product_status_display_order_in_use'));
            } elseif ($success === 'duplicate_key') {
                set_alert('warning', _l('opsdesk_product_status_key_in_use'));
            } else {
                set_alert('warning', _l('opsdesk_problem_updating'));
            }

            redirect(admin_url('opsdesk/settings/product_statuses'));
        }
    }

    /**
     * Delete a product status.
     */
    public function delete_product_status($id)
    {
        if (!opsdesk_can_manage_settings()) {
            access_denied('opsdesk');
        }

        if (!$id) {
            redirect(admin_url('opsdesk/settings/product_statuses'));
        }

        $response = $this->opsdesk_product_statuses_model->delete($id);

        if ($response) {
            set_alert('success', _l('deleted', _l('opsdesk_product_status')));
        } else {
            set_alert('warning', _l('opsdesk_problem_deleting'));
        }

        redirect(admin_url('opsdesk/settings/product_statuses'));
    }

    /**
     * AJAX/POST: save (add/update) a packing type from the Settings tab.
     */
    public function packing_type_setting($id = '')
    {
        if (!opsdesk_can_manage_settings()) {
            access_denied('opsdesk');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $data['is_active']    = isset($data['is_active']) ? 1 : 0;
            $data['display_order'] = (int) ($data['display_order'] ?? 0);

            if (!$this->input->post('id')) {
                $mess = $this->opsdesk_packing_types_model->add($data);

                if (is_numeric($mess) && (int) $mess > 0) {
                    set_alert('success', _l('added_successfully', _l('opsdesk_packing_type')));
                } elseif ($mess === 'duplicate_order') {
                    set_alert('warning', _l('opsdesk_packing_type_display_order_in_use'));
                } elseif ($mess === 'duplicate_key') {
                    set_alert('warning', _l('opsdesk_packing_type_key_in_use'));
                } else {
                    set_alert('warning', _l('opsdesk_problem_adding_packing_type'));
                }

                redirect(admin_url('opsdesk/settings/packing_types'));
            }

            $pid   = (int) $data['id'];
            unset($data['id']);
            $success = $this->opsdesk_packing_types_model->update($data, $pid);

            if ($success === true) {
                set_alert('success', _l('updated_successfully', _l('opsdesk_packing_type')));
            } elseif ($success === 'duplicate_order') {
                set_alert('warning', _l('opsdesk_packing_type_display_order_in_use'));
            } elseif ($success === 'duplicate_key') {
                set_alert('warning', _l('opsdesk_packing_type_key_in_use'));
            } else {
                set_alert('warning', _l('opsdesk_problem_updating_packing_type'));
            }

            redirect(admin_url('opsdesk/settings/packing_types'));
        }
    }

    /**
     * Delete a packing type.
     */
    public function delete_packing_type($id)
    {
        if (!opsdesk_can_manage_settings()) {
            access_denied('opsdesk');
        }

        if (!$id) {
            redirect(admin_url('opsdesk/settings/packing_types'));
        }

        $response = $this->opsdesk_packing_types_model->delete($id);

        if ($response) {
            set_alert('success', _l('deleted', _l('opsdesk_packing_type')));
        } else {
            set_alert('warning', _l('opsdesk_problem_deleting_packing_type'));
        }

        redirect(admin_url('opsdesk/settings/packing_types'));
    }

    /**
     * AJAX/POST: save (add/update) a transport medium from the Settings tab.
     */
    public function transport_medium_setting($id = '')
    {
        if (!opsdesk_can_manage_settings()) {
            access_denied('opsdesk');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $data['is_active']    = isset($data['is_active']) ? 1 : 0;
            $data['display_order'] = (int) ($data['display_order'] ?? 0);

            if (!$this->input->post('id')) {
                $mess = $this->opsdesk_transport_mediums_model->add($data);

                if (is_numeric($mess) && (int) $mess > 0) {
                    set_alert('success', _l('added_successfully', _l('opsdesk_transport_medium')));
                } elseif ($mess === 'duplicate_order') {
                    set_alert('warning', _l('opsdesk_transport_medium_display_order_in_use'));
                } elseif ($mess === 'duplicate_key') {
                    set_alert('warning', _l('opsdesk_transport_medium_key_in_use'));
                } else {
                    set_alert('warning', _l('opsdesk_problem_adding'));
                }

                redirect(admin_url('opsdesk/settings/transport_mediums'));
            }

            $pid   = (int) $data['id'];
            unset($data['id']);
            $success = $this->opsdesk_transport_mediums_model->update($data, $pid);

            if ($success === true) {
                set_alert('success', _l('updated_successfully', _l('opsdesk_transport_medium')));
            } elseif ($success === 'duplicate_order') {
                set_alert('warning', _l('opsdesk_transport_medium_display_order_in_use'));
            } elseif ($success === 'duplicate_key') {
                set_alert('warning', _l('opsdesk_transport_medium_key_in_use'));
            } else {
                set_alert('warning', _l('opsdesk_problem_updating'));
            }

            redirect(admin_url('opsdesk/settings/transport_mediums'));
        }
    }

    /**
     * Delete a transport medium.
     */
    public function delete_transport_medium($id)
    {
        if (!opsdesk_can_manage_settings()) {
            access_denied('opsdesk');
        }

        if (!$id) {
            redirect(admin_url('opsdesk/settings/transport_mediums'));
        }

        $response = $this->opsdesk_transport_mediums_model->delete($id);

        if ($response) {
            set_alert('success', _l('deleted', _l('opsdesk_transport_medium')));
        } else {
            set_alert('warning', _l('opsdesk_problem_deleting'));
        }

        redirect(admin_url('opsdesk/settings/transport_mediums'));
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
            opsdesk_asset_url('assets/js/opsdesk_inventory.js')
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
            if (isset($_FILES['combo_image']) && !empty($_FILES['combo_image']['name'])) {
                $img = opsdesk_handle_upload('combo_image');
                if ($img['success']) {
                    $_POST['image'] = $img['file'];
                } else {
                    set_alert('warning', _l('opsdesk_upload_failed') . ' ' . $img['message']);
                }
            }
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

        $status_filter  = $this->input->get('status');
        $priority_filter = $this->input->get('priority');
        $delivery_sort   = $this->input->get('sort_by_delivery_date');
        $own_only       = !opsdesk_can_view_all_orders();

        $data['title']         = _l('opsdesk_all_orders');
        $data['orders']        = $this->opsdesk_orders_model->get('', [
            'own_only' => $own_only,
            'staff_id' => get_staff_user_id(),
            'status'   => $status_filter,
            'priority' => $priority_filter,
            'sort_by_delivery_date' => $delivery_sort,
        ]);
        $data['status_filter']   = $status_filter;
        $data['priority_filter'] = $priority_filter;
        $data['delivery_sort']   = $delivery_sort;
        $data['status_counts'] = $this->opsdesk_orders_model->count_by_status([
            'own_only' => $own_only,
            'staff_id' => get_staff_user_id(),
        ]);
        $data['status_filters'] = [];
        foreach (opsdesk_get_order_status_option_keys(true) as $key) {
            $data['status_filters'][] = [
                'key'   => $key,
                'label' => opsdesk_get_order_status_label($key),
            ];
        }
        $data['can_edit']      = opsdesk_can_edit_orders();
        $data['can_delete']    = opsdesk_can_delete_orders();
        $data['can_create']    = opsdesk_can_create_orders();
        $data['global_view']   = !$own_only;
        $data['staff_members'] = opsdesk_get_staff_members();

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
        // Get transport mediums for dropdown
        $transport_mediums = $this->opsdesk_transport_mediums_model->get();
        $transport_mediums_dropdown = [];
        foreach ($transport_mediums as $tm) {
            $transport_mediums_dropdown[$tm['id']] = $tm['name'];
        }
        $data['transport_mediums'] = $transport_mediums_dropdown;
        $data['staff_members'] = opsdesk_get_staff_members();
        $data['prefill']       = $prefill;
        $data['prefill_customer_name'] = '';
        if (!empty($prefill['customer_id'])) {
            $c = get_client($prefill['customer_id']);
            if ($c) {
                $data['prefill_customer_name'] = $c->company;
            }
        }

        $this->app_scripts->add(
            'opsdesk-orders-js',
            opsdesk_asset_url('assets/js/opsdesk_orders.js')
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
        $data['staff_members']  = opsdesk_get_staff_members();
        $data['packed_by_name'] = opsdesk_get_assigned_name($order['packed_by'] ?? null);
        $data['can_edit']       = opsdesk_can_edit_orders();
        $data['can_cancel_own'] = (int) $order['created_by'] === (int) get_staff_user_id()
            && $order['status'] === 'pending';
        $data['can_cancel_any'] = $data['can_edit']
            && in_array($order['status'], ['pending', 'in_progress', 'packed'], true);
        $data['next_statuses']  = $this->opsdesk_orders_model->get_next_statuses($order['status']);

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
        $quantity     = (float) $this->input->post('quantity');
        $packing_type = trim($this->input->post('packing_type') ?? '');
        $priority     = (int) $this->input->post('priority');
        $priority     = in_array($priority, [0, 1], true) ? $priority : 0;
        $transport_medium_id = (int) $this->input->post('transport_medium_id');
        $delivery_date = trim($this->input->post('delivery_date') ?? '');
        $packing_types = array_keys(opsdesk_get_packing_types());

        if ($combo_id <= 0 || $quantity < 1 || !in_array($packing_type, $packing_types, true) || $transport_medium_id <= 0) {
            set_alert('warning', _l('opsdesk_invalid_request'));
            redirect(admin_url('opsdesk/order'));
        }

        if (!$this->opsdesk_transport_mediums_model->get($transport_medium_id)) {
            set_alert('warning', _l('opsdesk_invalid_request'));
            redirect(admin_url('opsdesk/order'));
        }

        if ($delivery_date !== '') {
            $delivery_date = date('Y-m-d', strtotime($delivery_date));
            if ($delivery_date === false) {
                set_alert('warning', _l('opsdesk_invalid_request'));
                redirect(admin_url('opsdesk/order'));
            }
        } else {
            $delivery_date = null;
        }

        $overrides = $this->parse_order_overrides_from_post();
        $built     = $this->opsdesk_orders_model->build_order_items($combo_id, $quantity, $overrides);

        if (!$built['success']) {
            set_alert('warning', $built['message']);
            redirect(admin_url('opsdesk/order'));
        }

        // Customer linkage
        $customer_id   = (int) $this->input->post('customer_id');
        $customer_city = trim($this->input->post('customer_city') ?? '');

        if ($customer_id > 0) {
            $client = get_client($customer_id);
            if (!$client) {
                set_alert('warning', _l('opsdesk_customer_not_found'));
                redirect(admin_url('opsdesk/order?combo_id=' . $combo_id . '&quantity=' . $quantity));
            }
            if ($customer_city === '') {
                $customer_city = trim($client->city ?? '');
            }
        }

        // Mandatory bill upload
        $bill_upload = opsdesk_handle_upload('bill_file');
        if (!$bill_upload['success']) {
            set_alert('warning', _l('opsdesk_bill_required') . ' ' . $bill_upload['message']);
            redirect(admin_url('opsdesk/order?combo_id=' . $combo_id . '&quantity=' . $quantity));
        }

        // Optional payment upload
        $payment_file = '';
        if (isset($_FILES['payment_file']) && !empty($_FILES['payment_file']['name'])) {
            $payment_upload = opsdesk_handle_upload('payment_file');
            if (!$payment_upload['success']) {
                set_alert('warning', _l('opsdesk_invalid_file_type') . ' ' . $payment_upload['message']);
                redirect(admin_url('opsdesk/order?combo_id=' . $combo_id . '&quantity=' . $quantity));
            }
            $payment_file = $payment_upload['file'];
        }

        $combo = $built['combo'];
        $result = $this->opsdesk_orders_model->create_order_with_reservation([
            'combo_id'     => $combo_id,
            'combo_name'   => $combo->name,
            'customer_id'  => $customer_id > 0 ? $customer_id : null,
            'customer_city' => $customer_city,
            'quantity'     => $quantity,
            'packing_type' => $packing_type,
            'transport_medium_id' => $transport_medium_id,
            'delivery_date' => $delivery_date,
            'notes'        => trim($this->input->post('notes') ?? ''),
            'bill_file'    => $bill_upload['file'],
            'payment_file' => $payment_file,
            'priority'     => $priority,
            'created_by'   => get_staff_user_id(),
        ], $built['items']);

        if (!$result['success']) {
            set_alert('warning', $result['message']);
            redirect(admin_url('opsdesk/order?combo_id=' . $combo_id . '&quantity=' . $quantity));
        }

        // FR-021.1: fire notification AFTER commit (outside transaction).
        try {
            opsdesk_notify_new_order($result['order_id'], get_staff_user_id());
        } catch (Exception $e) {
            log_activity('OpsDesk new order notification failed [ID:' . $result['order_id'] . ']: ' . $e->getMessage());
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

        // A packer must be assigned before the order can move on. The accept
        // form supplies packed_by in this same request, so only block when
        // neither the order nor the submission has one.
        if (!(int) $this->input->post('packed_by')) {
            $this->block_locked_order(
                $this->opsdesk_orders_model->get($order_id),
                admin_url('opsdesk/order/' . $order_id . '?tab=assign')
            );
        }

        // Handle file uploads for completion
        if ($new_status === 'completed') {
            // LR copy upload
            if (isset($_FILES['lr_copy']) && !empty($_FILES['lr_copy']['name'])) {
                $upload = opsdesk_handle_upload('lr_copy');
                if (!$upload['success']) {
                    if ($this->input->is_ajax_request()) {
                        echo json_encode(['success' => false, 'message' => _l('opsdesk_upload_failed') . ' ' . $upload['message']]);
                        die;
                    }
                    set_alert('warning', _l('opsdesk_upload_failed') . ' ' . $upload['message']);
                    redirect(admin_url('opsdesk/order/' . $order_id));
                }
                $extra['lr_copy'] = $upload['file'];
            }

            // Carton photo upload
            if (isset($_FILES['carton_photo']) && !empty($_FILES['carton_photo']['name'])) {
                $upload = opsdesk_handle_upload('carton_photo');
                if (!$upload['success']) {
                    if ($this->input->is_ajax_request()) {
                        echo json_encode(['success' => false, 'message' => _l('opsdesk_upload_failed') . ' ' . $upload['message']]);
                        die;
                    }
                    set_alert('warning', _l('opsdesk_upload_failed') . ' ' . $upload['message']);
                    redirect(admin_url('opsdesk/order/' . $order_id));
                }
                $extra['carton_photo'] = $upload['file'];
            }

            // Carton count
            if ($this->input->post('carton_count')) {
                $extra['carton_count'] = (int) $this->input->post('carton_count');
            }

            // Counted by
            if ($this->input->post('count_by')) {
                $extra['count_by'] = (int) $this->input->post('count_by');
            }
        }

        // Accepting order (pending -> in_progress) requires packed_by
        if ($new_status === 'in_progress' && !(int) $this->input->post('packed_by')) {
            $error = _l('opsdesk_packed_by_required');
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => false, 'message' => $error]);
                die;
            }
            set_alert('warning', $error);
            redirect(admin_url('opsdesk/order/' . $order_id));
        }

        if ($this->input->post('packed_by')) {
            $extra['packed_by'] = (int) $this->input->post('packed_by');
        }

        if ($this->input->post('packing_type')) {
            $packing_type = trim($this->input->post('packing_type'));
            $packing_types = array_keys(opsdesk_get_packing_types());
            if (in_array($packing_type, $packing_types, true)) {
                $extra['packing_type'] = $packing_type;
            }
        }

        if ($this->input->post('notes')) {
            $extra['notes'] = trim($this->input->post('notes'));
        }

        if ($this->input->post('delivery_date') !== null) {
            $delivery_date = trim($this->input->post('delivery_date'));
            if ($delivery_date !== '') {
                $delivery_date = date('Y-m-d', strtotime($delivery_date));
                if ($delivery_date === false) {
                    $delivery_date = null;
                }
            } else {
                $delivery_date = null;
            }
            $extra['delivery_date'] = $delivery_date;
        }

        if ($this->input->post('transport_medium_id') !== null) {
            $extra['transport_medium_id'] = (int) $this->input->post('transport_medium_id');
        }

        $result = $this->opsdesk_orders_model->update_status(
            $order_id,
            $new_status,
            get_staff_user_id(),
            $extra
        );

        // FR-021.2: notify creator on status change (not for cancellations,
        // which route through cancel_order and fire opsdesk_notify_cancellation).
        if ($result['success'] && $new_status !== 'cancelled') {
            try {
                opsdesk_notify_status_change($order_id, $new_status, get_staff_user_id());
            } catch (Exception $e) {
                log_activity('OpsDesk status change notification failed [ID:' . $order_id . ']: ' . $e->getMessage());
            }
        }

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
     * Stop the request when the order has no packer assigned.
     *
     * @param array  $order
     * @param string $redirect_url
     * @return void
     */
    private function block_locked_order($order, $redirect_url)
    {
        if (!opsdesk_order_is_locked($order)) {
            return;
        }

        $message = _l('opsdesk_order_locked_no_packer');

        if ($this->input->is_ajax_request()) {
            echo json_encode(['success' => false, 'message' => $message]);
            die;
        }

        set_alert('warning', $message);
        redirect($redirect_url);
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

        // FR-021.3/4: fire cancellation notification AFTER commit.
        if ($result['success']) {
            try {
                opsdesk_notify_cancellation((int) $id, get_staff_user_id());
            } catch (Exception $e) {
                log_activity('OpsDesk cancellation notification failed [ID:' . $id . ']: ' . $e->getMessage());
            }
        }

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
     * Permanently delete a cancelled or completed order.
     *
     * Active orders must be cancelled first so reserved stock is released by
     * the normal cancellation workflow.
     *
     * @param mixed $id
     * @return void
     */
    public function delete_order($id = '')
    {
        if (!opsdesk_can_delete_orders()) {
            access_denied('opsdesk_orders');
        }

        if (!is_numeric($id)) {
            redirect(admin_url('opsdesk/orders'));
        }

        $order = $this->opsdesk_orders_model->get((int) $id);
        if (!$order) {
            set_alert('warning', _l('opsdesk_order_not_found'));
            redirect(admin_url('opsdesk/orders'));
        }

        if (!in_array($order['status'], ['cancelled', 'completed'], true)) {
            set_alert('warning', _l('opsdesk_order_delete_requires_final_status'));
            redirect(admin_url('opsdesk/orders'));
        }

        if ($this->opsdesk_orders_model->delete((int) $id)) {
            log_activity('OpsDesk Order Deleted [ID:' . (int) $id . ']');
            set_alert('success', _l('deleted', _l('opsdesk_order')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('opsdesk_order')));
        }

        redirect(admin_url('opsdesk/orders'));
    }

    /**
     * POST: upload / replace the payment_file for an order.
     */
    public function upload_payment_file($order_id = '')
    {
        $this->upload_order_attachment($order_id, 'payment_file');
    }

    /**
     * POST: upload / replace the LR copy for an order.
     */
    public function upload_lr_copy($order_id = '')
    {
        $this->upload_order_attachment($order_id, 'lr_copy');
    }

    /**
     * POST: upload one or more carton photos (appended, not replaced).
     */
    public function upload_carton_photo($order_id = '')
    {
        if (!opsdesk_can_edit_orders()) {
            access_denied('opsdesk_orders');
        }

        if (!is_numeric($order_id)) {
            redirect(admin_url('opsdesk/orders'));
        }

        $order = $this->opsdesk_orders_model->get((int) $order_id);
        if (!$order) {
            set_alert('warning', _l('opsdesk_order_not_found'));
            redirect(admin_url('opsdesk/orders'));
        }

        $this->block_locked_order($order, admin_url('opsdesk/order/' . $order_id . '?tab=assign'));

        $field = isset($_FILES['carton_photos']) ? 'carton_photos' : 'carton_photo';
        $upload = opsdesk_handle_multi_upload($field);
        if (!$upload['success']) {
            set_alert('warning', _l('opsdesk_upload_failed') . ' ' . ($upload['message'] ?? ''));
            redirect(admin_url('opsdesk/order/' . $order_id));
        }

        $this->opsdesk_orders_model->append_carton_photos((int) $order_id, $upload['files']);

        set_alert('success', _l('opsdesk_file_uploaded'));
        redirect(admin_url('opsdesk/order/' . $order_id));
    }

    /**
     * Shared handler for order attachment uploads.
     *
     * @param mixed  $order_id
     * @param string $field
     */
    private function upload_order_attachment($order_id, $field)
    {
        if (!opsdesk_can_edit_orders()) {
            access_denied('opsdesk_orders');
        }

        $allowed = ['payment_file', 'lr_copy'];
        if (!in_array($field, $allowed, true) || !is_numeric($order_id)) {
            redirect(admin_url('opsdesk/orders'));
        }

        $order = $this->opsdesk_orders_model->get((int) $order_id);
        if (!$order) {
            set_alert('warning', _l('opsdesk_order_not_found'));
            redirect(admin_url('opsdesk/orders'));
        }

        $this->block_locked_order($order, admin_url('opsdesk/order/' . $order_id . '?tab=assign'));

        if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) {
            set_alert('warning', _l('opsdesk_upload_failed'));
            redirect(admin_url('opsdesk/order/' . $order_id));
        }

        $upload = opsdesk_handle_upload($field);
        if (!$upload['success']) {
            set_alert('warning', _l('opsdesk_upload_failed') . ' ' . $upload['message']);
            redirect(admin_url('opsdesk/order/' . $order_id));
        }

        $this->opsdesk_orders_model->update_order_file((int) $order_id, $field, $upload['file']);

        set_alert('success', _l('opsdesk_file_uploaded'));
        redirect(admin_url('opsdesk/order/' . $order_id));
    }

    /**
     * POST: update order priority (Operations/Admin only — Sales blocked).
     *
     * @param int $order_id
     */
    public function update_priority($order_id = '')
    {
        if (!opsdesk_can_edit_orders()) {
            if ($this->input->is_ajax_request()) {
                ajax_access_denied();
            }
            access_denied('opsdesk_orders');
        }

        if (!is_numeric($order_id)) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => false, 'message' => _l('opsdesk_order_not_found')]);
                die;
            }
            redirect(admin_url('opsdesk/orders'));
        }

        $this->block_locked_order(
            $this->opsdesk_orders_model->get((int) $order_id),
            admin_url('opsdesk/order/' . $order_id . '?tab=assign')
        );

        $priority = (int) $this->input->post('priority');
        if (!in_array($priority, [0, 1], true)) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => false, 'message' => _l('opsdesk_invalid_request')]);
                die;
            }
            set_alert('warning', _l('opsdesk_invalid_request'));
            redirect(admin_url('opsdesk/order/' . $order_id));
        }

        $result = $this->opsdesk_orders_model->update_priority(
            (int) $order_id,
            $priority,
            get_staff_user_id()
        );

        if ($this->input->is_ajax_request()) {
            echo json_encode($result);
            die;
        }

        if ($result['success']) {
            set_alert('success', _l('updated_successfully', _l('opsdesk_order')));
        } else {
            set_alert('warning', $result['message'] ?? _l('opsdesk_problem_updating'));
        }

        redirect(admin_url('opsdesk/order/' . $order_id . '?tab=priority'));
    }

    /**
     * POST: assign / reassign the packer for an order (Operations/Admin).
     */
    public function assign_order($order_id = '')
    {
        if (!opsdesk_can_edit_orders()) {
            if ($this->input->is_ajax_request()) {
                ajax_access_denied();
            }
            access_denied('opsdesk_orders');
        }

        if (!is_numeric($order_id)) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => false, 'message' => _l('opsdesk_order_not_found')]);
                die;
            }
            redirect(admin_url('opsdesk/orders'));
        }

        $packed_by    = (int) $this->input->post('packed_by');
        $count_by     = $this->input->post('count_by');
        $carton_count = $this->input->post('carton_count');

        // Read raw POST as well — XSS filtering can return false for missing keys.
        if ($count_by === false && isset($_POST['count_by'])) {
            $count_by = $_POST['count_by'];
        }
        if ($carton_count === false && isset($_POST['carton_count'])) {
            $carton_count = $_POST['carton_count'];
        }

        $result = $this->opsdesk_orders_model->save_staff_assignment(
            (int) $order_id,
            $packed_by,
            $count_by,
            $carton_count,
            get_staff_user_id()
        );

        if ($this->input->is_ajax_request()) {
            echo json_encode($result);
            die;
        }

        if ($result['success']) {
            set_alert('success', _l('updated_successfully', _l('opsdesk_assignment')));
        } else {
            set_alert('warning', $result['message'] ?? _l('opsdesk_problem_updating'));
        }

        redirect(admin_url('opsdesk/order/' . $order_id . '?tab=assign'));
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
            'customer_id'    => (int) $this->input->get('customer_id'),
            'customer_city'  => trim($this->input->get('customer_city') ?? ''),
            'delivery_date'  => trim($this->input->get('delivery_date') ?? ''),
            'substitutions'  => [],
            'removed'        => [],
            'added'         => [],
            'quantities'    => [],
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
