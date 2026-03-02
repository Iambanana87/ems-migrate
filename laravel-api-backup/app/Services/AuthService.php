<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * AuthService
 *
 * Orchestrates the full authentication flow:
 *   1. Delegates credential verification to the IAM service via IamClient.
 *   2. Decodes & normalises the JWT returned by IAM.
 *   3. Builds the exact user-payload response shape the frontend expects.
 *
 * Mirrors logic from: backend/login.php (credential flow + role normalisation)
 *                     helper/auth_helper.php (extractRolesFromClaim, getPrimaryRole)
 */
final class AuthService
{
    public function __construct(
        private readonly IamClient  $iam,
        private readonly JwtService $jwt,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    /**
     * Authenticate a user via the IAM service.
     *
     * On success, returns the complete JSON payload the frontend expects:
     *   { status, token, user: { id, uid, username, role, roles[], exp } }
     *
     * On failure, throws a RuntimeException whose message is safe to expose
     * directly in the API error response.
     *
     * @return array{status: string, token: string, user: array<string,mixed>}
     *
     * @throws RuntimeException  on IAM failure, bad token, or expired token.
     */
    public function login(string $username, string $password, string $otp = ''): array
    {
        // 1) Call IAM
        $result = $this->iam->authenticate($username, $password, $otp);

        if ($result['status'] === 0) {
            // Transport-level failure (connection refused, timeout, etc.)
            throw new RuntimeException('Auth service unreachable.');
        }

        // 2) Extract the access token from the IAM response body
        $body  = $result['body'];
        $token = (string) ($body['accessToken'] ?? '');

        if ($result['status'] !== 200 || $token === '') {
            // IAM returned a non-200 or missing token — surface its error message.
            // Mirrors legacy: stripos($apiErr, 'otp') !== false → 'OTP error!'
            $apiError = (string) ($body['error'] ?? ($result['raw'] !== '' ? $result['raw'] : 'auth_failed'));
            $message  = stripos($apiError, 'otp') !== false ? 'OTP error!' : $apiError;
            throw new RuntimeException($message);
        }

        // 3) Decode & verify the JWT (same secret shared with IAM)
        try {
            $payload = $this->jwt->decode($token);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Invalid or expired token.');
        }

        // 4) Build and return the standardised response
        return [
            'status' => 'ok',
            'token'  => $token,
            'user'   => $this->buildUserPayload($payload),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE CURRENT USER (for "me" endpoint)
    |--------------------------------------------------------------------------
    */

    /**
     * Build the user payload from already-decoded JWT claims.
     *
     * Used by AuthController::me() after the ResolveJwtUser middleware has
     * already verified & decoded the token.
     *
     * @param  array<string, mixed> $claims
     * @return array{status: string, token: string, user: array<string,mixed>}
     */
    public function buildMeResponse(string $token, array $claims): array
    {
        return [
            'status' => 'ok',
            'token'  => $token,
            'user'   => $this->buildUserPayload($claims),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE NORMALISATION
    |--------------------------------------------------------------------------
    | Mirrors: auth_extract_role_names() + auth_primary_role() in auth_helper.php
    |
    | IAM may return `role` / `roles` in one of several shapes:
    |   a) plain string:             "admin"
    |   b) array of strings:         ["admin", "manager"]
    |   c) array of objects:         [{"uuid":"…","name":"admin","description":"…"}, …]
    |--------------------------------------------------------------------------
    */

    /**
     * Extract a normalised list of lowercase role names from a JWT claim value.
     *
     * @param  mixed           $claim  The raw value of `role` or `roles` from the JWT payload.
     * @return list<string>            e.g. ['admin', 'manager']
     */
    public function extractRolesFromClaim(mixed $claim): array
    {
        if (is_string($claim) && $claim !== '') {
            return [strtolower($claim)];
        }

        if (! is_array($claim)) {
            return [];
        }

        $names = [];
        foreach ($claim as $item) {
            $name = match (true) {
                is_string($item)                                             => $item,
                is_array($item)  && isset($item['name'])                    => (string) $item['name'],
                is_object($item) && isset($item->name)                      => (string) $item->name,  // @phpstan-ignore-line
                default                                                      => null,
            };

            if ($name !== null && $name !== '') {
                $names[] = strtolower($name);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Return the highest-priority role from a normalised role list.
     *
     * Hierarchy defined in config('ems.role_hierarchy'): admin > manager > viewer > user
     *
     * @param  list<string> $roleNames
     */
    public function getPrimaryRole(array $roleNames): string
    {
        $hierarchy = (array) config('ems.role_hierarchy', ['admin', 'manager', 'viewer', 'user']);

        foreach ($hierarchy as $candidate) {
            if (in_array($candidate, $roleNames, strict: true)) {
                return $candidate;
            }
        }

        return $roleNames[0] ?? 'user';
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL BUILDER
    |--------------------------------------------------------------------------
    */

    /**
     * Produce the `user` object the frontend expects, from raw JWT claims.
     *
     * Legacy shape (login.php → respond_json_ok):
     *   { id, uid, username, role, roles[], exp }
     *
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildUserPayload(array $payload): array
    {
        // Roles — prefer `roles` over `role` (newer IAM format)
        $rawClaim = $payload['roles'] ?? ($payload['role'] ?? []);
        $roles    = $this->extractRolesFromClaim($rawClaim);
        $primary  = $this->getPrimaryRole($roles);

        return [
            'id'       => is_numeric($payload['user_id'] ?? null) ? (int) $payload['user_id'] : 0,
            'uid'      => isset($payload['sub']) ? (string) $payload['sub'] : null,
            'username' => $payload['username'] ?? ($payload['name'] ?? null),
            'role'     => $primary,
            'roles'    => $roles,
            'exp'      => isset($payload['exp']) ? (int) $payload['exp'] : null,
        ];
    }
}
