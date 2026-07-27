<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/order_detail.css'); ?>">
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
                        <?php echo opsdesk_get_priority_badge($order->priority); ?>
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
                                        <th><?php echo _l('opsdesk_transport_medium'); ?></th>
                                        <td><?php echo e(opsdesk_get_transport_medium_label($order->transport_medium_id ?? '')); ?></td>
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

                                <!-- Panel 1: Status & Actions -->
                                <div class="panel_s mtop15">
                                    <div class="panel-body">
                                        <h5 class="no-margin font-bold"><?php echo _l('opsdesk_status_actions'); ?></h5>
                                        <hr class="hr-panel-heading" />
                                        
                                        <div class="row-margin-bottom">
                                            <span class="label <?php echo opsdesk_get_order_status_class($order->status); ?>">
                                                <?php echo e(opsdesk_get_order_status_label($order->status)); ?>
                                            </span>
                                            <?php echo opsdesk_get_priority_badge($order->priority); ?>
                                        </div>

                                        <?php if (!empty($can_edit)) { ?>
                                        <?php if ($order->status === 'pending') { ?>
                                        <?php echo form_open(admin_url('opsdesk/update_order_status'), ['class' => 'opsdesk-accept-form row-margin-bottom']); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">
                                        <input type="hidden" name="status" value="in_progress">
                                        <div class="form-group row-margin-bottom">
                                            <label><?php echo _l('opsdesk_assigned_to'); ?></label>
                                            <select name="packed_by" class="selectpicker opsdesk-accept-packer" data-width="100%" required>
                                                <option value=""><?php echo _l('opsdesk_unassigned'); ?></option>
                                                <?php foreach ($staff_members as $sm) { ?>
                                                <option value="<?php echo (int) $sm['staffid']; ?>"><?php echo e($sm['full_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm button-margin-r-b">
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
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order->id; ?>">
                                                <select name="status" class="selectpicker" data-width="auto" onchange="if (this.value) this.form.submit();">
                                                    <option value=""><?php echo _l('opsdesk_update_status'); ?></option>
                                                    <?php foreach ($next_statuses as $status_key) {
                                                        if ($order->status === $status_key) { continue; }
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
                                        <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order->id); ?>"
                                            class="btn btn-danger _delete button-margin-r-b"
                                            data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                            <?php echo _l('opsdesk_cancel_order'); ?>
                                        </a>
                                        <?php } ?>
                                        <?php } else { ?>
                                        <div class="text-muted">
                                            <?php if (!empty($can_cancel_own) || !empty($can_cancel_any)) { ?>
                                            <a href="<?php echo admin_url('opsdesk/cancel_order/' . $order->id); ?>"
                                                class="btn btn-danger _delete button-margin-r-b"
                                                data-message="<?php echo e(_l('opsdesk_cancel_order_confirm')); ?>">
                                                <?php echo _l('opsdesk_cancel_order'); ?>
                                            </a>
                                            <?php } ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- Panel 2: Assignment -->
                                <div class="panel_s mtop15">
                                    <div class="panel-body">
                                        <h5 class="no-margin font-bold"><?php echo _l('opsdesk_assignment'); ?></h5>
                                        <hr class="hr-panel-heading" />

                                        <?php if (!empty($can_edit) && $order->status !== 'pending') { ?>
                                        <?php echo form_open(admin_url('opsdesk/assign_order/' . $order->id), ['id' => 'opsdesk_assign_form', 'class' => 'row-margin-bottom']); ?>
                                        <div class="form-group row-margin-bottom">
                                            <label><?php echo _l('opsdesk_assigned_to'); ?></label>
                                            <select name="packed_by" id="opsdesk_packed_by" class="selectpicker" data-width="100%">
                                                <option value=""><?php echo _l('opsdesk_unassigned'); ?></option>
                                                <?php foreach ($staff_members as $sm) { ?>
                                                <option value="<?php echo (int) $sm['staffid']; ?>"
                                                    <?php echo (!empty($order->packed_by) && (int) $order->packed_by === (int) $sm['staffid']) ? 'selected' : ''; ?>>
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
                                            <strong><?php echo _l('opsdesk_assigned_to'); ?>:</strong>
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
                                                    <?php echo (!empty($order->count_by) && (int) $order->count_by === (int) $sm['staffid']) ? 'selected' : ''; ?>>
                                                    <?php echo e($sm['full_name']); ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                            <?php } else { ?>
                                            <p><?php echo !empty($order->count_by) ? e(get_staff_full_name($order->count_by)) : '—'; ?></p>
                                            <?php } ?>
                                        </div>

                                        <div class="form-group row-margin-bottom">
                                            <label for="opsdesk_carton_count"><?php echo _l('opsdesk_carton_count'); ?></label>
                                            <?php if (!empty($can_edit)) { ?>
                                            <input type="number" name="carton_count" id="opsdesk_carton_count"
                                                class="form-control"
                                                min="1"
                                                value="<?php echo (int) ($order->carton_count ?? 0); ?>"
                                                placeholder="<?php echo _l('opsdesk_carton_count_placeholder'); ?>">
                                            <?php } else { ?>
                                            <p><?php echo !empty($order->carton_count) ? (int) $order->carton_count : '—'; ?></p>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Panel 3: Completion Documents -->
                                <div class="panel_s mtop15">
                                    <div class="panel-body">
                                        <h5 class="no-margin font-bold"><?php echo _l('opsdesk_completion_documents'); ?></h5>
                                        <hr class="hr-panel-heading" />

                                        <div class="form-group row-margin-bottom">
                                            <label><?php echo _l('opsdesk_lr_copy_upload'); ?></label>
                                            <?php if (!empty($order->lr_copy)) { ?>
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order->lr_copy)); ?>" target="_blank">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                            <?php if (!empty($can_edit)) { ?>
                                            <div class="mtop10">
                                                <input type="file" name="lr_copy" id="opsdesk_lr_copy"
                                                    class="form-control"
                                                    accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                            </div>
                                            <?php } ?>
                                        </div>

                                        <div class="form-group row-margin-bottom">
                                            <label><?php echo _l('opsdesk_carton_photo_upload'); ?></label>
                                            <?php if (!empty($order->carton_photo)) { ?>
                                            <a class="opsdesk-file-link" href="<?php echo e(opsdesk_file_url($order->carton_photo)); ?>" target="_blank">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } else { ?>
                                            <span class="text-muted">—</span>
                                            <?php } ?>
                                            <?php if (!empty($can_edit)) { ?>
                                            <div class="mtop10">
                                                <input type="file" name="carton_photo" id="opsdesk_carton_photo"
                                                    class="form-control"
                                                    accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Panel 4: Files -->
                                <div class="panel_s mtop15">
                                    <div class="panel-body">
                                        <h5 class="no-margin font-bold"><?php echo _l('opsdesk_files'); ?></h5>
                                        <hr class="hr-panel-heading" />

                                        <?php if (!empty($order->bill_file)) { ?>
                                        <div class="mbot10 row-margin-bottom">
                                            <span class="label label-primary"><?php echo _l('opsdesk_bill_upload'); ?></span>
                                            <a class="opsdesk-file-link mleft10" href="<?php echo e(opsdesk_file_url($order->bill_file)); ?>" target="_blank">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                        </div>
                                        <?php } ?>

                                        <div class="mbot10 row-margin-bottom">
                                            <span class="label label-<?php echo !empty($order->payment_file) ? 'success' : 'danger'; ?>">
                                                <?php echo _l('opsdesk_payment_upload'); ?>
                                            </span>
                                            <?php if (!empty($order->payment_file)) { ?>
                                            <a class="opsdesk-file-link mleft10" href="<?php echo e(opsdesk_file_url($order->payment_file)); ?>" target="_blank">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('opsdesk_view_file'); ?>
                                            </a>
                                            <?php } ?>
                                        </div>

                                        <?php if (!empty($can_edit)) { ?>
                                        <?php echo form_open_multipart(admin_url('opsdesk/upload_payment_file/' . $order->id), ['id' => 'opsdesk_payment_upload_form', 'class' => 'row-margin-bottom']); ?>
                                        <div class="mtop10">
                                            <input type="file" name="payment_file" id="opsdesk_payment_file"
                                                class="form-control"
                                                accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                            <button type="submit" class="btn btn-success btn-sm mtop5 button-margin-r-b">
                                                <i class="fa fa-upload"></i> <?php echo _l('opsdesk_upload_payment'); ?>
                                            </button>
                                        </div>
                                        <?php echo form_close(); ?>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- Panel 5: Priority -->
                                <?php if (!empty($can_edit)) { ?>
                                <div class="panel_s mtop15">
                                    <div class="panel-body">
                                        <h5 class="no-margin font-bold"><?php echo _l('opsdesk_priority'); ?></h5>
                                        <hr class="hr-panel-heading" />

                                        <div class="opsdesk-priority-block row-margin-bottom">
                                            <button type="button" class="btn btn-default btn-sm button-margin-r-b" id="opsdesk_change_priority_btn">
                                                <i class="fa fa-flag"></i> <?php echo _l('opsdesk_change_priority'); ?>
                                            </button>
                                            <div id="opsdesk_priority_inline" class="hide mtop10">
                                                <div class="radio radio-primary radio-inline mright15 row-margin-bottom">
                                                    <input type="radio" name="opsdesk_priority_inline" id="opsdesk_p_inline_normal" value="0"
                                                        <?php echo (int) $order->priority === 0 ? 'checked' : ''; ?>>
                                                    <label for="opsdesk_p_inline_normal"><?php echo _l('opsdesk_priority_normal'); ?></label>
                                                </div>
                                                <div class="radio radio-danger radio-inline mright15 row-margin-bottom">
                                                    <input type="radio" name="opsdesk_priority_inline" id="opsdesk_p_inline_high" value="1"
                                                        <?php echo (int) $order->priority === 1 ? 'checked' : ''; ?>>
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
                                    </div>
                                </div>
                                <?php } ?>
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
                                <?php if (!empty($log['notes'])) { ?>
                                <span class="text-muted mleft5">— <?php echo e($log['notes']); ?></span>
                                <?php } ?>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="opsdesk_order_id" value="<?php echo (int) $order->id; ?>">

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
           var hasPaymentFile = <?php echo !empty($order->payment_file) ? 'true' : 'false'; ?>;
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
  </script>