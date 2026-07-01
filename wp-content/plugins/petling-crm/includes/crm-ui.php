<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. ΜΗΧΑΝΙΣΜΟΣ ΔΗΜΙΟΥΡΓΙΑΣ .ICS (ΓΙΑ IPHONE, ANDROID, OUTLOOK)
add_action( 'template_redirect', 'petling_crm_generate_ics' );
function petling_crm_generate_ics() {
    if ( isset( $_GET['petling_ics'] ) && $_GET['petling_ics'] == '1' ) {
        $title = isset($_GET['title']) ? sanitize_text_field(stripslashes(urldecode($_GET['title']))) : 'Ραντεβού Petling';
        $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $desc = isset($_GET['desc']) ? sanitize_text_field(stripslashes(urldecode($_GET['desc']))) : '';

        if ( empty($date) ) return;

        $start = date('Ymd', strtotime($date));
        $end = date('Ymd', strtotime($date . ' +1 day')); // Ολοήμερο γεγονός
        $stamp = date('Ymd\THis\Z');

        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//Petling CRM//NONSGML v1.0//EN\r\n";
        $ics_content .= "CALSCALE:GREGORIAN\r\n";
        $ics_content .= "BEGIN:VEVENT\r\n";
        $ics_content .= "UID:" . uniqid() . "@petling.gr\r\n";
        $ics_content .= "DTSTAMP:" . $stamp . "\r\n";
        $ics_content .= "DTSTART;VALUE=DATE:" . $start . "\r\n";
        $ics_content .= "DTEND;VALUE=DATE:" . $end . "\r\n";
        $ics_content .= "SUMMARY:" . $title . "\r\n";
        $ics_content .= "DESCRIPTION:" . $desc . "\r\n";
        $ics_content .= "END:VEVENT\r\n";
        $ics_content .= "END:VCALENDAR\r\n";

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="petling-reminder.ics"');
        echo $ics_content;
        exit;
    }
}

// Helper για να φτιάχνουμε το URL του ICS
function petling_crm_get_ics_link( $title, $date, $details = '' ) {
    if ( empty( $date ) || $date === '0000-00-00' || strtotime($date) <= 0 ) return '';
    return add_query_arg( [
        'petling_ics' => '1',
        'title' => urlencode($title),
        'date' => $date,
        'desc' => urlencode($details)
    ], site_url() );
}

add_action( 'template_redirect', 'petling_crm_save_pets' );
function petling_crm_save_pets() {
    if ( isset( $_POST['petling_crm_nonce'] ) && wp_verify_nonce( $_POST['petling_crm_nonce'], 'petling_save_pets' ) ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        // ΕΛΕΓΧΟΣ: Μήπως πατήθηκε το κουμπί του Test Email;
        if ( isset($_POST['petling_test_email_trigger']) && $_POST['petling_test_email_trigger'] == '1' && current_user_can( 'manage_options' ) ) {
            $user_info = get_userdata($user_id);
            $pets = get_user_meta( $user_id, 'petling_pets', true );
            $pet_names = [];
            if ( is_array( $pets ) ) {
                foreach ( $pets as $p ) { if ( !empty($p['name']) ) $pet_names[] = esc_html($p['name']); }
            }
            if ( function_exists('petling_send_smart_reminder_email') ) {
                petling_send_smart_reminder_email( $user_info->user_email, $pet_names, '30' ); 
                wc_add_notice( '✅ Επιτυχία! Το δοκιμαστικό email μόλις στάλθηκε στο ' . $user_info->user_email . ' (Ελέγξτε και τον φάκελο Spam/Ανεπιθύμητα).', 'success' );
            }
            wp_safe_redirect( wc_get_endpoint_url( 'pet-crm' ) );
            exit;
        }
        
        // Αποθήκευση της νέας ρύθμισης ημερών
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
                    'breed'        => sanitize_text_field( $_POST['pet_breed'][ $index ] ?? '' ),
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
        wp_safe_redirect( wc_get_endpoint_url( 'pet-crm' ) );
        exit;
    }
}

add_action( 'template_redirect', 'petling_crm_handle_vaccines' );
function petling_crm_handle_vaccines() {
    global $wpdb;
    if ( isset( $_POST['petling_vaccine_nonce'] ) && wp_verify_nonce( $_POST['petling_vaccine_nonce'], 'petling_add_vaccine' ) ) {
        $wpdb->insert(
            $wpdb->prefix . 'petling_vaccines',
            array( 
                'pet_unique_id' => sanitize_text_field($_POST['pet_unique_id']), 
                'vaccine_name' => sanitize_text_field($_POST['vaccine_name']), 
                'date_administered' => sanitize_text_field($_POST['date_administered']), 
                'next_vaccine_date' => sanitize_text_field($_POST['next_vaccine_date']), 
                'vet_name' => sanitize_text_field($_POST['vet_name']),
                'status' => 'draft',
                'created_by' => 'owner'
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => sanitize_text_field($_POST['pet_unique_id']) ), wc_get_endpoint_url( 'pet-crm' ) ) );
        exit;
    }
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_vac' && isset( $_GET['vac_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_vac_' . $_GET['vac_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ), 'status' => 'draft' ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'vac_id', '_wpnonce' ) ) );
        exit;
    }
}

add_action( 'template_redirect', 'petling_crm_handle_vet_notes' );
function petling_crm_handle_vet_notes() {
    global $wpdb;
    if ( isset( $_POST['petling_vet_note_nonce'] ) && wp_verify_nonce( $_POST['petling_vet_note_nonce'], 'petling_add_vet_note' ) ) {
        $wpdb->insert(
            $wpdb->prefix . 'petling_vet_notes',
            array( 
                'pet_unique_id' => sanitize_text_field($_POST['pet_unique_id']), 
                'vet_comment' => sanitize_textarea_field($_POST['vet_comment']), 
                'next_exam_date'=> sanitize_text_field($_POST['next_exam_date']), 
                'vet_name' => sanitize_text_field($_POST['vet_name']),
                'status' => 'draft',
                'created_by' => 'owner'
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => sanitize_text_field($_POST['pet_unique_id']) ), wc_get_endpoint_url( 'pet-crm' ) ) );
        exit;
    }
    
    // Καταγραφή Βάρους
    if ( isset( $_POST['petling_weight_nonce'] ) && wp_verify_nonce( $_POST['petling_weight_nonce'], 'petling_log_weight' ) ) {
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $new_weight = sanitize_text_field($_POST['weight']);
        $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'weight' => $new_weight, 'vet_comment' => 'Τακτική Ζύγιση (Κηδεμόνας)', 'status' => 'verified', 'created_by' => 'owner' ), array( '%s', '%f', '%s', '%s', '%s' ) );
        
        $user_id = get_current_user_id();
        $pets_array = get_user_meta($user_id, 'petling_pets', true);
        if (is_array($pets_array)) {
            foreach ($pets_array as &$p) {
                if ($p['id'] === $pet_id) { $p['weight'] = $new_weight; break; }
            }
            update_user_meta($user_id, 'petling_pets', $pets_array);
        }
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'pet-crm' ) ) );
        exit;
    }

    // Διαγραφή Προσχεδίου Εξέτασης
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_note' && isset( $_GET['note_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_note_' . $_GET['note_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ), 'status' => 'draft' ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'note_id', '_wpnonce' ) ) );
        exit;
    }
    
    // Διαγραφή Ζύγισης (Ιστορικό Βάρους)
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_weight' && isset( $_GET['weight_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_weight_' . $_GET['weight_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['weight_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'weight_id', '_wpnonce' ) ) );
        exit;
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
            if ( isset($pet['id']) && $pet['id'] === $target_pet_id ) {
                petling_crm_render_medical_history( $pet );
                return;
            }
        }
    }

    $reminder_interval = get_user_meta( $user_id, 'petling_order_reminder_interval', true ) ?: 'no';
    $today = date('Y-m-d');
    $min_date = date('Y-m-d', strtotime('-30 years'));
    
    $energy_levels = [ 'low' => 'Χαμηλή', 'medium' => 'Μέτρια', 'high' => 'Υψηλή' ];
    $breeds = [
        'amstaff' => 'American Staffordshire Terrier (Amstaff)', 'beagle' => 'Beagle', 'boxer' => 'Boxer', 'chihuahua' => 'Chihuahua', 'cocker_spaniel' => 'Cocker Spaniel', 'dachshund' => 'Dachshund (Λουκάνικο)', 'doberman' => 'Doberman', 'french_bulldog' => 'French Bulldog', 'german_shepherd' => 'German Shepherd (Γερμανικός Ποιμενικός)', 'golden_retriever' => 'Golden Retriever', 'griffon' => 'Griffon', 'jack_russell' => 'Jack Russell Terrier', 'kane_korso' => 'Cane Corso', 'labrador_retriever' => 'Labrador Retriever', 'maltese' => 'Maltese', 'poodle' => 'Poodle (Κανίς)', 'pomeranian' => 'Pomeranian', 'pug' => 'Pug', 'rottweiler' => 'Rottweiler', 'setter' => 'Setter', 'shih_tzu' => 'Shih Tzu', 'siberian_husky' => 'Siberian Husky', 'westie' => 'West Highland White Terrier (Westie)', 'yorkshire_terrier' => 'Yorkshire Terrier', 'greek_harehound' => 'Ελληνικός Ιχνηλάτης', 'greek_shepherd' => 'Ελληνικός Ποιμενικός', 'kokoni' => 'Κοκόνι', 'imichano_dog' => 'Ημίαιμο (Σκύλος)',
        'aegean' => 'Aegean (Γάτα του Αιγαίου)', 'bengal' => 'Bengal', 'birman' => 'Birman', 'british_shorthair' => 'British Shorthair', 'maine_coon' => 'Maine Coon', 'persian' => 'Persian (Περσίας)', 'ragdoll' => 'Ragdoll', 'siamese' => 'Siamese (Σιάμ)', 'sphynx' => 'Sphynx', 'imichani_cat' => 'Ημίαιμη (Γάτα)',
        'other' => 'Άλλη Φυλή',
    ];
    asort($breeds);
    $health_issues = [ 'allergies' => 'Αλλεργίες (Δερματικές/Τροφικές)', 'gastrointestinal'=> 'Γαστρεντερικές Ευαισθησίες', 'dysplasia' => 'Δυσπλασία Ισχίου/Αγκώνα', 'arthritis' => 'Αρθρίτιδα / Οστεοαρθρίτιδα', 'leishmaniasis' => 'Λεϊσμανίαση (Καλαζάρ)', 'urinary' => 'Ουρολογικά Προβλήματα (FLUTD)', 'kidney' => 'Χρόνια Νεφρική Ανεπάρκεια', 'dental' => 'Οδοντικά Προβλήματα', 'heart' => 'Καρδιολογικά Προβλήματα', 'obesity' => 'Παχυσαρκία', 'thyroid' => 'Θυρεοειδής', 'ear_infections'  => 'Συχνές Ωτίτιδες' ];

    ?>
    <style>
        .petling-crm-form input[type="text"], .petling-crm-form input[type="date"], .petling-crm-form input[type="number"], .petling-crm-form select, .petling-crm-form textarea { box-sizing: border-box !important; width: 100% !important; max-width: 100% !important; }
    </style>
    <form method="post" action="" class="petling-crm-form">
        <?php wp_nonce_field( 'petling_save_pets', 'petling_crm_nonce' ); ?>
        <h2 style="color: #43282F; margin-bottom: 20px;">Τα Κατοικίδιά μου 🐾</h2>
        
        <div style="background: #eef7ee; border: 2px solid #5b9a68; padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
            <label style="font-size: 1.1em; font-weight: bold; color: #333; display:block; margin-bottom: 10px;">🔔 Υπενθύμιση Αναπλήρωσης 🐾: Κάντε τις αγορές σας παιχνίδι!</label>
            <p style="font-size: 0.95em; color: #555; margin-bottom: 15px;">Επιλέξτε κάθε πότε θέλετε να σας θυμίζουμε να ανανεώσετε τα αποθέματα σας:</p>
            <select name="petling_order_reminder_interval" style="padding: 10px; font-size: 15px; border-radius: 5px; border: 1px solid #ccc; width: 100%; max-width: 300px; display: inline-block;">
                <option value="no" <?php selected($reminder_interval, 'no'); ?>>Όχι, ευχαριστώ</option>
                <option value="15" <?php selected($reminder_interval, '15'); ?>>Κάθε 15 μέρες</option>
                <option value="30" <?php selected($reminder_interval, '30'); ?>>Κάθε 1 Μήνα (30 μέρες)</option>
                <option value="45" <?php selected($reminder_interval, '45'); ?>>Κάθε 1.5 Μήνα (45 μέρες)</option>
                <option value="60" <?php selected($reminder_interval, '60'); ?>>Κάθε 2 Μήνες (60 μέρες)</option>
            </select>
            
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <div style="margin-top: 15px; border-top: 1px dashed #5b9a68; padding-top: 15px;">
                <button type="submit" name="petling_test_email_trigger" value="1" style="background:#43282F; color:#fff; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; font-size:14px; font-weight:bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">📧 Αποστολή Δοκιμαστικού Email Τώρα</button>
                <p style="font-size: 12px; color: #666; margin-top:8px;">(Ορατό <strong>ΜΟΝΟ</strong> σε Διαχειριστές. Θα σταλεί άμεσα στο <strong><?php echo wp_get_current_user()->user_email; ?></strong> για να δείτε πώς φαίνεται!)</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div id="pet-repeater-container">
            <?php if ( ! empty( $pets ) ) : foreach ( $pets as $index => $pet ) : 
                $p_id      = $pet['id'] ?? '';
                $p_name    = $pet['name'] ?? '';
                $p_type    = $pet['type'] ?? '';
                $p_bday    = $pet['birthday'] ?? '';
                $p_energy  = $pet['energy'] ?? '';
                $p_breed   = $pet['breed'] ?? '';
                $p_weight  = $pet['weight'] ?? '';
                $p_micro   = $pet['microchip'] ?? '';
                $p_dfood   = $pet['daily_food'] ?? '';
                $p_neut    = $pet['neutered'] ?? 'no';
                $p_health  = (isset($pet['health']) && is_array($pet['health'])) ? $pet['health'] : [];
                $p_notes   = $pet['health_notes'] ?? '';
            ?>
                <div class="pet-block" style="border: 2px solid #C7B297; padding: 25px; margin-bottom: 30px; border-radius: 10px; background: #fffaf1;">
                    <h4 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 1.2em; color: #43282F;">Κατοικίδιο: <?php echo esc_html($p_name); ?></span>
                        <button type="button" class="remove-pet-button" style="background: #e62121; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">Αφαίρεση</button>
                    </h4>
                    <input type="hidden" name="pet_id[]" value="<?php echo esc_attr( $p_id ); ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                        <p><label>Όνομα</label><br><input type="text" name="pet_name[]" value="<?php echo esc_attr( $p_name ); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                        <p><label>Τύπος</label><br><select name="pet_type[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="dog" <?php selected( $p_type, 'dog' ); ?>>Σκύλος</option><option value="cat" <?php selected( $p_type, 'cat' ); ?>>Γάτα</option></select></p>
                        <p><label>Ημερομηνία Γέννησης 🎉</label><br><input type="date" name="pet_birthday[]" value="<?php echo esc_attr( $p_bday ); ?>" max="<?php echo $today; ?>" min="<?php echo $min_date; ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                        <p><label>Επίπεδο Ενέργειας</label><br><select name="pet_energy[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $p_energy, $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                        <p><label>Φυλή</label><br><select name="pet_breed[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $p_breed, $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                        <p><label>Βάρος (κιλά)</label><br><input type="text" name="pet_weight[]" placeholder="π.χ. 25.5" value="<?php echo esc_attr( $p_weight ); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                        <p style="grid-column: span 2;"><label>Αριθμός Microchip</label><br><input type="text" name="pet_microchip[]" placeholder="15ψήφιος κωδικός" value="<?php echo esc_attr( $p_micro ); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                    </div>

                    <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (Προαιρετικό - για το Ιατρικό Ιστορικό)</label>
                        <div style="margin-top: 10px;">
                            <input type="number" name="pet_daily_food[]" placeholder="π.χ. 250 γρ." value="<?php echo esc_attr( $p_dfood ); ?>" style="width: 150px !important; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        </div>
                    </div>
                    
                    <div style="background: #fbfbfb; padding: 15px; border-radius: 5px; border: 1px solid #e5e5e5; margin-bottom: 20px;">
                        <label style="font-weight: bold; cursor: pointer; display: flex; align-items: center;">
                            <input type="hidden" name="pet_neutered[<?php echo $index; ?>]" value="no">
                            <input type="checkbox" name="pet_neutered[<?php echo $index; ?>]" value="yes" <?php checked( $p_neut, 'yes' ); ?> style="margin-right: 10px; width: 20px; height: 20px;"> Το κατοικίδιο είναι στειρωμένο
                        </label>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: bold;">Προβλήματα Υγείας</label>
                        <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 15px; background: #fff; border-radius: 5px; margin-top: 5px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <?php foreach ($health_issues as $key => $label) : ?>
                                <label><input type="checkbox" name="pet_health[<?php echo $index; ?>][]" value="<?php echo esc_attr($key); ?>" <?php checked( in_array( $key, $p_health ) ); ?>> <?php echo esc_html($label); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <p><label style="font-weight: bold;">Σημειώσεις</label><br><textarea name="pet_health_notes[]" rows="2" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><?php echo esc_textarea( $p_notes ); ?></textarea></p>
                    
                    <?php if ( !empty($p_id) ) : ?>
                        <div style="text-align: right; margin-top: 20px; border-top: 1px dashed #C7B297; padding-top: 15px;">
                            <a href="<?php echo esc_url( add_query_arg( array( 'view' => 'medical', 'pet_id' => $p_id ) ) ); ?>" class="petling-medical-btn" style="display: inline-block; background: #5b9a68; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">💉 Ιατρικό Ιστορικό & Εμβόλια &rarr;</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <button type="button" id="add-pet-button" style="background: #C7B297; color: #43282F; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer; font-weight: bold;">＋ Προσθήκη Ζώου</button>
        <hr style="margin: 40px 0;">
        <button type="submit" style="background: #C7B297; color: #43282F; border: none; padding: 15px 30px; font-size: 18px; font-weight: bold; border-radius: 5px; cursor: pointer; width: 100%;">Αποθήκευση Αλλαγών</button>
    </form>
    
    <div id="pet-block-template" style="display:none;">
        <div class="pet-block" style="border: 2px solid #C7B297; padding: 25px; margin-bottom: 30px; border-radius: 10px; background: #fffaf1;">
            <h4 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 1.2em; color: #43282F;">Νέο Ζώο</span>
                <button type="button" class="remove-pet-button" style="background: #e62121; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">Αφαίρεση</button>
            </h4>
            <input type="hidden" name="pet_id[]" value="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                <p><label>Όνομα</label><br><input type="text" name="pet_name[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                <p><label>Τύπος</label><br><select name="pet_type[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="dog">Σκύλος</option><option value="cat">Γάτα</option></select></p>
                <p><label>Ημερομηνία Γέννησης 🎉</label><br><input type="date" name="pet_birthday[]" max="<?php echo $today; ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                <p><label>Επίπεδο Ενέργειας</label><br><select name="pet_energy[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p>
                <p><label>Φυλή</label><br><select name="pet_breed[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p>
                <p><label>Βάρος (κιλά)</label><br><input type="text" name="pet_weight[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                <p style="grid-column: span 2;"><label>Αριθμός Microchip</label><br><input type="text" name="pet_microchip[]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
            </div>
            
            <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <label style="font-weight: bold; color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (Προαιρετικό)</label>
                <div style="margin-top: 10px;">
                    <input type="number" name="pet_daily_food[]" placeholder="π.χ. 250 γρ." style="width: 150px !important; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div style="background: #fbfbfb; padding: 15px; border-radius: 5px; border: 1px solid #e5e5e5; margin-bottom: 20px;">
                <label style="font-weight: bold; cursor: pointer; display: flex; align-items: center;">
                    <input type="hidden" name="pet_neutered[__INDEX__]" value="no">
                    <input type="checkbox" name="pet_neutered[__INDEX__]" value="yes" style="margin-right: 10px; width: 20px; height: 20px;"> Το κατοικίδιο είναι στειρωμένο
                </label>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-weight: bold;">Προβλήματα Υγείας</label>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 15px; background: #fff; border-radius: 5px; margin-top: 5px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <?php foreach ($health_issues as $key => $label) : ?>
                        <label><input type="checkbox" name="pet_health[__INDEX__][]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <p><label style="font-weight: bold;">Σημειώσεις</label><br><textarea name="pet_health_notes[]" rows="2" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea></p>
        </div>
    </div>
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
    <style>
        .petling-crm-form input[type="text"], .petling-crm-form input[type="date"], .petling-crm-form input[type="number"], .petling-crm-form select, .petling-crm-form textarea { box-sizing: border-box !important; width: 100% !important; max-width: 100% !important; }
        summary::marker { color: #C7B297; }
        .weight-timeline { display:flex; gap:15px; overflow-x:auto; padding-bottom:10px; margin-bottom:20px; }
        .weight-card { background:#fff; border:1px solid #C7B297; padding:10px 15px; border-radius:8px; min-width:90px; text-align:center; box-shadow:0 2px 5px rgba(0,0,0,0.05); position: relative; }
        .weight-del-btn { position: absolute; top: 2px; right: 6px; color: #999; text-decoration: none; font-size: 14px; line-height: 1; cursor: pointer; }
        .weight-del-btn:hover { color: #e62121; }
    </style>
    
    <div style="background: #eef7ee; border: 1px solid #5b9a68; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #333;">
        <strong>ℹ️ Πώς λειτουργεί:</strong> Προσθέστε εμβόλια και σημειώσεις ως <em>προσχέδιο</em>. Ανοίξτε το Vet Pass (QR Code) στον κτηνίατρο. Ο ιατρός θα μπορεί να τα διορθώσει, να τα επιβεβαιώσει και να τα κλειδώσει μόνιμα στο ιστορικό από το κινητό του (ο σύνδεσμος ισχύει για 24 ώρες).
    </div>

    <details style="background: linear-gradient(135deg, #43282F 0%, #2a191d 100%); border-radius: 15px; padding: 20px; margin-bottom: 40px; color: #fff; box-shadow: 0 10px 25px rgba(67, 40, 47, 0.2);">
        <summary style="font-size: 18px; font-weight: bold; cursor: pointer; outline: none; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">🐾 Εμφάνιση ψηφιακού Vet Pass & QR Code</summary>
        
        <div style="margin-top: 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 250px;">
                <h3 style="margin: 0; color: #fff; font-size: 24px;"><?php echo esc_html($pet['name']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #e0e0e0; font-size: 16px;">
                    <?php echo ($pet['type'] === 'dog' ? 'Σκύλος 🐶' : 'Γάτα 🐱'); ?> 
                    <?php if(!empty($pet['breed'])) echo ' | ' . esc_html($pet['breed']); ?>
                </p>
                <?php if(!empty($pet['microchip'])): ?>
                    <p style="margin: 5px 0 0 0; color: #ffffff !important; font-size: 14px;"><strong style="color: #ffffff !important;">Microchip:</strong> <?php echo esc_html($pet['microchip']); ?></p>
                <?php endif; ?>
            </div>
            
            <div style="background: #fff; padding: 15px; border-radius: 12px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
                <img src="<?php echo esc_url($qr_code_url); ?>" alt="Vet Pass QR Code" style="width: 180px; height: 180px; display: block; margin: 0 auto;">
            </div>
        </div>
    </details>

    <?php
    echo '<div style="display: grid; grid-template-columns: 1fr; gap: 40px;">';

    echo '<div style="border:1px solid #C7B297; padding:25px; border-radius:10px; background:#fff;" class="petling-crm-form">';
    echo '<h3 style="color:#43282F; margin-top:0; border-bottom:2px solid #C7B297; padding-bottom:10px;">📈 Ιστορικό Βάρους</h3>';

    $weight_history = [];
    foreach ($vet_notes as $n) {
        if (!empty($n->weight)) { $weight_history[] = array('id' => $n->id, 'date' => $n->created_at, 'weight' => $n->weight); }
    }
    if (!empty($weight_history)) {
        echo '<div class="weight-timeline">';
        foreach($weight_history as $wh) {
            $delete_weight_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_weight', 'weight_id' => $wh['id'] ) ), 'del_weight_' . $wh['id'] );
            
            echo '<div class="weight-card">';
            echo '<a href="' . esc_url($delete_weight_url) . '" class="weight-del-btn" onclick="return confirm(\'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτή τη ζύγιση;\');" title="Διαγραφή Ζύγισης">&times;</a>';
            echo '<div style="font-size:12px; color:#666; margin-bottom:4px; margin-top:6px;">' . date('d/m/Y', strtotime($wh['date'])) . '</div>';
            echo '<div style="font-weight:bold; color:#43282F; font-size:16px;">' . esc_html($wh['weight']) . ' <span style="font-size:12px;">kg</span></div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p style="font-style:italic; color:#666; margin-bottom:15px;">Δεν υπάρχουν ακόμα καταγραφές βάρους.</p>';
    }
    ?>
    <form method="post" action="" style="background:#fdfaf5; padding:15px; border-radius:8px; border:1px dashed #C7B297; display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
        <?php wp_nonce_field( 'petling_log_weight', 'petling_weight_nonce' ); ?>
        <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
        <div>
            <label style="font-size:13px; font-weight:bold; color:#555;">Νέα Ζύγιση (kg) *</label><br>
            <input type="number" step="0.01" name="weight" required style="width:120px !important; padding:8px; margin-top:5px;">
        </div>
        <button type="submit" style="background:#C7B297; color:#43282F; border:none; padding:10px 20px; border-radius:4px; font-weight:bold; cursor:pointer; height:38px;">Προσθήκη Βάρους</button>
    </form>
    <?php
    echo '</div>';

    echo '<div style="border:1px solid #C7B297; padding:25px; border-radius:10px; background:#fff;" class="petling-crm-form">';
    echo '<h3 style="color:#43282F; margin-top:0; border-bottom:2px solid #C7B297; padding-bottom:10px;">Ιστορικό Εμβολιασμών (WSAVA)</h3>';
    
    if ( $vaccines ) {
        echo '<table style="width:100%; border-collapse:collapse; margin-bottom:25px; font-size: 14px;">';
        echo '<tr style="background:#C7B297; color:#43282F;"><th style="padding:10px; text-align:left;">Εμβόλιο</th><th style="padding:10px; text-align:left;">Ημ. Εμβολιασμού</th><th style="padding:10px; text-align:left;">Επόμενο Εμβόλιο</th><th style="padding:10px; text-align:left;">Κατάσταση</th><th style="padding:10px; text-align:right;">Ενέργεια</th></tr>';
        foreach ( $vaccines as $vac ) {
            $is_verified = ($vac->status === 'verified');
            $status_html = $is_verified ? '<span style="color:#5b9a68; font-weight:bold;">✅ Επιβεβαιωμένο</span>' : '<span style="color:#e6a23c; font-weight:bold;">⏳ Προσχέδιο</span>';
            
            echo '<tr style="border-bottom:1px solid #ddd;">';
            echo '<td style="padding:10px;">' . esc_html($vac->vaccine_name) . '</td>';
            echo '<td style="padding:10px;">' . esc_html(date('d/m/Y', strtotime($vac->date_administered))) . '</td>';
            
            // ΚΟΥΜΠΙ ICS CALENDAR ΓΙΑ ΕΜΒΟΛΙΑ
            echo '<td style="padding:10px; font-weight:bold; color:#8B6139;">';
            if ( !empty($vac->next_vaccine_date) && $vac->next_vaccine_date !== '0000-00-00' && strtotime($vac->next_vaccine_date) > 0 ) {
                echo esc_html(date('d/m/Y', strtotime($vac->next_vaccine_date)));
                $ics_url = petling_crm_get_ics_link( '💉 Εμβολιασμός: ' . $vac->vaccine_name . ' (' . $pet['name'] . ')', $vac->next_vaccine_date, 'Υπενθύμιση από το Petling CRM για το επόμενο εμβόλιο του κατοικιδίου σας.' );
                echo '<br><a href="' . esc_url($ics_url) . '" style="font-size:11px; color:#5b9a68; text-decoration:none; font-weight:normal; display:inline-block; margin-top:4px;">📅 Προσθήκη στο Ημερολόγιο</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            
            echo '<td style="padding:10px;">' . $status_html . '</td>';
            echo '<td style="padding:10px; text-align:right;">';
            if ( !$is_verified ) {
                $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_vac', 'vac_id' => $vac->id ) ), 'del_vac_' . $vac->id );
                echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Είστε σίγουροι;\');" style="color:#e62121; text-decoration:none;">🗑️</a>';
            } else { echo '🔒'; }
            echo '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="font-style:italic; color:#666;">Δεν έχουν καταγραφεί ακόμα εμβόλια.</p>';
    }
    ?>
    <form method="post" action="" style="background:#fffaf1; padding:20px; border-radius:8px; border:1px dashed #C7B297; margin-top:15px;">
        <?php wp_nonce_field( 'petling_add_vaccine', 'petling_vaccine_nonce' ); ?>
        <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
        <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Προσθήκη Προσχεδίου Εμβολίου</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
            <p style="margin:0;"><label>Επιλογή Εμβολίου *</label><br>
                <select name="vaccine_name" required style="padding:8px;">
                    <?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?>
                </select>
            </p>
            <p style="margin:0;"><label>Κλινική (Προαιρετικό)</label><br><input type="text" name="vet_name" style="padding:8px;"></p>
            <p style="margin:0;"><label>Ημερομηνία Εμβολιασμού *</label><br><input type="date" name="date_administered" required value="<?php echo date('Y-m-d'); ?>" style="padding:8px;"></p>
            <p style="margin:0;"><label>Επόμενο Εμβόλιο (Υπενθύμιση) 📅</label><br><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>" style="padding:8px;"></p>
        </div>
        <p style="margin-top:15px; text-align:right; margin-bottom:0;"><button type="submit" style="background:#C7B297; color:#43282F; padding:8px 20px; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Αποθήκευση</button></p>
    </form>
    <?php
    echo '</div>';

    echo '<div style="border:1px solid #C7B297; padding:25px; border-radius:10px; background:#fff;" class="petling-crm-form">';
    echo '<h3 style="color:#43282F; margin-top:0; border-bottom:2px solid #C7B297; padding-bottom:10px;">Σημειώσεις Κτηνιάτρου & Επανεξετάσεις</h3>';

    if ( $vet_notes ) {
        echo '<div style="margin-bottom:25px;">';
        foreach ( $vet_notes as $note ) {
            $is_verified = ($note->status === 'verified');
            
            echo '<div style="background:#fdfdfd; border:1px solid #eee; padding:15px; border-radius:6px; margin-bottom:15px; border-left:5px solid '.($is_verified ? '#5b9a68' : '#e6a23c').';">';
            echo '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; border-bottom: 1px dashed #eee; padding-bottom: 10px;">';
            echo '  <div style="font-size:0.9em; color:#666; line-height:1.5;">';
            echo '    <span style="display:inline-block; margin-right:15px;"><strong>Ημερομηνία:</strong> ' . date('d/m/Y', strtotime($note->created_at)) . '</span>';
            if (!empty($note->weight)) { echo '<span style="display:inline-block; margin-right:15px;"><strong>Βάρος:</strong> ' . esc_html($note->weight) . ' kg</span>'; }
            if (!empty($note->vet_name)) { echo '<span style="display:inline-block; margin-right:15px;"><strong>Γιατρός:</strong> ' . esc_html($note->vet_name) . '</span>'; }
            echo '    ' . ($is_verified ? '<span style="color:#5b9a68; font-weight:bold;">✅ Verified</span>' : '<span style="color:#e6a23c; font-weight:bold;">⏳ Draft</span>');
            echo '  </div>';
            
            if ( !$is_verified ) {
                $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_note', 'note_id' => $note->id ) ), 'del_note_' . $note->id );
                echo '  <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Είστε σίγουροι;\');" style="color:#e62121; text-decoration:none; font-size:14px; white-space:nowrap; margin-left:15px; flex-shrink:0;">🗑️ Διαγραφή</a>';
            } else { echo '🔒'; }
            
            echo '</div>';
            echo '<p style="margin:0; font-size:1.05em; color:#333;">' . nl2br(esc_html($note->vet_comment)) . '</p>';
            
            // ΚΟΥΜΠΙ ICS CALENDAR ΓΙΑ ΕΠΑΝΕΞΕΤΑΣΗ
            if ( !empty($note->next_exam_date) && $note->next_exam_date !== '0000-00-00' && strtotime($note->next_exam_date) > 0 ) {
                echo '<p style="margin:10px 0 0 0; font-size:0.95em; color:#d63638; font-weight:bold;">📅 Επανεξέταση: ' . date('d/m/Y', strtotime($note->next_exam_date));
                $ics_url = petling_crm_get_ics_link( '🩺 Κτηνιατρική Επανεξέταση: ' . $pet['name'], $note->next_exam_date, 'Υπενθύμιση από το Petling CRM για την προγραμματισμένη επανεξέταση στην κλινική.' );
                echo ' <a href="' . esc_url($ics_url) . '" style="font-size:12px; color:#5b9a68; text-decoration:none; font-weight:normal; margin-left:10px; display:inline-block; border: 1px solid #5b9a68; padding: 2px 6px; border-radius: 4px; background: #fff;">🗓️ Προσθήκη στο Ημερολόγιο</a></p>';
            }
            
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p style="font-style:italic; color:#666; margin-bottom:25px;">Δεν υπάρχουν ακόμα ιατρικές σημειώσεις.</p>';
    }
    ?>
    <form method="post" action="" style="background:#fffaf1; padding:20px; border-radius:8px; border:1px dashed #C7B297;">
        <?php wp_nonce_field( 'petling_add_vet_note', 'petling_vet_note_nonce' ); ?>
        <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
        <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Προσθήκη Προσχεδίου Εξέτασης</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
            <p style="margin:0;"><label>Όνομα Κλινικής (Προαιρετικό)</label><br><input type="text" name="vet_name" style="padding:8px;"></p>
            <p style="margin:0;"><label>Χρειάζεται Επανεξέταση; Επιλέξτε Ημερομηνία 📅</label><br><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>" style="padding:8px;"></p>
            <p style="margin:0; grid-column: span 2;"><label>Σχόλια *</label><br><textarea name="vet_comment" required rows="3" style="padding:8px;"></textarea></p>
        </div>
        <p style="text-align:right; margin-bottom:0;"><button type="submit" style="background:#C7B297; color:#43282F; padding:8px 20px; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Αποθήκευση</button></p>
    </form>
    <?php
    echo '</div></div>';
}