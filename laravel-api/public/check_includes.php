<?php
header('Content-Type: application/json');
echo json_encode([
    'included_files' => get_included_files(),
    'auto_prepend_file' => ini_get('auto_prepend_file'),
]);
