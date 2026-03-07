<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class QueryRouteMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $controller = $request->input('c');
        $method = $request->input('m');

        if ($controller && $method) {

            $controllerClass = "App\\Http\\Controllers\\{$controller}Controller";

            if (!class_exists($controllerClass)) {
                return response()->json([
                    'error' => 'Controller not found',
                    'controller' => $controller
                ], 404);
            }

            if (!method_exists($controllerClass, $method)) {
                return response()->json([
                    'error' => 'Method not found',
                    'method' => $method
                ], 404);
            }

            try {

                $controllerInstance = app($controllerClass);

                return app()->call([$controllerInstance, $method], [
                    'request' => $request
                ]);

            } catch (\Throwable $e) {

                return response()->json([
                    'error' => $e->getMessage()
                ], 500);

            }

        }

        return $next($request);
    }
}