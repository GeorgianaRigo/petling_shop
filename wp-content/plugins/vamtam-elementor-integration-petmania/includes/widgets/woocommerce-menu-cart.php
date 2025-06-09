<?php
namespace VamtamElementor\Widgets\MenuCart;

// Extending the Menu Cart widget.

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme Settings.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-menu-cart' ) ) {
	return;
}

function render_content( $content, $widget ) {
	if ( 'woocommerce-menu-cart' === $widget->get_name() ) {
		$settings            = $widget->get_settings();
		$show_close_cart_btn = ! empty( $settings[ 'close_cart_button_show' ] );
		$close_cart_btn_icon = isset( $settings[ 'close_cart_icon_svg' ] ) ? $settings[ 'close_cart_icon_svg' ] : [];
		$close_btn_el        = '';
		$regex               = '/<div class="elementor-menu-cart__close-button(-custom)?">.*?<\/div>/s';
		$force_show          = ! \VamtamElementorBridge::elementor_pro_is_v3_12_or_greater(); // before pro v3.12.0 there was no way to hide or change the close icon.

		preg_match_all( $regex, $content, $matches );

		// Remove current close button (we add it in header below).
		$content = preg_replace( $regex, '', $content );

		if ( $show_close_cart_btn || $force_show ) {
			if ( empty( $close_cart_btn_icon[ 'value' ] ) || $force_show ) { // empty value == default icon (we replace with theme default)
				$close_cart_icon = '<svg class="font-h4 vamtam-close vamtam-close-cart" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" version="1.1"><path d="M10 8.586l-7.071-7.071-1.414 1.414 7.071 7.071-7.071 7.071 1.414 1.414 7.071-7.071 7.071 7.071 1.414-1.414-7.071-7.071 7.071-7.071-1.414-1.414-7.071 7.071z"></path></svg>';

				if ( vamtam_theme_supports( 'woocommerce-menu-cart--close-cart-theme-icon' ) ) {
					$close_cart_icon = '<i class="vamtam-close vamtam-close-cart vamtamtheme- vamtam-theme-close"></i>';
				}

				$close_btn_el = '<div class="elementor-menu-cart__close-button">' . $close_cart_icon . '</div>';
			} else {
				$close_btn_el = ! empty( $matches[0] ) ? $matches[0][0] : '';
			}
		}

		// Inject cart header.
		$header  = '<div class="vamtam-elementor-menu-cart__header">
						<span class="font-h4 label">' . esc_html__( 'Cart', 'vamtam-elementor-integration' ) . '</span>
						<span class="font-h4 item-count">(' . esc_html( WC()->cart->get_cart_contents_count() ) . ')</span>
						' . $close_btn_el . '
					</div>';
		$content = str_replace( '<div class="widget_shopping_cart_content', $header . '<div class="widget_shopping_cart_content', $content );
	}
	return $content;
}
// Called frontend & editor (editor after element loses focus).
add_filter( 'elementor/widget/render_content', __NAMESPACE__ . '\render_content', 10, 2 );

function update_controls_style_tab_products_section( $controls_manager, $widget ) {
	// Product Title Typography.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'product_title_typography', [
		'selectors' => [
			'{{WRAPPER}} .vamtam-elementor-menu-cart__header > .item-count' => '{{_RESET_}}',
		],
		'separator' => 'before',
		],
		\Elementor\Group_Control_Typography::get_type()
	);

	// Product Variations Typography.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'product_variations_typography', [
		'selector' => [
			'selector' => '{{WRAPPER}} .elementor-menu-cart__product.cart_item .variation',
		],
		],
		\Elementor\Group_Control_Typography::get_type()
	);

	// Product Price Typography.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'product_price_typography', [
		'selector' => [
			'selector' => '{{WRAPPER}} .elementor-menu-cart__product-price, {{WRAPPER}} .elementor-menu-cart__product.cart_item .quantity .amount',
		],
		],
		\Elementor\Group_Control_Typography::get_type()
	);

	$qnty_selectors = '{{WRAPPER}} .elementor-menu-cart__product-price.product-price .quantity .vamtam-quantity select,' .
		'{{WRAPPER}} .elementor-menu-cart__product-price.product-price .quantity .vamtam-quantity select option,' .
		'{{WRAPPER}} .elementor-menu-cart__product-price.product-price .quantity .vamtam-quantity > .vamtam-quantity-input,' .
		'{{WRAPPER}} .product-price .quantity .vamtam-quantity .vamtam-count-wrap > *';

	// Product Quantity Color.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'product_quantity_color', [
		'selectors' => [
			$qnty_selectors => '{{_RESET_}}' ],
		]
	);

	// Product Quantity Typography.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'product_quantity_typography', [
		'selectors' => [
			$qnty_selectors => '{{_RESET_}}'
		],
		],
		\Elementor\Group_Control_Typography::get_type()
	);

	// Products Divider Style.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'divider_style', [
		'selectors' => [
			'{{WRAPPER}}' => '--divider-style: {{VALUE}};',
		]
	] );

	// Products Divider Color.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'divider_color', [
		'selectors' => [
			'{{WRAPPER}}' => '--divider-color: {{VALUE}};',
		]
	] );

	// Products Divider Weight.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'divider_width', [
		'selectors' => [
			'{{WRAPPER}}' => '--divider-width: {{SIZE}}{{UNIT}};',
		]
	] );
}

function update_menu_icon_section_controls( $controls_manager, $widget ) {
	if ( vamtam_theme_supports( 'woocommerce-menu-cart--theme-cart-icon' ) ) {
		// Icon.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'icon', [
			'options' => [
				'vamtam-theme' => esc_html__( 'Theme Default', 'vamtam-elementor-integration' ),
			],
		] );
		\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'icon', [
			'default' => 'vamtam-theme',
		] );
	}

	// Hide Emtpy.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'hide_empty_indicator', [
		'condition' => null,
	] );
}

function add_controls_content_tab_section( $controls_manager, $widget ) {
	$widget->add_control(
		'hide_on_wc_cart_checkout',
		[
			'label' => __( 'Hide on Cart/Checkout', 'vamtam-elementor-integration' ),
			'description' => __( 'Hides the menu-card widget on WC\'s Cart & Checkout pages.', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::SWITCHER,
			'prefix_class' => 'vamtam-has-',
			'return_value' => 'hide-cart-checkout',
			'default' => 'hide-cart-checkout',
		]
	);
}

// Content - Menu Icon Section
function section_menu_icon_content_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	add_controls_content_tab_section( $controls_manager, $widget );
	update_menu_icon_section_controls( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-menu-cart/section_menu_icon_content/before_section_end', __NAMESPACE__ . '\section_menu_icon_content_before_section_end', 10, 2 );

function update_cart_section_controls( $controls_manager, $widget ) {
	if ( \VamtamElementorBridge::elementor_pro_is_v3_12_or_greater() ) {
		// Close Icon.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'close_cart_button_show', [
			'render_type' => 'template',
		] );

		// Custom Icon.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'close_cart_icon_svg', [
			'render_type' => 'template',
		] );
	}
}
// Content - Cart Section
function section_cart_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_cart_section_controls( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-menu-cart/section_cart/before_section_end', __NAMESPACE__ . '\section_cart_before_section_end', 10, 2 );

// Style - Products Section
function section_product_tabs_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_controls_style_tab_products_section( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-menu-cart/section_product_tabs_style/before_section_end', __NAMESPACE__ . '\section_product_tabs_style_before_section_end', 10, 2 );

function add_padding_control_for_footer_buttons( $controls_manager, $widget ) {
	// Btns Border Radius.
	$widget->start_injection( [
		'of' => 'button_border_radius',
	] );
	$selectors = [
		'{{WRAPPER}} .elementor-menu-cart__container .elementor-menu-cart__main .elementor-menu-cart__footer-buttons' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
	];
	if ( vamtam_theme_supports( 'woocommerce-menu-cart--fixed-mobile-cart-padding' ) ) {
		$selectors = [
			'{{WRAPPER}} .elementor-menu-cart__container .elementor-menu-cart__main .elementor-menu-cart__footer-buttons'          => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			'(tablet) {{WRAPPER}} .elementor-menu-cart__container .elementor-menu-cart__main .elementor-menu-cart__footer-buttons' => 'padding: {{TOP}}{{UNIT}} 30px {{BOTTOM}}{{UNIT}} 30px;',
			'(mobile) {{WRAPPER}} .elementor-menu-cart__container .elementor-menu-cart__main .elementor-menu-cart__footer-buttons' => 'padding: {{TOP}}{{UNIT}} 20px {{BOTTOM}}{{UNIT}} 20px;',
		];
	}
	$widget->add_responsive_control(
		'footer_buttons_padding',
		[
			'label' => __( 'Padding', 'vamtam-elementor-integration' ),
			'type' => $controls_manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'allowed_dimensions' => 'vertical',
			'default' => [
				'top' => 20,
				'bottom' => 20,
				'unit' => 'px',
				'isLinked' => true,
			],
			'selectors' => $selectors,
		]
	);
	$widget->end_injection();
}

function update_buttons_controls( $controls_manager, $widget ) {
	// View Cart Border.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'view_cart_border', [
		'selector' => '{{WRAPPER}} a.elementor-button.elementor-button--view-cart',
		],
		\Elementor\Group_Control_Border::get_type()
	);
	// Checkout Border.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'checkout_border', [
		'selector' => '{{WRAPPER}} a.elementor-button.elementor-button--checkout',
		],
		\Elementor\Group_Control_Border::get_type()
	);
	// View Cart Padding.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'view_cart_button_padding', [
		'selectors' => [
			'{{WRAPPER}} .elementor-menu-cart__footer-buttons .elementor-button--view-cart' => 'padding: var(--view-cart-button-padding);',
		]
	] );
	// Checkout Padding.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'view_checkout_button_padding', [
		'selectors' => [
			'{{WRAPPER}} .elementor-menu-cart__footer-buttons .elementor-button--checkout' => 'padding: var(--checkout-button-padding);',
		]
	] );
}

// Style - Buttons section
function section_style_buttons_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_buttons_controls( $controls_manager, $widget );
	add_padding_control_for_footer_buttons( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-menu-cart/section_style_buttons/before_section_end', __NAMESPACE__ . '\section_style_buttons_before_section_end', 10, 2 );

function update_controls_style_tab_cart_section( $controls_manager, $widget ) {
	// Subtotal Typography.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'subtotal_typography', [
		'selector' => '{{WRAPPER}}.elementor-widget-woocommerce-menu-cart .elementor-menu-cart__container .elementor-menu-cart__main .elementor-menu-cart__subtotal',
		],
		\Elementor\Group_Control_Typography::get_type()
	);
	// Subtotal Typography Font Weight.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'subtotal_typography_font_weight', [
		'selectors' => [
			'{{WRAPPER}} .elementor-menu-cart__subtotal strong' => '{{_RESET_}}',
		]
	] );
	// Subtotal Alignment.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'subtotal_alignment', [
		'prefix_class' => 'vamtam-subtotal-align-',
	] );
	// Remove Icon Size (for SVG).
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'remove_item_button_size', [
		'selectors' => [
			'{{WRAPPER}} .vamtam-remove-product svg' => 'font-size: var(--remove-item-button-size);width: 1em;height: 1em;',
		]
	] );
	// Remove Icon Color (for SVG).
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'remove_item_button_color', [
		'selectors' => [
			'{{WRAPPER}} .vamtam-remove-product svg' => 'color: var(--remove-item-button-color);fill: currentColor;stroke: currentColor;',
			'{{WRAPPER}} .vamtam-remove-product svg :is(g, path)' => 'color: inherit;fill: inherit;stroke: inherit;',
		]
	] );
	// Remove Icon Hover Color (for SVG).
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'remove_item_button_hover_color', [
		'selectors' => [
			'{{WRAPPER}} .vamtam-remove-product svg:hover' => 'color: var(--remove-item-button-hover-color);fill: currentColor;stroke: currentColor;',
			'{{WRAPPER}} .vamtam-remove-product svg:hover :is(g, path)' => 'color: inherit;fill: inherit;stroke: inherit;',
		]
	] );
}
// Style - Cart section
function section_cart_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_controls_style_tab_cart_section( $controls_manager, $widget );
}
add_action( 'elementor/element/woocommerce-menu-cart/section_cart_style/before_section_end', __NAMESPACE__ . '\section_cart_style_before_section_end', 10, 2 );

// Before render (all widgets).
function menu_cart_before_render( $widget ) {
    $widget_name = $widget->get_name();

    if ( $widget->get_name() === 'global' ) {
        $widget_name = $widget->get_original_element_instance()->get_name();
    }

    if ( 'woocommerce-menu-cart' === $widget_name ) {
		$hide_empty = ! empty( $widget->get_settings( 'hide_empty_indicator' ) );
        if ( $hide_empty && WC()->cart->get_cart_contents_count() === 0 ) {
			// Add hidden class to wrapper element.
			$widget->add_render_attribute( '_wrapper',  'class', 'hidden' );
		}
    }
}
add_action( 'elementor/frontend/widget/before_render', __NAMESPACE__ . '\menu_cart_before_render', 10, 1 );

/* WC Filters */

// Cart quantity override.
function vamtam_woocommerce_widget_cart_item_quantity( $content, $cart_item_key, $cart_item ) {
	if ( \VamtamElementorBridge::is_elementor_active() ) {
		// Elementor's filter has different args order.
		if ( ! isset( $cart_item['data'] ) && isset( $cart_item_key['data'] ) ) {
			$temp          = $cart_item_key;
			$cart_item_key = $cart_item;
			$cart_item     = $temp;
		}
	}
	$_product  = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
	$only_one_allowed  = $_product->is_sold_individually();

	$max_product_quantity = $_product->get_stock_quantity();
	if ( ! isset( $max_product_quantity ) ) {
		if ( $_product->get_max_purchase_quantity() === -1 ) {
			// For product that don't adhere to stock_quantity, provide a default max-quantity.
			// This will be used for the number of options inside the quantity <select>.
			$max_product_quantity = apply_filters( 'vamtam_cart_item_max_quantity', 10 );
		} else {
			$max_product_quantity = $_product->get_max_purchase_quantity();
		}
	}

    // Create number input for quantity
    $input = '<div class="vamtam-quantity"' . ( $only_one_allowed ? ' disabled ' : '' ) . '>';
    $input .= '<input type="number" ' .
              ( $only_one_allowed ? 'disabled ' : '' ) .
              'name="cart[' . esc_attr( $cart_item_key ) . '][qty]" ' .
              'value="' . esc_attr( $cart_item['quantity'] ) . '" ' .
              'title="' . esc_attr__( 'Qty', 'wpv' ) . '" ' .
              'min="0" ' .
              'max="' . esc_attr( $max_product_quantity ) . '" ' .
              'step="1" ' .
              'data-product_id="' . esc_attr( $cart_item['product_id'] ) . '" ' .
              'data-cart_item_key="' . esc_attr( $cart_item_key ) . '" ' .
              'class="vamtam-quantity-input" ' .
              '/>';
    $input .= '</div>';

	if ( vamtam_extra_features() ) {
		$patterns = [
			'/<span class="quantity"><span class="product-quantity">(\d+)/',
			'/<span class="quantity">(\d+)/'
		];

		$replacements = [
			'<span class="quantity">' . $input,
			'<span class="quantity">' . $input
		];

		$content = preg_replace($patterns, $replacements, $content, 1, $count);

		if ($count > 0) {
			$content = str_replace([' &times;</span>', ' &times; '], '', $content);
		}
	} else {
        $content = preg_replace( '#</div>#', $content, $input, 1 ) . '</div>';
	}

	return $content;
}
// Elementor menu cart widget, quantity override.
add_filter( 'woocommerce_widget_cart_item_quantity', __NAMESPACE__ . '\vamtam_woocommerce_widget_cart_item_quantity', 10, 3 );

/* Menu Cart - Ajax Actions */

// Remove product in the menu cart using ajax
function vamtam_ajax_menu_cart_product_remove() {
	if ( is_cart() ) {
		// It's cart page.
		return;
	}

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		if( $cart_item['product_id'] == $_POST['product_id'] && $cart_item_key == $_POST['cart_item_key'] ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		}
	}

	WC()->cart->calculate_totals();
	WC()->cart->maybe_set_cart_cookies();

	// Fragments and mini cart are returned
	\WC_AJAX::get_refreshed_fragments();
}
// Ajax hooks for product remove from menu cart.
add_action( 'wp_ajax_product_remove', __NAMESPACE__ . '\vamtam_ajax_menu_cart_product_remove' );
add_action( 'wp_ajax_nopriv_product_remove', __NAMESPACE__ . '\vamtam_ajax_menu_cart_product_remove' );

// Update product quantity from menu cart.
function vamtam_ajax_update_item_from_menu_cart() {
	if ( is_cart() ) {
		// It's cart page.
		return;
	}

	$quantity = (int) $_POST['product_quantity'];

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		if( $cart_item['product_id'] == $_POST['product_id'] && $cart_item_key == $_POST['cart_item_key'] ) {
			WC()->cart->set_quantity( $cart_item_key, $quantity );
		}
	}

	WC()->cart->calculate_totals();
	WC()->cart->maybe_set_cart_cookies();

	// Fragments and mini cart are returned
	\WC_AJAX::get_refreshed_fragments();
}
// Ajax hooks for updating product quantity from menu cart.
add_action('wp_ajax_update_item_from_cart', __NAMESPACE__ . '\vamtam_ajax_update_item_from_menu_cart');
add_action('wp_ajax_nopriv_update_item_from_cart', __NAMESPACE__ . '\vamtam_ajax_update_item_from_menu_cart');
