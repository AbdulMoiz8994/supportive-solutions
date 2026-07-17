@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6" x-data="{ fields: @js($prefill), signatureName: '{{ $prefill['signature_name'] ?? '' }}' }">

    <div>
        <a href="{{ route('forms.submissions.show', $submission['id']) }}" class="text-[12px] font-semibold text-[#2563eb]">← Back to submission</a>
        <h1 class="text-[24px] font-extrabold text-[#0f172a] mt-2">{{ $template['name'] }}</h1>
        <p class="text-[13px] text-[#64748b]">Editing for {{ $subject['name'] }}</p>
    </div>

    <form method="POST" action="{{ route('forms.submissions.update', $submission['id']) }}" class="rounded-2xl border border-[#e6eef9] bg-white p-5 space-y-4" id="form-edit">
        @csrf
        @method('PUT')
        <input type="hidden" name="fields[signature_image]" id="signature_image" value="">

        <div class="rounded-xl bg-[#f8fbff] border border-[#e6eef9] px-4 py-3 text-[12px] text-[#64748b]">
            Status: <strong class="text-[#0f172a]">{{ $submission['status_label'] }}</strong>
        </div>

        @foreach($fields as $field)
            <div>
                <label class="block text-[12px] font-semibold text-[#64748b] mb-1">{{ $field['label'] }}</label>
                @if(($field['type'] ?? 'text') === 'textarea')
                    <textarea name="fields[{{ $field['key'] }}]" x-model="fields['{{ $field['key'] }}']" rows="3"
                              class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]"
                              {{ !empty($field['readonly']) ? 'readonly' : '' }}></textarea>
                @else
                    <input type="text" name="fields[{{ $field['key'] }}]" x-model="fields['{{ $field['key'] }}']"
                           class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]"
                           {{ !empty($field['readonly']) ? 'readonly' : '' }}>
                @endif
            </div>
        @endforeach

        @if(!empty($template['requires_signature']))
            <div class="border-t border-[#e2e8f0] pt-4 space-y-3">
                <div>
                    <label class="block text-[12px] font-semibold text-[#64748b] mb-1">E-Signature (type full name)</label>
                    <input type="text" name="fields[signature_name]" x-model="signatureName"
                           class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] font-serif italic">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Or draw signature</label>
                    <canvas id="signature-pad" width="520" height="160"
                            class="w-full border border-[#e2e8f0] rounded-lg bg-white touch-none"></canvas>
                    <button type="button" id="clear-signature" class="mt-2 text-[12px] font-semibold text-[#64748b]">Clear pad</button>
                </div>
            </div>
        @endif

        <div class="flex flex-wrap gap-2 pt-2">
            <x-ui.btn variant="outline" type="submit" name="action" value="save">Save changes</x-ui.btn>
            <x-ui.btn variant="outline" type="submit" name="action" value="send_signature">Send for e-signature</x-ui.btn>
            <x-ui.btn variant="primary" type="submit" name="action" value="sign">Sign now</x-ui.btn>
        </div>
    </form>
</div>

@if(!empty($template['requires_signature']))
<script src="{{ asset('js/signature.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pad = new SignaturePad('signature-pad');
    document.getElementById('clear-signature')?.addEventListener('click', () => pad.clear());
    document.getElementById('form-edit')?.addEventListener('submit', function () {
        const input = document.getElementById('signature_image');
        if (input) input.value = pad.getBase64();
    });
});
</script>
@endif
@endsection
