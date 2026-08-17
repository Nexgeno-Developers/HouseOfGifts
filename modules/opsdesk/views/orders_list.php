<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/orders_list.css'); ?>?v=<?php echo filemtime(module_dir_path(OPSDESK_MODULE_NAME, 'assets/css/orders_list.css')); ?>">
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
                                        <td class="opsdesk-actions-cell">
                                            <?php
                                            $is_own = (int) $order['created_by'] === (int) get_staff_user_id();
                                            $can_cancel = false;
                                            if (!empty($can_edit) && in_array($order['status'], ['pending', 'in_progress', 'packed'], true)) {
                                                $can_cancel = true;
                                            } elseif ($is_own && $order['status'] === 'pending') {
                                                $can_cancel = true;
                                            }
                                            $accept_status = '';
                                            $show_accept   = false;
                                            if (!empty($can_edit) && $order['status'] === 'pending') {
                                                $accept_status = opsdesk_get_default_next_status($order['status']);
                                                $show_accept   = $accept_status !== '';
                                            }
                                            $status_picks = [];
                                            if (!empty($can_edit) && !opsdesk_order_is_locked($order)) {
                                                foreach (opsdesk_get_next_order_statuses($order['status']) as $st) {
                                                    if ($st === 'cancelled') {
                                                        continue;
                                                    }
                                                    if ($show_accept && $st === 'in_progress') {
                                                        continue;
                                                    }
                                                    $status_picks[] = $st;
                                                }
                                            }
                                            ?>
                                            <div class="opsdesk-row-actions">
                                                <a href="<?php echo admin_url('opsdesk/order/' . $order['id']); ?>"
                                                    class="opsdesk-action-btn"
                                                    data-toggle="tooltip"
                                                    data-placement="top"
                                                    title="<?php echo e(_l('opsdesk_view_order')); ?>">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>
                                                <?php if ($show_accept) { ?>
                                                <button type="button"
                                                    class="opsdesk-action-btn opsdesk-action-success opsdesk-open-accept"
                                                    data-toggle="tooltip"
                                                    data-placement="top"
                                                    title="<?php echo e(_l('opsdesk_accept_order')); ?>"
                                                    data-order-id="<?php echo (int) $order['id']; ?>"
                                                    data-status="<?php echo e($accept_status); ?>">
                                                    <i class="fa-regular fa-circle-check"></i>
                                                </button>
                                                <?php } ?>
                                                <?php if (!empty($status_picks)) { ?>
                                                <div class="opsdesk-status-dd">
                                                    <button type="button"
                                                        class="opsdesk-action-btn opsdesk-status-toggle"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        data-toggle="tooltip"
                                                        data-placement="top"
                                                        title="<?php echo e(_l('opsdesk_update_status')); ?>">
                                                        <i class="fa fa-refresh"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-right opsdesk-status-menu">
                                                        <li class="dropdown-header"><?php echo e(_l('opsdesk_update_status')); ?></li>
                                                        <?php foreach ($status_picks as $st) { ?>
                                                        <li>
                                                            <a href="#"
                                                                class="opsdesk-status-pick"
                                                                data-order-id="<?php echo (int) $order['id']; ?>"
                                                                data-status="<?php echo e($st); ?>">
                                                                <?php echo e(opsdesk_get_order_status_label($st)); ?>
                                                            </a>
                                                        </li>
                                                        <?php } ?>
                                                    </ul>
                                                </div>
                                                <?php } ?>
                                                <?php if ($can_cancel) { ?>
                                                <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order['id']); ?>"
                                                    class="opsdesk-action-btn opsdesk-action-danger _delete"
                                                    data-toggle="tooltip"
                                                    data-placement="top"
                                                    title="<?php echo e(_l('opsdesk_cancel_order')); ?>">
                                                    <i class="fa-regular fa-circle-xmark"></i>
                                                </a>
                                                <?php } ?>
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

<?php if (!empty($can_edit)) { ?>
<?php echo form_open(admin_url('opsdesk/update_order_status'), ['id' => 'opsdesk_status_form', 'class' => 'hide']); ?>
<input type="hidden" name="order_id" id="opsdesk_status_order_id" value="">
<input type="hidden" name="status" id="opsdesk_status_value" value="">
<?php echo form_close(); ?>

<div class="modal fade" id="opsdesk_accept_modal" tabindex="-1" role="dialog" aria-labelledby="opsdesk_accept_modal_title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open(admin_url('opsdesk/update_order_status'), ['id' => 'opsdesk_accept_form']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo e(_l('close')); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="opsdesk_accept_modal_title"><?php echo e(_l('opsdesk_accept_order')); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="order_id" id="opsdesk_accept_order_id" value="">
                <input type="hidden" name="status" id="opsdesk_accept_status" value="">
                <p class="text-muted" id="opsdesk_accept_order_label"></p>
                <div class="form-group">
                    <label for="opsdesk_accept_packed_by">
                        <?php echo e(_l('opsdesk_assigned_to')); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <select name="packed_by" id="opsdesk_accept_packed_by" class="selectpicker" data-width="100%" data-live-search="true" required>
                        <option value=""><?php echo e(_l('opsdesk_assigned_to')); ?></option>
                        <?php foreach (($staff_members ?? []) as $sm) { ?>
                        <option value="<?php echo (int) $sm['staffid']; ?>"><?php echo e($sm['full_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo e(_l('close')); ?></button>
                <button type="submit" class="btn btn-success" id="opsdesk_accept_submit" disabled>
                    <?php echo e(_l('opsdesk_accept_order')); ?>
                </button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<script>
(function ($) {
    "use strict";

    var packerRequiredMsg = <?php echo json_encode(_l('opsdesk_packed_by_required')); ?>;
    var acceptTitle = <?php echo json_encode(_l('opsdesk_accept_order')); ?>;

    function packedByValue() {
        var el = document.getElementById("opsdesk_accept_packed_by");
        if (!el) { return ""; }
        var v = el.value;
        return (v === null || v === undefined) ? "" : String(v).trim();
    }

    function syncAcceptSubmit() {
        var btn = document.getElementById("opsdesk_accept_submit");
        if (btn) {
            btn.disabled = packedByValue() === "";
        }
    }

    function openAcceptModal(orderId, status) {
        var $modal = $("#opsdesk_accept_modal");
        if (!$modal.length) { return; }
        $("#opsdesk_accept_order_id").val(orderId);
        $("#opsdesk_accept_status").val(status);
        $("#opsdesk_accept_order_label").text("#" + orderId);
        $modal.find(".modal-title").text(acceptTitle + " #" + orderId);
        $("#opsdesk_accept_packed_by").val("");
        if ($.fn.selectpicker) {
            $("#opsdesk_accept_packed_by").selectpicker("val", "");
            $("#opsdesk_accept_packed_by").selectpicker("refresh");
        }
        syncAcceptSubmit();
        $modal.modal("show");
    }

    function submitStatus(orderId, status) {
        $("#opsdesk_status_order_id").val(orderId);
        $("#opsdesk_status_value").val(status);
        $("#opsdesk_status_form").trigger("submit");
    }

    function initOrdersTable() {
        var $table = $(".opsdesk-orders-table");
        if (!$table.length) {
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

    function initTooltips($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('[data-toggle="tooltip"]').tooltip({ container: "body" });
    }

    $(function () {
        initOrdersTable();
        initTooltips();

        $(document).on("click", ".opsdesk-open-accept", function (e) {
            e.preventDefault();
            openAcceptModal($(this).data("order-id"), $(this).data("status"));
        });

        function closeStatusDropdowns() {
            $(".opsdesk-status-dd").each(function () {
                var $group = $(this);
                var $menu = $group.data("opsdeskMenu");
                if (!$menu || !$menu.length) {
                    $menu = $group.children(".opsdesk-status-menu");
                }
                if ($menu && $menu.length) {
                    $menu.removeClass("opsdesk-status-menu-open").removeAttr("style");
                    $group.append($menu);
                }
                $group.removeClass("open").removeData("opsdeskMenu");
                $group.find(".opsdesk-status-toggle").attr("aria-expanded", "false");
            });
        }

        function openStatusDropdown($group) {
            var $menu = $group.children(".opsdesk-status-menu");
            var $toggle = $group.find(".opsdesk-status-toggle");
            if (!$menu.length) { return; }

            var offset = $toggle.offset();
            $group.addClass("open").data("opsdeskMenu", $menu);
            $toggle.attr("aria-expanded", "true");
            if ($toggle.data("bs.tooltip")) {
                $toggle.tooltip("hide");
            }
            $menu.appendTo("body").addClass("opsdesk-status-menu-open").css({
                display: "block",
                position: "absolute",
                top: offset.top + $toggle.outerHeight(),
                left: offset.left + $toggle.outerWidth() - $menu.outerWidth(),
                zIndex: 2000
            }).show();
        }

        $(document).on("click", ".opsdesk-status-toggle", function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $group = $(this).closest(".opsdesk-status-dd");
            var isOpen = $group.hasClass("open");
            closeStatusDropdowns();
            if (!isOpen) {
                openStatusDropdown($group);
            }
        });

        $(document).on("click", ".opsdesk-status-pick", function (e) {
            e.preventDefault();
            e.stopPropagation();
            var orderId = $(this).data("order-id");
            var status = $(this).data("status");
            closeStatusDropdowns();
            submitStatus(orderId, status);
        });

        $(document).on("click", function (e) {
            if ($(e.target).closest(".opsdesk-status-dd, .opsdesk-status-menu-open").length) {
                return;
            }
            closeStatusDropdowns();
        });

        $(document).on("keydown", function (e) {
            if (e.key === "Escape" || e.keyCode === 27) {
                closeStatusDropdowns();
            }
        });

        $(window).on("scroll resize", closeStatusDropdowns);
        $("#wrapper").on("scroll", closeStatusDropdowns);

        $(document).on("changed.bs.select change", "#opsdesk_accept_packed_by", syncAcceptSubmit);

        $("#opsdesk_accept_modal").on("shown.bs.modal", function () {
            if ($.fn.selectpicker) {
                $("#opsdesk_accept_packed_by").selectpicker("refresh");
            }
            syncAcceptSubmit();
        });

        $("#opsdesk_accept_form").on("submit", function (e) {
            if (packedByValue() === "") {
                e.preventDefault();
                if (typeof alert_float === "function") {
                    alert_float("warning", packerRequiredMsg);
                } else {
                    alert(packerRequiredMsg);
                }
            }
        });

        $(".opsdesk-orders-table").on("draw.dt", function () {
            initTooltips($(".opsdesk-orders-table"));
        });
    });
})(jQuery);
</script>
</body>
</html>
