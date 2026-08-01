<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Opsdesk_combos_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $CI = &get_instance();
        $CI->load->helper(OPSDESK_MODULE_NAME . '/opsdesk');
        $this->load->model('opsdesk/opsdesk_inventory_model');
    }

    /**
     * Get combo(s).
     *
     * @param int|string $id
     * @param bool       $active_only
     * @return object|array|null
     */
    public function get($id = '', $active_only = false)
    {
        if (is_numeric($id)) {
            $this->db->where('id', (int) $id);

            return $this->db->get(db_prefix() . 'opsdesk_combos')->row();
        }

        if ($active_only) {
            $this->db->where('status', 1);
        }

        $this->db->order_by('name', 'ASC');

        return $this->db->get(db_prefix() . 'opsdesk_combos')->result_array();
    }

    /**
     * Create a combo.
     *
     * @param array $data
     * @return int|false
     */
    public function add($data)
    {
        $payload = $this->prepare_combo_payload($data);
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $this->db->insert(db_prefix() . 'opsdesk_combos', $payload);

        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            log_activity('OpsDesk Combo Created [ID:' . $insert_id . ', Name:' . $payload['name'] . ']');
        }

        return $insert_id ?: false;
    }

    /**
     * Update a combo.
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

        $payload = $this->prepare_combo_payload($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $id);
        $this->db->update(db_prefix() . 'opsdesk_combos', $payload);

        if ($this->db->affected_rows() > 0) {
            log_activity('OpsDesk Combo Updated [ID:' . $id . ']');

            return true;
        }

        return false;
    }

    /**
     * Delete a combo and its items (cascade).
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $combo = $this->get($id);
        if (!$combo) {
            return false;
        }

        $this->db->where('id', (int) $id);
        $this->db->delete(db_prefix() . 'opsdesk_combos');

        if ($this->db->affected_rows() > 0) {
            log_activity('OpsDesk Combo Deleted [ID:' . $id . ', Name:' . $combo->name . ']');

            return true;
        }

        return false;
    }

    /**
     * Get combo items with product details.
     *
     * @param int $combo_id
     * @return array
     */
    public function get_combo_items($combo_id)
    {
        if (!is_numeric($combo_id)) {
            return [];
        }

        $this->db->select(
            db_prefix() . 'opsdesk_combo_items.*, ' .
            db_prefix() . 'items.description as product_description, ' .
            db_prefix() . 'items.commodity_code, ' .
            db_prefix() . 'items.commodity_name, ' .
            db_prefix() . 'items.sku_code'
        );
        $this->db->from(db_prefix() . 'opsdesk_combo_items');
        $this->db->join(
            db_prefix() . 'items',
            db_prefix() . 'items.id = ' . db_prefix() . 'opsdesk_combo_items.product_item_id',
            'left'
        );
        $this->db->where(db_prefix() . 'opsdesk_combo_items.combo_id', (int) $combo_id);
        $this->db->order_by(db_prefix() . 'opsdesk_combo_items.id', 'ASC');

        $items = $this->db->get()->result_array();

        foreach ($items as &$item) {
            $item['product_name'] = $this->resolve_combo_item_label($item);
        }
        unset($item);

        return $items;
    }

    /**
     * Get a single combo item.
     *
     * @param int $id
     * @return object|null
     */
    public function get_combo_item($id)
    {
        if (!is_numeric($id)) {
            return null;
        }

        $this->db->where('id', (int) $id);

        return $this->db->get(db_prefix() . 'opsdesk_combo_items')->row();
    }

    /**
     * Add a component to a combo.
     *
     * @param array $data
     * @return int|false
     */
    public function add_combo_item($data)
    {
        $payload = $this->prepare_combo_item_payload($data);

        if (empty($payload['combo_id']) || empty($payload['sku'])) {
            return false;
        }

        // Guard against duplicate combo items for the same combo + SKU.
        // Return false to signal that no new item was inserted.
        $existing = $this->db->where('combo_id', (int) $payload['combo_id'])
            ->where('sku', $payload['sku'])
            ->get(db_prefix() . 'opsdesk_combo_items')
            ->row();

        if ($existing) {
            return false;
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $this->db->insert(db_prefix() . 'opsdesk_combo_items', $payload);

        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            log_activity('OpsDesk Combo Item Added [Combo ID:' . $payload['combo_id'] . ', SKU:' . $payload['sku'] . ']');
        }

        return $insert_id ?: false;
    }

    /**
     * Update a combo item.
     *
     * @param array $data
     * @param int   $id
     * @return bool
     */
    public function update_combo_item($data, $id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $payload = $this->prepare_combo_item_payload($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');

        // Guard against duplicate SKU within the same combo.
        if (!empty($payload['combo_id']) && !empty($payload['sku'])) {
            $duplicate = $this->db->where('combo_id', (int) $payload['combo_id'])
                ->where('sku', $payload['sku'])
                ->where('id !=', (int) $id)
                ->get(db_prefix() . 'opsdesk_combo_items')
                ->row();

            if ($duplicate) {
                return false;
            }
        }

        $this->db->where('id', (int) $id);
        $this->db->update(db_prefix() . 'opsdesk_combo_items', $payload);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Delete a combo item.
     *
     * @param int $id
     * @return bool
     */
    public function delete_combo_item($id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $this->db->where('id', (int) $id);
        $this->db->delete(db_prefix() . 'opsdesk_combo_items');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Check component availability for a combo order quantity.
     *
     * @param int   $combo_id
     * @param float $order_quantity
     * @return array
     */
    public function check_availability($combo_id, $order_quantity)
    {
        if (!is_numeric($combo_id) || $order_quantity <= 0) {
            return [
                'combo_id'        => (int) $combo_id,
                'order_quantity'  => (float) $order_quantity,
                'is_fulfillable'  => false,
                'components'      => [],
            ];
        }

        $items = $this->get_combo_items($combo_id);
        $components = [];
        $is_fulfillable = true;

        foreach ($items as $item) {
            $required_qty = (float) $item['quantity_per_unit'] * (float) $order_quantity;
            $product_id   = $item['product_item_id'] ? (int) $item['product_item_id'] : null;
            $available    = $this->opsdesk_inventory_model->get_available_for_combo_item(
                $item['sku'],
                $product_id
            );

            $is_sufficient = $available >= $required_qty;

            if (!$is_sufficient) {
                $is_fulfillable = false;
            }

            $components[] = [
                'combo_item_id'      => (int) $item['id'],
                'sku'                => $item['sku'],
                'product_name'       => $item['product_name'],
                'product_item_id'    => $product_id,
                'custom_product_ref' => $item['custom_product_ref'],
                'quantity_per_unit'  => (float) $item['quantity_per_unit'],
                'required_quantity'  => $required_qty,
                'available_stock'    => $available,
                'is_sufficient'      => $is_sufficient,
            ];
        }

        return [
            'combo_id'       => (int) $combo_id,
            'order_quantity' => (float) $order_quantity,
            'is_fulfillable' => $is_fulfillable && count($components) > 0,
            'components'     => $components,
        ];
    }

    /**
     * Resolve display label for a combo item row.
     *
     * @param array $item
     * @return string
     */
    private function resolve_combo_item_label($item)
    {
        if (!empty($item['product_item_id'])) {
            $product = opsdesk_get_product_by_id((int) $item['product_item_id']);
            if ($product) {
                return $product['label'];
            }
        }

        if (!empty($item['commodity_code']) || !empty($item['product_description'])) {
            return opsdesk_get_product_label([
                'commodity_code' => $item['commodity_code'] ?? '',
                'commodity_name' => $item['commodity_name'] ?? '',
                'description'    => $item['product_description'] ?? '',
                'sku_code'       => $item['sku_code'] ?? '',
                'id'             => $item['product_item_id'] ?? 0,
            ]);
        }

        return $item['custom_product_ref'] ?: ($item['sku'] ?: _l('opsdesk_unknown_product'));
    }

    /**
     * Sanitize combo payload.
     *
     * @param array $data
     * @return array
     */
    private function prepare_combo_payload($data)
    {
        $payload = [
            'name'        => trim($data['name'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'status'      => isset($data['status']) && (int) $data['status'] === 1 ? 1 : 0,
        ];

        if (array_key_exists('image', $data)) {
            $payload['image'] = !empty($data['image']) ? trim($data['image']) : null;
        }

        return $payload;
    }

    /**
     * Sanitize combo item payload.
     *
     * @param array $data
     * @return array
     */
    private function prepare_combo_item_payload($data)
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

        $qty = isset($data['quantity_per_unit']) ? (float) $data['quantity_per_unit'] : 1.0;
        if ($qty <= 0) {
            $qty = 1.0;
        }

        return [
            'combo_id'           => isset($data['combo_id']) ? (int) $data['combo_id'] : null,
            'product_item_id'    => $product_item_id,
            'custom_product_ref' => $custom_ref,
            'sku'                => $sku,
            'quantity_per_unit'  => $qty,
        ];
    }
}
