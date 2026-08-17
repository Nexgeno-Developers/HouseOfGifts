<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.9 — Add completion fields to orders.
 *
 *  - lr_copy (VARCHAR): LR copy file
 *  - carton_photo (VARCHAR): Carton photo file
 *  - carton_count (INT): Number of cartons
 *
 * Also self-heals bill_file / payment_file when earlier migrations
 * (103/105) did not apply on the target install.
 */
class Migration_Version_109 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $orders = $prefix . 'opsdesk_orders';

        if ($CI->db->table_exists($orders)) {
            // Prerequisites from 103/105 — production may lack these even if
            // the recorded module version is already past those migrations.
            if (!$CI->db->field_exists('bill_file', $orders)) {
                $after = $CI->db->field_exists('notes', $orders) ? ' AFTER `notes`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `bill_file` varchar(255) DEFAULT NULL{$after}");
            }
            if (!$CI->db->field_exists('payment_file', $orders)) {
                $after = $CI->db->field_exists('bill_file', $orders) ? ' AFTER `bill_file`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD `payment_file` varchar(255) DEFAULT NULL{$after}");
            }

            if (!$CI->db->field_exists('lr_copy', $orders)) {
                $after = $CI->db->field_exists('payment_file', $orders) ? ' AFTER `payment_file`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD COLUMN `lr_copy` VARCHAR(255) DEFAULT NULL{$after}");
            }

            if (!$CI->db->field_exists('carton_photo', $orders)) {
                $after = $CI->db->field_exists('lr_copy', $orders) ? ' AFTER `lr_copy`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD COLUMN `carton_photo` VARCHAR(255) DEFAULT NULL{$after}");
            }

            if (!$CI->db->field_exists('carton_count', $orders)) {
                $after = $CI->db->field_exists('carton_photo', $orders) ? ' AFTER `carton_photo`' : '';
                $CI->db->query("ALTER TABLE `{$orders}` ADD COLUMN `carton_count` INT(11) DEFAULT NULL{$after}");
            }
        }

        update_option('opsdesk_module_version', '1.0.9');
    }
}
