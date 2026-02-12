<?php
if (!defined('ABSPATH')) { 
    exit; 
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Check if we're editing a PO
$edit_po_id = isset($_GET['edit_po']) ? intval($_GET['edit_po']) : 0;
$edit_po_data = null;

if ($edit_po_id) {
    global $wpdb;
    $table_pos = $wpdb->prefix . 'vwpm_pos';
    $edit_po_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_pos} WHERE id = %d", $edit_po_id), ARRAY_A);
    
    if ($edit_po_data) {
        $edit_po_data['items'] = json_decode($edit_po_data['items'], true);
        $edit_po_data['tools'] = json_decode($edit_po_data['tools'], true);
        $edit_po_data['product_summary'] = json_decode($edit_po_data['product_summary'], true);
        
        if (!is_array($edit_po_data['items'])) $edit_po_data['items'] = array();
        if (!is_array($edit_po_data['tools'])) $edit_po_data['tools'] = array();
        if (!is_array($edit_po_data['product_summary'])) $edit_po_data['product_summary'] = array();
    }
}

// Get all products with SKU
$products = get_posts(array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
));
?>

<div class="wrap">
    <h1><?php echo $edit_po_id ? 'Edit Purchase Order' : 'Production Calculator'; ?></h1>
    
    <?php if ($edit_po_id && $edit_po_data): ?>
        <div class="notice notice-info">
            <p><strong>Editing PO:</strong> <?php echo esc_html($edit_po_data['po_number']); ?> - Supplier: <?php echo esc_html($edit_po_data['supplier_name']); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="vwpm-card">
        <h2>Calculate Production Requirements</h2>
        
        <?php if ($edit_po_id): ?>
            <input type="hidden" id="vwpm_edit_po_id" value="<?php echo esc_attr($edit_po_id); ?>">
        <?php endif; ?>
        
               <p>
            <button type="button" class="button button-primary" id="add-product-row">+ Add Product to Order</button>
        </p>
        
        <div id="products-added-summary" style="display:none; background:#f0f6fc; border:1px solid #0073aa; padding:15px; margin-bottom:15px; border-radius:4px;">
            <h3 style="margin-top:0;">Products Added to This Order:</h3>
            <ul id="products-summary-list" style="list-style:none; padding:0; margin:0;"></ul>
        </div>
        
        <table class="widefat" id="products-table">
            <thead>
                <tr>
                    <th style="width: 60%">Product (search by SKU or name)</th>
                    <th style="width: 15%">Quantity</th>
                    <th style="width: 15%">Type</th>
                    <th style="width: 10%">Action</th>
                </tr>
            </thead>
            <tbody id="products-list">
                <tr>
                    <td colspan="4" style="text-align:center; color:#999;">Click "Add Product to Order" to start</td>
                </tr>
            </tbody>
        </table>
        
        <table class="form-table" style="margin-top: 20px;">
            <tr>
                <th><label for="vwpm_vat_toggle">VAT</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="vwpm_vat_toggle" checked>
                        <strong>Include UK VAT (20%)</strong> - Uncheck for international orders
                    </label>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="button" class="button button-primary button-large" id="calculate-production">
                <?php echo $edit_po_id ? 'Recalculate Requirements' : 'Calculate Requirements'; ?>
            </button>
            <?php if ($edit_po_id): ?>
                <a href="<?php echo admin_url('admin.php?page=vwpm-purchase-orders'); ?>" class="button">Cancel & Return to PO List</a>
            <?php endif; ?>
        </p>
    </div>

    <div id="vwpm-results">
        <?php if ($edit_po_id && $edit_po_data): ?>
            <div class="notice notice-info" style="background:#d7f0ff; border-left:4px solid #0073aa;">
                <p><strong>📝 Editing Mode:</strong> The products from this PO are loaded above. You can add more products, remove products, or modify quantities. Click "Recalculate Requirements" to update the component list below.</p>
            </div>
            
            <div class="notice notice-warning" style="background:#fff8e5; border-left:4px solid #ffb900; margin-top:15px;">
                <p><strong>ℹ️ Instructions:</strong> Review the components below, adjust quantities as needed, and click "Update PO" to save your changes.</p>
            </div>
            
            <?php
            // Display existing PO items in edit mode
            if (!empty($edit_po_data['items']) && is_array($edit_po_data['items'])) {
                $vat_enabled = !empty($edit_po_data['vat_enabled']) ? true : false;
                $subtotal = 0;
                
                echo '<div class="vwpm-supplier-block" data-supplier-id="' . esc_attr($edit_po_data['supplier_id']) . '" style="margin:20px 0;padding:15px;border:1px solid #ddd;background:#fff;">';
                echo '<h3>Current Purchase Order Components</h3>';
                echo '<p><strong>Supplier:</strong> ' . esc_html($edit_po_data['supplier_name']) . '</p>';
                if (!empty($edit_po_data['supplier_email'])) {
                    echo '<p><strong>Email:</strong> ' . esc_html($edit_po_data['supplier_email']) . '</p>';
                }
                
                echo '<table class="vwpm-results-table widefat" style="width:100%;border-collapse:collapse;margin-top:15px;">';
                echo '<thead><tr>';
                echo '<th style="width:40px;text-align:center;">Select</th>';
                echo '<th>Item Name</th>';
                echo '<th>Part Number</th>';
                echo '<th>Supplier Ref</th>';
                echo '<th style="width:120px;">Quantity</th>';
                echo '<th style="width:110px;">Unit Price</th>';
                echo '<th style="width:110px;">Line Total</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                
                foreach ($edit_po_data['items'] as $item) {
                    $component_id = isset($item['component_id']) ? $item['component_id'] : '';
                    $component_name = isset($item['component_name']) ? $item['component_name'] : '';
                    $component_number = isset($item['component_number']) ? $item['component_number'] : '';
                    $supplier_ref = isset($item['supplier_ref']) ? $item['supplier_ref'] : '';
                    $qty = isset($item['total_qty']) ? floatval($item['total_qty']) : 0;
                    $unit_price = isset($item['unit_price']) ? floatval($item['unit_price']) : 0;
                    $line_total = $qty * $unit_price;
                    $qty_per_unit = isset($item['qty_per_unit']) ? floatval($item['qty_per_unit']) : 1;
                    
                    $subtotal += $line_total;
                    
                    echo '<tr data-component-id="' . esc_attr($component_id) . '" class="vwpm-po-row">';
                    echo '<td style="text-align:center;"><input type="checkbox" class="vwpm-po-include" data-supplier-id="' . esc_attr($edit_po_data['supplier_id']) . '" checked></td>';
                    echo '<td>' . esc_html($component_name) . '</td>';
                    echo '<td>' . esc_html($component_number) . '</td>';
                    echo '<td>' . ($supplier_ref ? esc_html($supplier_ref) : '&ndash;') . '</td>';
                    echo '<td><input type="number" step="0.01" class="vwpm-po-qty" value="' . number_format($qty, 2, '.', '') . '" style="width:100px;" data-unit-price="' . esc_attr($unit_price) . '" data-qty-per-unit="' . esc_attr($qty_per_unit) . '"></td>';
                    echo '<td class="vwpm-po-unit">£' . number_format($unit_price, 2) . '</td>';
                    echo '<td class="vwpm-po-line">£' . number_format($line_total, 2) . '</td>';
                    echo '</tr>';
                }
                
                // Subtotal row
                echo '<tr class="vwpm-supplier-subtotal" style="background:#f5f5f5;font-weight:bold;">';
                echo '<td colspan="6" style="text-align:right;padding:10px;">Subtotal:</td>';
                echo '<td class="vwpm-supplier-subtotal-value" style="padding:10px;">£' . number_format($subtotal, 2) . '</td>';
                echo '</tr>';
                
                // VAT row
                $vat = $vat_enabled ? $subtotal * 0.20 : 0;
                echo '<tr class="vwpm-supplier-vat" style="background:#f9f9f9;' . ($vat_enabled ? '' : 'display:none;') . '">';
                echo '<td colspan="6" style="text-align:right;padding:10px;">VAT (20%):</td>';
                echo '<td class="vwpm-supplier-vat-value" style="padding:10px;">£' . number_format($vat, 2) . '</td>';
                echo '</tr>';
                
                // Grand Total row
                $grand_total = $subtotal + $vat;
                echo '<tr class="vwpm-supplier-total" style="background:#e8f4f8;font-weight:bold;font-size:1.1em;">';
                echo '<td colspan="6" style="text-align:right;padding:10px;">Grand Total:</td>';
                echo '<td class="vwpm-supplier-total-value" style="padding:10px;">£' . number_format($grand_total, 2) . '</td>';
                echo '</tr>';
                
                echo '</tbody></table>';
                
                // Action buttons
                echo '<div style="margin-top:15px;">';
                echo '<button class="button button-primary vwpm-update-po-btn" data-po-id="' . esc_attr($edit_po_id) . '" data-supplier-id="' . esc_attr($edit_po_data['supplier_id']) . '">Update PO (Save Changes)</button> ';
                echo '<button class="button vwpm-print-po-btn" data-supplier-id="' . esc_attr($edit_po_data['supplier_id']) . '">Print/PDF (Current State)</button> ';
                echo '<button class="button vwpm-add-custom-line-btn" data-supplier-id="' . esc_attr($edit_po_data['supplier_id']) . '">+ Add Custom Line</button> ';
                echo '<button class="button vwpm-add-shipping-btn" data-supplier-id="' . esc_attr($edit_po_data['supplier_id']) . '">+ Add Shipping</button> ';
                echo '<a href="' . admin_url('admin.php?page=vwpm-purchase-orders') . '" class="button">Cancel &amp; Return to List</a>';
                echo '</div>';
                
                echo '</div>';
            }
            ?>
            
            <script type="text/javascript">
            // Pre-populate products from existing PO
            jQuery(document).ready(function($) {
                <?php if (!empty($edit_po_data['product_summary'])): ?>
                    <?php foreach ($edit_po_data['product_summary'] as $index => $prod): ?>
                        <?php 
                        $prod_id = isset($prod['product_id']) ? intval($prod['product_id']) : 0;
                        $prod_qty = isset($prod['quantity']) ? floatval($prod['quantity']) : 1;
                        if ($prod_id): 
                        ?>
                        // Add product row for existing product
                        setTimeout(function() {
                            $('#add-product-row').trigger('click');
                            var lastRow = $('#products-list tr:last');
                            lastRow.find('.product-select').val(<?php echo $prod_id; ?>).trigger('change');
                            lastRow.find('.product-qty').val(<?php echo $prod_qty; ?>);
                        }, <?php echo ($index * 100); ?>);
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                // Trigger initial summary update after products are loaded
                setTimeout(function() {
                    $('#calculate-production').trigger('click');
                }, <?php echo (count($edit_po_data['product_summary']) * 100 + 200); ?>);
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var productIndex = 0;
    var allProducts = <?php echo json_encode($products); ?>;
    
    // Update products summary display
    function updateProductsSummary() {
        var products = [];
        
        $('#products-list tr').each(function() {
            var productId = $(this).find('.product-select').val();
            var productName = $(this).find('.product-select option:selected').text();
            var qty = $(this).find('.product-qty').val();
            var type = $(this).find('.product-type option:selected').text();
            var index = $(this).data('product-index');
            
            if (productId && qty) {
                products.push({
                    index: index,
                    name: productName,
                    qty: qty,
                    type: type
                });
            }
        });
        
        if (products.length === 0) {
            $('#products-added-summary').hide();
            return;
        }
        
        var html = '';
        products.forEach(function(p) {
            html += '<li style="padding:8px 0; border-bottom:1px solid #ddd;">';
            html += '<strong style="color:#0073aa;">' + p.name + '</strong> ';
            html += '<span style="color:#666;">— Qty: ' + p.qty + ' — Type: ' + p.type + '</span> ';
            html += '<button type="button" class="button button-small remove-product-from-summary" data-product-index="' + p.index + '" style="float:right;">Remove</button>';
            html += '</li>';
        });
        
        $('#products-summary-list').html(html);
        $('#products-added-summary').show();
    }
    
    // Add product row
    $('#add-product-row').on('click', function() {
        var row = $('<tr data-product-index="' + productIndex + '">');
        
        // Product dropdown
        var selectHtml = '<select class="product-select" style="width:100%;" required>';
        selectHtml += '<option value="">-- Type SKU or Product Name --</option>';
        <?php foreach ($products as $product): 
            $sku = get_post_meta($product->ID, '_sku', true);
            $display_text = $sku ? $sku . ' - ' . $product->post_title : $product->post_title;
        ?>
        selectHtml += '<option value="<?php echo esc_attr($product->ID); ?>" data-sku="<?php echo esc_attr($sku); ?>"><?php echo esc_js($display_text); ?></option>';
        <?php endforeach; ?>
        selectHtml += '</select>';
        
        row.append('<td>' + selectHtml + '</td>');
        row.append('<td><input type="number" class="product-qty" min="1" step="1" value="1" style="width:100%;" required></td>');
        row.append('<td><select class="product-type" style="width:100%;"><option value="manufactured">Manufactured</option><option value="ready_made">Ready-Made</option></select></td>');
        row.append('<td><button type="button" class="button button-small remove-product-row">Remove</button></td>');
        
        $('#products-list').append(row);
        
        // Initialize Select2 on new row
        row.find('.product-select').select2({
            width: '100%',
            matcher: function(params, data) {
                if ($.trim(params.term) === '') return data;
                if (typeof data.text === 'undefined') return null;
                
                var term = params.term.toLowerCase();
                var text = data.text.toLowerCase();
                var sku = $(data.element).data('sku');
                
                if (text.indexOf(term) > -1) return data;
                if (sku && String(sku).toLowerCase().indexOf(term) > -1) return data;
                
                return null;
            }
        });
        
        // Update summary when product is selected or quantity changes
        row.find('.product-select, .product-qty, .product-type').on('change', function() {
            updateProductsSummary();
        });
        
        productIndex++;
        
        // Remove placeholder
        $('#products-list tr td[colspan]').parent().remove();
        
        updateProductsSummary();
    });
    
    // Remove product row
    $(document).on('click', '.remove-product-row', function() {
        $(this).closest('tr').remove();
        if ($('#products-list tr').length === 0) {
            $('#products-list').html('<tr><td colspan="4" style="text-align:center; color:#999;">Click "Add Product to Order" to start</td></tr>');
        }
        updateProductsSummary();
    });
    
    // Remove from summary (alternative way to remove)
    $(document).on('click', '.remove-product-from-summary', function() {
        var index = $(this).data('product-index');
        $('#products-list tr[data-product-index="' + index + '"]').remove();
        if ($('#products-list tr').length === 0) {
            $('#products-list').html('<tr><td colspan="4" style="text-align:center; color:#999;">Click "Add Product to Order" to start</td></tr>');
        }
        updateProductsSummary();
    });
    
       // Calculate production
    $('#calculate-production').on('click', function() {
        var products = [];
        var hasError = false;
        
        $('#products-list tr').each(function() {
            var productId = $(this).find('.product-select').val();
            var qty = $(this).find('.product-qty').val();
            var type = $(this).find('.product-type').val();
            
            if (productId && qty) {
                products.push({
                    product_id: productId,
                    quantity: qty,
                    product_type: type
                });
            } else if (productId || qty) {
                hasError = true;
            }
        });
        
        if (hasError) {
            alert('Please complete all product fields');
            return;
        }
        
        if (products.length === 0) {
            alert('Please add at least one product');
            return;
        }
        
        var vatEnabled = $('#vwpm_vat_toggle').is(':checked');
        var editPoId = $('#vwpm_edit_po_id').val();
        
        // If editing, collect existing items to preserve them
        var existingItems = [];
        if (editPoId) {
            $('.vwpm-po-row').each(function() {
                if ($(this).find('.vwpm-po-include').is(':checked')) {
                    var row = $(this);
                    existingItems.push({
                        component_id: row.data('component-id'),
                        component_name: row.find('td').eq(1).text(),
                        component_number: row.find('td').eq(2).text(),
                        supplier_ref: row.find('td').eq(3).text(),
                        total_qty: parseFloat(row.find('.vwpm-po-qty').val()) || 0,
                        unit_price: parseFloat(row.find('.vwpm-po-qty').data('unit-price')) || 0,
                        qty_per_unit: parseFloat(row.find('.vwpm-po-qty').data('qty-per-unit')) || 1
                    });
                }
            });
        }
        
        $('#vwpm-results').html('<div class="notice notice-info"><p>Calculating requirements...</p></div>');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_calculate_production',
                nonce: vwpm_ajax.nonce,
                products: products,
                vat_enabled: vatEnabled,
                existing_items: existingItems,
                merge_mode: editPoId ? true : false
            },
            success: function(response) {
                if (response.success) {
                    $('#vwpm-results').html(response.data.html);
                    
                    // If editing, replace save button with update button
                    if (editPoId) {
                        $('.vwpm-save-po-btn').each(function() {
                            var supplierId = $(this).data('supplier-id');
                            $(this).replaceWith(
                                '<button class="button button-primary vwpm-update-po-btn" data-po-id="' + editPoId + '" data-supplier-id="' + supplierId + '">Update PO (Save Changes)</button>'
                            );
                        });
                    }
                } else {
                    $('#vwpm-results').html('<div class="notice notice-error"><p>Error: ' + (response.data.message || 'Unknown error') + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $('#vwpm-results').html('<div class="notice notice-error"><p>Request failed: ' + error + '</p></div>');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    });

    // Update PO button (for edit mode)
    $(document).on('click', '.vwpm-update-po-btn', function(e) {
        e.preventDefault();
        
        var poId = $(this).data('po-id');
        var supplierId = $(this).data('supplier-id');
        var block = $('.vwpm-supplier-block[data-supplier-id="' + supplierId + '"]');
        var $btn = $(this);
        
        var items = [];
        var tools = [];
        
        block.find('.vwpm-po-row').each(function() {
            if ($(this).find('.vwpm-po-include').is(':checked')) {
                var row = $(this);
                items.push({
                    component_id: row.data('component-id'),
                    component_name: row.find('td').eq(1).text(),
                    component_number: row.find('td').eq(2).text(),
                    supplier_ref: row.find('td').eq(3).text(),
                    qty: parseFloat(row.find('.vwpm-po-qty').val()) || 0,
                    unit_price: parseFloat(row.find('.vwpm-po-qty').data('unit-price')) || 0,
                    qty_per_unit: parseFloat(row.find('.vwpm-po-qty').data('qty-per-unit')) || 1
                });
            }
        });

        var products = [];
        $('#products-list tr').each(function() {
            var productId = $(this).find('.product-select').val();
            var qty = $(this).find('.product-qty').val();
            if (productId && qty) {
                var productTitle = $(this).find('.product-select option:selected').text();
                products.push({
                    product_id: productId,
                    title: productTitle,
                    quantity: qty
                });
            }
        });

        var vatEnabled = $('#vwpm_vat_toggle').is(':checked');

        if (!confirm('Update this PO with the current changes?')) {
            return;
        }

        $btn.prop('disabled', true).text('Updating...');

        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_update_po',
                nonce: vwpm_ajax.nonce,
                po_id: poId,
                supplier_id: supplierId,
                items: items,
                tools: tools,
                products: products,
                type: 'manufactured',
                vat_enabled: vatEnabled
            },
            success: function(response) {
                if (response.success) {
                    alert('PO updated successfully!');
                    $btn.text('Updated ✓').css('background', '#46b450');
                    
                    setTimeout(function() {
                        window.location.href = '<?php echo admin_url('admin.php?page=vwpm-purchase-orders'); ?>';
                    }, 1000);
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to update'));
                    $btn.prop('disabled', false).text('Update PO (Save Changes)');
                }
            },
            error: function() {
                alert('Request failed');
                $btn.prop('disabled', false).text('Update PO (Save Changes)');
            }
        });
    });

    // Handle PO quantity changes
    $(document).on('change', '.vwpm-po-include', function() {
        recalculateSupplierTotal($(this).data('supplier-id'));
    });

    $(document).on('input', '.vwpm-po-qty', function() {
        var row = $(this).closest('tr');
        var qty = parseFloat($(this).val()) || 0;
        var unitPrice = parseFloat($(this).data('unit-price')) || 0;
        var lineTotal = qty * unitPrice;
        row.find('.vwpm-po-line').text('£' + lineTotal.toFixed(2));
        
        var supplierId = $(this).closest('.vwpm-supplier-block').data('supplier-id');
        recalculateSupplierTotal(supplierId);
    });
    
    // VAT toggle
    $(document).on('change', '#vwpm_vat_toggle', function() {
        $('.vwpm-supplier-block').each(function() {
            var supplierId = $(this).data('supplier-id');
            recalculateSupplierTotal(supplierId);
        });
    });

    function recalculateSupplierTotal(supplierId) {
        var $block = $('.vwpm-supplier-block[data-supplier-id="' + supplierId + '"]');
        var subtotal = 0;
        
        $block.find('.vwpm-po-row').each(function() {
            if ($(this).find('.vwpm-po-include').is(':checked')) {
                var lineText = $(this).find('.vwpm-po-line').text().replace(/[£,]/g, '');
                subtotal += parseFloat(lineText) || 0;
            }
        });
        
        var vatEnabled = $('#vwpm_vat_toggle').is(':checked');
        var vat = vatEnabled ? subtotal * 0.20 : 0;
        var grandTotal = subtotal + vat;
        
        $block.find('.vwpm-supplier-subtotal-value').text('£' + subtotal.toFixed(2));
        $block.find('.vwpm-supplier-vat-value').text('£' + vat.toFixed(2));
        $block.find('.vwpm-supplier-total-value').text('£' + grandTotal.toFixed(2));
        
        // Show/hide VAT row
        if (vatEnabled) {
            $block.find('.vwpm-supplier-vat').show();
        } else {
            $block.find('.vwpm-supplier-vat').hide();
        }
    }
    
    // Add shipping button
    $(document).on('click', '.vwpm-add-shipping-btn', function(e) {
        e.preventDefault();
        var supplierId = $(this).data('supplier-id');
        var block = $('.vwpm-supplier-block[data-supplier-id="' + supplierId + '"]');
        var table = block.find('.vwpm-results-table tbody');
        
        var shippingCost = prompt('Enter shipping/delivery cost (£):', '0');
        if (shippingCost === null) return;
        
        shippingCost = parseFloat(shippingCost) || 0;
        var customId = 'shipping_' + Date.now();
        
        var row = '<tr data-component-id="' + customId + '" class="vwpm-po-row" style="background:#e8f4f8;">';
        row += '<td style="text-align:center;"><input type="checkbox" class="vwpm-po-include" data-supplier-id="' + supplierId + '" checked></td>';
        row += '<td>Shipping / Delivery</td>';
        row += '<td>-</td>';
        row += '<td>-</td>';
        row += '<td><input type="number" step="0.01" class="vwpm-po-qty" value="1" style="width:100px;" data-unit-price="' + shippingCost + '"></td>';
        row += '<td class="vwpm-po-unit">£' + shippingCost.toFixed(2) + '</td>';
        row += '<td class="vwpm-po-line">£' + shippingCost.toFixed(2) + '</td>';
        row += '</tr>';
        
        table.find('.vwpm-supplier-subtotal').before(row);
        recalculateSupplierTotal(supplierId);
    });
});
</script>
