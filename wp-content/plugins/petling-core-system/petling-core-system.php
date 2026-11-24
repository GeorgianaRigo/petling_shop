<?php
/*
Plugin Name: Petling Core System
Plugin URI: https://petling.gr
Description: Το κεντρικό, ΕΝΙΑΙΟ σύστημα του Petling. Περιλαμβάνει: Κεντρικό CRM, Διατροφικό Σύμβουλο, Δημιουργία Συνταγής, και το πλήρες σύστημα Promo & QR Συνεργατών.
Version: 5.1
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PTL_CORE_VERSION', '5.1' );
define( 'PTL_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PTL_CORE_URL', plugin_dir_url( __FILE__ ) );

// =========================================================================
// ΕΓΚΑΤΑΣΤΑΣΗ ΒΑΣΗΣ ΔΕΔΟΜΕΝΩΝ (Για Promo)
// =========================================================================
function ptl_core_install() {
    global $wpdb;
    $table = $wpdb->prefix . 'petling_partner_leads';$installed_ver = get_option( 'petling_promo_db_version', '1.0' );

    if ( version_compare( $installed_ver, '2.0', '<' ) ) {$charset_collate = $wpdb->get_charset_collate();$sql = "CREATE TABLE $table ( id mediumint(9) NOT NULL AUTO_INCREMENT, email varchar(100) NOT NULL, partner_prefix varchar(20) NOT NULL DEFAULT '', type varchar(20) NOT NULL DEFAULT 'shop', coupon_code varchar(50) NOT NULL, status varchar(20) NOT NULL DEFAULT 'active', created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00', redeemed_at datetime NULL DEFAULT NULL, PRIMARY KEY  (id), KEY coupon_code (coupon_code), KEY email_partner (email, partner_prefix) )$charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( false === get_option( 'petling_promo_partners', false ) ) {
            update_option( 'petling_promo_partners', array(
                array( 'prefix' => 'JOY', 'label' => 'Joy (Groomer)', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => 2, 'min_order' => 20, 'lock_days' => 20, 'password' => '' ),
                array( 'prefix' => 'VET', 'label' => 'Δρ. Μανωλάκου (VET)', 'type' => 'appointment', 'discount_type' => 'percent', 'amount' => 10, 'min_order' => 0, 'lock_days' => 30, 'password' => wp_generate_password( 8, false ) ),
            ) );
        }
        update_option( 'petling_promo_db_version', '2.0' );
    }
}
add_action( 'plugins_loaded', 'ptl_core_install' );

// =========================================================================
// ΦΟΡΤΩΣΗ ΥΠΟ-ΑΡΧΕΙΩΝ (MODULES)
// =========================================================================
require_once PTL_CORE_PATH . 'includes/crm-admin.php';
require_once PTL_CORE_PATH . 'includes/promo.php';
require_once PTL_CORE_PATH . 'includes/nutrition-quiz.php';
require_once PTL_CORE_PATH . 'includes/recipe-maker.php';
require_once PTL_CORE_PATH . 'includes/user-dashboard.php';

// =========================================================================
// ΦΟΡΤΩΣΗ FRONTEND CSS (Στο <head> για συμβατότητα με Elementor & Cache)
// =========================================================================
add_action( 'wp_enqueue_scripts', 'ptl_core_enqueue_global_styles' );
function ptl_core_enqueue_global_styles() {
    wp_enqueue_style( 'ptl-quiz-css', PTL_CORE_URL . 'assets/css/quiz.css', array(), PTL_CORE_VERSION );
    wp_enqueue_style( 'ptl-dashboard-css', PTL_CORE_URL . 'assets/css/dashboard.css', array(), PTL_CORE_VERSION );
    wp_enqueue_style( 'ptl-promo-css', PTL_CORE_URL . 'assets/css/promo.css', array(), PTL_CORE_VERSION );
    wp_enqueue_script( 'ptl-dashboard-js', PTL_CORE_URL . 'assets/js/dashboard.js', array(), PTL_CORE_VERSION, true );
}