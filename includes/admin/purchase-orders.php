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
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="8">Loading...</td></tr>
        </tbody>
    </table>
</div>

<div id="vwpm-po-detail-modal" style="display:none; position:fixed; top:50px; left:50%; transform:translateX(-50%); background:#fff; border:2px solid #333; padding:20px; max-width:800px; max-height:80vh; overflow:auto; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.3);">
    <button id="vwpm-close-modal" style="float:right; font-size:20px; border:none; background:none; cursor:pointer;">&times;</button>
    <div id="vwpm-po-detail-content"></div>
    <button onclick="window.print()" class="button button-primary" style="margin-top:10px;">Print</button>
</div>
<div id="vwpm-modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9998;"></div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    function formatStatus(raw) {
        if (!raw || raw === '0') return 'prepared';
        return String(raw).trim();
    }

    function fetchPos() {
        $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Loading…</td></tr>');
        
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_pos',
            nonce: vwpm_ajax.nonce
        }, function(res){
            console.log('GET POS Response:', res); // DEBUG
            
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
                html += '<td>';
                html += '<button class="button vwpm-po-view" data-po-id="' + r.id + '">View/Print</button> ';
                html += '<button class="button vwpm-po-mark" data-po-id="' + r.id + '" data-status="ordered">Mark Ordered</button> ';
                html += '<button class="button vwpm-po-mark" data-po-id="' + r.id + '" data-status="received">Mark Received</button> ';
                html += '<button class="button vwpm-po-toggle-lock" data-po-id="' + r.id + '" data-locked="' + (locked ? 1 : 0) + '">' + (locked ? 'Unlock' : 'Lock') + '</button>';
                html += '</td>';
                html += '</tr>';
            });
            
            $('#vwpm-pos-table tbody').html(html);
        }).fail(function(xhr, status, error){
            console.error('GET POS Failed:', xhr.responseText); // DEBUG
            $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Failed to load POs (request failed)</td></tr>');
        });
    }

    $('#vwpm-refresh-pos').on('click', function(e) {
        e.preventDefault();
        fetchPos();
    });

    $(document).on('click', '.vwpm-po-mark', function(e){
        e.preventDefault();
        var poId = $(this).data('po-id');
        var newStatus = $(this).data('status');
        
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

    $(document).on('click', '.vwpm-po-toggle-lock', function(e){
        e.preventDefault();
        var poId = $(this).data('po-id');
        var currentlyLocked = $(this).data('locked');
        var newLockState = currentlyLocked ? 0 : 1;
        
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

    // View PO - Save to transient then open print window
    $(document).on('click', '.vwpm-po-view', function(e){
        e.preventDefault();
        var poId = $(this).data('po-id');
        
        console.log('Viewing PO ID:', poId); // DEBUG
        
        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_po',
            nonce: vwpm_ajax.nonce,
            po_id: poId
        }, function(res){
            console.log('GET PO Response:', res); // DEBUG
            
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
                console.log('Save for Print Response:', saveRes); // DEBUG
                
                if (saveRes && saveRes.success) {
                    // Open print view
                    window.open('<?php echo admin_url('admin.php'); ?>?vwpm_print_po=1', '_blank');
                } else {
                    alert('Failed to prepare PO for printing');
                }
            }).fail(function(xhr, status, error){
                console.error('Save for Print Failed:', xhr.responseText); // DEBUG
                alert('Request failed while preparing print');
            });
            
        }).fail(function(xhr, status, error){
            console.error('GET PO Failed:', xhr.responseText); // DEBUG
            alert('Request failed: ' + error);
        });
    });

    $('#vwpm-close-modal, #vwpm-modal-overlay').on('click', function(){
        $('#vwpm-modal-overlay, #vwpm-po-detail-modal').fadeOut();
    });

    // Load POs on page load
    fetchPos();
});
</script>
