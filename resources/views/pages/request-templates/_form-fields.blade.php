<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Template Name</label>
        <input type="text" name="name" @if(!empty($alpine)) x-model="editTemplate.name" @endif required
            class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
    </div>
    <div>
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Category</label>
        <input type="text" name="category" @if(!empty($alpine)) x-model="editTemplate.category" @endif placeholder="Assessments, Medical, Follow-up"
            class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
    </div>
    <div>
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Delivery Method</label>
        <select name="delivery_method" @if(!empty($alpine)) x-model="editTemplate.delivery_method" @endif required
            class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
            @foreach($deliveryMethods as $method)
                <option value="{{ $method }}">{{ ucfirst($method) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Recipient Type</label>
        <select name="recipient_type" @if(!empty($alpine)) x-model="editTemplate.recipient_type" @endif required
            class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
            @foreach($recipientTypes as $type)
                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Default Email (optional)</label>
        <input type="email" name="default_recipient_email" @if(!empty($alpine)) x-model="editTemplate.default_recipient_email" @endif
            class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
    </div>
    <div>
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Default Fax (optional)</label>
        <input type="text" name="default_recipient_fax" @if(!empty($alpine)) x-model="editTemplate.default_recipient_fax" @endif
            class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
    </div>
</div>
<div>
    <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Email Subject</label>
    <input type="text" name="subject" @if(!empty($alpine)) x-model="editTemplate.subject" @endif placeholder="Request for @{{ client_name }}"
        class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
</div>
<div>
    <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Message Body</label>
    <textarea name="body" rows="8" @if(!empty($alpine)) x-model="editTemplate.body" @endif required
        class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">@verbatim
Dear {{ case_coordinator_name }},

Please provide updated documentation for {{ client_name }} (Member ID: {{ member_id }}).

Thank you,
{{ agency_name }}@endverbatim</textarea>
</div>
<div>
    <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Description (internal)</label>
    <textarea name="description" rows="2" @if(!empty($alpine)) x-model="editTemplate.description" @endif
        class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]"></textarea>
</div>
<div class="flex items-center gap-3">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @if(!empty($alpine)) x-bind:checked="editTemplate.is_active" @else checked @endif
        class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
    <label class="text-sm font-bold text-[#1e293b]">Active template</label>
</div>
