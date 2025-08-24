<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class BankingService
{
    public function __construct(private Client $http = new Client()) {}

    public function process(string $from, string $to, string $amount, ?string $idem = null): array
    {
        $idem = $idem ?: 'lg-' . Str::uuid();

        // You currently accept query params in Spring Boot; keep it simple.
        $url = config('services.java.base') . '/api/transactions/process';
        $query = http_build_query([
            'fromAccount' => $from,
            'toAccount'   => $to,
            'amount'      => $amount,
        ]);

        try {
            $resp = $this->http->post($url . '?' . $query, [
                'headers' => ['Idempotency-Key' => $idem],
                'http_errors' => false,
                'timeout' => 5.0,
                'connect_timeout' => 2.0,
            ]);
            $code = $resp->getStatusCode();
            $json = json_decode($resp->getBody()->getContents(), true) ?: [];

            return [
                'ok' => $code >= 200 && $code < 300,
                'status' => $code,
                'data' => $json,
                'idempotencyKey' => $idem,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 503,
                'data' => ['error' => 'gateway-unavailable', 'detail' => $e->getMessage()],
                'idempotencyKey' => $idem,
            ];
        }
    }

    public function accounts(): array
    {
        $url = config('services.java.base') . '/api/accounts';
        $resp = $this->http->get($url, ['http_errors' => false, 'timeout' => 2.0]);
        return json_decode($resp->getBody()->getContents(), true) ?: [];
    }

    public function recentTx(): array
    {
        $url = config('services.java.base') . '/api/transactions/recent';
        $resp = $this->http->get($url, ['http_errors' => false, 'timeout' => 2.0]);
        return json_decode($resp->getBody()->getContents(), true) ?: [];
    }

    public function blocked(): array
    {
        $url = config('services.java.base') . '/api/transactions/search?status=BLOCKED&page=0&size=20';
        $resp = $this->http->get($url, ['http_errors' => false, 'timeout' => 2.0]);
        return json_decode($resp->getBody()->getContents(), true) ?: [];
    }

    public function dailySummary(?string $tz = 'Asia/Kolkata'): array
    {
        $url = config('services.java.base') . '/api/reports/daily-summary?tz=' . urlencode($tz ?? 'Asia/Kolkata');
        $resp = $this->http->get($url, ['http_errors' => false, 'timeout' => 2.0]);
        return json_decode($resp->getBody()->getContents(), true) ?: [];
    }

    public function series(string $from, string $to, ?string $tz = 'Asia/Kolkata'): array
    {
        $url = config('services.java.base') . '/api/reports/series?from='.$from.'&to='.$to.'&tz='.urlencode($tz ?? 'Asia/Kolkata');
        $resp = $this->http->get($url, ['http_errors' => false, 'timeout' => 2.0]);
        return json_decode($resp->getBody()->getContents(), true) ?: [];
    }

    public function searchTransactions(array $params): array
    {
        $query = http_build_query($params);
        $url = config('services.java.base') . '/api/transactions/search?' . $query;

        $resp = $this->http->get($url, [
            'http_errors' => false,
            'timeout' => 2.5,
        ]);

        $code = $resp->getStatusCode();
        $json = json_decode($resp->getBody()->getContents(), true) ?: [];
        $total = (int)($resp->getHeaderLine('X-Total-Count') ?: 0);

        return [
            'ok' => $code >= 200 && $code < 300,
            'status' => $code,
            'items' => $json,
            'total' => $total,
        ];
    }
}
