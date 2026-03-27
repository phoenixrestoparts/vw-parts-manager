<?php
if (!defined('ABSPATH')) { 
    exit; 
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
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
    <h1>Production Calculator</h1>
    
    <div class="vwpm-card">
        <h2>Calculate Production Requirements</h2>
        <form id="vwpm-production-form">
            <table class="form-table">
                <tr>
                    <th><label for="vwpm_product_id">Select Product (search by SKU or name)</label></th>
                    <td>
                        <select id="vwpm_product_id" name="product_id" class="vwpm-product-select" style="width: 100%;" required>
                            <option value="">-- Type SKU or Product Name --</option>
                            <?php foreach ($products as $product): 
                                $sku = get_post_meta($product->ID, '_sku', true);
                                $display_text = $sku ? $sku . ' - ' . $product->post_title : $product->post_title;
                            ?>
                                <option value="<?php echo esc_attr($product->ID); ?>" data-sku="<?php echo esc_attr($sku); ?>">
                                    <?php echo esc_html($display_text); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Start typing a product SKU or name to search</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="vwpm_quantity">Quantity to Produce</label></th>
                    <td>
                        <input type="number" id="vwpm_quantity" name="quantity" min="1" step="1" value="1" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="vwpm_product_type">Product Type</label></th>
                    <td>
                        <select id="vwpm_product_type" name="product_type" class="regular-text">
                            <option value="manufactured">Manufactured (has BOM)</option>
                            <option value="ready_made">Ready-Made (purchase complete)</option>
                        </select>
                        <p class="description">Select "Manufactured" if this product has a Bill of Materials, or "Ready-Made" if you purchase it complete from a supplier.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large">Calculate Requirements</button>
            </p>
        </form>
    </div>

    <div id="vwpm-results"></div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize Select2 with SKU search for products
    $('.vwpm-product-select').select2({
        width: '100%',
        placeholder: '-- Type SKU or Product Name --',
        allowClear: true,
        matcher: function(params, data) {
            // If there are no search terms, return all data
            if ($.trim(params.term) === '') {
                return data;
            }

            // Skip if there is no 'text' or 'element' property
            if (typeof data.text === 'undefined') {
                return null;
            }

            var term = params.term.toLowerCase();
            var text = data.text.toLowerCase();
            var sku = $(data.element).data('sku');
            
            // Search in both product name and SKU
            if (text.indexOf(term) > -1) {
                return data;
            }
            
            if (sku && String(sku).toLowerCase().indexOf(term) > -1) {
                return data;
            }

            return null;
        }
    });

    $('#vwpm-production-form').on('submit', function(e) {
        e.preventDefault();
        
        var productId = $('#vwpm_product_id').val();
        var quantity = $('#vwpm_quantity').val();
        var productType = $('#vwpm_product_type').val();

        if (!productId || !quantity) {
            alert('Please select a product and enter quantity');
            return;
        }

        $('#vwpm-results').html('<div class="notice notice-info"><p>Calculating requirements...</p></div>');

        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vwpm_calculate_production',
                nonce: vwpm_ajax.nonce,
                product_id: productId,
                quantity: quantity,
                product_type: productType
            },
            success: function(response) {
                if (response.success) {
                    $('#vwpm-results').html(response.data.html);
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

    function recalculateSupplierTotal(supplierId) {
        var block = $('.vwpm-supplier-block[data-supplier-id="' + supplierId + '"]');
        var total = 0;
        
        block.find('.vwpm-po-row').each(function() {
            if ($(this).find('.vwpm-po-include').is(':checked')) {
                var lineText = $(this).find('.vwpm-po-line').text().replace(/[£,]/g, '');
                total += parseFloat(lineText) || 0;
            }
        });
        
        block.find('.vwpm-supplier-total-value').text('£' + total.toFixed(2));
    }

    // NOTE: Save PO and Create PO handlers are in vw-parts-manager.php
    // Do NOT duplicate them here!
});
</script>
