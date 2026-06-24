<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Opsdesk_inventory_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $CI = &get_instance();
        $CI->load->helper(OPSDESK_MODULE_NAME . '/opsdesk');
    }

    /**
     * Get inventory record(s).
     *
     * @param int|string $id
     * @return object|array|null
     */
    public function get($id = '')
    {
        if (is_numeric($id)) {
            $this->db->where('id', (int) $id);

            return $this->db->get(db_prefix() . 'opsdesk_inventory')->row();
        }

        $this->db->order_by('sku', 'ASC');

        return $this->db->get(db_prefix() . 'opsdesk_inventory')->result_array();
    }

    /**
     * Get inventory by SKU.
     *
     * @param string $sku
     * @return object|null
     */
    public function get_by_sku($sku)
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $this->db->where('sku', $sku);

        return $this->db->get(db_prefix() . 'opsdesk_inventory')->row();
    }

    /**
     * Get inventory by Perfex product item ID.
     *
     * @param int $product_item_id
     * @return object|null
     */
    public function get_by_product_item_id($product_item_id)
    {
        if (!is_numeric($product_item_id)) {
            return null;
        }

        $this->db->where('product_item_id', (int) $product_item_id);

        return $this->db->get(db_prefix() . 'opsdesk_inventory')->row();
    }

    /**
     * Net available quantity from an opsdesk_inventory row.
     *
     * @param object $inventory
     * @return float
     */
    public function get_net_available($inventory)
    {
        $available = (float) $inventory->quantity_available;
        $reserved  = (float) $inventory->quantity_reserved;
        $net       = $available - $reserved;

        return $net > 0 ? $net : 0.0;
    }

    /**
     * Available stock for a combo component.
     *
     * When the Warehouse module is active, reads live totals from
     * tblinventory_manage and subtracts OpsDesk reservations.
     * Otherwise falls back to tblopsdesk_inventory.
     *
     * @param string   $sku
     * @param int|null $product_item_id
     * @return float
     */
    public function get_available_for_combo_item($sku, $product_item_id = null)
    {
        $reserved = $this->get_reserved_quantity($sku, $product_item_id);

        if (opsdesk_is_warehouse_module_active() && is_numeric($product_item_id)) {
            $warehouse_stock = opsdesk_get_warehouse_stock_total((int) $product_item_id);
            $net             = $warehouse_stock - $reserved;

            return $net > 0 ? $net : 0.0;
        }

        $inventory = $this->get_by_sku($sku);

        if (!$inventory && is_numeric($product_item_id)) {
            $inventory = $this->get_by_product_item_id((int) $product_item_id);
        }

        if ($inventory) {
            return $this->get_net_available($inventory);
        }

        return 0.0;
    }

    /**
     * Reserved quantity tracked in OpsDesk (orders not yet fulfilled).
     *
     * @param string   $sku
     * @param int|null $product_item_id
     * @return float
     */
    public function get_reserved_quantity($sku, $product_item_id = null)
    {
        $inventory = $this->get_by_sku(trim($sku));

        if (!$inventory && is_numeric($product_item_id)) {
            $inventory = $this->get_by_product_item_id((int) $product_item_id);
        }

        if (!$inventory) {
            return 0.0;
        }

        $reserved = (float) $inventory->quantity_reserved;

        return $reserved > 0 ? $reserved : 0.0;
    }

    /**
     * Create inventory record.
     *
     * @param array $data
     * @return int|false
     */
    public function add($data)
    {
        $payload = $this->prepare_payload($data);

        if (empty($payload['sku'])) {
            return false;
        }

        if ($this->get_by_sku($payload['sku'])) {
            return false;
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $this->db->insert(db_prefix() . 'opsdesk_inventory', $payload);

        return $this->db->insert_id() ?: false;
    }

    /**
     * Update inventory record.
     *
     * @param array $data
     * @param int   $id
     * @return bool
     */
    public function update($data, $id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $payload = $this->prepare_payload($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');

        unset($payload['sku']);

        $this->db->where('id', (int) $id);
        $this->db->update(db_prefix() . 'opsdesk_inventory', $payload);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Delete inventory record.
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $this->db->where('id', (int) $id);
        $this->db->delete(db_prefix() . 'opsdesk_inventory');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Upsert inventory by SKU.
     *
     * @param array $data
     * @return int|false
     */
    public function upsert_by_sku($data)
    {
        $payload = $this->prepare_payload($data);
        $sku     = $payload['sku'] ?? '';

        if ($sku === '') {
            return false;
        }

        $existing = $this->get_by_sku($sku);

        if ($existing) {
            $this->db->where('id', (int) $existing->id);
            $payload['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update(db_prefix() . 'opsdesk_inventory', $payload);

            return (int) $existing->id;
        }

        return $this->add($data);
    }

    /**
     * Sync or update the OpsDesk inventory cache row for a product.
     *
     * @param int   $product_item_id
     * @param array $post
     * @return void
     */
    public function sync_from_product($product_item_id, $post = [])
    {
        if (!is_numeric($product_item_id)) {
            return;
        }

        $product = opsdesk_get_product_by_id((int) $product_item_id);
        if (!$product) {
            return;
        }

        $sku = trim($post['sku'] ?? '');
        if ($sku === '') {
            $sku = $product['sku'];
        }

        if ($sku === '') {
            return;
        }

        $quantity_available = 0.0;
        if (opsdesk_is_warehouse_module_active()) {
            $quantity_available = opsdesk_get_warehouse_stock_total((int) $product_item_id);
        }

        $this->upsert_by_sku([
            'sku'                => $sku,
            'product_item_id'    => (int) $product_item_id,
            'custom_product_ref' => $post['custom_product_ref'] ?? null,
            'quantity_available' => $quantity_available,
            'quantity_reserved'  => 0,
        ]);
    }

    /**
     * Sanitize inventory payload.
     *
     * @param array $data
     * @return array
     */
    private function prepare_payload($data)
    {
        $product_item_id = !empty($data['product_item_id']) && is_numeric($data['product_item_id'])
            ? (int) $data['product_item_id']
            : null;

        $custom_ref = !empty($data['custom_product_ref'])
            ? trim($data['custom_product_ref'])
            : null;

        $sku = trim($data['sku'] ?? '');

        if ($sku === '' && $product_item_id) {
            $product = opsdesk_get_product_by_id($product_item_id);
            $sku     = $product ? $product['sku'] : 'ITEM-' . $product_item_id;
        }

        $qty_available = isset($data['quantity_available']) ? (float) $data['quantity_available'] : 0.0;
        $qty_reserved  = isset($data['quantity_reserved']) ? (float) $data['quantity_reserved'] : 0.0;

        if ($qty_available < 0) {
            $qty_available = 0.0;
        }

        if ($qty_reserved < 0) {
            $qty_reserved = 0.0;
        }

        return [
            'sku'                => $sku,
            'product_item_id'    => $product_item_id,
            'custom_product_ref' => $custom_ref,
            'quantity_available' => $qty_available,
            'quantity_reserved'  => $qty_reserved,
        ];
    }
}
