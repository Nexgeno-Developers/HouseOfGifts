<?php

defined('BASEPATH') or exit('No direct script access allowed');

$prefix = db_prefix();

$CI->db->query('SET FOREIGN_KEY_CHECKS = 0');

if ($CI->db->table_exists($prefix . 'opsdesk_order_status_log')) {
    $CI->db->query('DROP TABLE IF EXISTS `' . $prefix . 'opsdesk_order_status_log`');
}

if ($CI->db->table_exists($prefix . 'opsdesk_order_items')) {
    $CI->db->query('DROP TABLE IF EXISTS `' . $prefix . 'opsdesk_order_items`');
}

if ($CI->db->table_exists($prefix . 'opsdesk_orders')) {
    $CI->db->query('DROP TABLE IF EXISTS `' . $prefix . 'opsdesk_orders`');
}

if ($CI->db->table_exists($prefix . 'opsdesk_combo_items')) {
    $CI->db->query('DROP TABLE IF EXISTS `' . $prefix . 'opsdesk_combo_items`');
}

if ($CI->db->table_exists($prefix . 'opsdesk_inventory')) {
    $CI->db->query('DROP TABLE IF EXISTS `' . $prefix . 'opsdesk_inventory`');
}

if ($CI->db->table_exists($prefix . 'opsdesk_combos')) {
    $CI->db->query('DROP TABLE IF EXISTS `' . $prefix . 'opsdesk_combos`');
}

$CI->db->query('SET FOREIGN_KEY_CHECKS = 1');

delete_option('opsdesk_module_version');
delete_option('opsdesk_bypass_stock_check');
