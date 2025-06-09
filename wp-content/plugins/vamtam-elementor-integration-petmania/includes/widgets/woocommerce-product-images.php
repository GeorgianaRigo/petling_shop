<?php
namespace VamtamElementor\Widgets\ProductImages;

use \ElementorPro\Modules\Woocommerce\Widgets\Product_Images as Elementor_Product_Images;
// Extending the WC Product Images widget.

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-images' ) ) {
	return;
}

if ( vamtam_theme_supports( 'woocommerce-product-images--sale-flash-section' ) ) {
	function add_vamtam_sale_flash_section( $controls_manager, $widget ) {
		\Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'sale_flash' );

		$widget->start_controls_section(
			'vamtam_sale_flash_style',
			[
				'label' => __( 'Sale Flash', 'vamtam-elementor-integration' ),
				'tab' => $controls_manager::TAB_STYLE,
			]
		);

		$widget->add_control(
			'sale_flash',
			[
				'label' => __( 'Sale Flash', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'label_on' => __( 'Show', 'vamtam-elementor-integration' ),
				'label_off' => __( 'Hide', 'vamtam-elementor-integration' ),
				'render_type' => 'template',
				'return_value' => 'yes',
				'default' => 'yes',
				'prefix_class' => 'vamtam-has-onsale-',
			]
		);

		$widget->add_control(
			'onsale_text_color',
			[
				'label' => __( 'Text Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} span.onsale' => 'color: {{VALUE}}',
				],
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->add_control(
			'onsale_text_background_color',
			[
				'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} span.onsale' => 'background-color: {{VALUE}}',
				],
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'onsale_typography',
				'selector' => '{{WRAPPER}} span.onsale',
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->add_control(
			'onsale_border_radius',
			[
				'label' => __( 'Border Radius', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} span.onsale' => 'border-radius: {{SIZE}}{{UNIT}}',
				],
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->add_control(
			'onsale_width',
			[
				'label' => __( 'Width', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} span.onsale' => 'min-width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->add_control(
			'onsale_height',
			[
				'label' => __( 'Height', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} span.onsale' => 'min-height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->add_control(
			'onsale_horizontal_position',
			[
				'label' => __( 'Position', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => __( 'Left', 'vamtam-elementor-integration' ),
						'icon' => 'eicon-h-align-left',
					],
					'right' => [
						'title' => __( 'Right', 'vamtam-elementor-integration' ),
						'icon' => 'eicon-h-align-right',
					],
				],
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->add_control(
			'onsale_distance',
			[
				'label' => __( 'Distance', 'vamtam-elementor-integration' ),
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
					'{{WRAPPER}} span.onsale' => 'margin: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'sale_flash' => 'yes',
				],
			]
		);

		$widget->end_controls_section();
	}
	function section_product_gallery_style_after_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_vamtam_sale_flash_section( $controls_manager, $widget );
	}
	add_action( 'elementor/element/woocommerce-product-images/section_product_gallery_style/after_section_end', __NAMESPACE__ . '\section_product_gallery_style_after_section_end', 10, 2 );
}

if ( vamtam_theme_supports( 'woocommerce-product-images--new-badge-section' ) ) {
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
					'{{WRAPPER}} .vamtam-new' => 'display: block',
				],
			]
		);

		$widget->add_control(
			'vamtam_new_badge_color',
			[
				'label' => esc_html__( 'Text Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .vamtam-new' => 'color: {{VALUE}}',
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
					'{{WRAPPER}} .vamtam-new' => 'background-color: {{VALUE}}',
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
				'selector' => '{{WRAPPER}} .vamtam-new',
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
					'{{WRAPPER}} .vamtam-new' => 'border-radius: {{SIZE}}{{UNIT}}',
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
					'{{WRAPPER}} .vamtam-new' => 'min-width: {{SIZE}}{{UNIT}};',
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
					'{{WRAPPER}} .vamtam-new' => 'min-height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
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
					'{{WRAPPER}} .vamtam-new' => '{{VALUE}}',
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
					'{{WRAPPER}} .vamtam-new' => 'margin: {{SIZE}}{{UNIT}};',
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
					'{{WRAPPER}}.vamtam-has-onsale-yes .vamtam-onsale.vamtam-new' => 'top: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'sale_flash!' => '',
					'vamtam_show_new_badge!' => '',
				],
			]
		);

		$widget->end_controls_section();
	}
	// Sale Flash section - After Section End.
	function vamtam_sale_flash_style_after_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_new_badge_section( $controls_manager, $widget );
	}
	add_action( 'elementor/element/woocommerce-product-images/vamtam_sale_flash_style/after_section_end', __NAMESPACE__ . '\vamtam_sale_flash_style_after_section_end', 10, 2 );
}

if ( vamtam_theme_supports( 'woocommerce-product-images--disable-image-link' ) ) {
	function add_disable_image_link_control( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'sale_flash',
		] );

		$widget->add_control(
			'disable_image_link',
		[
				'label' => __( 'Disable Image Link', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'disable-image-link',
				'description' => __( 'Disables opening the image on a new tab or Elementor lightbox. Doesn\'t disable WC\'s lightbox (if enabled).', 'vamtam-elementor-integration' ),
			]
		);

		$widget->end_injection();
	}
}

if ( vamtam_theme_supports( 'woocommerce-product-images--dot-nav-on-mobile' ) ) {
	function add_use_dot_nav_on_mobile_control( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'sale_flash',
		] );

		$widget->add_control(
			'vamtam_use_dot_nav_on_mobile',
		[
				'label' => __( 'Use Dot Navigation On Mobile', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'dot-nav-on-mobile',
				'default' => 'dot-nav-on-mobile',
			]
		);

		$widget->start_controls_tabs( 'vamtam_mobile_nav_color_tabs' );
		// Normal.
		$widget->start_controls_tab(
			'vamtam_mobile_nav_color_normal',
			[
				'label' => __( 'Normal', 'vamtam-elementor-integration' ),
				'condition' => [
					'vamtam_use_dot_nav_on_mobile!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_dot_nav_border_color',
			[
				'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-dot-nav-border-color: {{VALUE}}',
				],
				'condition' => [
					'vamtam_use_dot_nav_on_mobile!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_dot_nav_bg_color',
			[
				'label' => __( 'Bg Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-dot-nav-bg-color: {{VALUE}}',
				],
				'condition' => [
					'vamtam_use_dot_nav_on_mobile!' => '',
				],
			]
		);

		$widget->end_controls_tab();
		// Active
		$widget->start_controls_tab(
			'vamtam_mobile_nav_color_active',
			[
				'label' => __( 'Active', 'vamtam-elementor-integration' ),
				'condition' => [
					'vamtam_use_dot_nav_on_mobile!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_dot_nav_border_color_active',
			[
				'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-dot-nav-border-color-active: {{VALUE}}',
				],
				'condition' => [
					'vamtam_use_dot_nav_on_mobile!' => '',
				],
			]
		);

		$widget->add_control(
			'vamtam_dot_nav_bg_color_active',
			[
				'label' => __( 'Bg Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-dot-nav-bg-color-active: {{VALUE}}',
				],
				'condition' => [
					'vamtam_use_dot_nav_on_mobile!' => '',
				],
			]
		);

		$widget->end_controls_tab();

		$widget->end_controls_tabs();

		$widget->end_injection();
	}
}

if ( vamtam_theme_supports( 'woocommerce-product-images--no-border-controls' ) ) {
	function remove_border_controls( $controls_manager, $widget ) {
		$widget->remove_control( 'image_border_border' );
		$widget->remove_control( 'image_border_border_width' );
		$widget->remove_control( 'image_border_border_width_tablet' );
		$widget->remove_control( 'image_border_border_width_mobile' );
		$widget->remove_control( 'image_border_width' );
		$widget->remove_control( 'image_border_width_tablet' );
		$widget->remove_control( 'image_border_width_mobile' );
		$widget->remove_control( 'image_border_border_color' );
		$widget->remove_control( 'image_border_color' );

		$widget->remove_control( 'image_border_radius' );
		$widget->remove_control( 'image_border_radius_tablet' );
		$widget->remove_control( 'image_border_radius_mobile' );

		$widget->remove_control( 'spacing' );

		$widget->remove_control( 'heading_thumbs_style' );

		$widget->remove_control( 'thumbs_border_border' );
		$widget->remove_control( 'thumbs_border_border_width' );
		$widget->remove_control( 'thumbs_border_border_width_tablet' );
		$widget->remove_control( 'thumbs_border_border_width_mobile' );
		$widget->remove_control( 'thumbs_border_width' );
		$widget->remove_control( 'thumbs_border_width_tablet' );
		$widget->remove_control( 'thumbs_border_width_mobile' );
		$widget->remove_control( 'thumbs_border_border_color' );
		$widget->remove_control( 'thumbs_border_color' );

		$widget->remove_control( 'thumbs_border_radius' );
		$widget->remove_control( 'thumbs_border_radius_tablet' );
		$widget->remove_control( 'thumbs_border_radius_mobile' );

		$widget->remove_control( 'spacing_thumbs' );
	}
}

if ( vamtam_theme_supports( 'woocommerce-product-images--full-sized-gallery' ) ) {
	function add_full_sized_gallery_controls( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'sale_flash',
		] );

		$widget->add_control(
			'use_full_sized_gallery',
			[
				'label' => __( 'Display as Full Size Gallery', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'full-sized-gallery',
				'render_type' => 'template',
			]
		);

		$widget->add_control(
			'disable_on_mobile_browsers',
			[
				'label' => __( 'Disable On Mobile Browsers', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => 'vamtam-mbrowser-no-fsg',
				'return_value' => 'vamtam-mbrowser-no-fsg',
				'prefix_class' => '',
				'condition' => [
					'use_full_sized_gallery!' => '',
				]
			]
		);

		$widget->add_control(
			'vamtam_image_spacing',
			[
				'label' => __( 'Image Spacing', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .woocommerce-product-gallery__wrapper,{{WRAPPER}} .woocommerce-product-gallery--vamtam__wrapper' => 'grid-gap: {{SIZE}}{{UNIT}}',
				],
				'condition' => [
					'use_full_sized_gallery!' => '',
				]
			]
		);

		$widget->end_injection();
	}
	function full_sized_gallery_thumb_size( $sizes ) {
		if ( isset( $GLOBALS['product_images_use_full_sized_gallery'] ) && $GLOBALS['product_images_use_full_sized_gallery'] === true ) {
			return 'woocommerce_single';
		}
		return $sizes;
	}
	add_filter( 'woocommerce_gallery_thumbnail_size', __NAMESPACE__ . '\full_sized_gallery_thumb_size', 100 );
	function full_sized_gallery_thumb_cols( $cols ) {
		if ( isset( $GLOBALS['product_images_use_full_sized_gallery'] ) && $GLOBALS['product_images_use_full_sized_gallery'] === true ) {
			$cols = 1;
		}
		return $cols;
	}
	add_filter( 'woocommerce_product_thumbnails_columns', __NAMESPACE__ . '\full_sized_gallery_thumb_cols', 100 );
	function full_sized_gallery_flex_slider( $boolean ) {
		if ( isset( $GLOBALS['product_images_use_full_sized_gallery'] ) && $GLOBALS['product_images_use_full_sized_gallery'] === true ) {
			return false;
		}
		return $boolean;
	}
	add_filter( 'woocommerce_single_product_flexslider_enabled', __NAMESPACE__ . '\full_sized_gallery_flex_slider', 100 );
}

if ( vamtam_theme_supports( [ 'woocommerce-product-images--disable-image-link', 'woocommerce-product-images--full-sized-gallery'] ) ) {
	// Vamtam_Widget_Product_Images.
	function widgets_registered() {
		if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
			return;
		}

		if ( ! class_exists( '\ElementorPro\Modules\Woocommerce\Widgets\Product_Images' ) ) {
			return; // Elementor's autoloader acts weird sometimes.
		}

		class Vamtam_Widget_Product_Images extends Elementor_Product_Images {
			public $extra_depended_scripts = [
				'vamtam-woocommerce-product-images',
			];

			// Extend constructor.
			public function __construct($data = [], $args = null) {
				parent::__construct($data, $args);

				$this->register_assets();

				$this->add_extra_script_depends();
			}

			// Register the assets the widget depends on.
			public function register_assets() {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				wp_register_script(
					'vamtam-woocommerce-product-images',
					VAMTAM_ELEMENTOR_INT_URL . 'assets/js/widgets/woocommerce-product-images/vamtam-woocommerce-product-images' . $suffix . '.js',
					[
						'elementor-frontend',
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

			// Override.
			public function render() {
				$settings = $this->get_settings_for_display();
				global $product;

				$product = wc_get_product();

				if ( empty( $product ) ) {
					return;
				}

				if ( vamtam_theme_supports( 'woocommerce-product-images--full-sized-gallery' ) ) {
					if ( ! empty( $settings[ 'use_full_sized_gallery' ] ) ) {
						$GLOBALS['product_images_use_full_sized_gallery'] = true;
					}

					if ( wp_is_mobile() && ! empty( $settings[ 'disable_on_mobile_browsers' ] ) ) {
						unset( $GLOBALS['product_images_use_full_sized_gallery'] );
						$this->add_render_attribute( '_wrapper', 'class', 'vamtam-mobile-gallery' );
					}
				}

				// Theme options.
				if ( \Vamtam_Elementor_Utils::is_wc_mod_active( 'wc_products_new_badge' ) ) {
					// New badge.
					do_action( 'vamtam_display_product_new_badge' );
				}

				if ( 'yes' === $settings['sale_flash'] ) {
					wc_get_template( 'loop/sale-flash.php' );
				}

				wc_get_template( 'single-product/product-image.php' );

				if ( vamtam_theme_supports( 'woocommerce-product-images--full-sized-gallery' ) ) {
					if ( isset( $settings[ 'use_full_sized_gallery' ] ) && ! empty( $settings[ 'use_full_sized_gallery' ] ) ) {
						unset( $GLOBALS['product_images_use_full_sized_gallery'] );

						// That's a hack to not allow WC's flexslider to be applied for this gallery.
						// We fix this on the widget's js handler onInit.
						?>
						<script>
						jQuery( document ).ready( function () {
							const $fsGalls = jQuery( '.vamtam-has-full-sized-gallery' );
							jQuery.each( $fsGalls, function ( i, widget ) {
								const $galEl = jQuery( widget ).find( 'div.woocommerce-product-gallery' ),
									mBrNoFsg = jQuery( widget ).hasClass( 'vamtam-mbrowser-no-fsg' );
								if ( window.VAMTAM.isMobileBrowser && mBrNoFsg ) {
									jQuery( widget ).addClass('vamtam-mobile-gallery');
									return;
								} else {
									$galEl.removeClass( 'woocommerce-product-gallery' ).addClass( 'woocommerce-product-gallery--vamtam' );
								}
							} );
						} );
						</script>
						<?php
					}

				}

				// On render widget from Editor - trigger the init manually.
				if ( wp_doing_ajax() ) {
					?>
					<script>
						setTimeout(() => {
							jQuery( '.woocommerce-product-gallery' ).each( function() {
								jQuery( this ).wc_product_gallery();
							} );
						}, 200);
					</script>
					<?php
				}
			}
		}

		// Replace current products widget with our extended version.
		$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		$widgets_manager->unregister( 'woocommerce-product-images' );
		$widgets_manager->register( new Vamtam_Widget_Product_Images );
	}
	add_action( \Vamtam_Elementor_Utils::get_widgets_registration_hook(), __NAMESPACE__ . '\widgets_registered', 100 );
}

function section_product_gallery_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	if ( vamtam_theme_supports( 'woocommerce-product-images--dot-nav-on-mobile' ) ) {
		add_use_dot_nav_on_mobile_control( $controls_manager, $widget );
	}

	if ( vamtam_theme_supports( 'woocommerce-product-images--disable-image-link' ) ) {
		add_disable_image_link_control( $controls_manager, $widget );
	}

	if ( vamtam_theme_supports( 'woocommerce-product-images--full-sized-gallery' ) ) {
		add_full_sized_gallery_controls( $controls_manager, $widget );
	}

	if ( vamtam_theme_supports( 'woocommerce-product-images--no-border-controls' ) ) {
		remove_border_controls( $controls_manager, $widget );
	}
}
add_action( 'elementor/element/woocommerce-product-images/section_product_gallery_style/before_section_end', __NAMESPACE__ . '\section_product_gallery_style_before_section_end', 10, 2 );
