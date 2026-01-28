<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get stats
$tools_count = wp_count_posts('vwpm_tool')->publish;
$components_count = wp_count_posts('vwpm_component')->publish;

global $wpdb;
$suppliers_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vwpm_suppliers");

// Get products with BOMs
$products_with_bom = $wpdb->get_var("
    SELECT COUNT(DISTINCT post_id) 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = '_vwpm_bom' 
    AND meta_value != ''
");

// Get products with suppliers (ready-made)
$ready_made_products = $wpdb->get_var("
    SELECT COUNT(DISTINCT post_id) 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = '_vwpm_product_supplier_id' 
    AND meta_value != '' 
    AND meta_value != '0'
");

?>

<div class="wrap">
    <h1>VW Parts Manufacturing Manager</h1>
    
    <div class="vwpm-admin-wrapper">
        
        <!-- Stats Overview -->
        <div class="vwpm-card">
            <h2>System Overview</h2>
            <div class="vwpm-stats-grid">
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Total Tools</span>
                    <span class="vwpm-stat-number"><?php echo $tools_count; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Total Components</span>
                    <span class="vwpm-stat-number"><?php echo $components_count; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Suppliers</span>
                    <span class="vwpm-stat-number"><?php echo $suppliers_count; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Products with BOM</span>
                    <span class="vwpm-stat-number"><?php echo $products_with_bom; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Ready-Made Products</span>
                    <span class="vwpm-stat-number"><?php echo $ready_made_products; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="vwpm-card">
            <h2>Quick Actions</h2>
            <p>
                <a href="<?php echo admin_url('post-new.php?post_type=vwpm_tool'); ?>" class="button button-primary">Add New Tool</a>
                <a href="<?php echo admin_url('post-new.php?post_type=vwpm_component'); ?>" class="button button-primary">Add New Component</a>
                <a href="<?php echo admin_url('admin.php?page=vwpm-suppliers'); ?>" class="button button-primary">Manage Suppliers</a>
                <a href="<?php echo admin_url('admin.php?page=vwpm-production'); ?>" class="button button-secondary" style="margin-left: 10px;">Production Calculator</a>
                <a href="<?php echo admin_url('admin.php?page=vwpm-import-export'); ?>" class="button button-secondary">Import/Export</a>
            </p>
        </div>
        
        <!-- Getting Started -->
        <div class="vwpm-card">
            <h2>Getting Started</h2>
            <ol>
                <li><strong>Add Suppliers:</strong> Go to Suppliers and add your component suppliers with their contact details.</li>
                <li><strong>Import or Add Tools:</strong> Add your manufacturing tools with their numbers and locations.</li>
                <li><strong>Import or Add Components:</strong> Add components (laser blanks, bolts, etc.) with supplier info and pricing.</li>
                <li><strong>Set Up Products:</strong> Edit your WooCommerce products to add:
                    <ul>
                        <li>Bill of Materials (components needed)</li>
                        <li>Tools required</li>
                        <li>Supplier (for ready-made products)</li>
                    </ul>
                </li>
                <li><strong>Use Production Calculator:</strong> Calculate component requirements and generate purchase orders.</li>
            </ol>
        </div>
        
        <!-- Recent Activity -->
        <div class="vwpm-card">
            <h2>Recent Components</h2>
            <?php
            $recent_components = get_posts(array(
                'post_type' => 'vwpm_component',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if ($recent_components): ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Component Number</th>
                            <th>Supplier</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_components as $component): 
                            $component_number = get_post_meta($component->ID, '_vwpm_component_number', true);
                            $supplier_id = get_post_meta($component->ID, '_vwpm_supplier_id', true);
                            $price = get_post_meta($component->ID, '_vwpm_price', true);
                            
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
                        ?>
                            <tr>
                                <td><?php echo esc_html($component->post_title); ?></td>
                                <td><?php echo esc_html($component_number); ?></td>
                                <td><?php echo esc_html($supplier_name); ?></td>
                                <td><?php echo $price ? '£' . number_format($price, 2) : '-'; ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($component->ID); ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No components added yet. <a href="<?php echo admin_url('post-new.php?post_type=vwpm_component'); ?>">Add your first component</a></p>
            <?php endif; ?>
        </div>
        
        <div class="vwpm-card">
            <h2>Recent Tools</h2>
            <?php
            $recent_tools = get_posts(array(
                'post_type' => 'vwpm_tool',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if ($recent_tools): ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Tool Name</th>
                            <th>Tool Number</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_tools as $tool): 
                            $tool_number = get_post_meta($tool->ID, '_vwpm_tool_number', true);
                            $location = get_post_meta($tool->ID, '_vwpm_location', true);
                        ?>
                            <tr>
                                <td><?php echo esc_html($tool->post_title); ?></td>
                                <td><?php echo esc_html($tool_number); ?></td>
                                <td><?php echo esc_html($location); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($tool->ID); ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No tools added yet. <a href="<?php echo admin_url('post-new.php?post_type=vwpm_tool'); ?>">Add your first tool</a></p>
            <?php endif; ?>
        </div>
        
    </div>
</div>
