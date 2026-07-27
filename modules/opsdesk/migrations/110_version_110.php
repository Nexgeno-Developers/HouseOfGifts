<?php

defined('BASEPATH') or exit('No direct access allowed');

/**
 * OpsDesk 1.0.10 - Add Transport Medium functionality.
 *
 *  - Create opsdesk_transport_mediums table
 *  - Add transport_medium_id to opsdesk_orders table
 *  - Insert default transport mediums
 */
class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();

        // Create transport_mediums table
        $transportMediumsTable = $prefix . 'opsdesk_transport_mediums';
        if (!$CI->db->table_exists($transportMediumsTable)) {
            $CI->db->query("
                CREATE TABLE `{$transportMediumsTable}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `type_key` VARCHAR(100) NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `description` TEXT NULL,
                    `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `idx_opsdesk_tm_key` (`type_key`),
                    UNIQUE KEY `idx_opsdesk_tm_order` (`display_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Insert default transport mediums
            $now = date('Y-m-d H:i:s');
            $CI->db->insert_batch($transportMediumsTable, [
                ['type_key' => 'road',        'name' => 'Road Transport',       'description' => 'Transport by road/truck',              'display_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'rail',        'name' => 'Rail Transport',       'description' => 'Transport by train/railway',           'display_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'air',         'name' => 'Air Transport',        'description' => 'Transport by airplane/cargo plane',    'display_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'sea',         'name' => 'Sea Transport',        'description' => 'Transport by ship/cargo vessel',       'display_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'multimodal',  'name' => 'Multimodal Transport', 'description' => 'Combination of multiple transport modes','display_order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // Add transport_medium_id to orders table
        $ordersTable = $prefix . 'opsdesk_orders';
        if ($CI->db->table_exists($ordersTable)) {
            if (!$CI->db->field_exists('transport_medium_id', $ordersTable)) {
                $CI->db->query("ALTER TABLE `{$ordersTable}` ADD COLUMN `transport_medium_id` INT UNSIGNED DEFAULT NULL AFTER `packing_type`");
                $CI->db->query("ALTER TABLE `{$ordersTable}` ADD INDEX `idx_opsdesk_orders_transport_medium` (`transport_medium_id`)");
            }
        }

        update_option('opsdesk_module_version', '1.0.10');
    }
}