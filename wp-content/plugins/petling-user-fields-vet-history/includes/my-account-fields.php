<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. ΔΗΜΙΟΥΡΓΙΑ ΝΕΟΥ ΕΝDPOINT (TAB) ΣΤΟ WOOCOMMERCE
add_action( 'init', 'petling_vet_history_add_endpoint' );
function petling_vet_history_add_endpoint() {
    add_rewrite_endpoint( 'my-pets', EP_ROOT | EP_PAGES );
}

// 2. ΠΡΟΣΘΗΚΗ ΤΟΥ ΝΕΟΥ ΜΕΝΟΥ ΣΤΗΝ ΠΛΑΪΝΗ ΜΠΑΡΑ
add_filter( 'woocommerce_account_menu_items', 'petling_vet_history_add_menu_item' );
function petling_vet_history_add_menu_item( $items ) {
    $user_id = get_current_user_id();
    if ( petling_vet_history_has_access( $user_id ) ) {
        $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : '';
        unset( $items['customer-logout'] );
        $items['my-pets'] = '🐾 Τα Κατοικίδιά μου';
        if ( $logout ) { $items['customer-logout'] = $logout; }
    }
    return $items;
}

// 3. ΕΜΦΑΝΙΣΗ ΠΕΡΙΕΧΟΜΕΝΟΥ ΣΤΟ ΝΕΟ TAB
add_action( 'woocommerce_account_my-pets_endpoint', 'petling_vet_history_endpoint_content' );
function petling_vet_history_endpoint_content() {
    $user_id = get_current_user_id();
    
    if ( ! petling_vet_history_has_access( $user_id ) ) {
        echo '<p>Δεν έχετε πρόσβαση σε αυτή τη λειτουργία (Beta).</p>';
        return;
    }

    // ΠΡΟΣΘΗΚΗ: Έλεγχος αν ο χρήστης άνοιξε το Ιατρικό Ιστορικό (view=medical)
    if ( isset( $_GET['view'] ) && $_GET['view'] === 'medical' && ! empty( $_GET['pet_id'] ) ) {
        $target_pet_id = sanitize_text_field( $_GET['pet_id'] );
        $pets = get_user_meta( $user_id, 'petling_pets', true );
        if ( is_array($pets) ) {
            foreach ( $pets as $pet ) {
                if ( isset($pet['id']) && $pet['id'] === $target_pet_id ) { 
                    petling_crm_render_medical_history( $pet ); 
                    return; // Σταματάμε εδώ για να μη δείξει τη λίστα των ζώων
                }
            }
        }
    }

    if ( isset( $_GET['saved'] ) && $_GET['saved'] == '1' ) {
        echo '<div class="woocommerce-message" role="alert">Τα στοιχεία των κατοικιδίων αποθηκεύτηκαν επιτυχώς!</div>';
    }

    $pets = get_user_meta( $user_id, 'petling_pets', true );
    if ( ! is_array( $pets ) ) { $pets = []; }
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

    <div class="petling-loader-overlay" id="petling-global-loader" style="display: none;">
        <div class="petling-spinner"></div>
        <p>Αποθήκευση...</p>
    </div>

    <form method="post" action="" class="petling-crm-form" id="petling-main-form">
        <?php wp_nonce_field( 'petling_save_pets_action', 'petling_save_pets_nonce' ); ?>
        
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
            <div id="reminder-save-warning" style="display:none; color:#d63638; font-size:13px; font-weight:bold; margin-top:10px;">⚠️ Μην ξεχάσετε να πατήσετε 'Αποθήκευση' στο κάτω μέρος!</div>
        </div>

        <h2 style="color: #43282F; margin-bottom: 10px;">Τα Κατοικίδιά μου 🐾</h2>
        
        <!-- ΜΠΑΡΑ ΜΕ ΤΑ TABS -->
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
        
        <!-- ΠΕΡΙΕΧΟΜΕΝΟ ΚΑΤΟΙΚΙΔΙΩΝ -->
        <div id="pet-repeater-container">
            <?php if ( ! empty( $pets ) ) : foreach ( $pets as $index => $pet ) : 
                $p_id      = $pet['id'] ?? uniqid('pet_');
                $p_name    = $pet['name'] ?? '';
                $p_type    = $pet['type'] ?? 'dog';
                $p_bday    = $pet['birthday'] ?? '';
                $p_energy  = $pet['energy'] ?? '';
                $p_weight  = $pet['weight'] ?? '';
                $p_micro   = $pet['microchip'] ?? '';
                $p_dfood   = $pet['daily_food'] ?? '';
                $p_calc    = $pet['calc_food'] ?? 'no';
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
                    
                    <div class="petling-grid">
                        <div><label>Όνομα *</label><input type="text" name="pet_name[]" class="pet-name-input" value="<?php echo esc_attr( $p_name ); ?>" required pattern="^[a-zA-Zα-ωΑ-ΩΆΈΉΊΌΎΏάέήίόύώϊϋϊϋ\s]+$" title="Παρακαλώ εισάγετε μόνο γράμματα"></div>
                        <div><label>Τύπος *</label><select name="pet_type[]" class="pet-type-select" required><option value="dog" <?php selected( $p_type, 'dog' ); ?>>Σκύλος</option><option value="cat" <?php selected( $p_type, 'cat' ); ?>>Γάτα</option></select></div>
                        <div><label>Ημερομηνία Γέννησης 🎉 *</label><input type="date" name="pet_birthday[]" value="<?php echo esc_attr( $p_bday ); ?>" required max="<?php echo $today; ?>" min="<?php echo $min_date; ?>"></div>
                        <div><label>Επίπεδο Ενέργειας</label><select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $p_energy, $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></div>
                        <div><label>Φυλή</label><select name="pet_breed[]"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected( $p_breed, $key, false ) . '>' . esc_html($label) . '</option>'; } ?></select></div>
                        <div><label>Βάρος (κιλά)</label><input type="number" step="0.01" min="0" name="pet_weight[]" placeholder="π.χ. 25.5" value="<?php echo esc_attr( $p_weight ); ?>"></div>
                        <div><label>Αριθμός Microchip</label><input type="text" name="pet_microchip[]" placeholder="15ψήφιος κωδικός" value="<?php echo esc_attr( $p_micro ); ?>" pattern="^\d{15}$" title="Το Microchip πρέπει να αποτελείται ακριβώς από 15 νούμερα"></div>
                    </div>

                    <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <label style="color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια)</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px; flex-wrap: wrap;">
                            <input type="number" name="pet_daily_food[]" placeholder="π.χ. 250" value="<?php echo esc_attr( $p_dfood ); ?>" style="max-width: 120px; margin: 0 !important;" min="0">
                            <span style="color:#555; font-weight:bold;">γρ.</span>
                            <label class="checkbox-label" style="display: inline-flex; align-items: center; font-size: 0.95em; cursor: pointer; margin: 0;">
                                <input type="hidden" name="pet_calc_food[<?php echo $index; ?>]" value="no">
                                <input type="checkbox" name="pet_calc_food[<?php echo $index; ?>]" value="yes" <?php checked( $p_calc, 'yes' ); ?> style="margin-right: 8px; width: auto; min-height: auto;">
                                🛒 Αγοράζω την τροφή του από το Petling
                            </label>
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

                    <?php 
                    // ΠΡΟΣΘΗΚΗ: Το κουμπί για το Ιατρικό Ιστορικό εμφανίζεται ΜΟΝΟ αν το ζώο έχει αποθηκευτεί και έχει ID
                    if ( !empty($p_id) && strpos($p_id, 'pet_') !== false ) : 
                        $medical_url = add_query_arg( array( 'view' => 'medical', 'pet_id' => $p_id ), wc_get_endpoint_url( 'my-pets', '', wc_get_page_permalink( 'myaccount' ) ) );
                    ?>
                        <div class="petling-medical-btn-wrapper" style="border-top: 1px dashed #C7B297; padding-top: 20px; margin-top: 10px;">
                            <a href="<?php echo esc_url($medical_url); ?>" class="btn-petling btn-green no-loader" style="width: 100%; max-width: 300px; margin: 0 auto; display: block;">💉 Ιατρικό Ιστορικό & Εμβόλια &rarr;</a>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; endif; ?>
        </div>

        <div style="text-align: center; margin-bottom: 40px;">
            <button type="button" id="add-pet-button" class="btn-petling btn-brown">＋ Προσθήκη Ζώου</button>
        </div>
        <button type="submit" class="btn-petling btn-brown" name="save_pets_data" value="Save" style="width: 100%; font-size: 18px; padding: 15px;">Αποθήκευση Αλλαγών</button>
    </form>
    
    <!-- TEMPLATE ΓΙΑ ΤΗ JAVASCRIPT (ΟΤΑΝ ΠΑΤΑΣ ΠΡΟΣΘΗΚΗ ΝΕΟΥ) -->
    <div id="pet-block-template" style="display:none;">
        <input type="hidden" name="pet_id[]" value="">
        <div class="petling-grid">
            <div><label>Όνομα *</label><input type="text" name="pet_name[]" class="pet-name-input" placeholder="Όνομα ζώου" required pattern="^[a-zA-Zα-ωΑ-ΩΆΈΉΊΌΎΏάέήίόύώϊϋϊϋ\s]+$" title="Παρακαλώ εισάγετε μόνο γράμματα"></div>
            <div><label>Τύπος *</label><select name="pet_type[]" class="pet-type-select" required><option value="dog">Σκύλος</option><option value="cat">Γάτα</option></select></div>
            <div><label>Ημερομηνία Γέννησης 🎉 *</label><input type="date" name="pet_birthday[]" required max="<?php echo $today; ?>"></div>
            <div><label>Επίπεδο Ενέργειας</label><select name="pet_energy[]"><option value="">— Επιλέξτε —</option><?php foreach ($energy_levels as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></div>
            <div><label>Φυλή</label><select name="pet_breed[]"><option value="">— Επιλέξτε —</option><?php foreach ($breeds as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; } ?></select></div>
            <div><label>Βάρος (κιλά)</label><input type="number" step="0.01" min="0" name="pet_weight[]"></div>
            <div><label>Αριθμός Microchip</label><input type="text" name="pet_microchip[]" pattern="^\d{15}$" title="Το Microchip πρέπει να αποτελείται ακριβώς από 15 νούμερα"></div>
        </div>
        
        <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 6px; margin-bottom: 20px; margin-top: 15px;">
            <label style="color: #8a6a43;">Ημερήσια Κατανάλωση Τροφής (σε γραμμάρια)</label>
            <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px; flex-wrap: wrap;">
                <input type="number" name="pet_daily_food[]" placeholder="π.χ. 250" style="max-width: 120px; margin: 0 !important;" min="0">
                <span style="color:#555; font-weight:bold;">γρ.</span>
                <label class="checkbox-label" style="display: inline-flex; align-items: center; font-size: 0.95em; cursor: pointer; margin: 0;">
                    <input type="hidden" name="pet_calc_food[__INDEX__]" value="no">
                    <input type="checkbox" name="pet_calc_food[__INDEX__]" value="yes" style="margin-right: 8px; width: auto; min-height: auto;"> 🛒 Αγοράζω την τροφή του από το Petling
                </label>
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

// 4. ΑΠΟΘΗΚΕΥΣΗ ΔΕΔΟΜΕΝΩΝ ΑΠΟ ΤΟ ΝΕΟ TAB
add_action( 'template_redirect', 'petling_vet_history_save_data' );
function petling_vet_history_save_data() {
    if ( isset( $_POST['save_pets_data'] ) && isset( $_POST['petling_save_pets_nonce'] ) && wp_verify_nonce( $_POST['petling_save_pets_nonce'], 'petling_save_pets_action' ) ) {
        $user_id = get_current_user_id();
        
        if ( ! petling_vet_history_has_access( $user_id ) ) return;

        update_user_meta( $user_id, 'petling_order_reminder_interval', sanitize_text_field( $_POST['petling_order_reminder_interval'] ?? 'no' ) );

        $pets_data = [];
        if ( ! empty( $_POST['pet_name'] ) && is_array( $_POST['pet_name'] ) ) {
            foreach ( $_POST['pet_name'] as $index => $name ) {
                if ( empty( trim( $name ) ) ) continue;
                $pets_data[] = [
                    'id'           => sanitize_text_field( $_POST['pet_id'][$index] ?? uniqid('pet_') ),
                    'name'         => sanitize_text_field( $name ),
                    'type'         => sanitize_text_field( $_POST['pet_type'][ $index ] ?? '' ),
                    'birthday'     => sanitize_text_field( $_POST['pet_birthday'][ $index ] ?? '' ),
                    'energy'       => sanitize_text_field( $_POST['pet_energy'][ $index ] ?? '' ),
                    'breed'        => sanitize_text_field( $_POST['pet_breed'][ $index ] ?? '' ),
                    'weight'       => sanitize_text_field( $_POST['pet_weight'][ $index ] ?? '' ),
                    'microchip'    => sanitize_text_field( $_POST['pet_microchip'][ $index ] ?? '' ),
                    'daily_food'   => sanitize_text_field( $_POST['pet_daily_food'][ $index ] ?? '' ),
                    'calc_food'    => sanitize_text_field( $_POST['pet_calc_food'][ $index ] ?? 'no' ),
                    'neutered'     => sanitize_text_field( $_POST['pet_neutered'][ $index ] ?? 'no' ), 
                    'health'       => isset( $_POST['pet_health'][ $index ] ) ? array_map( 'sanitize_text_field', (array) $_POST['pet_health'][ $index ] ) : [],
                    'health_notes' => sanitize_textarea_field( $_POST['pet_health_notes'][ $index ] ?? '' ),
                ];
            }
        }
        update_user_meta( $user_id, 'petling_pets', $pets_data );

        wp_safe_redirect( wc_get_endpoint_url( 'my-pets', '', wc_get_page_permalink( 'myaccount' ) ) . '?saved=1' );
        exit;
    }
}

// 5. ΠΡΟΣΘΗΚΗ ΣΤΗΛΗΣ "ΚΑΤΟΙΚΙΔΙΑ" ΣΤΟΥΣ ΧΡΗΣΤΕΣ ΣΤΟ ADMIN
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
            $output = '<div style="margin-bottom: 5px;"><strong>Σύνολο: ' . count($pets) . '</strong></div><ul style="margin:0; padding-left:15px; list-style-type: disc;">';
            foreach ( $pets as $pet ) {
                $type = isset($pet['type']) && $pet['type'] === 'cat' ? 'Γάτα' : 'Σκύλος';
                $name = !empty($pet['name']) ? esc_html($pet['name']) : 'Χωρίς όνομα';
                $output .= '<li style="margin-bottom:4px;"><strong>' . $name . '</strong> - ' . $type . '</li>';
            }
            return $output . '</ul>';
        }
        return '<span style="color:#999;">Κανένα</span>';
    }
    return $val;
}