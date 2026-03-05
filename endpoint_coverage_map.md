# Unified Legacy Endpoint Coverage Map

**Status:** Phase B Discovery Complete  
**Objective:** 100% visibility of all legacy endpoints across both `api.php` and `backend/backend.php`.

## Coverage Status Legend

- 🔐 **Certified**: Strict parity confirmed in `config/parity.php` & CI.
- 🚧 **Mapped (Not Certified)**: Gateway/Controller exists, but parity not verified via scan.
- 🔴 **Missing**: No Laravel API route mapping exists yet.

---

## 1. Mapped & Certified (Phase 0/A/B)

| Legacy Endpoint                   | Laravel Controller@Method  | Status |
| --------------------------------- | -------------------------- | ------ |
| `(none)` (Feed)                   | `Device.live`              | 🔐     |
| `get_families`                    | `Family.index`             | 🔐     |
| `get_machine_details`             | `Device.machineDetails`    | 🔐     |
| `get_machine_details&device_id=X` | `Device.single`            | 🔐     |
| `tc_meta`                         | `Report.getTcMeta`         | 🔐     |
| `count_actions`                   | `Report.countActions`      | 🔐     |
| `count_device_status`             | `Report.countDeviceStatus` | 🔐     |
| `count_flexible`                  | `Report.countFlexible`     | 🔐     |

---

## 2. API Scope (`api.php`) – Mapped but NOT Certified

| Legacy Action                 | Laravel Gateway / Controller   | Notes                          |
| ----------------------------- | ------------------------------ | ------------------------------ |
| `search_device`               | `Device.history`               |                                |
| `get_summary_report`          | `Report.summary`               |                                |
| `get_output_report`           | `Report.output`                |                                |
| `get_output_report_bulk`      | `Report.outputBulk`            |                                |
| `get_efficiency_report_data`  | `Report.efficiency`            |                                |
| `get_hourly_report`           | `Report.hourly`                |                                |
| `actions_board`               | `Report.actionsBoard`          |                                |
| `devices_action_table`        | `Report.devicesActionTable`    | _(Also called in backend.php)_ |
| `list_users`                  | `User.listUsers`               | _(Also called in backend.php)_ |
| `list_device_actions_v2`      | `DeviceAction.listV2`          |                                |
| `create_device_action`        | `DeviceAction.store`           |                                |
| `update_device_action_status` | `DeviceAction.update`          |                                |
| `list_action_plans`           | `DeviceAction.listActionPlans` |                                |
| `get_total_count`             | `Report.getTotalCount`         |                                |

---

## 3. Backend Admin Scope (`backend/backend.php`) – Mapped but NOT Certified

| Legacy Action       | Laravel Backend Equivalent       | Category            |
| ------------------- | -------------------------------- | ------------------- |
| `add`               | `Device.store`                   | Device CRUD         |
| `update`            | `Device.update`                  | Device CRUD         |
| `delete`            | `Device.destroy`                 | Device CRUD         |
| `get_devices`       | (Needs mapping, returns grouped) | Device Utils        |
| `action_create`     | `DeviceAction.storeBackend`      | Action/Ticket CRUD  |
| `action_update`     | `DeviceAction.updateBackend`     | Action/Ticket CRUD  |
| `action_delete`     | `DeviceAction.destroy`           | Action/Ticket CRUD  |
| `approve_action`    | `DeviceAction.approve`           | Action/Ticket CRUD  |
| `reject_action`     | `DeviceAction.reject`            | Action/Ticket CRUD  |
| `actions_by_device` | `DeviceAction.listByDevice`      | Action/Ticket Utils |

---

## 4. Missing / Unmapped

> These appear in legacy code but have no direct `GatewayController` mapping or are handled in a different authentication flow.

| Legacy Action        | File      | Note                                                           |
| -------------------- | --------- | -------------------------------------------------------------- |
| `whoami`             | `api.php` | Legacy auth check. Should map to `Auth.me`                     |
| `preview_next_codes` | `api.php` | Mentioned in `$PUBLIC_ACTIONS` array, no implementation found. |

---

## Next Execution Steps (Phase B)

1. Add the 24 uncertified endpoints from Sections 2 & 3 to `config/parity.php`.
2. Define `legacy_params` and `laravel_params` mappings for each.
3. Run `parity:scan` on the newly registered batch to establish baseline drift.
