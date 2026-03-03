<?php
$content = file_get_contents('c:/xampp/htdocs/ems/api.php');
preg_match_all('/(?:===|==)\s*[\'"]([a-zA-Z0-9_]+)[\'"]/i', $content, $matches);
$actions = [];
foreach($matches[1] as $m) {
    if (strpos($m, 'action') !== false || strpos($m, 'plan') !== false || strpos($m, 'report') !== false || strpos($m, 'count') !== false || strpos($m, 'device') !== false) {
        $actions[] = $m;
    }
}
print_r(array_unique($actions));
