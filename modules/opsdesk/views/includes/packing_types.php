<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div>
    <div class="_buttons">
        <?php if (opsdesk_can_manage_settings()) { ?>
        <a href="#" onclick="new_opsdesk_packing_type(); return false;" class="btn btn-info pull-left display-block">
            <?php echo _l('opsdesk_add_packing_type'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="clearfix"></div>
    <hr class="hr-panel-heading" />
    <div class="clearfix"></div>

    <table class="table dt-table border table-striped">
        <thead>
            <tr>
                <th><?php echo _l('opsdesk_display_order'); ?></th>
                <th><?php echo _l('opsdesk_packing_type_key'); ?></th>
                <th><?php echo _l('opsdesk_packing_type_name'); ?></th>
                <th><?php echo _l('opsdesk_packing_type_description'); ?></th>
                <th><?php echo _l('opsdesk_display'); ?></th>
                <th><?php echo _l('options'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packing_types as $type) { ?>
            <tr>
                <td><?php echo (int) $type['display_order']; ?></td>
                <td><?php echo e($type['type_key']); ?></td>
                <td><?php echo e($type['name']); ?></td>
                <td><?php echo e($type['description']); ?></td>
                <td><?php echo !empty($type['is_active']) ? _l('opsdesk_display') : _l('opsdesk_not_display'); ?></td>
                <td>
                    <?php if (opsdesk_can_manage_settings()) { ?>
                    <a href="#"
                        onclick="edit_opsdesk_packing_type(this, <?php echo (int) $type['id']; ?>); return false;"
                        data-type_key="<?php echo e($type['type_key']); ?>"
                        data-name="<?php echo e($type['name']); ?>"
                        data-description="<?php echo e($type['description']); ?>"
                        data-display_order="<?php echo (int) $type['display_order']; ?>"
                        data-is_active="<?php echo !empty($type['is_active']) ? '1' : '0'; ?>"
                        class="btn btn-default btn-icon">
                        <i class="fa-regular fa-pen-to-square"></i>
                    </a>
                    <a href="<?php echo admin_url('opsdesk/delete_packing_type/' . $type['id']); ?>" class="btn btn-danger btn-icon _delete">
                        <i class="fa fa-remove"></i>
                    </a>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

    <div class="modal fade" id="opsdesk_packing_type_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog setting-handsome-table">
            <?php echo form_open_multipart(admin_url('opsdesk/packing_type_setting'), ['id' => 'opsdesk_packing_type_setting']); ?>
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">
                        <span class="add-title"><?php echo _l('opsdesk_add_packing_type'); ?></span>
                        <span class="edit-title"><?php echo _l('opsdesk_edit_packing_type'); ?></span>
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div id="opsdesk_packing_type_id_t"></div>
                            <div class="form">
                                <div class="col-md-6">
                                    <?php echo render_input('type_key', 'opsdesk_packing_type_key'); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php echo render_input('name', 'opsdesk_packing_type_name'); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php echo render_input('display_order', 'opsdesk_display_order', '0', 'number', ['min' => '0']); ?>
                                </div>
                                <div class="col-md-12">
                                    <?php echo render_textarea('description', 'opsdesk_packing_type_description'); ?>
                                </div>
                                <div class="col-md-12">
                                    <input type="checkbox" name="is_active" checked>
                                    <label class="pt-2">
                                        <?php echo _l('opsdesk_display'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-info intext-btn"><?php echo _l('submit'); ?></button>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
var existing_opsdesk_packing_type_orders = <?php echo json_encode(array_map(function ($type) {
    return ['id' => (int) $type['id'], 'order' => (int) $type['display_order']];
}, $packing_types)); ?>;
var existing_opsdesk_packing_type_keys = <?php echo json_encode(array_map(function ($type) {
    return ['id' => (int) $type['id'], 'key' => (string) $type['type_key']];
}, $packing_types)); ?>;
var opsdeskPackingTypeLang = {
    displayOrderInUse: '<?php echo _l('opsdesk_packing_type_display_order_in_use'); ?>',
    keyInUse: '<?php echo _l('opsdesk_packing_type_key_in_use'); ?>'
};
</script>
