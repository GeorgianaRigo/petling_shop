<?php
/*
 * Δύο shortcodes:
 *   [petling_partner_promo prefix="JOY"]   -> η φόρμα που παίρνει email ο πελάτης
 *   [petling_partner_redeem prefix="VET"]  -> η σελίδα όπου ο συνεργάτης "καταναλώνει" τον κωδικό
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Βοηθητική συνάρτηση για να φορτώνουμε τα αρχεία με ασφάλεια ΜΕΣΑ από τα shortcodes
function petling_promo_enqueue_assets_safely() {
    wp_enqueue_style( 'petling-promo', PETLING_PROMO_URL . 'assets/css/promo.css', array(), filemtime( PETLING_PROMO_DIR . 'assets/css/promo.css' ) );
    wp_enqueue_script( 'petling-promo', PETLING_PROMO_URL . 'assets/js/promo.js', array( 'jquery' ), filemtime( PETLING_PROMO_DIR . 'assets/js/promo.js' ), true );
    
    // Περνάμε το ajax URL στο JS
    wp_localize_script( 'petling-promo', 'petlingPromo', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    ) );
}

// =========================================================================
// [petling_partner_promo prefix="JOY"] - φόρμα claim για τον πελάτη
// =========================================================================
add_shortcode( 'petling_partner_promo', 'petling_partner_promo_shortcode' );
function petling_partner_promo_shortcode( $atts ) {

    // Φορτώνουμε τα CSS/JS απευθείας την ώρα που τρέχει το shortcode (λύση για Elementor)
    petling_promo_enqueue_assets_safely();

    $a = shortcode_atts( array(
        'prefix' => 'PTL',
    ), $atts );

    $prefix  = sanitize_text_field( strtoupper( $a['prefix'] ) );
    $partner = petling_promo_get_partner( $prefix );

    if ( ! $partner ) {
        return '<p style="color:#e62121;">Άγνωστος συνεργάτης "' . esc_html( $prefix ) . '". Έλεγξε το prefix στο shortcode ή πρόσθεσέ τον στις ρυθμίσεις του plugin.</p>';
    }

    $amount_text  = petling_promo_format_amount( $partner );
    $partner_name = esc_html( $partner['label'] );

    ob_start();
    ?>
    <div class="ptl-promo-container" id="ptl-promo-<?php echo esc_attr( strtolower( $prefix ) ); ?>">
        <div class="ptl-promo-inner">
            <h3>🎁 Πάρε την Έκπτωσή σου!</h3>
            <p>Βάλε το email σου για να λάβεις τον αποκλειστικό σου κωδικό <strong><?php echo esc_html( $amount_text ); ?></strong> από <?php echo $partner_name; ?> &amp; το Petling!</p>

            <form class="ptl-promo-form">
                <?php wp_nonce_field( 'petling_promo_nonce', 'security', true, true ); ?>
                <input type="hidden" name="promo_prefix" value="<?php echo esc_attr( $prefix ); ?>">
                <input type="email" name="promo_email" placeholder="Το email σου εδώ..." required>
                <label class="ptl-checkbox">
                    <input type="checkbox" name="ptl_consent" required>
                    <span>Συμφωνώ να λάβω τον κωδικό μου στο email που έδωσα.</span>
                </label>
                <button type="submit" class="ptl-btn-submit">Λήψη Κωδικού</button>
                <div class="ptl-loader" style="display:none;">⏳ Δημιουργία κωδικού...</div>
                <div class="ptl-message-area" style="margin-top:15px;"></div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// =========================================================================
// [petling_partner_redeem prefix="VET"] - σελίδα εξαργύρωσης για συνεργάτη
// =========================================================================
add_shortcode( 'petling_partner_redeem', 'petling_partner_redeem_shortcode' );
function petling_partner_redeem_shortcode( $atts ) {

    // Φορτώνουμε τα CSS/JS απευθείας την ώρα που τρέχει το shortcode (λύση για Elementor)
    petling_promo_enqueue_assets_safely();

    $a = shortcode_atts( array(
        'prefix' => '',
    ), $atts );

    $prefix  = sanitize_text_field( strtoupper( $a['prefix'] ) );
    $partner = petling_promo_get_partner( $prefix );

    if ( ! $partner || 'appointment' !== $partner['type'] ) {
        return '<p style="color:#e62121;">Αυτή η σελίδα δεν είναι διαθέσιμη για αυτόν τον συνεργάτη.</p>';
    }

    ob_start();
    ?>
    <div class="ptl-redeem-container">
        <div class="ptl-redeem-inner">
            <h3>🔒 Έλεγχος &amp; Εξαργύρωση Κωδικού</h3>
            <p>Σελίδα για <?php echo esc_html( $partner['label'] ); ?>. Γράψε τον κωδικό πρόσβασής σου και τον κωδικό (ή το email) του πελάτη.</p>

            <form class="ptl-redeem-form">
                <?php wp_nonce_field( 'petling_redeem_nonce', 'security', true, true ); ?>
                <input type="hidden" name="redeem_prefix" value="<?php echo esc_attr( $prefix ); ?>">

                <input type="password" name="redeem_password" placeholder="Κωδικός πρόσβασής σου" required>
                <input type="text" name="redeem_lookup" placeholder="Κωδικός πελάτη (π.χ. VET-A1B2C) ή email" required>

                <button type="submit" class="ptl-btn-submit ptl-btn-redeem">Έλεγχος &amp; Επιβεβαίωση</button>
                <div class="ptl-loader" style="display:none;">⏳ Έλεγχος...</div>
                <div class="ptl-message-area" style="margin-top:15px;"></div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}