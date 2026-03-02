<?php
function base64url_encode_jwt($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode_jwt($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

// Tạo JWT
function jwt_encode(array $payload, string $secret): string {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $h = base64url_encode_jwt(json_encode($header));
    $p = base64url_encode_jwt(json_encode($payload));
    $s = base64url_encode_jwt(hash_hmac('sha256', "$h.$p", $secret, true));
    return "$h.$p.$s";
}

// Giải JWT
function jwt_decode(string $jwt, string $secret) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;
    list($h, $p, $s) = $parts;
    $valid = base64url_encode_jwt(hash_hmac('sha256', "$h.$p", $secret, true));
    if (!hash_equals($valid, $s)) return false;

    $payload = json_decode(base64url_decode_jwt($p), true);
    if (!$payload) return false;
    if (isset($payload['exp']) && time() > $payload['exp']) return false;
    return $payload;
    
}
