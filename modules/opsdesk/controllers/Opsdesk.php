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
}
