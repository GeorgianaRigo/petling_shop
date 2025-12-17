<?php
/**
 * Plugin Name:       Petling - User Pet Profiles
 * Plugin URI:        https://33944474147.blog.com.gr/
 * Description:       Adds custom, repeatable fields for pet profiles in the WooCommerce My Account page.
 * Version:           1.4.1
 * Author:            Georgiana - Petling
 * Author URI:        https://33944474147.blog.com.gr/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       petling-user-pets
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PETLING_PETS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PETLING_PETS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PETLING_PETS_PLUGIN_PATH . 'includes/my-account-fields.php';

add_action( 'wp_enqueue_scripts', 'petling_enqueue_account_scripts_from_plugin' );
function petling_enqueue_account_scripts_from_plugin() {
    if ( is_account_page() ) {
        wp_enqueue_script(
            'petling-account-js',
            PETLING_PETS_PLUGIN_URL . 'js/my-account-pets.js',
            [ 'jquery' ],
            '1.4.1', // Updated version
            true
        );
    }
}