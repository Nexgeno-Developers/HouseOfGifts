<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.4 — Settings: own Product/Order Statuses.
 * Introduces tblopsdesk_product_statuses (managed from the OpsDesk Settings tab)
 * and relaxes tblopsdesk_orders.status from ENUM to VARCHAR so custom statuses work.
 */
class Migration_Version_104 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $charset = $CI->db->char_set;

        $table = $prefix . 'opsdesk_product_statuses';
        if (!$CI->db->table_exists($table)) {
            $CI->db->query("CREATE TABLE `{$table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `status_key` VARCHAR(100) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_opsdesk_ps_key` (`status_key`),
                UNIQUE KEY `idx_opsdesk_ps_order` (`display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");

            $now = date('Y-m-d H:i:s');
            $defaults = [
                ['status_key' => 'pending',     'name' => 'Pending',     'description' => 'Order is waiting for processing', 'display_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['status_key' => 'in_progress', 'name' => 'In Progress', 'description' => 'Order is being processed', 'display_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['status_key' => 'packed',      'name' => 'Packed',      'description' => 'Order has been packed', 'display_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['status_key' => 'shipped',     'name' => 'Shipped',     'description' => 'Order has been shipped', 'display_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['status_key' => 'completed',   'name' => 'Completed',   'description' => 'Order has been completed', 'display_order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['status_key' => 'cancelled',   'name' => 'Cancelled',   'description' => 'Order was cancelled', 'display_order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ];
            $CI->db->insert_batch($table, $defaults);
        }

        $orders = $prefix . 'opsdesk_orders';
        if ($CI->db->table_exists($orders) && $CI->db->field_exists('status', $orders)) {
            $CI->db->query("ALTER TABLE `{$orders}` MODIFY `status` varchar(50) NOT NULL DEFAULT 'pending'");
        }

        update_option('opsdesk_module_version', '1.0.4');
    }
}
