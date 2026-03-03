<?php

$pdo = new PDO('mysql:host=localhost;dbname=ems_migrate;charset=utf8', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Temporarily disable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

function runScenario($name, $payload, $setupSqls = []) {
    global $pdo;
    echo "====================================\n";
    echo "SCENARIO: {$name}\n";
    
    // Cleanup & Setup
    $pdo->exec("DELETE FROM device_action_plans WHERE id >= 99000");
    $pdo->exec("DELETE FROM device_actions WHERE id = 99999");
    $pdo->exec("DELETE FROM audit_trail WHERE detail LIKE '%device_action%'");
    
    foreach ($setupSqls as $sql) {
        $pdo->exec($sql);
    }

    $ch = curl_init('http://localhost:8000/api/gateway?c=DeviceAction&m=deleteActionPlan');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    $responseLaravel = curl_exec($ch);
    $laravelCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP CODE: {$laravelCode}\n";
    echo "RESPONSE: " . $responseLaravel . "\n";
    
    if (isset($payload['plan_id']) && $payload['plan_id'] > 0) {
        $aid = 99999;
        
        $st = $pdo->prepare("SELECT COUNT(*) as c FROM device_action_plans WHERE id=?");
        $st->execute([$payload['plan_id']]);
        $exists = $st->fetchColumn() > 0 ? "YES" : "NO";
        echo "PLAN EXISTS AFTER: {$exists}\n";
        
        $st = $pdo->prepare("SELECT status, approval_status FROM device_actions WHERE id=?");
        $st->execute([$aid]);
        $issue = $st->fetch(PDO::FETCH_ASSOC);
        echo "ISSUE STATUS AFTER: " . ($issue['status'] ?? 'N/A') . " (Approval: " . ($issue['approval_status'] ?? 'N/A') . ")\n";

        // Audit check
        $stA = $pdo->prepare("SELECT action, reason, detail FROM audit_trail ORDER BY id DESC LIMIT 2");
        $stA->execute();
        $audits = $stA->fetchAll(PDO::FETCH_ASSOC);
        echo "AUDIT LOGS:\n";
        foreach ($audits as $au) {
            echo " - [{$au['action']}] Reason: '{$au['reason']}' (Detail: '{$au['detail']}')\n";
        }
    }
}

// 1) plan_id <= 0
runScenario("1) plan_id <= 0", ['plan_id' => 0]);

// Remove column requirements not in base schema depending on strict mode 
$dCols = "INSERT INTO device_actions (id, device_id, title, status, approval_status) VALUES (99999, 'TEST', 'Test Issue', '%s', '%s')";
$pCols = "INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (%d, 99999, '%s', '%s')";

// 2) plan_id exists, only plan of ISSUE (in_progress, pending)
runScenario("2) plan_id exists, only plan of ISSUE", ['plan_id' => 99001, 'reason' => 'Test Del'], [
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

