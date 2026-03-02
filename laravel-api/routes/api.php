<?php

declare(strict_types=1);

use App\Http\Controllers\Api\GatewayController;
use App\Http\Middleware\ResolveJwtUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — EMS Laravel Gateway
|--------------------------------------------------------------------------
|
| This is the ONLY route file for the EMS API.
| ALL frontend requests are dispatched through the single gateway route
| using the legacy ?c=<Controller>&m=<method> query-string convention.
|
| Frontend call example:
|   POST /api/gateway?c=Auth&m=login      → AuthController::login()
|   POST /api/gateway?c=Auth&m=logout     → AuthController::logout()
|   GET  /api/gateway?c=Auth&m=me         → AuthController::me()
|
| Adding new actions: update DISPATCH_MAP in GatewayController.php only.
| No changes to this file are needed when adding new c/m pairs.
|
*/

Route::any('/gateway', [GatewayController::class, 'dispatch'])
    ->middleware(['resolve.jwt', 'check.iam'])
    ->name('gateway');

/*
|--------------------------------------------------------------------------
| Health Check (no auth, no gateway dispatch)
|--------------------------------------------------------------------------
| Used by infrastructure / Docker health probes.
*/
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
