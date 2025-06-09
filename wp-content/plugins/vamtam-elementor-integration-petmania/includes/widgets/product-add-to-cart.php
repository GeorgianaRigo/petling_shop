<?php
namespace VamtamElementor\Widgets\WC_Product_Add_To_Cart;

// Extending the WC_Product_Add_To_Cart widget.

use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-add-to-cart' ) ) {
	return;
}

function add_view_cart_section( $controls_manager, $widget ) {
	$widget->start_controls_section(
		'section_atc_view_cart_button_style',
		[
			'label' => __( 'View Cart', 'vamtam-elementor-integration' ),
			'tab' => $controls_manager::TAB_STYLE,
		]
	);

	$widget->add_control(
		'wc_style_warning_vc',
		[
			'type' => $controls_manager::RAW_HTML,
			'raw' => __( 'The style of this widget is often affected by your theme and plugins. If you experience any such issue, try to switch to a basic theme and deactivate related plugins.', 'vamtam-elementor-integration' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		]
	);

	$widget->add_group_control(
		Group_Control_Typography::get_type(),
		[
			'name' => 'vc_button_typography',
			'selector' => '{{WRAPPER}} .cart .added_to_cart',
		]
	);

	$widget->add_group_control(
		Group_Control_Border::get_type(),
		[
			'name' => 'vc_button_border',
			'selector' => '{{WRAPPER}} .cart .added_to_cart',
			'exclude' => [ 'color' ],
		]
	);

	$widget->add_control(
		'vc_button_border_radius',
		[
			'label' => __( 'Border Radius', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::DIMENSIONS,
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);

	$widget->add_control(
		'vc_button_padding',
		[
			'label' => __( 'Padding', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);

	$widget->start_controls_tabs( 'vc_button_style_tabs' );

	$widget->start_controls_tab( 'vc_button_style_normal',
		[
			'label' => __( 'Normal', 'vamtam-elementor-integration' ),
		]
	);

	$widget->add_control(
		'vc_button_text_color',
		[
			'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart' => 'color: {{VALUE}}',
			],
		]
	);

	$widget->add_control(
		'vc_button_bg_color',
		[
			'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart' => 'background-color: {{VALUE}}',
			],
		]
	);

	$widget->add_control(
		'vc_button_border_color',
		[
			'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart' => 'border-color: {{VALUE}}',
			],
		]
	);

	$widget->end_controls_tab();

	$widget->start_controls_tab( 'vc_button_style_hover',
		[
			'label' => __( 'Hover', 'vamtam-elementor-integration' ),
		]
	);

	$widget->add_control(
		'vc_button_text_color_hover',
		[
			'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart:hover' => 'color: {{VALUE}}',
			],
		]
	);

	$widget->add_control(
		'vc_button_bg_color_hover',
		[
			'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart:hover' => 'background-color: {{VALUE}}',
			],
		]
	);

	$widget->add_control(
		'vc_button_border_color_hover',
		[
			'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart:hover' => 'border-color: {{VALUE}}',
			],
		]
	);

	$widget->add_control(
		'vc_button_transition',
		[
			'label' => __( 'Transition Duration', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::SLIDER,
			'default' => [
				'size' => 0.2,
			],
			'range' => [
				'px' => [
					'max' => 2,
					'step' => 0.1,
				],
			],
			'selectors' => [
				'{{WRAPPER}} .cart .added_to_cart' => 'transition: all {{SIZE}}s !important',
			],
		]
	);

	$widget->end_controls_tab();

	$widget->end_controls_tabs();

	$widget->end_controls_section();
}

function update_style_button_section_controls( $controls_manager, $widget ) {
	// Button Transition.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'button_transition', [
		'selectors' => [
			'{{WRAPPER}} .cart button' => 'transition: all {{SIZE}}s !important',
		],
	] );
}

// Style - Button Section (After).
function section_atc_button_style_after_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_style_button_section_controls( $controls_manager, $widget );
	add_view_cart_section( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-product-add-to-cart/section_atc_button_style/after_section_end', __NAMESPACE__ . '\section_atc_button_style_after_section_end', 10, 2 );

function use_theme_ajax_handler_control( $controls_manager, $widget ) {
	$widget->add_control(
		'disable_theme_ajax_atc',
		[
			'label' => __( 'Disable Theme\'s Ajax Handler', 'vamtam-elementor-integration' ),
			'description' => __( 'Disables theme\'s Ajax add-to-cart handler implementation for single products.', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::SWITCHER,
			'prefix_class' => 'vamtam-has-',
			'return_value' => 'disable-theme-ajax-atc',
		]
	);
}
// Style - Buttons section
function section_atc_button_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	use_theme_ajax_handler_control( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-product-add-to-cart/section_atc_button_style/before_section_end', __NAMESPACE__ . '\section_atc_button_style_before_section_end', 10, 2 );

function variations_controls_selector_fixes( $controls_manager, $widget ) {
	// Space Between.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'variations_space_between', [
		'selectors' => [
			'.woocommerce {{WRAPPER}} form.cart table.variations tr:not(:last-child) > td' => 'padding-bottom: {{SIZE}}{{UNIT}}',
		],
	] );
	// Select Background Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'variations_select_bg_color', [
		'selectors' => [
			'.woocommerce {{WRAPPER}} form.cart table.variations td.value select' => 'background-color: {{VALUE}}!important',
		],
	] );
	// Select Border Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'variations_select_border_color', [
		'selectors' => [
			'.woocommerce {{WRAPPER}} form.cart table.variations td.value select' => '{{_RESET_}}',
		],
	] );
	// Select Border Radius.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'variations_select_border_radius', [
		'selectors' => [
			'.woocommerce {{WRAPPER}} form.cart table.variations td.value select' => '{{_RESET_}}',
		],
	] );
}
function add_custom_variations_controls( $controls_manager, $widget ) {
	// Variations Price Heading
	$widget->add_control(
		'vamtam_variations_price_heading',
		[
			'label' => esc_html__( 'Price', 'elementor-pro' ),
			'type' => $controls_manager::HEADING,
			'separator' => 'before',
		]
	);

	// Variations Price Color
	$widget->add_control(
		'vamtam_variations_price_color',
		[
			'label' => esc_html__( 'Color', 'elementor-pro' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'.woocommerce {{WRAPPER}} .woocommerce-variation .woocommerce-variation-price .price,
				.woocommerce {{WRAPPER}} .vamtam-variation-price .price .amount' => 'color: {{VALUE}}',
			],
		]
	);

	// Variations Price Typography
	$widget->add_group_control(
		Group_Control_Typography::get_type(),
		[
			'name' => 'vamtam_variations_price_typography',
			'selector' => '.woocommerce {{WRAPPER}} .woocommerce-variation .woocommerce-variation-price .price, .woocommerce {{WRAPPER}} .vamtam-variation-price .price .amount',
		]
	);
}
// Style - Variations Section (Before).
function section_variations_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	variations_controls_selector_fixes( $controls_manager, $widget );
	add_custom_variations_controls( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-product-add-to-cart/section_atc_variations_style/before_section_end', __NAMESPACE__ . '\section_variations_style_before_section_end', 10, 2 );

function update_spacing_control( $controls_manager, $widget ) {
	// Spacing.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'spacing', [
		'selectors' => [
			'body:not(.rtl) {{WRAPPER}} .quantity ~ .added_to_cart' => 'margin-left: {{SIZE}}{{UNIT}}',
			'body.rtl {{WRAPPER}} .quantity ~ .added_to_cart' => 'margin-right: {{SIZE}}{{UNIT}}',
		],
	] );
}
// Style - Quantity Section (Before).
function section_atc_quantity_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_spacing_control( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-product-add-to-cart/section_atc_quantity_style/before_section_end', __NAMESPACE__ . '\section_atc_quantity_style_before_section_end', 10, 2 );
