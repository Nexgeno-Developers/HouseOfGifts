/**
 * OpsDesk — Sales inventory viewer AJAX handler.
 */
(function ($) {
    'use strict';

    var debounceTimer = null;

    function fetchAvailability() {
        var comboId = $('#opsdesk_combo_id').val();
        var qty = parseFloat($('#opsdesk_order_quantity').val()) || 0;

        if (!comboId || qty <= 0) {
            resetTable();
            return;
        }

        $('#opsdesk_loading').removeClass('hide');
        $('#opsdesk_alert').addClass('hide');

        var postData = {
            combo_id: comboId,
            order_quantity: qty
        };

        if (typeof csrfData !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        $.post(opsdeskAjaxUrl, postData).done(function (response) {
            $('#opsdesk_loading').addClass('hide');

            if (typeof response === 'string') {
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

            renderTable(response.data);
        }).fail(function () {
            $('#opsdesk_loading').addClass('hide');
            showError();
        });
    }

    function resetTable() {
        $('#opsdesk_availability_body').html(
            '<tr id="opsdesk_empty_row"><td colspan="5" class="text-center text-muted">' +
            ($('#opsdesk_empty_row').text() || '') + '</td></tr>'
        );
        $('#opsdesk_summary').addClass('hide');
    }

    function showError(msg) {
        $('#opsdesk_alert')
            .removeClass('hide alert-success')
            .addClass('alert-danger')
            .text(msg || opsdeskLang.error);
        resetTable();
    }

    function renderTable(data) {
        var html = '';
        var components = data.components || [];

        if (components.length === 0) {
            html = '<tr><td colspan="5" class="text-center text-muted">—</td></tr>';
        } else {
            $.each(components, function (i, row) {
                var statusClass = row.is_sufficient ? 'label-success' : 'label-danger';
                var statusIcon = row.is_sufficient ? 'fa-check' : 'fa-times';
                var statusText = row.is_sufficient ? opsdeskLang.sufficient : opsdeskLang.insufficient;

                html += '<tr>';
                html += '<td>' + escapeHtml(row.sku) + '</td>';
                html += '<td>' + escapeHtml(row.product_name) + '</td>';
                html += '<td class="text-right">' + formatNumber(row.available_stock) + '</td>';
                html += '<td class="text-right">' + formatNumber(row.required_quantity) + '</td>';
                html += '<td class="text-center"><span class="label ' + statusClass + '">';
                html += '<i class="fa ' + statusIcon + '"></i> ' + statusText + '</span></td>';
                html += '</tr>';
            });
        }

        $('#opsdesk_availability_body').html(html);

        var summaryLabel = $('#opsdesk_summary_label');
        if (data.is_fulfillable) {
            summaryLabel.removeClass('label-danger').addClass('label-success')
                .text(opsdeskLang.fulfillable);
        } else {
            summaryLabel.removeClass('label-success').addClass('label-danger')
                .text(opsdeskLang.not_fulfillable);
        }
        $('#opsdesk_summary').removeClass('hide');
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        return $('<div/>').text(text).html();
    }

    function formatNumber(num) {
        return parseFloat(num).toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 4
        });
    }

    function debouncedFetch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchAvailability, 350);
    }

    $(function () {
        $('#opsdesk_check_btn').on('click', fetchAvailability);

        $('#opsdesk_combo_id').on('change', debouncedFetch);
        $('#opsdesk_order_quantity').on('input change', debouncedFetch);
    });
})(jQuery);
