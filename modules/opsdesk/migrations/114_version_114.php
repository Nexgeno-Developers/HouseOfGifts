<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.1.4 — Self-heal Phase 2 order columns.
 *
 * Production may record module version past 103/105 while still missing
 * customer_id / customer_city (and related 1.0.3 columns). Creating an
 * order then fails with: Unknown column 'customer_id' in 'field list'.
 *
 * All statements are idempotent (guarded by field_exists).
 */
class Migration_Version_114 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $orders = $prefix . 'opsdesk_orders';
        $combos = $prefix . 'opsdesk_combos';

        if ($CI->db->table_exists($orders)) {
            if (!$CI->db->field_exists('customer_id', $orders)) {
                $after = $CI->db->field_exists('combo_name', $orders) ? ' AFTER `combo_name`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `customer_id` int(11) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('customer_city', $orders)) {
                $after = $CI->db->field_exists('customer_id', $orders) ? ' AFTER `customer_id`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `customer_city` varchar(100) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('bill_file', $orders)) {
                $after = $CI->db->field_exists('notes', $orders) ? ' AFTER `notes`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `bill_file` varchar(255) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('payment_file', $orders)) {
                $after = $CI->db->field_exists('bill_file', $orders) ? ' AFTER `bill_file`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `payment_file` varchar(255) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('packed_by', $orders)) {
                $after = $CI->db->field_exists('updated_by', $orders) ? ' AFTER `updated_by`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `packed_by` int(11) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('count_by', $orders)) {
                $after = $CI->db->field_exists('packed_by', $orders) ? ' AFTER `packed_by`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `count_by` int(11) DEFAULT NULL{$after}");
            }
        }

        if ($CI->db->table_exists($combos) && !$CI->db->field_exists('image', $combos)) {
            $after = $CI->db->field_exists('description', $combos) ? ' AFTER `description`' : '';
            $CI->db->query("ALTER TABLE `{$combos}` ADD `image` varchar(255) DEFAULT NULL{$after}");
        }

        update_option('opsdesk_module_version', '1.1.4');
    }
}
