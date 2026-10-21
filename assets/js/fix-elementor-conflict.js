/**
 * Fix for Elementor hosting compatibility.
 *
 * This script intentionally avoids overriding global jQuery AJAX behavior, which
 * can break Elementor or other admin plugins. It only adds safe fallbacks for
 * editor compatibility and Select2 re-init when needed.
 */

jQuery(function($) {
    'use strict';

    if (typeof window.editPostSelector === 'undefined') {
        window.editPostSelector = function() {
            return false;
        };
    }

    function initVwpmSelect2() {
        if (typeof $.fn.select2 !== 'function') {
            return;
        }

        $('.vwpm-component-select, .vwpm-tool-select').each(function() {
            var $el = $(this);
            if (!$el.hasClass('select2-hidden-accessible')) {
                try {
                    $el.select2({
                        width: '100%',
                        placeholder: $el.is('.vwpm-component-select') ? 'Search by SKU or name...' : 'Search by number or name...'
                    });
                } catch (e) {
                    // Ignore Select2 initialization errors on admin pages that do not support it.
                }
            }
        });
    }

    $(document).on('click', '#vwpm-add-bom-row, #vwpm-add-tool-row', function() {
        setTimeout(function() {
            initVwpmSelect2();
        }, 50);
    });

    initVwpmSelect2();
});
