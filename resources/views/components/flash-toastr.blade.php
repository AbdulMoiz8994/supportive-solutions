@php
    $flashMessages = array_values(array_filter([
        session('success') ? ['type' => 'success', 'message' => session('success')] : null,
        session('error') ? ['type' => 'error', 'message' => session('error')] : null,
        session('warning') ? ['type' => 'warning', 'message' => session('warning')] : null,
        session('info') ? ['type' => 'info', 'message' => session('info')] : null,
        session('status') ? ['type' => 'info', 'message' => session('status')] : null,
    ]));
@endphp

@if (! empty($flashMessages) || $errors->any())
    <script id="flash-messages-data" type="application/json">
        {!! json_encode([
            'flash' => $flashMessages,
            'errors' => $errors->all(),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>
@endif
