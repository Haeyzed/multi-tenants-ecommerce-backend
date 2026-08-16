<?php

declare(strict_types=1);

namespace App\Contracts\Shipping;

use Illuminate\Http\Client\Response;

/**
 * HTTP client boundary for future shipping carrier API integrations.
 */
interface CarrierHttpClientInterface
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $query = [], array $headers = [], ?int $timeout = null): Response;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function post(string $url, array $data = [], array $headers = [], ?int $timeout = null): Response;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function put(string $url, array $data = [], array $headers = [], ?int $timeout = null): Response;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function delete(string $url, array $data = [], array $headers = [], ?int $timeout = null): Response;
}
