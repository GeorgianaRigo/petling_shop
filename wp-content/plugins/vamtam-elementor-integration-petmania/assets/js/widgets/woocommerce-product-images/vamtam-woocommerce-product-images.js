class VamtamProductImages extends elementorModules.frontend.handlers.Base {
	getDefaultSettings() {
		return {
			selectors: {
				container: '.elementor-widget-container',
				widget: '.elementor-widget-container',
				gallery: '.woocommerce-product-gallery, .woocommerce-product-gallery--vamtam',
				dummy: '.woocommerce-product-gallery--vamtam',
			},
		};
	}

	getDefaultElements() {
		const selectors = this.getSettings( 'selectors' );
		return {
			$container: this.$element.find( selectors.container ),
			$widget: this.$element.find( selectors.widget ),
			$gallery: this.$element.find( selectors.gallery ),
			$dummy: this.$element.find( selectors.dummy ),
		};
	}

	onInit( ...args ) {
		super.onInit( ...args );
		this.wcFlexsliderHack();
		this.handleProductImage();
        this.reInitWCProductGallery();
		this.ensureCorrectGallerySize();
		this.badgesInsideFlexViewport();
	}

	badgesInsideFlexViewport() {
		const _this = this,
			$container = this.elements.$container,
			$saleBadge = $container.find( '.onsale' ),
			$newBadge = $container.find( '.vamtam-new' ),
			$viewport = $container.find( '.woocommerce-product-gallery__wrapper' );

		if ( ! $saleBadge.length && ! $newBadge.length ) {
			return; // No badges to move.
		}

		if ( $viewport.find( '.woocommerce-product-gallery__image' ).length < 2 ) {
			return; // Only one image. No need to move badges.
		}

		if ( $saleBadge.length ) {
			$saleBadge.appendTo( $viewport ); // Move sale badge to the viewport.
		}

		if ( $newBadge.length ) {
			$newBadge.appendTo( $viewport ); // Move new badge to the viewport.
		}
	}

	ensureCorrectGallerySize() {
		const _this      = this;
		let runOnImgLoad = false;

		// resize event triggers gallery dimensions re-calculation.
		function doResize() {
			window.dispatchEvent( new Event( 'resize' ) );
			jQuery( window ).trigger( 'resize' );
		}

		jQuery( '.woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image:eq(0) .wp-post-image' ).one( 'load', function() {
			runOnImgLoad = true;

			setTimeout( () => {
				doResize();
			}, 150 ); // keep above 100ms
		} );

		// Element "load" has probems with caching & bubbling so in case it didnt run on image load, we run it on page load.
		jQuery( window ).on( 'load', () => {
			if ( runOnImgLoad ) {
				return;
			}

			// Without toggling image opacity here cause the time difference of loading one img vs the whole page could be significant.
			setTimeout( () => {
				doResize();
			}, 0 );
		} );
	}

	reInitWCProductGallery() {
		// We only need to do that in the case of a full-sized gallery,
		// with WC's lightbox enabled.
		const wcLightboxActive = jQuery( 'body' ).hasClass( 'wc-product-gallery-lightbox-active' ),
            isFullSizedGallery = this.$element.hasClass( 'vamtam-has-full-sized-gallery' );

		if ( ! wcLightboxActive || ! isFullSizedGallery ) {
			return;
		}

		const galleryParams = {
			...wc_single_product_params,
			flexslider_enabled: false, // No flexslider full-size gallery (would break the layout).
			zoom_enabled: false, // No WC zoom in full-size gallery (doesnt really make sense and also needs different html to support that).
		};

		this.elements.$gallery.trigger( 'wc-product-gallery-before-init', [ this, galleryParams ] );
		this.elements.$gallery.wc_product_gallery( galleryParams );
		this.elements.$gallery.trigger( 'wc-product-gallery-after-init', [ this, galleryParams ] );
	}

	wcFlexsliderHack() {
		if ( ! this.elements.$dummy.length ) {
			return;
		}

		this.elements.$gallery.removeClass( 'woocommerce-product-gallery--vamtam' );
		this.elements.$gallery.addClass( 'woocommerce-product-gallery' );
		this.elements.$gallery.css( 'opacity', '1' );
	}

	handleProductImage() {
		this.handleDisableLinkOption();
		this.handleDoubleLightbox();
		this.handleWcZoomElementorLightBoxConflict();
		this.clickThumbOnHover();
	}

	handleWcZoomElementorLightBoxConflict() {
		const wcZoomActive = jQuery( 'body' ).hasClass( 'wc-product-gallery-zoom-active' );

		if ( ! wcZoomActive ) {
			return;
		}

		const elementorLightboxActive = elementorFrontend.getKitSettings( 'global_image_lightbox' );

		if ( ! elementorLightboxActive ) {
			return;
		}

		const onZoomedImgClick = function ( e ) {
			const link = jQuery( e.target ).siblings( 'a' );
			if ( link.length ) {
				link.click(); // Open Elementor Lightbox.
			}
		}

		jQuery( document ).on( 'click', '.woocommerce-product-gallery__image img.zoomImg', onZoomedImgClick );
	}

	handleDoubleLightbox() {
		const wcLightboxActive = jQuery( 'body' ).hasClass( 'wc-product-gallery-lightbox-active' );

		if ( ! wcLightboxActive ) {
			return;
		}

		const elementorLightboxActive =  elementorFrontend.getKitSettings( 'global_image_lightbox' );

		if ( ! elementorLightboxActive ) {
			return;
		}

		// Both are enabled. WC's is explicit (added by add_theme_supports) but Elementor's
		// is implicit (by global Elementor option), thus we prioritize WC's.
		this.disableImageLinks( wcLightboxActive );
	}

	disableImageLinks( wcLightboxActive = false ) {
		const links = this.$element.find( 'a > img' ).parent();

		if ( ! links.length ) {
			return;
		}

		jQuery.each( links, function ( i, link ) {
			if ( wcLightboxActive ) {
				// Just disable Elementor's lightbox. We need the pointer-events for WC's lightbox.
				jQuery( link ).attr( 'data-elementor-open-lightbox', 'no' );
			} else {
				// Remove the link's href (no pointer-events/linking to the image/lightbox).
				jQuery( link ).removeAttr( 'href' );
			}
		} );
	}

	handleDisableLinkOption() {
		if ( ! this.$element.hasClass( 'vamtam-has-disable-image-link' ) ) {
			return;
		}

		const wcLightboxActive = jQuery( 'body' ).hasClass( 'wc-product-gallery-lightbox-active' );
		this.disableImageLinks( wcLightboxActive );
	}

	clickThumbOnHover() {
		let lastClicked = null;
		jQuery( 'ol.flex-control-thumbs li img' ).on( 'mouseover mouseout mousemove' , window.VAMTAM.debounce( function() {
			if ( lastClicked !== this  ) {
				lastClicked = this;
				jQuery( this ).click();
			} else if ( ! jQuery( this ).hasClass( 'flex-active' ) ) {
				// Safe-guard for when the click does not register/gets debounced from WC.
				jQuery( this ).click();
			}
		}, 50) );
	}
}

/*
	Hack for WooCommerce's flexslider on Firefox.

	For some reason, flexslider on Firefox calculates the width of the slider img
	differently than the other browsers. This causes the slider img to be too wide
	for its container.

	For this reason, we override the userAgent getter, which is used to determine
	the browser on flexslider.
*/

(function() {
	const agent   = navigator.userAgent,
		isFirefox = agent.toLowerCase().indexOf( 'firefox' ) > -1;

	if ( ! isFirefox ) {
		return;
	}

	navigator.__defineGetter__( 'userAgent', function() {
		return agent.replace( 'Firefox', 'Fire-Fox' );
	});
})();

jQuery( window ).on( 'elementor/frontend/init', () => {
	if ( ! elementorFrontend.elementsHandler || ! elementorFrontend.elementsHandler.attachHandler ) {
		const addHandler = ( $element ) => {
			elementorFrontend.elementsHandler.addHandler( VamtamProductImages, {
				$element,
			} );
		};

		elementorFrontend.hooks.addAction( 'frontend/element_ready/woocommerce-product-images.default', addHandler, 100 );
	} else {
		elementorFrontend.elementsHandler.attachHandler( 'woocommerce-product-images', VamtamProductImages );
	}
} );
