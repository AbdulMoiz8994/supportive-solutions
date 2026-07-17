import jQuery from 'jquery';
import select2 from 'select2';
import 'select2/dist/css/select2.min.css';
import '../../css/select2-overrides.css';

let initialized = false;

/**
 * Attach Select2 to a single shared jQuery instance (required for Vite production bundles).
 */
export function ensureSelect2() {
    if (! initialized) {
        window.jQuery = window.$ = jQuery;
        select2(jQuery);
        initialized = true;
    }

    return jQuery;
}

export { jQuery as default, jQuery };
