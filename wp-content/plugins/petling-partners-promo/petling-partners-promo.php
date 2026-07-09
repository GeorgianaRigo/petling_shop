<?php
/*
Plugin Name: Petling Partners Promo
Plugin URI: https://petling.gr
Description: Έξυπνος μηχανισμός κουπονιών (Lead Generation) για τους συνεργάτες του Petling. Δημιουργεί μοναδικά κουπόνια WooCommerce (για συνεργάτες τύπου "shop") ή κωδικούς-απόδειξης για ραντεβού (για συνεργάτες τύπου "appointment", π.χ. κτηνίατρος), και μαζεύει emails.
Version: 2.0
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Ασφάλεια: να μην ανοίγει απευθείας το αρχείο

/*
 * =========================================================================
 * ΙΣΤΟΡΙΚΟ ΕΝΗΜΕΡΩΣΕΩΝ (changelog)
 * -------------------------------------------------------------------------
 * Το ίδιο κείμενο εμφανίζεται και μέσα στο tab "Ρυθμίσεις & Οδηγίες" του
 * admin, ώστε να το βλέπεις χωρίς να ανοίγεις κώδικα.
 *
 * v1.0 - 1.4  Αρχική έκδοση. Ένα κοινό (global) ποσό έκπτωσης/ελάχιστη
 *             παραγγελία/lock-days για όλους τους συνεργάτες. Κάθε claim
 *             δημιουργούσε πάντα πραγματικό WooCommerce coupon. Μία μόνο
 *             ενεργή εγγραφή ανά email συνολικά (χωρίς διάκριση συνεργάτη).
 *
 * v2.0        - Ρυθμίσεις ΑΝΑ ΣΥΝΕΡΓΑΤΗ (prefix, ετικέτα, τύπος, ποσό,
 *               ελάχιστη παραγγελία, lock-days, password) αντί για ένα
 *               κοινό set.
 *             - Νέος τύπος συνεργάτη "appointment" (π.χ. κτηνίατρος): ΔΕΝ
 *               δημιουργεί WooCommerce coupon (δεν ξοδεύεται ποτέ στο
 *               δικό μας site) - δημιουργεί μόνο μοναδικό κωδικό-απόδειξη
 *               με κατάσταση active/redeemed.
 *             - Νέο shortcode [petling_partner_redeem prefix="VET"]:
 *               σελίδα προστατευμένη με password ανά συνεργάτη, όπου ο
 *               ίδιος ο συνεργάτης (π.χ. η κτηνίατρος) ψάχνει τον κωδικό
 *               του πελάτη και τον "καταναλώνει" (redeem) τη στιγμή του
 *               ραντεβού, ώστε να μην ξαναχρησιμοποιηθεί.
 *             - Η βάση δεδομένων πλέον κρατάει ΠΟΛΛΕΣ εγγραφές ανά email
 *               (μία ανά συνεργάτη/claim) αντί για μία συνολικά, με
 *               πεδία partner_prefix, type, status, redeemed_at.
 *             - Αναδιοργάνωση αρχείων σε includes/ (admin, ajax,
 *               shortcodes) και assets/ (css, js) αντί για όλα μαζί σε
 *               ένα αρχείο.
 * =========================================================================
 */

define( 'PETLING_PROMO_VERSION', '2.0' );
define( 'PETLING_PROMO_DB_VERSION', '2.0' );
define( 'PETLING_PROMO_DIR', plugin_dir_path( __FILE__ ) );
define( 'PETLING_PROMO_URL', plugin_dir_url( __FILE__ ) );

require_once PETLING_PROMO_DIR . 'includes/helpers.php';
require_once PETLING_PROMO_DIR . 'includes/admin-page.php';
require_once PETLING_PROMO_DIR . 'includes/ajax-handlers.php';
require_once PETLING_PROMO_DIR . 'includes/shortcodes.php';

// =========================================================================
// ΕΓΚΑΤΑΣΤΑΣΗ / ΑΝΑΒΑΘΜΙΣΗ ΒΑΣΗΣ ΔΕΔΟΜΕΝΩΝ
// Τρέχει αυτόματα σε κάθε φόρτωση αν η αποθηκευμένη έκδοση της βάσης είναι
// παλιότερη - ασφαλές να τρέξει πολλές φορές (idempotent).
// =========================================================================
function petling_promo_install() {
    global $wpdb;
    $table         = $wpdb->prefix . 'petling_partner_leads';
    $installed_ver = get_option( 'petling_promo_db_version', '1.0' );

    if ( version_compare( $installed_ver, PETLING_PROMO_DB_VERSION, '>=' ) ) {
        return; // Ήδη ενημερωμένο, δεν κάνουμε τίποτα
    }

    $charset_collate = $wpdb->get_charset_collate();

    // 1) Δημιουργία πίνακα αν πρόκειται για νέα εγκατάσταση
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        partner_prefix varchar(20) NOT NULL DEFAULT '',
        type varchar(20) NOT NULL DEFAULT 'shop',
        coupon_code varchar(50) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
        redeemed_at datetime NULL DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY coupon_code (coupon_code),
        KEY email_partner (email, partner_prefix)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // 2) Migration από παλιά δομή (v1.x), αν έρχεται από παλιό site
    $existing_columns = $wpdb->get_col( "DESC $table", 0 );

    if ( in_array( 'last_claimed', $existing_columns, true ) && ! in_array( 'created_at', $existing_columns, true ) ) {
        $wpdb->query( "ALTER TABLE $table CHANGE last_claimed created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00'" );
    }

    // Αφαίρεση παλιού UNIQUE KEY στο email, ώστε ένα email να μπορεί να
    // έχει πολλαπλές εγγραφές (μία ανά συνεργάτη)
    $existing_indexes = $wpdb->get_results( "SHOW INDEX FROM $table WHERE Key_name = 'email'" );
    if ( $existing_indexes ) {
        $wpdb->query( "ALTER TABLE $table DROP INDEX email" );
    }

    // 3) Seed ρυθμίσεων ανά συνεργάτη, με βάση τα παλιά global options (αν υπάρχουν ήδη ΔΕΝ τα πειράζουμε)
    if ( false === get_option( 'petling_promo_partners', false ) ) {
        $old_amount = get_option( 'petling_promo_discount_amount', 2 );
        $old_min    = get_option( 'petling_promo_min_order', 20 );
        $old_lock   = get_option( 'petling_promo_lock_days', 20 );

        update_option( 'petling_promo_partners', array(
            array(
                'prefix'        => 'JOY',
                'label'         => 'Joy (Groomer)',
                'type'          => 'shop',
                'discount_type' => 'fixed',
                'amount'        => $old_amount,
                'min_order'     => $old_min,
                'lock_days'     => $old_lock,
                'password'      => '',
            ),
            array(
                'prefix'        => 'VET',
                'label'         => 'Δρ. Μανωλάκου (Κτηνίατρος Διατροφής)',
                'type'          => 'appointment',
                'discount_type' => 'percent',
                'amount'        => 10,
                'min_order'     => 0,
                'lock_days'     => 30,
                'password'      => wp_generate_password( 8, false ),
            ),
        ) );
    }

    update_option( 'petling_promo_db_version', PETLING_PROMO_DB_VERSION );
}
add_action( 'plugins_loaded', 'petling_promo_install' );
