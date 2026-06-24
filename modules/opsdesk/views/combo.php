<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-<?php echo isset($combo) ? '8' : '6 col-md-offset-3'; ?>">
                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                    <?php echo e($title); ?>
                </h4>
                <?php echo form_open($this->uri->uri_string()); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <?php
                        $name_value = isset($combo) ? $combo->name : '';
                        echo render_input('name', 'opsdesk_combo_name', $name_value, 'text', isset($combo) ? [] : ['autofocus' => true]);
                        $desc_value = isset($combo) ? $combo->description : '';
                        echo render_textarea('description', 'opsdesk_description', $desc_value);
                        ?>
                        <div class="form-group">
                            <label for="status" class="control-label"><?php echo _l('opsdesk_status'); ?></label>
                            <select name="status" id="status" class="selectpicker" data-width="100%">
                                <option value="1" <?php echo (isset($combo) && (int) $combo->status === 1) ? 'selected' : ''; ?>>
                                    <?php echo _l('opsdesk_active'); ?>
                                </option>
                                <option value="0" <?php echo (isset($combo) && (int) $combo->status === 0) ? 'selected' : ''; ?>>
                                    <?php echo _l('opsdesk_inactive'); ?>
                                </option>
                            </select>
                        </div>
                        <?php if (staff_can('create', 'opsdesk') || staff_can('edit', 'opsdesk')
                            || has_permission('opsdesk', '', 'create') || has_permission('opsdesk', '', 'edit')) { ?>
                        <button type="submit" class="btn btn-primary">
                            <?php echo _l('submit'); ?>
                        </button>
                        <?php } ?>
                    </div>
                </div>
                <?php echo form_close(); ?>

                <?php if (isset($combo)) { ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-font-semibold"><?php echo _l('opsdesk_combo_items'); ?></h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('opsdesk_sku'); ?></th>
                                        <th><?php echo _l('opsdesk_product'); ?></th>
                                        <th><?php echo _l('opsdesk_qty_per_unit'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($combo_items as $ci) { ?>
                                    <tr>
                                        <td><?php echo e($ci['sku']); ?></td>
                                        <td>
                                            <?php
                                            echo e($ci['product_name'] ?: ($ci['custom_product_ref'] ?: '-'));
                                            ?>
                                        </td>
                                        <td><?php echo e(app_format_number($ci['quantity_per_unit'])); ?></td>
                                        <td>
                                            <?php if (staff_can('delete', 'opsdesk') || has_permission('opsdesk', '', 'delete')) { ?>
                                            <a href="<?php echo admin_url('opsdesk/delete_combo_item/' . $combo->id . '/' . $ci['id']); ?>"
                                                class="btn btn-danger btn-icon _delete">
                                                <i class="fa fa-remove"></i>
                                            </a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php if (empty($combo_items)) { ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            <?php echo _l('opsdesk_no_combo_items'); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (staff_can('create', 'opsdesk') || staff_can('edit', 'opsdesk')
                            || has_permission('opsdesk', '', 'create') || has_permission('opsdesk', '', 'edit')) { ?>
                        <hr>
                        <h5><?php echo _l('opsdesk_add_combo_item'); ?></h5>
                        <?php echo form_open(admin_url('opsdesk/add_combo_item/' . $combo->id)); ?>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group select-placeholder">
                                    <label for="product_item_id" class="control-label">
                                        <?php echo _l('opsdesk_product_select'); ?>
                                    </label>
                                    <select name="product_item_id" id="product_item_id" class="selectpicker"
                                        data-width="100%" data-live-search="true"
                                        data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                        <option value=""></option>
                                        <?php foreach ($products as $product) { ?>
                                        <option value="<?php echo (int) $product['itemid']; ?>"
                                            data-subtext="<?php echo e($product['subtext'] ?? ''); ?>">
                                            <?php echo e($product['label']); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <!-- <div class="col-md-3">
                                <?php // echo render_input('custom_product_ref', 'opsdesk_custom_product_ref', '', 'text'); ?>
                            </div> -->
                            <!-- <div class="col-md-2">
                                <?php /* echo render_input('sku', 'opsdesk_sku', '', 'text', [
                                    'placeholder' => _l('opsdesk_sku_optional'),
                                ]); */ ?>
                            </div> -->
                            <div class="col-md-3">
                                <?php echo render_input('quantity_per_unit', 'opsdesk_qty_per_unit', '1', 'number', [
                                    'min'  => '0.0001',
                                    'step' => 'any',
                                ]); ?>
                            </div>
                            <div class="col-md-1">
                                <label class="control-label visible-xs">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-muted">
                            <small><?php echo _l('opsdesk_combo_item_help'); ?></small>
                        </p>
                        <?php echo form_close(); ?>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
