<?php
$from = '2026-02-26T07:00:00';
$to = '2026-02-27T07:00:00';

$urls = [
    'summary' => "http://localhost/ems/api.php?action=get_summary_report&from=$from&to=$to",
    'efficiency' => "http://localhost/ems/api.php?action=get_efficiency_report_data&process=mold&from=$from&to=$to"
];

foreach ($urls as $name => $url) {
    $json = file_get_contents($url);
    if ($json === false) {
        echo "Failed to fetch $name\n";
    } else {
        file_put_contents("c:/xampp/htdocs/ems/expected_$name.json", json_encode(json_decode($json), JSON_PRETTY_PRINT));
        echo "Saved expected_$name.json\n";
    }
}
