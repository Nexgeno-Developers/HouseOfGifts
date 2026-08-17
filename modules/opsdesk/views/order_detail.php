<?php // opsdesk order detail view - rebuilt
init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/order_detail.css'); ?>?v=<?php echo filemtime(module_dir_path(OPSDESK_MODULE_NAME, 'assets/css/order_detail.css')); ?>">
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <!-- Header -->
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
                    <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 tw-mb-0">
                        <?php echo e($title); ?>
                        <span class="label <?php echo opsdesk_get_order_status_class($order['status']); ?> mleft5">
                            <?php echo e(opsdesk_get_order_status_label($order['status'])); ?>
                        </span>
                        <?php echo opsdesk_get_priority_badge($order['priority']); ?>
                    </h4>
                    <a href="<?php echo admin_url('opsdesk/orders'); ?>" class="btn btn-default">
                        <?php echo _l('opsdesk_all_orders'); ?>
                    </a>
                </div>

                <!-- Visual Stepper -->
                <?php
                $all_statuses = opsdesk_get_order_statuses(true);
                $flow_statuses = [];
                $cancelled_status = null;
                foreach ($all_statuses as $status) {
                    if ($status['status_key'] === 'cancelled') {
                        $cancelled_status = $status;
                        continue;
                    }
                    $flow_statuses[] = $status;
                }
                if ($cancelled_status === null) {
                    $cancelled_status = [
                        'status_key' => 'cancelled',
                        'name'       => _l('opsdesk_order_status_cancelled'),
                    ];
                }
                $current_status = $order['status'];
                $is_cancelled   = $current_status === 'cancelled';
                $flow_keys      = array_column($flow_statuses, 'status_key');
                $current_index  = array_search($current_status, $flow_keys, true);
                if ($current_index === false) {
                    $current_index = $is_cancelled ? -1 : 0;
                }
                ?>
                <div class="opsdesk-stepper-wrap tw-mb-4">
                    <div class="opsdesk-stepper">
                        <?php foreach ($flow_statuses as $index => $status) {
                            $is_done    = !$is_cancelled && $index < $current_index;
                            $is_current = !$is_cancelled && $index === $current_index;
                            $step_class = 'stepper-step';
                            if ($is_done) {
                                $step_class .= ' stepper-completed';
                            } elseif ($is_current) {
                                $step_class .= ' stepper-current';
                            }
                            if ($is_cancelled) {
                                $step_class .= ' stepper-skipped';
                            }
                        ?>
                        <div class="<?php echo $step_class; ?>">
                            <div class="stepper-icon">
                                <?php if ($is_done) { ?>
                                    <i class="fa fa-check"></i>
                                <?php } elseif ($is_current) { ?>
                                    <span class="stepper-current-dot"></span>
                                <?php } else { ?>
                                    <span class="stepper-number"><?php echo $index + 1; ?></span>
                                <?php } ?>
                            </div>
                            <div class="stepper-label"><?php echo e($status['name']); ?></div>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="opsdesk-stepper-cancelled<?php echo $is_cancelled ? ' is-active' : ''; ?>">
                        <div class="stepper-icon">
                            <?php if ($is_cancelled) { ?>
                                <i class="fa fa-times"></i>
                            <?php } else { ?>
                                <i class="fa fa-ban"></i>
                            <?php } ?>
                        </div>
                        <div class="stepper-label"><?php echo e($cancelled_status['name']); ?></div>
                    </div>
                </div>

                <div class="row">
                    <!-- Main Column (Left 7) -->
                    <div class="col-md-7">
                        <!-- Order Information Card -->
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="no-margin font-bold"><?php echo _l('opsdesk_order_information'); ?></h5>
                                <hr class="hr-panel-heading" />
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="opsdesk-meta-item">
                                            <span class="opsdesk-meta-label"><?php echo _l('opsdesk_combo_name'); ?></span>
                                            <span class="opsdesk-meta-value"><?php echo e($order['combo_name']); ?></span>
                                        </div>
                                        <div class="opsdesk-meta-item">
                                            <span class="opsdesk-meta-label"><?php echo _l('opsdesk_order_quantity'); ?></span>
                                            <span class="opsdesk-meta-value"><?php echo (int) $order['quantity']; ?></span>
                                        </div>
                                        <div class="opsdesk-meta-item">
                                            <span class="opsdesk-meta-label"><?php echo _l('opsdesk_packing_type'); ?></span>
                                            <span class="opsdesk-meta-value"><?php echo e(opsdesk_get_packing_type_label($order['packing_type'])); ?></span>
                                        </div>
                                        <div class="opsdesk-meta-item">
                                            <span class="opsdesk-meta-label"><?php echo _l('opsdesk_transport_medium'); ?></span>
                                            <span class="opsdesk-meta-value"><?php echo e(opsdesk_get_transport_medium_label($order['transport_medium_id'] ?? '')); ?></span>
                                        </div>
                                        <div class="opsdesk-meta-item">
                                            <span class="opsdesk-meta-label"><?php echo _l('opsdesk_delivery_date'); ?></span>
                                            <span class="opsdesk-meta-value">
                                                <?php if (!empty($order['delivery_date'])) { ?>
                                                    <?php echo e(_d($order['delivery_date'])); ?>
                                                <?php } else { ?>
                                                    <span class="text-muted"><?php echo _l('opsdesk_no_delivery_date'); ?></span>
                                                <?php } ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($order['customer_id'])) { ?>
                                        <div class="opsdesk-meta-item">
                                            <span class="opsdesk-meta-label"><?php echo _l('opsdesk_customer'); ?></span>
                                            <span class="opsdesk-meta-value">
                                                <?php
                                                $ops_customer = get_client($order['customer_id']);
                                                echo e($ops_customer ? $ops_customer->company : _l('opsdesk_unknown_product'));
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($order['customer_city'])) { ?>
                                        <div class="opsdesk-meta-item">
                                            <span class="opsdesk-meta-label"><?php echo _l('opsdesk_customer_city'); ?></span>
                                            <span class="opsdesk-meta-value"><?php echo e($order['customer_city']); ?></span>
                                        </div>
                                        <?php } ?>
                                        <?php } ?>
<div class="opsdesk-meta-item">
                                             <span class="opsdesk-meta-label"><?php echo _l('opsdesk_created_by'); ?></span>
                                             <span class="opsdesk-meta-value">
                                                 <?php if (!empty($order['creator_name'])) { ?>
                                                     <?php echo e($order['creator_name']); ?>
                                                 <?php } else { ?>
                                                     <span class="text-muted"><?php echo _l('opsdesk_unknown'); ?></span>
                                                 <?php } ?>
                                             </span>
                                         </div>
                                         <div class="opsdesk-meta-item">
                                             <span class="opsdesk-meta-label"><?php echo _l('opsdesk_created_at'); ?></span>
                                             <span class="opsdesk-meta-value">
                                                 <?php if (!empty($order['created_at'])) { ?>
                                                     <?php echo e(_dt($order['created_at'])); ?>
                                                 <?php } else { ?>
                                                     <span class="text-muted"><?php echo _l('opsdesk_no_date'); ?></span>
                                                 <?php } ?>
                                             </span>
                                         </div>
                                    </div>
                                </div>
                                <?php if (!empty($order['notes'])) { ?>
                                <div class="opsdesk-meta-item tw-mt-3">
                                    <span class="opsdesk-meta-label"><?php echo _l('opsdesk_notes'); ?></span>
                                    <p class="tw-mt-1 tw-mb-0"><?php echo nl2br(e($order['notes'])); ?></p>
                                </div>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Combo Line Items Table -->
                        <div class="panel_s mtop15">
                            <div class="panel-body">
                                <h5 class="no-margin font-bold"><?php echo _l('opsdesk_combo_items'); ?></h5>
                                <hr class="hr-panel-heading" />
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
                                            <?php foreach ($order['items'] as $item) { ?>
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
                            </div>
                        </div>

                        <!-- Status History Timeline -->
                        <div class="panel_s mtop15">
                            <div class="panel-body">
                                <h5 class="no-margin font-bold"><?php echo _l('opsdesk_status_history'); ?></h5>
                                <hr class="hr-panel-heading" />
                                <div class="opsdesk-timeline">
                                    <?php foreach ($order['status_log'] as $log) { ?>
                                    <div class="opsdesk-timeline-item">
                                        <div class="opsdesk-timeline-marker"></div>
                                        <div class="opsdesk-timeline-content">
                                            <div class="tw-flex tw-justify-between tw-items-start">
                                                <div>
                                                    <strong><?php echo e($log['staff_name']); ?></strong>
                                                    <div class="tw-text-sm tw-text-neutral-500">
                                                        <?php echo e(_dt($log['created_at'])); ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($log['from_status']) { ?>
                                                    <span class="label <?php echo opsdesk_get_order_status_class($log['from_status']); ?> tw-mr-1">
                                                        <?php echo e(opsdesk_get_order_status_label($log['from_status'])); ?>
                                                    </span>
                                                    <i class="fa fa-arrow-right tw-mx-1 tw-text-neutral-400"></i>
                                                    <?php } ?>
                                                    <span class="label <?php echo opsdesk_get_order_status_class($log['to_status']); ?>">
                                                        <?php echo e(opsdesk_get_order_status_label($log['to_status'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if (!empty($log['notes'])) { ?>
                                            <div class="tw-text-sm tw-text-neutral-600 tw-mt-1">
                                                <?php echo e($log['notes']); ?>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Unified Workflow Sidebar (Right 5) -->
                    <div class="col-md-5">
                        <!-- Operations Control Card -->
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="no-margin font-bold"><?php echo _l('opsdesk_operations_control'); ?></h5>
                                <hr class="hr-panel-heading" />

                                <!-- Tab navigation -->
                                <?php
                                $opsdesk_tab = $this->input->get('tab');
                                $opsdesk_tab_assign   = ($opsdesk_tab === 'assign');
                                $opsdesk_tab_priority = ($opsdesk_tab === 'priority');
                                $opsdesk_tab_status   = (!$opsdesk_tab_assign && !$opsdesk_tab_priority);
                                ?>
                                <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                    <li role="presentation"<?php echo $opsdesk_tab_status ? ' class="active"' : ''; ?>>
                                        <a href="#opsdesk-tab-status" aria-controls="opsdesk-tab-status" role="tab" data-toggle="tab">
                                            <?php echo _l('opsdesk_status'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation"<?php echo $opsdesk_tab_assign ? ' class="active"' : ''; ?>>
                                        <a href="#opsdesk-tab-assign" aria-controls="opsdesk-tab-assign" role="tab" data-toggle="tab">
                                            <?php echo _l('opsdesk_assignment'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation"<?php echo $opsdesk_tab_priority ? ' class="active"' : ''; ?>>
                                        <a href="#opsdesk-tab-priority" aria-controls="opsdesk-tab-priority" role="tab" data-toggle="tab">
                                            <?php echo _l('opsdesk_priority'); ?>
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content tw-mt-3">
                                    <!-- Tab 1: Status Advance & Acceptance -->
                                    <div role="tabpanel" class="tab-pane<?php echo $opsdesk_tab_status ? ' active' : ''; ?>" id="opsdesk-tab-status">
                                        <?php if (!empty($can_edit)) { ?>
                                            <?php if ($order['status'] === 'pending') { ?>
                                            <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'opsdesk-accept-form row-margin-bottom']); ?>
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <input type="hidden" name="status" value="in_progress">
                                            <div class="form-group row-margin-bottom">
                                                <label><?php echo _l('opsdesk_assigned_to'); ?> <span class="text-danger">*</span></label>
                                                <select name="packed_by" class="selectpicker opsdesk-accept-packer" data-width="100%" required>
                                                    <option value=""><?php echo _l('opsdesk_unassigned'); ?></option>
                                                    <?php foreach ($staff_members as $sm) { ?>
                                                    <option value="<?php echo (int) $sm['staffid']; ?>"><?php echo e($sm['full_name']); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm button-margin-r-b opsdesk-accept-btn" disabled>
                                                <i class="fa fa-check"></i> <?php echo _l('opsdesk_accept_order'); ?>
                                            </button>
                                            <?php echo form_close(); ?>
                                            <?php } ?>

                                            <div class="row-margin-bottom">
                                                <?php
                                                $next_statuses = opsdesk_get_order_status_option_keys(false);
                                                if (!empty($next_statuses)) {
                                                ?>
                                                <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'row-margin-bottom']); ?>
                                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                    <select name="status" class="selectpicker" data-width="100%" onchange="if (this.value) this.form.submit();">
                                                        <option value=""><?php echo _l('opsdesk_update_status'); ?></option>
                                                        <?php foreach ($next_statuses as $status_key) {
                                                            if ($order['status'] === $status_key) { continue; }
                                                        ?>
                                                        <option value="<?php echo e($status_key); ?>">
                                                            <?php echo e(opsdesk_get_order_status_label($status_key)); ?>
                                                        </option>
                                                        <?php } ?>
                                                    </select>
                                                <?php echo form_close(); ?>
                                                <?php } ?>
                                            </div>

                                            <?php if (!empty($can_cancel_own) || !empty($can_cancel_any)) { ?>
                                            <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order['id']); ?>"
                                                class="btn btn-danger _delete button-margin-r-b"
                                                data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                                <?php echo _l('opsdesk_cancel_order'); ?>
                                            </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <div class="text-muted">
                                                <?php if (!empty($can_cancel_own) || !empty($can_cancel_any)) { ?>
                                                <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order['id']); ?>"
                                                    class="btn btn-danger _delete button-margin-r-b"
                                                    data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                                    <?php echo _l('opsdesk_cancel_order'); ?>
                                                </a>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>

                                    <!-- Tab 2: Staff Assignment -->
                                    <div role="tabpanel" class="tab-pane<?php echo $opsdesk_tab_assign ? ' active' : ''; ?>" id="opsdesk-tab-assign">
                                        <?php if (!empty($can_edit)) { ?>
                                        <?php echo form_open(admin_url('opsdesk/assign_order/' . $order['id']), ['id' => 'opsdesk_staff_assign_form', 'class' => 'row-margin-bottom']); ?>
                                        <div class="form-group row-margin-bottom">
                                            <label><?php echo _l('opsdesk_packed_by'); ?></label>
                                            <select name="packed_by" id="opsdesk_packed_by" class="selectpicker" data-width="100%">
                                                <option value=""><?php echo _l('opsdesk_unassigned'); ?></option>
                                                <?php foreach ($staff_members as $sm) { ?>
                                                <option value="<?php echo (int) $sm['staffid']; ?>"
                                                    <?php echo (!empty($order['packed_by']) && (int) $order['packed_by'] === (int) $sm['staffid']) ? 'selected' : ''; ?>>
                                                    <?php echo e($sm['full_name']); ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group row-margin-bottom">
                                            <label><?php echo _l('opsdesk_count_by'); ?></label>
                                            <input type="hidden" name="count_by" id="opsdesk_count_by_hidden"
                                                value="<?php echo !empty($order['count_by']) ? (int) $order['count_by'] : ''; ?>">
                                            <select id="opsdesk_count_by" class="selectpicker" data-width="100%">
                                                <option value=""><?php echo _l('opsdesk_unassigned'); ?></option>
                                                <?php foreach ($staff_members as $sm) { ?>
                                                <option value="<?php echo (int) $sm['staffid']; ?>"
                                                    <?php echo (!empty($order['count_by']) && (int) $order['count_by'] === (int) $sm['staffid']) ? 'selected' : ''; ?>>
                                                    <?php echo e($sm['full_name']); ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group row-margin-bottom">
                                            <label for="opsdesk_carton_count"><?php echo _l('opsdesk_carton_count'); ?></label>
                                            <input type="number" name="carton_count" id="opsdesk_carton_count"
                                                class="form-control"
                                                min="1"
                                                value="<?php echo !empty($order['carton_count']) ? (int) $order['carton_count'] : ''; ?>"
                                                placeholder="<?php echo _l('opsdesk_carton_count_placeholder'); ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm button-margin-r-b" id="opsdesk_assign_btn">
                                            <?php echo _l('submit'); ?>
                                        </button>
                                        <?php echo form_close(); ?>
                                        <?php } else { ?>
                                        <div class="row-margin-bottom">
                                            <strong><?php echo _l('opsdesk_packed_by'); ?>:</strong>
                                            <span class="mleft10"><?php echo $packed_by_name ? e($packed_by_name) : '—'; ?></span>
                                        </div>
                                        <div class="row-margin-bottom">
                                            <strong><?php echo _l('opsdesk_count_by'); ?>:</strong>
                                            <span class="mleft10"><?php echo !empty($order['count_by']) ? e(get_staff_full_name($order['count_by'])) : '—'; ?></span>
                                        </div>
                                        <div class="row-margin-bottom">
                                            <strong><?php echo _l('opsdesk_carton_count'); ?>:</strong>
                                            <span class="mleft10"><?php echo !empty($order['carton_count']) ? (int) $order['carton_count'] : '—'; ?></span>
                                        </div>
                                        <?php } ?>
                                    </div>

                                    <!-- Tab 3: Priority Flag Toggle -->
                                    <div role="tabpanel" class="tab-pane<?php echo $opsdesk_tab_priority ? ' active' : ''; ?>" id="opsdesk-tab-priority">
                                        <?php if (!empty($can_edit)) { ?>
                                        <?php echo form_open(admin_url('opsdesk/update_priority/' . $order['id']), ['id' => 'opsdesk_priority_form', 'class' => 'row-margin-bottom']); ?>
                                        <div class="form-group row-margin-bottom">
                                            <div class="radio radio-primary radio-inline mright15">
                                                <input type="radio" name="priority" id="opsdesk_p_inline_normal" value="0"
                                                    <?php echo (int) $order['priority'] === 0 ? 'checked' : ''; ?>>
                                                <label for="opsdesk_p_inline_normal"><?php echo _l('opsdesk_priority_normal'); ?></label>
                                            </div>
                                            <div class="radio radio-danger radio-inline mright15">
                                                <input type="radio" name="priority" id="opsdesk_p_inline_high" value="1"
                                                    <?php echo (int) $order['priority'] === 1 ? 'checked' : ''; ?>>
                                                <label for="opsdesk_p_inline_high"><?php echo _l('opsdesk_priority_high'); ?></label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm button-margin-r-b">
                                            <?php echo _l('submit'); ?>
                                        </button>
                                        <?php echo form_close(); ?>
                                        <?php } else { ?>
                                        <p><?php echo e(opsdesk_get_priority_label($order['priority'])); ?></p>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents & Attachments Card -->
                        <div class="panel_s mtop15">
                            <div class="panel-body">
                                <h5 class="no-margin font-bold"><?php echo _l('opsdesk_documents_attachments'); ?></h5>
                                <hr class="hr-panel-heading" />

                                <div class="opsdesk-attachments">
                                    <!-- Bill File -->
                                    <div class="opsdesk-attachment-item">
                                        <div class="tw-flex tw-justify-between tw-items-center">
                                            <span class="label label-primary"><?php echo _l('opsdesk_bill_upload'); ?></span>
                                            <?php if (!empty($order['bill_file'])) { ?>
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order['bill_file'])); ?>"
                                                data-opsdesk-preview="1"
                                                data-opsdesk-type="<?php echo e(opsdesk_file_preview_type($order['bill_file'])); ?>">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <!-- Payment File -->
                                    <div class="opsdesk-attachment-item">
                                        <div class="tw-flex tw-justify-between tw-items-center">
                                            <span class="label label-<?php echo !empty($order['payment_file']) ? 'success' : 'danger'; ?>">
                                                <?php echo _l('opsdesk_payment_received'); ?>
                                            </span>
                                            <?php if (!empty($order['payment_file'])) { ?>
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order['payment_file'])); ?>"
                                                data-opsdesk-preview="1"
                                                data-opsdesk-type="<?php echo e(opsdesk_file_preview_type($order['payment_file'])); ?>">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                        </div>
                                        <?php if (!empty($can_edit)) { ?>
                                        <div class="mtop10">
                                            <?php echo form_open_multipart(admin_url('opsdesk/upload_payment_file/' . $order['id']), ['id' => 'opsdesk_payment_upload_form']); ?>
                                            <input type="file" name="payment_file" id="opsdesk_payment_file"
                                                class="form-control"
                                                accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                            <button type="submit" class="btn btn-success btn-sm mtop5 button-margin-r-b">
                                                <i class="fa fa-upload"></i> <?php echo _l('opsdesk_upload_payment'); ?>
                                            </button>
                                            <?php echo form_close(); ?>
                                        </div>
                                        <?php } ?>
                                    </div>

                                    <!-- LR Copy -->
                                    <div class="opsdesk-attachment-item">
                                        <div class="tw-flex tw-justify-between tw-items-center">
                                            <span class="label label-info"><?php echo _l('opsdesk_lr_copy_upload'); ?></span>
                                            <?php if (!empty($order['lr_copy'])) { ?>
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order['lr_copy'])); ?>"
                                                data-opsdesk-preview="1"
                                                data-opsdesk-type="<?php echo e(opsdesk_file_preview_type($order['lr_copy'])); ?>">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                        </div>
                                        <?php if (!empty($can_edit)) { ?>
                                        <div class="mtop10">
                                            <?php echo form_open_multipart(admin_url('opsdesk/upload_lr_copy/' . $order['id']), ['id' => 'opsdesk_lr_copy_upload_form']); ?>
                                            <input type="file" name="lr_copy" id="opsdesk_lr_copy"
                                                class="form-control"
                                                accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                            <button type="submit" class="btn btn-success btn-sm mtop5 button-margin-r-b">
                                                <i class="fa fa-upload"></i> <?php echo _l('opsdesk_upload_lr_copy'); ?>
                                            </button>
                                            <?php echo form_close(); ?>
                                        </div>
                                        <?php } ?>
                                    </div>

                                    <!-- Carton Photo -->
                                    <?php
                                    $carton_photos = opsdesk_parse_carton_photos($order['carton_photo'] ?? '');
                                    $carton_gallery = [];
                                    foreach ($carton_photos as $photo) {
                                        $carton_gallery[] = [
                                            'url'  => opsdesk_file_url($photo),
                                            'type' => opsdesk_file_preview_type($photo),
                                        ];
                                    }
                                    ?>
                                    <div class="opsdesk-attachment-item">
                                        <div class="tw-flex tw-justify-between tw-items-center">
                                            <span class="label label-warning"><?php echo _l('opsdesk_carton_photo_upload'); ?></span>
                                            <?php if (!empty($carton_gallery)) { ?>
                                            <a class="opsdesk-file-link" href="<?php echo e($carton_gallery[0]['url']); ?>"
                                                data-opsdesk-preview="1"
                                                data-opsdesk-type="<?php echo e($carton_gallery[0]['type']); ?>"
                                                data-opsdesk-gallery="<?php echo e(json_encode($carton_gallery)); ?>">
                                                <i class="fa fa-file-image-o"></i>
                                                <?php echo count($carton_gallery) > 1
                                                    ? _l('opsdesk_view_files') . ' (' . count($carton_gallery) . ')'
                                                    : _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                        </div>
                                        <?php if (!empty($can_edit)) { ?>
                                        <div class="mtop10">
                                            <?php echo form_open_multipart(admin_url('opsdesk/upload_carton_photo/' . $order['id']), ['id' => 'opsdesk_carton_photo_upload_form']); ?>
                                            <input type="file" name="carton_photos[]" id="opsdesk_carton_photos"
                                                class="form-control"
                                                accept="image/*"
                                                multiple>
                                            <button type="submit" class="btn btn-success btn-sm mtop5 button-margin-r-b">
                                                <i class="fa fa-upload"></i> <?php echo _l('opsdesk_upload_carton_photo'); ?>
                                            </button>
                                            <?php echo form_close(); ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="opsdesk_order_id" value="<?php echo (int) $order['id']; ?>">

<div class="modal fade" id="opsdesk_file_preview_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?php echo _l('opsdesk_file_preview'); ?></h4>
            </div>
            <div class="modal-body">
                <div id="opsdesk_preview_image_wrap" class="hide text-center">
                    <img id="opsdesk_preview_image" src="" alt="">
                </div>
                <div id="opsdesk_preview_pdf_wrap" class="hide">
                    <iframe id="opsdesk_preview_pdf" src="" title="<?php echo e(_l('opsdesk_file_preview')); ?>"></iframe>
                </div>
                <div id="opsdesk_preview_other_wrap" class="hide">
                    <p><?php echo _l('opsdesk_no_preview'); ?></p>
                    <a id="opsdesk_preview_download" href="#" class="btn btn-primary" download>
                        <i class="fa fa-download"></i> <?php echo _l('opsdesk_download_file'); ?>
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default hide" id="opsdesk_preview_prev">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <span id="opsdesk_preview_counter" class="text-muted hide tw-mx-2"></span>
                <button type="button" class="btn btn-default hide" id="opsdesk_preview_next">
                    <i class="fa fa-chevron-right"></i>
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
      (function () {
          'use strict';

          // Copy selectpicker values into named fields before the assignment form posts.
          document.addEventListener('submit', function (e) {
              var form = e.target;
              if (!form || form.id !== 'opsdesk_staff_assign_form') {
                  return;
              }
              var countSelect = document.getElementById('opsdesk_count_by');
              var countHidden = document.getElementById('opsdesk_count_by_hidden');
              var packedSelect = document.getElementById('opsdesk_packed_by');
              var countVal = '';
              var packedVal = '';
              if (typeof jQuery !== 'undefined') {
                  countVal = jQuery('#opsdesk_count_by').val() || '';
                  packedVal = jQuery('#opsdesk_packed_by').val() || '';
              } else {
                  countVal = countSelect ? countSelect.value : '';
                  packedVal = packedSelect ? packedSelect.value : '';
              }
              if (countHidden) {
                  countHidden.value = countVal;
              }
              if (packedSelect) {
                  packedSelect.value = packedVal;
              }
          }, true);

          if (typeof jQuery !== 'undefined') {
              jQuery(function ($) {
                  $(document).on('changed.bs.select', '#opsdesk_count_by', function () {
                      var hidden = document.getElementById('opsdesk_count_by_hidden');
                      if (hidden) {
                          hidden.value = $(this).val() || '';
                      }
                  });
              });
          }

var paymentRequiredMsg = <?php echo json_encode(_l('opsdesk_payment_required_for_completion')); ?>;
           var hasPaymentFile = <?php echo !empty($order['payment_file']) ? 'true' : 'false'; ?>;
           var hasLrCopy = <?php echo !empty($order['lr_copy']) ? 'true' : 'false'; ?>;
           var hasCartonPhoto = <?php echo !empty($carton_photos) ? 'true' : 'false'; ?>;
           var lrCopyRequiredMsg = <?php echo json_encode(_l('opsdesk_lr_copy_required_for_completion')); ?>;
           var cartonPhotoRequiredMsg = <?php echo json_encode(_l('opsdesk_carton_photo_required_for_completion')); ?>;
           var cartonCountRequiredMsg = <?php echo json_encode(_l('opsdesk_carton_count_required_for_completion')); ?>;
           var packedByRequiredMsg = <?php echo json_encode(_l('opsdesk_packed_by_required')); ?>;
           var countByRequiredMsg = <?php echo json_encode(_l('opsdesk_count_by_required_for_completion')); ?>;
           var hasCountBy = <?php echo !empty($order['count_by']) ? 'true' : 'false'; ?>;

           document.addEventListener('submit', function (e) {
               var form = e.target && e.target.closest
                   ? e.target.closest('form[action*="opsdesk/update_order_status"], form[action*="opsdesk/upload_payment_file"]')
                   : null;
               if (!form) {
                   return;
               }

               var actionUrl = form.getAttribute('action') || '';
               var isUpdateStatus = actionUrl.indexOf('opsdesk/update_order_status') !== -1;
               var isUploadPayment = actionUrl.indexOf('opsdesk/upload_payment_file') !== -1;

               // Handle payment upload form
               if (isUploadPayment && !hasPaymentFile) {
                   return; // Allow payment upload when no payment file exists
               }

               // Handle update order status form
               if (isUpdateStatus) {
                    var statusField = form.querySelector('input[name="status"], select[name="status"]');
                   var newStatus = statusField ? String(statusField.value || '').toLowerCase() : '';

// Prevent completion if payment file missing
                    if (newStatus === 'completed' && !hasPaymentFile) {
                        e.preventDefault();
                        if (typeof alert_float === 'function') {
                            alert_float('warning', paymentRequiredMsg);
                        } else {
                            alert(paymentRequiredMsg);
                        }
                        return;
                    }

                    // Prevent completion if LR copy missing
                    if (newStatus === 'completed' && !hasLrCopy) {
                        e.preventDefault();
                        if (typeof alert_float === 'function') {
                            alert_float('warning', lrCopyRequiredMsg);
                        } else {
                            alert(lrCopyRequiredMsg);
                        }
                        return;
                    }

                    // Prevent completion if carton photo missing
                    if (newStatus === 'completed' && !hasCartonPhoto) {
                        e.preventDefault();
                        if (typeof alert_float === 'function') {
                            alert_float('warning', cartonPhotoRequiredMsg);
                        } else {
                            alert(cartonPhotoRequiredMsg);
                        }
                        return;
                    }

                    // Prevent completion if carton count missing
                    if (newStatus === 'completed' && hasPaymentFile) {
                        var cartonCountInput = document.querySelector('input[name="carton_count"]');
                        if (!cartonCountInput || cartonCountInput.value === '') {
                            e.preventDefault();
                            if (typeof alert_float === 'function') {
                                alert_float('warning', cartonCountRequiredMsg);
                            } else {
                                alert(cartonCountRequiredMsg);
                            }
                            return;
                        }
                    }

                    // Prevent shipping or completion if counted by missing
                    if (newStatus === 'shipped' || newStatus === 'completed') {
                        var countByInput = document.getElementById('opsdesk_count_by_hidden')
                            || document.getElementById('opsdesk_count_by')
                            || document.querySelector('select[name="count_by"]');
                        var countByValue = countByInput ? String(countByInput.value || '') : '';
                        if (!hasCountBy && !countByValue) {
                            e.preventDefault();
                            if (typeof alert_float === 'function') {
                                alert_float('warning', countByRequiredMsg);
                            } else {
                                alert(countByRequiredMsg);
                            }
                            return;
                        }
                    }

                    // Prevent accepting order without packed_by
                    if (newStatus === 'in_progress') {
                        var packedByInput = form.querySelector('[name="packed_by"]');
                        if (!packedByInput || !packedByInput.value) {
                            e.preventDefault();
                            if (typeof alert_float === 'function') {
                                alert_float('warning', packedByRequiredMsg);
                            } else {
                                alert(packedByRequiredMsg);
                            }
                            return;
                        }
                    }
                }
            }, true);
      })();

      // Enable/disable the Accept button based on packer selection.
      (function () {
          'use strict';

          function syncAcceptButton(selectEl) {
              if (!selectEl || selectEl.tagName !== 'SELECT') {
                  return;
              }
              var form = selectEl.closest
                  ? selectEl.closest('.opsdesk-accept-form')
                  : null;
              if (!form) {
                  return;
              }
              var btn = form.querySelector('.opsdesk-accept-btn');
              if (!btn) {
                  return;
              }
              var val = '';
              if (typeof jQuery !== 'undefined') {
                  val = jQuery(selectEl).val();
              } else {
                  val = selectEl.value;
              }
              btn.disabled = !val;
          }

          function syncAllAcceptButtons() {
              var selects = document.querySelectorAll('select.opsdesk-accept-packer');
              for (var i = 0; i < selects.length; i++) {
                  syncAcceptButton(selects[i]);
              }
          }

          // Bind directly to each <select> so we don't pick up bootstrap-select's
          // wrapper <div>, and so we don't depend on event bubbling.
          if (typeof jQuery !== 'undefined') {
              jQuery(function () {
                  jQuery('select.opsdesk-accept-packer').each(function () {
                      var $sel = jQuery(this);
                      $sel.on('change changed.bs.select', function () {
                          syncAcceptButton(this);
                      });
                  });
              });
          }

          // Fallback: native change listener.
          document.addEventListener('change', function (e) {
              var packer = e.target && e.target.closest
                  ? e.target.closest('select.opsdesk-accept-packer')
                  : null;
              if (!packer) {
                  return;
              }
              syncAcceptButton(packer);
          }, true);

          // Safety net: keep button state correct even if events don't fire.
          setInterval(syncAllAcceptButtons, 200);

          // Initialize after bootstrap-select finishes its own setup.
          setTimeout(syncAllAcceptButtons, 300);
      })();

      (function () {
          'use strict';

          var galleryItems = [];
          var galleryIndex = 0;

          function guessPreviewType(url) {
              var clean = String(url || '').split('?')[0].toLowerCase();
              if (/\.(jpe?g|png|gif|bmp|webp)$/.test(clean)) {
                  return 'image';
              }
              if (/\.pdf$/.test(clean)) {
                  return 'pdf';
              }
              return 'other';
          }

          function parseGallery(raw) {
              if (!raw) {
                  return [];
              }
              try {
                  var parsed = JSON.parse(raw);
                  return Array.isArray(parsed) ? parsed : [];
              } catch (err) {
                  return [];
              }
          }

          function setGalleryControls() {
              var prev = document.getElementById('opsdesk_preview_prev');
              var next = document.getElementById('opsdesk_preview_next');
              var counter = document.getElementById('opsdesk_preview_counter');
              var showNav = galleryItems.length > 1;
              if (prev) { prev.classList.toggle('hide', !showNav); }
              if (next) { next.classList.toggle('hide', !showNav); }
              if (counter) {
                  counter.classList.toggle('hide', !showNav);
                  counter.textContent = showNav
                      ? (galleryIndex + 1) + ' / ' + galleryItems.length
                      : '';
              }
          }

          function renderPreview(url, type) {
              var imgWrap = document.getElementById('opsdesk_preview_image_wrap');
              var pdfWrap = document.getElementById('opsdesk_preview_pdf_wrap');
              var otherWrap = document.getElementById('opsdesk_preview_other_wrap');
              var img = document.getElementById('opsdesk_preview_image');
              var pdf = document.getElementById('opsdesk_preview_pdf');
              var download = document.getElementById('opsdesk_preview_download');
              if (!imgWrap || !pdfWrap || !otherWrap) {
                  return;
              }

              imgWrap.classList.add('hide');
              pdfWrap.classList.add('hide');
              otherWrap.classList.add('hide');
              if (img) { img.removeAttribute('src'); }
              if (pdf) { pdf.removeAttribute('src'); }

              if (type === 'image') {
                  if (img) { img.src = url; }
                  imgWrap.classList.remove('hide');
              } else if (type === 'pdf') {
                  if (pdf) { pdf.src = url; }
                  pdfWrap.classList.remove('hide');
              } else {
                  if (download) { download.href = url; }
                  otherWrap.classList.remove('hide');
              }
          }

          function showGalleryItem(index) {
              if (!galleryItems.length) {
                  return;
              }
              galleryIndex = (index + galleryItems.length) % galleryItems.length;
              var item = galleryItems[galleryIndex] || {};
              var url = item.url || '';
              var type = item.type || guessPreviewType(url);
              renderPreview(url, type);
              setGalleryControls();
          }

          function showFilePreview(url, type, gallery) {
              galleryItems = gallery && gallery.length ? gallery : [{ url: url, type: type }];
              galleryIndex = 0;
              showGalleryItem(0);
              if (typeof jQuery !== 'undefined') {
                  jQuery('#opsdesk_file_preview_modal').modal('show');
              }
          }

          document.addEventListener('click', function (e) {
              var link = e.target && e.target.closest
                  ? e.target.closest('a.opsdesk-file-link')
                  : null;
              if (!link) {
                  return;
              }
              var url = link.getAttribute('href') || '';
              if (!url || url === '#') {
                  return;
              }
              e.preventDefault();
              var type = link.getAttribute('data-opsdesk-type') || guessPreviewType(url);
              var gallery = parseGallery(link.getAttribute('data-opsdesk-gallery'));
              showFilePreview(url, type, gallery);
          }, true);

          var prevBtn = document.getElementById('opsdesk_preview_prev');
          var nextBtn = document.getElementById('opsdesk_preview_next');
          if (prevBtn) {
              prevBtn.addEventListener('click', function () {
                  showGalleryItem(galleryIndex - 1);
              });
          }
          if (nextBtn) {
              nextBtn.addEventListener('click', function () {
                  showGalleryItem(galleryIndex + 1);
              });
          }

          if (typeof jQuery !== 'undefined') {
              jQuery('#opsdesk_file_preview_modal').on('hidden.bs.modal', function () {
                  jQuery('#opsdesk_preview_image').removeAttr('src');
                  jQuery('#opsdesk_preview_pdf').removeAttr('src');
                  galleryItems = [];
                  galleryIndex = 0;
                  setGalleryControls();
              });
          }
      })();
  </script>
