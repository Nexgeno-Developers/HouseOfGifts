<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.2 — Phase 2: Order creation and stock reservation.
 */
class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $charset = $CI->db->char_set;

        if (!$CI->db->table_exists($prefix . 'opsdesk_orders')) {
            $CI->db->query("CREATE TABLE `{$prefix}opsdesk_orders` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `combo_id` int(11) NOT NULL,
                `combo_name` varchar(191) NOT NULL DEFAULT '',
                `quantity` int(11) NOT NULL DEFAULT 1,
                `packing_type` enum('box','separate','print_then_pack','print_then_ship') NOT NULL,
                `status` enum('pending','in_progress','packed','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
                `notes` text DEFAULT NULL,
                `created_by` int(11) NOT NULL,
                `updated_by` int(11) DEFAULT NULL,
                `cancelled_by` int(11) DEFAULT NULL,
                `cancelled_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_opsdesk_orders_combo_id` (`combo_id`),
                KEY `idx_opsdesk_orders_status` (`status`),
                KEY `idx_opsdesk_orders_created_by` (`created_by`),
                KEY `idx_opsdesk_orders_created_at` (`created_at`),
                CONSTRAINT `fk_opsdesk_orders_combo`
                    FOREIGN KEY (`combo_id`) REFERENCES `{$prefix}opsdesk_combos` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        if (!$CI->db->table_exists($prefix . 'opsdesk_order_items')) {
            $CI->db->query("CREATE TABLE `{$prefix}opsdesk_order_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `product_item_id` int(11) DEFAULT NULL,
                `sku` varchar(100) NOT NULL,
                `product_name` varchar(255) NOT NULL,
                `quantity_per_unit` decimal(15,4) NOT NULL DEFAULT 1.0000,
                `quantity_reserved` decimal(15,4) NOT NULL DEFAULT 0.0000,
                `is_substitution` tinyint(1) NOT NULL DEFAULT 0,
                `original_item_id` int(11) DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_opsdesk_order_items_order_id` (`order_id`),
                KEY `idx_opsdesk_order_items_product_item_id` (`product_item_id`),
                CONSTRAINT `fk_opsdesk_order_items_order`
                    FOREIGN KEY (`order_id`) REFERENCES `{$prefix}opsdesk_orders` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        if (!$CI->db->table_exists($prefix . 'opsdesk_order_status_log')) {
            $CI->db->query("CREATE TABLE `{$prefix}opsdesk_order_status_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `from_status` varchar(50) DEFAULT NULL,
                `to_status` varchar(50) NOT NULL,
                `changed_by` int(11) NOT NULL,
                `notes` text DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_opsdesk_order_status_log_order_id` (`order_id`),
                CONSTRAINT `fk_opsdesk_order_status_log_order`
                    FOREIGN KEY (`order_id`) REFERENCES `{$prefix}opsdesk_orders` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        update_option('opsdesk_module_version', '1.0.2');
    }
}
