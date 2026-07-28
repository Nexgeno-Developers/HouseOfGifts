# OpsDesk Module Redesign & Performance Optimization Plan

## Summary

This plan addresses:
1. **Order Details UI Redesign**: Transform the fragmented, stacked layout into a modern, cohesive Operations Dashboard with a visual lifecycle stepper, streamlined metadata cards, a unified workflow sidebar, and an activity timeline.
2. **Transport Medium Display Bug**: Resolve the bug causing Transport Medium to display as `0` instead of a human-readable label.
3. **Order Listing & Query Performance**: Eliminate N+1 SQL queries, fix heavy full-table stock aggregations, and clean character encoding issues across the module (**client search capping removed per user instruction**).

---

## User Review Required

> [!IMPORTANT]
> **Client Search Capping Omitted**: Per your feedback, client search will **not** be capped so that all existing members remain fully searchable without omissions.

> [!IMPORTANT]
> **Order Details Layout Change**: The 5 small panels currently stacked on the right side ("Status & Actions", "Assignment", "Completion Documents", "Files", "Priority") will be consolidated into a clean, tabbed/segmented **Unified Workflow Sidebar** alongside a top **Visual Status Stepper**.

> [!NOTE]
> All existing business permissions, completion file validations, and status log tracking will remain fully intact.

---

## Proposed Changes

### 1. Order Detail Redesign & Fixes

#### [MODIFY] [order_detail.php](file:///c:/xampp/htdocs/hog/modules/opsdesk/views/order_detail.php)
- **Top Visual Stepper Bar**: Display an interactive horizontal pipeline tracker showing order progression: `Pending` → `In Progress` → `Packed` → `Shipped` → `Completed` (or `Cancelled`).
- **Main Column (Left 7 Columns)**:
  - **Order Information Card**: Clean 2-column key-value grid with icons for Customer, Customer City, Combo Name, Order Quantity, Packing Type, Transport Medium, Creator, and Created Date.
  - **Combo Line Items Table**: Component breakdown (SKU, Product Name, Quantity per Unit, Total Reserved, Substitutions) with clean status pills.
  - **Status History Timeline**: Styled vertical activity feed with timestamps, staff names, transition badges, and notes.
- **Unified Workflow Sidebar (Right 5 Columns)**:
  - Consolidate the 5 stacked panels into 2 cohesive cards:
    - **Operations Control Card**: Tabbed or segmented interface grouping:
      1. *Status Advance & Acceptance* (with required Packer assignment selector)
      2. *Staff Assignment* (Packer & Counter assignment)
      3. *Priority Flag Toggle*
    - **Documents & Attachments Card**: Segmented section for Bill File, Payment File, LR Copy, and Carton Photo previews with inline upload dropzone triggers.

#### [MODIFY] [order_detail.css](file:///c:/xampp/htdocs/hog/modules/opsdesk/assets/css/order_detail.css)
- Add CSS styles for the pipeline stepper (`.opsdesk-stepper`, `.stepper-step`), metric grid cards (`.opsdesk-meta-card`), file preview badges, and timeline list styling.

#### [MODIFY] [opsdesk_helper.php](file:///c:/xampp/htdocs/hog/modules/opsdesk/helpers/opsdesk_helper.php)
- Fix `opsdesk_get_transport_medium_label()` to accept both `type_key` string and `id` integer lookup.
- Fix character encoding artifacts (`â†’`, `â€”`, `Â·` -> `→`, `—`, `·`).

#### [MODIFY] [Opsdesk.php](file:///c:/xampp/htdocs/hog/modules/opsdesk/controllers/Opsdesk.php)
- Update `save_order()` to save `transport_medium_id` as the string `type_key` or match the DB schema correctly so it never gets saved as integer `0`.

---

### 2. Performance & Data Optimizations

#### [MODIFY] [Opsdesk_orders_model.php](file:///c:/xampp/htdocs/hog/modules/opsdesk/models/Opsdesk_orders_model.php)
- **Fix N+1 Query in Order Listing**: Update `apply_list_joins()` to include `LEFT JOIN tblclients ON tblclients.userid = tblopsdesk_orders.customer_id` and select `company as customer_name`.

#### [MODIFY] [opsdesk_helper.php](file:///c:/xampp/htdocs/hog/modules/opsdesk/helpers/opsdesk_helper.php)
- **Optimize Warehouse Stock Query**: Update `opsdesk_get_warehouse_stock_total($commodity_id)` to query `SUM(inventory_number) WHERE commodity_id = ?` directly instead of calling `opsdesk_get_warehouse_stock_map()` which aggregates the entire table every time.
- **Client Search Uncapped**: Retain full client listing without applying limits, ensuring all members can be searched.

#### [MODIFY] [orders_list.php](file:///c:/xampp/htdocs/hog/modules/opsdesk/views/orders_list.php)
- Remove inline `get_client()` calls in table loop, using `$order['customer_name']` from the query join.
- Add responsive table wrappers and clean up inline selectpicker initializations.

---

## Verification Plan

### Automated Verification
- Run PHP syntax checks on modified files:
  ```powershell
  php -l modules/opsdesk/controllers/Opsdesk.php
  php -l modules/opsdesk/models/Opsdesk_orders_model.php
  php -l modules/opsdesk/helpers/opsdesk_helper.php
  php -l modules/opsdesk/views/order_detail.php
  ```

### Manual Verification
- Test creating a new order with transport medium and verify that the transport medium label displays correctly (e.g. "Road Transport") instead of `0`.
- Verify client search works without dropping any member records.
- Verify the redesigned Order Details page layout on both desktop and mobile viewports.
- Verify status transitions (`Pending` → `In Progress` → `Packed` → `Shipped` → `Completed`) and mandatory document uploads.
