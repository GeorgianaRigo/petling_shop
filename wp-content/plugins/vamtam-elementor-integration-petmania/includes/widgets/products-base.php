<?php
namespace VamtamElementor\Widgets\ProductsBase;

/*
	Common extensions for:
		- woocommerce-product-related
		- woocommerce-product-upsell
		- wc-archive-products
		- woocommerce-products
	products widgets.
*/

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

function update_controls_style_tab_products_section( $controls_manager, $widget ) {
	// Image Spacing.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'image_spacing', [
		'selectors' => [
			'{{WRAPPER}}' => '--vamtam-img-spacing: {{SIZE}}{{UNIT}}',
		]
	] );

	// Image Border Radius.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'image_border_radius', [
		'selectors' => [
			'{{WRAPPER}}' => '--vamtam-img-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		]
	] );

	// Increase specificity of View cart selectors so they override the Button ones, if needed.
	$new_options = [
		'selectors' => [
			'{{WRAPPER}}.elementor-wc-products .products .product .added_to_cart' => '{{_RESET_}}',
		]
	];
	// View Cart Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'view_cart_color', $new_options );
	// View Cart Typography.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'view_cart_typography', $new_options, \Elementor\Group_Control_Typography::get_type() );

	// Alignment
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'align', [
		'prefix_class' => 'elementor-product-loop-item%s--align-',
	] );
}

function add_controls_style_tab_products_section( $controls_manager, $widget ) {
	add_content_controls( $controls_manager, $widget );
	add_title_min_height_controls( $controls_manager, $widget );
}

function add_content_controls( $controls_manager, $widget ) {
	$widget->add_control(
		'heading_content',
		[
			'label' => __( 'Content', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::HEADING,
			'separator' => 'before',
		]
	);
	$widget->start_controls_tabs( 'content_style_tabs' );
	$widget->start_controls_tab( 'content_style_normal',
		[
			'label' => __( 'Normal', 'vamtam-elementor-integration' ),
		]
	);
	$widget->add_control(
		'content_bg_color',
		[
			'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'default' => '',
			'selectors' => [
				'{{WRAPPER}} .elementor-widget-container ul.products li.product .vamtam-product-content' => 'background-color: {{VALUE}};',
			],
		]
	);
	$widget->end_controls_tab();
	$widget->start_controls_tab( 'content_style_hover',
		[
			'label' => __( 'Hover', 'vamtam-elementor-integration' ),
		]
	);
	$widget->add_control(
		'content_bg_color_hover',
		[
			'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'default' => '',
			'selectors' => [
				'{{WRAPPER}} .elementor-widget-container ul.products li.product .vamtam-product-content:hover' => 'background-color: {{VALUE}};',
			],
		]
	);
	$widget->end_controls_tab();
	$widget->end_controls_tabs();
}

function add_title_min_height_controls( $controls_manager, $widget ) {
	$widget->start_injection( [
		'of' => 'title_spacing',
	] );
	// Use Title Min-Height.
	$widget->add_control(
		'has_title_min_height',
		[
			'label' => __( 'Use Title Min Height', 'vamtam-elementor-integration' ),
			'description' => __( 'Use this option to equalize any differences caused by inconsistent title names.', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::SWITCHER,
			'prefix_class' => 'vamtam-has-',
			'return_value' => 'title-min-height',
		]
	);
	// Title Min-Height.
	$widget->add_responsive_control(
		'title_min_height',
		[
			'label' => __( 'Min Height', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::SLIDER,
			'size_units' => [ 'px' ],
			'range' => [
				'px' => [
					'min' => 0,
					'max' => 200,
				],
			],
			'selectors' => [
				'{{WRAPPER}} .elementor-widget-container ul.products li.product .vamtam-product-content .woocommerce-loop-product__title' => 'min-height: {{SIZE}}{{UNIT}}',
			],
			'condition' => [
				'has_title_min_height!' => '',
			],
		]
	);
	$widget->end_injection();
}

function add_btn_widget_aligned_btn_controls( $controls_manager, $widget ) {
	// We have to remove and re-add existing controls.
	\Vamtam_Elementor_Utils::remove_tabs( $controls_manager, $widget, 'tabs_button_style' );
	\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'button_border', \Elementor\Group_Control_Border::get_type() );
	\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'button_border_radius' );
	\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'button_text_padding' );
	\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'button_spacing' );

	$selectors = '{{WRAPPER}}.elementor-wc-products ul.products li.product .button, {{WRAPPER}}.elementor-wc-products .added_to_cart';

	$widget->start_injection( [
		'of' => 'heading_button_style',
	] );

	$widget->add_group_control(
		\Elementor\Group_Control_Typography::get_type(),
		[
			'name' => 'button_typography',
			'selector' => $selectors,
		]
	);

	$widget->add_group_control(
		\Elementor\Group_Control_Text_Shadow::get_type(),
		[
			'name' => 'button_text_shadow',
			'selector' => $selectors,
		]
	);

	$widget->start_controls_tabs( 'tabs_button_style' );
	$widget->start_controls_tab(
		'tab_button_normal',
		[
			'label' => __( 'Normal', 'vamtam-elementor-integration' ),
		]
	);

	$widget->add_control(
		'button_text_color',
		[
			'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'default' => '',
			'selectors' => [
				$selectors => 'fill: {{VALUE}}; color: {{VALUE}};',
			],
		]
	);

	$widget->add_group_control(
		\Elementor\Group_Control_Background::get_type(),
		[
			'name' => 'button_background',
			'label' => __( 'Background', 'vamtam-elementor-integration' ),
			'types' => [ 'classic', 'gradient' ],
			'exclude' => [ 'image' ],
			'selector' => $selectors,
			'fields_options' => [
				'background' => [
					'default' => 'classic',
				],
			],
		]
	);

	$widget->end_controls_tab();
	$widget->start_controls_tab(
		'tab_button_hover',
		[
			'label' => __( 'Hover', 'vamtam-elementor-integration' ),
		]
	);

	$widget->add_control(
		'button_hover_color',
		[
			'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}.elementor-wc-products ul.products li.product .button:hover,
				{{WRAPPER}}.elementor-wc-products ul.products li.product .button:focus,
				{{WRAPPER}}.elementor-wc-products .added_to_cart:hover,
				{{WRAPPER}}.elementor-wc-products .added_to_cart:focus' => 'color: {{VALUE}};',
				'{{WRAPPER}}.elementor-wc-products ul.products li.product .button:hover svg,
				{{WRAPPER}}.elementor-wc-products ul.products li.product .button:focus svg,
				{{WRAPPER}}.elementor-wc-products .added_to_cart:hover svg,
				{{WRAPPER}}.elementor-wc-products .added_to_cart:focus svg' => 'fill: {{VALUE}};',
			],
		]
	);

	$widget->add_group_control(
		\Elementor\Group_Control_Background::get_type(),
		[
			'name' => 'button_hover_background',
			'label' => __( 'Background', 'vamtam-elementor-integration' ),
			'types' => [ 'classic', 'gradient' ],
			'exclude' => [ 'image' ],
			'selector' => '{{WRAPPER}}.elementor-wc-products ul.products li.product .button:hover,
							{{WRAPPER}}.elementor-wc-products ul.products li.product .button:focus,
							{{WRAPPER}}.elementor-wc-products .added_to_cart:hover,
							{{WRAPPER}}.elementor-wc-products .added_to_cart:focus',
			'fields_options' => [
				'background' => [
					'default' => 'classic',
				],
			],
		]
	);

	$widget->add_control(
		'button_hover_border_color',
		[
			'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::COLOR,
			'condition' => [
				'button_border_border!' => '',
			],
			'selectors' => [
				'{{WRAPPER}}.elementor-wc-products ul.products li.product .button:hover,
				{{WRAPPER}}.elementor-wc-products ul.products li.product .button:focus,
				{{WRAPPER}}.elementor-wc-products .added_to_cart:hover,
				{{WRAPPER}}.elementor-wc-products .added_to_cart:focus' => 'border-color: {{VALUE}};',
			],
		]
	);

	// TODO: If we need hover_anims, we need widget class extension.
	// $widget->add_control(
	// 	'hover_animation',
	// 	[
	// 		'label' => __( 'Hover Animation', 'vamtam-elementor-integration' ),
	// 		'type' => $controls_manager::HOVER_ANIMATION,
	// 	]
	// );

	$widget->end_controls_tab();
	$widget->end_controls_tabs();

	$widget->end_injection();

	// We have to re-inject here cause injection_point gets messed up when tabs
	// are used during an injection.

	$widget->start_injection( [
		'of' => 'heading_view_cart_style',
		'at' => 'before',
	] );

	$widget->add_group_control(
		\Elementor\Group_Control_Border::get_type(),
		[
			'name' => 'button_border',
			'selector' => $selectors,
			'separator' => 'before',
		]
	);

	$widget->add_control(
		'button_border_radius',
		[
			'label' => __( 'Border Radius', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::DIMENSIONS,
			'size_units' => [ 'px', '%', 'em' ],
			'selectors' => [
				$selectors => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);

	$widget->add_group_control(
		\Elementor\Group_Control_Box_Shadow::get_type(),
		[
			'name' => 'button_box_shadow',
			'selector' => $selectors,
		]
	);

	$widget->add_responsive_control(
		'button_text_padding',
		[
			'label' => __( 'Padding', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors' => [
				$selectors => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
			'separator' => 'before',
		]
	);

	$widget->add_responsive_control(
		'button_spacing',
		[
			'label' => __( 'Spacing', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::SLIDER,
			'size_units' => [ 'px', 'em' ],
			'selectors' => [
				'{{WRAPPER}}.elementor-wc-products ul.products li.product .button' => 'margin-top: {{SIZE}}{{UNIT}}',
				'{{WRAPPER}}.elementor-wc-products ul.products li.product .added_to_cart' => 'margin-top: {{SIZE}}{{UNIT}}',
			],
		]
	);

	$widget->end_injection();

}

// Products Button section (add_to_cart, view_cart).
function section_products_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	add_btn_widget_aligned_btn_controls( $controls_manager, $widget );
	update_controls_style_tab_products_section( $controls_manager, $widget );
	add_controls_style_tab_products_section( $controls_manager, $widget );

	if ( vamtam_theme_supports( 'products-base--horizontal-layout' ) ) {
		/*
			Feature for:
				- WooCommerce Product Related
				- WooCommerce Product Upsell
				- WooCommerce Products
		*/
		if ( in_array( $widget->get_name(), [ 'woocommerce-product-related', 'woocommerce-product-upsell', 'woocommerce-products' ] ) ) {
			update_column_gap_control( $controls_manager, $widget );
		}
	}
}

// Theme Settings.
if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-related' ) ) {
	add_action( 'elementor/element/woocommerce-product-related/section_products_style/before_section_end', __NAMESPACE__ . '\section_products_style_before_section_end', 10, 2 );
}
// Theme Settings.
if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-upsell' ) ) {
	add_action( 'elementor/element/woocommerce-product-upsell/section_products_style/before_section_end', __NAMESPACE__ . '\section_products_style_before_section_end', 10, 2 );
}
// Theme Settings.
if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-archive-products' ) ) {
	add_action( 'elementor/element/wc-archive-products/section_products_style/before_section_end', __NAMESPACE__ . '\section_products_style_before_section_end', 10, 2 );
}
// Theme Settings.
if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-products' ) ) {
	add_action( 'elementor/element/woocommerce-products/section_products_style/before_section_end', __NAMESPACE__ . '\section_products_style_before_section_end', 10, 2 );
}

function update_controls_style_tab_pagination_section( $controls_manager, $widget ) {
	$new_options = [
		'selectors' => [
			'{{WRAPPER}} .navigation.vamtam-pagination-wrapper' => '{{_RESET_}}',
		]
	];
	// Pagination Spacing.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_spacing', $new_options );
	// Pagination Typography.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_typography', $new_options, \Elementor\Group_Control_Typography::get_type() );
	// Pagination Padding.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_padding', [
		'selectors' => [
			'{{WRAPPER}} .navigation.vamtam-pagination-wrapper .page-numbers' => 'line-height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
		]
	] );
	$new_options = [
		'selectors' => [
			'{{WRAPPER}} .navigation.vamtam-pagination-wrapper .page-numbers' => '{{_RESET_}}',
		]
	];
	// Pagination Border Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_border_color', $new_options );
	// Pagination Link Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_link_color', $new_options );
	// Pagination Link Bg Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_link_bg_color', $new_options );
	$new_options = [
		'selectors' => [
			'{{WRAPPER}} .navigation.vamtam-pagination-wrapper .page-numbers:hover' => '{{_RESET_}}',
		]
	];
	// Pagination Link Hover Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_link_color_hover', $new_options );
	// Pagination Link Bg Hover Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_link_bg_color_hover', $new_options );
	$new_options = [
		'selectors' => [
			'{{WRAPPER}} .navigation.vamtam-pagination-wrapper .page-numbers.current' => '{{_RESET_}}',
		]
	];
	// Pagination Link Active Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_link_color_active', $new_options );
	// Pagination Link Bg Active Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'pagination_link_bg_color_active', $new_options );
}

// Products Archive Pagination section.
function section_pagination_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_controls_style_tab_pagination_section( $controls_manager, $widget );
}
// Theme Settings.
if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-archive-products' ) ) {
	add_action( 'elementor/element/wc-archive-products/section_pagination_style/before_section_end', __NAMESPACE__ . '\section_pagination_style_before_section_end', 10, 2 );
}

// Products_Base, before render_content.
function products_base_before_render_content( $widget ) {
    $widget_name = $widget->get_name();
    if ( $widget->get_name() === 'global' ) {
        $widget_name = $widget->get_original_element_instance()->get_name();
    }

	$products_widgets = [
		'woocommerce-product-related',
		'woocommerce-product-upsell',
		'woocommerce-products',
		'wc-archive-products',
	];

	if ( in_array( $widget_name, $products_widgets ) ) {
		// Theme Settings.
		if ( \Vamtam_Elementor_Utils::is_widget_mod_active( $widget_name ) ) {
			do_action( 'vamtam_before_products_widget_before_render_content', $widget_name, $widget );
		}
	}
}
add_action( 'elementor/widget/before_render_content', __NAMESPACE__ . '\products_base_before_render_content', 10, 1 );

if ( vamtam_theme_supports( 'products-base--new-badge-section' ) ) {
	function add_new_badge_section( $controls_manager, $widget ) {
		$widget->start_controls_section(
			'vamtam_section_new_badge_style',
			[
				'label' => esc_html__( 'New Badge', 'vamtam-elementor-integration' ),
				'tab' => $controls_manager::TAB_STYLE,
			]
		);

		$widget->add_control(
			'vamtam_show_new_badge',
			[
				'label' => esc_html__( 'New Badge', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'label_off' => esc_html__( 'Hide', 'vamtam-elementor-integration' ),
				'label_on' => esc_html__( 'Show', 'vamtam-elementor-integration' ),
				'separator' => 'before',
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'new-badge',
				'default' => 'new-badge',
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => 'display: block',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_color',
			[
				'label' => esc_html__( 'Text Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => 'color: {{VALUE}}',
				],
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_bg_color',
			[
				'label' => esc_html__( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => 'background-color: {{VALUE}}',
				],
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'vamtam_new_badge_typography',
				'selector' => '{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new',
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => 'border-radius: {{SIZE}}{{UNIT}}',
				],
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_width',
			[
				'label' => esc_html__( 'Width', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => 'min-width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_height',
			[
				'label' => esc_html__( 'Height', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => 'min-height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_hr_pos',
			[
				'label' => esc_html__( 'Position', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__( 'Left', 'vamtam-elementor-integration' ),
						'icon' => 'eicon-h-align-left',
					],
					'right' => [
						'title' => esc_html__( 'Right', 'vamtam-elementor-integration' ),
						'icon' => 'eicon-h-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'left' => 'right: auto; left: 0',
					'right' => 'left: auto; right: 0',
				],
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_distance',
			[
				'label' => esc_html__( 'Distance', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range' => [
					'px' => [
						'min' => -20,
						'max' => 20,
					],
					'em' => [
						'min' => -2,
						'max' => 2,
					],
				],
				'selectors' => [
					'{{WRAPPER}}.elementor-wc-products ul.products li.product .vamtam-new' => 'margin: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_top_offset',
			[
				'label' => esc_html__( 'Top Offset', 'vamtam-elementor-integration' ),
				'description' => esc_html__( 'Applied only when the "Sale" badge is present.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
					'em' => [
						'min' => 0,
						'max' => 5,
					],
				],
				'selectors' => [
					'{{WRAPPER}}.vamtam-has-onsale-yes ul.products li.product .vamtam-onsale.vamtam-new' => 'top: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'show_onsale_flash!' => '',
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->end_controls_section();
	}

	// Products Sale Flash section - After Section End.
	function sale_flash_style_after_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_new_badge_section( $controls_manager, $widget );
	}

	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-related' ) ) {
		add_action( 'elementor/element/woocommerce-product-related/sale_flash_style/after_section_end', __NAMESPACE__ . '\sale_flash_style_after_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-upsell' ) ) {
		add_action( 'elementor/element/woocommerce-product-upsell/sale_flash_style/after_section_end', __NAMESPACE__ . '\sale_flash_style_after_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-archive-products' ) ) {
		add_action( 'elementor/element/wc-archive-products/sale_flash_style/after_section_end', __NAMESPACE__ . '\sale_flash_style_after_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-products' ) ) {
		add_action( 'elementor/element/woocommerce-products/sale_flash_style/after_section_end', __NAMESPACE__ . '\sale_flash_style_after_section_end', 10, 2 );
	}
}

if ( vamtam_theme_supports( 'products-base--new-badge-section' ) ) {
	function update_show_onsale_control( $controls_manager, $widget ) {
		// Show onsale.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'show_onsale_flash', [
			'prefix_class' => 'vamtam-has-onsale-',
		] );
	}

	// Products Sale Flash section - Before Section End.
	function sale_flash_style_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		update_show_onsale_control( $controls_manager, $widget );
	}

	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-related' ) ) {
		add_action( 'elementor/element/woocommerce-product-related/sale_flash_style/before_section_end', __NAMESPACE__ . '\sale_flash_style_before_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-upsell' ) ) {
		add_action( 'elementor/element/woocommerce-product-upsell/sale_flash_style/before_section_end', __NAMESPACE__ . '\sale_flash_style_before_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-archive-products' ) ) {
		add_action( 'elementor/element/wc-archive-products/sale_flash_style/before_section_end', __NAMESPACE__ . '\sale_flash_style_before_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-products' ) ) {
		add_action( 'elementor/element/woocommerce-products/sale_flash_style/before_section_end', __NAMESPACE__ . '\sale_flash_style_before_section_end', 10, 2 );
	}
}

/*
	Feature for:
		- WooCommerce Product Related
		- WooCommerce Product Upsell
		- WooCommerce Products
*/
if ( vamtam_theme_supports( 'products-base--horizontal-layout' ) ) {
	function register_products_base_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'vamtam-hr-scrolling',
			VAMTAM_ELEMENTOR_INT_URL . 'assets/js/widgets/vamtam-hr-scrolling/vamtam-hr-scrolling' . $suffix . '.js',
			[
				'elementor-frontend',
			],
			\VamtamElementorIntregration::PLUGIN_VERSION,
			true
		);
	}
	add_action( 'elementor/frontend/before_register_scripts', __NAMESPACE__ . '\register_products_base_scripts' );


	function products_base_add_script_depends( $widget_name, $widget ) {
		// Theme settings are checked on vamtam_before_products_widget_before_render_content (do_action)
		$settings = $widget->get_settings();
		if ( ! empty( $settings[ 'vamtam_use_hr_layout' ] ) ) {
			$widget->add_script_depends( 'vamtam-hr-scrolling' );
		}
	}
	add_action( 'vamtam_before_products_widget_before_render_content', __NAMESPACE__ . '\products_base_add_script_depends', 10, 2 );


	function update_columns_control( $controls_manager, $widget ) {
		// Columns.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'columns', [
			'selectors' => [
				'{{WRAPPER}}' => '--vamtam-cols: {{VALUE}}',
			],
		] );
	}
	function add_hr_layout_controls( $controls_manager, $widget ) {
		$widget->add_control(
			'vamtam_use_hr_layout',
			[
				'label' => __( 'Use Horizontal Layout', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'hr-layout',
			]
		);

		// Additional Columns Hint.
		$widget->add_responsive_control(
			'vamtam_additional_cols_hint',
			[
				'label' => esc_html__( 'Additional Columns Hint', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em', '%' ],
				'default' => [
					'size' => 20,
					'unit' => 'px',
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-col-hint: {{SIZE}}{{UNIT}}',
				],
				'required' => true,
				'condition' => [
					'vamtam_use_hr_layout!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_has_nav',
			[
				'label' => __( 'Show Navigation', 'vamtam-elementor-integration' ),
				'description' => __( 'Disabled on mobile devices.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'nav',
				'condition' => [
					'vamtam_use_hr_layout!' => '',
				],
				'default' => 'nav',
				'render_type' => 'template',
			]
		);
	}

	function section_content_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_hr_layout_controls( $controls_manager, $widget );
		update_columns_control( $controls_manager, $widget );
	}

	function update_column_gap_control( $controls_manager, $widget ) {
		// Column Gap.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'column_gap', [
			'selectors' => [
				'{{WRAPPER}}' => '--vamtam-col-gap: {{SIZE}}{{UNIT}}',
			],
		] );

	}

	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-related' ) ) {
		add_action( 'elementor/element/woocommerce-product-related/section_related_products_content/before_section_end', __NAMESPACE__ . '\section_content_before_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-upsell' ) ) {
		add_action( 'elementor/element/woocommerce-product-upsell/section_upsell_content/before_section_end', __NAMESPACE__ . '\section_content_before_section_end', 10, 2 );
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-products' ) ) {
		add_action( 'elementor/element/woocommerce-products/section_content/before_section_end', __NAMESPACE__ . '\section_content_before_section_end', 10, 2 );
	}
}
