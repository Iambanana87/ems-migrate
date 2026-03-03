<?php
$content = file_get_contents('c:/xampp/htdocs/ems/api.php');
$actions = [];

// Match ($_GET['action'] ?? '') === '...' or ($_POST['action'] ?? '') === '...' or variations
preg_match_all('/(?:\\\$_GET|\\\$_POST|\\\$_REQUEST|\\\$_SESSION)\s*\[\s*[\'"]action[\'"]\s*\]\s*(?:\?\?\s*[\'"][\'"]\s*)?\s*[!=]==?\s*[\'"]([a-zA-Z0-1_]+)[\'"]/', $content, $matches);
foreach ($matches[1] as $m) {
    if ($m) $actions[] = $m;
}

// Match $action === '...' where $action was previously set from $_REQUEST['action']
preg_match_all('/\$action\s*(?:\?\?\s*[\'"][\'"]\s*)?\s*[!=]==?\s*[\'"]([a-zA-Z0-1_]+)[\'"]/', $content, $matches);
foreach ($matches[1] as $m) {
    if ($m) $actions[] = $m;
}

$uniqueActions = array_unique($actions);
sort($uniqueActions);
echo implode("\n", $uniqueActions);
