<?php

defined('BASEPATH') or exit('No direct access allowed');

/**
 * OpsDesk 1.0.11 - Add delivery_date to orders.
 *
 *  - Add delivery_date DATE column to opsdesk_orders
 *  - Add index for delivery_date sorting/filtering
 */
class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $orders = $prefix . 'opsdesk_orders';

        if ($CI->db->table_exists($orders)) {
            if (!$CI->db->field_exists('delivery_date', $orders)) {
                $CI->db->query("ALTER TABLE `{$orders}` ADD COLUMN `delivery_date` DATE DEFAULT NULL AFTER `cancelled_at`");
                $CI->db->query("ALTER TABLE `{$orders}` ADD INDEX `idx_opsdesk_orders_delivery_date` (`delivery_date`)");
            }
        }

        update_option('opsdesk_module_version', '1.0.11');
    }
}
