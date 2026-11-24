<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function petling_promo_get_partners() { return get_option( 'petling_promo_partners', array() ); }
function petling_promo_get_partner( $prefix ) {
    $prefix = strtoupper( trim( $prefix ) );
    foreach ( petling_promo_get_partners() as $partner ) {
        if ( strtoupper( $partner['prefix'] ) === $prefix ) { return $partner; }
    }
    return null;
}
function petling_promo_format_amount( $partner ) {
    return ( 'percent' === $partner['discount_type'] ) ? '-' . floatval( $partner['amount'] ) . '%' : '-' . floatval( $partner['amount'] ) . '€';
}
function petling_promo_generate_code( $prefix ) {
    return strtoupper( $prefix ) . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 5 ) );
}
function petling_promo_get_last_claim( $email, $prefix ) {
    global $wpdb;
    $table = $wpdb->prefix . 'petling_partner_leads';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1", $email, strtoupper( $prefix ) ) );
}

function ptl_enqueue_promo_assets() {
    wp_enqueue_style( 'ptl-promo-css', PTL_CORE_URL . 'assets/css/promo.css', array(), PTL_CORE_VERSION );
    wp_enqueue_script( 'ptl-promo-js', PTL_CORE_URL . 'assets/js/promo.js', array( 'jquery' ), PTL_CORE_VERSION, true );
    wp_localize_script( 'ptl-promo-js', 'petlingPromo', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
}

add_action( 'admin_init', 'petling_promo_handle_admin_actions' );
function petling_promo_handle_admin_actions() {
    if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'ptl-promo-settings', 'ptl-promo-qrcode' ) ) ) return;

    if ( isset( $_POST['petling_promo_save_partners'] ) && isset( $_POST['petling_promo_partners_nonce'] ) && wp_verify_nonce( $_POST['petling_promo_partners_nonce'], 'petling_promo_save_partners_action' ) ) {
        $prefixes = isset( $_POST['p_prefix'] ) ? (array) $_POST['p_prefix'] : array();
        $new_partners = array();
        foreach ( $prefixes as $i => $prefix ) {
            $prefix = strtoupper( sanitize_text_field( $prefix ) );
            if ( '' === $prefix || isset( $_POST['p_delete'][ $i ] ) ) continue;
            $new_partners[] = array(
                'prefix'        => $prefix,
                'label'         => sanitize_text_field( $_POST['p_label'][ $i ] ?? $prefix ),
                'type'          => in_array( $_POST['p_type'][ $i ] ?? '', array( 'shop', 'appointment' ), true ) ? $_POST['p_type'][ $i ] : 'shop',
                'discount_type' => in_array( $_POST['p_discount_type'][ $i ] ?? '', array( 'fixed', 'percent' ), true ) ? $_POST['p_discount_type'][ $i ] : 'fixed',
                'amount'        => floatval( $_POST['p_amount'][ $i ] ?? 0 ),
                'min_order'     => floatval( $_POST['p_min_order'][ $i ] ?? 0 ),
                'lock_days'     => intval( $_POST['p_lock_days'][ $i ] ?? 20 ),
                'password'      => sanitize_text_field( $_POST['p_password'][ $i ] ?? '' )
            );
        }
        update_option( 'petling_promo_partners', $new_partners );
        wp_redirect( admin_url( 'admin.php?page=ptl-promo-settings&saved=1' ) ); exit;
    }
}

function ptl_render_settings_page() {
    $partners = petling_promo_get_partners();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Petling CRM - Ρυθμίσεις</h1>
        <hr class="wp-header-end">
        <?php ptl_render_admin_tabs( 'settings' ); ?>
        <?php if ( isset( $_GET['saved'] ) ) echo '<div class="notice notice-success" style="padding:12px;"><strong>Αποθηκεύτηκε!</strong> Οι ρυθμίσεις συνεργατών ενημερώθηκαν.</div>'; ?>
        <div class="notice notice-info" style="padding: 15px; margin-bottom: 25px; border-left-color: #C7B297;">
            <h3 style="margin-top: 0;">💡 Οδηγίες Χρήσης (Shortcodes)</h3>
            <p><strong>Φόρμα Πελάτη (Για E-shop/Groomer):</strong> <code>[petling_partner_promo prefix="JOY"]</code></p>
            <p><strong>Σελίδα Ελέγχου/Εξαργύρωσης Κτηνιάτρου:</strong> <code>[petling_partner_redeem prefix="VET"]</code></p>
        </div>
        <form method="post" action=""><?php wp_nonce_field( 'petling_promo_save_partners_action', 'petling_promo_partners_nonce' ); ?>
        <table class="widefat striped" style="background:#fff;"><thead><tr><th>Prefix</th><th>Ετικέτα (όνομα)</th><th>Τύπος</th><th>Έκπτωση</th><th>Ποσό</th><th>Ελάχ. παραγγελία (€)</th><th>Μέρες αναμονής</th><th>Password (Ραντεβού)</th><th>Διαγραφή</th></tr></thead><tbody>
        <?php
        $rows = $partners;
        $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' );
        $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' );
        foreach ( $rows as $i => $p ) : ?>
            <tr>
                <td><input type="text" name="p_prefix[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['prefix'] ); ?>" style="width:70px;"></td>
                <td><input type="text" name="p_label[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['label'] ); ?>" style="width:150px;"></td>
                <td><select name="p_type[<?php echo $i; ?>]"><option value="shop" <?php selected( $p['type'], 'shop' ); ?>>Shop</option><option value="appointment" <?php selected( $p['type'], 'appointment' ); ?>>Ραντεβού</option></select></td>
                <td><select name="p_discount_type[<?php echo $i; ?>]"><option value="fixed" <?php selected( $p['discount_type'], 'fixed' ); ?>>Σταθερό (€)</option><option value="percent" <?php selected( $p['discount_type'], 'percent' ); ?>>Ποσοστό (%)</option></select></td>
                <td><input type="number" step="0.5" name="p_amount[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['amount'] ); ?>" style="width:70px;"></td>
                <td><input type="number" step="1" name="p_min_order[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['min_order'] ); ?>" style="width:70px;"></td>
                <td><input type="number" step="1" name="p_lock_days[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['lock_days'] ); ?>" style="width:70px;"></td>
                <td><input type="text" name="p_password[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['password'] ); ?>" style="width:110px;"></td>
                <td style="text-align:center;"><input type="checkbox" name="p_delete[<?php echo $i; ?>]" value="1"></td>
            </tr>
        <?php endforeach; ?></tbody></table>
        <br><?php submit_button( 'Αποθήκευση Συνεργατών', 'primary', 'petling_promo_save_partners' ); ?></form>
    </div>
    <?php
}

function ptl_render_qrcode_page() {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Petling CRM - QR Code</h1>
        <hr class="wp-header-end">
        <?php ptl_render_admin_tabs( 'qrcode' ); ?>
        <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:25px; max-width: 600px; margin-top:20px;">
            <h3 style="margin-top:0; color:#43282F;">📱 Γεννήτρια QR Code</h3>
            <table class="form-table">
                <tr><th scope="row">Όνομα Συνεργάτη</th><td><input type="text" id="qr_partner_name" class="regular-text"></td></tr>
                <tr><th scope="row">URL Σελίδας</th><td><input type="url" id="qr_partner_url" class="regular-text" placeholder="https://petling.gr/joy"></td></tr>
            </table>
            <p style="margin-top:20px;"><button type="button" class="button button-primary" onclick="generatePetlingQRCode()">Δημιουργία QR Code</button></p>
            <div id="qr_result_area" style="display:none; margin-top: 30px; text-align: center;">
                <canvas id="qr_canvas" style="border: 1px solid #eee; padding: 10px; border-radius: 8px; background: #fff;"></canvas><br><br>
                <a id="qr_download_btn" class="button button-secondary" download="qr-code.png">⬇️ Λήψη σε μορφή PNG</a>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <script>function generatePetlingQRCode() { var url = document.getElementById("qr_partner_url").value; var name = document.getElementById("qr_partner_name").value; if (!url) { alert("Παρακαλώ εισάγετε URL!"); return; } var qr = new QRious({ element: document.getElementById("qr_canvas"), value: url, size: 300, level: "H" }); document.getElementById("qr_result_area").style.display = "block"; var canvas = document.getElementById("qr_canvas"); var dataURL = canvas.toDataURL("image/png"); var dlBtn = document.getElementById("qr_download_btn"); dlBtn.href = dataURL; dlBtn.download = "qr-petling-" + (name ? name.toLowerCase().replace(/[^a-z0-9]/g, "-") : "partner") + ".png"; }</script>
    <?php
}

// =========================================================================
// AJAX & SHORTCODES (PROMO)
// =========================================================================
add_action( 'wp_ajax_petling_process_promo', 'petling_process_promo_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_promo', 'petling_process_promo_ajax' );
function petling_process_promo_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_promo_nonce' ) ) wp_send_json_error( '<div class="ptl-alert ptl-error">Σφάλμα ασφαλείας.</div>' );
    global $wpdb;
    $table  = $wpdb->prefix . 'petling_partner_leads';
    $email  = sanitize_email( $_POST['promo_email'] );
    $prefix = sanitize_text_field( strtoupper( $_POST['promo_prefix'] ) );

    if ( ! is_email( $email ) ) wp_send_json_error( '<div class="ptl-alert ptl-error">⚠️ Μη έγκυρο email.</div>' );
    $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner ) wp_send_json_error( '<div class="ptl-alert ptl-error">Άγνωστος συνεργάτης.</div>' );

    $last = petling_promo_get_last_claim( $email, $prefix );
    if ( $last ) {
        $days_passed = ( current_time( 'timestamp' ) - strtotime( $last->created_at ) ) / 86400;
        if ( $days_passed < $partner['lock_days'] ) {
            wp_send_json_error( '<div class="ptl-alert ptl-error">🐶 Έχεις ήδη λάβει κωδικό πρόσφατα!</div>' );
        }
    }

    $unique_code = petling_promo_generate_code( $prefix );
    if ( 'shop' === $partner['type'] && class_exists( 'WC_Coupon' ) ) {
        $coupon = new WC_Coupon();
        $coupon->set_code( $unique_code );
        $coupon->set_discount_type( 'percent' === $partner['discount_type'] ? 'percent' : 'fixed_cart' );
        $coupon->set_amount( $partner['amount'] );
        if ( ! empty( $partner['min_order'] ) ) $coupon->set_minimum_amount( $partner['min_order'] );
        $coupon->set_individual_use( true );
        $coupon->set_usage_limit( 1 );
        $coupon->set_email_restrictions( array( $email ) );
        $coupon->save();
    }

    $wpdb->insert( $table, array( 'email' => $email, 'partner_prefix' => $prefix, 'type' => $partner['type'], 'coupon_code' => $unique_code, 'status' => 'active', 'created_at' => current_time( 'mysql' ) ) );
    ptl_save_quiz_lead_data( $email, '-', 'PROMO_' . $prefix, $unique_code );

    $amount_text  = petling_promo_format_amount( $partner );
    $partner_name = esc_html( $partner['label'] );
    if ( 'appointment' === $partner['type'] ) {
        $subject    = 'Ο κωδικός σου για ραντεβού 🐾';
        $email_body = "<p>Γεια σου! 🐾 Κέρδισες έκπτωση <strong>{$amount_text}</strong> στο ραντεβού σου με {$partner_name}.</p><p><strong>Ο Κωδικός σου:</strong> <span style='font-size:20px;'>{$unique_code}</span></p>";
    } else {
        $subject    = 'Το δωράκι σου σε περιμένει! 🎁';
        $email_body = "<p>Γεια σου! 🐾 Κέρδισες έκπτωση <strong>{$amount_text}</strong> για τις αγορές σου στο petling.gr.</p><p><strong>Ο Κωδικός σου:</strong> <span style='font-size:20px;'>{$unique_code}</span></p>";
    }
    wp_mail( $email, $subject, $email_body, array( 'Content-Type: text/html; charset=UTF-8' ) );

    wp_send_json_success( '<div class="ptl-alert ptl-success"><h3 style="margin-top:0; color:#5b9a68;">🎉 Ο κωδικός σου είναι έτοιμος!</h3><div class="ptl-coupon-box">' . esc_html( $unique_code ) . '</div><p>Στάλθηκε και στο email σου!</p></div>' );
}

add_action( 'wp_ajax_petling_process_redeem', 'petling_process_redeem_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_redeem', 'petling_process_redeem_ajax' );
function petling_process_redeem_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_redeem_nonce' ) ) wp_send_json_error( '<div class="ptl-alert ptl-error">Σφάλμα ασφαλείας.</div>' );
    global $wpdb;
    $table    = $wpdb->prefix . 'petling_partner_leads';
    $prefix   = sanitize_text_field( strtoupper( $_POST['redeem_prefix'] ) );
    $password = (string) $_POST['redeem_password'];
    $lookup   = sanitize_text_field( trim( $_POST['redeem_lookup'] ) );

    $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner || 'appointment' !== $partner['type'] ) wp_send_json_error( '<div class="ptl-alert ptl-error">Μη διαθέσιμο.</div>' );
    if ( empty( $partner['password'] ) || ! hash_equals( (string) $partner['password'], $password ) ) wp_send_json_error( '<div class="ptl-alert ptl-error">Λάθος κωδικός πρόσβασης.</div>' );

    if ( is_email( $lookup ) ) {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1", sanitize_email( $lookup ), $prefix ) );
    } else {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE coupon_code = %s AND partner_prefix = %s LIMIT 1", strtoupper( $lookup ), $prefix ) );
    }
    if ( ! $row ) wp_send_json_error( '<div class="ptl-alert ptl-error">Δεν βρέθηκε κωδικός.</div>' );
    if ( 'redeemed' === $row->status ) wp_send_json_error( '<div class="ptl-alert ptl-error">⚠️ Αυτός ο κωδικός έχει ήδη χρησιμοποιηθεί.</div>' );

    $wpdb->update( $table, array( 'status' => 'redeemed', 'redeemed_at' => current_time( 'mysql' ) ), array( 'id' => $row->id ) );
    wp_send_json_success( '<div class="ptl-alert ptl-success">✅ Ο κωδικός επιβεβαιώθηκε επιτυχώς!</div>' );
}

add_shortcode( 'petling_partner_promo', 'petling_partner_promo_shortcode' );
function petling_partner_promo_shortcode( $atts ) {
    ptl_enqueue_promo_assets();
    $a       = shortcode_atts( array( 'prefix' => 'PTL' ), $atts );
    $prefix  = sanitize_text_field( strtoupper( $a['prefix'] ) );
    $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner ) return '<p style="color:#e62121;">Άγνωστος συνεργάτης.</p>';

    $amount_text  = petling_promo_format_amount( $partner );
    $partner_name = esc_html( $partner['label'] );
    ob_start();
    ?>
    <div class="ptl-promo-container">
        <div class="ptl-promo-inner">
            <h3>🎁 Πάρε την Έκπτωσή σου!</h3>
            <p>Βάλε το email σου για να λάβεις τον κωδικό <strong><?php echo esc_html( $amount_text ); ?></strong> από <?php echo $partner_name; ?>!</p>
            <form class="ptl-promo-form">
                <?php wp_nonce_field( 'petling_promo_nonce', 'security', true, true ); ?>
                <input type="hidden" name="promo_prefix" value="<?php echo esc_attr( $prefix ); ?>">
                <div class="ptl-message-area"></div>
                <input type="email" name="promo_email" placeholder="Το email σου εδώ..." required>
                <button type="submit" class="ptl-btn-submit">Λήψη Κωδικού</button>
                <div class="ptl-loader">⏳ Γίνεται επεξεργασία...</div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'petling_partner_redeem', 'petling_partner_redeem_shortcode' );
function petling_partner_redeem_shortcode( $atts ) {
    ptl_enqueue_promo_assets();
    $a       = shortcode_atts( array( 'prefix' => '' ), $atts );
    $prefix  = sanitize_text_field( strtoupper( $a['prefix'] ) );
    $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner || 'appointment' !== $partner['type'] ) return '<p style="color:#e62121;">Μη διαθέσιμη σελίδα.</p>';
    ob_start();
    ?>
    <div class="ptl-redeem-container">
        <h3>🔒 Έλεγχος &amp; Εξαργύρωση</h3>
        <form class="ptl-redeem-form">
            <?php wp_nonce_field( 'petling_redeem_nonce', 'security', true, true ); ?>
            <input type="hidden" name="redeem_prefix" value="<?php echo esc_attr( $prefix ); ?>">
            <div class="ptl-message-area"></div>
            <input type="password" name="redeem_password" placeholder="Κωδικός πρόσβασης" required>
            <input type="text" name="redeem_lookup" placeholder="Κωδικός (VET-...) ή email πελάτη" required>
            <button type="submit" class="ptl-btn-submit ptl-btn-redeem">Επιβεβαίωση</button>
            <div class="ptl-loader">⏳ Έλεγχος...</div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}