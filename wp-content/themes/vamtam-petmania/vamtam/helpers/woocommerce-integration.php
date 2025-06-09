<?php

/**
 * WooCommerce-related functions and filters
 *
 * @package vamtam/petmania
 */

if ( vamtam_has_woocommerce() || apply_filters( 'vamtam_force_dropdown_cart', false ) ) {
	/**
	 * Retrieve page ids - used for myaccount, edit_address, shop, cart, checkout, pay, view_order, terms. returns -1 if no page is found.
	 *
	 * @param string $page Page slug.
	 * @return int
	 */
	function vamtam_wc_get_page_id( $page ) {
		if ( 'pay' === $page || 'thanks' === $page ) {
			wc_deprecated_argument( __FUNCTION__, '2.1', 'The "pay" and "thanks" pages are no-longer used - an endpoint is added to the checkout instead. To get a valid link use the WC_Order::get_checkout_payment_url() or WC_Order::get_checkout_order_received_url() methods instead.' );

			$page = 'checkout';
		}
		if ( 'change_password' === $page || 'edit_address' === $page || 'lost_password' === $page ) {
			wc_deprecated_argument( __FUNCTION__, '2.1', 'The "change_password", "edit_address" and "lost_password" pages are no-longer used - an endpoint is added to the my-account instead. To get a valid link use the wc_customer_edit_account_url() function instead.' );

			$page = 'myaccount';
		}

		$page = apply_filters( 'woocommerce_get_' . $page . '_page_id', get_option( 'woocommerce_' . $page . '_page_id' ) );

		return $page ? absint( $page ) : -1;
	}

	/**
	 * Retrieve page permalink.
	 *
	 * @param string      $page page slug.
	 * @param string|bool $fallback Fallback URL if page is not set. Defaults to home URL. @since 3.4.0.
	 * @return string
	 */
	function vamtam_wc_get_page_permalink( $page, $fallback = null ) {
		$page_id   = vamtam_wc_get_page_id( $page );
		$permalink = 0 < $page_id ? get_permalink( $page_id ) : '';

		if ( ! $permalink ) {
			$permalink = is_null( $fallback ) ? get_home_url() : $fallback;
		}

		return apply_filters( 'woocommerce_get_' . $page . '_page_permalink', $permalink );
	}

	function vamtam_wc_get_cart_url() {
		return apply_filters( 'woocommerce_get_cart_url', vamtam_wc_get_page_permalink( 'cart' ) );
	}

	function vamtam_woocommerce_cart_dropdown() {
		get_template_part( 'templates/cart-dropdown' );
	}
	add_action( 'vamtam_header_cart', 'vamtam_woocommerce_cart_dropdown' );

	if ( ! vamtam_has_woocommerce() ) {
		// shim for the cart fragments script
		function vamtam_wc_cart_fragments_shim() {
			wp_localize_script( 'vamtam-all', 'wc_cart_fragments_params', [
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'wc_ajax_url'     => esc_url_raw( apply_filters( 'woocommerce_ajax_get_endpoint', add_query_arg( 'wc-ajax', '%%endpoint%%', remove_query_arg( array( 'remove_item', 'add-to-cart', 'added-to-cart', 'order_again', '_wpnonce' ), home_url( '/', 'relative' ) ) ), '%%endpoint%%' ) ),
				'cart_hash_key'   => apply_filters( 'woocommerce_cart_hash_key', 'wc_cart_hash_' . md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() ) ),
				'fragment_name'   => apply_filters( 'woocommerce_cart_fragment_name', 'wc_fragments_' . md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() ) ),
				'request_timeout' => 5000,
				'jspath'          => plugins_url( 'woocommerce/assets/js/frontend/cart-fragments.min.js' ),
				'csspath'         => plugins_url( 'woocommerce/assets/css/woocommerce.css' ),
			] );
		}
		add_action( 'wp_enqueue_scripts', 'vamtam_wc_cart_fragments_shim', 9999 );
	}
}

// TODO: Turn this into a class for better readability.

// Only use our pagination for fallback.
if ( ! vamtam_extra_features() ) {
	// We may want to add a separate master toggle for this.
	if ( ! function_exists( 'woocommerce_pagination' ) ) {
		// replace the default pagination with ours
		function woocommerce_pagination() {
			$query = null;

			$base = esc_url_raw( add_query_arg( 'product-page', '%#%', false ) );
			$format = '?product-page=%#%';

			if ( ! wc_get_loop_prop( 'is_shortcode' ) ) {
				$format = '';
				$base   = esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) );
			}

			if ( isset( $GLOBALS['woocommerce_loop'] ) ) {
				$query = (object)[
					'max_num_pages' => wc_get_loop_prop( 'total_pages' ),
					'query_vars'    => [
						'paged' => wc_get_loop_prop( 'current_page' )
					],
				];
			}

			echo VamtamTemplates::pagination_list( $query, $format, $base ); // xss ok
		}
	}
}

if ( vamtam_has_woocommerce() ) {
	// we have woocommerce

	remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );
	add_action( 'woocommerce_checkout_terms_and_conditions', 'vamtam_wc_terms_and_conditions_page_content', 30 );

	function vamtam_wc_terms_and_conditions_page_content() {
		$terms_page_id = wc_terms_and_conditions_page_id();

		if ( ! $terms_page_id ) {
			return;
		}

		$page = get_post( $terms_page_id );

		$content = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $page->post_content );

		if ( $page && 'publish' === $page->post_status && $page->post_content && ! has_shortcode( $page->post_content, 'woocommerce_checkout' ) ) {
			echo '<div class="woocommerce-terms-and-conditions" style="display: none; max-height: 200px; overflow: auto;">' . wc_format_content( wp_kses_post( $content ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	add_filter( 'wcml_load_multi_currency_in_ajax', '__return_true', 1000 );

	// Include WC theme funcionality based on site settings.
	function vamtam_check_include_wc_hooks() {
		// General purpose. (Always enabled).
		vamtam_wc_general_hooks();

		if ( vamtam_extra_features() ) {
			// Include WC hooks based on Theme Settings.
			if ( VamtamElementorBridge::is_elementor_active() && \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-loops-common-mods' ) ) {
				vamtam_wc_content_product_hooks();
				vamtam_wc_content_product_cat_hooks();
			}

			if ( VamtamElementorBridge::is_elementor_active() && \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-cart' ) ) {
				add_action( 'woocommerce_before_cart', 'vamtam_woocommerce_before_cart', 100 );
			}
		} else {
			// Fallback.
			vamtam_wc_content_product_hooks();
			vamtam_wc_content_product_cat_hooks();
		}

		if ( vamtam_extra_features() ) {
			if ( VamtamElementorBridge::is_elementor_active() && \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-cart' ) ) {
				add_action( 'woocommerce_before_cart', 'vamtam_woocommerce_before_cart', 100 );
			}
		}
	}

	if ( is_admin() ) {
		// Editor.
		add_action( 'init', 'vamtam_check_include_wc_hooks', 10 );
	} else {
		// Frontend.
		add_action( 'wp', 'vamtam_check_include_wc_hooks', 10 );
	}

	function vamtam_wc_content_product_hooks() {
		// Rating is rendered last inside product content.
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 10 );

		if ( ! function_exists( 'vamtam_woocommerce_before_shop_loop_item_title' ) ) {
			//WC products filtering
			function vamtam_woocommerce_before_shop_loop_item_title( $content ) {
				?>
					<?php do_action( 'vamtam_product_content_before_open' ); ?>
					<div class="vamtam-product-content">
					<?php do_action( 'vamtam_product_content_after_open' ); ?>
				<?php
			}
		}
		add_action( 'woocommerce_before_shop_loop_item_title', 'vamtam_woocommerce_before_shop_loop_item_title', 100 );

		if ( ! function_exists( 'vamtam_woocommerce_after_shop_loop_item_title' ) ) {
			//WC products filtering
			function vamtam_woocommerce_after_shop_loop_item_title( $content ) {
				?>
					<?php do_action( 'vamtam_product_content_before_close' ); ?>
					</div>
					<?php do_action( 'vamtam_product_content_after_close' ); ?>
				<?php
			}
		}
		add_action( 'woocommerce_after_shop_loop_item_title', 'vamtam_woocommerce_after_shop_loop_item_title', 10 );

		if ( ! function_exists( 'vamtam_woocommerce_loop_add_to_cart_link' ) ) {
			function vamtam_woocommerce_loop_add_to_cart_link( $content, $product, $args ) {
				// Hidden price.
				if ( vamtam_theme_supports( 'woocommerce-products--hide-price' ) ) {
					$hide_product_price = isset( $GLOBALS['vamtam_wc_products_hide_price'] ) && ! empty( $GLOBALS['vamtam_wc_products_hide_price'] );

					// If price is hidden, render the Read More btn.
					if ( $hide_product_price ) {
						$content = sprintf(
							'<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
							esc_url( $product->get_permalink() ),
							esc_attr( isset( $args['quantity'] ) ? $args['quantity'] : 1 ),
							esc_attr( 'button ' . 'product_type_' . $product->get_type() ),
							isset( $args['attributes'] ) ? wc_implode_html_attributes( $args['attributes'] ) : '',
							esc_html__( 'Read More', 'vamtam-petmania' )
						);
					}
				}

				// Add add_to_cart btn wrapper (products loop)
				$adc = '<div class="vamtam-add-to-cart-wrap">'
							. $content .
						'</div>';

				return apply_filters( 'vamtam_woocommerce_loop_add_to_cart_link', $adc );
			}
		}
		add_filter( 'woocommerce_loop_add_to_cart_link', 'vamtam_woocommerce_loop_add_to_cart_link', 10, 3 );

		if ( ! function_exists( 'vamtam_woocommerce_loop_add_to_cart_args' ) ) {
			function vamtam_woocommerce_loop_add_to_cart_args( $args, $product ) {
				if ( $product->get_type() === 'external' ) {
					$args['attributes']['target'] = '_blank';
				}

				return $args;
			}
		}

		add_filter( 'woocommerce_loop_add_to_cart_args', 'vamtam_woocommerce_loop_add_to_cart_args', 10, 2 );

		// Display title (in vamtam-product-content).
		remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
		add_action( 'vamtam_product_content_after_open', 'woocommerce_template_loop_product_title', 10 );

		// Price after title.
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		add_action( 'vamtam_product_content_before_close', 'woocommerce_template_loop_price', 102 );

		// Add to cart after product-content.
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		add_action( 'vamtam_product_content_after_close', 'woocommerce_template_loop_add_to_cart', 10 );

		// This fixes an issue with where previously removed wc actions will be added again
		// due to Elementor re-registering WC's hooks at a later stage [search: register_wc_hooks in elementor-pro]
		// for products-related widgets. It only affects the editor.
		if ( is_admin() ) {
			if ( ! function_exists( 'vamtam_woocommerce_before_shop_loop_item' ) ) {
				function vamtam_woocommerce_before_shop_loop_item() {
					remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
					remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
					remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
				}
			}
			add_action( 'woocommerce_before_shop_loop_item', 'vamtam_woocommerce_before_shop_loop_item' );
		}

		if ( ! function_exists( 'vamtam_close_outer_product_link' ) ) {
			// Fixes an issue with invalid markup caused by links inside vamtam-product-content interfering with the outer standard WC product link.
			function vamtam_close_outer_product_link() {
				if ( did_action( 'woocommerce_before_shop_loop_item' ) && has_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open' ) ) {
					// If the link was opened we close it here to avoid invalid html.

					// Close the link.
					woocommerce_template_loop_product_link_close();

					// We closed it ourshelves.
					remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
					do_action( 'vamtam_closed_outer_product_link' );
				}
			}
		}
		add_action( 'vamtam_product_content_before_open', 'vamtam_close_outer_product_link', 10 );
		if ( ! function_exists( 'vamtam_open_product_link_in_product_content' ) ) {
			function vamtam_open_product_link_in_product_content() {
				if ( did_action( 'vamtam_closed_outer_product_link' ) ) {
					// Open the link.
					woocommerce_template_loop_product_link_open();
					do_action( 'vamtam_opened_product_link_in_product_content' );
				}
			}
		}
		add_action( 'vamtam_product_content_after_open', 'vamtam_open_product_link_in_product_content', -10 );
		if ( ! function_exists( 'vamtam_close_product_link_in_product_content' ) ) {
			function vamtam_close_product_link_in_product_content() {
				if ( did_action( 'vamtam_opened_product_link_in_product_content' ) ) {
					// Close the link.
					woocommerce_template_loop_product_link_close();
					do_action( 'vamtam_closed_product_link_in_product_content' );
				}
			}
		}
		add_action( 'vamtam_product_content_before_close', 'vamtam_close_product_link_in_product_content', 100 );

		if ( ! function_exists( 'vamtam_products_ordering' ) ) {
			function vamtam_products_ordering() {
				if ( ! wc_get_loop_prop( 'is_paginated' ) || ! woocommerce_products_will_display() ) {
					return;
				}

				// Elementor doesnt allow products ordering on the front page.
				if ( is_front_page() ) {
					return;
				}

				$catalog_orderby_options = apply_filters( 'vamtam_products_filter_order_by', array(
					'menu_order' => __( 'Default sorting', 'vamtam-petmania' ),
					'popularity' => __( 'Sort by popularity', 'vamtam-petmania' ),
					'rating'     => __( 'Sort by average rating', 'vamtam-petmania' ),
					'date'       => __( 'Sort by latest', 'vamtam-petmania' ),
					'price'      => __( 'Sort by price: low to high', 'vamtam-petmania' ),
					'price-desc' => __( 'Sort by price: high to low', 'vamtam-petmania' ),
				) );

				$orderby    = ! empty( $_GET['orderby'] ) ? $_GET['orderby'] : '';
				$order_html = $order_current = '';
				foreach ( $catalog_orderby_options as $id => $name ) {
					$url       = '?paged=1&orderby=' . esc_attr( $id );
					$css_class = '';
					if ( $orderby == $id ) {
						$css_class     = 'active';
						$order_current = $name;
					}

					$order_html .= sprintf(
						'<li><a href="%s" class="woocommerce-ordering__link %s">%s</a></li>',
						esc_url( $url ),
						esc_attr( $css_class ),
						esc_html( $name )
					);
				}

				?>
				<div class="woocommerce-ordering">
					<span class="woocommerce-ordering__button">
						<span class="woocommerce-ordering__button-label">
							<?php
								echo ! empty( $orderby ) ? $order_current : esc_html__( 'Default', 'vamtam-petmania' )
							?>
						</span>
							<?php
								\Elementor\Icons_Manager::render_icon( [
									'value' => 'fas fa-chevron-down' ,
									'library' => 'fa-solid'
								], [ 'aria-hidden' => 'true' ] );
							?>
						</span>
					<ul class="woocommerce-ordering__submenu">
						<?php echo wp_kses_post( $order_html ); ?>
					</ul>
				</div>
				<?php
			}
		}

		if ( vamtam_extra_features() ) {
			remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
			add_action( 'woocommerce_before_shop_loop', 'vamtam_products_ordering', 30 );
		}
	}

	function vamtam_wc_check_include_content_product_cat_hooks( $widget_name ) {
		if ( \Vamtam_Elementor_Utils::is_widget_mod_active( $widget_name ) ) {
			vamtam_wc_content_product_cat_hooks();
		}
	}

	function vamtam_wc_content_product_cat_hooks() {
		if ( ! function_exists( 'vamtam_woocommerce_template_loop_category_title' ) ) {
			// WC product cats filtering.
			function vamtam_woocommerce_template_loop_category_title( $term ) {
				$class_description = get_term_meta( $term->term_id, 'class_description', true );
				?>
					<?php if ( ! empty( $class_description ) ) : ?>
						<p class="vamtam-product-cat-class-description">
							<?php echo esc_html( $class_description ); ?>
						</p>
					<?php endif; ?>
				<?php
			}
		}
		add_action( 'woocommerce_shop_loop_subcategory_title', 'vamtam_woocommerce_template_loop_category_title', 100 );

		if ( ! function_exists( 'vamtam_woocommerce_before_subcategory_title' ) ) {
			// WC product cats filtering.
			function vamtam_woocommerce_before_subcategory_title( $term ) {
				$class_level    = get_term_meta( $term->term_id, 'class_level', true );
				$class_category = get_term_meta( $term->term_id, 'class_category', true );
				?>
					<div class="vamtam-product-cat-thumb-wrap">
						<?php do_action( 'vamtam_cat_thumb_content', $term ); ?>
					</div>

					<?php if ( ! empty( $class_level ) || ! empty( $class_category ) ) : ?>
						<span class="vamtam-product-cat-info">
							<?php if ( ! empty( $class_level ) ) : ?>
								<span class="vamtam-product-cat-class-level"><?php echo esc_html( $class_level ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $class_category ) ) : ?>
								<span class="vamtam-product-cat-class-category"><?php echo esc_html( $class_category ); ?></span>
							<?php endif; ?>
						</span>
					<?php endif; ?>

					<div class="vamtam-product-cat-content">
					<?php do_action( 'vamtam_cat_content', $term ); ?>
				<?php
			}
		}
		add_action( 'woocommerce_before_subcategory_title', 'vamtam_woocommerce_before_subcategory_title', 100 );

		// Adding thumb in container so the hover anim can work properly.
		remove_action( 'woocommerce_before_subcategory_title', 'woocommerce_subcategory_thumbnail', 10 );
		add_action( 'vamtam_cat_thumb_content', 'woocommerce_subcategory_thumbnail', 10 );

		if ( ! function_exists( 'vamtam_woocommerce_after_subcategory_title' ) ) {
			// WC product cats filtering.
			function vamtam_woocommerce_after_subcategory_title( $term ) {
				?>
				<?php do_action( 'vamtam_cat_content_before_close' ); ?>
				</div>
				<?php
			}
		}
		add_action( 'woocommerce_after_subcategory_title', 'vamtam_woocommerce_after_subcategory_title', 100 );
	}

	function vamtam_woocommerce_before_cart() {
		echo '<h3>';
		esc_html_e( 'Your Cart', 'vamtam-petmania' );
		echo '</h3>';
	}

	function vamtam_wc_general_hooks() {
		// remove the WooCommerce breadcrumbs
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20,0 );

		// remove the WooCommerve sidebars
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

		function vamtam_woocommerce_body_class( $class ) {
			if ( is_cart() || is_checkout() || is_account_page() ) {
				$class[] = 'woocommerce';
			}

			return $class;
		}
		add_action( 'body_class', 'vamtam_woocommerce_body_class' );

		add_filter( 'woocommerce_product_description_heading', '__return_false' );
		add_filter( 'woocommerce_show_page_title', '__return_false' );

		if ( defined( 'WOOSW_VERSION' ) && ! function_exists( 'vamtam_print_woosw_button' ) ) {
			function vamtam_woosw_button_add_loading_class( $html ) {
				wp_enqueue_style( 'elementor-icons-fa-solid' );
				wp_enqueue_style( 'elementor-icons-fa-regular' );

				return str_replace( 'class="', 'class="vamtam-loading ', $html );
			}

			if ( ! wp_doing_ajax() ) {
				add_filter( 'woosw_button_html', 'vamtam_woosw_button_add_loading_class' );
			}

			function vamtam_print_woosw_buttons_ajax() {
				$result = [];

				foreach( $_POST['id'] as $id ) {
					$id = intval( $id );

					$result[ $id ] = do_shortcode( '[woosw id="' . $id . '"]' );
				}

				echo json_encode( $result );

				exit;
			}

			add_action( 'wp_ajax_nopriv_vamtam_get_woosw_buttons', 'vamtam_print_woosw_buttons_ajax' );
			add_action( 'wp_ajax_vamtam_get_woosw_buttons', 'vamtam_print_woosw_buttons_ajax' );

			function vamtam_print_woosw_button() {
				echo do_shortcode( '[woosw]' );
			}

			add_action( 'woocommerce_after_add_to_cart_button', 'vamtam_print_woosw_button' );

			function vamtam_paypal_button_widget_content( $widget_content, $widget ) {
				if ( 'paypal-button' === $widget->get_name() ) {
					return $widget_content . do_shortcode( '[woosw]' );
				}

				return $widget_content;

			}
			add_filter( 'elementor/widget/render_content', 'vamtam_paypal_button_widget_content', 10, 2 );

			// Confirm that all wishlist products exist
			add_filter( 'option_woosw_list_' . WPCleverWoosw::get_key(), 'vamtam_wishlist_fix' );
			function vamtam_wishlist_fix( $products ) {
				$option_name = 'woosw_list_' . WPCleverWoosw::get_key();

				if ( is_array( $products ) && count( $products ) > 0 ) {
					foreach ( $products as $product_id => $product_data ) {
						if ( get_post( $product_id ) === null ) {
							unset( $products[ $product_id ]);
						}
					}

					remove_filter( current_filter(), 'vamtam_wishlist_fix' );

					update_option( $option_name, $products );
				}

				return $products;
			}

		}

		function vamtam_woosw_after_items( $key, $products ) {
			if ( !! $products ) {
				echo '<div class="vamtam-empty-wishlist-notice">';
				echo '<div class="woosw-content-mid-notice">';

				$str = '';

				if ( ! empty( WPCleverWoosw::$localization[ 'empty_message' ] ) ) {
					$str = WPCleverWoosw::$localization[ 'empty_message' ];
				} else {
					$str = esc_html__( 'There are no products on the wishlist!', 'vamtam-petmania' );
				}

				echo apply_filters( 'woosw_localization_empty_message', $str );

				echo '</div>';
			}

			echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 146 160" width="146" class="vamtam-empty-wishlist-icon"><path d="M118.9 0a4.9 4.9 0 0 1 4.895 4.682l.005.218v110.91a.9.9 0 0 1-1.793.112l-.007-.113V4.9a3.1 3.1 0 0 0-2.924-3.095L118.9 1.8H4.9a3.1 3.1 0 0 0-3.095 2.924L1.8 4.9v136.045C1.8 150.363 9.431 158 18.841 158h.004l.1-.01.44-.063.507-.086c.495-.09 1.033-.203 1.604-.345 2.04-.505 4.078-1.254 5.973-2.288 5.355-2.922 8.531-7.49 8.531-14.165V135.9a2.9 2.9 0 0 1 2.9-2.9h104a2.9 2.9 0 0 1 2.9 2.9v5.143c0 10.36-8.465 18.757-18.9 18.757H18.841C8.55 159.8.184 151.54.003 141.284L0 140.945V4.9A4.9 4.9 0 0 1 4.682.005L4.9 0h114Zm24 134.8h-104a1.1 1.1 0 0 0-1.1 1.1v5.143c0 7.388-3.574 12.529-9.469 15.745-.743.405-1.503.77-2.276 1.094l-.292.118H126.9c9.336 0 16.924-7.422 17.097-16.635l.003-.322V135.9a1.1 1.1 0 0 0-1.1-1.1ZM94.9 93a.9.9 0 0 1 .113 1.793l-.113.007h-65a.9.9 0 0 1-.113-1.793L29.9 93h65Zm0-28a.9.9 0 0 1 .113 1.793l-.113.007h-65a.9.9 0 0 1-.113-1.793L29.9 65h65ZM62.587 27.434a7.661 7.661 0 0 1 7.606-2.167c3.318.897 5.618 3.886 5.607 7.294a7.442 7.442 0 0 1-2.494 5.553L63.041 48.532a.9.9 0 0 1-1.281 0L51.168 37.826a7.49 7.49 0 0 1-1.694-7.89c1.02-2.73 3.536-4.633 6.462-4.893a7.654 7.654 0 0 1 6.415 2.55l.046.054Zm7.136-.43c-2.536-.685-5.221.396-6.55 2.634a.9.9 0 0 1-1.546.002 5.837 5.837 0 0 0-5.532-2.805c-2.237.198-4.158 1.652-4.935 3.731a5.69 5.69 0 0 0 1.289 5.994l9.949 10.059 9.672-9.812a5.688 5.688 0 0 0 1.923-3.98l.007-.27c.008-2.591-1.743-4.868-4.277-5.553ZM93.9 36.5a.9.9 0 0 1 .113 1.793l-.113.007h-14a.9.9 0 0 1-.113-1.793l.113-.007h14Zm-50-1a.9.9 0 0 1 .113 1.793l-.113.007h-14a.9.9 0 0 1-.113-1.793l.113-.007h14Z" fill="#111" fill-rule="nonzero"/>
			';
			echo '<p class="vamtam-look-for-heart">';
			esc_html_e( 'Look for the heart to save favorites while you shop.', 'vamtam-petmania' );
			echo '</p>';
			echo '<form><button class="vamtam-start-shopping" formaction="' . esc_attr( get_permalink( wc_get_page_id( 'shop' ) ) ) . '">' . esc_html__( 'Start Shopping', 'vamtam-petmania' ) . '</button></form>';

			if ( !! $products ) {
				echo '</div>';
			}
		}

		add_action( 'woosw_after_items', 'vamtam_woosw_after_items', 10, 2 );

		if ( apply_filters( 'vamtam_woosw_use_defaults', true ) ) {
			$woosw_hardcoded = [
				'woosw_button_action' => 'no',
				'woosw_button_action_added' => 'page',
				'woosw_button_class' => '',
				'woosw_button_position_archive' => '0',
				'woosw_button_position_single' => '0',
				'woosw_button_text' => '',
				'woosw_button_text_added' => '',
				'woosw_button_type' => 'button',
				'woosw_color' => '#5fbd74',
				'woosw_continue_url' => '',
				'woosw_empty_button' => 'yes',
				'woosw_link' => 'yes',
				'woosw_menu_action' => 'open_page',
				'woosw_menus' => '',
				'woosw_page_copy' => 'no',
				'woosw_page_icon' => 'no',
				'woosw_page_items' => array (
					0 => 'facebook',
					1 => 'twitter',
					2 => 'pinterest',
					3 => 'mail',
				),
				'woosw_page_share' => 'no',
				'woosw_perfect_scrollbar' => 'yes',
				'woosw_show_note' => 'no',
			];

			foreach ( $woosw_hardcoded as $option_name => $option_value ) {
				add_filter( "option_{$option_name}", function ( $value, $option ) use ( $woosw_hardcoded ) {
					return $woosw_hardcoded[ $option ];
				}, 10, 2 );
			}
		}

		function vamtam_woocommerce_product_thumbnails() {
			wp_enqueue_script( 'vamtam-wc-gallery' );
		}
		add_action( 'woocommerce_product_thumbnails', 'vamtam_woocommerce_product_thumbnails' );

		// Cart quantity override.
		function vamtam_woocommerce_cart_item_quantity( $content, $cart_item_key, $cart_item ) {
			if ( VamtamElementorBridge::is_elementor_active() ) {
				// Elementor's filter has different args order.
				if ( ! isset( $cart_item['data'] ) && isset( $cart_item_key['data'] ) ) {
					$temp          = $cart_item_key;
					$cart_item_key = $cart_item;
					$cart_item     = $temp;
				}
			}

			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$disabled = $_product->is_sold_individually() ? ' disabled' : '';

			return str_replace( 'div class="quantity"', "div class=\"quantity vamtam-quantity{$disabled}\"", $content);
		}
		add_filter( 'woocommerce_cart_item_quantity', 'vamtam_woocommerce_cart_item_quantity', 10, 3 );

		add_filter( 'woocommerce_cart_item_quantity', 'vamtam_woocommerce_cart_item_quantity', 10, 3 );

		function vamtam_woocommerce_cart_item_remove_link( $content, $cart_item_key ) {
			$needle = '</a>'; // Default is for menu-cart.

			if ( is_cart() ) {
				$needle = '&times;</a>'; // Cart page, no menu-cart.
			}

			// Inject our close icon.
			$content = str_replace(
				$needle,
				'<i class="vamtam-remove-product"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 17 19" width="17px"><g fill="#BFBDBB" fill-rule="nonzero"><path d="M16.227 3.03h-5l-.433-1.87c-.086-.37-.394-.63-.749-.63h-3.09c-.355 0-.664.26-.75.632L5.771 3.03H.773c-.427 0-.773.373-.773.833v.834c0 .46.346.833.773.833h15.454c.427 0 .773-.373.773-.833v-.834c0-.46-.346-.833-.773-.833ZM15 6.53H3v9.75a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 15 16.28V6.53Z"/></g></svg></i></a>',
				$content );

			return $content;
		}
		add_filter( 'woocommerce_cart_item_remove_link', 'vamtam_woocommerce_cart_item_remove_link', 10, 2 );

		// WC Form Fields filtering (works in conjuction with vamtam_woocommerce_form_field())
		function vamtam_woocommerce_form_field_args( $args, $key, $value ) {
			if ( VamtamElementorBridge::is_elementor_active() ) {
				if ( $args['type'] === 'select' || $args['type'] === 'country' || $args['type'] === 'state' ) {
					$args['input_class'][] = 'elementor-field-textual';
					$args['class'][] = 'elementor-field-group';
				}
			}
			return $args;
		}
		add_filter( 'woocommerce_form_field_args', 'vamtam_woocommerce_form_field_args', 10, 3 );

		// WC Form Fields filtering (works in conjuction with vamtam_woocommerce_form_field_args())
		function vamtam_woocommerce_form_field( $field, $key, $args, $value ) {
			if ( VamtamElementorBridge::is_elementor_active() ) {
				if ( $args['type'] === 'select' || $args['type'] === 'country' || $args['type'] === 'state' ) {
					$field = str_replace( 'woocommerce-input-wrapper', 'woocommerce-input-wrapper elementor-select-wrapper', $field );
				}
			}
			return $field;
		}
		add_filter( 'woocommerce_form_field', 'vamtam_woocommerce_form_field', 10, 4 );

		// WC's smallscreen br aligned with site's mobile br.
		function vamtam_woocommerce_style_smallscreen_breakpoint( $px ) {
			$small_breakpoint = VamtamElementorBridge::get_site_breakpoints( 'md' );
			$new_br        = ( $small_breakpoint - 1 ) . 'px';
			return $new_br;
		}
		add_filter( 'woocommerce_style_smallscreen_breakpoint' ,'vamtam_woocommerce_style_smallscreen_breakpoint', 10, 1 );

		// Product "New" badge.
		if ( vamtam_extra_features() &&  VamtamElementorBridge::is_elementor_active() ) {
			if ( ! function_exists( 'vamtam_woocommerce_product_new_badge' ) ) {
				function vamtam_woocommerce_product_new_badge() {
					// Add field in the product's custom fields (advanced tab).
					function vamtam_woocommerce_product_custom_fields() {
						global $woocommerce, $post;
						echo '<div class="vamtam-wc-product-custom-field">';
						// Is New custom field.
						woocommerce_wp_checkbox(
							array(
								'id'          => '_vamtam_product_is_new',
								'label'       => __( 'New Product', 'vamtam-petmania' ),
								'description' => __( 'Products marked as new will display the "New" badge.', 'vamtam-petmania' ),
							)
						);
						echo '</div>';
					}
					add_action( 'woocommerce_product_options_advanced', 'vamtam_woocommerce_product_custom_fields' );

					// Save custom field's value in the db.
					function vamtam_woocommerce_product_custom_fields_save( $post_id ) {
						$_vamtam_product_is_new = $_POST['_vamtam_product_is_new'];
						update_post_meta( $post_id, '_vamtam_product_is_new', esc_attr( $_vamtam_product_is_new ) );
					}
					add_action( 'woocommerce_process_product_meta', 'vamtam_woocommerce_product_custom_fields_save' );

					// Display the new badge on product single/archive page.
					function vamtam_show_product_new_badge() {
						global $post, $product;
						$_vamtam_product_is_new = $product->get_meta( '_vamtam_product_is_new' );
						?>
						<?php if ( ! empty( $_vamtam_product_is_new ) ) : ?>

							<?php
								$classes =  'vamtam-new';
								if ( $product->is_on_sale() ) {
									$classes .= ' vamtam-onsale';
								}

								echo apply_filters( 'vamtam_wc_new_badge', '<span class="' . esc_attr( $classes ) .'">' . esc_html__( 'New!', 'vamtam-petmania' ) . '</span>', $post, $product ); ?>

							<?php
						endif;
					}
					// Loop.
					add_action( 'woocommerce_before_shop_loop_item_title', 'vamtam_show_product_new_badge', 9 );
					// Single.
					add_action( 'woocommerce_before_single_product_summary', 'vamtam_show_product_new_badge', 9 );
					// Single Elementor - For elementor template this is handled in product-images widget render().
					add_action( 'vamtam_display_product_new_badge', 'vamtam_show_product_new_badge', 10 );
				}
			}

			// Theme options (WC Mod).
			if ( \Vamtam_Elementor_Utils::is_wc_mod_active( 'wc_products_new_badge' ) ) {
				vamtam_woocommerce_product_new_badge();
			}

			if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'woocommerce-product-images' ) ) {
				function vamtam_woocommerce_single_product_carousel_options( $args ) {
					$args['animation'] = 'fade';
					return $args;
				}

				add_filter(	'woocommerce_single_product_carousel_options', 'vamtam_woocommerce_single_product_carousel_options' );
			}
		}
	}

	function vamtam_wc_single_product_ajax_hooks() {
		// Handles ajax add to cart calls.
		function woocommerce_ajax_add_to_cart() {
			new Vamtam_WC_Ajax_Add_To_Cart_Handler();
		}
		// Ajax hooks for add to cart on single products.
		add_action( 'wp_ajax_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart' );
		add_action( 'wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart' );


		class Vamtam_WC_Ajax_Add_To_Cart_Handler {
			protected static $product_id;
			protected static $quantity;

			public function __construct() {
				self::$product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
				self::$quantity   = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( $_POST['quantity'] );
				self::handle();
			}

			private static function handle() {
				$product_id        = self::$product_id;
				$quantity          = self::$quantity;
				$variation_id      = absint( $_POST['variation_id'] );
				$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
				$product_status    = get_post_status( $product_id );
				$is_valid          = $passed_validation && 'publish' === $product_status;
				$product_added     = false;

				// Don't manually add Bookables as they are already added by WC Bokkings.
				if ( $_POST['is_wc_booking'] && function_exists( 'is_wc_booking_product' ) ) {
					$product       = wc_get_product( $product_id );
					$product_added = is_wc_booking_product( $product );
					if ( $product_added ) {
						$is_valid = true;
					}
				}

				if ( ! $product_added ) {
					if ( isset( $_POST['is_grouped'] ) ) {
						// Grouped products.
						$product_added = self::handle_grouped_products();
					} elseif ( isset( $_POST['is_variable'] ) ) {
						// Variable products
						$product_added = self::handle_variable_products();
					} else {
						// Simple products
						// Add product to cart.
				if ( $is_valid ) {
					$product_added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
				}
					}
				}

				if ( $is_valid && $product_added ) {
					do_action( 'woocommerce_ajax_added_to_cart', $product_id );

					if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
						wc_add_to_cart_message( array( $product_id => $quantity ), true );

						// User has enabled redirect to cart on successful addition.
						if ( 0 === wc_notice_count( 'error' ) ) {
							$data = array(
								'redirect_to_cart' => true,
							);

							// Clear notices so they don't show up after redirect.
							wc_clear_notices();

							echo wp_send_json( $data );

							wp_die();
						}
					} else {
						// Adding the notice in the response so it can be outputted right away (woocommerce.js).
						add_filter( 'woocommerce_add_to_cart_fragments', [ __CLASS__, 'vamtam_woocommerce_add_to_cart_fragments' ] );
					}

					// Clear noticed so they don't show up on refresh.
					wc_clear_notices();

					WC_AJAX::get_refreshed_fragments();
				} else {
					$data = array(
						'error' => true,
						'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
						'notice' => '<div class="' . esc_attr( 'woocommerce-error vamtam-notice-error' ) . '" role="alert"><span class="vamtam-wc-msg">' . wc_kses_notice( end( wc_get_notices( 'error' ) )['notice'] ) . '</span><span class="vamtam-close-notice-btn" /></div>',
					);

					// Clear noticed so they don't show up on refresh.
					wc_clear_notices();

					echo wp_send_json( $data );
				}

				wp_die();
			}

			public static function handle_grouped_products() {
				$items             = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? $_POST['products'] : [];
				$added_to_cart     = [];
				$was_added_to_cart = false;

				if ( ! empty( $items ) ) {
					$quantity_set = false;

					foreach ( $items as $item => $quantity ) {
						if ( $quantity <= 0 ) {
							continue;
						}
						$quantity_set = true;

						// Add to cart validation.
						$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $item, $quantity );

						// Suppress total recalculation until finished.
						remove_action( 'woocommerce_add_to_cart', array( WC()->cart, 'calculate_totals' ), 20, 0 );

						if ( $passed_validation && false !== WC()->cart->add_to_cart( $item, $quantity ) ) {
							$was_added_to_cart      = true;
							$added_to_cart[ $item ] = $quantity;
						}

						add_action( 'woocommerce_add_to_cart', array( WC()->cart, 'calculate_totals' ), 20, 0 );
					}

					if ( ! $was_added_to_cart && ! $quantity_set ) {
						wc_add_notice( __( 'Please choose the quantity of items you wish to add to your cart.', 'vamtam-petmania' ), 'error' );
					} elseif ( $was_added_to_cart ) {
						WC()->cart->calculate_totals();
						return true;
					}
				}

				return false;
			}

			public static function handle_variable_products() {
				$product_id   = self::$product_id;
				$variation_id = empty( $_REQUEST['variation_id'] ) ? '' : absint( wp_unslash( $_REQUEST['variation_id'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$quantity     = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_REQUEST['quantity'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$variations   = array();

				$product      = wc_get_product( $product_id );

				foreach ( $_REQUEST as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( 'attribute_' !== substr( $key, 0, 10 ) ) {
						continue;
					}

					$variations[ sanitize_title( wp_unslash( $key ) ) ] = wp_unslash( $value );
				}

				$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

				if ( ! $passed_validation ) {
					return false;
				}

				// Prevent parent variable product from being added to cart.
				if ( empty( $variation_id ) && $product && $product->is_type( 'variable' ) ) {
					/* translators: 1: product link, 2: product name */
					wc_add_notice( sprintf( __( 'Please choose product options by visiting <a href="%1$s" title="%2$s">%2$s</a>.', 'vamtam-petmania' ), esc_url( get_permalink( $product_id ) ), esc_html( $product->get_name() ) ), 'error' );

					return false;
				}

				return WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
			}

			public static function vamtam_woocommerce_add_to_cart_fragments( $fragments ) {
				remove_filter( 'woocommerce_add_to_cart_fragments', [ __CLASS__, 'vamtam_woocommerce_add_to_cart_fragments' ] );
				$fragments['notice'] = '<div class="' . esc_attr( 'woocommerce-message' ) . '" role="alert"><span class="vamtam-wc-msg">' . wc_add_to_cart_message( array( self::$product_id => self::$quantity ), true, true ) . '</span><span class="vamtam-close-notice-btn" /></div>';
				return $fragments;
			}
		}
	}
	// Ajax hooks must be included early.
	if ( vamtam_extra_features() ) {
		vamtam_wc_single_product_ajax_hooks();
	}
}
