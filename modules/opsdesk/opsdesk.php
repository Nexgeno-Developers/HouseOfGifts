<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: OpsDesk
Description: Operations desk — combo product definitions and real-time inventory availability viewer.
Author: OpsDesk
Version: 1.0.1
Requires at least: 2.3.*
*/

define('OPSDESK_MODULE_NAME', 'opsdesk');

$CI = &get_instance();

$CI->load->helper(OPSDESK_MODULE_NAME . '/opsdesk');

hooks()->add_action('admin_init', 'opsdesk_init_menu_items');
hooks()->add_action('admin_init', 'opsdesk_permissions');

register_activation_hook(OPSDESK_MODULE_NAME, 'opsdesk_activation_hook');
register_uninstall_hook(OPSDESK_MODULE_NAME, 'opsdesk_uninstall_hook');
register_language_files(OPSDESK_MODULE_NAME, [OPSDESK_MODULE_NAME]);

/**
 * Run installation SQL on module activation.
 */
function opsdesk_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
}

/**
 * Run rollback SQL on module uninstall.
 */
function opsdesk_uninstall_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/uninstall.php';
}

/**
 * Register sidebar menu items.
 */
function opsdesk_init_menu_items()
{
    $CI = &get_instance();

    if (!staff_can('view', 'opsdesk')) {
        return;
    }

    $CI->app_menu->add_sidebar_menu_item('opsdesk', [
        'collapse' => true,
        'icon'     => 'fa fa-cubes',
        'name'     => _l('opsdesk'),
        'position' => 40,
    ]);

    $CI->app_menu->add_sidebar_children_item('opsdesk', [
        'slug'     => 'opsdesk-inventory-viewer',
        'name'     => _l('opsdesk_inventory_viewer'),
        'href'     => admin_url('opsdesk/inventory'),
        'position' => 1,
    ]);

    if (staff_can('create', 'opsdesk') || staff_can('edit', 'opsdesk')) {
        $CI->app_menu->add_sidebar_children_item('opsdesk', [
            'slug'     => 'opsdesk-combos',
            'name'     => _l('opsdesk_combos'),
            'href'     => admin_url('opsdesk/combos'),
            'position' => 2,
        ]);
    }
}

/**
 * Register staff capabilities for OpsDesk.
 */
function opsdesk_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view'   => _l('permission_view') . ' (' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities('opsdesk', $capabilities, _l('opsdesk'));
}
