<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                    <?php echo e($title); ?>
                </h4>
                <div class="panel_s">
                    <div class="panel-body">
                        <?php echo form_open(admin_url('opsdesk/save_order'), ['id' => 'opsdesk_order_form']); ?>
                        <input type="hidden" name="order_overrides" id="opsdesk_order_overrides" value="">

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group select-placeholder">
                                    <label for="opsdesk_order_combo_id" class="control-label">
                                        <?php echo _l('opsdesk_select_combo'); ?> <span class="text-danger">*</span>
                                    </label>
                                    <select name="combo_id" id="opsdesk_order_combo_id" class="selectpicker" data-width="100%" required
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                        <option value=""></option>
                                        <?php foreach ($combos as $combo) { ?>
                                        <option value="<?php echo (int) $combo['id']; ?>"
                                            <?php echo !empty($prefill['combo_id']) && (int) $prefill['combo_id'] === (int) $combo['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($combo['name']); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <?php echo render_input('quantity', 'opsdesk_order_quantity', !empty($prefill['quantity']) ? $prefill['quantity'] : '1', 'number', [
                                    'min'  => '1',
                                    'step' => '1',
                                    'id'   => 'opsdesk_order_qty',
                                    'required' => true,
                                ]); ?>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group select-placeholder">
                                    <label for="opsdesk_packing_type" class="control-label">
                                        <?php echo _l('opsdesk_packing_type'); ?> <span class="text-danger">*</span>
                                    </label>
                                    <select name="packing_type" id="opsdesk_packing_type" class="selectpicker" data-width="100%" required>
                                        <option value=""></option>
                                        <?php foreach ($packing_types as $key => $label) { ?>
                                        <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <?php echo render_textarea('notes', 'opsdesk_notes', '', ['rows' => 3, 'id' => 'opsdesk_order_notes']); ?>
                            </div>
                        </div>

                        <div id="opsdesk_order_loading" class="hide text-center mtop10">
                            <i class="fa fa-spinner fa-spin fa-2x"></i>
                        </div>

                        <div id="opsdesk_order_alert" class="hide alert mtop15"></div>

                        <div class="table-responsive mtop20">
                            <table class="table table-striped table-bordered" id="opsdesk_order_components">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('opsdesk_sku'); ?></th>
                                        <th><?php echo _l('opsdesk_product'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_available_stock'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_quantity_needed'); ?></th>
                                        <th class="text-center"><?php echo _l('opsdesk_status'); ?></th>
                                        <th class="text-center"><?php echo _l('opsdesk_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="opsdesk_order_components_body">
                                    <tr id="opsdesk_order_empty_row">
                                        <td colspan="6" class="text-center text-muted">
                                            <?php echo _l('opsdesk_select_combo_to_begin'); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="opsdesk_order_summary" class="hide alert mtop15"></div>

                        <div class="mtop20">
                            <button type="submit" id="opsdesk_submit_order" class="btn btn-primary" disabled>
                                <i class="fa fa-check"></i> <?php echo _l('opsdesk_confirm_order'); ?>
                            </button>
                            <a href="<?php echo admin_url('opsdesk/orders'); ?>" class="btn btn-default">
                                <?php echo _l('cancel'); ?>
                            </a>
                        </div>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="opsdesk_substitute_modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _l('opsdesk_substitute'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="opsdesk_sub_combo_item_id" value="">
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
    var opsdeskOrderPrefill = <?php echo json_encode($prefill); ?>;
    var opsdeskOrderLang = {
        sufficient: '<?php echo _l('opsdesk_sufficient'); ?>',
        insufficient: '<?php echo _l('opsdesk_insufficient'); ?>',
        allSufficient: '<?php echo _l('opsdesk_all_components_sufficient'); ?>',
        componentsInsufficient: '<?php echo _l('opsdesk_components_insufficient'); ?>',
        substitution: '<?php echo _l('opsdesk_substitution'); ?>',
        substitute: '<?php echo _l('opsdesk_substitute'); ?>',
        error: '<?php echo _l('opsdesk_error_loading'); ?>',
    };
</script>

<?php init_tail(); ?>

<script src="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/js/opsdesk_orders.js'); ?>"></script>

</body>
</html>
