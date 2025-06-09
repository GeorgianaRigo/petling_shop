<?php

namespace DgoraWcas\Integrations\Plugins\DiscontinuedProductStockStatusWoocommerce;

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Integration with Discontinued Product Stock Status for WooCommerce
 *
 * Plugin URL: https://wordpress.org/plugins/discontinued-product-stock-status-woocommerce/
 * Author: SaffireTech
 */
class DiscontinuedProductStockStatusWooCommerce {
    public function init() {
        if ( !defined( 'DPSSW_DISCOUNTINUED_PLUGIN_BASENAME' ) ) {
            return;
        }
        // WooCommerce >> Settings >> Discontinued Product Stock Status Global Settings tab: Hide Discontinued Products in WooCommerce Catalog & Search Results
        // Warning: This option has the opposite name than what it does.
        if ( get_option( 'discontinued_show_in_catalog' ) !== 'yes' ) {
            return;
        }
        if ( !dgoraAsfwFs()->is_premium() ) {
            add_filter( 'dgwt/wcas/search_query/args', function ( $args ) {
                $args['meta_query'] = $args['meta_query'] ?? [];
                $args['meta_query'][] = [
                    'key'     => '_stock_status',
                    'value'   => 'discontinued',
                    'compare' => '!=',
                ];
                return $args;
            } );
        }
    }

}
