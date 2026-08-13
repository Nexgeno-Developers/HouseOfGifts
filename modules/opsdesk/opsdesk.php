<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: OpsDesk
Description: Operations desk — combo products, inventory viewer, and work order management.
Author: OpsDesk
Version: 1.1.2
Requires at least: 2.3.*
*/

define('OPSDESK_MODULE_NAME', 'opsdesk');

$CI = &get_instance();

$CI->load->helper(OPSDESK_MODULE_NAME . '/opsdesk');

add_option('opsdesk_bypass_stock_check', '0');

hooks()->add_action('admin_init', 'opsdesk_init_menu_items');
hooks()->add_action('admin_init', 'opsdesk_permissions');
hooks()->add_action('admin_init', 'opsdesk_orders_permissions');

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
        'icon'     => 'fa fa-search menu-icon',
        'href'     => admin_url('opsdesk/inventory'),
        'position' => 1,
    ]);

    if (opsdesk_can_create_orders()) {
        $CI->app_menu->add_sidebar_children_item('opsdesk', [
            'slug'     => 'opsdesk-new-order',
            'name'     => _l('opsdesk_new_order'),
            'icon'     => 'fa fa-plus menu-icon',
            'href'     => admin_url('opsdesk/order'),
            'position' => 2,
        ]);
    }

    if (opsdesk_can_view_orders()) {
        $CI->app_menu->add_sidebar_children_item('opsdesk', [
            'slug'     => 'opsdesk-orders',
            'name'     => _l('opsdesk_orders'),
            'icon'     => 'fa fa-list menu-icon',
            'href'     => admin_url('opsdesk/orders'),
            'position' => 3,
        ]);
    }

    if (staff_can('create', 'opsdesk') || staff_can('edit', 'opsdesk')) {
        $CI->app_menu->add_sidebar_children_item('opsdesk', [
            'slug'     => 'opsdesk-combos',
            'name'     => _l('opsdesk_combos'),
            'icon'     => 'fa fa-object-group menu-icon',
            'href'     => admin_url('opsdesk/combos'),
            'position' => 4,
        ]);
    }

    if (staff_can('view', 'opsdesk') || has_permission('opsdesk', '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_children_item('opsdesk', [
            'slug'     => 'opsdesk-settings',
            'name'     => _l('opsdesk_settings'),
            'icon'     => 'fa fa-gears menu-icon',
            'href'     => admin_url('opsdesk/settings'),
            'position' => 5,
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

/**
 * Register staff capabilities for OpsDesk orders.
 */
function opsdesk_orders_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view_own' => _l('permission_view_own'),
            'view'     => _l('permission_view') . ' (' . _l('permission_global') . ')',
            'create'   => _l('permission_create'),
            'edit'     => _l('permission_edit'),
            'delete'   => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities('opsdesk_orders', $capabilities, _l('opsdesk') . ' - ' . _l('opsdesk_orders'));
}
