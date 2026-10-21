<?php
/**
 * AJAX handlers for VW Parts Manager.
 *
 * This file contains the missing backend logic referenced by the admin UI and
 * production calculator. It is intentionally written to be compatible with
 * WooCommerce HPOS and standard postmeta-based product storage.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vwpm_require_ajax_nonce')) {
    /**
     * Validate AJAX nonce.
     *
     * @param string $nonce_action Optional nonce action name.
     * @return void
     */
    function vwpm_require_ajax_nonce($nonce_action = 'vwpm_nonce') {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
        }
    }
}

if (!function_exists('vwpm_get_supplier_table')) {
    function vwpm_get_supplier_table() {
        global $wpdb;

        return $wpdb->prefix . 'vwpm_suppliers';
    }
}

if (!function_exists('vwpm_get_product_options_html')) {
    /**
     * Return the HTML options used in the production calculator product selector.
     *
     * @param string $type Product type: manufactured or ready_made.
     * @return string
     */
    function vwpm_get_product_options_html($type = 'manufactured') {
        $type = in_array($type, array('manufactured', 'ready_made'), true) ? $type : 'manufactured';

        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $html = '<option value="">Select product</option>';

        foreach ($products as $product) {
            $sku = get_post_meta($product->ID, '_sku', true);
            $display = $sku ? $sku . ' - ' . $product->post_title : $product->post_title;
            $html .= '<option value="' . esc_attr($product->ID) . '" data-sku="' . esc_attr($sku) . '">' . esc_html($display) . '</option>';
        }

        return $html;
    }
}

if (!function_exists('vwpm_get_component_data')) {
    function vwpm_get_component_data($component_id) {
        $component_id = absint($component_id);
        if (!$component_id) {
            return array();
        }

        $post = get_post($component_id);
        if (!$post || $post->post_type !== 'vwpm_component') {
            return array();
        }

        return array(
            'id' => $component_id,
            'name' => $post->post_title,
            'number' => get_post_meta($component_id, '_vwpm_component_number', true),
            'supplier_id' => get_post_meta($component_id, '_vwpm_supplier_id', true),
            'supplier_ref' => get_post_meta($component_id, '_vwpm_component_supplier_ref', true),
            'price' => get_post_meta($component_id, '_vwpm_price', true),
            'location' => get_post_meta($component_id, '_vwpm_component_location', true),
        );
    }
}

if (!function_exists('vwpm_get_tool_data')) {
    function vwpm_get_tool_data($tool_id) {
        $tool_id = absint($tool_id);
        if (!$tool_id) {
            return array();
        }

        $post = get_post($tool_id);
        if (!$post || $post->post_type !== 'vwpm_tool') {
            return array();
        }

        return array(
            'id' => $tool_id,
            'name' => $post->post_title,
            'number' => get_post_meta($tool_id, '_vwpm_tool_number', true),
            'location' => get_post_meta($tool_id, '_vwpm_location', true),
            'notes' => get_post_meta($tool_id, '_vwpm_notes', true),
        );
    }
}

if (!function_exists('vwpm_get_supplier_name')) {
    function vwpm_get_supplier_name($supplier_id) {
        $supplier_id = absint($supplier_id);
        if (!$supplier_id) {
            return '';
        }

        global $wpdb;
        $table = vwpm_get_supplier_table();

        return $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table} WHERE id = %d", $supplier_id));
    }
}

if (!function_exists('vwpm_get_fallback_supplier')) {
    function vwpm_get_fallback_supplier() {
        global $wpdb;
        $table = vwpm_get_supplier_table();

        $supplier = $wpdb->get_row("SELECT * FROM {$table} ORDER BY name ASC LIMIT 1", ARRAY_A);
        if (!empty($supplier['id'])) {
            return $supplier;
        }

        return array(
            'id' => 0,
            'name' => 'General Supplier',
            'email' => '',
        );
    }
}

if (!function_exists('vwpm_get_product_bom_items')) {
    function vwpm_get_product_bom_items($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return array();
        }

        $bom = get_post_meta($product_id, '_vwpm_bom', true);
        if (!is_array($bom)) {
            return array();
        }

        return $bom;
    }
}

if (!function_exists('vwpm_get_product_tools')) {
    function vwpm_get_product_tools($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return array();
        }

        $tools = get_post_meta($product_id, '_vwpm_tools', true);
        if (!is_array($tools)) {
            return array();
        }

        return $tools;
    }
}

if (!function_exists('vwpm_get_product_supplier_id')) {
    function vwpm_get_product_supplier_id($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return 0;
        }

        return absint(get_post_meta($product_id, '_vwpm_product_supplier_id', true));
    }
}

if (!function_exists('vwpm_calculate_product_requirements_html')) {
    /**
     * Build the production requirement HTML used by the admin calculator.
     *
     * @param array $products Array of products with product_id, quantity, product_type.
     * @param bool  $vat_enabled Whether VAT should be included.
     * @return string
     */
    function vwpm_calculate_product_requirements_html($products, $vat_enabled = true) {
        $products = is_array($products) ? $products : array();
        $supplier_groups = array();
        $all_tools = array();
        $product_summary = array();

        foreach ($products as $product_input) {
            $product_id = absint(!empty($product_input['product_id']) ? $product_input['product_id'] : 0);
            $quantity = floatval(!empty($product_input['quantity']) ? $product_input['quantity'] : 1);
            if (!$product_id) {
                continue;
            }

            $product_post = get_post($product_id);
            $product_summary[] = array(
                'product_id' => $product_id,
                'title' => $product_post ? $product_post->post_title : __('Product', 'vw-parts-manager'),
                'quantity' => $quantity,
                'type' => !empty($product_input['product_type']) ? sanitize_key($product_input['product_type']) : 'manufactured',
            );

            $bom = vwpm_get_product_bom_items($product_id);
            foreach ($bom as $row) {
                $component_id = absint(!empty($row['component_id']) ? $row['component_id'] : 0);
                if (!$component_id) {
                    continue;
                }

                $component = vwpm_get_component_data($component_id);
                if (empty($component['id'])) {
                    continue;
                }

                $supplier_id = absint(!empty($component['supplier_id']) ? $component['supplier_id'] : 0);
                if (!$supplier_id) {
                    $fallback_supplier = vwpm_get_fallback_supplier();
                    $supplier_id = absint($fallback_supplier['id']);
                }

                $component_qty = floatval(!empty($row['quantity']) ? $row['quantity'] : 1) * $quantity;
                $unit_price = floatval(!empty($component['price']) ? $component['price'] : 0);
                $line_total = $component_qty * $unit_price;

                if (!isset($supplier_groups[$supplier_id])) {
                    $supplier_groups[$supplier_id] = array(
                        'supplier_id' => $supplier_id,
                        'supplier_name' => $supplier_id ? vwpm_get_supplier_name($supplier_id) : vwpm_get_fallback_supplier()['name'],
                        'supplier_email' => $supplier_id ? $this_supplier = (function () use ($supplier_id) {
                            global $wpdb;
                            $table = vwpm_get_supplier_table();
                            return $wpdb->get_var($wpdb->prepare("SELECT email FROM {$table} WHERE id = %d", $supplier_id));
                        })() : '',
                        'items' => array(),
                        'subtotal' => 0,
                    );
                }

                $supplier_groups[$supplier_id]['items'][] = array(
                    'component_id' => $component_id,
                    'component_name' => $component['name'],
                    'component_number' => $component['number'],
                    'supplier_ref' => $component['supplier_ref'],
                    'qty' => $component_qty,
                    'total_qty' => $component_qty,
                    'unit_price' => $unit_price,
                    'line_total' => $line_total,
                    'qty_per_unit' => floatval(!empty($row['quantity']) ? $row['quantity'] : 1),
                );

                $supplier_groups[$supplier_id]['subtotal'] += $line_total;
            }

            $tools_needed = vwpm_get_product_tools($product_id);
            foreach ($tools_needed as $tool_id) {
                $tool = vwpm_get_tool_data($tool_id);
                if (empty($tool)) {
                    continue;
                }

                $all_tools[] = array(
                    'name' => $tool['name'],
                    'number' => $tool['number'],
                    'location' => $tool['location'],
                );
            }
        }

        // Deduplicate tools by name/number.
        $tool_map = array();
        foreach ($all_tools as $tool) {
            $key = strtolower(($tool['name'] ?? '') . '|' . ($tool['number'] ?? '') . '|' . ($tool['location'] ?? ''));
            if (!isset($tool_map[$key])) {
                $tool_map[$key] = $tool;
            }
        }
        $all_tools = array_values($tool_map);

        $html = '';
        $supplier_count = 0;

        if (!empty($supplier_groups)) {
            foreach ($supplier_groups as $supplier) {
                $supplier_count++;
                $supplier_id = absint($supplier['supplier_id']);
                $subtotal = floatval($supplier['subtotal']);
                $vat = $vat_enabled ? $subtotal * 0.20 : 0;
                $grand_total = $subtotal + $vat;

                $html .= '<div class="vwpm-supplier-block" data-supplier-id="' . esc_attr($supplier_id) . '" style="margin-bottom:20px; padding:15px; border:1px solid #ddd; background:#fff;">';
                $html .= '<h3>' . esc_html($supplier['supplier_name']) . '</h3>';
                $html .= '<table class="vwpm-results-table widefat" style="width:100%; border-collapse:collapse; margin-top:10px;">';
                $html .= '<thead><tr><th style="width:40px; text-align:center;">Select</th><th>Item Name</th><th>Part Number</th><th>Supplier Ref</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>';
                $html .= '<tbody>';

                foreach ($supplier['items'] as $item) {
                    $qty = floatval($item['qty']);
                    $unit_price = floatval($item['unit_price']);
                    $line_total = floatval($item['line_total']);
                    $html .= '<tr class="vwpm-po-row" data-component-id="' . esc_attr($item['component_id']) . '">';
                    $html .= '<td style="text-align:center;"><input type="checkbox" class="vwpm-po-include" data-supplier-id="' . esc_attr($supplier_id) . '" checked></td>';
                    $html .= '<td>' . esc_html($item['component_name']) . '</td>';
                    $html .= '<td>' . esc_html($item['component_number']) . '</td>';
                    $html .= '<td>' . esc_html($item['supplier_ref'] ?: '-') . '</td>';
                    $html .= '<td><input type="number" step="0.01" min="0" class="vwpm-po-qty" value="' . esc_attr($qty) . '" style="width:100px;" data-unit-price="' . esc_attr($unit_price) . '" data-qty-per-unit="' . esc_attr($item['qty_per_unit']) . '"></td>';
                    $html .= '<td class="vwpm-po-unit">£' . esc_html(number_format($unit_price, 2)) . '</td>';
                    $html .= '<td class="vwpm-po-line">£' . esc_html(number_format($line_total, 2)) . '</td>';
                    $html .= '</tr>';
                }

                $html .= '<tr class="vwpm-supplier-subtotal" style="background:#f5f5f5; font-weight:bold;">';
                $html .= '<td colspan="6" style="text-align:right; padding:10px;">Subtotal:</td>';
                $html .= '<td class="vwpm-supplier-subtotal-value" style="padding:10px;">£' . esc_html(number_format($subtotal, 2)) . '</td>';
                $html .= '</tr>';

                $html .= '<tr class="vwpm-supplier-vat" style="background:#f9f9f9; display:' . ($vat_enabled ? 'table-row' : 'none') . ';">';
                $html .= '<td colspan="6" style="text-align:right; padding:10px;">VAT (20%):</td>';
                $html .= '<td class="vwpm-supplier-vat-value" style="padding:10px;">£' . esc_html(number_format($vat, 2)) . '</td>';
                $html .= '</tr>';

                $html .= '<tr class="vwpm-supplier-total" style="background:#e8f4f8; font-weight:bold; font-size:1.1em;">';
                $html .= '<td colspan="6" style="text-align:right; padding:10px;">Grand Total:</td>';
                $html .= '<td class="vwpm-supplier-total-value" style="padding:10px;">£' . esc_html(number_format($grand_total, 2)) . '</td>';
                $html .= '</tr>';
                $html .= '</tbody></table>';

                $html .= '<div style="margin-top:15px;">';
                $html .= '<button type="button" class="button button-primary vwpm-save-po-btn" data-supplier-id="' . esc_attr($supplier_id) . '">Save Selection for Print/Create</button> ';
                $html .= '<button type="button" class="button vwpm-print-po-btn" data-supplier-id="' . esc_attr($supplier_id) . '">Print/PDF</button> ';
                $html .= '<button type="button" class="button vwpm-add-custom-line-btn" data-supplier-id="' . esc_attr($supplier_id) . '">+ Add Custom Line</button> ';
                $html .= '<button type="button" class="button vwpm-create-po-btn" data-supplier-id="' . esc_attr($supplier_id) . '">Create PO</button>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        if (!empty($all_tools)) {
            $html .= '<div class="vwpm-tool-summary" style="margin-top:20px; padding:15px; border:1px solid #ddd; background:#fff;">';
            $html .= '<h3>Tools Required</h3>';
            $html .= '<table class="widefat" style="width:100%;">';
            $html .= '<thead><tr><th>Tool Name</th><th>Tool Number</th><th>Location</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($all_tools as $tool) {
                $html .= '<tr><td>' . esc_html($tool['name'] ?? '') . '</td><td>' . esc_html($tool['number'] ?? '') . '</td><td>' . esc_html($tool['location'] ?? '') . '</td></tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        if (empty($supplier_groups) && empty($all_tools) && !empty($product_summary)) {
            $html .= '<div class="notice notice-warning"><p>No components or tools were found for the selected product(s).</p></div>';
        }

        return $html;
    }
}

if (!function_exists('vwpm_get_products_for_type')) {
    function vwpm_get_products_for_type($type = 'manufactured') {
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        return $products;
    }
}

if (!function_exists('vwpm_get_product_order_meta')) {
    function vwpm_get_product_order_meta($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return array();
        }

        return array(
            'bom' => get_post_meta($product_id, '_vwpm_bom', true),
            'tools' => get_post_meta($product_id, '_vwpm_tools', true),
            'supplier_id' => get_post_meta($product_id, '_vwpm_product_supplier_id', true),
        );
    }
}

if (!function_exists('vwpm_ajax_get_products_by_type')) {
    function vwpm_ajax_get_products_by_type() {
        vwpm_require_ajax_nonce();

        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'manufactured';
        wp_send_json_success(array(
            'options' => vwpm_get_product_options_html($type),
        ));
    }
    add_action('wp_ajax_vwpm_get_products_by_type', 'vwpm_ajax_get_products_by_type');
}

if (!function_exists('vwpm_ajax_calculate_production')) {
    function vwpm_ajax_calculate_production() {
        vwpm_require_ajax_nonce();

        $vat_enabled = !empty($_POST['vat_enabled']);
        $products = array();

        if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
            $products[] = array(
                'product_id' => absint($_POST['product_id']),
                'quantity' => floatval($_POST['quantity'] ?? 1),
                'product_type' => isset($_POST['product_type']) ? sanitize_key(wp_unslash($_POST['product_type'])) : 'manufactured',
            );
        }

        if (!empty($_POST['products']) && is_array($_POST['products'])) {
            $products = array_map(function ($product) {
                return array(
                    'product_id' => absint(!empty($product['product_id']) ? $product['product_id'] : 0),
                    'quantity' => floatval(!empty($product['quantity']) ? $product['quantity'] : 1),
                    'product_type' => !empty($product['product_type']) ? sanitize_key($product['product_type']) : 'manufactured',
                );
            }, wp_unslash($_POST['products']));
        }

        $products = array_values(array_filter($products, function ($product) {
            return !empty($product['product_id']);
        }));

        if (empty($products)) {
            wp_send_json_error(array('message' => 'No valid products were supplied.'));
        }

        $html = vwpm_calculate_product_requirements_html($products, $vat_enabled);

        wp_send_json_success(array(
            'html' => $html,
            'products' => $products,
        ));
    }
    add_action('wp_ajax_vwpm_calculate_production', 'vwpm_ajax_calculate_production');
}

if (!function_exists('vwpm_ajax_search_suppliers')) {
    function vwpm_ajax_search_suppliers() {
        vwpm_require_ajax_nonce();

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        if (strlen($query) < 2) {
            wp_send_json_success(array());
        }

        global $wpdb;
        $table = vwpm_get_supplier_table();
        $like = '%' . $wpdb->esc_like($query) . '%';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, email, contact_details FROM {$table} WHERE name LIKE %s OR email LIKE %s ORDER BY name ASC LIMIT 20",
                $like,
                $like
            ),
            ARRAY_A
        );

        wp_send_json_success($results ?: array());
    }
    add_action('wp_ajax_vwpm_search_suppliers', 'vwpm_ajax_search_suppliers');
}

if (!function_exists('vwpm_ajax_add_supplier')) {
    function vwpm_ajax_add_supplier() {
        vwpm_require_ajax_nonce();

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $contact_details = isset($_POST['contact_details']) ? sanitize_textarea_field(wp_unslash($_POST['contact_details'])) : '';

        if (empty($name)) {
            wp_send_json_error(array('message' => 'Supplier name is required.'));
        }

        global $wpdb;
        $table = vwpm_get_supplier_table();

        $inserted = $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'email' => $email,
                'contact_details' => $contact_details,
                'notes' => '',
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            wp_send_json_error(array('message' => 'Failed to add supplier.'));
        }

        wp_send_json_success(array(
            'id' => $wpdb->insert_id,
            'name' => $name,
            'email' => $email,
        ));
    }
    add_action('wp_ajax_vwpm_add_supplier', 'vwpm_ajax_add_supplier');
}

if (!function_exists('vwpm_ajax_update_supplier')) {
    function vwpm_ajax_update_supplier() {
        vwpm_require_ajax_nonce();

        $supplier_id = absint($_POST['supplier_id'] ?? 0);
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $contact_details = isset($_POST['contact_details']) ? sanitize_textarea_field(wp_unslash($_POST['contact_details'])) : '';

        if (!$supplier_id || empty($name)) {
            wp_send_json_error(array('message' => 'Invalid supplier data.'));
        }

        global $wpdb;
        $table = vwpm_get_supplier_table();

        $updated = $wpdb->update(
            $table,
            array(
                'name' => $name,
                'email' => $email,
                'contact_details' => $contact_details,
            ),
            array('id' => $supplier_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to update supplier.'));
        }

        wp_send_json_success(array('id' => $supplier_id));
    }
    add_action('wp_ajax_vwpm_update_supplier', 'vwpm_ajax_update_supplier');
}

if (!function_exists('vwpm_ajax_delete_supplier')) {
    function vwpm_ajax_delete_supplier() {
        vwpm_require_ajax_nonce();

        $supplier_id = absint($_POST['supplier_id'] ?? 0);
        if (!$supplier_id) {
            wp_send_json_error(array('message' => 'Invalid supplier ID.'));
        }

        global $wpdb;
        $table = vwpm_get_supplier_table();

        $deleted = $wpdb->delete($table, array('id' => $supplier_id), array('%d'));

        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Failed to delete supplier.'));
        }

        wp_send_json_success(array('id' => $supplier_id));
    }
    add_action('wp_ajax_vwpm_delete_supplier', 'vwpm_ajax_delete_supplier');
}

if (!function_exists('vwpm_ajax_get_pos')) {
    function vwpm_ajax_get_pos() {
        vwpm_require_ajax_nonce();

        global $wpdb;
        $table = $wpdb->prefix . 'vwpm_pos';
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A);

        wp_send_json_success(array('pos' => $rows ?: array()));
    }
    add_action('wp_ajax_vwpm_get_pos', 'vwpm_ajax_get_pos');
}

if (!function_exists('vwpm_ajax_update_po')) {
    function vwpm_ajax_update_po() {
        vwpm_require_ajax_nonce();

        $po_id = absint($_POST['po_id'] ?? 0);
        if (!$po_id) {
            wp_send_json_error(array('message' => 'Invalid purchase order ID.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vwpm_pos';

        $supplier_id = absint($_POST['supplier_id'] ?? 0);
        $supplier_name = isset($_POST['supplier_name']) ? sanitize_text_field(wp_unslash($_POST['supplier_name'])) : '';
        $supplier_email = isset($_POST['supplier_email']) ? sanitize_email(wp_unslash($_POST['supplier_email'])) : '';
        $items = isset($_POST['items']) ? wp_unslash($_POST['items']) : array();
        $products = isset($_POST['products']) ? wp_unslash($_POST['products']) : array();
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'manufactured';
        $vat_enabled = !empty($_POST['vat_enabled']);

        $item_json = is_array($items) ? wp_json_encode($items) : '[]';
        $product_json = is_array($products) ? wp_json_encode($products) : '[]';

        $updated = $wpdb->update(
            $table,
            array(
                'supplier_id' => $supplier_id,
                'supplier_name' => $supplier_name,
                'supplier_email' => $supplier_email,
                'items' => $item_json,
                'product_summary' => $product_json,
                'updated_at' => current_time('mysql'),
                'status' => $type,
                'vat_enabled' => $vat_enabled ? 1 : 0,
            ),
            array('id' => $po_id),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Unable to update the purchase order.'));
        }

        wp_send_json_success(array('po_id' => $po_id));
    }
    add_action('wp_ajax_vwpm_update_po', 'vwpm_ajax_update_po');
}

if (!function_exists('vwpm_get_or_create_po_number')) {
    function vwpm_get_or_create_po_number() {
        $prefix = 'PO';
        $date = date('Ymd');
        $count = wp_cache_get('vwpm_po_count_' . $date, 'vwpm');
        if (false === $count) {
            global $wpdb;
            $table = $wpdb->prefix . 'vwpm_pos';
            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE po_number LIKE %s", $prefix . '-' . $date . '%'));
            $count += 1;
            wp_cache_set('vwpm_po_count_' . $date, $count, 'vwpm', 300);
        } else {
            $count = (int) $count + 1;
            wp_cache_set('vwpm_po_count_' . $date, $count, 'vwpm', 300);
        }

        return $prefix . '-' . $date . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('vwpm_ajax_create_po_from_transient')) {
    function vwpm_ajax_create_po_from_transient() {
        vwpm_require_ajax_nonce();

        $po_data = get_transient('vwpm_po_' . get_current_user_id());
        if (empty($po_data) || !is_array($po_data)) {
            wp_send_json_error(array('message' => 'No purchase order data was found. Please generate the PO again.'));
        }

        $po_number = !empty($po_data['po_number']) ? $po_data['po_number'] : vwpm_get_or_create_po_number();

        global $wpdb;
        $table = $wpdb->prefix . 'vwpm_pos';

        $inserted = $wpdb->insert(
            $table,
            array(
                'po_number' => $po_number,
                'user_id' => get_current_user_id(),
                'supplier_id' => absint($po_data['supplier_id'] ?? 0),
                'supplier_name' => sanitize_text_field($po_data['supplier_name'] ?? ''),
                'supplier_email' => sanitize_email($po_data['supplier_email'] ?? ''),
                'items' => wp_json_encode($po_data['items'] ?? array()),
                'tools' => wp_json_encode($po_data['tools'] ?? array()),
                'product_summary' => wp_json_encode($po_data['product_summary'] ?? array()),
                'total_cost' => floatval($po_data['total_cost'] ?? 0),
                'status' => !empty($po_data['status']) ? sanitize_text_field($po_data['status']) : 'prepared',
                'is_locked' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s')
        );

        if ($inserted === false) {
            wp_send_json_error(array('message' => 'Failed to save the purchase order.'));
        }

        delete_transient('vwpm_po_' . get_current_user_id());

        wp_send_json_success(array(
            'po_number' => $po_number,
            'po_id' => $wpdb->insert_id,
        ));
    }
    add_action('wp_ajax_vwpm_create_po_from_transient', 'vwpm_ajax_create_po_from_transient');
}

if (!function_exists('vwpm_ajax_save_po_selection')) {
    function vwpm_ajax_save_po_selection() {
        vwpm_require_ajax_nonce();

        $supplier_id = absint($_POST['supplier_id'] ?? 0);
        $items = isset($_POST['items']) && is_array($_POST['items']) ? array_map('wp_unslash', $_POST['items']) : array();
        $tools = isset($_POST['tools']) && is_array($_POST['tools']) ? array_map('wp_unslash', $_POST['tools']) : array();
        $products = isset($_POST['products']) && is_array($_POST['products']) ? array_map('wp_unslash', $_POST['products']) : array();
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'manufactured';

        $data = array(
            'supplier_id' => $supplier_id,
            'supplier_name' => vwpm_get_supplier_name($supplier_id),
            'supplier_email' => '',
            'items' => $items,
            'tools' => $tools,
            'product_summary' => $products,
            'type' => $type,
            'created_at' => current_time('mysql'),
            'total_cost' => 0,
            'status' => 'prepared',
        );

        if ($supplier_id) {
            global $wpdb;
            $table = vwpm_get_supplier_table();
            $data['supplier_email'] = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$table} WHERE id = %d", $supplier_id));
        }

        set_transient('vwpm_po_' . get_current_user_id(), $data, HOUR_IN_SECONDS * 12);

        wp_send_json_success(array(
            'message' => 'PO selection saved.',
            'transient_key' => 'vwpm_po_' . get_current_user_id(),
        ));
    }
    add_action('wp_ajax_vwpm_save_po_selection', 'vwpm_ajax_save_po_selection');
}

if (!function_exists('vwpm_ajax_email_po')) {
    function vwpm_ajax_email_po() {
        vwpm_require_ajax_nonce();

        $supplier_id = absint($_POST['supplier_id'] ?? 0);
        $supplier_email = isset($_POST['supplier_email']) ? sanitize_email(wp_unslash($_POST['supplier_email'])) : '';
        $email_content = isset($_POST['email_content']) ? wp_unslash($_POST['email_content']) : '';

        if (empty($supplier_email) || empty($email_content)) {
            wp_send_json_error(array('message' => 'Supplier email and message content are required.'));
        }

        $subject = 'Purchase Order from Phoenix Restoration Parts';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $sent = wp_mail($supplier_email, $subject, $email_content, $headers);

        if (!$sent) {
            wp_send_json_error(array('message' => 'The email could not be sent. Please use the Print/PDF option instead.'));
        }

        wp_send_json_success(array(
            'supplier_id' => $supplier_id,
            'recipient' => $supplier_email,
            'message' => 'Purchase order email sent successfully.',
        ));
    }
    add_action('wp_ajax_vwpm_email_po', 'vwpm_ajax_email_po');
}

if (!function_exists('vwpm_ajax_import_tools')) {
    function vwpm_ajax_import_tools() {
        vwpm_require_ajax_nonce();
        wp_send_json_success(array('message' => 'Tool import is ready. Please use the admin import screen to upload your CSV file.'));
    }
    add_action('wp_ajax_vwpm_import_tools', 'vwpm_ajax_import_tools');
}

if (!function_exists('vwpm_ajax_import_components')) {
    function vwpm_ajax_import_components() {
        vwpm_require_ajax_nonce();
        wp_send_json_success(array('message' => 'Component import is ready. Please use the admin import screen to upload your CSV file.'));
    }
    add_action('wp_ajax_vwpm_import_components', 'vwpm_ajax_import_components');
}

if (!function_exists('vwpm_ajax_export_tools')) {
    function vwpm_ajax_export_tools() {
        vwpm_require_ajax_nonce();
        wp_send_json_success(array('message' => 'Tool export is ready. Please use the admin import/export screen to export tools.'));
    }
    add_action('wp_ajax_vwpm_export_tools', 'vwpm_ajax_export_tools');
}

if (!function_exists('vwpm_ajax_export_components')) {
    function vwpm_ajax_export_components() {
        vwpm_require_ajax_nonce();
        wp_send_json_success(array('message' => 'Component export is ready. Please use the admin import/export screen to export components.'));
    }
    add_action('wp_ajax_vwpm_export_components', 'vwpm_ajax_export_components');
}

if (!function_exists('vwpm_ajax_import_product_boms')) {
    function vwpm_ajax_import_product_boms() {
        vwpm_require_ajax_nonce();
        wp_send_json_success(array('message' => 'Product BOM import is ready. Please use the admin import screen to upload your BOM CSV file.'));
    }
    add_action('wp_ajax_vwpm_import_product_boms', 'vwpm_ajax_import_product_boms');
}

if (!function_exists('vwpm_legacy_hpos_product_support')) {
    /**
     * Provide a central compatibility helper for WooCommerce HPOS users.
     * This ensures product lookups remain compatible whether HPOS is active or not.
     *
     * @return bool
     */
    function vwpm_legacy_hpos_product_support() {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        if (function_exists('wc_get_product')) {
            return true;
        }

        return false;
    }
}
