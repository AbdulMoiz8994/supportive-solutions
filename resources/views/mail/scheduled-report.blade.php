@component('mail::message')
# {{ $reportName }}

**Period:** {{ $periodLabel }}

Your scheduled report is ready. Open SSHC Reports to view the full detail or export as {{ $format }}.

@if(!empty($kpis))
@component('mail::table')
| Metric | Value |
|:--|:--|
@foreach($kpis as $kpi)
| {{ $kpi['label'] ?? '' }} | {{ $kpi['value'] ?? '' }} |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => route('reports.index')])
Open Reports
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
