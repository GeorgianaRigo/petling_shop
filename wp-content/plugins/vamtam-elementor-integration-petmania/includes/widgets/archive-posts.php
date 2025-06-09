<?php
namespace VamtamElementor\Widgets\ArchivePosts;
use ElementorPro\Modules\ThemeBuilder\Widgets\Archive_Posts as Elementor_Archive_Posts;

// Extending the Archive Posts widget.

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'archive-posts' ) ) {
	return;
}

if ( vamtam_theme_supports( 'archive-posts.classic--box-section' ) ) {
	function add_box_section_for_skin( $widget, $skin_prefix ) {
		$widget->start_controls_section(
			"{$skin_prefix}_section_design_box",
			[
				'label' => __( 'Box', 'vamtam-elementor-integration' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'_skin' => "{$skin_prefix}",
				]
			]
		);

		$widget->add_control(
			"{$skin_prefix}_box_border_width",
			[
				'label' => __( 'Border Width', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'border-style: solid; border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			]
		);

		$widget->add_control(
			"{$skin_prefix}_box_border_radius",
			[
				'label' => __( 'Border Radius', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'border-radius: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$widget->add_control(
			"{$skin_prefix}_box_padding",
			[
				'label' => __( 'Padding', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			]
		);

		$widget->add_control(
			"{$skin_prefix}_content_padding",
			[
				'label' => __( 'Content Padding', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-post__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
				'separator' => 'after',
			]
		);

		$widget->start_controls_tabs( "{$skin_prefix}_bg_effects_tabs" );

		$widget->start_controls_tab( "{$skin_prefix}_style_normal",
			[
				'label' => __( 'Normal', 'vamtam-elementor-integration' ),
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => "{$skin_prefix}_box_shadow",
				'selector' => '{{WRAPPER}} .elementor-post',
			]
		);

		$widget->add_control(
			"{$skin_prefix}_box_bg_color",
			[
				'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'background-color: {{VALUE}}',
				],
			]
		);

		$widget->add_control(
			"{$skin_prefix}_box_border_color",
			[
				'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post' => 'border-color: {{VALUE}}',
				],
			]
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab( "{$skin_prefix}_style_hover",
			[
				'label' => __( 'Hover', 'vamtam-elementor-integration' ),
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => "{$skin_prefix}_box_shadow_hover",
				'selector' => '{{WRAPPER}} .elementor-post:hover',
			]
		);

		$widget->add_control(
			"{$skin_prefix}_box_bg_color_hover",
			[
				'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post:hover' => 'background-color: {{VALUE}}',
				],
			]
		);

		$widget->add_control(
			"{$skin_prefix}_box_border_color_hover",
			[
				'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-post:hover' => 'border-color: {{VALUE}}',
				],
			]
		);

		$widget->add_control(
			"{$skin_prefix}_content_hover_color",
			[
				'label' => __( 'Content Color', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					// Title.
					'{{WRAPPER}} .elementor-post:hover .elementor-post__title, {{WRAPPER}} .elementor-post:hover .elementor-post__title a' => 'color: {{VALUE}};',
					// Meta.
					'{{WRAPPER}} .elementor-post:hover .elementor-post__meta-data' => 'color: {{VALUE}};',
					// Excerpt.
					'{{WRAPPER}} .elementor-post:hover .elementor-post__excerpt p' => 'color: {{VALUE}};',
				],
			]
		);

		$widget->end_controls_tab();

		$widget->end_controls_tabs();

		$widget->end_controls_section();
	}

	// Style - Advanced Section (Classic Layout) - After Section End.
	function section_design_content_after_section_end_classic_skin( $widget, $args ) {
		add_box_section_for_skin( $widget, 'archive_classic' );
	}
	add_action( 'elementor/element/archive-posts/archive_classic_section_design_layout/after_section_end', __NAMESPACE__ . '\section_design_content_after_section_end_classic_skin', 10, 2 );

	// Style - Advanced Section (Vamtam_Classic Layout) - After Section End.
	function section_design_content_after_section_end_vamtam_classic_skin( $widget, $args ) {
		add_box_section_for_skin( $widget, 'vamtam_classic' );
	}
	add_action( 'elementor/element/archive-posts/vamtam_classic_section_design_layout/after_section_end', __NAMESPACE__ . '\section_design_content_after_section_end_vamtam_classic_skin', 10, 2 );
}

if ( vamtam_theme_supports( 'posts-base--display-categories', 'posts-base--404-handling-fix' ) ) {
	// Vamtam_Widget_Archive_Posts.
	function widgets_registered() {
		// Is Pro Widget.
		if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
			return;
		}

		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Widgets\Archive_Posts' ) ) {
			return; // Elementor's autoloader acts weird sometimes.
		}

		class Vamtam_Widget_Archive_Posts extends Elementor_Archive_Posts {
			public $extra_depended_scripts = [
				'vamtam-posts-base',
			];

			/*
				We override the get_script_depends method directly because Elementor's
				Posts_Base class returns the array directly, like so:

					public function get_script_depends() {
						return [ 'imagesloaded' ];
					}

				If this changes, we should update this and probably filter the script in the
				add_extra_script_depends method.
			*/
			public function get_script_depends() {
				$script_depends = [
					'imagesloaded',
					'vamtam-posts-base',
				];

				if (  vamtam_theme_supports( 'posts-base--horizontal-layout' ) ) {
					$script_depends[] = 'vamtam-hr-scrolling';
				}

				return $script_depends;
			}

			// Extend constructor.
			public function __construct($data = [], $args = null) {
				parent::__construct($data, $args);

				$this->register_assets();

				$this->add_extra_script_depends();
			}

			/*
				Skins (and their controls) are already registered in the parent class.

				Registering them again (by calling parent::__construct()), would trigger the re-addition of their options, which have already
				been registered at this point, leading to $control_stack issues (adding exisitng control options).
			*/
			protected function register_skins() {
				if ( vamtam_theme_supports( 'posts-base--display-categories' ) ) {
					$this->add_skin( new \VamtamElementor\Widgets\PostsBase\Skin_Vamtam_Archive_Posts_Classic( $this ) );
				}
			}

			// Register the assets the widget depends on.
			public function register_assets() {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				wp_register_script(
					'vamtam-posts-base',
					VAMTAM_ELEMENTOR_INT_URL . '/assets/js/widgets/posts-base/vamtam-posts-base' . $suffix . '.js',
					[
						'elementor-frontend'
					],
					\VamtamElementorIntregration::PLUGIN_VERSION,
					true
				);
			}

			// Assets the widget depends upon.
			public function add_extra_script_depends() {
				// Scripts
				foreach ( $this->extra_depended_scripts as $script ) {
					$this->add_script_depends( $script );
				}
			}
		}

		// Replace current posts widget with our extended version.
		$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		$widgets_manager->unregister( 'archive-posts' );
		$widgets_manager->register( new Vamtam_Widget_Archive_Posts );
	}
	add_action( \Vamtam_Elementor_Utils::get_widgets_registration_hook(), __NAMESPACE__ . '\widgets_registered', 100 );
}
