<?php

defined('BASEPATH') or exit('No direct script access allowed');

$prefix = db_prefix();
$charset = $CI->db->char_set;

if (!$CI->db->table_exists($prefix . 'opsdesk_combos')) {
    $CI->db->query("CREATE TABLE `{$prefix}opsdesk_combos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(191) NOT NULL,
        `description` text DEFAULT NULL,
        `image` varchar(255) DEFAULT NULL,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_opsdesk_combos_status` (`status`),
        KEY `idx_opsdesk_combos_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
}

if (!$CI->db->table_exists($prefix . 'opsdesk_combo_items')) {
    $CI->db->query("CREATE TABLE `{$prefix}opsdesk_combo_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `combo_id` int(11) NOT NULL,
        `product_item_id` int(11) DEFAULT NULL,
        `custom_product_ref` varchar(100) DEFAULT NULL,
        `sku` varchar(100) NOT NULL,
        `quantity_per_unit` decimal(15,4) NOT NULL DEFAULT 1.0000,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_opsdesk_combo_items_combo_id` (`combo_id`),
        KEY `idx_opsdesk_combo_items_product_item_id` (`product_item_id`),
        KEY `idx_opsdesk_combo_items_sku` (`sku`),
        CONSTRAINT `fk_opsdesk_combo_items_combo`
            FOREIGN KEY (`combo_id`) REFERENCES `{$prefix}opsdesk_combos` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_opsdesk_combo_items_product`
            FOREIGN KEY (`product_item_id`) REFERENCES `{$prefix}items` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
}

if (!$CI->db->table_exists($prefix . 'opsdesk_inventory')) {
    $CI->db->query("CREATE TABLE `{$prefix}opsdesk_inventory` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sku` varchar(100) NOT NULL,
        `product_item_id` int(11) DEFAULT NULL,
        `custom_product_ref` varchar(100) DEFAULT NULL,
        `quantity_available` decimal(15,4) NOT NULL DEFAULT 0.0000,
        `quantity_reserved` decimal(15,4) NOT NULL DEFAULT 0.0000,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_opsdesk_inventory_sku` (`sku`),
        KEY `idx_opsdesk_inventory_product_item_id` (`product_item_id`),
        KEY `idx_opsdesk_inventory_custom_product_ref` (`custom_product_ref`),
        CONSTRAINT `fk_opsdesk_inventory_product`
            FOREIGN KEY (`product_item_id`) REFERENCES `{$prefix}items` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
}

if (!$CI->db->table_exists($prefix . 'opsdesk_orders')) {
    $CI->db->query("CREATE TABLE `{$prefix}opsdesk_orders` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `combo_id` int(11) NOT NULL,
        `combo_name` varchar(191) NOT NULL DEFAULT '',
        `customer_id` int(11) DEFAULT NULL,
        `customer_city` varchar(100) DEFAULT NULL,
        `quantity` int(11) NOT NULL DEFAULT 1,
        `packing_type` enum('box','separate','print_then_pack','print_then_ship') NOT NULL,
        `status` varchar(50) NOT NULL DEFAULT 'pending',
        `notes` text DEFAULT NULL,
        `bill_file` varchar(255) DEFAULT NULL,
        `payment_file` varchar(255) DEFAULT NULL,
        `created_by` int(11) NOT NULL,
        `updated_by` int(11) DEFAULT NULL,
        `packed_by` int(11) DEFAULT NULL,
        `count_by` int(11) DEFAULT NULL,
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

if (!$CI->db->table_exists($prefix . 'opsdesk_product_statuses')) {
    $CI->db->query("CREATE TABLE `{$prefix}opsdesk_product_statuses` (
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
    $CI->db->insert_batch($prefix . 'opsdesk_product_statuses', [
        ['status_key' => 'pending',     'name' => 'Pending',     'description' => 'Order is waiting for processing', 'display_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['status_key' => 'in_progress', 'name' => 'In Progress', 'description' => 'Order is being processed', 'display_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['status_key' => 'packed',      'name' => 'Packed',      'description' => 'Order has been packed', 'display_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['status_key' => 'shipped',     'name' => 'Shipped',     'description' => 'Order has been shipped', 'display_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['status_key' => 'completed',   'name' => 'Completed',   'description' => 'Order has been completed', 'display_order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['status_key' => 'cancelled',   'name' => 'Cancelled',   'description' => 'Order was cancelled', 'display_order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);
}

add_option('opsdesk_module_version', '1.0.4');
