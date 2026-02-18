<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

?>

<div class="wrap">
    <h1>Import / Export</h1>
    
    <div class="vwpm-admin-wrapper">
        
        <!-- Tools Import/Export -->
        <div class="vwpm-card">
            <h2>Tools</h2>
            
            <div class="vwpm-import-section">
                <h3>Import Tools from CSV</h3>
                <p>Upload a CSV file with the following columns: <code>Tool Name, Tool Number, Location, Notes</code></p>
                <p><strong>Example:</strong></p>
                <pre>Tool Name,Tool Number,Location,Notes
Laser Cutter,LC-001,Workshop A,Main laser for blanks
Hydraulic Press,HP-002,Workshop B,For forming parts</pre>
                
                <form id="vwpm-import-tools-form" enctype="multipart/form-data">
                    <div class="vwpm-file-input">
                        <input type="file" name="tools_csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="button button-primary">Import Tools</button>
                </form>
            </div>
            
            <div style="margin-top: 20px;">
                <h3>Export Tools to CSV</h3>
                <p>Download all your tools as a CSV file.</p>
                <button id="vwpm-export-tools" class="button button-secondary">Export Tools CSV</button>
            </div>
        </div>
        
        <!-- Components Import/Export -->
        <div class="vwpm-card">
            <h2>Components</h2>
            
            <div class="vwpm-import-section">
                <h3>Import Components from CSV</h3>
                <p>Upload a CSV file with the following columns: <code>Component Name, Component Number, Location, Supplier Name, Price, Notes</code></p>
                <p><strong>Example:</strong></p>
                <pre>Component Name,Component Number,Location,Supplier Name,Price,Notes
Steel Blank Type A,com111-809-456,Shelf A1,MetalWorks Ltd,12.50,Standard blank
Steel Blank Type B,com111-809-456/b,Shelf A2,MetalWorks Ltd,15.00,Reinforced version
M8 Bolt,BOLT-M8-50,Bin B5,Fasteners Inc,0.25,50mm length</pre>
                
                <p class="description"><strong>Note:</strong> The supplier name must exactly match an existing supplier in your system.</p>
                
                <form id="vwpm-import-components-form" enctype="multipart/form-data">
                    <div class="vwpm-file-input">
                        <input type="file" name="components_csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="button button-primary">Import Components</button>
                </form>
            </div>
            
            <div style="margin-top: 20px;">
                <h3>Export Components to CSV</h3>
                <p>Download all your components as a CSV file.</p>
                <button id="vwpm-export-components" class="button button-secondary">Export Components CSV</button>
            </div>
        </div>
        
        <!-- CSV Format Help -->
        <div class="vwpm-card">
            <h2>CSV Format Guidelines</h2>
            <ul>
                <li>Use standard CSV format with comma separators</li>
                <li>First row should contain column headers (exactly as shown in examples)</li>
                <li>Use quotes around values that contain commas</li>
                <li>Blank fields are allowed (except for required fields like names and numbers)</li>
                <li>For components, the supplier must already exist in your system</li>
                <li>You can create CSV files in Excel, Google Sheets, or any spreadsheet program</li>
            </ul>
            
            <h3>Tips for Success</h3>
            <ul>
                <li><strong>Add suppliers first:</strong> Before importing components, make sure all suppliers exist in the Suppliers section</li>
                <li><strong>Test with small files:</strong> Try importing 2-3 items first to make sure your format is correct</li>
                <li><strong>Check for duplicates:</strong> The import will create new items each time, so avoid importing the same file twice</li>
                <li><strong>Back up regularly:</strong> Export your data regularly to have a backup</li>
            </ul>
        </div>
        
        <!-- Sample Download -->
        <div class="vwpm-card">
            <h2>Sample CSV Templates</h2>
            <p>Download sample CSV templates to get started:</p>
            <p>
                <a href="#" id="vwpm-download-tools-template" class="button">Download Tools Template</a>
                <a href="#" id="vwpm-download-components-template" class="button">Download Components Template</a>
            </p>
        </div>
        
    </div>
</div>

<script>
var vwpm_ajax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('vwpm_nonce'); ?>'
};

jQuery(document).ready(function($) {
    // Download sample templates
    $('#vwpm-download-tools-template').on('click', function(e) {
        e.preventDefault();
        var csv = 'Tool Name,Tool Number,Location,Notes\n';
        csv += 'Example Tool,TOOL-001,Workshop A,Sample tool entry\n';
        
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'tools-template.csv';
        a.click();
    });
    
    $('#vwpm-download-components-template').on('click', function(e) {
        e.preventDefault();
        var csv = 'Component Name,Component Number,Location,Supplier Name,Price,Notes\n';
        csv += 'Example Component,COM-001,Shelf A1,Example Supplier,10.00,Sample component entry\n';
        
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'components-template.csv';
        a.click();
    });
    
    // Export CSV buttons
    $('#vwpm-export-tools').on('click', function(e) {
        e.preventDefault();
        window.location.href = vwpm_ajax.ajax_url + '?action=vwpm_export_tools&nonce=' + vwpm_ajax.nonce;
    });
    
    $('#vwpm-export-components').on('click', function(e) {
        e.preventDefault();
        window.location.href = vwpm_ajax.ajax_url + '?action=vwpm_export_components&nonce=' + vwpm_ajax.nonce;
    });
    
    // Import Tools CSV
    $('#vwpm-import-tools-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileInput = $(this).find('input[type="file"]')[0];
        if (!fileInput.files.length) {
            alert('Please select a CSV file first.');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'vwpm_import_tools');
        formData.append('nonce', vwpm_ajax.nonce);
        formData.append('tools_csv', fileInput.files[0]);
        
        var $button = $(this).find('button[type="submit"]');
        var originalText = $button.text();
        $button.prop('disabled', true).text('Importing...');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Success! Imported ' + response.data.imported + ' tools.');
                    fileInput.value = '';
                } else {
                    alert('Error: ' + (response.data.message || 'Import failed'));
                }
            },
            error: function() {
                alert('Error: Server request failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Import Components CSV
    $('#vwpm-import-components-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileInput = $(this).find('input[type="file"]')[0];
        if (!fileInput.files.length) {
            alert('Please select a CSV file first.');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'vwpm_import_components');
        formData.append('nonce', vwpm_ajax.nonce);
        formData.append('components_csv', fileInput.files[0]);
        
        var $button = $(this).find('button[type="submit"]');
        var originalText = $button.text();
        $button.prop('disabled', true).text('Importing...');
        
        $.ajax({
            url: vwpm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Success! Imported ' + response.data.imported + ' components.');
                    fileInput.value = '';
                } else {
                    alert('Error: ' + (response.data.message || 'Import failed'));
                }
            },
            error: function() {
                alert('Error: Server request failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
