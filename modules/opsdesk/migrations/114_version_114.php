<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.1.4 — Self-heal all order columns introduced after the original install.
 *
 * Production may record a module version past 103–111 while still missing
 * columns (customer_id, priority, etc.). Order create then fails with
 * "Unknown column … in 'field list'".
 *
 * Mirrors the column set expected by the current install.php / later
 * migrations. All statements are idempotent (guarded by field_exists).
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
            // 103 / 105 — customer linkage + bill/payment + staff assignment
            if (!$CI->db->field_exists('customer_id', $orders)) {
                $after = $CI->db->field_exists('combo_name', $orders) ? ' AFTER `combo_name`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `customer_id` int(11) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('customer_city', $orders)) {
                $after = $CI->db->field_exists('customer_id', $orders) ? ' AFTER `customer_id`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `customer_city` varchar(100) DEFAULT NULL{$after}");
            }

            // 106 — priority (0 = Normal, 1 = High)
            if (!$CI->db->field_exists('priority', $orders)) {
                $after = $CI->db->field_exists('notes', $orders) ? ' AFTER `notes`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `priority` tinyint(1) NOT NULL DEFAULT 0{$after}");
                $CI->db->query("ALTER TABLE `{$orders}` ADD KEY `idx_opsdesk_orders_priority` (`priority`)");
            }

            if (!$CI->db->field_exists('bill_file', $orders)) {
                $after = $CI->db->field_exists('priority', $orders)
                    ? ' AFTER `priority`'
                    : ($CI->db->field_exists('notes', $orders) ? ' AFTER `notes`' : '');
                $CI->db->query("ALTER TABLE `{$orders}` ADD `bill_file` varchar(255) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('payment_file', $orders)) {
                $after = $CI->db->field_exists('bill_file', $orders) ? ' AFTER `bill_file`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `payment_file` varchar(255) DEFAULT NULL{$after}");
            }

            // 109 — completion uploads
            if (!$CI->db->field_exists('lr_copy', $orders)) {
                $after = $CI->db->field_exists('payment_file', $orders) ? ' AFTER `payment_file`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `lr_copy` varchar(255) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('carton_photo', $orders)) {
                $after = $CI->db->field_exists('lr_copy', $orders) ? ' AFTER `lr_copy`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `carton_photo` text DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('carton_count', $orders)) {
                $after = $CI->db->field_exists('carton_photo', $orders) ? ' AFTER `carton_photo`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `carton_count` int(11) DEFAULT NULL{$after}");
            }

            if (!$CI->db->field_exists('packed_by', $orders)) {
                $after = $CI->db->field_exists('updated_by', $orders) ? ' AFTER `updated_by`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `packed_by` int(11) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('count_by', $orders)) {
                $after = $CI->db->field_exists('packed_by', $orders) ? ' AFTER `packed_by`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `count_by` int(11) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('cancelled_by', $orders)) {
                $after = $CI->db->field_exists('count_by', $orders) ? ' AFTER `count_by`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `cancelled_by` int(11) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('cancelled_at', $orders)) {
                $after = $CI->db->field_exists('cancelled_by', $orders) ? ' AFTER `cancelled_by`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `cancelled_at` datetime DEFAULT NULL{$after}");
            }

            // 110 — transport medium
            if (!$CI->db->field_exists('transport_medium_id', $orders)) {
                $after = $CI->db->field_exists('packing_type', $orders) ? ' AFTER `packing_type`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `transport_medium_id` int(11) UNSIGNED DEFAULT NULL{$after}");
                $CI->db->query("ALTER TABLE `{$orders}` ADD KEY `idx_opsdesk_orders_transport_medium` (`transport_medium_id`)");
            }

            // 111 — delivery date
            if (!$CI->db->field_exists('delivery_date', $orders)) {
                $after = $CI->db->field_exists('cancelled_at', $orders) ? ' AFTER `cancelled_at`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `delivery_date` date DEFAULT NULL{$after}");
                $CI->db->query("ALTER TABLE `{$orders}` ADD KEY `idx_opsdesk_orders_delivery_date` (`delivery_date`)");
            }

            // 113 — carton_photo may still be VARCHAR on older installs
            if ($CI->db->field_exists('carton_photo', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` MODIFY COLUMN `carton_photo` TEXT DEFAULT NULL");
            }
        }

        if ($CI->db->table_exists($combos) && !$CI->db->field_exists('image', $combos)) {
            $after = $CI->db->field_exists('description', $combos) ? ' AFTER `description`' : '';
            $CI->db->query("ALTER TABLE `{$combos}` ADD `image` varchar(255) DEFAULT NULL{$after}");
        }

        update_option('opsdesk_module_version', '1.1.4');
    }
}
