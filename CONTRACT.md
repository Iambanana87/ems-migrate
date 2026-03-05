# CONTRACT.md

## EMS API — Version 1.0

**Effective Date:** 2026-03-05  
**Status:** FROZEN (Phase A complete)  
**Architecture:** Legacy PHP → Laravel 12 Gateway  
**Entry Point:** `GET /api/gateway?c={Controller}&m={Method}`

> Any PR that adds, removes, or changes an endpoint signature in this document  
> **must** bump the version number AND get Tech Lead sign-off before merge.

---

## GLOBAL CONTRACT RULES

| Rule                             | Value                                                                             |
| -------------------------------- | --------------------------------------------------------------------------------- |
| **Base URL**                     | `http://{host}/api/gateway`                                                       |
| **Auth**                         | `Authorization: Bearer {JWT}` header                                              |
| **JSON encoding**                | `JSON_NUMERIC_CHECK` enforced globally — all numeric strings are native int/float |
| **Status strings**               | Uppercase: `DISCONNECTED`, `BREACHED`, `NORMAL`                                   |
| **Error envelope**               | `{ "status": "error", "message": "..." }`                                         |
| **HTTP code on validation fail** | `400`                                                                             |
| **HTTP code on not found**       | `404`                                                                             |
| **HTTP code on auth fail**       | `401` / `403`                                                                     |

---

## ENDPOINT SIGNATURES

### AUTH

#### `Auth.login` — POST

**Auth required:** No  
**Params:** `username: string, password: string`  
**Response:** `{ "status": "ok", "token": string, "user": { id, username, role } }`

#### `Auth.logout` — POST

**Auth required:** Yes  
**Params:** none  
**Response:** `{ "status": "ok", "message": string }`

#### `Auth.me` — GET

**Auth required:** Yes  
**Params:** none  
**Response:** `{ "status": "ok", "user": { id, username, role } }`

---

### DEVICE — READ

#### `Device.live` — GET

**Auth required:** No  
**Params:** none  
**Response:** Raw array (NO wrapper) — `[ { device_snapshot }, ... ]`  
**Status values:** `DISCONNECTED | BREACHED | NORMAL`  
**Note:** Phase A fix — removed `{status, data}` wrapper to match legacy

#### `Device.single` — GET _(renamed from Device.details in Phase A)_

**Auth required:** No  
**Params:** `device_id: string`  
**Response:** `{ single device object with last_n_cycles array }`  
**Note:** Returns a single object, not an array. Different from Device.machineDetails.

#### `Device.history` — GET

**Auth required:** No  
**Params:** `device_id: string, from: date, to: date, limit?: int`  
**Response:** `{ "total": int, "data": [ ...raw IoT records... ] }`

#### `Device.machineDetails` — GET

**Auth required:** No  
**Params:** `process: mold|tuft|blister, status?: NORMAL|DISCONNECTED|BREACHED`  
**Response:** Raw array — `[ { device_row_with_metrics }, ... ]`  
**@parity-verified:** Phase 0 (all 3 processes, all scenarios)

---

### DEVICE — ADMIN CRUD

#### `Device.index` — GET

**Auth required:** Yes (any role)  
**Params:** `page?: int, page_size?: int, search?: string, type?: string, process?: string`  
**Response:** `{ "data": [...], "total": int, "page": int }`

#### `Device.store` — POST

**Auth required:** Yes (admin)  
**Params:** _(all device fields from AddDeviceRequest)_  
**Response:** `{ "status": "ok", "message": string, "data": { device } }`

#### `Device.update` — POST

**Auth required:** Yes (admin)  
**Params:** `device_id: string, reason: string, ...update fields`  
**Response:** `{ "status": "ok", "message": string, "data": { device } }`

#### `Device.destroy` — POST

**Auth required:** Yes (admin)  
**Params:** `device_id: string`  
**Response:** `{ "status": "ok", "message": string }`

---

### DEVICE — ACTIONS (WORK ORDERS)

#### `DeviceAction.store` — POST

**Auth required:** Yes  
**Params:** `device_id, action_type, description, ...`  
**Response:** `{ "status": "ok", "data": { action } }`

#### `DeviceAction.update` — POST

**Auth required:** Yes  
**Params:** `action_id, ...update fields`  
**Response:** `{ "status": "ok", "data": { action } }`

#### `DeviceAction.destroy` — POST

**Auth required:** Yes (admin)  
**Params:** `action_id: int`  
**Response:** `{ "status": "ok", "message": string }`

#### `DeviceAction.approve` — POST

**Auth required:** Yes (admin)  
**Guard:** `WHERE approval_status IS NULL OR approval_status <> 'approved'`  
**Params:** `action_id: int`  
**Response:** `{ "status": "ok", "message": string }`

#### `DeviceAction.reject` — POST

**Auth required:** Yes (admin)  
**Params:** `action_id: int, reason?: string`  
**Response:** `{ "status": "ok", "message": string }`

#### `DeviceAction.createPublic` — POST

**Auth required:** No  
**Params:** `device_id, description, ...`  
**Response:** `{ "status": "ok", "data": { action } }`

#### `DeviceAction.listV2` — GET

**Auth required:** Yes  
**Params:** `device_id?: string, status?: string, page?: int`  
**Response:** `{ "data": [...], "total": int }`

#### `DeviceAction.listActionPlans` — GET

**Auth required:** Yes  
**Params:** `device_id?: string`  
**Response:** `[ { action_plan }, ... ]`

#### `DeviceAction.updateActionPlanStatus` — POST

**Auth required:** Yes  
**Params:** `plan_id: int, status: string`  
**Response:** `{ "status": "ok" }`  
**@parity-verified:** Phase B1a (pending)

#### `DeviceAction.deleteActionPlan` — POST

**Auth required:** Yes (admin)  
**Params:** `plan_id: int`  
**Response:** `{ "status": "ok", "message": string }`

#### `DeviceAction.bulkUpdateActionPlanStatus` — POST

**Auth required:** Yes  
**Params:** `plan_ids: int[], status: string`  
**Response:** `{ "status": "ok", "updated": int }`  
**@parity-verified:** Phase B1b (pending)

---

### DEVICE STATUS

#### `DeviceStatus.update` — POST

**Auth required:** Yes (admin)  
**Params:** `device_id: string, status: NORMAL|DISCONNECTED|BREACHED`  
**Response:** `{ "status": "ok", "message": string }`

---

### ACTION PLANS

#### `ActionPlan.updateOrder` — POST

**Auth required:** Yes (admin)  
**Params:** `ordered_ids: int[]`  
**Response:** `{ "status": "ok" }`

---

### AP STATUS

#### `ApStatus.index` — GET

**Auth required:** No  
**Params:** none  
**Response:** `[ { ap_status_row }, ... ]`

---

### FACTORY LAYOUT

#### `FactoryLayout.index` — GET

**Auth required:** No  
**Params:** none  
**Response:** `[ { layout }, ... ]`

#### `FactoryLayout.show` — GET

**Auth required:** No  
**Params:** `layout_id: int`  
**Response:** `{ layout }`

#### `FactoryLayout.store` — POST

**Auth required:** Yes (admin)  
**Params:** `name: string, data: object`  
**Response:** `{ "status": "ok", "data": { layout } }`

#### `FactoryLayout.destroy` — POST

**Auth required:** Yes (admin + ownership)  
**Params:** `layout_id: int`  
**Response:** `{ "status": "ok", "message": string }`

---

### AUDIT TRAIL

#### `AuditTrail.index` — GET

**Auth required:** Yes  
**Params:** `page?: int, from?: date, to?: date`  
**Response:** `{ "data": [...], "total": int }`

#### `AuditTrail.show` — GET

**Auth required:** Yes  
**Params:** `audit_id: int`  
**Response:** `{ audit_record }`

---

### FAMILY

#### `Family.index` — GET

**Auth required:** No  
**Params:** none  
**Response:** `[ { family }, ... ]`

---

### REPORTS

#### `Report.summary` — GET

**Auth required:** Yes  
**Params:** `from: date, to: date, process?: string`  
**Response:** `{ summary aggregation }`

#### `Report.efficiency` — GET

**Auth required:** Yes  
**Params:** `from: date, to: date, device_id?: string, utc?: 0|1`  
**Response:** `[ { efficiency_row }, ... ]`  
**Note:** UTC flag replicates legacy timezone conversion logic

#### `Report.output` — GET

**Auth required:** Yes  
**Params:** `from: date, to: date, device_id?: string`  
**Response:** `{ output aggregation }`

#### `Report.outputBulk` — GET

**Auth required:** Yes  
**Params:** `from: date, to: date, device_ids: string[]`  
**Response:** `[ { per-device output }, ... ]`

#### `Report.hourly` — GET

**Auth required:** Yes  
**Params:** `date: date, device_id: string`  
**Response:** `[ { hourly_bucket }, ... ]`

#### `Report.actionsBoard` — GET

**Auth required:** Yes  
**Params:** `process?: string`  
**Response:** `{ actions_board }`

#### `Report.devicesActionTable` — GET

**Auth required:** Yes  
**Params:** `process?: string`  
**Response:** `[ { device_action_row }, ... ]`

#### `Report.previewNextCodes` — GET

**Auth required:** Yes  
**Params:** `device_id: string, count?: int`  
**Response:** `[ { code_preview }, ... ]`

#### `Report.listBackendIssues` — GET

**Auth required:** Yes  
**Params:** none  
**Response:** `[ { issue }, ... ]`

#### `Report.countActions` — GET

**Auth required:** No  
**Params:** none  
**Response:** `{ "count": int }`

#### `Report.countDeviceStatus` — GET

**Auth required:** No  
**Params:** none  
**Response:** `{ "NORMAL": int, "DISCONNECTED": int, "BREACHED": int }`

#### `Report.countFlexible` — GET

**Auth required:** No  
**Params:** none  
**Response:** `{ "count": int }`

#### `Report.getTotalCount` — GET

**Auth required:** Yes  
**Params:** `process?: string, from?: date, to?: date`  
**Response:** `{ count aggregation }`

#### `Report.getTcMeta` — GET

**Auth required:** No  
**Params:** none  
**Response:** `{ tc_meta object }`

---

### USER

#### `User.listUsers` — GET

**Auth required:** Yes (admin)  
**Params:** `page?: int`  
**Response:** `{ "data": [ { user } ], "total": int }`

---

## SIGN-OFF

```
CONTRACT.md v1.0 — Frozen: 2026-03-05
Phase A Gate: APPROVED

Signed: _________________________ (Technical Lead)
Date:   _________________________
```

---

## KNOWN INTENTIONAL DEVIATIONS

| Endpoint                | Key                  | Legacy Behavior                                     | Laravel Behavior                               | Reason                                               |
| ----------------------- | -------------------- | --------------------------------------------------- | ---------------------------------------------- | ---------------------------------------------------- |
| `Device.machineDetails` | `capacity_per_hr`    | Reads from `d.capacity` via `SELECT *`, no alias    | Explicit `d.capacity AS capacity_per_hr` alias | Required for `calculateBlisterMetrics` input mapping |
| `Device.machineDetails` | `loss_pcs` (blister) | Falls back to 0 (no `capacity_per_hr` in `$device`) | Correctly reads `capacity_per_hr`              | PRESERVED intentionally — certified in Phase 0       |
