/**
 * OpsDesk — Order form: stock check, substitutions, submit guard.
 */

// Shared helper (global scope) so every IIFE in this file can read the CSRF
// token. Defined once here; do not redeclare inside an IIFE.
function getCsrfPostData() {
  if (typeof csrfData !== "undefined" && csrfData.token_name && csrfData.hash) {
    var data = {};
    data[csrfData.token_name] = csrfData.hash;
    return data;
  }

  var tokenInput = jQuery("input[type='hidden'][name*='csrf']").first();
  if (tokenInput.length) {
    var tokenName = tokenInput.attr('name');
    var data = {};
    data[tokenName] = tokenInput.val();
    return data;
  }

  return {};
}

(function ($) {
   "use strict";

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

  function fetchStockCheck() {
    var comboId = $("#opsdesk_order_combo_id").val();
    var qty = parseFloat($("#opsdesk_order_qty").val()) || 0;

    if (!comboId || qty < 1) {
      resetComponentsTable();
      updateSubmitState(false);
      return;
    }

    syncOverridesField();
    $("#opsdesk_order_loading").removeClass("hide");
    $("#opsdesk_order_alert").addClass("hide");

    var postData = {
      combo_id: comboId,
      quantity: qty,
      order_overrides: JSON.stringify(overrides),
    };

    $.extend(postData, getCsrfPostData());

    $.post(opsdeskOrderStockUrl, postData)
      .done(function (response) {
        $("#opsdesk_order_loading").addClass("hide");

        if (typeof response === "string") {
          try {
            response = JSON.parse(response);
          } catch (e) {
            showAlert("danger", opsdeskOrderLang.error);
            return;
          }
        }

        if (!response.success) {
          showAlert("danger", response.message || opsdeskOrderLang.error);
          return;
        }

        renderComponents(response.data);
      })
      .fail(function (xhr, status, error) {
        $("#opsdesk_order_loading").addClass("hide");
        showAlert("danger", opsdeskOrderLang.error);
      });
  }

  function debouncedStockCheck() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function() {
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
    var transportMedium = $("#opsdesk_transport_medium").val();
    $("#opsdesk_submit_order").prop(
      "disabled",
      !(enabled && packing && transportMedium),
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

  function initCustomerSearch() {
    var $select = $("#opsdesk_customer_id");
    var $city = $("#opsdesk_customer_city");
    var prefillId = (opsdeskOrderPrefill && opsdeskOrderPrefill.customer_id)
      ? String(opsdeskOrderPrefill.customer_id)
      : "";
    var searchTimer = null;

    function loadOptions(q) {
      var post = { q: q || "" };
      if (typeof csrfData !== "undefined" && csrfData.token_name) {
        post[csrfData.token_name] = csrfData.hash;
      }
      $.post(opsdeskClientsUrl, post).done(function (resp) {
        if (typeof resp === "string") {
          try { resp = JSON.parse(resp); } catch (e) { return; }
        }
        if (!resp.success || !resp.clients) {
          return;
        }
        var html = '<option value=""></option>';
        $.each(resp.clients, function (i, c) {
          var sel = (String(c.id) === prefillId) ? " selected" : "";
          var city = c.city ? " — " + escapeHtml(c.city) : "";
          html += '<option value="' + c.id + '" data-city="' + escapeHtml(c.city || "") + '"' + sel + ">" +
            escapeHtml(c.company) + city + "</option>";
        });
        $select.html(html);
        $select.selectpicker("refresh");
        syncCityFromSelect();
        var $s = $select.next(".bootstrap-select").find(".bs-searchbox input");
        if ($s.length) {
          $s.focus();
        }
      });
    }

    function syncCityFromSelect() {
      var $opt = $select.find("option:selected");
      $city.val($opt.data("city") || "");
    }

    // Initial population so the dropdown has data on first open
    loadOptions("");

    // Remote search while typing in the selectpicker live-search box
    $select.on("shown.bs.select", function () {
      var $search = $(this).next(".bootstrap-select").find(".bs-searchbox input");
      $search.off("keyup.opsCust").on("keyup.opsCust", function () {
        var q = $.trim($(this).val());
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
          loadOptions(q);
        }, 350);
      });
    });

    $select.on("changed.bs.select", function () {
      syncCityFromSelect();
    });
  }

  $(function () {
    // This IIFE is only for the order *form* page. The order *detail* page
    // reuses this file but does not define the form globals.
    if (typeof opsdeskOrderStockUrl === "undefined") {
      return;
    }

    applyPrefill();

    if ($("#opsdesk_order_combo_id").val()) {
      fetchStockCheck();
    }

    $("#opsdesk_order_combo_id").on("change changed.bs.select", function () {
        resetOverrides();
        debouncedStockCheck();
    });

    $("#opsdesk_order_qty").on("input change", debouncedStockCheck);
    $("#opsdesk_packing_type").on("change", function () {
      var stockOk = $("#opsdesk_order_summary").hasClass("alert-success");
      updateSubmitState(stockOk);
    });

    $("#opsdesk_transport_medium").on("change", function () {
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
      if (!$("#opsdesk_bill_file").val()) {
        alert(opsdeskOrderLang.billRequired);
        $("#opsdesk_bill_file").focus();
        return false;
      }
      if (!$("#opsdesk_transport_medium").val()) {
        alert(opsdeskOrderLang.transportMediumRequired);
        $("#opsdesk_transport_medium").focus();
        return false;
      }
      return true;
    });

    initCustomerSearch();

  });

})(jQuery);

/**
 * OpsDesk — Order detail: inline priority change.
 */
(function ($) {
  "use strict";

  $(function () {
    var $changeBtn  = $("#opsdesk_change_priority_btn");
    if (!$changeBtn.length) {
      return;
    }

    var $inline   = $("#opsdesk_priority_inline");
    var $saveBtn  = $("#opsdesk_priority_save_btn");
    var $cancelBtn = $("#opsdesk_priority_cancel_btn");

    // Build the update_priority URL without relying on a global JS admin_url().
    // opsdeskOrderBaseUrl is provided by the detail view; fall back to a
    // path-relative guess if it is missing.
    var orderId = $("#opsdesk_order_id").val() ||
      window.location.pathname.split("/").filter(Boolean).pop();
    var orderUrl = (typeof opsdeskOrderBaseUrl !== "undefined" && opsdeskOrderBaseUrl)
      ? opsdeskOrderBaseUrl + "update_priority/" + orderId
      : "admin/opsdesk/update_priority/" + orderId;

    $changeBtn.on("click", function () {
      $changeBtn.addClass("hide");
      $inline.removeClass("hide");
    });

    $cancelBtn.on("click", function () {
      $inline.addClass("hide");
      $changeBtn.removeClass("hide");
    });

    $saveBtn.on("click", function () {
      var priority = $("input[name='opsdesk_priority_inline']:checked").val();
      if (priority === undefined) {
        return;
      }

      var postData = { priority: priority };
      $.extend(postData, getCsrfPostData());

      $saveBtn.prop("disabled", true);

      $.post(orderUrl, postData)
        .done(function (response) {
          if (typeof response === "string") {
            try { response = JSON.parse(response); } catch (e) { response = {}; }
          }
          if (response.success) {
            window.location.reload();
          } else {
            alert(response.message || "Error");
            $saveBtn.prop("disabled", false);
          }
        })
        .fail(function () {
          alert("Error updating priority");
          $saveBtn.prop("disabled", false);
        });
    });
  });
});

(function ($) {
  "use strict";

  /**
   * OpsDesk — Order detail: standalone assignment (packer) save.
   */
  $(function () {
    var $form = $("#opsdesk_assign_form");
    if (!$form.length) {
      return;
    }

    $form.on("submit", function (e) {
      e.preventDefault();

      var $btn = $("#opsdesk_assign_btn");
      $btn.prop("disabled", true);

      var postData = { packed_by: $("#opsdesk_packed_by").val() };
      $.extend(postData, getCsrfPostData());

      $.post($form.attr("action"), postData)
        .done(function (response) {
          if (typeof response === "string") {
            try { response = JSON.parse(response); } catch (err) { response = {}; }
          }
          if (response.success) {
            window.location.reload();
          } else {
            alert(response.message || "Error");
            $btn.prop("disabled", false);
          }
        })
        .fail(function () {
          alert("Error assigning order");
          $btn.prop("disabled", false);
        });
    });
  });
})(jQuery);
