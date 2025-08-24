{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Balance --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold">Balance</h2>
                    <div class="mt-3 flex items-center gap-2">
                        <label class="text-sm">Account ID</label>
                        <input id="acct" value="ACC123" class="border dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900">
                        <button onclick="loadBalance()" class="px-3 py-1 border rounded">Refresh</button>
                    </div>
                    <pre id="bal" class="mt-3 text-sm bg-gray-50 dark:bg-gray-900/40 p-2 rounded">—</pre>
                </div>
            </div>

            {{-- Transfer --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold">Transfer</h2>

                    <form id="txForm" class="grid sm:grid-cols-2 gap-3 mt-3">
                        <div>
                            <label class="text-sm block mb-1">From</label>
                            <input name="from_account" value="ACC123" class="w-full border dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900" required>
                        </div>
                        <div>
                            <label class="text-sm block mb-1">To</label>
                            <input name="to_account" value="ACC456" class="w-full border dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900" required>
                        </div>
                        <div>
                            <label class="text-sm block mb-1">Amount</label>
                            <input name="amount" value="25.00" class="w-full border dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900" required>
                        </div>
                        <div>
                            <label class="text-sm block mb-1">Currency</label>
                            <input name="currency" value="INR" class="w-full border dark:border-gray-700 rounded px-2 py-1 bg-white dark:bg-gray-900" required>
                        </div>

                        <div class="sm:col-span-2 flex items-center gap-2">
                            <button class="px-3 py-1 border rounded">Submit</button>
                            <span id="idem" class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-900/40"></span>
                        </div>
                    </form>

                    <pre id="tx" class="mt-3 text-sm bg-gray-50 dark:bg-gray-900/40 p-2 rounded">—</pre>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const API = '/api';

        async function loadBalance(){
            const acct = document.getElementById('acct').value.trim();
            const r = await fetch(`${API}/balance?account_id=${encodeURIComponent(acct)}`);
            const j = await r.json();
            document.getElementById('bal').textContent = JSON.stringify(j, null, 2);
        }

        function uuidv4(){
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        document.getElementById('txForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const body = {
                from_account: fd.get('from_account')?.toString().trim(),
                to_account:   fd.get('to_account')?.toString().trim(),
                amount:       fd.get('amount')?.toString().trim(),   // keep as STRING (Kotlin converts to minor units)
                currency:     fd.get('currency')?.toString().trim()
            };

            const idem = uuidv4();
            document.getElementById('idem').textContent = `Idempotency-Key: ${idem}`;

            const r = await fetch(`${API}/transfer`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Idempotency-Key': idem },
                body: JSON.stringify(body)
            });

            const txt = await r.text(); // show raw body to see all fields
            document.getElementById('tx').textContent = txt;

            // optionally refresh the balance of the "from" account
            document.getElementById('acct').value = body.from_account;
            loadBalance();
        });

        // initial balance load
        loadBalance();
    </script>
@endpush
