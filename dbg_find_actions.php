<?php
$content = file_get_contents('c:/xampp/htdocs/ems/api.php');
preg_match_all('/(?:===|==)\s*[\'"]([a-zA-Z0-9_]+)[\'"]/i', $content, $matches);
$actions = [];
foreach($matches[1] as $m) {
    if (strpos($m, 'action') !== false || strpos($m, 'plan') !== false) {
        $actions[] = $m;
    }
}
$uniq = array_unique($actions);
file_put_contents('c:/xampp/htdocs/ems/actions_list.txt', implode("\n", $uniq));
