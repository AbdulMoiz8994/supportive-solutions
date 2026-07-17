import jQuery, { ensureSelect2 } from './select2-setup';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatDirectoryResult(item) {
    if (item.loading || !item.id) {
        return item.text;
    }

    const data = item.item || {};
    const contact = data.email || data.phone || data.fax || 'No contact on file';

    return jQuery(
        `<span class="block">`
        + `<span class="font-semibold text-[#0f172a]">${escapeHtml(item.text)}</span>`
        + `<span class="block text-[11px] text-[#94a3b8]">${escapeHtml(data.context || '')} · ${escapeHtml(contact)}</span>`
        + `</span>`
    );
}

function formatDirectorySelection(item) {
    return item.text || item.id;
}

function normalizeDirectoryRows(data) {
    if (Array.isArray(data)) {
        return data;
    }

    if (data && Array.isArray(data.results)) {
        return data.results;
    }

    return [];
}

function mapDirectoryResults(data) {
    return normalizeDirectoryRows(data).map((item) => ({
        id: `${item.type}:${item.id}`,
        text: item.name,
        item,
        // Pre-set so Select2 skips _normalizeItem's this.container access (breaks in ES module strict mode).
        _resultId: `directory-${item.type}-${item.id}`,
    }));
}

function directorySearchTransport(searchUrl) {
    return (params, success, failure) => {
        const query = new URLSearchParams(params.data).toString();
        const controller = new AbortController();

        fetch(`${searchUrl}?${query}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Directory search failed (${response.status})`);
                }

                return response.json();
            })
            .then((data) => {
                success(data);
            })
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    failure(error);
                }
            });

        return {
            abort: () => controller.abort(),
        };
    };
}

export function initMessageRecipientSelect2(root, config, onSelect, onClear) {
    ensureSelect2();
    const $root = jQuery(root);
    const $select = $root.find('.js-select2-directory-recipient');

    if (!$select.length) {
        return () => {};
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.off('select2:select select2:clear');
        $select.select2('destroy');
    }

    $select.select2({
        width: '100%',
        placeholder: 'Search by name, email, or phone…',
        allowClear: true,
        minimumInputLength: 2,
        dropdownParent: jQuery('body'),
        ajax: {
            delay: 300,
            transport: directorySearchTransport(config.searchUrl),
            data(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                };
            },
            processResults(data) {
                return {
                    results: mapDirectoryResults(data),
                    pagination: { more: false },
                };
            },
        },
        templateResult: formatDirectoryResult,
        templateSelection: formatDirectorySelection,
        language: {
            inputTooShort: () => 'Type at least 2 characters to search',
            searching: () => 'Searching…',
            noResults: () => 'No directory matches found',
            errorLoading: () => 'Could not load directory results',
        },
    });

    $select.on('select2:select', (event) => {
        const selected = event.params.data.item;
        if (selected && onSelect) {
            onSelect(selected);
        }
    });

    $select.on('select2:clear', () => {
        if (onClear) {
            onClear();
        }
    });

    return () => {
        $select.off('select2:select select2:clear');
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    };
}

export function communicationsCompose(config) {
    let destroyMessageSelect2 = null;

    const updateComposeScrollLock = (showMessage, showEfax) => {
        const locked = showMessage || showEfax;
        document.documentElement.classList.toggle('overflow-hidden', locked);
        document.body.classList.toggle('overflow-hidden', locked);
    };

    return {
        showMessage: false,
        showEfax: false,
        integration: config.integration,
        templates: config.templates,
        messageSelect2Ready: false,
        init() {
            const compose = new URLSearchParams(window.location.search).get('compose');
            if (compose === 'efax') {
                this.showEfax = true;
            } else if (compose === 'message') {
                this.showMessage = true;
            }

            this.$watch('showMessage', (open) => {
                updateComposeScrollLock(open, this.showEfax);

                if (open) {
                    this.$nextTick(() => this.mountMessageRecipientSelect2());
                } else {
                    this.teardownMessageRecipientSelect2();
                }
            }, { immediate: true });

            this.$watch('showEfax', (open) => {
                updateComposeScrollLock(this.showMessage, open);
            });
        },
        mountMessageRecipientSelect2() {
            if (this.messageSelect2Ready) {
                return;
            }

            destroyMessageSelect2 = initMessageRecipientSelect2(
                this.$el,
                config,
                (item) => {
                    this.message.recipient = item;
                },
                () => {
                    this.message.recipient = null;
                }
            );

            this.messageSelect2Ready = true;
        },
        teardownMessageRecipientSelect2() {
            if (destroyMessageSelect2) {
                destroyMessageSelect2();
                destroyMessageSelect2 = null;
            }

            this.messageSelect2Ready = false;
            this.message.recipient = null;
        },
        message: {
            results: [],
            recipient: null,
            channel: 'sms',
            language: 'en',
            subject: '',
            body: '',
            templateId: '',
        },
        efax: {
            search: '',
            results: [],
            contact: null,
            recipientFax: '',
            clientSearch: '',
            clientResults: [],
            client: null,
            documents: [],
            documentId: '',
            coverNote: '',
        },
        async searchDirectory(target) {
            const state = target === 'message' ? this.message : this.efax;
            const q = state.search?.trim();
            if (!q || q.length < 2) {
                state.results = [];
                return;
            }
            const res = await fetch(`${config.searchUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            state.results = data.results || [];
        },
        async searchClients() {
            const q = this.efax.clientSearch?.trim();
            if (!q || q.length < 2) {
                this.efax.clientResults = [];
                return;
            }
            const res = await fetch(`${config.searchUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            this.efax.clientResults = (data.results || []).filter((i) => i.type === 'client');
        },
        selectEfaxContact(item) {
            this.efax.contact = item;
            this.efax.search = item.name;
            this.efax.recipientFax = item.fax || '';
            this.efax.results = [];
        },
        async selectEfaxClient(item) {
            this.efax.client = item;
            this.efax.clientSearch = item.name;
            this.efax.clientResults = [];
            this.efax.documentId = '';
            const url = config.clientDocumentsUrl.replace('__CLIENT__', item.id);
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            this.efax.documents = data.documents || [];
        },
        clearEfaxClient() {
            this.efax.client = null;
            this.efax.clientSearch = '';
            this.efax.documents = [];
            this.efax.documentId = '';
        },
        filteredTemplates() {
            return this.templates.filter((t) => t.channel === this.message.channel);
        },
        applyTemplate(id) {
            const tpl = this.templates.find((t) => String(t.id) === String(id));
            if (!tpl) {
                return;
            }
            this.message.templateId = tpl.id;
            this.message.body = tpl.body || '';
            if (tpl.subject) {
                this.message.subject = tpl.subject;
            }
        },
        canSendMessage() {
            if (!this.message.recipient || !this.message.body?.trim()) {
                return false;
            }
            if (this.message.channel === 'sms') {
                return this.integration.ringcentral_sms && this.message.recipient.phone;
            }

            return this.integration.google && this.message.recipient.email;
        },
        canSendEfax() {
            if (!this.integration.ringcentral_fax) {
                return false;
            }
            const fax = this.efax.recipientFax || this.efax.contact?.fax;

            return !!fax?.trim();
        },
    };
}
