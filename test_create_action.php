<?php

$url = 'http://localhost:8000/api/gateway';

// Test Payload
$payload = [
    'c' => 'DeviceAction',
    'm' => 'createPublic',
    'device_id' => 'MACHINE-01', // Assumes exists
    'title' => 'Test Action from Script',
    'issue_type' => 'maintenance',
    'short_form' => json_encode([
        [
            'label' => 'Action Plan 1',
            'value' => 'Fix the main drive',
            'est' => '2026-10-10',
            'owner' => 'Tech Dave',
            'owner_user_id' => null
        ]
    ])
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

// Assuming JWT auth might be required - or since we fallback to ID 0, maybe allowed.
// Let's see what happens.
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $code\n";
echo "Response: $response\n";
