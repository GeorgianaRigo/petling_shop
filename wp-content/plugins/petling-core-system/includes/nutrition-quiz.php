<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ptl_get_yoggies_data() {
    return array(
        'dog' => array(
            'dog_active_duck_venison' => array( 'title' => 'Active Κρέας Πάπιας, Κυνήγι & Προβιοτικά', 'url' => home_url('/product/xira-trofi-skylou-active-kreas-papias-kynigi-3/'), 'allergens' => array( 'duck', 'venison', 'poultry', 'fish_meat', 'egg', 'shellfish' ), 'activity_level' => 'high' ),
            'dog_chicken_beef'        => array( 'title' => 'Κοτόπουλο, Βόειο Κρέας & Προβιοτικά', 'url' => home_url('/?s=Κοτόπουλο+Βόειο+Κρέας+Προβιοτικά&post_type=product'), 'allergens' => array( 'chicken', 'beef', 'poultry', 'fish_meat', 'corn', 'shellfish' ), 'activity_level' => 'normal' ),
            'dog_lamb_fish'           => array( 'title' => 'Αρνί Γάλακτος, Λευκό Ψάρι & Προβιοτικά', 'url' => home_url('/?s=Αρνί+Λευκό+Ψάρι+Προβιοτικά&post_type=product'), 'allergens' => array( 'lamb', 'fish_meat' ), 'activity_level' => 'normal' ),
            'dog_turkey_millet'       => array( 'title' => 'Γαλοπούλα, Κεχρί & Προβιοτικά (Μονοπρωτεϊνική)', 'url' => home_url('/?s=Γαλοπούλα+Κεχρί+Προβιοτικά&post_type=product'), 'allergens' => array( 'turkey', 'poultry', 'fish_oil' ), 'activity_level' => 'low' ),
            'dog_iberian_pork'        => array( 'title' => 'Ιβηρικό Χοιρινό, Μήλα & Προβιοτικά', 'url' => home_url('/?s=Ιβηρικό+Χοιρινό+Μήλα&post_type=product'), 'allergens' => array( 'pork', 'fish_oil' ), 'activity_level' => 'normal' ),
            'dog_goat'                => array( 'title' => 'Κατσικίσιο Κρέας με Λαχανικά (Μονοπρωτεϊνική)', 'url' => home_url('/?s=Κατσικίσιο+Κρέας+Λαχανικά&post_type=product'), 'allergens' => array( 'goat', 'fish_oil', 'egg', 'shellfish' ), 'activity_level' => 'low' ),
            'dog_vet_gastro_turkey'   => array( 'title' => 'VET Gastro Sensitive με Γαλοπούλα', 'url' => home_url('/?s=VET+Gastro+Sensitive+Γαλοπούλα&post_type=product'), 'allergens' => array( 'turkey', 'poultry', 'fish_oil' ), 'activity_level' => 'normal' ),
            'dog_vet_insect'          => array( 'title' => 'VET Insect Derma (Πρωτεΐνη Εντόμων)', 'url' => home_url('/?s=VET+Insect+Derma&post_type=product'), 'allergens' => array( 'insect', 'fish_oil' ), 'activity_level' => 'normal' ),
            'dog_vet_veggie'          => array( 'title' => 'VET Veggie, Χωρίς Κρέας', 'url' => home_url('/?s=VET+Veggie&post_type=product'), 'allergens' => array( 'meat' ), 'activity_level' => 'normal' )
        ),
        'cat' => array(
            'cat_chicken'   => array( 'title' => 'Κοτόπουλο & Προβιοτικά (Γάτας)', 'url' => home_url('/?s=Τροφή+Γάτας+Κοτόπουλο+Προβιοτικά&post_type=product'), 'allergens' => array( 'chicken', 'poultry', 'fish_oil' ), 'activity_level' => 'normal' ),
            'cat_turkey'    => array( 'title' => 'Γαλοπούλα & Προβιοτικά (Γάτας)', 'url' => home_url('/?s=Τροφή+Γάτας+Γαλοπούλα+Προβιοτικά&post_type=product'), 'allergens' => array( 'turkey', 'poultry', 'fish_oil' ), 'activity_level' => 'normal' ),
            'cat_lamb_fish' => array( 'title' => 'Αρνί Γάλακτος, Λευκό Ψάρι & Προβιοτικά (Γάτας)', 'url' => home_url('/?s=Τροφή+Γάτας+Αρνί+Ψάρι&post_type=product'), 'allergens' => array( 'lamb', 'fish_meat' ), 'activity_level' => 'normal' )
        )
    );
}

add_shortcode( 'petling_nutrition_quiz', 'ptl_nutrition_quiz_shortcode' );
function ptl_nutrition_quiz_shortcode() {
    wp_enqueue_style( 'ptl-quiz-css', PTL_CORE_URL . 'assets/css/quiz.css', array(), PTL_CORE_VERSION );
    wp_enqueue_script( 'ptl-nutrition-quiz-js', PTL_CORE_URL . 'assets/js/nutrition-quiz.js', array( 'jquery' ), PTL_CORE_VERSION, true );
    wp_localize_script( 'ptl-nutrition-quiz-js', 'ptlNutritionQuiz', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );

    ob_start();
    ?>
    <div class="ptl-quiz-container" id="ptl-nutrition-app">
        <form id="ptl-nutrition-form">
            <?php wp_nonce_field( 'ptl_nutrition_nonce', 'security', true, true ); ?>
            <div class="ptl-step active" id="ptl-step-0">
                <h3>🐾 Ας ξεκινήσουμε!</h3>
                <p class="ptl-desc">Για ποιο μουσουδάκι ψάχνουμε την ιδανική τροφή;</p>
                <div class="ptl-radio-group ptl-radio-row">
                    <input type="radio" id="pet_dog" name="pet_type" value="dog" checked><label for="pet_dog">🐶 Σκύλος</label>
                    <input type="radio" id="pet_cat" name="pet_type" value="cat"><label for="pet_cat">🐱 Γάτα</label>
                </div>
                <div class="ptl-quiz-nav" style="justify-content: flex-end;">
                    <button type="button" class="ptl-btn-next" onclick="ptlnNextStep(1)">Επόμενο &rarr;</button>
                </div>
            </div>

            <div class="ptl-step" id="ptl-step-1">
                <h3>🩺 Θέματα Υγείας</h3>
                <p class="ptl-desc">Το κατοικίδιό σας έχει κάποιο διαγνωσμένο πρόβλημα υγείας;</p>
                <div class="ptl-radio-group ptl-radio-row">
                    <input type="radio" id="health_no" name="health_issue" value="no" checked><label for="health_no">Όχι, είναι υγιέστατο!</label>
                    <input type="radio" id="health_yes" name="health_issue" value="yes"><label for="health_yes">Ναι, υπάρχει διάγνωση</label>
                </div>
                <div class="ptl-quiz-nav">
                    <button type="button" class="ptl-btn-prev" onclick="ptlnNextStep(0)">&larr; Πίσω</button>
                    <button type="button" class="ptl-btn-next" onclick="ptlnSmartNextStep(2)">Επόμενο &rarr;</button>
                </div>
            </div>

            <div class="ptl-step" id="ptl-step-2">
                <h3>⚖️ Στοιχεία &amp; Αλλεργίες</h3>
                <label>Ηλικιακό Στάδιο:</label>
                <select name="life_stage">
                    <option value="puppy">Κουτάβι / Γατάκι</option>
                    <option value="adult" selected>Ενήλικο</option>
                    <option value="senior">Μεγάλης Ηλικίας</option>
                </select>
                <label>Τρέχον Βάρος (κιλά):</label>
                <input type="number" step="0.1" name="current_weight" placeholder="π.χ. 12.5">
                <label>Επίπεδο Δραστηριότητας:</label>
                <select name="activity_level">
                    <option value="low">Χαμηλή (Ήσυχο)</option>
                    <option value="normal" selected>Κανονική</option>
                    <option value="high">Υψηλή</option>
                </select>
                <label>Γνωστές Αλλεργίες:</label>
                <select name="allergies[]" multiple style="height: 120px;">
                    <option value="none" selected>Καμία</option>
                    <option value="chicken">Κοτόπουλο</option>
                    <option value="turkey">Γαλοπούλα</option>
                    <option value="poultry">Πουλερικά</option>
                    <option value="beef">Μοσχάρι</option>
                    <option value="pork">Χοιρινό</option>
                    <option value="lamb">Αρνί</option>
                    <option value="fish_meat">Ψάρι</option>
                    <option value="fish_oil">Ψάρι &amp; Λάδι</option>
                    <option value="egg">Αυγό</option>
                    <option value="corn">Καλαμπόκι</option>
                    <option value="meat">Όλα τα κρέατα</option>
                </select>
                <div class="ptl-quiz-nav">
                    <button type="button" class="ptl-btn-prev" onclick="ptlnSmartPrevStep(1)">&larr; Πίσω</button>
                    <button type="button" class="ptl-btn-next" onclick="ptlnCheckDataBeforeStep3()">Επόμενο &rarr;</button>
                </div>
            </div>

            <div class="ptl-step" id="ptl-step-3">
                <h3>📩 Λίγο πριν το αποτέλεσμα!</h3>
                <p class="ptl-desc">Συμπληρώστε τα στοιχεία σας.</p>
                <label>Ονοματεπώνυμο:</label><input type="text" name="user_name" required>
                <label>Email:</label><input type="email" name="user_email" required>
                <div style="margin-top: 15px; font-size: 13px;">
                    <label><input type="checkbox" name="consent_email" required> Συμφωνώ να λάβω το αποτέλεσμα.</label>
                </div>
                <div class="ptl-quiz-nav">
                    <button type="button" class="ptl-btn-prev" onclick="ptlnSmartPrevStep(2)">&larr; Πίσω</button>
                    <button type="submit" class="ptl-btn-submit">Δες το αποτέλεσμα!</button>
                </div>
            </div>

            <div class="ptl-step" id="ptl-step-result">
                <div id="ptl-result-content"></div>
                <div style="text-align:center; margin-top:30px;">
                    <button type="button" class="ptl-btn-prev" onclick="location.reload();">Επανεκκίνηση</button>
                </div>
            </div>

            <div class="ptl-loader-quiz" id="ptl-quiz-loader">⏳ Γίνεται υπολογισμός...</div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

add_action( 'wp_ajax_ptl_process_nutrition', 'ptl_process_nutrition_ajax' );
add_action( 'wp_ajax_nopriv_ptl_process_nutrition', 'ptl_process_nutrition_ajax' );
function ptl_process_nutrition_ajax() {
    check_ajax_referer( 'ptl_nutrition_nonce', 'security' );
    $pet_type       = sanitize_text_field( $_POST['pet_type'] ?? 'dog' );
    $health_issue   = sanitize_text_field( $_POST['health_issue'] ?? 'no' );
    $weight         = floatval( $_POST['current_weight'] ?? 0 );
    $life_stage     = sanitize_text_field( $_POST['life_stage'] ?? 'adult' );
    $activity_level = sanitize_text_field( $_POST['activity_level'] ?? 'normal' );
    $user_name      = sanitize_text_field( $_POST['user_name'] ?? '' );
    $user_email     = sanitize_email( $_POST['user_email'] ?? '' );
    $allergies      = isset( $_POST['allergies'] ) ? array_map( 'sanitize_text_field', $_POST['allergies'] ) : array( 'none' );
    $headers        = array( 'Content-Type: text/html; charset=UTF-8', 'From: Petling <info@petling.gr>' );

    if ( $health_issue === 'yes' ) {
        global $wpdb;
        $promo_table  = $wpdb->prefix . 'petling_partner_leads';
        $table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '$promo_table'" ) === $promo_table );
        $dynamic_code = '';
        if ( $table_exists ) {
            $recent = $wpdb->get_var( $wpdb->prepare( "SELECT coupon_code FROM $promo_table WHERE email = %s AND partner_prefix = 'VET' AND status = 'active' AND created_at >= %s ORDER BY created_at DESC LIMIT 1", $user_email, date( 'Y-m-d H:i:s', strtotime( '-24 hours', current_time( 'timestamp' ) ) ) ) );
            if ( $recent ) { $dynamic_code = $recent; }
        }
        if ( empty( $dynamic_code ) ) {
            $dynamic_code = 'VET-' . strtoupper( substr( md5( uniqid() ), 0, 6 ) );
            if ( $table_exists ) {
                $wpdb->insert( $promo_table, array( 'email' => $user_email, 'partner_prefix' => 'VET', 'type' => 'appointment', 'coupon_code' => $dynamic_code, 'status' => 'active', 'created_at' => current_time( 'mysql' ) ) );
            }
        }

        $pid = ptl_save_quiz_lead_data( $user_email, $user_name, 'FOOD_YOGGIES', 'VET (Κωδικός: ' . $dynamic_code . ')' );
        if ( $pid ) { update_post_meta( $pid, 'ptl_health_condition', 'Πρόβλημα Υγείας (Σύσταση VET)' ); }

        $html = '<h3>🩺 Απαιτείται Κτηνιατρική Συμβουλή</h3><p class="ptl-desc">Η επιλογή τροφής πρέπει να γίνει εξατομικευμένα.</p><div class="ptl-result-box"><h4 style="color: #43282F;">Κλείστε Ραντεβού με τη Δρ. Μανωλάκου</h4><p>Ο κωδικός σας για <strong>10% έκπτωση</strong> είναι:</p><div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; margin:20px 0;">' . $dynamic_code . '</div><p style="font-size:13px; color:#666;">Στάλθηκε και στο email σας.</p></div>';
        if ( is_email( $user_email ) ) { wp_mail( $user_email, 'Κωδικός έκπτωσης VET', $html, $headers ); }
        wp_send_json_success( $html ); exit;
    }

    $all_products = ptl_get_yoggies_data();
    $available    = $all_products[ $pet_type ];
    if ( ! in_array( 'none', $allergies ) ) {
        foreach ( $available as $key => $product ) {
            foreach ( $allergies as $allergy ) {
                if ( in_array( $allergy, $product['allergens'] ) ) { unset( $available[ $key ] ); break; }
            }
        }
    }
    if ( empty( $available ) ) { wp_send_json_error( 'Δεν βρέθηκε κατάλληλη τροφή.' ); }

    $final = null;
    foreach ( $available as $p ) {
        if ( $p['activity_level'] === $activity_level ) { $final = $p; break; }
    }
    if ( ! $final ) { $final = reset( $available ); }

    ptl_save_quiz_lead_data( $user_email, $user_name, 'FOOD_YOGGIES', $final['title'] );

    $grams_per_day = round( ( $pet_type === 'dog' ) ? ( $weight * 1000 * ( $life_stage === 'puppy' ? 0.025 : 0.012 ) ) : ( ( $weight <= 3 ) ? 50 : ( ( $weight <= 5 ) ? 70 : 110 ) ) );
    $html = '<h3>🎉 Βρήκαμε την ιδανική τροφή!</h3><div class="ptl-result-box"><h4 style="color:#43282F;">' . esc_html( $final['title'] ) . '</h4><p>Ημερήσια Δόση: <strong>' . $grams_per_day . ' γρ.</strong></p><a href="' . esc_url( $final['url'] ) . '" target="_blank" style="display:inline-block; margin-top:20px; background:#43282F; color:#fff; padding:12px 25px; border-radius:6px; text-decoration:none;">Δες το Προϊόν &rarr;</a></div>';
    if ( is_email( $user_email ) ) { wp_mail( $user_email, 'Η πρόταση τροφής σας', $html, $headers ); }
    wp_send_json_success( $html );
}