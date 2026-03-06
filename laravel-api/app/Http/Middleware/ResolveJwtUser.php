<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveJwtUser
 *
 * NON-BLOCKING middleware: decodes the JWT and stores the claims on the
 * request attributes, but never aborts on its own. Controllers decide
 * whether authentication is required for their specific action.
 *
 * Token source priority (mirrors legacy auth_helper.php::auth_user_or_null_ui):
 *   1. Authorization: Bearer <token>   (strict API clients, Vue axios)
 *   2. Cookie: ems_token               (legacy UI pages, browser auto-send)
 *   3. GET ?token=<token>              (legacy URL-based access — avoid in new code)
 *
 * On success: sets request attributes:
 *   - jwt_claims  → array<string,mixed>  (verified & decoded payload)
 *   - jwt_token   → string               (raw JWT string — needed by me() endpoint)
 *
 * On failure (invalid/expired/missing): attributes remain unset.
 */
class ResolveJwtUser
{
    public function __construct(
        private readonly JwtService $jwtService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token !== null) {
            try {
                $claims = $this->jwtService->decode($token);

                // Store both the decoded claims and the raw token.
                // Both are consumed by AuthController::me().
                $request->attributes->set('jwt_claims', $claims);
                $request->attributes->set('jwt_token',  $token);
            } catch (RuntimeException) {
                // Invalid signature or expired — continue without claims.
                // The stale cookie will be expired by the controller if needed,
                // or the next request's logout() action will clear it.
            }
        }

        return $next($request);
    }

    /*
    |--------------------------------------------------------------------------
    | TOKEN EXTRACTION
    |--------------------------------------------------------------------------
    */

    private function extractToken(Request $request): ?string
    {
        // 1) Prefer Authorization: Bearer (standard API header)
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            // Check common Apache/REDIRECT fallbacks if standard bearerToken() fails
            $auth = $request->header('Authorization') 
                 ?? $request->server('HTTP_AUTHORIZATION') 
                 ?? $request->server('REDIRECT_HTTP_AUTHORIZATION');
            
            if (is_string($auth) && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                $bearer = $matches[1];
            }
        }

        if ($bearer !== null && $bearer !== '') {
            return $bearer;
        }

        // 2) HttpOnly cookie (browsers send this automatically)
        $cookieName = (string) config('ems.jwt_cookie_name', 'ems_token');
        $cookie     = $request->cookie($cookieName);
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }

        // 3) GET ?token= (legacy fallback — avoid in new code)
        $queryToken = $request->query('token');
        if (is_string($queryToken) && $queryToken !== '') {
            return $queryToken;
        }

        return null;
    }
}
