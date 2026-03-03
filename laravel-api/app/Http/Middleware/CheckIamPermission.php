<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\IamClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckIamPermission
 *
 * IAM-based permission gate for the /api/gateway route.
 * Runs AFTER ResolveJwtUser (which sets jwt_claims on the request).
 *
 * FLOW:
 *   1. Read jwt_claims from request attributes (set by ResolveJwtUser).
 *   2. If no JWT → pass through (public endpoints handled by RequiresAuth later).
 *   3. Extract the user's role(s) from claims.
 *   4. Fetch allowed endpoint patterns from IAM (60-second cache per role set).
 *   5. Build the endpoint key: "c={C}&m={M}" from query params.
 *   6. fnmatch() each pattern against the key.
 *   7. ANY match → allow.  NO match → 403 permission_denied.
 *
 * DESIGN NOTE:
 *   The legacy middleware_endpoint_logger.php sent the raw Bearer token to IAM
 *   and let IAM decode it. We instead extract roles from the already-decoded
 *   jwt_claims (set by ResolveJwtUser), then call IamClient::getPermissionsForRoles().
 *   This eliminates a redundant IAM decode round-trip.
 *
 * CACHE KEY:
 *   Keyed by md5 of the sorted roles array. Same roles → same permissions cache entry.
 *   TTL: 60 seconds — roles are immutable within a JWT's lifetime, so 60s is safe.
 *
 * FAIL-CLOSED:
 *   If IAM is unreachable, we return 503 rather than silently allowing all requests.
 *   This is a deliberate departure from the legacy fail-open behaviour.
 */
class CheckIamPermission
{
    private const CACHE_TTL = 60; // seconds

    public function __construct(
        private readonly IamClient $iam,
    ) {}

    /**
     * Handle the incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<string, mixed>|null $claims */
        $claims = $request->attributes->get('jwt_claims');

        // ── Step 2: No JWT → transparent pass-through ────────────────────────
        // Public endpoints (Device.live, Family.index, ApStatus.index) work fine.
        // Protected endpoints will be blocked later by RequiresAuth::requireRole().
        if ($claims === null) {
            return $next($request);
        }

        // ── Step 3: Extract roles from claims ────────────────────────────────
        $roles = $this->extractRoles($claims);

        if (empty($roles)) {
            // Claims exist but no role found — treat as unauthorised
            return $this->permissionDenied();
        }

        // ── Step 4: Fetch allowed patterns from IAM (cached) ─────────────────
        $cacheKey = 'iam_perms_' . md5(json_encode(sort($roles) ? $roles : $roles));

        $patterns = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($roles): ?array {
            $result = $this->iam->getPermissionsForRoles($roles);

            // IAM call failed (connection error → status 0) → fail-closed
            if ($result['status'] === 0 || $result['error'] !== null) {
                return null; // signals failure to caller
            }

            // Expect: { "data": ["c=Device&m=*", "c=AuditTrail&m=index", ...] }
            $body = $result['body'] ?? [];
            return is_array($body['data'] ?? null) ? $body['data'] : $body;
        });

        // IAM service unreachable (null signals request failure)
        if ($patterns === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'permission_service_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // ── Step 5: Build endpoint key ─────────────────────────────────────
        // Matches the format the IAM uses in its pattern list.
        $c   = (string) $request->query('c', '');
        $m   = (string) $request->query('m', '');
        $key = "c={$c}&m={$m}";

        $candidates = [$key];

        // ── Step 5.5: Legacy Parity Mapping ────────────────────────────────
        // Support legacy URI-style permission patterns if specified in user requirements.
        if ($c === 'Report' && $m === 'previewNextCodes') {
            $candidates[] = '/ems/api.php?action=preview_next_codes';
        }

        if ($c === 'Report' && $m === 'listBackendIssues') {
            $candidates[] = '/ems/api.php?action=list_backend_issues';
        }

        // ── Step 6 & 7: Pattern match ──────────────────────────────────────
        foreach ($patterns as $pattern) {
            if (! is_string($pattern)) {
                continue;
            }

            foreach ($candidates as $cand) {
                // fnmatch supports '*' wildcard — e.g. "c=Device&m=*" matches any Device action
                if (fnmatch($pattern, $cand, FNM_CASEFOLD)) {
                    return $next($request);
                }
            }
        }

        // No pattern matched
        return $this->permissionDenied();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Extract a flat list of lowercase role names from JWT claims.
     *
     * Mirrors auth_extract_role_names() in legacy helper/auth_helper.php.
     * Handles: plain string, array of strings, array of {name: ...} objects.
     *
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    private function extractRoles(array $claims): array
    {
        $role = $claims['role'] ?? $claims['roles'] ?? null;

        if ($role === null) {
            return [];
        }

        if (is_string($role) && $role !== '') {
            return [strtolower($role)];
        }

        if (is_array($role)) {
            $names = [];
            foreach ($role as $item) {
                $name = match (true) {
                    is_string($item)                      => $item,
                    is_array($item) && isset($item['name'])  => $item['name'],
                    is_object($item) && isset($item->name)   => $item->name,
                    default                               => null,
                };
                if ($name !== null && $name !== '') {
                    $names[] = strtolower($name);
                }
            }
            return array_values(array_unique($names));
        }

        return [];
    }

    /**
     * Build the standard 403 permission_denied response.
     */
    private function permissionDenied(): Response
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'permission_denied',
        ], Response::HTTP_FORBIDDEN);
    }
}
