<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        th { background: #f8fafc; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ $periodLabel }}</div>

    <table>
        @if(!empty($headers))
            <thead><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        @endif
        <tbody>
            @forelse($rows as $row)
                <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ max(count($headers), 1) }}">No rows match your filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
