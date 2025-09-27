<?php
/**
 * Petmania Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Petmania Child
 */

add_action( 'wp_enqueue_scripts', 'petmania_child_enqueue_styles' );
/**
 * Enqueue scripts and styles.
 */
function petmania_child_enqueue_styles() {
    $parent_style = 'vamtam-petmania-style'; // Αυτό είναι το όνομα του κυρίως CSS του γονικού θέματος.

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'petmania-child-style',
        get_stylesheet_uri(),
        array( $parent_style )
    );
}