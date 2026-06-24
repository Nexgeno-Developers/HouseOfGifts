<?php

defined('BASEPATH') or exit('No direct script access allowed');

$prefix = db_prefix();

$CI->db->query('SET FOREIGN_KEY_CHECKS = 0');

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
