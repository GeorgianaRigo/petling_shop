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

add_action( 'template_redirect', 'petling_crm_handle_actions' );
function petling_crm_handle_actions() {
    global $wpdb;
    
    // --- ΠΡΟΣΘΗΚΗ / ΕΠΕΞΕΡΓΑΣΙΑ ΕΜΒΟΛΙΟΥ ---
    if ( isset( $_POST['petling_vaccine_nonce'] ) && wp_verify_nonce( $_POST['petling_vaccine_nonce'], 'petling_add_vaccine' ) ) {
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $v_name = sanitize_text_field($_POST['vaccine_name']);
        $next_date = sanitize_text_field($_POST['next_vaccine_date']);
        $vet_name = sanitize_text_field($_POST['vet_name'] ?? '');
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        
        $pets = get_user_meta(get_current_user_id(), 'petling_pets', true);
        $p_name = ''; if (is_array($pets)) { foreach ($pets as $p) { if ($p['id'] === $pet_id) { $p_name = $p['name']; break; } } }

        $parasite_options = array('Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)', 'Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)', 'Combo (Εσωτερικά & Εξωτερικά)');

        $generate_ics = false;
        
        if ($edit_id > 0) {
            $old_record = $wpdb->get_row( $wpdb->prepare("SELECT next_vaccine_date FROM {$wpdb->prefix}petling_vaccines WHERE id = %d", $edit_id) );
            $wpdb->update( $wpdb->prefix . 'petling_vaccines', array( 'vaccine_name' => $v_name, 'next_vaccine_date' => $next_date, 'vet_name' => $vet_name ), array('id' => $edit_id) );
            
            // Κατέβασμα Ημερολογίου ΜΟΝΟ αν άλλαξε η ημερομηνία
            if (!empty($next_date) && (!$old_record || $old_record->next_vaccine_date !== $next_date)) {
                $generate_ics = true;
            }
        } else {
            if (in_array($v_name, $parasite_options)) {
                $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s", $pet_id, $v_name) );
            }
            $wpdb->insert( $wpdb->prefix . 'petling_vaccines', array( 'pet_unique_id' => $pet_id, 'vaccine_name' => $v_name, 'date_administered' => date('Y-m-d'), 'next_vaccine_date' => $next_date, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'owner' ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            if (!empty($next_date)) $generate_ics = true;
        }
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'my-pets' ) );
        if ($generate_ics) {
            $type_text = in_array($v_name, $parasite_options) ? '🪱 Αποπαρασίτωση' : '💉 Εμβολιασμός';
            $ics_url = petling_crm_get_ics_link( $type_text . ': ' . $v_name . ' (' . $p_name . ')', $next_date );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }
    
    // --- ΠΡΟΣΘΗΚΗ / ΕΠΕΞΕΡΓΑΣΙΑ ΣΗΜΕΙΩΣΗΣ ---
    if ( isset( $_POST['petling_vet_note_nonce'] ) && wp_verify_nonce( $_POST['petling_vet_note_nonce'], 'petling_add_vet_note' ) ) {
        $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
        $next_exam = sanitize_text_field($_POST['next_exam_date']);
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $vet_comment = sanitize_textarea_field($_POST['vet_comment']);
        $vet_name = sanitize_text_field($_POST['vet_name']);
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        
        $pets = get_user_meta(get_current_user_id(), 'petling_pets', true);
        $p_name = ''; if (is_array($pets)) { foreach ($pets as $p) { if ($p['id'] === $pet_id) { $p_name = $p['name']; break; } } }

        $generate_ics = false;

        if ($edit_id > 0) {
            $old_record = $wpdb->get_row( $wpdb->prepare("SELECT next_exam_date FROM {$wpdb->prefix}petling_vet_notes WHERE id = %d", $edit_id) );
            $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'vet_comment' => $vet_comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'created_at' => $incident_date ), array('id' => $edit_id) );
            
            if (!empty($next_exam) && (!$old_record || $old_record->next_exam_date !== $next_exam)) {
                $generate_ics = true;
            }
        } else {
            $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $vet_comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'status' => 'draft', 'created_by' => 'owner', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            if (!empty($next_exam)) $generate_ics = true;
        }
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'my-pets' ) );
        if ($generate_ics) {
            $ics_url = petling_crm_get_ics_link( '🩺 Επανεξέταση (' . $p_name . ')', $next_exam );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }

    // --- ΠΡΟΣΘΗΚΗ / ΕΠΕΞΕΡΓΑΣΙΑ ΑΙΜΑΤΟΛΟΓΙΚΩΝ ---
    if ( isset( $_POST['petling_blood_nonce'] ) && wp_verify_nonce( $_POST['petling_blood_nonce'], 'petling_add_blood' ) ) {
        $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $next_exam = sanitize_text_field($_POST['next_exam_date'] ?? '');
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        
        $pets = get_user_meta(get_current_user_id(), 'petling_pets', true);
        $p_name = ''; if (is_array($pets)) { foreach ($pets as $p) { if ($p['id'] === $pet_id) { $p_name = $p['name']; break; } } }

        $comment = "[BLOOD]";
        if (!empty($_POST['blood_name']) && is_array($_POST['blood_name'])) {
            foreach ($_POST['blood_name'] as $idx => $b_name) {
                $b_val = $_POST['blood_value'][$idx] ?? '';
                $b_lim = $_POST['blood_limit'][$idx] ?? '';
                if (!empty($b_name) && !empty($b_val)) {
                    $line = "\n- **" . sanitize_text_field($b_name) . ":** " . sanitize_text_field($b_val);
                    if (!empty($b_lim)) { $line .= " (Όριο: " . sanitize_text_field($b_lim) . ")"; }
                    $comment .= $line;
                }
            }
        }

        $generate_ics = false;

        if ($edit_id > 0) {
            $old_record = $wpdb->get_row( $wpdb->prepare("SELECT next_exam_date FROM {$wpdb->prefix}petling_vet_notes WHERE id = %d", $edit_id) );
            $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'vet_comment' => $comment, 'vet_name' => '', 'next_exam_date' => $next_exam, 'created_at' => $incident_date ), array('id' => $edit_id) );
            
            if (!empty($next_exam) && (!$old_record || $old_record->next_exam_date !== $next_exam)) {
                $generate_ics = true;
            }
        } else {
            $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $comment, 'next_exam_date'=> $next_exam, 'vet_name' => '', 'status' => 'verified', 'created_by' => 'owner', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            if (!empty($next_exam)) $generate_ics = true;
        }
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id, 'saved' => '1' ), wc_get_endpoint_url( 'my-pets' ) );
        if ($generate_ics) {
            $ics_url = petling_crm_get_ics_link( '🩸 Επανεξέταση Τιμών / Αιματολογικών (' . $p_name . ')', $next_exam );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }
    
    // --- ΠΡΟΣΘΗΚΗ / ΕΠΕΞΕΡΓΑΣΙΑ ΖΥΓΙΣΗΣ ---
    if ( isset( $_POST['petling_weight_nonce'] ) && wp_verify_nonce( $_POST['petling_weight_nonce'], 'petling_log_weight' ) ) {
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $new_weight = sanitize_text_field($_POST['weight']);
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
        
        if ( !empty($new_weight) ) {
            if ($edit_id > 0) {
                $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'weight' => $new_weight, 'created_at' => $incident_date ), array('id' => $edit_id) );
            } else {
                $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'weight' => $new_weight, 'vet_comment' => 'Τακτική Ζύγιση (Κηδεμόνας)', 'status' => 'verified', 'created_by' => 'owner', 'created_at' => $incident_date ), array( '%s', '%f', '%s', '%s', '%s', '%s' ) );
            }
            $user_id = get_current_user_id();
            $pets_array = get_user_meta($user_id, 'petling_pets', true);
            if (is_array($pets_array)) {
                foreach ($pets_array as &$p) { if ($p['id'] === $pet_id) { $p['weight'] = $new_weight; break; } }
                update_user_meta($user_id, 'petling_pets', $pets_array);
            }
        }
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id, 'saved' => '1' ), wc_get_endpoint_url( 'my-pets' ) ) ); exit;
    }
    
    // --- ΔΙΑΓΡΑΦΕΣ ---
    if ( isset( $_GET['action'] ) ) {
        if ( $_GET['action'] === 'del_vac' && isset( $_GET['vac_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_vac_' . $_GET['vac_id'] ) ) {
            $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ) ) );
            wp_safe_redirect( remove_query_arg( array( 'action', 'vac_id', '_wpnonce', 'auto_ics' ) ) ); exit;
        }
        if ( $_GET['action'] === 'del_note' && isset( $_GET['note_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_note_' . $_GET['note_id'] ) ) {
            $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ) ) );
            wp_safe_redirect( remove_query_arg( array( 'action', 'note_id', '_wpnonce', 'auto_ics' ) ) ); exit;
        }
        if ( $_GET['action'] === 'del_weight' && isset( $_GET['weight_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_weight_' . $_GET['weight_id'] ) ) {
            $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['weight_id'] ) ) );
            wp_safe_redirect( remove_query_arg( array( 'action', 'weight_id', '_wpnonce', 'auto_ics' ) ) ); exit;
        }
    }
}

// =========================================================================
// 2. HTML VIEW (Η οθόνη του Πελάτη - Premium Εμφάνιση)
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

    // -------------------------------------------------------------
    // ΕΝΟΠΟΙΗΜΕΝΟ TIMELINE & UPCOMING LOGIC
    // -------------------------------------------------------------
    $timeline = [];
    $upcoming_actions = [];
    $chart_labels = [];
    $chart_data = [];
    $today_obj = new DateTime('today');

    // Εμβόλια & Αποπαρασιτώσεις
    foreach ($all_records as $rec) {
        $is_parasite = in_array($rec->vaccine_name, $parasite_options);
        $type = $is_parasite ? 'parasite' : 'vaccine';
        $icon = $is_parasite ? '🪱' : '💉';
        
        $timeline[] = [
            'type' => $type, 'icon' => $icon, 'date' => $rec->date_administered, 'title' => $rec->vaccine_name,
            'desc' => $rec->vet_name ? 'Σκεύασμα/Ιατρός: ' . $rec->vet_name : '',
            'next' => $rec->next_vaccine_date, 'id' => $rec->id, 'delete_action' => 'del_vac',
            'raw_comment' => '', 'raw_vet' => $rec->vet_name, 'raw_weight' => ''
        ];

        if (!empty($rec->next_vaccine_date) && $rec->next_vaccine_date !== '0000-00-00') {
            $next_obj = new DateTime($rec->next_vaccine_date);
            $diff = $today_obj->diff($next_obj)->days;
            $invert = $today_obj->diff($next_obj)->invert; 
            
            if ($invert) {
                $upcoming_actions[] = ['status' => 'red', 'msg' => 'Έληξε: ' . $rec->vaccine_name];
            } elseif ($diff <= 30) {
                $upcoming_actions[] = ['status' => 'orange', 'msg' => 'Σε '.$diff.' μέρες: ' . $rec->vaccine_name];
            }
        }
    }

    // Σημειώσεις, Αιματολογικές & Βάρος
    $weights_for_chart = [];
    foreach ($vet_notes as $note) {
        if (!empty($note->weight)) {
            $timeline[] = [
                'type' => 'weight', 'icon' => '⚖️', 'date' => $note->created_at, 'title' => 'Ζύγιση: ' . $note->weight . ' kg',
                'desc' => '', 'next' => '', 'id' => $note->id, 'delete_action' => 'del_weight',
                'raw_comment' => '', 'raw_vet' => '', 'raw_weight' => $note->weight
            ];
            $weights_for_chart[] = ['date' => $note->created_at, 'weight' => $note->weight];
        } else {
            // Ανίχνευση Αιματολογικών (χάρη στο [BLOOD] tag)
            if (strpos($note->vet_comment, '[BLOOD]') !== false) {
                $desc = str_replace('[BLOOD]', '', $note->vet_comment);
                $html_desc = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $desc);
                
                $timeline[] = [
                    'type' => 'blood', 'icon' => '🩸', 'date' => $note->created_at, 'title' => 'Αιματολογικές / Εξετάσεις',
                    'desc' => nl2br(trim($html_desc)),
                    'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note',
                    'raw_comment' => $note->vet_comment, 'raw_vet' => '', 'raw_weight' => ''
                ];
            } else {
                $timeline[] = [
                    'type' => 'note', 'icon' => '🩺', 'date' => $note->created_at, 'title' => 'Ιατρική Σημείωση / Επίσκεψη',
                    'desc' => nl2br(esc_html($note->vet_comment)) . ($note->vet_name ? '<br><small style="color:#888;">Ιατρός: ' . esc_html($note->vet_name) . '</small>' : ''),
                    'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note',
                    'raw_comment' => $note->vet_comment, 'raw_vet' => $note->vet_name, 'raw_weight' => ''
                ];
            }
            
            // Ειδοποιήσεις Επανεξέτασης (Κοινές για Σημείωση και Αιματολογικές)
            if (!empty($note->next_exam_date) && $note->next_exam_date !== '0000-00-00') {
                $next_obj = new DateTime($note->next_exam_date);
                $diff = $today_obj->diff($next_obj)->days;
                $invert = $today_obj->diff($next_obj)->invert;
                if ($invert) {
                    $upcoming_actions[] = ['status' => 'red', 'msg' => 'Επανεξέταση που πέρασε!'];
                } elseif ($diff <= 30) {
                    $upcoming_actions[] = ['status' => 'orange', 'msg' => 'Επανεξέταση σε '.$diff.' μέρες'];
                }
            }
        }
    }

    usort($timeline, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
    
    usort($weights_for_chart, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });
    foreach ($weights_for_chart as $w) {
        $chart_labels[] = date('d/m/y', strtotime($w['date']));
        $chart_data[] = $w['weight'];
    }

    $min_future_date = date('Y-m-d'); 
    $age_str = '';
    if ( !empty($pet['birthday']) ) {
        try {
            $bday = new DateTime($pet['birthday']);
            $diff = $bday->diff($today_obj);
            if ( $diff->y > 0 ) { $age_str = $diff->y . ' ετών'; } elseif ( $diff->m > 0 ) { $age_str = $diff->m . ' μηνών'; } else { $age_str = 'Νεογέννητο'; }
        } catch (Exception $e) {}
    }
    $neutered_text = (isset($pet['neutered']) && $pet['neutered'] === 'yes') ? (!empty($pet['neutered_date']) ? date('d/m/Y', strtotime($pet['neutered_date'])) : 'Ναι (Άγνωστη Ημ/νία)') : '-';

    echo '<a href="' . esc_url( remove_query_arg( array( 'view', 'pet_id', 'saved', 'auto_ics' ) ) ) . '" class="no-loader" style="display:inline-block; margin-bottom:20px; font-weight:bold; color:#8B6139; text-decoration:none;">&larr; Πίσω στα Κατοικίδιά μου</a>';
    if (isset($_GET['auto_ics'])) { echo '<iframe src="'.esc_url(urldecode($_GET['auto_ics'])).'" style="display:none;"></iframe>'; }
    ?>
    
    <!-- CHART.JS LIBRARY -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Accordion Arrows & General */
        details.petling-accordion summary { position: relative; padding-right: 40px; list-style: none; }
        details.petling-accordion summary::-webkit-details-marker { display: none; }
        details.petling-accordion summary::after { content: '▼'; position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 13px; color: #C7B297; transition: transform 0.3s ease; }
        details.petling-accordion[open] summary::after { transform: translateY(-50%) rotate(180deg); }
        details#vet-pass-block summary { background: transparent !important; color: #fff !important; }
        details#vet-pass-block summary::after { color: #fff !important; }
        
        /* Filter Chips */
        .petling-filters { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 20px; }
        .petling-chip { background: #fdfdfd; border: 1px solid #ddd; padding: 8px 16px; border-radius: 20px; font-size: 14px; cursor: pointer; white-space: nowrap; color: #555; transition: all 0.2s; }
        .petling-chip.active { background: #43282F; color: #fff; border-color: #43282F; font-weight: bold; }
        
        /* Unified Timeline */
        .petling-timeline { position: relative; padding-left: 30px; margin-bottom: 40px; }
        .petling-timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e2d4c0; }
        .timeline-item { position: relative; margin-bottom: 25px; padding: 15px; background: #fff; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .timeline-icon { position: absolute; left: -35px; top: 15px; width: 30px; height: 30px; background: #fff; border: 2px solid #C7B297; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 2; }
        .timeline-date { font-size: 12px; color: #888; margin-bottom: 5px; font-weight: bold; }
        .timeline-title { font-size: 16px; color: #43282F; margin: 0 0 5px 0; }
        .timeline-desc { font-size: 14px; color: #555; margin: 0; line-height: 1.5; }
        .timeline-next { margin-top: 10px; font-size: 13px; color: #d63638; font-weight: bold; background: #fff5f5; display: inline-block; padding: 4px 8px; border-radius: 4px; }
        
        /* Floating Action Button (FAB) & Modals */
        .petling-fab { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background: #43282F; color: #fff; border-radius: 50%; font-size: 30px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer; z-index: 999; transition: transform 0.2s; border: none; }
        .petling-fab:hover { transform: scale(1.05); }
        
        .petling-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; }
        .petling-modal { background: #fff; padding: 25px; border-radius: 12px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; position: relative; }
        .petling-modal-close { position: absolute; top: 15px; right: 15px; font-size: 24px; cursor: pointer; color: #999; line-height: 1; border: none; background: none; }
        
        .petling-fab-menu { position: fixed; bottom: 100px; right: 30px; display: flex; flex-direction: column; gap: 10px; z-index: 998; opacity: 0; pointer-events: none; transition: all 0.3s ease; transform: translateY(20px); }
        .petling-fab-menu.active { opacity: 1; pointer-events: auto; transform: translateY(0); }
        .fab-btn { background: #fff; border: 1px solid #ddd; padding: 12px 20px; border-radius: 30px; font-weight: bold; color: #43282F; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: pointer; text-align: right; }
        
        .dashboard-alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 6px; margin-bottom: 25px; }
        .dashboard-alert.red { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        
        /* Χρονιές στο Timeline - Διορθωμένο Κενό */
        .timeline-year-divider { background: #f3f4f6; color: #888; font-weight: bold; text-align: center; padding: 5px 15px; border-radius: 20px; display: inline-block; margin-bottom: 20px; border: 1px solid #ddd; position: relative; z-index: 2; margin-left: -5px; }
        
        /* Φόρμες μέσα στα Modals */
        .petling-modal label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 14px; }
        .petling-modal input, .petling-modal select, .petling-modal textarea { width: 100%; box-sizing: border-box; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; }
    </style>
    
    <div class="petling-loader-overlay" id="petling-global-loader" style="display: none;">
        <div class="petling-spinner"></div>
    </div>
    <div class="petling-toast-notification" id="petling-toast" style="display: none;">✅ Η αλλαγή αποθηκεύτηκε!</div>

    <!-- 1. DASHBOARD ΤΩΝ ΕΠΟΜΕΝΩΝ -->
    <?php if (!empty($upcoming_actions)) : ?>
        <div class="dashboard-alert <?php echo $upcoming_actions[0]['status'] === 'red' ? 'red' : ''; ?>">
            <strong style="display:block; margin-bottom:8px;">🔔 Εκκρεμότητες / Επερχόμενα:</strong>
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($upcoming_actions as $act) : ?>
                    <li><?php echo esc_html($act['msg']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- 2. VET PASS -->
    <details id="vet-pass-block" class="petling-accordion" style="background: linear-gradient(135deg, #43282F 0%, #2a191d 100%); border-radius: 12px; padding: 15px 20px; margin-bottom: 30px; color: #fff; box-shadow: 0 5px 15px rgba(67, 40, 47, 0.2);">
        <summary style="font-size: 18px; font-weight: bold; cursor: pointer; outline: none; padding: 5px 0 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">🐾 Εμφάνιση ψηφιακού Vet Pass</summary>
        <div class="qr-flex-container" style="margin-top: 15px; display: flex; align-items: center; justify-content: space-between;">
            <div style="flex: 1; text-align: left;">
                <h3 style="margin: 0; color: #fff; font-size: 20px;"><?php echo ($pet['type'] === 'dog' ? '🐶' : '🐱') . ' ' . esc_html($pet['name']); ?> <?php if (!empty($age_str)) echo '<span style="font-size: 15px; color: #e0e0e0; font-weight: normal;">(' . $age_str . ')</span>'; ?></h3>
                <p style="margin: 5px 0 0 0; color: #5b9a68; font-size: 13px; font-weight: bold;">Στειρωμένο: <span style="color:#eef7ee; font-weight:normal;"><?php echo $neutered_text; ?></span></p>
                <?php if(!empty($pet['microchip'])): ?>
                    <div style="margin-top: 12px; background: rgba(255,255,255,0.1); padding: 8px 10px; border-radius: 6px; display: inline-block;">
                        <span style="color: #C7B297; font-size: 10px; display:block;">MICROCHIP</span>
                        <span style="color: #fff; font-weight:bold; font-size: 13px;"><?php echo esc_html($pet['microchip']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div style="background: #fff; padding: 6px; border-radius: 8px; text-align: center;"><img src="<?php echo esc_url($qr_code_url); ?>" alt="Vet Pass QR Code" style="width: 85px; height: 85px; display: block;"></div>
        </div>
    </details>

    <!-- 3. ΟΙ ΚΤΗΝΙΑΤΡΟΙ ΜΟΥ -->
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
                            <div class="vet-edit-mode" style="background: #fcfcfc; border: 1px dashed #C7B297; padding: 15px; border-radius: 8px; margin-bottom: 15px; display:none;">
                                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2d4c0; padding-bottom:10px; margin-bottom:15px;">
                                    <h4 style="margin:0; font-size:16px; color:#43282F;">Επεξεργασία Ιατρείου</h4>
                                    <button type="button" class="remove-vet-button" style="background:none; border:none; color:#e62121; font-weight:bold; font-size:14px; cursor:pointer;">🗑️ Διαγραφή</button>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                    <div><label>Όνομα / Επωνυμία *</label><input type="text" name="vet_name[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['name']); ?>" style="width:100%; box-sizing:border-box;" required></div>
                                    <div><label>Email</label><input type="email" name="vet_email[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['email'] ?? ''); ?>" style="width:100%; box-sizing:border-box;"></div>
                                    <div><label>Διεύθυνση / Google Maps</label><input type="text" name="vet_address[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['address']); ?>" style="width:100%; box-sizing:border-box;"></div>
                                </div>
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

    <hr style="border:0; border-top: 1px solid #eee; margin: 30px 0;">

    <!-- 4. FILTER CHIPS -->
    <div class="petling-filters">
        <div class="petling-chip active" data-filter="all">🐾 Όλα</div>
        <div class="petling-chip" data-filter="weight">⚖️ Βάρος</div>
        <div class="petling-chip" data-filter="vaccine">💉 Εμβόλια</div>
        <div class="petling-chip" data-filter="parasite">🪱 Αποπαρασίτωση</div>
        <div class="petling-chip" data-filter="blood">🩸 Εξετάσεις</div>
        <div class="petling-chip" data-filter="note">🩺 Σημειώσεις</div>
    </div>

    <!-- ΓΡΑΦΗΜΑ ΒΑΡΟΥΣ (Αρχικά Κρυμμένο) -->
    <div id="weight-chart-container" style="display: none; background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 12px; margin-bottom: 25px;">
        <canvas id="weightChart" height="200"></canvas>
    </div>

    <!-- 5. UNIFIED TIMELINE -->
    <?php if (empty($timeline)): ?>
        <p style="color:#888; font-style:italic; text-align:center; padding:30px;">Δεν υπάρχουν ακόμα εγγραφές. Πατήστε το <b>+</b> κάτω δεξιά για να ξεκινήσετε!</p>
    <?php else: ?>
        <div class="petling-timeline">
            <?php 
            $current_year = '';
            foreach ($timeline as $item): 
                $item_year = date('Y', strtotime($item['date']));
                if ($item_year !== $current_year) {
                    $current_year = $item_year;
                    echo '<div class="timeline-year-divider">' . $current_year . '</div>';
                }
            ?>
                <div class="timeline-item" data-type="<?php echo esc_attr($item['type']); ?>" 
                     data-id="<?php echo esc_attr($item['id']); ?>"
                     data-raw-date="<?php echo date('Y-m-d', strtotime($item['date'])); ?>"
                     data-raw-next="<?php echo esc_attr($item['next']); ?>"
                     data-vet="<?php echo esc_attr($item['raw_vet']); ?>"
                     data-weight="<?php echo esc_attr($item['raw_weight']); ?>"
                     data-title="<?php echo esc_attr($item['title']); ?>">
                     
                    <!-- Hidden element to store raw comment for JS Edit -->
                    <div class="raw-comment" style="display:none;"><?php echo esc_html($item['raw_comment']); ?></div>
                    
                    <div class="timeline-icon"><?php echo $item['icon']; ?></div>
                    <div class="timeline-date"><?php echo date('d/m/Y', strtotime($item['date'])); ?></div>
                    <h4 class="timeline-title"><?php echo esc_html($item['title']); ?></h4>
                    <?php if (!empty($item['desc'])): ?>
                        <p class="timeline-desc"><?php echo $item['desc']; ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($item['next']) && $item['next'] !== '0000-00-00'): ?>
                        <div class="timeline-next">📅 Επόμενο: <?php echo date('d/m/Y', strtotime($item['next'])); ?></div>
                    <?php endif; ?>
                    
                    <div style="position:absolute; top:10px; right:15px; display:flex; gap:10px; align-items:center;">
                        <a href="#" class="copy-timeline-btn" style="color:#43282F; text-decoration:none; font-size:16px;" title="Αντιγραφή">📋</a>
                        <a href="#" class="edit-timeline-btn" style="color:#5b9a68; text-decoration:none; font-size:16px;" title="Επεξεργασία">✏️</a>
                        <?php $del_url = wp_nonce_url( add_query_arg( ['action' => $item['delete_action'], $item['delete_action'] == 'del_weight' ? 'weight_id' : ($item['delete_action'] == 'del_vac' ? 'vac_id' : 'note_id') => $item['id']] ), $item['delete_action'] . '_' . $item['id'] ); ?>
                        <a href="<?php echo esc_url($del_url); ?>" class="no-loader" onclick="return confirm('Είστε σίγουροι; Η εγγραφή θα διαγραφεί οριστικά.');" style="color:#e62121; text-decoration:none; font-size:20px; line-height:0.9;" title="Διαγραφή">&times;</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- 6. FLOATING ACTION BUTTON (FAB) & MENU -->
    <div class="petling-fab-menu" id="fab-menu">
        <button class="fab-btn" onclick="openModal('modal-weight')">⚖️ Νέα Ζύγιση</button>
        <button class="fab-btn" onclick="openModal('modal-vaccine')">💉 Νέο Εμβόλιο / Αποπαρασίτωση</button>
        <button class="fab-btn" onclick="openModal('modal-blood')">🩸 Νέες Αιματολογικές (SOS)</button>
        <button class="fab-btn" onclick="openModal('modal-note')">🩺 Νέα Ιατρική Σημείωση</button>
    </div>
    <button class="petling-fab" id="main-fab">+</button>

    <!-- MODALS (Φόρμες) -->
    
    <!-- Modal: Ζύγιση -->
    <div class="petling-modal-overlay" id="modal-weight">
        <div class="petling-modal">
            <button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">⚖️ Προσθήκη Βάρους</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_log_weight', 'petling_weight_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Ημερομηνία *</label>
                <input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;">
                
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Βάρος (kg) *</label>
                <input type="number" step="0.01" min="0" name="weight" required style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #ccc; border-radius:6px;">
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση</button>
            </form>
        </div>
    </div>

    <!-- Modal: Εμβόλιο / Αποπαρασίτωση -->
    <div class="petling-modal-overlay" id="modal-vaccine">
        <div class="petling-modal">
            <button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">💉 Υπενθύμιση (Εμβόλιο/Αποπαρασίτωση)</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_add_vaccine', 'petling_vaccine_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Τύπος / Εμβόλιο *</label>
                <select name="vaccine_name" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;">
                    <optgroup label="Αποπαρασίτωση">
                        <?php foreach($parasite_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?>
                    </optgroup>
                    <optgroup label="Εμβολιασμός">
                        <?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?>
                    </optgroup>
                </select>
                
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Σκεύασμα (Προαιρετικό)</label>
                <input type="text" name="vet_name" placeholder="π.χ. Bravecto" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;">
                
                <label style="display:block; margin-bottom:5px; font-weight:bold; color:#d63638;">Επόμενη Δόση 📅 *</label>
                <input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>" required style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #ccc; border-radius:6px;">
                
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση & Ημερολόγιο</button>
            </form>
        </div>
    </div>

    <!-- Modal: Αιματολογικές -->
    <div class="petling-modal-overlay" id="modal-blood">
        <div class="petling-modal">
            <button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">🩸 Αιματολογικές (Δείκτες)</h3>
            <p style="font-size:13px; color:#666;">Καταγράψτε μόνο τις τιμές που βρίσκονται εκτός των φυσιολογικών ορίων (αυξημένες/μειωμένες) για γρήγορη αναφορά.</p>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_add_blood', 'petling_blood_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Ημερομηνία Εξέτασης *</label>
                    <input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:10px; margin-bottom:0; border:1px solid #ccc; border-radius:6px;">
                </div>
                
                <label style="margin-bottom:10px; display:block; font-weight:bold;">Δείκτες εκτός ορίων:</label>
                
                <!-- Επικεφαλίδες Πεδίων -->
                <div style="display:flex; gap:10px; margin-bottom:5px; font-weight:bold; font-size:13px; color:#555; padding-right: 34px;">
                    <div style="flex:2.5;">Εξέταση</div>
                    <div style="flex:1;">Αποτέλεσμα</div>
                    <div style="flex:1;">Τιμές Αναφοράς</div>
                </div>

                <div id="blood-markers-container">
                    <!-- Το JS θα βάζει τα πεδία εδώ -->
                </div>
                <button type="button" id="add-blood-marker" style="margin-bottom:15px; margin-top: 5px; background:none; border:1px dashed #C7B297; color:#43282F; padding:8px; width:100%; border-radius:6px; font-weight:bold; cursor:pointer;">+ Προσθήκη Δείκτη</button>
                
                <label style="display:block; margin-bottom:5px; font-weight:bold; color:#d63638;">Επανεξέταση / Επανάληψη 📅 (Προαιρετικό)</label>
                <input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>" style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #ccc; border-radius:6px;">
                
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση</button>
            </form>
        </div>
    </div>

    <!-- Modal: Ιατρική Σημείωση -->
    <div class="petling-modal-overlay" id="modal-note">
        <div class="petling-modal">
            <button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">🩺 Ιατρική Σημείωση</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_add_vet_note', 'petling_vet_note_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Ημερομηνία Συμβάντος *</label>
                <input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;">
                
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Ιατρός (Προαιρετικό)</label>
                <input type="text" name="vet_name" placeholder="π.χ. Δρ. Παπαδόπουλος" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;">
                
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Πάθηση, Αγωγή & Σχόλια *</label>
                <textarea name="vet_comment" required rows="3" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;"></textarea>
                
                <label style="display:block; margin-bottom:5px; font-weight:bold; color:#d63638;">Επανεξέταση 📅 (Προαιρετικό)</label>
                <input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>" style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #ccc; border-radius:6px;">
                
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση</button>
            </form>
        </div>
    </div>
    
    <!-- TEMPLATE ΓΙΑ ΤΟΥΣ ΚΤΗΝΙΑΤΡΟΥΣ -->
    <div id="vet-block-template" style="display:none;">
        <div class="vet-edit-mode" style="background: #fcfcfc; border: 1px dashed #C7B297; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2d4c0; padding-bottom:10px; margin-bottom:15px;">
                <h4 style="margin:0; font-size:16px; color:#43282F;">Νέο Ιατρείο</h4>
                <button type="button" class="remove-vet-button" style="background:none; border:none; color:#e62121; font-weight:bold; font-size:14px; cursor:pointer;">🗑️ Διαγραφή</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div><label>Όνομα / Επωνυμία *</label><input type="text" name="vet_name[__VINDEX__]" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccc; border-radius:6px;" required></div>
                <div><label>Email</label><input type="email" name="vet_email[__VINDEX__]" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccc; border-radius:6px;"></div>
                <div><label>Διεύθυνση / Google Maps</label><input type="text" name="vet_address[__VINDEX__]" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccc; border-radius:6px;"></div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div><label>Τηλέφωνο ιατρείου *</label><input type="tel" pattern="[0-9\s\+]*" name="vet_phone[__VINDEX__]" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccc; border-radius:6px;" required></div>
                <div><label>Κινητό SOS 1</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos[__VINDEX__]" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccc; border-radius:6px;"></div>
                <div><label>Κινητό SOS 2</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos2[__VINDEX__]" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccc; border-radius:6px;"></div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        
        // --- VETS LOGIC ---
        var nextVetIndex = $('.vet-block').length;
        $(document).off('click', '.edit-vet-btn').on('click', '.edit-vet-btn', function(e) {
            e.preventDefault();
            $(this).closest('.vet-block').find('.vet-view-mode').hide();
            $(this).closest('.vet-block').find('.vet-edit-mode').fadeIn();
        });
        $(document).off('click', '#add-vet-button').on('click', '#add-vet-button', function(e) {
            e.preventDefault();
            if ( $('.vet-block').length >= 3 ) { alert('Μπορείτε να προσθέσετε μέχρι 3 κτηνιάτρους.'); return; }
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

        // --- BLOOD TEST REPEATER LOGIC ---
        $('#add-blood-marker').click(function(e) {
            e.preventDefault();
            $('#blood-markers-container').append('<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;"><input type="text" name="blood_name[]" placeholder="Εξέταση" required style="flex:2.5; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;"><input type="text" name="blood_value[]" placeholder="Αποτέλεσμα" required style="flex:1; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;"><input type="text" name="blood_limit[]" placeholder="Όρια" style="flex:1; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;"><button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>');
        });
        $(document).on('click', '.remove-blood-btn', function(e) {
            e.preventDefault();
            $(this).closest('.blood-marker-row').remove();
        });

        // --- SMART EDIT BUTTON LOGIC ---
        $('.edit-timeline-btn').click(function(e) {
            e.preventDefault();
            var $item = $(this).closest('.timeline-item');
            var type = $item.data('type');
            
            var modalId = '';
            if (type === 'weight') {
                modalId = 'modal-weight';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Βάρους');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id'));
                $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date'));
                $('#' + modalId + ' input[name="weight"]').val($item.data('weight'));
            } else if (type === 'vaccine' || type === 'parasite') {
                modalId = 'modal-vaccine';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Υπενθύμισης');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id'));
                $('#' + modalId + ' select[name="vaccine_name"]').val($item.data('title'));
                $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                $('#' + modalId + ' input[name="next_vaccine_date"]').val($item.data('raw-next'));
            } else if (type === 'note') {
                modalId = 'modal-note';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Σημείωσης');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id'));
                $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date'));
                $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                $('#' + modalId + ' textarea[name="vet_comment"]').val($item.find('.raw-comment').text());
                $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next'));
            } else if (type === 'blood') {
                modalId = 'modal-blood';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Αιματολογικών');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id'));
                $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date'));
                $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next'));
                
                $('#blood-markers-container').empty();
                var comment = $item.find('.raw-comment').text();
                var lines = comment.split('\n');
                var found = false;
                lines.forEach(function(line) {
                    if (line.indexOf('- **') === 0) {
                        var match = line.match(/- \*\*(.*?):\*\* (.*?)(?: \(Όριο: (.*?)\))?$/);
                        if (match) {
                            found = true;
                            var row = '<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">';
                            row += '<input type="text" name="blood_name[]" value="'+match[1]+'" required style="flex:2.5; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;">';
                            row += '<input type="text" name="blood_value[]" value="'+match[2]+'" required style="flex:1; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;">';
                            row += '<input type="text" name="blood_limit[]" value="'+(match[3] || '')+'" style="flex:1; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;">';
                            row += '<button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>';
                            $('#blood-markers-container').append(row);
                        }
                    }
                });
                if(!found) $('#add-blood-marker').click();
            }
            
            if (modalId) {
                document.getElementById('fab-menu').classList.remove('active');
                document.getElementById('main-fab').style.transform = 'rotate(0)';
                document.getElementById(modalId).style.display = 'flex';
            }
        });

        // --- SMART COPY BUTTON LOGIC ---
        $('.copy-timeline-btn').click(function(e) {
            e.preventDefault();
            var $item = $(this).closest('.timeline-item');
            var type = $item.data('type');
            
            var modalId = '';
            if (type === 'weight') {
                modalId = 'modal-weight';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Βάρους');
                $('#' + modalId + ' input[name="edit_id"]').val(''); 
                $('#' + modalId + ' input[name="incident_date"]').val(''); // Αφήνεται κενό για ασφάλεια
                $('#' + modalId + ' input[name="weight"]').val($item.data('weight'));
            } else if (type === 'vaccine' || type === 'parasite') {
                modalId = 'modal-vaccine';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Υπενθύμισης');
                $('#' + modalId + ' input[name="edit_id"]').val('');
                $('#' + modalId + ' select[name="vaccine_name"]').val($item.data('title'));
                $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                $('#' + modalId + ' input[name="next_vaccine_date"]').val(''); 
            } else if (type === 'note') {
                modalId = 'modal-note';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Σημείωσης');
                $('#' + modalId + ' input[name="edit_id"]').val('');
                $('#' + modalId + ' input[name="incident_date"]').val(''); 
                $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                $('#' + modalId + ' textarea[name="vet_comment"]').val($item.find('.raw-comment').text());
                $('#' + modalId + ' input[name="next_exam_date"]').val(''); 
            } else if (type === 'blood') {
                modalId = 'modal-blood';
                $('#' + modalId + ' form')[0].reset();
                $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Αιματολογικών');
                $('#' + modalId + ' input[name="edit_id"]').val('');
                $('#' + modalId + ' input[name="incident_date"]').val(''); 
                $('#' + modalId + ' input[name="next_exam_date"]').val(''); 
                
                $('#blood-markers-container').empty();
                var comment = $item.find('.raw-comment').text();
                var lines = comment.split('\n');
                var found = false;
                lines.forEach(function(line) {
                    if (line.indexOf('- **') === 0) {
                        var match = line.match(/- \*\*(.*?):\*\* (.*?)(?: \(Όριο: (.*?)\))?$/);
                        if (match) {
                            found = true;
                            var row = '<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">';
                            row += '<input type="text" name="blood_name[]" value="'+match[1]+'" required style="flex:2.5; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;">';
                            row += '<input type="text" name="blood_value[]" value="'+match[2]+'" required style="flex:1; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;">';
                            row += '<input type="text" name="blood_limit[]" value="'+(match[3] || '')+'" style="flex:1; margin-bottom:0; padding:10px; border:1px solid #ccc; border-radius:6px;">';
                            row += '<button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>';
                            $('#blood-markers-container').append(row);
                        }
                    }
                });
                if(!found) $('#add-blood-marker').click();
            }
            
            if (modalId) {
                document.getElementById('fab-menu').classList.remove('active');
                document.getElementById('main-fab').style.transform = 'rotate(0)';
                document.getElementById(modalId).style.display = 'flex';
            }
        });

        // --- FILTER CHIPS LOGIC ---
        $('.petling-chip').click(function() {
            $('.petling-chip').removeClass('active');
            $(this).addClass('active');
            var filter = $(this).data('filter');
            
            if (filter === 'weight') {
                $('#weight-chart-container').fadeIn();
                setTimeout(initChart, 100);
            } else {
                $('#weight-chart-container').hide();
            }
            
            if (filter === 'all') {
                $('.timeline-item').fadeIn();
                $('.timeline-year-divider').show();
            } else {
                $('.timeline-year-divider').hide();
                $('.timeline-item').hide();
                $('.timeline-item[data-type="' + filter + '"]').fadeIn();
            }
        });

        // --- CHART.JS LOGIC ---
        var weightChart = null;
        function initChart() {
            if (weightChart) return; 
            var ctx = document.getElementById('weightChart').getContext('2d');
            var labels = <?php echo json_encode($chart_labels); ?>;
            var data = <?php echo json_encode($chart_data); ?>;
            
            if(labels.length === 0) {
                $('#weight-chart-container').html('<p style="text-align:center; color:#888;">Δεν υπάρχουν αρκετά δεδομένα για το γράφημα.</p>');
                return;
            }
            
            weightChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Βάρος (kg)',
                        data: data,
                        borderColor: '#43282F',
                        backgroundColor: 'rgba(199, 178, 151, 0.3)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#C7B297',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: false } }
                }
            });
        }

        // --- FAB MENU LOGIC ---
        $('#main-fab').click(function() {
            $('#fab-menu').toggleClass('active');
            $(this).css('transform', $('#fab-menu').hasClass('active') ? 'rotate(45deg)' : 'rotate(0)');
        });
        
    });

    // --- MODALS FUNCTIONS (Για νέες καταχωρήσεις) ---
    function openModal(id) {
        document.getElementById('fab-menu').classList.remove('active');
        document.getElementById('main-fab').style.transform = 'rotate(0)';
        
        var form = document.querySelector('#' + id + ' form');
        if (form) {
            form.reset();
            var editIdField = form.querySelector('input[name="edit_id"]');
            if (editIdField) editIdField.value = '';
            
            // Επαναφορά δυναμικών τίτλων σε "Νέα εγγραφή"
            if (id === 'modal-weight') jQuery('#' + id + ' .modal-title-dynamic').text('⚖️ Προσθήκη Βάρους');
            if (id === 'modal-vaccine') jQuery('#' + id + ' .modal-title-dynamic').text('💉 Νέα Υπενθύμιση (Εμβόλιο/Αποπαρασίτωση)');
            if (id === 'modal-note') jQuery('#' + id + ' .modal-title-dynamic').text('🩺 Νέα Ιατρική Σημείωση');
            if (id === 'modal-blood') {
                jQuery('#' + id + ' .modal-title-dynamic').text('🩸 Νέες Αιματολογικές (Δείκτες)');
                jQuery('#blood-markers-container').empty();
                jQuery('#add-blood-marker').click();
            }
        }
        
        document.getElementById(id).style.display = 'flex';
    }
    
    function closeModals() {
        var modals = document.getElementsByClassName('petling-modal-overlay');
        for (var i = 0; i < modals.length; i++) { modals[i].style.display = 'none'; }
    }
    </script>
    
    <script>
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('saved') || url.searchParams.has('auto_ics')) {
                jQuery('#petling-toast').fadeIn(400).delay(3000).fadeOut(400);
                url.searchParams.delete('saved');
                url.searchParams.delete('auto_ics');
                window.history.replaceState(null, '', url.href);
            }
        }
    </script>
    <?php
}