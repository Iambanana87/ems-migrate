<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * IamClient
 *
 * Dedicated HTTP client for communicating with the external IAM service.
 * Mirrors the legacy helper/api_call.php::api_request() function.
 *
 * IAM endpoint pattern: <base_url>?c=<Controller>&m=<method>
 */
final class IamClient
{
    private readonly string $baseUrl;
    private readonly int    $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('ems.iam.base_url'), '/');
        $this->timeout = (int) config('ems.iam.timeout', 10);
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Authenticate credentials against the IAM service.
     *
     * Maps to legacy: api_request('POST', '?c=AuthController&m=login', [...])
     *
     * @param  string  $username
     * @param  string  $password
     * @param  string  $otp       Empty string if not provided.
     * @return array{status: int, body: array<string,mixed>, raw: string, error: string|null}
     */
    public function authenticate(string $username, string $password, string $otp = ''): array
    {
        return $this->post('?c=AuthController&m=login', [
            'username' => $username,
            'password' => $password,
            'otp'      => $otp,
        ]);
    }

    /**
     * Fetch permissions for a list of roles from the IAM service.
     *
     * Maps to legacy: api_request_iam('GET', '?c=PermissionController&m=getPermissionWithRoles', ...)
     *
     * @param  list<string>  $roles
     * @return array{status: int, body: array<string,mixed>, raw: string, error: string|null}
     */
    public function getPermissionsForRoles(array $roles): array
    {
        return $this->get('?c=PermissionController&m=getPermissionWithRoles', [
            'roles' => $roles,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE TRANSPORT LAYER
    |--------------------------------------------------------------------------
    */

    /**
     * Execute a POST request to the IAM service.
     *
     * Legacy sends JSON body with Content-Type: application/json.
     *
     * @param  array<string, mixed> $data
     * @return array{status: int, body: array<string,mixed>, raw: string, error: string|null}
     */
    private function post(string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post($url, $data);

            return $this->buildResult(
                $response->status(),
                $response->body(),
            );
        } catch (ConnectionException $e) {
            return $this->buildError('IAM service unreachable: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return $this->buildError('IAM request failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute a GET request to the IAM service.
     *
     * @param  array<string, mixed> $query
     * @return array{status: int, body: array<string,mixed>, raw: string, error: string|null}
     */
    private function get(string $endpoint, array $query = []): array
    {
        $url = $this->buildUrl($endpoint);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url, $query);

            return $this->buildResult(
                $response->status(),
                $response->body(),
            );
        } catch (ConnectionException $e) {
            return $this->buildError('IAM service unreachable: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return $this->buildError('IAM request failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Build the full IAM URL.
     *
     * Legacy: if endpoint starts with '?', append directly (no slash inserted).
     * Example: base=".../index.php" + "?c=AuthController&m=login"
     *          → ".../index.php?c=AuthController&m=login"
     */
    private function buildUrl(string $endpoint): string
    {
        if (str_starts_with($endpoint, '?')) {
            return $this->baseUrl . $endpoint;
        }

        return $this->baseUrl . '/' . ltrim($endpoint, '/');
    }

    /**
     * Normalise a successful HTTP response into the standard result shape.
     *
     * @return array{status: int, body: array<string,mixed>, raw: string, error: null}
     */
    private function buildResult(int $status, string $rawBody): array
    {
        $decoded = json_decode($rawBody, associative: true);

        return [
            'status' => $status,
            'body'   => is_array($decoded) ? $decoded : [],
            'raw'    => $rawBody,
            'error'  => null,
        ];
    }

    /**
     * Build a connection-error result (status 0 — never a real HTTP code).
     *
     * @return array{status: int, body: array<string,mixed>, raw: string, error: string}
     */
    private function buildError(string $message): array
    {
        return [
            'status' => 0,
            'body'   => [],
            'raw'    => '',
            'error'  => $message,
        ];
    }
}
