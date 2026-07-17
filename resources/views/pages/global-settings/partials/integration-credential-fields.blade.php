@php
    $meta = $entry['metadata'] ?? [];
    $flags = $entry['secret_flags'] ?? [];
    $name = fn (string $field) => "credentials[{$index}][metadata][{$field}]";
    $oldMeta = fn (string $field) => old("credentials.{$index}.metadata.{$field}", $meta[$field] ?? '');
    $secretPlaceholder = fn (string $field) => ($flags[$field] ?? false) ? 'Leave blank to keep current' : 'Enter value';
    $label = $settingsLabel ?? 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5';
    $input = $settingsInput ?? 'w-full px-3.5 py-2.5 text-sm bg-gray-50 border border-gray-100 rounded-xl font-semibold';
    $select = $settingsSelect ?? $input;
    $sub = $settingsSubheading ?? 'text-[10px] font-black uppercase tracking-widest text-gray-400';
@endphp

@if($key === \App\Models\IntegrationCredential::KEY_AVAILITY)
    <div class="space-y-4">
        <p class="{{ $sub }}">Availity API · MICH 837P (Billing & Claims Audit)</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            <div>
                <label class="{{ $label }}">Environment</label>
                <select name="{{ $name('env') }}" class="{{ $select }}">
                    <option value="demo" @selected($oldMeta('env') === 'demo')>Demo</option>
                    <option value="production" @selected($oldMeta('env') === 'production')>Production</option>
                </select>
            </div>
            <div>
                <label class="{{ $label }}">App ID</label>
                <input type="text" name="{{ $name('app_id') }}" value="{{ $oldMeta('app_id') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Default payer ID</label>
                <input type="text" name="{{ $name('default_payer_id') }}" value="{{ $oldMeta('default_payer_id') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Demo client ID</label>
                <input type="password" name="{{ $name('demo_key') }}" placeholder="{{ $secretPlaceholder('demo_key') }}" autocomplete="new-password" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Demo client secret</label>
                <input type="password" name="{{ $name('demo_secret') }}" placeholder="{{ $secretPlaceholder('demo_secret') }}" autocomplete="new-password" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Production client ID</label>
                <input type="password" name="{{ $name('prod_key') }}" placeholder="{{ $secretPlaceholder('prod_key') }}" autocomplete="new-password" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Production client secret</label>
                <input type="password" name="{{ $name('prod_secret') }}" placeholder="{{ $secretPlaceholder('prod_secret') }}" autocomplete="new-password" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Token URL</label>
                <input type="text" name="{{ $name('token_url') }}" value="{{ $oldMeta('token_url') }}" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="{{ $label }}">API base URL</label>
                <input type="text" name="{{ $name('api_base_url') }}" value="{{ $oldMeta('api_base_url') }}" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="{{ $label }}">Demo OAuth scope</label>
                <input type="text" name="{{ $name('scope_demo') }}" value="{{ $oldMeta('scope_demo') }}" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="{{ $label }}">Production OAuth scope</label>
                <input type="text" name="{{ $name('scope_prod') }}" value="{{ $oldMeta('scope_prod') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Request type</label>
                <input type="text" name="{{ $name('request_type') }}" value="{{ $oldMeta('request_type') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Diagnosis code</label>
                <input type="text" name="{{ $name('default_diagnosis_code') }}" value="{{ $oldMeta('default_diagnosis_code') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Place of service</label>
                <input type="text" name="{{ $name('place_of_service') }}" value="{{ $oldMeta('place_of_service') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Patient relationship</label>
                <input type="text" name="{{ $name('patient_relationship') }}" value="{{ $oldMeta('patient_relationship') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Token cache (sec)</label>
                <input type="number" name="{{ $name('token_cache_seconds') }}" value="{{ $oldMeta('token_cache_seconds') }}" min="60" max="3600" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">HTTP timeout (sec)</label>
                <input type="number" name="{{ $name('timeout') }}" value="{{ $oldMeta('timeout') }}" min="5" max="120" class="{{ $input }}">
            </div>
        </div>
    </div>
@elseif($key === \App\Models\IntegrationCredential::KEY_ACCOUNTANTSWORLD)
    @php
        $awAuthMode = old("credentials.{$index}.metadata.auth_mode", $meta['auth_mode'] ?? 'api_key');
    @endphp
    <div class="space-y-4" x-data="{ authMode: @js($awAuthMode) }">
        <p class="{{ $sub }}">AccountantsWorld / Payroll Relief</p>
        <p class="text-[11px] text-[#64748b]">Choose how BeydounTech authenticates with Payroll Relief. Use <strong>API key</strong> when AccountantsWorld gave you an <code class="text-[10px]">x-api-key</code> only, <strong>OAuth</strong> when they gave <code class="text-[10px]">client_id</code> + <code class="text-[10px]">client_secret</code> only, or <strong>Both</strong> if your tenant requires Bearer token and x-api-key together.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
                <label class="{{ $label }}">Portal URL</label>
                <input type="text" name="{{ $name('portal_url') }}" value="{{ $oldMeta('portal_url') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">API URL</label>
                <input type="text" name="{{ $name('api_url') }}" value="{{ $oldMeta('api_url') }}" placeholder="https://dev-api.payrollrelief.com/integration" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Authentication mode</label>
                <select name="{{ $name('auth_mode') }}" x-model="authMode" class="{{ $input }}">
                    <option value="api_key">API key (X-API-Key) only</option>
                    <option value="oauth">OAuth (Bearer token) only</option>
                    <option value="both">Both (API key + OAuth)</option>
                </select>
            </div>
            <div class="sm:col-span-2" x-show="authMode === 'api_key' || authMode === 'both'" x-cloak>
                <label class="{{ $label }}">App ID (x-api-key)</label>
                <input type="text" name="{{ $name('app_id') }}" value="{{ $oldMeta('app_id') }}" placeholder="Provided by AccountantsWorld" autocomplete="off" class="{{ $input }}">
            </div>
            <div x-show="authMode === 'oauth' || authMode === 'both'" x-cloak>
                <label class="{{ $label }}">OAuth client ID</label>
                <input type="text" name="{{ $name('oauth_client_id') }}" value="{{ $oldMeta('oauth_client_id') }}" placeholder="From AccountantsWorld" autocomplete="off" class="{{ $input }}">
            </div>
            <div x-show="authMode === 'oauth' || authMode === 'both'" x-cloak>
                <label class="{{ $label }}">OAuth client secret</label>
                <input type="password" name="{{ $name('oauth_client_secret') }}" placeholder="{{ $secretPlaceholder('oauth_client_secret') }}" autocomplete="new-password" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2" x-show="authMode === 'oauth' || authMode === 'both'" x-cloak>
                <label class="{{ $label }}">OAuth token URL</label>
                <input type="text" name="{{ $name('oauth_token_url') }}" value="{{ $oldMeta('oauth_token_url') }}" placeholder="https://dev-auth.accountantsoffice.com/connect/token" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Pay schedule ID (optional)</label>
                <input type="text" name="{{ $name('pay_schedule_id') }}" value="{{ $oldMeta('pay_schedule_id') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Default pay type code</label>
                <input type="text" name="{{ $name('default_pay_type_code') }}" value="{{ $oldMeta('default_pay_type_code', 'REG') }}" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Payroll contact email</label>
                <input type="email" name="{{ $name('accountant_email') }}" value="{{ $oldMeta('accountant_email') }}" placeholder="payroll@supportivesolutionshomecare.com" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">HTTP timeout (sec)</label>
                <input type="number" name="{{ $name('timeout') }}" value="{{ $oldMeta('timeout') }}" min="5" max="120" class="{{ $input }}">
            </div>
        </div>
    </div>
@elseif($key === \App\Models\IntegrationCredential::KEY_HHA)
    @php
        $hhaEnv = old("credentials.{$index}.metadata.environment", $meta['environment'] ?? 'implementation');
        $hhaBases = config('hha.bases', []);
    @endphp
    <div class="space-y-4" x-data="{
        environment: @js($hhaEnv),
        bases: @js($hhaBases),
        applyEnvironment() {
            const base = this.bases[this.environment];
            if (! base) return;
            const api = $refs.hhaApiUrl;
            const token = $refs.hhaTokenUrl;
            if (api) api.value = base;
            if (token) token.value = base + '/identity/connect/token';
        }
    }">
        <p class="{{ $sub }}">HHAeXchange EVV Aggregator</p>
        <p class="text-[11px] text-[#64748b]">OAuth uses <code class="text-[10px]">scope=write:aggregator</code>. API base URL must be the host only (e.g. <code class="text-[10px]">https://implementation.hhaexchange.com</code>) — do <strong>not</strong> append <code class="text-[10px]">/api/v2</code>.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
                <label class="{{ $label }}">Environment</label>
                <select name="{{ $name('environment') }}" x-model="environment" @change="applyEnvironment()" class="{{ $select }}">
                    <option value="implementation" @selected($hhaEnv === 'implementation')>Implementation (testing)</option>
                    <option value="production" @selected($hhaEnv === 'production')>Production</option>
                </select>
            </div>
            <div>
                <label class="{{ $label }}">Attestation status</label>
                <select name="{{ $name('attestation_status') }}" class="{{ $select }}">
                    <option value="pending" @selected($oldMeta('attestation_status') === 'pending')>Pending</option>
                    <option value="approved" @selected($oldMeta('attestation_status') === 'approved')>Approved</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">API base URL</label>
                <input type="text" name="{{ $name('api_url') }}" x-ref="hhaApiUrl" value="{{ $oldMeta('api_url') }}" class="{{ $input }}" autocomplete="off" placeholder="https://implementation.hhaexchange.com">
                <p class="mt-1 text-[10px] text-[#94a3b8] font-semibold">Correct: https://implementation.hhaexchange.com — Wrong: …/api/v2/</p>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Token URL</label>
                <input type="text" name="{{ $name('token_url') }}" x-ref="hhaTokenUrl" value="{{ $oldMeta('token_url') }}" class="{{ $input }}" autocomplete="off" placeholder="https://implementation.hhaexchange.com/identity/connect/token">
            </div>
            <div>
                <label class="{{ $label }}">Client ID</label>
                <input type="text" name="{{ $name('client_id') }}" value="{{ $oldMeta('client_id') }}" autocomplete="off" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Client secret</label>
                <input type="text" name="{{ $name('client_secret') }}" value="{{ $oldMeta('client_secret') }}" autocomplete="off" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">OAuth scope</label>
                <input type="text" name="{{ $name('scope') }}" value="{{ $oldMeta('scope') ?: 'write:aggregator' }}" class="{{ $input }}" autocomplete="off">
            </div>
            <div>
                <label class="{{ $label }}">Provider tax ID (EIN)</label>
                <input type="text" name="{{ $name('provider_tax_id') }}" value="{{ $oldMeta('provider_tax_id') }}" class="{{ $input }}" autocomplete="off" placeholder="9-digit EIN">
            </div>
            <div>
                <label class="{{ $label }}">Office NPI</label>
                <input type="text" name="{{ $name('office_npi') }}" value="{{ $oldMeta('office_npi') }}" class="{{ $input }}" autocomplete="off">
            </div>
            <div>
                <label class="{{ $label }}">Payer ID <span class="normal-case font-semibold text-gray-400">(HHAX test data)</span></label>
                <input type="text" name="{{ $name('payer_id') }}" value="{{ $oldMeta('payer_id') }}" class="{{ $input }}" autocomplete="off" placeholder="From HHAX secure email">
            </div>
        </div>
    </div>
@elseif($key === \App\Models\IntegrationCredential::KEY_RINGCENTRAL)
    <div class="space-y-4">
        <p class="{{ $sub }}">RingCentral (Phone / SMS / eFax)</p>
        <p class="text-[11px] text-[#64748b]">In the <a href="https://developers.ringcentral.com/" target="_blank" rel="noopener" class="text-[#2563eb] font-semibold">RingCentral Developer Portal</a>, open your app → <strong>Permissions</strong> and enable <strong>SMS</strong> and <strong>Fax</strong>. After saving, generate a new JWT and paste it here.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Server URL</label>
                <input type="text" name="{{ $name('server_url') }}" value="{{ $oldMeta('server_url') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Client ID</label>
                <input type="text" name="{{ $name('client_id') }}" value="{{ $oldMeta('client_id') }}" autocomplete="off" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Client secret</label>
                <input type="text" name="{{ $name('client_secret') }}" value="{{ $oldMeta('client_secret') }}" autocomplete="off" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">JWT</label>
                <textarea name="{{ $name('jwt') }}" rows="4" autocomplete="off" class="{{ $input }} resize-y min-h-[6rem]">{{ $oldMeta('jwt') }}</textarea>
            </div>
            <div>
                <label class="{{ $label }}">Extension <span class="normal-case font-semibold text-gray-400">(optional)</span></label>
                <input type="text" name="{{ $name('extension') }}" value="{{ $oldMeta('extension') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Outbound SMS / caller ID number <span class="normal-case font-semibold text-gray-400">(optional)</span></label>
                <input type="text" name="{{ $name('from_number') }}" value="{{ $oldMeta('from_number') }}" placeholder="+15551234567" class="{{ $input }}">
            </div>
        </div>
    </div>
@elseif($key === \App\Models\IntegrationCredential::KEY_GOOGLE_WORKSPACE)
    <div class="space-y-4">
        <p class="{{ $sub }}">Google Workspace · DHS Home Help invoice email</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Delegated user email</label>
                <input type="email" name="credentials[{{ $index }}][username]" value="{{ old("credentials.{$index}.username", $entry['username']) }}" class="{{ $input }}" autocomplete="off">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Client ID</label>
                <input type="text" name="{{ $name('client_id') }}" value="{{ $oldMeta('client_id') }}" class="{{ $input }} font-mono text-xs" autocomplete="off">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Client secret</label>
                <input type="text" name="{{ $name('client_secret') }}" value="{{ $oldMeta('client_secret') }}" class="{{ $input }} font-mono text-xs" autocomplete="off">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Refresh token</label>
                <textarea name="{{ $name('refresh_token') }}" rows="3" class="{{ $input }} font-mono text-xs" autocomplete="off">{{ $oldMeta('refresh_token') }}</textarea>
            </div>
        </div>
    </div>
@elseif($key === \App\Models\IntegrationCredential::KEY_SIGMA)
    <div class="space-y-4">
        <p class="{{ $sub }}">Sigma Portal · DHS billing (RPA)</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Portal URL</label>
                <input type="url" name="{{ $name('portal_url') }}" value="{{ $oldMeta('portal_url') }}" placeholder="https://www.michigan.gov/mdhhs" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}">Default ASW email (optional vault fallback)</label>
                <input type="email" name="{{ $name('default_asw_email') }}" value="{{ $oldMeta('default_asw_email') }}" placeholder="asw@mdhhs.example.gov" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Username</label>
                <input type="text" name="credentials[{{ $index }}][username]" value="{{ old("credentials.{$index}.username", $entry['username']) }}" autocomplete="off" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Password</label>
                <input type="password" name="credentials[{{ $index }}][password]" placeholder="{{ $entry['has_password'] ? 'Leave blank to keep current' : 'Enter password' }}" autocomplete="new-password" class="{{ $input }}">
            </div>
        </div>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        <div>
            <label class="{{ $label }}">Username</label>
            <input type="text" name="credentials[{{ $index }}][username]" value="{{ old("credentials.{$index}.username", $entry['username']) }}" autocomplete="off" class="{{ $input }}">
        </div>
        <div>
            <label class="{{ $label }}">Password</label>
            <input type="password" name="credentials[{{ $index }}][password]" placeholder="{{ $entry['has_password'] ? 'Leave blank to keep current' : 'Enter password' }}" autocomplete="new-password" class="{{ $input }}">
        </div>
        <div>
            <label class="{{ $label }}">API key</label>
            <input type="password" name="credentials[{{ $index }}][api_key]" placeholder="{{ $entry['has_api_key'] ? 'Leave blank to keep current' : 'Enter API key' }}" autocomplete="new-password" class="{{ $input }}">
        </div>
    </div>
@endif
