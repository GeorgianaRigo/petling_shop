<?php
/*
 * Οι δύο AJAX ενέργειες του plugin:
 *   1) petling_process_promo  -> ο πελάτης παίρνει κωδικό (claim)
 *   2) petling_process_redeem -> ο συνεργάτης (π.χ. κτηνίατρος) καταναλώνει
 *                                 τον κωδικό στο ραντεβού (redeem)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1) CLAIM: ο πελάτης βάζει email και παίρνει κωδικό
// =========================================================================
add_action( 'wp_ajax_petling_process_promo', 'petling_process_promo_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_promo', 'petling_process_promo_ajax' );

function petling_process_promo_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_promo_nonce' ) ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">Σφάλμα ασφαλείας. Ανανεώστε τη σελίδα.</div>' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'petling_partner_leads';

    $email  = sanitize_email( $_POST['promo_email'] );
    $prefix = sanitize_text_field( strtoupper( $_POST['promo_prefix'] ) );

    if ( ! is_email( $email ) ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">⚠️ Παρακαλώ εισάγετε ένα έγκυρο email.</div>' );
    }

    $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">Άγνωστος συνεργάτης. Επικοινωνήστε μαζί μας.</div>' );
    }

    // Έλεγχος lock-days ΑΝΑ ΣΥΝΕΡΓΑΤΗ (όχι συνολικά ανά email)
    $last = petling_promo_get_last_claim( $email, $prefix );
    if ( $last ) {
        $days_passed = ( current_time( 'timestamp' ) - strtotime( $last->created_at ) ) / 86400;
        if ( $days_passed < $partner['lock_days'] ) {
            $remaining = ceil( $partner['lock_days'] - $days_passed );
            wp_send_json_error( '<div class="ptl-alert ptl-error">🐶 Έχεις ήδη λάβει κωδικό από ' . esc_html( $partner['label'] ) . ' πρόσφατα! Μπορείς να πάρεις νέο σε ' . $remaining . ' μέρες.</div>' );
        }
    }

    $unique_code = petling_promo_generate_code( $prefix );

    // Μόνο οι συνεργάτες τύπου "shop" δημιουργούν πραγματικό WooCommerce
    // coupon (ξοδεύεται στο δικό μας checkout). Οι συνεργάτες τύπου
    // "appointment" (π.χ. κτηνίατρος) παίρνουν μόνο κωδικό-απόδειξη - δεν
    // έχει νόημα coupon στο δικό μας site αφού η έκπτωση γίνεται αλλού.
    if ( 'shop' === $partner['type'] && class_exists( 'WC_Coupon' ) ) {
        $coupon = new WC_Coupon();
        $coupon->set_code( $unique_code );
        $coupon->set_discount_type( 'percent' === $partner['discount_type'] ? 'percent' : 'fixed_cart' );
        $coupon->set_amount( $partner['amount'] );
        if ( ! empty( $partner['min_order'] ) ) {
            $coupon->set_minimum_amount( $partner['min_order'] );
        }
        $coupon->set_individual_use( true );
        $coupon->set_usage_limit( 1 );
        $coupon->set_email_restrictions( array( $email ) );
        $coupon->save();
    }

    // Κάθε claim γίνεται πάντα INSERT (όχι update) - έτσι κρατάμε ιστορικό
    // και ένα email μπορεί να έχει πολλούς ενεργούς κωδικούς από
    // διαφορετικούς συνεργάτες ταυτόχρονα.
    $wpdb->insert( $table, array(
        'email'          => $email,
        'partner_prefix' => $prefix,
        'type'           => $partner['type'],
        'coupon_code'    => $unique_code,
        'status'         => 'active',
        'created_at'     => current_time( 'mysql' ),
    ) );

    petling_promo_send_claim_email( $email, $partner, $unique_code );

    $success_html = '<div class="ptl-alert ptl-success">
        <h3 style="margin-top:0; color:#5b9a68;">🎉 Ο κωδικός σου είναι έτοιμος!</h3>
        <p style="margin-bottom:10px;">Τσέκαρε το email σου για τον κωδικό και τις οδηγίες. Εναλλακτικά, αντέγραψέ τον από εδώ:</p>
        <div class="ptl-coupon-box">' . esc_html( $unique_code ) . '</div><br>
        <a href="' . esc_url( site_url() ) . '" class="ptl-btn-shop">Πάμε για ψώνια! &rarr;</a>
    </div>';

    wp_send_json_success( $success_html );
}

/**
 * Στέλνει το email claim - το κείμενο διαφέρει ανάλογα με το αν ο
 * συνεργάτης είναι τύπου "shop" (έκπτωση στο site μας) ή "appointment"
 * (έκπτωση σε δική του υπηρεσία, π.χ. ραντεβού με την κτηνίατρο).
 */
function petling_promo_send_claim_email( $email, $partner, $unique_code ) {
    $amount_text = petling_promo_format_amount( $partner );
    $partner_name = esc_html( $partner['label'] );

    if ( 'appointment' === $partner['type'] ) {
        $subject = 'Ο κωδικός σου για ραντεβού με ' . $partner_name . ' 🐾';
        $heading = 'Ο κωδικός σου είναι έτοιμος!';
        $email_body = "<p>Γεια σου! 🐾</p>
        <p>Λάβαμε το αίτημά σου. Ο παρακάτω κωδικός σου δίνει έκπτωση <strong>{$amount_text}</strong> στο ραντεβού σου με {$partner_name}.</p>
        <div style='background:#f3f4f6; padding:15px; border-left:4px solid #C7B297; margin:20px 0;'>
            <strong>Ο Κωδικός σου:</strong> <span style='font-size:20px; color:#43282F;'>{$unique_code}</span>
        </div>
        <p><strong>Σημαντικό:</strong> κράτησε αυτό το email μέχρι το ραντεβού σου και δείξε τον κωδικό εκεί - είναι η απόδειξή σου. Ο κωδικός είναι προσωπικός, ισχύει για 1 χρήση, και ακυρώνεται αυτόματα μόλις χρησιμοποιηθεί στο ραντεβού.</p>
        <p>Θα χρειαστεί να κλείσεις ραντεβού ξεχωριστά· ο κωδικός απλά εξασφαλίζει την έκπτωσή σου.</p>
        <p>Σε περιμένουμε στο <a href='" . esc_url( site_url() ) . "' style='color:#C7B297; font-weight:bold;'>petling.gr</a>!</p>";
    } else {
        $subject = 'Το δωράκι σου από το Petling & ' . $partner_name . '! 🎁';
        $heading = 'Καλώς ήρθες στην παρέα μας!';
        $min_order_text = ! empty( $partner['min_order'] ) ? " (για αγορές άνω των {$partner['min_order']}€)" : '';
        $email_body = "<p>Γεια σου! 🐾</p>
        <p>Σε ευχαριστούμε που επισκέφθηκες {$partner_name}!</p>
        <p>Πάρε τον παρακάτω κωδικό για να γνωρίσεις την ποιότητα του Petling με έκπτωση <strong>{$amount_text}</strong>{$min_order_text}.</p>
        <div style='background:#f3f4f6; padding:15px; border-left:4px solid #C7B297; margin:20px 0;'>
            <strong>Ο Κωδικός σου:</strong> <span style='font-size:20px; color:#43282F;'>{$unique_code}</span>
        </div>
        <p><em>*Ο κωδικός είναι συνδεδεμένος αυστηρά με το email σου και ισχύει για 1 χρήση. Δεν συνδυάζεται με άλλες προσφορές.</em></p>
        <p>Σε περιμένουμε στο <a href='" . esc_url( site_url() ) . "' style='color:#C7B297; font-weight:bold;'>petling.gr</a>!</p>";
    }

    if ( function_exists( 'wc' ) ) {
        $mailer          = wc()->mailer();
        $wrapped_message = $mailer->wrap_message( $heading, $email_body );
        $mailer->send( $email, $subject, $wrapped_message, array( 'Content-Type: text/html; charset=UTF-8' ) );
    } else {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );
        $headers     = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $site_name . ' <' . $admin_email . '>' );
        wp_mail( $email, $subject, $email_body, $headers );
    }
}

// =========================================================================
// 2) REDEEM: ο συνεργάτης (π.χ. κτηνίατρος) καταναλώνει τον κωδικό
// =========================================================================
add_action( 'wp_ajax_petling_process_redeem', 'petling_process_redeem_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_redeem', 'petling_process_redeem_ajax' );

function petling_process_redeem_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_redeem_nonce' ) ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">Σφάλμα ασφαλείας. Ανανεώστε τη σελίδα.</div>' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'petling_partner_leads';

    $prefix   = sanitize_text_field( strtoupper( $_POST['redeem_prefix'] ) );
    $password = (string) $_POST['redeem_password'];
    $lookup   = sanitize_text_field( trim( $_POST['redeem_lookup'] ) ); // email ή κωδικός

    $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner || 'appointment' !== $partner['type'] ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">Αυτή η σελίδα δεν είναι διαθέσιμη.</div>' );
    }

    if ( empty( $partner['password'] ) || ! hash_equals( (string) $partner['password'], $password ) ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">Λάθος κωδικός πρόσβασης.</div>' );
    }

    if ( empty( $lookup ) ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">Γράψε τον κωδικό ή το email του πελάτη.</div>' );
    }

    // Αναζήτηση είτε με ακριβή κωδικό είτε με email, πάντα μέσα στον ίδιο συνεργάτη
    if ( is_email( $lookup ) ) {
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1",
            sanitize_email( $lookup ), $prefix
        ) );
    } else {
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE coupon_code = %s AND partner_prefix = %s LIMIT 1",
            strtoupper( $lookup ), $prefix
        ) );
    }

    if ( ! $row ) {
        wp_send_json_error( '<div class="ptl-alert ptl-error">Δεν βρέθηκε κωδικός με αυτά τα στοιχεία.</div>' );
    }

    if ( 'redeemed' === $row->status ) {
        $when = date_i18n( 'd/m/Y H:i', strtotime( $row->redeemed_at ) );
        wp_send_json_error( '<div class="ptl-alert ptl-error">⚠️ Αυτός ο κωδικός έχει ήδη χρησιμοποιηθεί (' . esc_html( $when ) . ').</div>' );
    }

    $wpdb->update(
        $table,
        array( 'status' => 'redeemed', 'redeemed_at' => current_time( 'mysql' ) ),
        array( 'id' => $row->id )
    );

    wp_send_json_success( '<div class="ptl-alert ptl-success"><h3 style="margin-top:0; color:#5b9a68;">✅ Ο κωδικός επιβεβαιώθηκε!</h3><p>Κωδικός <strong>' . esc_html( $row->coupon_code ) . '</strong> (' . esc_html( $row->email ) . ') μαρκαρίστηκε ως χρησιμοποιημένος. Δεν μπορεί να ξαναχρησιμοποιηθεί.</p></div>' );
}
