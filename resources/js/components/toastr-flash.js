import toastr from 'toastr';
import 'toastr/build/toastr.min.css';

toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    timeOut: 4000,
    extendedTimeOut: 2000,
    preventDuplicates: true,
};

export function initFlashToastr() {
    const element = document.getElementById('flash-messages-data');

    if (!element) {
        return;
    }

    let data;

    try {
        data = JSON.parse(element.textContent);
    } catch {
        return;
    }

    (data.flash ?? []).forEach(({ type, message }) => {
        if (! message) {
            return;
        }

        const notifier = toastr[type] ?? toastr.info;
        notifier(message);
    });

    const errors = data.errors ?? [];

    if (errors.length === 1) {
        toastr.error(errors[0]);
    } else if (errors.length > 1) {
        toastr.error(errors.join('<br>'));
    }
}
