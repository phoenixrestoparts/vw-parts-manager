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
        add_action('add_meta_boxes', array($this, 'add_product_meta_boxes'));
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
            
            // Add inline script - use multiple triggers to ensure it runs
            $custom_js = "
jQuery(document).ready(function($) {
    console.log('VWPM: Document ready');
    var vwpmSelect2Initialized = false;
    
    function initializeSelect2() {
        console.log('VWPM: Attempting to initialize Select2');
        
        // Product Supplier dropdown (on product edit page)
        if ($('#vwpm_product_supplier').length) {
            $('#vwpm_product_supplier').not('.select2-hidden-accessible').each(function() {
                console.log('VWPM: Initializing product supplier dropdown');
                $(this).select2({
                    width: '100%',
                    placeholder: 'Search for supplier or select none...',
                    allowClear: true,
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        if (typeof data.text === 'undefined') {
                            return null;
                        }
                        var term = params.term.toLowerCase();
                        var text = data.text.toLowerCase();
                        
                        if (text.indexOf(term) > -1) {
    return data;
}
var name = $(data.element).text();
if (name && String(name).toLowerCase().indexOf(term) > -1) {
    return data;
}
return null;
                    }
                });
                vwpmSelect2Initialized = true;
            });
        }
        
        // Component dropdowns (in BOM meta box)
        if ($('.vwpm-component-select').length) {
            $('.vwpm-component-select').not('.select2-hidden-accessible').each(function() {
                $(this).select2({
                    width: '100%',
                    placeholder: 'Search for component...',
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        if (typeof data.text === 'undefined') {
                            return null;
                        }
                        var term = params.term.toLowerCase();
                        var text = data.text.toLowerCase();
                        var sku = $(data.element).data('sku');
                        
                        if (text.indexOf(term) > -1) {
                            return data;
                        }
                        if (sku && String(sku).toLowerCase().indexOf(term) > -1) {
                            return data;
                        }
                        return null;
                    }
                });
            });
        }
        
        // Tool dropdowns (in Required Tools meta box)
        if ($('.vwpm-tool-select').length) {
            $('.vwpm-tool-select').not('.select2-hidden-accessible').each(function() {
                $(this).select2({
                    width: '100%',
                    placeholder: 'Search for tool...',
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        if (typeof data.text === 'undefined') {
                            return null;
                        }
                        var term = params.term.toLowerCase();
                        var text = data.text.toLowerCase();
                        var toolNumber = $(data.element).data('number');
                        
                        if (text.indexOf(term) > -1) {
                            return data;
                        }
                        if (toolNumber && String(toolNumber).toLowerCase().indexOf(term) > -1) {
                            return data;
                        }
                        return null;
                    }
                });
            });
        }
    }
    
    // Try initialization multiple times with different triggers
    initializeSelect2(); // Immediate
    
    setTimeout(function() {
        if (!vwpmSelect2Initialized && $('#vwpm_product_supplier').length) {
            console.log('VWPM: Delayed init (100ms)');
            initializeSelect2();
        }
    }, 100);
    
    setTimeout(function() {
        if (!vwpmSelect2Initialized && $('#vwpm_product_supplier').length) {
            console.log('VWPM: Delayed init (500ms)');
            initializeSelect2();
        }
    }, 500);
    
    setTimeout(function() {
        if (!vwpmSelect2Initialized && $('#vwpm_product_supplier').length) {
            console.log('VWPM: Delayed init (1000ms)');
            initializeSelect2();
        }
    }, 1000);
    
    // Also try on window load
    $(window).on('load', function() {
        if (!vwpmSelect2Initialized && $('#vwpm_product_supplier').length) {
            console.log('VWPM: Window load event');
            initializeSelect2();
        }
    });
    
    // Re-initialize when adding new BOM rows
    $(document).on('click', '#vwpm-add-bom-row', function() {
        setTimeout(function() {
            $('.vwpm-component-select').not('.select2-hidden-accessible').each(function() {
                $(this).select2({
                    width: '100%',
                    placeholder: 'Search for component...',
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        if (typeof data.text === 'undefined') {
                            return null;
                        }
                        var term = params.term.toLowerCase();
                        var text = data.text.toLowerCase();
                        var sku = $(data.element).data('sku');
                        
                        if (text.indexOf(term) > -1) {
                            return data;
                        }
                        if (sku && String(sku).toLowerCase().indexOf(term) > -1) {
                            return data;
                        }
                        return null;
                    }
                });
            });
        }, 100);
    });
    
    // Re-initialize when adding new tool rows
    $(document).on('click', '#vwpm-add-tool-row', function() {
        setTimeout(function() {
            $('.vwpm-tool-select').not('.select2-hidden-accessible').each(function() {
                $(this).select2({
                    width: '100%',
                    placeholder: 'Search for tool...',
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        if (typeof data.text === 'undefined') {
                            return null;
                        }
                        var term = params.term.toLowerCase();
                        var text = data.text.toLowerCase();
                        var toolNumber = $(data.element).data('number');
                        
                        if (text.indexOf(term) > -1) {
                            return data;
                        }
                        if (toolNumber && String(toolNumber).toLowerCase().indexOf(term) > -1) {
                            return data;
                        }
                        return null;
                    }
                });
            });
        }, 100);
    });
});
";
            
            wp_add_inline_script('select2', $custom_js);
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
    e.stopImmediatePropagation(); // STOP EVENT BUBBLING
    
    var $btn = $(this);
    
    // Prevent double-clicking
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
    
    return false; // PREVENT DEFAULT
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
        $supplier_id = get_post_meta($post->ID, '_vwpm_product_supplier_id', true);
        $suppliers = $this->get_suppliers();
        ?>
        <p>
            <label for="vwpm_product_supplier">Supplier (for ready-made products):</label>
            <select id="vwpm_product_supplier" name="vwpm_product_supplier_id" style="width: 100%;">
                <option value="">None (manufactured in-house)</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo esc_attr($supplier->id); ?>" <?php selected($supplier_id, $supplier->id); ?>>
                        <?php echo esc_html($supplier->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">Select a supplier if this is a ready-made product you purchase complete.</p>
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

// AJAX: Calculate Production
function vwpm_ajax_calculate_production() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    global $wpdb;
    
        // Check if we're in merge mode (editing existing PO)
    $merge_mode = isset($_POST['merge_mode']) && $_POST['merge_mode'];
    $existing_items = isset($_POST['existing_items']) && is_array($_POST['existing_items']) ? $_POST['existing_items'] : array();

    $products = array();

    if ( ! empty( $_POST['products'] ) && is_array( $_POST['products'] ) ) {
        foreach ( $_POST['products'] as $p ) {
            $pid = intval( $p['product_id'] ?? 0 );
            $qty = floatval( $p['quantity'] ?? 0 );
            $type = sanitize_text_field( $p['product_type'] ?? 'manufactured' );
            if ( $pid && $qty ) {
                $products[] = array( 'product_id' => $pid, 'quantity' => $qty, 'type' => $type );
            }
        }
    } else {
        $product_type = sanitize_text_field( $_POST['product_type'] ?? 'manufactured' );
        $product_id = intval( $_POST['product_id'] ?? 0 );
        $quantity = floatval( $_POST['quantity'] ?? 0 );

        if ( $product_id && $quantity ) {
            $products[] = array( 'product_id' => $product_id, 'quantity' => $quantity, 'type' => $product_type );
        }
    }

    if ( empty( $products ) ) {
        wp_send_json_error( array( 'message' => 'No valid products provided' ) );
    }

    $requirements = array();
    $tools_union = array();
    $grand_total = 0;

    foreach ( $products as $product_row ) {
        $product = get_post( $product_row['product_id'] );
        if ( ! $product ) continue;

        $prod_type = $product_row['type'];
        $prod_qty  = $product_row['quantity'];

        if ( $prod_type === 'manufactured' ) {
            $bom = get_post_meta( $product->ID, '_vwpm_bom', true );
            if ( ! is_array( $bom ) ) $bom = array();

            $product_tools = get_post_meta( $product->ID, '_vwpm_tools', true );
            if ( is_array( $product_tools ) ) {
                foreach ( $product_tools as $tid ) {
                    $tools_union[ intval( $tid ) ] = true;
                }
            }

            foreach ( $bom as $bom_item ) {
                $component = get_post( intval( $bom_item['component_id'] ) );
                if ( ! $component ) continue;

                $component_id = intval( $component->ID );
                $component_number = get_post_meta( $component_id, '_vwpm_component_number', true );
                $supplier_id = intval( get_post_meta( $component_id, '_vwpm_supplier_id', true ) );
                $unit_price = floatval( get_post_meta( $component_id, '_vwpm_price', true ) );
                $qty_per_unit = floatval( $bom_item['quantity'] );
                $req_qty = $qty_per_unit * $prod_qty;
                $line_cost = $unit_price * $req_qty;
                $supplier_name = '';
                $supplier_email = '';

                if ( $supplier_id ) {
                    $supplier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d", $supplier_id ) );
                    if ( $supplier ) {
                        $supplier_name = $supplier->name;
                        $supplier_email = $supplier->email;
                    }
                }

                if ( ! isset( $requirements[ $supplier_id ] ) ) {
                    $requirements[ $supplier_id ] = array(
                        'supplier_id' => $supplier_id,
                        'supplier_name' => $supplier_name,
                        'supplier_email' => $supplier_email,
                        'items' => array()
                    );
                }

                if ( ! isset( $requirements[ $supplier_id ]['items'][ $component_id ] ) ) {
                    $requirements[ $supplier_id ]['items'][ $component_id ] = array(
                        'component_id'   => $component_id,
                        'component_name' => $component->post_title,
                        'component_number' => $component_number,
                        'qty_per_unit'   => $qty_per_unit,
                        'total_qty'      => 0,
                        'unit_price'     => $unit_price,
                        'line_total'     => 0,
                        'supplier_ref'   => get_post_meta( $component_id, '_vwpm_component_supplier_ref', true )
                    );
                }

                $requirements[ $supplier_id ]['items'][ $component_id ]['total_qty'] += $req_qty;
                $requirements[ $supplier_id ]['items'][ $component_id ]['line_total'] += $line_cost;

                $grand_total += $line_cost;
            }

        } else {
            $supplier_id = intval( get_post_meta( $product->ID, '_vwpm_product_supplier_id', true ) );
            if ( ! $supplier_id ) {
                continue;
            }

            $supplier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d", $supplier_id ) );
            if ( ! $supplier ) continue;

            $supplier_name = $supplier->name;
            $supplier_email = $supplier->email;
            $unit_price = floatval( get_post_meta( $product->ID, '_price', true ) );
            $line_total = $unit_price * $prod_qty;
            $component_number = get_post_meta( $product->ID, '_sku', true );

            if ( ! isset( $requirements[ $supplier_id ] ) ) {
                $requirements[ $supplier_id ] = array(
                    'supplier_id' => $supplier_id,
                    'supplier_name' => $supplier_name,
                    'supplier_email' => $supplier_email,
                    'items' => array()
                );
            }

            $synthetic_component_id = 'product_' . $product->ID;
            if ( ! isset( $requirements[ $supplier_id ]['items'][ $synthetic_component_id ] ) ) {
                $requirements[ $supplier_id ]['items'][ $synthetic_component_id ] = array(
                    'component_id'   => $synthetic_component_id,
                    'component_name' => $product->post_title,
                    'component_number' => $component_number,
                    'qty_per_unit'   => 1,
                    'total_qty'      => 0,
                    'unit_price'     => $unit_price,
                    'line_total'     => 0,
                    'supplier_ref'   => get_post_meta( $product->ID, '_vwpm_product_supplier_ref', true )
                );
            }

            $requirements[ $supplier_id ]['items'][ $synthetic_component_id ]['total_qty'] += $prod_qty;
            $requirements[ $supplier_id ]['items'][ $synthetic_component_id ]['line_total'] += $line_total;

            $grand_total += $line_total;
        }
    }

        // If in merge mode, add existing items back to requirements
    if ($merge_mode && !empty($existing_items)) {
        foreach ($existing_items as $existing) {
            // Determine which supplier this item belongs to
            $supplier_id = 0;
            
            // Try to match by component_id if it's a real component
            if (isset($existing['component_id']) && is_numeric($existing['component_id'])) {
                $component_id = intval($existing['component_id']);
                $supplier_id = intval(get_post_meta($component_id, '_vwpm_supplier_id', true));
            }
            
            // If no supplier found, add to first available or create new entry
            if (!$supplier_id && !empty($requirements)) {
                $supplier_id = array_key_first($requirements);
            }
            
            // Ensure this supplier exists in requirements
            if ($supplier_id && !isset($requirements[$supplier_id])) {
                $supplier = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d", $supplier_id));
                $requirements[$supplier_id] = array(
                    'supplier_id' => $supplier_id,
                    'supplier_name' => $supplier ? $supplier->name : 'Unknown',
                    'supplier_email' => $supplier ? $supplier->email : '',
                    'items' => array()
                );
            }
            
            if ($supplier_id) {
                $item_key = $existing['component_id'];
                
                // Check if this item already exists in new calculation (merge quantities)
                $found = false;
                foreach ($requirements[$supplier_id]['items'] as $key => $item) {
                    if ($item['component_id'] == $item_key) {
                        // Item exists - add quantities together
                        $requirements[$supplier_id]['items'][$key]['total_qty'] += floatval($existing['total_qty']);
                        $requirements[$supplier_id]['items'][$key]['line_total'] = 
                            $requirements[$supplier_id]['items'][$key]['total_qty'] * 
                            $requirements[$supplier_id]['items'][$key]['unit_price'];
                        $found = true;
                        break;
                    }
                }
                
                // If not found, add as new item
                if (!$found) {
                    $requirements[$supplier_id]['items'][$item_key] = array(
                        'component_id' => $existing['component_id'],
                        'component_name' => $existing['component_name'],
                        'component_number' => $existing['component_number'],
                        'qty_per_unit' => floatval($existing['qty_per_unit']),
                        'total_qty' => floatval($existing['total_qty']),
                        'unit_price' => floatval($existing['unit_price']),
                        'line_total' => floatval($existing['total_qty']) * floatval($existing['unit_price']),
                        'supplier_ref' => $existing['supplier_ref']
                    );
                }
            }
        }
        
        // Recalculate grand total
        $grand_total = 0;
        foreach ($requirements as $supplier_data) {
            foreach ($supplier_data['items'] as $item) {
                $grand_total += $item['line_total'];
            }
        }
    }

    foreach ( $requirements as &$sup ) {
        $sup['items'] = array_values ( $sup['items'] );
    }
    unset( $sup );

    $tools = array_keys( $tools_union );
    $html = vwpm_build_po_html_multi( $products, $requirements, $tools, $grand_total );

    wp_send_json_success( array( 'html' => $html ) );
}

function vwpm_build_po_html_multi( $products, $requirements, $tools, $grand_total ) {
    $html = '<div class="vwpm-calculator-results">';
    $html .= '<h2>Production Order Preview</h2>';

    $html .= '<h3>Selected Products</h3><ul>';
    foreach ( $products as $p ) {
        $prod = get_post( $p['product_id'] );
        if ( $prod ) {
            $html .= '<li>' . esc_html( $prod->post_title ) . ' — ' . number_format( $p['quantity'], 2 ) . ' units</li>';
        }
    }
    $html .= '</ul>';

    foreach ( $requirements as $supplier_id => $supplier_data ) {
        $html .= '<div class="vwpm-supplier-block" data-supplier-id="' . esc_attr( $supplier_id ) . '" style="margin-bottom:20px;padding:15px;border:1px solid #ddd;">';
        $html .= '<h4>Supplier: ' . esc_html( $supplier_data['supplier_name'] ) . '</h4>';
        if ( ! empty( $supplier_data['supplier_email'] ) ) {
            $html .= '<p><strong>Email:</strong> ' . esc_html( $supplier_data['supplier_email'] ) . '</p>';
        }

        $html .= '<table class="vwpm-results-table" style="width:100%;border-collapse:collapse;">';
        $html .= '<thead><tr>';
        $html .= '<th style="width:40px"></th>';
        $html .= '<th>Item</th><th>Part Number</th><th>Supplier Ref</th><th class="no-print" style="width:60px;">Notes</th><th style="width:120px">Qty</th><th style="width:110px">Unit Price</th><th style="width:110px">Line Total</th>';
        $html .= '</tr></thead><tbody>';

        $supplier_total = 0;
        foreach ( $supplier_data['items'] as $item ) {
            $item_id_attr = esc_attr( $item['component_id'] );
            $qty = floatval( $item['total_qty'] );
            $unit_price = floatval( $item['unit_price'] );
            $line_total = floatval( $item['line_total'] );

            $supplier_total += $line_total;

            // Get component notes if it's a real component (not a product)
            $notes = '';
            $has_notes = false;
            if ( is_numeric( $item['component_id'] ) ) {
                $notes = get_post_meta( intval( $item['component_id'] ), '_vwpm_notes', true );
                $has_notes = !empty( $notes );
            }

            $html .= '<tr data-component-id="' . $item_id_attr . '" class="vwpm-po-row">';
            $html .= '<td style="text-align:center;"><input type="checkbox" class="vwpm-po-include" data-supplier-id="' . esc_attr( $supplier_id ) . '" checked></td>';
            $html .= '<td>' . esc_html( $item['component_name'] ) . '</td>';
            $html .= '<td>' . esc_html( $item['component_number'] ) . '</td>';
            $html .= '<td>' . ( $item['supplier_ref'] ? esc_html( $item['supplier_ref'] ) : '&ndash;' ) . '</td>';
            
            // Notes icon cell
            $html .= '<td class="no-print" style="text-align:center;">';
            if ( $has_notes ) {
                $html .= '<button type="button" class="vwpm-notes-icon" data-notes="' . esc_attr( $notes ) . '" style="cursor:pointer; color:#dc3545; font-size:18px; background:none; border:none; padding:0;" title="Click to view notes" aria-label="View component notes">🔴<span style="font-size:12px;vertical-align:super;">(!)</span></button>';
            } else {
                $html .= '&ndash;';
            }
            $html .= '</td>';
            
            $html .= '<td><input type="number" step="1" min="0" class="vwpm-po-qty" value="' . round( $qty, 0, PHP_ROUND_HALF_UP ) . '" style="width:100px;" data-unit-price="' . esc_attr( $unit_price ) . '"></td>';
            $html .= '<td class="vwpm-po-unit">£' . number_format( $unit_price, 2 ) . '</td>';
            $html .= '<td class="vwpm-po-line">£' . number_format( $line_total, 2 ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr class="vwpm-supplier-total">';
        $html .= '<td colspan="7" style="text-align:right;"><strong>Supplier Total:</strong></td>';
        $html .= '<td class="vwpm-supplier-total-value">£' . number_format( $supplier_total, 2 ) . '</td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

                $html .= '<div style="margin-top:10px;">';
        $html .= '<button class="button button-primary vwpm-save-po-btn" data-supplier-id="' . esc_attr( $supplier_id ) . '">Save Selection for Print/Create</button> ';
        $html .= '<button class="button button-secondary vwpm-create-po-btn" data-supplier-id="' . esc_attr( $supplier_id ) . '" style="display:none;">Create PO (persist to database)</button> ';
        $html .= '<button class="button vwpm-print-po-btn" data-supplier-id="' . esc_attr( $supplier_id ) . '" style="display:none;">Print/PDF</button> ';
        $html .= '<button class="button vwpm-add-custom-line-btn" data-supplier-id="' . esc_attr( $supplier_id ) . '">+ Add Custom Line</button>';
        $html .= '</div>';

        $html .= '</div>';
    }

    if ( ! empty( $tools ) ) {
        $html .= '<h3>Tools Required</h3><table class="vwpm-results-table">';
        $html .= '<thead><tr><th>Tool Name</th><th>Tool Number</th><th>Location</th></tr></thead><tbody>';
        foreach ( $tools as $tool_id ) {
            $tool = get_post( $tool_id );
            if ( ! $tool ) continue;
            $html .= '<tr>';
            $html .= '<td>' . esc_html( $tool->post_title ) . '</td>';
            $html .= '<td>' . esc_html( get_post_meta( $tool_id, '_vwpm_tool_number', true ) ) . '</td>';
            $html .= '<td>' . esc_html( get_post_meta( $tool_id, '_vwpm_location', true ) ) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '<div style="margin-top:20px;font-size:16px;"><strong>Grand Total: £' . number_format( $grand_total, 2 ) . '</strong></div>';
    
    // Add CSS for print
    $html .= '<style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>';
    
    // Add JavaScript for notes popup modal
    $html .= '<script>
    jQuery(document).ready(function($) {
        $(document).on("click", ".vwpm-notes-icon", function() {
            var notes = $(this).data("notes");
            var modal = $("<div>").css({
                position: "fixed",
                inset: "0",
                background: "rgba(0,0,0,0.7)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 99999
            });
            
            var content = $("<div>").css({
                background: "#fff",
                padding: "20px",
                borderRadius: "8px",
                maxWidth: "600px",
                maxHeight: "80vh",
                overflow: "auto",
                boxShadow: "0 10px 40px rgba(0,0,0,0.3)"
            });
            
            content.append($("<h3>").css({marginTop: "0", color: "#dc3545"}).text("Component Notes"));
            content.append($("<div>").css({whiteSpace: "pre-wrap", margin: "15px 0"}).text(notes));
            content.append($("<button>").addClass("button").css({marginTop: "10px"}).text("Close"));
            
            modal.append(content);
            $("body").append(modal);
            
            modal.on("click", function(e) {
                if (e.target === this || $(e.target).hasClass("button")) {
                    modal.remove();
                }
            });
        });
    });
    </script>';
    
    $html .= '</div>';

    return $html;
}

// PO number generator
if (!function_exists('vwpm_generate_po_number')) {
    function vwpm_generate_po_number() {
        global $wpdb;
        $table_pos = $wpdb->prefix . 'vwpm_pos';
        $next_id = $wpdb->get_var( "SELECT COALESCE(MAX(id),0) + 1 FROM {$table_pos}" );
        $site = get_bloginfo('name');
        $sitecode = strtoupper( preg_replace('/[^A-Z]/', '', substr($site, 0, 2) ) );
        if ( empty($sitecode) ) $sitecode = 'PX';
        $seq = str_pad( $next_id, 4, '0', STR_PAD_LEFT );
        return 'PO-' . $sitecode . $seq;
    }
}

// Save PO selection into transient
add_action( 'wp_ajax_vwpm_save_po_selection', 'vwpm_ajax_save_po_selection' );
function vwpm_ajax_save_po_selection() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );

    $supplier_id = sanitize_text_field( $_POST['supplier_id'] ?? '' );
    $items_raw   = $_POST['items'] ?? array();
    $tools       = $_POST['tools'] ?? array();
    $products    = $_POST['products'] ?? array();
    $type        = sanitize_text_field( $_POST['type'] ?? 'manufactured' );

    if ( empty( $supplier_id ) || ! is_array( $items_raw ) ) {
        wp_send_json_error( array( 'message' => 'Invalid data' ) );
    }

    $po_items = array();
    $supplier_total = 0.0;
    foreach ( $items_raw as $it ) {
        $qty  = floatval( $it['qty'] ?? 0 );
        $unit = floatval( $it['unit_price'] ?? 0 );
        $line = $unit * $qty;

        $po_items[] = array(
            'component_name'   => sanitize_text_field( $it['component_name'] ?? '' ),
            'component_number' => sanitize_text_field( $it['component_number'] ?? '' ),
            'qty_per_unit'     => isset( $it['qty_per_unit'] ) ? floatval( $it['qty_per_unit'] ) : 1,
            'total_qty'        => $qty,
            'unit_price'       => $unit,
            'line_total'       => $line,
            'supplier_ref'     => sanitize_text_field( $it['supplier_ref'] ?? '' ),
            'component_id'     => isset( $it['component_id'] ) ? sanitize_text_field( $it['component_id'] ) : '',
        );

        $supplier_total += $line;
    }

    global $wpdb;
    $supplier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d", intval($supplier_id) ) );

       $vat_enabled = isset( $_POST['vat_enabled'] ) ? (bool) $_POST['vat_enabled'] : true;
    $subtotal = isset( $_POST['subtotal'] ) ? floatval( $_POST['subtotal'] ) : $supplier_total;
    $vat_amount = isset( $_POST['vat_amount'] ) ? floatval( $_POST['vat_amount'] ) : ($subtotal * 0.20);
    $grand_total = isset( $_POST['grand_total'] ) ? floatval( $_POST['grand_total'] ) : ($subtotal + $vat_amount);
    $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

    $po_data = array(
        'product_summary' => $products,
        'product_name'    => isset( $products[0]['title'] ) ? sanitize_text_field( $products[0]['title'] ) : '',
        'quantity'        => isset( $products[0]['quantity'] ) ? floatval( $products[0]['quantity'] ) : 0,
        'supplier_id'     => intval($supplier_id),
        'supplier_name'   => $supplier ? $supplier->name : '',
        'supplier_email'  => $supplier ? $supplier->email : '',
        'items'           => $po_items,
        'type'            => $type,
        'total_cost'      => $supplier_total,
        'tools'           => $tools,
        'vat_enabled'     => $vat_enabled,
        'subtotal'        => $subtotal,
        'vat_amount'      => $vat_amount,
        'grand_total'     => $grand_total,
        'notes'           => $notes,
    );

    set_transient( 'vwpm_po_' . get_current_user_id(), $po_data, HOUR_IN_SECONDS );

    wp_send_json_success( array( 'message' => 'PO saved' ) );
}

// Create PO from current user's transient
add_action( 'wp_ajax_vwpm_create_po_from_transient', 'vwpm_ajax_create_po_from_transient' );
function vwpm_ajax_create_po_from_transient() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );

    $user_id = get_current_user_id();
    $transient = get_transient( 'vwpm_po_' . $user_id );
    if ( empty( $transient ) || ! is_array( $transient ) ) {
        wp_send_json_error( array( 'message' => 'No saved PO found for current user. Save selection first.' ) );
    }

    global $wpdb;
    $table_pos = $wpdb->prefix . 'vwpm_pos';

    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_pos ) );
    if ( ! $exists ) {
        wp_send_json_error( array( 'message' => 'PO table not found. Run plugin activation to create DB table.' ) );
    }

    $supplier_id = intval( $transient['supplier_id'] ?? 0 );
    $supplier_name = sanitize_text_field( $transient['supplier_name'] ?? '' );
    $supplier_email = sanitize_email( $transient['supplier_email'] ?? '' );
    $items_json = wp_json_encode( $transient['items'] ?? array() );
    $tools_json = wp_json_encode( $transient['tools'] ?? array() );
    $product_summary_json = wp_json_encode( $transient['product_summary'] ?? array() );
    $total_cost = floatval( $transient['total_cost'] ?? 0 );

    $po_number = function_exists('vwpm_generate_po_number') ? vwpm_generate_po_number() : 'PO-' . strtoupper(substr(get_bloginfo('name'),0,2)) . str_pad(time()%10000,4,'0',STR_PAD_LEFT);

    $inserted = $wpdb->insert(
        $table_pos,
        array(
            'po_number' => $po_number,
            'user_id' => $user_id,
            'supplier_id' => $supplier_id,
            'supplier_name' => $supplier_name,
            'supplier_email' => $supplier_email,
            'items' => $items_json,
            'tools' => $tools_json,
            'product_summary' => $product_summary_json,
            'total_cost' => $total_cost,
            'status' => 'prepared',
            'is_locked' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ),
        array('%s','%d','%d','%s','%s','%s','%s','%s','%f','%s','%d','%s','%s')
    );

    if ( false === $inserted ) {
        wp_send_json_error( array( 'message' => 'Failed to create PO: ' . $wpdb->last_error ) );
    }

    wp_send_json_success( array( 'message' => 'PO created', 'po_number' => $po_number ) );
}

// Get POs (for admin list)
add_action( 'wp_ajax_vwpm_get_pos', 'vwpm_ajax_get_pos' );
function vwpm_ajax_get_pos() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    global $wpdb;
    $table_pos = $wpdb->prefix . 'vwpm_pos';

    $rows = $wpdb->get_results( "SELECT id, po_number, supplier_name, total_cost, status, is_locked, user_id, created_at, updated_at FROM {$table_pos} ORDER BY created_at DESC" );

    wp_send_json_success( array( 'pos' => $rows ) );
}

// Get single PO details by id
add_action( 'wp_ajax_vwpm_get_po', 'vwpm_ajax_get_po' );
function vwpm_ajax_get_po() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    $po_id = intval( $_POST['po_id'] ?? 0 );
    if ( ! $po_id ) {
        wp_send_json_error( array( 'message' => 'Invalid PO id' ) );
    }

    global $wpdb;
    $table_pos = $wpdb->prefix . 'vwpm_pos';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_pos} WHERE id = %d", $po_id ), ARRAY_A );

    if ( ! $row ) {
        wp_send_json_error( array( 'message' => 'PO not found' ) );
    }

    $row['items'] = isset( $row['items'] ) ? json_decode( $row['items'], true ) : array();
    if ( ! is_array( $row['items'] ) ) $row['items'] = array();

    $row['tools'] = isset( $row['tools'] ) ? json_decode( $row['tools'], true ) : array();
    if ( ! is_array( $row['tools'] ) ) $row['tools'] = array();

    $row['product_summary'] = isset( $row['product_summary'] ) ? json_decode( $row['product_summary'], true ) : array();
    if ( ! is_array( $row['product_summary'] ) ) $row['product_summary'] = array();

    wp_send_json_success( array( 'po' => $row ) );
}

// Update PO status / lock
add_action( 'wp_ajax_vwpm_update_po_status', 'vwpm_ajax_update_po_status' );
function vwpm_ajax_update_po_status() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    $po_id = intval( $_POST['po_id'] ?? 0 );
    $new_status = sanitize_text_field( $_POST['status'] ?? '' );
    $lock = isset( $_POST['lock'] ) ? intval( $_POST['lock'] ) : null;

    if ( ! $po_id || ! in_array( $new_status, array('prepared','ordered','received','complete','locked') ) ) {
        wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
    }

    global $wpdb;
    $table_pos = $wpdb->prefix . 'vwpm_pos';

    $data = array( 'status' => $new_status, 'updated_at' => current_time('mysql') );
    $format = array( '%s', '%s' );
    if ( null !== $lock ) {
        $data['is_locked'] = $lock ? 1 : 0;
        $format[] = '%d';
    }

    $updated = $wpdb->update( $table_pos, $data, array( 'id' => $po_id ), $format, array('%d') );

    if ( false === $updated ) {
        wp_send_json_error( array( 'message' => 'DB update failed: ' . $wpdb->last_error ) );
    }

    wp_send_json_success( array( 'message' => 'PO updated' ) );
}

// Save PO data to transient for printing
add_action( 'wp_ajax_vwpm_save_po_for_print', 'vwpm_ajax_save_po_for_print' );
function vwpm_ajax_save_po_for_print() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }
    
    $po_data = $_POST['po_data'] ?? array();
    
    if ( empty( $po_data ) ) {
        wp_send_json_error( array( 'message' => 'No PO data provided' ) );
    }
    
    // Convert the database PO format to print format
    $print_data = array(
        'product_summary' => isset($po_data['product_summary']) ? $po_data['product_summary'] : array(),
        'supplier_name' => $po_data['supplier_name'] ?? '',
        'supplier_email' => $po_data['supplier_email'] ?? '',
        'items' => isset($po_data['items']) ? $po_data['items'] : array(),
        'tools' => isset($po_data['tools']) ? $po_data['tools'] : array(),
        'total_cost' => $po_data['total_cost'] ?? 0,
        'type' => 'manufactured',
        'po_number' => $po_data['po_number'] ?? ''
    );
    
    set_transient( 'vwpm_po_' . get_current_user_id(), $print_data, HOUR_IN_SECONDS );
    
    wp_send_json_success( array( 'message' => 'PO prepared for printing' ) );
}

// Delete PO
add_action( 'wp_ajax_vwpm_delete_po', 'vwpm_ajax_delete_po' );
function vwpm_ajax_delete_po() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }
    
    $po_id = intval( $_POST['po_id'] ?? 0 );
    if ( ! $po_id ) {
        wp_send_json_error( array( 'message' => 'Invalid PO ID' ) );
    }
    
    global $wpdb;
    $table_pos = $wpdb->prefix . 'vwpm_pos';
    
    // Check if PO is locked
    $po = $wpdb->get_row( $wpdb->prepare( "SELECT is_locked FROM {$table_pos} WHERE id = %d", $po_id ) );
    if ( ! $po ) {
        wp_send_json_error( array( 'message' => 'PO not found' ) );
    }
    
    if ( intval( $po->is_locked ) === 1 ) {
        wp_send_json_error( array( 'message' => 'Cannot delete a locked PO. Unlock it first.' ) );
    }
    
    $deleted = $wpdb->delete( $table_pos, array( 'id' => $po_id ), array( '%d' ) );
    
    if ( false === $deleted ) {
        wp_send_json_error( array( 'message' => 'Failed to delete PO: ' . $wpdb->last_error ) );
    }
    
    wp_send_json_success( array( 'message' => 'PO deleted' ) );
}

// Prepare PO for editing
add_action( 'wp_ajax_vwpm_prepare_po_for_edit', 'vwpm_ajax_prepare_po_for_edit' );
function vwpm_ajax_prepare_po_for_edit() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }
    
    $po_id = intval( $_POST['po_id'] ?? 0 );
    if ( ! $po_id ) {
        wp_send_json_error( array( 'message' => 'Invalid PO ID' ) );
    }
    
    // Store the PO ID for the edit session
    set_transient( 'vwpm_edit_po_' . get_current_user_id(), $po_id, HOUR_IN_SECONDS );
    
    wp_send_json_success( array( 'message' => 'PO ready for edit', 'po_id' => $po_id ) );
}

// Update existing PO
add_action( 'wp_ajax_vwpm_update_po', 'vwpm_ajax_update_po' );
function vwpm_ajax_update_po() {
    check_ajax_referer( 'vwpm_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }
    
    $po_id = intval( $_POST['po_id'] ?? 0 );
    $supplier_id = intval( $_POST['supplier_id'] ?? 0 );
    $items_raw = $_POST['items'] ?? array();
    $tools = $_POST['tools'] ?? array();
    $products = $_POST['products'] ?? array();
    $type = sanitize_text_field( $_POST['type'] ?? 'manufactured' );
    
    if ( ! $po_id || ! $supplier_id || ! is_array( $items_raw ) ) {
        wp_send_json_error( array( 'message' => 'Invalid data' ) );
    }
    
    global $wpdb;
    $table_pos = $wpdb->prefix . 'vwpm_pos';
    
    // Check if PO exists
    $existing_po = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_pos} WHERE id = %d", $po_id ) );
    if ( ! $existing_po ) {
        wp_send_json_error( array( 'message' => 'PO not found' ) );
    }
    
    // Check if PO is locked
    if ( intval( $existing_po->is_locked ) === 1 ) {
        wp_send_json_error( array( 'message' => 'Cannot update a locked PO. Unlock it first.' ) );
    }
    
    // Process items
    $po_items = array();
    $total_cost = 0.0;
    foreach ( $items_raw as $it ) {
        $qty = floatval( $it['qty'] ?? 0 );
        $unit = floatval( $it['unit_price'] ?? 0 );
        $line = $unit * $qty;
        
        $po_items[] = array(
            'component_name'   => sanitize_text_field( $it['component_name'] ?? '' ),
            'component_number' => sanitize_text_field( $it['component_number'] ?? '' ),
            'qty_per_unit'     => isset( $it['qty_per_unit'] ) ? floatval( $it['qty_per_unit'] ) : 1,
            'total_qty'        => $qty,
            'unit_price'       => $unit,
            'line_total'       => $line,
            'supplier_ref'     => sanitize_text_field( $it['supplier_ref'] ?? '' ),
            'component_id'     => isset( $it['component_id'] ) ? sanitize_text_field( $it['component_id'] ) : '',
        );
        
        $total_cost += $line;
    }
    
    // Get supplier info
    $supplier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d", $supplier_id ) );
    
    $items_json = wp_json_encode( $po_items );
    $tools_json = wp_json_encode( $tools );
    $product_summary_json = wp_json_encode( $products );
    
    // Update PO
    $updated = $wpdb->update(
        $table_pos,
        array(
            'supplier_id' => $supplier_id,
            'supplier_name' => $supplier ? $supplier->name : '',
            'supplier_email' => $supplier ? $supplier->email : '',
            'items' => $items_json,
            'tools' => $tools_json,
            'product_summary' => $product_summary_json,
            'total_cost' => $total_cost,
            'updated_at' => current_time('mysql'),
        ),
        array( 'id' => $po_id ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s' ),
        array( '%d' )
    );
    
    if ( false === $updated ) {
        wp_send_json_error( array( 'message' => 'Failed to update PO: ' . $wpdb->last_error ) );
    }
    
    wp_send_json_success( array( 'message' => 'PO updated', 'po_id' => $po_id ) );
}

// AJAX: Import Tools from CSV
function vwpm_ajax_import_tools() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    if (!isset($_FILES['tools_csv'])) {
        wp_send_json_error(array('message' => 'No file uploaded'));
    }
    
    $file = $_FILES['tools_csv'];
    
    // Validate file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        wp_send_json_error(array('message' => 'Invalid file type. Please upload a CSV file.'));
    }
    
    // Validate MIME type
    $allowed_mime_types = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_mime_types)) {
        wp_send_json_error(array('message' => 'Invalid file type. Please upload a CSV file.'));
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    
    if (!$handle) {
        wp_send_json_error(array('message' => 'Could not read file'));
    }
    
    $headers = fgetcsv($handle);
    $imported = 0;
    
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 2) continue;
        
        $tool_name = sanitize_text_field($row[0]);
        $tool_number = sanitize_text_field($row[1]);
        $location = isset($row[2]) ? sanitize_text_field($row[2]) : '';
        $notes = isset($row[3]) ? sanitize_textarea_field($row[3]) : '';
        
        // Check if tool with this number already exists
        $existing = get_posts(array(
            'post_type' => 'vwpm_tool',
            'meta_query' => array(
                array(
                    'key' => '_vwpm_tool_number',
                    'value' => $tool_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        if (!empty($existing)) {
            // UPDATE existing tool
            $post_id = $existing[0];
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $tool_name
            ));
        } else {
            // CREATE new tool
            $post_id = wp_insert_post(array(
                'post_title' => $tool_name,
                'post_type' => 'vwpm_tool',
                'post_status' => 'publish'
            ));
        }
        
        if ($post_id) {
            update_post_meta($post_id, '_vwpm_tool_number', $tool_number);
            update_post_meta($post_id, '_vwpm_location', $location);
            update_post_meta($post_id, '_vwpm_notes', $notes);
            $imported++;
        }
    }
    
    fclose($handle);
    
    wp_send_json_success(array('imported' => $imported));
}

// AJAX: Import Components from CSV
function vwpm_ajax_import_components() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    if (!isset($_FILES['components_csv'])) {
        wp_send_json_error(array('message' => 'No file uploaded'));
    }
    
    $file = $_FILES['components_csv'];
    
    // Validate file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        wp_send_json_error(array('message' => 'Invalid file type. Please upload a CSV file.'));
    }
    
    // Validate MIME type
    $allowed_mime_types = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_mime_types)) {
        wp_send_json_error(array('message' => 'Invalid file type. Please upload a CSV file.'));
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    
    if (!$handle) {
        wp_send_json_error(array('message' => 'Could not read file'));
    }
    
    global $wpdb;
    $headers = fgetcsv($handle);
    $imported = 0;
    
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 2) continue;
        
        $component_name = sanitize_text_field($row[0]);
        $component_number = sanitize_text_field($row[1]);
        $location = isset($row[2]) ? sanitize_text_field($row[2]) : '';
        $supplier_name = isset($row[3]) ? sanitize_text_field($row[3]) : '';
        $price = isset($row[4]) ? floatval($row[4]) : 0;
        $notes = isset($row[5]) ? sanitize_textarea_field($row[5]) : '';
        
        // Find supplier ID
        $supplier_id = 0;
        if ($supplier_name) {
            $supplier = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}vwpm_suppliers WHERE name = %s",
                $supplier_name
            ));
            if ($supplier) {
                $supplier_id = $supplier->id;
            }
        }
        
        // Check if component with this number already exists
        $existing = get_posts(array(
            'post_type' => 'vwpm_component',
            'meta_query' => array(
                array(
                    'key' => '_vwpm_component_number',
                    'value' => $component_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        if (!empty($existing)) {
            // UPDATE existing component
            $post_id = $existing[0];
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $component_name
            ));
        } else {
            // CREATE new component
            $post_id = wp_insert_post(array(
                'post_title' => $component_name,
                'post_type' => 'vwpm_component',
                'post_status' => 'publish'
            ));
        }
        
        if ($post_id) {
            update_post_meta($post_id, '_vwpm_component_number', $component_number);
            update_post_meta($post_id, '_vwpm_component_location', $location);
            update_post_meta($post_id, '_vwpm_supplier_id', $supplier_id);
            update_post_meta($post_id, '_vwpm_price', $price);
            update_post_meta($post_id, '_vwpm_notes', $notes);
            
            // Store supplier ref for quick lookup
            if ($supplier_id) {
                $supplier_ref = $wpdb->get_var($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d",
                    $supplier_id
                ));
                update_post_meta($post_id, '_vwpm_component_supplier_ref', $supplier_ref);
            }
            
            $imported++;
        }
    }
    
    fclose($handle);
    
    wp_send_json_success(array('imported' => $imported));
}

// AJAX: Export Tools to CSV
function vwpm_ajax_export_tools() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    $tools = get_posts(array(
        'post_type' => 'vwpm_tool',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tools-export-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Tool Name', 'Tool Number', 'Location', 'Notes'));
    
    foreach ($tools as $tool) {
        fputcsv($output, array(
            $tool->post_title,
            get_post_meta($tool->ID, '_vwpm_tool_number', true),
            get_post_meta($tool->ID, '_vwpm_location', true),
            get_post_meta($tool->ID, '_vwpm_notes', true)
        ));
    }
    
    fclose($output);
    exit;
}

// AJAX: Export Components to CSV
function vwpm_ajax_export_components() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    global $wpdb;
    
    $components = get_posts(array(
        'post_type' => 'vwpm_component',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="components-export-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Component Name', 'Component Number', 'Location', 'Supplier Name', 'Price', 'Notes'));
    
    foreach ($components as $component) {
        $supplier_id = get_post_meta($component->ID, '_vwpm_supplier_id', true);
        $supplier_name = '';
        
        if ($supplier_id) {
            $supplier = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d",
                $supplier_id
            ));
            if ($supplier) {
                $supplier_name = $supplier->name;
            }
        }
        
        fputcsv($output, array(
            $component->post_title,
            get_post_meta($component->ID, '_vwpm_component_number', true),
            get_post_meta($component->ID, '_vwpm_component_location', true),
            $supplier_name,
            get_post_meta($component->ID, '_vwpm_price', true),
            get_post_meta($component->ID, '_vwpm_notes', true)
        ));
    }
    
    fclose($output);
    exit;
}

// AJAX: Import Product BOMs from CSV
function vwpm_ajax_import_product_boms() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    if (!isset($_FILES['product_boms_csv'])) {
        wp_send_json_error(array('message' => 'No file uploaded'));
    }
    
    $file = $_FILES['product_boms_csv'];
    
    // Validate file type and size
    $allowed_types = array('text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/vnd.ms-excel');
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_extension !== 'csv' || !in_array($file['type'], $allowed_types)) {
        wp_send_json_error(array('message' => 'Invalid file type. Please upload a CSV file.'));
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5242880) {
        wp_send_json_error(array('message' => 'File too large. Maximum size is 5MB.'));
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    
    if (!$handle) {
        wp_send_json_error(array('message' => 'Could not read file'));
    }
    
    global $wpdb;
    $headers = fgetcsv($handle);
    
    // Validate CSV headers
    $expected_headers = array('Product SKU', 'Component Number', 'Quantity', 'Tool Number', 'Supplier Name');
    if (!$headers || count($headers) < 2) {
        fclose($handle);
        wp_send_json_error(array('message' => 'Invalid CSV format. Missing headers.'));
    }
    
    // Normalize headers for comparison (trim whitespace)
    $headers = array_map('trim', $headers);
    $expected_normalized = array_map('trim', $expected_headers);
    
    // Check if headers match expected format (allow some flexibility)
    $headers_match = true;
    for ($i = 0; $i < count($expected_normalized); $i++) {
        if (isset($headers[$i]) && strcasecmp($headers[$i], $expected_normalized[$i]) !== 0) {
            $headers_match = false;
            break;
        }
    }
    
    if (!$headers_match && count($headers) >= 2) {
        // Log warning but continue - headers might be slightly different
        // This allows for some flexibility in CSV format
    }
    
    $components_processed = 0;
    $tools_processed = 0;
    $suppliers_processed = 0;
    $errors = array();
    
    // Track which products we've processed to avoid duplicate tool assignments
    $products_processed = array();
    $product_tools = array(); // Store tools per product to deduplicate
    $product_suppliers = array(); // Store suppliers per product
    
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 2) continue; // Need at least SKU and one other field
        
        $product_sku = sanitize_text_field($row[0]);
        $component_number = isset($row[1]) && !empty($row[1]) ? sanitize_text_field($row[1]) : '';
        $quantity = isset($row[2]) && !empty($row[2]) ? floatval($row[2]) : 1;
        $tool_number = isset($row[3]) && !empty($row[3]) ? sanitize_text_field($row[3]) : '';
        $supplier_name = isset($row[4]) && !empty($row[4]) ? sanitize_text_field($row[4]) : '';
        
        // Find product by SKU
        $product = wc_get_product_id_by_sku($product_sku);
        if (!$product) {
            $errors[] = "Product SKU '$product_sku' not found";
            continue;
        }
        
        // Process Component (add to BOM)
        if (!empty($component_number)) {
            // Find component post by component_number
            $component_posts = get_posts(array(
                'post_type' => 'vwpm_component',
                'meta_query' => array(
                    array(
                        'key' => '_vwpm_component_number',
                        'value' => $component_number,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));
            
            if (!empty($component_posts)) {
                $component_id = $component_posts[0];
                
                // Get existing BOM
                $bom = get_post_meta($product, '_vwpm_bom', true);
                if (!is_array($bom)) {
                    $bom = array();
                }
                
                // Check if component already exists in BOM
                $found = false;
                foreach ($bom as &$item) {
                    if ($item['component_id'] == $component_id) {
                        $item['quantity'] = $quantity; // Update quantity
                        $found = true;
                        break;
                    }
                }
                
                // Add new component if not found
                if (!$found) {
                    $bom[] = array(
                        'component_id' => $component_id,
                        'quantity' => $quantity
                    );
                }
                
                update_post_meta($product, '_vwpm_bom', $bom);
                $components_processed++;
            } else {
                $errors[] = "Component '$component_number' not found for product SKU '$product_sku'";
            }
        }
        
        // Process Tool (add to required tools)
        if (!empty($tool_number)) {
            // Initialize array for this product if needed
            if (!isset($product_tools[$product])) {
                $product_tools[$product] = array();
            }
            
            // Find tool post by tool_number
            $tool_posts = get_posts(array(
                'post_type' => 'vwpm_tool',
                'meta_query' => array(
                    array(
                        'key' => '_vwpm_tool_number',
                        'value' => $tool_number,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));
            
            if (!empty($tool_posts)) {
                $tool_id = $tool_posts[0];
                // Store tool ID (will deduplicate later)
                $product_tools[$product][] = $tool_id;
            } else {
                $errors[] = "Tool '$tool_number' not found for product SKU '$product_sku'";
            }
        }
        
        // Process Supplier
        if (!empty($supplier_name)) {
            // Find supplier by name
            $supplier = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}vwpm_suppliers WHERE name = %s",
                $supplier_name
            ));
            
            if ($supplier) {
                $product_suppliers[$product] = $supplier->id;
            } else {
                $errors[] = "Supplier '$supplier_name' not found for product SKU '$product_sku'";
            }
        }
    }
    
    // Now save deduplicated tools for each product
    foreach ($product_tools as $product_id => $tool_ids) {
        $unique_tools = array_unique($tool_ids);
        update_post_meta($product_id, '_vwpm_tools', $unique_tools);
        $tools_processed += count($unique_tools);
    }
    
    // Save suppliers for each product
    foreach ($product_suppliers as $product_id => $supplier_id) {
        update_post_meta($product_id, '_vwpm_product_supplier_id', $supplier_id);
        $suppliers_processed++;
    }
    
    fclose($handle);
    
    // Build detailed success message
    $message_parts = array();
    if ($components_processed > 0) {
        $message_parts[] = "$components_processed component(s)";
    }
    if ($tools_processed > 0) {
        $message_parts[] = "$tools_processed tool(s)";
    }
    if ($suppliers_processed > 0) {
        $message_parts[] = "$suppliers_processed supplier assignment(s)";
    }
    
    $message = "Successfully processed: " . implode(', ', $message_parts);
    if (empty($message_parts)) {
        $message = "No items were processed.";
    }
    
    if (!empty($errors)) {
        $message .= " Errors: " . implode(', ', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $message .= " (and " . (count($errors) - 5) . " more)";
        }
    }
    
    wp_send_json_success(array(
        'components_processed' => $components_processed,
        'tools_processed' => $tools_processed,
        'suppliers_processed' => $suppliers_processed,
        'errors' => $errors,
        'message' => $message
    ));
}
