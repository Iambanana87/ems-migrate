<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * UserController
 * 
 * Handles user-related API requests with strict parity to legacy api.php.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * list_users: Returns id and username of all users.
     * STRICT PARITY with api.php:2112-2116.
     * 
     * @param  Request  $request
     * @return JsonResponse
     */
    public function listUsers(Request $request): JsonResponse
    {
        // 1. Authentication (Strict 401 Parity)
        /** @var array<string, mixed>|null $claims */
        $claims = $request->attributes->get('jwt_claims');
        if ($claims === null) {
            return response()->json(['error' => 'auth_required'], 401, [], \JSON_UNESCAPED_UNICODE);
        }

        // 2. Authorization (Strict 403 Parity for 'admin' requirement)
        $roles = $this->extractRoles($claims);
        if (!in_array('admin', $roles, true)) {
            return response()->json(['error' => 'forbidden'], 403, [], \JSON_UNESCAPED_UNICODE);
        }

        // 3. Execution (Strict SQL Parity via Service)
        $users = $this->userService->listUsers();

        // 4. Response (Strict Raw Array Parity)
        return response()->json($users, 200, [], \JSON_UNESCAPED_UNICODE);
    }

    /**
     * Helper to extract roles from claims.
     * Matches the logic in CheckIamPermission for consistency.
     */
    private function extractRoles(array $claims): array
    {
        $role = $claims['role'] ?? $claims['roles'] ?? null;
        if ($role === null) return [];
        if (is_string($role) && $role !== '') return [strtolower($role)];
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
}
