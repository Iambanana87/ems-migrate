<?php

declare(strict_types=1);

use App\Http\Middleware\CheckIamPermission;
use App\Http\Middleware\EnsureNumericJson;
use App\Http\Middleware\ResolveJwtUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
| EMS Laravel 12 API — bootstrap/app.php
|
| Key configuration:
|   1. API-only routing (no web routes needed)
|   2. ResolveJwtUser middleware aliased for use in routes/api.php
|   3. CORS configured to allow the Vue dev server + production origin
|   4. Global JSON exception rendering for all API errors
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Only API routes — no web.php needed
        api: __DIR__ . '/../routes/api.php',
        // API prefix is "/api" by default — all routes become /api/gateway, /api/health
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ── Middleware Aliases ─────────────────────────────────────────────
        // 'resolve.jwt' → decodes JWT from header/cookie/GET and sets jwt_claims on request.
        // 'check.iam'   → calls IAM service to verify the user has permission for this endpoint.
        //                  Must run AFTER 'resolve.jwt' (reads jwt_claims attribute).
        $middleware->alias([
            'resolve.jwt' => ResolveJwtUser::class,
            'check.iam'   => CheckIamPermission::class,
        ]);

        // ── Cookie Encryption Exclusion ───────────────────────────────────
        // The 'ems_token' cookie is a raw JWT string — encrypting it would
        // break token verification. Exclude it from Laravel's cookie encryptor.
        $middleware->encryptCookies(except: [
            'ems_token',
        ]);

        // ── CORS ──────────────────────────────────────────────────────────
        // Legacy: header("Access-Control-Allow-Origin: *")
        // In production, restrict allowed_origins to your Vue app domain.
        // Controlled via config/cors.php (see cors.php companion file).
        // ── Global JSON Numeric Encoding ──────────────────────────────────
        // Replicates legacy api.php's JSON_NUMERIC_CHECK flag globally.
        // Every JsonResponse is re-encoded so numeric strings become native
        // int/float values. StreamedResponse is skipped safely.
        // Must run LAST (outermost wrap) so it catches all JSON responses
        // including those produced by exception handlers.
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            EnsureNumericJson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ── Global JSON error shape ───────────────────────────────────────
        // Any unhandled exception on an API request returns:
        //   { "status": "error", "message": "..." }
        // This ensures even unexpected 500s match the legacy response contract.
        $exceptions->render(function (\Throwable $e, Request $request): ?\Illuminate\Http\JsonResponse {
            if ($request->is('api/*')) {
                $status  = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = $e->getMessage() ?: 'An unexpected error occurred.';

                // Do not expose stack traces in production
                return response()->json([
                    'status'  => 'error',
                    'message' => $message,
                ], $status);
            }
            return null; // Let Laravel handle non-API exceptions normally
        });
    })
    ->create();
