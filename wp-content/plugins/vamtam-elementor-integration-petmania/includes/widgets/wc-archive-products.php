<?php
namespace VamtamElementor\Widgets\ProductsArchive;

// Extending the Products Archive widget.

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme Settings.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-archive-products' ) ) {
	return;
}

/* WC Filters */

// Orderby catalog override.
function vamtam_woocommerce_catalog_orderby( $orderby_catalog ) {
	$orderby_catalog[ 'menu_order' ] = __( 'Sort By', 'vamtam-elementor-integration' );
	return $orderby_catalog;
}
add_filter( 'woocommerce_catalog_orderby', __NAMESPACE__ . '\vamtam_woocommerce_catalog_orderby', 10, 1 );
