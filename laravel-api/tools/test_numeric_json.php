<?php
require 'vendor/autoload.php';

// Test 1 — Numeric string coercion (the core fix)
$body     = '{"efficiency":"85.50","loss_pcs":"12","count":"0","rate":"1.000"}';
$decoded  = json_decode($body, true, 512, 0);
$flags    = JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;
$reencoded = json_encode($decoded, $flags);

echo 'Test 1 — Numeric string coercion' . PHP_EOL;
echo '  BEFORE : ' . $body . PHP_EOL;
echo '  AFTER  : ' . $reencoded . PHP_EOL;

$out = json_decode($reencoded, true);
$ok  = is_float($out['efficiency']) && is_int($out['loss_pcs']) && is_int($out['count']) && is_float($out['rate']);
echo '  TYPES  : efficiency=' . gettype($out['efficiency']) . ' loss_pcs=' . gettype($out['loss_pcs']) . PHP_EOL;
echo '  RESULT : ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL . PHP_EOL;

// Test 2 — literal null is valid JSON (must not be flagged as malformed)
$nullBody    = 'null';
$nullDecoded = json_decode($nullBody, true, 512, 0);
$nullErrNone = (json_last_error() === JSON_ERROR_NONE);
echo 'Test 2 — literal null' . PHP_EOL;
echo '  decoded=null, error_none=' . ($nullErrNone ? 'true' : 'false') . PHP_EOL;
echo '  RESULT : ' . ($nullDecoded === null && $nullErrNone ? 'PASS' : 'FAIL') . PHP_EOL . PHP_EOL;

// Test 3 — Malformed JSON detection
$bad = '{not valid';
json_decode($bad, true, 512, 0);
$malformed = (json_last_error() !== JSON_ERROR_NONE);
echo 'Test 3 — Malformed JSON detection' . PHP_EOL;
echo '  error=' . json_last_error_msg() . PHP_EOL;
echo '  RESULT : ' . ($malformed ? 'PASS' : 'FAIL') . PHP_EOL . PHP_EOL;

// Test 4 — JSON_PRESERVE_ZERO_FRACTION: 1.0 stays 1.0 not 1
$floatBody     = '{"val":1.0}';
$floatDecoded  = json_decode($floatBody, true, 512, 0);
$floatEncoded  = json_encode($floatDecoded, $flags);
echo 'Test 4 — JSON_PRESERVE_ZERO_FRACTION' . PHP_EOL;
echo '  BEFORE : ' . $floatBody . PHP_EOL;
echo '  AFTER  : ' . $floatEncoded . PHP_EOL;
echo '  RESULT : ' . (str_contains($floatEncoded, '1.0') ? 'PASS' : 'FAIL') . PHP_EOL;
