# Software Audit Report — OpsDesk Module

**Auditor:**		Strategic Workflow Orchestrator  
**Audit Date:**	2026-07-30  
**Location:**	modules/opsdesk  
**Files Audited:**	38 / 38  
**Framework:**	Perfex CRM / CodeIgniter 3  
**Module Version (header):**	1.0.8  
**Module Version (install default):**	1.0.11  

---

## 1. Module Overview

OpsDesk provides an operations desk solution for Perfex CRM around three core features:

| Area | Description |
|------|-------------|
| Combo Products | Manage product bundles and their component mappings |
| Inventory Viewer | Real-time per-combo availability checks with editable items |
| Orders | Order workflow with status stepper, stock reservation, staff assignment |

**File Structure:**

```
modules/opsdesk/
  opsdesk.php                  140 lines — module definition, hooks, menu
  install.php / uninstall.php  265 / 35 lines — schema, seed data, rollback
  controllers/Opsdesk.php      1327 lines — main controller (28 methods)
  helpers/opsdesk_helper.php   1024 lines — ~40 helper functions
  models/                       5 models
  views/                        8 views + 3 includes
  assets/css + assets/js       styles + jQuery interactions
  language/english/            231 language keys
  migrations/100-110            11 migration files
  tests/                        unit + integration + blackbox suites
```

---

## 2. Database Schema

| Table | Purpose | Key Notes |
|-------|---------|-----------|
| `opsdesk_combos` | Bundle definitions | PK id, UNIQUE name |
| `opsdesk_combo_items` | Bundle components | FK combo_id CASCADE, FK product_item_id SET NULL |
| `opsdesk_inventory` | Stock cache | UNIQUE sku |
| `opsdesk_orders` | Order headers | FK combo_id RESTRICT |
| `opsdesk_order_items` | Order lines | FK order_id CASCADE |
| `opsdesk_order_status_log` | Audit trail | FK order_id CASCADE |
| `opsdesk_product_statuses` | Configurable statuses | UNIQUE status_key, UNIQUE display_order |
| `opsdesk_packing_types` | Packing types | UNIQUE type_key, UNIQUE display_order |
| `opsdesk_transport_mediums` | Transport mediums | UNIQUE type_key, UNIQUE display_order |

All relevant fields are indexed; foreign keys are explicit.  
Column constraints: `opsdesk_orders.quantity` is `DECIMAL(15,4)` from migration 108 onward, and `status` is `VARCHAR(100)` from migration 108 onward.

---

## 3. Architecture & Design Patterns

### 3.1 MVC stack
- **Framework:** Perfex CRM / CodeIgniter 3
- **Controller:** `Opsdesk extends AdminController` with 28 public/private methods
- **Models:** 5 models extending `App_Model`
- **Views:** PHP views using `init_head()`/`init_tail()` plus Perfex view helpers

### 3.2 Permission model
Three layers of capability checks:

| Layer | Example |
|-------|---------|
| Module capabilities | `view`, `create`, `edit`, `delete` on `opsdesk` |
| Order capabilities | `view_own`, `view`, `create`, `edit`, `delete` on `opsdesk_orders` |
| Settings gate | `opsdesk_can_manage_settings()` checks create/edit on both layers |

### 3.3 Status engine
- 6 default statuses: `pending -> in_progress -> packed -> shipped -> completed -> cancelled`
- Custom definitions stored in `opsdesk_product_statuses`
- `opsdesk_get_next_statuses()` enforces forward-only transitions
- Every transition is persisted in `opsdesk_order_status_log`

### 3.4 Warehouse integration
- Falls back to `opsdesk_inventory` when Warehouse module is inactive
- When Warehouse is active, uses live totals from `tblinventory_manage` minus OpsDesk reservations

---

## 4. Functional Analysis

### 4.1 Combo management (5 CRUD + item actions)
| Method | HTTP | Action |
|--------|------|--------|
| `combos()` | GET | List |
| `combo($id)` | GET/POST | Create/Edit |
| `delete_combo($id)` | GET | Delete |
| `add_combo_item($combo_id)` | POST | Add component |
| `delete_combo_item($combo_id, $item_id)` | POST | Remove component |

Item creation automatically syncs `opsdesk_inventory` from the linked Perfex product.

### 4.2 Order workflow (9 endpoints)
| Method | HTTP | Guard |
|--------|------|-------|
| `order()` | GET | `opsdesk_can_create_orders()` |
| `save_order()` | POST | `opsdesk_can_create_orders()` |
| `order_detail($id)` | GET (private) | `opsdesk_can_view_orders()` |
| `update_order_status()` | POST/AJAX | `opsdesk_can_edit_orders()` |
| `cancel_order($id)` | POST/AJAX | `opsdesk_can_view_orders()` |
| `upload_payment_file($order_id)` | POST | `opsdesk_can_edit_orders()` |
| `update_priority($order_id)` | POST/AJAX | `opsdesk_can_edit_orders()` |
| `assign_order($order_id)` | POST/AJAX | `opsdesk_can_edit_orders()` |
| `ajax_order_stock_check()` | AJAX | `opsdesk_can_create_orders()` |

**Flow summary:**

```php
// create_order_with_reservation() — transactional
stock_check -> ensure_inventory_row -> FOR UPDATE read -> validate stock
-> insert order -> insert items -> UPDATE inventory.quantity_reserved += x
-> log_status_change -> COMMIT
```

```php
// update_status() — transactional
-> status validation -> on shipped: available -= reserved, reserved -= qty
-> optional warehouse deduct -> UPDATE order -> log_status_change -> COMMIT
```

```php
// cancel_order() — transactional
-> reserved = GREATEST(0, reserved - qty) -> UPDATE order -> log_status_change -> COMMIT
```

---

## 5. Security Analysis

### 5.1 Positive controls
- `defined('BASEPATH') or exit` at every entry point
- `opsdesk_is_warehouse_module_active()` defensive module check
- `table_exists()` guards before helper DB calls
- `FOR UPDATE` row-level locking in `create_order_with_reservation()`
- Explicit `trans_begin()` / `trans_commit()` / `trans_rollback()` in all mutating paths
- CSRF protection via Perfex framework
- `show_404()` for non-AJAX requests on AJAX endpoints
- File uploads use `encrypt_name` and an extension whitelist
- `set_alert()` instead of raw echo for errors
- `is_valid_transition()` prevents invalid status jumps
- `(int)` casts for all ID parameters

### 5.2 Identified risks and weaknesses

| ID | Severity | Description | Location |
|----|----------|-------------|----------|
| **S-1** | **HIGH** | Version mismatch: `opsdesk.php` header says `1.0.8`, `install.php` writes option `1.0.11`. The installed_version stored in `tblmodules` can diverge from the file header. | `opsdesk.php:9` vs `install.php:265` |
| **S-2** | **HIGH** | `delete_combo`, `delete_combo_item`, `delete_product_status`, `delete_packing_type`, and `delete_transport_medium` are exposed via GET links (`<a href>`) without CSRF tokens. They are vulnerable to CSRF and social engineering. | `controllers/Opsdesk.php:622-668` |
| **S-3** | **HIGH** | `cancel_order($id)` is a GET route invoked via `<a href>`. A logged-in admin can be tricked into cancelling an order by visiting a malicious page. | `controllers/Opsdesk.php:1051` |
| **S-4** | **MEDIUM** | `opsdesk_search_clients($q, 50)` comment-out the `$CI->db->limit()` call. Unbounded client search on every keystroke. | `helpers/opsdesk_helper.php:760` |
| **S-5** | **MEDIUM** | 40+ global helper functions. Any future PHP or third-party module function with the same name could collide. | `helpers/opsdesk_helper.php` |
| **S-6** | **MEDIUM** | `update_payment_file()` allows replacing payment evidence without an explicit `delete` capability on orders. | `controllers/Opsdesk.php:1094-1126` |
| **S-7** | **MEDIUM** | `seed_random_stock()` is accessible to users with only `view` permission on `opsdesk`. This is a testing/debugging endpoint that should be admin-only or removed in production. | `controllers/Opsdesk.php:456-496` |
| **S-8** | **LOW** | Inconsistent number formatting — views mix `app_format_number()` with `number_format()`. | `views/*.php` |
| **S-9** | **LOW** | Some GET parameters are not strictly cast to `(int)` before DB or arithmetic use, e.g. `$order_qty` in `combo_inventory()` is cast to float but lacks explicit integer validation on the input source. | `controllers/Opsdesk.php:690` |
| **S-10** | **LOW** | `update()` in models returns `affected_rows() >= 0`. When no row actually changes, the return is still truthy, which can mislead callers into thinking data was modified. | `models/Opsdesk_orders_model.php:723-731`, `models/Opsdesk_packing_types_model.php:100`, `models/Opsdesk_transport_mediums_model.php:100` |

### 5.3 Unused routes and files
- `index.html` files in several folders serve as directory-listing protection — good practice.
- `views/combo_inventory.php` is rendered from the controller but sits at the standard view path; no evidence of external exposure outside the normal module routing.

---

## 6. Code Quality

### 6.1 Strengths
- Transactions and `FOR UPDATE` row locking in the order workflow
- Explicit adjacency list for valid status transitions
- Consistent `opsdesk_` prefix for globals and language keys
- Strong test coverage: Unit (13), Integration (11), Blackbox (4)
- 231 language keys prepared for localization
- `e()` used for XSS-safe output across views
- Clear separation: models for DB, helpers for logic, views for markup

### 6.2 Weaknesses
- 40+ global functions reduce testability and risk namespace collisions
- Controller is 1327 lines and should be split into smaller controllers
- No dedicated form-request/validation classes — all validation inline
- `setInterval` in `order_detail.php` and `orders_list.php` to keep Accept button state in sync is fragile
- Commented-out code in views instead of feature-flag driven toggles
- Duplicate `duplicate_key` / `duplicate_order` handling across 3 settings methods without a shared helper
- `affected_rows() >= 0` semantics in model `update()` methods are misleading

---

## 7. Audit Trail & Logging

| Mechanism | Source | Example |
|-----------|--------|---------|
| Activity log | `log_activity()` | `OpsDesk Order Created [ID:42]` |
| Order status history | `opsdesk_order_status_log` | `from_status`, `to_status`, `changed_by`, `notes`, `created_at` |
| Notifications | `add_notification()` | Staff notification on status change |

Status changes are consistently appended via `log_status_change()` and support free-text `notes`, which satisfies typical operation-audit requirements.

---

## 8. Testing Strategy

### 8.1 Unit tests (13)
- SKU resolution
- Product label / subtext generation
- Fallback behavior without DB
- Status and priority helpers
- Capability-gate shims

### 8.2 Integration tests (11)
- Migration 108 idempotency and schema changes (`INT -> DECIMAL`, `VARCHAR(50) -> VARCHAR(100)`)
- Fractional quantity persistence
- Transaction rollback on failure
- Stock reserve / release / consume lifecycle
- UNIQUE constraint guarantees
- Cascade delete behavior
- Notification payload contract

### 8.3 Blackbox tests (4)
- Unauthenticated access redirects
- AJAX guard returns 404 for non-AJAX
- Module registration in `tblmodules`

**Test runner:** Custom framework (`tests/lib.php`) with colored pass/fail output. No PHPUnit dependency.

---

## 9. Performance Considerations

| Area | Observation |
|------|-------------|
| Inventory map | `opsdesk_get_warehouse_stock_map()` does a full `GROUP BY` across `tblinventory_manage`; slow above 10k products |
| Client search | `opsdesk_search_clients()` is unbounded (commented-out limit) |
| Order list | `apply_list_joins()` LEFT JOINs `staff` + `clients` plus `COUNT(*) GROUP BY`; partitioning recommended above 50k orders |
| Stock seeding | `seed_random_stock()` rewrites every inventory row and can block under load |

---

## 10. Risk Matrix

| ID | Category | Severity | Description |
|----|----------|----------|-------------|
| S-1 | Versioning | HIGH | Dual version source (`1.0.8` header vs `1.0.11` install default) |
| S-2 | CSRF | HIGH | GET-based destructive routes with no CSRF token |
| S-3 | CSRF | HIGH | `cancel_order()` reachable via unprotected GET link |
| S-4 | DoS / Memory | MEDIUM | Unbounded client search |
| S-5 | Architecture | MEDIUM | Global helper namespace pollution |
| S-6 | Authorization | MEDIUM | Payment file upload bypasses delete-style checks |
| S-7 | Authorization | MEDIUM | Stock seeding endpoint available to sales viewers |
| S-8 | Consistency | LOW | Mixed number formatters |
| S-9 | Input validation | LOW | Missing explicit casts in a few GET params |
| S-10 | Logic clarity | LOW | `affected_rows() >= 0` hides "no change" state |

---

## 11. Recommendations

1. **Synchronize version metadata:** Keep the file header and `install.php` version aligned; derive `installed_version` from migration state instead of a single option.
2. **Convert destructive routes to POST:** `cancel_order`, `delete_combo`, `delete_combo_item`, `delete_product_status`, `delete_packing_type`, `delete_transport_medium` should submit CSRF-protected POST forms.
3. **Restore limit in `opsdesk_search_clients()`:** Prevent unbounded result sets.
4. **Restrict `seed_random_stock()`:** Require `is_admin()` or a dedicated capability; disable in production.
5. **Split the controller:** Separate controllers for Orders, Combos, and Settings; each under ~500 lines.
6. **Fix model `update()` semantics:** Return `true` only on actual modification, or expose a separate `has_changed` flag.
7. **Extract validation logic:** Move repeated inline validation into reusable validators or CI Form_Validation rules.
8. **Standardize number formatting:** Use one formatter across views and avoid mixed display logic.
9. **Document AJAX endpoints:** Add OpenAPI-style comments or a small `docs/` folder describing request/response shapes.
10. **Rate-limit heavy AJAX routes:** `ajax_availability`, `clients`, and `seed_random_stock` should be throttled.

---

*Audit complete — 38 files reviewed, 38 files analyzed.*
