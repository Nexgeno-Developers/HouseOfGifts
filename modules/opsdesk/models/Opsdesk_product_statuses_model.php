<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk product / order statuses (managed from the Settings tab).
 */
class Opsdesk_product_statuses_model extends App_Model
{
    private $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'opsdesk_product_statuses';
    }

    /**
     * Get status(es), ordered by display_order.
     *
     * @param int|string $id
     * @return array|null
     */
    public function get($id = false)
    {
        if (is_numeric($id)) {
            $this->db->where('id', (int) $id);

            return $this->db->get($this->table)->row_array();
        }

        $this->db->order_by('display_order', 'ASC');
        $this->db->order_by('id', 'ASC');

        return $this->db->get($this->table)->result_array();
    }

    /**
     * @param array $data
     * @return int|string  insert_id | 'duplicate_order' | 'duplicate_key'
     */
    public function add($data)
    {
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        if ($this->display_order_exists($data['display_order'])) {
            return 'duplicate_order';
        }

        $status_key = trim((string) ($data['status_key'] ?? ''));
        if ($status_key === '' || $this->key_exists($status_key)) {
            return 'duplicate_key';
        }

        $data['status_key'] = $status_key;
        $data['is_active']  = !empty($data['is_active']) ? 1 : 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];

        $this->db->insert($this->table, $data);

        return $this->db->insert_id();
    }

    /**
     * @param array $data
     * @param int   $id
     * @return bool|string
     */
    public function update($data, $id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        if (isset($data['id'])) {
            unset($data['id']);
        }

        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        if ($this->display_order_exists($data['display_order'], $id)) {
            return 'duplicate_order';
        }

        if (isset($data['status_key'])) {
            $status_key = trim((string) $data['status_key']);
            if ($status_key === '' || $this->key_exists($status_key, $id)) {
                return 'duplicate_key';
            }
            $data['status_key'] = $status_key;
        }

        $data['is_active'] = !empty($data['is_active']) ? 1 : 0;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        $this->db->update($this->table, $data);

        return $this->db->affected_rows() >= 0;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->delete($this->table);

        return $this->db->affected_rows() > 0;
    }

    /**
     * @param int      $order
     * @param int|null $exclude_id
     * @return bool
     */
    private function display_order_exists($order, $exclude_id = null)
    {
        $this->db->select('id');
        $this->db->from($this->table);
        $this->db->where('display_order', (int) $order);

        if ($exclude_id !== null) {
            $this->db->where('id !=', (int) $exclude_id);
        }

        return (bool) $this->db->get()->row_array();
    }

    /**
     * @param string   $key
     * @param int|null $exclude_id
     * @return bool
     */
    private function key_exists($key, $exclude_id = null)
    {
        $this->db->select('id');
        $this->db->from($this->table);
        $this->db->where('status_key', trim((string) $key));

        if ($exclude_id !== null) {
            $this->db->where('id !=', (int) $exclude_id);
        }

        return (bool) $this->db->get()->row_array();
    }
}
