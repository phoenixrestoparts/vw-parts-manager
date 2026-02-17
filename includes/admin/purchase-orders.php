<?php
if (!defined('ABSPATH')) { 
    exit; 
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="wrap">
    <h1>Purchase Orders</h1>

    <p>
        <button id="vwpm-refresh-pos" class="button">Refresh List</button>
    </p>

    <table class="widefat fixed striped" id="vwpm-pos-table">
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Total</th>
                <th>Status</th>
                <th>Locked</th>
                <th>Created</th>
                <th>Updated</th>
                <th style="width: 350px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="8">Loading...</td></tr>
        </tbody>
    </table>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var isRefreshing = false;
    var eventHandlersAttached = false; // Prevent duplicate event handlers
    
    function formatStatus(raw) {
        if (!raw || raw === '0') return 'prepared';
        return String(raw).trim();
    }

    function fetchPos() {
        if (isRefreshing) return;
        isRefreshing = true;
        
        $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Loading…</td></tr>');
        
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_pos',
            nonce: vwpm_ajax.nonce
        }, function(res){
            console.log('GET POS Response:', res);
            
            if (!res || !res.success) {
                $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Failed to load POs</td></tr>');
                isRefreshing = false;
                return;
            }
            
            var rows = res.data.pos || [];
            if (!rows.length) {
                $('#vwpm-pos-table tbody').html('<tr><td colspan="8">No purchase orders found.</td></tr>');
                isRefreshing = false;
                return;
            }
            
            var html = '';
            $.each(rows, function(i, r){
                var status = formatStatus(r.status);
                var locked = (Number(r.is_locked) === 1);
                var poId = parseInt(r.id);
                
                // Safety check - skip if invalid ID
                if (!poId || isNaN(poId)) {
                    console.warn('Skipping PO with invalid ID:', r);
                    return true; // continue to next
                }

                html += '<tr data-po-id="' + poId + '">';
                html += '<td>' + (r.po_number || '') + '</td>';
                html += '<td>' + (r.supplier_name || '-') + '</td>';
                html += '<td>£' + parseFloat(r.total_cost || 0).toFixed(2) + '</td>';
// Add colored status badge
var statusColor = '#999';
var statusBg = '#f0f0f0';
if (status === 'ordered') {
    statusColor = '#fff';
    statusBg = '#dc3545'; // Red
} else if (status === 'received') {
    statusColor = '#fff';
    statusBg = '#28a745'; // Green
} else if (status === 'prepared') {
    statusColor = '#000';
    statusBg = '#ffc107'; // Yellow/Orange
}

html += '<td><span style="display:inline-block; padding:4px 12px; border-radius:12px; font-size:11px; font-weight:600; text-transform:uppercase; color:' + statusColor + '; background:' + statusBg + ';">' + status + '</span></td>';                html += '<td>' + (locked ? 'Yes' : 'No') + '</td>';
                html += '<td>' + (r.created_at || '') + '</td>';
                html += '<td>' + (r.updated_at || '') + '</td>';
                html += '<td class="po-actions">';
                html += '<button type="button" class="button vwpm-po-view" data-po-id="' + poId + '">View/Print</button> ';
                html += '<button type="button" class="button button-primary vwpm-po-edit" data-po-id="' + poId + '">Edit</button> ';
                html += '<button type="button" class="button vwpm-po-mark" data-po-id="' + poId + '" data-status="ordered">Mark Ordered</button> ';
                html += '<button type="button" class="button vwpm-po-mark" data-po-id="' + poId + '" data-status="received">Mark Received</button> ';
                html += '<button type="button" class="button vwpm-po-toggle-lock" data-po-id="' + poId + '" data-locked="' + (locked ? 1 : 0) + '">' + (locked ? 'Unlock' : 'Lock') + '</button> ';
                html += '<button type="button" class="button button-link-delete vwpm-po-delete" data-po-id="' + poId + '" style="color:#b32d2e;">Delete</button>';
                html += '</td>';
                html += '</tr>';
            });
            
            $('#vwpm-pos-table tbody').html(html);
            isRefreshing = false;
            
            console.log('Buttons rendered. Edit buttons:', $('.vwpm-po-edit').length, 'Delete buttons:', $('.vwpm-po-delete').length);
        }).fail(function(xhr, status, error){
            console.error('GET POS Failed:', xhr.responseText);
            $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Failed to load POs (request failed)</td></tr>');
            isRefreshing = false;
        });
    }

    // Attach event handlers ONCE using event delegation
    function attachEventHandlers() {
        if (eventHandlersAttached) {
            console.log('Event handlers already attached, skipping...');
            return;
        }
        
        console.log('Attaching event handlers...');
        
        // Refresh button
        $('#vwpm-refresh-pos').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fetchPos();
        });

        // Mark as Ordered/Received
        $('#vwpm-pos-table tbody').on('click', '.vwpm-po-mark', function(e){
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var poId = parseInt($btn.attr('data-po-id'));
            var newStatus = $btn.attr('data-status');
            
            console.log('Mark button clicked. PO ID:', poId, 'Status:', newStatus);
            
            if (!poId || isNaN(poId)) {
                alert('Invalid PO ID');
                return false;
            }
            
            $btn.prop('disabled', true).text('Updating...');
            
            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_update_po_status',
                nonce: vwpm_ajax.nonce,
                po_id: poId,
                status: newStatus
            }, function(res){
                if (res && res.success) {
                    alert('PO status updated to: ' + newStatus);
                    fetchPos();
                } else {
                    alert('Failed to update status');
                    $btn.prop('disabled', false).text('Mark ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                }
            }).fail(function(){
                alert('Request failed');
                $btn.prop('disabled', false).text('Mark ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
            });
            
            return false;
        });

        // Toggle lock
        $('#vwpm-pos-table tbody').on('click', '.vwpm-po-toggle-lock', function(e){
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var poId = parseInt($btn.attr('data-po-id'));
            var currentlyLocked = parseInt($btn.attr('data-locked'));
            var newLockState = currentlyLocked ? 0 : 1;
            
            console.log('Toggle lock clicked. PO ID:', poId, 'New state:', newLockState);
            
            if (!poId || isNaN(poId)) {
                alert('Invalid PO ID');
                return false;
            }
            
            $btn.prop('disabled', true);
            
            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_update_po_status',
                nonce: vwpm_ajax.nonce,
                po_id: poId,
                status: 'prepared',
                lock: newLockState
            }, function(res){
                if (res && res.success) {
                    alert('PO ' + (newLockState ? 'locked' : 'unlocked'));
                    fetchPos();
                } else {
                    alert('Failed to toggle lock');
                    $btn.prop('disabled', false);
                }
            }).fail(function(){
                alert('Request failed');
                $btn.prop('disabled', false);
            });
            
            return false;
        });

        // View/Print PO
        $('#vwpm-pos-table tbody').on('click', '.vwpm-po-view', function(e){
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var poId = parseInt($btn.attr('data-po-id'));
            
            console.log('View button clicked. PO ID:', poId, 'Type:', typeof poId, 'isNaN:', isNaN(poId));
            
            if (!poId || isNaN(poId)) {
                alert('Invalid PO ID: ' + poId);
                return false;
            }
            
            $btn.prop('disabled', true).text('Loading...');
            
            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_get_po',
                nonce: vwpm_ajax.nonce,
                po_id: poId
            }, function(res){
                console.log('GET PO Response:', res);
                
                if (!res || !res.success) {
                    alert('Failed to load PO details: ' + (res.data ? res.data.message : 'Unknown error'));
                    $btn.prop('disabled', false).text('View/Print');
                    return;
                }
                
                var po = res.data.po;
                
                $.post(vwpm_ajax.ajax_url, {
                    action: 'vwpm_save_po_for_print',
                    nonce: vwpm_ajax.nonce,
                    po_data: po
                }, function(saveRes){
                    console.log('Save for Print Response:', saveRes);
                    
                    if (saveRes && saveRes.success) {
                        window.open('<?php echo admin_url('admin.php'); ?>?vwpm_print_po=1', '_blank');
                        $btn.prop('disabled', false).text('View/Print');
                    } else {
                        alert('Failed to prepare PO for printing');
                        $btn.prop('disabled', false).text('View/Print');
                    }
                }).fail(function(xhr){
                    console.error('Save for Print Failed:', xhr.responseText);
                    alert('Request failed while preparing print');
                    $btn.prop('disabled', false).text('View/Print');
                });
                
            }).fail(function(xhr){
                console.error('GET PO Failed:', xhr.responseText);
                alert('Request failed: ' + xhr.statusText);
                $btn.prop('disabled', false).text('View/Print');
            });
            
            return false;
        });

        // Edit PO
        $('#vwpm-pos-table tbody').on('click', '.vwpm-po-edit', function(e){
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var poId = parseInt($btn.attr('data-po-id'));
            
            console.log('Edit button clicked. PO ID:', poId);
            
            if (!poId || isNaN(poId)) {
                alert('Invalid PO ID');
                return false;
            }
            
            if (confirm('Edit this PO? You will be redirected to the Production Calculator.')) {
                $btn.prop('disabled', true).text('Loading...');
                
                window.location.href = '<?php echo admin_url('admin.php?page=vwpm-production&edit_po='); ?>' + poId;
            }
            
            return false;
        });

        // Delete PO
        $('#vwpm-pos-table tbody').on('click', '.vwpm-po-delete', function(e){
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var poId = parseInt($btn.attr('data-po-id'));
            var $row = $btn.closest('tr');
            
            console.log('Delete button clicked. PO ID:', poId);
            
            if (!poId || isNaN(poId)) {
                alert('Invalid PO ID');
                return false;
            }
            
            if (confirm('Are you sure you want to DELETE this PO? This cannot be undone!')) {
                $btn.prop('disabled', true).text('Deleting...');
                
                $.post(vwpm_ajax.ajax_url, {
                    action: 'vwpm_delete_po',
                    nonce: vwpm_ajax.nonce,
                    po_id: poId
                }, function(res){
                    console.log('Delete Response:', res);
                    
                    if (res && res.success) {
                        alert('PO deleted successfully');
                        $row.fadeOut(300, function(){ 
                            $(this).remove();
                            if ($('#vwpm-pos-table tbody tr').length === 0) {
                                $('#vwpm-pos-table tbody').html('<tr><td colspan="8">No purchase orders found.</td></tr>');
                            }
                        });
                    } else {
                        alert('Failed to delete PO: ' + (res.data ? res.data.message : 'Unknown error'));
                        $btn.prop('disabled', false).text('Delete');
                    }
                }).fail(function(xhr){
                    console.error('Delete Failed:', xhr.responseText);
                    alert('Request failed: ' + xhr.statusText);
                    $btn.prop('disabled', false).text('Delete');
                });
            }
            
            return false;
        });
        
        eventHandlersAttached = true;
        console.log('Event handlers attached successfully');
    }

    // Attach handlers first, then load data
    attachEventHandlers();
    
    // Small delay to prevent auto-clicks from other plugins
    setTimeout(function() {
        fetchPos();
    }, 100);
});
</script>

<style>
.po-actions .button {
    margin: 2px 0;
}
/* Prevent buttons from being hidden by other plugins */
.po-actions .button {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}
</style>
