<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Create a helper function for easy SDK access.
function dgoraAsfwFs() {
    global $dgoraAsfwFs;
    if ( !isset( $dgoraAsfwFs ) ) {
        // Include Freemius SDK.
        require_once dirname( __FILE__ ) . '/lib/start.php';
        // Activate multisite network integration.
        if ( !defined( 'WP_FS__PRODUCT_700_MULTISITE' ) ) {
            define( 'WP_FS__PRODUCT_700_MULTISITE', true );
        }
        $dgoraAsfwFs = fs_dynamic_init( array(
            'id'             => '700',
            'slug'           => 'ajax-search-for-woocommerce',
            'type'           => 'plugin',
            'public_key'     => 'pk_f4f2a51dbe0aee43de0692db77a3e',
            'is_premium'     => false,
            'premium_suffix' => 'Pro',
            'has_addons'     => false,
            'has_paid_plans' => true,
            'menu'           => array(
                'slug'        => 'dgwt_wcas_settings',
                'parent'      => array(
                    'slug' => 'woocommerce',
                ),
                'account'     => false,
                'contact'     => false,
                'support'     => false,
                'pricing'     => false,
                'affiliation' => false,
            ),
            'is_live'        => true,
        ) );
    }
    return $dgoraAsfwFs;
}

// Init Freemius.
dgoraAsfwFs();
// Signal that SDK was initiated.
do_action( 'dgoraAsfwFs_loaded' );
dgoraAsfwFs()->add_filter( 'plugin_icon', function () {
    return dirname( dirname( __FILE__ ) ) . '/assets/img/logo-128.png';
} );
if ( !dgoraAsfwFs()->is_premium() ) {
    dgoraAsfwFs()->add_action( 'after_uninstall', function () {
        global $wpdb;
        /* ------------
         * WIPE OPTIONS
         * ------------ */
        $options = array(
            'dgwt_wcas_schedule_single',
            'dgwt_wcas_settings_show_advanced',
            'dgwt_wcas_images_regenerated',
            'dgwt_wcas_settings_version',
            'dgwt_wcas_activation_date',
            'dgwt_wcas_dismiss_review_notice',
            'dgwt_wcas_stats_db_version',
            'widget_dgwt_wcas_ajax_search'
        );
        foreach ( $options as $option ) {
            delete_option( $option );
        }
        /* ---------------
         * WIPE TRANSIENTS
         * --------------- */
        $transients = array('dgwt_wcas_troubleshooting_async_results');
        foreach ( $transients as $transient ) {
            delete_transient( $transient );
        }
        if ( is_multisite() ) {
            foreach ( get_sites() as $site ) {
                if ( is_numeric( $site->blog_id ) && $site->blog_id > 1 ) {
                    $table = $wpdb->prefix . $site->blog_id . '_' . 'options';
                    foreach ( $options as $option ) {
                        $wpdb->delete( $table, array(
                            'option_name' => $option,
                        ) );
                    }
                    foreach ( $transients as $transient ) {
                        $wpdb->delete( $table, array(
                            'option_name' => '_transient_' . $transient,
                        ) );
                        $wpdb->delete( $table, array(
                            'option_name' => '_transient_timeout_' . $transient,
                        ) );
                    }
                }
            }
        }
    } );
}