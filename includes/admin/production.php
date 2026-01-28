<?php
if (!defined('ABSPATH')) { exit; }

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>
<div class="wrap">
    <h1>Production Planning</h1>

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
        <tbody></tbody>
    </table>
</div>

<script type="text/javascript">
jQuery(function($){
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
        }).fail(function(xhr, status, err){
            $('#vwpm-pos-table tbody').html('<tr><td colspan="8">Failed to load POs (request failed)</td></tr>');
            console.error('fetchPos failed', status, err, xhr && xhr.responseText);
        });
    }

    $(document).on('click', '#vwpm-refresh-pos', function(e){ e.preventDefault(); fetchPos(); });

    $(document).on('click', '.vwpm-po-mark', function(e){
        e.preventDefault();
        var $tr = $(this).closest('tr');
        var po_id = $tr.data('po-id');
        var status = $(this).data('status');
        if (!po_id) return;
        if (!confirm('Change status to '+status+'?')) return;
        $.post(vwpm_ajax.ajax_url, { action: 'vwpm_update_po_status', nonce: vwpm_ajax.nonce, po_id: po_id, status: status }, function(res){
            if (res && res.success) {
                fetchPos();
            } else {
                alert('Failed to update PO');
                console.error('vwpm_update_po_status response', res);
            }
        }).fail(function(xhr, status, err){
            alert('Failed to update PO (request error)');
            console.error('vwpm_update_po_status failed', status, err, xhr && xhr.responseText);
        });
    });

    $(document).on('click', '.vwpm-po-toggle-lock', function(e){
        e.preventDefault();
        var $tr = $(this).closest('tr');
        var po_id = $tr.data('po-id');
        var currentlyLocked = ($tr.find('td').eq(4).text().trim() === 'Yes');
        var newLock = currentlyLocked ? 0 : 1;
        if (!confirm((newLock ? 'Lock' : 'Unlock') + ' this PO?')) return;
        $.post(vwpm_ajax.ajax_url, { action: 'vwpm_update_po_status', nonce: vwpm_ajax.nonce, po_id: po_id, status: 'prepared', lock: newLock }, function(res){
            if (res && res.success) {
                fetchPos();
            } else {
                alert('Failed to toggle lock');
                console.error('vwpm_update_po_status response', res);
            }
        }).fail(function(xhr, status, err){
            alert('Failed to toggle lock (request error)');
            console.error('vwpm_update_po_status failed', status, err, xhr && xhr.responseText);
        });
    });

    // VIEW handler: fetch PO detail and show modal + print
    $(document).on('click', '.vwpm-po-view', function(e){
        e.preventDefault();
        var $tr = $(this).closest('tr');
        var po_id = $tr.data('po-id');
        if (!po_id) return;

        $.post(vwpm_ajax.ajax_url, {
            action: 'vwpm_get_po',
            nonce: vwpm_ajax.nonce,
            po_id: po_id
        }, function(res){
            if (!res || !res.success) {
                alert('Failed to load PO: ' + (res && res.data && res.data.message ? res.data.message : 'Unknown'));
                console.error('vwpm_get_po response', res);
                return;
            }

            var po = res.data.po;

            // Build HTML for modal
            var html = '<div id="vwpm-po-modal" style="position:fixed;left:0;top:0;right:0;bottom:0;z-index:99999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;">';
            html += '<div style="width:90%;max-width:1000px;background:#fff;padding:18px;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,0.2);position:relative;">';
            html += '<button id="vwpm-po-modal-close" style="position:absolute;right:12px;top:12px;">Close</button>';
            html += '<h2>Purchase Order: ' + (po.po_number || po.id) + '</h2>';
            html += '<p><strong>Supplier:</strong> ' + (po.supplier_name || '-') + ' &nbsp; <strong>Email:</strong> ' + (po.supplier_email || '-') + '</p>';
            html += '<p><strong>Created:</strong> ' + (po.created_at || '') + ' &nbsp; <strong>Status:</strong> ' + (po.status || 'prepared') + '</p>';

            // Product summary (if any)
            if (po.product_summary && po.product_summary.length) {
                html += '<h3>Products</h3><ul>';
                po.product_summary.forEach(function(p){
                    var title = p.title || ('Product #' + (p.product_id || ''));
                    var qty = (p.quantity !== undefined) ? p.quantity : '';
                    html += '<li>' + title + ' x ' + qty + '</li>';
                });
                html += '</ul>';
            }

            // Items table
            html += '<h3>Items</h3>';
            html += '<table style="width:100%;border-collapse:collapse;margin-bottom:12px;">';
            html += '<thead><tr><th style="border:1px solid #ddd;padding:6px;">Item</th><th style="border:1px solid #ddd;padding:6px;">Part Number</th><th style="border:1px solid #ddd;padding:6px;">Supplier Ref</th><th style="border:1px solid #ddd;padding:6px;text-align:right;">Qty</th><th style="border:1px solid #ddd;padding:6px;text-align:right;">Unit</th><th style="border:1px solid #ddd;padding:6px;text-align:right;">Total</th></tr></thead><tbody>';
            if (po.items && po.items.length) {
                po.items.forEach(function(it){
                    html += '<tr>';
                    html += '<td style="border:1px solid #ddd;padding:6px;">' + (it.component_name || '') + '</td>';
                    html += '<td style="border:1px solid #ddd;padding:6px;">' + (it.component_number || '') + '</td>';
                    html += '<td style="border:1px solid #ddd;padding:6px;">' + (it.supplier_ref || '') + '</td>';
                    html += '<td style="border:1px solid #ddd;padding:6px;text-align:right;">' + (it.total_qty !== undefined ? Number(it.total_qty).toFixed(2) : '') + '</td>';
                    html += '<td style="border:1px solid #ddd;padding:6px;text-align:right;">' + (it.unit_price !== undefined ? '£' + Number(it.unit_price).toFixed(2) : '') + '</td>';
                    html += '<td style="border:1px solid #ddd;padding:6px;text-align:right;">' + (it.line_total !== undefined ? '£' + Number(it.line_total).toFixed(2) : '') + '</td>';
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="6" style="border:1px solid #ddd;padding:6px;text-align:center;">No items</td></tr>';
            }
            html += '</tbody></table>';

            // Tools
            if (po.tools && po.tools.length) {
                html += '<h3>Tools</h3><table style="width:100%;border-collapse:collapse;margin-bottom:12px;">';
                html += '<thead><tr><th style="border:1px solid #ddd;padding:6px;">Tool Name</th><th style="border:1px solid #ddd;padding:6px;">Number</th><th style="border:1px solid #ddd;padding:6px;">Location</th></tr></thead><tbody>';
                po.tools.forEach(function(t){
                    html += '<tr>';
                    html += '<td style="border:1px solid #ddd;padding:6px;">' + (t.name || '') + '</td>';
                    html += '<td style="border:1px solid #ddd;padding:6px;">' + (t.number || '') + '</td>';
                    html += '<td style="border:1px solid #ddd;padding:6px;">' + (t.location || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }

            html += '<div style="margin-top:10px;"><button id="vwpm-po-print" class="button button-primary">Print / Save PDF</button> ';
            html += '<button id="vwpm-po-close" class="button">Close</button></div>';

            html += '</div></div>';

            // Remove any previous modal and append
            $('#vwpm-po-modal').remove();
            $('body').append(html);

            // Close handlers
            $('#vwpm-po-modal, #vwpm-po-close, #vwpm-po-modal-close').on('click', function(e){
                var targetId = e.target.id;
                if (targetId === 'vwpm-po-modal' || targetId === 'vwpm-po-close' || targetId === 'vwpm-po-modal-close') {
                    $('#vwpm-po-modal').remove();
                }
            });

            // Print handler: open new window and print formatted PO
            $('#vwpm-po-print').on('click', function(){
                var printWindow = window.open('', '_blank', 'width=900,height=700');
                var doc = printWindow.document;
                doc.open();
                var title = 'Purchase Order - ' + (po.po_number || '');
                var content = '<html><head><title>' + title + '</title>';
                content += '<style>body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#000}table{width:100%;border-collapse:collapse}th,td{border:1px solid #000;padding:6px;text-align:left}th{background:#f2f2f2}</style>';
                content += '</head><body>';
                content += '<h1>' + title + '</h1>';
                content += '<p><strong>Supplier:</strong> ' + (po.supplier_name || '') + '</p>';
                content += '<p><strong>Date:</strong> ' + (po.created_at || '') + '</p>';
                content += '<h3>Items</h3><table><thead><tr><th>Item</th><th>Part</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead><tbody>';
                if (po.items && po.items.length) {
                    po.items.forEach(function(it){
                        content += '<tr>';
                        content += '<td>' + (it.component_name || '') + '</td>';
                        content += '<td>' + (it.component_number || '') + '</td>';
                        content += '<td>' + (it.total_qty !== undefined ? Number(it.total_qty).toFixed(2) : '') + '</td>';
                        content += '<td>' + (it.unit_price !== undefined ? '£' + Number(it.unit_price).toFixed(2) : '') + '</td>';
                        content += '<td>' + (it.line_total !== undefined ? '£' + Number(it.line_total).toFixed(2) : '') + '</td>';
                        content += '</tr>';
                    });
                }
                content += '</tbody></table>';
                content += '<p style="font-weight:bold;margin-top:12px;">Total: £' + (Number(po.total_cost || 0).toFixed(2)) + '</p>';
                content += '</body></html>';
                doc.write(content);
                doc.close();
                printWindow.focus();
                setTimeout(function(){ printWindow.print(); }, 300);
            });

        }).fail(function(xhr, status, err){
            alert('Request failed; see console');
            console.error('vwpm_get_po failed', status, err, xhr && xhr.responseText);
        });
    });

    // initial load
    fetchPos();
});
</script>
