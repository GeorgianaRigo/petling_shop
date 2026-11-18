<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. DATA HANDLING & FUNCTIONS
// =========================================================================

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
        
        $ics_content = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Petling CRM//NONSGML v1.0//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:" . uniqid() . "@petling.gr\r\nDTSTAMP:" . $stamp . "\r\nDTSTART;VALUE=DATE:" . $start . "\r\nDTEND;VALUE=DATE:" . $end . "\r\nSUMMARY:" . $title . "\r\nDESCRIPTION:" . $desc . "\r\nBEGIN:VALARM\r\nACTION:DISPLAY\r\nDESCRIPTION:Υπενθύμιση Petling\r\nTRIGGER:-P1W\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="petling-reminder.ics"');
        echo $ics_content;
        exit;
    }
}

function petling_crm_get_ics_link( $title, $date ) {
    if ( empty( $date ) || $date === '0000-00-00' || strtotime($date) <= 0 ) return '';
    
    $user_id = get_current_user_id();
    $saved_vets = get_user_meta($user_id, 'petling_vets', true);
    $vet_contact_str = '';
    
    if (is_array($saved_vets) && !empty($saved_vets[0]['name'])) {
        $vet_contact_str = $saved_vets[0]['name'];
        if (!empty($saved_vets[0]['phone'])) {
            $vet_contact_str .= ' στο ' . $saved_vets[0]['phone'];
        }
    }
    
    $details = "Υπενθύμιση από το Petling: Καλέστε τον κτηνίατρο " . ($vet_contact_str ? $vet_contact_str : 'σας') . " για να κλείσετε / επιβεβαιώσετε το ραντεβού σας.";
    
    return add_query_arg( [ 'petling_ics' => '1', 'title' => urlencode($title), 'date' => $date, 'desc' => urlencode($details) ], site_url() );
}

add_action( 'template_redirect', 'petling_crm_handle_vets' );
function petling_crm_handle_vets() {
    if ( isset( $_POST['petling_save_vets'] ) && wp_verify_nonce( $_POST['vets_nonce'], 'petling_vets_nonce' ) ) {
        $vets_data = [];
        if ( ! empty($_POST['vet_name']) && is_array($_POST['vet_name']) ) {
            foreach ($_POST['vet_name'] as $idx => $v_name) {
                if (empty(trim($v_name))) continue;
                $vets_data[] = [
                    'name'    => sanitize_text_field($v_name),
                    'email'   => sanitize_email($_POST['vet_email'][$idx] ?? ''),
                    'phone'   => sanitize_text_field($_POST['vet_phone'][$idx] ?? ''),
                    'sos'     => sanitize_text_field($_POST['vet_sos'][$idx] ?? ''),
                    'sos2'    => sanitize_text_field($_POST['vet_sos2'][$idx] ?? ''),
                    'address' => sanitize_text_field($_POST['vet_address'][$idx] ?? '')
                ];
            }
        }
        update_user_meta( get_current_user_id(), 'petling_vets', $vets_data );
        wp_safe_redirect( add_query_arg('saved', '1', wp_get_referer()) );
        exit;
    }
}

add_action( 'template_redirect', 'petling_crm_handle_vaccines' );
function petling_crm_handle_vaccines() {
    global $wpdb;
    if ( isset( $_POST['petling_vaccine_nonce'] ) && wp_verify_nonce( $_POST['petling_vaccine_nonce'], 'petling_add_vaccine' ) ) {
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $v_name = sanitize_text_field($_POST['vaccine_name']);
        $next_date = sanitize_text_field($_POST['next_vaccine_date']);
        
        $pets = get_user_meta(get_current_user_id(), 'petling_pets', true);
        $p_name = '';
        if (is_array($pets)) {
            foreach ($pets as $p) { if ($p['id'] === $pet_id) { $p_name = $p['name']; break; } }
        }

        $parasite_options = array('Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)', 'Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)', 'Combo (Εσωτερικά & Εξωτερικά)');

        if (in_array($v_name, $parasite_options)) {
            $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s", $pet_id, $v_name) );
        }

        $wpdb->insert( $wpdb->prefix . 'petling_vaccines', array( 
            'pet_unique_id' => $pet_id, 
            'vaccine_name' => $v_name, 
            'date_administered' => date('Y-m-d'),
            'next_vaccine_date' => $next_date, 
            'vet_name' => sanitize_text_field($_POST['vet_name'] ?? ''), 
            'status' => 'verified', 
            'created_by' => 'owner' 
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'my-pets' ) );
        
        if (!empty($next_date)) {
            $type_text = in_array($v_name, $parasite_options) ? '🪱 Αποπαρασίτωση' : '💉 Εμβολιασμός';
            $full_title = $type_text . ': ' . $v_name . ' (' . $p_name . ')';
            $ics_url = petling_crm_get_ics_link( $full_title, $next_date );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        
        wp_safe_redirect( $redirect_url ); exit;
    }
    
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_vac' && isset( $_GET['vac_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_vac_' . $_GET['vac_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'vac_id', '_wpnonce', 'auto_ics' ) ) ); exit;
    }
}

add_action( 'template_redirect', 'petling_crm_handle_vet_notes' );
function petling_crm_handle_vet_notes() {
    global $wpdb;
    if ( isset( $_POST['petling_vet_note_nonce'] ) && wp_verify_nonce( $_POST['petling_vet_note_nonce'], 'petling_add_vet_note' ) ) {
        $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
        $next_exam = sanitize_text_field($_POST['next_exam_date']);
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        
        $pets = get_user_meta(get_current_user_id(), 'petling_pets', true);
        $p_name = '';
        if (is_array($pets)) {
            foreach ($pets as $p) { if ($p['id'] === $pet_id) { $p_name = $p['name']; break; } }
        }

        $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 
            'pet_unique_id' => $pet_id, 
            'vet_comment' => sanitize_textarea_field($_POST['vet_comment']), 
            'next_exam_date'=> $next_exam, 
            'vet_name' => sanitize_text_field($_POST['vet_name']), 
            'status' => 'draft', 
            'created_by' => 'owner',
            'created_at' => $incident_date
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'my-pets' ) );
        
        if (!empty($next_exam)) {
            $ics_url = petling_crm_get_ics_link( '🩺 Επανεξέταση (' . $p_name . ')', $next_exam );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }
    
    if ( isset( $_POST['petling_weight_nonce'] ) && wp_verify_nonce( $_POST['petling_weight_nonce'], 'petling_log_weight' ) ) {
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $new_weight = sanitize_text_field($_POST['weight']);
        if ( !empty($new_weight) ) {
            $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'weight' => $new_weight, 'vet_comment' => 'Τακτική Ζύγιση (Κηδεμόνας)', 'status' => 'verified', 'created_by' => 'owner' ), array( '%s', '%f', '%s', '%s', '%s' ) );
            $user_id = get_current_user_id();
            $pets_array = get_user_meta($user_id, 'petling_pets', true);
            if (is_array($pets_array)) {
                foreach ($pets_array as &$p) { if ($p['id'] === $pet_id) { $p['weight'] = $new_weight; break; } }
                update_user_meta($user_id, 'petling_pets', $pets_array);
            }
        }
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'my-pets' ) ) ); exit;
    }
    
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_note' && isset( $_GET['note_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_note_' . $_GET['note_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'note_id', '_wpnonce', 'auto_ics' ) ) ); exit;
    }
    
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_weight' && isset( $_GET['weight_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_weight_' . $_GET['weight_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['weight_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'weight_id', '_wpnonce', 'auto_ics' ) ) ); exit;
    }
}

// =========================================================================
// 2. HTML VIEW (Η οθόνη του Πελάτη)
// =========================================================================

function petling_crm_render_medical_history( $pet ) {
    global $wpdb;
    $pet_id = $pet['id'] ?? '';
    
    $current_time = time();
    $vet_token = md5( $pet_id . $current_time . wp_salt() );
    $vet_pass_url = site_url( '/vet-pass/?pet=' . $pet_id . '&t=' . $current_time . '&token=' . $vet_token );
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode( $vet_pass_url );

    $parasite_options = array('Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)', 'Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)', 'Combo (Εσωτερικά & Εξωτερικά)');

    $one_year_ago = date('Y-m-d', strtotime('-1 year'));
    foreach ($parasite_options as $p_opt) {
        $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s AND date_administered < %s", $pet_id, $p_opt, $one_year_ago) );
    }

    $all_records = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s ORDER BY date_administered DESC", $pet_id ) );
    $vet_notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vet_notes WHERE pet_unique_id = %s ORDER BY created_at DESC", $pet_id ) );

    if ( $pet['type'] === 'dog' ) {
        $vaccine_options = array( 'Παρβοϊός / Μόρβα / Ηπατίτιδα (Core)', 'Λύσσα (Core)', 'Λεπτοσπείρωση (Core)', 'Βήχας Κυνοκομείου (Non-core)', 'Νόσος Lyme (Non-core)', 'Γρίπη Σκύλων (Non-core)', 'Άλλο' );
    } else {
        $vaccine_options = array( 'Τριπλό: FPV/FHV/FCV (Core)', 'Λύσσα (Core)', 'Λευχαιμία - FeLV (Core)', 'Χλαμυδίαση (Non-core)', 'Ανοσοανεπάρκεια - FIV (Non-core)', 'Άλλο' );
    }

    $vaccines = [];
    $parasites = [];
    foreach ($all_records as $rec) {
        if (in_array($rec->vaccine_name, $parasite_options)) { $parasites[] = $rec; } else { $vaccines[] = $rec; }
    }

    $min_future_date = date('Y-m-d'); 

    $age_str = '';
    if ( !empty($pet['birthday']) ) {
        try {
            $bday = new DateTime($pet['birthday']);
            $today = new DateTime('today');
            $diff = $bday->diff($today);
            if ( $diff->y > 0 ) { $age_str = $diff->y . ' ετών'; } elseif ( $diff->m > 0 ) { $age_str = $diff->m . ' μηνών'; } else { $age_str = 'Νεογέννητο'; }
        } catch (Exception $e) { $age_str = ''; }
    }

    $neutered_text = '-';
    if ( isset($pet['neutered']) && $pet['neutered'] === 'yes' ) {
        $neutered_text = !empty($pet['neutered_date']) ? date('d/m/Y', strtotime($pet['neutered_date'])) : 'Ναι (Άγνωστη Ημ/νία)';
    }

    echo '<a href="' . esc_url( remove_query_arg( array( 'view', 'pet_id', 'saved', 'auto_ics' ) ) ) . '" class="no-loader" style="display:inline-block; margin-bottom:20px; font-weight:bold; color:#8B6139; text-decoration:none;">&larr; Πίσω στα Κατοικίδιά μου</a>';
    
    if (isset($_GET['auto_ics'])) {
        echo '<iframe src="'.esc_url(urldecode($_GET['auto_ics'])).'" style="display:none;"></iframe>';
    }
    ?>
    <style>
        /* CSS για τα βελάκια δεξιά στο Accordion */
        details.petling-accordion summary {
            position: relative;
            padding-right: 40px;
            list-style: none; /* Κρύβει το default βελάκι */
        }
        details.petling-accordion summary::-webkit-details-marker {
            display: none; /* Για Safari */
        }
        details.petling-accordion summary::after {
            content: '▼';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: #C7B297;
            transition: transform 0.3s ease;
        }
        details.petling-accordion[open] summary::after {
            transform: translateY(-50%) rotate(180deg);
        }
        
        /* Ειδικά για το Vet Pass (Μπορντό) για να μην παίρνει το λευκό φόντο */
        details#vet-pass-block summary {
            background: transparent !important;
            color: #fff !important;
        }
        details#vet-pass-block summary::after {
            color: #fff !important;
        }
    </style>
    
    <div class="petling-loader-overlay" id="petling-global-loader" style="display: none;">
        <div class="petling-spinner"></div>
        <p>Αποθήκευση...</p>
    </div>

    <div class="petling-toast-notification" id="petling-toast" style="display: none;">✅ Η αλλαγή αποθηκεύτηκε. Το ημερολόγιο κατεβαίνει!</div>

    <div style="background: #eef7ee; border: 1px solid #5b9a68; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #333; line-height: 1.5;">
        <strong>ℹ️ Πώς λειτουργεί:</strong> Βάλτε μόνο την <strong>ημερομηνία της επόμενης δόσης</strong> για τα εμβόλια. Το σύστημα θα περάσει αυτόματα την υπενθύμιση στο ημερολόγιο του κινητού σας. Ο κτηνίατρος μπορεί να χρησιμοποιήσει το QR Code για να γράψει ιατρικές σημειώσεις και αποπαρασιτώσεις.
    </div>

    <details id="vet-pass-block" class="petling-accordion" style="background: linear-gradient(135deg, #43282F 0%, #2a191d 100%); border-radius: 12px; padding: 15px 20px; margin-bottom: 30px; color: #fff; box-shadow: 0 5px 15px rgba(67, 40, 47, 0.2);">
        <summary style="font-size: 18px; font-weight: bold; cursor: pointer; outline: none; padding: 5px 0 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">🐾 Εμφάνιση ψηφιακού Vet Pass</summary>
        
        <div class="qr-flex-container" style="margin-top: 15px; display: flex; flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: nowrap; gap: 10px;">
            <div style="flex: 1; min-width: 0; text-align: left;">
                
                <div style="display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;">
                    <h3 style="margin: 0; color: #fff; font-size: 20px; word-wrap: break-word;">
                        <?php echo ($pet['type'] === 'dog' ? '🐶' : '🐱') . ' ' . esc_html($pet['name']); ?>
                        <?php if (!empty($age_str)) echo '<span style="font-size: 15px; color: #e0e0e0; font-weight: normal; margin-left: 5px;">(' . $age_str . ')</span>'; ?>
                    </h3>
                </div>
                
                <p style="margin: 5px 0 0 0; color: #5b9a68; font-size: 13px; font-weight: bold;">
                    Στειρωμένο: <span style="color:#eef7ee; font-weight:normal;"><?php echo $neutered_text; ?></span>
                </p>

                <?php if(!empty($pet['microchip'])): ?>
                    <div style="margin-top: 12px; background: rgba(255,255,255,0.1); padding: 8px 10px; border-radius: 6px; display: inline-block;">
                        <span style="color: #C7B297; font-size: 10px; display:block;">MICROCHIP</span>
                        <span style="color: #fff; font-weight:bold; font-size: 13px; letter-spacing: 0.5px; word-break: break-all;"><?php echo esc_html($pet['microchip']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div style="background: #fff; padding: 6px; border-radius: 8px; text-align: center; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><img src="<?php echo esc_url($qr_code_url); ?>" alt="Vet Pass QR Code" style="width: 85px; height: 85px; display: block;"></div>
        </div>
    </details>

    <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">

        <!-- ΝΕΑ ΕΝΟΤΗΤΑ: ΟΙ ΚΤΗΝΙΑΤΡΟΙ ΜΟΥ (ΜΕ GRID 3 ΣΤΗΛΩΝ & KΛΕΙΣΤΗ ΑΡΧΙΚΑ) -->
        <details class="petling-accordion petling-crm-form">
            <summary>👨‍⚕️ Οι Κτηνίατροί μου (Επικοινωνία)</summary>
            <div class="accordion-content">
                <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Προσθέστε έως 3 κτηνιάτρους. Το τηλέφωνό τους θα ενσωματώνεται αυτόματα στις υπενθυμίσεις ραντεβού στο ημερολόγιό σας!</p>
                <form method="post" action="">
                    <?php wp_nonce_field('petling_vets_nonce', 'vets_nonce'); ?>
                    <input type="hidden" name="petling_save_vets" value="1">
                    <div id="vets-repeater-container">
                        <?php 
                        $saved_vets = get_user_meta( get_current_user_id(), 'petling_vets', true ); 
                        if ( !is_array($saved_vets) ) $saved_vets = []; 
                        foreach ($saved_vets as $v_idx => $v) : 
                        ?>
                            <div class="vet-block">
                                <!-- VIEW MODE -->
                                <div class="vet-view-mode" style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2d4c0; padding-bottom:10px; margin-bottom:10px;">
                                        <h4 style="margin:0; font-size:16px; color:#43282F;"><?php echo esc_html($v['name']); ?></h4>
                                        <button type="button" class="edit-vet-btn" style="background:none; border:none; color:#5b9a68; font-weight:bold; cursor:pointer;">✏️ Επεξεργασία</button>
                                    </div>
                                    <?php if(!empty($v['email'])): ?><p style="margin:5px 0; font-size:14px;">📧 <a href="mailto:<?php echo esc_attr($v['email']); ?>" style="color:#333; text-decoration:none;"><?php echo esc_html($v['email']); ?></a></p><?php endif; ?>
                                    <?php if(!empty($v['address'])): ?><p style="margin:5px 0; font-size:14px;">🗺️ <a href="https://maps.google.com/?q=<?php echo urlencode($v['address']); ?>" target="_blank" style="color:#333; text-decoration:underline;"><?php echo esc_html($v['address']); ?></a></p><?php endif; ?>
                                    <div style="margin-top:10px; padding-top:10px; border-top:1px dashed #e2d4c0;">
                                        <?php if(!empty($v['phone'])): ?><p style="margin:5px 0; font-size:14px;">☎️ <strong><a href="tel:<?php echo esc_attr(str_replace(' ', '', $v['phone'])); ?>" style="color:#333; text-decoration:none;"><?php echo esc_html($v['phone']); ?></a></strong></p><?php endif; ?>
                                        <?php if(!empty($v['sos'])): ?><p style="margin:5px 0; font-size:14px; color:#d63638;">🚨 <strong>SOS 1:</strong> <a href="tel:<?php echo esc_attr(str_replace(' ', '', $v['sos'])); ?>" style="color:#d63638; text-decoration:none;"><strong><?php echo esc_html($v['sos']); ?></strong></a></p><?php endif; ?>
                                        <?php if(!empty($v['sos2'])): ?><p style="margin:5px 0; font-size:14px; color:#d63638;">🚨 <strong>SOS 2:</strong> <a href="tel:<?php echo esc_attr(str_replace(' ', '', $v['sos2'])); ?>" style="color:#d63638; text-decoration:none;"><strong><?php echo esc_html($v['sos2']); ?></strong></a></p><?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- EDIT MODE -->
                                <div class="vet-edit-mode" style="background: #fcfcfc; border: 1px dashed #C7B297; padding: 15px; border-radius: 8px; margin-bottom: 15px; display:none;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2d4c0; padding-bottom:10px; margin-bottom:15px;">
                                        <h4 style="margin:0; font-size:16px; color:#43282F;">Επεξεργασία Ιατρείου</h4>
                                        <button type="button" class="remove-vet-button" style="background:none; border:none; color:#e62121; font-weight:bold; font-size:14px; cursor:pointer;">🗑️ Διαγραφή</button>
                                    </div>
                                    <!-- Σειρά 1: Όνομα, Email, Διεύθυνση -->
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                        <div><label>Όνομα / Επωνυμία *</label><input type="text" name="vet_name[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['name']); ?>" style="width:100%; box-sizing:border-box;" required></div>
                                        <div><label>Email</label><input type="email" name="vet_email[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['email'] ?? ''); ?>" style="width:100%; box-sizing:border-box;"></div>
                                        <div><label>Διεύθυνση / Google Maps</label><input type="text" name="vet_address[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['address']); ?>" style="width:100%; box-sizing:border-box;"></div>
                                    </div>
                                    <!-- Σειρά 2: Τηλέφωνο, SOS1, SOS2 -->
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                        <div><label>Τηλέφωνο ιατρείου *</label><input type="tel" pattern="[0-9\s\+]*" name="vet_phone[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['phone']); ?>" style="width:100%; box-sizing:border-box;" required></div>
                                        <div><label>Κινητό SOS 1 (Προαιρετικό)</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['sos']); ?>" style="width:100%; box-sizing:border-box;"></div>
                                        <div><label>Κινητό SOS 2 (Προαιρετικό)</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos2[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['sos2'] ?? ''); ?>" style="width:100%; box-sizing:border-box;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex; justify-content:space-between; gap:10px; margin-top:15px;">
                        <button type="button" id="add-vet-button" class="btn-petling" style="background:#f3f4f6; color:#333; border:1px solid #ccc; width:auto; padding:0 20px;">➕ Προσθήκη Ιατρείου</button>
                        <button type="submit" class="btn-petling btn-brown" style="width:auto; padding:0 20px;">Αποθήκευση Στοιχείων</button>
                    </div>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>📈 Ιστορικό Βάρους</summary>
            <div class="accordion-content">
                <?php
                $weight_history = [];
                foreach ($vet_notes as $n) {
                    if (!empty($n->weight)) { 
                        $year = date('Y', strtotime($n->created_at));
                        if(!isset($weight_history[$year])) $weight_history[$year] = [];
                        $weight_history[$year][] = array('id' => $n->id, 'date' => $n->created_at, 'weight' => $n->weight); 
                    }
                }
                
                $total_weights = 0;
                foreach($weight_history as $year => $weights) { $total_weights += count($weights); }

                if ($total_weights > 0) {
                    echo '<div class="weight-history-wrapper">';
                    $global_index = 0;
                    
                    foreach($weight_history as $year => $weights) {
                        $year_display = ($global_index >= 5) ? 'display:none;' : '';
                        echo '<div class="weight-year-group" data-year="'.$year.'" style="'.$year_display.'">';
                        echo '<h5>📅 '.$year.'</h5>';
                        echo '<ul class="weight-compact-list" style="padding:0; margin:0; list-style:none;">';
                        
                        foreach($weights as $wh) {
                            $item_display = ($global_index >= 5) ? 'display:none;' : '';
                            $delete_weight_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_weight', 'weight_id' => $wh['id'] ) ), 'del_weight_' . $wh['id'] );
                            
                            echo '<li class="weight-compact-item weight-item-row" style="'.$item_display.'">';
                            echo '<span class="w-date" style="flex: 0 0 100px; display: inline-block; color: #666;">' . date('d/m/Y', strtotime($wh['date'])) . '</span>';
                            echo '<span class="w-val" style="flex:1; text-align:right; font-weight:bold; padding-right:10px;">' . esc_html($wh['weight']) . ' <span style="font-size:13px; font-weight:normal;">kg</span></span>';
                            echo '<a href="' . esc_url($delete_weight_url) . '" class="w-del no-loader" onclick="if(confirm(\'Θέλετε σίγουρα να διαγράψετε αυτή τη ζύγιση;\')) { sessionStorage.setItem(\'petling_saved\', \'1\'); return true; } else { return false; }" style="color:#e62121; text-decoration:none; font-size:24px;">&times;</a>';
                            echo '</li>';
                            $global_index++;
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                    
                    if ($total_weights > 5) {
                        $hidden_count = $total_weights - 5;
                        echo '<div style="text-align:center; padding:12px; border-top:1px solid #e5e5e5; background:#fafafa;">';
                        echo '<button type="button" class="btn-show-older-weights">👁️ Εμφάνιση παλαιότερων ('.$hidden_count.')</button>';
                        echo '</div>';
                    }
                    echo '</div>';
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
                    <button type="submit" class="btn-petling btn-brown" style="height:42px; width: 100%; max-width: 200px;">Προσθήκη</button>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>💉 Εμβολιασμοί / Υπενθυμίσεις</summary>
            <div class="accordion-content">
                <?php if ( $vaccines ) : ?>
                    <table class="petling-table">
                        <thead><tr><th>Εμβόλιο</th><th>Επόμενο Εμβόλιο</th><th style="text-align:center;">Ενέργεια</th></tr></thead>
                        <tbody>
                        <?php foreach ( $vaccines as $vac ) : ?>
                        <tr>
                            <td data-label="Εμβόλιο:"><strong><?php echo esc_html($vac->vaccine_name); ?></strong></td>
                            <td data-label="Επόμενο Εμβόλιο:" style="font-weight:bold; color:#8B6139;">
                                <?php if ( !empty($vac->next_vaccine_date) && $vac->next_vaccine_date !== '0000-00-00' && strtotime($vac->next_vaccine_date) > 0 ) : 
                                    echo esc_html(date('d/m/Y', strtotime($vac->next_vaccine_date)));
                                else : echo '-'; endif; ?>
                            </td>
                            <td data-label="Ενέργεια:" style="text-align:center;">
                                <?php $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_vac', 'vac_id' => $vac->id ) ), 'del_vac_' . $vac->id );
                                echo '<a href="' . esc_url($delete_url) . '" class="no-loader" onclick="if(confirm(\'Διαγραφή υπενθύμισης;\')) { sessionStorage.setItem(\'petling_saved\', \'1\'); return true; } else { return false; }" style="color:#e62121; font-size:18px;">🗑️</a>'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="font-style:italic; color:#666; font-size:14px;">Δεν έχουν καταγραφεί ακόμα υπενθυμίσεις εμβολίων.</p>
                <?php endif; ?>

                <form method="post" action="" style="background:#fffaf1; padding:20px; border-radius:8px; border:1px dashed #C7B297; margin-top:15px;">
                    <?php wp_nonce_field( 'petling_add_vaccine', 'petling_vaccine_nonce' ); ?>
                    <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                    <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Νέα Υπενθύμιση</h4>
                    <div class="petling-grid">
                        <div class="petling-grid-full"><label>Επιλογή Εμβολίου *</label><select name="vaccine_name" required><?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?></select></div>
                        <div class="petling-grid-full"><label>Επόμενο Εμβόλιο 📅 *</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>" required></div>
                    </div>
                    <div style="text-align:right; margin-top:15px;"><button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:250px;">Αποθήκευση & Ημερολόγιο</button></div>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>🪱 Αποπαρασίτωση</summary>
            <div class="accordion-content">
                <div style="background:#fff3cd; border-left:4px solid #ffeeba; padding:10px 15px; margin-bottom:20px; font-size:13px; color:#856404; border-radius:4px; line-height:1.5;">
                    <strong>ℹ️ Πώς λειτουργεί:</strong> Όταν προσθέτετε μια νέα προστασία ίδιου τύπου, η προηγούμενη αντικαθίσταται αυτόματα.
                </div>

                <?php if ( $parasites ) : ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-bottom: 25px;">
                    <?php foreach ( $parasites as $vac ) : ?>
                        <div style="background:#fffaf1; border:2px solid #e2d4c0; padding:15px; border-radius:10px; position:relative;">
                            <?php $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_vac', 'vac_id' => $vac->id ) ), 'del_vac_' . $vac->id ); ?>
                            <a href="<?php echo esc_url($delete_url); ?>" class="no-loader" onclick="if(confirm('Διαγραφή αυτής της προστασίας;')) { sessionStorage.setItem('petling_saved', '1'); return true; } else { return false; }" style="position:absolute; top:10px; right:15px; color:#e62121; text-decoration:none; font-size:20px; font-weight:bold;">&times;</a>
                            <h4 style="margin:0 0 10px 0; color:#43282F; font-size:16px; padding-right:20px; border-bottom:1px solid #e2d4c0; padding-bottom:8px;"><?php echo esc_html($vac->vaccine_name); ?></h4>
                            <p style="margin:5px 0; font-size:14px;"><strong>Σκεύασμα:</strong> <?php echo esc_html($vac->vet_name ? $vac->vet_name : '-'); ?></p>
                            <?php if ( !empty($vac->next_vaccine_date) && $vac->next_vaccine_date !== '0000-00-00' && strtotime($vac->next_vaccine_date) > 0 ) : ?>
                                <p style="margin:10px 0 5px 0; color:#d63638; font-weight:bold; font-size:14px;">📅 Επόμενη Δόση: <?php echo esc_html(date('d/m/Y', strtotime($vac->next_vaccine_date))); ?></p>
                            <?php else: echo '<p style="margin:10px 0 5px 0; color:#666; font-size:14px;">Δεν έχει οριστεί επόμενη δόση.</p>'; endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="font-style:italic; color:#666; font-size:14px; margin-bottom:20px;">Δεν υπάρχει ενεργή προστασία καταγεγραμμένη.</p>
                <?php endif; ?>

                <form method="post" action="" style="background:#fff; padding:20px; border-radius:8px; border:1px dashed #C7B297;">
                    <?php wp_nonce_field( 'petling_add_vaccine', 'petling_vaccine_nonce' ); ?>
                    <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                    <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Ανανέωση Προστασίας</h4>
                    <div class="petling-grid">
                        <div><label>Τύπος *</label><select name="vaccine_name" required><?php foreach($parasite_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?></select></div>
                        <div><label>Σκεύασμα (π.χ. Bravecto, Nexgard)</label><input type="text" name="vet_name" placeholder="Προαιρετικό"></div>
                        <div class="petling-grid-full"><label>Επόμενη Δόση 📅 *</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>" required></div>
                    </div>
                    <div style="text-align:right; margin-top:15px;"><button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:250px;">Αποθήκευση & Ημερολόγιο</button></div>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>🩺 Παθήσεις, Αγωγές & Σημειώσεις</summary>
            <div class="accordion-content">
                <?php if ( $vet_notes ) : ?>
                    <div style="margin-bottom:25px;">
                    <?php foreach ( $vet_notes as $note ) : 
                        if ( empty($note->vet_comment) || strpos($note->vet_comment, 'Τακτική Ζύγιση') !== false ) continue;
                        $is_verified = ($note->status === 'verified');
                    ?>
                        <div style="background:#fdfdfd; border:1px solid #eee; padding:15px; border-radius:8px; margin-bottom:15px; border-left:5px solid <?php echo ($is_verified ? '#5b9a68' : '#e6a23c'); ?>;">
                            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px; border-bottom: 1px dashed #eee; padding-bottom: 10px;">
                                <div style="font-size:13px; color:#666; line-height:1.6;">
                                    <span style="display:inline-block; margin-right:15px;"><strong>Ημ/νία:</strong> <?php echo date('d/m/Y', strtotime($note->created_at)); ?></span>
                                    <?php if (!empty($note->vet_name)) { echo '<span style="display:inline-block; margin-right:15px;"><strong>Ιατρός:</strong> ' . esc_html($note->vet_name) . '</span>'; } ?>
                                    <?php echo ($is_verified ? '<span style="color:#5b9a68; font-weight:bold;">✅ Επιβεβαιωμένο από Γιατρό</span>' : '<span style="color:#e6a23c; font-weight:bold;">⏳ Σημείωση</span>'); ?>
                                </div>
                                <?php $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_note', 'note_id' => $note->id ) ), 'del_note_' . $note->id );
                                echo '<a href="' . esc_url($delete_url) . '" class="no-loader" onclick="if(confirm(\'Διαγραφή εγγραφής;\')) { sessionStorage.setItem(\'petling_saved\', \'1\'); return true; } else { return false; }" style="color:#e62121; font-size:18px; margin-left:10px;">🗑️</a>'; ?>
                            </div>
                            <p style="margin:0; font-size:15px; color:#333; line-height:1.5;"><?php echo nl2br(esc_html($note->vet_comment)); ?></p>
                            <?php if ( !empty($note->next_exam_date) && $note->next_exam_date !== '0000-00-00' && strtotime($note->next_exam_date) > 0 ) : ?>
                                <div style="margin-top:15px; background:#fff5f5; padding:10px; border-radius:6px; border:1px solid #ffebeb; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                    <span style="color:#d63638; font-size:14px; font-weight:bold;">📅 Επανεξέταση: <?php echo date('d/m/Y', strtotime($note->next_exam_date)); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="font-style:italic; color:#666; margin-bottom:25px; font-size:14px;">Δεν υπάρχουν ακόμα καταγραφές.</p>
                <?php endif; ?>

                <form method="post" action="" style="background:#fffaf1; padding:20px; border-radius:8px; border:1px dashed #C7B297;">
                    <?php wp_nonce_field( 'petling_add_vet_note', 'petling_vet_note_nonce' ); ?>
                    <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                    <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Νέα Σημείωση / Επανεξέταση</h4>
                    <div class="petling-grid">
                        <div><label>Ιατρός (Προαιρετικό)</label><input type="text" name="vet_name" placeholder="π.χ. Δρ. Παπαδόπουλος"></div>
                        <div><label>Ημερομηνία Συμβάντος *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>"></div>
                        <div><label>Επανεξέταση 📅 (Προαιρετικό)</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>"></div>
                        <div class="petling-grid-full"><label>Πάθηση, Αγωγή & Σχόλια *</label><textarea name="vet_comment" required placeholder="π.χ. Ελαφριά ουρολοίμωξη. Αντιβίωση για 7 ημέρες."></textarea></div>
                    </div>
                    <div style="text-align:right; margin-top:15px;"><button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:250px;">Αποθήκευση & Ημερολόγιο</button></div>
                </form>
            </div>
        </details>
    </div>
    
    <!-- TEMPLATE ΓΙΑ ΤΟΥΣ ΚΤΗΝΙΑΤΡΟΥΣ JAVASCRIPT -->
    <div id="vet-block-template" style="display:none;">
        <div class="vet-edit-mode" style="background: #fcfcfc; border: 1px dashed #C7B297; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2d4c0; padding-bottom:10px; margin-bottom:15px;">
                <h4 style="margin:0; font-size:16px; color:#43282F;">Νέο Ιατρείο</h4>
                <button type="button" class="remove-vet-button" style="background:none; border:none; color:#e62121; font-weight:bold; font-size:14px; cursor:pointer;">🗑️ Διαγραφή</button>
            </div>
            <!-- Σειρά 1 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div><label>Όνομα / Επωνυμία *</label><input type="text" name="vet_name[__VINDEX__]" placeholder="π.χ. Κτηνιατρική Αθηνών" style="width:100%; box-sizing:border-box;" required></div>
                <div><label>Email</label><input type="email" name="vet_email[__VINDEX__]" placeholder="π.χ. info@vet.gr" style="width:100%; box-sizing:border-box;"></div>
                <div><label>Διεύθυνση / Google Maps</label><input type="text" name="vet_address[__VINDEX__]" placeholder="Οδός & Αριθμός, Πόλη" style="width:100%; box-sizing:border-box;"></div>
            </div>
            <!-- Σειρά 2 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div><label>Τηλέφωνο ιατρείου *</label><input type="tel" pattern="[0-9\s\+]*" name="vet_phone[__VINDEX__]" placeholder="π.χ. 2101234567" style="width:100%; box-sizing:border-box;" required></div>
                <div><label>Κινητό SOS 1 (Προαιρετικό)</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos[__VINDEX__]" style="width:100%; box-sizing:border-box;"></div>
                <div><label>Κινητό SOS 2 (Προαιρετικό)</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos2[__VINDEX__]" style="width:100%; box-sizing:border-box;"></div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var nextVetIndex = $('.vet-block').length;
        
        // Λειτουργία Edit (Μετατροπή View σε Form)
        $(document).off('click', '.edit-vet-btn').on('click', '.edit-vet-btn', function(e) {
            e.preventDefault();
            $(this).closest('.vet-block').find('.vet-view-mode').hide();
            $(this).closest('.vet-block').find('.vet-edit-mode').fadeIn();
        });

        // Προσθήκη νέου γιατρού (Φορτώνει κατευθείαν το Form Edit Mode)
        $(document).off('click', '#add-vet-button').on('click', '#add-vet-button', function(e) {
            e.preventDefault();
            if ( $('.vet-block').length >= 3 ) {
                alert('Μπορείτε να προσθέσετε μέχρι 3 κτηνιάτρους.');
                return;
            }
            var templateHtml = $('#vet-block-template').html().replace(/__VINDEX__/g, nextVetIndex);
            var newBlock = $('<div class="vet-block" style="margin-bottom:15px; display:none;">' + templateHtml + '</div>');
            $('#vets-repeater-container').append(newBlock);
            newBlock.slideDown(200);
            nextVetIndex++;
        });

        $(document).off('click', '.remove-vet-button').on('click', '.remove-vet-button', function(e) {
            e.preventDefault();
            if (confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε τον κτηνίατρο;')) {
                $(this).closest('.vet-block').slideUp(200, function() { $(this).remove(); });
            }
        });
    });
    </script>
    
    <script>
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('saved') || url.searchParams.has('auto_ics')) {
                jQuery('#petling-toast').fadeIn(400).delay(3000).fadeOut(400);
                
                // Καθαρίζουμε το URL για να μην ξανακατέβει το ημερολόγιο σε περίπτωση Refresh
                url.searchParams.delete('saved');
                url.searchParams.delete('auto_ics');
                window.history.replaceState(null, '', url.href);
            }
        }
    </script>
    <?php
}