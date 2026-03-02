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
     * Return the currently authenticated user's profile from the JWT.
     *
     * GET gateway: c=Auth&m=me
     *
     * Legacy mirrors:
     *   - backend/login.php  (GET branch with valid cookie → respond_json_ok)
     *
     * Requires: ResolveJwtUser middleware to have run and stored claims on
     *           the request attributes as 'jwt_claims' and 'jwt_token'.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $claims */
        $claims = $request->attributes->get('jwt_claims');

        /** @var string|null $token */
        $token = $request->attributes->get('jwt_token');

        // If ResolveJwtUser found no valid token, claims will be null.
        // Mirror legacy: respond_json_error('auth_required', 401)
        if ($claims === null || $token === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'auth_required',
            ], 401);
        }

        return response()->json(
            $this->authService->buildMeResponse($token, $claims)
        );
    }
}
