<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. ΕΠΙΒΟΛΗ GLOBAL & MOBILE CSS ΜΕ ΜΕΓΙΣΤΗ ΠΡΟΤΕΡΑΙΟΤΗΤΑ (ΣΤΟ <HEAD>)
// =========================================================================
add_action( 'wp_head', 'petling_crm_force_mobile_css', 999 );
function petling_crm_force_mobile_css() {
    if ( ! is_account_page() ) return;
    ?>
    <style>
        /* Bασικά Στοιχεία Φόρμας */
        .petling-crm-form { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; max-width: 100%; overflow-x: hidden; }
        .petling-crm-form * { box-sizing: border-box !important; }
        .petling-crm-form label { font-size: 13px; font-weight: bold; color: #555; display: block; margin-bottom: 5px; }
        
        .petling-crm-form input[type="text"], 
        .petling-crm-form input[type="date"], 
        .petling-crm-form input[type="number"], 
        .petling-crm-form select, 
        .petling-crm-form textarea { 
            -webkit-appearance: none !important; appearance: none !important;
            width: 100% !important; max-width: 100% !important;
            padding: 10px !important; border: 1px solid #ccc !important; 
            border-radius: 6px !important; font-size: 15px !important; height: 42px !important; background-color: #fff !important; 
        }
        
        /* Error States for Validation */
        .petling-crm-form input:invalid, .petling-crm-form select:invalid { border-color: #e62121 !important; box-shadow: 0 0 3px rgba(230,33,33,0.3); }
        .petling-crm-form input:focus:invalid { outline: none; }
        
        .petling-crm-form textarea { height: auto !important; min-height: 80px !important; }
        
        /* Grids & Blocks */
        .petling-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px; width: 100%; max-width: 100%; }
        .pet-block { border: 2px solid #C7B297 !important; padding: 20px !important; margin-bottom: 30px !important; border-radius: 10px !important; background: #fffaf1 !important; display: none; width: 100%; max-width: 100%; box-sizing: border-box !important; }
        .pet-block-header { border-bottom: 1px solid #ccc; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .pet-block-title { font-size: 1.2em; color: #43282F; font-weight: bold; margin: 0; }
        
        /* ΣΥΣΤΗΜΑ TABS ΓΙΑ ΤΑ ΚΑΤΟΙΚΙΔΙΑ */
        .pet-tabs-nav { display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: 8px !important; margin: 20px 0 !important; padding-bottom: 8px !important; border-bottom: 2px solid #C7B297 !important; -webkit-overflow-scrolling: touch; scrollbar-width: none; max-width: 100%; box-sizing: border-box !important; }
        .pet-tabs-nav::-webkit-scrollbar { display: none; }
        .pet-tab-btn { padding: 10px 18px !important; background: #fff !important; border: 2px solid #C7B297 !important; border-radius: 12px 12px 0 0 !important; color: #43282F !important; font-weight: bold !important; cursor: pointer !important; white-space: nowrap !important; font-size: 14px !important; transition: all 0.2s !important; border-bottom: none !important; position: relative; top: 2px; flex-shrink: 0; }
        .pet-tab-btn.is-active { background: #43282F !important; color: #fff !important; border-color: #43282F !important; }

        /* Checkboxes (Στοίχιση) */
        .health-issues-container { display: grid; grid-template-columns: 1fr; gap: 12px; max-height: 280px; overflow-y: auto; padding: 15px; background: #fff; border: 1px solid #ccc; border-radius: 6px; }
        .health-issue-item { display: flex !important; align-items: center !important; font-weight: normal; font-size: 14px; margin: 0; cursor: pointer; }
        .health-issue-item input[type="checkbox"] { width: 20px !important; height: 20px !important; margin-right: 12px !important; flex-shrink: 0; }
        .custom-checkbox-wrapper { background: #fbfbfb; padding: 15px; border-radius: 6px; border: 1px solid #e5e5e5; display: flex; align-items: center; margin-bottom: 20px; }
        .custom-checkbox-wrapper label { margin: 0; display: flex; align-items: center; cursor: pointer; width: 100%; font-weight:normal; }
        .custom-checkbox-wrapper input[type="checkbox"] { width: 22px !important; height: 22px !important; margin-right: 12px !important; }

        /* Buttons */
        .btn-petling { padding: 10px 20px !important; border-radius: 6px !important; font-weight: bold !important; cursor: pointer !important; border: none !important; display: inline-flex !important; justify-content: center !important; align-items: center !important; text-decoration: none !important; text-align: center !important; max-width: 100%; }
        .btn-red { background: #e62121 !important; color: white !important; }
        .btn-green { background: #5b9a68 !important; color: white !important; }
        .btn-brown { background: #C7B297 !important; color: #43282F !important; }
        .btn-dark { background: #43282F !important; color: #fff !important; }
        .petling-medical-btn-wrapper { text-align: center !important; width: 100%; }
        
        /* ACCORDIONS ΓΙΑ TO MEDICAL HISTORY */
        details.petling-accordion { background: #fff; border: 1px solid #C7B297 !important; border-radius: 10px; margin-bottom: 20px; overflow: hidden; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.04); max-width: 100%; }
        details.petling-accordion summary { background: #fdfaf5; padding: 18px 20px; font-size: 17px; font-weight: bold; color: #43282F; cursor: pointer; user-select: none; display: flex !important; align-items: center; justify-content: space-between; list-style: none; outline: none; margin: 0; }
        details.petling-accordion summary::-webkit-details-marker { display: none; }
        details.petling-accordion summary::after { content: '▼'; font-size: 14px; color: #C7B297; font-weight: normal; }
        details.petling-accordion[open] summary::after { content: '▲'; }
        details.petling-accordion .accordion-content { padding: 20px; border-top: 1px solid #e2d4c0; }
        
        /* ΙΣΤΟΡΙΚΟ ΒΑΡΟΥΣ */
        .weight-compact-list { list-style: none !important; padding: 0 !important; margin: 0 0 20px 0 !important; max-height: 220px; overflow-y: auto; border: 1px solid #e5e5e5; border-radius: 8px; scrollbar-width: thin; }
        .weight-compact-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #e5e5e5; }
        .weight-compact-item:last-child { border-bottom: none; }
        .weight-compact-item:nth-child(even) { background: #fafafa; }
        .w-date { color: #666; font-size: 14px; flex: 0 0 90px; }
        .w-val { font-weight: bold; color: #43282F; font-size: 16px; flex: 1; text-align: right; padding-right: 15px; }
        .w-del { color: #999; font-size: 24px; text-decoration: none !important; font-weight: bold; line-height: 1; padding: 0 5px; cursor: pointer; transition: color 0.2s; }
        .w-del:hover { color: #e62121; }

        .petling-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 14px; max-width: 100%; }
        .petling-table th { background: #C7B297; color: #43282F; padding: 12px 10px; text-align: left; }
        .petling-table td { padding: 12px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        /* ========================================================
           ΚΙΝΗΤΑ (SMARTPHONES) - ΕΠΙΘΕΤΙΚΟ OVERRIDE
           ======================================================== */
        @media (max-width: 767px) {
            body.woocommerce-account .woocommerce-MyAccount-navigation { width: 100% !important; margin: 0 0 20px 0 !important; padding: 0 !important; float: none !important; background: transparent !important; border: none !important; }
            body.woocommerce-account .woocommerce-MyAccount-navigation ul { display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; gap: 10px !important; padding: 0 0 10px 0 !important; margin: 0 !important; border: none !important; width: 100% !important; box-sizing: border-box !important; }
            body.woocommerce-account .woocommerce-MyAccount-navigation ul::-webkit-scrollbar { height: 4px; }
            body.woocommerce-account .woocommerce-MyAccount-navigation ul::-webkit-scrollbar-thumb { background: #dcdcdc; border-radius: 4px; }
            body.woocommerce-account .woocommerce-MyAccount-navigation ul li { flex: 0 0 auto !important; width: auto !important; border: none !important; padding: 0 !important; margin: 0 !important; }
            body.woocommerce-account .woocommerce-MyAccount-navigation ul li a { display: block !important; padding: 10px 15px !important; background: #fff !important; border: 2px solid #C7B297 !important; border-radius: 25px !important; color: #43282F !important; font-size: 13px !important; font-weight: 600 !important; white-space: nowrap !important; line-height: 1 !important; }
            body.woocommerce-account .woocommerce-MyAccount-navigation ul li.is-active a { background: #43282F !important; color: #fff !important; border-color: #43282F !important; }
            
            .petling-table thead, .petling-table th { display: none !important; }
            .petling-table, .petling-table tbody, .petling-table tr, .petling-table td { display: block !important; width: 100% !important; box-sizing: border-box !important; }
            .petling-table tr { margin-bottom: 20px !important; border: 2px solid #e5e5e5 !important; border-radius: 10px !important; padding: 15px !important; background: #fafafa !important; }
            .petling-table td { text-align: right !important; padding: 10px 0 !important; border-bottom: 1px dashed #e5e5e5 !important; position: relative !important; padding-left: 50% !important; min-height: 40px !important; }
            .petling-table td:last-child { border-bottom: none !important; }
            .petling-table td::before { content: attr(data-label); position: absolute !important; left: 0 !important; width: 45% !important; text-align: left !important; font-weight: bold !important; color: #666 !important; }
            .petling-table td[data-label="Ενέργεια"] { text-align: right !important; padding-top: 15px !important; }
            
            .weight-form { flex-wrap: wrap !important; }
            .weight-form > div { width: 100% !important; }
            .weight-form button { width: 100% !important; max-width: 100% !important; margin-top: 10px !important; }
            details.petling-accordion summary { font-size: 16px; padding: 15px; }
            
            .qr-flex-container { display: flex; flex-direction: row !important; flex-wrap: nowrap !important; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box; }
            .qr-flex-container > div:first-child { flex: 1; min-width: 0; padding-right: 10px; }
        }
        
        /* TABLETS & DESKTOPS */
        @media (min-width: 768px) {
            .petling-grid { grid-template-columns: 1fr 1fr; gap: 20px; }
            .health-issues-container { grid-template-columns: 1fr 1fr; }
            .pet-block { padding: 30px !important; }
            .petling-medical-btn-wrapper { text-align: right !important; }
            .weight-form { flex-wrap: nowrap !important; }
        }
    </style>
    <?php
}

// 2. ΜΗΧΑΝΙΣΜΟΣ ΔΗΜΙΟΥΡΓΙΑΣ .ICS 
add_action( 'template_redirect', 'petling_crm_generate_ics' );
function petling_crm_generate_ics() {
    if ( isset( $_GET['petling_ics'] ) && $_GET['petling_ics'] == '1' ) {
        $title = isset($_GET['title']) ? sanitize_text_field(stripslashes(urldecode($_GET['title']))) : 'Ραντεβού Petling';
        $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $desc = isset($_GET['desc']) ? sanitize_text_field(stripslashes(urldecode($_GET['desc']))) : '';
        if ( empty($date) ) return;
        $start = date('Ymd', strtotime($date));
        $end = date('Ymd', strtotime($date . ' +1 day'));
        $stamp = date('Ymd\THis\Z');
        $ics_content = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Petling CRM//NONSGML v1.0//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:" . uniqid() . "@petling.gr\r\nDTSTAMP:" . $stamp . "\r\nDTSTART;VALUE=DATE:" . $start . "\r\nDTEND;VALUE=DATE:" . $end . "\r\nSUMMARY:" . $title . "\r\nDESCRIPTION:" . $desc . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="petling-reminder.ics"');
        echo $ics_content;
        exit;
    }
}
function petling_crm_get_ics_link( $title, $date, $details = '' ) {
    if ( empty( $date ) || $date === '0000-00-00' || strtotime($date) <= 0 ) return '';
    return add_query_arg( [ 'petling_ics' => '1', 'title' => urlencode($title), 'date' => $date, 'desc' => urlencode($details) ], site_url() );
}

add_action( 'template_redirect', 'petling_crm_save_pets' );
function petling_crm_save_pets() {
    if ( isset( $_POST['petling_crm_nonce'] ) && wp_verify_nonce( $_POST['petling_crm_nonce'], 'petling_save_pets' ) ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        if ( isset($_POST['petling_test_email_trigger']) && $_POST['petling_test_email_trigger'] == '1' && current_user_can( 'manage_options' ) ) {
            $user_info = get_userdata($user_id);
            $pets = get_user_meta( $user_id, 'petling_pets', true );
            $pet_names = [];
            if ( is_array( $pets ) ) { foreach ( $pets as $p ) { if ( !empty($p['name']) ) $pet_names[] = esc_html($p['name']); } }
            if ( function_exists('petling_send_smart_reminder_email') ) {
                petling_send_smart_reminder_email( $user_info->user_email, $pet_names, '30' ); 
                wc_add_notice( '✅ Επιτυχία! Το δοκιμαστικό email μόλις στάλθηκε.', 'success' );
            }
            wp_safe_redirect( wc_get_endpoint_url( 'pet-crm' ) ); exit;
        }
        
        update_user_meta( $user_id, 'petling_order_reminder_interval', sanitize_text_field( $_POST['petling_order_reminder_interval'] ?? 'no' ) );
        $pets_data = [];
        if ( ! empty( $_POST['pet_name'] ) && is_array( $_POST['pet_name'] ) ) {
            foreach ( $_POST['pet_name'] as $index => $name ) {
                if ( empty( trim( $name ) ) ) continue;
                $pet_id = !empty( $_POST['pet_id'][$index] ) ? sanitize_text_field( $_POST['pet_id'][$index] ) : uniqid('pet_');
                $pets_data[] = [
                    'id'           => $pet_id,
                    'name'         => sanitize_text_field( $name ),
                    'type'         => sanitize_text_field( $_POST['pet_type'][ $index ] ?? '' ),
                    'birthday'     => sanitize_text_field( $_POST['pet_birthday'][ $index ] ?? '' ),
                    'energy'       => sanitize_text_field( $_POST['pet_energy'][ $index ] ?? '' ),
                    'breed'        => sanitize_text_field( $_POST['pet_breed'][ $index ] ?? '' ), // Διατηρούμε τυχόν παλιά δεδομένα
                    'weight'       => sanitize_text_field( $_POST['pet_weight'][ $index ] ?? '' ),
                    'microchip'    => sanitize_text_field( $_POST['pet_microchip'][$index] ?? '' ),
                    'daily_food'   => sanitize_text_field( $_POST['pet_daily_food'][ $index ] ?? '' ),
                    'neutered'     => sanitize_text_field( $_POST['pet_neutered'][ $index ] ?? 'no' ), 
                    'health'       => isset( $_POST['pet_health'][ $index ] ) ? array_map( 'sanitize_text_field', (array) $_POST['pet_health'][ $index ] ) : [],
                    'health_notes' => sanitize_textarea_field( $_POST['pet_health_notes'][ $index ] ?? '' ),
                ];
            }
        }
        update_user_meta( $user_id, 'petling_pets', $pets_data );
        wp_safe_redirect( wc_get_endpoint_url( 'pet-crm' ) ); exit;
    }
}

add_action( 'template_redirect', 'petling_crm_handle_vaccines' );
function petling_crm_handle_vaccines() {
    global $wpdb;
    if ( isset( $_POST['petling_vaccine_nonce'] ) && wp_verify_nonce( $_POST['petling_vaccine_nonce'], 'petling_add_vaccine' ) ) {
        $wpdb->insert( $wpdb->prefix . 'petling_vaccines', array( 'pet_unique_id' => sanitize_text_field($_POST['pet_unique_id']), 'vaccine_name' => sanitize_text_field($_POST['vaccine_name']), 'date_administered' => sanitize_text_field($_POST['date_administered']), 'next_vaccine_date' => sanitize_text_field($_POST['next_vaccine_date']), 'vet_name' => sanitize_text_field($_POST['vet_name']), 'status' => 'draft', 'created_by' => 'owner' ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => sanitize_text_field($_POST['pet_unique_id']) ), wc_get_endpoint_url( 'pet-crm' ) ) ); exit;
    }
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_vac' && isset( $_GET['vac_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_vac_' . $_GET['vac_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ), 'status' => 'draft' ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'vac_id', '_wpnonce' ) ) ); exit;
    }
}

add_action( 'template_redirect', 'petling_crm_handle_vet_notes' );
function petling_crm_handle_vet_notes() {
    global $wpdb;
    if ( isset( $_POST['petling_vet_note_nonce'] ) && wp_verify_nonce( $_POST['petling_vet_note_nonce'], 'petling_add_vet_note' ) ) {
        $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => sanitize_text_field($_POST['pet_unique_id']), 'vet_comment' => sanitize_textarea_field($_POST['vet_comment']), 'next_exam_date'=> sanitize_text_field($_POST['next_exam_date']), 'vet_name' => sanitize_text_field($_POST['vet_name']), 'status' => 'draft', 'created_by' => 'owner' ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => sanitize_text_field($_POST['pet_unique_id']) ), wc_get_endpoint_url( 'pet-crm' ) ) ); exit;
    }
    if ( isset( $_POST['petling_weight_nonce'] ) && wp_verify_nonce( $_POST['petling_weight_nonce'], 'petling_log_weight' ) ) {
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $new_weight = sanitize_text_field($_POST['weight']);
        $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'weight' => $new_weight, 'vet_comment' => 'Τακτική Ζύγιση (Κηδεμόνας)', 'status' => 'verified', 'created_by' => 'owner' ), array( '%s', '%f', '%s', '%s', '%s' ) );
        $user_id = get_current_user_id();
        $pets_array = get_user_meta($user_id, 'petling_pets', true);
        if (is_array($pets_array)) {
            foreach ($pets_array as &$p) { if ($p['id'] === $pet_id) { $p['weight'] = $new_weight; break; } }
            update_user_meta($user_id, 'petling_pets', $pets_array);
        }
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'pet-crm' ) ) ); exit;
    }
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_note' && isset( $_GET['note_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_note_' . $_GET['note_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ), 'status' => 'draft' ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'note_id', '_wpnonce' ) ) ); exit;
    }
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_weight' && isset( $_GET['weight_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_weight_' . $_GET['weight_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['weight_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'weight_id', '_wpnonce' ) ) ); exit;
    }
}

add_action( 'woocommerce_account_pet-crm_endpoint', 'petling_crm_endpoint_content' );
function petling_crm_endpoint_content() {
    $user_id = get_current_user_id();
    $pets = get_user_meta( $user_id, 'petling_pets', true );
    if ( ! is_array( $pets ) ) { $pets = []; }

    if ( isset( $_GET['view'] ) && $_GET['view'] === 'medical' && ! empty( $_GET['pet_id'] ) ) {
        $target_pet_id = sanitize_text_field( $_GET['pet_id'] );
        foreach ( $pets as $pet ) {
            if ( isset($pet['id']) && $pet['id'] === $target_pet_id ) { petling_crm_render_medical_history( $pet ); return; }
        }
    }

    $reminder_interval = get_user_meta( $user_id, 'petling_order_reminder_interval', true ) ?: 'no';
    $today = date('Y-m-d');
    $min_date = date('Y-m-d', strtotime('-30 years'));
    
    $energy_levels = [ 'low' => 'Χαμηλή', 'medium' => 'Μέτρια', 'high' => 'Υψηλή' ];
    
    // SMART FORM: Διαχωρισμός παθήσεων με έμφαση στην Ελλάδα
    $health_issues = [ 
        'allergies'        => ['label' => 'Αλλεργίες (Δερματικές/Τροφικές)', 'target' => 'both'],
        'gastrointestinal' => ['label' => 'Γαστρεντερικές Ευαισθησίες', 'target' => 'both'],
        'arthritis'        => ['label' => 'Αρθρίτιδα / Οστεοαρθρίτιδα', 'target' => 'both'],
        'urinary'          => ['label' => 'Ουρολογικά (π.χ. Κρυσταλλουρία, FLUTD)', 'target' => 'both'],
        'kidney'           => ['label' => 'Νεφρική Ανεπάρκεια', 'target' => 'both'],
        'dental'           => ['label' => 'Οδοντικά Προβλήματα / Ουλίτιδα', 'target' => 'both'],
        'heart'            => ['label' => 'Καρδιοπάθειες / Φύσημα', 'target' => 'both'],
        'obesity'          => ['label' => 'Παχυσαρκία', 'target' => 'both'],
        'thyroid_diabetes' => ['label' => 'Θυρεοειδής / Διαβήτης', 'target' => 'both'],
        'leishmaniasis'    => ['label' => 'Λεϊσμανίαση (Καλαζάρ)', 'target' => 'dog'],
        'ehrlichiosis'     => ['label' => 'Ερλιχίωση (από τσιμπούρια)', 'target' => 'dog'],
        'heartworm'        => ['label' => 'Διροφιλαρίωση (Σκουλήκι καρδιάς)', 'target' => 'dog'],
        'dysplasia'        => ['label' => 'Δυσπλασία (Ισχίου/Αγκώνα)', 'target' => 'dog'],
        'ear_infections'   => ['label' => 'Συχνές Ωτίτιδες', 'target' => 'dog'],
        'fiv_felv'         => ['label' => 'FIV / FeLV (AIDS / Λευχαιμία)', 'target' => 'cat'],
        'fip'              => ['label' => 'FIP (Λοιμώδης Περιτονίτιδα)', 'target' => 'cat'],
        'cat_flu'          => ['label' => 'Χρόνια Καταρροή / Ρινοτραχειίτιδα', 'target' => 'cat']
    ];

    ?>
    <form method="post" action="" class="petling-crm-form" id="petling-main-form">
        <?php wp_nonce_field( 'petling_save_pets', 'petling_crm_nonce' ); ?>
        
        <div style="background: #eef7ee; border: 2px solid #5b9a68; padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: center; position: relative;">
            <label style="font-size: 1.1em; font-weight: bold; color: #333; display:block; margin-bottom: 10px; text-align:center;">🔔 Υπενθύμιση Αναπλήρωσης 🐾: Κάντε τις αγορές σας παιχνίδι!</label>
            <p style="font-size: 0.95em; color: #555; margin-bottom: 15px;">Επιλέξτε κάθε πότε θέλετε να σας θυμίζουμε να ανανεώσετε τα αποθέματα σας:</p>
            <select name="petling_order_reminder_interval" id="petling_reminder_dropdown" style="max-width: 300px; margin: 0 auto; display: block;">
                <option value="no" <?php selected($reminder_interval, 'no'); ?>>Όχι, ευχαριστώ</option>
                <option value="15" <?php selected($reminder_interval, '15'); ?>>Κάθε 15 μέρες</option>
                <option value="30" <?php selected($reminder_interval, '30'); ?>>Κάθε 1 Μήνα (30 μέρες)</option>
                <option value="45" <?php selected($reminder_interval, '45'); ?>>Κάθε 1.5 Μήνα (45 μέρες)</option>
                <option value="60" <?php selected($reminder_interval, '60'); ?>>Κάθε 2 Μήνες (60 μέρες)</option>
            </select>
            <div id="reminder-save-warning" style="display:none; color:#d63638; font-size:13px; font-weight:bold; margin-top:10px; animation: fadeIn 0.3s;">⚠️ Μην ξεχάσετε να πατήσετε 'Αποθήκευση' στο κάτω μέρος!</div>
            
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <div style="margin-top: 15px; border-top: 1px dashed #5b9a68; padding-top: 15px;">
                <button type="submit" name="petling_test_email_trigger" value="1" class="btn-petling btn-dark" style="width: 100%; max-width: 300px;" formnovalidate>📧 Δοκιμαστικό Email</button>
            </div>
            <?php endif; ?>
        </div>

        <h2 style="color: #43282F; margin-bottom: 10px;">Τα Κατοικίδιά μου 🐾</h2>
        
        <div class="pet-tabs-nav">
            <?php if ( ! empty( $pets ) ) : foreach ( $pets as $index => $pet ) : 
                $tab_name = !empty($pet['name']) ? esc_html($pet['name']) : 'Ζώο ' . ($index + 1);
                $tab_emoji = ($pet['type'] === 'cat') ? '🐱' : '🐶';
                $active_tab_class = ($index === 0) ? 'is-active' : '';
            ?>
                <button type="button" class="pet-tab-btn <?php echo $active_tab_class; ?>" data-tab="<?php echo $index; ?>">
                    <?php echo $tab_emoji . ' ' . $tab_name; ?>
                </button>
            <?php endforeach; endif; ?>
        </div>
        
        <div id="pet-repeater-container">
            <?php if ( ! empty( $pets ) ) : foreach ( $pets as $index => $pet ) : 
                $p_id      = $pet['id'] ?? '';
                $p_name    = $pet['name'] ?? '';
                $p_type    = $pet['type'] ?? 'dog';
                $p_bday    = $pet['birthday'] ?? '';
                $p_energy  = $pet['energy'] ?? '';
                $p_breed   = $pet['breed'] ?? ''; // Κρυφό πλέον
                $p_weight  = $pet['weight'] ?? '';
                $p_micro   = $pet['microchip'] ?? '';
                $p_dfood   = $pet['daily_food'] ?? '';
                $p_neut    = $pet['neutered'] ?? 'no';
                $p_health  = (isset($pet['health']) && is_array($pet['health'])) ? $pet['health'] : [];
                $p_notes   = $pet['health_notes'] ?? '';
                
                $display_style = ($index === 0) ? 'block' : 'none';
            ?>
                <div class="pet-block" id="pet-block-<?php echo $index; ?>" style="display: <?php echo $display_style; ?>;">
                    <div class="pet-block-header">
                        <h4 class="pet-block-title">Κατοικίδιο: <?php echo esc_html($p_name); ?></h4>
                        <button type="button" class="btn-petling btn-red remove-pet-button">Αφαίρεση</button>
                    </div>
                    
                    <input type="hidden" name="pet_id[]" value="<?php echo esc_attr( $p_id ); ?>">
                    <input type="hidden" name="pet_breed[]" value="<?php echo esc_attr( $p_breed ); ?>">
                    
                    <div class="petling-grid">
                        <div><label>Όνομα *</label><input type="text" name="pet_name[]" class="pet-name-input" value="<?php echo esc_attr( $p_name ); ?>" required pattern="^[a-zA-Zα-ωΑ-ΩΆΈΉΊΌΎΏάέήίόύώϊϋϊϋ\s]+$" title="Παρακαλώ εισάγετε μόνο γράμματα"></div>
                        
                        <div><label>Τύπος *</label><select name="pet_type[]" class="pet-type-select" required><option value="dog" <?php selected( $p_type, 'dog' ); ?>>Σκύλος</option><option value="cat" <?php selected( $p_type, 'cat' ); ?>>Γάτα</option></select></div>
                        
                        <div><label>Ημερομηνία Γέννησης 🎉 *</label><input type="date" name="pet_birthday[]" value="<?php echo esc_attr( $p_bday ); ?>" required max="<?php echo $today; ?>" min="<?php echo $min_date; ?>"></div>
                        
                        <div><label>Επίπεδο Ενέργειας</label><select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $p_energy, $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></div>
                        
                        <div><label>Βάρος (κιλά)</label><input type="number" step="0.01" min="0" name="pet_weight[]" placeholder="π.χ. 25.5" value="<?php echo esc_attr( $p_weight ); ?>"></div>
                        
                        <div><label>Αριθμός Microchip</label><input type="text" name="pet_microchip[]" placeholder="15ψήφιος κωδικός" value="<?php echo esc_attr( $p_micro ); ?>" pattern="^\d{15}$" title="Το Microchip πρέπει να αποτελείται ακριβώς από 15 νούμερα"></div>
                    </div>

                    <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <label style="color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια)</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                            <input type="number" name="pet_daily_food[]" placeholder="π.χ. 250" value="<?php echo esc_attr( $p_dfood ); ?>" style="max-width: 120px; margin: 0 !important;" min="0">
                            <span style="color:#555; font-weight:bold;">γρ.</span>
                        </div>
                    </div>
                    
                    <div class="custom-checkbox-wrapper">
                        <label>
                            <input type="hidden" name="pet_neutered[<?php echo $index; ?>]" value="no">
                            <input type="checkbox" name="pet_neutered[<?php echo $index; ?>]" value="yes" <?php checked( $p_neut, 'yes' ); ?>> 
                            Το κατοικίδιο είναι στειρωμένο
                        </label>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label>Προβλήματα Υγείας</label>
                        <div class="health-issues-container">
                            <?php foreach ($health_issues as $key => $data) : ?>
                                <label class="health-issue-item" data-animal="<?php echo esc_attr($data['target']); ?>"><input type="checkbox" name="pet_health[<?php echo $index; ?>][]" value="<?php echo esc_attr($key); ?>" <?php checked( in_array( $key, $p_health ) ); ?>> <span><?php echo esc_html($data['label']); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label>Σημειώσεις</label>
                        <textarea name="pet_health_notes[]" rows="2"><?php echo esc_textarea( $p_notes ); ?></textarea>
                    </div>
                    
                    <?php if ( !empty($p_id) ) : ?>
                        <div class="petling-medical-btn-wrapper" style="border-top: 1px dashed #C7B297; padding-top: 20px; margin-top: 10px;">
                            <a href="<?php echo esc_url( add_query_arg( array( 'view' => 'medical', 'pet_id' => $p_id ) ) ); ?>" class="btn-petling btn-green" style="width: 100%; max-width: 300px; margin: 0 auto; display: block;">💉 Ιατρικό Ιστορικό & Εμβόλια &rarr;</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div style="text-align: center; margin-bottom: 40px;">
            <button type="button" id="add-pet-button" class="btn-petling btn-brown">＋ Προσθήκη Ζώου</button>
        </div>
        <button type="submit" class="btn-petling btn-brown" style="width: 100%; font-size: 18px; padding: 15px;">Αποθήκευση Αλλαγών</button>
    </form>
    
    <div id="pet-block-template" style="display:none;">
        <input type="hidden" name="pet_id[]" value="">
        <input type="hidden" name="pet_breed[]" value="">
        
        <div class="petling-grid">
            <div><label>Όνομα *</label><input type="text" name="pet_name[]" class="pet-name-input" placeholder="Όνομα ζώου" required pattern="^[a-zA-Zα-ωΑ-ΩΆΈΉΊΌΎΏάέήίόύώϊϋϊϋ\s]+$" title="Παρακαλώ εισάγετε μόνο γράμματα"></div>
            <div><label>Τύπος *</label><select name="pet_type[]" class="pet-type-select" required><option value="dog">Σκύλος</option><option value="cat">Γάτα</option></select></div>
            <div><label>Ημερομηνία Γέννησης 🎉 *</label><input type="date" name="pet_birthday[]" required max="<?php echo $today; ?>"></div>
            <div><label>Επίπεδο Ενέργειας</label><select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></div>
            <div><label>Βάρος (κιλά)</label><input type="number" step="0.01" min="0" name="pet_weight[]"></div>
            <div><label>Αριθμός Microchip</label><input type="text" name="pet_microchip[]" pattern="^\d{15}$" title="Το Microchip πρέπει να αποτελείται ακριβώς από 15 νούμερα"></div>
        </div>
        
        <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 6px; margin-bottom: 20px; margin-top: 15px;">
            <label style="color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια)</label>
            <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                <input type="number" name="pet_daily_food[]" placeholder="π.χ. 250" style="max-width: 120px; margin: 0 !important;" min="0">
                <span style="color:#555; font-weight:bold;">γρ.</span>
            </div>
        </div>

        <div class="custom-checkbox-wrapper">
            <label>
                <input type="hidden" name="pet_neutered[__INDEX__]" value="no">
                <input type="checkbox" name="pet_neutered[__INDEX__]" value="yes"> 
                Το κατοικίδιο είναι στειρωμένο
            </label>
        </div>

        <div style="margin-bottom: 20px;">
            <label>Προβλήματα Υγείας</label>
            <div class="health-issues-container">
                <?php foreach ($health_issues as $key => $data) : ?>
                    <label class="health-issue-item" data-animal="<?php echo esc_attr($data['target']); ?>"><input type="checkbox" name="pet_health[__INDEX__][]" value="<?php echo esc_attr($key); ?>"> <span><?php echo esc_html($data['label']); ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label>Σημειώσεις</label>
            <textarea name="pet_health_notes[]" rows="2"></textarea>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        
        // --- 1. UNSAVED CHANGES WARNING ---
        let formIsDirty = false;
        
        $('#petling-main-form').on('input change', 'input, select, textarea', function() {
            formIsDirty = true;
            if ($(this).attr('name') === 'petling_order_reminder_interval') {
                $('#reminder-save-warning').slideDown(200);
            }
        });

        $(window).on('beforeunload', function() {
            if (formIsDirty) {
                return "Έχετε μη αποθηκευμένες αλλαγές. Είστε σίγουροι ότι θέλετε να φύγετε;";
            }
        });

        $('#petling-main-form').on('submit', function() {
            formIsDirty = false; 
        });

        // --- 2. SMART FORM FILTERING (DOG/CAT) ---
        function filterPetData($block) {
            let type = $block.find('.pet-type-select').val(); // 'dog' or 'cat'

            // Filter Health Issues
            $block.find('.health-issue-item').each(function() {
                let animal = $(this).data('animal');
                if (animal === 'both' || animal === type) {
                    $(this).show();
                } else {
                    $(this).hide();
                    $(this).find('input[type="checkbox"]').prop('checked', false);
                }
            });
        }

        $('.pet-block').each(function() { filterPetData($(this)); });

        $(document).on('change', '.pet-type-select', function() {
            filterPetData($(this).closest('.pet-block'));
        });

        // --- 3. TABS LOGIC ---
        $(document).on('click', '.pet-tab-btn', function() {
            $('.pet-tab-btn').removeClass('is-active');
            $(this).addClass('is-active');
            $('.pet-block').hide();
            $('#pet-block-' + $(this).data('tab')).show();
        });

        $('#add-pet-button').on('click', function(e) {
            e.preventDefault();
            const nextIndex = $('.pet-block').length; 
            let templateHTML = $('#pet-block-template').html();
            templateHTML = templateHTML.replace(/__INDEX__/g, nextIndex);
            
            const $newBlock = $('<div class="pet-block" id="pet-block-' + nextIndex + '"></div>');
            const $header = $('<div class="pet-block-header"><h4 class="pet-block-title">Νέο Ζώο 🐾</h4><button type="button" class="btn-petling btn-red remove-pet-button" formnovalidate>Αφαίρεση</button></div>');
            
            $newBlock.append($header).append(templateHTML);
            $('#pet-repeater-container').append($newBlock);
            
            filterPetData($newBlock);
            
            const $newTab = $('<button type="button" class="pet-tab-btn" data-tab="' + nextIndex + '">🐾 Νέο Ζώο</button>');
            $('.pet-tabs-nav').append($newTab);
            $newTab.click();
            
            $('#pet-block-' + nextIndex + ' .pet-name-input').on('input', function() {
                let name = $(this).val().trim();
                let type = $('#pet-block-' + nextIndex + ' .pet-type-select').val();
                let emoji = (type === 'cat') ? '🐱' : '🐶';
                if(name === '') name = 'Νέο Ζώο';
                $newTab.html(emoji + ' ' + name);
                $('#pet-block-' + nextIndex + ' .pet-block-title').text('Κατοικίδιο: ' + name);
            });
        });

        $(document).on('change', '.pet-type-select', function() {
            const $block = $(this).closest('.pet-block');
            const id = $block.attr('id');
            if (id) {
                const index = id.replace('pet-block-', '');
                const nameInput = $block.find('.pet-name-input').val().trim();
                const name = nameInput === '' ? 'Νέο Ζώο' : nameInput;
                const emoji = ($(this).val() === 'cat') ? '🐱' : '🐶';
                $('.pet-tab-btn[data-tab="' + index + '"]').html(emoji + ' ' + name);
            }
        });

        $(document).on('click', '.remove-pet-button', function() {
            if (confirm('Είστε σίγουροι ότι θέλετε να αφαιρέσετε αυτό το κατοικίδιο;')) {
                const $block = $(this).closest('.pet-block');
                const id = $block.attr('id');
                if (id) {
                    const index = id.replace('pet-block-', '');
                    $('.pet-tab-btn[data-tab="' + index + '"]').remove();
                }
                $block.remove();
                $('.pet-tab-btn').first().click();
                formIsDirty = true; 
            }
        });
    });
    </script>
    <?php
}

function petling_crm_render_medical_history( $pet ) {
    global $wpdb;
    $pet_id = $pet['id'] ?? '';
    
    $current_time = time();
    $vet_token = md5( $pet_id . $current_time . wp_salt() );
    $vet_pass_url = site_url( '/vet-pass/?pet=' . $pet_id . '&t=' . $current_time . '&token=' . $vet_token );
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode( $vet_pass_url );

    $vaccines = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s ORDER BY date_administered DESC", $pet_id ) );
    $vet_notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vet_notes WHERE pet_unique_id = %s ORDER BY created_at DESC", $pet_id ) );

    if ( $pet['type'] === 'dog' ) {
        $vaccine_options = array( 'Παρβοϊός / Μόρβα / Ηπατίτιδα (Core)', 'Λύσσα (Core)', 'Λεπτοσπείρωση (Core)', 'Βήχας Κυνοκομείου (Non-core)', 'Νόσος Lyme (Non-core)', 'Γρίπη Σκύλων (Non-core)', 'Άλλο' );
    } else {
        $vaccine_options = array( 'Τριπλό: FPV/FHV/FCV (Core)', 'Λύσσα (Core)', 'Λευχαιμία - FeLV (Core)', 'Χλαμυδίαση (Non-core)', 'Ανοσοανεπάρκεια - FIV (Non-core)', 'Άλλο' );
    }

    $min_future_date = date('Y-m-d'); 

    echo '<a href="' . esc_url( remove_query_arg( array( 'view', 'pet_id' ) ) ) . '" style="display:inline-block; margin-bottom:20px; font-weight:bold; color:#8B6139; text-decoration:none;">&larr; Πίσω στα Κατοικίδιά μου</a>';
    
    ?>
    <div style="background: #eef7ee; border: 1px solid #5b9a68; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #333; line-height: 1.5;">
        <strong>ℹ️ Πώς λειτουργεί:</strong> Προσθέστε εμβόλια και σημειώσεις ως <em>προσχέδιο</em>. Ανοίξτε το Vet Pass (QR Code) στον κτηνίατρο. Ο ιατρός θα μπορεί να τα διορθώσει, να τα επιβεβαιώσει και να τα κλειδώσει μόνιμα στο ιστορικό από το κινητό του.
    </div>

    <details style="background: linear-gradient(135deg, #43282F 0%, #2a191d 100%); border-radius: 12px; padding: 15px 20px; margin-bottom: 30px; color: #fff; box-shadow: 0 5px 15px rgba(67, 40, 47, 0.2);">
        <summary style="font-size: 18px; font-weight: bold; cursor: pointer; outline: none; padding: 5px 0 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1); list-style: none;">🐾 Εμφάνιση ψηφιακού Vet Pass</summary>
        
        <div class="qr-flex-container" style="margin-top: 15px; display: flex; flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: nowrap; gap: 10px;">
            <div style="flex: 1; min-width: 0; text-align: left;">
                <h3 style="margin: 0; color: #fff; font-size: 20px; word-wrap: break-word;"><?php echo esc_html($pet['name']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #e0e0e0; font-size: 14px;">
                    <?php echo ($pet['type'] === 'dog' ? 'Σκύλος 🐶' : 'Γάτα 🐱'); ?> 
                </p>
                <?php if(!empty($pet['microchip'])): ?>
                    <div style="margin-top: 12px; background: rgba(255,255,255,0.1); padding: 8px 10px; border-radius: 6px; display: inline-block;">
                        <span style="color: #C7B297; font-size: 10px; display:block;">MICROCHIP</span>
                        <span style="color: #fff; font-weight:bold; font-size: 13px; letter-spacing: 0.5px; word-break: break-all;"><?php echo esc_html($pet['microchip']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="background: #fff; padding: 6px; border-radius: 8px; text-align: center; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <img src="<?php echo esc_url($qr_code_url); ?>" alt="Vet Pass QR Code" style="width: 85px; height: 85px; display: block;">
            </div>
        </div>
    </details>

    <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">

        <details class="petling-accordion petling-crm-form" open>
            <summary>📈 Ιστορικό Βάρους</summary>
            <div class="accordion-content">
                <?php
                $weight_history = [];
                foreach ($vet_notes as $n) {
                    if (!empty($n->weight)) { $weight_history[] = array('id' => $n->id, 'date' => $n->created_at, 'weight' => $n->weight); }
                }
                if (!empty($weight_history)) {
                    echo '<ul class="weight-compact-list">';
                    foreach($weight_history as $wh) {
                        $delete_weight_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_weight', 'weight_id' => $wh['id'] ) ), 'del_weight_' . $wh['id'] );
                        echo '<li class="weight-compact-item">';
                        echo '<span class="w-date">' . date('d/m/Y', strtotime($wh['date'])) . '</span>';
                        echo '<span class="w-val">' . esc_html($wh['weight']) . ' <span style="font-size:13px; font-weight:normal;">kg</span></span>';
                        echo '<a href="' . esc_url($delete_weight_url) . '" class="w-del" onclick="return confirm(\'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτή τη ζύγιση;\');" title="Διαγραφή Ζύγισης">&times;</a>';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p style="font-style:italic; color:#666; margin-bottom:15px; font-size:14px;">Δεν υπάρχουν ακόμα καταγραφές βάρους.</p>';
                }
                ?>
                <form method="post" action="" class="weight-form" style="background:#fdfaf5; padding:15px; border-radius:8px; border:1px dashed #C7B297; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                    <?php wp_nonce_field( 'petling_log_weight', 'petling_weight_nonce' ); ?>
                    <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                    <div style="flex:1; min-width: 150px;">
                        <label>Νέα Ζύγιση (kg) *</label>
                        <input type="number" step="0.01" min="0" name="weight" required>
                    </div>
                    <button type="submit" class="btn-petling btn-brown" style="height:42px; width: 100%; max-width: 200px;">Προσθήκη Βάρους</button>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>💉 Ιστορικό Εμβολιασμών (WSAVA)</summary>
            <div class="accordion-content">
                <?php if ( $vaccines ) : ?>
                    <table class="petling-table">
                        <thead>
                            <tr><th>Εμβόλιο</th><th>Ημ. Εμβολιασμού</th><th>Επόμενο Εμβόλιο</th><th>Κατάσταση</th><th style="text-align:center;">Ενέργεια</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $vaccines as $vac ) : 
                            $is_verified = ($vac->status === 'verified');
                            $status_html = $is_verified ? '<span style="color:#5b9a68; font-weight:bold;">✅ Επιβεβαιωμένο</span>' : '<span style="color:#e6a23c; font-weight:bold;">⏳ Προσχέδιο</span>';
                        ?>
                        <tr>
                            <td data-label="Εμβόλιο:"><strong><?php echo esc_html($vac->vaccine_name); ?></strong></td>
                            <td data-label="Ημ. Εμβολιασμού:"><?php echo esc_html(date('d/m/Y', strtotime($vac->date_administered))); ?></td>
                            <td data-label="Επόμενο Εμβόλιο:" style="font-weight:bold; color:#8B6139;">
                                <?php if ( !empty($vac->next_vaccine_date) && $vac->next_vaccine_date !== '0000-00-00' && strtotime($vac->next_vaccine_date) > 0 ) : 
                                    echo esc_html(date('d/m/Y', strtotime($vac->next_vaccine_date)));
                                    $ics_url = petling_crm_get_ics_link( '💉 Εμβολιασμός: ' . $vac->vaccine_name . ' (' . $pet['name'] . ')', $vac->next_vaccine_date, 'Υπενθύμιση από το Petling CRM.' );
                                    echo '<br><a href="' . esc_url($ics_url) . '" style="font-size:12px; color:#5b9a68; text-decoration:none; font-weight:normal; display:inline-block; margin-top:5px; background:#eef7ee; padding:3px 8px; border-radius:4px;">📅 Προσθήκη στο Ημερολόγιο</a>';
                                else : echo '-'; endif; ?>
                            </td>
                            <td data-label="Κατάσταση:"><?php echo $status_html; ?></td>
                            <td data-label="Ενέργεια:" style="text-align:center;">
                                <?php if ( !$is_verified ) : 
                                    $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_vac', 'vac_id' => $vac->id ) ), 'del_vac_' . $vac->id );
                                    echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Είστε σίγουροι;\');" style="color:#e62121; text-decoration:none; font-size:18px;">🗑️</a>';
                                else : echo '🔒'; endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="font-style:italic; color:#666; font-size:14px;">Δεν έχουν καταγραφεί ακόμα εμβόλια.</p>
                <?php endif; ?>

                <form method="post" action="" style="background:#fffaf1; padding:20px; border-radius:8px; border:1px dashed #C7B297; margin-top:15px;">
                    <?php wp_nonce_field( 'petling_add_vaccine', 'petling_vaccine_nonce' ); ?>
                    <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                    <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Προετοιμασία Εγγραφής <span style="display:block; font-size:12px; color:#666; font-weight:normal; margin-top:5px;">(Προετοιμάστε τα στοιχεία για γρήγορη επιβεβαίωση από τον ιατρό)</span></h4>
                    <div class="petling-grid">
                        <div><label>Επιλογή Εμβολίου *</label>
                            <select name="vaccine_name" required>
                                <?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?>
                            </select>
                        </div>
                        <div><label>Κλινική (Προαιρετικό)</label><input type="text" name="vet_name"></div>
                        <div><label>Ημερομηνία Εμβολιασμού *</label><input type="date" name="date_administered" required value="<?php echo date('Y-m-d'); ?>"></div>
                        <div><label>Επόμενο Εμβόλιο (Υπενθύμιση) 📅</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>"></div>
                    </div>
                    <div style="text-align:right; margin-top:15px;">
                        <button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:200px;">Αποθήκευση</button>
                    </div>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>🩺 Σημειώσεις & Επανεξετάσεις</summary>
            <div class="accordion-content">
                <?php if ( $vet_notes ) : ?>
                    <div style="margin-bottom:25px;">
                    <?php foreach ( $vet_notes as $note ) : 
                        if (!empty($note->weight) && empty($note->vet_comment)) continue; 
                        $is_verified = ($note->status === 'verified');
                    ?>
                        <div style="background:#fdfdfd; border:1px solid #eee; padding:15px; border-radius:8px; margin-bottom:15px; border-left:5px solid <?php echo ($is_verified ? '#5b9a68' : '#e6a23c'); ?>;">
                            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px; border-bottom: 1px dashed #eee; padding-bottom: 10px;">
                                <div style="font-size:13px; color:#666; line-height:1.6;">
                                    <span style="display:inline-block; margin-right:15px;"><strong>Ημ/νία:</strong> <?php echo date('d/m/Y', strtotime($note->created_at)); ?></span>
                                    <?php if (!empty($note->vet_name)) { echo '<span style="display:inline-block; margin-right:15px;"><strong>Γιατρός:</strong> ' . esc_html($note->vet_name) . '</span>'; } ?>
                                    <?php echo ($is_verified ? '<span style="color:#5b9a68; font-weight:bold;">✅ Verified</span>' : '<span style="color:#e6a23c; font-weight:bold;">⏳ Draft</span>'); ?>
                                </div>
                                
                                <?php if ( !$is_verified ) : 
                                    $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_note', 'note_id' => $note->id ) ), 'del_note_' . $note->id );
                                    echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Είστε σίγουροι;\');" style="color:#e62121; text-decoration:none; font-size:18px; margin-left:10px;">🗑️</a>';
                                else : echo '<span style="font-size:18px;">🔒</span>'; endif; ?>
                            </div>
                            
                            <p style="margin:0; font-size:15px; color:#333; line-height:1.5;"><?php echo nl2br(esc_html($note->vet_comment)); ?></p>
                            
                            <?php if ( !empty($note->next_exam_date) && $note->next_exam_date !== '0000-00-00' && strtotime($note->next_exam_date) > 0 ) : ?>
                                <div style="margin-top:15px; background:#fff5f5; padding:10px; border-radius:6px; border:1px solid #ffebeb; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                    <span style="color:#d63638; font-size:14px; font-weight:bold;">📅 Επανεξέταση: <?php echo date('d/m/Y', strtotime($note->next_exam_date)); ?></span>
                                    <?php $ics_url = petling_crm_get_ics_link( '🩺 Κτηνιατρική Επανεξέταση: ' . $pet['name'], $note->next_exam_date, 'Υπενθύμιση από το Petling CRM.' ); ?>
                                    <a href="<?php echo esc_url($ics_url); ?>" style="font-size:12px; color:#5b9a68; text-decoration:none; font-weight:bold; border: 1px solid #5b9a68; padding: 6px 12px; border-radius: 4px; background: #fff;">🗓️ Προσθήκη στο Ημερολόγιο</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="font-style:italic; color:#666; margin-bottom:25px; font-size:14px;">Δεν υπάρχουν ακόμα ιατρικές σημειώσεις.</p>
                <?php endif; ?>

                <form method="post" action="" style="background:#fffaf1; padding:20px; border-radius:8px; border:1px dashed #C7B297;">
                    <?php wp_nonce_field( 'petling_add_vet_note', 'petling_vet_note_nonce' ); ?>
                    <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                    <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Προετοιμασία Εγγραφής <span style="display:block; font-size:12px; color:#666; font-weight:normal; margin-top:5px;">(Προετοιμάστε τα στοιχεία για γρήγορη επιβεβαίωση από τον ιατρό)</span></h4>
                    <div class="petling-grid">
                        <div><label>Όνομα Κλινικής (Προαιρετικό)</label><input type="text" name="vet_name"></div>
                        <div><label>Επανεξέταση 📅</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>"></div>
                        <div class="petling-grid-full"><label>Σχόλια / Διάγνωση *</label><textarea name="vet_comment" required></textarea></div>
                    </div>
                    <div style="text-align:right; margin-top:15px;">
                        <button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:200px;">Αποθήκευση</button>
                    </div>
                </form>
            </div>
        </details>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const accordions = document.querySelectorAll('.petling-accordion');
            accordions.forEach(acc => {
                acc.addEventListener('click', function(e) {
                    if (!this.hasAttribute('open')) {
                        accordions.forEach(otherAcc => {
                            if (otherAcc !== this) {
                                otherAcc.removeAttribute('open');
                            }
                        });
                    }
                });
            });
        });
    </script>
    <?php
}