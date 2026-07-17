<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.7 — Packing Types CRUD.
 *
 * Adds a manageable `opsdesk_packing_types` table and widens
 * opsdesk_orders.packing_type from a fixed ENUM to VARCHAR so that
 * user-managed packing types can be stored without an ALTER per new type.
 * Existing enum values remain valid VARCHAR content, so no data is lost.
 */
class Migration_Version_107 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();

        // 1. Widen the orders.packing_type column to VARCHAR.
        $orders = $prefix . 'opsdesk_orders';
        if ($CI->db->table_exists($orders)
            && $CI->db->field_exists('packing_type', $orders)) {
            $CI->db->query(
                "ALTER TABLE `{$orders}` MODIFY `packing_type` VARCHAR(50) NOT NULL DEFAULT 'box'"
            );
        }

        // 2. Create the packing types table.
        $table = $prefix . 'opsdesk_packing_types';
        if (!$CI->db->table_exists($table)) {
            $CI->db->query("CREATE TABLE `{$table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `type_key` VARCHAR(100) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_opsdesk_pt_key` (`type_key`),
                UNIQUE KEY `idx_opsdesk_pt_order` (`display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$CI->db->char_set};");

            $now = date('Y-m-d H:i:s');
            $CI->db->insert_batch($table, [
                ['type_key' => 'box',              'name' => 'Box',              'description' => 'Pack items together in a single box',            'display_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'separate',         'name' => 'Separate',         'description' => 'Ship items as separate packages',                'display_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'print_then_pack',  'name' => 'Print then Pack',  'description' => 'Print labels before packing',                    'display_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'print_then_ship',  'name' => 'Print then Ship',  'description' => 'Print labels and ship directly',                 'display_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        update_option('opsdesk_module_version', '1.0.7');
    }

    public function down()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();

        $table = $prefix . 'opsdesk_packing_types';
        if ($CI->db->table_exists($table)) {
            $CI->db->query("DROP TABLE `{$table}`");
        }

        $orders = $prefix . 'opsdesk_orders';
        if ($CI->db->table_exists($orders)
            && $CI->db->field_exists('packing_type', $orders)) {
            $CI->db->query(
                "ALTER TABLE `{$orders}` MODIFY `packing_type` ENUM('box','separate','print_then_pack','print_then_ship') NOT NULL DEFAULT 'box'"
            );
        }
    }
}
