<?php

namespace App\Http\Controllers;

use App\Services\BankingService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private BankingService $bank) {}

    public function dashboard(Request $req)
    {
        return view('dashboard', [
            'accounts' => $this->bank->accounts(),
            'recent'   => $this->bank->recentTx(),
        ]);
    }

    public function admin(Request $req)
    {
        return view('admin');
    }

    public function series(Request $req)
    {
        $from = $req->query('from') ?? now()->subDays(14)->toDateString();
        $to   = $req->query('to')   ?? now()->toDateString();
        $tz   = $req->query('tz')   ?? 'Asia/Kolkata';

        return response()->json($this->bank->series($from, $to, $tz));
    }

    public function search(Request $req)
    {
        $params = [
            'status' => $req->query('status', ''),
            'account'=> $req->query('account', ''),
            'from'   => $req->query('from', ''),
            'to'     => $req->query('to', ''),
            'page'   => (int)$req->query('page', 0),
            'size'   => (int)$req->query('size', 10),
        ];
        $res = $this->bank->searchTransactions($params);
        return response()->json($res);
    }
}
