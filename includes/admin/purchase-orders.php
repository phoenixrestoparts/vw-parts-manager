<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get PO statistics
global $wpdb;
$table_pos = $wpdb->prefix . 'vwpm_pos';

// Check if table exists
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_pos)) === $table_pos;

$total_pos = 0;
$prepared_count = 0;
$ordered_count = 0;
$received_count = 0;
$complete_count = 0;

if ($table_exists) {
    $total_pos = $wpdb->get_var("SELECT COUNT(*) FROM {$table_pos}");
    $prepared_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_pos} WHERE status = 'prepared'");
    $ordered_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_pos} WHERE status = 'ordered'");
    $received_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_pos} WHERE status = 'received'");
    $complete_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_pos} WHERE status = 'complete'");
}
?>

<div class="wrap">
    <h1>Purchase Orders</h1>
    
    <div class="vwpm-admin-wrapper">
        
        <?php if (!$table_exists): ?>
            <div class="notice notice-error">
                <p><strong>Error:</strong> Purchase Orders table not found. Please deactivate and reactivate the plugin to create the required database tables.</p>
            </div>
        <?php else: ?>
        
        <!-- Stats Overview -->
        <div class="vwpm-card">
            <h2>Purchase Order Overview</h2>
            <div class="vwpm-stats-grid">
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Total POs</span>
                    <span class="vwpm-stat-number"><?php echo $total_pos; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Prepared</span>
                    <span class="vwpm-stat-number"><?php echo $prepared_count; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Ordered</span>
                    <span class="vwpm-stat-number"><?php echo $ordered_count; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Received</span>
                    <span class="vwpm-stat-number"><?php echo $received_count; ?></span>
                </div>
                <div class="vwpm-stat-card">
                    <span class="vwpm-stat-label">Complete</span>
                    <span class="vwpm-stat-number"><?php echo $complete_count; ?></span>
                </div>
            </div>
        </div>
        
        <!-- PO List -->
        <div class="vwpm-card">
            <h2>Purchase Orders List</h2>
            <div id="vwpm-po-list-container">
                <p>Loading purchase orders...</p>
            </div>
        </div>
        
        <!-- PO Details Modal -->
        <div id="vwpm-po-details-modal" style="display:none;">
            <div class="vwpm-modal-overlay" onclick="vwpmClosePOModal()"></div>
            <div class="vwpm-modal-content">
                <div class="vwpm-modal-header">
                    <h2>Purchase Order Details</h2>
                    <button class="vwpm-modal-close" onclick="vwpmClosePOModal()">&times;</button>
                </div>
                <div class="vwpm-modal-body" id="vwpm-po-details-content">
                    <p>Loading...</p>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<style>
.vwpm-po-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.vwpm-po-table th,
.vwpm-po-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.vwpm-po-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.vwpm-po-table tr:hover {
    background-color: #f9f9f9;
}

.vwpm-po-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.vwpm-po-status.prepared {
    background-color: #fff3cd;
    color: #856404;
}

.vwpm-po-status.ordered {
    background-color: #d1ecf1;
    color: #0c5460;
}

.vwpm-po-status.received {
    background-color: #d4edda;
    color: #155724;
}

.vwpm-po-status.complete {
    background-color: #c3e6cb;
    color: #155724;
}

.vwpm-po-status.locked {
    background-color: #f8d7da;
    color: #721c24;
}

#vwpm-po-details-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
}

.vwpm-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.vwpm-modal-content {
    position: relative;
    width: 90%;
    max-width: 1000px;
    max-height: 90vh;
    margin: 30px auto;
    background-color: white;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.vwpm-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vwpm-modal-header h2 {
    margin: 0;
}

.vwpm-modal-close {
    background: none;
    border: none;
    font-size: 30px;
    cursor: pointer;
    color: #666;
}

.vwpm-modal-close:hover {
    color: #000;
}

.vwpm-modal-body {
    padding: 20px;
    overflow-y: auto;
}

.vwpm-po-detail-section {
    margin-bottom: 20px;
}

.vwpm-po-detail-section h3 {
    margin-top: 0;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 5px;
}

.vwpm-po-items-table {
    width: 100%;
    border-collapse: collapse;
}

.vwpm-po-items-table th,
.vwpm-po-items-table td {
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
}

.vwpm-po-items-table th {
    background-color: #f5f5f5;
}

.vwpm-po-detail-grid {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 10px;
    margin-bottom: 10px;
}

.vwpm-po-detail-label {
    font-weight: bold;
}

.vwpm-status-controls {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #ddd;
}

.vwpm-status-controls select,
.vwpm-status-controls button {
    margin-right: 10px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Load PO list on page load
    vwpmLoadPOList();
});

function vwpmLoadPOList() {
    jQuery.ajax({
        url: vwpm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'vwpm_get_pos',
            nonce: vwpm_ajax.nonce
        },
        success: function(response) {
            if (response.success && response.data.pos) {
                vwpmRenderPOList(response.data.pos);
            } else {
                jQuery('#vwpm-po-list-container').html('<p>Error loading purchase orders: ' + (response.data.message || 'Unknown error') + '</p>');
            }
        },
        error: function() {
            jQuery('#vwpm-po-list-container').html('<p>Error loading purchase orders. Please try again.</p>');
        }
    });
}

function vwpmRenderPOList(pos) {
    if (pos.length === 0) {
        jQuery('#vwpm-po-list-container').html('<p>No purchase orders found. Create your first PO from the Production page.</p>');
        return;
    }
    
    var html = '<table class="vwpm-po-table">';
    html += '<thead><tr>';
    html += '<th>PO Number</th>';
    html += '<th>Supplier</th>';
    html += '<th>Total Cost</th>';
    html += '<th>Status</th>';
    html += '<th>Created</th>';
    html += '<th>Actions</th>';
    html += '</tr></thead><tbody>';
    
    pos.forEach(function(po) {
        var statusClass = po.is_locked == 1 ? 'locked' : po.status;
        var statusText = po.is_locked == 1 ? 'Locked' : po.status.charAt(0).toUpperCase() + po.status.slice(1);
        
        html += '<tr>';
        html += '<td><strong>' + vwpmEscapeHtml(po.po_number) + '</strong></td>';
        html += '<td>' + vwpmEscapeHtml(po.supplier_name || 'N/A') + '</td>';
        html += '<td>£' + parseFloat(po.total_cost).toFixed(2) + '</td>';
        html += '<td><span class="vwpm-po-status ' + statusClass + '">' + statusText + '</span></td>';
        html += '<td>' + vwpmFormatDate(po.created_at) + '</td>';
        html += '<td><button class="button button-small" onclick="vwpmViewPO(' + po.id + ')">View Details</button></td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    
    jQuery('#vwpm-po-list-container').html(html);
}

function vwpmViewPO(poId) {
    jQuery('#vwpm-po-details-content').html('<p>Loading...</p>');
    jQuery('#vwpm-po-details-modal').show();
    
    jQuery.ajax({
        url: vwpm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'vwpm_get_po',
            nonce: vwpm_ajax.nonce,
            po_id: poId
        },
        success: function(response) {
            if (response.success && response.data.po) {
                vwpmRenderPODetails(response.data.po);
            } else {
                jQuery('#vwpm-po-details-content').html('<p>Error loading PO details: ' + (response.data.message || 'Unknown error') + '</p>');
            }
        },
        error: function() {
            jQuery('#vwpm-po-details-content').html('<p>Error loading PO details. Please try again.</p>');
        }
    });
}

function vwpmRenderPODetails(po) {
    var html = '';
    
    // PO Header
    html += '<div class="vwpm-po-detail-section">';
    html += '<h3>Purchase Order: ' + vwpmEscapeHtml(po.po_number) + '</h3>';
    html += '<div class="vwpm-po-detail-grid">';
    html += '<div class="vwpm-po-detail-label">Supplier:</div><div>' + vwpmEscapeHtml(po.supplier_name || 'N/A') + '</div>';
    html += '<div class="vwpm-po-detail-label">Supplier Email:</div><div>' + vwpmEscapeHtml(po.supplier_email || 'N/A') + '</div>';
    html += '<div class="vwpm-po-detail-label">Total Cost:</div><div>£' + parseFloat(po.total_cost).toFixed(2) + '</div>';
    html += '<div class="vwpm-po-detail-label">Status:</div><div><span class="vwpm-po-status ' + po.status + '">' + po.status.charAt(0).toUpperCase() + po.status.slice(1) + '</span></div>';
    html += '<div class="vwpm-po-detail-label">Locked:</div><div>' + (po.is_locked == 1 ? 'Yes' : 'No') + '</div>';
    html += '<div class="vwpm-po-detail-label">Created:</div><div>' + vwpmFormatDate(po.created_at) + '</div>';
    html += '<div class="vwpm-po-detail-label">Updated:</div><div>' + vwpmFormatDate(po.updated_at) + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Items
    if (po.items && po.items.length > 0) {
        html += '<div class="vwpm-po-detail-section">';
        html += '<h3>Items</h3>';
        html += '<table class="vwpm-po-items-table">';
        html += '<thead><tr>';
        html += '<th>Component Name</th>';
        html += '<th>Component Number</th>';
        html += '<th>Supplier Ref</th>';
        html += '<th>Qty</th>';
        html += '<th>Unit Price</th>';
        html += '<th>Line Total</th>';
        html += '</tr></thead><tbody>';
        
        po.items.forEach(function(item) {
            html += '<tr>';
            html += '<td>' + vwpmEscapeHtml(item.component_name || '') + '</td>';
            html += '<td>' + vwpmEscapeHtml(item.component_number || '') + '</td>';
            html += '<td>' + vwpmEscapeHtml(item.supplier_ref || '-') + '</td>';
            html += '<td>' + parseFloat(item.total_qty || 0).toFixed(2) + '</td>';
            html += '<td>£' + parseFloat(item.unit_price || 0).toFixed(2) + '</td>';
            html += '<td>£' + parseFloat(item.line_total || 0).toFixed(2) + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
    }
    
    // Tools
    if (po.tools && po.tools.length > 0) {
        html += '<div class="vwpm-po-detail-section">';
        html += '<h3>Tools Required</h3>';
        html += '<ul>';
        po.tools.forEach(function(toolId) {
            html += '<li>Tool ID: ' + vwpmEscapeHtml(toolId) + '</li>';
        });
        html += '</ul>';
        html += '</div>';
    }
    
    // Product Summary
    if (po.product_summary && po.product_summary.length > 0) {
        html += '<div class="vwpm-po-detail-section">';
        html += '<h3>Product Summary</h3>';
        html += '<table class="vwpm-po-items-table">';
        html += '<thead><tr><th>Product</th><th>Quantity</th></tr></thead><tbody>';
        
        po.product_summary.forEach(function(prod) {
            html += '<tr>';
            html += '<td>' + vwpmEscapeHtml(prod.title || prod.name || 'Unknown') + '</td>';
            html += '<td>' + parseFloat(prod.quantity || 0) + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
    }
    
    // Status Controls
    if (po.is_locked != 1) {
        html += '<div class="vwpm-status-controls">';
        html += '<h3>Update Status</h3>';
        html += '<select id="vwpm-po-new-status">';
        html += '<option value="prepared"' + (po.status === 'prepared' ? ' selected' : '') + '>Prepared</option>';
        html += '<option value="ordered"' + (po.status === 'ordered' ? ' selected' : '') + '>Ordered</option>';
        html += '<option value="received"' + (po.status === 'received' ? ' selected' : '') + '>Received</option>';
        html += '<option value="complete"' + (po.status === 'complete' ? ' selected' : '') + '>Complete</option>';
        html += '<option value="locked"' + (po.status === 'locked' ? ' selected' : '') + '>Locked</option>';
        html += '</select>';
        html += '<button class="button button-primary" onclick="vwpmUpdatePOStatus(' + po.id + ')">Update Status</button>';
        html += '<label style="margin-left: 20px;"><input type="checkbox" id="vwpm-po-lock-checkbox" ' + (po.is_locked == 1 ? 'checked' : '') + '> Lock PO</label>';
        html += '</div>';
    }
    
    jQuery('#vwpm-po-details-content').html(html);
}

function vwpmUpdatePOStatus(poId) {
    var newStatus = jQuery('#vwpm-po-new-status').val();
    var isLocked = jQuery('#vwpm-po-lock-checkbox').is(':checked') ? 1 : 0;
    
    jQuery.ajax({
        url: vwpm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'vwpm_update_po_status',
            nonce: vwpm_ajax.nonce,
            po_id: poId,
            status: newStatus,
            lock: isLocked
        },
        success: function(response) {
            if (response.success) {
                alert('PO updated successfully!');
                vwpmClosePOModal();
                vwpmLoadPOList();
            } else {
                alert('Error updating PO: ' + (response.data.message || 'Unknown error'));
            }
        },
        error: function() {
            alert('Error updating PO. Please try again.');
        }
    });
}

function vwpmClosePOModal() {
    jQuery('#vwpm-po-details-modal').hide();
}

function vwpmEscapeHtml(text) {
    if (text === null || text === undefined) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function vwpmFormatDate(dateStr) {
    if (!dateStr) return 'N/A';
    var date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}
</script>
