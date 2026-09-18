/**
 * OpsDesk — Order form: multi-combo and standalone products selection,
 * real-time stock check, substitutions, and submit guard.
 */

// Shared helper (global scope) so every IIFE in this file can read the CSRF token.
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

  if (window.opsdeskOrdersScriptLoaded) {
    return;
  }
  window.opsdeskOrdersScriptLoaded = true;

  var debounceTimer = null;
  var selectedCombos = [];
  var selectedProducts = [];
  var currentStockData = null;
  var currentViewMode = "grouped";
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

  function syncInputs() {
    $("#opsdesk_order_combos_input").val(JSON.stringify(selectedCombos));
    $("#opsdesk_order_products_input").val(JSON.stringify(selectedProducts));
    $("#opsdesk_order_overrides").val(JSON.stringify(overrides));

    var totalUnits = 0;
    $.each(selectedCombos, function (i, c) {
      totalUnits += parseFloat(c.quantity) || 0;
    });
    $.each(selectedProducts, function (i, p) {
      totalUnits += parseFloat(p.quantity) || 0;
    });
    $("#opsdesk_order_total_qty_input").val(totalUnits > 0 ? totalUnits : 1);
  }

  function renderSelectedCombosTable() {
    var $tbody = $("#opsdesk_selected_combos_body");
    if (selectedCombos.length === 0) {
      $tbody.html(
        '<tr id="opsdesk_no_combos_row"><td colspan="3" class="text-center text-muted" style="padding: 12px;">' +
          escapeHtml(opsdeskOrderLang.noCombosAdded || "No combos added yet.") +
          "</td></tr>"
      );
      return;
    }

    var html = "";
    $.each(selectedCombos, function (index, combo) {
      html += '<tr data-combo-index="' + index + '">';
      html += '<td style="vertical-align: middle;"><strong>' + escapeHtml(combo.combo_name) + "</strong></td>";
      html += '<td style="vertical-align: middle;">';
      html += '<input type="number" class="form-control text-center opsdesk-combo-row-qty" data-combo-index="' +
        index + '" value="' + (parseFloat(combo.quantity) || 1) + '" min="1" step="1" style="height: 30px; padding: 2px 6px;">';
      html += '</td>';
      html += '<td class="text-center" style="vertical-align: middle;">';
      html += '<button type="button" class="btn btn-danger btn-xs opsdesk-remove-combo-btn" data-combo-index="' +
        index + '" title="Remove"><i class="fa fa-trash"></i></button>';
      html += '</td>';
      html += "</tr>";
    });

    $tbody.html(html);
  }

  function renderSelectedProductsTable() {
    var $tbody = $("#opsdesk_selected_products_body");
    if (selectedProducts.length === 0) {
      $tbody.html(
        '<tr id="opsdesk_no_products_row"><td colspan="3" class="text-center text-muted" style="padding: 12px;">' +
          escapeHtml(opsdeskOrderLang.noProductsAdded || "No products added yet.") +
          "</td></tr>"
      );
      return;
    }

    var html = "";
    $.each(selectedProducts, function (index, prod) {
      html += '<tr data-product-index="' + index + '">';
      html += '<td style="vertical-align: middle;"><strong>' + escapeHtml(prod.product_name) +
        '</strong> <small class="text-muted">(' + escapeHtml(prod.sku) + ')</small></td>';
      html += '<td style="vertical-align: middle;">';
      html += '<input type="number" class="form-control text-center opsdesk-product-row-qty" data-product-index="' +
        index + '" value="' + (parseFloat(prod.quantity) || 1) + '" min="1" step="1" style="height: 30px; padding: 2px 6px;">';
      html += '</td>';
      html += '<td class="text-center" style="vertical-align: middle;">';
      html += '<button type="button" class="btn btn-danger btn-xs opsdesk-remove-product-btn" data-product-index="' +
        index + '" title="Remove"><i class="fa fa-trash"></i></button>';
      html += '</td>';
      html += "</tr>";
    });

    $tbody.html(html);
  }

  function fetchStockCheck() {
    syncInputs();

    if (selectedCombos.length === 0 && selectedProducts.length === 0) {
      resetComponentsTable();
      updateSubmitState(false);
      return;
    }

    $("#opsdesk_order_loading").removeClass("hide");
    $("#opsdesk_order_alert").addClass("hide");

    var postData = {
      combos: JSON.stringify(selectedCombos),
      products: JSON.stringify(selectedProducts),
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
      .fail(function () {
        $("#opsdesk_order_loading").addClass("hide");
        showAlert("danger", opsdeskOrderLang.error);
      });
  }

  function debouncedStockCheck() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      fetchStockCheck();
    }, 350);
  }

  function resetComponentsTable() {
    currentStockData = null;
    $("#opsdesk_components_wrapper").html(
      '<div id="opsdesk_order_empty_state" class="opsdesk-empty-state-card">' +
        '<i class="fa fa-boxes fa-3x"></i>' +
        '<h5>' + escapeHtml(opsdeskOrderLang.selectItemsToBegin || "Add at least one combo or product to view availability.") + '</h5>' +
        '<p class="text-muted">' + escapeHtml(opsdeskOrderLang.noCombosAdded || "Add one or more combos from the left or select direct inventory products from the right above to view components and stock availability.") + '</p>' +
      '</div>'
    );
    $("#opsdesk_quick_stats_bar").addClass("hide").html("");
    $("#opsdesk_order_summary").addClass("hide");
    $("#opsdesk_open_add_item").prop("disabled", true);
  }

  function showAlert(type, msg) {
    $("#opsdesk_order_alert")
      .removeClass("hide alert-success alert-danger")
      .addClass("alert-" + type)
      .text(msg);
  }

  function notify(type, msg) {
    if (typeof alert_float === "function") {
      alert_float(type, msg);
    } else if (typeof showAlert === "function") {
      showAlert(type, msg);
    } else {
      alert(msg);
    }
  }

  function renderComponents(data) {
    currentStockData = data;
    var components = data.components || [];

    componentMeta = {};
    $.each(components, function (i, row) {
      var key = row.key || row.combo_item_id || "item_" + i;
      componentMeta[key] = row;
    });

    if (components.length === 0) {
      resetComponentsTable();
      updateSubmitState(false);
      return;
    }

    // Update Quick Stats Bar
    var totalCombos = selectedCombos.length;
    var totalStandalone = selectedProducts.length;
    var totalUnits = 0;
    $.each(selectedCombos, function (i, c) { totalUnits += parseFloat(c.quantity) || 0; });
    $.each(selectedProducts, function (i, p) { totalUnits += parseFloat(p.quantity) || 0; });

    var insufficientCount = 0;
    $.each(components, function (i, row) {
      if (!row.is_sufficient) {
        insufficientCount++;
      }
    });

    var statsHtml = "";
    statsHtml += '<div class="opsdesk-stat-item"><strong>' + (opsdeskOrderLang.combosIncluded || "Combos") + ':</strong> ' + totalCombos + "</div>";
    statsHtml += '<div class="opsdesk-stat-item"><strong>' + (opsdeskOrderLang.standaloneProductsIncluded || "Standalone") + ':</strong> ' + totalStandalone + "</div>";
    statsHtml += '<div class="opsdesk-stat-item"><strong>Total Units:</strong> ' + formatNumber(totalUnits) + "</div>";
    statsHtml += '<div class="opsdesk-stat-item"><strong>Line Items:</strong> ' + components.length + "</div>";
    if (insufficientCount === 0) {
      statsHtml += '<div class="opsdesk-stat-item" style="border-color: #a7f3d0; background: #ecfdf5; color: #065f46;"><i class="fa fa-check-circle" style="color: #10b981;"></i> ' + (opsdeskOrderLang.readyToFulfill || "Ready to Fulfill") + "</div>";
    } else {
      statsHtml += '<div class="opsdesk-stat-item" style="border-color: #fecaca; background: #fef2f2; color: #991b1b;"><i class="fa fa-exclamation-triangle" style="color: #ef4444;"></i> <strong>' + insufficientCount + "</strong> " + (opsdeskOrderLang.outOfStock || "Out of Stock") + "</div>";
    }
    $("#opsdesk_quick_stats_bar").removeClass("hide").html(statsHtml);
    $("#opsdesk_open_add_item").prop("disabled", false);

    if (currentViewMode === "consolidated") {
      renderConsolidatedView(data);
    } else {
      renderGroupedView(data);
    }

    var summary = $("#opsdesk_order_summary");
    summary.removeClass("hide alert-success alert-danger alert-warning");

    if (data.is_fulfillable) {
      summary.addClass("alert-success").html('<i class="fa fa-check-circle"></i> ' + (opsdeskOrderLang.allSufficient || "All components are sufficient in stock."));
      updateSubmitState(true);
    } else if (typeof opsdeskBypassStockCheck !== "undefined" && opsdeskBypassStockCheck) {
      summary.addClass("alert-warning").html(
        '<i class="fa fa-exclamation-triangle"></i> ' +
          (opsdeskOrderLang.componentsInsufficient || "%s components are insufficient in stock.").replace("%s", insufficientCount)
      );
      updateSubmitState(true);
    } else {
      summary.addClass("alert-danger").html(
        '<i class="fa fa-exclamation-circle"></i> ' +
          (opsdeskOrderLang.componentsInsufficient || "%s components are insufficient in stock.").replace("%s", insufficientCount)
      );
      updateSubmitState(false);
    }
  }

  function renderGroupedView(data) {
    var components = data.components || [];
    var html = "";

    // 1. Render Each Selected Combo
    $.each(selectedCombos, function (cIdx, combo) {
      var comboItems = [];
      $.each(components, function (i, row) {
        if (row.source_type === "combo") {
          if (row.combo_index !== null && row.combo_index !== undefined) {
            if (row.combo_index === cIdx) {
              comboItems.push(row);
            }
          } else if (row.combo_id == combo.combo_id) {
            comboItems.push(row);
          }
        }
      });

      var comboInsufficient = 0;
      $.each(comboItems, function (i, row) {
        if (!row.is_sufficient) comboInsufficient++;
      });

      html += '<div class="opsdesk-group-card">';
      html += '<div class="opsdesk-group-header is-combo">';
      html += '<div class="opsdesk-group-title">';
      html += '<span class="opsdesk-badge-icon"><i class="fa fa-object-group"></i></span>';
      html += '<span>' + escapeHtml(combo.combo_name) + '</span>';
      html += '<span class="label label-primary tw-font-semibold" style="margin-left: 6px;"><i class="fa fa-times"></i> ' + formatNumber(combo.quantity) + ' ' + (opsdeskOrderLang.combosSection || 'combos') + '</span>';
      html += '<span class="text-muted tw-text-xs" style="margin-left: 6px;">(' + comboItems.length + ' components)</span>';
      html += '</div>';
      html += '<div>';
      if (comboInsufficient === 0) {
        html += '<span class="opsdesk-status-badge opsdesk-status-sufficient"><i class="fa fa-check-circle"></i> ' + (opsdeskOrderLang.readyToFulfill || "In Stock") + '</span>';
      } else {
        html += '<span class="opsdesk-status-badge opsdesk-status-insufficient"><i class="fa fa-exclamation-circle"></i> ' + comboInsufficient + ' ' + (opsdeskOrderLang.outOfStock || "Out of Stock") + '</span>';
      }
      html += '</div>';
      html += '</div>';

      html += '<div class="table-responsive">';
      html += '<table class="table table-hover opsdesk-components-subtable">';
      html += '<thead><tr>';
      html += '<th style="width: 130px;">' + (opsdeskOrderLang.sku || "SKU") + '</th>';
      html += '<th>' + (opsdeskOrderLang.product || "Product Name") + '</th>';
      html += '<th class="text-center" style="width: 110px;">' + (opsdeskOrderLang.perCombo || "Per Combo") + '</th>';
      html += '<th class="text-right" style="width: 120px;">' + (opsdeskOrderLang.totalNeeded || "Total Needed") + '</th>';
      html += '<th class="text-right" style="width: 140px;">' + (opsdeskOrderLang.availableStock || "Available Stock") + '</th>';
      html += '<th class="text-center" style="width: 120px;">' + (opsdeskOrderLang.status || "Status") + '</th>';
      html += '<th class="text-center" style="width: 140px;">' + (opsdeskOrderLang.actions || "Actions") + '</th>';
      html += '</tr></thead>';
      html += '<tbody>';

      if (comboItems.length === 0) {
        html += '<tr><td colspan="7" class="text-center text-muted" style="padding: 16px;">No components for this combo.</td></tr>';
      } else {
        $.each(comboItems, function (i, row) {
          var key = row.key || row.combo_item_id || "item_" + i;
          var statusClass = row.is_sufficient ? "opsdesk-status-sufficient" : "opsdesk-status-insufficient";
          var statusIcon = row.is_sufficient ? "fa-check-circle" : "fa-times-circle";
          var statusText = row.is_sufficient ? (opsdeskOrderLang.sufficient || "In Stock") : (opsdeskOrderLang.insufficient || "Insufficient");

          var availClass = row.is_sufficient ? 'style="color: #16a34a; font-weight: 700;"' : 'style="color: #dc2626; font-weight: 700;"';
          var availIcon = row.is_sufficient ? '<i class="fa fa-check" style="margin-right: 3px;"></i>' : '<i class="fa fa-exclamation-triangle" style="margin-right: 3px;"></i>';

          html += '<tr data-component-key="' + escapeHtml(String(key)) + '">';
          html += '<td><span class="opsdesk-sku-badge">' + escapeHtml(row.sku) + '</span></td>';
          html += '<td>' + escapeHtml(row.product_name);
          if (row.is_substitution) {
            html += ' <span class="label label-warning" style="font-size: 10px; margin-left: 4px;"><i class="fa fa-exchange"></i> ' + escapeHtml(opsdeskOrderLang.substitution || "Substituted") + '</span>';
          }
          html += '</td>';
          html += '<td class="text-center">' + formatNumber(row.quantity_per_unit) + '</td>';
          html += '<td class="text-right font-bold">' + formatNumber(row.required_quantity) + '</td>';
          html += '<td class="text-right"><span ' + availClass + '>' + availIcon + formatNumber(row.available_stock) + '</span></td>';
          html += '<td class="text-center"><span class="opsdesk-status-badge ' + statusClass + '"><i class="fa ' + statusIcon + '"></i> ' + statusText + '</span></td>';
          html += '<td class="text-center">';
          html += '<div class="opsdesk-row-actions">';
          if (row.combo_item_id) {
            html += '<button type="button" class="btn btn-default btn-xs opsdesk-sub-btn" data-item-key="' +
              escapeHtml(String(key)) + '" data-combo-item-id="' + escapeHtml(String(row.combo_item_id || "")) +
              '" data-sku="' + escapeHtml(String(row.sku || "")) + '" title="' + escapeHtml(opsdeskOrderLang.substitute || "Substitute") + '">' +
              '<i class="fa fa-exchange"></i> ' + escapeHtml(opsdeskOrderLang.substitute || "Substitute") + '</button>';
          }
          html += '<button type="button" class="btn btn-danger btn-xs opsdesk-remove-component-btn" data-item-key="' +
            escapeHtml(String(key)) + '" data-combo-item-id="' + escapeHtml(String(row.combo_item_id || "")) +
            '" data-sku="' + escapeHtml(String(row.sku || "")) + '" title="Remove"><i class="fa fa-trash"></i></button>';
          html += '</div>';
          html += '</td>';
          html += '</tr>';
        });
      }

      html += '</tbody></table></div></div>';
    });

    // 2. Render Direct Inventory Products (Standalone)
    var standaloneItems = [];
    $.each(components, function (i, row) {
      if (row.source_type === "standalone" && String(row.key || "").indexOf("added_") !== 0) {
        standaloneItems.push(row);
      }
    });

    if (standaloneItems.length > 0) {
      var standaloneInsufficient = 0;
      $.each(standaloneItems, function (i, row) {
        if (!row.is_sufficient) standaloneInsufficient++;
      });

      html += '<div class="opsdesk-group-card">';
      html += '<div class="opsdesk-group-header is-standalone">';
      html += '<div class="opsdesk-group-title">';
      html += '<span class="opsdesk-badge-icon"><i class="fa fa-cube"></i></span>';
      html += '<span>' + (opsdeskOrderLang.standaloneProductsIncluded || "Direct Inventory Products (Standalone)") + '</span>';
      html += '<span class="label label-success tw-font-semibold" style="margin-left: 6px;">' + standaloneItems.length + ' products</span>';
      html += '</div>';
      html += '<div>';
      if (standaloneInsufficient === 0) {
        html += '<span class="opsdesk-status-badge opsdesk-status-sufficient"><i class="fa fa-check-circle"></i> ' + (opsdeskOrderLang.readyToFulfill || "In Stock") + '</span>';
      } else {
        html += '<span class="opsdesk-status-badge opsdesk-status-insufficient"><i class="fa fa-exclamation-circle"></i> ' + standaloneInsufficient + ' ' + (opsdeskOrderLang.outOfStock || "Out of Stock") + '</span>';
      }
      html += '</div>';
      html += '</div>';

      html += '<div class="table-responsive">';
      html += '<table class="table table-hover opsdesk-components-subtable">';
      html += '<thead><tr>';
      html += '<th style="width: 130px;">' + (opsdeskOrderLang.sku || "SKU") + '</th>';
      html += '<th>' + (opsdeskOrderLang.product || "Product Name") + '</th>';
      html += '<th class="text-right" style="width: 120px;">' + (opsdeskOrderLang.quantity || "Quantity") + '</th>';
      html += '<th class="text-right" style="width: 140px;">' + (opsdeskOrderLang.availableStock || "Available Stock") + '</th>';
      html += '<th class="text-center" style="width: 120px;">' + (opsdeskOrderLang.status || "Status") + '</th>';
      html += '<th class="text-center" style="width: 110px;">' + (opsdeskOrderLang.actions || "Actions") + '</th>';
      html += '</tr></thead>';
      html += '<tbody>';

      $.each(standaloneItems, function (i, row) {
        var key = row.key || "standalone_" + i;
        var statusClass = row.is_sufficient ? "opsdesk-status-sufficient" : "opsdesk-status-insufficient";
        var statusIcon = row.is_sufficient ? "fa-check-circle" : "fa-times-circle";
        var statusText = row.is_sufficient ? (opsdeskOrderLang.sufficient || "In Stock") : (opsdeskOrderLang.insufficient || "Insufficient");

        var availClass = row.is_sufficient ? 'style="color: #16a34a; font-weight: 700;"' : 'style="color: #dc2626; font-weight: 700;"';
        var availIcon = row.is_sufficient ? '<i class="fa fa-check" style="margin-right: 3px;"></i>' : '<i class="fa fa-exclamation-triangle" style="margin-right: 3px;"></i>';

        html += '<tr data-component-key="' + escapeHtml(String(key)) + '">';
        html += '<td><span class="opsdesk-sku-badge">' + escapeHtml(row.sku) + '</span></td>';
        html += '<td>' + escapeHtml(row.product_name) + '</td>';
        html += '<td class="text-right font-bold">' + formatNumber(row.required_quantity) + '</td>';
        html += '<td class="text-right"><span ' + availClass + '>' + availIcon + formatNumber(row.available_stock) + '</span></td>';
        html += '<td class="text-center"><span class="opsdesk-status-badge ' + statusClass + '"><i class="fa ' + statusIcon + '"></i> ' + statusText + '</span></td>';
        html += '<td class="text-center">';
        html += '<div class="opsdesk-row-actions">';
        html += '<button type="button" class="btn btn-danger btn-xs opsdesk-remove-component-btn" data-item-key="' +
          escapeHtml(String(key)) + '" data-sku="' + escapeHtml(String(row.sku || "")) + '" title="Remove"><i class="fa fa-trash"></i></button>';
        html += '</div>';
        html += '</td>';
        html += '</tr>';
      });

      html += '</tbody></table></div></div>';
    }

    // 3. Render Custom / Additional Items (if any)
    var customItems = [];
    $.each(components, function (i, row) {
      if (String(row.key || "").indexOf("added_") === 0) {
        customItems.push(row);
      }
    });

    if (customItems.length > 0) {
      html += '<div class="opsdesk-group-card">';
      html += '<div class="opsdesk-group-header is-custom">';
      html += '<div class="opsdesk-group-title">';
      html += '<span class="opsdesk-badge-icon"><i class="fa fa-plus-circle"></i></span>';
      html += '<span>' + (opsdeskOrderLang.customItems || "Custom / Additional Items") + '</span>';
      html += '<span class="label label-warning tw-font-semibold" style="margin-left: 6px;">' + customItems.length + ' items</span>';
      html += '</div>';
      html += '</div>';

      html += '<div class="table-responsive">';
      html += '<table class="table table-hover opsdesk-components-subtable">';
      html += '<thead><tr>';
      html += '<th style="width: 130px;">SKU</th>';
      html += '<th>Product Name</th>';
      html += '<th class="text-right" style="width: 120px;">Quantity</th>';
      html += '<th class="text-right" style="width: 130px;">Available Stock</th>';
      html += '<th class="text-center" style="width: 120px;">Status</th>';
      html += '<th class="text-center" style="width: 140px;">Actions</th>';
      html += '</tr></thead>';
      html += '<tbody>';

      $.each(customItems, function (i, row) {
        var key = row.key || "added_" + i;
        var statusClass = row.is_sufficient ? "opsdesk-status-sufficient" : "opsdesk-status-insufficient";
        var statusIcon = row.is_sufficient ? "fa-check-circle" : "fa-times-circle";
        var statusText = row.is_sufficient ? (opsdeskOrderLang.sufficient || "In Stock") : (opsdeskOrderLang.insufficient || "Insufficient");

        var availClass = row.is_sufficient ? 'style="color: #16a34a; font-weight: 700;"' : 'style="color: #dc2626; font-weight: 700;"';

        html += '<tr data-component-key="' + escapeHtml(String(key)) + '">';
        html += '<td><span class="opsdesk-sku-badge">' + escapeHtml(row.sku) + '</span></td>';
        html += '<td>' + escapeHtml(row.product_name) + '</td>';
        html += '<td class="text-right font-bold">' + formatNumber(row.required_quantity) + '</td>';
        html += '<td class="text-right"><span ' + availClass + '>' + formatNumber(row.available_stock) + '</span></td>';
        html += '<td class="text-center"><span class="opsdesk-status-badge ' + statusClass + '"><i class="fa ' + statusIcon + '"></i> ' + statusText + '</span></td>';
        html += '<td class="text-center">';
        html += '<div class="opsdesk-row-actions">';
        html += '<button type="button" class="btn btn-danger btn-xs opsdesk-remove-component-btn" data-item-key="' +
          escapeHtml(String(key)) + '" data-sku="' + escapeHtml(String(row.sku || "")) + '" title="Remove"><i class="fa fa-trash"></i></button>';
        html += '</div>';
        html += '</td>';
        html += '</tr>';
      });

      html += '</tbody></table></div></div>';
    }

    $("#opsdesk_components_wrapper").html(html);
  }

  function renderConsolidatedView(data) {
    var components = data.components || [];
    var skuTotals = data.sku_totals || {};
    var skuMap = {};

    $.each(components, function (i, row) {
      var sku = row.sku;
      if (!skuMap[sku]) {
        skuMap[sku] = {
          sku: sku,
          product_name: row.product_name,
          available_stock: parseFloat(row.available_stock) || 0,
          total_needed: parseFloat(skuTotals[sku]) || 0,
          sources: [],
          is_sufficient: (parseFloat(row.available_stock) || 0) >= (parseFloat(skuTotals[sku]) || 0),
        };
      }
      var sName = row.source_name || (row.source_type === "standalone" ? "Direct Product" : "Combo");
      var req = parseFloat(row.required_quantity) || 0;
      skuMap[sku].sources.push(sName + " (x" + formatNumber(req) + ")");
    });

    var html = '<div class="opsdesk-group-card">';
    html += '<div class="opsdesk-group-header" style="border-left: 5px solid #6366f1; background: #eef2ff;">';
    html += '<div class="opsdesk-group-title">';
    html += '<span class="opsdesk-badge-icon" style="background: #e0e7ff; color: #4338ca;"><i class="fa fa-table"></i></span>';
    html += '<span>' + (opsdeskOrderLang.viewConsolidated || "Consolidated SKU Demand Summary") + '</span>';
    html += '<span class="text-muted tw-text-xs" style="margin-left: 6px;">(' + Object.keys(skuMap).length + ' unique SKUs across order)</span>';
    html += '</div>';
    html += '</div>';

    html += '<div class="table-responsive">';
    html += '<table class="table table-hover opsdesk-components-subtable">';
    html += '<thead><tr>';
    html += '<th style="width: 130px;">SKU</th>';
    html += '<th>Product Name</th>';
    html += '<th>' + (opsdeskOrderLang.usedIn || "Used In") + '</th>';
    html += '<th class="text-right" style="width: 120px;">' + (opsdeskOrderLang.totalDemand || "Total Demand") + '</th>';
    html += '<th class="text-right" style="width: 130px;">' + (opsdeskOrderLang.availableStock || "Available Stock") + '</th>';
    html += '<th class="text-right" style="width: 120px;">' + (opsdeskOrderLang.balance || "Balance") + '</th>';
    html += '<th class="text-center" style="width: 120px;">' + (opsdeskOrderLang.status || "Status") + '</th>';
    html += '</tr></thead>';
    html += '<tbody>';

    $.each(skuMap, function (sku, item) {
      var balance = item.available_stock - item.total_needed;
      var statusClass = item.is_sufficient ? "opsdesk-status-sufficient" : "opsdesk-status-insufficient";
      var statusIcon = item.is_sufficient ? "fa-check-circle" : "fa-times-circle";
      var statusText = item.is_sufficient ? (opsdeskOrderLang.sufficient || "In Stock") : (opsdeskOrderLang.insufficient || "Insufficient");

      var balanceHtml = balance >= 0
        ? '<span class="tw-font-bold text-success">+' + formatNumber(balance) + '</span>'
        : '<span class="tw-font-bold text-danger">' + formatNumber(balance) + '</span>';

      html += '<tr>';
      html += '<td><span class="opsdesk-sku-badge">' + escapeHtml(item.sku) + '</span></td>';
      html += '<td>' + escapeHtml(item.product_name) + '</td>';
      html += '<td>';
      $.each(item.sources, function(sIdx, s) {
        html += '<span class="label label-default" style="margin-right: 4px; display: inline-block; margin-bottom: 2px;">' + escapeHtml(s) + '</span>';
      });
      html += '</td>';
      html += '<td class="text-right font-bold">' + formatNumber(item.total_needed) + '</td>';
      html += '<td class="text-right">' + formatNumber(item.available_stock) + '</td>';
      html += '<td class="text-right">' + balanceHtml + '</td>';
      html += '<td class="text-center"><span class="opsdesk-status-badge ' + statusClass + '"><i class="fa ' + statusIcon + '"></i> ' + statusText + '</span></td>';
      html += '</tr>';
    });

    html += '</tbody></table></div></div>';

    $("#opsdesk_components_wrapper").html(html);
  }

  function updateSubmitState(isFulfillable) {
    var packing = $("#opsdesk_packing_type").val();
    var transportMedium = $("#opsdesk_transport_medium").val();
    var hasItems = (selectedCombos.length > 0 || selectedProducts.length > 0);

    $("#opsdesk_submit_order").prop(
      "disabled",
      !(isFulfillable && packing && transportMedium && hasItems)
    );
  }

  function openProductModal(mode, itemKey, comboItemId, sku) {
    $("#opsdesk_product_modal_mode").val(mode);
    $("#opsdesk_product_modal_title").text(
      mode === "add" ? opsdeskOrderLang.addItem : opsdeskOrderLang.substitute
    );
    $("#opsdesk_sub_item_key").val(itemKey || "");
    $("#opsdesk_sub_combo_item_id").val(comboItemId || "");
    $("#opsdesk_sub_original_sku").val(sku || "");
    $("#opsdesk_sub_product_id").val("").selectpicker("refresh");
    $("#opsdesk_substitute_modal").modal("show");
  }

  function getProductDetails(productId, callback) {
    var postData = {
      action: "get_product_details",
      product_id: productId,
    };
    $.extend(postData, getCsrfPostData());

    $.post(opsdeskProductDetailsUrl, postData)
      .done(function (response) {
        if (typeof response === "string") {
          try { response = JSON.parse(response); } catch (e) { callback(null); return; }
        }
        if (response.success && response.product) {
          callback(response.product);
        } else {
          callback(null);
        }
      })
      .fail(function () {
        callback(null);
      });
  }

  function applyProductModal() {
    var mode = $("#opsdesk_product_modal_mode").val();
    var itemKey = $("#opsdesk_sub_item_key").val();
    var comboItemId = $("#opsdesk_sub_combo_item_id").val();
    var productId = $("#opsdesk_sub_product_id").val();
    var originalSku = $("#opsdesk_sub_original_sku").val();

    if (!productId) {
      return;
    }

    if (mode === "add") {
      getProductDetails(productId, function (product) {
        if (!product) {
          notify("danger", opsdeskOrderLang.error);
          return;
        }

        overrides.added.push({
          product_item_id: product.product_item_id,
          sku: product.sku,
          product_name: product.product_name,
          quantity_per_unit: 1.0,
          required_quantity: 1.0,
        });
        syncInputs();
        $("#opsdesk_substitute_modal").modal("hide");
        fetchStockCheck();
      });
      return;
    }

    // Substitute mode
    var subKey = itemKey || comboItemId || originalSku;
    if (subKey) {
      overrides.substitutions[String(subKey)] = parseInt(productId, 10);
      syncInputs();
      $("#opsdesk_substitute_modal").modal("hide");
      fetchStockCheck();
    }
  }

  function removeOrderItem(itemKey, comboItemId, sku) {
    if (itemKey) {
      overrides.removed.push(String(itemKey));
    }
    if (comboItemId) {
      overrides.removed.push(String(comboItemId));
      delete overrides.substitutions[String(comboItemId)];
    }
    if (sku) {
      overrides.added = $.grep(overrides.added, function (item) {
        return String(item.sku) !== String(sku);
      });
      if (itemKey && String(itemKey).indexOf("standalone_") === 0) {
        selectedProducts = $.grep(selectedProducts, function (p) {
          return String(p.sku) !== String(sku);
        });
        renderSelectedProductsTable();
      }
    }

    syncInputs();
    fetchStockCheck();
  }

  function applyPrefill() {
    if (!opsdeskOrderPrefill) {
      return;
    }

    if (opsdeskOrderPrefill.combo_id) {
      var cId = parseInt(opsdeskOrderPrefill.combo_id, 10);
      var cQty = parseFloat(opsdeskOrderPrefill.quantity) || 1;
      var $opt = $('#opsdesk_quick_combo_id option[value="' + cId + '"]');
      var cName = $opt.data("name") || $opt.text() || "Combo #" + cId;

      selectedCombos.push({
        combo_id: cId,
        combo_name: cName,
        quantity: cQty,
      });
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

    renderSelectedCombosTable();
    renderSelectedProductsTable();
    syncInputs();
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

    loadOptions("");

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
    if (typeof opsdeskOrderStockUrl === "undefined") {
      return;
    }

    applyPrefill();

    // 1. Add Combo Button
    $("#opsdesk_add_combo_btn").off("click").on("click", function () {
      var comboId = parseInt($("#opsdesk_quick_combo_id").val(), 10);
      var comboQty = parseFloat($("#opsdesk_quick_combo_qty").val()) || 1;
      if (!comboId || comboQty < 1) {
        notify("warning", opsdeskOrderLang.selectCombo || "Please select a combo before adding.");
        return;
      }

      var $opt = $('#opsdesk_quick_combo_id option[value="' + comboId + '"]');
      var comboName = $opt.data("name") || $opt.text();

      // Check if already in selectedCombos
      var existing = false;
      $.each(selectedCombos, function (i, c) {
        if (c.combo_id === comboId) {
          c.quantity += comboQty;
          existing = true;
          return false;
        }
      });

      if (!existing) {
        selectedCombos.push({
          combo_id: comboId,
          combo_name: comboName,
          quantity: comboQty,
        });
      }

      $("#opsdesk_quick_combo_id").val("").selectpicker("refresh");
      $("#opsdesk_quick_combo_qty").val("1");

      renderSelectedCombosTable();
      fetchStockCheck();
    });

    // Enter key support for quick combo quantity
    $("#opsdesk_quick_combo_qty").off("keypress").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $("#opsdesk_add_combo_btn").trigger("click");
      }
    });

    // 2. Add Standalone Product Button
    $("#opsdesk_add_product_btn").off("click").on("click", function () {
      var prodId = parseInt($("#opsdesk_quick_product_id").val(), 10);
      var prodQty = parseFloat($("#opsdesk_quick_product_qty").val()) || 1;
      if (!prodId || prodQty < 1) {
        notify("warning", opsdeskOrderLang.selectProduct || "Please select a product before adding.");
        return;
      }

      var $opt = $('#opsdesk_quick_product_id option[value="' + prodId + '"]');
      var prodSku = $opt.data("sku");
      var prodName = $opt.data("name");

      var existing = false;
      $.each(selectedProducts, function (i, p) {
        if (p.product_id === prodId || (prodSku && p.sku === prodSku)) {
          p.quantity += prodQty;
          existing = true;
          return false;
        }
      });

      if (!existing) {
        selectedProducts.push({
          product_id: prodId,
          sku: prodSku,
          product_name: prodName,
          quantity: prodQty,
        });
      }

      $("#opsdesk_quick_product_id").val("").selectpicker("refresh");
      $("#opsdesk_quick_product_qty").val("1");

      renderSelectedProductsTable();
      fetchStockCheck();
    });

    // Enter key support for quick product quantity
    $("#opsdesk_quick_product_qty").off("keypress").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $("#opsdesk_add_product_btn").trigger("click");
      }
    });

    // 3. Remove Combo Row
    $(document).on("click", ".opsdesk-remove-combo-btn", function () {
      var idx = parseInt($(this).data("combo-index"), 10);
      if (!isNaN(idx) && idx >= 0 && idx < selectedCombos.length) {
        selectedCombos.splice(idx, 1);
        renderSelectedCombosTable();
        fetchStockCheck();
      }
    });

    // 4. Remove Standalone Product Row
    $(document).on("click", ".opsdesk-remove-product-btn", function () {
      var idx = parseInt($(this).data("product-index"), 10);
      if (!isNaN(idx) && idx >= 0 && idx < selectedProducts.length) {
        selectedProducts.splice(idx, 1);
        renderSelectedProductsTable();
        fetchStockCheck();
      }
    });

    // 5. Quantity changes inside Selected Combos Table
    $(document).on("change input", ".opsdesk-combo-row-qty", function () {
      var idx = parseInt($(this).data("combo-index"), 10);
      var newQty = parseFloat($(this).val()) || 1;
      if (newQty < 1) {
        newQty = 1;
        $(this).val(1);
      }
      if (!isNaN(idx) && selectedCombos[idx]) {
        selectedCombos[idx].quantity = newQty;
        debouncedStockCheck();
      }
    });

    // 6. Quantity changes inside Selected Products Table
    $(document).on("change input", ".opsdesk-product-row-qty", function () {
      var idx = parseInt($(this).data("product-index"), 10);
      var newQty = parseFloat($(this).val()) || 1;
      if (newQty < 1) {
        newQty = 1;
        $(this).val(1);
      }
      if (!isNaN(idx) && selectedProducts[idx]) {
        selectedProducts[idx].quantity = newQty;
        debouncedStockCheck();
      }
    });

    // 7. Packing type & transport medium change
    $("#opsdesk_packing_type, #opsdesk_transport_medium").on("change", function () {
      var stockOk =
        $("#opsdesk_order_summary").hasClass("alert-success") ||
        $("#opsdesk_order_summary").hasClass("alert-warning");
      updateSubmitState(stockOk);
    });

    // 8. Substitution & manual item handlers
    $(document).on("click", ".opsdesk-sub-btn", function () {
      openProductModal("substitute", $(this).data("item-key"), $(this).data("combo-item-id"), $(this).data("sku"));
    });

    $(document).on("click", ".opsdesk-remove-component-btn", function () {
      removeOrderItem($(this).data("item-key"), $(this).data("combo-item-id"), $(this).data("sku"));
    });

    $("#opsdesk_open_add_item").on("click", function () {
      openProductModal("add");
    });

    $("#opsdesk_apply_substitute").on("click", applyProductModal);

    // View mode switcher handlers
    $("#opsdesk_btn_view_grouped").on("click", function () {
      currentViewMode = "grouped";
      $(this).addClass("active").siblings().removeClass("active");
      if (currentStockData) {
        renderGroupedView(currentStockData);
      }
    });

    $("#opsdesk_btn_view_consolidated").on("click", function () {
      currentViewMode = "consolidated";
      $(this).addClass("active").siblings().removeClass("active");
      if (currentStockData) {
        renderConsolidatedView(currentStockData);
      }
    });

    // 9. Submit validation
    $("#opsdesk_order_form").off("submit").on("submit", function () {
      syncInputs();
      if ($("#opsdesk_submit_order").prop("disabled")) {
        return false;
      }
      if (selectedCombos.length === 0 && selectedProducts.length === 0) {
        notify("warning", opsdeskOrderLang.atLeastOneItem || "Please select at least one combo or product.");
        return false;
      }
      if (!$("#opsdesk_bill_file").val()) {
        notify("warning", opsdeskOrderLang.billRequired);
        $("#opsdesk_bill_file").focus();
        return false;
      }
      if (!$("#opsdesk_transport_medium").val()) {
        notify("warning", opsdeskOrderLang.transportMediumRequired);
        $("#opsdesk_transport_medium").focus();
        return false;
      }
      return true;
    });

    initCustomerSearch();

    if (selectedCombos.length > 0 || selectedProducts.length > 0) {
      fetchStockCheck();
    }
  });

})(jQuery);
