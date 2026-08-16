<?php

declare(strict_types=1);

namespace App\Services\Shipping\Http;

use App\Contracts\Shipping\CarrierHttpClientInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Laravel HTTP facade wrapper for future carrier API clients.
 */
class LaravelCarrierHttpClient implements CarrierHttpClientInterface
{
    public function get(string $url, array $query = [], array $headers = [], ?int $timeout = null): Response
    {
        return $this->request($headers, $timeout)->get($url, $query);
    }

    public function post(string $url, array $data = [], array $headers = [], ?int $timeout = null): Response
    {
        return $this->request($headers, $timeout)->asJson()->post($url, $data);
    }

    public function put(string $url, array $data = [], array $headers = [], ?int $timeout = null): Response
    {
        return $this->request($headers, $timeout)->asJson()->put($url, $data);
    }

    public function delete(string $url, array $data = [], array $headers = [], ?int $timeout = null): Response
    {
        return $this->request($headers, $timeout)->asJson()->delete($url, $data);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function request(array $headers = [], ?int $timeout = null): PendingRequest
    {
        $timeout ??= (int) config('shipping.http.timeout', 15);
        $connectTimeout = (int) config('shipping.http.connect_timeout', 5);

        $pending = Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->acceptJson();

        if ($headers !== []) {
            $pending = $pending->withHeaders($headers);
        }

        return $pending;
    }
}
