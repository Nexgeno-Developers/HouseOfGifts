/**
 * OpsDesk — Sales inventory viewer AJAX handler.
 */
(function ($) {
   "use strict";

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

   function stockCheckBypassed() {
     return (
       typeof opsdeskBypassStockCheck !== "undefined" && opsdeskBypassStockCheck
     );
   }

   var debounceTimer = null;
  var editedItems = null;
  var addedItems = null;
  var substitutions = null;
  var substitutionSnapshots = null;
  var originalComboId = null;
  var currentAvailabilityData = null;
  var newCombo = false;
  function initializeEditorState(comboId) {
    editedItems = null;
    addedItems = null;
    substitutions = null;
    substitutionSnapshots = null;
    originalComboId = comboId;
  }

  function getEditedItemsData() {
    if (editedItems === null) {
      return null;
    }
    return editedItems;
  }

  function hasEdits() {
    return (
      (editedItems !== null && Object.keys(editedItems).length > 0) ||
      (addedItems !== null && Object.keys(addedItems).length > 0)
    );
  }

  function resetEdits() {
    editedItems = null;
    addedItems = null;
    substitutions = null;
    substitutionSnapshots = null;
    originalComboId = null;
    currentAvailabilityData = null;
    updateAddItemButton(false);
  }

  function updateAddItemButton(enabled) {
    $("#opsdesk_open_add_item").prop("disabled", !enabled);
  }

  function actionButtonsHtml(itemId) {
    var substituteLabel =
      typeof opsdeskLang !== "undefined" && opsdeskLang.substitute
        ? opsdeskLang.substitute
        : "Substitute";
    return (
      '<td class="text-center opsdesk-row-actions">' +
      '<button type="button" class="btn btn-xs btn-default opsdesk-sub-btn" data-item-id="' +
      itemId +
      '" title="' +
      escapeHtml(substituteLabel) +
      '">' +
      '<i class="fa fa-exchange"></i> ' +
      escapeHtml(substituteLabel) +
      "</button> " +
      '<button type="button" class="btn btn-xs btn-danger opsdesk-remove-item" data-item-id="' +
      itemId +
      '">' +
      '<i class="fa fa-trash"></i></button>' +
      "</td>"
    );
  }

  function fetchAvailability() {
    var comboId = $("#opsdesk_combo_id").val();
    var qty = parseFloat($("#opsdesk_order_quantity").val()) || 0;

    if (!comboId || qty <= 0) {
      resetTable();
      resetEdits();
      return;
    }

    // Initialize editor state if new combo
    if (!originalComboId || originalComboId != comboId) {
      initializeEditorState(comboId);
    }

    $("#opsdesk_loading").removeClass("hide");
    $("#opsdesk_alert").addClass("hide");

    var postData = {
      combo_id: comboId,
      order_quantity: qty,
      edited_items: editedItems,
      added_items: addedItems,
    };

     $.extend(postData, getCsrfPostData());

     $.post(opsdeskAjaxUrl, postData)
       .done(function (response) {
         $("#opsdesk_loading").addClass("hide");

         if (typeof response === "string") {
           try {
             response = JSON.parse(response);
           } catch (e) {
             showError();
             return;
           }
         }

         if (!response.success) {
           showError(response.message || opsdeskLang.error);
           return;
         }

         currentAvailabilityData = response.data;
         renderTable(response.data);
         updateAddItemButton(true);
       })
       .fail(function () {
         $("#opsdesk_loading").addClass("hide");
         showError();
       });
   }

  function getProductDetailsForAdding(productId, callback) {
    var postData = {
      combo_id: $("#opsdesk_combo_id").val(),
      action: "get_product_details",
      product_id: productId,
      order_quantity: parseFloat($("#opsdesk_order_quantity").val()) || 1,
    };

     $.extend(postData, getCsrfPostData());

     $.post(opsdeskAjaxUrl, postData)
       .done(function (response) {
         if (typeof response === "string") {
           try {
             response = JSON.parse(response);
           } catch (e) {
             callback(null);
             return;
           }
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

  function addItemToTable(productData) {
    if (!productData) {
      alert(opsdeskLang.error);
      return;
    }

    if (!currentAvailabilityData) {
      currentAvailabilityData = { components: [], is_fulfillable: true };
    }

    var comboItemId = "new_" + Date.now();
    var orderQty = parseFloat($("#opsdesk_order_quantity").val()) || 1;
    var qtyPerUnit = parseFloat(productData.quantity_per_unit) || 1.0;
    var requiredQty = qtyPerUnit * orderQty;
    var availableStock = parseFloat(productData.available_stock) || 0;
    var isSufficient = availableStock >= requiredQty;

    if (addedItems === null) {
      addedItems = {};
    }

    addedItems[comboItemId] = {
      product_item_id: productData.product_item_id,
      sku: productData.sku,
      product_name: productData.product_name,
      quantity_per_unit: qtyPerUnit,
      required_quantity: requiredQty,
      available_stock: availableStock,
      is_sufficient: isSufficient,
    };

    var html = "";
    var statusClass = isSufficient ? "label-success" : "label-danger";
    var statusIcon = isSufficient ? "fa-check" : "fa-times";
    var statusText = isSufficient
      ? opsdeskLang.sufficient
      : opsdeskLang.insufficient;

    html +=
      '<tr data-combo-item-id="' +
      comboItemId +
      '" style="background-color: #fffacd;">';
    html += "<td>" + escapeHtml(productData.sku) + "</td>";
    html += "<td>" + escapeHtml(productData.product_name) + "</td>";
    html += '<td class="text-right">' + formatNumber(availableStock) + "</td>";
    html += '<td class="text-right">';
    html += '<div class="input-group" style="width: 120px; margin: 0 auto;">';
    html +=
      '<input type="number" class="form-control opsdesk-qty-input" value="' +
      formatNumber(requiredQty) +
      '" min="0" step="1" data-item-id="' +
      comboItemId +
      '" data-qty-per-unit="' +
      qtyPerUnit +
      '">';
    html += "</div>";
    html += "</td>";
    html += '<td class="text-center"><span class="label ' + statusClass + '">';
    html +=
      '<i class="fa ' + statusIcon + '"></i> ' + statusText + "</span></td>";
    html += actionButtonsHtml(comboItemId);
    html += "</tr>";

    var $emptyRow = $("#opsdesk_availability_body tr#opsdesk_empty_row");
    if ($emptyRow.length) {
      $emptyRow.remove();
    }

    $("#opsdesk_availability_body").append(html);
    attachRowHandlers();

    updateFulfillableSummary();
    if ($("#opsdesk_create_order_btn").length) {
      $("#opsdesk_create_order_btn").attr("href", buildCreateOrderUrl());
    }
  }

  function updateFulfillableSummary() {
    var allItems = $("#opsdesk_availability_body tr:not(#opsdesk_empty_row)");
    var allSufficient = true;
    var itemCount = 0;

    allItems.each(function () {
      var $row = $(this);
      itemCount++;

      // Check if any status label is danger (insufficient)
      if ($row.find("td:eq(4) .label-danger").length > 0) {
        allSufficient = false;
      }
    });

    var summaryLabel = $("#opsdesk_summary_label");
    var summaryContainer = $("#opsdesk_summary");

    // Show summary only if there are items
    if (itemCount > 0) {
      summaryContainer.removeClass("hide");

      if (allSufficient) {
        summaryLabel
          .removeClass("label-danger")
          .addClass("label-success")
          .html(
            '<i class="fa fa-check-circle"></i> ' + opsdeskLang.fulfillable,
          );
      } else {
        summaryLabel
          .removeClass("label-success")
          .addClass("label-danger")
          .html(
            '<i class="fa fa-exclamation-circle"></i> ' +
              opsdeskLang.not_fulfillable,
          );
      }
    } else {
      summaryContainer.addClass("hide");
    }

    updateCreateOrderButton((allSufficient || stockCheckBypassed()) && itemCount > 0);
  }

  function collectOrderOverrides() {
    var overrides = {
      substitutions: {},
      removed: [],
      added: [],
      quantities: {},
    };

    if (editedItems !== null) {
      $.each(editedItems, function (itemId, data) {
        if (data.removed) {
          overrides.removed.push(String(itemId));
        } else if (data.quantity_needed !== undefined) {
          overrides.quantities[String(itemId)] = parseFloat(data.quantity_needed);
        }
      });
    }

    if (addedItems !== null) {
      $.each(addedItems, function (itemId, data) {
        overrides.added.push({
          product_item_id: data.product_item_id,
          sku: data.sku,
          product_name: data.product_name,
          quantity_per_unit: data.quantity_per_unit,
          required_quantity: data.required_quantity,
        });
      });
    }

    if (substitutions !== null) {
      $.each(substitutions, function (itemId, productId) {
        if (String(itemId).indexOf("new_") === 0) {
          return;
        }
        overrides.substitutions[String(itemId)] = productId;
      });
    }

    return overrides;
  }

  function buildCreateOrderUrl() {
    var comboId = $("#opsdesk_combo_id").val();
    var qty = parseInt($("#opsdesk_order_quantity").val(), 10) || 1;
    var overrides = collectOrderOverrides();
    var url =
      opsdeskNewOrderUrl +
      "?combo_id=" +
      encodeURIComponent(comboId) +
      "&quantity=" +
      encodeURIComponent(qty) +
      "&items=" +
      encodeURIComponent(JSON.stringify(overrides));

    return url;
  }

  function updateCreateOrderButton(show) {
    if (typeof opsdeskCanCreateOrder === "undefined" || !opsdeskCanCreateOrder) {
      return;
    }

    // With the stock check bypassed the order can always be raised, so the
    // button only needs a combo to carry over to the order form.
    if (stockCheckBypassed() && $("#opsdesk_combo_id").val()) {
      show = true;
    }

    if (show) {
      $("#opsdesk_create_order_wrap").removeClass("hide");
      $("#opsdesk_create_order_btn").attr("href", buildCreateOrderUrl());
    } else {
      $("#opsdesk_create_order_wrap").addClass("hide");
    }
  }

  function populateProductSelector() {
    var comboId = $("#opsdesk_combo_id").val();
    if (!comboId) {
      return;
    }

    var postData = {
      combo_id: comboId,
      action: "get_available_products",
    };

     $.extend(postData, getCsrfPostData());

     $.post(opsdeskAjaxUrl, postData).done(function (response) {
      if (typeof response === "string") {
        try {
          response = JSON.parse(response);
        } catch (e) {
          return;
        }
      }

      if (response.success && response.products) {
        var $selector = $("#opsdesk_product_selector");
        var html = '<option value=""></option>';

        $.each(response.products, function (i, product) {
          html += '<option value="' + product.id + '"';
          if (product.subtext) {
            html += ' data-subtext="' + escapeHtml(product.subtext) + '"';
          }
          html += ">" + escapeHtml(product.label) + "</option>";
        });

        $selector.html(html);
        $selector.selectpicker("refresh");
      }
    });
  }

  function resetTable() {
    $("#opsdesk_availability_body").html(
      '<tr id="opsdesk_empty_row"><td colspan="6" class="text-center text-muted">' +
        ($("#opsdesk_empty_row").text() || "") +
        "</td></tr>",
    );
    $("#opsdesk_summary").addClass("hide");
    updateCreateOrderButton(false);
    updateAddItemButton(false);
  }

  function showError(msg) {
    $("#opsdesk_alert")
      .removeClass("hide alert-success")
      .addClass("alert-danger")
      .text(msg || opsdeskLang.error);
    resetTable();
  }
  var listdata;
  function renderTable(data) {
    var html = "";
    var components = data.components || [];
    listdata = components; // Store the components data for later use
    if (components.length === 0) {
      html = '<tr><td colspan="6" class="text-center text-muted">—</td></tr>';
    } else {
      $.each(components, function (i, row) {
        var statusClass = row.is_sufficient ? "label-success" : "label-danger";
        var statusIcon = row.is_sufficient ? "fa-check" : "fa-times";
        var statusText = row.is_sufficient
          ? opsdeskLang.sufficient
          : opsdeskLang.insufficient;

        html += '<tr data-combo-item-id="' + row.combo_item_id + '">';
        html += "<td>" + escapeHtml(row.sku) + "</td>";
        html += "<td>" + escapeHtml(row.product_name) + "</td>";
        html +=
          '<td class="text-right">' +
          formatNumber(row.available_stock) +
          "</td>";
        html += '<td class="text-right">';
        html +=
          '<div class="input-group" style="width: 120px; margin: 0 auto;">';
        html +=
          '<input type="number" class="form-control opsdesk-qty-input" value="' +
          formatNumber(row.required_quantity) +
          '" min="0" step="1" data-item-id="' +
          row.combo_item_id +
          '" data-qty-per-unit="' +
          row.quantity_per_unit +
          '">';
        html += "</div>";
        html += "</td>";
        html +=
          '<td class="text-center"><span class="label ' + statusClass + '">';
        html +=
          '<i class="fa ' +
          statusIcon +
          '"></i> ' +
          statusText +
          "</span></td>";
        html += actionButtonsHtml(row.combo_item_id);
        html += "</tr>";
      });
    }

    $("#opsdesk_availability_body").html(html);

    var summaryLabel = $("#opsdesk_summary_label");
    if (data.is_fulfillable) {
      summaryLabel
        .removeClass("label-danger")
        .addClass("label-success")
        .text(opsdeskLang.fulfillable);
    } else {
      summaryLabel
        .removeClass("label-success")
        .addClass("label-danger")
        .text(opsdeskLang.not_fulfillable);
    }
    $("#opsdesk_summary").removeClass("hide");

    updateCreateOrderButton(data.is_fulfillable || stockCheckBypassed());
    applyLocalEditsAfterRender();
    attachRowHandlers();
    updateFulfillableSummary();
  }

  function updateRowProduct(itemId, productData, isSubstitution) {
    var $row = $('tr[data-combo-item-id="' + itemId + '"]');
    if ($row.length === 0 || !productData) {
      return;
    }

    $row.find("td:eq(0)").text(productData.sku || "");
    var nameHtml = escapeHtml(productData.product_name || "");
    if (isSubstitution) {
      nameHtml +=
        ' <span class="label label-warning">' +
        escapeHtml(opsdeskLang.substitution || "") +
        "</span>";
    }
    $row.find("td:eq(1)").html(nameHtml);
    $row.find("td:eq(2)").text(formatNumber(productData.available_stock));
    var requiredQty = parseFloat($row.find(".opsdesk-qty-input").val()) || 0;
    updateItemStatus(itemId, requiredQty, productData.available_stock);
  }

  function applyLocalEditsAfterRender() {
    if (editedItems !== null) {
      $.each(editedItems, function (itemId, data) {
        var $row = $('tr[data-combo-item-id="' + itemId + '"]');
        if (!$row.length) {
          return;
        }
        if (data.removed) {
          $row.remove();
          return;
        }
        if (data.quantity_needed !== undefined) {
          $row.find(".opsdesk-qty-input").val(data.quantity_needed);
          var availableStock =
            parseFloat($row.find("td:eq(2)").text().replace(/,/g, "")) || 0;
          updateItemStatus(itemId, data.quantity_needed, availableStock);
        }
      });
    }

    if (substitutions !== null && substitutionSnapshots !== null) {
      $.each(substitutions, function (itemId) {
        if (String(itemId).indexOf("new_") === 0) {
          return;
        }
        var snap = substitutionSnapshots[itemId];
        if (snap) {
          updateRowProduct(itemId, snap, true);
        }
      });
    }

    if (addedItems !== null) {
      var hasAdded = false;
      $.each(addedItems, function () {
        hasAdded = true;
        return false;
      });
      if (hasAdded) {
        $("#opsdesk_availability_body tr").filter(function () {
          return !$(this).attr("data-combo-item-id");
        }).remove();
      }
      $.each(addedItems, function (itemId, data) {
        if ($('tr[data-combo-item-id="' + itemId + '"]').length) {
          return;
        }
        var html = "";
        var statusClass = data.is_sufficient ? "label-success" : "label-danger";
        var statusIcon = data.is_sufficient ? "fa-check" : "fa-times";
        var statusText = data.is_sufficient
          ? opsdeskLang.sufficient
          : opsdeskLang.insufficient;
        html +=
          '<tr data-combo-item-id="' +
          itemId +
          '" style="background-color: #fffacd;">';
        html += "<td>" + escapeHtml(data.sku) + "</td>";
        html += "<td>" + escapeHtml(data.product_name) + "</td>";
        html +=
          '<td class="text-right">' + formatNumber(data.available_stock) + "</td>";
        html += '<td class="text-right">';
        html +=
          '<div class="input-group" style="width: 120px; margin: 0 auto;">';
        html +=
          '<input type="number" class="form-control opsdesk-qty-input" value="' +
          formatNumber(data.required_quantity) +
          '" min="0" step="1" data-item-id="' +
          itemId +
          '" data-qty-per-unit="' +
          data.quantity_per_unit +
          '">';
        html += "</div></td>";
        html +=
          '<td class="text-center"><span class="label ' +
          statusClass +
          '"><i class="fa ' +
          statusIcon +
          '"></i> ' +
          statusText +
          "</span></td>";
        html += actionButtonsHtml(itemId);
        html += "</tr>";
        $("#opsdesk_availability_body").append(html);
      });
    }
  }

  function attachRowHandlers() {
    $(".opsdesk-qty-input")
      .off("input change")
      .on("input change", function () {
        var itemId = $(this).data("item-id");
        var newQty = parseFloat($(this).val()) || 0;
        var availableStock =
          parseFloat(
            $(this).closest("tr").find("td:eq(2)").text().replace(/,/g, ""),
          ) || 0;

        updateItemQuantity(itemId, newQty);
        updateItemStatus(itemId, newQty, availableStock);
        updateFulfillableSummary();
        if ($("#opsdesk_create_order_btn").length) {
          $("#opsdesk_create_order_btn").attr("href", buildCreateOrderUrl());
        }
      });

    $(".opsdesk-remove-item")
      .off("click")
      .on("click", function () {
        var itemId = $(this).data("item-id");
        removeItem(itemId);
      });

    $(".opsdesk-sub-btn")
      .off("click")
      .on("click", function () {
        openProductModal("substitute", $(this).data("item-id"));
      });
  }

  function openProductModal(mode, itemId) {
    if (!$("#opsdesk_combo_id").val()) {
      alert(opsdeskLang.selectCombo || opsdeskLang.error);
      return;
    }

    $("#opsdesk_product_modal_mode").val(mode);
    $("#opsdesk_product_modal_item_id").val(itemId || "");
    $("#opsdesk_product_modal_title").text(
      mode === "substitute" ? opsdeskLang.substitute : opsdeskLang.addItem,
    );
    $("#opsdesk_product_modal_product_id").val("").selectpicker("refresh");
    $("#opsdesk_product_modal").modal("show");
  }

  function applyProductModal() {
    var mode = $("#opsdesk_product_modal_mode").val();
    var productId = $("#opsdesk_product_modal_product_id").val();
    var itemId = $("#opsdesk_product_modal_item_id").val();

    if (!productId) {
      return;
    }

    getProductDetailsForAdding(productId, function (productData) {
      if (!productData) {
        alert(opsdeskLang.error);
        return;
      }

      if (mode === "substitute") {
        substituteItem(itemId, productData);
      } else {
        newCombo = true;
        $("#opsdesk_check_btn").html(
          '<i class="fa fa-search"></i> Check New Availability',
        );
        addItemToTable(productData);
      }

      $("#opsdesk_product_modal").modal("hide");
    });
  }

  function substituteItem(itemId, productData) {
    if (!itemId || !productData) {
      return;
    }

    if (addedItems !== null && addedItems[itemId]) {
      addedItems[itemId].product_item_id = productData.product_item_id;
      addedItems[itemId].sku = productData.sku;
      addedItems[itemId].product_name = productData.product_name;
      addedItems[itemId].available_stock = productData.available_stock;
      updateRowProduct(itemId, productData, false);
    } else {
      if (substitutions === null) {
        substitutions = {};
      }
      if (substitutionSnapshots === null) {
        substitutionSnapshots = {};
      }
      substitutions[itemId] = productData.product_item_id;
      substitutionSnapshots[itemId] = productData;
      updateRowProduct(itemId, productData, true);
    }

    updateFulfillableSummary();
    if ($("#opsdesk_create_order_btn").length) {
      $("#opsdesk_create_order_btn").attr("href", buildCreateOrderUrl());
    }
  }

  function updateItemQuantity(itemId, newQty) {
    if (editedItems === null) {
      editedItems = {};
    }
    editedItems[itemId] = { quantity_needed: newQty };
  }

  function updateItemStatus(itemId, requiredQty, availableStock) {
    var $row = $('tr[data-combo-item-id="' + itemId + '"]');
    if ($row.length === 0) return;

    var isSufficient = availableStock >= requiredQty;
    var statusCell = $row.find("td:eq(4)");

    var statusClass = isSufficient ? "label-success" : "label-danger";
    var statusIcon = isSufficient ? "fa-check" : "fa-times";
    var statusText = isSufficient
      ? opsdeskLang.sufficient
      : opsdeskLang.insufficient;

    statusCell.html(
      '<span class="label ' +
        statusClass +
        '">' +
        '<i class="fa ' +
        statusIcon +
        '"></i> ' +
        statusText +
        "</span>",
    );
  }

  function removeItem(itemId) {
    if (addedItems !== null && addedItems[itemId]) {
      delete addedItems[itemId];
    } else {
      if (editedItems === null) {
        editedItems = {};
      }
      editedItems[itemId] = { removed: true };
      if (substitutions !== null) {
        delete substitutions[itemId];
      }
      if (substitutionSnapshots !== null) {
        delete substitutionSnapshots[itemId];
      }
    }
    $('tr[data-combo-item-id="' + itemId + '"]').fadeOut(300, function () {
      $(this).remove();
      updateFulfillableSummary();
      if ($("#opsdesk_create_order_btn").length) {
        $("#opsdesk_create_order_btn").attr("href", buildCreateOrderUrl());
      }
    });
  }

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

  function debouncedFetch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchAvailability, 350);
  }

  $(function () {
    var checkBtnDefaultHtml = $("#opsdesk_check_btn").html();

    // Re-checks the rows currently in the table. Used once items have been
    // added or removed locally, because the availability endpoint only knows
    // the combo's saved items.
    function newAvailability() {
      var allItems = $("#opsdesk_availability_body tr:not(#opsdesk_empty_row)");
      var allSufficient = allItems.length > 0;

      allItems.each(function () {
        var $row = $(this);
        var requiredQty = parseFloat($row.find("td:eq(3) input").val()) || 0;
        var availableStock =
          parseFloat($row.find("td:eq(2)").text().replace(/,/g, "")) || 0;

        updateItemStatus($row.data("combo-item-id"), requiredQty, availableStock);

        if (availableStock < requiredQty) {
          allSufficient = false;
        }
      });

      updateFulfillableSummary();

      if (allSufficient) {
        alert(
          "Inventory is sufficient for all items. You can proceed with the order.",
        );
      } else if (!stockCheckBypassed()) {
        alert(
          "Inventory insufficient for one or more items. Please adjust the quantities.",
        );
      }
    }

    $("#opsdesk_check_btn").on("click", function () {
      if (newCombo) newAvailability();
      else fetchAvailability();
    });

    $("#opsdesk_seed_stock_btn").on("click", function () {
      if (
        !confirm(
          "Update all inventory items with random stock (10-500)? This cannot be undone.",
        )
      ) {
        return;
      }

      var $btn = $(this);
      $btn.prop("disabled", true);

       var postData = {};
       $.extend(postData, getCsrfPostData());

       $.post(
         opsdeskAjaxUrl.replace("ajax_availability", "seed_random_stock"),
        postData,
      )
        .done(function (response) {
          $btn.prop("disabled", false);
          if (typeof response === "string") {
            try {
              response = JSON.parse(response);
            } catch (e) {
              alert("Error updating stock.");
              return;
            }
          }

          if (response.success) {
            alert("✓ Stock updated: " + response.updated + " items");
            fetchAvailability();
          } else {
            alert("Error: " + response.message);
          }
        })
        .fail(function () {
          $btn.prop("disabled", false);
          alert("Error updating stock.");
        });
    });

    $("#opsdesk_combo_id").on("change", function () {
      newCombo = false;
      $("#opsdesk_check_btn").html(checkBtnDefaultHtml);
      updateCreateOrderButton(false);
      updateAddItemButton(false);
      debouncedFetch();
    });

    $("#opsdesk_order_quantity").on("input change", function () {
      var need = parseFloat($(this).val()) || 0; //Qty of combos needed
      var allItems = $("#opsdesk_availability_body tr:not(#opsdesk_empty_row)");

      allItems.each(function () {
        var $row = $(this);
        var $input = $row.find("td:eq(3) input");
        var qtyPerUnit = parseFloat($input.data("qty-per-unit")) || 1;
        var newRequired = qtyPerUnit * need;

        $input.val(newRequired);

        var availableStock =
          parseFloat($row.find("td:eq(2)").text().replace(/,/g, "")) || 0;
        updateItemStatus(
          $row.data("combo-item-id"),
          newRequired,
          availableStock,
        );
      });

      updateFulfillableSummary();
    });

    $("#opsdesk_open_add_item").on("click", function () {
      openProductModal("add");
    });

    $("#opsdesk_product_modal_apply").on("click", applyProductModal);
  });
})(jQuery);
