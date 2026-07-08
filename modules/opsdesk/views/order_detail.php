<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
                    <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 tw-mb-0">
                        <?php echo e($title); ?>
                        <span class="label <?php echo opsdesk_get_order_status_class($order->status); ?> mleft5">
                            <?php echo e(opsdesk_get_order_status_label($order->status)); ?>
                        </span>
                    </h4>
                    <a href="<?php echo admin_url('opsdesk/orders'); ?>" class="btn btn-default">
                        <?php echo _l('opsdesk_all_orders'); ?>
                    </a>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th><?php echo _l('opsdesk_combo_name'); ?></th>
                                        <td><?php echo e($order->combo_name); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php echo _l('opsdesk_order_quantity'); ?></th>
                                        <td><?php echo (int) $order->quantity; ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php echo _l('opsdesk_packing_type'); ?></th>
                                        <td><?php echo e(opsdesk_get_packing_type_label($order->packing_type)); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php echo _l('opsdesk_created_by'); ?></th>
                                        <td><?php echo e($order->creator_name); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php echo _l('opsdesk_created_at'); ?></th>
                                        <td><?php echo e(_dt($order->created_at)); ?></td>
                                    </tr>
                                    <?php if (!empty($order->customer_id)) {
                                        $ops_customer = get_client($order->customer_id);
                                    ?>
                                    <tr>
                                        <th><?php echo _l('opsdesk_customer'); ?></th>
                                        <td><?php echo e($ops_customer ? $ops_customer->company : _l('opsdesk_unknown_product')); ?></td>
                                    </tr>
                                    <?php if (!empty($order->customer_city)) { ?>
                                    <tr>
                                        <th><?php echo _l('opsdesk_customer_city'); ?></th>
                                        <td><?php echo e($order->customer_city); ?></td>
                                    </tr>
                                    <?php } ?>
                                    <?php } ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($order->notes)) { ?>
                                <h5><?php echo _l('opsdesk_notes'); ?></h5>
                                <p><?php echo nl2br(e($order->notes)); ?></p>
                                <?php } ?>

                                <div class="opsdesk-attachments mtop15">
                                    <?php if (!empty($order->bill_file)) { ?>
                                    <div class="mbot10">
                                        <span class="label label-primary"><?php echo _l('opsdesk_bill_upload'); ?></span>
                                        <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order->bill_file)); ?>" target="_blank">
                                            <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                        </a>
                                    </div>
                                    <?php } ?>
                                    <?php if (!empty($order->payment_file)) { ?>
                                    <div class="mbot10">
                                        <span class="label label-success"><?php echo _l('opsdesk_payment_upload'); ?></span>
                                        <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order->payment_file)); ?>" target="_blank">
                                            <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                        </a>
                                    </div>
                                    <?php } ?>
                                </div>

                                <div class="opsdesk-assignment-info mtop15">
                                    <h5><?php echo _l('opsdesk_assignment'); ?></h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <th><?php echo _l('opsdesk_packed_by'); ?></th>
                                            <td><?php echo $packed_by_name ? e($packed_by_name) : '—'; ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo _l('opsdesk_count_by'); ?></th>
                                            <td><?php echo $count_by_name ? e($count_by_name) : '—'; ?></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="opsdesk-stack-actions mtop15">
                                    <?php if (!empty($can_cancel_own) || !empty($can_cancel_any)) { ?>
                                    <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order->id); ?>"
                                        class="btn btn-danger _delete"
                                        data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                        <?php echo _l('opsdesk_cancel_order'); ?>
                                    </a>
                                    <?php } ?>

                                    <?php if (!empty($can_edit) && !empty($next_statuses)) {
                                        foreach ($next_statuses as $st) { ?>
                                    <?php echo form_open(admin_url('opsdesk/update_order_status')); ?>
                                    <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">
                                    <input type="hidden" name="status" value="<?php echo e($st); ?>">
                                    <?php if ($st === 'in_progress') { ?>
                                    <div class="form-group">
                                        <label class="control-label"><?php echo _l('opsdesk_packed_by'); ?> <span class="text-danger">*</span></label>
                                        <select name="packed_by" class="selectpicker" data-width="100%" required>
                                            <option value=""></option>
                                            <?php foreach ($staff_members as $sm) { ?>
                                            <option value="<?php echo (int) $sm['staffid']; ?>"
                                                <?php echo (!empty($order->packed_by) && (int) $order->packed_by === (int) $sm['staffid']) ? 'selected' : ''; ?>>
                                                <?php echo e($sm['full_name']); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <?php } elseif ($st === 'completed') { ?>
                                    <div class="form-group">
                                        <label class="control-label"><?php echo _l('opsdesk_count_by'); ?> <span class="text-danger">*</span></label>
                                        <select name="count_by" class="selectpicker" data-width="100%" required>
                                            <option value=""></option>
                                            <?php foreach ($staff_members as $sm) { ?>
                                            <option value="<?php echo (int) $sm['staffid']; ?>"
                                                <?php echo (!empty($order->count_by) && (int) $order->count_by === (int) $sm['staffid']) ? 'selected' : ''; ?>>
                                                <?php echo e($sm['full_name']); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <?php } ?>
                                    <button type="submit" class="btn btn-<?php echo $st === 'completed' ? 'success' : ($st === 'cancelled' ? 'danger' : 'info'); ?>">
                                        <?php echo e(opsdesk_get_order_status_label($st)); ?>
                                    </button>
                                    <?php echo form_close(); ?>
                                    <?php }
                                    } ?>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h5><?php echo _l('opsdesk_combo_items'); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('opsdesk_sku'); ?></th>
                                        <th><?php echo _l('opsdesk_product'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_qty_per_unit'); ?></th>
                                        <th class="text-right"><?php echo _l('opsdesk_total_reserved'); ?></th>
                                        <th><?php echo _l('opsdesk_status'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order->items as $item) { ?>
                                    <tr>
                                        <td><?php echo e($item['sku']); ?></td>
                                        <td>
                                            <?php echo e($item['product_name']); ?>
                                            <?php if ((int) $item['is_substitution'] === 1) { ?>
                                            <span class="label label-warning mleft5"><?php echo _l('opsdesk_substitution'); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-right"><?php echo app_format_number($item['quantity_per_unit']); ?></td>
                                        <td class="text-right"><?php echo app_format_number($item['quantity_reserved']); ?></td>
                                        <td>—</td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <hr>

                        <h5><?php echo _l('opsdesk_status_history'); ?></h5>
                        <ul class="list-unstyled">
                            <?php foreach ($order->status_log as $log) { ?>
                            <li class="mbot10">
                                <i class="fa fa-clock-o"></i>
                                <strong><?php echo e(_dt($log['created_at'])); ?></strong>
                                — <?php echo e($log['staff_name']); ?>:
                                <?php if ($log['from_status']) { ?>
                                <?php echo e(opsdesk_get_order_status_label($log['from_status'])); ?>
                                →
                                <?php } ?>
                                <?php echo e(opsdesk_get_order_status_label($log['to_status'])); ?>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
