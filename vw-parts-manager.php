<?php
/**
 * Plugin Name: VW Parts Manufacturing Manager
 * Description: Manage components, tools, BOMs, and purchase orders for VW parts manufacturing
 * Version: 1.0.3
 * Author: Custom Development
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('VWPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VWPM_PLUGIN_URL', plugin_dir_url(__FILE__));

class VW_Parts_Manager {
    
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Register product meta boxes
add_action('add_meta_boxes_product', array($this, 'add_product_meta_boxes'));        
add_action('save_post_product', array($this, 'save_product_meta'));
        
        // AJAX handlers (registered once here)
        add_action('wp_ajax_vwpm_calculate_production', 'vwpm_ajax_calculate_production');
        add_action('wp_ajax_vwpm_email_po', 'vwpm_ajax_email_po');
        add_action('wp_ajax_vwpm_import_tools', 'vwpm_ajax_import_tools');
        add_action('wp_ajax_vwpm_import_components', 'vwpm_ajax_import_components');
        add_action('wp_ajax_vwpm_export_tools', 'vwpm_ajax_export_tools');
        add_action('wp_ajax_vwpm_export_components', 'vwpm_ajax_export_components');
        add_action('wp_ajax_vwpm_import_product_boms', 'vwpm_ajax_import_product_boms');
        add_action('wp_ajax_vwpm_add_supplier', 'vwpm_ajax_add_supplier');
        add_action('wp_ajax_vwpm_update_supplier', 'vwpm_ajax_update_supplier');
        add_action('wp_ajax_vwpm_delete_supplier', 'vwpm_ajax_delete_supplier');
    }
    
    public function activate() {
        $this->create_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Suppliers table
        $table_name = $wpdb->prefix . 'vwpm_suppliers';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255),
            contact_details text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $column = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'notes'
        ) );
        if ( empty( $column ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN notes text" );
        }

        // POs table
        $table_pos = $wpdb->prefix . 'vwpm_pos';
        $sql_pos = "CREATE TABLE IF NOT EXISTS {$table_pos} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            po_number varchar(50) NOT NULL,
            user_id bigint(20) NOT NULL,
            supplier_id bigint(20) DEFAULT 0,
            supplier_name varchar(255) DEFAULT '',
            supplier_email varchar(255) DEFAULT '',
            items longtext DEFAULT NULL,
            tools longtext DEFAULT NULL,
            product_summary longtext DEFAULT NULL,
            total_cost decimal(12,2) DEFAULT 0,
            status varchar(32) DEFAULT 'prepared',
            is_locked tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY po_number_unique (po_number)
        ) $charset_collate;";

        dbDelta( $sql_pos );

        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_pos ) );
        if ( ! $exists ) {
            $wpdb->query( $sql_pos );
        }
    }
    
    public function init() {
        $this->register_tools_post_type();
        $this->register_components_post_type();
    }
    
    private function register_tools_post_type() {
        $labels = array(
            'name' => 'Tools',
            'singular_name' => 'Tool',
            'menu_name' => 'Tools',
            'add_new' => 'Add New Tool',
            'add_new_item' => 'Add New Tool',
            'edit_item' => 'Edit Tool',
            'new_item' => 'New Tool',
            'view_item' => 'View Tool',
            'search_items' => 'Search Tools',
            'not_found' => 'No tools found',
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'supports' => array('title'),
            'has_archive' => false,
        );
        
        register_post_type('vwpm_tool', $args);
        
        add_action('add_meta_boxes_vwpm_tool', array($this, 'add_tool_meta_boxes'));
        add_action('save_post_vwpm_tool', array($this, 'save_tool_meta'));
    }
    
    private function register_components_post_type() {
        $labels = array(
            'name' => 'Components',
            'singular_name' => 'Component',
            'menu_name' => 'Components',
            'add_new' => 'Add New Component',
            'add_new_item' => 'Add New Component',
            'edit_item' => 'Edit Component',
            'new_item' => 'New Component',
            'view_item' => 'View Component',
            'search_items' => 'Search Components',
            'not_found' => 'No components found',
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'supports' => array('title'),
            'has_archive' => false,
        );
        
        register_post_type('vwpm_component', $args);
        
        add_action('add_meta_boxes_vwpm_component', array($this, 'add_component_meta_boxes'));
        add_action('save_post_vwpm_component', array($this, 'save_component_meta'));
    }
    
    public function add_admin_menus() {
        add_menu_page(
            'Manufacturing Manager',
            'Manufacturing',
            'manage_options',
            'vw-parts-manager',
            array($this, 'render_dashboard'),
            'dashicons-hammer',
            30
        );
        
        add_submenu_page(
            'vw-parts-manager',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'vw-parts-manager',
            array($this, 'render_dashboard')
        );
        
        add_submenu_page(
            'vw-parts-manager',
            'Suppliers',
            'Suppliers',
            'manage_options',
            'vwpm-suppliers',
            array($this, 'render_suppliers_page')
        );
        
        add_submenu_page(
            'vw-parts-manager',
            'Tools',
            'Tools',
            'manage_options',
            'edit.php?post_type=vwpm_tool'
        );
        
        add_submenu_page(
            'vw-parts-manager',
            'Components',
            'Components',
            'manage_options',
            'edit.php?post_type=vwpm_component'
        );
        
        add_submenu_page(
            'vw-parts-manager',
            'Production Calculator',
            'Production Calculator',
            'manage_options',
            'vwpm-production',
            array($this, 'render_production_page')
        );
        
         add_submenu_page(
            'vw-parts-manager',
            'Create Custom PO',
            'Create Custom PO',
            'manage_options',
            'vwpm-custom-po',
            array($this, 'render_custom_po_page')
        );
        
        add_submenu_page(
            'vw-parts-manager',
            'Import/Export',
            'Import/Export',
            'manage_options',
            'vwpm-import-export',
            array($this, 'render_import_export_page')
        );
        
        add_submenu_page(
            'vw-parts-manager',
            'Purchase Orders',
            'Purchase Orders',
            'manage_options',
            'vwpm-purchase-orders',
            array($this, 'render_purchase_orders_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        
        // Enqueue Select2 on product edit pages and other admin pages
        $allowed_screens = array('product', 'vwpm_component', 'vwpm_tool', 'toplevel_page_vw-parts-manager', 'manufacturing_page_vwpm-production');
        
        if (in_array($screen->id, $allowed_screens) || strpos($screen->id, 'vwpm') !== false) {
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        }
        
        // Output inline CSS and JS
        add_action('admin_head', array($this, 'output_inline_css'));
        add_action('admin_footer', array($this, 'output_inline_js'));
    }
    
    public function output_inline_css() {
        ?>
        <style type="text/css" id="vwpm-admin-styles">
        .vwpm-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin: 20px 0;
            padding: 20px;
        }
        .vwpm-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .vwpm-stat-card {
            background: #f0f0f1;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .vwpm-stat-label {
            display: block;
            font-size: 14px;
            color: #646970;
            margin-bottom: 5px;
        }
        .vwpm-stat-number {
            display: block;
            font-size: 32px;
            font-weight: bold;
            color: #1d2327;
        }
        .vwpm-results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .vwpm-results-table th,
        .vwpm-results-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .vwpm-results-table th {
            background: #f0f0f1;
            font-weight: bold;
        }
        .vwpm-supplier-block {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .vwpm-calculator-results {
            margin-top: 20px;
        }
        </style>
        <?php
    }

    public function output_inline_js() {
    ?>
    <script type="text/javascript">
    var vwpm_ajax = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('vwpm_nonce'); ?>'
    };
    
    jQuery(document).ready(function($) {
        // Initialize Select2 on product supplier dropdown
        if ($('#vwpm_product_supplier').length) {
            $('#vwpm_product_supplier').select2({
                width: '100%',
                placeholder: 'Search for supplier or select none...',
                allowClear: true
            });
        }
        
        // Initialize Select2 on component dropdowns
        $('.vwpm-component-select').select2({
            width: '100%',
            placeholder: 'Search by SKU or name...'
        });
        
        // Initialize Select2 on tool dropdowns
        $('.vwpm-tool-select').select2({
            width: '100%',
            placeholder: 'Search by number or name...'
        });
        
        // BOM Repeater
        $(document).on('click', '#vwpm-add-bom-row', function(e) {
            e.preventDefault();
            var $rows = $('#vwpm-bom-rows');
            var bomIndex = $rows.find('tr').length;
            var template = $('#vwpm-bom-row-template').html();
            template = template.replace(/INDEX/g, bomIndex);
            $rows.append(template);
            
            // Initialize Select2 on new row
            $rows.find('tr:last .vwpm-component-select').select2({
                width: '100%',
                placeholder: 'Search by SKU or name...'
            });
        });
        
        $(document).on('click', '.vwpm-remove-row', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            if ($row.find('select').hasClass('select2-hidden-accessible')) {
                $row.find('select').select2('destroy');
            }
            $row.remove();
        });
        
        // Tools Repeater
        $(document).on('click', '#vwpm-add-tool-row', function(e) {
            e.preventDefault();
            var $rows = $('#vwpm-tools-rows');
            var toolIndex = $rows.find('tr').length;
            var template = $('#vwpm-tool-row-template').html();
            template = template.replace(/INDEX/g, toolIndex);
            $rows.append(template);
            
            // Initialize Select2 on new row
            $rows.find('tr:last .vwpm-tool-select').select2({
                width: '100%',
                placeholder: 'Search by number or name...'
            });
        });
        
        $(document).on('click', '.vwpm-remove-tool-row', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            if ($row.find('select').hasClass('select2-hidden-accessible')) {
                $row.find('select').select2('destroy');
            }
            $row.remove();
        });
        
        // Production Calculator
        $(document).on('click', '#vwpm-calculate-production', function(e) {
            e.preventDefault();
            
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
                success: function(response) {
                    if (response.success) {
                        $('#vwpm-calculator-results').html(response.data.html).show();
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to calculate'));
                    }
                    $button.prop('disabled', false).text('Calculate Production Requirements');
                },
                error: function() {
                    alert('Request failed');
                    $button.prop('disabled', false).text('Calculate Production Requirements');
                }
            });
        });
        
        // BOM Row Management
        var bomIndex = $('#vwpm-bom-rows tr').length;
        
        $(document).on('click', '.vwpm-remove-row', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
        });

        // Tools Row Management
        var toolIndex = $('#vwpm-tools-rows tr').length;

        $(document).on('click', '.vwpm-remove-tool-row', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
        });

        // Production Calculator
        function formatStatus(raw) {
            if (raw === null || raw === undefined) return 'prepared';
            raw = String(raw).trim();
            if (raw === '' || raw === '0') return 'prepared';
            return raw;
        }

        function fetchPos() {
            $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Loading…</td></tr>');
            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_get_pos',
                nonce: vwpm_ajax.nonce
            }, function(res){
                if (!res || !res.success) {
                    $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Failed to load POs</td></tr>');
                    return;
                }
                var rows = res.data.pos || [];
                if (!rows.length) {
                    $('#vwpm-pos-table tbody').html('<tr><td colspan="8">No purchase orders found.</td></tr>');
                    return;
                }
                var html = '';
                rows.forEach(function(r){
                    var status = formatStatus(r.status);
                    var locked = (Number(r.is_locked) === 1);
                    var created = r.created_at || '';
                    var updated = r.updated_at || '';

                    html += '<tr data-po-id="'+r.id+'">';
                    html += '<td>'+ (r.po_number || '') +'</td>';
                    html += '<td>'+ (r.supplier_name || '-') +'</td>';
                    html += '<td>£'+ (parseFloat(r.total_cost) ? parseFloat(r.total_cost).toFixed(2) : '0.00') +'</td>';
                    html += '<td>'+ status +'</td>';
                    html += '<td>'+(locked ? 'Yes' : 'No')+'</td>';
                    html += '<td>'+created+'</td>';
                    html += '<td>'+updated+'</td>';
                    html += '<td>';
                    html += '<button class="button vwpm-po-view">View</button> ';
                    html += '<button class="button vwpm-po-mark" data-status="ordered">Mark Ordered</button> ';
                    html += '<button class="button vwpm-po-mark" data-status="received">Mark Received</button> ';
                    html += '<button class="button vwpm-po-toggle-lock">'+(locked ? 'Unlock' : 'Lock')+'</button>';
                    html += '</td>';
                    html += '</tr>';
                });
                $('#vwpm-pos-table tbody').html(html);
            }).fail(function(){
                $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Failed to load POs (request failed)</td></tr>');
            });
        }

        $('#vwpm-refresh-pos').on('click', function(e) {
            e.preventDefault();
            fetchPos();
        });

        // Auto-load POs on page load if table exists
        if ($('#vwpm-pos-table').length) {
            fetchPos();
        }

        // PO quantity/checkbox changes
        $(document).on('change', '.vwpm-po-include', function() {
            recalculateSupplierTotal($(this).data('supplier-id'));
        });

        $(document).on('input', '.vwpm-po-qty', function() {
            var $row = $(this).closest('tr');
            var qty = parseFloat($(this).val()) || 0;
            var unitPrice = parseFloat($(this).data('unit-price')) || 0;
            var lineTotal = qty * unitPrice;
            $row.find('.vwpm-po-line').text('£' + lineTotal.toFixed(2));
            recalculateSupplierTotal($(this).closest('.vwpm-supplier-block').data('supplier-id'));
        });

        function recalculateSupplierTotal(supplierId) {
            var $block = $('.vwpm-supplier-block[data-supplier-id="' + supplierId + '"]');
            var total = 0;
            $block.find('.vwpm-po-row').each(function() {
                if ($(this).find('.vwpm-po-include').is(':checked')) {
                    var lineText = $(this).find('.vwpm-po-line').text().replace(/[£,]/g, '');
                    total += parseFloat(lineText) || 0;
                }
            });
            $block.find('.vwpm-supplier-total-value').text('£' + total.toFixed(2));
        }

        // Create PO - FIXED to prevent duplicates
$(document).on('click', '.vwpm-create-po-btn', function(e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    
    var $btn = $(this);
    
    if ($btn.prop('disabled')) {
        return false;
    }
    
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
    
    return false;
});

// Save PO - UPDATED to show Create/Print buttons after saving
$(document).on('click', '.vwpm-save-po-btn', function(e) {
    e.preventDefault();
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

    // Get product info from products table
    var products = [];
    $('#products-list tr').each(function() {
        var $row = $(this);
        var productId = $row.find('.product-select').val();
        var qty = $row.find('.product-qty').val();
        
        if (productId && qty) {
            var productTitle = $row.find('.product-select option:selected').text();
            products.push({
                product_id: productId,
                title: productTitle,
                quantity: qty
            });
        }
    });

    console.log('Products being saved:', products);

    $btn.prop('disabled', true).text('Saving...');

    $.ajax({
        url: vwpm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'vwpm_save_po_selection',
            nonce: vwpm_ajax.nonce,
            supplier_id: supplierId,
            items: items,
            tools: tools,
            products: products,
            type: $('#vwpm_product_type').length ? $('#vwpm_product_type').val() : 'manufactured'
        },
        success: function(response) {
            if (response.success) {
                alert('PO selection saved! You can now create or print.');
                $btn.text('Saved ✓').css('background', '#46b450');
                
                // Show the Create and Print buttons
                block.find('.vwpm-create-po-btn, .vwpm-print-po-btn').show();
            } else {
                alert('Error: ' + (response.data.message || 'Failed to save'));
                $btn.prop('disabled', false).text('Save Selection for Print/Create');
            }
        },
        error: function() {
            alert('Request failed');
            $btn.prop('disabled', false).text('Save Selection for Print/Create');
        }
    });
});

// Print PO button
$(document).on('click', '.vwpm-print-po-btn', function(e) {
    e.preventDefault();
    window.open(vwpm_ajax.ajax_url.replace('admin-ajax.php', 'admin.php') + '?vwpm_print_po=1', '_blank');
});

// Add custom line to PO
$(document).on('click', '.vwpm-add-custom-line-btn', function(e) {
    e.preventDefault();
    var supplierId = $(this).data('supplier-id');
    var block = $('.vwpm-supplier-block[data-supplier-id="' + supplierId + '"]');
    var table = block.find('.vwpm-results-table tbody');
    
    // Prompt for custom item details
    var itemName = prompt('Enter item name:');
    if (!itemName) return;
    
    var itemNumber = prompt('Enter part/item number (optional):', '');
    var supplierRef = prompt('Enter supplier reference (optional):', '');
    var qty = prompt('Enter quantity:', '1');
    var unitPrice = prompt('Enter unit price (£):', '0');
    
    qty = parseFloat(qty) || 1;
    unitPrice = parseFloat(unitPrice) || 0;
    var lineTotal = qty * unitPrice;
    
    // Generate a unique custom ID
    var customId = 'custom_' + Date.now();
    
    // Add row to table (before the total row)
    var row = '<tr data-component-id="' + customId + '" class="vwpm-po-row vwpm-custom-row" style="background:#fffbcc;">';
    row += '<td style="text-align:center;"><input type="checkbox" class="vwpm-po-include" data-supplier-id="' + supplierId + '" checked></td>';
        row += '<td>' + itemName + '</td>';
    row += '<td>' + itemNumber + '</td>';
    row += '<td>' + (supplierRef || '-') + '</td>';
    row += '<td><input type="number" step="0.01" class="vwpm-po-qty" value="' + qty.toFixed(2) + '" style="width:100px;" data-unit-price="' + unitPrice + '"></td>';
    row += '<td class="vwpm-po-unit">£' + unitPrice.toFixed(2) + '</td>';
    row += '<td class="vwpm-po-line">£' + lineTotal.toFixed(2) + '</td>';
    row += '</tr>';
    
    // Insert before the supplier total row
    table.find('.vwpm-supplier-total').before(row);
    
    // Recalculate total
    recalculateSupplierTotal(supplierId);
    
    alert('Custom line added! Remember to click "Save Selection" before creating/printing the PO.');
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
    });
    </script>
    <?php
    }
    
    // Tool Meta Boxes
    public function add_tool_meta_boxes() {
        add_meta_box(
            'vwpm_tool_details',
            'Tool Details',
            array($this, 'render_tool_meta_box'),
            'vwpm_tool',
            'normal',
            'high'
        );
    }
    
    public function render_tool_meta_box($post) {
        wp_nonce_field('vwpm_tool_meta', 'vwpm_tool_nonce');
        
        $tool_number = get_post_meta($post->ID, '_vwpm_tool_number', true);
        $location = get_post_meta($post->ID, '_vwpm_location', true);
        $notes = get_post_meta($post->ID, '_vwpm_notes', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="vwpm_tool_number">Tool Number</label></th>
                <td><input type="text" id="vwpm_tool_number" name="vwpm_tool_number" value="<?php echo esc_attr($tool_number); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="vwpm_location">Location</label></th>
                <td><input type="text" id="vwpm_location" name="vwpm_location" value="<?php echo esc_attr($location); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="vwpm_notes">Notes</label></th>
                <td><textarea id="vwpm_notes" name="vwpm_notes" rows="4" class="large-text"><?php echo esc_textarea($notes); ?></textarea></td>
            </tr>
        </table>
        <?php
    }
    
    public function save_tool_meta($post_id) {
        if (!isset($_POST['vwpm_tool_nonce']) || !wp_verify_nonce($_POST['vwpm_tool_nonce'], 'vwpm_tool_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['vwpm_tool_number'])) {
            update_post_meta($post_id, '_vwpm_tool_number', sanitize_text_field($_POST['vwpm_tool_number']));
        }
        
        if (isset($_POST['vwpm_location'])) {
            update_post_meta($post_id, '_vwpm_location', sanitize_text_field($_POST['vwpm_location']));
        }
        
        if (isset($_POST['vwpm_notes'])) {
            update_post_meta($post_id, '_vwpm_notes', sanitize_textarea_field($_POST['vwpm_notes']));
        }
    }
    
    // Component Meta Boxes
    public function add_component_meta_boxes() {
        add_meta_box(
            'vwpm_component_details',
            'Component Details',
            array($this, 'render_component_meta_box'),
            'vwpm_component',
            'normal',
            'high'
        );
    }
    
    public function render_component_meta_box($post) {
        wp_nonce_field('vwpm_component_meta', 'vwpm_component_nonce');

        $component_number = get_post_meta($post->ID, '_vwpm_component_number', true);
        $supplier_id = get_post_meta($post->ID, '_vwpm_supplier_id', true);
        $price = get_post_meta($post->ID, '_vwpm_price', true);
        $notes = get_post_meta($post->ID, '_vwpm_notes', true);
        $drawing_file = get_post_meta($post->ID, '_vwpm_drawing_file', true);
        $component_location = get_post_meta($post->ID, '_vwpm_component_location', true);
        $component_supplier_ref = get_post_meta($post->ID, '_vwpm_component_supplier_ref', true);

        $suppliers = $this->get_suppliers();
        ?>
        <table class="form-table">
            <tr>
                <th><label for="vwpm_component_number">Component Number</label></th>
                <td><input type="text" id="vwpm_component_number" name="vwpm_component_number" value="<?php echo esc_attr($component_number); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="vwpm_supplier_id">Supplier</label></th>
                <td>
                    <select id="vwpm_supplier_id" name="vwpm_supplier_id" class="regular-text">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo esc_attr($supplier->id); ?>" <?php selected($supplier_id, $supplier->id); ?>>
                                <?php echo esc_html($supplier->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="vwpm_price">Price</label></th>
                <td><input type="number" step="0.01" id="vwpm_price" name="vwpm_price" value="<?php echo esc_attr($price); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="vwpm_component_location">Location</label></th>
                <td><input type="text" id="vwpm_component_location" name="vwpm_component_location" value="<?php echo esc_attr($component_location); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="vwpm_component_supplier_ref">Supplier Ref</label></th>
                <td><input type="text" id="vwpm_component_supplier_ref" name="vwpm_component_supplier_ref" value="<?php echo esc_attr($component_supplier_ref); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="vwpm_drawing_file">Drawing File (DWG)</label></th>
                <td>
                    <input type="file" id="vwpm_drawing_file" name="vwpm_drawing_file" accept=".dwg">
                    <?php if ($drawing_file): ?>
                        <p>Current file: <a href="<?php echo esc_url($drawing_file); ?>" target="_blank">View File</a></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="vwpm_notes">Notes</label></th>
                <td><textarea id="vwpm_notes" name="vwpm_notes" rows="4" class="large-text"><?php echo esc_textarea($notes); ?></textarea></td>
            </tr>
        </table>
        <?php
    }
    
    public function save_component_meta($post_id) {
        if (!isset($_POST['vwpm_component_nonce']) || !wp_verify_nonce($_POST['vwpm_component_nonce'], 'vwpm_component_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['vwpm_component_number'])) {
            update_post_meta($post_id, '_vwpm_component_number', sanitize_text_field($_POST['vwpm_component_number']));
        }

        if (isset($_POST['vwpm_supplier_id'])) {
            update_post_meta($post_id, '_vwpm_supplier_id', intval($_POST['vwpm_supplier_id']));
        }

        if (isset($_POST['vwpm_price'])) {
            update_post_meta($post_id, '_vwpm_price', floatval($_POST['vwpm_price']));
        }

        if (isset($_POST['vwpm_notes'])) {
            update_post_meta($post_id, '_vwpm_notes', sanitize_textarea_field($_POST['vwpm_notes']));
        }

        if (isset($_POST['vwpm_component_location'])) {
            update_post_meta($post_id, '_vwpm_component_location', sanitize_text_field($_POST['vwpm_component_location']));
        } else {
            delete_post_meta($post_id, '_vwpm_component_location');
        }

        if (isset($_POST['vwpm_component_supplier_ref'])) {
            update_post_meta($post_id, '_vwpm_component_supplier_ref', sanitize_text_field($_POST['vwpm_component_supplier_ref']));
        } else {
            delete_post_meta($post_id, '_vwpm_component_supplier_ref');
        }

        if (isset($_FILES['vwpm_drawing_file']) && $_FILES['vwpm_drawing_file']['error'] === UPLOAD_ERR_OK) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['vwpm_drawing_file'], array('test_form' => false));
            if (isset($upload['url'])) {
                update_post_meta($post_id, '_vwpm_drawing_file', $upload['url']);
            }
        }
    }
    
    // Product Meta Boxes (BOM)
    public function add_product_meta_boxes() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        add_meta_box(
            'vwpm_product_bom',
            'Bill of Materials',
            array($this, 'render_product_bom_meta_box'),
            'product',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vwpm_product_tools',
            'Required Tools',
            array($this, 'render_product_tools_meta_box'),
            'product',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vwpm_product_supplier',
            'Product Supplier (for ready-made items)',
            array($this, 'render_product_supplier_meta_box'),
            'product',
            'normal',
            'high'
        );
    }
    
    public function render_product_bom_meta_box($post) {
        wp_nonce_field('vwpm_product_meta', 'vwpm_product_nonce');
        
        $bom = get_post_meta($post->ID, '_vwpm_bom', true);
        if (!is_array($bom)) {
            $bom = array();
        }
        
        $components = get_posts(array(
            'post_type' => 'vwpm_component',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        <div id="vwpm-bom-repeater">
            <p><strong>Search components by SKU/Name:</strong></p>
            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width: 50%;">Component (search by name or SKU)</th>
                        <th style="width: 20%;">Quantity</th>
                        <th style="width: 30%;">Action</th>
                    </tr>
                </thead>
                <tbody id="vwpm-bom-rows">
                    <?php if (!empty($bom)): ?>
                        <?php foreach ($bom as $index => $item): ?>
                            <tr class="vwpm-bom-row">
                                <td>
                                    <select name="vwpm_bom[<?php echo $index; ?>][component_id]" class="vwpm-component-select" style="width: 100%;">
                                        <option value="">Select Component</option>
                                        <?php foreach ($components as $component): 
                                            $comp_num = get_post_meta($component->ID, '_vwpm_component_number', true);
                                        ?>
                                            <option value="<?php echo $component->ID; ?>" 
                                                    data-sku="<?php echo esc_attr($comp_num); ?>"
                                                    <?php selected($item['component_id'], $component->ID); ?>>
                                                <?php echo esc_html($comp_num ? $comp_num . ' - ' : '') . esc_html($component->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" name="vwpm_bom[<?php echo $index; ?>][quantity]" value="<?php echo esc_attr(round($item['quantity'], 0, PHP_ROUND_HALF_UP)); ?>" class="regular-text">
                                </td>
                                <td>
                                    <button type="button" class="button vwpm-remove-row">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><button type="button" class="button button-primary" id="vwpm-add-bom-row">Add Component</button></p>
        </div>
        
        <script type="text/html" id="vwpm-bom-row-template">
            <tr class="vwpm-bom-row">
                <td>
                    <select name="vwpm_bom[INDEX][component_id]" class="vwpm-component-select" style="width: 100%;">
                        <option value="">Select Component</option>
                        <?php foreach ($components as $component): 
                            $comp_num = get_post_meta($component->ID, '_vwpm_component_number', true);
                        ?>
                            <option value="<?php echo $component->ID; ?>" data-sku="<?php echo esc_attr($comp_num); ?>">
                                <?php echo esc_html($comp_num ? $comp_num . ' - ' : '') . esc_html($component->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" step="1" min="0" name="vwpm_bom[INDEX][quantity]" value="1" class="regular-text">
                </td>
                <td>
                    <button type="button" class="button vwpm-remove-row">Remove</button>
                </td>
            </tr>
        </script>
        <?php
    }
    
    public function render_product_tools_meta_box($post) {
        $tools_needed = get_post_meta($post->ID, '_vwpm_tools', true);
        if (!is_array($tools_needed)) {
            $tools_needed = array();
        }
        
        $tools = get_posts(array(
            'post_type' => 'vwpm_tool',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        <div id="vwpm-tools-repeater">
            <p><strong>Search tools by number or name:</strong></p>
            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width: 70%;">Tool (search by number or name)</th>
                        <th style="width: 30%;">Action</th>
                    </tr>
                </thead>
                <tbody id="vwpm-tools-rows">
                    <?php if (!empty($tools_needed)): ?>
                        <?php foreach ($tools_needed as $index => $tool_id): ?>
                            <tr class="vwpm-tool-row">
                                <td>
                                    <select name="vwpm_tools[<?php echo $index; ?>]" class="vwpm-tool-select" style="width: 100%;">
                                        <option value="">Select Tool</option>
                                        <?php foreach ($tools as $tool): 
                                            $tool_number = get_post_meta($tool->ID, '_vwpm_tool_number', true);
                                            $location = get_post_meta($tool->ID, '_vwpm_location', true);
                                        ?>
                                            <option value="<?php echo $tool->ID; ?>" 
                                                    data-number="<?php echo esc_attr($tool_number); ?>"
                                                    <?php selected($tool_id, $tool->ID); ?>>
                                                <?php echo esc_html($tool_number ? $tool_number . ' - ' : '') . esc_html($tool->post_title); ?>
                                                <?php echo $location ? ' [' . esc_html($location) . ']' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="button vwpm-remove-tool-row">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><button type="button" class="button button-primary" id="vwpm-add-tool-row">Add Tool</button></p>
        </div>
        
        <script type="text/html" id="vwpm-tool-row-template">
            <tr class="vwpm-tool-row">
                <td>
                    <select name="vwpm_tools[INDEX]" class="vwpm-tool-select" style="width: 100%;">
                        <option value="">Select Tool</option>
                        <?php foreach ($tools as $tool): 
                            $tool_number = get_post_meta($tool->ID, '_vwpm_tool_number', true);
                            $location = get_post_meta($tool->ID, '_vwpm_location', true);
                        ?>
                            <option value="<?php echo $tool->ID; ?>" data-number="<?php echo esc_attr($tool_number); ?>">
                                <?php echo esc_html($tool_number ? $tool_number . ' - ' : '') . esc_html($tool->post_title); ?>
                                <?php echo $location ? ' [' . esc_html($location) . ']' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="button vwpm-remove-tool-row">Remove</button>
                </td>
            </tr>
        </script>
        <?php
    }
    
public function render_product_supplier_meta_box($post) {
    wp_nonce_field('vwpm_product_meta', 'vwpm_product_nonce');
    
    $supplier_id = get_post_meta($post->ID, '_vwpm_product_supplier_id', true);
    
    ?>
    <div id="vwpm-supplier-container" class="vwpm-supplier-box" style="padding: 15px; background: #f9f9f9; border-radius: 4px;">
        <div class="vwpm-supplier-search">
            <label for="vwpm_supplier_search"><strong>Search & Add Supplier:</strong></label>
            <input type="text" 
                   id="vwpm_supplier_search" 
                   class="vwpm-supplier-search-input" 
                   placeholder="Type supplier name (minimum 2 characters)..."
                   autocomplete="off"
                   style="width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;">
            <div id="vwpm_supplier_results" class="vwpm-search-results"></div>
        </div>
        
        <div class="vwpm-supplier-selected" style="margin-top: 15px;">
            <label><strong>Currently Selected Supplier:</strong></label>
            <div id="vwpm-supplier-item" style="margin-top: 10px;">
                <?php if ($supplier_id): 
                    global $wpdb;
                    $supplier = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d",
                        $supplier_id
                    ));
                    if ($supplier):
                ?>
                    <div class="vwpm-supplier-selected-item" data-id="<?php echo esc_attr($supplier->id); ?>" style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;">
                        <span class="vwpm-supplier-name" style="font-weight: bold; flex: 1;"><?php echo esc_html($supplier->name); ?></span>
                        <button type="button" class="button button-small vwpm-remove-supplier" style="margin-left: 10px;">Remove</button>
                    </div>
                    <input type="hidden" name="vwpm_product_supplier_id" value="<?php echo esc_attr($supplier->id); ?>">
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <p class="description" style="margin-top: 10px;">Search for a supplier and click to select. Use this for ready-made products you purchase complete.</p>
    </div>
    
    <style>
        .vwpm-search-results {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            border-radius: 3px;
            max-height: 250px;
            overflow-y: auto;
            width: 100%;
            max-width: 400px;
            z-index: 9999;
            display: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .vwpm-search-results.active {
            display: block;
        }
        .vwpm-search-result-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .vwpm-search-result-item:last-child {
            border-bottom: none;
        }
        .vwpm-search-result-item:hover {
            background: #f5f5f5;
        }
        .vwpm-search-result-item strong {
            display: block;
        }
        .vwpm-search-result-item small {
            color: #666;
            display: block;
            margin-top: 3px;
        }
        .vwpm-supplier-selected-item {
            margin-top: 10px;
        }
        #vwpm_supplier_search:focus {
            outline: 2px solid #0073aa;
        }
    </style>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        let searchTimeout;
        const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        const nonce = '<?php echo wp_create_nonce('vwpm_nonce'); ?>';
        
        // Search suppliers with debounce
        $('#vwpm_supplier_search').on('keyup', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if (query.length < 2) {
                $('#vwpm_supplier_results').removeClass('active').empty();
                return;
            }
            
            // Show loading state
            $('#vwpm_supplier_results').html('<div class="vwpm-search-result-item">Searching...</div>').addClass('active');
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'vwpm_search_suppliers',
                        nonce: nonce,
                        query: query
                    },
                    success: function(response) {
                        if (response.success) {
                            const results = response.data;
                            let html = '';
                            
                            if (!results || results.length === 0) {
                                html = '<div class="vwpm-search-result-item">No suppliers found. Check the Suppliers page in Manufacturing menu to add suppliers.</div>';
                            } else {
                                results.forEach(supplier => {
                                    html += '<div class="vwpm-search-result-item" data-id="' + supplier.id + '" data-name="' + escapeHtml(supplier.name) + '">';
                                    html += '<strong>' + escapeHtml(supplier.name) + '</strong>';
                                    if (supplier.email) {
                                        html += '<small>' + escapeHtml(supplier.email) + '</small>';
                                    }
                                    html += '</div>';
                                });
                            }
                            
                            $('#vwpm_supplier_results').html(html).addClass('active');
                            
                            // Click handler for results
                            $('.vwpm-search-result-item').on('click', function() {
                                const id = $(this).data('id');
                                const name = $(this).data('name');
                                if (id && name) {
                                    selectSupplier(id, name);
                                }
                            });
                        } else {
                            $('#vwpm_supplier_results').html('<div class="vwpm-search-result-item">Error: ' + (response.data.message || 'Failed to load suppliers') + '</div>').addClass('active');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        $('#vwpm_supplier_results').html('<div class="vwpm-search-result-item">Error loading suppliers. Check browser console.</div>').addClass('active');
                    }
                });
            }, 300);
        });
        
        // Close results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#vwpm_supplier_search, #vwpm_supplier_results').length) {
                $('#vwpm_supplier_results').removeClass('active');
            }
        });
        
        // Select supplier
        function selectSupplier(id, name) {
            const itemHtml = '<div class="vwpm-supplier-selected-item" data-id="' + id + '" style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;">' +
                            '<span class="vwpm-supplier-name" style="font-weight: bold; flex: 1;">' + escapeHtml(name) + '</span>' +
                            '<button type="button" class="button button-small vwpm-remove-supplier" style="margin-left: 10px;">Remove</button>' +
                            '</div>' +
                            '<input type="hidden" name="vwpm_product_supplier_id" value="' + id + '">';
            
            $('#vwpm-supplier-item').html(itemHtml);
            $('#vwpm_supplier_search').val('').focus();
            $('#vwpm_supplier_results').removeClass('active').empty();
            
            // Attach remove handler
            attachRemoveHandler();
        }
        
        // Remove supplier
        function attachRemoveHandler() {
            $(document).off('click', '.vwpm-remove-supplier').on('click', '.vwpm-remove-supplier', function(e) {
                e.preventDefault();
                $('#vwpm-supplier-item').html('');
                $('input[name="vwpm_product_supplier_id"]').remove();
                $('#vwpm_supplier_search').val('').focus();
            });
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        attachRemoveHandler();
    });
    </script>
    <?php
}
    
    public function save_product_meta($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        if (!isset($_POST['vwpm_product_nonce']) || !wp_verify_nonce($_POST['vwpm_product_nonce'], 'vwpm_product_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save BOM
        if (isset($_POST['vwpm_bom'])) {
            $bom = array();
            foreach ($_POST['vwpm_bom'] as $item) {
                if (!empty($item['component_id'])) {
                    $bom[] = array(
                        'component_id' => intval($item['component_id']),
                        'quantity' => floatval($item['quantity'])
                    );
                }
            }
            update_post_meta($post_id, '_vwpm_bom', $bom);
        }
        
        // Save Tools
        if (isset($_POST['vwpm_tools'])) {
            $tools = array();
            foreach ($_POST['vwpm_tools'] as $tool_id) {
                if (!empty($tool_id)) {
                    $tools[] = intval($tool_id);
                }
            }
            update_post_meta($post_id, '_vwpm_tools', $tools);
        }
        
        // Save product supplier
        if (isset($_POST['vwpm_product_supplier_id'])) {
            update_post_meta($post_id, '_vwpm_product_supplier_id', intval($_POST['vwpm_product_supplier_id']));
        }
    }
    
    private function get_suppliers() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vwpm_suppliers';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    }
    
    public function render_dashboard() {
        include VWPM_PLUGIN_DIR . 'includes/admin/dashboard.php';
    }

    public function render_suppliers_page() {
        include VWPM_PLUGIN_DIR . 'includes/admin/suppliers.php';
    }

    public function render_production_page() {
        include VWPM_PLUGIN_DIR . 'includes/admin/production.php';
    }

    public function render_import_export_page() {
        include VWPM_PLUGIN_DIR . 'includes/admin/import-export.php';
    }

    public function render_purchase_orders_page() {
        include VWPM_PLUGIN_DIR . 'includes/admin/purchase-orders.php';
    }
    public function render_custom_po_page() {
        include VWPM_PLUGIN_DIR . 'includes/admin/custom-po.php';
    }
}
new VW_Parts_Manager();

// Admin list columns for Components & Tools
add_filter( 'manage_edit-vwpm_component_columns', 'vwpm_component_columns', 15 );
function vwpm_component_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        if ( 'title' === $key ) {
            $new['component_number'] = 'Component Number';
            $new['title'] = 'Component Name';
            $new['component_location'] = 'Location';
            $new['component_supplier_ref'] = 'Supplier Ref';
        } else {
            $new[ $key ] = $label;
        }
    }
    return $new;
}

add_action( 'manage_vwpm_component_posts_custom_column', 'vwpm_render_component_list_columns', 10, 2 );
function vwpm_render_component_list_columns( $column, $post_id ) {
    if ( 'component_number' === $column ) {
        $num = get_post_meta( $post_id, '_vwpm_component_number', true );
        echo $num ? esc_html( $num ) : '-';
    }
    if ( 'component_location' === $column ) {
        $loc = get_post_meta( $post_id, '_vwpm_component_location', true );
        echo $loc ? esc_html( $loc ) : '-';
    }
    if ( 'component_supplier_ref' === $column ) {
        $ref = get_post_meta( $post_id, '_vwpm_component_supplier_ref', true );
        echo $ref ? esc_html( $ref ) : '-';
    }
}

add_filter( 'manage_edit-vwpm_tool_columns', 'vwpm_tool_columns', 15 );
function vwpm_tool_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        if ( 'title' === $key ) {
            $new['tool_number'] = 'Tool Number';
            $new['title'] = 'Tool Name';
            $new['tool_location'] = 'Location';
        } else {
            $new[ $key ] = $label;
        }
    }
    return $new;
}

add_action( 'manage_vwpm_tool_posts_custom_column', 'vwpm_render_tool_list_columns', 10, 2 );
function vwpm_render_tool_list_columns( $column, $post_id ) {
    if ( 'tool_number' === $column ) {
        $num = get_post_meta( $post_id, '_vwpm_tool_number', true );
        echo $num ? esc_html( $num ) : '-';
    }
    if ( 'tool_location' === $column ) {
        $loc = get_post_meta( $post_id, '_vwpm_location', true );
        echo $loc ? esc_html( $loc ) : '-';
    }
}

add_action( 'admin_head', 'vwpm_admin_columns_css' );
function vwpm_admin_columns_css() {
    echo '<style>
        .wp-list-table .column-component_location, .wp-list-table .column-component_supplier_ref, .wp-list-table .column-tool_location { width: 140px; min-width: 120px; max-width: 260px; }
    </style>';
}

// PRINT PO HANDLER
add_action('admin_init', 'vwpm_handle_print_po');
function vwpm_handle_print_po() {
    if (!isset($_GET['vwpm_print_po']) || !current_user_can('manage_woocommerce')) {
        return;
    }

    $po_data = get_transient('vwpm_po_' . get_current_user_id());
    if ( ! $po_data || ! is_array( $po_data ) ) {
        wp_die('PO data expired or invalid. Please generate the PO again.');
    }

    $product_name = '';
    $quantity = '';
    if ( ! empty( $po_data['product_name'] ) ) {
        $product_name = $po_data['product_name'];
        $quantity = isset( $po_data['quantity'] ) ? $po_data['quantity'] : '';
    } elseif ( ! empty( $po_data['product_summary'] ) && is_array( $po_data['product_summary'] ) ) {
        $parts = array();
        $total_units = 0;
        foreach ( $po_data['product_summary'] as $p ) {
            $label = '';
            if ( isset( $p['title'] ) ) {
                $label = $p['title'];
            } elseif ( isset( $p['product_id'] ) ) {
                $post = get_post( intval( $p['product_id'] ) );
                $label = $post ? $post->post_title : 'Product #' . intval( $p['product_id'] );
            }
            $qty = isset( $p['quantity'] ) ? floatval( $p['quantity'] ) : 0;
            $parts[] = $label . ' x' . number_format( $qty, 2 );
            $total_units += $qty;
        }
        $product_name = implode( ', ', $parts );
        $quantity = $total_units;
    }

    $items = isset( $po_data['items'] ) && is_array( $po_data['items'] ) ? $po_data['items'] : array();
    $total_cost = isset( $po_data['total_cost'] ) ? floatval( $po_data['total_cost'] ) : 0;
    $type = isset( $po_data['type'] ) ? $po_data['type'] : 'manufactured';
    $supplier_name = $po_data['supplier_name'] ?? '';
    $supplier_email = $po_data['supplier_email'] ?? '';
    $po_number = $po_data['po_number'] ?? 'DRAFT-' . date('YmdHis');

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order <?php echo esc_html($po_number); ?></title>
      <style>
        @page { size: A4 landscape; margin: 15mm; }
        html, body { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; }
        .logo img { max-width: 220px; }
        .logo p { margin: 10px 0 0 0; line-height: 1.6; }
        .po-title { text-align: right; }
        .po-title h1 { margin: 0 0 10px 0; font-size: 28px; }
        .addresses { display: flex; justify-content: space-between; margin-top: 20px; }
        .address-box { width: 48%; border: 1px solid #ccc; padding: 8px; min-height: 100px; }
        .address-box strong { display: block; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background: #f2f2f2; font-weight: bold; }
        
        /* Column widths - optimized for landscape */
        table th:nth-child(1), table td:nth-child(1) { width: 30%; } /* Item */
        table th:nth-child(2), table td:nth-child(2) { width: 15%; } /* Part Number */
        table th:nth-child(3), table td:nth-child(3) { width: 15%; } /* Supplier Ref */
        table th:nth-child(4), table td:nth-child(4) { width: 8%; }  /* Qty Per Unit */
        table th:nth-child(5), table td:nth-child(5) { width: 8%; }  /* Total Qty */
        table th:nth-child(6), table td:nth-child(6) { width: 10%; } /* Unit Price */
        table th:nth-child(7), table td:nth-child(7) { width: 10%; } /* Line Total */
        
        /* For ready-made (no Qty Per Unit column) */
        table.no-qty-per-unit th:nth-child(1), table.no-qty-per-unit td:nth-child(1) { width: 35%; }
        table.no-qty-per-unit th:nth-child(2), table.no-qty-per-unit td:nth-child(2) { width: 20%; }
        table.no-qty-per-unit th:nth-child(3), table.no-qty-per-unit td:nth-child(3) { width: 20%; }
        table.no-qty-per-unit th:nth-child(4), table.no-qty-per-unit td:nth-child(4) { width: 8%; }
        table.no-qty-per-unit th:nth-child(5), table.no-qty-per-unit td:nth-child(5) { width: 10%; }
        table.no-qty-per-unit th:nth-child(6), table.no-qty-per-unit td:nth-child(6) { width: 10%; }
        
        .right { text-align: right; }
        .totals { width: 40%; float: right; margin-top: 20px; page-break-inside: avoid; }
        .totals table { margin: 0; table-layout: auto; }
        .print-bar { text-align: right; margin-bottom: 10px; }
        .print-bar button { background: #0073aa; color: #fff; padding: 10px 20px; border: none; cursor: pointer; font-size: 14px; }
        .footer { margin-top: 40px; font-size: 10px; clear: both; page-break-inside: avoid; }
        @media print { .print-bar { display: none; } }
    </style>
</head>
<body>
<div class="phoenix-po">

<div class="print-bar">
    <button onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="header">
    <div class="logo">
        <img src="https://stg-be925n.elementor.cloud/wp-content/uploads/2025/07/phoneix-logo-website-01-scaled.png" alt="Phoenix Restoration Parts">
        <p>
            Units 11, Springfield Farm<br>
            Nuneaton Road, Ansley, Nuneaton<br>
            Warwickshire CV10 0QU<br><br>
            VAT No: 491851758<br>
            Company No: 16305577
        </p>
    </div>

    <div class="po-title">
        <h1>PURCHASE ORDER</h1>
        <strong>PO Number:</strong> <?php echo esc_html($po_number); ?><br>
        <strong>PO Date:</strong> <?php echo date('d/m/Y H:i'); ?><br>
        <?php if ($product_name): ?>
            <strong>Product(s):</strong> <?php echo esc_html($product_name); ?><br>
        <?php endif; ?>
        <?php if ($quantity): ?>
            <strong>Quantity:</strong> <?php echo esc_html($quantity); ?><br>
        <?php endif; ?>
    </div>
</div>

<div class="addresses">
    <div class="address-box">
        <strong>Supplier Details</strong>
        <?php echo esc_html($supplier_name); ?><br>
        <?php if ($supplier_email): ?>
            <strong>Email:</strong> <?php echo esc_html($supplier_email); ?><br>
        <?php endif; ?>
    </div>

    <div class="address-box">
        <strong>Deliver To</strong>
        Phoenix Restoration Parts<br>
        Units 11, Springfield Farm<br>
        Nuneaton Road, Ansley<br>
        Nuneaton, Warwickshire<br>
        CV10 0QU
    </div>
</div>

<table<?php echo ($type === 'manufactured') ? '' : ' class="no-qty-per-unit"'; ?>>
    <thead>
        <tr>
            <th>Item</th>
            <th>Part Number</th>
            <th>Supplier Ref</th>
            <?php if ($type === 'manufactured'): ?>
                <th class="right">Qty Per Unit</th>
            <?php endif; ?>
            <th class="right">Total Qty</th>
            <th class="right">Unit Price</th>
            <th class="right">Line Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo esc_html($item['component_name'] ?? ''); ?></td>
                <td><?php echo esc_html($item['component_number'] ?? ''); ?></td>
                <td><?php echo esc_html($item['supplier_ref'] ?? '-'); ?></td>
                <?php if ($type === 'manufactured'): ?>
                    <td class="right"><?php echo number_format($item['qty_per_unit'] ?? 0, 2); ?></td>
                <?php endif; ?>
                <td class="right"><?php echo number_format($item['total_qty'] ?? 0, 2); ?></td>
                <td class="right">£<?php echo number_format($item['unit_price'] ?? 0, 2); ?></td>
                <td class="right">£<?php echo number_format($item['line_total'] ?? 0, 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="totals">
    <table>
        <?php
        $vat_enabled = isset($po_data['vat_enabled']) ? $po_data['vat_enabled'] : true;
        $subtotal = isset($po_data['subtotal']) ? floatval($po_data['subtotal']) : $total_cost;
        $vat_amount = isset($po_data['vat_amount']) ? floatval($po_data['vat_amount']) : 0;
        $grand_total = isset($po_data['grand_total']) ? floatval($po_data['grand_total']) : $total_cost;
        
        // If no VAT data stored, calculate from total_cost
        if (!isset($po_data['vat_enabled'])) {
            $vat_enabled = true;
            $subtotal = $total_cost;
            $vat_amount = $subtotal * 0.20;
            $grand_total = $subtotal + $vat_amount;
        }
        ?>
        <tr>
            <td>Subtotal (excl. VAT)</td>
            <td class="right">£<?php echo number_format($subtotal, 2); ?></td>
        </tr>
        <?php if ($vat_enabled): ?>
        <tr>
            <td>VAT (20%)</td>
            <td class="right">£<?php echo number_format($vat_amount, 2); ?></td>
        </tr>
        <?php else: ?>
        <tr>
            <td>VAT</td>
            <td class="right">£0.00 <em>(International)</em></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td><strong>Grand Total (inc. VAT)</strong></td>
            <td class="right"><strong>£<?php echo number_format($grand_total, 2); ?></strong></td>
        </tr>
    </table>
</div>

<?php if (!empty($po_data['notes'])): ?>
<div style="margin-top: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
    <strong>PO Notes:</strong><br>
    <?php echo nl2br(esc_html($po_data['notes'])); ?>
</div>
<?php endif; ?>
<?php if (!empty($po_data['tools'])): ?>
    <div style="clear:both; margin-top: 20px;">
        <h3>Tools Required for Production</h3>
        <table>
            <thead>
                <tr>
                    <th>Tool Name</th>
                    <th>Tool Number</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($po_data['tools'] as $tool): ?>
                    <tr>
                        <td><?php echo esc_html($tool['name'] ?? ''); ?></td>
                        <td><?php echo esc_html($tool['number'] ?? ''); ?></td>
                        <td><?php echo esc_html($tool['location'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="footer">
    <p>
        Sort Code 20-49-17 / Account No 90254517 / IBAN GB73 BUKB 2049 1790 2545 17 / SWIF BIC BUKBGB22<br>
        VAT No 491851758 / Company No 16305577
    </p>
</div>

</div>
</body>
</html>
    <?php
    exit;
}

// Include all AJAX handlers from separate file
require_once VWPM_PLUGIN_DIR . 'includes/ajax-handlers.php';
