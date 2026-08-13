<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/orders_list.css'); ?>">
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($can_create)) { ?>
                <div class="tw-mb-2">
                    <a href="<?php echo admin_url('opsdesk/order'); ?>" class="btn btn-primary button-margin-r-b">
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
                            <div class="col-md-3">
                                <select name="priority" class="selectpicker" data-width="100%"
                                    onchange="this.form.submit()">
                                    <option value="all" <?php echo ($priority_filter === null || $priority_filter === '' || $priority_filter === 'all') ? 'selected' : ''; ?>>
                                        <?php echo _l('opsdesk_filter_priority_all'); ?>
                                    </option>
                                    <option value="1" <?php echo (string) $priority_filter === '1' ? 'selected' : ''; ?>>
                                        <?php echo _l('opsdesk_priority_high'); ?>
                                    </option>
                                    <option value="0" <?php echo (string) $priority_filter === '0' ? 'selected' : ''; ?>>
                                        <?php echo _l('opsdesk_priority_normal'); ?>
                                    </option>
                                </select>
                            </div>
                        </form>

                        <div class="panel-table-full">
                        <table class="table table-striped opsdesk-orders-table"
                            data-order-col="0"
                            data-order-type="desc">
                            <thead>
                                <tr>
                                    <th><?php echo _l('opsdesk_order_id'); ?></th>
                                    <th><?php echo _l('opsdesk_priority'); ?></th>
                                    <th><?php echo _l('opsdesk_delivery_date'); ?></th>
                                    <th><?php echo _l('opsdesk_combo_name'); ?></th>
                                    <th><?php echo _l('opsdesk_customer'); ?></th>
                                    <th><?php echo _l('opsdesk_order_quantity'); ?></th>
                                    <th><?php echo _l('opsdesk_packing_type'); ?></th>
                                    <th><?php echo _l('opsdesk_transport_medium'); ?></th>
                                    <th><?php echo _l('opsdesk_status'); ?></th>
                                    <th><?php echo _l('opsdesk_packed_by'); ?></th>
                                    <?php if (!empty($global_view)) { ?>
                                    <th><?php echo _l('opsdesk_created_by'); ?></th>
                                    <?php } ?>
                                    <th><?php echo _l('opsdesk_created_at'); ?></th>
                                    <th class="options"><?php echo _l('opsdesk_actions'); ?></th>
                                </tr>
                            </thead>
                                <tbody>
                                    <?php foreach ($orders as $order) { ?>
                                    <tr>
                                         <td data-order="<?php echo (int) $order['id']; ?>">
                                            <a href="<?php echo admin_url('opsdesk/order/' . $order['id']); ?>">
                                                #<?php echo (int) $order['id']; ?>
                                            </a>
                                        </td>
                                        <td data-order="<?php echo (int) $order['priority']; ?>"><?php echo opsdesk_get_priority_badge($order['priority']); ?></td>
                                        <td data-order="<?php echo e($order['delivery_date'] ?? ''); ?>">
                                            <?php if (!empty($order['delivery_date'])) { ?>
                                                <?php
                                                $is_high = (int) $order['priority'] === 1;
                                                $date_style = $is_high ? 'color:#e7515e;font-weight:600;' : 'color:#0259d1;';
                                                echo '<span style="' . $date_style . '">' . e(_d($order['delivery_date'])) . '</span>';
                                                ?>
                                            <?php } else { ?>
                                                <span class="text-muted"><?php echo _l('opsdesk_no_delivery_date'); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo e($order['combo_name'] ?: '—'); ?></td>
                                        <td><?php echo e(!empty($order['customer_name']) ? $order['customer_name'] : '—'); ?></td>
                                        <td data-order="<?php echo (int) $order['quantity']; ?>"><?php echo (int) $order['quantity']; ?></td>
                                        <td>
                                            <span class="label label-tag tag-id-1">
                                                <?php echo e(opsdesk_get_packing_type_label($order['packing_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $transport_medium = !empty($order['transport_medium_id']) ? opsdesk_get_transport_medium_label($order['transport_medium_id']) : null;
                                            echo e($transport_medium ?: '—');
                                            ?>
                                        </td>
                                        <td>
                                            <span class="label <?php echo opsdesk_get_order_status_class($order['status'] ?? ''); ?>">
                                                <?php echo e(opsdesk_get_order_status_label($order['status'] ?? '') ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo e(opsdesk_get_assigned_name($order['packed_by'] ?? null) ?: '—'); ?></td>
                                        <?php if (!empty($global_view)) { ?>
                                         <td><?php echo e(!empty($order['creator_name']) ? $order['creator_name'] : '—'); ?></td>
                                        <?php } ?>
                                         <td data-order="<?php echo e($order['created_at'] ?? ''); ?>"><?php echo e(!empty($order['created_at']) ? _dt($order['created_at']) : '—'); ?></td>
                                        <td>
                                            <div class="row-options">
                                                <a href="<?php echo admin_url('opsdesk/order/' . $order['id']); ?>">
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
                                                | <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order['id']); ?>"
                                                    class="_delete"
                                                    data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                                    <?php echo _l('opsdesk_cancel_order'); ?>
                                                </a>
                                                <?php } ?>

                                                <?php if (!empty($can_edit) && $order['status'] === 'pending') { ?>
                                                <?php $accept_status = opsdesk_get_default_next_status($order['status']); ?>
                                                <?php if ($accept_status !== '') { ?>
                                                | <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'inline-block opsdesk-accept-form']); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo e($accept_status); ?>">
                                                <select name="packed_by" class="form-control input-sm opsdesk-accept-packer" style="display:inline-block;width:auto;max-width:180px;vertical-align:middle;" required>
                                                    <option value=""><?php echo _l('opsdesk_assigned_to'); ?></option>
                                                    <?php foreach ($staff_members as $sm) { ?>
                                                    <option value="<?php echo (int) $sm['staffid']; ?>"><?php echo e($sm['full_name']); ?></option>
                                                    <?php } ?>
                                                </select>
                                                <button type="submit" class="btn btn-info btn-sm opsdesk-accept-btn button-margin-r-b" disabled>
                                                    <?php echo _l('opsdesk_accept_order'); ?>
                                                </button>
                                                <?php echo form_close(); ?>
                                                <?php } ?>
                                                <?php } ?>

                                                 <?php
                                                 if (!empty($can_edit)) {
                                                     $status_options = opsdesk_get_order_statuses(true);
                                                     if (!empty($status_options)) {
                                                 ?>
                                                 | <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'inline-block']); ?>
                                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                    <select name="status" class="selectpicker" data-width="auto" onchange="if (this.value) this.form.submit();">
                                                        <option value=""><?php echo _l('opsdesk_update_status'); ?></option>
                                                        <?php foreach ($status_options as $status) {
                                                            $st = $status['status_key'];
                                                            $is_current = $order['status'] === $st;
                                                        ?>
                                                        <option value="<?php echo e($st); ?>" <?php echo $is_current ? 'disabled' : ''; ?>>
                                                            <?php echo e(opsdesk_get_order_status_label($st)); ?>
                                                        </option>
                                                        <?php } ?>
                                                    </select>
                                                 <?php echo form_close(); ?>
                                                 <?php }
                                                 } ?>
                                            </div>
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
<script>
(function () {
    "use strict";

    var packerRequiredMsg = <?php echo json_encode(_l('opsdesk_packed_by_required')); ?>;

    function value(el) {
        if (!el) { return ""; }
        var v = el.value;
        return (v === null || v === undefined) ? "" : String(v).trim();
    }

    function syncForm(form) {
        var packer = form.querySelector(".opsdesk-accept-packer");
        var btn = form.querySelector(".opsdesk-accept-btn");
        if (!packer || !btn) { return; }
        btn.disabled = value(packer) === "";
    }

    function syncAll() {
        var forms = document.querySelectorAll(".opsdesk-accept-form");
        for (var i = 0; i < forms.length; i++) {
            syncForm(forms[i]);
        }
    }

    function initOrdersTable() {
        var $table = typeof jQuery !== "undefined" ? jQuery(".opsdesk-orders-table") : null;
        if (!$table || !$table.length) {
            return;
        }

        try {
            if (typeof appDataTableInline === "function") {
                appDataTableInline($table, {
                    supportsButtons: true,
                    supportsLoading: true,
                    autoWidth: false,
                    order: [[0, "desc"]],
                    columnDefs: [
                        { orderable: false, targets: -1 },
                        { searchable: false, targets: -1 }
                    ]
                });
            } else if (typeof initDataTableInline === "function") {
                initDataTableInline($table);
            }
        } catch (err) {
            // Keep the table visible even if DataTables fails to start.
        }

        $table.parents(".table-loading").removeClass("table-loading");
        $table.removeClass("dt-table-loading table-loading");
    }

    function init() {
        initOrdersTable();
        syncAll();

        // Native capture-phase change listener: fires even if a bootstrap-select
        // handler throws or stops propagation. bootstrap-select updates the
        // underlying <select> and dispatches a native 'change' on it.
        document.addEventListener("change", function (e) {
            var packer = e.target && e.target.closest
                ? e.target.closest(".opsdesk-accept-packer")
                : null;
            if (!packer) { return; }
            var form = packer.closest(".opsdesk-accept-form");
            if (form) { syncForm(form); }
        }, true);

        // Safety net: keep button state correct even if change never fires
        // (e.g. selectpicker sets value programmatically).
        setInterval(syncAll, 500);

        // Submit guard.
        document.addEventListener("submit", function (e) {
            var form = e.target && e.target.closest
                ? e.target.closest(".opsdesk-accept-form")
                : null;
            if (!form) { return; }
            var packer = form.querySelector(".opsdesk-accept-packer");
            if (value(packer) === "") {
                e.preventDefault();
                if (typeof alert_float === "function") {
                    alert_float("warning", packerRequiredMsg);
                } else {
                    alert(packerRequiredMsg);
                }
            }
        }, true);
    }

    if (typeof jQuery !== "undefined") {
        jQuery(init);
    } else if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
</script>
</body>
</html>
