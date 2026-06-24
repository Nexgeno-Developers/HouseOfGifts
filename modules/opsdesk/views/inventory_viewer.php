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
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group select-placeholder">
                                    <label for="opsdesk_combo_id" class="control-label">
                                        <?php echo _l('opsdesk_select_combo'); ?>
                                    </label>
                                    <select id="opsdesk_combo_id" class="selectpicker" data-width="100%"
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                        <option value=""></option>
                                        <?php foreach ($combos as $combo) { ?>
                                        <option value="<?php echo (int) $combo['id']; ?>">
                                            <?php echo e($combo['name']); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_input('opsdesk_order_quantity', 'opsdesk_order_quantity', '1', 'number', [
                                    'min'  => '1',
                                    'step' => '1',
                                    'id'   => 'opsdesk_order_quantity',
                                ]); ?>
                            </div>
                            <div class="col-md-2">
                                <label class="control-label visible-xs">&nbsp;</label>
                                <button type="button" id="opsdesk_check_btn" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i> <?php echo _l('opsdesk_check_availability'); ?>
                                </button>
                            </div>
                        </div>

                        <div id="opsdesk_loading" class="hide text-center mtop20">
                            <i class="fa fa-spinner fa-spin fa-2x"></i>
                        </div>

                        <div id="opsdesk_alert" class="hide alert mtop15"></div>

                        <div class="table-responsive mtop20">
                            <table class="table table-striped table-bordered" id="opsdesk_availability_table">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('opsdesk_sku'); ?></th>
                                        <th><?php echo _l('opsdesk_product'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_available_stock'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_quantity_needed'); ?></th>
                                        <th class="text-center"><?php echo _l('opsdesk_status'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="opsdesk_availability_body">
                                    <tr id="opsdesk_empty_row">
                                        <td colspan="5" class="text-center text-muted">
                                            <?php echo _l('opsdesk_select_combo_to_begin'); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="opsdesk_summary" class="hide mtop15">
                            <span class="label" id="opsdesk_summary_label"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    var opsdeskAjaxUrl = '<?php echo admin_url('opsdesk/ajax_availability'); ?>';
    var opsdeskLang = {
        sufficient: '<?php echo _l('opsdesk_sufficient'); ?>',
        insufficient: '<?php echo _l('opsdesk_insufficient'); ?>',
        fulfillable: '<?php echo _l('opsdesk_order_fulfillable'); ?>',
        not_fulfillable: '<?php echo _l('opsdesk_order_not_fulfillable'); ?>',
        error: '<?php echo _l('opsdesk_error_loading'); ?>',
    };
</script>
</body>
</html>
