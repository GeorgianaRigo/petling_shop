<?php
/**
 * Petmania Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Petmania Child
 */

add_action( 'wp_enqueue_scripts', 'petmania_child_enqueue_assets' );
/**
 * Enqueue scripts and styles.
 */
function petmania_child_enqueue_assets() {
    $parent_style = 'vamtam-petmania-style'; // Το handle του CSS του γονικού θέματος.

    // Φόρτωση του CSS του γονικού θέματος
    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );

    // Φόρτωση του CSS του child θέματος
    wp_enqueue_style( 
        'petmania-child-style',
        get_stylesheet_uri(),
        array( $parent_style )
    );

    // Φόρτωση του custom JavaScript αρχείου
    wp_enqueue_script(
        'petmania-child-custom-script', // Όνομα (handle) για το script σου
        get_stylesheet_directory_uri() . '/js/custom-script.js', // Η διαδρομή προς το αρχείο
        array( 'jquery' ), // Δήλωση ότι εξαρτάται από το jQuery
        '1.0.0', // Αριθμός έκδοσης (καλό για caching)
        true // Φόρτωση του script στο footer της σελίδας για καλύτερη απόδοση
    );
}
\add_filter( 'gettext', 'change_elementor_pro_coupon_text', 20, 3 );

function change_elementor_pro_coupon_text( $translated_text, $text, $domain ) {
    if ( 'elementor-pro' === $domain ) {
        // Εδώ "πιάνουμε" τις φράσεις που θέλουμε και τις αλλάζουμε
        switch ( $text ) {
            case 'If you have a coupon code, please apply it below.':
                $translated_text = 'Αν έχετε κάποιο κουπόνι, εισάγετε τον κωδικό παρακάτω.';
                break;
            
            // Μπορείς να προσθέσεις κι άλλες φράσεις εδώ αν χρειαστεί
            // case 'Another English Text':
            //     $translated_text = 'Η Ελληνική Μετάφραση';
            //     break;
        }
    }
    return $translated_text;
}

function petling_secure_logout_shortcode() {
    if ( is_user_logged_in() ) {
        $redirect_url = home_url(); 
        $logout_url = wp_logout_url( $redirect_url );

        $html = '<a href="' . esc_url( $logout_url ) . '" title="' . esc_attr__( 'Αποσύνδεση', 'woocommerce' ) . '" class="vamtam-logout-link">';
        $html .= '<i aria-hidden="true" class="fas fa-sign-out-alt petling-logout-icon"></i>';
        $html .= '</a>';
        
        return $html;
    }
    return ''; 
}
add_shortcode( 'petling_logout_icon', 'petling_secure_logout_shortcode' );


// ---- Add responsive CSS ----
function petling_logout_icon_css() {
    ?>
    <style>
        .petling-logout-icon {
            font-size: 28px;
            color: var(--e-global-color-vamtam_accent_2);
        }

        /* Tablet (μέχρι 1024px) */
        @media (max-width: 1024px) {
            .petling-logout-icon {
                font-size: 24px !important;
            }
        }
    </style>
    <?php
}
add_action( 'wp_head', 'petling_logout_icon_css' );