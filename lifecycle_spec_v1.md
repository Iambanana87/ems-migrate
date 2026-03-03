# Device Action Lifecycle Specification (v1)

## 1. Possible Values

### device_actions.status

- `open`: 0% plan completion or initial state from public creation.
- `in_progress`: Partial plan completion ( > 0% and < 100% ).
- `done`: 100% plan completion.
- `pending`: Internal state used for manual action creation (legacy flow).
- `approved`: Internal state used for manual action approval (legacy flow).
- `rejected`: Internal state used for manual action rejection (legacy flow).

### device_actions.approval_status

- `pending`: Initial state; also used when 100% completion is reached but no prior approval/rejection exists.
- `approved`: Terminal approval state; preserved during recalculation when 100% completion is maintained.
- `rejected`: Terminal rejection state; preserved during recalculation when 100% completion is maintained.

---

## 2. Transition Matrix (Automatic Recalculation)

| Input State (Done / Total) | Logic Path                                                     | Resulting Status | Resulting Approval Status |
| :------------------------- | :------------------------------------------------------------- | :--------------- | :------------------------ |
| `done == 0`                | All plans incomplete                                           | `open`           | (Unchanged)               |
| `0 < done < total`         | Partial completion                                             | `in_progress`    | (Unchanged)               |
| `done == total`            | 100% completion AND `prev == 'approved'`                       | `done`           | `approved`                |
| `done == total`            | 100% completion AND `prev == 'rejected'`                       | `done`           | `rejected`                |
| `done == total`            | 100% completion AND `prev` NOT IN (`'approved'`, `'rejected'`) | `done`           | `pending`                 |

---

## 3. Recalculation Rules

- **Triggers**:
  - `updateActionPlanStatus` (Plan status update).
  - `deleteActionPlan` (Plan removal).
- **Logic**:
  - If `total > 0`:
    - `done == 0` $\rightarrow$ `status = 'open'`.
    - `done < total` $\rightarrow$ `status = 'in_progress'`.
    - `done == total` $\rightarrow$ `status = 'done'`.
- **Approval Status Logic (Only evaluated if `done == total`)**:
  - `if ($beforeIssue['approval_status'] === 'approved') { $newAppr = 'approved'; }`
  - `elseif ($beforeIssue['approval_status'] === 'rejected') { $newAppr = 'rejected'; }`
  - `else { $newAppr = 'pending'; }`

---

## 4. Terminal State Rules

- **Approval Terminal**: Once `approval_status` is `approved`, it remains `approved` even if plans are added/removed, as long as the state remains `done` (100% completion).
- **Rejection Terminal**: Once `approval_status` is `rejected`, it remains `rejected` even if plans are added/removed, as long as the state remains `done` (100% completion).

---

## 5. Recalculation Skip Conditions

- **Empty Plan Set**: Recalculation is skipped entirely if the total plan count is `0`. The issue retains its current `status` and `approval_status`.
- **No Logical Change**: Database `UPDATE` on `device_actions` is skipped if `newStatus == currentStatus` AND `newAppr == currentAppr`.

---

## 6. Audit Trigger Rules

- **Primary Mutation**: An audit log is always generated for the plan update or deletion itself.
- **Cascading Recalculation (Deletion)**: In `deleteActionPlan`, a secondary audit log `device_actions:auto_status_recalc` is triggered if and only if the issue's `status` or `approval_status` changes.
- **Cascading Recalculation (Update)**: In `updateActionPlanStatus`, the secondary audit log is conditionally gated by the `ENABLE_ISSUE_AUTORECALC_AUDIT` constant (currently logically disabled).
