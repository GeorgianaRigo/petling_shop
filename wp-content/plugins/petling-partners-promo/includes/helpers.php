<?php
/*
 * Κοινές βοηθητικές συναρτήσεις που χρησιμοποιούνται και από το admin-page.php
 * και από το ajax-handlers.php και από το shortcodes.php - μαζεμένες εδώ ώστε
 * να μην επαναλαμβάνεται κώδικας.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Επιστρέφει όλους τους συνεργάτες (array of arrays).
 */
function petling_promo_get_partners() {
    return get_option( 'petling_promo_partners', array() );
}

/**
 * Βρίσκει τις ρυθμίσεις ενός συγκεκριμένου συνεργάτη με βάση το prefix του
 * (π.χ. "JOY", "VET"). Επιστρέφει null αν δεν υπάρχει.
 */
function petling_promo_get_partner( $prefix ) {
    $prefix = strtoupper( trim( $prefix ) );
    foreach ( petling_promo_get_partners() as $partner ) {
        if ( strtoupper( $partner['prefix'] ) === $prefix ) {
            return $partner;
        }
    }
    return null;
}

/**
 * Ανθρώπινη περιγραφή της έκπτωσης ενός συνεργάτη, για χρήση μέσα σε
 * emails / frontend κείμενα (π.χ. "-2€" ή "-10%").
 */
function petling_promo_format_amount( $partner ) {
    if ( 'percent' === $partner['discount_type'] ) {
        return '-' . floatval( $partner['amount'] ) . '%';
    }
    return '-' . floatval( $partner['amount'] ) . '€';
}

/**
 * Δημιουργεί έναν μοναδικό κωδικό με το πρόθεμα του συνεργάτη,
 * π.χ. JOY-A1B2C.
 */
function petling_promo_generate_code( $prefix ) {
    return strtoupper( $prefix ) . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 5 ) );
}

/**
 * Πόσες μέρες έχουν περάσει από την τελευταία φορά που το συγκεκριμένο
 * email πήρε κωδικό ΑΠΟ ΤΟΝ ΙΔΙΟ συνεργάτη (partner_prefix). Το lock
 * υπολογίζεται ανά συνεργάτη, όχι συνολικά - ένας πελάτης που πήρε
 * πρόσφατα κωδικό από την Joy μπορεί κάλλιστα να πάρει και κωδικό από
 * τη Μανωλάκου την ίδια μέρα.
 */
function petling_promo_get_last_claim( $email, $prefix ) {
    global $wpdb;
    $table = $wpdb->prefix . 'petling_partner_leads';
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s AND partner_prefix = %s ORDER BY created_at DESC LIMIT 1",
        $email, strtoupper( $prefix )
    ) );
}
