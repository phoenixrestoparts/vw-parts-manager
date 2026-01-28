<?php
/**
 * Purchase Orders Admin Page
 * Clean UTF-8 version with no BOM
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Purchase Orders</h1>
    
    <div class="vwpm-admin-wrapper">
        <div class="vwpm-card">
            <h2>All Purchase Orders</h2>
            
            <table class="wp-list-table widefat fixed striped" id="vwpm-pos-table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Locked</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="vwpm-pos-tbody">
                    <tr>
                        <td colspan="7" style="text-align: center;">Loading purchase orders...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PO Details Modal -->
<div id="vwpm-po-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:999999;">
    <div style="position:relative; width:90%; max-width:1000px; margin:50px auto; background:#fff; padding:30px; border-radius:8px; max-height:90vh; overflow-y:auto;">
        <button id="vwpm-close-modal" style="position:absolute; top:15px; right:15px; background:#ddd; border:none; padding:8px 15px; cursor:pointer; font-size:18px; border-radius:4px;">&times;</button>
        
        <div id="vwpm-po-details">
            <p>Loading PO details...</p>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    // Fetch and display all POs
    function loadPOs() {
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_pos',
            nonce: vwpm_ajax.nonce
        }, function(response) {
            if (response.success && response.data.pos) {
                renderPOsTable(response.data.pos);
            } else {
                $('#vwpm-pos-tbody').html('<tr><td colspan="7" style="text-align:center; color:#d63638;">Failed to load purchase orders.</td></tr>');
            }
        }).fail(function() {
            $('#vwpm-pos-tbody').html('<tr><td colspan="7" style="text-align:center; color:#d63638;">Error loading purchase orders.</td></tr>');
        });
    }
    
    // Render POs table
    function renderPOsTable(pos) {
        if (!pos || pos.length === 0) {
            $('#vwpm-pos-tbody').html('<tr><td colspan="7" style="text-align:center;">No purchase orders found.</td></tr>');
            return;
        }
        
        var html = '';
        $.each(pos, function(i, po) {
            // Normalize status
            var status = po.status || 'prepared';
            status = status.toLowerCase().trim();
            
            // Status badge styling
            var statusBadge = '';
            switch(status) {
                case 'prepared':
                    statusBadge = '<span style="display:inline-block; padding:4px 10px; background:#f0f0f1; color:#2c3338; border-radius:3px; font-size:12px;">Prepared</span>';
                    break;
                case 'ordered':
                    statusBadge = '<span style="display:inline-block; padding:4px 10px; background:#72aee6; color:#fff; border-radius:3px; font-size:12px;">Ordered</span>';
                    break;
                case 'received':
                    statusBadge = '<span style="display:inline-block; padding:4px 10px; background:#00a32a; color:#fff; border-radius:3px; font-size:12px;">Received</span>';
                    break;
                case 'complete':
                    statusBadge = '<span style="display:inline-block; padding:4px 10px; background:#00a32a; color:#fff; border-radius:3px; font-size:12px;">Complete</span>';
                    break;
                case 'locked':
                    statusBadge = '<span style="display:inline-block; padding:4px 10px; background:#d63638; color:#fff; border-radius:3px; font-size:12px;">Locked</span>';
                    break;
                default:
                    statusBadge = '<span style="display:inline-block; padding:4px 10px; background:#dba617; color:#fff; border-radius:3px; font-size:12px;">' + escapeHtml(status) + '</span>';
            }
            
            var lockedBadge = (parseInt(po.is_locked) === 1) 
                ? '<span style="color:#d63638;">&#128274; Yes</span>' 
                : '<span style="color:#787c82;">No</span>';
            
            html += '<tr>';
            html += '<td><strong>' + escapeHtml(po.po_number || '') + '</strong></td>';
            html += '<td>' + escapeHtml(po.supplier_name || 'N/A') + '</td>';
            html += '<td>£' + parseFloat(po.total_cost || 0).toFixed(2) + '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td>' + lockedBadge + '</td>';
            html += '<td>' + formatDate(po.created_at) + '</td>';
            html += '<td><button class="button button-small vwpm-view-po" data-po-id="' + po.id + '">View</button></td>';
            html += '</tr>';
        });
        
        $('#vwpm-pos-tbody').html(html);
    }
    
    // View PO details
    $(document).on('click', '.vwpm-view-po', function() {
        var poId = $(this).data('po-id');
        
        $('#vwpm-po-modal').show();
        $('#vwpm-po-details').html('<p>Loading PO details...</p>');
        
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_po',
            nonce: vwpm_ajax.nonce,
            po_id: poId
        }, function(response) {
            if (response.success && response.data.po) {
                renderPODetails(response.data.po);
            } else {
                $('#vwpm-po-details').html('<p style="color:#d63638;">Failed to load PO details.</p>');
            }
        }).fail(function() {
            $('#vwpm-po-details').html('<p style="color:#d63638;">Error loading PO details.</p>');
        });
    });
    
    // Render PO details in modal
    function renderPODetails(po) {
        var status = (po.status || 'prepared').toLowerCase().trim();
        var items = po.items || [];
        var tools = po.tools || [];
        var productSummary = po.product_summary || [];
        
        var html = '<h2>Purchase Order: ' + escapeHtml(po.po_number || '') + '</h2>';
        
        html += '<div style="margin-bottom:20px;">';
        html += '<p><strong>Supplier:</strong> ' + escapeHtml(po.supplier_name || 'N/A') + '</p>';
        if (po.supplier_email) {
            html += '<p><strong>Email:</strong> ' + escapeHtml(po.supplier_email) + '</p>';
        }
        html += '<p><strong>Status:</strong> ' + escapeHtml(status) + '</p>';
        html += '<p><strong>Total Cost:</strong> £' + parseFloat(po.total_cost || 0).toFixed(2) + '</p>';
        html += '<p><strong>Created:</strong> ' + formatDate(po.created_at) + '</p>';
        html += '</div>';
        
        // Product Summary
        if (productSummary && productSummary.length > 0) {
            html += '<h3>Product Summary</h3>';
            html += '<table class="wp-list-table widefat" style="margin-bottom:20px;">';
            html += '<thead><tr><th>Product</th><th>Quantity</th></tr></thead>';
            html += '<tbody>';
            $.each(productSummary, function(i, prod) {
                html += '<tr>';
                html += '<td>' + escapeHtml(prod.title || prod.product_id || 'N/A') + '</td>';
                html += '<td>' + parseFloat(prod.quantity || 0).toFixed(2) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        }
        
        // Items
        if (items && items.length > 0) {
            html += '<h3>Components</h3>';
            html += '<table class="wp-list-table widefat" style="margin-bottom:20px;">';
            html += '<thead><tr><th>Item</th><th>Part Number</th><th>Qty Per Unit</th><th>Total Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>';
            html += '<tbody>';
            $.each(items, function(i, item) {
                html += '<tr>';
                html += '<td>' + escapeHtml(item.component_name || '') + '</td>';
                html += '<td>' + escapeHtml(item.component_number || '') + '</td>';
                html += '<td>' + parseFloat(item.qty_per_unit || 0).toFixed(2) + '</td>';
                html += '<td>' + parseFloat(item.total_qty || 0).toFixed(2) + '</td>';
                html += '<td>£' + parseFloat(item.unit_price || 0).toFixed(2) + '</td>';
                html += '<td>£' + parseFloat(item.line_total || 0).toFixed(2) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        }
        
        // Tools
        if (tools && tools.length > 0) {
            html += '<h3>Tools Required</h3>';
            html += '<table class="wp-list-table widefat" style="margin-bottom:20px;">';
            html += '<thead><tr><th>Tool Name</th><th>Tool Number</th><th>Location</th></tr></thead>';
            html += '<tbody>';
            $.each(tools, function(i, tool) {
                html += '<tr>';
                html += '<td>' + escapeHtml(tool.name || '') + '</td>';
                html += '<td>' + escapeHtml(tool.number || '') + '</td>';
                html += '<td>' + escapeHtml(tool.location || '') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        }
        
        html += '<div style="margin-top:30px; padding-top:20px; border-top:1px solid #ddd;">';
        html += '<button class="button button-primary" id="vwpm-print-po" data-po-id="' + po.id + '">Print PO</button> ';
        html += '<button class="button" id="vwpm-close-modal-btn">Close</button>';
        html += '</div>';
        
        $('#vwpm-po-details').html(html);
    }
    
    // Print PO - open printable view in new window
    $(document).on('click', '#vwpm-print-po', function() {
        var poId = $(this).data('po-id');
        
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_po',
            nonce: vwpm_ajax.nonce,
            po_id: poId
        }, function(response) {
            if (response.success && response.data.po) {
                openPrintWindow(response.data.po);
            } else {
                alert('Failed to load PO for printing.');
            }
        }).fail(function() {
            alert('Error loading PO for printing.');
        });
    });
    
    // Open print window
    function openPrintWindow(po) {
        var items = po.items || [];
        var tools = po.tools || [];
        var productSummary = po.product_summary || [];
        
        var printHTML = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Purchase Order - ' + escapeHtml(po.po_number || '') + '</title>';
        printHTML += '<style>';
        printHTML += '@page { size: A4; margin: 15mm; }';
        printHTML += 'body { font-family: Arial, sans-serif; font-size: 12px; color: #000; margin: 0; padding: 20px; }';
        printHTML += 'h1, h2, h3 { margin: 10px 0; }';
        printHTML += 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
        printHTML += 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }';
        printHTML += 'th { background: #f2f2f2; font-weight: bold; }';
        printHTML += '.text-right { text-align: right; }';
        printHTML += '.totals { font-weight: bold; background: #f9f9f9; }';
        printHTML += '.print-btn { background: #0073aa; color: #fff; padding: 10px 20px; border: none; cursor: pointer; margin-bottom: 20px; }';
        printHTML += '@media print { .print-btn { display: none; } }';
        printHTML += '</style></head><body>';
        
        printHTML += '<button class="print-btn" onclick="window.print()">PRINT / SAVE AS PDF</button>';
        printHTML += '<h1>Purchase Order: ' + escapeHtml(po.po_number || '') + '</h1>';
        
        if (productSummary && productSummary.length > 0) {
            var productNames = [];
            var totalQty = 0;
            $.each(productSummary, function(i, prod) {
                productNames.push(escapeHtml(prod.title || 'Product #' + (prod.product_id || '')));
                totalQty += parseFloat(prod.quantity || 0);
            });
            printHTML += '<p><strong>Product(s):</strong> ' + productNames.join(', ') + '</p>';
            printHTML += '<p><strong>Total Quantity:</strong> ' + totalQty.toFixed(2) + '</p>';
        }
        
        printHTML += '<p><strong>Date:</strong> ' + formatDate(po.created_at) + '</p>';
        printHTML += '<h2>Supplier: ' + escapeHtml(po.supplier_name || 'N/A') + '</h2>';
        if (po.supplier_email) {
            printHTML += '<p><strong>Email:</strong> ' + escapeHtml(po.supplier_email) + '</p>';
        }
        
        if (items && items.length > 0) {
            printHTML += '<table><thead><tr>';
            printHTML += '<th>Item</th><th>Part Number</th><th>Qty Per Unit</th><th>Total Qty</th>';
            printHTML += '<th class="text-right">Unit Price</th><th class="text-right">Total</th>';
            printHTML += '</tr></thead><tbody>';
            
            $.each(items, function(i, item) {
                printHTML += '<tr>';
                printHTML += '<td>' + escapeHtml(item.component_name || '') + '</td>';
                printHTML += '<td>' + escapeHtml(item.component_number || '') + '</td>';
                printHTML += '<td>' + parseFloat(item.qty_per_unit || 0).toFixed(2) + '</td>';
                printHTML += '<td>' + parseFloat(item.total_qty || 0).toFixed(2) + '</td>';
                printHTML += '<td class="text-right">£' + parseFloat(item.unit_price || 0).toFixed(2) + '</td>';
                printHTML += '<td class="text-right">£' + parseFloat(item.line_total || 0).toFixed(2) + '</td>';
                printHTML += '</tr>';
            });
            
            printHTML += '<tr class="totals">';
            printHTML += '<td colspan="5" class="text-right"><strong>Total:</strong></td>';
            printHTML += '<td class="text-right"><strong>£' + parseFloat(po.total_cost || 0).toFixed(2) + '</strong></td>';
            printHTML += '</tr>';
            printHTML += '</tbody></table>';
        }
        
        if (tools && tools.length > 0) {
            printHTML += '<h2>Tools Required</h2>';
            printHTML += '<table><thead><tr><th>Tool Name</th><th>Tool Number</th><th>Location</th></tr></thead><tbody>';
            $.each(tools, function(i, tool) {
                printHTML += '<tr>';
                printHTML += '<td>' + escapeHtml(tool.name || '') + '</td>';
                printHTML += '<td>' + escapeHtml(tool.number || '') + '</td>';
                printHTML += '<td>' + escapeHtml(tool.location || '') + '</td>';
                printHTML += '</tr>';
            });
            printHTML += '</tbody></table>';
        }
        
        printHTML += '</body></html>';
        
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(printHTML);
        printWindow.document.close();
    }
    
    // Close modal
    $(document).on('click', '#vwpm-close-modal, #vwpm-close-modal-btn', function() {
        $('#vwpm-po-modal').hide();
    });
    
    // Close modal on ESC key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#vwpm-po-modal').is(':visible')) {
            $('#vwpm-po-modal').hide();
        }
    });
    
    // Utility functions
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        var date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    // Initial load
    loadPOs();
});
</script>
