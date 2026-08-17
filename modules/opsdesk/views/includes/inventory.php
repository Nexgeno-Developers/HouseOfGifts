<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div>
    <p class="text-muted">
        <?php echo _l('opsdesk_inventory_settings_help'); ?>
    </p>
    <hr class="hr-panel-heading" />

    <?php if (opsdesk_can_manage_settings()) { ?>
    <?php echo form_open(admin_url('opsdesk/save_inventory_settings')); ?>
    <div class="form-group">
        <label for="opsdesk_bypass_stock_check" class="control-label">
            <?php echo _l('opsdesk_bypass_stock_check'); ?>
        </label>
        <?php // Submitted when the switch is off, so the value always reaches the controller. ?>
        <input type="hidden" name="opsdesk_bypass_stock_check" value="0">
        <div class="onoffswitch">
            <input type="checkbox" name="opsdesk_bypass_stock_check" class="onoffswitch-checkbox"
                id="opsdesk_bypass_stock_check" value="1"
                <?php echo !empty($bypass_stock_check) ? 'checked' : ''; ?>>
            <label class="onoffswitch-label" for="opsdesk_bypass_stock_check"></label>
        </div>
        <p class="text-muted mtop10">
            <?php echo _l('opsdesk_bypass_stock_check_help'); ?>
        </p>
    </div>
    <button type="submit" class="btn btn-primary">
        <?php echo _l('submit'); ?>
    </button>
    <?php echo form_close(); ?>
    <?php } else { ?>
    <p>
        <?php echo !empty($bypass_stock_check)
            ? _l('opsdesk_bypass_stock_check_on')
            : _l('opsdesk_bypass_stock_check_off'); ?>
    </p>
    <?php } ?>
</div>
