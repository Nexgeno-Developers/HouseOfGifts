<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                    <?php echo _l('opsdesk_settings'); ?>
                </h4>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <ul class="nav nav-tabs" role="tablist">
                            <?php foreach ($tabs as $tab) { ?>
                            <li role="presentation" class="<?php echo $tab === $group ? 'active' : ''; ?>">
                                <a href="<?php echo admin_url('opsdesk/settings/' . $tab); ?>">
                                    <?php echo _l('opsdesk_' . $tab); ?>
                                </a>
                            </li>
                            <?php } ?>
                        </ul>
                        <div class="tab-content mtop15">
                            <div class="tab-pane active">
                                <?php $this->load->view($tab_view, ['group' => $group, 'product_statuses' => $product_statuses]); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
