<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get all products
$all_products = get_posts(array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
));

// Separate manufactured vs ready-made products
$manufactured_products = array();
$ready_made_products = array();

foreach ($all_products as $product) {
    $supplier_id = get_post_meta($product->ID, '_vwpm_product_supplier_id', true);
    if ($supplier_id) {
        $ready_made_products[] = $product;
    } else {
        $manufactured_products[] = $product;
    }
}

// Prepare data for JavaScript
$manufactured_data = array_map(function($p) {
    $sku = get_post_meta($p->ID, '_sku', true);
    return array(
        'id' => $p->ID,
        'sku' => $sku,
        'title' => $p->post_title,
        'display' => ($sku ? $sku . ' - ' : '') . $p->post_title
    );
}, $manufactured_products);

$ready_made_data = array_map(function($p) {
    $sku = get_post_meta($p->ID, '_sku', true);
    return array(
        'id' => $p->ID,
        'sku' => $sku,
        'title' => $p->post_title,
        'display' => ($sku ? $sku . ' - ' : '') . $p->post_title
    );
}, $ready_made_products);

?>

<div class="wrap">
    <h1>Production Calculator</h1>
    
    <div class="vwpm-admin-wrapper">
        
        <div class="vwpm-card vwpm-production-calculator">
            <h2>Calculate Production Requirements</h2>
            
            <form class="vwpm-calculator-form">
                <div class="vwpm-form-row">
                    <label for="vwpm_production_type">Production Type</label>
                    <select id="vwpm_production_type" class="regular-text">
                        <option value="manufactured">Manufactured Products (with BOM)</option>
                        <option value="ready_made">Ready-Made Products (for resale)</option>
                    </select>
                </div>
                
                <div class="vwpm-form-row">
                    <label for="vwpm_product_id">Select Product (search by SKU or name)</label>
                    <select id="vwpm_product_id" class="vwpm-product-select" style="width: 100%;">
                        <option value="">-- Select Product --</option>
                        <?php foreach ($manufactured_products as $product): 
                            $sku = get_post_meta($product->ID, '_sku', true);
                        ?>
                            <option value="<?php echo $product->ID; ?>" data-type="manufactured" data-sku="<?php echo esc_attr($sku); ?>">
                                <?php echo esc_html($sku ? $sku . ' - ' : '') . esc_html($product->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="vwpm-form-row">
                    <label for="vwpm_quantity">Quantity to Produce/Order</label>
                    <input type="number" id="vwpm_quantity" min="1" value="1" class="regular-text">
                </div>
                
                <button type="button" id="vwpm-calculate-production" class="button button-primary button-large">
                    Calculate Requirements
                </button>
            </form>
            
            <div id="vwpm-calculator-results" style="display: none;">
                <!-- Results will be loaded here via AJAX -->
            </div>
            
          
<div id="vwpm-production-batch" style="margin-top:18px;">
    <div style="margin-bottom:8px;">
        <!-- The product select/qty IDs below are examples; change to match your markup -->
        <button id="vwpm-add-to-batch" class="button">Add to Batch</button>
        <button id="vwpm-run-batch-calc" class="button">Calculate Batch Requirements</button>
        <span id="vwpm-batch-status" style="margin-left:12px;"></span>
    </div>

    <!-- Results will be appended here by AJAX -->
    <div id="vwpm-production-results-container"></div>
</div>
            
        </div>
        
        <!-- Help Section -->
        <div class="vwpm-card">
            <h2>How to Use</h2>
            <ol>
                <li><strong>Choose Production Type:</strong> Select whether you're manufacturing products (using components) or ordering ready-made products.</li>
                <li><strong>Select a Product:</strong> Type to search by SKU or product name.</li>
                <li><strong>Enter Quantity:</strong> How many units do you need?</li>
                <li><strong>Calculate:</strong> Click the button to see what components you need to order.</li>
                <li><strong>Generate PO:</strong> Review the results and either email the PO to your supplier or print it as PDF.</li>
            </ol>
            
            <div class="vwpm-message info">
                <strong>Note:</strong> Make sure your products have their Bill of Materials set up (for manufactured items) or supplier assigned (for ready-made items) before using the calculator.
            </div>
        </div>
        
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('Production page script loaded');
    console.log('vwpm_ajax available:', typeof vwpm_ajax !== 'undefined');

    // Initialize Select2 on product dropdown
    if (typeof $.fn.select2 !== 'undefined') {
        $('#vwpm_product_id').select2({
            width: '100%',
            placeholder: 'Search by SKU or product name...'
        });
    }

    // Prepare product data for switching selects
    var vwpmProductDataLocal = {
        manufactured: <?php echo wp_json_encode($manufactured_data); ?>,
        readyMade: <?php echo wp_json_encode($ready_made_data); ?>
    };

    // Update product dropdown when type changes
    $('#vwpm_production_type').on('change', function() {
        var type = $(this).val();
        var $productSelect = $('#vwpm_product_id');

        // Destroy Select2 before updating (if present)
        if (typeof $.fn.select2 !== 'undefined') {
            try { $productSelect.select2('destroy'); } catch(e) { /* ignore */ }
        }

        $productSelect.html('<option value="">-- Select Product --</option>');

        var products = (type === 'manufactured') ? vwpmProductDataLocal.manufactured : vwpmProductDataLocal.readyMade;

        $.each(products, function(i, product) {
            $productSelect.append($('<option>', {
                value: product.id,
                text: product.display,
                'data-sku': product.sku
            }));
        });

        // Re-initialize Select2
        if (typeof $.fn.select2 !== 'undefined') {
            $productSelect.select2({
                width: '100%',
                placeholder: 'Search by SKU or product name...'
            });
        }
    });

    // -------------------------
    // Single-product calculate (existing)
    // -------------------------
    $('#vwpm-calculate-production').on('click', function(e) {
        e.preventDefault();
        console.log('Calculate button clicked!');

        var productType = $('#vwpm_production_type').val();
        var productId = $('#vwpm_product_id').val();
        var quantity = $('#vwpm_quantity').val();

        if (!productId || !quantity) {
            alert('Please select a product and enter a quantity');
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Calculating...');

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
            dataType: 'json',
            success: function(response) {
                console.log('AJAX response:', response);
                if (response && response.success) {
                    // Show single-product results
                    $('#vwpm-calculator-results').html(response.data.html).show();
                    // attach PO UI events if returned HTML contains editable rows
                    attachPoUiEvents();
                } else {
                    alert('Error: ' + (response && response.data && response.data.message ? response.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr && xhr.responseText ? xhr.responseText : xhr);
                alert('An error occurred. Check console for details.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Calculate Requirements');
            }
        });
    });

    // -------------------------
    // Batch / multi-product functionality
    // -------------------------
    window.vwpm_selectedProducts = window.vwpm_selectedProducts || [];

    // Add currently selected product into batch
    $('#vwpm-add-to-batch').on('click', function(e){
        e.preventDefault();
        var productId = parseInt($('#vwpm_product_id').val() || 0, 10);
        var qty = parseFloat($('#vwpm_quantity').val() || 0);
        var type = $('#vwpm_production_type').val() || 'manufactured';

        if (!productId || qty <= 0) {
            alert('Please choose a product and enter a quantity.');
            return;
        }

        // push into batch array
        window.vwpm_selectedProducts.push({ product_id: productId, quantity: qty, product_type: type });

        $('#vwpm-batch-status').text('Added to batch (' + window.vwpm_selectedProducts.length + ' items).');
        // auto-calc
        vwpmCalculateBatch();
    });

    // Manual calculate button for the batch
    $('#vwpm-run-batch-calc').on('click', function(e){
        e.preventDefault();
        vwpmCalculateBatch();
    });

    // Calculate batch (sends products[] to server)
    window.vwpmCalculateBatch = function(){
        if ( window.vwpm_selectedProducts.length === 0 ) {
            alert('Add at least one product to the batch first.');
            return;
        }
        $('#vwpm-batch-status').text('Calculating...');
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_calculate_production',
            nonce: vwpm_ajax.nonce,
            products: window.vwpm_selectedProducts
        }, function(res){
            $('#vwpm-batch-status').text('');
            if ( res && res.success ) {
                // Put results into the batch results container
                $('#vwpm-production-results-container').html(res.data.html);
                // Attach PO UI behavior for newly rendered DOM
                attachPoUiEvents();
            } else {
                console.error(res);
                alert('Error calculating requirements. Check debug log.');
            }
        }).fail(function(xhr, status, err){
            $('#vwpm-batch-status').text('');
            console.error('Batch calculate failed', status, err, xhr && xhr.responseText ? xhr.responseText : xhr);
            alert('Batch calculate AJAX failed. See console.');
        });
    };

    // -------------------------
    // Attach UI handlers for PO rows rendered by vwpm_build_po_html_multi
    // -------------------------
    function attachPoUiEvents() {

        // Recalculate a single row (line total) based on qty and unit price
        function recalcRow($row) {
            var $qty = $row.find('.vwpm-po-qty');
            var qty = parseFloat($qty.val()) || 0;
            var unit = parseFloat($qty.data('unit-price')) || 0;

            // Fallback: try to parse unit from display cell if data attr missing
            if (!unit) {
                var unitText = $row.find('.vwpm-po-unit').text();
                unit = parseFloat(unitText.replace(/[^0-9\.\-]/g, '')) || 0;
            }

            var line = qty * unit;
            $row.find('.vwpm-po-line').text('£' + line.toFixed(2));
            return line;
        }

        // Recalculate supplier block total
        function recalcSupplierTotal($supplierBlock) {
            var total = 0;
            $supplierBlock.find('.vwpm-po-row').each(function(){
                var $r = $(this);
                if ( $r.find('.vwpm-po-include').prop('checked') ) {
                    total += recalcRow($r);
                }
            });
            $supplierBlock.find('.vwpm-supplier-total-value').text('£' + total.toFixed(2));
            return total;
        }

        // Wire qty changes to recalc
        $(document).off('input', '.vwpm-po-qty').on('input', '.vwpm-po-qty', function(){
            var $row = $(this).closest('.vwpm-po-row');
            var $supplierBlock = $(this).closest('.vwpm-supplier-block');
            recalcRow($row);
            recalcSupplierTotal($supplierBlock);
        });

        // Wire include checkbox to recalc totals when toggled
        $(document).off('change', '.vwpm-po-include').on('change', '.vwpm-po-include', function(){
            var $supplierBlock = $(this).closest('.vwpm-supplier-block');
            recalcSupplierTotal($supplierBlock);
        });

        // SAVE selection for a supplier -> transient (vwpm_save_po_selection)
        $(document).off('click', '.vwpm-save-po-btn').on('click', '.vwpm-save-po-btn', function(e){
            e.preventDefault();
            var $btn = $(this);
            var originalText = $btn.text();
            var supplierId = $btn.data('supplier-id') || $btn.closest('.vwpm-supplier-block').data('supplier-id');
            var $block = $btn.closest('.vwpm-supplier-block');

            if (!supplierId) {
                alert('Missing supplier id');
                return;
            }

            // Gather items from this supplier block
            var items = [];
            $block.find('.vwpm-po-row').each(function(){
                var $r = $(this);
                if ( ! $r.find('.vwpm-po-include').prop('checked') ) {
                    return; // skip unchecked rows
                }
                var component_id = $r.data('component-id') || '';
                var qty = parseFloat($r.find('.vwpm-po-qty').val()) || 0;
                var unit_price = parseFloat($r.find('.vwpm-po-qty').data('unit-price')) || parseFloat($r.find('.vwpm-po-unit').text().replace(/[^0-9\.\-]/g,'')) || 0;
                var component_name = $r.find('td').eq(1).text().trim();
                var component_number = $r.find('td').eq(2).text().trim();
                var supplier_ref = $r.find('td').eq(3).text().trim();

                items.push({
                    component_id: component_id,
                    component_name: component_name,
                    component_number: component_number,
                    qty: qty,
                    unit_price: unit_price,
                    supplier_ref: supplier_ref
                });
            });

            if (items.length === 0) {
                alert('No items selected to save.');
                return;
            }

            // Tools (optional): gather tools from page if present
            var tools = [];
            $('.vwpm-tools-rows .vwpm-tool-row').each(function(){
                var toolId = $(this).find('select').val();
                if (toolId) tools.push(toolId);
            });

            // Products summary: use global if available, otherwise empty array
            var products = window.vwpm_selectedProducts || [];

            $btn.prop('disabled', true).text('Saving...');

            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_save_po_selection',
                nonce: vwpm_ajax.nonce,
                supplier_id: supplierId,
                items: items,
                tools: tools,
                products: products,
                type: 'manufactured'
            }, function(res){
                $btn.prop('disabled', false).text(originalText);
                console.log('Save PO response:', res);
                if (res && res.success) {
                    alert('Selection saved for supplier.');
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Unknown error';
                    alert('Save PO failed: ' + msg);
                    console.error('Save PO error detail:', res);
                }
            }).fail(function(xhr, status, err){
                $btn.prop('disabled', false).text(originalText);
                console.error('Save PO AJAX failed:', status, err, xhr && xhr.responseText ? xhr.responseText : xhr);
                alert('Save PO failed. See console and server logs for details.');
            });
        });

        // CREATE persistent PO from the current transient (vwpm_create_po_from_transient)
        $(document).off('click', '.vwpm-create-po-btn').on('click', '.vwpm-create-po-btn', function(e){
            e.preventDefault();

            if (!confirm('Create a persistent PO from your saved selection?')) return;

            var $btn = $(this);
            var originalText = $btn.text();

            $btn.prop('disabled', true).text('Creating...');

            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_create_po_from_transient',
                nonce: vwpm_ajax.nonce
            }, function(res){
                $btn.prop('disabled', false).text(originalText || 'Create PO (persist)');
                console.log('Create PO response:', res);
                if (res && res.success) {
                    alert('PO created: ' + (res.data.po_number || 'unknown'));
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Unknown error';
                    alert('Failed to create PO: ' + msg);
                    console.error('Create PO error detail:', res);
                }
            }).fail(function(xhr, status, err){
                $btn.prop('disabled', false).text(originalText || 'Create PO (persist)');
                console.error('Create PO AJAX failed:', status, err, xhr && xhr.responseText ? xhr.responseText : xhr);
                alert('Create PO request failed. See console and server logs for details.');
            });
        });

    } // end attachPoUiEvents

    // initialize handlers for existing content (if any)
    attachPoUiEvents();

}); // end jQuery ready
</script>
