<?php
$content = file_get_contents('c:/xampp/htdocs/ems/api.php');
$actions = [];

// 1. Strings in $PUBLIC_ACTIONS array
if (preg_match('/\$PUBLIC_ACTIONS\s*=\s*\[(.*?)\];/s', $content, $m)) {
    preg_match_all('/\'([a-z0-9_]+)\'/', $m[1], $matches);
    foreach ($matches[1] as $action) $actions[] = $action;
}

// 2. Direct comparisons in if/elseif blocks
preg_match_all('/===?\s*\'([a-z0-9_]+)\'/', $content, $matches);
foreach ($matches[1] as $action) $actions[] = $action;

preg_match_all('/===?\s*"([a-z0-9_]+)"/', $content, $matches);
foreach ($matches[1] as $action) $actions[] = $action;

// 3. $_GET/$_POST checks
preg_match_all('/\[\'action\'\]\s*===\s*\'([a-z0-9_]+)\'/', $content, $matches);
foreach ($matches[1] as $action) $actions[] = $action;

$uniqueActions = array_unique($actions);
sort($uniqueActions);
echo implode("\n", $uniqueActions);
