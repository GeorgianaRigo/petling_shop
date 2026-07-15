<?php
/*
Plugin Name: Petling Global Minimum Order
Plugin URI: https://petling.gr
Description: Απαγορεύει την ολοκλήρωση παραγγελίας κάτω από ένα όριο & επιτρέπει εξαιρέσεις (Bypass & Δωρεάν Μεταφορικά) για συγκεκριμένα emails.
Version: 1.2
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. ΠΡΟΣΘΗΚΗ ΥΠΟΜΕΝΟΥ ΣΤΟ ΚΕΝΤΡΙΚΟ "PETLING"
// =========================================================================
add_action( 'admin_menu', 'ptl_min_order_admin_menu' );
function ptl_min_order_admin_menu() {
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'ptl_min_order_admin_page', 'dashicons-pets', 55 );
        add_submenu_page( 'petling-main', 'Ελάχιστη Παραγγελία', 'Ελάχ. Παραγγελία', 'manage_options', 'petling-min-order', 'ptl_min_order_admin_page' );
    } else {
        add_submenu_page( 'petling-main', 'Ελάχιστη Παραγγελία', 'Ελάχ. Παραγγελία', 'manage_options', 'petling-min-order', 'ptl_min_order_admin_page' );
    }
}

// =========================================================================
// 2. Η ΣΕΛΙΔΑ ΡΥΘΜΙΣΕΩΝ ΣΤΟ ΔΙΑΧΕΙΡΙΣΤΙΚΟ
// =========================================================================
function ptl_min_order_admin_page() {
    // Αποθήκευση των ρυθμίσεων
    if ( isset( $_POST['ptl_min_order_save'] ) && wp_verify_nonce( $_POST['ptl_min_order_nonce'], 'ptl_min_order_action' ) ) {
        update_option( 'ptl_global_min_order_amount', floatval( $_POST['ptl_global_min_order_amount'] ) );
        // Αποθηκεύουμε τα emails
        update_option( 'ptl_global_min_order_exempt_emails', sanitize_textarea_field( $_POST['ptl_global_min_order_exempt_emails'] ) );
        
        echo '<div class="notice notice-success is-dismissible"><p>Οι ρυθμίσεις αποθηκεύτηκαν επιτυχώς!</p></div>';
    }

    // Ανάκτηση τρεχουσών τιμών
    $min_order = get_option( 'ptl_global_min_order_amount', 20 );
    $exempt_emails = get_option( 'ptl_global_min_order_exempt_emails', '' );
    ?>
    <div class="wrap">
        <h1 style="color: #43282F; display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
            <span class="dashicons dashicons-cart" style="font-size: 28px; width: 28px; height: 28px; color: #d63638;"></span> 
            Ελάχιστη Παραγγελία Καταστήματος
        </h1>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'ptl_min_order_action', 'ptl_min_order_nonce' ); ?>
            
            <div style="background: #fff; border: 1px solid #ccc; border-left: 4px solid #d63638; padding: 20px; max-width: 700px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
                <h3 style="margin-top: 0;">Γενικός Κανόνας</h3>
                <p style="font-size: 14px;">Ορίστε το ελάχιστο ποσό προϊόντων που πρέπει να έχει ένας πελάτης στο καλάθι του για να μπορέσει να προχωρήσει στο Ταμείο (Checkout).</p>
                
                <table class="form-table">
                    <tr>
                        <th style="width: 150px;">Ελάχιστο Ποσό:</th>
                        <td>
                            <input type="number" step="0.1" name="ptl_global_min_order_amount" value="<?php echo esc_attr( $min_order ); ?>" style="width: 100px;"> €
                        </td>
                    </tr>
                </table>
                <p class="description" style="color: #666;">
                    * Ελέγχεται αποκλειστικά η αξία των προϊόντων (χωρίς μεταφορικά).<br>
                    * Εάν βάλετε 0, ο κανόνας απενεργοποιείται πλήρως.
                </p>
            </div>

            <div style="background: #fff; border: 1px solid #ccc; border-left: 4px solid #2271b1; padding: 20px; max-width: 700px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="margin-top: 0; color: #2271b1;">VIP Εξαιρέσεις (Bypass & Δωρεάν Μεταφορικά)</h3>
                <p style="font-size: 14px;">Τα παρακάτω emails μπορούν να κάνουν παραγγελίες κάτω από το ελάχιστο όριο <strong>ΚΑΙ</strong> δεν χρεώνονται ποτέ μεταφορικά! Ιδανικό για συνεργάτες ή δικά σας τεστ.</p>
                
                <table class="form-table">
                    <tr>
                        <th style="width: 150px;">Λίστα Emails:<br><small>(Ένα ανά γραμμή)</small></th>
                        <td>
                            <textarea name="ptl_global_min_order_exempt_emails" rows="6" style="width: 100%; padding: 10px;" placeholder="test@petling.gr&#10;admin@petling.gr"><?php echo esc_textarea( $exempt_emails ); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p><button type="submit" name="ptl_min_order_save" class="button button-primary button-large">Αποθήκευση Ρυθμίσεων</button></p>
        </form>
    </div>
    <?php
}

// =========================================================================
// HELPER: Βρίσκει το email του πελάτη (Είτε είναι συνδεδεμένος, είτε στο Checkout)
// =========================================================================
function ptl_get_current_checkout_email() {
    $email = '';
    // Αν είναι συνδεδεμένος, παίρνουμε το λογαριασμό του
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
    } 
    // Αν είναι επισκέπτης, παίρνουμε το email που γράφει στο Checkout (το WooCommerce το αποθηκεύει στο session προσωρινά)
    elseif ( null !== WC()->customer ) {
        $email = WC()->customer->get_billing_email();
    }
    return strtolower( trim( $email ) );
}

function ptl_is_email_exempt( $email ) {
    if ( empty( $email ) ) return false;
    $exempt_raw = get_option( 'ptl_global_min_order_exempt_emails', '' );
    if ( empty( trim( $exempt_raw ) ) ) return false;
    
    // Σπάμε τα emails σε πίνακα (ανά αλλαγή γραμμής)
    $exempt_array = array_map( 'trim', explode( "\n", strtolower( $exempt_raw ) ) );
    
    return in_array( $email, $exempt_array );
}

// =========================================================================
// 3. Ο ΚΑΝΟΝΑΣ ΠΟΥ ΜΠΛΟΚΑΡΕΙ ΤΟ CHECKOUT (ΑΚΥΡΩΝΕΤΑΙ ΓΙΑ ΕΞΑΙΡΕΣΕΙΣ)
// =========================================================================
add_action( 'woocommerce_check_cart_items', 'ptl_enforce_global_minimum_order' );
function ptl_enforce_global_minimum_order() {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( is_null( WC()->cart ) ) return;
    
    // Έλεγχος Εξαίρεσης
    $customer_email = ptl_get_current_checkout_email();
    if ( ptl_is_email_exempt( $customer_email ) ) {
        return; // Είναι στη λίστα εξαιρέσεων, οπότε τον αφήνουμε να περάσει ελεύθερα!
    }

    $min_order = get_option( 'ptl_global_min_order_amount', 20 );
    
    if ( $min_order <= 0 ) return;
    
    $subtotal = WC()->cart->get_subtotal(); // Αξία προϊόντων (χωρίς μεταφορικά)
    
    if ( $subtotal < $min_order && $subtotal > 0 ) {
        $diff = $min_order - $subtotal;
        wc_add_notice( 
            sprintf( 'Η ελάχιστη παραγγελία του καταστήματος είναι %s€. Προσθέστε ακόμα <strong>%s€</strong> σε προϊόντα (χωρίς τα μεταφορικά) για να προχωρήσετε στο Ταμείο.', 
                number_format($min_order, 2, ',', '.'), 
                number_format($diff, 2, ',', '.') 
            ), 
            'error' 
        );
    }
}

// =========================================================================
// 4. ΜΗΔΕΝΙΣΜΟΣ ΜΕΤΑΦΟΡΙΚΩΝ ΓΙΑ ΤΙΣ ΕΞΑΙΡΕΣΕΙΣ
// =========================================================================
add_filter( 'woocommerce_package_rates', 'ptl_make_shipping_free_for_exempt_emails', 100, 2 );
function ptl_make_shipping_free_for_exempt_emails( $rates, $package ) {
    $customer_email = ptl_get_current_checkout_email();
    
    if ( ptl_is_email_exempt( $customer_email ) ) {
        // Αν το email είναι στη λίστα, διατρέχουμε όλους τους τρόπους αποστολής (BoxNow, Courier κλπ)
        foreach ( $rates as $rate_key => $rate ) {
            // Μηδενίζουμε το κόστος
            $rates[$rate_key]->cost = 0;
            // Μηδενίζουμε και τους φόρους των μεταφορικών
            $rates[$rate_key]->taxes = array();
        }
    }
    
    return $rates;
}