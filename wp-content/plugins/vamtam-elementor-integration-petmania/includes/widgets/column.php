<?php
namespace VamtamElementor\Widgets\Column;

// Extending the Column widget.

if ( current_theme_supports( 'vamtam-elementor-widgets', 'column--layout-overflow' ) ) {
	function add_content_tab_controls( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'html_tag',
			'at' => 'before',
		] );
		$widget->add_control(
			'layout_overflow',
			[
				'label' => __( 'Overflow', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SELECT,
				'default' => '',
				'options' => [
					'' => __( 'Default', 'vamtam-elementor-integration' ),
					'hidden' => __( 'Hidden', 'vamtam-elementor-integration' ),
				],
				'selectors' => [
					'{{WRAPPER}}' => 'overflow: {{VALUE}}',
				],
			]
		);
		$widget->end_injection();
	}

	function layout_before_section_end( $widget ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_content_tab_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/column/layout/before_section_end', __NAMESPACE__ . '\layout_before_section_end' );
}

function update_background_controls( $controls_manager, $widget ) {
	// Bg Hover Image.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'background_hover_image', [
		'selectors' => [
			// Preloading of hover bg image.
			'{{WRAPPER}} > .elementor-element-populated::after' => 'content: url("{{URL}}");position:absolute;opacity:0;overflow:hidden;width:0;height:0;',
		],
	] );
}

function section_style_before_section_end( $widget ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_background_controls( $controls_manager, $widget );
}
add_action( 'elementor/element/column/section_style/before_section_end', __NAMESPACE__ . '\section_style_before_section_end' );

if ( current_theme_supports( 'vamtam-elementor-widgets', 'column--logical-spacings') ) {
	function update_advanced_section_controls( $controls_manager, $widget ) {
		// Margins.
		\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'margin', [
			'selectors' => [
				'{{WRAPPER}} > .elementor-element-populated' => '
					margin-block-start: {{TOP}}{{UNIT}};
					margin-inline-start: {{LEFT}}{{UNIT}};
					margin-block-end: {{BOTTOM}}{{UNIT}};
					margin-inline-end: {{RIGHT}}{{UNIT}};
					--e-column-margin-right: {{RIGHT}}{{UNIT}};
					--e-column-margin-left: {{LEFT}}{{UNIT}};',
			],
		] );

		// Padding.
		\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'padding', [
			'selectors' => [
				'{{WRAPPER}} > .elementor-element-populated' => '
					padding-block-start: {{TOP}}{{UNIT}};
					padding-inline-start: {{LEFT}}{{UNIT}};
					padding-block-end: {{BOTTOM}}{{UNIT}};
					padding-inline-end: {{RIGHT}}{{UNIT}};'
			],
		] );
	}

	function section_advanced_before_section_end( $widget ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		update_advanced_section_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/column/section_advanced/before_section_end', __NAMESPACE__ . '\section_advanced_before_section_end' );
}
