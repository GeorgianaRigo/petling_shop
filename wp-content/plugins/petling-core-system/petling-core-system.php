<?php
/*
Plugin Name: Petling Core System
Plugin URI: https://petling.gr
Description: Το κεντρικό, ΕΝΙΑΙΟ σύστημα του Petling. Περιλαμβάνει: Κεντρικό CRM, Διατροφικό Σύμβουλο, Δημιουργία Συνταγής, Κουπόνια & QR Συνεργατών.
Version: 3.0
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. ΕΓΚΑΤΑΣΤΑΣΗ ΒΑΣΗΣ ΚΟΥΠΟΝΙΩΝ
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
// 1. CUSTOM POST TYPE: QUIZ LEADS (Κρυφό από προεπιλογή)
// =========================================================================
add_action('init', 'ptl_register_leads_cpt');
function ptl_register_leads_cpt() {
    register_post_type('ptl_quiz_lead', array(
        'labels' => array( 
            'name' => 'Συνεργάτης - Κουπιάνια', 
            'singular_name' => 'Lead', 
            'all_items' => 'Συνεργάτης - Κουπιάνια' 
        ),
        'public' => false, 
        'show_ui' => true, 
        // ΣΗΜΑΝΤΙΚΟ: false για να μην φτιάξει δικό του ανεξάρτητο μενού και σπάσει τα υπόλοιπα.
        // Το "κρεμάμε" χειροκίνητα στην ακριβώς από κάτω συνάρτηση.
        'show_in_menu' => false, 
        'supports' => array('title')
    ));
}

// =========================================================================
// 2. ΕΝΟΠΟΙΗΜΕΝΟ ΜΕΝΟΥ ADMIN (ΜΕ ΤΗ ΛΟΓΙΚΗ ΠΟΥ ΔΟΥΛΕΥΕΙ ΣΤΑ ΑΛΛΑ PLUGINS)
// =========================================================================
add_action( 'admin_menu', 'petling_core_unified_admin_menu' );
function petling_core_unified_admin_menu() {
    
    // Ελέγχουμε αν υπάρχει ήδη η ομπρέλα "Petling" από τα άλλα μας plugins
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        // Αν δεν υπάρχει, την δημιουργούμε
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'petling_core_settings_page', 'dashicons-pets', 55 );
    
        // Κρεμάμε τη Συνεργάτης - Κουπιάνια (Quiz Leads)
        add_submenu_page( 'petling-main', 'Συνεργάτης - Κουπιάνια', 'Συνεργάτης - Κουπιάνια', 'manage_options', 'edit.php?post_type=ptl_quiz_lead' );
        
    }
}

// =========================================================================
// 3. CRM LEADS (ΛΙΣΤΑ ΠΕΛΑΤΩΝ)
// =========================================================================
add_filter('manage_ptl_quiz_lead_posts_columns', 'ptl_set_custom_lead_columns');
function ptl_set_custom_lead_columns($columns) {
    return array( 'cb' => $columns['cb'], 'title' => 'Email Χρήστη', 'ptl_name' => 'Όνομα', 'ptl_type' => 'Πηγή (Quiz / Συνεργάτης)', 'ptl_result' => 'Αποτέλεσμα / Κωδικός', 'ptl_status' => 'Κατάσταση', 'date' => $columns['date'] );
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

// ΔΙΟΡΘΩΣΗ: Πλέον επιστρέφει το ID για να μπορούμε να σώζουμε το ιατρικό ιστορικό!
function ptl_save_quiz_lead_data($email, $name, $type, $result) {
    $existing_post = get_page_by_title($email, OBJECT, 'ptl_quiz_lead');
    $post_id = $existing_post ? $existing_post->ID : wp_insert_post(array('post_title' => $email, 'post_type' => 'ptl_quiz_lead', 'post_status' => 'publish'));
    update_post_meta($post_id, 'ptl_lead_name', sanitize_text_field($name));
    update_post_meta($post_id, 'ptl_lead_type', sanitize_text_field($type));
    update_post_meta($post_id, 'ptl_lead_result', sanitize_text_field($result));
    return $post_id;
}

add_action('restrict_manage_posts', 'ptl_quiz_add_admin_filters');
function ptl_quiz_add_admin_filters($post_type) {
    if ($post_type !== 'ptl_quiz_lead') return;
    $selected_type = isset($_GET['ptl_filter_type']) ? sanitize_text_field($_GET['ptl_filter_type']) : '';
    $selected_status = isset($_GET['ptl_filter_status']) ? sanitize_text_field($_GET['ptl_filter_status']) : '';
    ?>
    <select name="ptl_filter_type">
        <option value="">Όλες οι Πηγές</option>
        <option value="FOOD_YOGGIES" <?php selected($selected_type, 'FOOD_YOGGIES'); ?>>Quiz Τροφής (Yoggies)</option>
        <option value="HOME_COOKED_RECIPE" <?php selected($selected_type, 'HOME_COOKED_RECIPE'); ?>>Μαγειρευτή Συνταγή</option>
        <option value="PROMO_JOY" <?php selected($selected_type, 'PROMO_JOY'); ?>>Συνεργάτης (JOY)</option>
        <option value="VET_MANOLAKOU" <?php selected($selected_type, 'VET_MANOLAKOU'); ?>>Δρ. Μανωλάκου (VET)</option>
    </select>
    <select name="ptl_filter_status">
        <option value="">Κατάσταση Κουπονιού</option>
        <option value="active" <?php selected($selected_status, 'active'); ?>>🟢 Ενεργά</option>
        <option value="redeemed" <?php selected($selected_status, 'redeemed'); ?>>✔️ Εξαργυρωμένα</option>
    </select>
    <?php
}

add_filter('pre_get_posts', 'ptl_quiz_filter_by_type');
function ptl_quiz_filter_by_type($query) {
    global $pagenow, $wpdb;
    if ( is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'ptl_quiz_lead' && $query->is_main_query() ) {
        if (!empty($_GET['ptl_filter_type'])) { $query->set('meta_query', array( array( 'key' => 'ptl_lead_type', 'value' => sanitize_text_field($_GET['ptl_filter_type']), 'compare' => '=' ) )); }
        if (!empty($_GET['ptl_filter_status'])) {
            $status = sanitize_text_field($_GET['ptl_filter_status']);
            $promo_table = $wpdb->prefix . 'petling_partner_leads';
            if ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table) {
                $emails = $wpdb->get_col($wpdb->prepare("SELECT email FROM $promo_table WHERE status = %s", $status));
                if (!empty($emails)) {
                    $emails_list = "'" . implode("','", array_map('esc_sql', $emails)) . "'";
                    $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_title IN ($emails_list) AND post_type = 'ptl_quiz_lead'");
                    if (empty($post_ids)) { $query->set('post__in', array(0)); } else {
                        $current_in = $query->get('post__in');
                        if (!empty($current_in)) { $post_ids = array_intersect($current_in, $post_ids); if (empty($post_ids)) $post_ids = array(0); }
                        $query->set('post__in', $post_ids);
                    }
                } else { $query->set('post__in', array(0)); }
            }
        }
    }
}

add_action('admin_notices', 'ptl_quiz_admin_notice');
function ptl_quiz_admin_notice() {
    global $typenow;
    if ( $typenow === 'ptl_quiz_lead' ) { echo '<div class="notice notice-info" style="border-left-color: #C7B297; padding: 10px;"><p style="font-size: 14px; margin: 0;"><strong>💡 Ενιαίο CRM:</strong> Εδώ βλέπετε συγκεντρωμένους όλους τους πελάτες από το Quiz Τροφής, τις Συνταγές και τα QR Codes των Συνεργατών.</p></div>'; }
}

// =========================================================================
// 4. ΡΥΘΜΙΣΕΙΣ ΣΥΝΕΡΓΑΤΩΝ & QR CODE (TABS)
// =========================================================================
function petling_promo_get_partners() { $partners = get_option( 'petling_promo_partners', array() ); return is_array($partners) ? $partners : array(); }
function petling_promo_get_partner( $prefix ) { $prefix = strtoupper( trim( $prefix ) ); foreach ( petling_promo_get_partners() as $partner ) { if ( strtoupper( $partner['prefix'] ) === $prefix ) { return $partner; } } return null; }
function petling_promo_format_amount( $partner ) { if ( 'percent' === $partner['discount_type'] ) { return '-' . floatval( $partner['amount'] ) . '%'; } return '-' . floatval( $partner['amount'] ) . '€'; }
function petling_promo_generate_code( $prefix ) { return strtoupper( $prefix ) . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 5 ) ); }
function petling_promo_get_last_claim( $email, $prefix ) { global $wpdb; $table = $wpdb->prefix . 'petling_partner_leads'; return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1", $email, strtoupper( $prefix ) ) ); }

add_action( 'admin_init', 'petling_promo_handle_admin_actions' );
function petling_promo_handle_admin_actions() {
    if ( ! isset( $_GET['page'] ) || 'ptl-promo-settings' !== $_GET['page'] ) return;

    if ( isset( $_POST['petling_promo_save_partners'] ) && isset( $_POST['petling_promo_partners_nonce'] ) && wp_verify_nonce( $_POST['petling_promo_partners_nonce'], 'petling_promo_save_partners_action' ) ) {
        $prefixes = isset( $_POST['p_prefix'] ) ? (array) $_POST['p_prefix'] : array(); $new_partners = array();
        foreach ( $prefixes as $i => $prefix ) {
            $prefix = strtoupper( sanitize_text_field( $prefix ) );
            if ( '' === $prefix || isset( $_POST['p_delete'][ $i ] ) ) continue;
            $new_partners[] = array( 'prefix' => $prefix, 'label' => sanitize_text_field( $_POST['p_label'][ $i ] ?? $prefix ), 'type' => in_array( $_POST['p_type'][ $i ] ?? '', array( 'shop', 'appointment' ), true ) ? $_POST['p_type'][ $i ] : 'shop', 'discount_type' => in_array( $_POST['p_discount_type'][ $i ] ?? '', array( 'fixed', 'percent' ), true ) ? $_POST['p_discount_type'][ $i ] : 'fixed', 'amount' => floatval( $_POST['p_amount'][ $i ] ?? 0 ), 'min_order' => floatval( $_POST['p_min_order'][ $i ] ?? 0 ), 'lock_days' => intval( $_POST['p_lock_days'][ $i ] ?? 20 ), 'password' => sanitize_text_field( $_POST['p_password'][ $i ] ?? '' ) );
        }
        update_option( 'petling_promo_partners', $new_partners );
        wp_redirect( admin_url( 'edit.php?post_type=ptl_quiz_lead&page=ptl-promo-settings&tab=settings&saved=1' ) ); exit;
    }
}

function petling_promo_settings_page() {
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
    echo '<div class="wrap"><h2>🐾 Ρυθμίσεις Promo & QR Code</h2><h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">';
    echo '<a href="?post_type=ptl_quiz_lead&page=ptl-promo-settings&tab=settings" class="nav-tab ' . ('settings' === $active_tab ? 'nav-tab-active' : '') . '">Ρυθμίσεις Συνεργατών</a>';
    echo '<a href="?post_type=ptl_quiz_lead&page=ptl-promo-settings&tab=qrcode" class="nav-tab ' . ('qrcode' === $active_tab ? 'nav-tab-active' : '') . '">Δημιουργία QR Code</a></h2>';
    if ( 'settings' === $active_tab ) petling_promo_render_settings_tab(); elseif ( 'qrcode' === $active_tab ) petling_promo_render_qrcode_tab();
    echo '</div>';
}

function petling_promo_render_settings_tab() {
    $partners = petling_promo_get_partners();
    if ( isset( $_GET['saved'] ) ) echo '<div class="notice notice-success" style="padding:12px;"><strong>Αποθηκεύτηκε!</strong> Οι ρυθμίσεις ενημερώθηκαν.</div>';
    ?>
    <form method="post" action=""><?php wp_nonce_field( 'petling_promo_save_partners_action', 'petling_promo_partners_nonce' ); ?>
    <table class="widefat striped" style="background:#fff; margin-top:15px;"><thead><tr><th>Prefix</th><th>Ετικέτα (όνομα)</th><th>Τύπος</th><th>Είδος έκπτωσης</th><th>Ποσό</th><th>Ελάχ. παραγγελία (€)</th><th>Μέρες αναμονής</th><th>Password (Ραντεβού)</th><th>Διαγραφή</th></tr></thead><tbody>
    <?php $rows = $partners; $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' ); $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' );
    foreach ( $rows as $i => $p ) : ?>
        <tr><td><input type="text" name="p_prefix[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['prefix'] ); ?>" style="width:80px;"></td><td><input type="text" name="p_label[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['label'] ); ?>" style="width:180px;"></td><td><select name="p_type[<?php echo $i; ?>]"><option value="shop" <?php selected( $p['type'], 'shop' ); ?>>Shop</option><option value="appointment" <?php selected( $p['type'], 'appointment' ); ?>>Ραντεβού</option></select></td><td><select name="p_discount_type[<?php echo $i; ?>]"><option value="fixed" <?php selected( $p['discount_type'], 'fixed' ); ?>>Σταθερό (€)</option><option value="percent" <?php selected( $p['discount_type'], 'percent' ); ?>>Ποσοστό (%)</option></select></td><td><input type="number" step="0.5" name="p_amount[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['amount'] ); ?>" style="width:70px;"></td><td><input type="number" step="1" name="p_min_order[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['min_order'] ); ?>" style="width:70px;"></td><td><input type="number" step="1" name="p_lock_days[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['lock_days'] ); ?>" style="width:70px;"></td><td><input type="text" name="p_password[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['password'] ); ?>" style="width:110px;"></td><td style="text-align:center;"><input type="checkbox" name="p_delete[<?php echo $i; ?>]" value="1"></td></tr>
    <?php endforeach; ?></tbody></table><br><?php submit_button( 'Αποθήκευση Συνεργατών', 'primary', 'petling_promo_save_partners' ); ?></form>
    <?php
}

function petling_promo_render_qrcode_tab() {
    echo '<div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:25px; max-width: 600px; margin-top:20px;"><h3 style="margin-top:0; color:#43282F;">📱 Γεννήτρια QR Code</h3><table class="form-table"><tr><th scope="row">Όνομα Συνεργάτη</th><td><input type="text" id="qr_partner_name" class="regular-text"></td></tr><tr><th scope="row">URL Σελίδας</th><td><input type="url" id="qr_partner_url" class="regular-text" placeholder="https://petling.gr/joy"></td></tr></table><p style="margin-top:20px;"><button type="button" class="button button-primary" onclick="generatePetlingQRCode()">Δημιουργία QR Code</button></p><div id="qr_result_area" style="display:none; margin-top: 30px; text-align: center;"><canvas id="qr_canvas" style="border: 1px solid #eee; padding: 10px; border-radius: 8px; background: #fff;"></canvas><br><br><a id="qr_download_btn" class="button button-secondary" download="qr-code.png">⬇️ Λήψη σε μορφή PNG</a></div></div><script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script><script>function generatePetlingQRCode() { var url = document.getElementById("qr_partner_url").value; var name = document.getElementById("qr_partner_name").value; if (!url) { alert("Παρακαλώ εισάγετε URL!"); return; } var qr = new QRious({ element: document.getElementById("qr_canvas"), value: url, size: 300, level: "H" }); document.getElementById("qr_result_area").style.display = "block"; var canvas = document.getElementById("qr_canvas"); var dataURL = canvas.toDataURL("image/png"); var dlBtn = document.getElementById("qr_download_btn"); dlBtn.href = dataURL; dlBtn.download = "qr-petling-" + (name ? name.toLowerCase().replace(/[^a-z0-9]/g, "-") : "partner") + ".png"; }</script>';
}

// =========================================================================
// 5. YOGGIES NUTRITION QUIZ (ΔΕΔΟΜΕΝΑ & SHORTCODE)
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

add_action('wp_enqueue_scripts', 'ptl_core_enqueue_assets');
function ptl_core_enqueue_assets() { wp_enqueue_script( 'jquery' ); }

add_shortcode('petling_nutrition_quiz', 'ptl_nutrition_quiz_shortcode');
function ptl_nutrition_quiz_shortcode() {
    $html = '<style>.ptl-quiz-container { max-width: 650px; margin: 40px auto; background: #fffaf1; padding: 40px; border-radius: 12px; border: 2px dashed #C7B297; font-family: sans-serif; box-shadow: 0 5px 20px rgba(0,0,0,0.05); } .ptl-step { display: none; animation: fadeIn 0.4s; } .ptl-step.active { display: block; } .ptl-quiz-container h3 { color: #43282F; margin-top: 0; font-size: 24px; text-align: center; margin-bottom:10px; } .ptl-quiz-container p.ptl-desc { color: #555; font-size: 15px; margin-bottom: 25px; text-align: center; line-height: 1.5; } .ptl-quiz-container label { display: block; font-weight: bold; color: #43282F; margin-bottom: 8px; margin-top: 15px; } .ptl-quiz-container input[type="number"], .ptl-quiz-container input[type="email"], .ptl-quiz-container input[type="text"], .ptl-quiz-container select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 15px; } .ptl-radio-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; } .ptl-radio-group label { flex: 1 1 calc(50% - 10px); min-width:140px; text-align: center; background: #fff; border: 2px solid #e0d5c1; padding: 15px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-weight: normal; margin-top: 0; } .ptl-radio-group input[type="radio"] { display: none; } .ptl-radio-group input[type="radio"]:checked + label { border-color: #C7B297; background: #F5EDE3; color: #43282F; font-weight: bold; } .ptl-quiz-nav { display: flex; justify-content: space-between; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 20px; } .ptl-btn-prev { background: #e0d5c1; color: #43282F; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; } .ptl-btn-next, .ptl-btn-submit { background: #43282F; color: #fff; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; } .ptl-btn-next:hover, .ptl-btn-submit:hover { background: #C7B297; color: #43282F; } .ptl-btn-prev:hover { background: #C7B297; color: #fff; } .ptl-loader { text-align:center; padding:20px; font-weight:bold; color:#43282F; display:none; } .ptl-result-box { background:#fff; padding:25px; border-radius:8px; border:2px solid #C7B297; margin-top:20px; text-align:center; } .ptl-transition-box { background: #F5EDE3; border-left: 4px solid #C7B297; padding: 15px 20px; margin-top: 25px; text-align: left; font-size: 14px; color: #43282F; line-height: 1.6; border-radius: 0 8px 8px 0; } .ptl-transition-box h5 { margin: 0 0 10px 0; font-size: 16px; color: #43282F; } @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }</style>';
    $html .= '<div class="ptl-quiz-container" id="ptl-nutrition-app"><form id="ptl-nutrition-form">' . wp_nonce_field('ptl_nutrition_nonce', 'security', true, false);
    $html .= '<div class="ptl-step active" id="ptl-step-0"><h3>🐾 Ας ξεκινήσουμε!</h3><p class="ptl-desc">Για ποιο μουσουδάκι ψάχνουμε την ιδανική τροφή;</p><div class="ptl-radio-group"><input type="radio" id="pet_dog" name="pet_type" value="dog" checked><label for="pet_dog">🐶 Σκύλος</label><input type="radio" id="pet_cat" name="pet_type" value="cat"><label for="pet_cat">🐱 Γάτα</label></div><div class="ptl-quiz-nav" style="justify-content: flex-end;"><button type="button" class="ptl-btn-next" onclick="ptlnNextStep(1)">Επόμενο &rarr;</button></div></div>';
    $html .= '<div class="ptl-step" id="ptl-step-1"><h3>🩺 Θέματα Υγείας</h3><p class="ptl-desc">Το κατοικίδιό σας έχει κάποιο διαγνωσμένο πρόβλημα υγείας;</p><div class="ptl-radio-group"><input type="radio" id="health_no" name="health_issue" value="no" checked><label for="health_no">Όχι, είναι υγιέστατο!</label><input type="radio" id="health_yes" name="health_issue" value="yes"><label for="health_yes">Ναι, υπάρχει διάγνωση</label></div><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlnNextStep(0)">&larr; Πίσω</button><button type="button" class="ptl-btn-next" onclick="ptlnSmartNextStep(2)">Επόμενο &rarr;</button></div></div>';
    $html .= '<div class="ptl-step" id="ptl-step-2"><h3>⚖️ Στοιχεία & Αλλεργίες</h3><label>Ηλικιακό Στάδιο:</label><select name="life_stage"><option value="puppy">Κουτάβι / Γατάκι</option><option value="adult" selected>Ενήλικο</option><option value="senior">Μεγάλης Ηλικίας</option></select><label>Τρέχον Βάρος (κιλά):</label><input type="number" step="0.1" name="current_weight" placeholder="π.χ. 12.5"><label>Επίπεδο Δραστηριότητας:</label><select name="activity_level"><option value="low">Χαμηλή (Ήσυχο)</option><option value="normal" selected>Κανονική</option><option value="high">Υψηλή</option></select><label>Γνωστές Αλλεργίες:</label><select name="allergies[]" multiple style="height: 120px;"><option value="none" selected>Καμία</option><option value="chicken">Κοτόπουλο</option><option value="turkey">Γαλοπούλα</option><option value="poultry">Πουλερικά</option><option value="beef">Μοσχάρι</option><option value="pork">Χοιρινό</option><option value="lamb">Αρνί</option><option value="fish_meat">Ψάρι</option><option value="fish_oil">Ψάρι & Λάδι</option><option value="egg">Αυγό</option><option value="corn">Καλαμπόκι</option><option value="meat">Όλα τα κρέατα</option></select><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlnSmartPrevStep(1)">&larr; Πίσω</button><button type="button" class="ptl-btn-next" onclick="ptlnCheckDataBeforeStep3()">Επόμενο &rarr;</button></div></div>';
    $html .= '<div class="ptl-step" id="ptl-step-3"><h3>📩 Λίγο πριν το αποτέλεσμα!</h3><p class="ptl-desc">Συμπληρώστε τα στοιχεία σας.</p><label>Το Όνομά σας:</label><input type="text" name="user_name" required><label>Το Email σας:</label><input type="email" name="user_email" required><div style="margin-top: 15px; font-size: 13px;"><label><input type="checkbox" name="consent_email" required> Συμφωνώ να λάβω το αποτέλεσμα.</label></div><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlnSmartPrevStep(2)">&larr; Πίσω</button><button type="submit" class="ptl-btn-submit">Δες το αποτέλεσμα!</button></div></div>';
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
    
    // ΔΙΟΡΘΩΣΗ ΚΑΤΑΓΡΑΦΗΣ: Αν έχει πάθηση αποθηκεύεται!
    if ( $health_issue === 'yes' ) {
        global $wpdb; $promo_table = $wpdb->prefix . 'petling_partner_leads'; $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table); $dynamic_code = '';
        if ( $table_exists ) { $recent = $wpdb->get_var( $wpdb->prepare( "SELECT coupon_code FROM $promo_table WHERE email = %s AND partner_prefix = 'VET' AND status = 'active' AND created_at >= %s ORDER BY created_at DESC LIMIT 1", $user_email, date('Y-m-d H:i:s', strtotime('-24 hours', current_time('timestamp'))) ) ); if ( $recent ) { $dynamic_code = $recent; } }
        if ( empty($dynamic_code) ) {
            $dynamic_code = 'VET-' . strtoupper(substr(md5(uniqid()), 0, 6));
            if ( $table_exists ) { $wpdb->insert( $promo_table, array( 'email' => $user_email, 'partner_prefix' => 'VET', 'type' => 'appointment', 'coupon_code' => $dynamic_code, 'status' => 'active', 'created_at' => current_time('mysql') ) ); }
        }
        
        // ΣΩΖΟΥΜΕ ΣΤΟ CRM 
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
// 6. RECIPE MAKER (ΔΥΝΑΜΙΚΑ ΥΛΙΚΑ)
// =========================================================================
function ptl_get_dynamic_recipe_options() {
    return array(
        'proteins_1' => array( 'Κοτόπουλο', 'Γαλοπούλα', 'Κουνέλι', 'Μοσχάρι', 'Αρνί', 'Χοιρινό', 'Πάπια', 'Πέρκα', 'Τσιπούρα', 'Λαβράκι', 'Μπακαλιάρος', 'Σολομός', 'Σαρδέλα' ),
        'proteins_2' => array( 'Αυγό (βραστό)', 'Κεφίρ', 'Γιαούρτι', 'Τυρί cottage', 'Ανθότυρο' ),
        'carbs_1'    => array( 'Ρύζι (Λευκό ή Ολικής)', 'Κινόα', 'Κοφτό μακαρονάκι', 'Πλιγούρι', 'Κριθαράκι' ),
        'carbs_2'    => array( 'Πατάτα', 'Γλυκοπατάτα', 'Κολοκύθα' ),
        'veggies'    => array( 'Καρότο', 'Μπρόκολο', 'Κολοκυθάκι', 'Σπανάκι', 'Λαχανάκι Βρ.', 'Παντζάρι', 'Φασολάκια', 'Αρακάς' ),
        'fruits'     => array( 'Μήλο', 'Μπανάνα', 'Καρπούζι', 'Αγγούρι', 'Πεπόνι', 'Φράουλα', 'Μύρτιλλα', 'Ροδάκινο', 'Αχλάδι' ),
        'oils'       => array( 'Εξαιρετικά παρθένο ελαιόλαδο', 'Λινέλαιο', 'Κολοκυθέλαιο' ),
        'conditions' => array( 'Γαστρεντερικά / ΙΦΝΕ', 'Νεφρική Ανεπάρκεια', 'Ηπατοπάθεια', 'Ουροποιητικό (Κρύσταλλοι/Πέτρες)', 'Παχυσαρκία', 'Σακχαρώδης Διαβήτης', 'Αλλεργία / Δερματικά', 'Λεϊσμανίαση (Καλαζάρ)', 'Άλλο' )
    );
}

add_shortcode('petling_recipe_maker', 'ptl_recipe_maker_shortcode');
function ptl_recipe_maker_shortcode() {
    $r_data = ptl_get_dynamic_recipe_options();

    $html = '<style>.ptl-quiz-container { max-width: 650px; margin: 40px auto; background: #fffaf1; padding: 40px; border-radius: 12px; border: 2px dashed #C7B297; font-family: sans-serif; box-shadow: 0 5px 20px rgba(0,0,0,0.05); } .ptl-step { display: none; animation: fadeIn 0.4s; } .ptl-step.active { display: block; } .ptl-quiz-container h3 { color: #43282F; margin-top: 0; font-size: 24px; text-align: center; margin-bottom:10px; } .ptl-quiz-container p.ptl-desc { color: #555; font-size: 15px; margin-bottom: 25px; text-align: center; line-height: 1.5; } .ptl-quiz-container label.ptl-section-label { display: block; font-weight: bold; color: #43282F; margin-bottom: 8px; margin-top: 20px; font-size: 16px; border-bottom: 1px solid #e0d5c1; padding-bottom: 5px; } .ptl-quiz-container select, .ptl-quiz-container input[type="text"], .ptl-quiz-container input[type="email"], .ptl-quiz-container textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 15px; margin-bottom: 10px; background: #fff; } .ptl-radio-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; } .ptl-radio-group label { flex: 1 1 calc(50% - 10px); text-align: center; background: #fff; border: 2px solid #e0d5c1; padding: 15px; border-radius: 8px; cursor: pointer; } .ptl-radio-group input[type="radio"] { display: none; } .ptl-radio-group input[type="radio"]:checked + label { border-color: #C7B297; background: #F5EDE3; color: #43282F; font-weight: bold; } .ptl-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px; } .ptl-checkbox-group label { flex: 1 1 calc(33% - 8px); text-align: center; background: #fff; border: 2px solid #e0d5c1; padding: 10px 5px; border-radius: 6px; cursor: pointer; font-size: 13px; margin:0; } .ptl-checkbox-group input[type="checkbox"] { display: none; } .ptl-checkbox-group input[type="checkbox"]:checked + label { border-color: #C7B297; background: #F5EDE3; font-weight: bold; } .ptl-quiz-nav { display: flex; justify-content: space-between; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 20px; } .ptl-btn-prev { background: #e0d5c1; color: #43282F; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; } .ptl-btn-next, .ptl-btn-submit { background: #43282F; color: #fff; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; } .ptl-btn-next:hover, .ptl-btn-submit:hover { background: #C7B297; } .ptl-loader { text-align:center; padding:20px; display:none; } .ptl-result-box { background:#fff; padding:25px; border-radius:8px; border:2px solid #C7B297; margin-top:20px; }</style>';
    
    $html .= '<div class="ptl-quiz-container" id="ptl-recipe-app"><form id="ptl-recipe-form">' . wp_nonce_field('ptl_recipe_nonce', 'security', true, false);
    
    // ΒΗΜΑ 1
    $html .= '<div class="ptl-step active" id="rec-step-1"><h3>🩺 Θέματα Υγείας</h3><div class="ptl-radio-group"><input type="radio" id="rec_health_no" name="health_issue" value="no" checked><label for="rec_health_no">Όχι, είναι υγιέστατο!</label><input type="radio" id="rec_health_yes" name="health_issue" value="yes"><label for="rec_health_yes">Ναι, υπάρχει διάγνωση</label></div><p id="healthy-dog-text" style="color: #666; font-size: 14px; margin-top: 15px; text-align: center;">Εάν το σκυλάκι σας είναι υγιέστατο, επιλέξτε "Όχι" για να προχωρήσετε στη δημιουργία μιας υπέροχης, σπιτικής συνταγής.</p><div id="ptl-health-details" style="display:none; text-align:left; background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; margin-bottom:20px;"><label class="ptl-section-label" style="border:none; margin-top:0;">Επιλέξτε την Πάθηση:</label><select name="health_condition" id="health_condition_select"><option value="" disabled selected>Επιλέξτε...</option>';
    foreach($r_data['conditions'] as $cond) { $html .= '<option value="'.esc_attr($cond).'">'.esc_html($cond).'</option>'; }
    $html .= '</select><label class="ptl-section-label" style="border:none;">Σημειώσεις (Προαιρετικό):</label><textarea name="health_notes" rows="3"></textarea></div><div class="ptl-quiz-nav" style="justify-content: flex-end;"><button type="button" class="ptl-btn-next" onclick="ptlrSmartNextStep(2)">Επόμενο &rarr;</button></div></div>';
    
    // ΒΗΜΑ 2
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
    
    // ΒΗΜΑ 3 & 4
    $html .= '<div class="ptl-step" id="rec-step-3"><h3>📩 Στοιχεία Επικοινωνίας</h3><label>Όνομα:</label><input type="text" name="user_name" required><label>Email:</label><input type="email" name="user_email" required><div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlrSmartPrevStep(2)">&larr; Πίσω</button><button type="submit" class="ptl-btn-submit">Ολοκλήρωση!</button></div></div>';
    $html .= '<div class="ptl-step" id="rec-step-result"><div id="ptl-recipe-content"></div><div style="text-align:center; margin-top:30px;"><button type="button" class="ptl-btn-prev" onclick="location.reload();">Επανεκκίνηση</button></div></div><div class="ptl-loader" id="ptl-recipe-loader">⏳ Γίνεται υπολογισμός...</div></form></div>';
    
    $ajax_url = admin_url( 'admin-ajax.php' );
    $html .= "<script>
        jQuery('#ptl-recipe-app input[name=\"health_issue\"]').on('change', function(){ if(jQuery(this).val() === 'yes') { jQuery('#ptl-health-details').slideDown(); jQuery('#healthy-dog-text').slideUp(); } else { jQuery('#ptl-health-details').slideUp(); jQuery('#healthy-dog-text').slideDown(); } });
        function ptlrNextStep(step) { jQuery('#ptl-recipe-app .ptl-step').removeClass('active'); jQuery('#ptl-recipe-app #rec-step-' + step).addClass('active'); }
        function ptlrSmartNextStep(step) { var health = jQuery('#ptl-recipe-app input[name=\"health_issue\"]:checked').val(); var cond = jQuery('#health_condition_select').val(); if (health === 'yes') { if(!cond) { alert('Επιλέξτε πάθηση.'); return; } step = 3; } ptlrNextStep(step); }
        function ptlrSmartPrevStep(step) { var health = jQuery('#ptl-recipe-app input[name=\"health_issue\"]:checked').val(); if (step === 2 && health === 'yes') { step = 1; } ptlrNextStep(step); }
        jQuery('#ptl-veg-group input[type=\"checkbox\"]').on('change', function() { if(jQuery('#ptl-veg-group input[type=\"checkbox\"]:checked').length > 2) { this.checked = false; } });
        function ptlrValidateStep2() { var valid = true; jQuery('#ptl-recipe-app #rec-step-2 select').each(function() { if(!jQuery(this).val()) valid = false; }); var vegCount = jQuery('#ptl-veg-group input[type=\"checkbox\"]:checked').length; if (vegCount !== 2) { jQuery('#veg-error').show(); valid = false; } else { jQuery('#veg-error').hide(); } if(!valid) { alert('Συμπληρώστε όλα τα πεδία.'); return; } ptlrNextStep(3); }
        jQuery('#ptl-recipe-form').on('submit', function(e){
            e.preventDefault(); var formData = jQuery(this).serialize(); jQuery('#ptl-recipe-app .ptl-step').removeClass('active'); jQuery('#ptl-recipe-loader').show();
            jQuery.post('{$ajax_url}', formData + '&action=ptl_process_recipe', function(response){
                jQuery('#ptl-recipe-loader').hide(); jQuery('#ptl-recipe-app #rec-step-result').addClass('active');
                if(response.success) { jQuery('#ptl-recipe-content').html(response.data); } else { jQuery('#ptl-recipe-content').html('<p style=\"color:red;\">' + response.data + '</p>'); }
            }).fail(function() { jQuery('#ptl-recipe-loader').hide(); jQuery('#ptl-recipe-app #rec-step-result').addClass('active'); jQuery('#ptl-recipe-content').html('<p style=\"color:red;\">Σφάλμα.</p>'); });
        });
    </script>";
    return $html;
}

add_action('wp_ajax_ptl_process_recipe', 'ptl_process_recipe_ajax');
add_action('wp_ajax_nopriv_ptl_process_recipe', 'ptl_process_recipe_ajax');
function ptl_process_recipe_ajax() {
    check_ajax_referer('ptl_recipe_nonce', 'security');
    $health_issue = sanitize_text_field($_POST['health_issue'] ?? 'no'); 
    $health_condition = sanitize_text_field($_POST['health_condition'] ?? ''); 
    $health_notes = sanitize_textarea_field($_POST['health_notes'] ?? ''); 
    $user_name = sanitize_text_field($_POST['user_name'] ?? ''); 
    $user_email = sanitize_email($_POST['user_email'] ?? '');
    
    global $wpdb; 
    $promo_table = $wpdb->prefix . 'petling_partner_leads'; 
    $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table); 
    $dynamic_code = 'VET-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    if ( $table_exists ) { 
        $wpdb->insert( $promo_table, array( 'email' => $user_email, 'partner_prefix' => 'VET', 'type' => 'appointment', 'coupon_code' => $dynamic_code, 'status' => 'active', 'created_at' => current_time('mysql') ) ); 
    }
    
    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Petling <info@petling.gr>');
    
    if ( $health_issue === 'yes' ) {
        $pid = ptl_save_quiz_lead_data($user_email, $user_name, 'HOME_COOKED_RECIPE', 'VET (Πάθηση: ' . $health_condition . ')');
        if ($pid) { 
            update_post_meta($pid, 'ptl_health_condition', $health_condition); 
            update_post_meta($pid, 'ptl_health_notes', $health_notes); 
        }
        
        $html = '<h3>🩺 Απαιτείται Κλινική Δίαιτα</h3><div class="ptl-result-box"><h4 style="color: #43282F;">Κλείστε Ραντεβού με τη Δρ. Μανωλάκου</h4><p>Ο κωδικός σας για <strong>10% έκπτωση</strong> είναι:</p><div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; text-align:center;">' . $dynamic_code . '</div></div>';
        if ( is_email( $user_email ) ) { wp_mail( $user_email, 'Κλινική Δίαιτα', $html, $headers ); }
        wp_send_json_success($html); 
        exit;
    }
    
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

    // ΑΛΛΑΓΗ: Προσθήκη όλων των υλικών με τα γραμμάριά τους σε μία inline σειρά
    $recipe_summary_text = "{$g_p1}γρ {$p1} / {$g_p2}γρ {$p2} / {$g_c1}γρ {$c1} / {$g_c2}γρ {$c2} / {$g_veg}γρ {$veg_str} / {$g_fruit}γρ {$fruit} / {$g_oil}γρ {$oil}";
    
    ptl_save_quiz_lead_data($user_email, $user_name, 'HOME_COOKED_RECIPE', $recipe_summary_text);
    
    $html = '<h3>👨‍🍳 Η Συνταγή σου είναι έτοιμη!</h3><div class="ptl-result-box"><p>Συνταγή για 10kg σκύλο (Σύνολο 300γρ):</p><ul><li>🥩 150γρ ' . $p1 . '</li><li>🥚 15γρ ' . $p2 . '</li><li>🌾 45γρ ' . $c1 . '</li><li>🥔 30γρ ' . $c2 . '</li><li>🥦 39γρ ' . $veg_str . '</li><li>🍎 15γρ ' . $fruit . '</li><li>🫒 6γρ ' . $oil . '</li></ul><div style="margin-top:20px; background:#F5EDE3; padding:15px; text-align:center;">Κωδικός 10% VET: <strong>' . $dynamic_code . '</strong></div></div>';
    if ( is_email( $user_email ) ) { wp_mail( $user_email, 'Η Συνταγή σας', $html, $headers ); }
    wp_send_json_success($html);
}

// =========================================================================
// 7. SHORTCODES FRONTEND (ΚΟΥΠΟΝΙΑ ΚΑΙ ΕΞΑΡΓΥΡΩΣΗ)
// =========================================================================
function petling_promo_enqueue_assets_safely() {
    wp_enqueue_script('jquery');
}

add_shortcode( 'petling_partner_promo', 'petling_partner_promo_shortcode' );
function petling_partner_promo_shortcode( $atts ) {
    petling_promo_enqueue_assets_safely(); 
    $a = shortcode_atts( array( 'prefix' => 'PTL' ), $atts ); 
    $prefix = sanitize_text_field( strtoupper( $a['prefix'] ) ); 
    $partner = petling_promo_get_partner( $prefix );
    
    if ( ! $partner ) return '<p style="color:#e62121;">Άγνωστος συνεργάτης.</p>';
    $amount_text = petling_promo_format_amount( $partner ); 
    $partner_name = esc_html( $partner['label'] );
    ob_start();
    ?>
    <div class="ptl-promo-container" style="max-width: 500px; margin: 20px auto; background: #fffaf1; padding: 30px; border-radius: 12px; border: 2px dashed #C7B297; text-align:center;">
        <h3>🎁 Πάρε την Έκπτωσή σου!</h3>
        <p>Βάλε το email σου για να λάβεις τον κωδικό <strong><?php echo esc_html( $amount_text ); ?></strong> από <?php echo $partner_name; ?>!</p>
        <form class="ptl-promo-form" onsubmit="event.preventDefault(); var btn = jQuery(this).find('button'); btn.text('⏳...'); jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', jQuery(this).serialize() + '&action=petling_process_promo', function(res){ if(res.success) jQuery('.ptl-promo-container').html(res.data); else { alert(jQuery(res.data).text()); btn.text('Λήψη Κωδικού'); } });">
            <?php wp_nonce_field( 'petling_promo_nonce', 'security', true, true ); ?>
            <input type="hidden" name="promo_prefix" value="<?php echo esc_attr( $prefix ); ?>">
            <input type="email" name="promo_email" placeholder="Το email σου εδώ..." required style="width:100%; padding:12px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
            <button type="submit" style="background:#43282F; color:#fff; padding:12px 25px; border:none; border-radius:6px; cursor:pointer;">Λήψη Κωδικού</button>
        </form>
    </div>
    <?php return ob_get_clean();
}

add_shortcode( 'petling_partner_redeem', 'petling_partner_redeem_shortcode' );
function petling_partner_redeem_shortcode( $atts ) {
    petling_promo_enqueue_assets_safely(); 
    $a = shortcode_atts( array( 'prefix' => '' ), $atts ); 
    $prefix = sanitize_text_field( strtoupper( $a['prefix'] ) ); 
    $partner = petling_promo_get_partner( $prefix );
    
    if ( ! $partner || 'appointment' !== $partner['type'] ) return '<p style="color:#e62121;">Αυτή η σελίδα δεν είναι διαθέσιμη.</p>';
    ob_start();
    ?>
    <div class="ptl-redeem-container" style="max-width: 500px; margin: 20px auto; background: #fffaf1; padding: 30px; border-radius: 12px; border: 2px dashed #C7B297; text-align:center;">
        <h3>🔒 Έλεγχος &amp; Εξαργύρωση</h3>
        <form class="ptl-redeem-form" onsubmit="event.preventDefault(); var btn = jQuery(this).find('button'); btn.text('⏳...'); jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', jQuery(this).serialize() + '&action=petling_process_redeem', function(res){ alert(jQuery(res.data).text()); btn.text('Επιβεβαίωση'); if(res.success) location.reload(); });">
            <?php wp_nonce_field( 'petling_redeem_nonce', 'security', true, true ); ?>
            <input type="hidden" name="redeem_prefix" value="<?php echo esc_attr( $prefix ); ?>">
            <input type="password" name="redeem_password" placeholder="Κωδικός πρόσβασης" required style="width:100%; padding:12px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
            <input type="text" name="redeem_lookup" placeholder="Κωδικός ή email" required style="width:100%; padding:12px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
            <button type="submit" style="background:#43282F; color:#fff; padding:12px 25px; border:none; border-radius:6px; cursor:pointer;">Επιβεβαίωση</button>
        </form>
    </div>
    <?php return ob_get_clean();
}

add_action( 'wp_ajax_petling_process_promo', 'petling_process_promo_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_promo', 'petling_process_promo_ajax' );
function petling_process_promo_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_promo_nonce' ) ) wp_send_json_error( 'Σφάλμα ασφαλείας.' );
    global $wpdb; 
    $table = $wpdb->prefix . 'petling_partner_leads'; 
    $email = sanitize_email( $_POST['promo_email'] ); 
    $prefix = sanitize_text_field( strtoupper( $_POST['promo_prefix'] ) );
    
    if ( ! is_email( $email ) ) wp_send_json_error( '⚠️ Μη έγκυρο email.' );
    $partner = petling_promo_get_partner( $prefix ); 
    if ( ! $partner ) wp_send_json_error( 'Άγνωστος συνεργάτης.' );
    
    $last = petling_promo_get_last_claim( $email, $prefix );
    if ( $last ) { 
        $days_passed = ( current_time( 'timestamp' ) - strtotime( $last->created_at ) ) / 86400; 
        if ( $days_passed < $partner['lock_days'] ) { 
            wp_send_json_error( 'Έχεις ήδη λάβει κωδικό!' ); 
        } 
    }
    
    $unique_code = petling_promo_generate_code( $prefix );
    if ( 'shop' === $partner['type'] && class_exists( 'WC_Coupon' ) ) {
        $coupon = new WC_Coupon(); $coupon->set_code( $unique_code ); $coupon->set_discount_type( 'percent' === $partner['discount_type'] ? 'percent' : 'fixed_cart' ); $coupon->set_amount( $partner['amount'] ); if ( ! empty( $partner['min_order'] ) ) $coupon->set_minimum_amount( $partner['min_order'] ); $coupon->set_individual_use( true ); $coupon->set_usage_limit( 1 ); $coupon->set_email_restrictions( array( $email ) ); $coupon->save();
    }
    
    $wpdb->insert( $table, array( 'email' => $email, 'partner_prefix' => $prefix, 'type' => $partner['type'], 'coupon_code' => $unique_code, 'status' => 'active', 'created_at' => current_time( 'mysql' ) ) );
    
    // ΔΙΟΡΘΩΣΗ ΚΑΤΑΓΡΑΦΗΣ: Η φόρμα της JOY καταγράφεται ΣΩΣΤΑ στο Κεντρικό CRM!
    ptl_save_quiz_lead_data($email, '-', 'PROMO_' . $prefix, $unique_code);
    
    $amount_text = petling_promo_format_amount( $partner ); $partner_name = esc_html( $partner['label'] );
    if ( 'appointment' === $partner['type'] ) { $subject = 'Ο κωδικός σου για ραντεβού 🐾'; $email_body = "<p>Γεια σου! 🐾 Κέρδισες έκπτωση <strong>{$amount_text}</strong> στο ραντεβού σου με {$partner_name}.</p><p><strong>Ο Κωδικός σου:</strong> <span style='font-size:20px;'>{$unique_code}</span></p>"; } 
    else { $subject = 'Το δωράκι σου σε περιμένει! 🎁'; $email_body = "<p>Γεια σου! 🐾 Κέρδισες έκπτωση <strong>{$amount_text}</strong> για τις αγορές σου στο petling.gr.</p><p><strong>Ο Κωδικός σου:</strong> <span style='font-size:20px;'>{$unique_code}</span></p>"; }
    wp_mail( $email, $subject, $email_body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    
    wp_send_json_success( '<h3 style="margin-top:0; color:#5b9a68;">🎉 Ο κωδικός σου είναι έτοιμος!</h3><div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; margin:20px 0;">' . esc_html( $unique_code ) . '</div><p>Μπορείς να τον χρησιμοποιήσεις αμέσως.</p>' );
}

add_action( 'wp_ajax_petling_process_redeem', 'petling_process_redeem_ajax' );
add_action( 'wp_ajax_nopriv_petling_process_redeem', 'petling_process_redeem_ajax' );
function petling_process_redeem_ajax() {
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'petling_redeem_nonce' ) ) wp_send_json_error( 'Σφάλμα ασφαλείας.' );
    global $wpdb; $table = $wpdb->prefix . 'petling_partner_leads'; $prefix = sanitize_text_field( strtoupper( $_POST['redeem_prefix'] ) ); $password = (string) $_POST['redeem_password']; $lookup = sanitize_text_field( trim( $_POST['redeem_lookup'] ) );
    $partner = petling_promo_get_partner( $prefix ); if ( ! $partner || 'appointment' !== $partner['type'] ) wp_send_json_error( 'Μη διαθέσιμο.' );
    if ( empty( $partner['password'] ) || ! hash_equals( (string) $partner['password'], $password ) ) wp_send_json_error( 'Λάθος κωδικός πρόσβασης.' );
    if ( is_email( $lookup ) ) { $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1", sanitize_email( $lookup ), $prefix ) ); } else { $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE coupon_code = %s AND partner_prefix = %s LIMIT 1", strtoupper( $lookup ), $prefix ) ); }
    if ( ! $row ) wp_send_json_error( 'Δεν βρέθηκε κωδικός.' );
    if ( 'redeemed' === $row->status ) wp_send_json_error( '⚠️ Αυτός ο κωδικός έχει ήδη χρησιμοποιηθεί.' );
    $wpdb->update( $table, array( 'status' => 'redeemed', 'redeemed_at' => current_time( 'mysql' ) ), array( 'id' => $row->id ) );
    wp_send_json_success( '✅ Ο κωδικός επιβεβαιώθηκε επιτυχώς!' );
}