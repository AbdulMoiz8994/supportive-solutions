<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Caregiver Audit — {{ $caregiver->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .muted { color: #64748b; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; vertical-align: top; }
        th { background: #f8fafc; text-align: left; font-size: 11px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>Caregiver Audit Log</h1>
    <p class="muted">{{ $caregiver->name }} · Exported {{ now()->format('F j, Y g:i A') }} · {{ $logs->count() }} entries</p>

    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Entity / Change</th>
                <th>Source</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->occurred_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->actor_name }}<br><span class="muted">{{ $log->actor_role }}</span></td>
                    <td>{{ $log->action }}</td>
                    <td>
                        {{ $log->entity }}
                        @if($log->value_before || $log->value_after || $log->detail)
                            <br>{{ collect([$log->value_before, $log->value_after, $log->detail])->filter()->implode(' → ') }}
                        @endif
                    </td>
                    <td>{{ $log->source }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
