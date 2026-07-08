/**
 * OpsDesk — Settings: product / order status management.
 * The existing-status data globals (existing_opsdesk_status_orders / _keys)
 * are emitted inline by the product_statuses view partial.
 */
(function ($) {
    "use strict";

    function new_opsdesk_product_status() {
        $("#opsdesk_product_status_modal").modal("show");
        $("#opsdesk_product_status_setting").trigger("reset");
        $("#opsdesk_product_status_id_t").html("");
        $(".edit-title").hide();
        $(".add-title").show();
        $('#opsdesk_product_status_modal input[name="is_active"]').prop("checked", true);
    }

    function edit_opsdesk_product_status(invoker, id) {
        $("#opsdesk_product_status_id_t").html('<input type="hidden" name="id" value="' + id + '">');
        $("#opsdesk_product_status_modal input[name=\"status_key\"]").val($(invoker).data("status_key"));
        $("#opsdesk_product_status_modal input[name=\"name\"]").val($(invoker).data("name"));
        $("#opsdesk_product_status_modal textarea[name=\"description\"]").val($(invoker).data("description"));
        $("#opsdesk_product_status_modal input[name=\"display_order\"]").val($(invoker).data("display_order"));
        $("#opsdesk_product_status_modal input[name=\"is_active\"]").prop("checked", $(invoker).data("is_active") == "1");
        $(".add-title").hide();
        $(".edit-title").show();
        $("#opsdesk_product_status_modal").modal("show");
    }

    function bindProductStatusForm() {
        $("#opsdesk_product_status_setting").on("submit", function (e) {
            var orderValue = parseInt($('#opsdesk_product_status_modal input[name="display_order"]').val(), 10);
            var currentId = parseInt($("#opsdesk_product_status_id_t input[name=\"id\"]").val(), 10) || 0;
            var keyValue = ($('#opsdesk_product_status_modal input[name="status_key"]').val() || "").trim();

            if (!isNaN(orderValue)) {
                var alreadyUsedOrder = existing_opsdesk_status_orders.some(function (item) {
                    return item.order === orderValue && item.id !== currentId;
                });

                if (alreadyUsedOrder) {
                    e.preventDefault();
                    alert(opsdeskSettingsLang.displayOrderInUse);
                    return false;
                }
            }

            var alreadyUsedKey = existing_opsdesk_status_keys.some(function (item) {
                return item.key.toLowerCase() === keyValue.toLowerCase() && item.id !== currentId;
            });

            if (keyValue && alreadyUsedKey) {
                e.preventDefault();
                alert(opsdeskSettingsLang.keyInUse);
                return false;
            }
        });
    }

    window.new_opsdesk_product_status = new_opsdesk_product_status;
    window.edit_opsdesk_product_status = edit_opsdesk_product_status;

    $(function () {
        bindProductStatusForm();
    });
})(jQuery);
