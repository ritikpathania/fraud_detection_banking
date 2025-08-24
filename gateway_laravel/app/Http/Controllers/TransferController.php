<?php

namespace App\Http\Controllers;

use App\Services\BankingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(private BankingService $bank) {}

    public function transfer(Request $req): JsonResponse
    {
        $data = $req->validate([
            'from'   => 'required|string',
            'to'     => 'required|string|different:from',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $idem = $req->header('Idempotency-Key') ?: $req->input('idempotencyKey');
        $res = $this->bank->process($data['from'], $data['to'], (string)$data['amount'], $idem);

        if (!$res['ok']) {
            return response()->json([
                'message' => $res['data']['message'] ?? 'flagged or failed',
                'detail'  => $res['data'],
                'idempotencyKey' => $res['idempotencyKey'],
            ], $res['status']);
        }

        return response()->json([
            'message' => $res['data']['message'] ?? 'Transaction successful',
            'idempotencyKey' => $res['idempotencyKey'],
        ]);
    }

    public function dashboard()
    {
        return view('dashboard', [
            'accounts' => $this->bank->accounts(),
            'recent'   => $this->bank->recentTx(),
        ]);
    }

    public function admin(Request $req)
    {
        return view('admin', [
            'blocked' => $this->bank->blocked(),
            'summary' => $this->bank->dailySummary($req->query('tz', 'Asia/Kolkata')),
        ]);
    }
}
