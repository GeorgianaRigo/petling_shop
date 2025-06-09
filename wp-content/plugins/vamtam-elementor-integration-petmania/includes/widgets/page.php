<?php
namespace VamtamElementor\Element\Page;

function update_scroll_snap_selectors( $controls_manager, $element ) {
	// Scroll Snap.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $element, 'scroll_snap', [
		'selectors' => [
			'html' => 'overflow: auto; scroll-snap-type: y mandatory;',
		],
	] );
	// Scroll Snap Padding.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $element, 'scroll_snap_padding', [
		'selectors' => [
			'html' => 'scroll-padding: {{SIZE}}{{UNIT}}',
		],
	] );
}

// Advance Tab - Scroll Snap Section.
function wp_page_section_scroll_snap_before_section_end( $element, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_scroll_snap_selectors( $controls_manager, $element );
}

add_action( 'elementor/element/wp-page/section_scroll_snap/before_section_end', __NAMESPACE__ . '\wp_page_section_scroll_snap_before_section_end', 10, 2 );
