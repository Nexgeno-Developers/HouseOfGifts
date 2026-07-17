<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.8 — Schema hardening for existing installs.
 *
 *  - Widens opsdesk_orders.quantity from INT to DECIMAL(15,4) so fractional
 *    combo quantities are preserved (the UI already accepts step="any" and the
 *    controller casts to float; the old INT column silently truncated them).
 *  - Ensures opsdesk_orders.status is VARCHAR(100) to match
 *    opsdesk_product_statuses.status_key (VARCHAR(100)) so custom status keys
 *    are never truncated on insert.
 *  - Ensures the opsdesk_packing_types table exists and that
 *    opsdesk_orders.packing_type is VARCHAR(50) (user-managed packing types).
 *
 * All statements are idempotent (guarded by field_exists / table_exists).
 */
class Migration_Version_108 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $orders = $prefix . 'opsdesk_orders';

        if ($CI->db->table_exists($orders)) {
            if ($CI->db->field_exists('quantity', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` MODIFY `quantity` DECIMAL(15,4) NOT NULL DEFAULT 1.0000");
            }

            if ($CI->db->field_exists('status', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` MODIFY `status` VARCHAR(100) NOT NULL DEFAULT 'pending'");
            }

            if ($CI->db->field_exists('packing_type', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` MODIFY `packing_type` VARCHAR(50) NOT NULL DEFAULT 'box'");
            }
        }

        $pt = $prefix . 'opsdesk_packing_types';
        if (!$CI->db->table_exists($pt)) {
            $CI->db->query("CREATE TABLE `{$pt}` (
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
            $CI->db->insert_batch($pt, [
                ['type_key' => 'box',             'name' => 'Box',             'description' => 'Pack items together in a single box', 'display_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'separate',        'name' => 'Separate',        'description' => 'Ship items as separate packages', 'display_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'print_then_pack', 'name' => 'Print then Pack', 'description' => 'Print labels before packing', 'display_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['type_key' => 'print_then_ship', 'name' => 'Print then Ship', 'description' => 'Print labels and ship directly', 'display_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        update_option('opsdesk_module_version', '1.0.8');
    }
}
