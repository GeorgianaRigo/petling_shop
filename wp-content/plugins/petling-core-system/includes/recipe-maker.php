<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ptl_get_dynamic_recipe_options() {
    return array(
        'proteins_1'          => array( 'Κοτόπουλο', 'Γαλοπούλα', 'Αρνί', 'Μοσχάρι 🔒', 'Χοιρινό 🔒', 'Κουνέλι 🔒', 'Πάπια 🔒', 'Πέρκα 🔒', 'Τσιπούρα 🔒', 'Λαβράκι 🔒', 'Μπακαλιάρος 🔒', 'Σολομός 🔒', 'Σαρδέλα 🔒' ),
        'proteins_2'          => array( 'Αυγό (βραστό)', 'Γιαούρτι', 'Κεφίρ', 'Τυρί Cottage 🔒', 'Ανθότυρο 🔒' ),
        'carbs_1'             => array( 'Ρύζι (Λευκό ή Ολικής)', 'Κινόα', 'Κοφτό μακαρονάκι (Λευκό ή Ολικής) 🔒', 'Πλιγούρι 🔒', 'Κριθαράκι (Λευκό ή Ολικής) 🔒' ),
        'carbs_2'             => array( 'Γλυκοπατάτα', 'Πατάτα 🔒', 'Κολοκύθα 🔒' ),
        'veggies'             => array( 'Καρότο', 'Κολοκυθάκι', 'Μπρόκολο 🔒', 'Σπανάκι 🔒', 'Λαχανάκι Βρυξελλών 🔒', 'Παντζάρι 🔒', 'Φασολάκια 🔒', 'Αρακάς 🔒' ),
        'fruits'              => array( 'Μήλο', 'Μπανάνα', 'Αχλάδι', 'Αγγούρι', 'Καρπούζι 🔒', 'Πεπόνι 🔒', 'Φράουλα 🔒', 'Μύρτιλλα 🔒', 'Ροδάκινο 🔒' ),
        'oils'                => array( 'Εξαιρετικά παρθένο ελαιόλαδο', 'Έλαιο Σολομού', 'Λινέλαιο 🔒', 'Κολοκυθέλαιο 🔒' ),
        'mild_sensitivities'  => array( 'Τροφική Αλλεργία / Δερματικά', 'Ευαίσθητο Στομάχι / Αέρια', 'Ευαίσθητες Αρθρώσεις', 'Τάση για παχυσαρκία / Στειρωμένο' ),
        'clinical_conditions' => array( 'Γαστρεντερικά / ΙΦΝΕ', 'Νεφρική Ανεπάρκεια', 'Ηπατοπάθεια', 'Ουροποιητικό (Κρύσταλλοι/Πέτρες)', 'Σακχαρώδης Διαβήτης', 'Λεϊσμανίαση (Καλαζάρ)', 'Άλλο (Σοβαρό)' )
    );
}

add_shortcode( 'petling_recipe_maker', 'ptl_recipe_maker_shortcode' );
function ptl_recipe_maker_shortcode() {
    $r_data       = ptl_get_dynamic_recipe_options();
    $is_logged    = is_user_logged_in();
    $current_user = $is_logged ? wp_get_current_user() : null;
    $user_id      = $is_logged ? $current_user->ID : 0;

    $recipe_count = 0;
    $saved_pets   = array();
    if ( $is_logged ) {
        $existing_posts = get_posts( array(
            'post_type'      => 'ptl_quiz_lead',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array( 'key' => 'ptl_user_id', 'value' => $user_id ),
                array( 'key' => 'ptl_lead_type', 'value' => 'HOME_COOKED_RECIPE' )
            )
        ) );
        foreach ( $existing_posts as $post_item ) {
            if ( get_post_meta( $post_item->ID, 'ptl_locked_recipe', true ) !== 'yes' ) {
                $recipe_count++;
            }
        }
        $saved_pets = get_user_meta( $user_id, 'petling_pets', true );
    }

    $limit_reached = ( $is_logged && $recipe_count >= 2 );
    $submit_text   = $limit_reached ? 'Ολοκλήρωση & Πληρωμή' : 'Αποθήκευση (Δωρεάν)';

    wp_enqueue_style( 'ptl-quiz-css', PTL_CORE_URL . 'assets/css/quiz.css', array(), PTL_CORE_VERSION );
    wp_enqueue_script( 'ptl-recipe-maker-js', PTL_CORE_URL . 'assets/js/recipe-maker.js', array( 'jquery' ), PTL_CORE_VERSION, true );
    wp_localize_script( 'ptl-recipe-maker-js', 'ptlRecipeMaker', array(
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'limitReached' => $limit_reached
    ) );

    ob_start();
    ?>
    <div class="ptl-quiz-container" id="ptl-recipe-app">
        <?php if ( $limit_reached ) : ?>
            <div class="ptl-paywall-banner">
                <h4 style="margin:0 0 8px 0; color:#d63638; font-size:17px;">🔒 Έχετε φτάσει το όριο των δωρεάν συνταγών</h4>
                <p style="margin:0; font-size:13px; color:#555;">Μπορείτε να περιηγηθείτε στα υλικά. Η αποθήκευση νέας συνταγής θα απαιτήσει πληρωμή στο τελευταίο βήμα.</p>
            </div>
        <?php endif; ?>

        <div class="ptl-inline-error" id="ptl-general-error"></div>

        <form id="ptl-recipe-form">
            <?php wp_nonce_field( 'ptl_recipe_nonce', 'security', true, true ); ?>

            <!-- ΒΗΜΑ 1 -->
            <div class="ptl-step active" id="rec-step-1">
                <h3>🐶 Το προφίλ του σκύλου σας</h3>
                <p class="ptl-desc">Ας γνωριστούμε καλύτερα για να υπολογίσουμε σωστά τις μερίδες!</p>

                <?php
                if ( $is_logged && ! empty( $saved_pets ) && is_array( $saved_pets ) ) :
                    $has_dogs    = false;
                    $pet_options = '<option value="">-- Νέο Κατοικίδιο --</option>';
                    foreach ( $saved_pets as $p ) {
                        if ( ( $p['type'] ?? 'dog' ) !== 'dog' ) continue;
                        $has_dogs = true;
                        $id = $p['id'] ?? ''; $w = $p['weight'] ?? ''; $n = $p['neutered'] ?? 'no'; $a = $p['energy'] ?? 'medium'; $s = 'adult';
                        if ( ! empty( $p['birthday'] ) ) {
                            $age_years = ( time() - strtotime( $p['birthday'] ) ) / ( 365 * 24 * 60 * 60 );
                            if ( $age_years < 1 ) $s = 'puppy';
                            elseif ( $age_years > 7 ) $s = 'senior';
                        }
                        $json_attr    = esc_attr( json_encode( array( 'id' => $id, 'nm' => $p['name'], 'w' => $w, 'nt' => $n, 'act' => $a, 'st' => $s ), JSON_UNESCAPED_UNICODE ) );
                        $pet_options .= '<option value="' . $json_attr . '">🐾 ' . esc_html( $p['name'] ) . ( ! empty( $w ) ? " ({$w}kg)" : '' ) . '</option>';
                    }
                    if ( $has_dogs ) : ?>
                        <label class="ptl-section-label" style="margin-top:0;">Επιλογή Αποθηκευμένου Κατοικιδίου:</label>
                        <select id="ptl-saved-pet-selector"><?php echo $pet_options; ?></select>
                        <hr style="margin:20px 0; border:0; border-top:1px dashed #e0d5c1;">
                    <?php endif;
                endif;
                ?>

                <input type="hidden" name="pet_id" id="rw_id" value="">
                <label class="ptl-section-label" style="margin-top:0;">Όνομα Σκύλου:</label>
                <input type="text" name="pet_name" id="rw_name" placeholder="π.χ. Μάιλο" required>

                <label class="ptl-section-label">Βάρος (κιλά):</label>
                <input type="number" step="0.1" name="pet_weight" id="rw_weight" placeholder="π.χ. 12.5" required>

                <label class="ptl-section-label">Στάδιο Ζωής:</label>
                <select name="pet_stage" id="rw_stage">
                    <option value="adult">Ενήλικο</option>
                    <option value="puppy">Κουτάβι</option>
                    <option value="senior">Ηλικιωμένο</option>
                </select>

                <label class="ptl-section-label">Στειρωμένο:</label>
                <select name="pet_neutered" id="rw_neutered">
                    <option value="no">Όχι</option>
                    <option value="yes">Ναι</option>
                </select>

                <label class="ptl-section-label">Δραστηριότητα:</label>
                <select name="pet_activity" id="rw_activity">
                    <option value="medium">Κανονική (1 ώρα βόλτα/μέρα)</option>
                    <option value="low">Χαμηλή (Ήσυχο / Μέσα στο σπίτι)</option>
                    <option value="high">Υψηλή (Αθλητικό / Εργασίας)</option>
                </select>

                <div class="ptl-quiz-nav" style="justify-content: flex-end;">
                    <button type="button" class="ptl-btn-next" onclick="ptlrValidateStep1()">Επόμενο &rarr;</button>
                </div>
            </div>

            <!-- ΒΗΜΑ 2 -->
            <div class="ptl-step" id="rec-step-2">
                <h3>🩺 Θέματα Υγείας</h3>
                <p class="ptl-desc">Υπάρχουν κάποιες ειδικές ευαισθησίες ή κλινικές ανάγκες;</p>
                <div class="ptl-radio-group">
                    <input type="radio" id="health_none" name="health_tier" value="none" checked><label for="health_none">Κανένα πρόβλημα (Είναι υγιέστατος!)</label>
                    <input type="radio" id="health_mild" name="health_tier" value="mild"><label for="health_mild">Ήπιες Ευαισθησίες (π.χ. Αλλεργία, Τρίχωμα, Αρθρώσεις)</label>
                    <input type="radio" id="health_clinical" name="health_tier" value="clinical"><label for="health_clinical">Ιατρική/Κλινική Πάθηση (π.χ. Νεφρά, Διαβήτης)</label>
                </div>

                <div id="ptl-health-mild-box" style="display:none; background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; margin-bottom:20px;">
                    <label class="ptl-section-label" style="border:none; margin-top:0;">Τι είδους ευαισθησία;</label>
                    <select name="health_condition_mild">
                        <option value="" disabled selected>Επιλέξτε...</option>
                        <?php foreach ( $r_data['mild_sensitivities'] as $cond ) : ?>
                            <option value="<?php echo esc_attr( $cond ); ?>"><?php echo esc_html( $cond ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="ptl-health-clinical-box" style="display:none; background:#fff3f3; padding:20px; border-radius:8px; border:1px solid #d63638; margin-bottom:20px;">
                    <label class="ptl-section-label" style="border:none; margin-top:0; color:#d63638;">Κλινική Πάθηση:</label>
                    <select name="health_condition_clinical">
                        <option value="" disabled selected>Επιλέξτε...</option>
                        <?php foreach ( $r_data['clinical_conditions'] as $cond ) : ?>
                            <option value="<?php echo esc_attr( $cond ); ?>"><?php echo esc_html( $cond ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" name="health_issue" id="legacy_health_issue" value="no">
                <input type="hidden" name="health_condition" id="legacy_health_condition" value="">
                <div class="ptl-quiz-nav">
                    <button type="button" class="ptl-btn-prev" onclick="ptlrNextStep(1)">&larr; Πίσω</button>
                    <button type="button" class="ptl-btn-next" onclick="ptlrSmartNextStep(3)">Επόμενο &rarr;</button>
                </div>
            </div>

            <!-- ΒΗΜΑ 3 -->
            <div class="ptl-step" id="rec-step-3">
                <h3>🍲 Υλικά Συνταγής</h3>

                <label class="ptl-section-label">1. Κύρια Πρωτεΐνη (Επιλέξτε 1)</label>
                <select name="protein_1">
                    <option value="" disabled selected>Επίλεξε...</option>
                    <?php foreach ( $r_data['proteins_1'] as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>"><?php echo esc_html( $item ); ?></option><?php endforeach; ?>
                </select>

                <label class="ptl-section-label">2. Δευτερεύουσα Πρωτεΐνη</label>
                <select name="protein_2">
                    <option value="" disabled selected>Επίλεξε...</option>
                    <?php foreach ( $r_data['proteins_2'] as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>"><?php echo esc_html( $item ); ?></option><?php endforeach; ?>
                </select>

                <label class="ptl-section-label">3. 1η Πηγή Υδατάνθρακα</label>
                <select name="carb_1">
                    <option value="" disabled selected>Επίλεξε...</option>
                    <?php foreach ( $r_data['carbs_1'] as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>"><?php echo esc_html( $item ); ?></option><?php endforeach; ?>
                </select>

                <label class="ptl-section-label">4. 2η Πηγή Υδατάνθρακα</label>
                <select name="carb_2">
                    <option value="" disabled selected>Επίλεξε...</option>
                    <?php foreach ( $r_data['carbs_2'] as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>"><?php echo esc_html( $item ); ?></option><?php endforeach; ?>
                </select>

                <label class="ptl-section-label">5. Λαχανικά (Ακριβώς 2)</label>
                <div class="ptl-checkbox-group" id="ptl-veg-group">
                    <?php foreach ( $r_data['veggies'] as $item ) : $slug = sanitize_title( $item ); ?>
                        <input type="checkbox" id="veg_<?php echo esc_attr( $slug ); ?>" name="veggies[]" value="<?php echo esc_attr( $item ); ?>">
                        <label for="veg_<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $item ); ?></label>
                    <?php endforeach; ?>
                </div>
                <div id="ptl-veg-hint" class="ptl-veg-hint">✅ Έχετε επιλέξει 2/2 λαχανικά. Για να διαλέξετε κάποιο άλλο, ξε-τικάρετε ένα από τα ήδη επιλεγμένα.</div>
                <p id="veg-error" style="color:#d63638; font-size:13px; font-weight:bold; display:none; margin-top:8px;">⚠️ Επιλέξτε ακριβώς 2 λαχανικά.</p>

                <label class="ptl-section-label">6. Φρούτα</label>
                <select name="fruit">
                    <option value="" disabled selected>Επίλεξε...</option>
                    <?php foreach ( $r_data['fruits'] as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>"><?php echo esc_html( $item ); ?></option><?php endforeach; ?>
                </select>

                <label class="ptl-section-label">7. Έλαια</label>
                <select name="oil">
                    <option value="" disabled selected>Επίλεξε...</option>
                    <?php foreach ( $r_data['oils'] as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>"><?php echo esc_html( $item ); ?></option><?php endforeach; ?>
                </select>

                <div id="ptl-premium-warning" class="ptl-premium-warning">✨ Έχετε επιλέξει Premium υλικά (🔒).<br><span style="font-weight:normal;">Η αποθήκευση αυτής της συνταγής θα απαιτήσει πληρωμή στο επόμενο βήμα.</span></div>
                <div id="ptl-step3-error" class="ptl-inline-error" style="margin-top:20px; text-align:center; font-size:15px;"></div>

                <div class="ptl-quiz-nav">
                    <button type="button" class="ptl-btn-prev" onclick="ptlrNextStep(2)">&larr; Πίσω</button>
                    <button type="button" class="ptl-btn-next" onclick="ptlrValidateStep3()">Επόμενο &rarr;</button>
                </div>
            </div>

            <!-- ΒΗΜΑ 4 -->
            <div class="ptl-step" id="rec-step-4">
                <div id="ptl-duplicate-box" style="display:none;"></div>
                <div id="ptl-step4-body">
                    <h3>📩 Ολοκλήρωση</h3>
                    <?php if ( $is_logged ) : ?>
                        <div style="background:#eef7ee; padding:20px; border-radius:8px; border-left:4px solid #5b9a68; margin-bottom:20px;">
                            <p style="margin:0; font-size:15px; color:#333;">Συνδεδεμένος λογαριασμός: <strong><?php echo esc_html( $current_user->display_name ); ?></strong>.</p>
                            <p style="margin:5px 0 0 0; font-size:13px; color:#666;">Η συνταγή θα αποθηκευτεί στο προφίλ σας!</p>
                        </div>
                        <input type="hidden" name="user_name" value="<?php echo esc_attr( $current_user->display_name ); ?>">
                        <input type="hidden" name="user_email" value="<?php echo esc_attr( $current_user->user_email ); ?>">
                    <?php else : ?>
                        <p class="ptl-desc" style="margin-bottom:15px;">Συμπληρώστε τα στοιχεία σας για να αποθηκεύσουμε τη συνταγή!</p>
                        <label>Όνοματεπώνυμο Ιδιοκτήτη:</label><input type="text" name="user_name" required>
                        <label>Email:</label><input type="email" name="user_email" required>
                        <label>Κωδικός Πρόσβασης:</label><input type="password" name="user_password" required>
                        <p style="font-size:12px; color:#666; margin-top:5px; line-height:1.4;">Δημιουργήστε έναν κωδικό. Αν έχετε ήδη λογαριασμό, βάλτε τον κωδικό σας και θα συνδεθείτε αυτόματα.</p>
                    <?php endif; ?>
                </div>

                <div class="ptl-quiz-nav">
                    <button type="button" class="ptl-btn-prev" onclick="ptlrSmartPrevStep(3)">&larr; Πίσω</button>
                    <button type="submit" class="ptl-btn-submit"><?php echo esc_html( $submit_text ); ?></button>
                </div>
            </div>

            <!-- LOADING & ΑΠΟΤΕΛΕΣΜΑ -->
            <div class="ptl-step" id="rec-step-loading" style="text-align:center; padding: 40px 10px;">
                <h3 id="loading-title">⚙️ Ετοιμάζουμε τη συνταγή...</h3>
                <div style="width:100%; background:#e0d5c1; border-radius:10px; height:20px; overflow:hidden; margin:30px 0; border: 1px solid #C7B297;">
                    <div id="ptl-loading-progress" style="width:0%; background:#43282F; height:100%; transition:width 0.8s ease-in-out;"></div>
                </div>
                <p id="loading-desc" style="font-size: 14px; color: #888;">Υπολογισμός μερίδων...</p>
            </div>

            <div class="ptl-step" id="rec-step-result">
                <div id="ptl-recipe-content"></div>
                <div style="text-align:center; margin-top:30px;">
                    <button type="button" class="ptl-btn-prev" onclick="location.reload();">Επανεκκίνηση</button>
                </div>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// =========================================================================
// AJAX: ΕΠΕΞΕΡΓΑΣΙΑ ΣΥΝΤΑΓΗΣ
// =========================================================================
add_action( 'wp_ajax_ptl_process_recipe', 'ptl_process_recipe_ajax' );
add_action( 'wp_ajax_nopriv_ptl_process_recipe', 'ptl_process_recipe_ajax' );
function ptl_process_recipe_ajax() {
    check_ajax_referer( 'ptl_recipe_nonce', 'security' );

    $pet_id       = sanitize_text_field( $_POST['pet_id'] ?? '' );
    $pet_name     = sanitize_text_field( $_POST['pet_name'] ?? '' );
    $pet_weight   = floatval( $_POST['pet_weight'] ?? 0 );
    $pet_stage    = sanitize_text_field( $_POST['pet_stage'] ?? 'adult' );
    $pet_neutered = sanitize_text_field( $_POST['pet_neutered'] ?? 'no' );
    $pet_activity = sanitize_text_field( $_POST['pet_activity'] ?? 'medium' );

    $health_tier      = sanitize_text_field( $_POST['health_tier'] ?? 'none' );
    $health_condition = '';
    if ( $health_tier === 'clinical' ) { $health_condition = sanitize_text_field( $_POST['health_condition_clinical'] ?? '' ); }
    elseif ( $health_tier === 'mild' ) { $health_condition = sanitize_text_field( $_POST['health_condition_mild'] ?? '' ); }

    $new_account = false;
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $user_id      = $current_user->ID;
        $user_email   = $current_user->user_email;
        $user_name    = $current_user->display_name;
    } else {
        $user_name     = sanitize_text_field( $_POST['user_name'] ?? '' );
        $user_email    = sanitize_email( $_POST['user_email'] ?? '' );
        $user_password = $_POST['user_password'] ?? '';
        if ( empty( $user_password ) ) wp_send_json_error( 'Παρακαλώ εισάγετε κωδικό πρόσβασης.' );

        $user_id = email_exists( $user_email );
        if ( $user_id ) {
            $user = get_user_by( 'email', $user_email );
            if ( $user && wp_check_password( $user_password, $user->user_pass, $user->ID ) ) {
                wp_set_current_user( $user->ID );
                wp_set_auth_cookie( $user->ID );
            } else {
                wp_send_json_error( 'Το email υπάρχει ήδη. Παρακαλώ εισάγετε τον σωστό κωδικό σας ή κάντε επαναφορά κωδικού.' );
            }
        } else {
            $user_id = wp_create_user( $user_email, $user_password, $user_email );
            if ( is_wp_error( $user_id ) ) wp_send_json_error( 'Σφάλμα: ' . $user_id->get_error_message() );
            wp_update_user( array( 'ID' => $user_id, 'first_name' => $user_name ) );
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id );
            $new_account = true;
        }
    }

    // ΕΝΗΜΕΡΩΣΗ ΙΑΤΡΙΚΟΥ ΠΡΟΦΙΛ
    $health_map = array(
        'Τροφική Αλλεργία / Δερματικά' => 'allergies', 'Ευαίσθητο Στομάχι / Αέρια' => 'gastrointestinal',
        'Ευαίσθητες Αρθρώσεις' => 'arthritis', 'Τάση για παχυσαρκία / Στειρωμένο' => 'obesity',
        'Γαστρεντερικά / ΙΦΝΕ' => 'gastrointestinal', 'Νεφρική Ανεπάρκεια' => 'kidney',
        'Ουροποιητικό (Κρύσταλλοι/Πέτρες)' => 'urinary', 'Σακχαρώδης Διαβήτης' => 'thyroid_diabetes',
        'Λεϊσμανίαση (Καλαζάρ)' => 'leishmaniasis'
    );
    $mapped_health = $health_map[ $health_condition ] ?? '';

    $saved_pets = get_user_meta( $user_id, 'petling_pets', true );
    if ( ! is_array( $saved_pets ) ) $saved_pets = array();
    $pet_found = false;

    if ( ! empty( $pet_id ) ) {
        foreach ( $saved_pets as &$p ) {
            if ( isset( $p['id'] ) && $p['id'] === $pet_id ) {
                $p['name'] = $pet_name; $p['weight'] = $pet_weight; $p['neutered'] = $pet_neutered; $p['energy'] = $pet_activity;
                if ( ! isset( $p['health'] ) || ! is_array( $p['health'] ) ) $p['health'] = array();
                if ( $mapped_health && ! in_array( $mapped_health, $p['health'] ) ) { $p['health'][] = $mapped_health; }
                $pet_found = true; break;
            }
        }
        unset( $p );
    }
    if ( ! $pet_found ) {
        $pet_id = uniqid( 'pet_' );
        $bday   = ( $pet_stage === 'puppy' ) ? date( 'Y-m-d', strtotime( '-6 months' ) ) : ( ( $pet_stage === 'senior' ) ? date( 'Y-m-d', strtotime( '-8 years' ) ) : date( 'Y-m-d', strtotime( '-3 years' ) ) );
        $saved_pets[] = array( 'id' => $pet_id, 'name' => $pet_name, 'type' => 'dog', 'birthday' => $bday, 'energy' => $pet_activity, 'weight' => $pet_weight, 'neutered' => $pet_neutered, 'health' => $mapped_health ? array( $mapped_health ) : array() );
    }
    update_user_meta( $user_id, 'petling_pets', $saved_pets );

    // VET LOGIC
    global $wpdb;
    $promo_table  = $wpdb->prefix . 'petling_partner_leads';
    $table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '$promo_table'" ) === $promo_table );
    $dynamic_code = 'VET-' . strtoupper( substr( md5( uniqid() ), 0, 6 ) );

    if ( $health_tier === 'clinical' ) {
        if ( $table_exists ) { $wpdb->insert( $promo_table, array( 'email' => $user_email, 'partner_prefix' => 'VET', 'type' => 'appointment', 'coupon_code' => $dynamic_code, 'status' => 'active', 'created_at' => current_time( 'mysql' ) ) ); }
        $pid = wp_insert_post( array( 'post_title' => 'Συνταγή #' . strtoupper( substr( md5( uniqid() ), 0, 6 ) ), 'post_type' => 'ptl_quiz_lead', 'post_status' => 'publish' ) );
        update_post_meta( $pid, 'ptl_lead_email', sanitize_email( $user_email ) );
        update_post_meta( $pid, 'ptl_lead_name', sanitize_text_field( $user_name ) );
        update_post_meta( $pid, 'ptl_lead_type', 'HOME_COOKED_RECIPE' );
        update_post_meta( $pid, 'ptl_lead_result', 'VET (Κλινική Πάθηση: ' . $health_condition . ')' );
        update_post_meta( $pid, 'ptl_health_condition', $health_condition );
        update_post_meta( $pid, 'ptl_user_id', $user_id );

        $html    = '<h3>🩺 Απαιτείται Κλινική Δίαιτα</h3><div class="ptl-result-box"><h4 style="color: #43282F;">Κλείστε Ραντεβού με τη Δρ. Μανωλάκου</h4><p>Η κατάσταση του σκύλου σας απαιτεί εξατομικευμένη ιατρική προσέγγιση. Ο κωδικός σας για <strong>10% έκπτωση</strong> στο ραντεβού είναι:</p><div style="background:#F5EDE3; padding:15px; font-size:24px; font-weight:bold; color:#43282F; border-radius:6px; text-align:center;">' . $dynamic_code . '</div></div>';
        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: Petling <info@petling.gr>' );
        wp_mail( $user_email, 'Κωδικός Κλινικής Δίαιτας', $html, $headers );
        wp_send_json_success( $html ); exit;
    }

    $p1      = sanitize_text_field( $_POST['protein_1'] ?? '' );
    $p2      = sanitize_text_field( $_POST['protein_2'] ?? '' );
    $c1      = sanitize_text_field( $_POST['carb_1'] ?? '' );
    $c2      = sanitize_text_field( $_POST['carb_2'] ?? '' );
    $fruit   = sanitize_text_field( $_POST['fruit'] ?? '' );
    $oil     = sanitize_text_field( $_POST['oil'] ?? '' );
    $veggies = isset( $_POST['veggies'] ) ? array_map( 'sanitize_text_field', $_POST['veggies'] ) : array();

    if ( empty( $p1 ) || empty( $p2 ) || empty( $c1 ) || empty( $c2 ) || empty( $fruit ) || empty( $oil ) || count( $veggies ) !== 2 ) {
        wp_send_json_error( 'Παρακαλώ επιλέξτε όλα τα απαραίτητα υλικά (και ακριβώς 2 λαχανικά) στο Βήμα 3.' );
        exit;
    }

    $p1_clean = trim( str_replace( '🔒', '', $p1 ) ); $p2_clean = trim( str_replace( '🔒', '', $p2 ) );
    $c1_clean = trim( str_replace( '🔒', '', $c1 ) ); $c2_clean = trim( str_replace( '🔒', '', $c2 ) );
    $fruit_clean = trim( str_replace( '🔒', '', $fruit ) ); $oil_clean = trim( str_replace( '🔒', '', $oil ) );
    $clean_veggies = array_map( function( $v ) { return trim( str_replace( '🔒', '', $v ) ); }, $veggies );
    $veg_str_clean = implode( ' & ', $clean_veggies );

    $pet_name_lower           = mb_strtolower( trim( $pet_name ), 'UTF-8' );
    $unlocked_count           = 0;
    $found_unlocked_duplicate = false;

    $existing_posts = get_posts( array(
        'post_type'      => 'ptl_quiz_lead',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array( 'key' => 'ptl_user_id', 'value' => $user_id ),
            array( 'key' => 'ptl_lead_type', 'value' => 'HOME_COOKED_RECIPE' )
        )
    ) );

    foreach ( $existing_posts as $post_item ) {
        $is_locked = get_post_meta( $post_item->ID, 'ptl_locked_recipe', true );
        if ( $is_locked !== 'yes' ) { $unlocked_count++; }

        $saved_json = get_post_meta( $post_item->ID, 'ptl_recipe_data', true );
        if ( $saved_json ) {
            $saved_data = json_decode( $saved_json, true );
            $s_name     = mb_strtolower( trim( $saved_data['pet_name'] ?? '' ), 'UTF-8' );
            $s_weight   = floatval( $saved_data['pet_weight'] ?? 0 );
            $s_veggies  = is_array( $saved_data['veggies'] ) ? implode( ' & ', $saved_data['veggies'] ) : ( $saved_data['veggies'] ?? '' );

            if ( $s_name === $pet_name_lower &&
                 $s_weight === floatval( $pet_weight ) &&
                 trim( $saved_data['p1'] ?? '' ) === $p1_clean &&
                 trim( $saved_data['p2'] ?? '' ) === $p2_clean &&
                 trim( $saved_data['c1'] ?? '' ) === $c1_clean &&
                 trim( $saved_data['c2'] ?? '' ) === $c2_clean &&
                 trim( $saved_data['fruit'] ?? '' ) === $fruit_clean &&
                 trim( $saved_data['oil'] ?? '' ) === $oil_clean &&
                 $s_veggies === $veg_str_clean ) {

                if ( $is_locked !== 'yes' ) { $found_unlocked_duplicate = true; }
                else { wp_delete_post( $post_item->ID, true ); }
            }
        }
    }

    if ( $found_unlocked_duplicate ) {
        wp_send_json_error( array(
            'message'       => 'Η συνταγή με ακριβώς αυτά τα υλικά για τον/την <strong>' . esc_html( $pet_name ) . '</strong> είναι ήδη έτοιμη στο προφίλ σας.',
            'is_duplicate'  => true,
            'dashboard_url' => home_url( '/diatrofi/?r=' . time() )
        ) );
    }

    $veg_str       = implode( ' & ', $veggies );
    $is_premium    = ( strpos( $p1, '🔒' ) !== false || strpos( $p2, '🔒' ) !== false || strpos( $c1, '🔒' ) !== false || strpos( $c2, '🔒' ) !== false || strpos( $veg_str, '🔒' ) !== false || strpos( $fruit, '🔒' ) !== false || strpos( $oil, '🔒' ) !== false );
    $needs_payment = ( $unlocked_count >= 2 || $is_premium );
    $new_signature = md5( $pet_name_lower . '|' . $pet_weight . '|' . $p1_clean . '|' . $p2_clean . '|' . $c1_clean . '|' . $c2_clean . '|' . $veg_str_clean . '|' . $fruit_clean . '|' . $oil_clean );

    $multiplier = 0.025;
    if ( $pet_stage === 'puppy' ) $multiplier = 0.04;
    if ( $pet_stage === 'senior' ) $multiplier = 0.02;
    if ( $pet_activity === 'low' ) $multiplier -= 0.005;
    if ( $pet_activity === 'high' ) $multiplier += 0.01;
    if ( $pet_neutered === 'yes' ) $multiplier -= 0.005;
    if ( $multiplier < 0.015 ) $multiplier = 0.015;
    if ( $multiplier > 0.08 ) $multiplier = 0.08;

    $total_g = round( $pet_weight * 1000 * $multiplier );
    if ( $total_g < 50 ) $total_g = 50;

    $g_p1 = round( $total_g * 0.50 ); $g_p2 = round( $total_g * 0.05 ); $g_c1 = round( $total_g * 0.15 ); $g_c2 = round( $total_g * 0.10 ); $g_veg = round( $total_g * 0.13 ); $g_fruit = round( $total_g * 0.05 ); $g_oil = round( $total_g * 0.02 );
    $recipe_summary_text = "{$g_p1}γρ {$p1_clean} / {$g_p2}γρ {$p2_clean} / {$g_c1}γρ {$c1_clean} / {$g_c2}γρ {$c2_clean} / {$g_veg}γρ {$veg_str_clean} / {$g_fruit}γρ {$fruit_clean} / {$g_oil}γρ {$oil_clean}";

    $pid = wp_insert_post( array( 'post_title' => 'Συνταγή #' . strtoupper( substr( md5( uniqid() ), 0, 6 ) ), 'post_type' => 'ptl_quiz_lead', 'post_status' => 'publish' ) );
    update_post_meta( $pid, 'ptl_lead_email', sanitize_email( $user_email ) );
    update_post_meta( $pid, 'ptl_lead_name', sanitize_text_field( $user_name ) );
    update_post_meta( $pid, 'ptl_lead_type', 'HOME_COOKED_RECIPE' );
    update_post_meta( $pid, 'ptl_lead_result', sanitize_text_field( $recipe_summary_text ) );
    update_post_meta( $pid, 'ptl_user_id', $user_id );
    update_post_meta( $pid, 'ptl_recipe_signature', $new_signature );
    if ( ! empty( $health_condition ) ) update_post_meta( $pid, 'ptl_health_condition', $health_condition );

    $recipe_data_array = array(
        'p1' => $p1_clean, 'p2' => $p2_clean, 'c1' => $c1_clean, 'c2' => $c2_clean, 'veggies' => $clean_veggies, 'fruit' => $fruit_clean, 'oil' => $oil_clean, 'total_g' => $total_g,
        'pet_name' => $pet_name, 'pet_weight' => $pet_weight, 'pet_stage' => $pet_stage, 'pet_neutered' => $pet_neutered, 'pet_activity' => $pet_activity
    );
    update_post_meta( $pid, 'ptl_recipe_data', json_encode( $recipe_data_array, JSON_UNESCAPED_UNICODE ) );

    if ( $needs_payment ) {
        update_post_meta( $pid, 'ptl_locked_recipe', 'yes' );
        if ( function_exists( 'WC' ) && WC()->cart ) { WC()->cart->empty_cart(); }
        wp_send_json_success( array( 'redirect' => wc_get_checkout_url() . '?add-to-cart=1016376' ) );
        exit;
    }

    $dashboard_url = home_url( '/diatrofi/?r=' . time() );
    $html  = '<h3>🎉 Η Συνταγή για τον/την ' . esc_html( $pet_name ) . ' είναι έτοιμη!</h3>';
    $html .= '<div class="ptl-result-box" style="text-align:left;">';
    $html .= $new_account
        ? '<div style="background:#eef7ee; color:#5b9a68; border-left:4px solid #5b9a68; padding:15px; margin-bottom:20px; font-size:14px;">✅ <strong>Ο λογαριασμός σας δημιουργήθηκε επιτυχώς!</strong> Έχετε ήδη συνδεθεί.</div>'
        : '<div style="background:#eef7ee; color:#5b9a68; border-left:4px solid #5b9a68; padding:15px; margin-bottom:20px; font-size:14px;">✅ <strong>Η συνταγή αποθηκεύτηκε επιτυχώς</strong> στον λογαριασμό σας!</div>';
    $html .= '<p>Η συνταγή του σκύλου σας αποθηκεύτηκε με ασφάλεια στο <strong>Προσωπικό σας Προφίλ</strong>. Από εκεί μπορείτε να τη δείτε και να την κατεβάσετε σε PDF ανά πάσα στιγμή.</p>';
    $html .= '<div style="text-align:center; margin-top:25px;">';
    $html .= '<a href="' . esc_url( $dashboard_url ) . '" style="display:inline-block; background:#43282F; color:#fff; padding:15px 30px; border-radius:6px; text-decoration:none; font-weight:bold; font-size:16px;">Μετάβαση στο Προφίλ...</a>';
    $html .= '<p style="font-size:13px; color:#888; margin-top:12px;">Αυτόματη μεταφορά σε λίγα δευτερόλεπτα... ⏳</p>';
    $html .= '</div></div>';
    $html .= '<script>setTimeout(function(){ window.location.href = "' . esc_url_raw( $dashboard_url ) . '"; }, 3500);</script>';

    ptl_send_recipe_email( $user_email, 'Η Συνταγή σας είναι έτοιμη! 🐾', '🎉 Η Συνταγή είναι έτοιμη!', $recipe_data_array, $dashboard_url );
    wp_send_json_success( $html );
}

// Κοινή helper function για το email συνταγής (Δωρεάν & Unlock)
function ptl_send_recipe_email( $to_email, $subject, $heading, $data, $cta_url ) {
    $logo_url = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
    if ( empty( $logo_url ) ) { $logo_url = home_url( '/wp-content/uploads/logo.png' ); }

    $p_name  = ! empty( $data['pet_name'] ) ? $data['pet_name'] : 'τον σκύλο σας';
    $total_g = floatval( $data['total_g'] ?? 300 );
    $g_p1 = round( $total_g * 0.50 ); $g_p2 = round( $total_g * 0.05 ); $g_c1 = round( $total_g * 0.15 ); $g_c2 = round( $total_g * 0.10 ); $g_veg = round( $total_g * 0.13 ); $g_fruit = round( $total_g * 0.05 ); $g_oil = round( $total_g * 0.02 );
    $veg_str = is_array( $data['veggies'] ) ? implode( ' & ', $data['veggies'] ) : ( $data['veggies'] ?? '' );

    $email_body  = '<div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; color: #333;">';
    $email_body .= '<div style="text-align:center; padding-bottom:15px; margin-bottom:20px; border-bottom:2px solid #C7B297;">';
    $email_body .= '<img src="' . esc_url( $logo_url ) . '" style="display:block; margin: 0 auto 15px auto; max-width:220px; height:auto;" alt="Petling Logo">';
    $email_body .= '<h2 style="color:#43282F; margin:0;">' . esc_html( $heading ) . '</h2></div>';
    $email_body .= '<div style="background:#fffaf1; padding:25px; border-radius:8px; border:2px solid #C7B297; margin-top:20px; text-align:left;">';
    $email_body .= '<h4 style="color:#43282F; margin-top:0; border-bottom:1px solid #e0d5c1; padding-bottom:10px; font-size:18px;">Συνταγή για τον/την ' . esc_html( $p_name ) . ' (' . $total_g . 'γρ.)</h4>';
    $email_body .= '<ul style="list-style:none; padding:10px 0; margin:0 0 20px 0; font-size:15px; color:#333;">';
    $email_body .= '<li style="padding:8px 0; border-bottom:1px dashed #e0d5c1;">🥩 <strong>' . $g_p1 . 'γρ</strong> ' . esc_html( $data['p1'] ?? '' ) . ' <span style="font-size:13px; color:#666;">(βραστό, χωρίς πέτσα/κόκκαλα)</span></li>';
    $email_body .= '<li style="padding:8px 0; border-bottom:1px dashed #e0d5c1;">🥚 <strong>' . $g_p2 . 'γρ</strong> ' . esc_html( $data['p2'] ?? '' ) . '</li>';
    $email_body .= '<li style="padding:8px 0; border-bottom:1px dashed #e0d5c1;">🌾 <strong>' . $g_c1 . 'γρ</strong> ' . esc_html( $data['c1'] ?? '' ) . ' <span style="font-size:13px; color:#666;">(μαγειρεμένο)</span></li>';
    $email_body .= '<li style="padding:8px 0; border-bottom:1px dashed #e0d5c1;">🥔 <strong>' . $g_c2 . 'γρ</strong> ' . esc_html( $data['c2'] ?? '' ) . ' <span style="font-size:13px; color:#666;">(βραστά, πουρέ, χωρίς φλούδα)</span></li>';
    $email_body .= '<li style="padding:8px 0; border-bottom:1px dashed #e0d5c1;">🥦 <strong>' . $g_veg . 'γρ</strong> ' . esc_html( $veg_str ) . ' <span style="font-size:13px; color:#666;">(βραστά, πολτοποιημένα)</span></li>';
    $email_body .= '<li style="padding:8px 0; border-bottom:1px dashed #e0d5c1;">🍎 <strong>' . $g_fruit . 'γρ</strong> ' . esc_html( $data['fruit'] ?? '' ) . ' <span style="font-size:13px; color:#666;">(τεμαχισμένο, χωρίς φλούδα/κουκούτσια)</span></li>';
    $email_body .= '<li style="padding:8px 0; border-bottom:1px dashed #e0d5c1;">🫒 <strong>' . $g_oil . 'γρ</strong> ' . esc_html( $data['oil'] ?? '' ) . ' <span style="font-size:13px; color:#666;">(στο τέλος)</span></li>';
    $email_body .= '</ul>';
    $email_body .= '<div style="padding:15px; background:#fff; border-left:4px solid #d63638; border-radius:4px; margin-bottom: 20px;"><h4 style="margin-top:0; color:#d63638; font-size:15px;">⚠️ Σημαντική Επιστημονική Επισήμανση</h4><p style="font-size:13px; line-height:1.5; color:#555; margin:0;">Οι ποσότητες είναι ενδεικτικές και ενδέχεται να χρειαστούν προσαρμογή. Η συγκεκριμένη διατροφή <strong>δεν πρέπει να εφαρμόζεται εφ’ όρου ζωής χωρίς επίβλεψη</strong>.</p></div>';
    $email_body .= '<div style="text-align:right; margin-bottom:30px;"><p style="margin:0; font-size:14px; color:#333;"><strong>Δρ. Σταυριάννα Μανωλάκου</strong></p><p style="margin:0; font-size:13px; color:#666;">Κτηνίατρος - Ειδική Διατροφολόγος</p></div>';
    $email_body .= '<div style="text-align:center;"><a href="' . esc_url( $cta_url ) . '" style="display:inline-block; background:#43282F; color:#fff; padding:15px 30px; border-radius:6px; text-decoration:none; font-weight:bold; font-size:15px;">Προβολή στο Προφίλ &amp; Εκτύπωση PDF &rarr;</a></div>';
    $email_body .= '</div></div>';

    $headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: Petling <info@petling.gr>' );
    wp_mail( $to_email, $subject, $email_body, $headers );
}

// =========================================================================
// WOOCOMMERCE PAY-PER-RECIPE UNLOCK & REDIRECT HOOKS
// =========================================================================
add_action( 'woocommerce_payment_complete', 'ptl_unlock_recipes_on_purchase' );
add_action( 'woocommerce_order_status_completed', 'ptl_unlock_recipes_on_purchase' );
function ptl_unlock_recipes_on_purchase( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || get_post_meta( $order_id, '_ptl_recipe_unlocked', true ) ) return;

    $user_id = $order->get_user_id();
    if ( ! $user_id ) return;

    $has_premium = false;
    foreach ( $order->get_items() as $item ) {
        if ( $item->get_product_id() == 1016376 ) { $has_premium = true; break; }
    }

    if ( $has_premium ) {
        update_post_meta( $order_id, '_ptl_recipe_unlocked', 'yes' );
        $locked_recipes = get_posts( array(
            'post_type'      => 'ptl_quiz_lead',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array( 'key' => 'ptl_user_id', 'value' => $user_id ),
                array( 'key' => 'ptl_locked_recipe', 'value' => 'yes' )
            )
        ) );
        $user_info = get_userdata( $user_id );
        foreach ( $locked_recipes as $lr ) {
            delete_post_meta( $lr->ID, 'ptl_locked_recipe' );
            $recipe_data_json = get_post_meta( $lr->ID, 'ptl_recipe_data', true );
            if ( $recipe_data_json ) {
                $data = json_decode( $recipe_data_json, true );
                ptl_send_recipe_email( $user_info->user_email, 'Η Premium Συνταγή σας ξεκλειδώθηκε! 🐾', '🎉 Η Premium Συνταγή ξεκλειδώθηκε!', $data, home_url( '/diatrofi/' ) );
            }
        }
    }
}

add_action( 'template_redirect', 'ptl_force_redirect_after_purchase' );
function ptl_force_redirect_after_purchase() {
    if ( is_wc_endpoint_url( 'order-received' ) ) {
        global $wp;
        $order_id = absint( $wp->query_vars['order-received'] );
        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                foreach ( $order->get_items() as $item ) {
                    if ( $item->get_product_id() == 1016376 ) {
                        wp_safe_redirect( home_url( '/diatrofi/?payment=success' ) );
                        exit;
                    }
                }
            }
        }
    }
}