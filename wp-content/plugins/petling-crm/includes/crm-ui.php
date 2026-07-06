<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   ΕΝΟΤΗΤΑ 1: DATA HANDLING & FUNCTIONS (PHP Λογική)
   ========================================================================= */

// 1A. Δημιουργία .ICS Υπενθυμίσεων
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

// 1B. Αποθήκευση Βασικών Στοιχείων Κατοικιδίων
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
        wp_safe_redirect( wc_get_endpoint_url( 'pet-crm' ) ); exit;
    }
}

// 1C. Αποθήκευση/Διαγραφή Εμβολίων & Παρασίτων
add_action( 'template_redirect', 'petling_crm_handle_vaccines' );
function petling_crm_handle_vaccines() {
    global $wpdb;
    if ( isset( $_POST['petling_vaccine_nonce'] ) && wp_verify_nonce( $_POST['petling_vaccine_nonce'], 'petling_add_vaccine' ) ) {
        $pet_id = sanitize_text_field($_POST['pet_unique_id']);
        $v_name = sanitize_text_field($_POST['vaccine_name']);
        
        $parasite_options = array('Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)', 'Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)', 'Combo (Εσωτερικά & Εξωτερικά)');

        if (in_array($v_name, $parasite_options)) {
            $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s", $pet_id, $v_name) );
            $d_admin = date('Y-m-d'); 
        } else {
            $d_admin = sanitize_text_field($_POST['date_administered']);
        }

        $wpdb->insert( $wpdb->prefix . 'petling_vaccines', array( 
            'pet_unique_id' => $pet_id, 
            'vaccine_name' => $v_name, 
            'date_administered' => $d_admin, 
            'next_vaccine_date' => sanitize_text_field($_POST['next_vaccine_date']), 
            'vet_name' => sanitize_text_field($_POST['vet_name']), 
            'status' => 'draft', 
            'created_by' => 'owner' 
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'pet-crm' ) ) ); exit;
    }
    
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_vac' && isset( $_GET['vac_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_vac_' . $_GET['vac_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'vac_id', '_wpnonce' ) ) ); exit;
    }
}

// 1D. Αποθήκευση/Διαγραφή Σημειώσεων & Βάρους
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
        if ( !empty($new_weight) ) {
            $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'weight' => $new_weight, 'vet_comment' => 'Τακτική Ζύγιση (Κηδεμόνας)', 'status' => 'verified', 'created_by' => 'owner' ), array( '%s', '%f', '%s', '%s', '%s' ) );
            $user_id = get_current_user_id();
            $pets_array = get_user_meta($user_id, 'petling_pets', true);
            if (is_array($pets_array)) {
                foreach ($pets_array as &$p) { if ($p['id'] === $pet_id) { $p['weight'] = $new_weight; break; } }
                update_user_meta($user_id, 'petling_pets', $pets_array);
            }
        }
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'pet-crm' ) ) ); exit;
    }
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_note' && isset( $_GET['note_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_note_' . $_GET['note_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'note_id', '_wpnonce' ) ) ); exit;
    }
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_weight' && isset( $_GET['weight_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_weight_' . $_GET['weight_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['weight_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'weight_id', '_wpnonce' ) ) ); exit;
    }
}


/* =========================================================================
   ΕΝΟΤΗΤΑ 2: HTML VIEWS (Φόρμες Πελάτη)
   ========================================================================= */

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
            <label style="font-size: 1.1em; font-weight: bold; color: #333; display:block; margin-bottom: 10px; text-align:center;">🔔 Υπενθύμιση Αναπλήρωσης 🐾</label>
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
                $p_weight  = $pet['weight'] ?? '';
                $p_micro   = $pet['microchip'] ?? '';
                $p_dfood   = $pet['daily_food'] ?? '';
                $p_neut    = $pet['neutered'] ?? 'no';
                $p_health  = (isset($pet['health']) && is_array($pet['health'])) ? $pet['health'] : [];
                $p_notes   = $pet['health_notes'] ?? '';
                $p_breed   = $pet['breed'] ?? '';
                
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
    <?php
}


/* =========================================================================
   ΕΝΟΤΗΤΑ 3: HTML VIEW (Ιατρικό Ιστορικό Πελάτη)
   ========================================================================= */

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
                        echo '<ul class="weight-compact-list">';
                        
                        foreach($weights as $wh) {
                            $item_display = ($global_index >= 5) ? 'display:none;' : '';
                            $delete_weight_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_weight', 'weight_id' => $wh['id'] ) ), 'del_weight_' . $wh['id'] );
                            
                            echo '<li class="weight-compact-item weight-item-row" style="'.$item_display.'">';
                            echo '<span class="w-date">' . date('d/m/Y', strtotime($wh['date'])) . '</span>';
                            echo '<span class="w-val">' . esc_html($wh['weight']) . ' <span style="font-size:13px; font-weight:normal;">kg</span></span>';
                            echo '<a href="' . esc_url($delete_weight_url) . '" class="w-del" onclick="return confirm(\'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτή τη ζύγιση;\');" title="Διαγραφή Ζύγισης">&times;</a>';
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
                    <button type="submit" class="btn-petling btn-brown" style="height:42px; width: 100%; max-width: 200px;">Προσθήκη Βάρους</button>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>💉 Εμβολιασμοί (WSAVA)</summary>
            <div class="accordion-content">
                <?php if ( $vaccines ) : ?>
                    <table class="petling-table">
                        <thead><tr><th>Εμβόλιο</th><th>Ημ. Εμβολιασμού</th><th>Επόμενο Εμβόλιο</th><th>Κατάσταση</th><th style="text-align:center;">Ενέργεια</th></tr></thead>
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
                                    echo '<br><a href="' . esc_url($ics_url) . '" style="font-size:12px; color:#5b9a68; text-decoration:none; font-weight:normal; display:inline-block; margin-top:5px; background:#eef7ee; padding:3px 8px; border-radius:4px;">📅 Προσθήκη</a>';
                                else : echo '-'; endif; ?>
                            </td>
                            <td data-label="Κατάσταση:"><?php echo $status_html; ?></td>
                            <td data-label="Ενέργεια:" style="text-align:center;">
                                <?php if ( !$is_verified ) : 
                                    $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_vac', 'vac_id' => $vac->id ) ), 'del_vac_' . $vac->id );
                                    echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Διαγραφή προσχεδίου;\');" style="color:#e62121; font-size:18px;">🗑️</a>';
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
                    <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Προετοιμασία Εγγραφής</h4>
                    <div class="petling-grid">
                        <div><label>Επιλογή Εμβολίου *</label><select name="vaccine_name" required><?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?></select></div>
                        <div><label>Κλινική (Προαιρετικό)</label><input type="text" name="vet_name"></div>
                        <div><label>Ημερομηνία Εμβολιασμού *</label><input type="date" name="date_administered" required value="<?php echo date('Y-m-d'); ?>"></div>
                        <div><label>Επόμενο Εμβόλιο (Υπενθύμιση) 📅</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>"></div>
                    </div>
                    <div style="text-align:right; margin-top:15px;"><button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:200px;">Αποθήκευση</button></div>
                </form>
            </div>
        </details>

        <details class="petling-accordion petling-crm-form">
            <summary>🪱 Τρέχουσα Αποπαρασίτωση</summary>
            <div class="accordion-content">
                <div style="background:#fff3cd; border-left:4px solid #ffeeba; padding:10px 15px; margin-bottom:20px; font-size:13px; color:#856404; border-radius:4px; line-height:1.5;">
                    <strong>ℹ️ Πώς λειτουργεί:</strong> Όταν προσθέτετε μια νέα προστασία ίδιου τύπου, η προηγούμενη αντικαθίσταται αυτόματα. Επίσης, καταγραφές αποπαρασίτωσης <strong>παλαιότερες του 1 έτους</strong> διαγράφονται οριστικά.
                </div>

                <?php if ( $parasites ) : ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-bottom: 25px;">
                    <?php foreach ( $parasites as $vac ) : ?>
                        <div style="background:#fffaf1; border:2px solid #e2d4c0; padding:15px; border-radius:10px; position:relative;">
                            <?php $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_vac', 'vac_id' => $vac->id ) ), 'del_vac_' . $vac->id ); ?>
                            <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Διαγραφή αυτής της προστασίας;');" style="position:absolute; top:10px; right:15px; color:#e62121; text-decoration:none; font-size:20px; font-weight:bold;">&times;</a>
                            <h4 style="margin:0 0 10px 0; color:#43282F; font-size:16px; padding-right:20px; border-bottom:1px solid #e2d4c0; padding-bottom:8px;"><?php echo esc_html($vac->vaccine_name); ?></h4>
                            <p style="margin:5px 0; font-size:14px;"><strong>Σκεύασμα:</strong> <?php echo esc_html($vac->vet_name ? $vac->vet_name : '-'); ?></p>
                            
                            <?php if ( !empty($vac->next_vaccine_date) && $vac->next_vaccine_date !== '0000-00-00' && strtotime($vac->next_vaccine_date) > 0 ) : ?>
                                <p style="margin:10px 0 5px 0; color:#d63638; font-weight:bold; font-size:14px;">📅 Επόμενη Δόση: <?php echo esc_html(date('d/m/Y', strtotime($vac->next_vaccine_date))); ?></p>
                                <?php $ics_url = petling_crm_get_ics_link( '🪱 Αποπαρασίτωση: ' . $vac->vaccine_name . ' (' . $pet['name'] . ')', $vac->next_vaccine_date, 'Υπενθύμιση από το Petling CRM.' ); ?>
                                <a href="' . esc_url($ics_url) . '" style="display:inline-block; font-size:12px; color:#5b9a68; text-decoration:none; font-weight:bold; border: 1px solid #5b9a68; padding: 4px 8px; border-radius: 4px; background: #fff; margin-top:5px;">🗓️ Προσθήκη Υπενθύμισης</a>
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
                    <div style="text-align:right; margin-top:15px;"><button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:200px;">Αποθήκευση</button></div>
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
                                    echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Διαγραφή σημείωσης;\');" style="color:#e62121; font-size:18px; margin-left:10px;">🗑️</a>';
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
                    <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Προετοιμασία Εγγραφής</h4>
                    <div class="petling-grid">
                        <div><label>Όνομα Κλινικής (Προαιρετικό)</label><input type="text" name="vet_name"></div>
                        <div><label>Επανεξέταση 📅</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>"></div>
                        <div class="petling-grid-full"><label>Σχόλια / Διάγνωση *</label><textarea name="vet_comment" required></textarea></div>
                    </div>
                    <div style="text-align:right; margin-top:15px;"><button type="submit" class="btn-petling btn-brown" style="width:100%; max-width:200px;">Αποθήκευση</button></div>
                </form>
            </div>
        </details>
    </div>
    <?php
}