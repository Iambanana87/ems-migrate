<?php
use Illuminate\Support\Facades\Route;

Route::get('/debug-headers', function () {
    return response()->json([
        'all_headers' => getallheaders(),
        'request_headers' => request()->headers->all(),
        'bearer_token' => request()->bearerToken(),
        'server' => array_intersect_key($_SERVER, array_flip(['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'])),
    ]);
});
