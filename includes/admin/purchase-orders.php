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
    var isRefreshing = false; // Prevent multiple simultaneous refreshes
    
    function formatStatus(raw) {
        if (!raw || raw === '0') return 'prepared';
        return String(raw).trim();
    }

    function fetchPos() {
        if (isRefreshing) return; // Prevent double-refresh
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

                html += '<tr data-po-id="' + r.id + '">';
                html += '<td>' + (r.po_number || '') + '</td>';
                html += '<td>' + (r.supplier_name || '-') + '</td>';
                html += '<td>£' + parseFloat(r.total_cost || 0).toFixed(2) + '</td>';
                html += '<td>' + status + '</td>';
                html += '<td>' + (locked ? 'Yes' : 'No') + '</td>';
                html += '<td>' + (r.created_at || '') + '</td>';
                html += '<td>' + (r.updated_at || '') + '</td>';
                html += '<td class="po-actions">';
                html += '<button class="button vwpm-po-view" data-po-id="' + r.id + '">View/Print</button> ';
                html += '<button class="button button-primary vwpm-po-edit" data-po-id="' + r.id + '">Edit</button> ';
                html += '<button class="button vwpm-po-mark" data-po-id="' + r.id + '" data-status="ordered">Mark Ordered</button> ';
                html += '<button class="button vwpm-po-mark" data-po-id="' + r.id + '" data-status="received">Mark Received</button> ';
                html += '<button class="button vwpm-po-toggle-lock" data-po-id="' + r.id + '" data-locked="' + (locked ? 1 : 0) + '">' + (locked ? 'Unlock' : 'Lock') + '</button> ';
                html += '<button class="button button-link-delete vwpm-po-delete" data-po-id="' + r.id + '" style="color:#b32d2e;">Delete</button>';
                html += '</td>';
                html += '</tr>';
            });
            
            $('#vwpm-pos-table tbody').html(html);
            isRefreshing = false;
        }).fail(function(xhr, status, error){
            console.error('GET POS Failed:', xhr.responseText);
            $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Failed to load POs (request failed)</td></tr>');
            isRefreshing = false;
        });
    }

    // Refresh button
    $('#vwpm-refresh-pos').on('click', function(e) {
        e.preventDefault();
        fetchPos();
    });

    // Use event delegation on table body - prevents handlers from disappearing
    $('#vwpm-pos-table tbody').on('click', '.vwpm-po-mark', function(e){
        e.preventDefault();
        var poId = parseInt($(this).data('po-id'));
        var newStatus = $(this).data('status');
        
        console.log('Marking PO ID:', poId, 'as', newStatus);
        
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
            }
        });
    });

    // Toggle lock
    $('#vwpm-pos-table tbody').on('click', '.vwpm-po-toggle-lock', function(e){
        e.preventDefault();
        var poId = parseInt($(this).data('po-id'));
        var currentlyLocked = parseInt($(this).data('locked'));
        var newLockState = currentlyLocked ? 0 : 1;
        
        console.log('Toggling lock for PO ID:', poId);
        
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
            }
        });
    });

    // View/Print PO
    $('#vwpm-pos-table tbody').on('click', '.vwpm-po-view', function(e){
        e.preventDefault();
        var poId = parseInt($(this).data('po-id'));
        
        console.log('Viewing PO ID:', poId, 'Type:', typeof poId);
        
        if (!poId || isNaN(poId)) {
            alert('Invalid PO ID: ' + poId);
            return;
        }
        
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_po',
            nonce: vwpm_ajax.nonce,
            po_id: poId
        }, function(res){
            console.log('GET PO Response:', res);
            
            if (!res || !res.success) {
                alert('Failed to load PO details: ' + (res.data ? res.data.message : 'Unknown error'));
                return;
            }
            
            var po = res.data.po;
            
            // Save to transient for printing
            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_save_po_for_print',
                nonce: vwpm_ajax.nonce,
                po_data: po
            }, function(saveRes){
                console.log('Save for Print Response:', saveRes);
                
                if (saveRes && saveRes.success) {
                    window.open('<?php echo admin_url('admin.php'); ?>?vwpm_print_po=1', '_blank');
                } else {
                    alert('Failed to prepare PO for printing');
                }
            }).fail(function(xhr){
                console.error('Save for Print Failed:', xhr.responseText);
                alert('Request failed while preparing print');
            });
            
        }).fail(function(xhr){
            console.error('GET PO Failed:', xhr.responseText);
            alert('Request failed: ' + xhr.statusText);
        });
    });

    // Edit PO
    $('#vwpm-pos-table tbody').on('click', '.vwpm-po-edit', function(e){
        e.preventDefault();
        var poId = parseInt($(this).data('po-id'));
        
        console.log('Editing PO ID:', poId);
        
        if (!poId || isNaN(poId)) {
            alert('Invalid PO ID');
            return;
        }
        
        if (confirm('Edit this PO? You will be redirected to the Production Calculator.')) {
            // Store the PO ID in a transient for editing
            $.post(vwpm_ajax.ajax_url, {
                action: 'vwpm_prepare_po_for_edit',
                nonce: vwpm_ajax.nonce,
                po_id: poId
            }, function(res){
                if (res && res.success) {
                    // Redirect to production calculator with edit mode
                    window.location.href = '<?php echo admin_url('admin.php?page=vwpm-production&edit_po='); ?>' + poId;
                } else {
                    alert('Failed to prepare PO for editing: ' + (res.data ? res.data.message : 'Unknown error'));
                }
            }).fail(function(){
                alert('Request failed while preparing edit');
            });
        }
    });

    // Delete PO
    $('#vwpm-pos-table tbody').on('click', '.vwpm-po-delete', function(e){
        e.preventDefault();
        var poId = parseInt($(this).data('po-id'));
        var $row = $(this).closest('tr');
        
        console.log('Deleting PO ID:', poId);
        
        if (!poId || isNaN(poId)) {
            alert('Invalid PO ID');
            return;
        }
        
        if (confirm('Are you sure you want to DELETE this PO? This cannot be undone!')) {
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
                        // Check if table is empty
                        if ($('#vwpm-pos-table tbody tr').length === 0) {
                            $('#vwpm-pos-table tbody').html('<tr><td colspan="8">No purchase orders found.</td></tr>');
                        }
                    });
                } else {
                    alert('Failed to delete PO: ' + (res.data ? res.data.message : 'Unknown error'));
                }
            }).fail(function(xhr){
                console.error('Delete Failed:', xhr.responseText);
                alert('Request failed: ' + xhr.statusText);
            });
        }
    });

    // Load POs on page load
    fetchPos();
});
</script>

<style>
.po-actions .button {
    margin: 2px 0;
}
</style>
