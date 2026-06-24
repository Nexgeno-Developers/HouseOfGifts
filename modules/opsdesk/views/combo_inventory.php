<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                    <?php echo e($title); ?> — <?php echo e($combo->name); ?>
                </h4>
                <div class="panel_s">
                    <div class="panel-body">
                        <form method="get" class="form-inline mbot15">
                            <div class="form-group">
                                <label for="qty"><?php echo _l('opsdesk_order_quantity'); ?></label>
                                <input type="number" name="qty" id="qty" class="form-control"
                                    value="<?php echo e($order_qty); ?>" min="1" step="1">
                            </div>
                            <button type="submit" class="btn btn-default"><?php echo _l('opsdesk_check'); ?></button>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('opsdesk_sku'); ?></th>
                                        <th><?php echo _l('opsdesk_product'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_available_stock'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_quantity_needed'); ?></th>
                                        <th class="text-center"><?php echo _l('opsdesk_status'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availability['components'] as $row) { ?>
                                    <tr>
                                        <td><?php echo e($row['sku']); ?></td>
                                        <td><?php echo e($row['product_name']); ?></td>
                                        <td class="text-right"><?php echo e(app_format_number($row['available_stock'])); ?></td>
                                        <td class="text-right"><?php echo e(app_format_number($row['required_quantity'])); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['is_sufficient']) { ?>
                                            <span class="label label-success">
                                                <i class="fa fa-check"></i> <?php echo _l('opsdesk_sufficient'); ?>
                                            </span>
                                            <?php } else { ?>
                                            <span class="label label-danger">
                                                <i class="fa fa-times"></i> <?php echo _l('opsdesk_insufficient'); ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php if (empty($availability['components'])) { ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            <?php echo _l('opsdesk_no_combo_items'); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($availability['components'])) { ?>
                        <p class="mtop15">
                            <?php if ($availability['is_fulfillable']) { ?>
                            <span class="label label-success"><?php echo _l('opsdesk_order_fulfillable'); ?></span>
                            <?php } else { ?>
                            <span class="label label-danger"><?php echo _l('opsdesk_order_not_fulfillable'); ?></span>
                            <?php } ?>
                        </p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
