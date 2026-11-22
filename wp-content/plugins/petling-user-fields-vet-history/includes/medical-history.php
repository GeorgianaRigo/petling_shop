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
        $uid = isset($_GET['uid']) ? sanitize_text_field($_GET['uid']) : uniqid() . '@petling.gr';
        
        if ( empty($date) ) return;
        $start = date('Ymd', strtotime($date));
        $end = date('Ymd', strtotime($date . ' +1 day'));
        $stamp = date('Ymd\THis\Z');
        
        $ics_content = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Petling CRM//NONSGML v1.0//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:" . $uid . "\r\nDTSTAMP:" . $stamp . "\r\nDTSTART;VALUE=DATE:" . $start . "\r\nDTEND;VALUE=DATE:" . $end . "\r\nSUMMARY:" . $title . "\r\nDESCRIPTION:" . str_replace("\n", "\\n", $desc) . "\r\nBEGIN:VALARM\r\nACTION:DISPLAY\r\nDESCRIPTION:Υπενθύμιση Petling\r\nTRIGGER:-P1W\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="petling-reminder.ics"');
        echo $ics_content;
        exit;
    }
}

function petling_crm_get_ics_link( $title, $date, $pet_id, $record_id, $type, $extra_context = '' ) {
    if ( empty( $date ) || $date === '0000-00-00' || strtotime($date) <= 0 ) return '';
    $user_id = get_current_user_id();
    $saved_vets = get_user_meta($user_id, 'petling_vets', true);
    $vet_contact_str = '';
    if (is_array($saved_vets) && !empty($saved_vets[0]['name'])) {
        $vet_contact_str = $saved_vets[0]['name'];
        if (!empty($saved_vets[0]['phone'])) { $vet_contact_str .= ' στο ' . $saved_vets[0]['phone']; }
    }
    
    $deep_link = add_query_arg( ['view' => 'medical', 'pet_id' => $pet_id], wc_get_endpoint_url( 'my-pets', '', wc_get_page_permalink( 'myaccount' ) ) );
    
    if ($type === 'meds') {
        $details = "Υπενθύμιση από το Petling:\nΤο κατοικίδιό σας ολοκλήρωσε την αγωγή για: " . $extra_context . ".\nΚαλέστε τον κτηνίατρο " . ($vet_contact_str ? $vet_contact_str : 'σας') . " για ραντεβού / επανεξέταση.\n\nΕνημέρωση Ιστορικού:\nΓια να δηλώσετε την επανεξέταση ως ολοκληρωμένη, πατήστε το παρακάτω link:\n" . $deep_link;
    } else {
        $details = "Υπενθύμιση από το Petling: Καλέστε τον κτηνίατρο " . ($vet_contact_str ? $vet_contact_str : 'σας') . ".\n\nΕνημέρωση Ιστορικού:\nΓια να δηλώσετε την εξέταση ως ολοκληρωμένη ή να την αναβάλετε, πατήστε το παρακάτω link για να μεταφερθείτε στην καρτέλα του κατοικιδίου σας:\n" . $deep_link;
    }
    
    $uid = md5( $pet_id . '_' . $type . '_' . $record_id ) . '@petling.gr';
    return add_query_arg( [ 'petling_ics' => '1', 'title' => urlencode($title), 'date' => $date, 'desc' => urlencode($details), 'uid' => $uid ], site_url() );
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
        wp_safe_redirect( add_query_arg('saved', '1', wp_get_referer()) ); exit;
    }
}

add_action( 'template_redirect', 'petling_crm_handle_actions' );
function petling_crm_handle_actions() {
    global $wpdb;
    
    // --- ΕΜΒΟΛΙΑ ---
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
            $record_id = $edit_id;
            if (!empty($next_date) && (!$old_record || $old_record->next_vaccine_date !== $next_date)) { $generate_ics = true; }
        } else {
            if (in_array($v_name, $parasite_options)) { $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s", $pet_id, $v_name) ); }
            $wpdb->insert( $wpdb->prefix . 'petling_vaccines', array( 'pet_unique_id' => $pet_id, 'vaccine_name' => $v_name, 'date_administered' => date('Y-m-d'), 'next_vaccine_date' => $next_date, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'owner' ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            $record_id = $wpdb->insert_id;
            if (!empty($next_date)) $generate_ics = true;
        }
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id, 'saved' => '1' ), wc_get_endpoint_url( 'my-pets' ) );
        if ($generate_ics) {
            $type_text = in_array($v_name, $parasite_options) ? '🪱 Αποπαρασίτωση' : '💉 Εμβολιασμός';
            $ics_url = petling_crm_get_ics_link( $type_text . ': ' . $v_name . ' (' . $p_name . ')', $next_date, $pet_id, $record_id, 'vaccine' );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }
    
    // --- ΣΗΜΕΙΩΣΗ ---
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
            $record_id = $edit_id;
            if (!empty($next_exam) && (!$old_record || $old_record->next_exam_date !== $next_exam)) { $generate_ics = true; }
        } else {
            $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $vet_comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'status' => 'draft', 'created_by' => 'owner', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            $record_id = $wpdb->insert_id;
            if (!empty($next_exam)) $generate_ics = true;
        }
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id, 'saved' => '1' ), wc_get_endpoint_url( 'my-pets' ) );
        if ($generate_ics) {
            $ics_url = petling_crm_get_ics_link( '🩺 Επανεξέταση (' . $p_name . ')', $next_exam, $pet_id, $record_id, 'note' );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }

    // --- ΑΙΜΑΤΟΛΟΓΙΚΕΣ ---
    if ( isset( $_POST['petling_blood_nonce'] ) && wp_verify_nonce( $_POST['petling_blood_nonce'], 'petling_add_blood' ) ) {
        $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $next_exam = sanitize_text_field($_POST['next_exam_date'] ?? '');
        $vet_name = sanitize_text_field($_POST['vet_name'] ?? '');
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
            $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'vet_comment' => $comment, 'vet_name' => $vet_name, 'next_exam_date' => $next_exam, 'created_at' => $incident_date ), array('id' => $edit_id) );
            $record_id = $edit_id;
            if (!empty($next_exam) && (!$old_record || $old_record->next_exam_date !== $next_exam)) { $generate_ics = true; }
        } else {
            $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'owner', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            $record_id = $wpdb->insert_id;
            if (!empty($next_exam)) $generate_ics = true;
        }
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id, 'saved' => '1' ), wc_get_endpoint_url( 'my-pets' ) );
        if ($generate_ics) {
            $ics_url = petling_crm_get_ics_link( '🩸 Επανεξέταση Αιματολογικών (' . $p_name . ')', $next_exam, $pet_id, $record_id, 'blood' );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }

    // --- ΦΑΡΜΑΚΑ ---
    if ( isset( $_POST['petling_med_nonce'] ) && wp_verify_nonce( $_POST['petling_med_nonce'], 'petling_add_med' ) ) {
        $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $next_exam = sanitize_text_field($_POST['next_exam_date'] ?? '');
        $med_end_date = sanitize_text_field($_POST['med_end_date'] ?? '');
        $vet_name = sanitize_text_field($_POST['vet_name'] ?? '');
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        
        $pets = get_user_meta(get_current_user_id(), 'petling_pets', true);
        $p_name = ''; if (is_array($pets)) { foreach ($pets as $p) { if ($p['id'] === $pet_id) { $p_name = $p['name']; break; } } }

        $med_name = sanitize_text_field($_POST['med_name']);
        $med_condition = sanitize_text_field($_POST['med_condition']);
        $med_dosage = sanitize_textarea_field($_POST['med_dosage']);
        
        $comment = "[MED]\n**Φάρμακο:** " . $med_name . "\n**Πάθηση:** " . $med_condition;
        if (!empty($med_end_date)) { $comment .= "\n**Λήξη Αγωγής:** " . date('d/m/Y', strtotime($med_end_date)); }
        $comment .= "\n**Χορήγηση:**\n" . $med_dosage;
        
        $generate_ics = false;

        if ($edit_id > 0) {
            $old_record = $wpdb->get_row( $wpdb->prepare("SELECT next_exam_date FROM {$wpdb->prefix}petling_vet_notes WHERE id = %d", $edit_id) );
            $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'vet_comment' => $comment, 'vet_name' => $vet_name, 'next_exam_date' => $next_exam, 'created_at' => $incident_date ), array('id' => $edit_id) );
            $record_id = $edit_id;
            if (!empty($next_exam) && (!$old_record || $old_record->next_exam_date !== $next_exam)) { $generate_ics = true; }
        } else {
            $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'owner', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            $record_id = $wpdb->insert_id;
            if (!empty($next_exam)) $generate_ics = true;
        }
        
        $redirect_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id, 'saved' => '1' ), wc_get_endpoint_url( 'my-pets' ) );
        if ($generate_ics) {
            $ics_url = petling_crm_get_ics_link( '🩺 Επανεξέταση μετά από αγωγή (' . $p_name . ')', $next_exam, $pet_id, $record_id, 'meds', $med_condition );
            $redirect_url = add_query_arg('auto_ics', urlencode($ics_url), $redirect_url);
        }
        wp_safe_redirect( $redirect_url ); exit;
    }
    
    // --- ΖΥΓΙΣΗ ---
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
    
    // --- SKIP ALERT ---
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'skip_next' && isset( $_GET['item_id'] ) && isset( $_GET['type'] ) ) {
        if ( wp_verify_nonce( $_GET['_wpnonce'], 'skip_next_' . $_GET['item_id'] ) ) {
            $item_id = intval($_GET['item_id']);
            if ($_GET['type'] === 'vaccine' || $_GET['type'] === 'parasite') {
                $wpdb->update( $wpdb->prefix . 'petling_vaccines', ['next_vaccine_date' => ''], ['id' => $item_id] );
            } else {
                $wpdb->update( $wpdb->prefix . 'petling_vet_notes', ['next_exam_date' => ''], ['id' => $item_id] );
            }
            $redirect = remove_query_arg( ['action', 'item_id', 'type', '_wpnonce', 'auto_ics'] );
            if (isset($_GET['open_modal'])) { $redirect = add_query_arg('open_modal', sanitize_text_field($_GET['open_modal']), $redirect); }
            wp_safe_redirect( $redirect ); exit;
        }
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

    $all_records = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s ORDER BY date_administered DESC", $pet_id ) );
    $vet_notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vet_notes WHERE pet_unique_id = %s ORDER BY created_at DESC", $pet_id ) );

    if ( $pet['type'] === 'dog' ) {
        $vaccine_options = array( 'Παρβοϊός / Μόρβα / Ηπατίτιδα (Core)', 'Λύσσα (Core)', 'Λεπτοσπείρωση (Core)', 'Βήχας Κυνοκομείου (Non-core)', 'Νόσος Lyme (Non-core)', 'Γρίπη Σκύλων (Non-core)', 'Άλλο' );
    } else {
        $vaccine_options = array( 'Τριπλό: FPV/FHV/FCV (Core)', 'Λύσσα (Core)', 'Λευχαιμία - FeLV (Core)', 'Χλαμυδίαση (Non-core)', 'Ανοσοανεπάρκεια - FIV (Non-core)', 'Άλλο' );
    }

    $timeline = [];
    $upcoming_actions = [];
    $chart_labels = [];
    $chart_data = [];
    $today_obj = new DateTime('today', wp_timezone());
    $today_date_str = $today_obj->format('Y-m-d');

    // Εμβόλια
    foreach ($all_records as $rec) {
        $is_parasite = in_array($rec->vaccine_name, $parasite_options);
        $type = $is_parasite ? 'parasite' : 'vaccine';
        $icon = $is_parasite ? '🪱' : '💉';
        
        $timeline[] = [
            'type' => $type, 'icon' => $icon, 'date' => $rec->date_administered, 'title' => $rec->vaccine_name,
            'desc' => $rec->vet_name ? 'Σκεύασμα/Κτηνίατρος: ' . $rec->vet_name : '',
            'next' => $rec->next_vaccine_date, 'id' => $rec->id, 'delete_action' => 'del_vac',
            'raw_comment' => '', 'raw_vet' => $rec->vet_name, 'raw_weight' => ''
        ];

        if (!empty($rec->next_vaccine_date) && $rec->next_vaccine_date !== '0000-00-00') {
            $next_obj = new DateTime($rec->next_vaccine_date, wp_timezone());
            $diff = $today_obj->diff($next_obj)->days;
            $invert = $today_obj->diff($next_obj)->invert; 
            if ($invert && $diff <= 180) {
                $upcoming_actions[] = ['status' => 'red', 'msg' => 'Έληξε: ' . $rec->vaccine_name, 'type' => $type, 'id' => $rec->id];
            } elseif (!$invert && $diff <= 30) {
                $upcoming_actions[] = ['status' => 'orange', 'msg' => 'Σε '.$diff.' μέρες: ' . $rec->vaccine_name, 'type' => $type, 'id' => $rec->id];
            }
        }
    }

    // Σημειώσεις, Αιματολογικές, Φάρμακα & Βάρος
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
            $note_type = 'note';
            $title = 'Ιατρική Σημείωση / Επίσκεψη';
            if (strpos($note->vet_comment, '[BLOOD]') !== false) {
                $note_type = 'blood';
                $title = 'Αιματολογικές / Εξετάσεις';
                $desc = str_replace('[BLOOD]', '', $note->vet_comment);
                $html_desc = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $desc);
                
                $timeline[] = [
                    'type' => 'blood', 'icon' => '🩸', 'date' => $note->created_at, 'title' => 'Αιματολογικές / Εξετάσεις',
                    'desc' => nl2br(trim($html_desc)) . ($note->vet_name ? '<br><small style="color:#888; display:inline-block; margin-top:5px;">Κτηνίατρος: ' . esc_html($note->vet_name) . '</small>' : ''),
                    'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note',
                    'raw_comment' => $note->vet_comment, 'raw_vet' => $note->vet_name, 'raw_weight' => ''
                ];
            } elseif (strpos($note->vet_comment, '[MED]') !== false) {
                $note_type = 'meds';
                $title = 'Φαρμακευτική Αγωγή';
                $desc = str_replace('[MED]', '', $note->vet_comment);
                $html_desc = preg_replace_callback('/\*\*Λήξη Αγωγής:\*\* ([\d-]+)/', function($m) { return '**Λήξη Αγωγής:** ' . date('d/m/Y', strtotime($m[1])); }, $desc);
                $html_desc = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html_desc);
                
                $timeline[] = [
                    'type' => 'meds', 'icon' => '💊', 'date' => $note->created_at, 'title' => 'Φαρμακευτική Αγωγή',
                    'desc' => nl2br(trim($html_desc)) . ($note->vet_name ? '<br><small style="color:#888; display:inline-block; margin-top:5px;">Κτηνίατρος: ' . esc_html($note->vet_name) . '</small>' : ''),
                    'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note',
                    'raw_comment' => $note->vet_comment, 'raw_vet' => $note->vet_name, 'raw_weight' => ''
                ];
            } else {
                $timeline[] = [
                    'type' => 'note', 'icon' => '🩺', 'date' => $note->created_at, 'title' => 'Ιατρική Σημείωση / Επίσκεψη',
                    'desc' => nl2br(esc_html($note->vet_comment)) . ($note->vet_name ? '<br><small style="color:#888;">Κτηνίατρος: ' . esc_html($note->vet_name) . '</small>' : ''),
                    'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note',
                    'raw_comment' => $note->vet_comment, 'raw_vet' => $note->vet_name, 'raw_weight' => ''
                ];
            }
            
            if (!empty($note->next_exam_date) && $note->next_exam_date !== '0000-00-00') {
                $next_obj = new DateTime($note->next_exam_date, wp_timezone());
                $diff = $today_obj->diff($next_obj)->days;
                $invert = $today_obj->diff($next_obj)->invert;
                if ($invert && $diff <= 180) {
                    $upcoming_actions[] = ['status' => 'red', 'msg' => 'Επανεξέταση που πέρασε!', 'type' => $note_type, 'id' => $note->id];
                } elseif (!$invert && $diff <= 30) {
                    $upcoming_actions[] = ['status' => 'orange', 'msg' => 'Επανεξέταση σε '.$diff.' μέρες', 'type' => $note_type, 'id' => $note->id];
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
    $today_date_str = date('Y-m-d');
    
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
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        details.petling-accordion summary { position: relative; padding-right: 40px; list-style: none; }
        details.petling-accordion summary::-webkit-details-marker { display: none; }
        details.petling-accordion summary::after { content: '▼'; position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 13px; color: #C7B297; transition: transform 0.3s ease; }
        details.petling-accordion[open] summary::after { transform: translateY(-50%) rotate(180deg); }
        details#vet-pass-block summary { background: transparent !important; color: #fff !important; }
        details#vet-pass-block summary::after { color: #fff !important; }
        
        .petling-dashboard-card { background: #fff; border: 1px solid #e2d4c0; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .dashboard-header { font-weight: bold; font-size: 16px; color: #43282F; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .dashboard-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed #f0f0f0; gap: 15px; flex-wrap: wrap; }
        .dashboard-item:last-child { border-bottom: none; padding-bottom: 0; }
        .dashboard-item-info { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px; }
        .dashboard-item-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .dashboard-item-dot.red { background: #e62121; box-shadow: 0 0 5px rgba(230,33,33,0.4); }
        .dashboard-item-dot.orange { background: #f59e0b; box-shadow: 0 0 5px rgba(245,158,11,0.4); }
        .dashboard-item-title { font-size: 14px; color: #333; font-weight: 500; line-height: 1.3; }
        .dashboard-item-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .dash-action-btn { background: #fdfaf5; border: 1px solid #e2d4c0; padding: 6px 12px; border-radius: 6px; font-size: 12px; color: #43282F; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; font-weight: bold; }
        .dash-action-btn.done:hover { background: #e6f4ea; border-color: #5b9a68; color: #2e663a; }
        .dash-action-btn.edit:hover { background: #fff5e6; border-color: #f59e0b; color: #b47400; }
        .dash-action-btn.skip:hover { background: #fbe9e7; border-color: #e62121; color: #a11212; padding: 6px 8px; }
        
        .petling-filters { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 20px; }
        .petling-chip { background: #fdfdfd; border: 1px solid #ddd; padding: 8px 16px; border-radius: 20px; font-size: 14px; cursor: pointer; white-space: nowrap; color: #555; transition: all 0.2s; }
        .petling-chip.active { background: #43282F; color: #fff; border-color: #43282F; font-weight: bold; }
        
        .petling-timeline { position: relative; padding-left: 30px; margin-bottom: 40px; }
        .petling-timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e2d4c0; }
        .timeline-item { position: relative; margin-bottom: 25px; padding: 15px; background: #fff; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .timeline-icon { position: absolute; left: -35px; top: 15px; width: 30px; height: 30px; background: #fff; border: 2px solid #C7B297; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 2; }
        .timeline-date { font-size: 12px; color: #888; margin-bottom: 5px; font-weight: bold; }
        .timeline-title { font-size: 16px; color: #43282F; margin: 0 0 5px 0; }
        .timeline-desc { font-size: 14px; color: #555; margin: 0; line-height: 1.5; }
        .timeline-next { margin-top: 10px; font-size: 13px; color: #d63638; font-weight: bold; background: #fff5f5; display: inline-block; padding: 4px 8px; border-radius: 4px; }
        .timeline-next-past { margin-top: 10px; font-size: 13px; color: #888; font-weight: normal; background: #f9f9f9; border: 1px dashed #ccc; display: inline-block; padding: 4px 8px; border-radius: 4px; }
        .timeline-year-divider { background: #f3f4f6; color: #888; font-weight: bold; text-align: center; padding: 5px 15px; border-radius: 20px; display: inline-block; margin-bottom: 20px; border: 1px solid #ddd; position: relative; z-index: 2; margin-left: -5px; }
        
        .petling-fab { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background: #43282F; color: #fff; border-radius: 50%; font-size: 30px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer; z-index: 999; transition: transform 0.2s; border: none; }
        .petling-fab:hover { transform: scale(1.05); }
        .petling-fab-menu { position: fixed; bottom: 100px; right: 30px; display: flex; flex-direction: column; gap: 10px; z-index: 998; opacity: 0; pointer-events: none; transition: all 0.3s ease; transform: translateY(20px); }
        .petling-fab-menu.active { opacity: 1; pointer-events: auto; transform: translateY(0); }
        .fab-btn { background: #fff; border: 1px solid #ddd; padding: 12px 20px; border-radius: 30px; font-weight: bold; color: #43282F; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: pointer; text-align: right; }
        
        .petling-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; }
        .petling-modal { background: #fff; padding: 25px; border-radius: 12px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; position: relative; }
        .petling-modal-close { position: absolute; top: 15px; right: 15px; font-size: 24px; cursor: pointer; color: #999; line-height: 1; border: none; background: none; }
        
        .petling-modal label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 14px; line-height: 1.2; }
        .petling-modal input, .petling-modal select, .petling-modal textarea { width: 100%; box-sizing: border-box; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; background-color: #fff; color: #333; font-family: inherit; -webkit-appearance: none; appearance: none; }
        .petling-modal input[type="date"] { min-height: 42px; }
        
        .petling-toast-notification { position: fixed; top: 20px; right: 20px; background: #5b9a68; color: #fff; padding: 15px 25px; border-radius: 8px; font-weight: bold; font-size: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 999999; animation: slideInRight 0.4s ease-out forwards, fadeOut 0.4s ease-in 3.5s forwards; }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; visibility: hidden; } }
    </style>
    
    <div class="petling-loader-overlay" id="petling-global-loader" style="display: none;">
        <div class="petling-spinner"></div>
    </div>
    <div class="petling-toast-notification" id="petling-toast" style="display: none;">✅ Η αλλαγή αποθηκεύτηκε!</div>

    <datalist id="saved_vets_list">
        <?php 
        $user_vets = get_user_meta(get_current_user_id(), 'petling_vets', true);
        if (is_array($user_vets)) {
            foreach ($user_vets as $uv) {
                if (!empty($uv['name'])) echo '<option value="' . esc_attr($uv['name']) . '">';
            }
        }
        ?>
    </datalist>

    <!-- 1. ΝΕΟ ΛΕΥΚΟ DASHBOARD -->
    <?php if (!empty($upcoming_actions)) : ?>
        <div class="petling-dashboard-card">
            <div class="dashboard-header">📋 Πρέπει να γίνουν:</div>
            <div class="dashboard-list">
                <?php foreach ($upcoming_actions as $act) : 
                    $skip_url = wp_nonce_url( add_query_arg( ['action' => 'skip_next', 'item_id' => $act['id'], 'type' => $act['type']] ), 'skip_next_' . $act['id'] );
                    $done_modal = ($act['type'] === 'vaccine' || $act['type'] === 'parasite') ? 'modal-vaccine' : ($act['type'] === 'blood' ? 'modal-blood' : ($act['type'] === 'meds' ? 'modal-meds' : 'modal-note'));
                    $done_url = add_query_arg( 'open_modal', $done_modal, $skip_url );
                ?>
                    <div class="dashboard-item">
                        <div class="dashboard-item-info">
                            <div class="dashboard-item-dot <?php echo esc_attr($act['status']); ?>"></div>
                            <div class="dashboard-item-title"><?php echo esc_html($act['msg']); ?></div>
                        </div>
                        <div class="dashboard-item-actions">
                            <a href="<?php echo esc_url($done_url); ?>" class="dash-action-btn done" title="Αποθήκευση νέας εγγραφής (Σβήνει το παλιό)">✔️ Έγινε</a>
                            <button type="button" class="dash-action-btn edit" onclick="jQuery('.timeline-item[data-id=\'<?php echo esc_attr($act['id']); ?>\'] .edit-timeline-btn').click();" title="Αλλαγή ημερομηνίας">📅 Αλλαγή</button>
                            <a href="<?php echo esc_url($skip_url); ?>" class="dash-action-btn skip" onclick="return confirm('Να σβηστεί η υπενθύμιση;');" title="Παράλειψη">✖️</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
                                    <div><label>Όνομα / Επωνυμία *</label><input type="text" name="vet_name[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['name']); ?>" required></div>
                                    <div><label>Email</label><input type="email" name="vet_email[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['email'] ?? ''); ?>"></div>
                                    <div><label>Διεύθυνση / Google Maps</label><input type="text" name="vet_address[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['address']); ?>"></div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                    <div><label>Τηλέφωνο ιατρείου *</label><input type="tel" pattern="[0-9\s\+]*" name="vet_phone[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['phone']); ?>" required></div>
                                    <div><label>Κινητό SOS 1 (Προαιρετικό)</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['sos']); ?>"></div>
                                    <div><label>Κινητό SOS 2 (Προαιρετικό)</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos2[<?php echo $v_idx; ?>]" value="<?php echo esc_attr($v['sos2'] ?? ''); ?>"></div>
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
        <div class="petling-chip" data-filter="meds">💊 Φάρμακα</div>
        <div class="petling-chip" data-filter="blood">🩸 Εξετάσεις</div>
        <div class="petling-chip" data-filter="note">🩺 Σημειώσεις</div>
    </div>

    <!-- ΓΡΑΦΗΜΑ ΒΑΡΟΥΣ -->
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
                <div class="timeline-item" data-type="<?php echo esc_attr($item['type']); ?>" data-id="<?php echo esc_attr($item['id']); ?>" data-raw-date="<?php echo date('Y-m-d', strtotime($item['date'])); ?>" data-raw-next="<?php echo esc_attr($item['next']); ?>" data-vet="<?php echo esc_attr($item['raw_vet']); ?>" data-weight="<?php echo esc_attr($item['raw_weight']); ?>" data-title="<?php echo esc_attr($item['title']); ?>">
                    <div class="raw-comment" style="display:none;"><?php echo esc_html($item['raw_comment']); ?></div>
                    <div class="timeline-icon"><?php echo $item['icon']; ?></div>
                    <div class="timeline-date"><?php echo date('d/m/Y', strtotime($item['date'])); ?></div>
                    <h4 class="timeline-title"><?php echo esc_html($item['title']); ?></h4>
                    <?php if (!empty($item['desc'])): ?><p class="timeline-desc"><?php echo $item['desc']; ?></p><?php endif; ?>
                    
                    <?php if (!empty($item['next']) && $item['next'] !== '0000-00-00'): 
                        $is_past = ($item['next'] < $today_date_str);
                        if ($is_past): ?>
                            <div class="timeline-next-past">📅 Είχε οριστεί: <?php echo date('d/m/Y', strtotime($item['next'])); ?></div>
                        <?php else: ?>
                            <div class="timeline-next">📅 Επόμενο: <?php echo date('d/m/Y', strtotime($item['next'])); ?></div>
                        <?php endif; 
                    endif; ?>
                    
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
        <button class="fab-btn" onclick="openModal('modal-meds')">💊 Νέο Φάρμακο / Αγωγή</button>
        <button class="fab-btn" onclick="openModal('modal-blood')">🩸 Νέες Αιματολογικές (SOS)</button>
        <button class="fab-btn" onclick="openModal('modal-note')">🩺 Νέα Ιατρική Σημείωση</button>
    </div>
    <button class="petling-fab" id="main-fab">+</button>

    <!-- MODALS (Φόρμες) -->
    
    <div class="petling-modal-overlay" id="modal-weight">
        <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">⚖️ Προσθήκη Βάρους</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_log_weight', 'petling_weight_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                <label>Ημερομηνία *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                <label>Βάρος (kg) *</label><input type="number" step="0.01" min="0" name="weight" required>
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση</button>
            </form>
        </div>
    </div>

    <div class="petling-modal-overlay" id="modal-vaccine">
        <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">💉 Υπενθύμιση (Εμβόλιο/Αποπαρασίτωση)</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_add_vaccine', 'petling_vaccine_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                
                <label>Τύπος / Εμβόλιο *</label>
                <select name="vaccine_name" required>
                    <optgroup label="Αποπαρασίτωση"><?php foreach($parasite_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?></optgroup>
                    <optgroup label="Εμβολιασμός"><?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?></optgroup>
                </select>
                
                <label>Σκεύασμα / Κτηνίατρος (Προαιρετικό)</label>
                <input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off">
                <label style="color:#d63638;">Επόμενη Δόση 📅 *</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>" required>
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση & Ημερολόγιο</button>
            </form>
        </div>
    </div>

    <!-- Modal: Φάρμακα -->
    <div class="petling-modal-overlay" id="modal-meds">
        <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">💊 Φαρμακευτική Αγωγή</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_add_med', 'petling_med_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                
                <div style="display:flex; gap:10px; margin-bottom:15px; width:100%; align-items:flex-start;">
                    <div style="flex:1; min-width:0;"><label>Έναρξη *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="margin-bottom:0;"></div>
                    <div style="flex:1; min-width:0;"><label>Λήξη Αγωγής</label><input type="date" name="med_end_date" style="margin-bottom:0;"></div>
                </div>
                
                <label>Κτηνίατρος (Προαιρετικό)</label><input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off">
                <label>Φάρμακο / Ουσία *</label><input type="text" name="med_name" required placeholder="π.χ. Augmentin 500">
                <label>Πάθηση / Αιτία *</label><input type="text" name="med_condition" required placeholder="π.χ. Ουρολοίμωξη">
                <label>Οδηγίες Χορήγησης *</label><textarea name="med_dosage" required rows="2" placeholder="π.χ. 3 φορές τη μέρα με φαΐ"></textarea>
                <label style="color:#d63638;">Επανεξέταση 📅 (Προαιρετικό)</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>">
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση & Ημερολόγιο</button>
            </form>
        </div>
    </div>

    <!-- Modal: Αιματολογικές -->
    <div class="petling-modal-overlay" id="modal-blood">
        <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">🩸 Αιματολογικές (Δείκτες)</h3>
            <form method="post" action="" id="blood-form">
                <?php wp_nonce_field( 'petling_add_blood', 'petling_blood_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                
                <div style="display:flex; gap:10px; margin-bottom:15px; width:100%; align-items:flex-start;">
                    <div style="flex:1; min-width:0;"><label>Ημερομηνία Εξέτασης *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="margin-bottom:0;"></div>
                    <div style="flex:1; min-width:0;"><label>Εργαστήριο/Κτηνίατρος</label><input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off" style="margin-bottom:0;"></div>
                </div>
                
                <label>Δείκτες εκτός ορίων:</label>
                <div style="display:flex; gap:10px; margin-bottom:5px; font-weight:bold; font-size:13px; color:#555; padding-right: 34px;">
                    <div style="flex:2.5;">Εξέταση</div><div style="flex:1;">Αποτέλεσμα</div><div style="flex:1;">Τιμές Αναφοράς</div>
                </div>
                <div id="blood-markers-container"></div>
                <button type="button" id="add-blood-marker" style="margin-bottom:15px; margin-top: 5px; background:none; border:1px dashed #C7B297; color:#43282F; padding:8px; width:100%; border-radius:6px; font-weight:bold; cursor:pointer;">+ Προσθήκη Δείκτη</button>
                <label style="color:#d63638;">Επανεξέταση / Επανάληψη 📅 (Προαιρετικό)</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>">
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση & Ημερολόγιο</button>
            </form>
        </div>
    </div>

    <!-- Modal: Ιατρική Σημείωση -->
    <div class="petling-modal-overlay" id="modal-note">
        <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">🩺 Ιατρική Σημείωση</h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'petling_add_vet_note', 'petling_vet_note_nonce' ); ?>
                <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
                <input type="hidden" name="edit_id" value="">
                <label>Ημερομηνία Συμβάντος *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                <label>Κτηνίατρος (Προαιρετικό)</label><input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off">
                <label>Πάθηση, Αγωγή & Σχόλια *</label><textarea name="vet_comment" required rows="3"></textarea>
                <label style="color:#d63638;">Επανεξέταση 📅 (Προαιρετικό)</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>">
                <button type="submit" class="btn-petling btn-brown" style="width:100%;">Αποθήκευση & Ημερολόγιο</button>
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
                <div><label>Όνομα / Επωνυμία *</label><input type="text" name="vet_name[__VINDEX__]" required></div>
                <div><label>Email</label><input type="email" name="vet_email[__VINDEX__]"></div>
                <div><label>Διεύθυνση / Google Maps</label><input type="text" name="vet_address[__VINDEX__]"></div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div><label>Τηλέφωνο ιατρείου *</label><input type="tel" pattern="[0-9\s\+]*" name="vet_phone[__VINDEX__]" required></div>
                <div><label>Κινητό SOS 1</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos[__VINDEX__]"></div>
                <div><label>Κινητό SOS 2</label><input type="tel" pattern="[0-9\s\+]*" name="vet_sos2[__VINDEX__]"></div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('open_modal')) {
            openModal(urlParams.get('open_modal')); urlParams.delete('open_modal'); window.history.replaceState(null, '', window.location.pathname + '?' + urlParams.toString());
        }

        // --- VETS LOGIC ---
        var nextVetIndex = $('.vet-block').length;
        $(document).off('click', '.edit-vet-btn').on('click', '.edit-vet-btn', function(e) {
            e.preventDefault(); $(this).closest('.vet-block').find('.vet-view-mode').hide(); $(this).closest('.vet-block').find('.vet-edit-mode').fadeIn();
        });
        $(document).off('click', '#add-vet-button').on('click', '#add-vet-button', function(e) {
            e.preventDefault(); if ( $('.vet-block').length >= 3 ) { alert('Μπορείτε να προσθέσετε μέχρι 3 κτηνιάτρους.'); return; }
            var templateHtml = $('#vet-block-template').html().replace(/__VINDEX__/g, nextVetIndex); var newBlock = $('<div class="vet-block" style="margin-bottom:15px; display:none;">' + templateHtml + '</div>');
            $('#vets-repeater-container').append(newBlock); newBlock.slideDown(200); nextVetIndex++;
        });
        $(document).off('click', '.remove-vet-button').on('click', '.remove-vet-button', function(e) {
            e.preventDefault(); if (confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε τον κτηνίατρο;')) { $(this).closest('.vet-block').slideUp(200, function() { $(this).remove(); }); }
        });

        // --- BLOOD TEST REPEATER LOGIC ---
        $('#add-blood-marker').click(function(e) {
            e.preventDefault();
            $('#blood-markers-container').append('<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;"><input type="text" name="blood_name[]" placeholder="Εξέταση" required style="flex:2.5; margin-bottom:0;"><input type="text" name="blood_value[]" placeholder="Αποτέλεσμα" required style="flex:1; margin-bottom:0;"><input type="text" name="blood_limit[]" placeholder="Όρια" style="flex:1; margin-bottom:0;"><button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>');
        });
        $(document).on('click', '.remove-blood-btn', function(e) { e.preventDefault(); $(this).closest('.blood-marker-row').remove(); });
        $('#blood-form').on('submit', function(e) { if ($('.blood-marker-row').length === 0) { e.preventDefault(); alert('Παρακαλώ προσθέστε τουλάχιστον μία Εξέταση (Δείκτη).'); } });

        function parseGreekDate(str) { var parts = str.split('/'); if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0]; return ''; }

        // --- SMART EDIT BUTTON ---
        $('.edit-timeline-btn').click(function(e) {
            e.preventDefault(); var $item = $(this).closest('.timeline-item'); var type = $item.data('type'); var modalId = '';
            
            if (type === 'weight') {
                modalId = 'modal-weight'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Βάρους');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="weight"]').val($item.data('weight'));
            } else if (type === 'vaccine' || type === 'parasite') {
                modalId = 'modal-vaccine'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Υπενθύμισης');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' select[name="vaccine_name"]').val($item.data('title')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' input[name="next_vaccine_date"]').val($item.data('raw-next'));
            } else if (type === 'note') {
                modalId = 'modal-note'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Σημείωσης');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' textarea[name="vet_comment"]').val($item.find('.raw-comment').text()); $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next'));
            } else if (type === 'meds') {
                modalId = 'modal-meds'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Φαρμάκου');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                var lines = $item.find('.raw-comment').text().split('\n'); var dosageLines = []; var isDosage = false;
                lines.forEach(function(line) {
                    if (line.indexOf('**Φάρμακο:**') > -1) { $('#' + modalId + ' input[name="med_name"]').val(line.replace('**Φάρμακο:**', '').trim()); } 
                    else if (line.indexOf('**Πάθηση:**') > -1) { $('#' + modalId + ' input[name="med_condition"]').val(line.replace('**Πάθηση:**', '').trim()); } 
                    else if (line.indexOf('**Λήξη Αγωγής:**') > -1) { $('#' + modalId + ' input[name="med_end_date"]').val(parseGreekDate(line.replace('**Λήξη Αγωγής:**', '').trim())); }
                    else if (line.indexOf('**Χορήγηση:**') > -1) { isDosage = true; dosageLines.push(line.replace('**Χορήγηση:**', '').trim()); } 
                    else if (isDosage && line.trim() !== '' && line.indexOf('[MED]') === -1) { dosageLines.push(line.trim()); }
                });
                $('#' + modalId + ' textarea[name="med_dosage"]').val(dosageLines.join('\n').trim());
            } else if (type === 'blood') {
                modalId = 'modal-blood'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Αιματολογικών');
                $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                $('#blood-markers-container').empty(); var lines = $item.find('.raw-comment').text().split('\n'); var found = false;
                lines.forEach(function(line) {
                    if (line.indexOf('- **') === 0) {
                        var match = line.match(/- \*\*(.*?):\*\* (.*?)(?: \(Όριο: (.*?)\))?$/);
                        if (match) { found = true; $('#blood-markers-container').append('<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;"><input type="text" name="blood_name[]" value="'+match[1]+'" required style="flex:2.5; margin-bottom:0;"><input type="text" name="blood_value[]" value="'+match[2]+'" required style="flex:1; margin-bottom:0;"><input type="text" name="blood_limit[]" value="'+(match[3] || '')+'" style="flex:1; margin-bottom:0;"><button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>'); }
                    }
                });
                if(!found) $('#add-blood-marker').click();
            }
            if (modalId) { document.getElementById('fab-menu').classList.remove('active'); document.getElementById('main-fab').style.transform = 'rotate(0)'; document.getElementById(modalId).style.display = 'flex'; }
        });

        // --- SMART COPY BUTTON ---
        $('.copy-timeline-btn').click(function(e) {
            e.preventDefault(); var $item = $(this).closest('.timeline-item'); var type = $item.data('type'); var modalId = '';
            
            if (type === 'weight') {
                modalId = 'modal-weight'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Βάρους');
                $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="weight"]').val($item.data('weight'));
            } else if (type === 'vaccine' || type === 'parasite') {
                modalId = 'modal-vaccine'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Υπενθύμισης');
                $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' select[name="vaccine_name"]').val($item.data('title')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' input[name="next_vaccine_date"]').val(''); 
            } else if (type === 'note') {
                modalId = 'modal-note'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Σημείωσης');
                $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' textarea[name="vet_comment"]').val($item.find('.raw-comment').text()); $('#' + modalId + ' input[name="next_exam_date"]').val(''); 
            } else if (type === 'meds') {
                modalId = 'modal-meds'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Φαρμάκου');
                $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="med_end_date"]').val(''); $('#' + modalId + ' input[name="next_exam_date"]').val(''); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                var lines = $item.find('.raw-comment').text().split('\n'); var dosageLines = []; var isDosage = false;
                lines.forEach(function(line) {
                    if (line.indexOf('**Φάρμακο:**') > -1) { $('#' + modalId + ' input[name="med_name"]').val(line.replace('**Φάρμακο:**', '').trim()); } 
                    else if (line.indexOf('**Πάθηση:**') > -1) { $('#' + modalId + ' input[name="med_condition"]').val(line.replace('**Πάθηση:**', '').trim()); } 
                    else if (line.indexOf('**Χορήγηση:**') > -1) { isDosage = true; dosageLines.push(line.replace('**Χορήγηση:**', '').trim()); } 
                    else if (isDosage && line.trim() !== '' && line.indexOf('[MED]') === -1) { dosageLines.push(line.trim()); }
                });
                $('#' + modalId + ' textarea[name="med_dosage"]').val(dosageLines.join('\n').trim());
            } else if (type === 'blood') {
                modalId = 'modal-blood'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Αιματολογικών');
                $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="next_exam_date"]').val(''); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                $('#blood-markers-container').empty(); var lines = $item.find('.raw-comment').text().split('\n'); var found = false;
                lines.forEach(function(line) {
                    if (line.indexOf('- **') === 0) {
                        var match = line.match(/- \*\*(.*?):\*\* (.*?)(?: \(Όριο: (.*?)\))?$/);
                        if (match) { found = true; $('#blood-markers-container').append('<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;"><input type="text" name="blood_name[]" value="'+match[1]+'" required style="flex:2.5; margin-bottom:0;"><input type="text" name="blood_value[]" value="'+match[2]+'" required style="flex:1; margin-bottom:0;"><input type="text" name="blood_limit[]" value="'+(match[3] || '')+'" style="flex:1; margin-bottom:0;"><button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>'); }
                    }
                });
                if(!found) $('#add-blood-marker').click();
            }
            if (modalId) { document.getElementById('fab-menu').classList.remove('active'); document.getElementById('main-fab').style.transform = 'rotate(0)'; document.getElementById(modalId).style.display = 'flex'; }
        });

        $('.petling-chip').click(function() {
            $('.petling-chip').removeClass('active'); $(this).addClass('active'); var filter = $(this).data('filter');
            if (filter === 'weight') { $('#weight-chart-container').fadeIn(); setTimeout(initChart, 100); } else { $('#weight-chart-container').hide(); }
            if (filter === 'all') { $('.timeline-item').fadeIn(); $('.timeline-year-divider').show(); } else { $('.timeline-year-divider').hide(); $('.timeline-item').hide(); $('.timeline-item[data-type="' + filter + '"]').fadeIn(); }
        });

        var weightChart = null;
        function initChart() {
            if (weightChart) return; 
            var ctx = document.getElementById('weightChart').getContext('2d');
            var labels = <?php echo json_encode($chart_labels); ?>;
            var data = <?php echo json_encode($chart_data); ?>;
            if(labels.length === 0) { $('#weight-chart-container').html('<p style="text-align:center; color:#888;">Δεν υπάρχουν αρκετά δεδομένα για το γράφημα.</p>'); return; }
            weightChart = new Chart(ctx, { type: 'line', data: { labels: labels, datasets: [{ label: 'Βάρος (kg)', data: data, borderColor: '#43282F', backgroundColor: 'rgba(199, 178, 151, 0.3)', borderWidth: 2, tension: 0.3, fill: true, pointBackgroundColor: '#C7B297', pointRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false } } } });
        }
        $('#main-fab').click(function() { $('#fab-menu').toggleClass('active'); $(this).css('transform', $('#fab-menu').hasClass('active') ? 'rotate(45deg)' : 'rotate(0)'); });
    });

    function openModal(id) {
        document.getElementById('fab-menu').classList.remove('active'); document.getElementById('main-fab').style.transform = 'rotate(0)';
        var form = document.querySelector('#' + id + ' form');
        if (form) {
            form.reset();
            var editIdField = form.querySelector('input[name="edit_id"]'); if (editIdField) editIdField.value = '';
            if (id === 'modal-weight') jQuery('#' + id + ' .modal-title-dynamic').text('⚖️ Προσθήκη Βάρους');
            if (id === 'modal-vaccine') jQuery('#' + id + ' .modal-title-dynamic').text('💉 Νέα Υπενθύμιση (Εμβόλιο/Αποπαρασίτωση)');
            if (id === 'modal-meds') jQuery('#' + id + ' .modal-title-dynamic').text('💊 Νέο Φάρμακο / Αγωγή');
            if (id === 'modal-note') jQuery('#' + id + ' .modal-title-dynamic').text('🩺 Νέα Ιατρική Σημείωση');
            if (id === 'modal-blood') { jQuery('#' + id + ' .modal-title-dynamic').text('🩸 Νέες Αιματολογικές (Δείκτες)'); jQuery('#blood-markers-container').empty(); jQuery('#add-blood-marker').click(); }
        }
        document.getElementById(id).style.display = 'flex';
    }
    function closeModals() { var modals = document.getElementsByClassName('petling-modal-overlay'); for (var i = 0; i < modals.length; i++) { modals[i].style.display = 'none'; } }
    </script>
    <script>
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('saved') || url.searchParams.has('auto_ics')) {
                jQuery('#petling-toast').fadeIn(400).delay(3000).fadeOut(400);
                url.searchParams.delete('saved'); url.searchParams.delete('auto_ics');
                window.history.replaceState(null, '', url.href);
            }
        }
    </script>
    <?php
}