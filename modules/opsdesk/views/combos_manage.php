<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url(OPSDESK_MODULE_NAME, 'assets/css/opsdesk.css'); ?>">
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if (staff_can('create', 'opsdesk') || has_permission('opsdesk', '', 'create')) { ?>
                <div class="tw-mb-2">
                    <a href="<?php echo admin_url('opsdesk/combo'); ?>" class="btn btn-primary">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php echo _l('opsdesk_new_combo'); ?>
                    </a>
                </div>
                <?php } ?>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <table class="table table-striped dt-table">
                            <thead>
                                <tr>
                                    <th><?php echo _l('opsdesk_combo_name'); ?></th>
                                    <th><?php echo _l('opsdesk_combo_image'); ?></th>
                                    <th><?php echo _l('opsdesk_description'); ?></th>
                                    <th><?php echo _l('opsdesk_status'); ?></th>
                                    <th><?php echo _l('options'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($combos as $combo) { ?>
                                <tr>
                                    <td><?php echo e($combo['name']); ?></td>
                                    <td>
                                        <?php if (!empty($combo['image'])) { ?>
                                        <img src="<?php echo e(opsdesk_file_url($combo['image'])); ?>" alt=""
                                            class="img-thumbnail" style="max-width:48px;max-height:48px;">
                                        <?php } else { ?>
                                        <span class="text-muted">—</span>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo e($combo['description']); ?></td>
                                    <td>
                                        <?php if ((int) $combo['status'] === 1) { ?>
                                        <span class="label label-success"><?php echo _l('opsdesk_active'); ?></span>
                                        <?php } else { ?>
                                        <span class="label label-default"><?php echo _l('opsdesk_inactive'); ?></span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if (staff_can('edit', 'opsdesk') || has_permission('opsdesk', '', 'edit')) { ?>
                                        <a href="<?php echo admin_url('opsdesk/combo/' . $combo['id']); ?>"
                                            class="btn btn-default btn-icon">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <?php } ?>
                                        <a href="<?php echo admin_url('opsdesk/combo_inventory/' . $combo['id']); ?>"
                                            class="btn btn-default btn-icon" title="<?php echo _l('opsdesk_view_inventory'); ?>">
                                            <i class="fa fa-cubes"></i>
                                        </a>
                                        <?php if (staff_can('delete', 'opsdesk') || has_permission('opsdesk', '', 'delete')) { ?>
                                        <a href="<?php echo admin_url('opsdesk/delete_combo/' . $combo['id']); ?>"
                                            class="btn btn-danger btn-icon _delete">
                                            <i class="fa fa-remove"></i>
                                        </a>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php if (empty($combos)) { ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        <?php echo _l('opsdesk_no_combos'); ?>
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
<?php init_tail(); ?>
</body>
</html>
