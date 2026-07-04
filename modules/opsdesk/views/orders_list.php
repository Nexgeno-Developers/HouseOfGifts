<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($can_create)) { ?>
                <div class="tw-mb-2">
                    <a href="<?php echo admin_url('opsdesk/order'); ?>" class="btn btn-primary">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php echo _l('opsdesk_new_order'); ?>
                    </a>
                </div>
                <?php } ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <form method="get" action="<?php echo admin_url('opsdesk/orders'); ?>" class="row mbot15">
                            <div class="col-md-3">
                                <select name="status" class="selectpicker" data-width="100%"
                                    onchange="this.form.submit()">
                                    <option value="all" <?php echo ($status_filter === null || $status_filter === '' || $status_filter === 'all') ? 'selected' : ''; ?>>
                                        <?php echo _l('opsdesk_status_all'); ?>
                                    </option>
                                    <?php
                                    $statuses = ['pending', 'in_progress', 'packed', 'shipped', 'completed', 'cancelled'];
                                    foreach ($statuses as $st) { ?>
                                    <option value="<?php echo e($st); ?>" <?php echo $status_filter === $st ? 'selected' : ''; ?>>
                                        <?php echo e(opsdesk_get_order_status_label($st)); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('opsdesk_order_id'); ?></th>
                                        <th><?php echo _l('opsdesk_combo_name'); ?></th>
                                        <th><?php echo _l('opsdesk_order_quantity'); ?></th>
                                        <th><?php echo _l('opsdesk_packing_type'); ?></th>
                                        <th><?php echo _l('opsdesk_status'); ?></th>
                                        <?php if (!empty($global_view)) { ?>
                                        <th><?php echo _l('opsdesk_created_by'); ?></th>
                                        <?php } ?>
                                        <th><?php echo _l('opsdesk_created_at'); ?></th>
                                        <th><?php echo _l('opsdesk_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order) { ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('opsdesk/order/' . $order['id']); ?>">
                                                #<?php echo (int) $order['id']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo e($order['combo_name'] ?: '—'); ?></td>
                                        <td><?php echo (int) $order['quantity']; ?></td>
                                        <td>
                                            <span class="label label-default">
                                                <?php echo e(opsdesk_get_packing_type_label($order['packing_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="label <?php echo opsdesk_get_order_status_class($order['status']); ?>">
                                                <?php echo e(opsdesk_get_order_status_label($order['status'])); ?>
                                            </span>
                                        </td>
                                        <?php if (!empty($global_view)) { ?>
                                        <td><?php echo e($order['creator_name']); ?></td>
                                        <?php } ?>
                                        <td><?php echo e(_dt($order['created_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('opsdesk/order/' . $order['id']); ?>"
                                                class="btn btn-default btn-sm">
                                                <?php echo _l('opsdesk_view_order'); ?>
                                            </a>

                                            <?php
                                            $is_own   = (int) $order['created_by'] === (int) get_staff_user_id();
                                            $can_cancel = false;
                                            if (!empty($can_edit) && in_array($order['status'], ['pending', 'in_progress', 'packed'], true)) {
                                                $can_cancel = true;
                                            } elseif ($is_own && $order['status'] === 'pending') {
                                                $can_cancel = true;
                                            }
                                            if ($can_cancel) { ?>
                                            <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order['id']); ?>"
                                                class="btn btn-danger btn-sm _delete"
                                                data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                                <?php echo _l('opsdesk_cancel_order'); ?>
                                            </a>
                                            <?php } ?>

                                            <?php if (!empty($can_edit) && $order['status'] === 'pending') { ?>
                                            <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'inline-block']); ?>
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="btn btn-info btn-sm">
                                                <?php echo _l('opsdesk_accept_order'); ?>
                                            </button>
                                            <?php echo form_close(); ?>
                                            <?php } ?>

                                            <?php
                                            if (!empty($can_edit)) {
                                                $next = opsdesk_get_next_order_statuses($order['status']);
                                                $progress = array_values(array_filter($next, function ($s) {
                                                    return $s !== 'cancelled';
                                                }));
                                                if (count($progress) > 0) { ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-default btn-sm dropdown-toggle"
                                                    data-toggle="dropdown">
                                                    <?php echo _l('opsdesk_update_status'); ?>
                                                    <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php foreach ($progress as $st) { ?>
                                                    <li>
                                                        <?php echo form_open(admin_url('opsdesk/update_order_status')); ?>
                                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
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
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php if (empty($orders)) { ?>
                                    <tr>
                                        <td colspan="<?php echo !empty($global_view) ? 8 : 7; ?>" class="text-center text-muted">
                                            <?php echo _l('opsdesk_no_orders'); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
