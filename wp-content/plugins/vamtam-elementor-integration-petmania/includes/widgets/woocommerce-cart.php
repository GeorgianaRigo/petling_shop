<?php
namespace VamtamElementor\Widgets\WooCommerceCart;

// Extending the Cart widget.

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-cart' ) ) {
	return;
}

function update_checkout_button_typography_controls( $controls_manager, $widget ) {
	// Checkout Button Typography.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, "checkout_button_typography", [
		'selectors' => [
			"{{WRAPPER}} .wc-proceed-to-checkout .checkout-button" => '{{_RESET_}}',
		],
	],
	\Elementor\Group_Control_Typography::get_type()
);
}

// Style - Checkout Button Section (After).
function section_cart_tabs_checkout_button_after_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_checkout_button_typography_controls( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-cart/section_cart_tabs_checkout_button/after_section_end', __NAMESPACE__ . '\section_cart_tabs_checkout_button_after_section_end', 10, 2 );
