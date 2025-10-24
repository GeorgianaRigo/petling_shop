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

    // --- ΟΡΙΖΟΥΜΕ ΤΑ ΟΡΙΑ ΓΙΑ ΤΟ DATE PICKER ---
    $today = date('Y-m-d');
    $thirty_years_ago = date('Y-m-d', strtotime('-30 years'));

    // --- Δεδομένα για τα Dropdowns ---
    $energy_levels = [ 'low' => 'Χαμηλή', 'medium' => 'Μέτρια', 'high' => 'Υψηλή' ];
    $breeds = [
        'amstaff' => 'American Staffordshire Terrier (Amstaff)', 'beagle' => 'Beagle', 'boxer' => 'Boxer', 'chihuahua' => 'Chihuahua', 'cocker_spaniel' => 'Cocker Spaniel', 'dachshund' => 'Dachshund (Λουκάνικο)', 'doberman' => 'Doberman', 'french_bulldog' => 'French Bulldog', 'german_shepherd' => 'German Shepherd (Γερμανικός Ποιμενικός)', 'golden_retriever' => 'Golden Retriever', 'griffon' => 'Griffon', 'jack_russell' => 'Jack Russell Terrier', 'kane_korso' => 'Cane Corso', 'labrador_retriever' => 'Labrador Retriever', 'maltese' => 'Maltese', 'poodle' => 'Poodle (Κανίς)', 'pomeranian' => 'Pomeranian', 'pug' => 'Pug', 'rottweiler' => 'Rottweiler', 'setter' => 'Setter', 'shih_tzu' => 'Shih Tzu', 'siberian_husky' => 'Siberian Husky', 'westie' => 'West Highland White Terrier (Westie)', 'yorkshire_terrier' => 'Yorkshire Terrier', 'greek_harehound' => 'Ελληνικός Ιχνηλάτης', 'greek_shepherd' => 'Ελληνικός Ποιμενικός', 'kokoni' => 'Κοκόνι', 'imichano_dog' => 'Ημίαιμο (Σκύλος)',
        'aegean' => 'Aegean (Γάτα του Αιγαίου)', 'bengal' => 'Bengal', 'birman' => 'Birman', 'british_shorthair' => 'British Shorthair', 'maine_coon' => 'Maine Coon', 'persian' => 'Persian (Περσίας)', 'ragdoll' => 'Ragdoll', 'siamese' => 'Siamese (Σιάμ)', 'sphynx' => 'Sphynx', 'imichani_cat' => 'Ημίαιμη (Γάτα)',
        'other' => 'Άλλη Φυλή (θα το διευκρινίσω στις σημειώσεις)',
    ];
    asort($breeds);
    $health_issues = [
        'allergies' => 'Αλλεργίες (Δερματικές/Τροφικές)', 'gastrointestinal'=> 'Γαστρεντερικές Ευαισθησίες', 'dysplasia' => 'Δυσπλασία Ισχίου/Αγκώνα', 'arthritis' => 'Αρθρίτιδα / Οστεοαρθρίτιδα', 'leishmaniasis' => 'Λεϊσμανίαση (Καλαζάρ)', 'urinary' => 'Ουρολογικά Προβλήματα (FLUTD)', 'kidney' => 'Χρόνια Νεφρική Ανεπάρκεια', 'dental' => 'Οδοντικά Προβλήματα', 'heart' => 'Καρδιολογικά Προβλήματα', 'obesity' => 'Τάση για Παχυσαρκία', 'thyroid' => 'Θυρεοειδής', 'ear_infections'  => 'Συχνές Ωτίτιδες',
    ];
    ?>
    <fieldset class="pet-details-fieldset">
        <legend><?php esc_html_e( 'Τα Κατοικίδιά μου 🐾', 'woocommerce' ); ?></legend>
        <p class="pet-fieldset-description">Συμπληρώστε το προφίλ των κατοικιδίων σας για να λαμβάνετε εξατομικευμένες προτάσεις και μοναδικά δώρα, φτιαγμένα ειδικά για εκείνα!</p>
        <div id="pet-repeater-container">
            <?php if ( ! empty( $pets ) ) : foreach ( $pets as $index => $pet ) : ?>
                <div class="pet-block">
                    <h4>Κατοικίδιο #<?php echo $index + 1; ?> <button type="button" class="button remove-pet-button">Αφαίρεση</button></h4>
                    <p class="form-row form-row-first"><label>Όνομα</label><input type="text" class="input-text" name="pet_name[]" value="<?php echo esc_attr( $pet['name'] ?? '' ); ?>"></p>
                    <p class="form-row form-row-last"><label>Τύπος</label><select name="pet_type[]"><option value="">— Επιλέξτε —</option><option value="dog" <?php selected( $pet['type'], 'dog' ); ?>>Σκύλος</option><option value="cat" <?php selected( $pet['type'], 'cat' ); ?>>Γάτα</option></select></p>
                    <div class="clear"></div>
                    <p class="form-row form-row-first"><label>Ημερομηνία Γέννησης</label><input type="date" class="input-text" name="pet_birthday[]" value="<?php echo esc_attr( $pet['birthday'] ?? '' ); ?>" max="<?php echo $today; ?>" min="<?php echo $thirty_years_ago; ?>"></p>
                    <p class="form-row form-row-last"><label>Επίπεδο Ενέργειας</label><select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $pet['energy'] ?? '', $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                    <div class="clear"></div>
                    <p class="form-row form-row-first"><label>Φυλή</label><select name="pet_breed[]"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $pet['breed'] ?? '', $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></p>
                    <p class="form-row form-row-last"><label>Βάρος (κιλά)</label><input type="text" class="input-text" name="pet_weight[]" placeholder="π.χ. 25.5" value="<?php echo esc_attr( $pet['weight'] ?? '' ); ?>"></p>
                    <div class="clear"></div>
                    <div class="form-row form-row-first"><label>Προβλήματα Υγείας (επιλογή)</label><div class="checkbox-wrapper"><?php foreach ($health_issues as $key => $label) : ?><label class="checkbox-label"><input type="checkbox" name="pet_health[<?php echo $index; ?>][]" value="<?php echo esc_attr($key); ?>" <?php checked( in_array( $key, $pet['health'] ?? [] ) ); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></div></div>
                    <p class="form-row form-row-last"><label>Σημειώσεις</label><textarea name="pet_health_notes[]" placeholder="Πείτε μας κάτι που κρίνετε χρήσιμο για το κατοικίδιο σας..." rows="4"><?php echo esc_textarea( $pet['health_notes'] ?? '' ); ?></textarea></p>
                    <div class="clear"></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <button type="button" id="add-pet-button" class="button"><?php esc_html_e( '＋ Προσθήκη Κατοικιδίου', 'woocommerce' ); ?></button>
    </fieldset>
    <div class="clear"></div>
    <div id="pet-block-template" style="display:none;">
        <div class="pet-block">
            <h4>Νέο Κατοικίδιο <button type="button" class="button remove-pet-button">Αφαίρεση</button></h4>
            <p class="form-row form-row-first"><label>Όνομα</label><input type="text" class="input-text" name="pet_name[]"></p>
            <p class="form-row form-row-last"><label>Τύπος</label><select name="pet_type[]"><option value="">— Επιλέξτε —</option><option value="dog">Σκύλος</option><option value="cat">Γάτα</option></select></p>
            <div class="clear"></div>
            <p class="form-row form-row-first"><label>Ημερομηνία Γέννησης</label><input type="date" class="input-text" name="pet_birthday[]" max="<?php echo $today; ?>" min="<?php echo $thirty_years_ago; ?>"></p>
            <p class="form-row form-row-last"><label>Επίπεδο Ενέργειας</label><select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p>
            <div class="clear"></div>
            <p class="form-row form-row-first"><label>Φυλή</label><select name="pet_breed[]"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></p>
            <p class="form-row form-row-last"><label>Βάρος (κιλά)</label><input type="text" class="input-text" name="pet_weight[]" placeholder="π.χ. 25.5"></p>
            <div class="clear"></div>
            <div class="form-row form-row-first"><label>Προβλήματα Υγείας</label><div class="checkbox-wrapper"><?php foreach ($health_issues as $key => $label) : ?><label class="checkbox-label"><input type="checkbox" name="pet_health[__INDEX__][]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label); ?></label><?php endforeach; ?></div></div>
            <p class="form-row form-row-last"><label>Σημειώσεις</label><textarea name="pet_health_notes[]" placeholder="Πείτε μας κάτι που κρίνετε χρήσιμο για το κατοικίδιο σας..." rows="4"></textarea></p>
            <div class="clear"></div>
        </div>
    </div>
    <?php
}

/**
 * 2. Save the custom pet profile fields when the user updates their account.
 */
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
                'health'       => isset( $_POST['pet_health'][ $index ] ) ? array_map( 'sanitize_text_field', (array) $_POST['pet_health'][ $index ] ) : [],
                'health_notes' => sanitize_textarea_field( $_POST['pet_health_notes'][ $index ] ?? '' ),
            ];
        }
    }
    update_user_meta( $user_id, 'petling_pets', $pets_data );
}