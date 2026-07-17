# OpsDesk Notifications — FR-021.1 to FR-021.8 Compliance Plan

## Context
The OpsDesk module already implements order notifications via Perfex's core
`add_notification()` (inserts into `tblnotifications` — no custom tables). A code
audit of `modules/opsdesk/helpers/opsdesk_helper.php`, the controller call sites,
and `application/helpers/database_helper.php` (`add_notification`) shows
FR-021.1–021.5, 021.7, 021.8 are **already satisfied and wired**. The only gap is
**FR-021.6**: the "🔴 High Priority — " prefix is currently applied ONLY in
`opsdesk_notify_new_order()`; it is missing from status-change and cancellation
messages. User decision: apply the prefix to **all** notification types when
`priority === 1`.

## Current wiring (verified)
- `save_order()` → `opsdesk_notify_new_order($order_id, $created_by)` (FR-021.1)
- `update_order_status()` (non-cancelled) → `opsdesk_notify_status_change(...)` (FR-021.2)
- `cancel_order()` → `opsdesk_notify_cancellation(...)` (FR-021.3 / FR-021.4)
- Self-notify guards present in all three functions (FR-021.8)
- `opsdesk_get_operations_staff()` = admins + staff with `view` on `opsdesk_orders`
  (interpreted as "Operations staff and Admins").

## Changes required

### 1. `opsdesk_notify_status_change()` — `helpers/opsdesk_helper.php` (~line 830)
Add the High-Priority prefix to the message, mirroring `opsdesk_notify_new_order()`:
```php
$order = $CI->opsdesk_orders_model->get($order_id);
if (!$order) { return; }

$created_by = (int) $order->created_by;
if ($created_by === (int) $changed_by) { return; }

$prefix = (int) $order->priority === 1 ? _l('opsdesk_priority_high_prefix') : '';
$status_label = ucfirst(str_replace('_', ' ', $new_status));
$message = $prefix . _l('opsdesk_notify_status_updated', [
    (int) $order->id,
    e($order->combo_name),
    e($status_label),
]);
```
Language key `opsdesk_notify_status_updated` = `'Your Order #%1$s — %2$s status updated to %3$s'`
already supports a leading prefix (plain concatenation). No key change needed.

### 2. `opsdesk_notify_cancellation()` — `helpers/opsdesk_helper.php` (~line 873)
Prepend the prefix to BOTH messages (sales-cancels / ops-cancels branches):
```php
$prefix = (int) $order->priority === 1 ? _l('opsdesk_priority_high_prefix') : '';
// in the created_by === cancelled_by branch:
$message = $prefix . _l('opsdesk_notify_cancelled_by_sales', [...]);
// in the else branch:
$message = $prefix . _l('opsdesk_notify_cancelled_by_ops', [...]);
```
Keys `opsdesk_notify_cancelled_by_sales` / `_ops` already support a leading prefix.
No key change needed.

### 3. No controller / model / migration / language-key changes required.
FR-021.7 is already met (core `add_notification`). FR-021.5 content (ID, combo,
status, link) already present in every message.

## Notes / interpretations (carry into implementation)
- "Sales" = order `created_by`; "Operations" = any staff in
  `opsdesk_get_operations_staff()`. Cancellation routing keys off
  `created_by === cancelled_by`. Keep as-is.
- The "new status" portion of FR-021.5 is N/A for new-order notifications and is
  implicitly "cancelled" for cancellation notifications — both acceptable per
  "where applicable".
- There were previously DUPLICATE notify-function definitions in this helper
  (fixed earlier). Before shipping, grep the helper to confirm exactly ONE
  definition of each of: `opsdesk_notify_new_order`, `opsdesk_notify_status_change`,
  `opsdesk_notify_cancellation`, `opsdesk_get_operations_staff`.

## Validation
1. `php -l` on `helpers/opsdesk_helper.php` (no syntax errors).
2. Grep helper for duplicate function definitions (expect 1 each).
3. Functional (manual, in browser):
   - Create a NORMAL order → Ops/Admin staff get "New Order #…" (no prefix).
   - Create a HIGH-PRIORITY order → Ops/Admin get "🔴 High Priority — New Order #…".
   - As Ops, move that order In Progress → creator gets
     "🔴 High Priority — Your Order #… status updated to In Progress".
   - As Sales, cancel own order → Ops get
     "🔴 High Priority — Order #… cancelled by Sales (…)".
   - As Ops, cancel order → creator gets
     "🔴 High Priority — Your Order #… was cancelled by Operations (…)".
   - Confirm actor never receives their own action's notification (FR-021.8).
   - Confirm `tblnotifications` rows created (confirm no custom notification table).

## Open questions (none blocking)
- None. All requirements resolvable with the above.
