# OpsDesk Phase 3 — Priority Orders & In-App Notifications

## Status of prerequisites

Phase 2 runtime fixes are **already implemented** (previous turn): module version is `1.0.5`,
`install.php` + `migrations/105_version_105.php` add the 103/104 columns. **Therefore Phase 3 must
use version `1.0.6` and migration `106`** — `105` is taken.

## Corrections to the FRD (conflicts found against the actual codebase)

1. **Migration number/version collision** — FRD specifies `105_version_105.php` adding `priority`.
   That file already exists (schema hotfix). Phase 3 uses **`106_version_106.php`**, version **`1.0.6`**.
   - `opsdesk.php` header: `Version: 1.0.6` (currently `1.0.5`).
   - `install.php`: `add_option('opsdesk_module_version', '1.0.6')`.
   - New file `migrations/106_version_106.php`.
2. **Wrong migration base class** — FRD's migration `extends CI_Migration`. Module migrations MUST
   `extend App_module_migration` (the runner keys off `headers['version']` and provides `dbforge`/
   `field_exists`/`update_option`). Use `App_module_migration`.
3. **`opsdesk_get_operations_staff()` array/object bug** — FRD iterates `$staff['staffid']` then casts
   `(object)$staff` and the notify loops read `->staffid`. Inconsistent → fatal on the array read.
   Fix: return **consistent objects** (or consistent arrays). Reuse existing permission helpers
   `opsdesk_can_view_all_orders()` / `opsdesk_can_edit_orders()` instead of re-implementing.
4. **Task P3-002 (`opsdesk_run_upgrades()`) does not exist** — module migrations auto-run via Perfex's
   module migration runner on (re)activation / version bump. No manual upgrade function. Drop P3-002.
5. **Cancel notification double-fire risk** — `update_order_status()` can receive `status=cancelled`;
   the model delegates to `cancel_order()` internally. Wire ONE unified notify helper called once per
   controller after commit, guarding cancel vs status-change branches so we never double-notify and
   never send the "status updated" message for a cancellation.
6. **Permission reuse** — `update_priority` gate on existing `opsdesk_can_edit_orders()` (Operations+Admin),
   not a raw inline `staff_can`. Sales already blocked by that helper.

## Implementation tasks (ordered)

### T1 — Migration 106: add `priority` column (replaces FRD's 105)
File `modules/opsdesk/migrations/106_version_106.php`, class `Migration_Version_106 extends App_module_migration`:
- `up()`: if `!field_exists('priority', db_prefix().'opsdesk_orders')`, `dbforge->add_column`:
  `priority` TINYINT(1) NOT NULL DEFAULT 0, AFTER `notes`; then `ADD KEY idx_opsdesk_orders_priority (priority)`.
- `down()`: drop column if exists (mirror FRD; safe, idempotent guards).
- `update_option('opsdesk_module_version', '1.0.6');`

### T2 — Bump version
- `opsdesk.php` header `Version: 1.0.6`.
- `install.php` `add_option(... '1.0.6')`.

### T3 — Model: default sort by priority
`Opsdesk_orders_model::get()` — in the list branch (NOT the single-record `is_numeric($id)` branch),
change `order_by('created_at','DESC')` to:
```
$this->db->order_by('priority', 'DESC');
$this->db->order_by('created_at', 'DESC');
```
Single-record path returns `row()`; order_by there is harmless but leave unchanged for clarity.

### T4 — Model: `update_priority()`
Add to `Opsdesk_orders_model`:
```
public function update_priority($order_id, $priority, $staff_id) {
    $order = $this->get($order_id);
    if (!$order) return ['success'=>false,'message'=>_l('opsdesk_order_not_found')];
    $priority = $priority ? 1 : 0;                       // clamp to 0/1
    $old = $order->priority ? 'High' : 'Normal';
    $new = $priority ? 'High' : 'Normal';
    $this->db->update($this->table_orders,
        ['priority'=>(int)$priority,'updated_by'=>(int)$staff_id], ['id'=>(int)$order_id]);
    $this->log_status_change((int)$order_id, $order->status, $order->status, (int)$staff_id,
        'Priority changed from '.$old.' to '.$new);
    return ['success'=>true];
}
```
Reuses existing `log_status_change()` (5-arg signature confirmed).

### T5 — Helper: notification + operations-staff functions
Add to `opsdesk_helper.php` (no name collisions with existing helpers):
- `opsdesk_get_operations_staff()` → return array of **consistent objects** of staff who are admin OR
  have `opsdesk_orders` view capability. Implementation:
  ```
  $all = $CI->staff_model->get('', ['active'=>1]);
  $ops = [];
  foreach ($all as $s) {
      $id = (int)(is_array($s) ? $s['staffid'] : $s->staffid);
      if (is_admin($id) || staff_can('view','opsdesk_orders',$id) || staff_can('view_own','opsdesk_orders',$id)) {
          $ops[] = is_object($s) ? $s : (object)$s;
      }
  }
  return $ops;
  ```
  (keeps array/object uniform; avoids FRD's mixed-access bug.)
- `opsdesk_notify_new_order($order_id, $created_by)` — per FRD §10.1, but use `$order = $this->opsdesk_orders_model->get($order_id)` (object). Skip creator in ops loop. `add_notification([description, touserid, link=>'admin/opsdesk/order/'.$order_id, fromcompany=>1])`.
- `opsdesk_notify_status_change($order_id, $new_status, $changed_by)` — skip if creator == changer.
- `opsdesk_notify_cancellation($order_id, $cancelled_by)` — direction by creator check (FRD §10.1).
- `opsdesk_notify_after_change($order_id, $new_status, $changed_by)` — **unified dispatcher**:
  if `$new_status === 'cancelled'` → `opsdesk_notify_cancellation(...)`; else → `opsdesk_notify_status_change(...)`.
  Guards against double-fire; used by both controllers.

### T6 — Controller: wire notifications AFTER commit
In `Opsdesk.php`:
- `save_order()` — after `$result = create_order_with_reservation(...)` and `$result['success']`, call
  `opsdesk_notify_new_order($result['order_id'], get_staff_user_id());` (already after model commit).
- `update_order_status()` — after `$result = update_status(...)` and `$result['success']`, call
  `opsdesk_notify_after_change($order_id, $new_status, get_staff_user_id());`. (Covers both normal
  status changes AND cancels delegated to `cancel_order()` inside the model — single notify, correct message.)
- `cancel_order()` — after `$result = cancel_order(...)` and `$result['success']`, call
  `opsdesk_notify_after_change($order_id, 'cancelled', get_staff_user_id());`.
  (Because cancel can arrive via either controller, ensure the unified helper is the single source so we
  never send both a "status updated" and a "cancelled" notification for the same action.)

### T7 — Controller: new `update_priority()` endpoint + AJAX
```
public function update_priority($order_id = '') {
    if (!opsdesk_can_edit_orders()) { ajax_access_denied()/access_denied; }   // Sales blocked
    if (!is_numeric($order_id) || !$this->input->post()) { json error; }
    $priority = (int)$this->input->post('priority');
    $r = $this->opsdesk_orders_model->update_priority((int)$order_id, $priority, get_staff_user_id());
    echo json_encode($r); die;   // CSRF middleware applies (POST)
}
```
Route: `admin/opsdesk/update_priority/{order_id}` (add to `config/routes.php` if module uses explicit routes;
else Perfex resolves by method name).

### T8 — View: order_form.php — Priority field
Add below Packing Type (AC-020.1): two radios `Normal` (default checked) / `High`, name `priority`,
value `0`/`1`. Use styled radios (FRD §7.1). Keep `required` off (default Normal).

### T9 — View: orders_list.php — Priority column + filter + badge
- New column **immediately after Order ID** (FRD §7.2): red "High" badge when `priority==1`, empty otherwise.
- Priority filter chip alongside Status filter: All / High / Normal (drives `?priority=`); controller `orders()`
  already reads `status`; add `priority` to `$options` and pass to model `get()` / `count_by_status()`.
- Default sort already handled by model (T3). Maintain under status filter (T3 order_by applies globally).

### T10 — View: order_detail.php — priority badge + inline edit
- Header: priority badge next to status badge (FRD §7.3).
- "Change Priority" button shown only when `opsdesk_can_edit_orders()` (Operations/Admin). Sales sees badge, no button (AC-022.5).
- Inline toggle (High/Normal) posting to `update_priority()` via AJAX; on success refresh badge + status-log timeline.

### T11 — JS: inline priority toggle
In `assets/js/opsdesk_orders.js` (or a small detail-page script): button → AJAX POST `update_priority/{id}`
with CSRF; on success update badge DOM + prepend a timeline entry client-side (or reload the status log partial).

### T12 — Language strings
Add to `language/english/opsdesk_lang.php`:
`opsdesk_priority`, `opsdesk_priority_high`, `opsdesk_priority_normal`, `opsdesk_change_priority`,
`opsdesk_priority_filter_*` (all/high/normal), and notification message templates
(`opsdesk_notif_new_order`, `opsdesk_notif_new_order_high`, `opsdesk_notif_status_change`,
`opsdesk_notif_cancelled_by_sales`, `opsdesk_notif_cancelled_by_ops`). Keep `e()` on user strings.

## Validation
- Migration 106 runs on (re)activation (version 1.0.6 > recorded). `DESCRIBE opsdesk_orders` shows `priority` + index.
- Fresh install: `install.php` creates full schema; version 1.0.6.
- TC-030..TC-051 from FRD §17 all map to T1–T12. Specifically:
  - High-priority order → red badge + Ops notification with 🔴 prefix; creator excluded.
  - Default sort priority DESC, created_at DESC; priority filter works with status filter.
  - Ops inline priority change → badge + timeline log, no reload; Sales has no edit button.
  - Status change → creator notified (changer excluded). Cancel → correct direction; self excluded.
  - Notification links to `admin/opsdesk/order/{id}`.
  - Regression: stock reservation, inventory viewer, combo CRUD unchanged; `priority` defaults Normal on old orders (DEFAULT 0).

## Risks / notes
- Notification failure must NEVER block order save (FRD §15): notify calls are after commit and their
  return values are ignored by the controller.
- `add_notification()` silently skips inactive users — acceptable.
- Keep migration 105 (schema hotfix) intact; do not merge priority into it.
- No new tables; notifications use `tblnotifications` via `add_notification()`.
