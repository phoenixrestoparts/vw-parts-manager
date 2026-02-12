<?php
if (!defined('ABSPATH')) { 
    exit; 
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;
$suppliers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vwpm_suppliers ORDER BY name ASC");
?>

<div class="wrap">
    <h1>Create Custom Purchase Order</h1>
    <p class="description">Create a PO for items not in your product/component system (e.g., office supplies, miscellaneous purchases)</p>
    
    <div class="vwpm-card">
        <h2>PO Details</h2>
        
        <table class="form-table">
            <tr>
                <th><label for="custom_po_supplier">Select Supplier *</label></th>
                <td>
                    <select id="custom_po_supplier" class="regular-text" required>
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo esc_attr($supplier->id); ?>" 
                                    data-name="<?php echo esc_attr($supplier->name); ?>"
                                    data-email="<?php echo esc_attr($supplier->email); ?>">
                                <?php echo esc_html($supplier->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="custom_po_vat_toggle">VAT</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="custom_po_vat_toggle" checked>
                        <strong>Include UK VAT (20%)</strong> - Uncheck for international orders
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="custom_po_notes">PO Notes (optional)</label></th>
                <td>
                    <textarea id="custom_po_notes" class="large-text" rows="3" placeholder="Add any notes about this purchase order..."></textarea>
                </td>
            </tr>
        </table>

        <h3>Line Items</h3>
        <p>
            <button type="button" class="button button-primary" id="add-custom-po-line">+ Add Line Item</button>
            <button type="button" class="button" id="add-shipping-line">+ Add Shipping/Delivery</button>
        </p>
        
        <table class="widefat" id="custom-po-items-table">
            <thead>
                <tr>
                    <th style="width: 30%">Item Name *</th>
                    <th style="width: 15%">Part Number</th>
                    <th style="width: 15%">Supplier Ref</th>
                    <th style="width: 10%">Quantity *</th>
                    <th style="width: 10%">Unit Price (£) *</th>
                    <th style="width: 10%">Line Total</th>
                    <th style="width: 10%">Action</th>
                </tr>
            </thead>
            <tbody id="custom-po-items">
                <tr>
                    <td colspan="7" style="text-align:center; color:#999;">Click "Add Line Item" to start building your PO</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right;"><strong>Subtotal (excl. VAT):</strong></td>
                    <td id="custom-po-subtotal" style="font-weight:bold;">£0.00</td>
                    <td></td>
                </tr>
                <tr id="vat-row">
                    <td colspan="5" style="text-align:right;"><strong>VAT (20%):</strong></td>
                    <td id="custom-po-vat" style="font-weight:bold;">£0.00</td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align:right;"><strong>Grand Total (inc. VAT):</strong></td>
                    <td id="custom-po-total" style="font-weight:bold; font-size:16px;">£0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <p class="submit">
            <button type="button" class="button button-primary button-large" id="save-custom-po">Save for Print/Create</button>
            <button type="button" class="button button-secondary" id="create-custom-po" style="display:none;">Create PO (persist to database)</button>
            <button type="button" class="button" id="print-custom-po" style="display:none;">Print/PDF</button>
        </p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var lineIndex = 0;

    // Add line item
    $('#add-custom-po-line').on('click', function() {
        addLineItem('item');
    });

    // Add shipping line
    $('#add-shipping-line').on('click', function() {
        addLineItem('shipping');
    });

    function addLineItem(type) {
        var row = $('<tr data-line-index="' + lineIndex + '" data-line-type="' + type + '">');
        
        if (type === 'shipping') {
            row.append('<td><input type="text" class="regular-text item-name" value="Shipping / Delivery" required></td>');
        } else {
            row.append('<td><input type="text" class="regular-text item-name" placeholder="Item name" required></td>');
        }
        
        row.append('<td><input type="text" class="regular-text item-part-number" placeholder="Optional"></td>');
        row.append('<td><input type="text" class="regular-text item-supplier-ref" placeholder="Optional"></td>');
        row.append('<td><input type="number" step="1" min="1" class="small-text item-qty" value="1" required></td>');
        row.append('<td><input type="number" step="0.01" min="0" class="small-text item-price" value="0.00" required></td>');
        row.append('<td class="item-line-total">£0.00</td>');
        row.append('<td><button type="button" class="button button-small remove-line">Remove</button></td>');
        
        $('#custom-po-items').append(row);
        lineIndex++;
        
        // Remove placeholder row if exists
        $('#custom-po-items tr td[colspan]').parent().remove();
    }

    // Remove line item
    $(document).on('click', '.remove-line', function() {
        $(this).closest('tr').remove();
        calculateTotal();
        
        // Add placeholder if no items
        if ($('#custom-po-items tr').length === 0) {
            $('#custom-po-items').html('<tr><td colspan="7" style="text-align:center; color:#999;">Click "Add Line Item" to start building your PO</td></tr>');
        }
    });

    // Calculate line total on quantity or price change
    $(document).on('input', '.item-qty, .item-price', function() {
        var row = $(this).closest('tr');
        var qty = parseFloat(row.find('.item-qty').val()) || 0;
        var price = parseFloat(row.find('.item-price').val()) || 0;
        var lineTotal = qty * price;
        row.find('.item-line-total').text('£' + lineTotal.toFixed(2));
        calculateTotal();
    });

    // VAT toggle
    $('#custom_po_vat_toggle').on('change', function() {
        calculateTotal();
        if ($(this).is(':checked')) {
            $('#vat-row').show();
        } else {
            $('#vat-row').hide();
        }
    });

    function calculateTotal() {
        var subtotal = 0;
        $('#custom-po-items tr').each(function() {
            var lineText = $(this).find('.item-line-total').text().replace(/[£,]/g, '');
            if (lineText) {
                subtotal += parseFloat(lineText) || 0;
            }
        });
        
        var vatEnabled = $('#custom_po_vat_toggle').is(':checked');
        var vat = vatEnabled ? subtotal * 0.20 : 0;
        var grandTotal = subtotal + vat;
        
        $('#custom-po-subtotal').text('£' + subtotal.toFixed(2));
        $('#custom-po-vat').text('£' + vat.toFixed(2));
        $('#custom-po-total').text('£' + grandTotal.toFixed(2));
    }

    // Save for print/create
    $('#save-custom-po').on('click', function() {
        var supplierId = $('#custom_po_supplier').val();
        var supplierName = $('#custom_po_supplier option:selected').data('name');
        var supplierEmail = $('#custom_po_supplier option:selected').data('email');
        var notes = $('#custom_po_notes').val();
        var vatEnabled = $('#custom_po_vat_toggle').is(':checked');

        if (!supplierId) {
            alert('Please select a supplier');
            return;
        }

        var items = [];
        var hasError = false;

        $('#custom-po-items tr').each(function() {
            var name = $(this).find('.item-name').val();
            var partNumber = $(this).find('.item-part-number').val();
            var supplierRef = $(this).find('.item-supplier-ref').val();
            var qty = parseFloat($(this).find('.item-qty').val());
            var price = parseFloat($(this).find('.item-price').val());
            var lineType = $(this).data('line-type') || 'item';

            if (name && !isNaN(qty) && !isNaN(price)) {
                items.push({
                    component_id: 'custom_' + Date.now() + '_' + Math.random(),
                    component_name: name,
                    component_number: partNumber || '',
                    supplier_ref: supplierRef || '',
                    total_qty: qty,
                    unit_price: price,
                    line_total: qty * price,
                    qty_per_unit: 1,
                    line_type: lineType
                });
            } else if (name || qty || price) {
                hasError = true;
            }
        });

        if (hasError) {
            alert('Please complete all required fields (Item Name, Quantity, Unit Price)');
            return;
        }

        if (items.length === 0) {
            alert('Please add at least one line item');
            return;
        }

        var subtotal = items.reduce(function(sum, item) { return sum + item.line_total; }, 0);
        var vat = vatEnabled ? subtotal * 0.20 : 0;
        var grandTotal = subtotal + vat;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_save_po_selection',
                nonce: vwpm_ajax.nonce,
                supplier_id: supplierId,
                items: items,
                tools: [],
                products: [{ title: 'Custom PO: ' + supplierName, quantity: items.length + ' items' }],
                type: 'custom',
                notes: notes,
                vat_enabled: vatEnabled,
                subtotal: subtotal,
                vat_amount: vat,
                grand_total: grandTotal
            },
            success: function(response) {
                if (response.success) {
                    alert('Custom PO saved! You can now create or print it.');
                    $btn.text('Saved ✓').css('background', '#46b450');
                    $('#create-custom-po, #print-custom-po').show();
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to save'));
                    $btn.prop('disabled', false).text('Save for Print/Create');
                }
            },
            error: function() {
                alert('Request failed');
                $btn.prop('disabled', false).text('Save for Print/Create');
            }
        });
    });

    // Create PO
    $('#create-custom-po').on('click', function() {
        var $btn = $(this);
        
        if ($btn.prop('disabled')) return;
        
        $btn.prop('disabled', true).text('Creating...');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_create_po_from_transient',
                nonce: vwpm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('PO created successfully! PO Number: ' + response.data.po_number);
                    $btn.text('Created ✓').css('background', '#46b450');
                    
                    setTimeout(function() {
                        window.location.href = '<?php echo admin_url('admin.php?page=vwpm-purchase-orders'); ?>';
                    }, 1000);
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to create PO'));
                    $btn.prop('disabled', false).text('Create PO (persist to database)');
                }
            },
            error: function() {
                alert('Request failed');
                $btn.prop('disabled', false).text('Create PO (persist to database)');
            }
        });
    });

    // Print PO
    $('#print-custom-po').on('click', function() {
        window.open(vwpm_ajax.ajax_url.replace('admin-ajax.php', 'admin.php') + '?vwpm_print_po=1', '_blank');
    });
});
</script>
