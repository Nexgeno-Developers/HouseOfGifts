<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.3 — Client Requests:
 *  - Bill/Invoice upload (mandatory) + Payment Received upload (optional) on orders
 *  - Customer linkage (customer_id + auto city)
 *  - Packed By (mandatory at accept) + Count By (mandatory at complete) staff assignment
 *  - Combo product images
 */
class Migration_Version_103 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();

        $orders = $prefix . 'opsdesk_orders';
        if ($CI->db->table_exists($orders)) {
            if (!$CI->db->field_exists('customer_id', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD `customer_id` int(11) DEFAULT NULL AFTER `combo_name`");
            }
            if (!$CI->db->field_exists('customer_city', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD `customer_city` varchar(100) DEFAULT NULL AFTER `customer_id`");
            }
            if (!$CI->db->field_exists('bill_file', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD `bill_file` varchar(255) DEFAULT NULL AFTER `notes`");
            }
            if (!$CI->db->field_exists('payment_file', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD `payment_file` varchar(255) DEFAULT NULL AFTER `bill_file`");
            }
            if (!$CI->db->field_exists('packed_by', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD `packed_by` int(11) DEFAULT NULL AFTER `updated_by`");
            }
            if (!$CI->db->field_exists('count_by', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD `count_by` int(11) DEFAULT NULL AFTER `packed_by`");
            }
        }

        $combos = $prefix . 'opsdesk_combos';
        if ($CI->db->table_exists($combos)) {
            if (!$CI->db->field_exists('image', $combos)) {
                $CI->db->query("ALTER TABLE `{$combos}` ADD `image` varchar(255) DEFAULT NULL AFTER `description`");
            }
        }

        update_option('opsdesk_module_version', '1.0.3');
    }
}
