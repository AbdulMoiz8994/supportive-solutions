@props(['contact' => null, 'types'])

@php
    $statusValue = (string) old('is_active', $contact ? ($contact->is_active ? '1' : '0') : '1');
    $typeOptions = collect($types)->mapWithKeys(fn ($type) => [$type => $type])->all();
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
        <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Basic Information</h3>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @include('pages.directory._field', ['label' => 'Full Name', 'name' => 'name', 'value' => $contact?->name, 'required' => true, 'placeholder' => 'e.g. Dr. Jane Smith', 'colSpan' => 2])
            @include('pages.directory._field', ['label' => 'Contact Type', 'name' => 'type', 'type' => 'select', 'value' => $contact?->type ?? $types[0] ?? '', 'required' => true, 'options' => $typeOptions])
            @include('pages.directory._field', ['label' => 'Job Title / Role', 'name' => 'job_title', 'value' => $contact?->job_title, 'placeholder' => 'e.g. Case Manager'])
        </div>
    </section>

    <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
        <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Organization</h3>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @include('pages.directory._field', ['label' => 'Organization / Company', 'name' => 'clinic_name', 'value' => $contact?->clinic_name])
            @include('pages.directory._field', ['label' => 'Provider ID', 'name' => 'provider_id', 'value' => $contact?->provider_id, 'help' => 'Optional NPI or agency reference ID.'])
        </div>
    </section>

    <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
        <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Contact Information</h3>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @include('pages.directory._field', ['label' => 'Phone', 'name' => 'phone', 'type' => 'tel', 'value' => $contact?->phone, 'placeholder' => '(555) 000-0000'])
            @include('pages.directory._field', ['label' => 'Fax', 'name' => 'fax', 'type' => 'tel', 'value' => $contact?->fax])
            @include('pages.directory._field', ['label' => 'Email', 'name' => 'email', 'type' => 'email', 'value' => $contact?->email, 'colSpan' => 2])
        </div>
    </section>

    <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
        <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Address</h3>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @include('pages.directory._field', ['label' => 'Street Address', 'name' => 'address_line1', 'value' => $contact?->address_line1, 'colSpan' => 2])
            @include('pages.directory._field', ['label' => 'Address Line 2', 'name' => 'address_line2', 'value' => $contact?->address_line2, 'colSpan' => 2])
            @include('pages.directory._field', ['label' => 'City', 'name' => 'city', 'value' => $contact?->city])
            @include('pages.directory._field', ['label' => 'County', 'name' => 'county', 'value' => $contact?->county])
            @include('pages.directory._field', ['label' => 'State', 'name' => 'state', 'value' => $contact?->state, 'maxlength' => 50])
            @include('pages.directory._field', ['label' => 'ZIP Code', 'name' => 'zip', 'value' => $contact?->zip, 'maxlength' => 20])
        </div>
    </section>

    <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
        <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Notes</h3>
        @include('pages.directory._field', ['label' => 'Internal Notes', 'name' => 'notes', 'type' => 'textarea', 'value' => $contact?->notes, 'rows' => 5, 'maxlength' => 5000, 'placeholder' => 'Office-only notes about this contact.'])
    </section>

    <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
        <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Status</h3>
        @include('pages.directory._field', ['label' => 'Record Status', 'name' => 'is_active', 'type' => 'select', 'value' => $statusValue, 'options' => ['1' => 'Active', '0' => 'Inactive'], 'help' => 'Inactive contacts remain searchable but are hidden from default filters.'])
    </section>

    @if(($contact?->type ?? old('type')) === \App\Models\Contact::TYPE_INSURANCE)
        <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
            <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Payer / billing details</h3>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @php
                    $channelOptions = ['' => '— Select channel —'] + collect(\App\Models\Contact::claimChannels())->mapWithKeys(fn ($channel) => [
                        $channel => match ($channel) {
                            \App\Models\Contact::CLAIM_CHANNEL_AVAILITY => '837P · Availity',
                            \App\Models\Contact::CLAIM_CHANNEL_SEPARATE_EDI => '837P · Separate EDI',
                            default => $channel,
                        },
                    ])->all();
                @endphp
                @include('pages.directory._field', ['label' => 'Claim channel', 'name' => 'claim_channel', 'type' => 'select', 'value' => old('claim_channel', $contact?->claim_channel), 'options' => $channelOptions])
                @include('pages.directory._field', ['label' => 'Contracted rate ($/hr)', 'name' => 'contracted_rate', 'type' => 'number', 'value' => old('contracted_rate', $contact?->contracted_rate), 'placeholder' => '30.00', 'step' => '0.01', 'min' => '0'])
            </div>
        </section>
    @endif

    @if(($contact?->type ?? old('type')) === \App\Models\Contact::TYPE_CASE_COORDINATOR)
        @php
            $payerOptions = ['' => '— Select payer / MCO —'] + \App\Models\Contact::query()
                ->where('type', \App\Models\Contact::TYPE_INSURANCE)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        @endphp
        <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
            <h3 class="mb-4 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Plan linkage</h3>
            @include('pages.directory._field', ['label' => 'Parent payer / MCO', 'name' => 'parent_contact_id', 'type' => 'select', 'value' => old('parent_contact_id', $contact?->parent_contact_id), 'options' => $payerOptions, 'colSpan' => 2, 'help' => 'Links this coordinator to their managed care plan entry.'])
        </section>
    @endif

    @if(($contact?->type ?? old('type')) === \App\Models\Contact::TYPE_VENDOR)
        @php
            $vendorOptions = ['' => '— Reference vendor (no API) —'] + collect(\App\Support\DirectoryIntegrationCatalog::vendorOptions())->pluck('label', 'value')->all();
            $credentialOptions = ['' => '— Select credential —'] + collect(\App\Support\DirectoryIntegrationCatalog::credentialKeyOptions())->pluck('label', 'value')->all();
        @endphp
        <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
            <h3 class="mb-1 border-b border-[#eef2f9] pb-2 text-[11px] font-bold uppercase tracking-wider text-[#2563eb]">Integration wiring</h3>
            <p class="mb-4 text-[12px] text-[#64748b]">Link this card to a credential in Global Settings → Credential Vault. Secrets stay in the vault; this card only points to them.</p>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @include('pages.directory._field', ['label' => 'Integration system', 'name' => 'integration_slug', 'type' => 'select', 'value' => old('integration_slug', $contact?->integration_slug), 'options' => $vendorOptions, 'help' => 'Pick a known vendor to auto-fill data flow and app tab.', 'colSpan' => 2])
                @include('pages.directory._field', ['label' => 'Credential vault key', 'name' => 'integration_credential_key', 'type' => 'select', 'value' => old('integration_credential_key', $contact?->integration_credential_key), 'options' => $credentialOptions, 'help' => 'Usually auto-filled when you pick an integration system.'])
                @include('pages.directory._field', ['label' => 'What flows', 'name' => 'data_flow', 'type' => 'textarea', 'value' => old('data_flow', $contact?->data_flow), 'rows' => 2, 'colSpan' => 2])
                @include('pages.directory._field', ['label' => 'App area slug', 'name' => 'app_area', 'value' => old('app_area', $contact?->app_area), 'placeholder' => 'e.g. payroll, communications, billing'])
                @include('pages.directory._field', ['label' => 'Owning agent', 'name' => 'owning_agent', 'value' => old('owning_agent', $contact?->owning_agent), 'placeholder' => 'e.g. Payroll agent'])
            </div>
        </section>
    @endif
</div>
