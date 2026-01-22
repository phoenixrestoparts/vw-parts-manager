<?php
/**
 * Plugin Name: VW Parts Manufacturing Manager
 * Description: Manage components, tools, BOMs, and purchase orders for VW parts manufacturing
 * Version: 1.0.1
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
        
        // AJAX handlers
        add_action('wp_ajax_vwpm_calculate_production', 'vwpm_ajax_calculate_production');
        add_action('wp_ajax_vwpm_email_po', 'vwpm_ajax_email_po');
        add_action('wp_ajax_vwpm_import_tools', 'vwpm_ajax_import_tools');
        add_action('wp_ajax_vwpm_import_components', 'vwpm_ajax_import_components');
        add_action('wp_ajax_vwpm_export_tools', 'vwpm_ajax_export_tools');
        add_action('wp_ajax_vwpm_export_components', 'vwpm_ajax_export_components');
        add_action('wp_ajax_vwpm_add_supplier', 'vwpm_ajax_add_supplier');
        add_action('wp_ajax_vwpm_update_supplier', 'vwpm_ajax_update_supplier');
        add_action('wp_ajax_vwpm_delete_supplier', 'vwpm_ajax_delete_supplier');
    }
    
    public function activate() {
        // Create custom tables if needed
        $this->create_tables();
        
        // Flush rewrite rules
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

    // Ensure notes column exists (safe for existing installs)
    $column = $wpdb->get_results( $wpdb->prepare(
        "SHOW COLUMNS FROM {$table_name} LIKE %s",
        'notes'
    ) );
    if ( empty( $column ) ) {
        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN notes text" );
    }
}
    
    public function init() {
        // Register custom post types
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
        
        // Add meta boxes for tools
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
        
        // Add meta boxes for components
        add_action('add_meta_boxes_vwpm_component', array($this, 'add_component_meta_boxes'));
        add_action('save_post_vwpm_component', array($this, 'save_component_meta'));
    }
    
    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            'VW Parts Manager',
            'VW Parts',
            'manage_options',
            'vw-parts-manager',
            array($this, 'render_dashboard'),
            'dashicons-hammer',
            30
        );
        
        // Submenu items
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
            'Import/Export',
            'Import/Export',
            'manage_options',
            'vwpm-import-export',
            array($this, 'render_import_export_page')
        );
    }
    
      public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages and product edit pages
        $screen = get_current_screen();
        
        if (strpos($hook, 'vw-parts-manager') === false && 
            strpos($hook, 'vwpm-') === false &&
            (!$screen || ($screen->post_type !== 'vwpm_tool' &&
            $screen->post_type !== 'vwpm_component' &&
            $screen->post_type !== 'product'))) {
            return;
        }
        
        // Enqueue Select2 for searchable dropdowns (from CDN)
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        
        // Force inline loading due to hosting environment issues
        add_action('admin_head', array($this, 'output_inline_css'));
        add_action('admin_footer', array($this, 'output_inline_js'));
    }
    
    public function output_inline_css() {
    $css_file = VWPM_PLUGIN_DIR . 'assets/css/admin.css';
    if (file_exists($css_file)) {
        $css_content = file_get_contents($css_file);
        if ($css_content !== false) {
            echo '<style type="text/css" id="vwpm-admin-styles">' . $css_content . '</style>';
        }
    }
}

public function output_inline_js() {
    ?>
    <script type="text/javascript">
    var vwpm_ajax = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('vwpm_nonce'); ?>'
    };
    
    jQuery(document).ready(function($) {
        // BOM row management
        var bomIndex = $('#vwpm-bom-rows tr').length;
        
        $('#vwpm-add-bom-row').on('click', function() {
            var template = $('#vwpm-bom-row-template').html();
            template = template.replace(/INDEX/g, bomIndex);
            $('#vwpm-bom-rows').append(template);
            $('.vwpm-component-select').select2();
            bomIndex++;
        });
        
        $(document).on('click', '.vwpm-remove-row', function() {
            $(this).closest('tr').remove();
        });
        
        // Tools row management
        var toolIndex = $('#vwpm-tools-rows tr').length;
        
        $('#vwpm-add-tool-row').on('click', function() {
            var template = $('#vwpm-tool-row-template').html();
            template = template.replace(/INDEX/g, toolIndex);
            $('#vwpm-tools-rows').append(template);
            $('.vwpm-tool-select').select2();
            toolIndex++;
        });
        
        $(document).on('click', '.vwpm-remove-tool-row', function() {
            $(this).closest('tr').remove();
        });
        
        // Initialize Select2
        $('.vwpm-component-select, .vwpm-tool-select').select2();
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

    // NEW fields
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

    // NEW: save component location and supplier ref
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

    // Handle file upload
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
    // Check if WooCommerce is active
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
        'side',
        'default'
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
                                    <input type="number" step="0.01" name="vwpm_bom[<?php echo $index; ?>][quantity]" value="<?php echo esc_attr($item['quantity']); ?>" class="regular-text">
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
                    <input type="number" step="0.01" name="vwpm_bom[INDEX][quantity]" value="1" class="regular-text">
                </td>
                <td>
                    <button type="button" class="button vwpm-remove-row">Remove</button>
                </td>
            </tr>
        </script>
        
        <style>
        #vwpm-bom-repeater select {
            max-width: 100%;
        }
        </style>
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
    
    
    
    // Helper function to get suppliers
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
}
new VW_Parts_Manager();

// ---- Admin list columns for Components & Tools (global scope - outside the class) ----

// Components: add Location and Supplier Ref columns in the admin list
add_filter( 'manage_edit-vwpm_component_columns', 'vwpm_component_columns', 15 );
function vwpm_component_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['component_location']     = 'Location';
            $new['component_supplier_ref'] = 'Supplier Ref';
        }
    }
    return $new;
}

add_action( 'manage_vwpm_component_posts_custom_column', 'vwpm_render_component_list_columns', 10, 2 );
function vwpm_render_component_list_columns( $column, $post_id ) {
    if ( 'component_location' === $column ) {
        $loc = get_post_meta( $post_id, '_vwpm_component_location', true );
        echo $loc ? esc_html( $loc ) : '-';
    }
    if ( 'component_supplier_ref' === $column ) {
        $ref = get_post_meta( $post_id, '_vwpm_component_supplier_ref', true );
        echo $ref ? esc_html( $ref ) : '-';
    }
}

// Tools: add Location column in the admin list
add_filter( 'manage_edit-vwpm_tool_columns', 'vwpm_tool_columns', 15 );
function vwpm_tool_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['tool_location'] = 'Location';
        }
    }
    return $new;
}

add_action( 'manage_vwpm_tool_posts_custom_column', 'vwpm_render_tool_list_columns', 10, 2 );
function vwpm_render_tool_list_columns( $column, $post_id ) {
    if ( 'tool_location' === $column ) {
        $loc = get_post_meta( $post_id, '_vwpm_location', true );
        echo $loc ? esc_html( $loc ) : '-';
    }
}

// Small admin CSS for these columns
add_action( 'admin_head', 'vwpm_admin_columns_css' );
function vwpm_admin_columns_css() {
    echo '<style>
        .wp-list-table .column-component_location, .wp-list-table .column-component_supplier_ref, .wp-list-table .column-tool_location { width: 140px; min-width: 120px; max-width: 260px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    </style>';
}

/* PRINT PO HANDLER */
add_action('admin_init', 'vwpm_handle_print_po');
function vwpm_handle_print_po() {
    if (!isset($_GET['vwpm_print_po']) || !current_user_can('manage_woocommerce')) {
        return;
    }
    
    $po_data = get_transient('vwpm_po_' . get_current_user_id());
    if (!$po_data) {
        wp_die('PO data expired. Please generate the PO again.');
    }
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Purchase Order</title>
        <style>
            @page { size: A4; margin: 15mm; }
            body { font-family: Arial, sans-serif; font-size: 12px; color: #000; margin: 0; padding: 20px; }
            h1 { text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background: #f2f2f2; font-weight: bold; }
            .text-right { text-align: right; }
            .totals { font-weight: bold; background: #f9f9f9; }
            .print-btn { background: #0073aa; color: #fff; padding: 10px 20px; border: none; cursor: pointer; margin-bottom: 20px; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()">PRINT / SAVE AS PDF</button>
        
        <h1>Purchase Order</h1>
        
        <p><strong>Product:</strong> <?php echo esc_html($po_data['product_name']); ?></p>
        <p><strong>Quantity:</strong> <?php echo esc_html($po_data['quantity']); ?> units</p>
        <p><strong>Date:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        
        <h2>Supplier: <?php echo esc_html($po_data['supplier_name']); ?></h2>
        <?php if (!empty($po_data['supplier_email'])): ?>
            <p><strong>Email:</strong> <?php echo esc_html($po_data['supplier_email']); ?></p>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Part Number</th>
                    <?php if ($po_data['type'] === 'manufactured'): ?>
                        <th>Qty Per Unit</th>
                    <?php endif; ?>
                    <th>Total Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($po_data['items'] as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['component_name']); ?></td>
                        <td><?php echo esc_html($item['component_number']); ?></td>
                        <?php if ($po_data['type'] === 'manufactured'): ?>
                            <td><?php echo number_format($item['qty_per_unit'], 2); ?></td>
                        <?php endif; ?>
                        <td><?php echo number_format($item['total_qty'], 2); ?></td>
                        <td class="text-right">£<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-right">£<?php echo number_format($item['line_total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="totals">
                    <td colspan="<?php echo $po_data['type'] === 'manufactured' ? '5' : '4'; ?>" class="text-right">
                        <strong>Total:</strong>
                    </td>
                    <td class="text-right">
                        <strong>£<?php echo number_format($po_data['total_cost'], 2); ?></strong>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php if (!empty($po_data['tools'])): ?>
            <h2>Tools Required</h2>
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
                            <td><?php echo esc_html($tool['name']); ?></td>
                            <td><?php echo esc_html($tool['number']); ?></td>
                            <td><?php echo esc_html($tool['location']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
    </body>
    </html>
    <?php
    exit;
}



// AJAX Handlers
add_action('wp_ajax_vwpm_calculate_production', 'vwpm_ajax_calculate_production');
add_action('wp_ajax_vwpm_email_po', 'vwpm_ajax_email_po');
add_action('wp_ajax_vwpm_import_tools', 'vwpm_ajax_import_tools');
add_action('wp_ajax_vwpm_import_components', 'vwpm_ajax_import_components');
add_action('wp_ajax_vwpm_export_tools', 'vwpm_ajax_export_tools');
add_action('wp_ajax_vwpm_export_components', 'vwpm_ajax_export_components');
add_action('wp_ajax_vwpm_add_supplier', 'vwpm_ajax_add_supplier');
add_action('wp_ajax_vwpm_update_supplier', 'vwpm_ajax_update_supplier');
add_action('wp_ajax_vwpm_delete_supplier', 'vwpm_ajax_delete_supplier');

function vwpm_ajax_calculate_production() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    $product_type = sanitize_text_field($_POST['product_type']);
    $product_id = intval($_POST['product_id']);
    $quantity = floatval($_POST['quantity']);
    
    if (!$product_id || !$quantity) {
        wp_send_json_error(array('message' => 'Invalid product or quantity'));
    }
    
    $product = get_post($product_id);
    if (!$product) {
        wp_send_json_error(array('message' => 'Product not found'));
    }
    
    global $wpdb;
    
    if ($product_type === 'manufactured') {
        // Get BOM
        $bom = get_post_meta($product_id, '_vwpm_bom', true);
        if (!is_array($bom) || empty($bom)) {
            wp_send_json_error(array('message' => 'This product has no Bill of Materials set up'));
        }
        
        // Get tools
        $tools = get_post_meta($product_id, '_vwpm_tools', true);
        if (!is_array($tools)) {
            $tools = array();
        }
        
        // Calculate requirements
        $requirements = array();
        $total_cost = 0;
        
        foreach ($bom as $item) {
            $component = get_post($item['component_id']);
            if (!$component) continue;
            
            $component_number = get_post_meta($component->ID, '_vwpm_component_number', true);
            $supplier_id = get_post_meta($component->ID, '_vwpm_supplier_id', true);
            $price = get_post_meta($component->ID, '_vwpm_price', true);
            
            $supplier_name = '';
            if ($supplier_id) {
                $supplier = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d",
                    $supplier_id
                ));
                if ($supplier) {
                    $supplier_name = $supplier->name;
                }
            }
            
            $qty_needed = $item['quantity'] * $quantity;
            $line_cost = $price * $qty_needed;
            $total_cost += $line_cost;
            
            if (!isset($requirements[$supplier_id])) {
                $requirements[$supplier_id] = array(
                    'supplier_name' => $supplier_name,
                    'supplier_id' => $supplier_id,
                    'supplier_email' => $supplier ? $supplier->email : '',
                    'items' => array()
                );
            }
            
            $component_supplier_ref = get_post_meta($component->ID, '_vwpm_component_supplier_ref', true);

$requirements[$supplier_id]['items'][] = array(
    'component_name'   => $component->post_title,
    'component_number' => $component_number,
    'qty_per_unit'     => $item['quantity'],
    'total_qty'        => $qty_needed,
    'unit_price'       => $price,
    'line_total'       => $line_cost,
    'supplier_ref'     => $component_supplier_ref ? $component_supplier_ref : ''
);
        }
        
        // Build HTML output
        $html = vwpm_build_po_html($product, $quantity, $requirements, $tools, 'manufactured', $total_cost);
        
    } else {
        // Ready-made products
        $supplier_id = get_post_meta($product_id, '_vwpm_product_supplier_id', true);
        if (!$supplier_id) {
            wp_send_json_error(array('message' => 'This product has no supplier assigned'));
        }
        
        $supplier = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d",
            $supplier_id
        ));
        
        if (!$supplier) {
            wp_send_json_error(array('message' => 'Supplier not found'));
        }
        
        $price = get_post_meta($product_id, '_price', true);
        $line_total = $price * $quantity;
        
        $product_supplier_ref = get_post_meta($product_id, '_vwpm_product_supplier_ref', true);

$requirements = array(
    $supplier_id => array(
        'supplier_name' => $supplier->name,
        'supplier_id'   => $supplier_id,
        'supplier_email'=> $supplier->email,
        'items' => array(
            array(
                'component_name'   => $product->post_title,
                'component_number' => get_post_meta($product_id, '_sku', true),
                'qty_per_unit'     => 1,
                'total_qty'        => $quantity,
                'unit_price'       => $price,
                'line_total'       => $line_total,
                'supplier_ref'     => $product_supplier_ref ? $product_supplier_ref : ''
            )
        )
    )
);
        
        $html = vwpm_build_po_html($product, $quantity, $requirements, array(), 'ready_made', $line_total);
    }
    
    wp_send_json_success(array('html' => $html));
}

function vwpm_build_po_html($product, $quantity, $requirements, $tools, $type, $total_cost) {
    $html = '<div class="vwpm-calculator-results">';
    $html .= '<h2>Production Order: ' . esc_html($product->post_title) . '</h2>';
    $html .= '<p><strong>Quantity:</strong> ' . number_format($quantity, 2) . ' units</p>';
    $html .= '<p><strong>Date:</strong> ' . date('d/m/Y H:i') . '</p>';

    if ($type === 'manufactured') {
        $html .= '<h3>Required Components</h3>';
    } else {
        $html .= '<h3>Products to Order</h3>';
    }

    $email_content = "Production Order: " . $product->post_title . "\n";
    $email_content .= "Quantity: " . number_format($quantity, 2) . " units\n";
    $email_content .= "Date: " . date('d/m/Y H:i') . "\n\n";

    $tools_data = array();
    if (!empty($tools)) {
        foreach ($tools as $tool_id) {
            $tool = get_post($tool_id);
            if ($tool) {
                $tools_data[] = array(
                    'name' => $tool->post_title,
                    'number' => get_post_meta($tool_id, '_vwpm_tool_number', true),
                    'location' => get_post_meta($tool_id, '_vwpm_location', true)
                );
            }
        }
    }

    foreach ($requirements as $supplier_data) {
        $html .= '<div style="margin-bottom: 30px; padding: 20px; border: 2px solid #0073aa;">';
        $html .= '<h4 style="margin-top: 0;">Supplier: ' . esc_html($supplier_data['supplier_name']) . '</h4>';

        if ($supplier_data['supplier_email']) {
            $html .= '<p><strong>Email:</strong> ' . esc_html($supplier_data['supplier_email']) . '</p>';
        }

        // Table header includes Supplier Ref column
        $html .= '<table class="vwpm-results-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Item</th><th>Part Number</th><th>Supplier Ref</th>';
        if ($type === 'manufactured') {
            $html .= '<th>Qty Per Unit</th>';
        }
        $html .= '<th>Total Qty</th><th>Unit Price</th><th>Total</th>';
        $html .= '</tr></thead><tbody>';

        $supplier_total = 0;
        foreach ($supplier_data['items'] as $item) {
            $supplier_ref = isset($item['supplier_ref']) ? $item['supplier_ref'] : '';
            $html .= '<tr>';
            $html .= '<td>' . esc_html($item['component_name']) . '</td>';
            $html .= '<td>' . esc_html($item['component_number']) . '</td>';
            $html .= '<td>' . ($supplier_ref ? esc_html($supplier_ref) : '-') . '</td>';
            if ($type === 'manufactured') {
                $html .= '<td>' . number_format($item['qty_per_unit'], 2) . '</td>';
            }
            $html .= '<td>' . number_format($item['total_qty'], 2) . '</td>';
            $html .= '<td>£' . number_format($item['unit_price'], 2) . '</td>';
            $html .= '<td>£' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';

            $supplier_total += $item['line_total'];
        }

        $html .= '<tr class="vwpm-results-total">';
        $html .= '<td colspan="' . ($type === 'manufactured' ? '6' : '5') . '" style="text-align: right;"><strong>Supplier Total:</strong></td>';
        $html .= '<td><strong>£' . number_format($supplier_total, 2) . '</strong></td>';
        $html .= '</tr></tbody></table>';

        // Store PO data for print page (include supplier_ref for each item)
        $po_items = array();
        foreach ($supplier_data['items'] as $item) {
            $po_items[] = array(
                'component_name'   => $item['component_name'],
                'component_number' => $item['component_number'],
                'qty_per_unit'     => isset($item['qty_per_unit']) ? $item['qty_per_unit'] : ($item['qty_per_unit'] ?? 1),
                'total_qty'        => $item['total_qty'],
                'unit_price'       => $item['unit_price'],
                'line_total'       => $item['line_total'],
                'supplier_ref'     => isset($item['supplier_ref']) ? $item['supplier_ref'] : ''
            );
        }

        $po_data = array(
            'product_name'   => $product->post_title,
            'quantity'       => $quantity,
            'supplier_name'  => $supplier_data['supplier_name'],
            'supplier_email' => $supplier_data['supplier_email'],
            'items'          => $po_items,
            'type'           => $type,
            'total_cost'     => $supplier_total,
            'tools'          => $tools_data
        );

        set_transient('vwpm_po_' . get_current_user_id(), $po_data, HOUR_IN_SECONDS);

        $email_content_b64 = base64_encode($email_content);
        $print_url = admin_url('admin.php?vwpm_print_po=1');

        $html .= '<div style="margin-top: 15px;">';
        $html .= '<a href="' . esc_url($print_url) . '" target="_blank" class="button button-primary">Print/Save as PDF</a> ';
        $html .= '<button class="button button-secondary vwpm-email-po-btn" data-supplier-id="' . $supplier_data['supplier_id'] . '" data-supplier-email="' . esc_attr($supplier_data['supplier_email']) . '" data-email-content="' . esc_attr($email_content_b64) . '">Email to Supplier</button>';
        $html .= '</div></div>';
    }

    if ($type === 'manufactured' && !empty($tools)) {
        $html .= '<h3>Tools Required</h3><table class="vwpm-results-table">';
        $html .= '<thead><tr><th>Tool Name</th><th>Tool Number</th><th>Location</th></tr></thead><tbody>';

        foreach ($tools as $tool_id) {
            $tool = get_post($tool_id);
            if (!$tool) continue;

            $html .= '<tr>';
            $html .= '<td>' . esc_html($tool->post_title) . '</td>';
            $html .= '<td>' . esc_html(get_post_meta($tool_id, '_vwpm_tool_number', true)) . '</td>';
            $html .= '<td>' . esc_html(get_post_meta($tool_id, '_vwpm_location', true)) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
    }

    $html .= '<div class="vwpm-results-total" style="font-size: 18px; margin-top: 20px; padding: 15px; background: #f0f0f0;">';
    $html .= '<strong>Total Estimated Cost: £' . number_format($total_cost, 2) . '</strong></div></div>';

    return $html;
}
function vwpm_ajax_email_po() {
    error_log('VWPM: EMAIL FUNCTION HIT');

    // Verify nonce
    check_ajax_referer('vwpm_nonce', 'nonce');

    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $supplier_email = sanitize_email($_POST['supplier_email'] ?? '');
    $email_content_b64 = $_POST['email_content'] ?? '';
    $email_content = base64_decode($email_content_b64);
    $email_content = wp_kses_post($email_content);

    if (!$supplier_email) {
        error_log('VWPM: No supplier email provided in AJAX request.');
        wp_send_json_error(array('message' => 'No supplier email provided'));
    }

    global $wpdb;
    $supplier = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d",
        $supplier_id
    ));

    if (!$supplier) {
        error_log('VWPM: Supplier not found for id: ' . $supplier_id);
        wp_send_json_error(array('message' => 'Supplier not found'));
    }

    $site_name = get_bloginfo('name');
    $subject = 'Purchase Order from ' . $site_name;

    // Build HTML email (same as before)
    $html_message = '<html><body style="font-family: Arial, sans-serif;">';
    $html_message .= '<h2>Purchase Order</h2>';
    $html_message .= '<p><strong>From:</strong> ' . esc_html($site_name) . '</p>';
    $html_message .= '<p><strong>Date:</strong> ' . date('d/m/Y H:i') . '</p>';
    $html_message .= '<hr>';
    $html_message .= '<pre style="font-family: monospace; background: #f5f5f5; padding: 15px; border: 1px solid #ddd;">';
    $html_message .= esc_html($email_content);
    $html_message .= '</pre>';
    $html_message .= '<hr>';
    $html_message .= '<p><em>This is an automated purchase order. Please confirm receipt.</em></p>';
    $html_message .= '</body></html>';

    // Email headers
    $from_email = get_option('admin_email');
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: "' . esc_attr($site_name) . '" <' . esc_attr($from_email) . '>'
    );

    // DEBUG LOGGING: record what we're about to send (don't log full HTML to public logs if sensitive)
    error_log('VWPM: Preparing email to: ' . $supplier_email);
    error_log('VWPM: Subject: ' . $subject);
    error_log('VWPM: From: ' . $from_email);
    error_log('VWPM: Headers: ' . print_r($headers, true));
    error_log('VWPM: Email content (text excerpt): ' . wp_trim_words(strip_tags($email_content), 40, '...'));

    // Attach a PHPMailer debug callback that writes to error_log
    add_action('phpmailer_init', function($phpmailer) {
        // Turn on debugging at runtime and route debug output to error_log
        $phpmailer->SMTPDebug = 2; // 0 = off, 2 = messages
        $phpmailer->Debugoutput = function($str, $level) {
            error_log('PHPMail Debug [' . $level . ']: ' . $str);
        };
    });

    // Attempt to send
      $sent = wp_mail($supplier_email, $subject, $html_message, $headers);

    error_log('VWPM: wp_mail returned: ' . ($sent ? 'true' : 'false'));

    if ($sent) {
        wp_send_json_success(array('message' => 'Email sent successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to send email. Check server mail logs and WP debug.log for PHPMailer output.'));
    }
}

function vwpm_ajax_import_tools() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    if (!isset($_FILES['tools_csv'])) {
        wp_send_json_error(array('message' => 'No file uploaded'));
    }
    
    $file = $_FILES['tools_csv'];
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
        
        $post_id = wp_insert_post(array(
            'post_title' => $tool_name,
            'post_type' => 'vwpm_tool',
            'post_status' => 'publish'
        ));
        
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

function vwpm_ajax_import_components() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    if (!isset($_FILES['components_csv'])) {
        wp_send_json_error(array('message' => 'No file uploaded'));
    }
    
    $file = $_FILES['components_csv'];
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
        $supplier_name = isset($row[2]) ? sanitize_text_field($row[2]) : '';
        $price = isset($row[3]) ? floatval($row[3]) : 0;
        $notes = isset($row[4]) ? sanitize_textarea_field($row[4]) : '';
        
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
        
        $post_id = wp_insert_post(array(
            'post_title' => $component_name,
            'post_type' => 'vwpm_component',
            'post_status' => 'publish'
        ));
        
        if ($post_id) {
            update_post_meta($post_id, '_vwpm_component_number', $component_number);
            update_post_meta($post_id, '_vwpm_supplier_id', $supplier_id);
            update_post_meta($post_id, '_vwpm_price', $price);
            update_post_meta($post_id, '_vwpm_notes', $notes);
            $imported++;
        }
    }
    
    fclose($handle);
    
    wp_send_json_success(array('imported' => $imported));
}

function vwpm_ajax_export_tools() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    $tools = get_posts(array(
        'post_type' => 'vwpm_tool',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tools-export.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Tool Name', 'Tool Number', 'Location', 'Notes'));
    
    foreach ($tools as $tool) {
        $tool_number = get_post_meta($tool->ID, '_vwpm_tool_number', true);
        $location = get_post_meta($tool->ID, '_vwpm_location', true);
        $notes = get_post_meta($tool->ID, '_vwpm_notes', true);
        
        fputcsv($output, array(
            $tool->post_title,
            $tool_number,
            $location,
            $notes
        ));
    }
    
    fclose($output);
    exit;
}

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
    header('Content-Disposition: attachment; filename="components-export.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Component Name', 'Component Number', 'Supplier Name', 'Price', 'Notes'));
    
    foreach ($components as $component) {
        $component_number = get_post_meta($component->ID, '_vwpm_component_number', true);
        $supplier_id = get_post_meta($component->ID, '_vwpm_supplier_id', true);
        $price = get_post_meta($component->ID, '_vwpm_price', true);
        $notes = get_post_meta($component->ID, '_vwpm_notes', true);
        
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
            $component_number,
            $supplier_name,
            $price,
            $notes
        ));
    }
    
    fclose($output);
    exit;
}

function vwpm_ajax_add_supplier() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vwpm_suppliers';
    
    $name = sanitize_text_field($_POST['supplier_name'] ?? '');
    $email = sanitize_email($_POST['supplier_email'] ?? '');
    $contact = sanitize_textarea_field($_POST['supplier_contact'] ?? '');
    $notes = sanitize_textarea_field($_POST['supplier_notes'] ?? '');
    
    $wpdb->insert(
        $table_name,
        array(
            'name' => $name,
            'email' => $email,
            'contact_details' => $contact,
            'notes' => $notes
        ),
        array('%s', '%s', '%s', '%s')
    );
    
    wp_send_json_success();
}

function vwpm_ajax_update_supplier() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vwpm_suppliers';
    
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $contact = sanitize_textarea_field($_POST['contact_details'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    $wpdb->update(
        $table_name,
        array(
            'name' => $name,
            'email' => $email,
            'contact_details' => $contact,
            'notes' => $notes
        ),
        array('id' => $supplier_id),
        array('%s', '%s', '%s', '%s'),
        array('%d')
    );
    
    wp_send_json_success();
}

function vwpm_ajax_delete_supplier() {
    check_ajax_referer('vwpm_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vwpm_suppliers';
    
    $supplier_id = intval($_POST['supplier_id']);
    
    $wpdb->delete($table_name, array('id' => $supplier_id), array('%d'));
    
    wp_send_json_success();
}

