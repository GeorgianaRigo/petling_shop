<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. ΑΠΟΘΗΚΕΥΣΗ ΒΑΣΙΚΩΝ ΣΤΟΙΧΕΙΩΝ ΚΑΤΟΙΚΙΔΙΩΝ
 */
add_action( 'template_redirect', 'petling_crm_save_pets' );
function petling_crm_save_pets() {
    if ( isset( $_POST['petling_crm_nonce'] ) && wp_verify_nonce( $_POST['petling_crm_nonce'], 'petling_save_pets' ) ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        update_user_meta( $user_id, 'petling_global_food_consent', sanitize_text_field( $_POST['petling_global_food_consent'] ?? 'no' ) );

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
                    'daily_food'   => sanitize_text_field( $_POST['pet_daily_food'][ $index ] ?? '' ),
                    'calc_food'    => sanitize_text_field( $_POST['pet_calc_food'][ $index ] ?? 'no' ),
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

/**
 * 2. ΑΠΟΘΗΚΕΥΣΗ & ΔΙΑΓΡΑΦΗ ΕΜΒΟΛΙΟΥ
 */
add_action( 'template_redirect', 'petling_crm_handle_vaccines' );
function petling_crm_handle_vaccines() {
    global $wpdb;
    
    // Προσθήκη
    if ( isset( $_POST['petling_vaccine_nonce'] ) && wp_verify_nonce( $_POST['petling_vaccine_nonce'], 'petling_add_vaccine' ) ) {
        $pet_id            = sanitize_text_field( $_POST['pet_unique_id'] );
        $vaccine_name      = sanitize_text_field( $_POST['vaccine_name'] );
        $date_administered = sanitize_text_field( $_POST['date_administered'] );
        $next_vaccine_date = sanitize_text_field( $_POST['next_vaccine_date'] );
        $vet_name          = sanitize_text_field( $_POST['vet_name'] );

        $wpdb->insert(
            $wpdb->prefix . 'petling_vaccines',
            array( 'pet_unique_id' => $pet_id, 'vaccine_name' => $vaccine_name, 'date_administered' => $date_administered, 'next_vaccine_date' => $next_vaccine_date, 'vet_name' => $vet_name ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'pet-crm' ) ) );
        exit;
    }

    // Διαγραφή σε περίπτωση λάθους
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_vac' && isset( $_GET['vac_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_vac_' . $_GET['vac_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'vac_id', '_wpnonce' ) ) );
        exit;
    }
}

/**
 * 3. ΑΠΟΘΗΚΕΥΣΗ & ΔΙΑΓΡΑΦΗ ΙΑΤΡΙΚΗΣ ΣΗΜΕΙΩΣΗΣ
 */
add_action( 'template_redirect', 'petling_crm_handle_vet_notes' );
function petling_crm_handle_vet_notes() {
    global $wpdb;

    // Προσθήκη
    if ( isset( $_POST['petling_vet_note_nonce'] ) && wp_verify_nonce( $_POST['petling_vet_note_nonce'], 'petling_add_vet_note' ) ) {
        $pet_id          = sanitize_text_field( $_POST['pet_unique_id'] );
        $weight          = sanitize_text_field( $_POST['weight'] );
        $vet_comment     = sanitize_textarea_field( $_POST['vet_comment'] );
        $next_exam_date  = sanitize_text_field( $_POST['next_exam_date'] );
        $vet_name        = sanitize_text_field( $_POST['vet_name'] );

        $wpdb->insert(
            $wpdb->prefix . 'petling_vet_notes',
            array( 'pet_unique_id' => $pet_id, 'weight' => $weight, 'vet_comment' => $vet_comment, 'next_exam_date'=> $next_exam_date, 'vet_name' => $vet_name ),
            array( '%s', '%f', '%s', '%s', '%s' )
        );
        wp_safe_redirect( add_query_arg( array( 'view' => 'medical', 'pet_id' => $pet_id ), wc_get_endpoint_url( 'pet-crm' ) ) );
        exit;
    }

    // Διαγραφή σε περίπτωση λάθους
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'del_note' && isset( $_GET['note_id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'del_note_' . $_GET['note_id'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ) ) );
        wp_safe_redirect( remove_query_arg( array( 'action', 'note_id', '_wpnonce' ) ) );
        exit;
    }
}

/**
 * 4. ΚΕΝΤΡΙΚΟΣ ΜΗΧΑΝΙΣΜΟΣ ΕΜΦΑΝΙΣΗΣ ENDPOINT
 */
add_action( 'woocommerce_account_pet-crm_endpoint', 'petling_crm_endpoint_content' );
function petling_crm_endpoint_content() {
    $user_id = get_current_user_id();
    $pets = get_user_meta( $user_id, 'petling_pets', true );
    if ( ! is_array( $pets ) ) { $pets = []; }

    // Έλεγχος για το Ιατρικό Ιστορικό
    if ( isset( $_GET['view'] ) && $_GET['view'] === 'medical' && ! empty( $_GET['pet_id'] ) ) {
        $target_pet_id = sanitize_text_field( $_GET['pet_id'] );
        $current_pet = null;
        foreach ( $pets as $pet ) {
            if ( isset($pet['id']) && $pet['id'] === $target_pet_id ) {
                $current_pet = $pet;
                break;
            }
        }
        if ( $current_pet ) {
            petling_crm_render_medical_history( $current_pet );
            return;
        }
    }

    $global_consent = get_user_meta( $user_id, 'petling_global_food_consent', true );
    $today = date('Y-m-d');
    $min_date = date('Y-m-d', strtotime('-30 years'));
    
    $energy_levels = [ 'low' => 'Χαμηλή', 'medium' => 'Μέτρια', 'high' => 'Υψηλή' ];
    $breeds = [
        'amstaff' => 'American Staffordshire Terrier (Amstaff)', 'beagle' => 'Beagle', 'boxer' => 'Boxer', 'chihuahua' => 'Chihuahua', 'cocker_spaniel' => 'Cocker Spaniel', 'dachshund' => 'Dachshund (Λουκάνικο)', 'doberman' => 'Doberman', 'french_bulldog' => 'French Bulldog', 'german_shepherd' => 'German Shepherd (Γερμανικός Ποιμενικός)', 'golden_retriever' => 'Golden Retriever', 'griffon' => 'Griffon', 'jack_russell' => 'Jack Russell Terrier', 'kane_korso' => 'Cane Corso', 'labrador_retriever' => 'Labrador Retriever', 'maltese' => 'Maltese', 'poodle' => 'Poodle (Κανίς)', 'pomeranian' => 'Pomeranian', 'pug' => 'Pug', 'rottweiler' => 'Rottweiler', 'setter' => 'Setter', 'shih_tzu' => 'Shih Tzu', 'siberian_husky' => 'Siberian Husky', 'westie' => 'West Highland White Terrier (Westie)', 'yorkshire_terrier' => 'Yorkshire Terrier', 'greek_harehound' => 'Ελληνικός Ιχνηλάτης', 'greek_shepherd' => 'Ελληνικός Ποιμενικός', 'kokoni' => 'Κοκόνι', 'imichano_dog' => 'Ημίαιμο (Σκύλος)',
        'aegean' => 'Aegean (Γάτα του Αιγαίου)', 'bengal' => 'Bengal', 'birman' => 'Birman', 'british_shorthair' => 'British Shorthair', 'maine_coon' => 'Maine Coon', 'persian' => 'Persian (Περσίας)', 'ragdoll' => 'Ragdoll', 'siamese' => 'Siamese (Σιάμ)', 'sphynx' => 'Sphynx', 'imichani_cat' => 'Ημίαιμη (Γάτα)',
        'other' => 'Άλλη Φυλή',
    ];
    asort($breeds);
    $health_issues = [
        'allergies' => 'Αλλεργίες (Δερματικές/Τροφικές)', 'gastrointestinal'=> 'Γαστρεντερικές Ευαισθησίες', 'dysplasia' => 'Δυσπλασία Ισχίου/Αγκώνα', 'arthritis' => 'Αρθρίτιδα / Οστεοαρθρίτιδα', 'leishmaniasis' => 'Λεϊσμανίαση (Καλαζάρ)', 'urinary' => 'Ουρολογικά Προβλήματα (FLUTD)', 'kidney' => 'Χρόνια Νεφρική Ανεπάρκεια', 'dental' => 'Οδοντικά Προβλήματα', 'heart' => 'Καρδιολογικά Προβλήματα', 'obesity' => 'Παχυσαρκία', 'thyroid' => 'Θυρεοειδής', 'ear_infections'  => 'Συχνές Ωτίτιδες',
    ];

    ?>
    <form method="post" action="" class="petling-crm-form">
        <?php wp_nonce_field( 'petling_save_pets', 'petling_crm_nonce' ); ?>
        <h2 style="color: #43282F; margin-bottom: 20px;">Τα Κατοικίδιά μου 🐾</h2>
        
        <div style="background: #eef7ee; border: 2px solid #5b9a68; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <label style="display: flex; align-items: center; font-size: 1.1em; font-weight: bold; cursor: pointer; color: #333;">
                <input type="hidden" name="petling_global_food_consent" value="no">
                <input type="checkbox" name="petling_global_food_consent" value="yes" <?php checked( $global_consent, 'yes' ); ?> style="width: 25px; height: 25px; margin-right: 15px;">
                🔔 Ναι, θέλω έξυπνες ειδοποιήσεις όταν κοντεύει να τελειώσει η τροφή τους!
            </label>
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
                $p_dfood   = $pet['daily_food'] ?? '';
                $p_cfood   = $pet['calc_food'] ?? 'no';
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
                        <p><label>Όνομα</label><br><input type="text" name="pet_name[]" value="<?php echo esc_attr( $p_name ); ?>" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                        <p><label>Τύπος</label><br><select name="pet_type[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="dog" <?php selected( $p_type, 'dog' ); ?>>Σκύλος</option><option value="cat" <?php selected( $p_type, 'cat' ); ?>>Γάτα</option></select></p>
                        <p><label>Ημερομηνία Γέννησης 🎉</label><br><input type="date" name="pet_birthday[]" value="<?php echo esc_attr( $p_bday ); ?>" max="<?php echo $today; ?>" min="<?php echo $min_date; ?>" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                        <p><label>Επίπεδο Ενέργειας</label><br><select name="pet_energy[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $p_energy, $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                        <p><label>Φυλή</label><br><select name="pet_breed[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $p_breed, $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                        <p><label>Βάρος (κιλά)</label><br><input type="text" name="pet_weight[]" placeholder="π.χ. 25.5" value="<?php echo esc_attr( $p_weight ); ?>" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                    </div>

                    <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια) 🍲</label>
                        <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                            <input type="number" name="pet_daily_food[]" value="<?php echo esc_attr( $p_dfood ); ?>" style="width: 120px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <label style="cursor: pointer;">
                                <input type="hidden" name="pet_calc_food[<?php echo $index; ?>]" value="no">
                                <input type="checkbox" name="pet_calc_food[<?php echo $index; ?>]" value="yes" <?php checked( $p_cfood, 'yes' ); ?>> 🛒 Αγοράζω την τροφή του από το Petling
                            </label>
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
                    
                    <p><label style="font-weight: bold;">Σημειώσεις</label><br><textarea name="pet_health_notes[]" rows="2" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><?php echo esc_textarea( $p_notes ); ?></textarea></p>
                    
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
                <p><label>Όνομα</label><br><input type="text" name="pet_name[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                <p><label>Τύπος</label><br><select name="pet_type[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="dog">Σκύλος</option><option value="cat">Γάτα</option></select></p>
                <p><label>Ημερομηνία Γέννησης 🎉</label><br><input type="date" name="pet_birthday[]" max="<?php echo $today; ?>" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
                <p><label>Επίπεδο Ενέργειας</label><br><select name="pet_energy[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p>
                <p><label>Φυλή</label><br><select name="pet_breed[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p>
                <p><label>Βάρος (κιλά)</label><br><input type="text" name="pet_weight[]" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></p>
            </div>
            
            <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <label style="font-weight: bold; color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια) 🍲</label>
                <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                    <input type="number" name="pet_daily_food[]" style="width: 120px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <label style="cursor: pointer;">
                        <input type="hidden" name="pet_calc_food[__INDEX__]" value="no">
                        <input type="checkbox" name="pet_calc_food[__INDEX__]" value="yes"> 🛒 Αγοράζω την τροφή του από το Petling
                    </label>
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
            
            <p><label style="font-weight: bold;">Σημειώσεις</label><br><textarea name="pet_health_notes[]" rows="2" style="width:100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea></p>
        </div>
    </div>
    <?php
}

/**
 * 5. ΝΕΑ ΟΘΟΝΗ: ΠΡΟΒΟΛΗ ΙΑΤΡΙΚΟΥ ΙΣΤΟΡΙΚΟΥ & ΕΜΒΟΛΙΩΝ
 */
function petling_crm_render_medical_history( $pet ) {
    global $wpdb;
    $pet_id = $pet['id'] ?? '';
    $pet_name = $pet['name'] ?? '';
    $pet_type = $pet['type'] ?? '';

    $vaccines = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s ORDER BY date_administered DESC", $pet_id ) );
    $vet_notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vet_notes WHERE pet_unique_id = %s ORDER BY created_at DESC", $pet_id ) );

    if ( $pet_type === 'dog' ) {
        $vaccine_options = array( 'Πολυδύναμο', 'Λύσσας', 'Καλαζάρ (Leishmania)', 'Βήχας Κυνοκομείου', 'Νόσος του Lyme', 'Άλλο' );
    } else {
        $vaccine_options = array( 'Τριπλό (Πανλευκοπενία/Καλικοϊός/Ερπητοϊός)', 'Λύσσας', 'Λευχαιμία (FeLV)', 'Περιτονίτιδα (FIP)', 'Άλλο' );
    }

    echo '<a href="' . esc_url( remove_query_arg( array( 'view', 'pet_id' ) ) ) . '" style="display:inline-block; margin-bottom:20px; font-weight:bold; color:#8B6139; text-decoration:none;">&larr; Πίσω στα Κατοικίδιά μου</a>';
    echo '<h2 style="color: #43282F; margin-bottom: 30px;">🩺 Ιατρική Καρτέλα: ' . esc_html($pet_name) . ' (' . ($pet_type === 'dog' ? 'Σκύλος 🐶' : 'Γάτα 🐱') . ')</h2>';

    echo '<div style="display: grid; grid-template-columns: 1fr; gap: 40px;">';

    echo '<div style="border:1px solid #C7B297; padding:25px; border-radius:10px; background:#fff;">';
    echo '<h3 style="color:#43282F; margin-top:0; border-bottom:2px solid #C7B297; padding-bottom:10px;">Ιστορικό Εμβολιασμών</h3>';
    
    if ( $vaccines ) {
        echo '<table style="width:100%; border-collapse:collapse; margin-bottom:25px;">';
        echo '<tr style="background:#C7B297; color:#43282F;"><th style="padding:10px; text-align:left;">Εμβόλιο</th><th style="padding:10px; text-align:left;">Ημ. Εμβολιασμού</th><th style="padding:10px; text-align:left;">Επόμενο Εμβόλιο</th><th style="padding:10px; text-align:left;">Κτηνίατρος</th><th style="padding:10px; text-align:right;">Ενέργεια</th></tr>';
        foreach ( $vaccines as $vac ) {
            $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_vac', 'vac_id' => $vac->id ) ), 'del_vac_' . $vac->id );
            
            echo '<tr style="border-bottom:1px solid #ddd;">';
            echo '<td style="padding:10px;">' . esc_html($vac->vaccine_name) . '</td>';
            echo '<td style="padding:10px;">' . esc_html(date('d/m/Y', strtotime($vac->date_administered))) . '</td>';
            echo '<td style="padding:10px; font-weight:bold; color:#8B6139;">' . ( !empty($vac->next_vaccine_date) ? esc_html(date('d/m/Y', strtotime($vac->next_vaccine_date))) : '-' ) . '</td>';
            echo '<td style="padding:10px;">' . esc_html($vac->vet_name) . '</td>';
            echo '<td style="padding:10px; text-align:right;"><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Είστε σίγουροι;\');" style="color:#e62121; text-decoration:none; font-size:14px;">🗑️ Διαγραφή</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="font-style:italic; color:#666;">Δεν έχουν καταγραφεί ακόμα εμβόλια.</p>';
    }

    ?>
    <form method="post" action="" style="background:#fffaf1; padding:20px; border-radius:8px; border:1px dashed #C7B297; margin-top:15px;">
        <?php wp_nonce_field( 'petling_add_vaccine', 'petling_vaccine_nonce' ); ?>
        <input type="hidden" name="pet_unique_id" value="<?php echo esc_attr($pet_id); ?>">
        <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Καταχώρηση Νέου Εμβολίου</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
            <p style="margin:0;"><label>Επιλογή Εμβολίου *</label><br>
                <select name="vaccine_name" required style="width:100%; padding:8px;">
                    <?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?>
                </select>
            </p>
            <p style="margin:0;"><label>Όνομα Κτηνιάτρου / Κλινικής *</label><br><input type="text" name="vet_name" required style="width:100%; padding:8px;"></p>
            <p style="margin:0;"><label>Ημερομηνία Εμβολιασμού *</label><br><input type="date" name="date_administered" required value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px;"></p>
            <p style="margin:0;"><label>Επόμενος Εμβολιασμός (Υπενθύμιση) 📅</label><br><input type="date" name="next_vaccine_date" style="width:100%; padding:8px;"></p>
        </div>
        <p style="margin-top:15px; text-align:right; margin-bottom:0;"><button type="submit" style="background:#C7B297; color:#43282F; padding:8px 20px; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Προσθήκη Εμβολίου</button></p>
    </form>
    <?php
    echo '</div>';

    echo '<div style="border:1px solid #C7B297; padding:25px; border-radius:10px; background:#fff;">';
    echo '<h3 style="color:#43282F; margin-top:0; border-bottom:2px solid #C7B297; padding-bottom:10px;">Σημειώσεις Κτηνιάτρου & Επανεξετάσεις</h3>';

    if ( $vet_notes ) {
        echo '<div style="margin-bottom:25px;">';
        foreach ( $vet_notes as $note ) {
            $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'del_note', 'note_id' => $note->id ) ), 'del_note_' . $note->id );
            
            echo '<div style="background:#fdfdfd; border:1px solid #eee; padding:15px; border-radius:6px; margin-bottom:15px; border-left:5px solid #5b9a68;">';
            // Flex container για να μην πέφτει το κουμπί διαγραφής πάνω στο κείμενο
            echo '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; border-bottom: 1px dashed #eee; padding-bottom: 10px;">';
            echo '  <div style="font-size:0.9em; color:#666; line-height:1.5;">';
            echo '    <span style="display:inline-block; margin-right:15px;"><strong>Ημερομηνία:</strong> ' . date('d/m/Y', strtotime($note->created_at)) . '</span>';
            echo '    <span style="display:inline-block; margin-right:15px;"><strong>Βάρος:</strong> ' . esc_html($note->weight) . ' kg</span>';
            echo '    <span style="display:inline-block;"><strong>Γιατρός:</strong> ' . esc_html($note->vet_name) . '</span>';
            echo '  </div>';
            echo '  <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Είστε σίγουροι;\');" style="color:#e62121; text-decoration:none; font-size:14px; white-space:nowrap; margin-left:15px; flex-shrink:0;">🗑️ Διαγραφή</a>';
            echo '</div>';
            
            echo '<p style="margin:0; font-size:1.05em; color:#333;">' . nl2br(esc_html($note->vet_comment)) . '</p>';
            if ( !empty($note->next_exam_date) ) {
                echo '<p style="margin:10px 0 0 0; font-size:0.95em; color:#d63638; font-weight:bold;">📅 Επανεξέταση: ' . date('d/m/Y', strtotime($note->next_exam_date)) . '</p>';
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
        <h4 style="margin:0 0 15px 0; color:#43282F;">➕ Προσθήκη Ιατρικού Συμβάντος / Σημείωσης</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
            <p style="margin:0;"><label>Τρέχον Βάρος (σε κιλά)</label><br><input type="text" name="weight" placeholder="π.χ. 26.2" style="width:100%; padding:8px;"></p>
            <p style="margin:0;"><label>Όνομα Κτηνιάτρου / Κλινικής *</label><br><input type="text" name="vet_name" required style="width:100%; padding:8px;"></p>
            <p style="margin:0; grid-column: span 2;"><label>Σχόλια / Διάγνωση / Φαρμακευτική Αγωγή *</label><br><textarea name="vet_comment" required rows="3" style="width:100%; padding:8px;"></textarea></p>
            <p style="margin:0; grid-column: span 2;"><label>Χρειάζεται Επανεξέταση; Επιλέξτε Ημερομηνία 📅</label><br><input type="date" name="next_exam_date" style="width:100%; padding:8px;"></p>
        </div>
        <p style="text-align:right; margin-bottom:0;"><button type="submit" style="background:#C7B297; color:#43282F; padding:8px 20px; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Αποθήκευση Σημείωσης</button></p>
    </form>
    <?php
    echo '</div></div>';
}