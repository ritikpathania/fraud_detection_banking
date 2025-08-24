{{-- resources/views/admin.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Header --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-xl font-semibold">Admin — Fraud Audits</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Review fraud decisions produced by the Kotlin service. Use filters to narrow results.
                    </p>
                </div>
            </div>

            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form id="filterForm" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                        <div>
                            <label class="text-sm block mb-1">Transaction ID</label>
                            <input name="transaction_id" placeholder="txn_..." class="w-full border dark:border-gray-700 rounded-md px-3 py-2 bg-white dark:bg-gray-900">
                        </div>

                        <div>
                            <label class="text-sm block mb-1">Action</label>
                            <select name="action" class="w-full border dark:border-gray-700 rounded-md px-3 py-2 bg-white dark:bg-gray-900">
                                <option value="">Any</option>
                                <option value="BLOCK">BLOCK</option>
                                <option value="ALLOW">ALLOW</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm block mb-1">Min Score</label>
                            <input name="min_score" type="number" step="0.01" placeholder="e.g. 0.60" class="w-full border dark:border-gray-700 rounded-md px-3 py-2 bg-white dark:bg-gray-900">
                        </div>

                        <div>
                            <label class="text-sm block mb-1">Since (local)</label>
                            <input name="since_local" type="datetime-local" class="w-full border dark:border-gray-700 rounded-md px-3 py-2 bg-white dark:bg-gray-900">
                        </div>

                        <div>
                            <label class="text-sm block mb-1">Until (local)</label>
                            <input name="until_local" type="datetime-local" class="w-full border dark:border-gray-700 rounded-md px-3 py-2 bg-white dark:bg-gray-900">
                        </div>

                        <div>
                            <label class="text-sm block mb-1">Page Size</label>
                            <input id="pageSize" name="limit" type="number" min="1" max="100" value="20" class="w-full border dark:border-gray-700 rounded-md px-3 py-2 bg-white dark:bg-gray-900">
                        </div>

                        <div class="md:col-span-6 flex gap-2">
                            <button class="px-4 py-2 rounded-md border dark:border-gray-700 bg-gray-100 dark:bg-gray-900">Search</button>
                            <button type="button" id="resetBtn" class="px-4 py-2 rounded-md border dark:border-gray-700">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Table --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <div id="summary" class="text-sm text-gray-500 dark:text-gray-400">—</div>
                        <div class="flex gap-2">
                            <button id="prevBtn" class="px-3 py-1 rounded-md border dark:border-gray-700 disabled:opacity-50">Prev</button>
                            <button id="nextBtn" class="px-3 py-1 rounded-md border dark:border-gray-700 disabled:opacity-50">Next</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                            <tr class="text-left border-b dark:border-gray-700">
                                <th class="py-2 pr-4">Time (UTC)</th>
                                <th class="py-2 pr-4">Txn</th>
                                <th class="py-2 pr-4">Action</th>
                                <th class="py-2 pr-4">Score</th>
                                <th class="py-2 pr-4">Reasons</th>
                                <th class="py-2 pr-4">Model</th>
                            </tr>
                            </thead>
                            <tbody id="rows">
                            <tr><td class="py-3 text-gray-500 dark:text-gray-400" colspan="6">No data</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mt-3">
                        <div id="count" class="text-xs text-gray-500 dark:text-gray-400">count=0</div>
                        <div id="pageInfo" class="text-xs text-gray-500 dark:text-gray-400">page 1</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form     = document.getElementById('filterForm');
            const rows     = document.getElementById('rows');
            const summary  = document.getElementById('summary');
            const countEl  = document.getElementById('count');
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn  = document.getElementById('prevBtn');
            const nextBtn  = document.getElementById('nextBtn');
            const resetBtn = document.getElementById('resetBtn');
            const sizeEl   = document.getElementById('pageSize');

            let page = 0; // 0-based
            let lastCount = 0;
            const API = '/api/audits';

            function toEpochMs(dtLocal) {
                if (!dtLocal) return null;
                const d = new Date(dtLocal);
                if (Number.isNaN(d.getTime())) return null;
                return d.getTime();
            }

            function fmtScore(v) {
                return (typeof v === 'number') ? v.toFixed(2) : (v ?? '');
            }

            function iso(ms) {
                if (ms === null || ms === undefined) return '';
                try { return new Date(ms).toISOString(); } catch { return ''; }
            }

            async function load(p=0) {
                page = Math.max(0, p);
                const size = Math.min(100, Math.max(1, parseInt(sizeEl.value || '20', 10)));

                const fd = new FormData(form);
                const qs = new URLSearchParams();

                const txid = (fd.get('transaction_id') || '').trim();
                const act  = (fd.get('action') || '').trim();       // BLOCK | ALLOW
                const minS = (fd.get('min_score') || '').trim();
                const sinceMs = toEpochMs(fd.get('since_local'));
                const untilMs = toEpochMs(fd.get('until_local'));

                if (txid)   qs.set('transaction_id', txid);
                if (act)    qs.set('action', act);
                if (minS)   qs.set('min_score', minS);
                if (sinceMs !== null) qs.set('since_ms', sinceMs);
                if (untilMs !== null) qs.set('until_ms', untilMs);
                qs.set('limit', String(size));
                qs.set('skip',  String(page * size));

                summary.textContent = 'Loading…';
                rows.innerHTML = '<tr><td class="py-3 text-gray-500 dark:text-gray-400" colspan="6">Loading…</td></tr>';

                const res = await fetch(`${API}?${qs.toString()}`);
                const json = await res.json();  // { items: [], count: N }

                const items = Array.isArray(json.items) ? json.items : [];
                lastCount = Number.isFinite(json.count) ? json.count : items.length;

                rows.innerHTML = '';
                if (!items.length) {
                    rows.innerHTML = '<tr><td class="py-3 text-gray-500 dark:text-gray-400" colspan="6">No data</td></tr>';
                } else {
                    for (const a of items) {
                        const tr = document.createElement('tr');
                        tr.className = 'border-b dark:border-gray-700';
                        tr.innerHTML = `
          <td class="py-2 pr-4 font-mono">${iso(a.createdAtMs)}</td>
          <td class="py-2 pr-4 font-mono">${a.transactionId ?? ''}</td>
          <td class="py-2 pr-4">${a.action ?? ''}</td>
          <td class="py-2 pr-4">${fmtScore(a.score)}</td>
          <td class="py-2 pr-4">${(a.reasons || []).join(', ')}</td>
          <td class="py-2 pr-4 font-mono">${a.modelVersion ?? ''}</td>
        `;
                        rows.appendChild(tr);
                    }
                }

                const start = lastCount ? (page * size + 1) : 0;
                const end   = Math.min((page + 1) * size, lastCount);
                summary.textContent = `${lastCount} items • showing ${start || 0}-${end || 0}`;
                pageInfo.textContent = `page ${page + 1}`;

                prevBtn.disabled = page <= 0;
                nextBtn.disabled = (page + 1) * size >= lastCount;
                countEl.textContent = `count=${lastCount}`;
            }

            form.addEventListener('submit', (e) => { e.preventDefault(); load(0); });
            prevBtn.addEventListener('click', () => load(page - 1));
            nextBtn.addEventListener('click', () => load(page + 1));
            resetBtn.addEventListener('click', () => {
                form.reset();
                sizeEl.value = 20;
                load(0);
            });

            // initial load
            load(0);
        })();
    </script>
@endpush
