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