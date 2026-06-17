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
        'allergies' => 'Αλλεργίες (Δερματικές/Τροφικές)', 'gastrointestinal'=> 'Γαστρεντερικές Ευαισθησίες', 'dysplasia' => 'Δυσπλασία Ισχίου/Αγκώνα', 'arthritis' => 'Αρθρίτιδα / Οστεοαρθρίτιδα', 'leishmaniasis' => 'Λεϊσμανίαση (Καλαζάρ)', 'urinary' => 'Ουρολογικά Προβλήματα (FLUTD)', 'kidney' => 'Χρόνια Νεφρική Ανεπάρκεια', 'dental' => 'Οδοντικά Προβλήματα', 'heart' => 'Καρδιολογικά Προβλήματα', 'obesity' => 'Τάση για Παχυσαρκία', 'thyroid' => 'Θυρεοειδής', 'ear_infections'  => 'Συχνές Ωτίτιδες',
    ];
    ?>
    <fieldset class="pet-details-fieldset">
        <legend><?php esc_html_e( 'Τα Κατοικίδιά μου 🐾', 'woocommerce' ); ?></legend>
        <p class="pet-fieldset-description">Συμπληρώστε τα γενέθλια του φίλου σας για να του στέλνουμε δωράκια!</p>
        
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

                    <p class="form-row form-row-wide" style="background: #fbfbfb; padding: 15px; border-radius: 5px; border: 1px solid #e5e5e5; margin-bottom: 20px; margin-top: 10px;">
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
            <p class="form-row form-row-last"><label>Βάρος (κιλά)</label><input type="text" class="input-text" name="pet_weight[]"></p>
            <div class="clear"></div>
            
            <p class="form-row form-row-wide" style="background: #fbfbfb; padding: 15px; border-radius: 5px; border: 1px solid #e5e5e5; margin-bottom: 20px; margin-top: 10px;">
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

add_action( 'woocommerce_save_account_details', 'petling_save_multi_pet_profile_fields' );
function petling_save_multi_pet_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return false;

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
                'neutered'     => sanitize_text_field( $_POST['pet_neutered'][ $index ] ?? 'no' ), 
                'health'       => isset( $_POST['pet_health'][ $index ] ) ? array_map( 'sanitize_text_field', (array) $_POST['pet_health'][ $index ] ) : [],
                'health_notes' => sanitize_textarea_field( $_POST['pet_health_notes'][ $index ] ?? '' ),
            ];
        }
    }
    update_user_meta( $user_id, 'petling_pets', $pets_data );
}

// 1. Προσθήκη στήλης 'Κατοικίδια' στη λίστα χρηστών του WordPress
add_filter( 'manage_users_columns', 'petling_add_pets_column' );
function petling_add_pets_column( $columns ) {
    $columns['petling_pets'] = 'Κατοικίδια';
    return $columns;
}

// 2. Εμφάνιση των δεδομένων και καταμέτρηση στη νέα στήλη
add_filter( 'manage_users_custom_column', 'petling_show_pets_column_content', 10, 3 );
function petling_show_pets_column_content( $val, $column_name, $user_id ) {
    if ( 'petling_pets' === $column_name ) {
        $pets = get_user_meta( $user_id, 'petling_pets', true );
        
        if ( ! empty( $pets ) && is_array( $pets ) ) {
            $total_pets = count( $pets );
            
            $type_mapping = ['dog' => 'Σκύλος', 'cat' => 'Γάτα'];
            
            // Μετάφραση προβλημάτων υγείας
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

                // Χτίσιμο κειμένου Προβλημάτων Υγείας
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