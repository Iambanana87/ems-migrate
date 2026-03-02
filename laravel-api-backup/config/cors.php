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

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    // Production: replace with explicit domains, local dev: use Vue app URL
    'allowed_origins' => ['http://localhost:5174', 'http://127.0.0.1:5174'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    // Mirrors legacy: OPTIONS → 204 No Content + preflight headers immediately
    'max_age' => 86400,

    // Allow cookies (ems_token) to be sent cross-origin.
    // NOTE: When supports_credentials is true, allowed_origins CANNOT be '*'.
    // Set allowed_origins to the explicit Vue origin in production.
    'supports_credentials' => false,

];
