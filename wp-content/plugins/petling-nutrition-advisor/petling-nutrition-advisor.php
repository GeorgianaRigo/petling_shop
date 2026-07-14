<?php
/*
Plugin Name: Petling Nutrition Advisor
Plugin URI: https://petling.gr
Description: Διαδραστικός Διατροφικός Σύμβουλος (Quiz). ΣΗΜΑΝΤΙΚΟ: Για να αποθηκεύονται οι VET κωδικοί κτηνιάτρου κεντρικά και να λειτουργεί η σελίδα εξαργύρωσης, πρέπει να είναι ΕΝΕΡΓΟΠΟΙΗΜΕΝΟ και το plugin "Petling Partners Promo".
Version: 1.7
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 0. ΕΞΑΣΦΑΛΙΣΗ ΚΕΝΤΡΙΚΟΥ ΜΕΝΟΥ PETLING 🐾
// =========================================================================
add_action( 'admin_menu', 'ptl_quiz_ensure_admin_menu', 9 );
function ptl_quiz_ensure_admin_menu() {
    // Ελέγχουμε αν υπάρχει ήδη η "Πατούσα" (από τα άλλα plugins). Αν όχι, τη φτιάχνουμε!
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'ptl_quiz_fallback_page', 'dashicons-pets', 55 );
    }
}
function ptl_quiz_fallback_page() {
    echo '<div class="wrap"><h1 style="color:#43282F;"><span class="dashicons dashicons-pets" style="font-size:32px; width:32px; height:32px;"></span> Petling Control Panel</h1><p>Επιλέξτε ένα εργαλείο από το μενού στα αριστερά.</p></div>';
}

// =========================================================================
// 1. ΤΑ ΔΕΔΟΜΕΝΑ ΜΑΣ (Ο "ΕΓΚΕΦΑΛΟΣ")
// =========================================================================
function ptl_get_yoggies_data() {
    return array(
        'dog' => array(
            'dog_active_duck_venison' => array(
                'title'          => 'Active Κρέας Πάπιας, Κυνήγι & Προβιοτικά',
                'url'            => home_url('/product/xira-trofi-skylou-active-kreas-papias-kynigi-3/'),
                'allergens'      => array( 'duck', 'venison', 'poultry', 'fish_meat', 'egg', 'shellfish' ),
                'activity_level' => 'high'
            ),
            'dog_chicken_beef' => array(
                'title'          => 'Κοτόπουλο, Βόειο Κρέας & Προβιοτικά',
                'url'            => home_url('/?s=Κοτόπουλο+Βόειο+Κρέας+Προβιοτικά&post_type=product'),
                'allergens'      => array( 'chicken', 'beef', 'poultry', 'fish_meat', 'corn', 'shellfish' ),
                'activity_level' => 'normal'
            ),
            'dog_lamb_fish' => array(
                'title'          => 'Αρνί Γάλακτος, Λευκό Ψάρι & Προβιοτικά',
                'url'            => home_url('/?s=Αρνί+Λευκό+Ψάρι+Προβιοτικά&post_type=product'),
                'allergens'      => array( 'lamb', 'fish_meat' ),
                'activity_level' => 'normal'
            ),
            'dog_turkey_millet' => array(
                'title'          => 'Γαλοπούλα, Κεχρί & Προβιοτικά (Μονοπρωτεϊνική)',
                'url'            => home_url('/?s=Γαλοπούλα+Κεχρί+Προβιοτικά&post_type=product'),
                'allergens'      => array( 'turkey', 'poultry', 'fish_oil' ),
                'activity_level' => 'low'
            ),
            'dog_iberian_pork' => array(
                'title'          => 'Ιβηρικό Χοιρινό, Μήλα & Προβιοτικά',
                'url'            => home_url('/?s=Ιβηρικό+Χοιρινό+Μήλα&post_type=product'),
                'allergens'      => array( 'pork', 'fish_oil' ),
                'activity_level' => 'normal'
            ),
            'dog_goat' => array(
                'title'          => 'Κατσικίσιο Κρέας με Λαχανικά (Μονοπρωτεϊνική)',
                'url'            => home_url('/?s=Κατσικίσιο+Κρέας+Λαχανικά&post_type=product'),
                'allergens'      => array( 'goat', 'fish_oil', 'egg', 'shellfish' ),
                'activity_level' => 'low'
            ),
            'dog_vet_gastro_turkey' => array(
                'title'          => 'VET Gastro Sensitive με Γαλοπούλα',
                'url'            => home_url('/?s=VET+Gastro+Sensitive+Γαλοπούλα&post_type=product'),
                'allergens'      => array( 'turkey', 'poultry', 'fish_oil' ),
                'activity_level' => 'normal'
            ),
            'dog_vet_insect' => array(
                'title'          => 'VET Insect Derma (Πρωτεΐνη Εντόμων)',
                'url'            => home_url('/?s=VET+Insect+Derma&post_type=product'),
                'allergens'      => array( 'insect', 'fish_oil' ),
                'activity_level' => 'normal'
            ),
            'dog_vet_veggie' => array(
                'title'          => 'VET Veggie, Χωρίς Κρέας',
                'url'            => home_url('/?s=VET+Veggie&post_type=product'),
                'allergens'      => array( 'meat' ),
                'activity_level' => 'normal'
            )
        ),
        'cat' => array(
            'cat_chicken' => array(
                'title'          => 'Κοτόπουλο & Προβιοτικά (Γάτας)',
                'url'            => home_url('/?s=Τροφή+Γάτας+Κοτόπουλο+Προβιοτικά&post_type=product'),
                'allergens'      => array( 'chicken', 'poultry', 'fish_oil' ),
                'activity_level' => 'normal'
            ),
            'cat_turkey' => array(
                'title'          => 'Γαλοπούλα & Προβιοτικά (Γάτας)',
                'url'            => home_url('/?s=Τροφή+Γάτας+Γαλοπούλα+Προβιοτικά&post_type=product'),
                'allergens'      => array( 'turkey', 'poultry', 'fish_oil' ),
                'activity_level' => 'normal'
            ),
            'cat_lamb_fish' => array(
                'title'          => 'Αρνί Γάλακτος, Λευκό Ψάρι & Προβιοτικά (Γάτας)',
                'url'            => home_url('/?s=Τροφή+Γάτας+Αρνί+Ψάρι&post_type=product'),
                'allergens'      => array( 'lamb', 'fish_meat' ),
                'activity_level' => 'normal'
            )
        )
    );
}

// =========================================================================
// 2. ΔΗΜΙΟΥΡΓΙΑ ΤΟΥ MINI-CRM (ΜΟΝΟ ΓΙΑ ΤΑ LEADS ΤΟΥ QUIZ)
// =========================================================================
add_action('init', 'ptl_register_leads_cpt');
function ptl_register_leads_cpt() {
    register_post_type('ptl_quiz_lead', array(
        'labels' => array( 
            'name' => 'Quiz Leads', 
            'singular_name' => 'Quiz Lead',
            'all_items' => 'Quiz Leads' // Το όνομα που θα φαίνεται στο Dropdown
        ),
        'public' => false, 
        'show_ui' => true, 
        'show_in_menu' => 'petling-main', // <--- ΤΟ ΜΥΣΤΙΚΟ! Κουμπώνει κάτω από την πατούσα
        'supports' => array('title')
    ));
}

add_filter('manage_ptl_quiz_lead_posts_columns', 'ptl_set_custom_lead_columns');
function ptl_set_custom_lead_columns($columns) {
    return array(
        'cb' => $columns['cb'],
        'title' => 'Email Χρήστη',
        'ptl_name' => 'Όνομα',
        'ptl_type' => 'Κατηγορία (Συνεργάτης)',
        'ptl_result' => 'Αποτέλεσμα / Κωδικός',
        'date' => $columns['date']
    );
}

add_action('manage_ptl_quiz_lead_posts_custom_column', 'ptl_custom_lead_column', 10, 2);
function ptl_custom_lead_column($column, $post_id) {
    switch ($column) {
        case 'ptl_name':
            echo esc_html(get_post_meta($post_id, 'ptl_lead_name', true));
            break;
        case 'ptl_type':
            $type = get_post_meta($post_id, 'ptl_lead_type', true);
            if ($type === 'VET_MANOLAKOU') { echo '<span style="color: #d63638; font-weight:bold;">Δρ. Μανωλάκου (VET)</span>'; } 
            else { echo '<span style="color: #2271b1; font-weight:bold;">Πρόταση Τροφής (Yoggies)</span>'; }
            break;
        case 'ptl_result':
            echo esc_html(get_post_meta($post_id, 'ptl_lead_result', true));
            break;
    }
}

function ptl_save_quiz_lead_data($email, $name, $type, $result) {
    $existing_post = get_page_by_title($email, OBJECT, 'ptl_quiz_lead');
    $post_id = $existing_post ? $existing_post->ID : wp_insert_post(array('post_title' => $email, 'post_type' => 'ptl_quiz_lead', 'post_status' => 'publish'));
    update_post_meta($post_id, 'ptl_lead_name', sanitize_text_field($name));
    update_post_meta($post_id, 'ptl_lead_type', sanitize_text_field($type));
    update_post_meta($post_id, 'ptl_lead_result', sanitize_text_field($result));
}

// =========================================================================
// 3. ΕΓΓΡΑΦΗ SCRIPTS & STYLES (ELEMENTOR) & QUIZ SHORTCODE
// =========================================================================
add_action('wp_enqueue_scripts', 'ptl_nutrition_enqueue_assets');
function ptl_nutrition_enqueue_assets() {
    wp_register_script( 'ptl-nutrition-js', false ); 
    wp_enqueue_script( 'jquery' );
}

add_shortcode('petling_nutrition_quiz', 'ptl_nutrition_quiz_shortcode');
function ptl_nutrition_quiz_shortcode() {
    
    $html = '<style>
        .ptl-quiz-container { max-width: 650px; margin: 40px auto; background: #fffaf1; padding: 40px; border-radius: 12px; border: 2px dashed #C7B297; font-family: sans-serif; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .ptl-step { display: none; animation: fadeIn 0.4s; }
        .ptl-step.active { display: block; }
        .ptl-quiz-container h3 { color: #43282F; margin-top: 0; font-size: 24px; text-align: center; margin-bottom:10px; }
        .ptl-quiz-container p.ptl-desc { color: #555; font-size: 15px; margin-bottom: 25px; text-align: center; line-height: 1.5; }
        .ptl-quiz-container label { display: block; font-weight: bold; color: #43282F; margin-bottom: 8px; margin-top: 15px; }
        .ptl-quiz-container input[type="number"], .ptl-quiz-container input[type="email"], .ptl-quiz-container input[type="text"], .ptl-quiz-container select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 15px; }
        .ptl-radio-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .ptl-radio-group label { flex: 1 1 calc(50% - 10px); min-width:140px; text-align: center; background: #fff; border: 2px solid #e0d5c1; padding: 15px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-weight: normal; margin-top: 0; }
        .ptl-radio-group input[type="radio"] { display: none; }
        .ptl-radio-group input[type="radio"]:checked + label { border-color: #C7B297; background: #F5EDE3; color: #43282F; font-weight: bold; }
        .ptl-quiz-nav { display: flex; justify-content: space-between; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 20px; }
        .ptl-btn-prev { background: #e0d5c1; color: #43282F; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .ptl-btn-next, .ptl-btn-submit { background: #43282F; color: #fff; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .ptl-btn-next:hover, .ptl-btn-submit:hover { background: #C7B297; color: #43282F; }
        .ptl-btn-prev:hover { background: #C7B297; color: #fff; }
        .ptl-loader { text-align:center; padding:20px; font-weight:bold; color:#43282F; display:none; }
        .ptl-result-box { background:#fff; padding:25px; border-radius:8px; border:2px solid #C7B297; margin-top:20px; text-align:center; }
        .ptl-transition-box { background: #F5EDE3; border-left: 4px solid #C7B297; padding: 15px 20px; margin-top: 25px; text-align: left; font-size: 14px; color: #43282F; line-height: 1.6; border-radius: 0 8px 8px 0; }
        .ptl-transition-box h5 { margin: 0 0 10px 0; font-size: 16px; color: #43282F; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>';

    $html .= '<div class="ptl-quiz-container" id="ptl-nutrition-app"><form id="ptl-nutrition-form">';
    $html .= wp_nonce_field('ptl_nutrition_nonce', 'security', true, false);

    // ΒΗΜΑ 0
    $html .= '<div class="ptl-step active" id="ptl-step-0">';
    $html .= '<h3>🐾 Ας ξεκινήσουμε!</h3><p class="ptl-desc">Για ποιο μουσουδάκι ψάχνουμε την ιδανική τροφή;</p>';
    $html .= '<div class="ptl-radio-group">';
    $html .= '<input type="radio" id="pet_dog" name="pet_type" value="dog" checked><label for="pet_dog">🐶 Σκύλος</label>';
    $html .= '<input type="radio" id="pet_cat" name="pet_type" value="cat"><label for="pet_cat">🐱 Γάτα</label>';
    $html .= '</div>';
    $html .= '<div class="ptl-quiz-nav" style="justify-content: flex-end;"><button type="button" class="ptl-btn-next" onclick="ptlNextStep(1)">Επόμενο &rarr;</button></div></div>';

    // ΒΗΜΑ 1
    $html .= '<div class="ptl-step" id="ptl-step-1">';
    $html .= '<h3>🩺 Θέματα Υγείας</h3><p class="ptl-desc">Το κατοικίδιό σας έχει κάποιο διαγνωσμένο πρόβλημα υγείας (π.χ. νεφρικά, ηπατικά);</p>';
    $html .= '<div class="ptl-radio-group">';
    $html .= '<input type="radio" id="health_no" name="health_issue" value="no" checked><label for="health_no">Όχι, είναι υγιέστατο!</label>';
    $html .= '<input type="radio" id="health_yes" name="health_issue" value="yes"><label for="health_yes">Ναι, υπάρχει διάγνωση</label>';
    $html .= '</div>';
    $html .= '<div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlPrevStep(0)">&larr; Πίσω</button><button type="button" class="ptl-btn-next" onclick="ptlSmartNextStep(2)">Επόμενο &rarr;</button></div></div>';

    // ΒΗΜΑ 2
    $html .= '<div class="ptl-step" id="ptl-step-2">';
    $html .= '<h3>⚖️ Στοιχεία & Αλλεργίες</h3>';
    $html .= '<label>Ηλικιακό Στάδιο:</label>';
    $html .= '<select name="life_stage"><option value="puppy">Κουτάβι / Γατάκι (έως 12 μηνών)</option><option value="adult" selected>Ενήλικο</option><option value="senior">Μεγάλης Ηλικίας (Senior)</option></select>';
    $html .= '<label>Τρέχον Βάρος (σε κιλά):</label><input type="number" step="0.1" name="current_weight" placeholder="π.χ. 12.5">';
    $html .= '<label>Επίπεδο Δραστηριότητας:</label>';
    $html .= '<select name="activity_level"><option value="low">Χαμηλή (Ήσυχο / Υπέρβαρο)</option><option value="normal" selected>Κανονική (1-2 βόλτες / παιχνίδι)</option><option value="high">Υψηλή (Αθλητικό / Εργασίας)</option></select>';
    $html .= '<label>Γνωστές Αλλεργίες / Δυσανεξίες (Πολλαπλή Επιλογή):</label>';
    $html .= '<select name="allergies[]" multiple style="height: 120px;">';
    $html .= '<option value="none" selected>Καμία γνωστή αλλεργία</option><option value="chicken">Κοτόπουλο</option><option value="turkey">Γαλοπούλα</option><option value="poultry">Όλα τα Πουλερικά</option><option value="beef">Μοσχάρι / Βόειο</option><option value="pork">Χοιρινό</option><option value="lamb">Αρνί</option><option value="fish_meat">Ψάρι (Κρέας Ψαριού)</option><option value="fish_oil">Ψάρι (και Λάδι/Ιχθυέλαιο)</option><option value="egg">Αυγό</option><option value="corn">Καλαμπόκι</option><option value="meat">Όλα τα κρέατα (Ακραία Ευαισθησία)</option></select>';
    $html .= '<p style="font-size: 11px; color:#777; margin-top: 5px;">*Κρατήστε πατημένο το Ctrl (Windows) ή Cmd (Mac) για πολλαπλή επιλογή.</p>';
    $html .= '<div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlSmartPrevStep(1)">&larr; Πίσω</button><button type="button" class="ptl-btn-next" onclick="ptlCheckDataBeforeStep3()">Επόμενο &rarr;</button></div></div>';

    // ΒΗΜΑ 3
    $html .= '<div class="ptl-step" id="ptl-step-3">';
    $html .= '<h3>📩 Λίγο πριν το αποτέλεσμα!</h3><p class="ptl-desc">Συμπληρώστε τα στοιχεία σας για να δείτε το αποτέλεσμα και να σας σταλεί στο email.</p>';
    $html .= '<label>Το Όνομά σας:</label><input type="text" name="user_name" placeholder="π.χ. Γιώργος" required>';
    $html .= '<label>Το Email σας:</label><input type="email" name="user_email" placeholder="Το email σας εδώ..." required>';
    $html .= '<div style="margin-top: 15px; font-size: 13px; color: #555;">';
    $html .= '<label style="font-weight: normal; display: flex; align-items:center; gap: 8px;"><input type="checkbox" name="consent_email" required> <span>Συμφωνώ να λάβω το αποτέλεσμα στο email μου.</span></label>';
    $html .= '<label style="font-weight: normal; display: flex; align-items:center; gap: 8px; margin-top:5px;"><input type="checkbox" name="consent_marketing"> <span>Θέλω να λαμβάνω νέα και προσφορές από το Petling.</span></label>';
    $html .= '</div>';
    $html .= '<div class="ptl-quiz-nav"><button type="button" class="ptl-btn-prev" onclick="ptlSmartPrevStep(2)">&larr; Πίσω</button><button type="submit" class="ptl-btn-submit">Δες το αποτέλεσμα!</button></div></div>';

    // RESULT
    $html .= '<div class="ptl-step" id="ptl-step-result"><div id="ptl-result-content"></div><div style="text-align:center; margin-top:30px;"><button type="button" class="ptl-btn-prev" onclick="location.reload();">Επανεκκίνηση Test</button></div></div>';
    $html .= '<div class="ptl-loader" id="ptl-quiz-loader">⏳ Γίνεται υπολογισμός...</div></form></div>';

    $ajax_url = admin_url( 'admin-ajax.php' );
    $html .= "<script>
        function ptlNextStep(step) { jQuery('.ptl-step').removeClass('active'); jQuery('#ptl-step-' + step).addClass('active'); }
        function ptlSmartNextStep(step) { var health = jQuery('input[name=\"health_issue\"]:checked').val(); if ( step === 2 && health === 'yes' ) { step = 3; } ptlNextStep(step); }
        function ptlSmartPrevStep(step) { var health = jQuery('input[name=\"health_issue\"]:checked').val(); if ( step === 2 && health === 'yes' ) { step = 1; } ptlNextStep(step); }
        function ptlCheckDataBeforeStep3() { var weight = jQuery('input[name=\"current_weight\"]').val(); if(!weight || weight <= 0) { alert('Παρακαλώ εισάγετε σωστό βάρος.'); return; } ptlNextStep(3); }

        jQuery('#ptl-nutrition-form').on('submit', function(e){
            e.preventDefault();
            var formData = jQuery(this).serialize();
            jQuery('.ptl-step').removeClass('active');
            jQuery('#ptl-quiz-loader').show();
            jQuery.post('{$ajax_url}', formData + '&action=ptl_process_nutrition', function(response){
                jQuery('#ptl-quiz-loader').hide(); jQuery('#ptl-step-result').addClass('active');
                if(response.success) { jQuery('#ptl-result-content').html(response.data); } 
                else { jQuery('#ptl-result-content').html('<p style=\"color:red;\">' + response.data + '</p>'); }
            }).fail(function(xhr) {
                jQuery('#ptl-quiz-loader').hide(); jQuery('#ptl-step-result').addClass('active');
                jQuery('#ptl-result-content').html('<p style=\"color:red;\">Υπήρξε ένα σφάλμα. Ελέγξτε την κονσόλα (F12).</p>');
            });
        });
    </script>";

    return $html;
}

// =========================================================================
// 4. ΕΠΕΞΕΡΓΑΣΙΑ AJAX, ΑΛΓΟΡΙΘΜΟΣ & ΑΠΟΣΤΟΛΗ EMAIL
// =========================================================================
add_action('wp_ajax_ptl_process_nutrition', 'ptl_process_nutrition_ajax');
add_action('wp_ajax_nopriv_ptl_process_nutrition', 'ptl_process_nutrition_ajax');

function ptl_process_nutrition_ajax() {
    check_ajax_referer('ptl_nutrition_nonce', 'security');

    $pet_type       = isset($_POST['pet_type']) ? sanitize_text_field($_POST['pet_type']) : 'dog';
    $health_issue   = isset($_POST['health_issue']) ? sanitize_text_field($_POST['health_issue']) : 'no';
    $weight         = isset($_POST['current_weight']) ? floatval($_POST['current_weight']) : 0;
    $life_stage     = isset($_POST['life_stage']) ? sanitize_text_field($_POST['life_stage']) : 'adult';
    $activity_level = isset($_POST['activity_level']) ? sanitize_text_field($_POST['activity_level']) : 'normal';
    $user_name      = isset($_POST['user_name']) ? sanitize_text_field($_POST['user_name']) : '';
    $user_email     = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
    $allergies      = isset($_POST['allergies']) ? array_map('sanitize_text_field', $_POST['allergies']) : array('none');

    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Petling <info@petling.gr>');

    // ==========================================
    // ΣΕΝΑΡΙΟ Α: ΕΧΕΙ ΠΡΟΒΛΗΜΑ ΥΓΕΙΑΣ (VET)
    // ==========================================
    if ( $health_issue === 'yes' ) {
        
        global $wpdb;
        $promo_table = $wpdb->prefix . 'petling_partner_leads';
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table);
        $dynamic_code = '';
        
        // --- ΕΛΕΓΧΟΣ 24 ΩΡΩΝ ΑΠΕΥΘΕΙΑΣ ΣΤΟ PROMO DB ---
        if ( $table_exists ) {
            $recent_code = $wpdb->get_var( $wpdb->prepare(
                "SELECT coupon_code FROM $promo_table WHERE email = %s AND partner_prefix = 'VET' AND status = 'active' AND created_at >= %s ORDER BY created_at DESC LIMIT 1",
                $user_email,
                date('Y-m-d H:i:s', strtotime('-24 hours', current_time('timestamp')))
            ) );
            if ( $recent_code ) {
                $dynamic_code = $recent_code;
            }
        }
        
        // Αν δεν υπάρχει κωδικός τις τελευταίες 24h (ή το plugin Promo είναι κλειστό), φτιάχνουμε νέο
        if ( empty($dynamic_code) ) {
            $dynamic_code = 'VET-' . strtoupper(substr(md5(uniqid()), 0, 6));
            
            if ( $table_exists ) {
                $wpdb->insert( $promo_table, array(
                    'email'          => $user_email,
                    'partner_prefix' => 'VET',
                    'type'           => 'appointment',
                    'coupon_code'    => $dynamic_code,
                    'status'         => 'active',
                    'created_at'     => current_time('mysql')
                ) );
            }
        }

        // Το γράφουμε και στο τοπικό Quiz CRM για δική σου ευκολία απεικόνισης
        // οι πελάτες της Μανωλάκου δεν εμφανιζονται στο quiz.!
        // ptl_save_quiz_lead_data($user_email, $user_name, 'VET_MANOLAKOU', $dynamic_code);

        // HTML Response
        $html = '<h3>🩺 Απαιτείται Κτηνιατρική Συμβουλή</h3>';
        $html .= '<p class="ptl-desc">Επειδή το ζωάκι σας έχει διαγνωσμένο πρόβλημα υγείας, η επιλογή τροφής πρέπει να γίνει εξατομικευμένα.</p>';
        $html .= '<div class="ptl-result-box">';
        $html .= '<h4 style="color: #43282F; font-size:20px; margin-bottom:5px;">Κλείστε Ραντεβού με τη Δρ. Μανωλάκου</h4>';
        $html .= '<p>Ο μοναδικός σας κωδικός για <strong>10% έκπτωση</strong> στο ραντεβού σας είναι:</p>';
        $html .= '<div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; margin:20px 0; border:2px dashed #C7B297;">' . $dynamic_code . '</div>';
        $html .= '<p style="font-size:13px; color:#666;">Σας έχουμε στείλει τον κωδικό και στο email σας.</p></div>';

        // Email
        if ( is_email( $user_email ) ) {
            $subject = 'Ο κωδικός έκπτωσης σας από τον Διατροφικό Σύμβουλο Petling';
            $message = '<div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; background: #fffaf1; padding: 30px; border-radius: 8px; border: 2px solid #C7B297;">';
            $message .= '<h2 style="color: #43282F; text-align:center;">Γεια σας ' . esc_html($user_name) . '!</h2>';
            $message .= '<p style="color:#555; text-align:center;">Επειδή το κατοικίδιό σας έχει κάποιο διαγνωσμένο θέμα υγείας, η επιλογή της τροφής του πρέπει να γίνει με προσοχή και εξατομικευμένα.</p>';
            $message .= '<div style="background: #fff; padding: 25px; border: 1px solid #C7B297; border-radius: 8px; margin: 25px 0; text-align:center;">';
            $message .= '<h3 style="color: #43282F; margin-top: 0; font-size: 20px;">Κλείστε Ραντεβού με τη Δρ. Μανωλάκου</h3>';
            $message .= '<p style="font-size: 16px; color: #333;">Χρησιμοποιήστε τον παρακάτω κωδικό για 10% έκπτωση στο ραντεβού σας:</p>';
            $message .= '<div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; margin:20px 0; border:2px dashed #C7B297;">' . $dynamic_code . '</div></div></div>';
            wp_mail( $user_email, $subject, $message, $headers );
        }

        wp_send_json_success($html);
        exit;
    }

    // ==========================================
    // ΣΕΝΑΡΙΟ Β: ΥΓΙΕΣ (ΠΡΟΤΑΣΗ ΤΡΟΦΗΣ)
    // ==========================================
    $all_products = ptl_get_yoggies_data();
    $available_products = $all_products[$pet_type];
    
    if ( ! in_array( 'none', $allergies ) ) {
        foreach ( $available_products as $key => $product ) {
            $product_allergens = $product['allergens'];
            foreach ( $allergies as $allergy ) {
                if ( in_array( $allergy, $product_allergens ) ) { unset( $available_products[$key] ); break; }
            }
        }
    }

    if ( empty( $available_products ) ) { wp_send_json_error('Δυστυχώς δεν βρέθηκε τροφή Yoggies που να καλύπτει αυτούς τους περιορισμούς αλλεργιών. Επικοινωνήστε μαζί μας για βοήθεια.'); }

    $final_product = null;
    foreach ( $available_products as $product ) { if ( $product['activity_level'] === $activity_level ) { $final_product = $product; break; } }
    if ( ! $final_product ) { $final_product = reset( $available_products ); }

    ptl_save_quiz_lead_data($user_email, $user_name, 'FOOD_YOGGIES', $final_product['title']);

    $grams_per_day = 0; $dosage_text = '';
    if ( $pet_type === 'dog' ) {
        if ( $life_stage === 'puppy' ) { $grams_per_day = $weight * 1000 * 0.025; $dosage_text = 'Υπολογισμός 2.5% του σωματικού βάρους (κουτάβι).'; } 
        else { $grams_per_day = $weight * 1000 * 0.012; $dosage_text = 'Υπολογισμός 1.2% του σωματικού βάρους (ενήλικος).'; }
    } else {
        if ( $weight <= 3 ) { $grams_per_day = 50; } elseif ( $weight <= 5 ) { $grams_per_day = 70; } else { $grams_per_day = 110; }
        $dosage_text = 'Βάσει της κλίμακας κιλών της Yoggies για γάτες.';
    }

    $grams_per_day = round($grams_per_day);
    $disclaimer = 'Υπολογισμένο με βάση τις οδηγίες της Κτηνιάτρου Διατροφής, Δρ. Χαράς Μανωλάκου.';
    $transition_text = 'Επειδή η Yoggies είναι ψυχρής έκθλιψης, πέπτεται διαφορετικά και πιο γρήγορα από μια συνηθισμένη κροκέτα — γι\' αυτό δεν αναμειγνύουμε τις δύο τροφές στο ίδιο μπολ.<br><br>Σερβίρισέ τες σε ξεχωριστά γεύματα, με απόσταση τουλάχιστον 8-10 ωρών μεταξύ τους (π.χ. παλιά τροφή το πρωί, Yoggies το βράδυ). Ξεκίνα με ένα μόνο γεύμα Yoggies την ημέρα και αύξησε σταδιακά τις μερίδες της νέας τροφής μέσα στις επόμενες ημέρες, μέχρι να αντικατασταθεί πλήρως η παλιά.<br><br>Αν παρατηρήσεις μαλακό κόπρανο ή αναγούλα, κράτα τον ίδιο ρυθμό 1-2 επιπλέον μέρες πριν προχωρήσεις. Νερό πάντα άφθονο και διαθέσιμο.<br><br>Η τροφή Yoggies περιέχει ήδη προβιοτική καλλιέργεια (Enterococcus faecium), που βοηθάει το πεπτικό σύστημα να προσαρμοστεί πιο ομαλά.';

    $html = '<h3>🎉 Βρήκαμε την ιδανική τροφή, ' . esc_html($user_name) . '!</h3>';
    $html .= '<div class="ptl-result-box"><h4 style="color:#43282F; font-size:20px; margin-bottom:5px;">' . esc_html($final_product['title']) . '</h4>';
    $html .= '<p style="font-size:18px; margin:15px 0;">Προτεινόμενη Ημερήσια Δόση: <strong>' . $grams_per_day . ' γρ.</strong></p>';
    $html .= '<p style="font-size:13px; color:#666;">' . $dosage_text . '<br>' . $disclaimer . '</p>';
    $html .= '<a href="' . esc_url($final_product['url']) . '" target="_blank" style="display:inline-block; margin-top:20px; background:#43282F; color:#fff; padding:12px 25px; border-radius:6px; text-decoration:none; font-weight:bold;">Δες το Προϊόν &rarr;</a></div>';
    $html .= '<div class="ptl-transition-box"><h5>💡 Πώς να περάσεις το κατοικίδιό σου στη νέα τροφή</h5>' . $transition_text . '</div>';

    if ( is_email( $user_email ) ) {
        $subject = 'Η διατροφική σύσταση για το κατοικίδιό σας από το Petling!';
        $message = '<div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; background: #fffaf1; padding: 30px; border-radius: 8px; border: 2px solid #C7B297;">';
        $message .= '<h2 style="color: #43282F; text-align:center;">Γεια σας ' . esc_html($user_name) . '!</h2>';
        $message .= '<p style="color:#555; text-align:center;">Ο Διατροφικός Σύμβουλος του Petling ανέλυσε τα δεδομένα σας και βρήκε την ιδανική ξηρά τροφή ψυχρής έκθλιψης (Yoggies).</p>';
        $message .= '<div style="background: #fff; padding: 25px; border: 1px solid #C7B297; border-radius: 8px; margin: 25px 0; text-align:center;">';
        $message .= '<h3 style="color: #43282F; margin-top: 0; font-size: 20px;">' . esc_html($final_product['title']) . '</h3>';
        $message .= '<p style="font-size: 18px; color: #333;">Προτεινόμενη Ημερήσια Δόση: <strong>' . $grams_per_day . ' γρ.</strong></p>';
        $message .= '<p style="font-size: 12px; color: #666;">' . $dosage_text . '<br>' . $disclaimer . '</p>';
        $message .= '<a href="' . esc_url($final_product['url']) . '" style="background: #43282F; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; margin-top:15px;">Αγοράστε την τροφή εδώ</a></div>';
        $message .= '<div style="background: #F5EDE3; border-left: 4px solid #C7B297; padding: 20px; border-radius: 0 8px 8px 0; margin-top: 25px; color:#43282F; font-size:14px; line-height:1.6;"><h4 style="margin: 0 0 10px 0; font-size:16px;">💡 Πώς να περάσετε στη νέα τροφή</h4>' . $transition_text . '</div></div>';
        wp_mail( $user_email, $subject, $message, $headers );
    }

    wp_send_json_success($html);
}

// Προσθήκη Dropdown Φίλτρου στο "Leads Quiz"
add_action('restrict_manage_posts', 'ptl_quiz_add_admin_filters');
function ptl_quiz_add_admin_filters($post_type) {
    if ($post_type !== 'ptl_quiz_lead') return;

    $selected_type = isset($_GET['ptl_filter_type']) ? sanitize_text_field($_GET['ptl_filter_type']) : '';
    ?>
    <select name="ptl_filter_type">
        <option value="">Όλες οι Κατηγορίες</option>
        <option value="VET_MANOLAKOU" <?php selected($selected_type, 'VET_MANOLAKOU'); ?>>Δρ. Μανωλάκου (VET)</option>
        <option value="FOOD_YOGGIES" <?php selected($selected_type, 'FOOD_YOGGIES'); ?>>Πρόταση Τροφής (Yoggies)</option>
    </select>
    <?php
}

// Εφαρμογή του Φίλτρου στο Query του WordPress
add_filter('pre_get_posts', 'ptl_quiz_filter_by_type');
function ptl_quiz_filter_by_type($query) {
    global $pagenow;
    if ( is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'ptl_quiz_lead' && $query->is_main_query() ) {
        if (!empty($_GET['ptl_filter_type'])) {
            $query->set('meta_query', array(
                array(
                    'key'     => 'ptl_lead_type',
                    'value'   => sanitize_text_field($_GET['ptl_filter_type']),
                    'compare' => '='
                )
            ));
        }
    }
}

// =========================================================================
// ΕΝΗΜΕΡΩΤΙΚΟ ΜΗΝΥΜΑ ΣΤΟ ΔΙΑΧΕΙΡΙΣΤΙΚΟ (ΜΟΝΟ ΣΤΗ ΣΕΛΙΔΑ ΤΟΥ QUIZ)
// =========================================================================
add_action('admin_notices', 'ptl_quiz_admin_notice');
function ptl_quiz_admin_notice() {
    global $typenow;
    
    // Εμφάνιση μόνο όταν βρισκόμαστε στο μενού "Leads Quiz"
    if ( $typenow === 'ptl_quiz_lead' ) {
        ?>
        <div class="notice notice-info" style="border-left-color: #C7B297; padding: 10px;">
            <p style="font-size: 14px; margin: 0;">
                <strong>💡 Χρήσιμη Σημείωση:</strong> Εδώ αποθηκεύονται <strong>μόνο</strong> οι χρήστες που έλαβαν "Πρόταση Τροφής". 
                <br>Όσοι χρήστες είχαν πρόβλημα υγείας και έλαβαν εκπτωτικό κωδικό για τη Δρ. Μανωλάκου (VET), καταγράφονται αυτόματα στο μενού <strong>Petling Promo > Εγγεγραμμένοι Πελάτες</strong>, ώστε να μπορεί η κτηνίατρος να τους διαχειριστεί και να τους εξαργυρώσει από το δικό της σύστημα.
            </p>
        </div>
        <?php
    }
}