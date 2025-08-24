<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use App\Models\Transaction;
use App\Mail\FraudFlagged;

class GatewayController extends Controller
{
    public function analyzeTransaction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'amount'         => 'required|numeric|min:0.01',
            'account'        => 'required|string',
            'currency'       => 'required|string|in:USD,INR,EUR,GBP',
            'timestamp'      => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $fraudApi = rtrim(config('services.fraud_api.base_url'), '/');
        try {
            $resp = Http::timeout(5)->post("{$fraudApi}/check_fraud", $data);
        } catch (\Throwable $e) {
            Log::error('Fraud API unreachable', ['err' => $e->getMessage()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Fraud service unreachable',
            ], 502);
        }

        if (!$resp->ok()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Fraud service error',
                'code'    => $resp->status(),
            ], 502);
        }

        $fraud = $resp->json();
        $isFraud = !empty($fraud['reasons']) || (isset($fraud['is_fraud']) && $fraud['is_fraud']);
        $fraudScore =        $fraud['fraud_score'] ?? null;
        $reasons    =        $fraud['reasons']     ?? [];

        if (!empty($reasons)) {
            $isFraud = true;
        }

        $txn = Transaction::create([
            'transaction_id' => $data['transaction_id'],
            'account'        => $data['account'],
            'amount'         => $data['amount'],
            'currency'       => $data['currency'],
            'timestamp'      => $data['timestamp'] ?? now(),
            'fraud_score'    => $fraudScore,
            'is_fraud'       => $isFraud,
            'reasons'        => $reasons,
        ]);

        if ($txn->is_fraud) {
            try {
                Mail::to('admin@example.com')->send(new FraudFlagged($txn));
            } catch (\Throwable $e) {
                Log::warning('Failed to send fraud email', ['err' => $e->getMessage()]);
            }
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'Transaction analyzed successfully',
            'data'    => [
                'transaction_id' => $txn->transaction_id,
                'is_fraud'       => (bool) $txn->is_fraud,
                'fraud_score'    => is_null($txn->fraud_score) ? null : (float) $txn->fraud_score,
                'reasons'        => $txn->reasons ?? [],
            ],
        ]);
    }

    public function listTransactions(Request $request)
    {
        $limit = min((int) $request->query('limit', 25), 100);

        $query = Transaction::query();

        if ($request->filled('account')) {
            $query->where('account', $request->query('account'));
        }
        if ($request->filled('is_fraud')) {
            $query->where('is_fraud', (bool)$request->query('is_fraud'));
        }

        $items = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'status' => 'ok',
            'count'  => $items->count(),
            'data'   => $items->map(function ($t) {
                return [
                    'id'             => $t->id,
                    'transaction_id' => $t->transaction_id,
                    'account'        => $t->account,
                    'amount'         => (float)$t->amount,
                    'currency'       => $t->currency,
                    'timestamp'      => optional($t->timestamp)->toISOString(),
                    'fraud_score'    => $t->fraud_score !== null ? (float)$t->fraud_score : null,
                    'is_fraud'       => (bool)$t->is_fraud,
                    'reasons'        => $t->reasons ?? [],
                    'created_at'     => $t->created_at->toISOString(),
                ];
            }),
        ]);
    }

    public function recentTransactions(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $items = Transaction::orderByDesc('id')
            ->limit(min($limit, 200))
            ->get([
                'id','transaction_id','account','amount','currency',
                'timestamp','fraud_score','is_fraud','reasons','created_at'
            ]);
        return response()->json(['status' => 'ok', 'data' => $items]);
    }

    public function fraudRules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rule_name' => 'required|string',
            'threshold' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Fraud rules applied successfully',
            'data' => $request->all()
        ]);
    }

    public function notifyUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'message' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User notified',
            'data' => $request->all()
        ]);
    }

    public function getRiskCategoryAttribute()
    {
        $score = (float) $this->fraud_score;

        return match (true) {
            $score <= 0.25 => 'low',
            $score <= 0.60 => 'medium',
            $score <= 1.00 => 'high',
            default => null,
        };
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'pong',
        ]);
    }
}
