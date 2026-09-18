<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.1.5 — Multi-Combo & Standalone Product Selection per Order.
 *
 * - Creates tblopsdesk_order_combos to support multiple combos per order.
 * - Relaxes tblopsdesk_orders.combo_id to NULL to support multi-combo or standalone-only orders.
 * - Drops strict fk_opsdesk_orders_combo on tblopsdesk_orders.
 * - Adds order_combo_id, source_type, and source_name to tblopsdesk_order_items.
 */
class Migration_Version_115 extends App_module_migration
{
    public function up()
    {
        $CI      = &get_instance();
        $prefix  = db_prefix();
        $charset = $CI->db->char_set;
        $orders  = $prefix . 'opsdesk_orders';
        $combos  = $prefix . 'opsdesk_combos';
        $items   = $prefix . 'opsdesk_order_items';
        $order_combos = $prefix . 'opsdesk_order_combos';

        // 1. Create tblopsdesk_order_combos if not exists
        if (!$CI->db->table_exists($order_combos)) {
            $CI->db->query("CREATE TABLE `{$order_combos}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `combo_id` int(11) NOT NULL,
                `combo_name` varchar(191) NOT NULL DEFAULT '',
                `quantity` decimal(15,4) NOT NULL DEFAULT 1.0000,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_opsdesk_order_combos_order_id` (`order_id`),
                KEY `idx_opsdesk_order_combos_combo_id` (`combo_id`),
                CONSTRAINT `fk_opsdesk_order_combos_order`
                    FOREIGN KEY (`order_id`) REFERENCES `{$orders}` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_opsdesk_order_combos_combo`
                    FOREIGN KEY (`combo_id`) REFERENCES `{$combos}` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        // 2. Relax tblopsdesk_orders.combo_id and drop foreign key constraint
        if ($CI->db->table_exists($orders)) {
            // Check if foreign key exists and drop it
            $fk_check = $CI->db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$orders}' 
                AND CONSTRAINT_NAME = 'fk_opsdesk_orders_combo'")->row_array();

            if (!empty($fk_check)) {
                $CI->db->query("ALTER TABLE `{$orders}` DROP FOREIGN KEY `fk_opsdesk_orders_combo`");
            }

            // Make combo_id nullable
            $CI->db->query("ALTER TABLE `{$orders}` MODIFY COLUMN `combo_id` int(11) NULL DEFAULT NULL");
        }

        // 3. Add source tracking columns to tblopsdesk_order_items
        if ($CI->db->table_exists($items)) {
            if (!$CI->db->field_exists('order_combo_id', $items)) {
                $after = $CI->db->field_exists('order_id', $items) ? ' AFTER `order_id`' : '';
                $CI->db->query("ALTER TABLE `{$items}` ADD COLUMN `order_combo_id` int(11) DEFAULT NULL{$after}");
                $CI->db->query("ALTER TABLE `{$items}` ADD KEY `idx_opsdesk_order_items_order_combo_id` (`order_combo_id`)");
            }

            if (!$CI->db->field_exists('source_type', $items)) {
                $after = $CI->db->field_exists('original_item_id', $items) ? ' AFTER `original_item_id`' : '';
                $CI->db->query("ALTER TABLE `{$items}` ADD COLUMN `source_type` varchar(50) NOT NULL DEFAULT 'combo'{$after}");
                $CI->db->query("ALTER TABLE `{$items}` ADD KEY `idx_opsdesk_order_items_source_type` (`source_type`)");
            }

            if (!$CI->db->field_exists('source_name', $items)) {
                $after = $CI->db->field_exists('source_type', $items) ? ' AFTER `source_type`' : '';
                $CI->db->query("ALTER TABLE `{$items}` ADD COLUMN `source_name` varchar(191) DEFAULT NULL{$after}");
            }
        }

        update_option('opsdesk_module_version', '1.1.5');
    }
}
