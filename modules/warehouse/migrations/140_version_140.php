<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_140 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'wh_product_statuses';

        if (!$CI->db->table_exists($table)) {
            $CI->db->query('CREATE TABLE `' . $table . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `status_key` VARCHAR(100) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_wh_product_status_key` (`status_key`),
                UNIQUE KEY `idx_wh_product_status_order` (`display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }

        $existing = $CI->db->get($table)->num_rows();
        if ($existing == 0) {
            $default_rows = [
                ['status_key' => 'pending', 'name' => 'Pending', 'description' => 'Order is waiting for processing', 'display_order' => 1, 'is_active' => 1],
                ['status_key' => 'in_progress', 'name' => 'In Progress', 'description' => 'Order is being processed', 'display_order' => 2, 'is_active' => 1],
                ['status_key' => 'packed', 'name' => 'Packed', 'description' => 'Order has been packed', 'display_order' => 3, 'is_active' => 1],
                ['status_key' => 'shipped', 'name' => 'Shipped', 'description' => 'Order has been shipped', 'display_order' => 4, 'is_active' => 1],
                ['status_key' => 'completed', 'name' => 'Completed', 'description' => 'Order has been completed', 'display_order' => 5, 'is_active' => 1],
                ['status_key' => 'cancelled', 'name' => 'Cancelled', 'description' => 'Order was cancelled', 'display_order' => 6, 'is_active' => 1],
            ];

            foreach ($default_rows as $row) {
                $CI->db->insert($table, array_merge($row, [
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]));
            }
        }
    }
}
