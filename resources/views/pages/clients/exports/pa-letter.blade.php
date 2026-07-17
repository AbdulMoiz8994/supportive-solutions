<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $paNumber }} — Prior Authorization</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .muted { color: #64748b; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: left; font-size: 14px; }
        th { background: #f8fafc; width: 30%; }
    </style>
</head>
<body>
    <h1>Prior Authorization Letter</h1>
    <p class="muted">{{ $paNumber }} · Generated {{ now()->format('F j, Y') }}</p>

    <table>
        <tr><th>Client</th><td>{{ $client->first_name }} {{ $client->last_name }}</td></tr>
        <tr><th>Medicaid ID</th><td>{{ $client->member_id ?? '—' }}</td></tr>
        <tr><th>Service code</th><td>{{ $auth->billing_code ?? '—' }}</td></tr>
        <tr><th>Authorized units</th><td>{{ $auth->total_units ?? '—' }}</td></tr>
        <tr><th>Effective</th><td>{{ $auth->start_date ? \Carbon\Carbon::parse($auth->start_date)->format('F j, Y') : '—' }}</td></tr>
        <tr><th>Expires</th><td>{{ $auth->end_date ? \Carbon\Carbon::parse($auth->end_date)->format('F j, Y') : '—' }}</td></tr>
        <tr><th>Status</th><td>{{ $auth->status ?? '—' }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 32px;">This document was generated from authorization records on file. Upload the official PA letter to the client documents folder when available.</p>
</body>
</html>
