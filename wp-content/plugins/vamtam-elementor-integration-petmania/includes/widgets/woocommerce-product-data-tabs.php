<?php
namespace VamtamElementor\Widgets\ProductDataTabs;

// Extending the WC Product Data Tabs widget.

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-data-tabs' ) ) {
	return;
}

if ( vamtam_theme_supports( [ 'woocommerce-product-data-tabs--hide-tabs-toggles', 'woocommerce-product-data-tabs--mobile-hr-layout' ] ) ) {
	function add_hide_tabs_controls( $controls_manager, $widget ) {
		// Hide Description Tab.
		$widget->add_control(
			'vamtam_hide_description',
			[
				'label' => __( 'Hide Description', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'separator' => 'before',
			]
		);

		// Hide Additional Information Tab.
		$widget->add_control(
			'vamtam_hide_additional_info',
			[
				'label' => __( 'Hide Additional Information', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
			]
		);

		// Hide Reviews Tab.
		$widget->add_control(
			'vamtam_hide_reviews',
			[
				'label' => __( 'Hide Reviews', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
			]
		);

		if ( class_exists( 'Vamtam_Product_QA' ) ) {
			// Hide Q&A Tab.
			$widget->add_control(
				'vamtam_hide_qa',
				[
					'label' => __( 'Hide Q&A', 'vamtam-elementor-integration' ),
					'type' => $controls_manager::SWITCHER,
				]
			);
		}

	}

	function add_use_mobile_hr_layout_control( $controls_manager, $widget ) {
		// Use Mobile Horizontal Layout.
		$widget->add_control(
			'vamtam_use_mobile_hr_layout',
			[
				'label' => __( 'Use Mobile Horizontal Layout', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'separator' => 'before',
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'mobile-hr-layout',
				'default' => 'mobile-hr-layout',
			]
		);
	}

	if ( vamtam_theme_supports( 'woocommerce-product-data-tabs--hide-tabs-toggles' ) ) {
		// Product Data Tabs, before render_content.
		function product_data_tabs_before_render_content( $widget ) {
			$widget_name = $widget->get_name();
			if ( $widget->get_name() === 'global' ) {
				$widget_name = $widget->get_original_element_instance()->get_name();
			}

			if ( $widget_name === 'woocommerce-product-data-tabs' ) {
				$settings = $widget->get_settings();

				function vamtam_woocommerce_product_tabs( $tabs = array() ) {
					// Make sure the $tabs parameter is an array.
					if ( ! is_array( $tabs ) ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
						trigger_error( 'Function vamtam_woocommerce_product_tabs() expects an array as the first parameter. Defaulting to empty array.' );
						$tabs = array();
					}

					$settings = $GLOBALS['vamtam_widget_settings'];

					// Description Tab.
					if ( ! empty( $settings['vamtam_hide_description'] ) ) {
						unset( $tabs['description'] );
					}

					// Additional Information Tab.
					if ( ! empty( $settings['vamtam_hide_additional_info'] ) ) {
						unset( $tabs['additional_information'] );
					}

					// Reviews Tab.
					if ( ! empty( $settings['vamtam_hide_reviews'] ) ) {
						unset( $tabs['reviews'] );
					}

					// Q&A Tab.
					if ( ! empty( $settings['vamtam_hide_qa'] ) ) {
						unset( $tabs['ask'] );
					}

					unset( $GLOBALS['vamtam_widget_settings'] );

					return $tabs;
				}

				if ( ! empty( $settings['vamtam_hide_description'] ) || ! empty( $settings['vamtam_hide_additional_info'] ) ||
					! empty( $settings['vamtam_hide_reviews'] ) || ! empty( $settings['vamtam_hide_qa'] ) ) {
					$GLOBALS['vamtam_widget_settings'] = $settings; // Needed so that vamtam_woocommerce_product_tabs() can access the widget's settings.
					add_filter( 'woocommerce_product_tabs', __NAMESPACE__ . '\vamtam_woocommerce_product_tabs', 98, 1 );
				}
			}
		}
		add_action( 'elementor/widget/before_render_content', __NAMESPACE__ . '\product_data_tabs_before_render_content', 10, 1 );
	}
}

function update_border_color_controls( $controls_manager, $widget ) {
	// Normal border color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'tabs_border_color', [
		'selectors' => [
			'{{WRAPPER}}' => '--vamtam-ptabs-border-color: {{VALUE}}',
		],
		]
	);

	// Active border color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'active_tabs_border_color', [
		'selectors' => [
			'{{WRAPPER}}' => '--vamtam-ptabs-border-color-active: {{VALUE}}',
		],
		]
	);
}

// Product Data Tabs - Before Section End.
function section_product_tabs_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	if ( vamtam_theme_supports( 'woocommerce-product-data-tabs--hide-tabs-toggles' ) ) {
		add_hide_tabs_controls( $controls_manager, $widget );
	}
	if ( vamtam_theme_supports( 'woocommerce-product-data-tabs--mobile-hr-layout' ) ) {
		add_use_mobile_hr_layout_control( $controls_manager, $widget );
	}
	update_border_color_controls( $controls_manager, $widget );
}

add_action( 'elementor/element/woocommerce-product-data-tabs/section_product_tabs_style/before_section_end', __NAMESPACE__ . '\section_product_tabs_style_before_section_end', 10, 2 );
