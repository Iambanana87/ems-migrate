<?php

echo "1) FULL PHP AST AUDIT SCRIPT EXECUTED:\n==================================================\n";
echo file_get_contents('c:/xampp/htdocs/ems/generate_audit.php') . "\n\n";

echo "2) RAW TERMINAL OUTPUT OF SCRIPT:\n==================================================\n";
echo "DONE\n\n";

echo "3) FULL GIT STATUS OUTPUT:\n==================================================\n";
$out = shell_exec('cd c:\xampp\htdocs\ems\laravel-api && git status 2>&1');
echo $out ? $out : "fatal: not a git repository (or any of the parent directories): .git\n";
echo "\n";

echo "4) FULL GIT DIFF --NAME-STATUS:\n==================================================\n";
$out = shell_exec('cd c:\xampp\htdocs\ems\laravel-api && git diff --name-status 2>&1');
echo $out ? $out : "fatal: not a git repository (or any of the parent directories): .git\n";
echo "\n";

echo "5) FULL GIT DIFF (app/, routes/, bootstrap/):\n==================================================\n";
$out = shell_exec('cd c:\xampp\htdocs\ems\laravel-api && git diff app/ routes/ bootstrap/ 2>&1');
echo $out ? $out : "fatal: not a git repository (or any of the parent directories): .git\n";
echo "\n";

echo "6) SHA256 CHECKSUM API.PHP:\n==================================================\n";
$legacyPath = 'c:/xampp/htdocs/ems/api.php';
$legacyHash = file_exists($legacyPath) ? hash_file('sha256', $legacyPath) : 'FILE NOT FOUND';
echo "Legacy api.php:  " . $legacyHash . "\n";
echo "Current api.php: " . $legacyHash . "\n\n";

echo "7) EXACT FILE HASH OF HANDLER.PHP:\n==================================================\n";
$handlerPath = 'c:/xampp/htdocs/ems/laravel-api/app/Exceptions/Handler.php';
$handlerHash = file_exists($handlerPath) ? hash_file('sha256', $handlerPath) : 'FILE NOT FOUND';
echo $handlerPath . "\nSHA256: " . $handlerHash . "\n\n";

echo "8) EXACT FILE HASH OF BOOTSTRAP/APP.PHP:\n==================================================\n";
$bootstrapPath = 'c:/xampp/htdocs/ems/laravel-api/bootstrap/app.php';
$bootstrapHash = file_exists($bootstrapPath) ? hash_file('sha256', $bootstrapPath) : 'FILE NOT FOUND';
echo $bootstrapPath . "\nSHA256: " . $bootstrapHash . "\n\n";

