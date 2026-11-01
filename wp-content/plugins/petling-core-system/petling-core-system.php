<?php
/*
Plugin Name: Petling Core System
Plugin URI: https://petling.gr
Description: Το κεντρικό, ΕΝΙΑΙΟ σύστημα του Petling. Περιλαμβάνει: Κεντρικό CRM, Διατροφικό Σύμβουλο, Δημιουργία Συνταγής, και το πλήρες σύστημα Promo & QR Συνεργατών.
Version: 4.3
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. ΕΓΚΑΤΑΣΤΑΣΗ ΒΑΣΗΣ ΔΕΔΟΜΕΝΩΝ (Για Promo)
// =========================================================================
function ptl_core_install() {
    global $wpdb;
    $table = $wpdb->prefix . 'petling_partner_leads';
    $installed_ver = get_option( 'petling_promo_db_version', '1.0' );

    if ( version_compare( $installed_ver, '2.0', '<' ) ) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            partner_prefix varchar(20) NOT NULL DEFAULT '',
            type varchar(20) NOT NULL DEFAULT 'shop',
            coupon_code varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            redeemed_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY coupon_code (coupon_code),
            KEY email_partner (email, partner_prefix)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( false === get_option( 'petling_promo_partners', false ) ) {
            update_option( 'petling_promo_partners', array(
                array( 'prefix' => 'JOY', 'label' => 'Joy (Groomer)', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => 2, 'min_order' => 20, 'lock_days' => 20, 'password' => '' ),
                array( 'prefix' => 'VET', 'label' => 'Δρ. Μανωλάκου (VET)', 'type' => 'appointment', 'discount_type' => 'percent', 'amount' => 10, 'min_order' => 0, 'lock_days' => 30, 'password' => wp_generate_password( 8, false ) ),
            ) );
        }
        update_option( 'petling_promo_db_version', '2.0' );
    }
}
add_action( 'plugins_loaded', 'ptl_core_install' );


// =========================================================================
// 2. CUSTOM POST TYPE: QUIZ LEADS & ΕΝΙΑΙΟ ADMIN MENU
// =========================================================================
add_action('init', 'ptl_register_leads_cpt');
function ptl_register_leads_cpt() {
    register_post_type('ptl_quiz_lead', array(
        'labels' => array( 'name' => 'Petling CRM', 'singular_name' => 'Lead', 'all_items' => 'Λίστα Πελατών' ),
        'public' => false, 
        'show_ui' => true, 
        'show_in_menu' => false, 
        'supports' => array('title'),
        'capabilities' => array( 'create_posts' => 'do_not_allow' ),
        'map_meta_cap' => true
    ));
}

add_action( 'admin_menu', 'petling_core_unified_admin_menu' );
function petling_core_unified_admin_menu() {
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        // Η Κεντρική Πατούσα
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'ptl_core_fallback_page', 'dashicons-pets', 55 );
        
        // Το ΜΟΝΑΔΙΚΟ ορατό μενού (Λίστα Πελατών)
        add_submenu_page( 'petling-main', 'Λίστα Πελατών', 'Λίστα Πελατών', 'manage_options', 'edit.php?post_type=ptl_quiz_lead' );
        remove_submenu_page( 'petling-main', 'petling-main' );
    }
    
    // Κρυφές σελίδες για τα Tabs
    add_submenu_page( null, 'Ρυθμίσεις Promo', 'Ρυθμίσεις', 'manage_options', 'ptl-promo-settings', 'ptl_render_settings_page' );
    add_submenu_page( null, 'QR Code', 'QR Code', 'manage_options', 'ptl-promo-qrcode', 'ptl_render_qrcode_page' );
}

function ptl_core_fallback_page() { 
    echo '<div class="wrap"><h1 style="color:#43282F;"><span class="dashicons dashicons-pets" style="font-size:32px; width:32px; height:32px;"></span> Petling Control Panel</h1><p>Επιλέξτε τη Λίστα Πελατών από το μενού στα αριστερά.</p></div>'; 
}


// =========================================================================
// 3. TABS ΠΛΟΗΓΗΣΗΣ CRM 
// =========================================================================
function ptl_render_admin_tabs( $active_tab = 'leads' ) {
    ?>
    <h2 class="nav-tab-wrapper" style="margin-bottom: 15px;">
        <a href="edit.php?post_type=ptl_quiz_lead" class="nav-tab <?php echo 'leads' === $active_tab ? 'nav-tab-active' : ''; ?>">Εγγεγραμμένοι Πελάτες (CRM)</a>
        <a href="admin.php?page=ptl-promo-settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">Ρυθμίσεις Συνεργατών</a>
        <a href="admin.php?page=ptl-promo-qrcode" class="nav-tab <?php echo 'qrcode' === $active_tab ? 'nav-tab-active' : ''; ?>">Δημιουργία QR Code</a>
    </h2>
    <?php
}

add_filter( 'views_edit-ptl_quiz_lead', 'ptl_add_tabs_to_cpt_lead_views' );
function ptl_add_tabs_to_cpt_lead_views( $views ) {
    ptl_render_admin_tabs('leads');
    return $views;
}


// =========================================================================
// 4. CRM LEADS (ΛΙΣΤΑ ΠΕΛΑΤΩΝ) - ΣΤΗΛΕΣ ΚΑΙ ΛΕΙΤΟΥΡΓΙΕΣ
// =========================================================================
add_filter('manage_ptl_quiz_lead_posts_columns', 'ptl_set_custom_lead_columns');
function ptl_set_custom_lead_columns($columns) {
    return array( 'cb' => $columns['cb'], 'title' => 'Email Χρήστη', 'ptl_name' => 'Όνοματεπώνυμο', 'ptl_type' => 'Πηγή (Quiz / Συνεργάτης)', 'ptl_result' => 'Αποτέλεσμα / Κωδικός', 'ptl_status' => 'Κατάσταση', 'date' => $columns['date'] );
}

add_action('manage_ptl_quiz_lead_posts_custom_column', 'ptl_custom_lead_column', 10, 2);
function ptl_custom_lead_column($column, $post_id) {
    switch ($column) {
        case 'ptl_name': echo esc_html(get_post_meta($post_id, 'ptl_lead_name', true)); break;
        case 'ptl_type':
            $type = get_post_meta($post_id, 'ptl_lead_type', true);
            if ($type === 'VET_MANOLAKOU') { echo '<span style="color: #d63638; font-weight:bold;">Δρ. Μανωλάκου (VET)</span>'; } 
            elseif ($type === 'HOME_COOKED_RECIPE') { echo '<span style="color: #46b450; font-weight:bold;">Μαγειρευτή Συνταγή</span>'; }
            elseif ($type === 'FOOD_YOGGIES') { echo '<span style="color: #2271b1; font-weight:bold;">Πρόταση Τροφής (Yoggies)</span>'; }
            elseif (strpos($type, 'PROMO_') === 0) { 
                $partner_prefix = str_replace('PROMO_', '', $type);
                echo '<span style="color: #ff9800; font-weight:bold;">Συνεργάτης (' . esc_html($partner_prefix) . ')</span>'; 
            }
            else { echo '<span style="color: #666; font-weight:bold;">' . esc_html($type) . '</span>'; }
            break;
        case 'ptl_result':
            echo esc_html(get_post_meta($post_id, 'ptl_lead_result', true));
            $condition = get_post_meta($post_id, 'ptl_health_condition', true);
            $notes = get_post_meta($post_id, 'ptl_health_notes', true);
            if (!empty($condition) || !empty($notes)) {
                echo '<div style="margin-top:8px; padding:8px; background:#fff3f3; border-left:3px solid #d63638; font-size:12px; border-radius:4px;">';
                if (!empty($condition)) echo '<strong>🩺 Πάθηση:</strong> ' . esc_html($condition) . '<br>';
                if (!empty($notes)) echo '<strong style="display:inline-block; margin-top:4px;">📝 Ιστορικό:</strong><br>' . nl2br(esc_html($notes));
                echo '</div>';
            }
            break;
        case 'ptl_status':
            global $wpdb;
            $email = get_the_title($post_id);
            $promo_table = $wpdb->prefix . 'petling_partner_leads';
            if ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table) {
                $promo = $wpdb->get_row($wpdb->prepare("SELECT status FROM $promo_table WHERE email = %s ORDER BY created_at DESC LIMIT 1", $email));
                if ($promo) {
                    if ($promo->status === 'redeemed') { echo '<span style="display:inline-block; padding:4px 8px; background:#f0f0f0; color:#555; border-radius:4px; font-size:11px; border:1px solid #ccc;">✔️ Εξαργυρώθηκε</span>'; } 
                    else { echo '<span style="display:inline-block; padding:4px 8px; background:#eef7ee; color:#5b9a68; border-radius:4px; font-size:11px; border:1px solid #5b9a68;">🟢 Ενεργό Κουπόνι</span>'; }
                } else { echo '<span style="color:#999;">-</span>'; }
            }
            break;
    }
}

function ptl_save_quiz_lead_data($email, $name, $type, $result) {
    $existing_post = get_page_by_title($email, OBJECT, 'ptl_quiz_lead');
    $post_id = $existing_post ? $existing_post->ID : wp_insert_post(array('post_title' => $email, 'post_type' => 'ptl_quiz_lead', 'post_status' => 'publish'));
    update_post_meta($post_id, 'ptl_lead_name', sanitize_text_field($name));
    update_post_meta($post_id, 'ptl_lead_type', sanitize_text_field($type));
    update_post_meta($post_id, 'ptl_lead_result', sanitize_text_field($result));
    return $post_id;
}


// =========================================================================
// 5. ΛΕΙΤΟΥΡΓΙΕΣ PROMO (ΡΥΘΜΙΣΕΙΣ & QR CODE)
// =========================================================================
function petling_promo_get_partners() { return get_option( 'petling_promo_partners', array() ); }
function petling_promo_get_partner( $prefix ) { $prefix = strtoupper( trim( $prefix ) ); foreach ( petling_promo_get_partners() as $partner ) { if ( strtoupper( $partner['prefix'] ) === $prefix ) { return $partner; } } return null; }
function petling_promo_format_amount( $partner ) { return ( 'percent' === $partner['discount_type'] ) ? '-' . floatval( $partner['amount'] ) . '%' : '-' . floatval( $partner['amount'] ) . '€'; }
function petling_promo_generate_code( $prefix ) { return strtoupper( $prefix ) . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 5 ) ); }
function petling_promo_get_last_claim( $email, $prefix ) { global $wpdb; $table = $wpdb->prefix . 'petling_partner_leads'; return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1", $email, strtoupper( $prefix ) ) ); }

add_action( 'admin_init', 'petling_promo_handle_admin_actions' );
function petling_promo_handle_admin_actions() {
    if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array('ptl-promo-settings', 'ptl-promo-qrcode') ) ) return;

    if ( isset( $_POST['petling_promo_save_partners'] ) && isset( $_POST['petling_promo_partners_nonce'] ) && wp_verify_nonce( $_POST['petling_promo_partners_nonce'], 'petling_promo_save_partners_action' ) ) {
        $prefixes = isset( $_POST['p_prefix'] ) ? (array) $_POST['p_prefix'] : array(); $new_partners = array();
        foreach ( $prefixes as $i => $prefix ) {
            $prefix = strtoupper( sanitize_text_field( $prefix ) );
            if ( '' === $prefix || isset( $_POST['p_delete'][ $i ] ) ) continue;
            $new_partners[] = array( 
                'prefix' => $prefix, 
                'label' => sanitize_text_field( $_POST['p_label'][ $i ] ?? $prefix ), 
                'type' => in_array( $_POST['p_type'][ $i ] ?? '', array( 'shop', 'appointment' ), true ) ? $_POST['p_type'][ $i ] : 'shop', 
                'discount_type' => in_array( $_POST['p_discount_type'][ $i ] ?? '', array( 'fixed', 'percent' ), true ) ? $_POST['p_discount_type'][ $i ] : 'fixed', 
                'amount' => floatval( $_POST['p_amount'][ $i ] ?? 0 ), 
                'min_order' => floatval( $_POST['p_min_order'][ $i ] ?? 0 ), 
                'lock_days' => intval( $_POST['p_lock_days'][ $i ] ?? 20 ), 
                'password' => sanitize_text_field( $_POST['p_password'][ $i ] ?? '' ) 
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
        <?php ptl_render_admin_tabs('settings'); ?>
        
        <?php if ( isset( $_GET['saved'] ) ) echo '<div class="notice notice-success" style="padding:12px;"><strong>Αποθηκεύτηκε!</strong> Οι ρυθμίσεις συνεργατών ενημερώθηκαν.</div>'; ?>
        
        <div class="notice notice-info" style="padding: 15px; margin-bottom: 25px; border-left-color: #C7B297;">
            <h3 style="margin-top: 0;">💡 Οδηγίες Χρήσης (Shortcodes)</h3>
            <p><strong>Φόρμα Πελάτη (Για E-shop/Groomer):</strong> <code>[petling_partner_promo prefix="JOY"]</code></p>
            <p><strong>Σελίδα Ελέγχου/Εξαργύρωσης Κτηνιάτρου:</strong> <code>[petling_partner_redeem prefix="VET"]</code></p>
        </div>
        <form method="post" action=""><?php wp_nonce_field( 'petling_promo_save_partners_action', 'petling_promo_partners_nonce' ); ?>
        <table class="widefat striped" style="background:#fff;"><thead><tr><th>Prefix</th><th>Ετικέτα (όνομα)</th><th>Τύπος</th><th>Έκπτωση</th><th>Ποσό</th><th>Ελάχ. παραγγελία (€)</th><th>Μέρες αναμονής</th><th>Password (Ραντεβού)</th><th>Διαγραφή</th></tr></thead><tbody>
        <?php $rows = $partners; $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' ); $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' );
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
        <?php ptl_render_admin_tabs('qrcode'); ?>
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
// 6. AJAX & SHORTCODES (PROMO FORMS)
// =========================================================================
add_action( 'wp_ajax_petling_process_promo', 'petling_process_promo_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_promo', 'petling_process_promo_ajax' );
function petling_process_promo_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_promo_nonce' ) ) wp_send_json_error( '<div class="ptl-alert ptl-error">Σφάλμα ασφαλείας.</div>' );
    global $wpdb; $table = $wpdb->prefix . 'petling_partner_leads';
    $email = sanitize_email( $_POST['promo_email'] ); $prefix = sanitize_text_field( strtoupper( $_POST['promo_prefix'] ) );
    
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
        $coupon = new WC_Coupon(); $coupon->set_code( $unique_code ); $coupon->set_discount_type( 'percent' === $partner['discount_type'] ? 'percent' : 'fixed_cart' ); $coupon->set_amount( $partner['amount'] ); if ( ! empty( $partner['min_order'] ) ) $coupon->set_minimum_amount( $partner['min_order'] ); $coupon->set_individual_use( true ); $coupon->set_usage_limit( 1 ); $coupon->set_email_restrictions( array( $email ) ); $coupon->save();
    }
    
    $wpdb->insert( $table, array( 'email' => $email, 'partner_prefix' => $prefix, 'type' => $partner['type'], 'coupon_code' => $unique_code, 'status' => 'active', 'created_at' => current_time( 'mysql' ) ) );
    
    // ΕΓΓΡΑΦΗ ΣΤΟ CRM
    ptl_save_quiz_lead_data($email, '-', 'PROMO_' . $prefix, $unique_code);
    
    $amount_text = petling_promo_format_amount( $partner ); $partner_name = esc_html( $partner['label'] );
    if ( 'appointment' === $partner['type'] ) { $subject = 'Ο κωδικός σου για ραντεβού 🐾'; $email_body = "<p>Γεια σου! 🐾 Κέρδισες έκπτωση <strong>{$amount_text}</strong> στο ραντεβού σου με {$partner_name}.</p><p><strong>Ο Κωδικός σου:</strong> <span style='font-size:20px;'>{$unique_code}</span></p>"; } 
    else { $subject = 'Το δωράκι σου σε περιμένει! 🎁'; $email_body = "<p>Γεια σου! 🐾 Κέρδισες έκπτωση <strong>{$amount_text}</strong> για τις αγορές σου στο petling.gr.</p><p><strong>Ο Κωδικός σου:</strong> <span style='font-size:20px;'>{$unique_code}</span></p>"; }
    wp_mail( $email, $subject, $email_body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    
    wp_send_json_success( '<div class="ptl-alert ptl-success"><h3 style="margin-top:0; color:#5b9a68;">🎉 Ο κωδικός σου είναι έτοιμος!</h3><div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; margin:20px 0;">' . esc_html( $unique_code ) . '</div><p>Στάλθηκε και στο email σου!</p></div>' );
}

add_action( 'wp_ajax_petling_process_redeem', 'petling_process_redeem_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_redeem', 'petling_process_redeem_ajax' );
function petling_process_redeem_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_redeem_nonce' ) ) wp_send_json_error( '<div class="ptl-alert ptl-error">Σφάλμα ασφαλείας.</div>' );
    global $wpdb; $table = $wpdb->prefix . 'petling_partner_leads'; $prefix = sanitize_text_field( strtoupper( $_POST['redeem_prefix'] ) ); $password = (string) $_POST['redeem_password']; $lookup = sanitize_text_field( trim( $_POST['redeem_lookup'] ) );
    $partner = petling_promo_get_partner( $prefix ); if ( ! $partner || 'appointment' !== $partner['type'] ) wp_send_json_error( '<div class="ptl-alert ptl-error">Μη διαθέσιμο.</div>' );
    if ( empty( $partner['password'] ) || ! hash_equals( (string) $partner['password'], $password ) ) wp_send_json_error( '<div class="ptl-alert ptl-error">Λάθος κωδικός πρόσβασης.</div>' );
    if ( is_email( $lookup ) ) { $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1", sanitize_email( $lookup ), $prefix ) ); } else { $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE coupon_code = %s AND partner_prefix = %s LIMIT 1", strtoupper( $lookup ), $prefix ) ); }
    if ( ! $row ) wp_send_json_error( '<div class="ptl-alert ptl-error">Δεν βρέθηκε κωδικός.</div>' );
    if ( 'redeemed' === $row->status ) wp_send_json_error( '<div class="ptl-alert ptl-error">⚠️ Αυτός ο κωδικός έχει ήδη χρησιμοποιηθεί.</div>' );
    $wpdb->update( $table, array( 'status' => 'redeemed', 'redeemed_at' => current_time( 'mysql' ) ), array( 'id' => $row->id ) );
    wp_send_json_success( '<div class="ptl-alert ptl-success">✅ Ο κωδικός επιβεβαιώθηκε επιτυχώς!</div>' );
}

add_shortcode( 'petling_partner_promo', 'petling_partner_promo_shortcode' );
function petling_partner_promo_shortcode( $atts ) {
    wp_enqueue_script('jquery');
    $a = shortcode_atts( array( 'prefix' => 'PTL' ), $atts ); $prefix = sanitize_text_field( strtoupper( $a['prefix'] ) ); $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner ) return '<p style="color:#e62121;">Άγνωστος συνεργάτης.</p>';
    $amount_text = petling_promo_format_amount( $partner ); $partner_name = esc_html( $partner['label'] );
    ob_start();
    ?>
    <style>.ptl-promo-container { max-width: 500px; margin: 20px auto; background: #fffaf1; padding: 30px; border-radius: 12px; border: 2px dashed #C7B297; text-align:center; font-family:sans-serif; } .ptl-promo-container input[type="email"] { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 6px; border: 1px solid #ddd; box-sizing:border-box; } .ptl-btn-submit { background: #43282F; color: #fff; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width:100%; } .ptl-alert { padding:15px; border-radius:6px; margin-bottom:15px; } .ptl-error { background:#fff3f3; color:#d63638; border-left:4px solid #d63638; } .ptl-success { background:#eef7ee; color:#5b9a68; border-left:4px solid #5b9a68; }</style>
    <div class="ptl-promo-container">
        <h3>🎁 Πάρε την Έκπτωσή σου!</h3>
        <p>Βάλε το email σου για να λάβεις τον κωδικό <strong><?php echo esc_html( $amount_text ); ?></strong> από <?php echo $partner_name; ?>!</p>
        <form class="ptl-promo-form" onsubmit="event.preventDefault(); var btn = jQuery(this).find('button'); btn.text('⏳...'); jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', jQuery(this).serialize() + '&action=petling_process_promo', function(res){ if(res.success) jQuery('.ptl-promo-container').html(res.data); else { alert(jQuery(res.data).text()); btn.text('Λήψη Κωδικού'); } });">
            <?php wp_nonce_field( 'petling_promo_nonce', 'security', true, true ); ?>
            <input type="hidden" name="promo_prefix" value="<?php echo esc_attr( $prefix ); ?>">
            <input type="email" name="promo_email" placeholder="Το email σου εδώ..." required>
            <button type="submit" class="ptl-btn-submit">Λήψη Κωδικού</button>
        </form>
    </div>
    <?php return ob_get_clean();
}

add_shortcode( 'petling_partner_redeem', 'petling_partner_redeem_shortcode' );
function petling_partner_redeem_shortcode( $atts ) {
    wp_enqueue_script('jquery');
    $a = shortcode_atts( array( 'prefix' => '' ), $atts ); $prefix = sanitize_text_field( strtoupper( $a['prefix'] ) ); $partner = petling_promo_get_partner( $prefix );
    if ( ! $partner || 'appointment' !== $partner['type'] ) return '<p style="color:#e62121;">Μη διαθέσιμη σελίδα.</p>';
    ob_start();
    ?>
    <style>.ptl-redeem-container { max-width: 500px; margin: 20px auto; background: #fffaf1; padding: 30px; border-radius: 12px; border: 2px dashed #C7B297; text-align:center; font-family:sans-serif; } .ptl-redeem-container input { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 6px; border: 1px solid #ddd; box-sizing:border-box; } .ptl-btn-submit { background: #43282F; color: #fff; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width:100%; } .ptl-alert { padding:15px; border-radius:6px; margin-bottom:15px; } .ptl-error { background:#fff3f3; color:#d63638; border-left:4px solid #d63638; } .ptl-success { background:#eef7ee; color:#5b9a68; border-left:4px solid #5b9a68; }</style>
    <div class="ptl-redeem-container">
        <h3>🔒 Έλεγχος &amp; Εξαργύρωση</h3>
        <form class="ptl-redeem-form" onsubmit="event.preventDefault(); var btn = jQuery(this).find('button'); btn.text('⏳...'); jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', jQuery(this).serialize() + '&action=petling_process_redeem', function(res){ alert(jQuery(res.data).text()); btn.text('Επιβεβαίωση'); if(res.success) location.reload(); });">
            <?php wp_nonce_field( 'petling_redeem_nonce', 'security', true, true ); ?>
            <input type="hidden" name="redeem_prefix" value="<?php echo esc_attr( $prefix ); ?>">
            <input type="password" name="redeem_password" placeholder="Κωδικός πρόσβασης" required>
            <input type="text" name="redeem_lookup" placeholder="Κωδικός (VET-...) ή email πελάτη" required>
            <button type="submit" class="ptl-btn-submit">Επιβεβαίωση</button>
        </form>
    </div>
    <?php return ob_get_clean();
}


// =========================================================================
// 7. YOGGIES NUTRITION QUIZ 
// =========================================================================
function ptl_get_yoggies_data() {
    return array(
        'dog' => array(
            'dog_active_duck_venison' => array( 'title' => 'Active Κρέας Πάπιας, Κυνήγι & Προβιοτικά', 'url' => home_url('/product/xira-trofi-skylou-active-kreas-papias-kynigi-3/'), 'allergens' => array( 'duck', 'venison', 'poultry', 'fish_meat', 'egg', 'shellfish' ), 'activity_level' => 'high' ),
            'dog_chicken_beef' => array( 'title' => 'Κοτόπουλο, Βόειο Κρέας & Προβιοτικά', 'url' => home_url('/?s=Κοτόπουλο+Βόειο+Κρέας+Προβιοτικά&post_type=product'), 'allergens' => array( 'chicken', 'beef', 'poultry', 'fish_meat', 'corn', 'shellfish' ), 'activity_level' => 'normal' ),
            'dog_lamb_fish' => array( 'title' => 'Αρνί Γάλακτος, Λευκό Ψάρι & Προβιοτικά', 'url' => home_url('/?s=Αρνί+Λευκό+Ψάρι+Προβιοτικά&post_type=product'), 'allergens' => array( 'lamb', 'fish_meat' ), 'activity_level' => 'normal' ),
            'dog_turkey_millet' => array( 'title' => 'Γαλοπούλα, Κεχρί & Προβιοτικά (Μονοπρωτεϊνική)', 'url' => home_url('/?s=Γαλοπούλα+Κεχρί+Προβιοτικά&post_type=product'), 'allergens' => array( 'turkey', 'poultry', 'fish_oil' ), 'activity_level' => 'low' ),
            'dog_iberian_pork' => array( 'title' => 'Ιβηρικό Χοιρινό, Μήλα & Προβιοτικά', 'url' => home_url('/?s=Ιβηρικό+Χοιρινό+Μήλα&post_type=product'), 'allergens' => array( 'pork', 'fish_oil' ), 'activity_level' => 'normal' ),
            'dog_goat' => array( 'title' => 'Κατσικίσιο Κρέας με Λαχανικά (Μονοπρωτεϊνική)', 'url' => home_url('/?s=Κατσικίσιο+Κρέας+Λαχανικά&post_type=product'), 'allergens' => array( 'goat', 'fish_oil', 'egg', 'shellfish' ), 'activity_level' => 'low' ),
            'dog_vet_gastro_turkey' => array( 'title' => 'VET Gastro Sensitive με Γαλοπούλα', 'url' => home_url('/?s=VET+Gastro+Sensitive+Γαλοπούλα&post_type=product'), 'allergens' => array( 'turkey', 'poultry', 'fish_oil' ), 'activity_level' => 'normal' ),
            'dog_vet_insect' => array( 'title' => 'VET Insect Derma (Πρωτεΐνη Εντόμων)', 'url' => home_url('/?s=VET+Insect+Derma&post_type=product'), 'allergens' => array( 'insect', 'fish_oil' ), 'activity_level' => 'normal' ),
            'dog_vet_veggie' => array( 'title' => 'VET Veggie, Χωρίς Κρέας', 'url' => home_url('/?s=VET+Veggie&post_type=product'), 'allergens' => array( 'meat' ), 'activity_level' => 'normal' )
        ),
        'cat' => array(
            'cat_chicken' => array( 'title' => 'Κοτόπουλο & Προβιοτικά (Γάτας)', 'url' => home_url('/?s=Τροφή+Γάτας+Κοτόπουλο+Προβιοτικά&post_type=product'), 'allergens' => array( 'chicken', 'poultry', 'fish_oil' ), 'activity_level' => 'normal' ),
            'cat_turkey' => array( 'title' => 'Γαλοπούλα & Προβιοτικά (Γάτας)', 'url' => home_url('/?s=Τροφή+Γάτας+Γαλοπούλα+Προβιοτικά&post_type=product'), 'allergens' => array( 'turkey', 'poultry', 'fish_oil' ), 'activity_level' => 'normal' ),
            'cat_lamb_fish' => array( 'title' => 'Αρνί Γάλακτος, Λευκό Ψάρι & Προβιοτικά (Γάτας)', 'url' => home_url('/?s=Τροφή+Γάτας+Αρνί+Ψάρι&post_type=product'), 'allergens' => array( 'lamb', 'fish_meat' ), 'activity_level' => 'normal' )
        )
    );
}

add_shortcode('petling_nutrition_quiz', 'ptl_nutrition_quiz_shortcode');
function ptl_nutrition_quiz_shortcode() {
    $html = '<style>.ptl-quiz-container { max-width: 650px; margin: 40px auto; background: #fffaf1; padding: 40px; border-radius: 12px; border: 2px dashed #C7B297; font-family: sans-serif; box-shadow: 0 5px 20px rgba(0,0,0,0.05); } .ptl-step { display: none; animation: fadeIn 0.4s; } .ptl-step.active { display: block; } .ptl-quiz-container h3 { color: #43282F; margin-top: 0; font-size: 24px; text-align: center; margin-bottom:10px; } .ptl-quiz-container p.ptl-desc { color: #555; font-size: 15px; margin-bottom: 25px; text-align: center; line-height: 1.5; } .ptl-quiz-container label { display: block; font-weight: bold; color: #43282F; margin-bottom: 8px; margin-top: 15px; } .ptl-quiz-container input[type="number"], .ptl-quiz-container input[type="email"], .ptl-quiz-container input[type="text"], .ptl-quiz-container select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 15px; } .ptl-radio-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; } .ptl-radio-group label { flex: 1 1 calc(50% - 10px); min-width:140px; text-align: center; background: #fff; border: 2px solid #e0d5c1; padding: 15px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-weight: normal; margin-top: 0; } .ptl-radio-group input[type="radio"] { display: none; } .ptl-radio-group input[type="radio"]:checked + label { border-color: #C7B297; background: #F5EDE3; color: #43282F; font-weight: bold; } .ptl-quiz-nav { display: flex; justify-content: space-between; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 20px; } .ptl-btn-prev { background: #e0d5c1; color: #43282F; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; } .ptl-btn-next, .ptl-btn-submit { background: #43282F; color: #fff; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; } .ptl-btn-next:hover, .ptl-btn-submit:hover { background: #C7B297; color: #43282F; } .ptl-btn-prev:hover { background: #C7B297; color: #fff; } .ptl-loader { text-align:center; padding:20px; font-weight:bold; color:#43282F; display:none; } .ptl-result-box { background:#fff; padding:25px; border-radius:8px; border:2px solid #C7B297; margin-top:20px; text-align:center; } .ptl-transition-box { background: #F5EDE3; border-left: 4px solid #C7B297; padding: 15px 20px; margin-top: 25px; text-align: left; font-size: 14px; color: #43282F; line-height: 1.6; border-radius: 0 8px 8px 0; } .ptl-transition-box h5 { margin: 0 0 10px 0; font-size: 16px; color: #43282F; } @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }</style>';
    $html .= '<div class="ptl-quiz-container" id="ptl-nutrition-app"><form id="ptl-nutrition-form">' . wp_nonce_field('ptl_nutrition_nonce', 'security', true, false);
    $html .= '<div class="ptl-step active" id="ptl-step-0"><h3>🐾 Ας ξεκινήσουμε!</h3><p class="ptl-desc">Για ποιο μουσουδάκι ψάχνουμε την ιδανική τροφή;</p><div class="ptl-radio-group"><input type="radio" id="pet_dog" name="pet_type" value="dog" checked><label for="pet_dog">🐶 Σκύλος</label><input type="radio" id="pet_cat" name="pet_type" value="cat"><label for="pet_cat">🐱 Γάτα</label></div><div class="ptl-quiz-nav" style="justify-content: flex-end;"><button type="button" class="ptl-btn-next" onclick="ptlnNextStep(1)">Επόμενο &rarr;</button></div></div>';
    $html .= '<div class="ptl-step" id="ptl-step-1"><h3>🩺 Θέματα Υγείας</h3><p class="ptl-desc">Το κατοικίδιό σας έχει κάποιο διαγνωσμένο πρόβλημα υγείας;</p><div class="ptl-radio-group"><input type="radio" id="health_no" name="health_issue" value="no" checked><label for="health_no">Όχι, είναι υγιέστατο!</label><input type="radio" id="health_yes" name="health_issue" value="yes"><label for="health_yes">Ναι, υπάρχει διάγνωση</label></div><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlnNextStep(0)">&larr; Πίσω</button><button type="button" class="ptl-btn-next" onclick="ptlnSmartNextStep(2)">Επόμενο &rarr;</button></div></div>';
    $html .= '<div class="ptl-step" id="ptl-step-2"><h3>⚖️ Στοιχεία & Αλλεργίες</h3><label>Ηλικιακό Στάδιο:</label><select name="life_stage"><option value="puppy">Κουτάβι / Γατάκι</option><option value="adult" selected>Ενήλικο</option><option value="senior">Μεγάλης Ηλικίας</option></select><label>Τρέχον Βάρος (κιλά):</label><input type="number" step="0.1" name="current_weight" placeholder="π.χ. 12.5"><label>Επίπεδο Δραστηριότητας:</label><select name="activity_level"><option value="low">Χαμηλή (Ήσυχο)</option><option value="normal" selected>Κανονική</option><option value="high">Υψηλή</option></select><label>Γνωστές Αλλεργίες:</label><select name="allergies[]" multiple style="height: 120px;"><option value="none" selected>Καμία</option><option value="chicken">Κοτόπουλο</option><option value="turkey">Γαλοπούλα</option><option value="poultry">Πουλερικά</option><option value="beef">Μοσχάρι</option><option value="pork">Χοιρινό</option><option value="lamb">Αρνί</option><option value="fish_meat">Ψάρι</option><option value="fish_oil">Ψάρι & Λάδι</option><option value="egg">Αυγό</option><option value="corn">Καλαμπόκι</option><option value="meat">Όλα τα κρέατα</option></select><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlnSmartPrevStep(1)">&larr; Πίσω</button><button type="button" class="ptl-btn-next" onclick="ptlnCheckDataBeforeStep3()">Επόμενο &rarr;</button></div></div>';
    $html .= '<div class="ptl-step" id="ptl-step-3"><h3>📩 Λίγο πριν το αποτέλεσμα!</h3><p class="ptl-desc">Συμπληρώστε τα στοιχεία σας.</p><label>Ονοματεπώνυμο:</label><input type="text" name="user_name" required><label>Email:</label><input type="email" name="user_email" required><div style="margin-top: 15px; font-size: 13px;"><label><input type="checkbox" name="consent_email" required> Συμφωνώ να λάβω το αποτέλεσμα.</label></div><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlnSmartPrevStep(2)">&larr; Πίσω</button><button type="submit" class="ptl-btn-submit">Δες το αποτέλεσμα!</button></div></div>';
    $html .= '<div class="ptl-step" id="ptl-step-result"><div id="ptl-result-content"></div><div style="text-align:center; margin-top:30px;"><button type="button" class="ptl-btn-prev" onclick="location.reload();">Επανεκκίνηση</button></div></div><div class="ptl-loader" id="ptl-quiz-loader">⏳ Γίνεται υπολογισμός...</div></form></div>';
    $ajax_url = admin_url( 'admin-ajax.php' );
    $html .= "<script>
        function ptlnNextStep(step) { jQuery('#ptl-nutrition-app .ptl-step').removeClass('active'); jQuery('#ptl-nutrition-app #ptl-step-' + step).addClass('active'); }
        function ptlnSmartNextStep(step) { var health = jQuery('#ptl-nutrition-app input[name=\"health_issue\"]:checked').val(); if ( step === 2 && health === 'yes' ) { step = 3; } ptlnNextStep(step); }
        function ptlnSmartPrevStep(step) { var health = jQuery('#ptl-nutrition-app input[name=\"health_issue\"]:checked').val(); if ( step === 2 && health === 'yes' ) { step = 1; } ptlnNextStep(step); }
        function ptlnCheckDataBeforeStep3() { var weight = jQuery('#ptl-nutrition-app input[name=\"current_weight\"]').val(); if(!weight || weight <= 0) { alert('Παρακαλώ εισάγετε σωστό βάρος.'); return; } ptlnNextStep(3); }
        jQuery('#ptl-nutrition-form').on('submit', function(e){
            e.preventDefault(); var formData = jQuery(this).serialize(); jQuery('#ptl-nutrition-app .ptl-step').removeClass('active'); jQuery('#ptl-quiz-loader').show();
            jQuery.post('{$ajax_url}', formData + '&action=ptl_process_nutrition', function(response){
                jQuery('#ptl-quiz-loader').hide(); jQuery('#ptl-nutrition-app #ptl-step-result').addClass('active');
                if(response.success) { jQuery('#ptl-result-content').html(response.data); } else { jQuery('#ptl-result-content').html('<p style=\"color:red;\">' + response.data + '</p>'); }
            }).fail(function(xhr) { jQuery('#ptl-quiz-loader').hide(); jQuery('#ptl-nutrition-app #ptl-step-result').addClass('active'); jQuery('#ptl-result-content').html('<p style=\"color:red;\">Σφάλμα.</p>'); });
        });
    </script>";
    return $html;
}

add_action('wp_ajax_ptl_process_nutrition', 'ptl_process_nutrition_ajax');
add_action('wp_ajax_nopriv_ptl_process_nutrition', 'ptl_process_nutrition_ajax');
function ptl_process_nutrition_ajax() {
    check_ajax_referer('ptl_nutrition_nonce', 'security');
    $pet_type = sanitize_text_field($_POST['pet_type'] ?? 'dog'); $health_issue = sanitize_text_field($_POST['health_issue'] ?? 'no'); $weight = floatval($_POST['current_weight'] ?? 0); $life_stage = sanitize_text_field($_POST['life_stage'] ?? 'adult'); $activity_level = sanitize_text_field($_POST['activity_level'] ?? 'normal'); $user_name = sanitize_text_field($_POST['user_name'] ?? ''); $user_email = sanitize_email($_POST['user_email'] ?? ''); $allergies = isset($_POST['allergies']) ? array_map('sanitize_text_field', $_POST['allergies']) : array('none');
    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Petling <info@petling.gr>');
    
    if ( $health_issue === 'yes' ) {
        global $wpdb; $promo_table = $wpdb->prefix . 'petling_partner_leads'; $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table); $dynamic_code = '';
        if ( $table_exists ) { $recent = $wpdb->get_var( $wpdb->prepare( "SELECT coupon_code FROM $promo_table WHERE email = %s AND partner_prefix = 'VET' AND status = 'active' AND created_at >= %s ORDER BY created_at DESC LIMIT 1", $user_email, date('Y-m-d H:i:s', strtotime('-24 hours', current_time('timestamp'))) ) ); if ( $recent ) { $dynamic_code = $recent; } }
        if ( empty($dynamic_code) ) {
            $dynamic_code = 'VET-' . strtoupper(substr(md5(uniqid()), 0, 6));
            if ( $table_exists ) { $wpdb->insert( $promo_table, array( 'email' => $user_email, 'partner_prefix' => 'VET', 'type' => 'appointment', 'coupon_code' => $dynamic_code, 'status' => 'active', 'created_at' => current_time('mysql') ) ); }
        }
        
        $pid = ptl_save_quiz_lead_data($user_email, $user_name, 'FOOD_YOGGIES', 'VET (Κωδικός: ' . $dynamic_code . ')');
        if ($pid) { update_post_meta($pid, 'ptl_health_condition', 'Πρόβλημα Υγείας (Σύσταση VET)'); }

        $html = '<h3>🩺 Απαιτείται Κτηνιατρική Συμβουλή</h3><p class="ptl-desc">Η επιλογή τροφής πρέπει να γίνει εξατομικευμένα.</p><div class="ptl-result-box"><h4 style="color: #43282F;">Κλείστε Ραντεβού με τη Δρ. Μανωλάκου</h4><p>Ο κωδικός σας για <strong>10% έκπτωση</strong> είναι:</p><div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; margin:20px 0;">' . $dynamic_code . '</div><p style="font-size:13px; color:#666;">Στάλθηκε και στο email σας.</p></div>';
        if ( is_email( $user_email ) ) { wp_mail( $user_email, 'Κωδικός έκπτωσης VET', $html, $headers ); }
        wp_send_json_success($html); exit;
    }

    $all_products = ptl_get_yoggies_data(); $available = $all_products[$pet_type];
    if ( ! in_array( 'none', $allergies ) ) { foreach ( $available as $key => $product ) { foreach ( $allergies as $allergy ) { if ( in_array( $allergy, $product['allergens'] ) ) { unset( $available[$key] ); break; } } } }
    if ( empty( $available ) ) { wp_send_json_error('Δεν βρέθηκε κατάλληλη τροφή.'); }
    $final = null; foreach ( $available as $p ) { if ( $p['activity_level'] === $activity_level ) { $final = $p; break; } } if ( ! $final ) { $final = reset( $available ); }
    
    ptl_save_quiz_lead_data($user_email, $user_name, 'FOOD_YOGGIES', $final['title']);
    
    $grams_per_day = round(($pet_type === 'dog') ? ($weight * 1000 * ($life_stage === 'puppy' ? 0.025 : 0.012)) : (($weight <= 3) ? 50 : (($weight <= 5) ? 70 : 110)));
    $html = '<h3>🎉 Βρήκαμε την ιδανική τροφή!</h3><div class="ptl-result-box"><h4 style="color:#43282F;">' . esc_html($final['title']) . '</h4><p>Ημερήσια Δόση: <strong>' . $grams_per_day . ' γρ.</strong></p><a href="' . esc_url($final['url']) . '" target="_blank" style="display:inline-block; margin-top:20px; background:#43282F; color:#fff; padding:12px 25px; border-radius:6px; text-decoration:none;">Δες το Προϊόν &rarr;</a></div>';
    if ( is_email( $user_email ) ) { wp_mail( $user_email, 'Η πρόταση τροφής σας', $html, $headers ); }
    wp_send_json_success($html);
}


// =========================================================================
// 8. RECIPE MAKER 
// =========================================================================
function ptl_get_dynamic_recipe_options() {
    return array(
        // ΒΑΣΙΚΑ ΥΛΙΚΑ ΓΙΑ ΤΗ ΔΩΡΕΑΝ ΕΚΔΟΣΗ (Αυστηρά 3 κύριες πρωτεΐνες)
        'proteins_1' => array( 'Κοτόπουλο', 'Γαλοπούλα', 'Αρνί' ), 
        'proteins_2' => array( 'Αυγό (βραστό)', 'Γιαούρτι' ),
        'carbs_1'    => array( 'Ρύζι (Λευκό ή Ολικής)', 'Κοφτό μακαρονάκι' ),
        'carbs_2'    => array( 'Πατάτα' ),
        'veggies'    => array( 'Καρότο', 'Κολοκυθάκι', 'Αρακάς', 'Φασολάκια' ),
        'fruits'     => array( 'Μήλο', 'Μπανάνα', 'Αχλάδι' ),
        'oils'       => array( 'Εξαιρετικά παρθένο ελαιόλαδο' ),
        
        // ΔΙΑΧΩΡΙΣΜΟΣ ΥΓΕΙΑΣ (Απαραίτητα για να γεμίζουν τα νέα dropdowns)
        'mild_sensitivities'  => array( 'Τροφική Αλλεργία / Δερματικά', 'Ευαίσθητο Στομάχι / Αέρια', 'Ευαίσθητες Αρθρώσεις', 'Τάση για παχυσαρκία / Στειρωμένο' ),
        'clinical_conditions' => array( 'Γαστρεντερικά / ΙΦΝΕ', 'Νεφρική Ανεπάρκεια', 'Ηπατοπάθεια', 'Ουροποιητικό (Κρύσταλλοι/Πέτρες)', 'Σακχαρώδης Διαβήτης', 'Λεϊσμανίαση (Καλαζάρ)', 'Άλλο (Σοβαρό)' )
    );
}

add_shortcode('petling_recipe_maker', 'ptl_recipe_maker_shortcode');
function ptl_recipe_maker_shortcode() {
    $r_data = ptl_get_dynamic_recipe_options();

    $html = '<style>.ptl-quiz-container { max-width: 650px; margin: 40px auto; background: #fffaf1; padding: 40px; border-radius: 12px; border: 2px dashed #C7B297; font-family: sans-serif; box-shadow: 0 5px 20px rgba(0,0,0,0.05); } .ptl-step { display: none; animation: fadeIn 0.4s; } .ptl-step.active { display: block; } .ptl-quiz-container h3 { color: #43282F; margin-top: 0; font-size: 24px; text-align: center; margin-bottom:10px; } .ptl-quiz-container p.ptl-desc { color: #555; font-size: 15px; margin-bottom: 25px; text-align: center; line-height: 1.5; } .ptl-quiz-container label.ptl-section-label { display: block; font-weight: bold; color: #43282F; margin-bottom: 8px; margin-top: 20px; font-size: 16px; border-bottom: 1px solid #e0d5c1; padding-bottom: 5px; } .ptl-quiz-container select, .ptl-quiz-container input[type="text"], .ptl-quiz-container input[type="email"], .ptl-quiz-container input[type="password"], .ptl-quiz-container textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 15px; margin-bottom: 10px; background: #fff; } .ptl-radio-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; } .ptl-radio-group label { text-align: center; background: #fff; border: 2px solid #e0d5c1; padding: 15px; border-radius: 8px; cursor: pointer; } .ptl-radio-group input[type="radio"] { display: none; } .ptl-radio-group input[type="radio"]:checked + label { border-color: #C7B297; background: #F5EDE3; color: #43282F; font-weight: bold; } .ptl-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px; } .ptl-checkbox-group label { flex: 1 1 calc(33% - 8px); text-align: center; background: #fff; border: 2px solid #e0d5c1; padding: 10px 5px; border-radius: 6px; cursor: pointer; font-size: 13px; margin:0; } .ptl-checkbox-group input[type="checkbox"] { display: none; } .ptl-checkbox-group input[type="checkbox"]:checked + label { border-color: #C7B297; background: #F5EDE3; font-weight: bold; } .ptl-quiz-nav { display: flex; justify-content: space-between; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 20px; } .ptl-btn-prev { background: #e0d5c1; color: #43282F; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; } .ptl-btn-next, .ptl-btn-submit { background: #43282F; color: #fff; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; } .ptl-btn-next:hover, .ptl-btn-submit:hover { background: #C7B297; } .ptl-result-box { background:#fff; padding:25px; border-radius:8px; border:2px solid #C7B297; margin-top:20px; } @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }</style>';
    
    $html .= '<div class="ptl-quiz-container" id="ptl-recipe-app"><form id="ptl-recipe-form">' . wp_nonce_field('ptl_recipe_nonce', 'security', true, false);
    
    // ΒΗΜΑ 1 - ΟΛΙΣΤΙΚΗ ΕΡΩΤΗΣΗ ΥΓΕΙΑΣ
    $html .= '<div class="ptl-step active" id="rec-step-1"><h3>🩺 Θέματα Υγείας</h3><p class="ptl-desc">Ο σκύλος σας έχει κάποιες ειδικές ευαισθησίες ή κλινικές ανάγκες;</p>';
    $html .= '<div class="ptl-radio-group">';
    $html .= '<input type="radio" id="health_none" name="health_tier" value="none" checked><label for="health_none">Κανένα πρόβλημα (Είναι υγιέστατος!)</label>';
    $html .= '<input type="radio" id="health_mild" name="health_tier" value="mild"><label for="health_mild">Ήπιες Ευαισθησίες (π.χ. Αλλεργία, Τρίχωμα, Αρθρώσεις)</label>';
    $html .= '<input type="radio" id="health_clinical" name="health_tier" value="clinical"><label for="health_clinical">Ιατρική/Κλινική Πάθηση (π.χ. Νεφρά, Διαβήτης)</label>';
    $html .= '</div>';
    
    $html .= '<div id="ptl-health-mild-box" style="display:none; background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; margin-bottom:20px;"><label class="ptl-section-label" style="border:none; margin-top:0;">Τι είδους ευαισθησία;</label><select name="health_condition_mild"><option value="" disabled selected>Επιλέξτε...</option>';
    foreach($r_data['mild_sensitivities'] as $cond) { $html .= '<option value="'.esc_attr($cond).'">'.esc_html($cond).'</option>'; }
    $html .= '</select></div>';

    $html .= '<div id="ptl-health-clinical-box" style="display:none; background:#fff3f3; padding:20px; border-radius:8px; border:1px solid #d63638; margin-bottom:20px;"><label class="ptl-section-label" style="border:none; margin-top:0; color:#d63638;">Κλινική Πάθηση:</label><select name="health_condition_clinical"><option value="" disabled selected>Επιλέξτε...</option>';
    foreach($r_data['clinical_conditions'] as $cond) { $html .= '<option value="'.esc_attr($cond).'">'.esc_html($cond).'</option>'; }
    $html .= '</select></div>';
    
    $html .= '<input type="hidden" name="health_issue" id="legacy_health_issue" value="no">';
    $html .= '<input type="hidden" name="health_condition" id="legacy_health_condition" value="">';

    $html .= '<div class="ptl-quiz-nav" style="justify-content: flex-end;"><button type="button" class="ptl-btn-next" onclick="ptlrSmartNextStep(2)">Επόμενο &rarr;</button></div></div>';
    
    // ΒΗΜΑ 2 - ΥΛΙΚΑ
    $html .= '<div class="ptl-step" id="rec-step-2"><h3>🍲 Υλικά Συνταγής</h3><label class="ptl-section-label">1. Κύρια Πρωτεΐνη (50% - 1 επιλογή)</label><select name="protein_1"><option value="" disabled selected>Επίλεξε...</option>';
    foreach($r_data['proteins_1'] as $item) { $html .= '<option value="'.esc_attr($item).'">'.esc_html($item).'</option>'; }
    $html .= '</select><label class="ptl-section-label">2. Δευτερεύουσα Πρωτεΐνη (5% - 1 επιλογή)</label><select name="protein_2"><option value="" disabled selected>Επίλεξε...</option>';
    foreach($r_data['proteins_2'] as $item) { $html .= '<option value="'.esc_attr($item).'">'.esc_html($item).'</option>'; }
    $html .= '</select><label class="ptl-section-label">3. 1η Πηγή Υδατάνθρακα (15% - 1 επιλογή)</label><select name="carb_1"><option value="" disabled selected>Επίλεξε...</option>';
    foreach($r_data['carbs_1'] as $item) { $html .= '<option value="'.esc_attr($item).'">'.esc_html($item).'</option>'; }
    $html .= '</select><label class="ptl-section-label">4. 2η Πηγή Υδατάνθρακα (10% - 1 επιλογή)</label><select name="carb_2"><option value="" disabled selected>Επίλεξε...</option>';
    foreach($r_data['carbs_2'] as $item) { $html .= '<option value="'.esc_attr($item).'">'.esc_html($item).'</option>'; }
    $html .= '</select><label class="ptl-section-label">5. Λαχανικά (13% - Ακριβώς 2)</label><div class="ptl-checkbox-group" id="ptl-veg-group">';
    foreach($r_data['veggies'] as $i => $item) { $html .= '<input type="checkbox" id="veg_'.$i.'" name="veggies[]" value="'.esc_attr($item).'"><label for="veg_'.$i.'">'.esc_html($item).'</label>'; }
    $html .= '</div><p id="veg-error" style="color:red; font-size:12px; display:none;">Επιλέξτε ακριβώς 2 λαχανικά.</p>';
    $html .= '<label class="ptl-section-label">6. Φρούτα (5% - 1 επιλογή)</label><select name="fruit"><option value="" disabled selected>Επίλεξε...</option>';
    foreach($r_data['fruits'] as $item) { $html .= '<option value="'.esc_attr($item).'">'.esc_html($item).'</option>'; }
    $html .= '</select><label class="ptl-section-label">7. Έλαια (2% - 1 επιλογή)</label><select name="oil"><option value="" disabled selected>Επίλεξε...</option>';
    foreach($r_data['oils'] as $item) { $html .= '<option value="'.esc_attr($item).'">'.esc_html($item).'</option>'; }

    $html .= '</select><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlrNextStep(1)">&larr; Πίσω</button><button type="button" class="ptl-btn-next" onclick="ptlrValidateStep2()">Επόμενο &rarr;</button></div></div>';
    
    // ΒΗΜΑ 3 - ΕΠΙΚΟΙΝΩΝΙΑ & ΚΩΔΙΚΟΣ (ΝΕΟ)
    $html .= '<div class="ptl-step" id="rec-step-3"><h3>📩 Στοιχεία & Αποθήκευση</h3>
        <p class="ptl-desc" style="margin-bottom:15px;">Συμπληρώστε τα στοιχεία σας για να αποθηκεύσουμε τη συνταγή στο προσωπικό σας προφίλ!</p>
        <label>Όνοματεπώνυμο:</label><input type="text" name="user_name" required>
        <label>Email:</label><input type="email" name="user_email" required>
        <label>Κωδικός Πρόσβασης:</label><input type="password" name="user_password" required>
        <p style="font-size:12px; color:#666; margin-top:5px; line-height:1.4;">Δημιουργήστε έναν κωδικό. Αν έχετε ήδη λογαριασμό στο Petling, βάλτε τον κωδικό σας και θα συνδεθείτε αυτόματα!</p>
        <div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlrSmartPrevStep(2)">&larr; Πίσω</button><button type="submit" class="ptl-btn-submit">Ολοκλήρωση!</button></div></div>';
    
    // ΒΗΜΑ LOADING - Η ΨΥΧΟΛΟΓΙΚΗ ΑΝΑΜΟΝΗ
    $html .= '<div class="ptl-step" id="rec-step-loading" style="text-align:center; padding: 40px 10px;">
        <h3>⚙️ Ετοιμάζουμε τη συνταγή σας...</h3>
        <div style="width:100%; background:#e0d5c1; border-radius:10px; height:20px; overflow:hidden; margin:30px 0; border: 1px solid #C7B297;">
            <div id="ptl-loading-progress" style="width:0%; background:#43282F; height:100%; transition:width 0.8s ease-in-out;"></div>
        </div>
        <p style="font-size: 14px; color: #888;">Παρακαλώ περιμένετε, η διαδικασία μπορεί να διαρκέσει λίγα δευτερόλεπτα.</p>
    </div>';

    // ΒΗΜΑ 4 - ΑΠΟΤΕΛΕΣΜΑ
    $html .= '<div class="ptl-step" id="rec-step-result"><div id="ptl-recipe-content"></div><div style="text-align:center; margin-top:30px;"><button type="button" class="ptl-btn-prev" onclick="location.reload();">Επανεκκίνηση</button></div></div></form></div>';
    
    $ajax_url = admin_url( 'admin-ajax.php' );
    $html .= "<script>
        jQuery('#ptl-recipe-app input[name=\"health_tier\"]').on('change', function(){ 
            var val = jQuery(this).val();
            if (val === 'mild') {
                jQuery('#ptl-health-mild-box').slideDown(); jQuery('#ptl-health-clinical-box').slideUp(); jQuery('#legacy_health_issue').val('no'); 
            } else if (val === 'clinical') {
                jQuery('#ptl-health-mild-box').slideUp(); jQuery('#ptl-health-clinical-box').slideDown(); jQuery('#legacy_health_issue').val('yes'); 
            } else {
                jQuery('#ptl-health-mild-box').slideUp(); jQuery('#ptl-health-clinical-box').slideUp(); jQuery('#legacy_health_issue').val('no'); 
            }
        });

        jQuery('select[name=\"health_condition_mild\"], select[name=\"health_condition_clinical\"]').on('change', function() {
            jQuery('#legacy_health_condition').val(jQuery(this).val());
        });

        function ptlrNextStep(step) { jQuery('#ptl-recipe-app .ptl-step').removeClass('active'); jQuery('#ptl-recipe-app #rec-step-' + step).addClass('active'); }
        
        function ptlrSmartNextStep(step) { 
            var tier = jQuery('#ptl-recipe-app input[name=\"health_tier\"]:checked').val(); 
            if (tier === 'mild' && !jQuery('select[name=\"health_condition_mild\"]').val()) { alert('Επιλέξτε την ευαισθησία.'); return; }
            if (tier === 'clinical' && !jQuery('select[name=\"health_condition_clinical\"]').val()) { alert('Επιλέξτε την κλινική πάθηση.'); return; }
            if (step === 2 && tier === 'clinical') { step = 3; } 
            ptlrNextStep(step); 
        }

        function ptlrSmartPrevStep(step) { 
            var tier = jQuery('#ptl-recipe-app input[name=\"health_tier\"]:checked').val(); 
            if (step === 2 && tier === 'clinical') { step = 1; } 
            ptlrNextStep(step); 
        }

        jQuery('#ptl-veg-group input[type=\"checkbox\"]').on('change', function() { if(jQuery('#ptl-veg-group input[type=\"checkbox\"]:checked').length > 2) { this.checked = false; } });
        
        function ptlrValidateStep2() { 
            var valid = true; 
            jQuery('#ptl-recipe-app #rec-step-2 select').each(function() { if(!jQuery(this).val()) valid = false; }); 
            var vegCount = jQuery('#ptl-veg-group input[type=\"checkbox\"]:checked').length; 
            if (vegCount !== 2) { jQuery('#veg-error').show(); valid = false; } else { jQuery('#veg-error').hide(); } 
            if(!valid) { alert('Συμπληρώστε όλα τα πεδία.'); return; } 
            ptlrNextStep(3); 
        }

        jQuery('#ptl-recipe-form').on('submit', function(e){
            e.preventDefault(); 
            var formData = jQuery(this).serialize(); 
            
            jQuery('#ptl-recipe-app .ptl-step').removeClass('active'); 
            jQuery('#rec-step-loading').addClass('active');
            
            var ajaxCall = jQuery.post('{$ajax_url}', formData + '&action=ptl_process_recipe');
            
            var progress = 0;
            var progressInterval = setInterval(function() {
                progress += 20; 
                if(progress <= 100) { jQuery('#ptl-loading-progress').css('width', progress + '%'); }
            }, 800); 
            
            setTimeout(function() {
                clearInterval(progressInterval);
                jQuery('#ptl-loading-progress').css('width', '100%');
                
                ajaxCall.done(function(response) {
                    if(response.success) { 
                        jQuery('#rec-step-loading').removeClass('active');
                        jQuery('#rec-step-result').addClass('active');
                        jQuery('#ptl-recipe-content').html(response.data); 
                    } else { 
                        // Αν το password είναι λάθος, επιστρέφουμε στο Βήμα 3
                        jQuery('#rec-step-loading').removeClass('active');
                        jQuery('#rec-step-3').addClass('active');
                        alert(response.data); 
                    }
                }).fail(function() { 
                    jQuery('#rec-step-loading').removeClass('active');
                    jQuery('#rec-step-3').addClass('active');
                    alert('Υπήρξε σφάλμα σύνδεσης. Δοκιμάστε ξανά.'); 
                });
            }, 4000); 
        });
    </script>";
    return $html;
}

add_action('wp_ajax_ptl_process_recipe', 'ptl_process_recipe_ajax');
add_action('wp_ajax_nopriv_ptl_process_recipe', 'ptl_process_recipe_ajax');
function ptl_process_recipe_ajax() {
    check_ajax_referer('ptl_recipe_nonce', 'security');
    
    $health_tier = sanitize_text_field($_POST['health_tier'] ?? 'none'); 
    $health_condition = '';
    if ($health_tier === 'clinical') {
        $health_condition = sanitize_text_field($_POST['health_condition_clinical'] ?? '');
    } elseif ($health_tier === 'mild') {
        $health_condition = sanitize_text_field($_POST['health_condition_mild'] ?? '');
    }
    
    $user_name = sanitize_text_field($_POST['user_name'] ?? ''); 
    $user_email = sanitize_email($_POST['user_email'] ?? '');
    $user_password = isset($_POST['user_password']) ? $_POST['user_password'] : '';
    
    if (empty($user_password)) {
        wp_send_json_error('Παρακαλώ εισάγετε κωδικό πρόσβασης.');
    }

    // --- ΑΥΤΟΜΑΤΟ LOGIN / REGISTER ---
    $user_id = email_exists($user_email);
    $new_account = false;

    if ($user_id) {
        $user = get_user_by('email', $user_email);
        if ($user && wp_check_password($user_password, $user->user_pass, $user->ID)) {
            // Σωστό email & password: Κάνουμε login
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
        } else {
            // Λάθος password
            wp_send_json_error('Το email υπάρχει ήδη στο σύστημα. Παρακαλώ βάλτε τον σωστό κωδικό σας ή κάντε επαναφορά κωδικού από τη σελίδα "Ο Λογαριασμός Μου".');
        }
    } else {
        // Νέος Χρήστης: Δημιουργία και Login
        $user_id = wp_create_user($user_email, $user_password, $user_email);
        if (is_wp_error($user_id)) {
            wp_send_json_error('Σφάλμα κατά τη δημιουργία λογαριασμού: ' . $user_id->get_error_message());
        }
        wp_update_user(['ID' => $user_id, 'first_name' => $user_name]);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        $new_account = true;
    }
    
    global $wpdb; 
    $promo_table = $wpdb->prefix . 'petling_partner_leads'; 
    $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table); 
    $dynamic_code = 'VET-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Petling <info@petling.gr>');
    
    // 1. ΔΙΑΧΕΙΡΙΣΗ ΚΛΙΝΙΚΗΣ ΠΑΘΗΣΗΣ
    if ( $health_tier === 'clinical' ) {
        if ( $table_exists ) { 
            $wpdb->insert( $promo_table, array( 'email' => $user_email, 'partner_prefix' => 'VET', 'type' => 'appointment', 'coupon_code' => $dynamic_code, 'status' => 'active', 'created_at' => current_time('mysql') ) ); 
        }
        $pid = ptl_save_quiz_lead_data($user_email, $user_name, 'HOME_COOKED_RECIPE', 'VET (Κλινική Πάθηση: ' . $health_condition . ')');
        if ($pid) { 
            update_post_meta($pid, 'ptl_health_condition', $health_condition); 
            update_post_meta($pid, 'ptl_user_id', $user_id); // Συνδέουμε το lead με το user_id
        }
        
        $html = '<h3>🩺 Απαιτείται Κλινική Δίαιτα</h3><div class="ptl-result-box"><h4 style="color: #43282F;">Κλείστε Ραντεβού με τη Δρ. Μανωλάκου</h4><p>Η κατάσταση του σκύλου σας απαιτεί εξατομικευμένη ιατρική προσέγγιση. Ο κωδικός σας για <strong>10% έκπτωση</strong> στο ραντεβού είναι:</p><div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; text-align:center;">' . $dynamic_code . '</div></div>';
        if ( is_email( $user_email ) ) { wp_mail( $user_email, 'Κωδικός Κλινικής Δίαιτας', $html, $headers ); }
        wp_send_json_success($html); 
        exit;
    }
    
    // 3. ΥΠΟΛΟΓΙΣΜΟΣ ΣΥΝΤΑΓΗΣ
    $p1 = isset($_POST['protein_1']) ? sanitize_text_field($_POST['protein_1']) : ''; 
    $p2 = isset($_POST['protein_2']) ? sanitize_text_field($_POST['protein_2']) : ''; 
    $c1 = isset($_POST['carb_1']) ? sanitize_text_field($_POST['carb_1']) : ''; 
    $c2 = isset($_POST['carb_2']) ? sanitize_text_field($_POST['carb_2']) : ''; 
    $fruit = isset($_POST['fruit']) ? sanitize_text_field($_POST['fruit']) : ''; 
    $oil = isset($_POST['oil']) ? sanitize_text_field($_POST['oil']) : ''; 
    $veggies = isset($_POST['veggies']) ? array_map('sanitize_text_field', $_POST['veggies']) : []; 
    $veg_str = implode(' & ', $veggies);
    
    $total_g = 300; 
    $g_p1 = round($total_g * 0.50); 
    $g_p2 = round($total_g * 0.05); 
    $g_c1 = round($total_g * 0.15); 
    $g_c2 = round($total_g * 0.10); 
    $g_veg = round($total_g * 0.13); 
    $g_fruit = round($total_g * 0.05); 
    $g_oil = round($total_g * 0.02);

    $recipe_summary_text = "{$g_p1}γρ {$p1} / {$g_p2}γρ {$p2} / {$g_c1}γρ {$c1} / {$g_c2}γρ {$c2} / {$g_veg}γρ {$veg_str} / {$g_fruit}γρ {$fruit} / {$g_oil}γρ {$oil}";
    
    // Αποθήκευση στο CRM & Διασύνδεση με το User ID
    $pid = ptl_save_quiz_lead_data($user_email, $user_name, 'HOME_COOKED_RECIPE', $recipe_summary_text);
    if ($pid) {
        update_post_meta($pid, 'ptl_user_id', $user_id);
        if (!empty($health_condition)) update_post_meta($pid, 'ptl_health_condition', $health_condition);
        
        $recipe_data_array = array('p1' => $p1, 'p2' => $p2, 'c1' => $c1, 'c2' => $c2, 'veggies' => $veggies, 'fruit' => $fruit, 'oil' => $oil, 'total_g' => $total_g);
        update_post_meta($pid, 'ptl_recipe_data', json_encode($recipe_data_array, JSON_UNESCAPED_UNICODE));
    }
    
    // 4. ΕΜΦΑΝΙΣΗ (Οδήγηση στο Dashboard)
    $dashboard_url = home_url('/my-account/'); 
    
    $html = '<h3>🎉 Η Συνταγή σας είναι έτοιμη!</h3>';
    $html .= '<div class="ptl-result-box" style="text-align:left;">';
    
    if ($new_account) {
        $html .= '<div style="background:#eef7ee; color:#5b9a68; border-left:4px solid #5b9a68; padding:15px; margin-bottom:20px; font-size:14px;">✅ <strong>Ο λογαριασμός σας δημιουργήθηκε επιτυχώς!</strong> Έχετε ήδη συνδεθεί.</div>';
    } else {
        $html .= '<div style="background:#eef7ee; color:#5b9a68; border-left:4px solid #5b9a68; padding:15px; margin-bottom:20px; font-size:14px;">✅ <strong>Καλώς ήρθατε ξανά!</strong> Συνδεθήκατε επιτυχώς.</div>';
    }
    
    $html .= '<p>Η συνταγή του σκύλου σας αποθηκεύτηκε με ασφάλεια στο <strong>Προσωπικό σας Προφίλ</strong>. Από εκεί μπορείτε να τη βλέπετε όποτε θέλετε.</p>';
    $html .= '<div style="text-align:center;"><a href="' . esc_url($dashboard_url) . '" style="display:inline-block; margin-top:20px; background:#43282F; color:#fff; padding:15px 30px; border-radius:6px; text-decoration:none; font-weight:bold; font-size:16px;">Προβολή Συνταγής στο Προφίλ μου &rarr;</a></div>';
    $html .= '</div>';

    wp_send_json_success($html);
}
// =========================================================================
// 9. USER DASHBOARD (WOOCOMMERCE MY ACCOUNT TAB)
// =========================================================================

add_action( 'init', 'ptl_add_recipes_endpoint' );
function ptl_add_recipes_endpoint() {
    add_rewrite_endpoint( 'diatrofi', EP_ROOT | EP_PAGES );
}

add_filter( 'woocommerce_account_menu_items', 'ptl_recipes_menu_item' );
function ptl_recipes_menu_item( $items ) {
    $new_items = array();
    foreach ( $items as $key => $value ) {
        $new_items[ $key ] = $value;
        if ( 'dashboard' === $key ) {
            $new_items['diatrofi'] = '👨‍🍳 Οι Συνταγές μου';
        }
    }
    if ( ! array_key_exists( 'diatrofi', $new_items ) ) {
        $new_items['diatrofi'] = '👨‍🍳 Οι Συνταγές μου';
    }
    return $new_items;
}

add_action( 'woocommerce_account_diatrofi_endpoint', 'ptl_recipes_endpoint_content' );
function ptl_recipes_endpoint_content() {
    echo '<h3 style="color:#43282F; margin-bottom: 20px;">Οι Συνταγές του Σκύλου μου 🐾</h3>';
    echo '<p>Η διαχείριση της διατροφής μεταφέρθηκε στο νέο, αναβαθμισμένο περιβάλλον του Petling!</p>';
    echo '<a href="'.home_url('/nutrition/').'" class="button" style="background:#43282F; color:#fff; padding:12px 25px; border-radius:6px; text-decoration:none;">Μετάβαση στο Πρόγραμμα Διατροφής &rarr;</a>';
}

// =========================================================================
// 10. CUSTOM USER DASHBOARD SHORTCODE (TABS & PDF GENERATION)
// =========================================================================

add_shortcode('petling_user_dashboard', 'ptl_custom_user_dashboard_shortcode');
function ptl_custom_user_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div style="background:#fffaf1; padding:30px; text-align:center; border:2px dashed #C7B297; border-radius:12px;"><h3>🔒 Απαιτείται Σύνδεση</h3><p>Παρακαλώ συνδεθείτε για να δείτε το προσωπικό σας προφίλ.</p><a href="' . wp_login_url(get_permalink()) . '" class="button" style="background:#43282F; color:#fff;">Σύνδεση</a></div>';
    }

    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $user_email = $current_user->user_email;

    // Παίρνουμε το λογότυπο του Petling για το PDF
    $logo_url = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
    if (empty($logo_url)) { $logo_url = home_url('/wp-content/uploads/logo.png'); }

    ob_start();
    ?>
    <style>
        .ptl-dash-container { display: flex; flex-direction: column; gap: 20px; font-family: sans-serif; }
        @media(min-width: 768px) { .ptl-dash-container { flex-direction: row; align-items: flex-start; } }
        .ptl-dash-menu { display: flex; flex-direction: row; gap: 10px; flex-wrap: wrap; background: #fffaf1; padding: 15px; border-radius: 12px; border: 1px solid #e0d5c1; }
        @media(min-width: 768px) { .ptl-dash-menu { flex-direction: column; min-width: 220px; } }
        .ptl-dash-menu button { background: none; border: 2px solid transparent; padding: 12px 20px; text-align: left; cursor: pointer; font-size: 15px; font-weight: bold; color: #43282F; border-radius: 8px; transition: 0.3s; }
        .ptl-dash-menu button:hover { background: #F5EDE3; }
        .ptl-dash-menu button.active { background: #43282F; color: #fff; }
        .ptl-dash-content { flex-grow: 1; background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e0d5c1; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .ptl-tab-pane { display: none; animation: fadeIn 0.4s; }
        .ptl-tab-pane.active { display: block; }
        .ptl-recipe-card { border: 2px solid #C7B297; border-radius: 10px; padding: 20px; margin-bottom: 20px; background: #fffaf1; }
        .ptl-coupon-card { border: 2px dashed #5b9a68; border-radius: 10px; padding: 15px; margin-bottom: 15px; background: #eef7ee; display: flex; justify-content: space-between; align-items: center; }
        .ptl-event-card { border: 2px solid #ddd; border-radius: 10px; padding: 20px; text-align: center; background: #fafafa; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .ptl-pdf-template { display: none; }
    </style>

    <div class="ptl-dash-container">
        <!-- Αριστερό Μενού -->
        <div class="ptl-dash-menu">
            <button class="ptl-tab-btn active" onclick="ptlSwitchTab('nutrition')">🥣 Διατροφή</button>
            <button class="ptl-tab-btn" onclick="ptlSwitchTab('events')">🗓️ Σεμινάρια & Events</button>
            <button class="ptl-tab-btn" onclick="ptlSwitchTab('coupons')">🎁 Τα Κουπόνια μου</button>
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" style="padding: 12px 20px; text-decoration: none; color: #d63638; font-weight: bold; margin-top: 10px; font-size: 14px;">Αποσύνδεση</a>
        </div>

        <!-- Περιεχόμενο -->
        <div class="ptl-dash-content">
            
            <!-- ΤΑΒ 1: ΔΙΑΤΡΟΦΗ -->
            <div id="tab-nutrition" class="ptl-tab-pane active">
                <h3 style="margin-top:0; color:#43282F;">Το Πρόγραμμα Διατροφής του Σκύλου μου</h3>
                <?php
                $args = array(
                    'post_type'  => 'ptl_quiz_lead',
                    'post_status'=> 'publish',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        array('key' => 'ptl_user_id', 'value' => $user_id, 'compare' => '='),
                        array('key' => 'ptl_lead_type', 'value' => 'HOME_COOKED_RECIPE', 'compare' => '=')
                    )
                );
                $recipes = new WP_Query( $args );

                if ( $recipes->have_posts() ) {
                    $recipe_counter = 1;
                    while ( $recipes->have_posts() ) {
                        $recipes->the_post();
                        $post_id = get_the_ID();
                        $recipe_data_json = get_post_meta($post_id, 'ptl_recipe_data', true);
                        $result_text = get_post_meta($post_id, 'ptl_lead_result', true);

                        if (strpos($result_text, 'VET') !== false) {
                            $health_cond = get_post_meta($post_id, 'ptl_health_condition', true);
                            echo '<div class="ptl-recipe-card" style="border-color:#d63638; background:#fff3f3;">';
                            echo '<h4 style="margin-top:0; color:#d63638;">🩺 Απαιτείται Κλινική Δίαιτα</h4>';
                            echo '<p>Η συνταγή σας απαιτεί κτηνιατρική καθοδήγηση (<strong>'.esc_html($health_cond).'</strong>).</p>';
                            echo '</div>';
                        } elseif ($recipe_data_json) {
                            $data = json_decode($recipe_data_json, true);
                            $total_g = isset($data['total_g']) ? floatval($data['total_g']) : 300;
                            $veg_str = is_array($data['veggies']) ? implode(' & ', $data['veggies']) : $data['veggies'];
                            
                            $g_p1 = round($total_g * 0.50);
                            $g_p2 = round($total_g * 0.05);
                            $g_c1 = round($total_g * 0.15);
                            $g_c2 = round($total_g * 0.10);
                            $g_veg = round($total_g * 0.13);
                            $g_fruit = round($total_g * 0.05);
                            $g_oil = round($total_g * 0.02);

                            // ΕΜΦΑΝΙΣΗ ΣΤΟ DASHBOARD
                            echo '<div class="ptl-recipe-card">';
                            echo '<h4 style="margin-top:0; color:#43282F;">Σπιτική Συνταγή με ' . esc_html($data['p1']) . '</h4>';
                            echo '<p style="font-size:13px; color:#666; margin-bottom:15px;">Σύνολο: ' . $total_g . 'γρ.</p>';
                            echo '<ul style="list-style:none; padding:0; margin:0 0 20px 0; font-size:14px;">';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥩 <strong>'.$g_p1.'γρ</strong> '.esc_html($data['p1']).'</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥚 <strong>'.$g_p2.'γρ</strong> '.esc_html($data['p2']).'</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🌾 <strong>'.$g_c1.'γρ</strong> '.esc_html($data['c1']).'</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥔 <strong>'.$g_c2.'γρ</strong> '.esc_html($data['c2']).'</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥦 <strong>'.$g_veg.'γρ</strong> '.esc_html($veg_str).'</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🍎 <strong>'.$g_fruit.'γρ</strong> '.esc_html($data['fruit']).'</li>';
                            echo '<li style="padding:4px 0;">🫒 <strong>'.$g_oil.'γρ</strong> '.esc_html($data['oil']).'</li>';
                            echo '</ul>';
                            echo '<button class="button" style="background:#43282F; color:#fff; border:none;" onclick="ptlDownloadPDF(\'pdf-content-' . $recipe_counter . '\', this)">⬇️ Λήψη σε PDF</button>';
                            echo '</div>';

                            // ΚΡΥΦΟ TEMPLATE ΓΙΑ ΤΟ PDF (Καθαρή Λίστα)
                            echo '<div id="pdf-content-' . $recipe_counter . '" class="ptl-pdf-template">';
                            
                            // Header
                            echo '<div style="text-align:center; border-bottom:2px solid #C7B297; padding-bottom:15px; margin-bottom:20px;">';
                            echo '<img src="'.esc_url($logo_url).'" style="max-height:60px; margin-bottom:10px;" alt="Petling Logo">';
                            echo '<h2 style="color:#43282F; margin:0; font-size:22px;">Σπιτική Συνταγή Σκύλου</h2>';
                            echo '<p style="color:#666; margin:5px 0 0 0; font-size:13px;">Εξατομικευμένο πρόγραμμα από το Petling</p>';
                            echo '</div>';
                            
                            // Τίτλος & Γραμμάρια
                            echo '<h3 style="color:#43282F; margin-top:0;">Προτεινόμενο Γεύμα με ' . esc_html($data['p1']) . '</h3>';
                            echo '<p style="font-size:14px; font-weight:bold; margin-bottom: 15px;">Ημερήσια Ποσότητα: ' . $total_g . ' γραμμάρια</p>';
                            
                            // Καθαρή Λίστα 
                            echo '<ul style="list-style:none; padding:0; margin:0 0 30px 0; font-size:15px; color:#333;">';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥩 <strong>'.$g_p1.'γρ</strong> '.esc_html($data['p1']).'</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥚 <strong>'.$g_p2.'γρ</strong> '.esc_html($data['p2']).'</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🌾 <strong>'.$g_c1.'γρ</strong> '.esc_html($data['c1']).'</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥔 <strong>'.$g_c2.'γρ</strong> '.esc_html($data['c2']).'</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥦 <strong>'.$g_veg.'γρ</strong> '.esc_html($veg_str).'</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🍎 <strong>'.$g_fruit.'γρ</strong> '.esc_html($data['fruit']).'</li>';
                            echo '<li style="padding:8px 0;">🫒 <strong>'.$g_oil.'γρ</strong> '.esc_html($data['oil']).'</li>';
                            echo '</ul>';
                            
                            // Disclaimer
                            echo '<div style="padding:15px; background:#fffaf1; border-left:4px solid #d63638; border-radius:4px; margin-bottom: 30px;">';
                            echo '<h4 style="margin-top:0; color:#d63638; font-size:15px;">⚠️ Σημαντική Επιστημονική Επισήμανση</h4>';
                            echo '<p style="font-size:12px; line-height:1.5; color:#555; margin:0;">Οι ποσότητες είναι ενδεικτικές και ενδέχεται να χρειαστούν προσαρμογή ανάλογα με τον μεταβολισμό και τη δραστηριότητα του σκύλου σας. Η συγκεκριμένη διατροφή <strong>δεν πρέπει να εφαρμόζεται εφ’ όρου ζωής χωρίς επίβλεψη</strong>. Για να είναι πλήρως ισορροπημένη, πρέπει να εμπλουτίζεται σωστά και να επανεξετάζεται τακτικά.</p>';
                            echo '</div>';
                            
                            // Υπογραφή
                            echo '<div style="text-align:right;">';
                            echo '<p style="margin:0; font-size:14px; color:#333;"><strong>Δρ. Σταυριάννα Μανωλάκου</strong></p>';
                            echo '<p style="margin:0; font-size:12px; color:#666;">Κτηνίατρος - Ειδική Διατροφολόγος Ζώων Συντροφιάς</p>';
                            echo '</div>';
                            
                            echo '</div>'; // Κλείσιμο Κρυφού Template
                            
                            $recipe_counter++;
                        }
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p>Δεν έχετε δημιουργήσει ακόμα κάποια συνταγή.</p>';
                }
                ?>
            </div>

            <!-- ΤΑΒ 2: EVENTS -->
            <div id="tab-events" class="ptl-tab-pane">
                <h3 style="margin-top:0; color:#43282F;">Σεμινάρια & Αποκλειστικά Events</h3>
                <div class="ptl-event-card">
                    <h4 style="color:#C7B297; margin-bottom:5px;">Προσεχώς...</h4>
                    <p>Μείνετε συντονισμένοι! Ετοιμάζουμε αποκλειστικά Q&A webinars και σεμινάρια διατροφής με την <strong>Δρ. Σταυριάννα Μανωλάκου</strong>.</p>
                </div>
            </div>

            <!-- ΤΑΒ 3: ΚΟΥΠΟΝΙΑ -->
            <div id="tab-coupons" class="ptl-tab-pane">
                <h3 style="margin-top:0; color:#43282F;">Τα Ενεργά Κουπόνια μου</h3>
                <?php
                global $wpdb;
                $promo_table = $wpdb->prefix . 'petling_partner_leads';
                if ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table) {
                    $coupons = $wpdb->get_results($wpdb->prepare("SELECT * FROM $promo_table WHERE email = %s AND status = 'active' AND partner_prefix = 'VET' ORDER BY created_at DESC", $user_email));
                    
                    if ($coupons) {
                        foreach ($coupons as $coupon) {
                            echo '<div class="ptl-coupon-card">';
                            echo '<div><strong>Δρ. Μανωλάκου (VET)</strong><br><span style="font-size:12px;">Έκπτωση 10% στο ραντεβού</span></div>';
                            echo '<div style="font-size:18px; font-weight:bold; letter-spacing:1px; background:#fff; padding:5px 15px; border-radius:6px; border:1px solid #5b9a68;">' . esc_html($coupon->coupon_code) . '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p>Δεν έχετε ενεργά κουπόνια ραντεβού αυτή τη στιγμή.</p>';
                    }
                }
                ?>
            </div>

        </div>
    </div>

    <!-- Ενσωμάτωση της βιβλιοθήκης html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function ptlSwitchTab(tabName) {
        document.querySelectorAll('.ptl-tab-pane').forEach(function(pane) { pane.classList.remove('active'); });
        document.querySelectorAll('.ptl-tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
        document.getElementById('tab-' + tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    function ptlDownloadPDF(elementId, btnElement) {
        var originalText = btnElement.innerHTML;
        btnElement.innerHTML = '⏳ Δημιουργία PDF...';
        btnElement.disabled = true;

        // 1. Παίρνουμε το περιεχόμενο της κρυφής συνταγής
        var sourceElement = document.getElementById(elementId);
        
        // 2. Το βάζουμε σε ένα καθαρό, εικονικό div της Javascript. 
        // Έτσι το html2pdf το διαβάζει 100% καθαρά, χωρίς να επηρεάζεται από CSS ή κρυφά z-index του site!
        var virtualDiv = document.createElement('div');
        virtualDiv.innerHTML = sourceElement.innerHTML;
        virtualDiv.style.padding = '20px';
        virtualDiv.style.background = '#fff';
        virtualDiv.style.fontFamily = 'Helvetica, sans-serif';
        virtualDiv.style.color = '#333';

        // 3. Ρυθμίσεις (Το scrollY: 0 λύνει οριστικά το πρόβλημα του μεγάλου κενού στην κορυφή)
        var opt = {
            margin:       15,
            filename:     'Petling_Syntagi.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, scrollY: 0, windowY: 0 },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        // 4. Παραγωγή PDF απευθείας από το εικονικό div!
        html2pdf().set(opt).from(virtualDiv).save().then(function() {
            btnElement.innerHTML = originalText;
            btnElement.disabled = false;
        }).catch(function(error) {
            console.error('Σφάλμα δημιουργίας PDF:', error);
            btnElement.innerHTML = originalText;
            btnElement.disabled = false;
        });
    }
    </script>
    <?php
    return ob_get_clean();
}