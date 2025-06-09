<?php
namespace VamtamElementor\Documents\Popup;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'popup' ) ) {
	return;
}

if ( vamtam_theme_supports( 'popup--absolute-position' ) ) {

	function add_advanced_section_controls( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'avoid_multiple_popups',
		] );
		$widget->add_control(
			'vamtam_abs_pos',
			[
				'label' => __( 'Retain Position', 'vamtam-elementor-integration' ),
				'description' => __( 'The popup will retain it\'s initial position regardless of page scroll.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => '',
				'frontend_available' => true,
			]
		);
		$widget->end_injection();
		// Align with selector
		$widget->start_injection( [
			'of' => 'open_selector',
		] );
		$widget->add_control(
			'vamtam_align_with_selector',
			[
				'label' => __( 'Align with Selector', 'vamtam-elementor-integration' ),
				'description' => __( 'The popup will be positioned relative to the selector (trigger) element. When using this feature make sure the <strong>selector</strong> used in the "Open By Selector" field is <strong>unique</strong> (use ID when possible). <em>*Does <strong>not</strong> support multiple selectors.</em>', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => '',
				'frontend_available' => true,
				'condition' => [
					'open_selector!' => '',
				],
			]
		);
		$widget->end_injection();
	}

	// Advanced - Advanced section
	function section_advanced_content_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_advanced_section_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/popup/section_advanced/before_section_end', __NAMESPACE__ . '\section_advanced_content_before_section_end', 10, 2 );
}
