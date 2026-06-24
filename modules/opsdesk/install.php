<?php

defined('BASEPATH') or exit('No direct script access allowed');

$prefix = db_prefix();
$charset = $CI->db->char_set;

if (!$CI->db->table_exists($prefix . 'opsdesk_combos')) {
    $CI->db->query("CREATE TABLE `{$prefix}opsdesk_combos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(191) NOT NULL,
        `description` text DEFAULT NULL,
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

add_option('opsdesk_module_version', '1.0.1');
