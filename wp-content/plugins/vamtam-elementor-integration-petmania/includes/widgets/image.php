<?php
namespace VamtamElementor\Widgets\Image;

// Extending the Image widget.

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'image' ) ) {
	return;
}

if ( vamtam_theme_supports( 'image--foreground-layer' ) ) {

	function add_foreground_layer_controls( $controls_manager, $widget ) {
		// Use background layer.
		$widget->add_control(
			'vamtam_use_fg_layer',
			[
				'label' => esc_html__( 'Use Foreground Layer', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => '',
				'separator' => 'before',
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'fg-layer',
			]
		);

		$prefix = 'vamtam_fg_layer';

		// Fg Layer Bg color.
		$widget->add_control(
			"{$prefix}_bg_color",
			[
				'label' => esc_html__( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-fg-layer-bg-color: {{VALUE}}',
				],
				'condition' => [
					'vamtam_use_fg_layer!' => '',
				],
			]
		);

		// Foreground Layer Mask.
		$widget->add_control(
			"{$prefix}_mask",
			[
				'label' => esc_html__( 'Use Mask', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => '',
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'fg-layer-mask',
				'condition' => [
					'vamtam_use_fg_layer!' => '',
				],
			]
		);

		// Use Selected Image as Mask.
		$widget->add_control(
			"{$prefix}_mask_is_selected_img",
			[
				'label' => esc_html__( 'Use Selected Image as Mask', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => 'yes',
				'selectors' => [
					'{{WRAPPER}}' => "--vamtam-fg-layer-mask-image: url('{{image.url}}')",
				],
				'condition' => [
					"{$prefix}_mask!" => '',
					'vamtam_use_fg_layer!' => '',
					'image[url]!' => '',
				],
			]
		);

		// Choose Custom Image
		$widget->add_control(
			"{$prefix}_mask_img",
			[
				'label' => esc_html__( 'Choose Image', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::MEDIA,
				'default' => [
					'url' => \Elementor\Utils::get_placeholder_image_src(),
				],
				'selectors' => [
					'{{WRAPPER}}' => "--vamtam-fg-layer-mask-image: url('{{vamtam_fg_layer_mask_img.url}}')",
				],
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'terms' => [
								[
									'name' => 'vamtam_use_fg_layer',
									'operator' => '!==',
									'value' => ''
								],
								[
									'name' => "{$prefix}_mask",
									'operator' => '!==',
									'value' => ''
								],
								[
									'name' => 'image[url]',
									'operator' => '!==',
									'value' => ''
								],
								[
									'name' => "{$prefix}_mask_is_selected_img",
									'operator' => '===',
									'value' => ''
								],
							]
						],
						[
							'terms' => [
								[
									'name' => 'vamtam_use_fg_layer',
									'operator' => '!==',
									'value' => ''
								],
								[
									'name' => "{$prefix}_mask",
									'operator' => '!==',
									'value' => ''
								],
								[
									'name' => 'image[url]',
									'operator' => '===',
									'value' => ''
								],
							]
						]
					]
				]
			]
		);

		$widget->add_responsive_control(
			"{$prefix}_mask_size",
			[
				'label' => esc_html__( 'Size', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SELECT,
				'options' => [
					'contain' => esc_html__( 'Fit', 'vamtam-elementor-integration' ),
					'cover' => esc_html__( 'Fill', 'vamtam-elementor-integration' ),
					'custom' => esc_html__( 'Custom', 'vamtam-elementor-integration' ),
				],
				'default' => 'contain',
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-fg-layer-mask-size: {{VALUE}};',
				],
				'condition' => [
					'vamtam_use_fg_layer!' => '',
					"{$prefix}_mask!" => '',
				],
			]
		);

		$widget->add_responsive_control(
			"{$prefix}_mask_size_scale",
			[
				'label' => esc_html__( 'Scale', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em', '%', 'vw' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 500,
					],
					'em' => [
						'min' => 0,
						'max' => 100,
					],
					'%' => [
						'min' => 0,
						'max' => 200,
					],
					'vw' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => '%',
					'size' => 100,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-fg-layer-mask-scale: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'vamtam_use_fg_layer!' => '',
					"{$prefix}_mask!" => '',
					"{$prefix}_mask_size" => 'custom',
				],
			]
		);

		$widget->add_responsive_control(
			"{$prefix}_mask_position",
			[
				'label' => esc_html__( 'Position', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SELECT,
				'options' => [
					'center center' => esc_html__( 'Center Center', 'vamtam-elementor-integration' ),
					'center left' => esc_html__( 'Center Left', 'vamtam-elementor-integration' ),
					'center right' => esc_html__( 'Center Right', 'vamtam-elementor-integration' ),
					'top center' => esc_html__( 'Top Center', 'vamtam-elementor-integration' ),
					'top left' => esc_html__( 'Top Left', 'vamtam-elementor-integration' ),
					'top right' => esc_html__( 'Top Right', 'vamtam-elementor-integration' ),
					'bottom center' => esc_html__( 'Bottom Center', 'vamtam-elementor-integration' ),
					'bottom left' => esc_html__( 'Bottom Left', 'vamtam-elementor-integration' ),
					'bottom right' => esc_html__( 'Bottom Right', 'vamtam-elementor-integration' ),
					'custom' => esc_html__( 'Custom', 'vamtam-elementor-integration' ),
				],
				'default' => 'center center',
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-fg-layer-mask-position: {{VALUE}};',
				],
				'condition' => [
					'vamtam_use_fg_layer!' => '',
					"{$prefix}_mask!" => '',
				],
			]
		);

		$widget->add_responsive_control(
			"{$prefix}_mask_position_x",
			[
				'label' => esc_html__( 'X Position', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em', '%', 'vw' ],
				'range' => [
					'px' => [
						'min' => -500,
						'max' => 500,
					],
					'em' => [
						'min' => -100,
						'max' => 100,
					],
					'%' => [
						'min' => -100,
						'max' => 100,
					],
					'vw' => [
						'min' => -100,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => '%',
					'size' => 0,
				],
				'selectors' => [
					'{{WRAPPER}}' => "--vamtam-fg-layer-mask-position: {{{$prefix}_mask_position_y.size}}{{{$prefix}_mask_position_y.unit}} {{SIZE}}{{UNIT}};",
				],
				'condition' => [
					'vamtam_use_fg_layer!' => '',
					"{$prefix}_mask!" => '',
					"{$prefix}_mask_position" => 'custom',
				],
			]
		);

		$widget->add_responsive_control(
			"{$prefix}_mask_position_y",
			[
				'label' => esc_html__( 'Y Position', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em', '%', 'vw' ],
				'range' => [
					'px' => [
						'min' => -500,
						'max' => 500,
					],
					'em' => [
						'min' => -100,
						'max' => 100,
					],
					'%' => [
						'min' => -100,
						'max' => 100,
					],
					'vw' => [
						'min' => -100,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => '%',
					'size' => 0,
				],
				'selectors' => [
					'{{WRAPPER}}' => "{{SIZE}}{{UNIT}} {{{$prefix}_mask_position_x.size}}{{{$prefix}_mask_position_x.unit}};",
				],
				'condition' => [
					'vamtam_use_fg_layer!' => '',
					"{$prefix}_mask!" => '',
					"{$prefix}_mask_position" => 'custom',
				],
			]
		);

		$widget->add_responsive_control(
			"{$prefix}_mask_repeat",
			[
				'label' => esc_html__( 'Repeat', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SELECT,
				'options' => [
					'no-repeat' => esc_html__( 'No-Repeat', 'vamtam-elementor-integration' ),
					'repeat' => esc_html__( 'Repeat', 'vamtam-elementor-integration' ),
					'repeat-x' => esc_html__( 'Repeat-X', 'vamtam-elementor-integration' ),
					'repeat-Y' => esc_html__( 'Repeat-Y', 'vamtam-elementor-integration' ),
					'round' => esc_html__( 'Round', 'vamtam-elementor-integration' ),
					'space' => esc_html__( 'Space', 'vamtam-elementor-integration' ),
				],
				'default' => 'no-repeat',
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-fg-layer-mask-repeat: {{VALUE}};',
				],
				'condition' => [
					'vamtam_use_fg_layer!' => '',
					"{$prefix}_mask!" => '',
					"{$prefix}_mask_size!" => 'cover',
				],
			]
		);
	}

	// Style - Image Section.
	function section_style_image_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_foreground_layer_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/image/section_style_image/before_section_end', __NAMESPACE__ . '\section_style_image_before_section_end', 10, 2 );

	// Vamtam_Widget_Image.
	function widgets_registered() {
		class Vamtam_Widget_Image extends \Elementor\Widget_Image {

			protected function render() {
				$settings = $this->get_settings_for_display();

				if ( empty( $settings['image']['url'] ) ) {
					return;
				}

				$has_grow_scale_anim = in_array( $settings['_animation'], [ 'imageGrowWithScaleLeft', 'imageGrowWithScaleRight', 'imageGrowWithScaleTop', 'imageGrowWithScaleBottom' ] );

				$has_caption = $this->has_caption( $settings );

				$this->add_render_attribute( 'wrapper', 'class', 'elementor-image' );

				if ( ! empty( $settings['shape'] ) ) {
					$this->add_render_attribute( 'wrapper', 'class', 'elementor-image-shape-' . $settings['shape'] );
				}

				$link = $this->get_link_url( $settings );

				if ( $link ) {
					$this->add_link_attributes( 'link', $link );

					if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
						$this->add_render_attribute( 'link', [
							'class' => 'elementor-clickable',
						] );
					}

					if ( 'custom' !== $settings['link_to'] ) {
						$this->add_lightbox_data_attributes( 'link', $settings['image']['id'], $settings['open_lightbox'] );
					}
				} ?>
				<div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>
					<?php if ( $has_grow_scale_anim ) : ?>
						<div class="vamtam-image-wrapper">
					<?php endif; ?>
					<?php if ( $has_caption ) : ?>
						<figure class="wp-caption">
					<?php endif; ?>
					<?php if ( $link ) : ?>
							<a <?php echo $this->get_render_attribute_string( 'link' ); ?>>
					<?php endif; ?>
						<?php echo \Elementor\Group_Control_Image_Size::get_attachment_image_html( $settings ); ?>
					<?php if ( $link ) : ?>
							</a>
					<?php endif; ?>
					<?php if ( $has_caption ) : ?>
							<figcaption class="widget-image-caption wp-caption-text"><?php echo $this->get_caption( $settings ); ?></figcaption>
					<?php endif; ?>
					<?php if ( $has_caption ) : ?>
						</figure>
					<?php endif; ?>
					<?php if ( $has_grow_scale_anim ) : ?>
						</div>
					<?php endif; ?>
				</div>
				<?php
			}

			/**
			 * Render image widget output in the editor.
			 *
			 * Written as a Backbone JavaScript template and used to generate the live preview.
			 *
			 * @since 2.9.0
			 * @access protected
			 */
			protected function content_template() {
				?>
				<# if ( settings.image.url ) {
					var image = {
						id: settings.image.id,
						url: settings.image.url,
						size: settings.image_size,
						dimension: settings.image_custom_dimension,
						model: view.getEditModel()
					};

					var image_url = elementor.imagesManager.getImageUrl( image );

					if ( ! image_url ) {
						return;
					}

					var hasGrowScaleAnim = [ 'imageGrowWithScaleLeft', 'imageGrowWithScaleRight', 'imageGrowWithScaleTop', 'imageGrowWithScaleBottom' ].includes( settings._animation );

					var hasCaption = function() {
						if( ! settings.caption_source || 'none' === settings.caption_source ) {
							return false;
						}
						return true;
					}

					var ensureAttachmentData = function( id ) {
						if ( 'undefined' === typeof wp.media.attachment( id ).get( 'caption' ) ) {
							wp.media.attachment( id ).fetch().then( function( data ) {
								view.render();
							} );
						}
					}

					var getAttachmentCaption = function( id ) {
						if ( ! id ) {
							return '';
						}
						ensureAttachmentData( id );
						return wp.media.attachment( id ).get( 'caption' );
					}

					var getCaption = function() {
						if ( ! hasCaption() ) {
							return '';
						}
						return 'custom' === settings.caption_source ? settings.caption : getAttachmentCaption( settings.image.id );
					}

					var link_url;

					if ( 'custom' === settings.link_to ) {
						link_url = settings.link.url;
					}

					if ( 'file' === settings.link_to ) {
						link_url = settings.image.url;
					}

					#><div class="elementor-image{{ settings.shape ? ' elementor-image-shape-' + settings.shape : '' }}"><#
					var imgClass = '';

					if ( '' !== settings.hover_animation ) {
						imgClass = 'elementor-animation-' + settings.hover_animation;
					}

					if ( hasGrowScaleAnim ) {
						#><div class="vamtam-image-wrapper"><#
					}

					if ( hasCaption() ) {
						#><figure class="wp-caption"><#
					}

					if ( link_url ) {
							#><a class="elementor-clickable" data-elementor-open-lightbox="{{ settings.open_lightbox }}" href="{{ link_url }}"><#
					}
								#><img src="{{ image_url }}" class="{{ imgClass }}" /><#

					if ( link_url ) {
							#></a><#
					}

					if ( hasCaption() ) {
							#><figcaption class="widget-image-caption wp-caption-text">{{{ getCaption() }}}</figcaption><#
					}

					if ( hasCaption() ) {
						#></figure><#
					}

					if ( hasGrowScaleAnim ) {
						#></div><#
					}

					#></div><#
				} #>
				<?php
			}

			/**
			 * Check if the current widget has caption
			 *
			 * @access private
			 * @since 2.3.0
			 *
			 * @param array $settings
			 *
			 * @return boolean
			 */
			private function has_caption( $settings ) {
				return ( ! empty( $settings['caption_source'] ) && 'none' !== $settings['caption_source'] );
			}

			/**
			 * Get the caption for current widget.
			 *
			 * @access private
			 * @since 2.3.0
			 * @param $settings
			 *
			 * @return string
			 */
			private function get_caption( $settings ) {
				$caption = '';
				if ( ! empty( $settings['caption_source'] ) ) {
					switch ( $settings['caption_source'] ) {
						case 'attachment':
							$caption = wp_get_attachment_caption( $settings['image']['id'] );
							break;
						case 'custom':
							$caption = ! \Elementor\Utils::is_empty( $settings['caption'] ) ? $settings['caption'] : '';
					}
				}
				return $caption;
			}

			/**
			 * Retrieve image widget link URL.
			 *
			 * @since 1.0.0
			 * @access protected
			 *
			 * @param array $settings
			 *
			 * @return array|string|false An array/string containing the link URL, or false if no link.
			 */
			protected function get_link_url( $settings ) {
				switch ( $settings['link_to'] ) {
					case 'none':
						return false;

					case 'custom':
						return ( ! empty( $settings['link']['url'] ) ) ? $settings['link'] : false;

					case 'site_url':
						return [ 'url' => \ElementorPro\Plugin::elementor()->dynamic_tags->get_tag_data_content( null, 'site-url' ) ?? '' ];

					default:
						return [ 'url' => $settings['image']['url'] ];
				}
			}

		}

		// Replace current image widget with our extended version.
		$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		$widgets_manager->unregister( 'image' );
		$widgets_manager->register( new Vamtam_Widget_Image );
	}
	add_action( \Vamtam_Elementor_Utils::get_widgets_registration_hook(), __NAMESPACE__ . '\widgets_registered', 100 );
}
