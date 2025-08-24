<!doctype html>
<html>
<head><meta charset="utf-8"><title>Admin</title>
    <style>table{border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px}</style>
</head>
<body>
<h2>Blocked Transactions</h2>
<pre>{{ json_encode($blocked, JSON_PRETTY_PRINT) }}</pre>

<h2>Daily Summary</h2>
<pre>{{ json_encode($summary, JSON_PRETTY_PRINT) }}</pre>
</body>
</html>
