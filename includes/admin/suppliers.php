<?php
// Suppliers admin page for VW Parts Manager (combined, non-AJAX + modal edit)
// Place this file at: includes/admin/suppliers.php
// This version supports: add (POST), edit (POST via modal), delete (GET with nonce).
// It expects the suppliers DB table to have a `notes` column. If not, run the table migration provided earlier.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'vwpm_suppliers';
$notices = array();

/*
 * Handle Add Supplier (non-AJAX)
 */
if ( isset( $_POST['vwpm_add_supplier'] ) && check_admin_referer( 'vwpm_add_supplier', 'vwpm_supplier_nonce' ) ) {
    $name    = isset( $_POST['supplier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier_name'] ) ) : '';
    $email   = isset( $_POST['supplier_email'] ) ? sanitize_email( wp_unslash( $_POST['supplier_email'] ) ) : '';
    $contact = isset( $_POST['supplier_contact'] ) ? sanitize_textarea_field( wp_unslash( $_POST['supplier_contact'] ) ) : '';
    $notes   = isset( $_POST['supplier_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['supplier_notes'] ) ) : '';

    $wpdb->insert(
        $table_name,
        array(
            'name'            => $name,
            'email'           => $email,
            'contact_details' => $contact,
            'notes'           => $notes,
        ),
        array( '%s', '%s', '%s', '%s' )
    );

    if ( $wpdb->insert_id ) {
        $notices[] = '<div class="notice notice-success"><p>Supplier added successfully.</p></div>';
    } else {
        $notices[] = '<div class="notice notice-error"><p>Failed to add supplier. Check database permissions.</p></div>';
    }
}

/*
 * Handle Update Supplier (non-AJAX, modal form posts here)
 */
if ( isset( $_POST['vwpm_update_supplier'] ) && check_admin_referer( 'vwpm_update_supplier', 'vwpm_update_supplier_nonce' ) ) {
    $supplier_id = isset( $_POST['supplier_id'] ) ? intval( $_POST['supplier_id'] ) : 0;
    $name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $contact     = isset( $_POST['contact_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['contact_details'] ) ) : '';
    $notes       = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

    if ( $supplier_id > 0 ) {
        $updated = $wpdb->update(
            $table_name,
            array(
                'name'            => $name,
                'email'           => $email,
                'contact_details' => $contact,
                'notes'           => $notes,
            ),
            array( 'id' => $supplier_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( false !== $updated ) {
            $notices[] = '<div class="notice notice-success"><p>Supplier updated successfully.</p></div>';
        } else {
            $notices[] = '<div class="notice notice-error"><p>Failed to update supplier.</p></div>';
        }
    } else {
        $notices[] = '<div class="notice notice-error"><p>Invalid supplier ID.</p></div>';
    }
}

/*
 * Handle Delete Supplier (GET with nonce)
 */
if ( isset( $_GET['delete_supplier'] ) ) {
    $del_id = intval( $_GET['delete_supplier'] );
    if ( $del_id > 0 && check_admin_referer( 'vwpm_delete_supplier_' . $del_id ) ) {
        $deleted = $wpdb->delete( $table_name, array( 'id' => $del_id ), array( '%d' ) );
        if ( $deleted ) {
            $notices[] = '<div class="notice notice-success"><p>Supplier deleted successfully.</p></div>';
        } else {
            $notices[] = '<div class="notice notice-error"><p>Failed to delete supplier.</p></div>';
        }
    } else {
        $notices[] = '<div class="notice notice-error"><p>Invalid delete request.</p></div>';
    }
}

/*
 * Load suppliers for display
 */
$suppliers = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY name ASC" );
?>

<div class="wrap">
    <h1>VWPM Suppliers</h1>

    <?php
    // display admin notices
    foreach ( $notices as $n ) {
        echo $n;
    }
    ?>

    <div style="display:flex; gap: 30px; align-items:flex-start;">

        <!-- Add New Supplier -->
        <div style="flex: 0 0 420px; background:#fff; padding:18px; border:1px solid #e1e1e1; border-radius:4px;">
            <h2>Add New Supplier</h2>
            <form method="post" id="vwpm-add-supplier-form">
                <?php wp_nonce_field( 'vwpm_add_supplier', 'vwpm_supplier_nonce' ); ?>

                <p>
                    <label for="supplier_name"><strong>Supplier Name *</strong></label><br>
                    <input type="text" id="supplier_name" name="supplier_name" required style="width:100%;">
                </p>

                <p>
                    <label for="supplier_email">Email</label><br>
                    <input type="email" id="supplier_email" name="supplier_email" style="width:100%;">
                </p>

                <p>
                    <label for="supplier_contact">Contact Details</label><br>
                    <textarea id="supplier_contact" name="supplier_contact" rows="3" style="width:100%;"></textarea>
                </p>

                <p>
                    <label for="supplier_notes">Notes</label><br>
                    <textarea id="supplier_notes" name="supplier_notes" rows="4" style="width:100%;"></textarea>
                </p>

                <p>
                    <button type="submit" name="vwpm_add_supplier" class="button button-primary">Add Supplier</button>
                </p>
            </form>
        </div>

        <!-- Suppliers List -->
        <div style="flex: 1 1 auto; background:#fff; padding:18px; border:1px solid #e1e1e1; border-radius:4px;">
            <h2>Existing Suppliers (<?php echo intval( count( $suppliers ) ); ?>)</h2>

            <?php if ( $suppliers ) : ?>
                <table class="widefat fixed striped" id="vwpm-suppliers-table">
                    <thead>
                        <tr>
                            <th style="width:24%;">Name</th>
                            <th style="width:18%;">Email</th>
                            <th style="width:30%;">Contact Details</th>
                            <th style="width:18%;">Notes</th>
                            <th style="width:110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $suppliers as $supplier ) : ?>
                            <tr data-supplier-id="<?php echo esc_attr( $supplier->id ); ?>">
                                <td class="vwpm-supplier-name"><?php echo esc_html( $supplier->name ); ?></td>
                                <td class="vwpm-supplier-email">
                                    <?php if ( $supplier->email ) : ?>
                                        <a href="mailto:<?php echo esc_attr( $supplier->email ); ?>"><?php echo esc_html( $supplier->email ); ?></a>
                                    <?php else : ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                                <td class="vwpm-supplier-contact"><?php echo nl2br( esc_html( $supplier->contact_details ) ); ?></td>
                                <td class="vwpm-supplier-notes"><?php echo nl2br( esc_html( $supplier->notes ) ); ?></td>
                               <td>
    <button class="button vwpm-edit-supplier-btn">Edit</button>
    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=vwpm-suppliers&delete_supplier=' . $supplier->id ), 'vwpm_delete_supplier_' . $supplier->id ) ); ?>"
       class="button"
       onclick="return confirm('Are you sure you want to delete this supplier? This cannot be undone.');">Delete</a>
</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No suppliers added yet. Add your first supplier using the form on the left.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal (form posts back to same page) -->
<div id="vwpm-edit-supplier-modal" style="display:none; position:fixed; inset:0; background: rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:18px; width:720px; max-width:95%; border-radius:6px; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
        <h2>Edit Supplier</h2>
        <form method="post" id="vwpm-edit-supplier-form">
            <?php wp_nonce_field( 'vwpm_update_supplier', 'vwpm_update_supplier_nonce' ); ?>
            <input type="hidden" name="supplier_id" id="vwpm_supplier_id_edit" value="0">

            <p>
                <label for="vwpm_supplier_name_edit"><strong>Name *</strong></label><br>
                <input type="text" id="vwpm_supplier_name_edit" name="name" required style="width:100%;">
            </p>

            <p>
                <label for="vwpm_supplier_email_edit">Email</label><br>
                <input type="email" id="vwpm_supplier_email_edit" name="email" style="width:100%;">
            </p>

            <p>
                <label for="vwpm_supplier_contact_edit">Contact Details</label><br>
                <textarea id="vwpm_supplier_contact_edit" name="contact_details" rows="3" style="width:100%;"></textarea>
            </p>

            <p>
                <label for="vwpm_supplier_notes_edit">Notes</label><br>
                <textarea id="vwpm_supplier_notes_edit" name="notes" rows="4" style="width:100%;"></textarea>
            </p>

            <p>
                <button type="submit" name="vwpm_update_supplier" class="button button-primary">Save</button>
                <button id="vwpm-cancel-edit-btn" class="button">Cancel</button>
            </p>
        </form>
    </div>
</div>

<style>
/* Small layout tweaks */
.vwpm-admin-wrapper { display:flex; gap:20px; }
#vwpm-edit-supplier-modal { display:flex; }
</style>

<script type="text/javascript">
jQuery(function($){
    // Open edit modal and populate fields
    $(document).on('click', '.vwpm-edit-supplier-btn', function(e){
        e.preventDefault();
        var tr = $(this).closest('tr');
        var id = tr.data('supplier-id');
        var name = tr.find('.vwpm-supplier-name').text().trim();
        var email = tr.find('.vwpm-supplier-email').text().trim();
        var contact = tr.find('.vwpm-supplier-contact').html().replace(/<br\s*\/?>/gi, "\n").trim();
        var notes = tr.find('.vwpm-supplier-notes').html().replace(/<br\s*\/?>/gi, "\n").trim();

        $('#vwpm_supplier_id_edit').val(id);
        $('#vwpm_supplier_name_edit').val(name);
        $('#vwpm_supplier_email_edit').val(email === '–' ? '' : email);
        $('#vwpm_supplier_contact_edit').val(contact);
        $('#vwpm_supplier_notes_edit').val(notes);

        $('#vwpm-edit-supplier-modal').fadeIn(120);
    });

    // Cancel edit
    $('#vwpm-cancel-edit-btn').on('click', function(e){
        e.preventDefault();
        $('#vwpm-edit-supplier-modal').fadeOut(120);
    });

    // Close modal if clicking outside content
    $('#vwpm-edit-supplier-modal').on('click', function(e){
        if (e.target === this) {
            $(this).fadeOut(120);
        }
    });
});
</script>
