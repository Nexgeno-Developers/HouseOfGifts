<?php // opsdesk order detail view - rebuilt
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/order_detail.css'); ?>">
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
                $statuses = opsdesk_get_order_statuses(true);
                $current_status = $order['status'];
                $status_keys = array_column($statuses, 'status_key');
                $current_index = array_search($current_status, $status_keys, true);
                if ($current_index === false) {
                    $current_index = 0;
                }
                $is_cancelled = $current_status === 'cancelled';
                ?>
                <div class="opsdesk-stepper tw-mb-4">
                    <?php foreach ($statuses as $index => $status) {
                        $is_completed = $index < $current_index;
                        $is_current = $index === $current_index;
                        $step_class = 'stepper-step';
                        if ($is_completed) {
                            $step_class .= ' stepper-completed';
                        } elseif ($is_current && !$is_cancelled) {
                            $step_class .= ' stepper-current';
                        }
                        if ($is_cancelled && $status['status_key'] !== 'cancelled') {
                            $step_class .= ' stepper-skipped';
                        }
                    ?>
                    <div class="<?php echo $step_class; ?>">
                        <div class="stepper-icon">
                            <?php if ($is_completed): ?>
                                <i class="fa fa-check"></i>
                            <?php elseif ($is_current && !$is_cancelled): ?>
                                <span class="stepper-current-dot"></span>
                            <?php else: ?>
                                <span class="stepper-number"><?php echo $index + 1; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="stepper-label"><?php echo e($status['name']); ?></div>
                        <?php if ($index < count($statuses) - 1): ?>
                        <div class="stepper-line"></div>
                        <?php endif; ?>
                    </div>
                    <?php } ?>
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
                                <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                    <li role="presentation" class="active">
                                        <a href="#opsdesk-tab-status" aria-controls="opsdesk-tab-status" role="tab" data-toggle="tab">
                                            <?php echo _l('opsdesk_status'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#opsdesk-tab-assign" aria-controls="opsdesk-tab-assign" role="tab" data-toggle="tab">
                                            <?php echo _l('opsdesk_assignment'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#opsdesk-tab-priority" aria-controls="opsdesk-tab-priority" role="tab" data-toggle="tab">
                                            <?php echo _l('opsdesk_priority'); ?>
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content tw-mt-3">
                                    <!-- Tab 1: Status Advance & Acceptance -->
                                    <div role="tabpanel" class="tab-pane active" id="opsdesk-tab-status">
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
                                    <div role="tabpanel" class="tab-pane" id="opsdesk-tab-assign">
                                        <?php if (!empty($can_edit) && $order['status'] !== 'pending') { ?>
                                        <?php echo form_open(admin_url('opsdesk/assign_order/' . $order['id']), ['id' => 'opsdesk_assign_form', 'class' => 'row-margin-bottom']); ?>
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
                                        <button type="submit" class="btn btn-primary btn-sm button-margin-r-b" id="opsdesk_assign_btn">
                                            <?php echo _l('opsdesk_assign'); ?>
                                        </button>
                                        <?php echo form_close(); ?>
                                        <?php } else { ?>
                                        <div class="row-margin-bottom">
                                            <strong><?php echo _l('opsdesk_packed_by'); ?>:</strong>
                                            <span class="mleft10"><?php echo $packed_by_name ? e($packed_by_name) : '—'; ?></span>
                                        </div>
                                        <?php } ?>

                                        <div class="form-group row-margin-bottom">
                                            <label><?php echo _l('opsdesk_count_by'); ?></label>
                                            <?php if (!empty($can_edit)) { ?>
                                            <select name="count_by" id="opsdesk_count_by" class="selectpicker" data-width="100%">
                                                <option value=""><?php echo _l('opsdesk_unassigned'); ?></option>
                                                <?php foreach ($staff_members as $sm) { ?>
                                                <option value="<?php echo (int) $sm['staffid']; ?>"
                                                    <?php echo (!empty($order['count_by']) && (int) $order['count_by'] === (int) $sm['staffid']) ? 'selected' : ''; ?>>
                                                    <?php echo e($sm['full_name']); ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                            <?php } else { ?>
                                            <p><?php echo !empty($order['count_by']) ? e(get_staff_full_name($order['count_by'])) : '—'; ?></p>
                                            <?php } ?>
                                        </div>

                                        <div class="form-group row-margin-bottom">
                                            <label for="opsdesk_carton_count"><?php echo _l('opsdesk_carton_count'); ?></label>
                                            <?php if (!empty($can_edit)) { ?>
                                            <input type="number" name="carton_count" id="opsdesk_carton_count"
                                                class="form-control"
                                                min="1"
                                                value="<?php echo (int) ($order['carton_count'] ?? 0); ?>"
                                                placeholder="<?php echo _l('opsdesk_carton_count_placeholder'); ?>">
                                            <?php } else { ?>
                                            <p><?php echo !empty($order['carton_count']) ? (int) $order['carton_count'] : '—'; ?></p>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <!-- Tab 3: Priority Flag Toggle -->
                                    <div role="tabpanel" class="tab-pane" id="opsdesk-tab-priority">
                                        <?php if (!empty($can_edit)) { ?>
                                        <div class="opsdesk-priority-block">
                                            <button type="button" class="btn btn-default btn-sm button-margin-r-b" id="opsdesk_change_priority_btn">
                                                <i class="fa fa-flag"></i> <?php echo _l('opsdesk_change_priority'); ?>
                                            </button>
                                            <div id="opsdesk_priority_inline" class="hide mtop10">
                                                <div class="radio radio-primary radio-inline mright15 row-margin-bottom">
                                                    <input type="radio" name="opsdesk_priority_inline" id="opsdesk_p_inline_normal" value="0"
                                                        <?php echo (int) $order['priority'] === 0 ? 'checked' : ''; ?>>
                                                    <label for="opsdesk_p_inline_normal"><?php echo _l('opsdesk_priority_normal'); ?></label>
                                                </div>
                                                <div class="radio radio-danger radio-inline mright15 row-margin-bottom">
                                                    <input type="radio" name="opsdesk_priority_inline" id="opsdesk_p_inline_high" value="1"
                                                        <?php echo (int) $order['priority'] === 1 ? 'checked' : ''; ?>>
                                                    <label for="opsdesk_p_inline_high"><?php echo _l('opsdesk_priority_high'); ?></label>
                                                </div>
                                                <button type="button" class="btn btn-primary btn-sm button-margin-r-b" id="opsdesk_priority_save_btn">
                                                    <?php echo _l('submit'); ?>
                                                </button>
                                                <button type="button" class="btn btn-default btn-sm button-margin-r-b" id="opsdesk_priority_cancel_btn">
                                                    <?php echo _l('cancel'); ?>
                                                </button>
                                            </div>
                                        </div>
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
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order['bill_file'])); ?>" target="_blank">
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
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order['payment_file'])); ?>" target="_blank">
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
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order['lr_copy'])); ?>" target="_blank">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                        </div>
                                        <div class="mtop10">
                                            <input type="file" name="lr_copy" id="opsdesk_lr_copy"
                                                class="form-control"
                                                accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                        </div>
                                    </div>

                                    <!-- Carton Photo -->
                                    <div class="opsdesk-attachment-item">
                                        <div class="tw-flex tw-justify-between tw-items-center">
                                            <span class="label label-warning"><?php echo _l('opsdesk_carton_photo_upload'); ?></span>
                                            <?php if (!empty($order['carton_photo'])) { ?>
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order['carton_photo'])); ?>" target="_blank">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                        </div>
                                        <div class="mtop10">
                                            <input type="file" name="carton_photo" id="opsdesk_carton_photo"
                                                class="form-control"
                                                accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.doc,.docx,.xls,.xlsx,.txt,.csv">
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
</div>
<input type="hidden" id="opsdesk_order_id" value="<?php echo (int) $order['id']; ?>">

<?php init_tail(); ?>

<script>
    // Globals expected by opsdesk_orders.js (form IIFE is guarded by their
    // absence, but they must be declared to avoid ReferenceErrors on load).
    var opsdeskOrderStockUrl = '';
    var opsdeskProductDetailsUrl = '';
    var opsdeskClientsUrl = '';
    var opsdeskOrderPrefill = {};
    var opsdeskOrderLang = {};
    // Base admin URL used by the priority-change IIFE (no global admin_url() in JS).
    var opsdeskOrderBaseUrl = '<?php echo admin_url('opsdesk/'); ?>';
</script>

<script src="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/js/opsdesk_orders.js'); ?>"></script>

<script>
      (function () {
          'use strict';

var paymentRequiredMsg = <?php echo json_encode(_l('opsdesk_payment_required_for_completion')); ?>;
           var hasPaymentFile = <?php echo !empty($order['payment_file']) ? 'true' : 'false'; ?>;
           var lrCopyRequiredMsg = <?php echo json_encode(_l('opsdesk_lr_copy_required_for_completion')); ?>;
           var cartonPhotoRequiredMsg = <?php echo json_encode(_l('opsdesk_carton_photo_required_for_completion')); ?>;
           var cartonCountRequiredMsg = <?php echo json_encode(_l('opsdesk_carton_count_required_for_completion')); ?>;
           var packedByRequiredMsg = <?php echo json_encode(_l('opsdesk_packed_by_required')); ?>;
           var countByRequiredMsg = <?php echo json_encode(_l('opsdesk_count_by_required_for_completion')); ?>;

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
                    if (newStatus === 'completed' && hasPaymentFile) {
                        var lrCopyInput = document.querySelector('input[name="lr_copy"]');
                        if (!lrCopyInput || !lrCopyInput.value) {
                            e.preventDefault();
                            if (typeof alert_float === 'function') {
                                alert_float('warning', lrCopyRequiredMsg);
                            } else {
                                alert(lrCopyRequiredMsg);
                            }
                            return;
                        }
                    }

                    // Prevent completion if carton photo missing
                    if (newStatus === 'completed' && hasPaymentFile) {
                        var cartonPhotoInput = document.querySelector('input[name="carton_photo"]');
                        if (!cartonPhotoInput || !cartonPhotoInput.value) {
                            e.preventDefault();
                            if (typeof alert_float === 'function') {
                                alert_float('warning', cartonPhotoRequiredMsg);
                            } else {
                                alert(cartonPhotoRequiredMsg);
                            }
                            return;
                        }
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

                    // Prevent completion if counted by missing
                    if (newStatus === 'completed' && hasPaymentFile) {
                        var countByInput = document.querySelector('select[name="count_by"]');
                        if (!countByInput || !countByInput.value) {
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
  </script>
