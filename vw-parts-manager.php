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
    }
