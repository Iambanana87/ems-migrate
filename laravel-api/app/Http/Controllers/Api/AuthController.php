<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * AuthController
 *
 * Handles authentication actions dispatched by the Gateway:
 *   c=Auth & m=login   → login()
 *   c=Auth & m=logout  → logout()
 *   c=Auth & m=me      → me()
 *
 * All responses are JSON only. This controller never renders HTML.
 *
 * Token lifecycle:
 *   - On login:   JWT is returned in the JSON body AND set as an HttpOnly
 *                 SameSite=Lax cookie so both SPA (localStorage) and
 *                 legacy server-rendered pages work without changes.
 *   - On logout:  The HttpOnly cookie is expired server-side. The client
 *                 is responsible for clearing localStorage (handled by
 *                 the existing Vue authService.js).
 *   - On me:      Token claims are already decoded by ResolveJwtUser
 *                 middleware and stored in request attributes.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly JwtService  $jwtService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | m=login
    |--------------------------------------------------------------------------
    */

    /**
     * Authenticate credentials and issue a JWT.
     *
     * POST gateway: c=Auth&m=login
     *
     * Legacy mirrors:
     *   - backend/login.php  (POST branch → IAM call → token → set cookie)
     *   - respond_json_ok()  (exact response shape)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                username: $request->getUsername(),
                password: $request->getPassword(),
                otp:      $request->getOtp(),
            );
        } catch (RuntimeException $e) {
            // Mirror legacy: respond_json_error($error, 401)
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 401);
        }

        $token = $result['token'];

        // Compute cookie TTL in minutes from the JWT `exp` claim.
        // Legacy: setcookie('ems_token', $token, ['expires' => $payload['exp'], ...])
        $exp        = (int) ($result['user']['exp'] ?? (time() + (int) config('ems.jwt_ttl', 3600)));
        $ttlMinutes = (int) max(1, ceil(($exp - time()) / 60));
        $isSecure   = $request->isSecure();
        $cookieName = (string) config('ems.jwt_cookie_name', 'ems_token');

        // Response shape:
        //   { "status": "ok", "token": "<jwt>", "user": { id, uid, username, role, roles[], exp } }
        return response()
            ->json($result)
            ->withCookie(
                cookie(
                    name:     $cookieName,
                    value:    $token,
                    minutes:  $ttlMinutes,
                    path:     '/',
                    domain:   null,
                    secure:   $isSecure,
                    httpOnly: true,
                    raw:      false,
                    sameSite: 'Lax',
                )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | m=logout
    |--------------------------------------------------------------------------
    */

    /**
     * Invalidate the current session by expiring the JWT cookie.
     *
     * POST gateway: c=Auth&m=logout
     *
     * Legacy mirrors:
     *   - backend/logout.php  (clears ems_token cookie across multiple paths)
     *
     * Note: JWT is stateless — no server-side blacklist in this implementation.
     * The cookie is expired across all known legacy paths to match legacy behaviour.
     * Client must clear localStorage separately (handled by Vue authService.js).
     */
    public function logout(Request $request): JsonResponse
    {
        $cookieName = (string) config('ems.jwt_cookie_name', 'ems_token');
        $isSecure   = $request->isSecure();

        // Build a base expired-cookie instance
        $clearCookie = fn (string $path) => cookie(
            name:     $cookieName,
            value:    '',
            minutes:  -2880,    // 48 hours in the past — definitely expired
            path:     $path,
            domain:   null,
            secure:   $isSecure,
            httpOnly: true,
            raw:      false,
            sameSite: 'Lax',
        );

        // Legacy clears cookie for paths: '/', '/backend', '/api', '/api.php'
        $response = response()->json(['status' => 'ok']);

        foreach (['/', '/backend', '/api', '/api.php'] as $path) {
            $response->withCookie($clearCookie($path));
        }

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | m=me
    |--------------------------------------------------------------------------
    */

    /**
     * whoami: Return the currently authenticated user's profile from the JWT.
     * STRICT PARITY with api.php:2696-2707.
     *
     * GET gateway: c=Auth&m=whoami (or legacy m=me)
     */
    public function me(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $claims */
        $claims = $request->attributes->get('jwt_claims');

        // Legacy: $u = require_auth();
        if ($claims === null) {
            return response()->json([
                'error' => 'auth_required'
            ], 401, [], \JSON_UNESCAPED_UNICODE);
        }

        // Legacy: 'id' => (int)($u['id'] ?? 0),
        // (Note: $u['id'] is populated from claims['user_id'] in helper)
        $id = isset($claims['user_id']) && is_numeric($claims['user_id']) 
            ? (int) $claims['user_id'] 
            : 0;

        // Legacy: 'role' => $u['role'] ?? 'user'
        $role = $claims['role'] ?? 'user';

        $data = [
            'logged_in' => true,
            'id'        => $id,
            // Legacy: 'username'  => $u['username'] ?? null
            'username'  => $claims['username'] ?? null,
            // Legacy: 'role'      => $u['role'] ?? 'user'
            'role'      => $role,
            // Legacy: 'is_admin'  => strtolower($u['role'] ?? '') === 'admin'
            'is_admin'  => strtolower($role) === 'admin',
        ];

        // Legacy: header('Cache-Control: no-store');
        return response()->json($data, 200, [
            'Cache-Control' => 'no-store',
        ], \JSON_UNESCAPED_UNICODE);
    }
}
