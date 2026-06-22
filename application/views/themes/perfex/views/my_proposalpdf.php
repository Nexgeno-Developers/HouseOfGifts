<?php

defined('BASEPATH') or exit('No direct script access allowed');

$dimensions = $pdf->getPageDimensions();

// $pdf->SetMargins(7,3,7);
// $pdf->SetHeaderMargin(0);
// $pdf->SetFooterMargin(0);
// $pdf->SetAutoPageBreak(false,0);

$pdf->SetY(0);

/* =========================
   CENTER TITLE (LIKE INVOICE)
========================= */
$pdf->Ln(0);

$pdf->writeHTMLCell(
    0,
    '',
    '',
    '',
    '',
    0,
    1,
    false,
    true,
    'C',
    true
);

$pdf->Ln(0);

/* =========================
   HEADER : LOGO LEFT + TITLE RIGHT
========================= */

$logo_html = '';
if (function_exists('pdf_logo_url')) {
    $logo_html = pdf_logo_url(); // returns <img>
}

$header_table = '
<table width="99%" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
    <tr>
        <td width="50%" align="left">
            ' . $logo_html . '
        </td>

        <td width="50%" align="right" style="font-size:22px; font-weight:bold;">
            PROPOSAL
        </td>
    </tr>
</table>
';

$pdf->writeHTML($header_table, true, false, false, false, '');
$pdf->Ln(-4);


/* =========================
   SAFE STATUS + ASSIGNED (NO ERRORS)
========================= */
$proposal_status_text = (function_exists('format_proposal_status'))
    ? format_proposal_status($proposal->status, '', false)
    : (string) $proposal->status;

$proposal_status_color = '#000';
if (function_exists('proposal_status_color_pdf')) {
    // assume it returns "r,g,b" like invoice_status_color_pdf
    $proposal_status_color = 'rgb(' . proposal_status_color_pdf($proposal->status) . ')';
}

$proposal_status_html = '<span style="color:' . $proposal_status_color . ';text-transform:uppercase;">' . $proposal_status_text . '</span>';

$assigned_name = '-';
if (isset($proposal->assigned) && !empty($proposal->assigned)) {
    $assigned_name = get_staff_full_name($proposal->assigned);
} elseif (isset($proposal->sale_agent) && !empty($proposal->sale_agent)) {
    // fallback if your build uses sale_agent field
    $assigned_name = get_staff_full_name($proposal->sale_agent);
}

/* =========================
   HEADER TABLE (LIKE YOUR INVOICE TABLE)
========================= */
$open_till = (!empty($proposal->open_till)) ? _d($proposal->open_till) : '-';

$org_block = '';
// if (function_exists('pdf_logo_url')) {
//     $org_block .= pdf_logo_url() . '<br/>';
// }
$org_block .= format_organization_info();

$client_block = '' . format_proposal_info($proposal, 'pdf');

$info_table = '
<table width="99%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:14px;">
    <tr>
        <td border="1" rowspan="3" width="60%" valign="top">' . $org_block . '
        </td>
        <td border="1" width="40%"><strong>Proposal No:</strong><br>' . $number . '
        </td>
    </tr>
    <tr>
        <td border="1"><strong>Date:</strong><br>' . _d($proposal->date) . '
        </td>
    </tr>
    <tr>
        <td border="1"><strong>Open Till:</strong><br>' . $open_till . '
        </td>
    </tr>

    <tr>
        <td border="1" rowspan="2" valign="top">' . $client_block . '
        </td>
        <td border="1"><strong>Status:</strong><br>' . $proposal_status_html . '
        </td>
    </tr>
    <tr>
        <td border="1"><strong>' . _l('sale_agent_string') . ':</strong><br>' . $assigned_name . '
        </td>
    </tr>

    
</table>
';


// <tr>
//         <td border="1" colspan="2"><strong>Subject:</strong><br>' . $proposal->subject . '
//         </td>
//     </tr>
    
    
$pdf->writeHTML($info_table, true, false, false, false, '');
$pdf->Ln(1);

/* =========================
   ITEMS TABLE (BORDERED LIKE INVOICE)
========================= */
$items = get_items_table_data($proposal, 'proposal', 'pdf')->set_headings('estimate');
// $tblhtml = $items->table();

// add class only to FIRST table tag
$tblhtml = preg_replace('/<table\b/', '<table class="items-table"', $tblhtml, 1);

$style = '
<style>
.items-table th,
.items-table td {
    border:1px solid #000;
    padding:6px;
}
.items-table thead th {
    font-weight:bold;
}
</style>
';

$pdf->writeHTML($style . $tblhtml, true, false, false, false, '');
$pdf->Ln(-8);



/* =========================
   ITEMS TABLE (FIXED 10 ROWS)
========================= */

$items = get_items_table_data($proposal, 'proposal', 'pdf')->set_headings('estimate');

$tblhtml = $items->table();


/* ========= FORCE 10 ROWS ========= */

// count real items
$itemCount = count($proposal->items ?? []);

// calculate blanks
$emptyRows = 10 - $itemCount;

if ($emptyRows > 0) {

    $blankRow = '
    <tr>
        <td style="border:1px solid #000; height:24px;"></td>
        <td style="border:1px solid #000;"></td>
        <td style="border:1px solid #000;"></td>
        <td style="border:1px solid #000;"></td>
        <td style="border:1px solid #000;"></td>
        <td style="border:1px solid #000;"></td>
        <td style="border:1px solid #000;"></td>
    </tr>';

    $tblhtml = str_replace(
        '</tbody>',
        str_repeat($blankRow, $emptyRows) . '</tbody>',
        $tblhtml
    );
}


/* ========= STYLE ========= */

$tblhtml = preg_replace('/<table\b/', '<table class="items-table"', $tblhtml, 1);

$style = '
<style>
.items-table th,
.items-table td {
    border:1px solid #000;
    padding:6px;
}
.items-table thead th {
    font-weight:bold;
}
</style>
';


/* ========= PRINT ONCE ========= */

$pdf->writeHTML($style . $tblhtml, true, false, false, false, '');
$pdf->Ln(-8);

/* =========================
   TOTALS TABLE (BORDERED LIKE INVOICE)
========================= */
$tbltotal  = '';
$tbltotal .= '<table width="99%" cellpadding="6" style="border:1px solid #000; font-size:' . ($font_size + 4) . 'px">';

$tbltotal .= '
<tr>
    <td align="right" width="85%" style="border:1px solid #000;"><strong>' . _l('estimate_subtotal') . '</strong></td>
    <td align="right" width="15%" style="border:1px solid #000;">' . app_format_money($proposal->subtotal, $proposal->currency_name) . '</td>
</tr>';

if (is_sale_discount_applied($proposal)) {
    $tbltotal .= '
    <tr>
        <td align="right" width="85%" style="border:1px solid #000;"><strong>' . _l('estimate_discount');
    if (is_sale_discount($proposal, 'percent')) {
        $tbltotal .= ' (' . app_format_number($proposal->discount_percent, true) . '%)';
    }
    $tbltotal .= '</strong></td>
        <td align="right" width="15%" style="border:1px solid #000;">-' . app_format_money($proposal->discount_total, $proposal->currency_name) . '</td>
    </tr>';
}

foreach ($items->taxes() as $tax) {
    $tbltotal .= '
    <tr>
        <td align="right" width="85%" style="border:1px solid #000;"><strong>' . $tax['taxname'] . ' (' . app_format_number($tax['taxrate']) . '%)</strong></td>
        <td align="right" width="15%" style="border:1px solid #000;">' . app_format_money($tax['total_tax'], $proposal->currency_name) . '</td>
    </tr>';
}

if ((int) $proposal->adjustment != 0) {
    $tbltotal .= '
    <tr>
        <td align="right" width="85%" style="border:1px solid #000;"><strong>' . _l('estimate_adjustment') . '</strong></td>
        <td align="right" width="15%" style="border:1px solid #000;">' . app_format_money($proposal->adjustment, $proposal->currency_name) . '</td>
    </tr>';
}

$tbltotal .= '
<tr style="background-color:#e5e7eb;">
    <td align="right" width="85%" style="border:1px solid #000;"><strong>' . _l('estimate_total') . '</strong></td>
    <td align="right" width="15%" style="border:1px solid #000;">' . app_format_money($proposal->total, $proposal->currency_name) . '</td>
</tr>';

$tbltotal .= '</table>';

$pdf->writeHTML($tbltotal, true, false, false, false, '');
$pdf->Ln(-6);

/* =========================
   TOTAL IN WORDS (LIKE INVOICE)
========================= */
if (get_option('total_to_words_enabled') == 1) {
    $amount_in_words = _l('num_word') . ': ' . $CI->numberword->convert($proposal->total, $proposal->currency_name);

    $amount_words_table = '
    <table width="99%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:13px; text-align:right">
        <tr>
            <td style="border:1px solid #000; font-weight:bold;">
                ' . $amount_in_words . '
            </td>
        </tr>
    </table>';

    $pdf->writeHTML($amount_words_table, true, false, false, false, '');
    $pdf->Ln(-8);
}


/* =========================
   OFFLINE PAYMENT DETAILS (TABLE FORMAT)
========================= */
$pdf->Ln(2);
$qrPath = FCPATH . 'qrcode.png';

$offline_payment_table = '
<table width="99%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:13px;">

    <tr>
        <td colspan="5" style="border:1px solid #000; font-weight:bold; background-color:#f3f4f6; text-align:center;">
            Payment Details
        </td>
    </tr>

    <tr style="font-weight:bold;">
        <td width="20%" style="border:1px solid #000;">Bank Name</td>
        <td width="20%" style="border:1px solid #000;">A/c Holder’s Name</td>
        <td width="20%" style="border:1px solid #000;">A/C No</td>
        <td width="20%" style="border:1px solid #000;">Branch & IFSC Code</td>
        <td width="20%" style="border:1px solid #000; text-align:center;">Scan QR Code</td>
    </tr>

    <tr>
        <td style="border:1px solid #000;">HDFC BANK</td>
        <td style="border:1px solid #000;">C S ENTERPRISE</td>
        <td style="border:1px solid #000;">50200077784082</td>
        <td style="border:1px solid #000;">VILEPARLE & HDFC0000227</td>

        <td align="center" style="border:1px solid #000;">
            <img src="'.$qrPath.'" width="100" height="100">
        </td>
    </tr>

</table>
';

$pdf->writeHTML($offline_payment_table, true, false, false, false, '');


/* =========================
   OPTIONAL: PRINT PROPOSAL BODY CONTENT (IF YOU WANT)
   - If your proposal template has {proposal_items}, we already printed items above.
   - If you still want the proposal->content text, print it at the end.
========================= */
if (!empty($proposal->content)) {
    $content = $proposal->content;

    // prevent duplicate items if template contains {proposal_items}
    $content = str_replace('{proposal_items}', '', $content);

    // print only if meaningful remains
    if (trim(strip_tags($content)) !== '') {
        $pdf->Ln(4);
        $pdf->writeHTML('<div style="font-size:12px;">' . $content . '</div>', true, false, true, false, '');
    }
}