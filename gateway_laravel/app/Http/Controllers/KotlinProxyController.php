<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KotlinProxyController extends Controller
{
    private function client()
    {
        $base = rtrim(config('services.kotlin.base_url'), '/');
        $timeoutSec = max(1, (int) config('services.kotlin.timeout_ms') / 1000);
        return Http::timeout($timeoutSec)->baseUrl($base);
    }

    public function healthKotlin()
    {
        try {
            $resp = $this->client()->get('/health');
            return response($resp->body(), $resp->status())
                ->header('Content-Type', $resp->header('Content-Type', 'application/json'));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'gateway_health_error',
                'base'  => config('services.kotlin.base_url'),
                'msg'   => $e->getMessage(),
                'type'  => class_basename($e),
            ], 502);
        }
    }

    public function balance(Request $request)
    {
        $request->validate(['account_id' => 'required|string']);
        try {
            $resp = $this->client()->get('/balance', [
                'account_id' => $request->query('account_id'),
            ]);
            return response($resp->body(), $resp->status())
                ->header('Content-Type', $resp->header('Content-Type', 'application/json'));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'gateway_balance_error',
                'base'  => config('services.kotlin.base_url'),
                'msg'   => $e->getMessage(),
                'type'  => class_basename($e),
            ], 502);
        }
    }

    public function transfer(Request $request)
    {
        // Match Kotlin’s schema (amount is a STRING; Kotlin converts to minor units)
        $data = $request->validate([
            'from_account' => 'required|string',
            'to_account'   => 'required|string',
            'amount'       => 'required|string',
            'currency'     => 'required|string|size:3',
            'metadata'     => 'sometimes|array',
        ]);

        // Ensure idempotency key exists (Kotlin reads this header)
        $idem = $request->header('Idempotency-Key') ?: (string) Str::uuid();

        try {
            $resp = $this->client()
                ->withHeaders(['Idempotency-Key' => $idem, 'Content-Type' => 'application/json'])
                ->post('/transfer', $data);

            // Return raw JSON and bubble back the idempotency key
            return response($resp->body(), $resp->status())
                ->header('Content-Type', $resp->header('Content-Type', 'application/json'))
                ->header('Idempotency-Key', $idem);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'gateway_transfer_error',
                'base'  => config('services.kotlin.base_url'),
                'msg'   => $e->getMessage(),
                'type'  => class_basename($e),
            ], 502);
        }
    }

    public function audits(\Illuminate\Http\Request $req)
    {
        $qs = array_filter([
            'transaction_id' => $req->query('transaction_id'),
            'action'         => $req->query('action'),
            'min_score'      => $req->query('min_score'),
            'since_ms'       => $req->query('since_ms'),
            'until_ms'       => $req->query('until_ms'),
            'limit'          => $req->query('limit'),
            'skip'           => $req->query('skip'),
        ], fn($v) => !is_null($v) && $v !== '');

        try {
            $resp = $this->client()->get('/audits', $qs);
            return response($resp->body(), $resp->status())
                ->header('Content-Type', $resp->header('Content-Type', 'application/json'));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'gateway_audits_error',
                'base'  => config('services.kotlin.base_url'),
                'msg'   => $e->getMessage(),
                'type'  => class_basename($e),
            ], 502);
        }
    }
}
