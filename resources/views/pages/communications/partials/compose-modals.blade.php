{{-- New message modal --}}
<div x-show="showMessage" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-3 sm:p-4"
     role="dialog" aria-modal="true" aria-labelledby="compose-message-title">
    <div class="absolute inset-0 bg-[#0f172a]/50" @click="showMessage = false"></div>
    <div data-compose-message-modal
         class="relative z-10 flex w-full max-w-lg max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-2xl border border-[#e2e8f0] bg-white shadow-xl"
         @click.stop>
        <div class="shrink-0 border-b border-[#f1f5f9] px-4 pb-3 pt-4 sm:px-6 sm:pb-4 sm:pt-5">
            <h2 id="compose-message-title" class="text-[18px] font-extrabold text-[#0f172a] sm:text-[20px]">New message</h2>
            <p class="mt-1 text-[11px] text-[#64748b] sm:text-[12px]">Send an SMS or email to a client, caregiver, or contact.</p>
        </div>

        <form method="POST" action="{{ route('communications.compose.message.store') }}" class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @csrf
            <input type="hidden" name="recipient_type" :value="message.recipient?.type">
            <input type="hidden" name="recipient_id" :value="message.recipient?.id">
            <input type="hidden" name="channel" :value="message.channel">
            <input type="hidden" name="language" :value="message.language">

            <div class="compose-modal-body min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-4 custom-scrollbar sm:space-y-4 sm:px-6 sm:py-5">
                    <div>
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[#64748b]" for="compose-message-recipient">To (search directory)</label>
                        <select id="compose-message-recipient" class="js-select2-directory-recipient w-full"></select>
                        <p x-show="message.recipient" class="mt-2 text-[12px] text-[#334155]">
                            Selected: <strong x-text="message.recipient?.name"></strong>
                            <span class="text-[#94a3b8]" x-text="' · ' + (message.channel === 'sms' ? (message.recipient?.phone || 'no phone') : (message.recipient?.email || 'no email'))"></span>
                        </p>
                        <p class="mt-1 hidden text-[11px] text-[#94a3b8] sm:block">Search by name, email, or phone. Picking a person auto-fills their phone (SMS) or email.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Channel</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="message.channel = 'sms'"
                                    :class="message.channel === 'sms' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]'"
                                    class="rounded-xl border px-2 py-2 text-[11px] font-bold transition sm:px-3 sm:py-2.5 sm:text-[12px]">
                                <span class="sm:hidden">SMS</span>
                                <span class="hidden sm:inline">SMS · RingCentral</span>
                            </button>
                            <button type="button" @click="message.channel = 'email'"
                                    :class="message.channel === 'email' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]'"
                                    class="rounded-xl border px-2 py-2 text-[11px] font-bold transition sm:px-3 sm:py-2.5 sm:text-[12px]">
                                <span class="sm:hidden">Email</span>
                                <span class="hidden sm:inline">Email · Google</span>
                            </button>
                        </div>
                        <p x-show="message.channel === 'sms' && !integration.ringcentral_sms" class="mt-1.5 text-[11px] font-semibold text-[#ea580c]" x-text="integration.ringcentral_sms_message || integration.ringcentral_message"></p>
                        <p x-show="message.channel === 'email' && !integration.google" class="mt-1.5 text-[11px] font-semibold text-[#ea580c]" x-text="integration.google_message"></p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Language</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="message.language = 'en'"
                                    :class="message.language === 'en' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]'"
                                    class="rounded-xl border px-2 py-2 text-[11px] font-bold transition sm:px-3 sm:py-2.5 sm:text-[12px]">English</button>
                            <button type="button" @click="message.language = 'ar'"
                                    :class="message.language === 'ar' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]'"
                                    class="rounded-xl border px-2 py-2 text-[11px] font-bold transition sm:px-3 sm:py-2.5 sm:text-[12px]">العربية</button>
                        </div>
                    </div>

                    <div x-show="message.channel === 'email'" x-cloak>
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Subject</label>
                        <input type="text" name="subject" x-model="message.subject" placeholder="Email subject"
                               class="w-full rounded-xl border border-[#e2e8f0] px-3.5 py-2.5 text-[13px] outline-none focus:border-[#2563eb]">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Template (optional)</label>
                        <select @change="applyTemplate($event.target.value)" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-3.5 py-2.5 text-[13px]">
                            <option value="">Type message… (or pick a template)</option>
                            <template x-for="tpl in filteredTemplates()" :key="tpl.id">
                                <option :value="tpl.id" x-text="tpl.name"></option>
                            </template>
                        </select>
                        <input type="hidden" name="template_id" :value="message.templateId">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Message</label>
                        <textarea name="body" rows="3" x-model="message.body" required
                                  class="w-full resize-none rounded-xl border border-[#e2e8f0] px-3.5 py-2.5 text-[13px] outline-none focus:border-[#2563eb]"
                                  placeholder="Type message…"></textarea>
                    </div>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-[#f1f5f9] bg-white px-4 py-3 sm:px-6 sm:py-4">
                    <button type="button" @click="showMessage = false" class="rounded-xl border border-[#e2e8f0] px-4 py-2.5 text-[12px] font-semibold text-[#475569]">Cancel</button>
                    <button type="submit" :disabled="!canSendMessage()"
                            class="rounded-xl bg-[#2563eb] px-4 py-2.5 text-[12px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Send</button>
                </div>
            </form>
        </div>
</div>

{{-- New eFax modal --}}
<div x-show="showEfax" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-3 sm:p-4"
     role="dialog" aria-modal="true" aria-labelledby="compose-efax-title">
    <div class="absolute inset-0 bg-[#0f172a]/50" @click="showEfax = false"></div>
    <div class="relative z-10 flex w-full max-w-lg max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-2xl border border-[#e2e8f0] bg-white shadow-xl" @click.stop>
        <div class="shrink-0 border-b border-[#f1f5f9] px-4 pb-3 pt-4 sm:px-6 sm:pb-4 sm:pt-5">
            <h2 id="compose-efax-title" class="text-[18px] font-extrabold text-[#0f172a] sm:text-[20px]">New eFax</h2>
            <p class="mt-1 text-[11px] text-[#64748b] sm:text-[12px]">Send a document by fax through RingCentral.</p>
        </div>

        <form method="POST" action="{{ route('communications.compose.efax.store') }}" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @csrf
            <input type="hidden" name="contact_id" :value="efax.contact?.type === 'contact' ? efax.contact.id : ''">
            <input type="hidden" name="client_id" :value="efax.client?.id">
            <input type="hidden" name="document_id" :value="efax.documentId">

            <div class="compose-modal-body min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-4 custom-scrollbar sm:space-y-4 sm:px-6 sm:py-5">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-[#64748b] mb-1.5">To (fax number)</label>
                <div class="relative">
                    <input type="text" x-model="efax.search" @input.debounce.300ms="searchDirectory('efax')" @focus="searchDirectory('efax')"
                           :placeholder="efax.contact ? efax.recipientFax : 'Pick MCO / physician from directory, or type a fax #'"
                           @input="efax.recipientFax = efax.search"
                           name="recipient_fax"
                           class="w-full rounded-xl border border-[#e2e8f0] px-3.5 py-2.5 text-[13px] focus:border-[#2563eb] outline-none">
                    <div x-show="efax.results.length && efax.search && !efax.contact" x-cloak
                         class="absolute z-10 mt-1 w-full rounded-xl border border-[#e2e8f0] bg-white shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="item in efax.results.filter(i => i.fax || i.type === 'contact')" :key="'efax-' + item.type + '-' + item.id">
                            <button type="button" @click="selectEfaxContact(item)"
                                    class="w-full text-left px-3.5 py-2.5 hover:bg-[#f8fafc] border-b border-[#f8fafc] last:border-0">
                                <span class="text-[13px] font-semibold text-[#0f172a]" x-text="item.name"></span>
                                <span class="block text-[11px] text-[#94a3b8]" x-text="(item.fax || 'No fax on file') + ' · ' + item.context"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-[#64748b] mb-1.5">About (client — optional)</label>
                <div class="relative">
                    <input type="text" x-model="efax.clientSearch" @input.debounce.300ms="searchClients()" @focus="searchClients()"
                           placeholder="Link this fax to a client record…"
                           class="w-full rounded-xl border border-[#e2e8f0] px-3.5 py-2.5 text-[13px] focus:border-[#2563eb] outline-none">
                    <div x-show="efax.clientResults.length && efax.clientSearch && !efax.client" x-cloak
                         class="absolute z-10 mt-1 w-full rounded-xl border border-[#e2e8f0] bg-white shadow-lg max-h-40 overflow-y-auto">
                        <template x-for="item in efax.clientResults" :key="'client-' + item.id">
                            <button type="button" @click="selectEfaxClient(item)"
                                    class="w-full text-left px-3.5 py-2.5 hover:bg-[#f8fafc]">
                                <span class="text-[13px] font-semibold" x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <p x-show="efax.client" class="mt-1 text-[12px]">Linked: <strong x-text="efax.client?.name"></strong>
                    <button type="button" @click="clearEfaxClient()" class="ml-2 text-[#2563eb] text-[11px] font-semibold">Remove</button>
                </p>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-[#64748b] mb-1.5">Cover note (optional)</label>
                <input type="text" name="cover_note" x-model="efax.coverNote" placeholder="e.g. Home Help Invoice — Khalil Ahmed, May"
                       class="w-full rounded-xl border border-[#e2e8f0] px-3.5 py-2.5 text-[13px] focus:border-[#2563eb] outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-[#64748b] mb-1.5">Attach document <span class="text-[#ef4444]">*</span></label>
                <div x-show="efax.client && efax.documents.length" class="mb-2">
                    <select @change="efax.documentId = $event.target.value" class="w-full rounded-xl border border-[#e2e8f0] px-3.5 py-2.5 text-[13px] bg-white mb-2">
                        <option value="">Pick from the client's Documents…</option>
                        <template x-for="doc in efax.documents" :key="doc.id">
                            <option :value="doc.id" x-text="doc.name + (doc.created_at ? ' · ' + doc.created_at : '')"></option>
                        </template>
                    </select>
                </div>
                <label class="flex flex-col items-center justify-center w-full rounded-xl border-2 border-dashed border-[#cbd5e1] bg-[#f8fafc] px-4 py-6 cursor-pointer transition hover:border-[#2563eb] sm:py-8">
                    <input type="file" name="attachment" accept="application/pdf,.pdf" class="hidden" @change="efax.documentId = ''">
                    <span class="text-center text-[11px] font-semibold text-[#475569] sm:text-[12px]">Pick from the client's Documents, or upload a file (PDF)</span>
                </label>
            </div>

            <p x-show="!integration.ringcentral_fax" class="text-[11px] font-semibold text-[#ea580c]" x-text="integration.ringcentral_fax_message || integration.ringcentral_message"></p>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-[#f1f5f9] bg-white px-4 py-3 sm:px-6 sm:py-4">
                    <button type="button" @click="showEfax = false" class="rounded-xl border border-[#e2e8f0] px-4 py-2.5 text-[12px] font-semibold text-[#475569]">Cancel</button>
                    <button type="submit" :disabled="!canSendEfax()"
                            class="rounded-xl bg-[#2563eb] px-4 py-2.5 text-[12px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Send eFax</button>
                </div>
            </form>
        </div>
</div>
