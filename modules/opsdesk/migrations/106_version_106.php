<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.0.6 — Phase 3: Priority Orders.
 *
 * Adds the `priority` column (0 = Normal, 1 = High) and an index to
 * opsdesk_orders. Migration 105 is reserved for the Phase 2 schema hotfix,
 * so Phase 3 uses 106 and bumps the module version to 1.0.6.
 */
class Migration_Version_106 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $table  = $prefix . 'opsdesk_orders';

        if ($CI->db->table_exists($table) && !$CI->db->field_exists('priority', $table)) {
            $CI->dbforge->add_column($table, [
                'priority' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'notes',
                ],
            ]);

            $CI->db->query('ALTER TABLE ' . $table . ' ADD KEY idx_opsdesk_orders_priority (priority)');
        }

        update_option('opsdesk_module_version', '1.0.6');
    }

    public function down()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $table  = $prefix . 'opsdesk_orders';

        if ($CI->db->table_exists($table) && $CI->db->field_exists('priority', $table)) {
            $CI->dbforge->drop_column($table, 'priority');
        }
    }
}
