<?php
/**
 * Plugin Name:       Petling - User Fields & Vet History (Beta)
 * Plugin URI:        https://petling.gr/
 * Description:       Προσθέτει τα πεδία κατοικιδίων στο WooCommerce My Account, με επιλεκτική προβολή (Beta) βάσει λίστας email.
 * Version:           1.0.0
 * Author:            Georgiana | Petling
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PETLING_VET_FIELDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PETLING_VET_FIELDS_URL', plugin_dir_url( __FILE__ ) );

require_once PETLING_VET_FIELDS_PATH . 'includes/admin-menu.php';
require_once PETLING_VET_FIELDS_PATH . 'includes/my-account-fields.php';

// Φορτώνουμε CSS και JS μόνο αν ο χρήστης έχει πρόσβαση
add_action( 'wp_enqueue_scripts', 'petling_vet_fields_enqueue_scripts' );
function petling_vet_fields_enqueue_scripts() {
    if ( is_account_page() && is_user_logged_in() ) {
        $user_id = get_current_user_id();
        if ( petling_vet_history_has_access( $user_id ) ) {
            wp_enqueue_style( 'petling-account-css', PETLING_VET_FIELDS_URL . 'css/my-account-pets.css', [], '1.0.0' );
            wp_enqueue_script( 'petling-account-js', PETLING_VET_FIELDS_URL . 'js/my-account-pets.js', [ 'jquery' ], '1.0.0', true );
        }
    }
}