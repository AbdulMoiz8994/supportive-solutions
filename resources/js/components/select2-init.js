import jQuery, { ensureSelect2 } from './select2-setup';

/**
 * Initialize Select2 on multi-select fields (locations, clients, etc.).
 */
export function initSelect2(root = document) {
    ensureSelect2();
    const $root = jQuery(root);

    $root.find('.js-select2-multi').each(function initOne() {
        const $el = jQuery(this);

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        $el.select2({
            width: '100%',
            placeholder: $el.data('placeholder') || 'Select…',
            allowClear: true,
            closeOnSelect: false,
        });
    });
}
