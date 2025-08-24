<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Models\Transaction;

class AdminTransactionsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'account'   => ['nullable', 'string', 'max:100'],
            'is_fraud'  => ['nullable', Rule::in(['true','false','1','0'])],
            'from'      => ['nullable', 'date'],
            'to'        => ['nullable', 'date'],
            'sort'      => ['nullable', Rule::in(['created_at','amount','fraud_score'])],
            'order'     => ['nullable', Rule::in(['asc','desc'])],
            'page'      => ['nullable', 'integer', 'min:1'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:100'],
            'export'    => ['nullable', Rule::in(['csv'])],
        ]);

        $sort  = $validated['sort']  ?? 'created_at';
        $order = $validated['order'] ?? 'desc';
        $limit = (int)($validated['limit'] ?? 20);
        $page  = (int)($validated['page']  ?? 1);

        $q = Transaction::query();

        if (!empty($validated['account'])) {
            $q->where('account', $validated['account']);
        }

        if (array_key_exists('is_fraud', $validated)) {
            $bool = in_array($validated['is_fraud'], ['true','1'], true);
            $q->where('is_fraud', $bool);
        }

        if (!empty($validated['from'])) {
            $q->where('timestamp', '>=', Carbon::parse($validated['from']));
        }
        if (!empty($validated['to'])) {
            $q->where('timestamp', '<=', Carbon::parse($validated['to']));
        }

        if (($validated['export'] ?? null) === 'csv') {
            $transactions = $q->orderBy($sort, $order)->get();

            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="transactions.csv"',
            ];

            $callback = function () use ($transactions) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, [
                    'id', 'transaction_id', 'account', 'amount',
                    'is_fraud', 'fraud_score', 'timestamp', 'created_at'
                ]);

                foreach ($transactions as $t) {
                    fputcsv($handle, [
                        $t->id,
                        $t->transaction_id,
                        $t->account,
                        $t->amount,
                        $t->is_fraud ? 'true' : 'false',
                        $t->fraud_score,
                        $t->timestamp?->toDateTimeString(),
                        $t->created_at?->toDateTimeString(),
                    ]);
                }
                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        }

        $paginator = $q->orderBy($sort, $order)
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'status'       => 'ok',
            'count'        => $paginator->count(),
            'page'         => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'total_pages'  => $paginator->lastPage(),
            'data'         => $paginator->items(),
        ]);
    }
}
