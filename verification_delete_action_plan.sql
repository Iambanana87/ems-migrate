-- SQL Verification Script for delete_action_plan
-- Database: ems_migrate

SET @TEST_ACTION_ID = 99999;
SET @TEST_PLAN_ID_1 = 99001;
SET @TEST_PLAN_ID_2 = 99002;

-- ============================================================================
-- SCENARIO setup: Common starting point
-- ============================================================================
DELETE FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;
DELETE FROM device_actions WHERE id = @TEST_ACTION_ID;

INSERT INTO device_actions (id, device_id, title, status, approval_status) 
VALUES (@TEST_ACTION_ID, 'MOCK-DEV', 'Audit Delete Test', 'in_progress', 'pending');

-- ============================================================================
-- SCENARIO 2: Delete ONLY plan of the ISSUE
-- ============================================================================
INSERT INTO device_action_plans (id, action_id, plan_text, status) 
VALUES (@TEST_PLAN_ID_1, @TEST_ACTION_ID, 'Only Plan', 'open');

SELECT 'BEFORE DELETE (Only Plan)' AS CaseName;
SELECT id, status, approval_status FROM device_actions WHERE id = @TEST_ACTION_ID;
SELECT action_id, SUM(status='done') AS done, COUNT(*) AS total FROM device_action_plans WHERE action_id = @TEST_ACTION_ID GROUP BY action_id;

-- SIMULATE DELETE
DELETE FROM device_action_plans WHERE id = @TEST_PLAN_ID_1;

-- SIMULATE RECALC
SELECT 'AFTER DELETE (total = 0)' AS CaseName;
SELECT SUM(status='done') AS done, COUNT(*) AS total FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;
-- Expected: total = 0. Per legacy, device_actions remains 'in_progress' because the recalc block only runs if total > 0.

-- ============================================================================
-- SCENARIO 3A: 1 done / 2 total -> Delete the done plan
-- ============================================================================
DELETE FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;
INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (@TEST_PLAN_ID_1, @TEST_ACTION_ID, 'Plan Open', 'open');
INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (@TEST_PLAN_ID_2, @TEST_ACTION_ID, 'Plan Done', 'done');

SELECT 'BEFORE DELETE (1 done / 2 total)' AS CaseName;
SELECT id, status, approval_status FROM device_actions WHERE id = @TEST_ACTION_ID;

-- SIMULATE DELETE Plan Done
DELETE FROM device_action_plans WHERE id = @TEST_PLAN_ID_2;

-- SIMULATE RECALC
SELECT 'AFTER DELETE (0 done / 1 total)' AS CaseName;
SELECT SUM(status='done') AS done, COUNT(*) AS total FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;
-- Expected Result: done=0, total=1. Logic: newStatus = 'open'.
-- Simulated Update:
UPDATE device_actions SET status = 'open' WHERE id = @TEST_ACTION_ID;
SELECT id, status, approval_status FROM device_actions WHERE id = @TEST_ACTION_ID;

-- ============================================================================
-- SCENARIO 3B: 2 done / 2 total -> Delete one done
-- ============================================================================
DELETE FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;
UPDATE device_actions SET status = 'done', approval_status = 'approved' WHERE id = @TEST_ACTION_ID;
INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (@TEST_PLAN_ID_1, @TEST_ACTION_ID, 'Plan Done 1', 'done');
INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (@TEST_PLAN_ID_2, @TEST_ACTION_ID, 'Plan Done 2', 'done');

SELECT 'BEFORE DELETE (2 done / 2 total)' AS CaseName;
SELECT id, status, approval_status FROM device_actions WHERE id = @TEST_ACTION_ID;

-- SIMULATE DELETE
DELETE FROM device_action_plans WHERE id = @TEST_PLAN_ID_2;

-- SIMULATE RECALC
SELECT 'AFTER DELETE (1 done / 1 total)' AS CaseName;
SELECT SUM(status='done') AS done, COUNT(*) AS total FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;
-- Expected Result: done=1, total=1. Logic: newStatus = 'done', newAppr = 'approved' (as it was approved).
-- Simulated Update (No change expected actually):
UPDATE device_actions SET status = 'done', approval_status = 'approved' WHERE id = @TEST_ACTION_ID;
SELECT id, status, approval_status FROM device_actions WHERE id = @TEST_ACTION_ID;

-- CLEANUP
DELETE FROM device_action_plans WHERE id IN (@TEST_PLAN_ID_1, @TEST_PLAN_ID_2);
DELETE FROM device_actions WHERE id = @TEST_ACTION_ID;

-- ============================================================================
-- SCENARIO 4: issue='done', approval='rejected' -> Delete one 'done' plan
-- ============================================================================
DELETE FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;
UPDATE device_actions SET status = 'done', approval_status = 'rejected' WHERE id = @TEST_ACTION_ID;
INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (@TEST_PLAN_ID_1, @TEST_ACTION_ID, 'Done 1', 'done');
INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (@TEST_PLAN_ID_2, @TEST_ACTION_ID, 'Done 2', 'done');

SELECT 'BEFORE DELETE (2 done / 2 total, rejected)' AS CaseName;
SELECT id, status, approval_status FROM device_actions WHERE id = @TEST_ACTION_ID;

-- SIMULATE DELETE
DELETE FROM device_action_plans WHERE id = @TEST_PLAN_ID_2;

-- SIMULATE RECALC result for explanation
SELECT 'AFTER DELETE (1 done / 1 total)' AS CaseName;
SELECT SUM(status='done') AS done, COUNT(*) AS total FROM device_action_plans WHERE action_id = @TEST_ACTION_ID;

-- CLEANUP
DELETE FROM device_action_plans WHERE id IN (@TEST_PLAN_ID_1, @TEST_PLAN_ID_2);
DELETE FROM device_actions WHERE id = @TEST_ACTION_ID;
