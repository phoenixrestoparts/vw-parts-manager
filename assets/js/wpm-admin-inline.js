jQuery(document).ready(function($) {
    
    console.log('VWPM Admin JS loaded');
    console.log('vwpm_ajax object:', vwpm_ajax);
    
    // Initialize Select2 on existing dropdowns
    if (typeof $.fn.select2 !== 'undefined') {
        $('.vwpm-component-select, .vwpm-tool-select').select2({
            width: '100%',
            placeholder: 'Search by SKU or name...'
        });
    }
    
    // BOM Repeater - Fixed version
    $(document).on('click', '#vwpm-add-bom-row', function(e) {
        e.preventDefault();
        console.log('Add BOM button clicked'); // Debug
        var $rows = $('#vwpm-bom-rows');
        var bomIndex = $rows.find('tr').length;
        var template = $('#vwpm-bom-row-template').html();
        template = template.replace(/INDEX/g, bomIndex);
        $rows.append(template);
        
        // Initialize Select2 on new row
        if (typeof $.fn.select2 !== 'undefined') {
            $rows.find('tr:last .vwpm-component-select').select2({
                width: '100%',
                placeholder: 'Search by SKU or name...'
            });
        }
    });
    
    $(document).on('click', '.vwpm-remove-row', function(e) {
        e.preventDefault();
        console.log('Remove row clicked'); // Debug
        var $row = $(this).closest('tr');
        // Destroy Select2 before removing
        if (typeof $.fn.select2 !== 'undefined') {
            $row.find('select').select2('destroy');
        }
        $row.remove();
    });
    
    // Tools Repeater - Fixed version
    $(document).on('click', '#vwpm-add-tool-row', function(e) {
        e.preventDefault();
        console.log('Add tool button clicked'); // Debug
        var $rows = $('#vwpm-tools-rows');
        var toolIndex = $rows.find('tr').length;
        var template = $('#vwpm-tool-row-template').html();
        template = template.replace(/INDEX/g, toolIndex);
        $rows.append(template);
        
        // Initialize Select2 on new row
        if (typeof $.fn.select2 !== 'undefined') {
            $rows.find('tr:last .vwpm-tool-select').select2({
                width: '100%',
                placeholder: 'Search by number or name...'
            });
        }
    });
    
    $(document).on('click', '.vwpm-remove-tool-row', function(e) {
        e.preventDefault();
        console.log('Remove tool row clicked'); // Debug
        var $row = $(this).closest('tr');
        // Destroy Select2 before removing
        if (typeof $.fn.select2 !== 'undefined') {
            $row.find('select').select2('destroy');
        }
        $row.remove();
    });
    
    // Production Calculator
    $(document).on('click', '#vwpm-calculate-production', function(e) {
        e.preventDefault();
        console.log('Calculate button clicked!'); // Debug
        
        var productType = $('#vwpm_production_type').val();
        var productId = $('#vwpm_product_id').val();
        var quantity = $('#vwpm_quantity').val();
        
        console.log('Product Type:', productType);
        console.log('Product ID:', productId);
        console.log('Quantity:', quantity);
        
        if (!productId || !quantity) {
            alert('Please select a product and enter a quantity');
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('Calculating...');
        
        console.log('Sending AJAX request...');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_calculate_production',
                nonce: vwpm_ajax.nonce,
                product_type: productType,
                product_id: productId,
                quantity: quantity
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    $('#vwpm-calculator-results').html(response.data.html).show();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('An error occurred. Check console for details.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Calculate Requirements');
            }
        });
    });
    
    // Switch between manufactured and ready-made products
    $('#vwpm_production_type').on('change', function() {
        var type = $(this).val();
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_get_products_by_type',
                nonce: vwpm_ajax.nonce,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    $('#vwpm_product_id').html(response.data.options);
                }
            }
        });
    });
    
    // Email PO - Updated
    $(document).on('click', '.vwpm-email-po-btn', function(e) {
        e.preventDefault();
        
        var supplierId = $(this).data('supplier-id');
        var supplierEmail = $(this).data('supplier-email');
        var emailContent = atob($(this).data('email-content'));
        
        if (!supplierEmail) {
            alert('This supplier has no email address on file.');
            return;
        }
        
        if (!confirm('Send this purchase order to ' + supplierEmail + '?')) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('Sending...');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_email_po',
                nonce: vwpm_ajax.nonce,
                supplier_id: supplierId,
                supplier_email: supplierEmail,
                email_content: emailContent
            },
            success: function(response) {
                if (response.success) {
                    alert('Purchase order sent successfully to ' + supplierEmail);
                } else {
                    alert('Error sending email: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('An error occurred sending the email.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Print PO - Opens in new window
    $(document).on('click', '.vwpm-print-po-btn', function(e) {
        e.preventDefault();
        
        var $section = $(this).closest('.vwpm-supplier-section');
        var content = $section.clone();
        
        // Remove the action buttons from the print version
        content.find('.vwpm-no-print').remove();
        
        // Create print window
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write('<html><head><title>Purchase Order</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin: 20px 0; }');
        printWindow.document.write('th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }');
        printWindow.document.write('th { background: #0073aa; color: #fff; }');
        printWindow.document.write('.vwpm-results-total { background: #f0f0f0; font-weight: bold; }');
        printWindow.document.write('h2, h3, h4 { color: #333; }');
        printWindow.document.write('@media print { button { display: none; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h2>Purchase Order</h2>');
        printWindow.document.write('<p><strong>Date:</strong> ' + new Date().toLocaleDateString('en-GB') + '</p>');
        printWindow.document.write(content.html());
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        
        // Auto print after a short delay
        setTimeout(function() {
            printWindow.print();
        }, 250);
    });
    
    // Old handlers for backwards compatibility - remove these
    $(document).on('click', '#vwpm-email-po', function(e) {
        e.preventDefault();
        alert('Please use the "Email to Supplier" button within each supplier section.');
    });
    
    $(document).on('click', '#vwpm-print-po', function(e) {
        e.preventDefault();
        window.print();
    });
    
    // Tool Search
    $('#vwpm-tool-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.vwpm-tools-list li').each(function() {
            var text = $(this).text().toLowerCase();
            if (text.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // CSV Import
    $('#vwpm-import-tools-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'vwpm_import_tools');
        formData.append('nonce', vwpm_ajax.nonce);
        
        var $button = $(this).find('button[type="submit"]');
        $button.prop('disabled', true).text('Importing...');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Import successful: ' + response.data.imported + ' tools imported');
                    location.reload();
                } else {
                    alert('Import failed: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred during import.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Import Tools');
            }
        });
    });
    
    $('#vwpm-import-components-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'vwpm_import_components');
        formData.append('nonce', vwpm_ajax.nonce);
        
        var $button = $(this).find('button[type="submit"]');
        $button.prop('disabled', true).text('Importing...');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Import successful: ' + response.data.imported + ' components imported');
                    location.reload();
                } else {
                    alert('Import failed: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred during import.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Import Components');
            }
        });
    });
    
    // Export CSV
    $('#vwpm-export-tools').on('click', function(e) {
        e.preventDefault();
        window.location.href = vwpm_ajax.ajax_url + '?action=vwpm_export_tools&nonce=' + vwpm_ajax.nonce;
    });
    
    $('#vwpm-export-components').on('click', function(e) {
        e.preventDefault();
        window.location.href = vwpm_ajax.ajax_url + '?action=vwpm_export_components&nonce=' + vwpm_ajax.nonce;
    });
    
    // Supplier Management
    $('#vwpm-add-supplier-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=vwpm_add_supplier&nonce=' + vwpm_ajax.nonce;
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred.');
            }
        });
    });
    
    $(document).on('click', '.vwpm-delete-supplier', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this supplier?')) {
            return;
        }
        
        var supplierId = $(this).data('supplier-id');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_delete_supplier',
                nonce: vwpm_ajax.nonce,
                supplier_id: supplierId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    $(document).on('click', '.vwpm-edit-supplier', function(e) {
        e.preventDefault();
        
        var $row = $(this).closest('tr');
        var supplierId = $(this).data('supplier-id');
        var name = $row.find('td:eq(0)').text();
        var email = $row.find('td:eq(1)').text();
        var contact = $row.find('td:eq(2)').text();
        
        // You could show a modal here or inline edit
        var newName = prompt('Supplier Name:', name);
        if (newName === null) return;
        
        var newEmail = prompt('Email:', email);
        if (newEmail === null) return;
        
        var newContact = prompt('Contact Details:', contact);
        if (newContact === null) return;
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_update_supplier',
                nonce: vwpm_ajax.nonce,
                supplier_id: supplierId,
                name: newName,
                email: newEmail,
                contact_details: newContact
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
});
