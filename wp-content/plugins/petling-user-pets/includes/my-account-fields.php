<?php
// Αποτρέπει την απευθείας πρόσβαση στο αρχείο για λόγους ασφαλείας.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Εμφανίζει τα custom πεδία για το προφίλ των κατοικιδίων στη σελίδα του λογαριασμού.
 */
add_action( 'woocommerce_edit_account_form', 'petling_add_multi_pet_profile_fields' );
function petling_add_multi_pet_profile_fields() {
    $user_id = get_current_user_id();
    $pets = get_user_meta( $user_id, 'petling_pets', true );
    if ( ! is_array( $pets ) ) { $pets = []; }
    
    // Η γενική επιλογή συναίνεσης (Global Consent)
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
    <fieldset class="pet-details-fieldset">
        <legend><?php esc_html_e( 'Τα Κατοικίδιά μου 🐾', 'woocommerce' ); ?></legend>
        <p class="pet-fieldset-description">Συμπληρώστε τα στοιχεία των φίλων σας για να σας εξυπηρετούμε καλύτερα!</p>
        
        <div style="background: #eef7ee; border: 2px solid #5b9a68; padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
            <label class="checkbox-label" style="display: inline-flex; align-items: center; font-size: 1.2em; font-weight: bold; cursor: pointer; color: #333;">
                <input type="hidden" name="petling_global_food_consent" value="no">
                <input type="checkbox" name="petling_global_food_consent" value="yes" <?php checked( $global_consent, 'yes' ); ?> style="width: 25px; height: 25px; margin-right: 15px; cursor: pointer;">
                🔔 Ναι, θέλω έξυπνες ειδοποιήσεις όταν κοντεύει να τελειώσει η τροφή τους!
            </label>
            <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #555;">(Το σύστημα θα υπολογίζει αυτόματα πότε πρέπει να παραγγείλετε ξανά, βάσει των γραμμαρίων που καταναλώνουν)</p>
        </div>
        
        <div id="pet-repeater-container">
            <?php if ( ! empty( $pets ) ) : foreach ( $pets as $index => $pet ) : ?>
                <div class="pet-block">
                    <h4>Κατοικίδιο #<?php echo $index + 1; ?> <button type="button" class="button remove-pet-button">Αφαίρεση</button></h4>
                    
                    <p class="form-row form-row-first"><label>Όνομα</label>
                    <input type="text" class="input-text" name="pet_name[]" value="<?php echo esc_attr( $pet['name'] ?? '' ); ?>"></p>
                    
                    <p class="form-row form-row-last"><label>Τύπος</label>
                    <select name="pet_type[]"><option value="">— Επιλέξτε —</option><option value="dog" <?php selected( $pet['type'], 'dog' ); ?>>Σκύλος</option><option value="cat" <?php selected( $pet['type'], 'cat' ); ?>>Γάτα</option></select></p>
                    <div class="clear"></div>

                    <p class="form-row form-row-first"><label>Ημερομηνία Γέννησης 🎉</label>
                    <input type="date" class="input-text" name="pet_birthday[]" value="<?php echo esc_attr( $pet['birthday'] ?? '' ); ?>" max="<?php echo $today; ?>" min="<?php echo $min_date; ?>"></p>
                    
                    <p class="form-row form-row-last"><label>Επίπεδο Ενέργειας</label>
                    <select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $pet['energy'] ?? '', $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                    <div class="clear"></div>

                    <p class="form-row form-row-first"><label>Φυλή</label>
                    <select name="pet_breed[]"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $pet['breed'] ?? '', $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                    
                    <p class="form-row form-row-last">
                        <label>Βάρος (κιλά)</label>
                        <input type="text" class="input-text" name="pet_weight[]" placeholder="π.χ. 25.5" value="<?php echo esc_attr( $pet['weight'] ?? '' ); ?>">
                    </p>
                    <div class="clear"></div>

                    <div class="form-row form-row-wide" style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <label style="font-weight: bold; color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια) 🍲</label>
                        <span style="display: block; font-size: 0.85em; color: #666; margin-bottom: 12px;">Συμπληρώστε τη δόση σύμφωνα με τη συσκευασία της τροφής ή βάσει οδηγίας του κτηνιάτρου σας (π.χ. για διαχείριση βάρους).</span>
                        
                        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">
                            <input type="number" class="input-text" name="pet_daily_food[]" placeholder="π.χ. 250" value="<?php echo esc_attr( $pet['daily_food'] ?? '' ); ?>" style="width: 120px;">
                            
                            <label class="checkbox-label" style="display: inline-flex; align-items: center; font-size: 0.95em; cursor: pointer; margin: 0;">
                                <input type="hidden" name="pet_calc_food[<?php echo $index; ?>]" value="no">
                                <input type="checkbox" name="pet_calc_food[<?php echo $index; ?>]" value="yes" <?php checked( isset($pet['calc_food']) && $pet['calc_food'] === 'yes' ); ?> style="margin-right: 8px; width: auto; min-height: auto;">
                                🛒 Αγοράζω την τροφή του από το Petling (Να συμπεριλαμβάνεται στους υπολογισμούς)
                            </label>
                        </div>
                    </div>
                    <div class="clear"></div>

                    <p class="form-row form-row-wide" style="background: #fbfbfb; padding: 15px; border-radius: 5px; border: 1px solid #e5e5e5; margin-bottom: 20px;">
                        <label class="checkbox-label" style="display: flex; align-items: center; font-size: 1.05em; font-weight: bold; margin: 0; cursor: pointer;">
                            <input type="hidden" name="pet_neutered[<?php echo $index; ?>]" value="no">
                            <input type="checkbox" name="pet_neutered[<?php echo $index; ?>]" value="yes" <?php checked( isset($pet['neutered']) && $pet['neutered'] === 'yes' ); ?> style="width: 22px; height: 22px; margin-right: 12px; margin-top: 0; cursor: pointer; flex-shrink: 0;"> 
                            Το κατοικίδιο είναι στειρωμένο
                        </label>
                    </p>

                    <div class="form-row form-row-wide">
                        <label>Προβλήματα Υγείας (επιλογή)</label>
                        <div class="checkbox-wrapper">
                            <?php foreach ($health_issues as $key => $label) : ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="pet_health[<?php echo $index; ?>][]" value="<?php echo esc_attr($key); ?>" <?php checked( in_array( $key, $pet['health'] ?? [] ) ); ?>> 
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <p class="form-row form-row-wide"><label>Σημειώσεις</label>
                    <textarea name="pet_health_notes[]" placeholder="Πείτε μας κάτι που κρίνετε χρήσιμο..." rows="3"><?php echo esc_textarea( $pet['health_notes'] ?? '' ); ?></textarea></p>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <button type="button" id="add-pet-button" class="button">＋ Προσθήκη Κατοικιδίου</button>
    </fieldset>
    
    <div id="pet-block-template" style="display:none;">
        <div class="pet-block">
            <h4>Νέο Κατοικίδιο <button type="button" class="button remove-pet-button">Αφαίρεση</button></h4>
            <p class="form-row form-row-first"><label>Όνομα</label><input type="text" class="input-text" name="pet_name[]"></p>
            <p class="form-row form-row-last"><label>Τύπος</label><select name="pet_type[]"><option value="">— Επιλέξτε —</option><option value="dog">Σκύλος</option><option value="cat">Γάτα</option></select></p><div class="clear"></div>
            <p class="form-row form-row-first"><label>Ημερομηνία Γέννησης 🎉</label><input type="date" class="input-text" name="pet_birthday[]" max="<?php echo $today; ?>"></p>
            <p class="form-row form-row-last"><label>Επίπεδο Ενέργειας</label><select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p><div class="clear"></div>
            <p class="form-row form-row-first"><label>Φυλή</label><select name="pet_breed[]"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p>
            <p class="form-row form-row-last"><label>Βάρος (κιλά)</label><input type="text" class="input-text" name="pet_weight[]"></p><div class="clear"></div>

            <div class="form-row form-row-wide" style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <label style="font-weight: bold; color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια) 🍲</label>
                <span style="display: block; font-size: 0.85em; color: #666; margin-bottom: 12px;">Συμπληρώστε τη δόση σύμφωνα με τη συσκευασία ή βάσει οδηγίας του κτηνιάτρου σας.</span>
                
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <input type="number" class="input-text" name="pet_daily_food[]" placeholder="π.χ. 250" style="width: 120px;">
                    
                    <label class="checkbox-label" style="display: inline-flex; align-items: center; font-size: 0.95em; cursor: pointer; margin: 0;">
                        <input type="hidden" name="pet_calc_food[__INDEX__]" value="no">
                        <input type="checkbox" name="pet_calc_food[__INDEX__]" value="yes" style="margin-right: 8px; width: auto; min-height: auto;">
                        🛒 Αγοράζω την τροφή του από το Petling (Να συμπεριλαμβάνεται στους υπολογισμούς)
                    </label>
                </div>
            </div>
            <div class="clear"></div>
            
            <p class="form-row form-row-wide" style="background: #fbfbfb; padding: 15px; border-radius: 5px; border: 1px solid #e5e5e5; margin-bottom: 20px;">
                <label class="checkbox-label" style="display: flex; align-items: center; font-size: 1.05em; font-weight: bold; margin: 0; cursor: pointer;">
                    <input type="hidden" name="pet_neutered[__INDEX__]" value="no">
                    <input type="checkbox" name="pet_neutered[__INDEX__]" value="yes" style="width: 22px; height: 22px; margin-right: 12px; margin-top: 0; cursor: pointer; flex-shrink: 0;"> 
                    Το κατοικίδιο είναι στειρωμένο
                </label>
            </p>

            <div class="form-row form-row-wide"><label>Προβλήματα Υγείας</label><div class="checkbox-wrapper"><?php foreach ($health_issues as $key => $label) : ?><label class="checkbox-label"><input type="checkbox" name="pet_health[__INDEX__][]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label); ?></label><?php endforeach; ?></div></div>
            <p class="form-row form-row-wide"><label>Σημειώσεις</label><textarea name="pet_health_notes[]" rows="3"></textarea></p>
        </div>
    </div>
    <?php
}

/**
 * 2. Αποθήκευση των δεδομένων των κατοικιδίων.
 */
add_action( 'woocommerce_save_account_details', 'petling_save_multi_pet_profile_fields' );
function petling_save_multi_pet_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return false;

    // Αποθήκευση της Γενικής Συναίνεσης Ειδοποιήσεων
    update_user_meta( $user_id, 'petling_global_food_consent', sanitize_text_field( $_POST['petling_global_food_consent'] ?? 'no' ) );

    $pets_data = [];
    if ( ! empty( $_POST['pet_name'] ) && is_array( $_POST['pet_name'] ) ) {
        foreach ( $_POST['pet_name'] as $index => $name ) {
            if ( empty( trim( $name ) ) ) continue;
            $pets_data[] = [
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
}

/**
 * 3. Προσθήκη στήλης 'Κατοικίδια' στη λίστα χρηστών του WordPress Admin Dashboard.
 */
add_filter( 'manage_users_columns', 'petling_add_pets_column' );
function petling_add_pets_column( $columns ) {
    $columns['petling_pets'] = 'Κατοικίδια';
    return $columns;
}

add_filter( 'manage_users_custom_column', 'petling_show_pets_column_content', 10, 3 );
function petling_show_pets_column_content( $val, $column_name, $user_id ) {
    if ( 'petling_pets' === $column_name ) {
        $pets = get_user_meta( $user_id, 'petling_pets', true );
        
        if ( ! empty( $pets ) && is_array( $pets ) ) {
            $total_pets = count( $pets );
            $type_mapping = ['dog' => 'Σκύλος', 'cat' => 'Γάτα'];
            
            $health_mapping = [
                'allergies' => 'Αλλεργίες', 'gastrointestinal'=> 'Γαστρεντερικά', 'dysplasia' => 'Δυσπλασία', 
                'arthritis' => 'Αρθρίτιδα', 'leishmaniasis' => 'Καλαζάρ', 'urinary' => 'Ουρολογικά', 
                'kidney' => 'Νεφρικά', 'dental' => 'Οδοντικά', 'heart' => 'Καρδιολογικά', 
                'obesity' => 'Παχυσαρκία', 'thyroid' => 'Θυρεοειδής', 'ear_infections'  => 'Ωτίτιδες',
            ];

            $output = '<div style="margin-bottom: 5px;"><strong>Σύνολο: ' . $total_pets . '</strong></div>';
            $output .= '<ul style="margin:0; padding-left:15px; list-style-type: disc;">';
            
            foreach ( $pets as $pet ) {
                $raw_type = isset($pet['type']) ? $pet['type'] : '';
                $type = isset($type_mapping[$raw_type]) ? $type_mapping[$raw_type] : (!empty($raw_type) ? esc_html($raw_type) : 'Άγνωστο');
                $name = ! empty($pet['name']) ? esc_html($pet['name']) : 'Χωρίς όνομα';
                
                $neutered_text = '';
                if ( isset($pet['neutered']) && $pet['neutered'] === 'yes' ) {
                    $neutered_text = ' <span style="color: #2ea2cc; font-size: 0.9em;">(Στειρωμένο ✂️)</span>';
                }

                $health_text = '';
                if ( ! empty( $pet['health'] ) && is_array( $pet['health'] ) ) {
                    $health_labels = [];
                    foreach ( $pet['health'] as $h_key ) {
                        if ( isset( $health_mapping[$h_key] ) ) {
                            $health_labels[] = $health_mapping[$h_key];
                        }
                    }
                    if ( ! empty( $health_labels ) ) {
                        $health_text = '<br><span style="color: #d63638; font-size: 0.85em; display: inline-block; margin-top: 2px;">⚠️ Υγεία: ' . esc_html( implode(', ', $health_labels) ) . '</span>';
                    }
                }

                $output .= '<li style="margin-bottom:8px; line-height: 1.3;"><strong>' . $name . '</strong> - ' . $type . $neutered_text . $health_text . '</li>';
            }
            $output .= '</ul>';
            return $output;
        }
        return '<span style="color:#999;">Κανένα</span>';
    }
    return $val;
}