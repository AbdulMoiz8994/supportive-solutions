<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $definition['name'] ?? 'Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 16px; }
        h2 { font-size: 13px; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        th { background: #f8fafc; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>{{ $definition['name'] ?? 'Report' }}</h1>
    <div class="meta">{{ $periodLabel }}</div>

    @foreach($sheets as $sheet)
        <h2>{{ $sheet['title'] }}</h2>
        <table>
            @if(!empty($sheet['headers']))
                <thead><tr>@foreach($sheet['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            @endif
            <tbody>
                @foreach($sheet['rows'] as $row)
                    <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
