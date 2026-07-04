<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
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
                                </table>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($order->notes)) { ?>
                                <h5><?php echo _l('opsdesk_notes'); ?></h5>
                                <p><?php echo nl2br(e($order->notes)); ?></p>
                                <?php } ?>

                                <div class="mtop15">
                                    <?php if (!empty($can_cancel_own) || !empty($can_cancel_any)) { ?>
                                    <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order->id); ?>"
                                        class="btn btn-danger _delete"
                                        data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                        <?php echo _l('opsdesk_cancel_order'); ?>
                                    </a>
                                    <?php } ?>

                                    <?php if (!empty($can_edit) && $order->status === 'pending') { ?>
                                    <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'inline-block']); ?>
                                    <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="btn btn-info">
                                        <?php echo _l('opsdesk_accept_order'); ?>
                                    </button>
                                    <?php echo form_close(); ?>
                                    <?php } ?>

                                    <?php if (!empty($can_edit) && count($next_statuses) > 0) {
                                        $progress = array_values(array_filter($next_statuses, function ($s) {
                                            return $s !== 'cancelled';
                                        }));
                                        if (count($progress) > 0) { ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
                                            <?php echo _l('opsdesk_update_status'); ?>
                                            <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php foreach ($progress as $st) { ?>
                                            <li>
                                                <?php echo form_open(admin_url('opsdesk/update_order_status')); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">
                                                <input type="hidden" name="status" value="<?php echo e($st); ?>">
                                                <button type="submit" class="btn btn-link btn-block text-left">
                                                    <?php echo e(opsdesk_get_order_status_label($st)); ?>
                                                </button>
                                                <?php echo form_close(); ?>
                                            </li>
                                            <?php } ?>
                                        </ul>
                                    </div>
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
