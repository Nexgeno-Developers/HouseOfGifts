<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo opsdesk_asset_url('assets/css/opsdesk.css'); ?>">
<link rel="stylesheet" href="<?php echo opsdesk_asset_url('assets/css/order_form.css'); ?>">
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
                    <h4 class="tw-my-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                        <i class="fa fa-search text-primary tw-mr-1"></i> <?php echo e($title); ?>
                    </h4>
                    <?php if (opsdesk_can_create_orders()) { ?>
                    <a href="<?php echo admin_url('opsdesk/order'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-plus tw-mr-1"></i> <?php echo _l('opsdesk_new_order'); ?>
                    </a>
                    <?php } ?>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Order Items Selection Panel: Combos & Standalone Products -->
                        <div class="panel panel-default" style="border: 1px solid #dcdfe6; border-radius: 6px; margin-top: 5px; margin-bottom: 25px;">
                            <div class="panel-heading" style="background: #f8fafc; font-weight: 600; font-size: 14px; border-bottom: 1px solid #e2e8f0;">
                                <i class="fa fa-cubes text-primary"></i> <?php echo _l('opsdesk_order_items_selection'); ?>
                            </div>
                            <div class="panel-body" style="background: #fdfdfd;">
                                <div class="row">
                                    <!-- Left Section: Combos -->
                                    <div class="col-md-6" style="border-right: 1px solid #edf2f7;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <h5 class="font-bold tw-my-0" style="color: #0284c7;">
                                                <i class="fa fa-object-group"></i> <?php echo _l('opsdesk_combos_section'); ?>
                                            </h5>
                                        </div>
                                        <p class="text-muted"><small>Select and add one or multiple combo sets with desired quantities to check stock.</small></p>

                                        <div class="row mbot10">
                                            <div class="col-xs-7 col-sm-8" style="padding-right: 5px;">
                                                <select id="opsdesk_quick_combo_id" class="selectpicker" data-width="100%" data-live-search="true" title="<?php echo _l('opsdesk_select_combo'); ?>">
                                                    <option value=""></option>
                                                    <?php foreach ($combos as $combo) { ?>
                                                    <option value="<?php echo (int) $combo['id']; ?>" data-name="<?php echo e($combo['name']); ?>">
                                                        <?php echo e($combo['name']); ?>
                                                    </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-xs-3 col-sm-2" style="padding-left: 2px; padding-right: 5px;">
                                                <input type="number" id="opsdesk_quick_combo_qty" class="form-control" value="1" min="1" step="1" title="<?php echo _l('opsdesk_order_quantity'); ?>">
                                            </div>
                                            <div class="col-xs-2 col-sm-2" style="padding-left: 0;">
                                                <button type="button" id="opsdesk_add_combo_btn" class="btn btn-info btn-block" title="<?php echo _l('opsdesk_add_combo'); ?>">
                                                    <i class="fa fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped" id="opsdesk_selected_combos_table" style="background: #fff; margin-bottom: 0;">
                                                <thead>
                                                    <tr style="background: #f1f5f9;">
                                                        <th><?php echo _l('opsdesk_combo_name'); ?></th>
                                                        <th style="width: 100px;" class="text-center"><?php echo _l('opsdesk_order_quantity'); ?></th>
                                                        <th style="width: 45px;" class="text-center"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="opsdesk_selected_combos_body">
                                                    <tr id="opsdesk_no_combos_row">
                                                        <td colspan="3" class="text-center text-muted" style="padding: 12px;">
                                                            <?php echo _l('opsdesk_no_combos_added'); ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Right Section: Standalone Inventory Products -->
                                    <div class="col-md-6">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <h5 class="font-bold tw-my-0" style="color: #10b981;">
                                                <i class="fa fa-cube"></i> <?php echo _l('opsdesk_products_section'); ?>
                                            </h5>
                                        </div>
                                        <p class="text-muted"><small>Select single products directly from inventory (e.g. Pen P-12).</small></p>

                                        <div class="row mbot10">
                                            <div class="col-xs-7 col-sm-8" style="padding-right: 5px;">
                                                <select id="opsdesk_quick_product_id" class="selectpicker" data-width="100%" data-live-search="true" title="<?php echo _l('opsdesk_select_product'); ?>">
                                                    <option value=""></option>
                                                    <?php foreach ($products as $product) { ?>
                                                    <option value="<?php echo (int) $product['id']; ?>" 
                                                            data-sku="<?php echo e($product['sku']); ?>"
                                                            data-name="<?php echo e($product['label']); ?>"
                                                            data-subtext="<?php echo e($product['subtext'] ?? ''); ?>">
                                                        <?php echo e($product['label']); ?>
                                                    </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-xs-3 col-sm-2" style="padding-left: 2px; padding-right: 5px;">
                                                <input type="number" id="opsdesk_quick_product_qty" class="form-control" value="1" min="1" step="1" title="<?php echo _l('opsdesk_order_quantity'); ?>">
                                            </div>
                                            <div class="col-xs-2 col-sm-2" style="padding-left: 0;">
                                                <button type="button" id="opsdesk_add_product_btn" class="btn btn-success btn-block" title="<?php echo _l('opsdesk_add_product'); ?>">
                                                    <i class="fa fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped" id="opsdesk_selected_products_table" style="background: #fff; margin-bottom: 0;">
                                                <thead>
                                                    <tr style="background: #f1f5f9;">
                                                        <th><?php echo _l('opsdesk_product'); ?></th>
                                                        <th style="width: 100px;" class="text-center"><?php echo _l('opsdesk_order_quantity'); ?></th>
                                                        <th style="width: 45px;" class="text-center"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="opsdesk_selected_products_body">
                                                    <tr id="opsdesk_no_products_row">
                                                        <td colspan="3" class="text-center text-muted" style="padding: 12px;">
                                                            <?php echo _l('opsdesk_no_products_added'); ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Combined Bill of Materials & Stock Check Section -->
                        <div class="mtop25" id="opsdesk_stock_section">
                            <div class="opsdesk-section-toolbar">
                                <div>
                                    <div class="tw-flex tw-items-center tw-gap-2">
                                        <h5 class="tw-my-0 tw-font-bold tw-text-neutral-800">
                                            <i class="fa fa-boxes tw-text-primary tw-mr-1"></i>
                                            <?php echo _l('opsdesk_combo_items'); ?> &amp; Stock Availability
                                        </h5>
                                    </div>
                                    <div id="opsdesk_quick_stats_bar" class="opsdesk-stats-pills tw-mt-2 hide">
                                        <!-- Dynamically populated stats pills -->
                                    </div>
                                </div>
                                <div class="tw-flex tw-items-center tw-gap-2">
                                    <!-- View Mode Switcher -->
                                    <div class="btn-group btn-group-sm" id="opsdesk_view_mode_group" role="group">
                                        <button type="button" class="btn btn-default active" id="opsdesk_btn_view_grouped" title="<?php echo _l('opsdesk_view_grouped'); ?>">
                                            <i class="fa fa-layer-group tw-mr-1"></i> <?php echo _l('opsdesk_view_grouped'); ?>
                                        </button>
                                        <button type="button" class="btn btn-default" id="opsdesk_btn_view_consolidated" title="<?php echo _l('opsdesk_view_consolidated'); ?>">
                                            <i class="fa fa-table tw-mr-1"></i> <?php echo _l('opsdesk_view_consolidated'); ?>
                                        </button>
                                    </div>

                                    <button type="button" id="opsdesk_open_add_item"
                                        class="btn btn-default btn-sm" disabled
                                        title="<?php echo e(_l('opsdesk_add_item')); ?>">
                                        <i class="fa fa-plus"></i> <?php echo _l('opsdesk_add_item'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Dynamic Container for Grouped Panels or Consolidated Table -->
                            <div id="opsdesk_components_wrapper">
                                <div id="opsdesk_order_empty_state" class="opsdesk-empty-state-card">
                                    <i class="fa fa-boxes fa-3x"></i>
                                    <h5><?php echo _l('opsdesk_select_items_to_begin'); ?></h5>
                                    <p class="text-muted">
                                        Add one or more combos from the left or select direct inventory products from the right above to view components, BOM quantities, and real-time inventory availability.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div id="opsdesk_order_loading" class="hide text-center mtop10">
                            <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        </div>

                        <div id="opsdesk_order_alert" class="hide alert mtop15"></div>

                        <div id="opsdesk_order_summary" class="hide alert mtop15"></div>

                        <?php if (opsdesk_bypass_stock_check()) { ?>
                        <div class="alert alert-warning mtop15">
                            <i class="fa fa-exclamation-triangle"></i>
                            <?php echo _l('opsdesk_bypass_stock_check_notice'); ?>
                        </div>
                        <?php } ?>

                        <?php if (opsdesk_can_create_orders()) { ?>
                        <div id="opsdesk_create_order_wrap" class="hide mtop20">
                            <a href="#" id="opsdesk_create_order_btn" class="btn btn-success btn-lg">
                                <i class="fa fa-shopping-cart"></i> <?php echo _l('opsdesk_create_order'); ?>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="opsdesk_substitute_modal" tabindex="-1">
    <div class="modal-dialog ht-dialog-width">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="opsdesk_product_modal_title"><?php echo _l('opsdesk_substitute'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="opsdesk_product_modal_mode" value="substitute">
                <input type="hidden" id="opsdesk_sub_combo_item_id" value="">
                <input type="hidden" id="opsdesk_sub_item_key" value="">
                <input type="hidden" id="opsdesk_sub_original_sku" value="">
                <div class="form-group select-placeholder">
                    <label><?php echo _l('opsdesk_product_select'); ?></label>
                    <select id="opsdesk_sub_product_id" class="selectpicker" data-width="100%" data-live-search="true">
                        <option value=""></option>
                        <?php foreach ($products as $product) { ?>
                        <option value="<?php echo (int) $product['id']; ?>"
                            data-subtext="<?php echo e($product['subtext'] ?? ''); ?>">
                            <?php echo e($product['label']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="button" id="opsdesk_apply_substitute" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    var opsdeskOrderStockUrl = '<?php echo admin_url('opsdesk/ajax_order_stock_check'); ?>';
    var opsdeskProductDetailsUrl = '<?php echo admin_url('opsdesk/ajax_availability'); ?>';
    var opsdeskNewOrderUrl = '<?php echo admin_url('opsdesk/order'); ?>';
    var opsdeskCanCreateOrder = <?php echo opsdesk_can_create_orders() ? 'true' : 'false'; ?>;
    var opsdeskBypassStockCheck = <?php echo opsdesk_bypass_stock_check() ? 'true' : 'false'; ?>;
    var opsdeskOrderLang = {
        sufficient: '<?php echo _l('opsdesk_sufficient'); ?>',
        insufficient: '<?php echo _l('opsdesk_insufficient'); ?>',
        allSufficient: '<?php echo _l('opsdesk_all_components_sufficient'); ?>',
        componentsInsufficient: '<?php echo _l('opsdesk_components_insufficient'); ?>',
        substitution: '<?php echo _l('opsdesk_substitution'); ?>',
        substitute: '<?php echo _l('opsdesk_substitute'); ?>',
        addItem: '<?php echo _l('opsdesk_add_item'); ?>',
        selectCombo: '<?php echo _l('opsdesk_please_select_combo'); ?>',
        selectProduct: '<?php echo _l('opsdesk_please_select_product'); ?>',
        selectItemsToBegin: '<?php echo _l('opsdesk_select_items_to_begin'); ?>',
        error: '<?php echo _l('opsdesk_error_loading'); ?>',
        noCombosAdded: '<?php echo _l('opsdesk_no_combos_added'); ?>',
        noProductsAdded: '<?php echo _l('opsdesk_no_products_added'); ?>',
        viewGrouped: '<?php echo _l('opsdesk_view_grouped'); ?>',
        viewConsolidated: '<?php echo _l('opsdesk_view_consolidated'); ?>',
        readyToFulfill: '<?php echo _l('opsdesk_ready_to_fulfill'); ?>',
        outOfStock: '<?php echo _l('opsdesk_out_of_stock'); ?>',
        perCombo: '<?php echo _l('opsdesk_per_combo'); ?>',
        totalNeeded: '<?php echo _l('opsdesk_total_needed'); ?>',
        usedIn: '<?php echo _l('opsdesk_used_in'); ?>',
        customItems: '<?php echo _l('opsdesk_custom_items'); ?>',
        shortage: '<?php echo _l('opsdesk_shortage'); ?>',
        surplus: '<?php echo _l('opsdesk_surplus'); ?>',
        totalDemand: '<?php echo _l('opsdesk_total_demand'); ?>',
        inStock: '<?php echo _l('opsdesk_in_stock'); ?>',
        combosSection: '<?php echo _l('opsdesk_combos_section'); ?>',
        standaloneProductsIncluded: '<?php echo _l('opsdesk_standalone_products_included'); ?>',
        combosIncluded: '<?php echo _l('opsdesk_combos_included'); ?>',
        sku: '<?php echo _l('opsdesk_sku'); ?>',
        product: '<?php echo _l('opsdesk_product'); ?>',
        availableStock: '<?php echo _l('opsdesk_available_stock'); ?>',
        status: '<?php echo _l('opsdesk_status'); ?>',
        actions: '<?php echo _l('opsdesk_actions'); ?>',
        quantity: '<?php echo _l('opsdesk_order_quantity'); ?>',
        balance: 'Balance'
    };
</script>

<?php init_tail(); ?>
</body>
</html>
