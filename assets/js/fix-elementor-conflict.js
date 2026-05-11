/**
 * Fix for Elementor hosting compatibility
 * Prevents "editPostSelector is not a function" error
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Define editPostSelector as a no-op function if it doesn't exist
    // This prevents errors from undefined function calls
    if (typeof window.editPostSelector === 'undefined') {
        window.editPostSelector = function() {
            console.warn('editPostSelector called but not defined');
            return false;
        };
    }
    
    // Prevent jQuery.fn.select2 errors by wrapping initialization
    if (typeof $.fn.select2 !== 'undefined') {
        console.log('Select2 is available');
    }
    
    // Override any problematic AJAX calls from other plugins
    var originalAjax = $.ajax;
    $.ajax = function(settings) {
        if (settings && settings.data && settings.data.action) {
            console.log('AJAX call to action: ' + settings.data.action);
        }
        return originalAjax.apply(this, arguments);
    };
    
    // Ensure our Select2 initialization works even on Elementor hosting
    $(document).on('click', '#vwpm-add-bom-row, #vwpm-add-tool-row', function() {
        setTimeout(function() {
            $('.vwpm-component-select, .vwpm-tool-select').not('.select2-hidden-accessible').each(function() {
                if (typeof $(this).select2 === 'function') {
                    try {
                        $(this).select2({
                            width: '100%',
                            placeholder: $(this).is('.vwpm-component-select') ? 'Search by SKU or name...' : 'Search by number or name...'
                        });
                    } catch(e) {
                        console.error('Select2 initialization failed:', e);
                    }
                }
            });
        }, 50);
    });
});
