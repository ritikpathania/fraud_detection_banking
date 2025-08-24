<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin • Transactions</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
  <div class="max-w-7xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Transactions</h1>

    <!-- Filters -->
    <form method="GET" class="grid md:grid-cols-6 gap-3 mb-6 bg-white p-4 rounded-lg shadow">
      <input type="text" name="account" value="{{ $query['account'] ?? '' }}" placeholder="Account"
             class="border rounded px-3 py-2 w-full">

      <select name="is_fraud" class="border rounded px-3 py-2 w-full">
        <option value="" {{ !isset($query['is_fraud']) ? 'selected' : '' }}>All</option>
        <option value="true"  {{ ($query['is_fraud'] ?? '') === 'true' ? 'selected' : '' }}>Fraud only</option>
        <option value="false" {{ ($query['is_fraud'] ?? '') === 'false' ? 'selected' : '' }}>Non-fraud</option>
      </select>

      <input type="datetime-local" name="from"
             value="{{ isset($query['from']) ? \Illuminate\Support\Str::of($query['from'])->replace('Z','')->toString() : '' }}"
             class="border rounded px-3 py-2 w-full">

      <input type="datetime-local" name="to"
             value="{{ isset($query['to']) ? \Illuminate\Support\Str::of($query['to'])->replace('Z','')->toString() : '' }}"
             class="border rounded px-3 py-2 w-full">

      <div class="flex gap-2">
        <select name="sort" class="border rounded px-3 py-2 w-full">
          @foreach (['id','transaction_id','account','amount','currency','timestamp','fraud_score','is_fraud','created_at'] as $col)
            <option value="{{ $col }}" {{ $sort===$col ? 'selected' : '' }}>
              Sort: {{ ucfirst(str_replace('_',' ', $col)) }}
            </option>
          @endforeach
        </select>
        <select name="order" class="border rounded px-3 py-2 w-full">
          <option value="desc" {{ $order==='desc' ? 'selected' : '' }}>Desc</option>
          <option value="asc"  {{ $order==='asc'  ? 'selected' : '' }}>Asc</option>
        </select>
      </div>

      <div class="flex gap-2">
        <input type="number" min="1" max="100" name="limit" value="{{ $limit }}" class="border rounded px-3 py-2 w-full" placeholder="Limit">
        <button class="bg-blue-600 text-white px-4 rounded hover:bg-blue-700">Apply</button>
        <button name="export" value="csv" class="bg-green-600 text-white px-4 rounded hover:bg-green-700">Export CSV</button>
      </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-100 text-xs uppercase text-gray-600 sticky top-0">
          @php
            $columns = [
              'id' => 'ID',
              'transaction_id' => 'Txn ID',
              'account' => 'Account',
              'amount' => 'Amount',
              'currency' => 'Currency',
              'timestamp' => 'Timestamp',
              'fraud_score' => 'Fraud Score',
              'is_fraud' => 'Status',
              'reasons' => 'Reasons',
              'created_at' => 'Created',
            ];
          @endphp
          <tr>
            @foreach ($columns as $colKey => $colLabel)
              <th class="px-4 py-3 text-left whitespace-nowrap">
                @if($colKey === 'reasons')
                  {{ $colLabel }}
                @else
                  @php
                    $isActive = $sort === $colKey;
                    $icon = $isActive
                      ? ($order === 'asc' ? '▲' : '▼')
                      : '△';
                  @endphp
                  <a href="{{ request()->fullUrlWithQuery([
                    'sort' => $colKey,
                    'order' => $isActive && $order === 'asc' ? 'desc' : 'asc',
                  ]) }}"
                     class="flex items-center gap-1 hover:text-blue-600">
                    {{ $colLabel }}
                    <span class="text-[10px]">{{ $icon }}</span>
                  </a>
                @endif
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($paginator as $row)
          <tr class="hover:bg-gray-50 odd:bg-white even:bg-gray-50/50">
            <td class="px-4 py-3">{{ $row->id }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ $row->transaction_id }}</td>
            <td class="px-4 py-3">{{ $row->account }}</td>
            <td class="px-4 py-3 text-right font-semibold">{{ number_format((float)$row->amount,2) }}</td>
            <td class="px-4 py-3">
              <span class="inline-block px-2 py-1 text-xs rounded bg-blue-100 text-blue-700">
                {{ $row->currency }}
              </span>
            </td>
            <td class="px-4 py-3">{{ optional($row->timestamp)->toDateTimeString() }}</td>
            <td class="px-4 py-3 text-right">
              @php $fs = is_numeric($row->fraud_score) ? (float)$row->fraud_score : null; @endphp
              {{ $fs !== null ? number_format($fs, 2) : '—' }}
            </td>
            <td class="px-4 py-3">
              @if($row->is_fraud)
                <span class="inline-block px-2 py-1 text-xs rounded bg-red-100 text-red-700 font-medium">Fraud</span>
              @else
                <span class="inline-block px-2 py-1 text-xs rounded bg-emerald-100 text-emerald-700 font-medium">OK</span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs">
              @if(is_array($row->reasons))
                <ul class="list-disc pl-5 space-y-1">
                  @foreach($row->reasons as $r)
                    <li>{{ $r }}</li>
                  @endforeach
                </ul>
              @else
                —
              @endif
            </td>
            <td class="px-4 py-3">{{ $row->created_at->toDateTimeString() }}</td>
          </tr>
        @empty
          <tr><td class="px-4 py-6 text-center text-gray-500" colspan="10">No results</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
      {{ $paginator->onEachSide(1)->links() }}
    </div>
  </div>
</body>
</html>
