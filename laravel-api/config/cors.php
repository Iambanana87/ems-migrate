<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
| Mirrors the legacy CORS headers in api.php and backend.php:
|   header("Access-Control-Allow-Origin: *");
|   header("Access-Control-Allow-Headers: Authorization, Content-Type");
|   header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
|
| PRODUCTION: Replace allowed_origins '*' with your specific Vue app domain.
| DEVELOPMENT: '*' allows the Vue dev server (http://localhost:5174) to call
|              the API without CORS errors.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:5173'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
