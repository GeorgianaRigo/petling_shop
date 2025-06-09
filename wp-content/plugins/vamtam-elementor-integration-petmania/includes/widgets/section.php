<?php
namespace VamtamElementor\Widgets\Section;

// Extending the Section widget.

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'section' ) ) {
	return;
}

if ( vamtam_theme_supports( 'section--vamtam-sticky-header-controls' ) ) {
	// Removes render attributes recursively.
	function unset_render_attrs_recursively( $element ) {
		// As long as we have children, keep removing their render attributes.
		if ( ! empty( $element->get_children() ) ) {
			foreach ($element->get_children() as $child ) {
				unset_render_attrs_recursively( $child );
			}
		}

		$attrs = $element->get_render_attributes();
		foreach ( $attrs as $key => $value ) {
			$element->remove_render_attribute( $key );
		}
	}

	function section_after_render( $element ) {
		if ( 'section' === $element->get_name() ) {
			// Editor.
			if ( \Elementor\Plugin::$instance->preview->is_preview_mode()  ) {
				return;
			}

			$settings         = $element->get_settings_for_display();
			$is_sticky_header = isset( $settings['use_vamtam_sticky_header'] ) && '' !== $settings['use_vamtam_sticky_header'];

			if (  $is_sticky_header ) {
				// Make a copy of the element, to be used as a spacer for the sticky header.
				$section_html = '';

				ob_start();
				// Before section render.
				$element->before_render();
				// Section content.
				foreach ( $element->get_children() as $child ) {
					// Cause of the double render, we end up with some duplicate render attributes.
					// We remove the ones already printed by the previous render, so they don't get rendered twice.
					unset_render_attrs_recursively( $child );
					$child->print_element();
				}
				// After section render.
				$element->after_render();

				$section_html = ob_get_clean();

				// Add the spacer class.
				$replace      = 'vamtam-sticky-header';
				$replaceWith  = 'vamtam-sticky-header vamtam-sticky-header--spacer';
				// Regex needs to be only for exact match.
				$section_html = preg_replace('/(?<=\s|^)(?:' . $replace . ')(?=\s|$)/', $replaceWith, $section_html, 1);

				// Print spacer element.
				echo $section_html; //xss ok
			}
		}
	}
	add_action( 'elementor/frontend/after_render', __NAMESPACE__ . '\section_after_render', 10, 2 );

	function add_vamtam_sticky_header_controls( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'sticky_effects_offset',
			'at' => 'after',
		] );
		$widget->add_control(
			'use_vamtam_sticky_header',
			[
				'label' => __( 'Theme Sticky Header (Desktop)', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => '',
				'return_value' => 'vamtam-sticky-header',
				'condition' => [
					'sticky' => '',
				]
			]
		);
		$widget->add_control(
			'vamtam_sticky_header_transparent',
			[
				'label' => __( 'Header Is Transparent', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => '',
				'return_value' => 'vamtam-sticky-header--transparent-header',
				'condition' => [
					'sticky' => '',
					'use_vamtam_sticky_header!' => '',
				],
			]
		);
		$widget->add_control(
			'vamtam_sticky_offset',
			[
				'label' => esc_html__( 'Offset (px)', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::NUMBER,
				'default' => 0,
				'min' => 0,
				'max' => 500,
				'condition' => [
					'sticky' => '',
					'use_vamtam_sticky_header!' => '',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-sticky-offset: {{VALUE}}px',
				]
			]
		);
		$widget->add_control(
			'vamtam_offset_on_sticky',
			[
				'label' => __( 'Offset on Sticky', 'vamtam-elementor-integration' ),
				'description' => __( 'Offset will be applied to the sticky state of the header as well. When disabled, offset is only applied on the initial position of the sticky header.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => '',
				'return_value' => 'vamtam-sticky-header--offset-on-sticky',
				'condition' => [
					'sticky' => '',
					'use_vamtam_sticky_header!' => '',
				],
			]
		);
		$widget->end_injection();
	}

	// Advanced - Motion effects.
	function section_effects_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_vamtam_sticky_header_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/section/section_effects/before_section_end', __NAMESPACE__ . '\section_effects_before_section_end', 10, 2 );
}
