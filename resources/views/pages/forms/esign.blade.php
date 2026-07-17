<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign {{ $templateName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/signature.js') }}"></script>
</head>
<body class="bg-[#f8fbff] min-h-screen">
<div class="max-w-xl mx-auto py-10 px-4 space-y-6">
    <div>
        <p class="text-[12px] font-semibold uppercase tracking-wide text-[#64748b]">Electronic signature</p>
        <h1 class="text-[24px] font-extrabold text-[#0f172a] mt-1">{{ $templateName }}</h1>
        <p class="text-[13px] text-[#64748b] mt-1">Please review and sign for {{ $signerName }}.</p>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-[13px] text-[#b91c1c]">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-2xl border border-[#e2e8f0] bg-white p-5 space-y-3">
        @foreach($fields as $field)
            <div>
                <div class="text-[11px] font-semibold text-[#94a3b8] uppercase">{{ $field['label'] ?? $field['key'] }}</div>
                <div class="text-[14px] text-[#0f172a] mt-0.5">{{ $values[$field['key'] ?? ''] ?? '—' }}</div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ url('/esign/'.$token) }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-5 space-y-4" id="esign-form">
        @csrf
        <div>
            <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Full legal name</label>
            <input type="text" name="signed_by_name" value="{{ old('signed_by_name', $signerName) }}" required
                   class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Draw signature</label>
            <canvas id="signature-pad" width="480" height="160"
                    class="w-full border border-[#e2e8f0] rounded-lg bg-white touch-none"></canvas>
            <input type="hidden" name="signature_image" id="signature_image">
            <button type="button" id="clear-signature" class="mt-2 text-[12px] font-semibold text-[#64748b]">Clear</button>
        </div>
        <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-[#2563eb] text-white text-[13px] font-semibold">
            Sign &amp; submit
        </button>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pad = new SignaturePad('signature-pad');
    document.getElementById('clear-signature')?.addEventListener('click', () => pad.clear());
    document.getElementById('esign-form')?.addEventListener('submit', function () {
        document.getElementById('signature_image').value = pad.getBase64();
    });
});
</script>
</body>
</html>
