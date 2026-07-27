<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.9 — Add completion fields to orders.
 *
 *  - lr_copy (VARCHAR): LR copy file
 *  - carton_photo (VARCHAR): Carton photo file
 *  - carton_count (INT): Number of cartons
 */
class Migration_Version_109 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $orders = $prefix . 'opsdesk_orders';

        if ($CI->db->table_exists($orders)) {
            if (!$CI->db->field_exists('lr_copy', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD COLUMN `lr_copy` VARCHAR(255) DEFAULT NULL AFTER `payment_file`");
            }

            if (!$CI->db->field_exists('carton_photo', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD COLUMN `carton_photo` VARCHAR(255) DEFAULT NULL AFTER `lr_copy`");
            }

            if (!$CI->db->field_exists('carton_count', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD COLUMN `carton_count` INT(11) DEFAULT NULL AFTER `carton_photo`");
            }
        }

        update_option('opsdesk_module_version', '1.0.9');
    }
}