/**
 * Often used vanilla js functions, so that we don't need
 * to use all of underscore/jQuery
 */
(function( undefined ) {
	"use strict";

	var v = ( window.VAMTAM = window.VAMTAM || {} ); // Namespace

	// Returns a function, that, as long as it continues to be invoked, will not
	// be triggered. The function will be called after it stops being called for
	// N milliseconds. If `immediate` is passed, trigger the function on the
	// leading edge, instead of the trailing.
	v.debounce = function( func, wait = 300, immediate = false ) {
		var timeout;
		return function() {
			var context = this, args = arguments;
			var later = function() {
				timeout = null;
				if ( ! immediate ) func.apply( context, args );
			};
			var callNow = immediate && ! timeout;
			clearTimeout( timeout );
			timeout = setTimeout( later, wait );
			if ( callNow ) func.apply( context, args );
		};
	};

	// vanilla jQuery.fn.offset() replacement
	// @see https://plainjs.com/javascript/styles/get-the-position-of-an-element-relative-to-the-document-24/

	v.offset = function( el ) {
		var rect = el.getBoundingClientRect(),
		scrollLeft = window.pageXOffset || document.documentElement.scrollLeft,
		scrollTop = window.pageYOffset || document.documentElement.scrollTop;
		return { top: rect.top + scrollTop, left: rect.left + scrollLeft };
	};

	// Faster scroll-based animations

	v.scroll_handlers = [];
	v.latestKnownScrollY = 0;

	var ticking = false;

	v.addScrollHandler = function( handler ) {
		requestAnimationFrame( function() {
			handler.init();
			v.scroll_handlers.push( handler );

			handler.measure( v.latestKnownScrollY );
			handler.mutate( v.latestKnownScrollY );
		} );
	};

	v.onScroll = function() {
		v.latestKnownScrollY = window.pageYOffset;

		if ( ! ticking ) {
			ticking = true;

			requestAnimationFrame( function() {
				var i;

				for ( i = 0; i < v.scroll_handlers.length; i++ ) {
					v.scroll_handlers[i].measure( v.latestKnownScrollY );
				}

				for ( i = 0; i < v.scroll_handlers.length; i++ ) {
					v.scroll_handlers[i].mutate( v.latestKnownScrollY );
				}

				ticking = false;
			} );
		}
	};

	window.addEventListener( 'scroll', v.onScroll, { passive: true } );

	// Load an async script
	v.load_script = function( src, callback ) {
		var s = document.createElement('script');
		s.type = 'text/javascript';
		s.async = true;
		s.src = src;

		if ( callback ) {
			s.onload = callback;
		}

		document.getElementsByTagName('script')[0].before( s );
	};

	v.load_style = function( href, media, callback, after ) {
		var l = document.createElement('link');
		l.rel = 'stylesheet';
		l.type = 'text/css';
		l.media = media;
		l.href = href;

		if ( callback ) {
			l.onload = callback;
		}

		if ( after ) {
			after.after( l );
		} else {
			document.getElementsByTagName('link')[0].before( l );
		}
	};

	// Checks if current window size is inside the below-max breakpoint range.
	v.isBelowMaxDeviceWidth = function () {
		return ! window.matchMedia( '(min-width: ' + VAMTAM_FRONT.max_breakpoint + 'px)' ).matches;
	};

	// Checks if current window size is inside the max breakpoint range.
	v.isMaxDeviceWidth = function () {
		return window.matchMedia( '(min-width: ' + VAMTAM_FRONT.max_breakpoint + 'px)' ).matches;
	};

	// Checks if current window size is inside the max breakpoint range.
	v.isMediumDeviceOrWider = function () {
		return window.matchMedia( '(min-width: ' + VAMTAM_FRONT.medium_breakpoint + 'px)' ).matches;
	};

	// Checks if current window size is below the medium breakpoint.
	v.isSmallDeviceWidth = function () {
		return window.matchMedia( '(max-width: ' + (VAMTAM_FRONT.medium_breakpoint - 1) + 'px)' ).matches;
	};

	v.isMobileBrowser = /Android|webOS|iPhone|iPad|iPod|BlackBerry|Windows Phone/.test( navigator.userAgent ) || ( /Macintosh/.test( navigator.userAgent ) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2 );

	v.getScrollbarWidth = () => window.innerWidth - document.documentElement.clientWidth;

	let windowLoaded = false;

	v.waitForLoad = function( callback ) {
		if ( windowLoaded ) {
			callback();
		} else {
			window.addEventListener( 'load', callback );
		}
	};

	window.addEventListener('load', function () {
		windowLoaded = true;
	} );
})();

(function($, v, undefined) {
	"use strict";

	var mainHeader      = $('header.main-header');
	var header_contents = mainHeader.find( '.header-contents' );
	var menu_toggle     = document.getElementById( 'vamtam-fallback-main-menu-toggle' );
	var original_toggle = document.querySelector( '#main-menu > .mega-menu-wrap > .mega-menu-toggle' );

	// scrolling below

	var smoothScrollTimer, smoothScrollCallback;

	var smoothScrollListener = function() {
		clearTimeout( smoothScrollTimer );

		smoothScrollTimer = setTimeout( scrollToElComplete, 200 );
	};

	var scrollToElComplete = function() {
		window.removeEventListener( 'scroll', smoothScrollListener, { passive: true } );
		v.blockStickyHeaderAnimation = false;

		if ( smoothScrollCallback ) {
			smoothScrollCallback();
		}
	};

	var scrollToEl = function( el, duration, callback ) {
		requestAnimationFrame( function() {
			var el_offset = el.offset().top;

			v.blockStickyHeaderAnimation = true;

			// measure header height
			var header_height = 0;
			header_height = header_contents.height() || 0;


			var scroll_position = el_offset - v.adminBarHeight - header_height;

			smoothScrollCallback = callback;

			window.addEventListener( 'scroll', smoothScrollListener, { passive: true } );

			window.scroll( { left: 0, top: scroll_position, behavior: 'smooth' } );

			if ( el.attr( 'id' ) ) {
				if ( history.pushState ) {
					history.pushState( null, null, '#' + el.attr( 'id' ) );
				} else {
					window.location.hash = el.attr( 'id' );
				}
			}

			menu_toggle && menu_toggle.classList.remove( 'mega-menu-open' );
			original_toggle && original_toggle.classList.remove( 'mega-menu-open' );
		} );
	};

	$( document.body ).on('click', '.vamtam-animated-page-scroll[href], .vamtam-animated-page-scroll [href], .vamtam-animated-page-scroll [data-href]', function(e) {
		var href = $( this ).prop( 'href' ) || $( this ).data( 'href' );
		var el   = $( '#' + ( href ).split( "#" )[1] );

		var l  = document.createElement('a');
		l.href = href;

		if(el.length && l.pathname === window.location.pathname) {
			menu_toggle && menu_toggle.classList.remove( 'mega-menu-open' );
			original_toggle && original_toggle.classList.remove( 'mega-menu-open' );

			scrollToEl( el );
			e.preventDefault();
		}
	});

	if ( window.location.hash !== "" &&
		(
			$( '.vamtam-animated-page-scroll[href*="' + window.location.hash + '"]' ).length ||
			$( '.vamtam-animated-page-scroll [href*="' + window.location.hash + '"]').length ||
			$( '.vamtam-animated-page-scroll [data-href*="'+window.location.hash+'"]' ).length
		)
	) {
		var el = $( window.location.hash );

		if ( el.length > 0 ) {
			$( window ).add( 'html, body, #page' ).scrollTop( 0 );
		}

		setTimeout( function() {
			scrollToEl( el );
		}, 400 );
	}

	document.addEventListener('DOMContentLoaded', function() {
		if ( 'elementorFrontend' in window && ! window.elementorFrontend.isEditMode() ) {
			let elements = document.querySelectorAll('.vamtam-menu-click-on-hover a');
			let header = document.querySelector( '.elementor-location-header' );

			let timeout_prevent_close, timeout_prevent_open;

			// used as an interlock to prevent flickering when the pointer returns to the original menu item
			let currently_open = false;

			// used to prevent a situation where the popup is not shown following a second hover,
			// because timeout_prevent_open was cleared before the popup was opened
			let open_from = null;

			const closeMenu = function() {
				header.style.zIndex = undefined;
				header.style.position = undefined;

				if ( currently_open ) {
					$( document.body ).click();
				}

				open_from = null;
				currently_open = false;
			};

			$( document.body ).on( 'mouseenter', '.dialog-widget-content', function() {
				clearTimeout( timeout_prevent_close );
			} );

			$( document.body ).on( 'mouseleave', '.dialog-widget-content', function() {
				timeout_prevent_close = setTimeout( closeMenu, 500 );
			} );

			$( document.body ).on( 'mouseenter', '.elementor-nav-menu > .menu-item-has-children, .vamtam-menu-click-on-hover', function( e ) {
				if ( e.target !== open_from ) {
					clearTimeout( timeout_prevent_close );
					clearTimeout( timeout_prevent_open );

					closeMenu();
				}
			} );

			elements.forEach(function( el ) {
				el.addEventListener('mouseenter', function( ev ) {
					ev.preventDefault();
					ev.stopPropagation();

					if ( open_from !== el && ! currently_open ) {
						open_from = el;

						timeout_prevent_open = setTimeout( () => {
							header.style.zIndex = 9999;
							header.style.position = 'relative';

							currently_open = true;

							$( el ).click();
						}, 200 );
					} else {
						clearTimeout( timeout_prevent_close );
					}
				});

				el.addEventListener('mouseleave', function() {
					clearTimeout( timeout_prevent_open );

					if ( currently_open ) {
						timeout_prevent_close = setTimeout( closeMenu, 500 );
					} else {
						open_from = null;
						currently_open = false;
					}
				});

				el.innerHTML += '<span class="sub-arrow"><i class="fas fa-chevron-down"></i></span>';
			});
		}
	});
})( jQuery, window.VAMTAM );

/* jshint multistr:true */
(function( $, undefined ) {
	"use strict";

	window.VAMTAM = window.VAMTAM || {}; // Namespace

	$(function () {
		window.VAMTAM.adminBarHeight = document.body.classList.contains( 'admin-bar' ) ? 32 : 0;

		if ( /iPad|iPhone|iPod/.test( navigator.userAgent ) && ! window.MSStream) {
			requestAnimationFrame( function() {
				document.documentElement.classList.add( 'ios-safari' );
			} );
		}

		if ( navigator.userAgent.includes( 'Safari' ) && ! navigator.userAgent.includes( 'Chrome' ) ) {
			requestAnimationFrame( function() {
				document.documentElement.classList.add( 'safari' );
			} );
		}

		// prevent hover when scrolling
		(function() {
			var wrapper = document.body,
				timer;

			window.addEventListener( 'scroll', function() {
				clearTimeout(timer);

				requestAnimationFrame( function() {
					wrapper.classList.add( 'disable-hover' );

					timer = setTimeout( function() {
						wrapper.classList.remove( 'disable-hover' );
					}, 300 );
				} );
			}, { passive:true } );
		})();

		// print trigger

		document.addEventListener( 'click', function( e ) {
			if ( e.target.closest( '.vamtam-trigger-print' ) ) {
				window.print();
				e.preventDefault();
			}
		} );

		// Code which depends on the window width
		// =====================================================================

		window.VAMTAM.resizeElements = function() {
			// video size
			$('#page .media-inner,\
				.wp-block-embed-vimeo:not(.wp-has-aspect-ratio),\
				:not(.wp-block-embed__wrapper) > .vamtam-video-frame').find('iframe, object, embed, video').each(function() {

				setTimeout( function() {
					requestAnimationFrame( function() {
						var v_width = this.offsetWidth;

						this.style.width = '100%';

						if ( this.width === '0' && this.height === '0' ) {
							this.style.height = ( v_width * 9/16 ) + 'px';
						} else {
							this.style.height = ( this.height * v_width / this.width ) + 'px';
						}

						$( this ).trigger('vamtam-video-resized');
					}.bind( this ) );
				}.bind( this ), 50 );
			});

			setTimeout( function() {
				requestAnimationFrame( function() {
					$('.mejs-time-rail').css('width', '-=1px');
				} );
			}, 100 );
		};

		window.addEventListener( 'resize', window.VAMTAM.debounce( window.VAMTAM.resizeElements, 100 ), false );
		window.VAMTAM.resizeElements();

		$( document ).ajaxSuccess(function( event, xhr, settings ) {
			const args = settings.data
			                   .split( '&' )
			                   .map( pair => pair.split( '=' ) )
			                   .reduce( (prev, curr) => { prev[ curr[0] ] = curr[1]; return prev; }, {} );

			if ( args.action === 'wishlist_remove' ) {
				const response = JSON.parse( xhr.responseText );

				if ( response.status === 1 && response.count === 0 ) {
					$( '.vamtam-empty-wishlist-notice' ).show();
					$( 'table.woosw-items' ).hide();
				}
			}
		});
	} );

	// Handles various overlay types.
	/*
		How it works:
			-keep track of all overlays (vamtam-overlay-trigger class).
			-keep track of all overlays state (active or not).
			-bind the relevant click handlers (custom for each overlay type).
			-attach a doc click handler which figures out which overlays to close or not.
			-if we switch breakpoint (below-max / max) we close all overlays to avoid conflicts.

		*Every overlay type will be a bit different so it will prob need
		some custom coding (defining the overlay's targets, close triggers).
	*/
	var vamtamOverlaysHandler = function () {
		var elsThatCauseOverlay    = document.querySelectorAll( '.vamtam-overlay-trigger' );
		var elsThatCauseOverlayArr = [];
		var prevIsBelowMax         = window.VAMTAM.isBelowMaxDeviceWidth();

		var triggerCloseHandlers = function () {
			elsThatCauseOverlayArr.forEach( function( el ) {
				if ( el.isActive ) {
					el.closeTrigger.click();
				}
			} );
		};

		var overlaysResizeHandler = function () {
			var isBelowMax = window.VAMTAM.isBelowMaxDeviceWidth();
			if ( prevIsBelowMax !== isBelowMax) {
				// We changed breakpoint (max/below-max).
				// Close all overlays.
				triggerCloseHandlers();
				prevIsBelowMax = isBelowMax;
			}
		};

		var overlayCloseHandler = function ( target ) {
			// Is this an elementor menu overlay?
			if ( $( target ).hasClass( 'elementor-menu-toggle' ) ) {
				// Button is a toggle (on/off).
				target.removeEventListener( 'click', onOverlayCloseClick );
				target.addEventListener( 'click', onOverlayCloseClick );
				return;
			}
			// Add other type of overlays here.
		};

		var onOverlayCloseClick = function ( e ) {
			// Is this an elementor menu overlay?
			var target = e.currentTarget;
			if ( $( target ).hasClass( 'elementor-menu-toggle' ) ) {
				var elRow = $( target ).closest( '.elementor-row' );
				// Elementor >= v3.0.
				if ( ! elRow.length ) {
					// v3.0 removed the .elementor-row element.
					elRow = $( target ).closest( '.elementor-container' );
				}

				if ( elRow.hasClass( 'vamtam-overlay-trigger--overlay' ) ) {
					// We need to remove the overlay
					elRow.removeClass( 'vamtam-overlay-trigger--overlay' );
					target.removeEventListener( 'click', onOverlayCloseClick ); // cause it's a toggle.
					elsThatCauseOverlayArr.forEach( function( e ) {
						if ( e.overlayTarget === target || e.closeTrigger === target ) {
							e.isActive = false;
						}
					});
				}
			}
			// Add other type of overlays here.

			var activeOverlays = $( '.vamtam-overlay-trigger--overlay .vamtam-overlay-element:visible' );
			if ( activeOverlays.length < 2 ) { // If there are other active overlays, don't activate scrollers/stt.
				// Enable page scroll.
				$( 'html, body' ).removeClass( 'vamtam-disable-scroll' );
				// Show stt.
				$( '#scroll-to-top' ).removeClass( 'hidden' );
			}
		};

		var onOverlayTriggerClick = function ( e ) {
			var target = e.currentTarget;
			// Is this an elementor menu overlay?
			if ( $( target ).hasClass( 'elementor-menu-toggle' ) ) {
				var elRow = $( target ).closest( '.elementor-row' );
				// Elementor >= v3.0.
				if ( ! elRow.length ) {
					// v3.0 removed the .elementor-row element.
					elRow = $( target ).closest( '.elementor-container' );
				}

				// This is for moving the overlay underneath the main-menu.
				if ( ! elRow.hasClass( 'vamtam-menu-nav-overlay-inside' ) ) {
					elRow.addClass( 'vamtam-menu-nav-overlay-inside' );
					$( elRow ).find( '.vamtam-overlay-element' ).css( 'top', ( $( elRow )[ 0 ].getBoundingClientRect().top + $( elRow ).height() ) + 'px' );
				}

				if ( ! elRow.hasClass( 'vamtam-overlay-trigger--overlay' ) ) {
					// We need to add the overlay class
					elRow.addClass( 'vamtam-overlay-trigger--overlay' );
					elsThatCauseOverlayArr.forEach( function( e ) {
						if ( e.overlayTarget === target || e.closeTrigger === target ) {
							e.isActive = true;
						}
					});
				} else {
					// This is a close instruction, let onOverlayCloseClick() handle it.
					return;
				}
			}
			// Add other type of overlays here.

			// Disable page scroll.
			$( 'html, body' ).addClass( 'vamtam-disable-scroll' );
			// Hide stt
			$( '#scroll-to-top' ).addClass( 'hidden' );

			// Register the overlay close handler
			overlayCloseHandler( target );
		};

		elsThatCauseOverlay.forEach( function ( el ) {
			// Is this an elementor menu overlay?
			if ( $( el ).hasClass( 'elementor-widget-nav-menu' ) ) {
				// Get menu toggle.
				var menuToggle = $( el ).find( '.elementor-menu-toggle' )[ 0 ];

				// The click listener should be on menu toggle for nav menus.
				menuToggle.removeEventListener( 'click', onOverlayTriggerClick );
				menuToggle.addEventListener( 'click', onOverlayTriggerClick );

				elsThatCauseOverlayArr.push( {
					overlayTarget: el, // The el that holds the vamtam-overlay-trigger class.
					closeTrigger: menuToggle, // The el that closes the overlay.
					isActive: false // If the overlay is active or not.
				} );

				// Add the overlay el.
				var elRow = $( el ).closest( '.elementor-row' );
				// Elementor >= v3.0.
				if ( ! elRow.length ) {
					// v3.0 removed the .elementor-row element.
					elRow = $( el ).closest( '.elementor-container' );
				}
				$( '<span class="vamtam-overlay-element"></span>' ).appendTo( elRow );

				return;
			}
			// Add other type of overlays here.
		} );

		if ( elsThatCauseOverlay.length ) {
			var docClickHandler = function ( e ) {
				elsThatCauseOverlayArr.forEach( function( el ) {
					if ( ! el.isActive ) {
						return;
					}
					// If a click happened,
					// and there is an active overlay,
					// and the click target isn't the overlay target or an element inside it,
					// then call the overlay close handler.
					if ( e.target !== el.overlayTarget && ! el.overlayTarget.contains( e.target ) ) {
						el.closeTrigger.click();
					}
				} );
			};

			document.addEventListener( 'click', docClickHandler, true ); // we need capture phase here.
			window.addEventListener( 'resize', window.VAMTAM.debounce( overlaysResizeHandler, 200 ), false );
		}
	};

	const addScrollbarWidthCSSProp = () => {
		jQuery( 'html' ).css( '--vamtam-scrollbar-width', window.VAMTAM.getScrollbarWidth() + 'px' );
	};

	// Low priority scripts are loaded later
	document.addEventListener('DOMContentLoaded', function () {
		window.VAMTAM.load_script( VAMTAM_FRONT.jspath + 'low-priority.js' );

		vamtamOverlaysHandler();

		addScrollbarWidthCSSProp();
	}, { passive: true } );

})(jQuery);

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

( function( $, undefined ) {
	"use strict";

	window.VAMTAM = window.VAMTAM || {}; // Namespace
	window.VAMTAM.CUSTOM_ANIMATIONS = {};

	window.VAMTAM.CUSTOM_ANIMATIONS = {
		init: function () {
			// DOM is not ready yet.
		},
		onDomReady: function () {
			this.VamtamCustomAnimations.init();
			this.VamtamCustomAnimations.scrollBasedAnims();
		},
		// Handles custom animations.
		VamtamCustomAnimations: {
			init: function() {
				this.registerAnimations();
				this.utils.watchScrollDirection();
				// this.observedAnims(); // Disabled in favor of elementorFrontend.waypoint().
			},
			registerAnimations: function () {
				var self = this;

				// Register animations here.
				var animations = [
					'stickyHeader', // Same name as function.
				];

				animations.forEach( function( animation ) {
					self[ animation ].apply( self );
				} );
			},
			// A sticky header animation.
			stickyHeader: function () {
				var $target                = $( '.vamtam-sticky-header' ),
					topScrollOffsetTrigger = 10,
					_self                  = this;

				if ( ! $target.length ) {
					return;
				}

				if ( $target.length > 1 ) {
					// There should only be one sticky header.
					$target = $target[ 0 ];
				}

				( function () { // IIFE for closure so $target is available in rAF.
					var prevAnimState,
						isTransparentHeader   = $( $target ).hasClass( 'vamtam-sticky-header--transparent-header' ),
						stickyHeight          = $( $target ).innerHeight();


					// state: fixed, scrolled up (not visible).
					var fixedHiddenState = function () {
						$( $target ).removeClass( 'vamtam-sticky-header--fixed-shown' );
						if ( ! $( $target ).hasClass( 'vamtam-sticky-header--fixed-hidden' ) ) {
							$( $target ).addClass( 'vamtam-sticky-header--fixed-hidden' );
						}
						prevAnimState = 'fixedHiddenState';
					};

					// state: fixed, scrolled down (visible).
					var fixedShownState = function () {
						$( $target ).removeClass( 'vamtam-sticky-header--fixed-hidden' );
						if ( ! $( $target ).hasClass( 'vamtam-sticky-header--fixed-shown' ) ) {
							$( $target ).addClass( 'vamtam-sticky-header--fixed-shown' );
						}
						prevAnimState = 'fixedShownState';
					};

					// state: no animation.
					var noAnimState = function () {
						$( $target ).removeClass( 'vamtam-sticky-header--fixed-shown' );
						$( $target ).removeClass( 'vamtam-sticky-header--fixed-hidden' );
						prevAnimState = 'noAnimState';
					};

					// body-padding normalization.
					const checkBodyPadding = () => {
						const leftBodyPadding  = $( 'body' ).css( 'padding-left' ),
							rightBodyPadding   = $( 'body' ).css( 'padding-right' ),
							$headerEl           = $( $target ).parents( '[data-elementor-type="header"]').first();

						if ( ! $headerEl.length ) {
							return;
						}

						// any body-padding we negate with negative margin.
						// we apply it on the headerEl cause margins on sticky header mess up the width calc.
						if ( parseInt( leftBodyPadding ) ) {
							$headerEl.css( '--vamtam-sticky-mleft', `-${leftBodyPadding}` );
						}
						if ( parseInt( rightBodyPadding ) ) {
							$headerEl.css( '--vamtam-sticky-mright', `-${rightBodyPadding}` );
						}
					};
					checkBodyPadding();

					const headerShouldAnimate = () => {
						// If a link inside the header is being hovered, we don't want to trigger the sticky header.
						if ( $( $target ).find( 'a:hover' ).length ) {
							return false;
						}
						// If a mega-menu belonging to the header is open, we don't want to trigger the sticky header.
						if ( $( '.vamtam-header-mega-menu:visible' ).length ) {
							return false;
						}

						return true;
					};

					// Initial phase

					// If passed the trigger point it should always be at fixed hidden state.
					const initialScrollPosCheck = ( pageLoad = false ) => {
						if ( window.pageYOffset >= topScrollOffsetTrigger ) {
							fixedHiddenState();
						} else if ( ! pageLoad ) {
							// Sometimes the browser's onload scroll comes after the initialScrollPosCheck() so we check on page load jic. Happens mostly when initial scroll pos is after middle of page.
							window.addEventListener( 'load', function() {
								if ( ! prevAnimState ) {
										setTimeout( () => {
											initialScrollPosCheck( true );
										}, 10 );
								}
							} );
						}
					};
					initialScrollPosCheck();

					var scrollTimer = null, lastScrollYPause = window.scrollY, lastDirection; // Used to check if the user has scrolled up far enough to trigger the sticky header.
					window.addEventListener( 'scroll', function( e ) {
						if ( scrollTimer !== null ) {
							clearTimeout( scrollTimer );
						}

						// If the user hasn't scrolled for 500ms we use that as the new Y point.
						scrollTimer = setTimeout( function() {
							lastScrollYPause = window.scrollY;
						}, 500 );

						var anim = window.VAMTAM.debounce( function() {
							if ( e.target.nodeName === '#document' ) {

									if ( ! headerShouldAnimate() ) {

										// Don't animate, but go to fixedShown state for the transparent header.
										if ( isTransparentHeader ) {
											if ( prevAnimState !== 'fixedShownState' ) {
												fixedShownState();
											}
										}

										return;
									}

								var direction =  _self.utils.getScrollDirection();

								if ( lastDirection !== direction ) {
									lastScrollYPause = window.scrollY;
								}
								lastDirection = direction;

								const scrollDifference = Math.abs( window.scrollY - lastScrollYPause ); // Pixels.
								if ( window.scrollY > stickyHeight && scrollDifference < 20 ) {
									return;
								}

								if ( direction === 'up' ) {
									if ( window.pageYOffset >= topScrollOffsetTrigger ) {
										if ( prevAnimState !== 'fixedShownState' ) {
											fixedShownState();
										}
									} else {
										if ( prevAnimState !== 'noAnimState' ) {
											noAnimState();
										}
									}
									return;
								}

								if ( direction === 'down' ) {
									if ( window.pageYOffset >= topScrollOffsetTrigger || isTransparentHeader ) { // Transparent header gets hidden right away.
										// Safe-guard for times when the opening of the cart can cause a scroll down and hide the menu (also sliding the cart upwards).
										var menuCardNotVisible = ! $( $target ).find( '.elementor-menu-cart--shown' ).length;
										if ( prevAnimState !== 'fixedHiddenState' && menuCardNotVisible ) {
											fixedHiddenState();
										}
									}
								}
							}
						}, 25 );

						if ( window.VAMTAM.isMaxDeviceWidth() ) {
							requestAnimationFrame( anim );
						} else if ( prevAnimState !== 'noAnimState' ) {
							noAnimState();
						}
					}, { passive: true } );
				} )();
			},
			// Scroll-based anims.
			scrollBasedAnims: function() {
				var scrollAnims = [
					'[data-settings*="growFromLeftScroll"]',
					'[data-settings*="growFromRightScroll"]',
				];

				var animEls = document.querySelectorAll( scrollAnims.join( ', ' ) );

				if ( ! animEls.length ) {
					return;
				}

				var observer, entries = {}, _this = this;

				var cb = function( iOEntries ) {
					iOEntries.forEach( function( entry ) {
						var currentScrollY       = entry.boundingClientRect.y,
							isInViewport         = entry.isIntersecting,
							observedEl           = entry.target,
							scrollPercentage     = Math.abs( parseFloat( ( entry.intersectionRatio * 100 ).toFixed( 2 ) ) ),
							prevScrollPercentage = entries[ observedEl.dataset.vamtam_anim_id ].lastScrollPercentage,
							lastScrollY          = entries[ observedEl.dataset.vamtam_anim_id ].lastScrollY,
							animateEl            = entries[ observedEl.dataset.vamtam_anim_id ].animateEl;

						var animate = function () {
							window.requestAnimationFrame( function() {
								animateEl.style.setProperty( '--vamtam-scroll-ratio', scrollPercentage + '%' );
							} );
						};

						if ( isInViewport && lastScrollY !== currentScrollY ) {
							if( _this.utils.getScrollDirection() === 'down') {
								if ( prevScrollPercentage < scrollPercentage ) {
									animate();
								}
							} else {
								animate();
							}
						}

						entries[ observedEl.dataset.vamtam_anim_id ].lastScrollY          = currentScrollY;
						entries[ observedEl.dataset.vamtam_anim_id ].lastScrollPercentage = scrollPercentage;
					} );
				};

				var buildThresholdList = function() {
					var thresholds = [],
						numSteps   = 50,
						i;

					for ( i = 1.0; i <= numSteps; i++ ) {
						var ratio = i / numSteps;
						thresholds.push( ratio );
					}

					thresholds.push( 0 );
					return thresholds;
				};

				const thresholds = buildThresholdList();

				animEls.forEach( function( el ) {
					if ( ! observer ) {
						var options = {
							root: null,
							rootMargin: "20% 0% 20% 0%",
							threshold: thresholds,
						};
						observer = new IntersectionObserver( cb, options );
					}

					// Init.
					el.style.setProperty( '--vamtam-scroll-ratio', '1%' );

					var observeEl;
					if ( el.classList.contains( 'elementor-widget' ) || el.classList.contains( 'elementor-column' ) ) {
						// For widgets we observe .elementor-widget-wrap
						// For columns we observe .elementor-row
						observeEl = el.parentElement;
						observeEl.setAttribute('data-vamtam_anim_id', el.dataset.id );
					} else {
						// Sections.
						// Add scroll anim wrapper.
						$( el ).before( '<div class="vamtam-scroll-anim-wrap" data-vamtam_anim_id="' + el.dataset.id + '"></div>' );
						var $wrap = $( el ).prev( '.vamtam-scroll-anim-wrap' );
						$( $wrap ).append( el );
						observeEl = $wrap[ 0 ];
					}

					entries[el.dataset.id] = {
						lastScrollY: '',
						lastScrollPercentage: '',
						observeEl: observeEl,
						animateEl: el,
					};

					observer.observe( observeEl );
				} );
			},
			// Common funcs used in custom animations.
			utils: {
				getAdminBarHeight: function () {
					return window.VAMTAM.adminBarHeight;
				},
				watchScrollDirection: function () {
					var watcher = function () {
						this.lastScrollTop = 0;
						this.utils = this;
						return {
							init: function () {
							},
							measure: function ( cpos ) {
								this.direction = cpos > this.lastScrollTop ? 'down' : 'up';
							}.bind( this ),
							mutate: function ( cpos ) {
								this.utils.getScrollDirection = function () {
									return this.direction;
								};
								this.lastScrollTop = cpos <= 0 ? 0 : cpos; // For Mobile or negative scrolling
							}.bind( this ),
						};
					}.bind( this );

					window.VAMTAM.addScrollHandler( watcher() );
				},
				isTouchDevice: function() {
					const prefixes = ' -webkit- -moz- -o- -ms- '.split( ' ' );

					const mq = function( query ) {
						return window.matchMedia( query ).matches;
					};

					if ( ( 'ontouchstart' in window ) || window.DocumentTouch && document instanceof DocumentTouch ) { // jshint ignore:line
						return true;
					}

					// include the 'heartz' as a way to have a non matching MQ to help terminate the join
					// https://git.io/vznFH
					var query = [ '(', prefixes.join( 'touch-enabled),(' ), 'heartz', ')' ].join( '' );

					return mq( query );
				},
			}
		},
	};

	window.VAMTAM.CUSTOM_ANIMATIONS.init();

	document.addEventListener('DOMContentLoaded', function () {
		window.VAMTAM.CUSTOM_ANIMATIONS.onDomReady();
	}, true );
})( jQuery );
