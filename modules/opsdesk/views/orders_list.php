<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
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
                        <div class="leads-overview tw-mb-6">
                            <div class="tw-grid tw-gap-2 sm:tw-grid-flow-col sm:tw-auto-cols-max tw-overflow-x-auto">
                                <?php
                                $ops_status_colors = [
                                    'pending'     => 'rgb(2, 89, 209)',
                                    'in_progress' => 'rgb(34, 91, 216)',
                                    'packed'      => 'rgb(245, 124, 0)',
                                    'shipped'     => 'rgb(175, 180, 43)',
                                    'completed'   => 'rgb(46, 125, 50)',
                                    'cancelled'   => 'rgb(231, 81, 90)',
                                ];
                                $ops_total_count = array_sum($status_counts ?? []);
                                $ops_all_active   = ($status_filter === null || $status_filter === '' || $status_filter === 'all');
                                ?>
                                <a href="<?php echo admin_url('opsdesk/orders'); ?>"
                                    class="tw-bg-transparent tw-border tw-border-solid tw-border-neutral-300 tw-shadow-sm tw-py-1 tw-px-2 tw-rounded-lg tw-text-sm hover:tw-bg-neutral-200/60 tw-text-neutral-600 hover:tw-text-neutral-600 focus:tw-text-neutral-600 text-left <?php echo $ops_all_active ? 'tw-bg-neutral-200/60' : ''; ?>">
                                    <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1"><?php echo (int) $ops_total_count; ?></span>
                                    <span class="tw-font-medium"><?php echo _l('opsdesk_status_all'); ?></span>
                                </a>
                                <?php foreach ($status_filters as $sf) {
                                    $ops_cnt   = isset($status_counts[$sf['key']]) ? (int) $status_counts[$sf['key']] : 0;
                                    $ops_color = $ops_status_colors[$sf['key']] ?? 'rgb(65, 72, 76)';
                                    $ops_active = ($status_filter === $sf['key']);
                                ?>
                                <a href="<?php echo admin_url('opsdesk/orders?status=' . $sf['key']); ?>"
                                    class="tw-bg-transparent tw-border tw-border-solid tw-border-neutral-300 tw-shadow-sm tw-py-1 tw-px-2 tw-rounded-lg tw-text-sm hover:tw-bg-neutral-200/60 tw-text-neutral-600 hover:tw-text-neutral-600 focus:tw-text-neutral-600 text-left <?php echo $ops_active ? 'tw-bg-neutral-200/60' : ''; ?>">
                                    <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1"><?php echo $ops_cnt; ?></span>
                                    <span class="tw-font-medium" style="color: <?php echo $ops_color; ?>"><?php echo e($sf['label']); ?></span>
                                </a>
                                <?php } ?>
                            </div>
                        </div>

                        <form method="get" action="<?php echo admin_url('opsdesk/orders'); ?>" class="row mbot15">
                            <div class="col-md-3">
                                <select name="status" class="selectpicker" data-width="100%"
                                    onchange="this.form.submit()">
                                    <option value="all" <?php echo ($status_filter === null || $status_filter === '' || $status_filter === 'all') ? 'selected' : ''; ?>>
                                        <?php echo _l('opsdesk_status_all'); ?>
                                    </option>
                                    <?php
                                    $statuses = opsdesk_get_order_status_option_keys(true);
                                    foreach ($statuses as $st) { ?>
                                    <option value="<?php echo e($st); ?>" <?php echo $status_filter === $st ? 'selected' : ''; ?>>
                                        <?php echo e(opsdesk_get_order_status_label($st)); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </form>

                        <div class="table-responsive">
                        <table class="table table-striped opsdesk-orders-table">
                            <thead>
                                <tr>
                                    <th><?php echo _l('opsdesk_order_id'); ?></th>
                                    <th><?php echo _l('opsdesk_combo_name'); ?></th>
                                    <th><?php echo _l('opsdesk_customer'); ?></th>
                                    <th><?php echo _l('opsdesk_order_quantity'); ?></th>
                                    <th><?php echo _l('opsdesk_packing_type'); ?></th>
                                    <th><?php echo _l('opsdesk_status'); ?></th>
                                    <th><?php echo _l('opsdesk_packed_by'); ?></th>
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
                                        <td>
                                            <?php
                                            $ops_customer = !empty($order['customer_id']) ? get_client($order['customer_id']) : null;
                                            echo e($ops_customer ? $ops_customer->company : '—');
                                            ?>
                                        </td>
                                        <td><?php echo (int) $order['quantity']; ?></td>
                                        <td>
                                            <span class="label label-default">
                                                <?php echo e(opsdesk_get_packing_type_label($order['packing_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="label <?php echo opsdesk_get_order_status_class($order['status'] ?? ''); ?>">
                                                <?php echo e(opsdesk_get_order_status_label($order['status'] ?? '') ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo e(opsdesk_get_assigned_name($order['packed_by'] ?? null) ?: '—'); ?></td>
                                        <?php if (!empty($global_view)) { ?>
                                        <td><?php echo e($order['creator_name']); ?></td>
                                        <?php } ?>
                                        <td><?php echo e(_dt($order['created_at'])); ?></td>
                                        <td>
                                            <div class="opsdesk-order-actions">
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
                                            <?php $accept_status = opsdesk_get_default_next_status($order['status']); ?>
                                            <?php if ($accept_status !== '') { ?>
                                            <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'inline-block opsdesk-accept-form']); ?>
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo e($accept_status); ?>">
                                            <select name="packed_by" class="selectpicker" data-width="100%" required>
                                                <option value=""><?php echo _l('opsdesk_packed_by'); ?></option>
                                                <?php foreach ($staff_members as $sm) { ?>
                                                <option value="<?php echo (int) $sm['staffid']; ?>"><?php echo e($sm['full_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                            <button type="submit" class="btn btn-info btn-sm">
                                                <?php echo _l('opsdesk_accept_order'); ?>
                                            </button>
                                            <?php echo form_close(); ?>
                                            <?php } ?>
                                            <?php } ?>

                                            <?php
                                            if (!empty($can_edit)) {
                                                $status_options = opsdesk_get_order_statuses(true);
                                                if (!empty($status_options)) { ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-default btn-sm dropdown-toggle"
                                                    data-toggle="dropdown">
                                                    <?php echo _l('opsdesk_update_status'); ?>
                                                    <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php foreach ($status_options as $status) {
                                                        $st = $status['status_key']; ?>
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
                                            </div>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php if (empty($orders)) { ?>
                                    <tr>
                                        <td colspan="<?php echo !empty($global_view) ? 10 : 9; ?>" class="text-center text-muted">
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
