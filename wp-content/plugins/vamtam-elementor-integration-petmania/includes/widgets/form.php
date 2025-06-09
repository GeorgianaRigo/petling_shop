<?php
namespace VamtamElementor\Widgets\Form;

use ElementorPro\Modules\Forms\Widgets\Form as Elementor_Form;

use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

// Extending the Form widget.

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'form' ) ) {
	return;
}

if ( vamtam_theme_supports( 'form--updated-fields-style-section' ) ) {
	function add_new_field_style_section( $controls_manager, $widget ) {
		// Remove prev section_field_style section.
		\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'section_field_style' );

		// We also need to remove it's fields (so we can re-declare them)
		// Text Color.
		\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'field_text_color' );
		// Typography.
		\Vamtam_Elementor_Utils::remove_group_control( $controls_manager, $widget, 'field_typography', \Elementor\Group_Control_Typography::get_type() );
		// Bg Color.
		\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'field_background_color' );
		// Border Color.
		\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'field_border_color' );
		// Border Width.
		\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'field_border_width' );
		// Border Radius.
		\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'field_border_radius' );


		// Start new section.
		$widget->start_controls_section(
			'section_field_styles',
			[
				'label' => __( 'Field', 'vamtam-elementor-integration' ),
				'tab' => $controls_manager::TAB_STYLE,
			]
		);

		// Typography.
		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'field_typography',
				'selector' => '{{WRAPPER}} .elementor-field-group .elementor-field, {{WRAPPER}} .elementor-field-subgroup label',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);
		// Padding
		$widget->add_responsive_control(
			'field_padding',
			[
				'label' => __( 'Padding', 'vamtam-elementor-integration' ),
				'description' => __( 'Applies to fields & labels.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field, {{WRAPPER}} .elementor-field-group .elementor-field-label, {{WRAPPER}} .elementor-field-subgroup label' => 'padding-left: {{LEFT}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}};',
				],
				'allowed_dimensions' => 'horizontal',
			]
		);

		$widget->start_controls_tabs( 'vamtam_field_tabs' );
		// Normal.
		$widget->start_controls_tab(
			'vamtam_field_tabs_normal',
			[
				'label' => __( 'Normal', 'vamtam-elementor-integration' ),
			]
		);
		// Text Color.
		$widget->add_control(
			'field_text_color',
			[
				'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field,
					{{WRAPPER}} .elementor-field-group .elementor-field::placeholder' => 'color: {{VALUE}}; caret-color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);
		// Bg Color.
		$widget->add_control(
			'field_background_color',
			[
				'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper),
					{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'background-color: {{VALUE}};',
				],
			]
		);
		// Box Shadow.
		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'field_box_shadow',
				'selector' => '{{WRAPPER}} input:not([type="button"]):not([type="submit"]), {{WRAPPER}} textarea, {{WRAPPER}} .elementor-field-textual',
			]
		);
		// Border.
		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'field_border',
				'selector' => '{{WRAPPER}} input:not([type="button"]):not([type="submit"]), {{WRAPPER}} textarea, {{WRAPPER}} .elementor-field-textual',
				'fields_options' => [
					'color' => [
						'dynamic' => [],
						'selectors' => [
							'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper),
							{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-color: {{VALUE}};',
							'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper::before' => 'color: {{VALUE}};',
						],
					],
					'width' => [
						'selectors' => [
							'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper),
							{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						],
					],
				],
			]
		);
		// Border Radius.
		$widget->add_control(
			'field_border_radius',
			[
				'label' => __( 'Border Radius', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper),
					{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$widget->end_controls_tab();
		// Hover
		$widget->start_controls_tab(
			'vamtam_field_tabs_hover',
			[
				'label' => __( 'Hover', 'vamtam-elementor-integration' ),
			]
		);
		// Hover Text Color.
		$widget->add_control(
			'field_text_color_hover',
			[
				'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field:hover,
					{{WRAPPER}} .elementor-field-group .elementor-field:hover::placeholder' => 'color: {{VALUE}}; caret-color: {{VALUE}};',
				],
			]
		);
		// Hover Bg Color.
		$widget->add_control(
			'field_background_color_hover',
			[
				'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):hover,
					{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:hover select' => 'background-color: {{VALUE}};',
				],
			]
		);
		// Hover Box Shadow.
		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'field_box_shadow_hover',
				'selector' => '{{WRAPPER}} input:hover:not([type="button"]):not([type="submit"]), {{WRAPPER}} textarea:hover, {{WRAPPER}} .elementor-field-textual:hover',
			]
		);
		// Hover Border.
		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'field_border_hover',
				'selector' => '{{WRAPPER}} input:hover:not([type="button"]):not([type="submit"]), {{WRAPPER}} textarea:hover, {{WRAPPER}} .elementor-field-textual:hover',
				'fields_options' => [
					'color' => [
						'dynamic' => [],
						'selectors' => [
							'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):hover,
							{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:hover select' => 'border-color: {{VALUE}};',
							'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:hover::before' => 'color: {{VALUE}};',
						],
					],
					'width' => [
						'selectors' => [
							'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):hover,
							{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:hover select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						],
					],
				],
			]
		);
		// Hover Border Radius.
		$widget->add_control(
			'field_border_radius_hover',
			[
				'label' => __( 'Border Radius', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):hover,
					{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:hover select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		// Hover Transition Duration.
		$widget->add_control(
			'field_hover_transition_duration',
			[
				'label' => __( 'Transition Duration', 'vamtam-elementor-integration' ) . ' (ms)',
				'type' => $controls_manager::SLIDER,
				'selectors' => [
					'{{WRAPPER}} input:not([type="button"]):not([type="submit"]),
					{{WRAPPER}} textarea,
					{{WRAPPER}} .elementor-field-textual' => 'transition: {{SIZE}}ms',
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 3000,
					],
				],
			]
		);
		$widget->end_controls_tab();
		// Focus
		$widget->start_controls_tab(
			'vamtam_field_tabs_focus',
			[
				'label' => __( 'Focus', 'vamtam-elementor-integration' ),
			]
		);
		// Focus Text Color.
		$widget->add_control(
			'field_text_color_focus',
			[
				'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field:focus,
					{{WRAPPER}} .elementor-field-group .elementor-field:focus::placeholder' => 'color: {{VALUE}}; caret-color: {{VALUE}};',
				],
			]
		);
		// Focus Bg Color.
		$widget->add_control(
			'field_background_color_focus',
			[
				'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):focus,
					{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:focus select' => 'background-color: {{VALUE}};',
				],
			]
		);
		// Focus Box Shadow.
		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'field_box_shadow_focus',
				'selector' => '{{WRAPPER}} input:focus:not([type="button"]):not([type="submit"]), {{WRAPPER}} textarea:focus, {{WRAPPER}} .elementor-field-textual:focus',
			]
		);
		// Focus Border.
		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'field_border_focus',
				'selector' => '{{WRAPPER}} input:focus:not([type="button"]):not([type="submit"]), {{WRAPPER}} textarea:focus, {{WRAPPER}} .elementor-field-textual:focus',
				'fields_options' => [
					'color' => [
						'dynamic' => [],
						'selectors' => [
							'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):focus,
							{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:focus select' => 'border-color: {{VALUE}};',
							'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:focus::before' => 'color: {{VALUE}};',
						],
					],
					'width' => [
						'selectors' => [
							'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):focus,
							{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:focus select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						],
					],
				],
			]
		);
		// Focus Border Radius.
		$widget->add_control(
			'field_border_radius_focus',
			[
				'label' => __( 'Border Radius', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper):focus,
					{{WRAPPER}} .elementor-field-group .elementor-select-wrapper:focus select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$widget->end_controls_tab();
		$widget->end_controls_tabs();

		$widget->end_controls_section();
	}
	// Style - Fields section
	function section_field_style_after_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_new_field_style_section( $controls_manager, $widget );
	}
	add_action( 'elementor/element/form/section_field_style/after_section_end', __NAMESPACE__ . '\section_field_style_after_section_end', 10, 2 );
}
