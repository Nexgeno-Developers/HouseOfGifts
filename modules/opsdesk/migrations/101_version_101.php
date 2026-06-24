<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.1 — Warehouse module integration.
 * Aligns SKU references with tblitems.commodity_code and enables warehouse stock lookups.
 */
class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();

        $CI->load->helper('opsdesk/opsdesk');

        if ($CI->db->table_exists($prefix . 'opsdesk_combo_items') && $CI->db->table_exists($prefix . 'items')) {
            $CI->db->query(
                'UPDATE ' . $prefix . 'opsdesk_combo_items oci
                INNER JOIN ' . $prefix . 'items i ON i.id = oci.product_item_id
                SET oci.sku = TRIM(i.commodity_code)
                WHERE oci.product_item_id IS NOT NULL
                  AND TRIM(i.commodity_code) != ""
                  AND (oci.sku = CONCAT("ITEM-", oci.product_item_id) OR oci.sku = "" OR oci.sku IS NULL)'
            );

            $CI->db->query(
                'UPDATE ' . $prefix . 'opsdesk_combo_items oci
                INNER JOIN ' . $prefix . 'items i ON i.id = oci.product_item_id
                SET oci.sku = TRIM(i.sku_code)
                WHERE oci.product_item_id IS NOT NULL
                  AND TRIM(i.commodity_code) = ""
                  AND TRIM(i.sku_code) != ""
                  AND (oci.sku = CONCAT("ITEM-", oci.product_item_id) OR oci.sku = "" OR oci.sku IS NULL)'
            );
        }

        if ($CI->db->table_exists($prefix . 'opsdesk_inventory') && $CI->db->table_exists($prefix . 'items')) {
            $CI->db->query(
                'UPDATE ' . $prefix . 'opsdesk_inventory oi
                INNER JOIN ' . $prefix . 'items i ON i.id = oi.product_item_id
                SET oi.sku = TRIM(i.commodity_code)
                WHERE oi.product_item_id IS NOT NULL
                  AND TRIM(i.commodity_code) != ""
                  AND (oi.sku = CONCAT("ITEM-", oi.product_item_id) OR oi.sku = "" OR oi.sku IS NULL)'
            );

            $CI->db->query(
                'UPDATE ' . $prefix . 'opsdesk_inventory oi
                INNER JOIN ' . $prefix . 'items i ON i.id = oi.product_item_id
                SET oi.sku = TRIM(i.sku_code)
                WHERE oi.product_item_id IS NOT NULL
                  AND TRIM(i.commodity_code) = ""
                  AND TRIM(i.sku_code) != ""
                  AND (oi.sku = CONCAT("ITEM-", oi.product_item_id) OR oi.sku = "" OR oi.sku IS NULL)'
            );
        }

        update_option('opsdesk_module_version', '1.0.1');
    }
}
