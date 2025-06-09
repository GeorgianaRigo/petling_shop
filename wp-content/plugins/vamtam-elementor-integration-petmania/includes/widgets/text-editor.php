<?php
namespace VamtamElementor\Widgets\TextEditor;

// Extending the Text Editor widget.

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'text-editor' ) ) {
	return;
}

// Increase specificity of Title selectors so the kit's link ones dont override the local ones.
if ( vamtam_theme_supports( 'text-editor--selectors-include-links' ) ) {
	function update_text_editor_style_controls( $controls_manager, $widget ) {
		// Text Color.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'text_color', [
			'selectors' => [
				'{{WRAPPER}} a' => 'color: {{VALUE}};',
			]
		] );
		// Typography.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'typography', [
			'selector' => '{{WRAPPER}} a',
		], \Elementor\Group_Control_Typography::get_type() );

	}
	// Style - Text Editor Section (Before).
	function section_style_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		update_text_editor_style_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/text-editor/section_style/before_section_end', __NAMESPACE__ . '\section_style_before_section_end', 10, 2 );
}
