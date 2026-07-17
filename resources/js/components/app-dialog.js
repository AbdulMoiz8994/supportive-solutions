const defaults = {
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    variant: 'primary',
};

export function registerAppDialog(Alpine) {
    Alpine.store('dialog', {
        open: false,
        mode: 'confirm',
        title: '',
        message: '',
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        variant: 'primary',
        _resolve: null,

        confirm(options = {}) {
            return this._open('confirm', {
                confirmLabel: 'Confirm',
                cancelLabel: 'Cancel',
                ...options,
            });
        },

        alert(options = {}) {
            return this._open('alert', {
                confirmLabel: 'Got it',
                ...options,
            });
        },

        async confirmSubmit(form, options = {}) {
            if (!form) {
                return false;
            }

            const ok = await this.confirm(options);

            if (ok) {
                form.submit();
            }

            return ok;
        },

        _open(mode, options) {
            return new Promise((resolve) => {
                const merged = { ...defaults, ...options };

                this.mode = mode;
                this.title = merged.title || (mode === 'confirm' ? 'Please confirm' : 'Notice');
                this.message = merged.message || '';
                this.confirmLabel = merged.confirmLabel;
                this.cancelLabel = merged.cancelLabel;
                this.variant = merged.variant;
                this._resolve = resolve;
                this.open = true;
                document.body.style.overflow = 'hidden';
            });
        },

        accept() {
            this._resolve?.(this.mode === 'alert' ? true : true);
            this._close();
        },

        dismiss() {
            this._resolve?.(false);
            this._close();
        },

        _close() {
            this.open = false;
            this._resolve = null;
            document.body.style.overflow = '';
        },
    });

    window.appConfirm = (options) => Alpine.store('dialog').confirm(options);
    window.appAlert = (options) => Alpine.store('dialog').alert(options);
}
