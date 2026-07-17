# OpsDesk Phase 3 — Priority Orders & Notifications (Implementation Plan)

## Context
Phase 3 adds two capabilities to the OpsDesk Perfex module:
1. **Priority flag** on orders (Normal=0 / High=1) — Sales sets it at creation, Operations sees/updates it, list sorts High-first.
2. **In-app Perfex notifications** — native `tblnotifications` (via `add_notification()`) fired after DB commit for: new order (Ops+Admin), status change (creator), cancellation (the other party).

## Current Code State (verified)
- `migrations/106_version_106.php` **already exists** and adds `priority TINYINT(1) DEFAULT 0` + index to `opsdesk_orders`, bumps version to 1.0.6. (FRD references "migration 105" but the repo reserves 105 for the Phase 2 schema hotfix; 106 is the correct file. **No code change needed for the migration — it is done.**)
- `opsdesk.php` module version is already `1.0.6`.
- **Not yet implemented**: model `update_priority` + default sort, notification helper functions, controller wiring + `update_priority` endpoint, view changes (form field, list column/filter, detail badge + inline edit), JS inline toggle, language strings.
- `add_notification($values)` (application/helpers/database_helper.php:72) accepts `description`, `touserid`, `link`, `fromcompany`; silently returns false for inactive users — safe to call.
- `staff_can($capability, $feature, $staff_id)` (admin_helper.php:108) and `is_admin($staff_id)` exist; `staff_modelel->get('', true)` returns **arrays** (`$staff['staffid']`), not objects — the FRD's helper uses `$staff->staffid`; adapt to array access.
- `log_status_change()` signature: `(order_id, from, to, staff_id, notes=null)`.

## Implementation Tasks

### 1. Model — `models/Opsdesk_orders_model.php`
- **Default sort** (FR-020.5): in `get()` list branch, replace `order_by(created_at, DESC)` with two order_bys:
  ```php
  $this->db->order_by($this->table_orders . '.priority', 'DESC');
  $this->db->order_by($this->table_orders . '.created_at', 'DESC');
  ```
  Keep the single-order branch returning `priority` automatically (it selects `*`).
- **New method `update_priority($order_id, $priority, $staff_id)`** (FR-020.7/8): load order; cast/validate `$priority` to `in_array([0,1])` (reject/cast otherwise — clamp to 0/1); update `priority` + `updated_by`; call `log_status_change($order_id, $order->status, $order->status, $staff_id, 'Priority changed from Normal to High'...)` with computed old/new label; return `['success'=>true]` or `['success'=>false,'message'=>...]` if order missing.
- Optionally add a `priority` filter param to `get()` (`options['priority']` => `WHERE priority = ?`) to support the list filter without JS-only solutions. Add `$priority = $options['priority'] ?? null;` and `if ($priority !== null && $priority !== '' && $priority !== 'all') $this->db->where(...priority, $priority);`.

### 2. Helper — `helpers/opsdesk_helper.php`
Add (FR-021):
- `opsdesk_get_operations_staff()` — loop `staff_model->get('', true)`, include if `is_admin($s['staffid'])` OR `staff_can('view', 'opsdesk_orders', $s['staffid'])`. Return array of arrays (preserve `staffid`, `firstname`, `lastname`).
- `opsdesk_notify_new_order($order_id, $created_by)` — load order + creator; prefix `🔴 High Priority — ` when `$order->priority == 1`; message `New Order #[ID] — [combo] (Qty: [x]) placed by [name]`; `add_notification([description, touserid, link=>admin/opsdesk/order/{id}, fromcompany=>1])` to each ops staff except `$created_by`. Escaper all user strings via `e()`.
- `opsdesk_notify_status_change($order_id, $new_status, $changed_by)` — skip if `created_by == changed_by`; message `Your Order #[ID] — [combo] status updated to [Status]` (Status via `ucfirst(str_replace('_',' ',$new_status))`); notify `created_by`.
- `opsdesk_notify_cancellation($order_id, $cancelled_by)` — if creator cancelled → notify all ops staff (exclude creator); else (ops cancelled) → notify creator. Messages per FR-021.3/4.
- All four call `add_notification()` only; never touch DB transaction.
- Load `staff_model` if needed (`$CI->load->model('staff_model')`) — models are autoloaded in Perfex but guard with `if (!$CI->load->model...)`.
- Helpers `opsdesk_get_priority_badge($priority)`/`opsdesk_get_priority_label($priority)` for view reuse (Normal→empty, High→red badge `label-danger` "High Priority").

### 3. Controller — `controllers/Opsdesk.php`
- **`save_order()`** (FR-021.1): pass `priority => (int)$this->input->post('priority')` (cast, default 0) into `create_order_with_reservation()` data. After success, call `opsdesk_notify_new_order($result['order_id'], get_staff_user_id());` AFTER commit (already outside transaction).
- **`update_order_status()`** (FR-021.2): after `$result['success']`, call `opsdesk_notify_status_change($order_id, $new_status, get_staff_user_id());`. Skip notification when `cancelled` handled by `cancel_order()` path (note: status `cancelled` routes through `cancel_order`; ensure no double-notify — guard so status-change notification is NOT fired when `$new_status === 'cancelled'`).
- **`cancel_order()`** (FR-021.3/4): after success, call `opsdesk_notify_cancellation($id, get_staff_user_id());`.
- **New `update_priority($order_id)`** (FR-020.7, new endpoint `POST /admin/opsdesk/update_priority/{id}`):
  - Permission: `if (!opsdesk_can_edit_orders()) access_denied(...)` (Sales blocked — matches "Operations/Admin only").
  - `$id` numeric check; `$priority = (int)$this->input->post('priority')`; validate `in_array($priority,[0,1])` else 404/error JSON.
  - `$res = $this->opsdesk_orders_model->update_priority($id, $priority, get_staff_user_id());`
  - Respond via `is_ajax_request()` → JSON `{success, message}`; else `set_alert` + redirect to order detail.

### 4. Views
- **`views/order_form.php`** (FR-020.1/2/3): add Priority field block **below Packing Type** (after line ~55, inside the row or new row). Two styled radio/select: Normal (default) / High Priority. Name `priority`, values `0`/`1`. Recommended: two radios styled grey/red-outline.
- **`views/orders_list.php`** (FR-020.4/5/6, US-021):
  - Add **Priority column** immediately after Order ID (`<th>` + `<td>` with `opsdesk_get_priority_badge($order['priority'])`; empty for Normal).
  - Add **Priority filter** `<select name="priority">` (All/High/Normal) next to the status `<select>` inside the GET form (onchange submit). Pass `$priority_filter` from controller.
  - Update `colspan` in empty-row to `!empty($global_view) ? 11 : 10`.
  - Controller `orders()` must read `$priority_filter = $this->input->get('priority')` and pass to `get()` options + view var; also add to status-filter chip active state optionally.
- **`views/order_detail.php`** (FR-020.9, US-022):
  - Add priority **badge next to status badge** in header title.
  - If `!empty($can_edit)`: show **Change Priority** button + inline toggle (two radios Normal/High) and hidden container. Use `data-order-id`.
  - Status log already renders `notes`; priority changes appear automatically via `log_status_change` notes (FR-020.8). Verify log display shows notes text (currently it shows from→to only; optionally append `$log['notes']` when present).

### 5. JS — `assets/js/opsdesk_orders.js`
- Add a block (new IIFE section or extend) for order detail inline priority toggle:
  - On "Change Priority" click → show inline radios, hide button.
  - On save → `$.post(admin_url('opsdesk/update_priority/'+id), {priority, csrf...})`; on success refresh badge in header + status log (optional: reload or prepend log entry); on fail show error.
  - Use existing `getCsrfPostData()` pattern.

### 6. Language — `language/english/opsdesk_lang.php`
Add Phase 3 strings: `opsdesk_priority`, `opsdesk_priority_normal`, `opsdesk_priority_high`, `opsdesk_priority_high_badge` ("High Priority"), `opsdesk_change_priority`, `opsdesk_filter_priority`, `opsdesk_priority_high_prefix` (not strictly needed), notification templates can be inline but prefer `_l` keys: `opsdesk_notify_new_order`, `opsdesk_notify_new_order_high`, `opsdesk_notify_status_updated`, `opsdesk_notify_cancelled_by_sales`, `opsdesk_notify_cancelled_by_ops`, `opsdesk_priority_changed_note`. Keep messages simple; concatenation in helper is acceptable.

## Validation / Test Alignment
- Run module activate (migration 106 executes) → `priority` column + index present.
- TC-030..042 functional + TC-043..047 negative (Sales POST update_priority → 403/access_denied; invalid priority clamps; nonexistent order → JSON error; self-notification excluded; no-ops-staff → loop 0, order still saves).
- Regression TC-048..051: existing creation flow unchanged, priority defaults to 0, combo CRUD/inventory unaffected, clean activate/uninstall (106 down() drops column).

## Risks / Notes
- **FRD migration mismatch**: repo uses 106, not 105 — already implemented; do NOT create a second migration 105 for priority.
- `staff_model->get('', true)` returns arrays → use `$s['staffid']`, `$s['firstname']`, `$s['lastname']` in helpers.
- Cancel via `update_order_status` with `cancelled` reuses `cancel_order` → ensure only `opsdesk_notify_cancellation` fires, not `opsdesk_notify_status_change`.
- Notification failure must never roll back order ops (call after commit; OK to wrap in try/catch and log).
- XSS: wrap all user-derived strings with `e()` in notification text.
