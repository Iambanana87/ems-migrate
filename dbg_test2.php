<?php

$pdo = new PDO('mysql:host=localhost;dbname=ems;charset=utf8', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

function deleteActionPlan($data, $who) {
    global $pdo;

    $planId = (int)($data['plan_id'] ?? 0);
    if ($planId <= 0) {
        throw new \Exception('plan_id required', 400);
    }

    $pdo->beginTransaction();
    try {
        $st1 = $pdo->prepare("SELECT * FROM device_action_plans WHERE id=?");
        $st1->execute([$planId]);
        $beforePlan = $st1->fetch(PDO::FETCH_ASSOC);
        
        if (empty($beforePlan)) {
            throw new \Exception('Plan not found');
        }
        $aid = (int)$beforePlan['action_id'];

        $userReason = trim((string)($data['reason'] ?? ''));
        $context = 'device_action_plans:delete#'.$planId;
        $reasonForAudit = $userReason !== '' ? $userReason : $context;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($reasonForAudit) > 100) $reasonForAudit = mb_substr($reasonForAudit, 0, 100);
        } else {
            if (strlen($reasonForAudit) > 100) $reasonForAudit = substr($reasonForAudit, 0, 100);
        }

        $st2 = $pdo->prepare("DELETE FROM device_action_plans WHERE id=?");
        $st2->execute([$planId]);

        $stInsertA = $pdo->prepare("INSERT INTO audit_trail (action, reason, before_data, after_data, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stInsertA->execute(['delete', $reasonForAudit, json_encode($beforePlan), json_encode([]), $who]);

        $st3 = $pdo->prepare("SELECT SUM(status='done') AS done, COUNT(*) AS total FROM device_action_plans WHERE action_id=?");
        $st3->execute([$aid]);
        $agg = $st3->fetch(PDO::FETCH_ASSOC);

        $st4 = $pdo->prepare("SELECT id, status, approval_status FROM device_actions WHERE id=?");
        $st4->execute([$aid]);
        $beforeIssue = $st4->fetch(PDO::FETCH_ASSOC);

        if (!empty($beforeIssue)) {
            $newStatus = $beforeIssue['status'];
            $newAppr   = $beforeIssue['approval_status'];

            if ($agg && (int)$agg['total'] > 0) {
                if ((int)$agg['done'] === 0) {
                    $newStatus = 'open';
                } elseif ((int)$agg['done'] < (int)$agg['total']) {
                    $newStatus = 'in_progress';
                } else {
                    $newStatus = 'done';
                    $newAppr = ($beforeIssue['approval_status'] === 'approved') ? 'approved' : 'pending';
                }
            }

            if ($newStatus !== $beforeIssue['status'] || $newAppr !== $beforeIssue['approval_status']) {
                $st5 = $pdo->prepare("UPDATE device_actions SET status=?, approval_status=? WHERE id=?");
                $st5->execute([$newStatus, $newAppr, $aid]);

                $st6 = $pdo->prepare("SELECT id, status, approval_status FROM device_actions WHERE id=?");
                $st6->execute([$aid]);
                $afterIssue = $st6->fetch(PDO::FETCH_ASSOC);

                $stInsertA->execute(['update', 'device_actions:auto_status_recalc#'.$aid.' (after plan delete)', json_encode($beforeIssue), json_encode($afterIssue), $who]);
            }
        }
        $pdo->commit();
    } catch (\Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function runScenario($name, $payload, $setupSqls = []) {
    global $pdo;
    echo "====================================\n";
    echo "SCENARIO: {$name}\n";
    
    $pdo->exec("DELETE FROM device_action_plans WHERE id >= 99000");
    $pdo->exec("DELETE FROM device_actions WHERE id = 99999");
    $pdo->exec("DELETE FROM audit_trail WHERE reason LIKE '%device_action%' OR reason LIKE 'Test Del%'");
    
    foreach ($setupSqls as $sql) {
        $pdo->exec($sql);
    }

    try {
        deleteActionPlan($payload, 'test-user');
        echo "HTTP 200 SUCCESS\n";
    } catch (\Exception $e) {
        if ($e->getCode() === 400) {
            echo "HTTP 400 ERROR: " . json_encode(['error' => $e->getMessage()]) . "\n";
        } else {
            echo "HTTP 500 ERROR: " . json_encode(['error' => $e->getMessage()]) . "\n";
        }
    }
    
    if (isset($payload['plan_id']) && $payload['plan_id'] > 0) {
        $aid = 99999;
        
        $st = $pdo->prepare("SELECT COUNT(*) as c FROM device_action_plans WHERE id=?");
        $st->execute([$payload['plan_id']]);
        $exists = $st->fetchColumn() > 0 ? "YES" : "NO";
        echo "PLAN EXISTS: {$exists}\n";
        
        $st = $pdo->prepare("SELECT status, approval_status FROM device_actions WHERE id=?");
        $st->execute([$aid]);
        $issue = $st->fetch(PDO::FETCH_ASSOC);
        echo "ISSUE STATUS AFTER: " . ($issue['status'] ?? 'N/A') . " (Approval: " . ($issue['approval_status'] ?? 'N/A') . ")\n";

        $stA = $pdo->prepare("SELECT action, reason FROM audit_trail ORDER BY id DESC LIMIT 2");
        $stA->execute();
        $audits = $stA->fetchAll(PDO::FETCH_ASSOC);
        echo "AUDIT LOGS TRACE:\n";
        foreach ($audits as $au) {
            echo " - [{$au['action']}] Reason: '{$au['reason']}' \n";
        }
    }
}

// 1) plan_id <= 0
runScenario("1) plan_id <= 0", ['plan_id' => 0]);

// DB insertions
$dCols = "INSERT INTO device_actions (id, device_id, title, status, approval_status) VALUES (99999, 'TEST', 'Test Issue', '%s', '%s')";
$pCols = "INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (%d, 99999, '%s', '%s')";

// 2) plan_id exists, only plan of ISSUE (in_progress, pending)
runScenario("2) plan_id exists, only plan of ISSUE", ['plan_id' => 99001, 'reason' => 'Test Del Only'], [
    sprintf($dCols, 'in_progress', 'pending'),
    sprintf($pCols, 99001, 'Only Plan', 'open')
]);

// 3A) Multiple plans: Before 1 done / 2 total -> delete the done plan
runScenario("3A) Before 1 done / 2 total -> Delete done plan", ['plan_id' => 99002, 'reason' => 'Test Del 3A'], [
    sprintf($dCols, 'in_progress', 'pending'),
    sprintf($pCols, 99001, 'Plan 1', 'open'),
    sprintf($pCols, 99002, 'Plan 2', 'done')
]);

// 3B) Multiple plans: Before 2 done / 2 total -> Delete one done plan
runScenario("3B) Before 2 done / 2 total -> Delete done plan", ['plan_id' => 99002, 'reason' => 'Test Del 3B'], [
    sprintf($dCols, 'done', 'approved'),
    sprintf($pCols, 99001, 'Plan 1', 'done'),
    sprintf($pCols, 99002, 'Plan 2', 'done')
]);

// Cleanup
$pdo->exec("DELETE FROM device_action_plans WHERE id >= 99000");
$pdo->exec("DELETE FROM device_actions WHERE id = 99999");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

