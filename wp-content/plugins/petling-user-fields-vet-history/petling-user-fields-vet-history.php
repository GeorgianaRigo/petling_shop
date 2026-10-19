<?php
/**
 * Plugin Name:       Petling - User Fields & Vet History (Beta)
 * Plugin URI:        https://petling.gr/
 * Description:       Προσθέτει τα πεδία κατοικιδίων στο WooCommerce My Account, με επιλεκτική προβολή (Beta). Περιλαμβάνει το Ιατρικό Ιστορικό και το Ψηφιακό Vet Pass.
 * Version:           1.1.0
 * Author:            Georgiana | Petling
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PETLING_VET_FIELDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PETLING_VET_FIELDS_URL', plugin_dir_url( __FILE__ ) );

// 1. Δημιουργία Βάσης Δεδομένων κατά την ενεργοποίηση
register_activation_hook( __FILE__, 'petling_vet_history_activate' );
function petling_vet_history_activate() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $charset_collate = $wpdb->get_charset_collate();

    $table_vaccines = $wpdb->prefix . 'petling_vaccines';
    $sql_vaccines = "CREATE TABLE $table_vaccines (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        pet_unique_id varchar(50) NOT NULL,
        vaccine_name varchar(150) NOT NULL,
        date_administered date NOT NULL,
        next_vaccine_date date DEFAULT NULL,
        vet_name varchar(150) DEFAULT '',
        status varchar(20) DEFAULT 'draft',
        created_by varchar(20) DEFAULT 'owner',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY pet_unique_id (pet_unique_id)
    ) $charset_collate;";
    dbDelta( $sql_vaccines );

    $table_vet_notes = $wpdb->prefix . 'petling_vet_notes';
    $sql_vet_notes = "CREATE TABLE $table_vet_notes (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        pet_unique_id varchar(50) NOT NULL,
        weight decimal(5,2) DEFAULT NULL,
        vet_comment text DEFAULT '',
        next_exam_date date DEFAULT NULL,
        vet_name varchar(150) DEFAULT '',
        status varchar(20) DEFAULT 'draft',
        created_by varchar(20) DEFAULT 'owner',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_vet_notes );
}

require_once PETLING_VET_FIELDS_PATH . 'includes/admin-menu.php';
require_once PETLING_VET_FIELDS_PATH . 'includes/my-account-fields.php';
require_once PETLING_VET_FIELDS_PATH . 'includes/medical-history.php';
require_once PETLING_VET_FIELDS_PATH . 'includes/vet-pass-public.php';

// Φορτώνουμε CSS και JS μόνο αν ο χρήστης έχει πρόσβαση
add_action( 'wp_enqueue_scripts', 'petling_vet_fields_enqueue_scripts' );
function petling_vet_fields_enqueue_scripts() {
    if ( is_account_page() && is_user_logged_in() ) {
        $user_id = get_current_user_id();
        if ( petling_vet_history_has_access( $user_id ) ) {
            wp_enqueue_style( 'petling-account-css', PETLING_VET_FIELDS_URL . 'css/my-account-pets.css', [], '1.1.0' );
            wp_enqueue_script( 'petling-account-js', PETLING_VET_FIELDS_URL . 'js/my-account-pets.js', [ 'jquery' ], '1.1.0', true );
        }
    }
}