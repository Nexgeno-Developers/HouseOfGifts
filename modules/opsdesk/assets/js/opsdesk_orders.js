/**
 * OpsDesk — Order form: stock check, substitutions, submit guard.
 */
console.log('opsdesk_orders.js file loaded');

(function ($) {
  "use strict";

  console.log('opsdesk_orders.js IIFE executing, jQuery available:', typeof $ !== 'undefined');

  var debounceTimer = null;
  var overrides = {
    substitutions: {},
    removed: [],
    added: [],
    quantities: {},
  };
  var componentMeta = {};

  function escapeHtml(text) {
    if (text === null || text === undefined) {
      return "";
    }
    return $("<div/>").text(text).html();
  }

  function formatNumber(num) {
    return parseFloat(num).toLocaleString(undefined, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 4,
    });
  }

  function syncOverridesField() {
    $("#opsdesk_order_overrides").val(JSON.stringify(overrides));
  }

  function resetOverrides() {
    overrides = {
      substitutions: {},
      removed: [],
      added: [],
      quantities: {},
    };
    componentMeta = {};
    syncOverridesField();
  }

  function applyPrefill() {
    if (!opsdeskOrderPrefill) {
      return;
    }

    if (opsdeskOrderPrefill.substitutions) {
      overrides.substitutions = opsdeskOrderPrefill.substitutions;
    }
    if (opsdeskOrderPrefill.removed) {
      overrides.removed = opsdeskOrderPrefill.removed;
    }
    if (opsdeskOrderPrefill.added) {
      overrides.added = opsdeskOrderPrefill.added;
    }
    if (opsdeskOrderPrefill.quantities) {
      overrides.quantities = opsdeskOrderPrefill.quantities;
    }
    syncOverridesField();
  }

  function getCsrfPostData() {
    if (typeof csrfData !== "undefined" && csrfData.token_name && csrfData.hash) {
      var data = {};
      data[csrfData.token_name] = csrfData.hash;
      return data;
    }

    var tokenInput = $("input[type='hidden'][name*='csrf']").first();
    if (tokenInput.length) {
      var tokenName = tokenInput.attr('name');
      var data = {};
      data[tokenName] = tokenInput.val();
      return data;
    }

    return {};
  }

  function fetchStockCheck() {
    console.log('fetchStockCheck() START');
    var comboId = $("#opsdesk_order_combo_id").val();
    var qty = parseInt($("#opsdesk_order_qty").val(), 10) || 0;

    console.log('Combo ID:', comboId, 'Qty:', qty);

    if (!comboId || qty < 1) {
      console.log('Early exit: comboId or qty invalid');
      resetComponentsTable();
      updateSubmitState(false);
      return;
    }

    console.log('Validation passed, preparing AJAX request');
    syncOverridesField();
    $("#opsdesk_order_loading").removeClass("hide");
    $("#opsdesk_order_alert").addClass("hide");

    var postData = {
      combo_id: comboId,
      quantity: qty,
      order_overrides: JSON.stringify(overrides),
    };

    console.log('CSRF data:', getCsrfPostData());
    $.extend(postData, getCsrfPostData());

    console.log('POST data:', postData);
    console.log('AJAX URL:', opsdeskOrderStockUrl);

    $.post(opsdeskOrderStockUrl, postData)
      .done(function (response) {
        console.log('AJAX success response:', response);
        $("#opsdesk_order_loading").addClass("hide");

        if (typeof response === "string") {
          try {
            response = JSON.parse(response);
          } catch (e) {
            console.error('OpsDesk stock check JSON parse error:', e, response);
            showAlert("danger", opsdeskOrderLang.error);
            return;
          }
        }

        if (!response.success) {
          console.error('OpsDesk stock check failed:', response);
          showAlert("danger", response.message || opsdeskOrderLang.error);
          return;
        }

        console.log('Rendering components:', response.data);
        renderComponents(response.data);
      })
      .fail(function (xhr, status, error) {
        console.error('AJAX request failed');
        console.error('  Status:', status);
        console.error('  Error:', error);
        console.error('  XHR Status Code:', xhr.status);
        console.error('  XHR Response Text:', xhr.responseText);
        $("#opsdesk_order_loading").addClass("hide");
        showAlert("danger", opsdeskOrderLang.error);
      });
  }

  function debouncedStockCheck() {
    console.log('debouncedStockCheck() called');
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function() {
      console.log('debouncedStockCheck() timeout fired, calling fetchStockCheck()');
      fetchStockCheck();
    }, 400);
  }

  function resetComponentsTable() {
    $("#opsdesk_order_components_body").html(
      '<tr id="opsdesk_order_empty_row"><td colspan="6" class="text-center text-muted">' +
        escapeHtml($("#opsdesk_order_empty_row").text() || "") +
        "</td></tr>",
    );
    $("#opsdesk_order_summary").addClass("hide");
  }

  function showAlert(type, msg) {
    $("#opsdesk_order_alert")
      .removeClass("hide alert-success alert-danger")
      .addClass("alert-" + type)
      .text(msg);
  }

  function renderComponents(data) {
    console.log('renderComponents() called with data:', data);
    var components = data.components || [];
    var html = "";
    var insufficientCount = 0;

    componentMeta = {};

    if (components.length === 0) {
      html =
        '<tr><td colspan="6" class="text-center text-muted">—</td></tr>';
    } else {
      $.each(components, function (i, row) {
        var key = row.combo_item_id || "item_" + i;
        componentMeta[key] = row;

        if (!row.is_sufficient) {
          insufficientCount++;
        }

        var statusClass = row.is_sufficient ? "label-success" : "label-danger";
        var statusIcon = row.is_sufficient ? "fa-check" : "fa-times";
        var statusText = row.is_sufficient
          ? opsdeskOrderLang.sufficient
          : opsdeskOrderLang.insufficient;

        html += '<tr data-component-key="' + escapeHtml(String(key)) + '">';
        html += "<td>" + escapeHtml(row.sku) + "</td>";
        html += "<td>";
        html += escapeHtml(row.product_name);
        if (row.is_substitution) {
          html +=
            ' <span class="label label-warning">' +
            escapeHtml(opsdeskOrderLang.substitution) +
            "</span>";
        }
        html += "</td>";
        html +=
          '<td class="text-right">' + formatNumber(row.available_stock) + "</td>";
        html +=
          '<td class="text-right">' + formatNumber(row.required_quantity) + "</td>";
        html +=
          '<td class="text-center"><span class="label ' +
          statusClass +
          '"><i class="fa ' +
          statusIcon +
          '"></i> ' +
          statusText +
          "</span></td>";
        html += '<td class="text-center">';
        if (row.combo_item_id) {
          html +=
            '<button type="button" class="btn btn-xs btn-default opsdesk-sub-btn" data-combo-item-id="' +
            escapeHtml(String(row.combo_item_id)) +
            '">' +
            escapeHtml(opsdeskOrderLang.substitute) +
            "</button>";
        }
        html += "</td>";
        html += "</tr>";
      });
    }

    $("#opsdesk_order_components_body").html(html);

    var summary = $("#opsdesk_order_summary");
    summary.removeClass("hide alert-success alert-danger");

    if (data.is_fulfillable) {
      summary
        .addClass("alert-success")
        .html(
          '<i class="fa fa-check-circle"></i> ' + opsdeskOrderLang.allSufficient,
        );
      updateSubmitState(true);
    } else {
      summary
        .addClass("alert-danger")
        .html(
          '<i class="fa fa-exclamation-circle"></i> ' +
            opsdeskOrderLang.componentsInsufficient.replace(
              "%s",
              insufficientCount,
            ),
        );
      updateSubmitState(false);
    }
  }

  function updateSubmitState(enabled) {
    var packing = $("#opsdesk_packing_type").val();
    $("#opsdesk_submit_order").prop(
      "disabled",
      !(enabled && packing),
    );
  }

  function openSubstituteModal(comboItemId) {
    $("#opsdesk_sub_combo_item_id").val(comboItemId);
    $("#opsdesk_sub_product_id").val("").selectpicker("refresh");
    $("#opsdesk_substitute_modal").modal("show");
  }

  function applySubstitute() {
    var comboItemId = $("#opsdesk_sub_combo_item_id").val();
    var productId = $("#opsdesk_sub_product_id").val();

    if (!comboItemId || !productId) {
      return;
    }

    overrides.substitutions[String(comboItemId)] = parseInt(productId, 10);
    syncOverridesField();
    $("#opsdesk_substitute_modal").modal("hide");
    fetchStockCheck();
  }

  $(function () {
    console.log('OpsDesk order form initializing...');
    console.log('opsdeskOrderStockUrl:', opsdeskOrderStockUrl);
    console.log('opsdeskOrderLang:', opsdeskOrderLang);

    var $comboSelect = $("#opsdesk_order_combo_id");
    console.log('Combo select element found:', $comboSelect.length);
    console.log('Combo select element:', $comboSelect);

    applyPrefill();

    if ($("#opsdesk_order_combo_id").val()) {
      console.log('Pre-filled combo ID detected, fetching stock...');
      fetchStockCheck();
    }

    $("#opsdesk_order_combo_id").on("change changed.bs.select", function () {
      console.log('Combo selection changed event fired');
      resetOverrides();
      debouncedStockCheck();
    });

    // Direct jQuery change handler as fallback
    $comboSelect.on("change", function () {
      console.log('Direct change handler: combo changed to', $(this).val());
    });

    // Bootstrap-select event handler
    if ($comboSelect.data('selectpicker')) {
      $comboSelect.on('changed.bs.select', function () {
        console.log('Bootstrap-select changed event');
      });
    }

    $("#opsdesk_order_qty").on("input change", debouncedStockCheck);
    $("#opsdesk_packing_type").on("change", function () {
      var stockOk = $("#opsdesk_order_summary").hasClass("alert-success");
      updateSubmitState(stockOk);
    });

    $(document).on("click", ".opsdesk-sub-btn", function () {
      openSubstituteModal($(this).data("combo-item-id"));
    });

    $("#opsdesk_apply_substitute").on("click", applySubstitute);

    $("#opsdesk_order_form").on("submit", function () {
      syncOverridesField();
      if ($("#opsdesk_submit_order").prop("disabled")) {
        return false;
      }
      return true;
    });

    console.log('OpsDesk order form initialization complete');
  });
})(jQuery);
