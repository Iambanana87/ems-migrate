<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * JwtService
 *
 * A self-contained HS256 JWT encode / decode service.
 * Reimplements the logic from legacy helper/jwt.php with full type safety.
 *
 * Shared secret: config('ems.jwt_secret')
 * Algorithm:     HMAC-SHA256  (HS256)
 */
final class JwtService
{
    public function __construct(
        private readonly string $secret
    ) {}

    /*
    |--------------------------------------------------------------------------
    | ENCODE
    |--------------------------------------------------------------------------
    */

    /**
     * Create a signed JWT from a payload array.
     *
     * @param  array<string, mixed> $payload
     */
    public function encode(array $payload): string
    {
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $sig     = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true));

        return "{$header}.{$payload}.{$sig}";
    }

    /*
    |--------------------------------------------------------------------------
    | DECODE & VERIFY
    |--------------------------------------------------------------------------
    */

    /**
     * Decode and VERIFY a JWT.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException  on invalid structure, bad signature, or expiry.
     */
    public function decode(string $jwt): array
    {
        [$header, $payloadB64, $signature] = $this->splitJwt($jwt);

        // Verify signature
        $expected = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payloadB64}", $this->secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('JWT signature mismatch.');
        }

        $payload = $this->decodePayloadSegment($payloadB64);

        // Check expiry
        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new RuntimeException('JWT has expired.');
        }

        return $payload;
    }

    /**
     * Decode WITHOUT verifying the signature.
     *
     * Used only for non-sensitive reads: checking `exp` before verifying,
     * refreshing cookie TTL, etc. NEVER use for authorization decisions.
     *
     * @return array<string, mixed>|null  null if the JWT is structurally invalid.
     */
    public function decodeWithoutVerify(string $jwt): ?array
    {
        try {
            [, $payloadB64] = $this->splitJwt($jwt);

            return $this->decodePayloadSegment($payloadB64);
        } catch (\Throwable) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNALS
    |--------------------------------------------------------------------------
    */

    /**
     * Split a JWT into its three base64url segments.
     *
     * @return array{string, string, string}
     *
     * @throws RuntimeException
     */
    private function splitJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new RuntimeException('JWT must have exactly 3 segments.');
        }

        return $parts; // @phpstan-ignore-line (count checked above)
    }

    /**
     * Base64url-decode a JWT segment and json_decode to an associative array.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function decodePayloadSegment(string $segment): array
    {
        $json = base64_decode(strtr($segment, '-_', '+/'), strict: false);

        if ($json === false) {
            throw new RuntimeException('JWT payload segment is not valid base64url.');
        }

        $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException('JWT payload is not a JSON object.');
        }

        return $data;
    }

    /** base64url encode (no padding). */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
