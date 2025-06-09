( function( $, v, undefined ) {
	'use strict';

	window.Cookies = window.Cookies || {
		get: function( name ) {
			var value = '; ' + document.cookie;
			var parts = value.split( '; ' + name + '=' );

			if ( parts.length === 2 ) {
				return parts.pop().split( ';' ).shift();
			}
		}
	};

	// DOMContentLoaded.
	$( function() {
		var dropdown        = $( '.fixed-header-box .cart-dropdown' ),
			link            = $( '.vamtam-cart-dropdown-link' ),
			count           = $( '.products', link ),
			$elementorCart  = $( '.elementor-widget-woocommerce-menu-cart' ),
			isElementorCart = $elementorCart.length,
			$itemsCount     = isElementorCart && $( $elementorCart ).find( '.vamtam-elementor-menu-cart__header .item-count' ),
			isCartPage      = 'wc_add_to_cart_params' in window && window.wc_add_to_cart_params.is_cart;


		function fixElementorWcCartConflicts() {
			if ( isCartPage ) {
				// Cart page
				var targets = document.querySelectorAll( '.woocommerce-cart-form__contents' );
				targets.forEach( function( target ) {
					var shouldRemoveClass = ! $( target ).hasClass( 'shop_table' ) && ! $( target ).parent().hasClass( 'vamtam-cart-main' );
					if ( shouldRemoveClass ) {
						// This class is used by WC. https://github.com/woocommerce/woocommerce/blob/master/assets/js/frontend/cart.js#L92
						// Elementor uses on their menu cart which causes problems.
						// So, if we are on the cart page and the class is not added by WC or us, remove it.
						$( target ).removeClass( 'woocommerce-cart-form__contents' );
					}
				});
			}
		}

		function triggerSideCart() {
			const toggleCartOpenBtns = $( '#elementor-menu-cart__toggle_button:visible' );
			$.each( toggleCartOpenBtns, function ( i, el ) {
				el.click();
			} );
		}

		var openCartHandle = function ( e ) {
			const toggleCartOpenBtns = $( '.elementor-widget-woocommerce-menu-cart.vamtam-has-theme-widget-styles .elementor-menu-cart__toggle_button' );
			let toggleClicked = false;

			$.each( toggleCartOpenBtns, function ( i, el ) {
				if ( $( el ).is( e.target ) || $( el ).has( e.target ).length ) {
					toggleClicked = true;
					return false; // break.
				}
			} );

			if ( ! toggleClicked ) {
				/*
					We only care about clicks on the toggle button.

					We do manual target detection because we cant use the on() method to attach
					the event cause we need capture phase.
				*/
				return;
			}

			e.preventDefault();
			if ( isCartPage ) {
				// Don't do anything on cart page.
				e.stopImmediatePropagation();
				return false;
			} else {
				if ( window.VAMTAM.isMobileBrowser ) {
					// Redirect to cart page.
					e.stopImmediatePropagation();
					window.location = window.wc_add_to_cart_params.cart_url;
					return false;
				}

				// Disable page scroll.
				$( 'body' ).addClass( 'vamtam-disable-scroll' );
				// Hide stt
				$( '#scroll-to-top' ).addClass( 'hidden' );
				// Furthest section from target inside header.
				var closestTopSection = $( e.target ).closest( '.elementor-top-section' );
				// Raise z-index cause sometimes card gets hidden by other elements.
				closestTopSection.css( 'z-index', '1000' );
			}
		};

		var closeCartHandle = function ( e, cartIsEmpty ) {
			var targetIsWrapperOrCloseBtn = $( e.target ).is( '.elementor-menu-cart__container, .vamtam-close-cart, .e-close-cart-custom-icon' );
			var lastItemRemoved           = e === 'no-target' && cartIsEmpty;
			if ( targetIsWrapperOrCloseBtn || lastItemRemoved ) {
				// Enable page scroll.
				$( 'body' ).removeClass( 'vamtam-disable-scroll' );
				// Show stt.
				$( '#scroll-to-top' ).removeClass( 'hidden' );
				// Unset z-index
				$( e.target ).closest( 'section.elementor-element' ).css( 'z-index', '' );
			}
		};

		// Registers the handlers that toggle the scroll to top.
		function bindOpenCloseMenuCartHandlers() {
			// Menu cart open btn (Elementor)
			const toggleCartOpenBtns =  document.querySelectorAll( '.elementor-widget-woocommerce-menu-cart.vamtam-has-theme-widget-styles .elementor-menu-cart__toggle_wrapper' );
			toggleCartOpenBtns.forEach( function( el ) {
				el.removeEventListener( 'click', openCartHandle );
				el.addEventListener( 'click', openCartHandle, true );
			});

			// Menu cart wrap/close btn (Elementor)
			const toggleCartCloseBtns =  document.querySelectorAll( '.elementor-widget-woocommerce-menu-cart.vamtam-has-theme-widget-styles .elementor-menu-cart__container .elementor-menu-cart__close-button, .elementor-widget-woocommerce-menu-cart.vamtam-has-theme-widget-styles .elementor-menu-cart__container' );
			toggleCartCloseBtns.forEach( function( el ) {
				el.removeEventListener( 'click', closeCartHandle );
				el.addEventListener( 'click', closeCartHandle );
			} );
		}
		bindOpenCloseMenuCartHandlers();

		function moveScrollToTop( reset ) {
			const $stt = $('#scroll-to-top.vamtam-scroll-to-top');
			const $wcMsg = $('.woocommerce-notices-wrapper .woocommerce-message');
			if ( $stt.length ) {
				if ( reset ) {
					$stt.css( 'bottom', '10px' );
				} else {
					if ( $wcMsg.length ) {
						$stt.css('bottom', $wcMsg.outerHeight() + 10 + 'px');
					} else {
						$stt.css( 'bottom', '95px' );
					}
				}
			}
		}

		// "vamtam_elementor_menu_cart_fragments" == "added_to_cart" + "removed_from_cart" + "wc_fragments_refreshed" + "wc_fragments_loaded"
		// We also add "wc_fragments_loaded" on "vamtam_elementor_menu_cart_fragments" for caching reasons.
		const menuCartEvents = isElementorCart ? 'vamtam_elementor_menu_cart_fragments wc_fragments_loaded' : 'added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded';
		$( document.body ).on( menuCartEvents, function() {
			var count_val = parseInt( Cookies.get( 'woocommerce_items_in_cart' ) || 0, 10 );
			if ( count_val > 0 ) {
				if ( isElementorCart ) {
					$elementorCart.removeClass( 'hidden' );
					var itemsInCart = $elementorCart[ 0 ].querySelectorAll( '.cart_item .quantity select, .cart_item .quantity .vamtam-quantity-input' ),
						getFromTextContent = false,
						bubbleIconEls = $elementorCart.find( '#elementor-menu-cart__toggle_button .elementor-button-icon-qty' );

					if ( ! itemsInCart.length ) {
						itemsInCart = $elementorCart[ 0 ].querySelectorAll( '.cart_item .product-quantity' );
						getFromTextContent = true;
					}

					var total = 0;
					itemsInCart.forEach( function( item ) {
						const val = getFromTextContent ? item.textContent : item.value;
						total += parseInt( val, 10 );
					} );

					$itemsCount.text( '(' + total + ')' );

					$.each( bubbleIconEls, function( index, el ) {
						const bubbleIconVal = parseInt( $( el ).attr( 'data-counter' ), 10 );
						// Mismatch between products total and cart bubble icon - Can happen due to caching.
						if ( total !== bubbleIconVal ) {
							$( el ).attr( 'data-counter', total );
						}
					} );
				} else {
					var count_real = 0;

					var widgetShoppingCart = document.querySelector( '.widget_shopping_cart' ),
						spans              = widgetShoppingCart ? widgetShoppingCart.querySelectorAll( 'li .quantity' ) : [];

					if ( widgetShoppingCart ) {
						for ( var i = 0; i < spans.length; i++ ) {
							count_real += parseInt( spans[i].innerHTML.split( '<span' )[0].replace( /[^\d]/g, '' ), 10 );
						}

						// sanitize count_real - if it's not a number, then don't show the counter at all
						count_real = count_real >= 0 ? count_real : '';

						count.text( count_real );
						count.removeClass( 'cart-empty' );
						dropdown.removeClass( 'hidden' );
					}
				}
			} else {
				if ( isElementorCart ) {
					var hideEmpty = $elementorCart.hasClass( 'elementor-menu-cart--empty-indicator-hide' );
					$elementorCart.toggleClass( 'hidden', hideEmpty );
					$itemsCount.text( '(0)' );
					closeCartHandle( 'no-target', true );
				} else {
					var show_if_empty = dropdown.hasClass( 'show-if-empty' );

					count.addClass( 'cart-empty' );
					count.text( '0' );

					dropdown.toggleClass( 'hidden', ! show_if_empty );
				}
			}

			document.body.classList.toggle( 'vamtam-wc-cart-empty', count_val === 0 );

			// Move the scroll to top so it's not on top of checkout message (single product).
			var isSingleProduct = $('body').hasClass('single-product');
			var checkoutMessageExists = isSingleProduct ? $('.woocommerce-notices-wrapper .woocommerce-message').length : false;
			if ( checkoutMessageExists ) {
				moveScrollToTop();
			}

			bindOpenCloseMenuCartHandlers();
			fixElementorWcCartConflicts();
		} );

		function injectWCNotice( notice ) {
			if ( notice ) {
				// Append notice.
				$( '.woocommerce-notices-wrapper').empty().append( notice );
				// Remove notice btn handler.
				var $closeNoticeBtn = $( '.woocommerce-notices-wrapper' ).find( '.vamtam-close-notice-btn' );

				if ( ! $closeNoticeBtn.length ) {
					return;
				}

				$closeNoticeBtn[ 0 ].addEventListener( 'click', function () {
					var $msg = $( this ).closest( '.woocommerce-message' );
					$msg.fadeOut( 'fast' );
					moveScrollToTop( true );
					setTimeout( function() {
						$msg.remove();
					}, 2000 );
				} );
				// Remove notice after 10s.
				setTimeout( function() {
					var $msg = $closeNoticeBtn.closest( '.woocommerce-message' );
					$msg.fadeOut( 'fast' );
					setTimeout( function() {
						$msg.remove();
						moveScrollToTop( true );
					}, 2000 );
				}, 1000 * 10 );
			}
		}

		function moveCheckoutErrorNotices() {
			const isCheckout     = $( document.body ).hasClass( 'woocommerce-checkout' ),
				  $checkout_form = isCheckout && $( 'form.checkout' ),
				  $dest          = isCheckout && $( '.woocommerce > .woocommerce-notices-wrapper' ).first();

			if ( ! isCheckout || ! $checkout_form.length || ! $dest.length ) {
				return;
			}

			const onCheckoutError = function () {
				const noticeGroup = $checkout_form.find( '.woocommerce-NoticeGroup.woocommerce-NoticeGroup-checkout' );
				$dest.append( noticeGroup );
			};

			$( document.body ).on( 'checkout_error', onCheckoutError );
		}

		// Products with stock management that have a single last unit available.
		function lastStockQtyFix() {
			if ( $( 'body' ).hasClass( 'single-product' ) && ! $( '.sold-individually' ).length ) {
				const $stock = $( '.stock.in-stock' );
				const qty = $( 'input[name="quantity"][max="1"][min="1"]' );
				if ( $stock.length &&  qty.length) {
					$stock.addClass( 'last-one' );
				}
			}
		}
		lastStockQtyFix();

		// Apply coupon (standard cart).
		$( document ).on( 'click', '.woocommerce-cart button[name="apply_coupon"]:not(".e-apply-coupon"):visible', function( e ) {
			e.preventDefault();
			// This is a proxy btn, trigger the sumbit which is inside the wc-cart-form.
			const $applyCouponSubmit = $( 'input[type="submit"][name="apply_coupon"]' );
			$applyCouponSubmit.trigger( 'click' );
		});

		if ( ! document.body.classList.contains( 'vamtam-limited-layout' ) ) {
			// Add to cart ajax
			$( document ).on( 'click', '.single_add_to_cart_button, .products.vamtam-wc.table-layout .add_to_cart_button:not(.product_type_variable)', function( e ) {
				// Collect product data.
				var $thisbutton     = $( this ),
					$form           = $thisbutton.closest( 'form.cart' ),
					id              = $thisbutton.val(),
					product_qty     = $form.find( 'input[name=quantity]' ).val() || 1,
					product_id      = $form.find( 'input[name=product_id]' ).val() || id,
					variation_id    = $form.find( 'input[name=variation_id]' ).val() || 0,
					isVariable      = variation_id,
					isBookable      = $form.find( 'input[name=add-to-cart].wc-booking-product-id' ).val(),
					isGrouped       = $form.hasClass( 'grouped_form' ),
					isExternal      = $form.parent( '.elementor-product-external').length && $form.attr('method') === 'get',
					isTableLayout   = $thisbutton.closest( '.products.vamtam-wc.table-layout' ).length,
					hasExtraOptions = $form.find( '.thwepo-extra-options' ).length, // Plugin: Extra Product Options (Product Addons) for WooCommerce.
					products        = {};

				// Check if theme AJAX is disabled by widget option (single-product).
				const disableThemeHandler = $thisbutton.parents( '.elementor-widget-woocommerce-product-add-to-cart.vamtam-has-disable-theme-ajax-atc' ).length;
				if ( disableThemeHandler ) {
					return;
				}

				// Don't submit the form.
				e.preventDefault();

				// External product.
				if ( isExternal ) {
					// Open the external link in a new tab instead.
					window.open( $form.attr( 'action' ),'_blank' );
					return;
				}

				// Grouped products
				if ( isGrouped ) {
					product_id = parseInt( $form.find( 'input[name=add-to-cart]' ).val() );
					var $products  = $form.find( '[id^="product-"]' );

					$.each( $products, function( index, product ) {
						var addToCartBtn = $( product ).find( '.add_to_cart_button' );
						var p_id = $( product ).attr( 'id' ).substr( 8 ), // the "product-" part.
							p_qty;

						if ( addToCartBtn.length ) {
							p_qty = parseInt( addToCartBtn.attr( 'data-quantity' ) ) || 0;
						} else {
							p_qty = parseInt( $( product ).find( 'input.qty' ).val() ) || 0;
						}

						products[ p_id ] = p_qty;
					} );
				}

				// Table Layout.
				if ( isTableLayout ) {
					// For table-layout (product archives) we have to consider WC's option for enabling AJAX on archives.
					if ( window.VAMTAM_FRONT.enable_ajax_add_to_cart === 'yes' ) {
						const $row = $thisbutton.closest( 'tr.vamtam-product' );
						if ( $row.length ) {
							product_qty = $row.find( 'input[name=quantity]' ).val() || 1;
							product_id  = $thisbutton.attr( 'data-product_id' ) || id;
						}
					}
					else {
						$form.submit();
						return;
					}
				}

				if ( ! window.wc_add_to_cart_params ) {
					return; // No ajax_url
				}

				// Format post data.
				var data = {};
				if ( isBookable ) {
					// Channel bookables through our woocommerce_ajax_add_to_cart so there's
					// a single endpoint for all "add to cart" actions.
					const fData = new FormData( $form[ 0 ] );
					fData.forEach( function( value, key ){
						// We need to generate those fields to pass woocommerce_add_to_cart_validation
						// since we are not posting the form directly to wc_bookings.
						if ( key === 'add-to-cart' ) {
							data.product_id = value;
						} else {
							data[ key.replace( 'wc_bookings_field', '' ) ] = value;
						}

						// if key contains "[]" it should be treated as an array.
						if ( key.includes( '[]') ) {
							data[ key ] = fData.getAll( key );
						} else {
							data[ key ] = value;
						}
					});
					data.is_wc_booking = true;
				} else if ( isGrouped ) {
					// Grouped product
					data = {
						product_id: product_id,
						products: products,
						is_grouped: true,
					};

					if ( hasExtraOptions ) {
						// With extra options from Extra Product Options plugin.

						// Send all fields.
						const fData = new FormData( $form[ 0 ] );
						fData.forEach( function( value, key ){
							if ( key !== 'add-to-cart' ) {
								// the "add-to-cart: id" pair triggers WC's WC_Form_Handler::add_to_cart_action()
								// and the product ends up being added twice to the cart.
								data[ key ] = value;
							}
						});
					}
				} else if ( isVariable ) {
					// Variable product
					data = {
						product_id: product_id,
						is_variable: true,
					};
					// Send all fields.
					const fData = new FormData( $form[ 0 ] );
					fData.forEach( function( value, key ) {
						if ( key === 'add-to-cart' ) {
							// the "add-to-cart: id" pair triggers WC's WC_Form_Handler::add_to_cart_action()
							// and the product ends up being added twice to the cart.
							data.product_id = value;
						} else {
							// if key contains "[]" it should be treated as an array.
							if ( key.includes( '[]') ) {
								data[ key ] = fData.getAll( key );
							} else {
								data[ key ] = value;
							}
						}
					} );
				} else {
					// Simple product
					data = {
						product_id: product_id,
					};

					// Send all fields.
					const fData = new FormData( $form[ 0 ] );
					fData.forEach( function( value, key ) {
						if ( key === 'add-to-cart' ) {
							// the "add-to-cart: id" pair triggers WC's WC_Form_Handler::add_to_cart_action()
							// and the product ends up being added twice to the cart.
							if ( ! data.product_id ) {
								data.product_id = value;
							}
						} else {
							// if key contains "[]" it should be treated as an array.
							if ( key.includes( '[]') ) {
								data[ key ] = fData.getAll( key );
							} else {
								data[ key ] = value;
							}
						}
					} );
				}

				// Common fields.
				data.product_sku  = '';
				data.quantity     = product_qty;
				data.variation_id = variation_id;
				data.action       = 'woocommerce_ajax_add_to_cart';

				// Triger adding_to_cart event (theme/plugins might wanna use it).
				$( document.body ).trigger( 'adding_to_cart', [$thisbutton, data] );

				// Perform Ajax.
				$.ajax({
					type: 'post',
					url: window.wc_add_to_cart_params.ajax_url,
					data: data,
					beforeSend: function () {
						$thisbutton.removeClass( 'added' ).addClass( 'loading' );
					},
					complete: function ( response ) {
						if ( response.error ) {
							$thisbutton.removeClass( 'loading' );
						} else {
							$thisbutton.addClass( 'added' ).removeClass( 'loading' );
						}
					},
					success: function ( response ) {
						if ( response.error ) {
							// Inject wc notice if there's one.
							injectWCNotice( response.notice );
							$( document.body ).trigger( 'wc_fragments_refreshed' );
						} else {
							// Successful addition
							if ( response.redirect_to_cart ) {
								// User has enabled redirect to cart on successful addition.
								// Redirect to cart page, don't do anything else.
								window.location = window.wc_add_to_cart_params.cart_url;
								return;
							}

							if ( isElementorCart ) {
								if ( isTableLayout ) {
									const shouldTriggerSideCart = ! window.VAMTAM.isMobileBrowser && $thisbutton.parents( '.vamtam-has-adc-triggers-menu-cart[data-widget_type="woocommerce-products.products_table_layout"]' ).length;
									if ( shouldTriggerSideCart ) {
										setTimeout(() => {
											triggerSideCart();
										}, 500);
									}
								} else {
									const shouldTriggerSideCart = ! window.VAMTAM.isMobileBrowser;
									if ( shouldTriggerSideCart ) {
										setTimeout(() => {
											triggerSideCart();
										}, 500);
									}
								}
							} else {
								// Inject wc notice if there's one.
								injectWCNotice( response.fragments.notice );
							}
							$( document.body ).trigger( 'added_to_cart', [response.fragments, response.cart_hash, $thisbutton] );
						}
					},
				});

				return false;
			});

			// Ajax delete product in the menu cart.
			$( document ).on( 'click', '.woocommerce-mini-cart .woocommerce-cart-form__cart-item .product-remove > a', function () {
				// We just add a fade but let WC handle the actual removal.
				const product_container = $( this ).parents('.woocommerce-cart-form__cart-item' );
				product_container.css( {
					'pointer-events': 'none',
					'transition': 'opacity .3s ease',
					'opacity': '0.5',
				} );
			} );

			if ( window.wc_add_to_cart_params ) {
				// Ajax delete product in the menu cart.
				$( document ).on( 'click', '.mini_cart_item a.remove, .woocommerce-mini-cart .woocommerce-cart-form__cart-item .product-remove > a:not([class])', function ( e ) {
					// Don't refresh.
					e.preventDefault();

					// Collect product data.
					var $thisbutton       = $( this ),
						product_id        = $( this ).attr( 'data-product_id' ),
						cart_item_key     = $( this ).attr( 'data-cart_item_key' ),
						product_container = $( this ).parents('.mini_cart_item, .woocommerce-cart-form__cart-item' );

					// Perform Ajax.
					$.ajax({
						type: 'post',
						dataType: 'json',
						url: window.wc_add_to_cart_params.ajax_url,
						data: {
							action: 'product_remove',
							product_id: product_id,
							cart_item_key: cart_item_key
						},
						beforeSend: function () {
							product_container.css( 'pointer-events', 'none' ).css( 'opacity', '0.5' );
							$( 'body' ).css( 'cursor', 'wait' );
						},
						complete: function () {
							$( 'body' ).css( 'cursor', 'default' );
						},
						success: function( response ) {
							if ( ! response || ! response.fragments ) {
								window.location = $thisbutton.attr( 'href' );
								return;
							}
							$( document.body ).trigger( 'removed_from_cart', [ response.fragments, response.cart_hash, $thisbutton ] );
						},
						error: function() {
							window.location = $thisbutton.attr( 'href' );
							return;
						},
					});
				});
			}

			// Ajax update product quantity from cart (menu/standard).
		const cartQtySelector = '.woocommerce-cart-form__cart-item .vamtam-quantity';
		$( document ).on( 'change', `${cartQtySelector} select, ${cartQtySelector} input`, v.debounce( function ( e ) {
				e.preventDefault();

				// Collect data.
				var isStandardCard    = $( '.woocommerce-cart' ).length,
					product_quantity  = $( this ).val(),
					product_id        = $( this ).attr( 'data-product_id' ),
					cart_item_key     = $( this ).attr( 'data-cart_item_key' ),
					product_container = $( this ).parents('.mini_cart_item, .woocommerce-cart-form__cart-item' );

				if ( isStandardCard ) {
					var $updateCardBtn = $( 'input[type="submit"][name="update_cart"]' );
					$updateCardBtn.prop( 'disabled', false );
					$updateCardBtn.trigger( 'click' );
					return;
				}

				if ( ! window.wc_add_to_cart_params ) {
					return; // No ajax_url
				}

				// Perform Ajax.
				$.ajax({
					type: 'post',
					dataType: 'json',
					url: window.wc_add_to_cart_params.ajax_url,
					data: {
						action : 'update_item_from_cart',
						'product_id' : product_id,
						'cart_item_key' : cart_item_key,
						'product_quantity' : product_quantity,
					},
					beforeSend: function () {
						product_container.css( 'pointer-events', 'none' ).css( 'opacity', '0.5' );
						$( 'body' ).css( 'cursor', 'wait' );
					},
					complete: function () {
						product_container.css( 'pointer-events', 'auto' ).css( 'opacity', '1' );
						$( 'body' ).css( 'cursor', 'default' );
					},
					success: function( response ) {
						if ( ! response || ! response.fragments ) {
							return;
						}
						$( document.body ).trigger( 'wc_fragment_refresh' );
					},
					error: function() {
						return;
					}
				});
			} ) );
		}

		window.addEventListener('load',function(){
			moveCheckoutErrorNotices();
			if ( isElementorCart ) {
				bindOpenCloseMenuCartHandlers();
				fixElementorWcCartConflicts();
			}
		} );

		function onElementorMenuCartFragmentsUpdated() {
			/*
				They have replaced WC's cart-fragments ajax handling but have not provided any way of reacting to their changes.
				We do this ourselves here.
			*/
			$( document ).ajaxComplete( ( event, xhr, settings ) => {
				const response = xhr.responseJSON,
					responseOk = response && response.fragments,
					actionOK   = ( settings.data &&  settings.data.indexOf( 'action=elementor_menu_cart_fragments' ) !== -1 );

				if ( responseOk && actionOK ) {
					$( document.body ).trigger( 'vamtam_elementor_menu_cart_fragments' );
				}
			} );
		}

		if ( isElementorCart ) {
			onElementorMenuCartFragmentsUpdated();
		}
	} );

	function wooswBtnFix() {
		// reload woosw buttons - used when single product pages are cached

		const body = new URLSearchParams({ action: 'vamtam_get_woosw_buttons' });

		let buttons_by_id = {};
		document.querySelectorAll( '.woosw-btn' ).forEach( button => {
			let id = parseInt( button.dataset.id, 10 );

			body.append( 'id[]', id );
			buttons_by_id [ id ] = button;
		} );

		fetch( VAMTAM_FRONT.ajaxurl, {
			method: 'POST',
			body
		} )
		.then( response => response.json() )
		.then( buttons => {
			for ( let id in buttons ) {
				buttons_by_id[ id ].outerHTML = buttons[ id ];
			}
		} );
	}

	document.addEventListener('DOMContentLoaded', function() {
		wooswBtnFix();
	});
} )( jQuery, window.VAMTAM );
