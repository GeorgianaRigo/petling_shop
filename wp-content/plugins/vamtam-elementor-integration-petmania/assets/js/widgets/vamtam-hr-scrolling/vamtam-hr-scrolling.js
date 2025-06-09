class VamtamHrScrolling extends elementorModules.frontend.handlers.Base {
	getDefaultSettings() {
		return {
			selectors: {
				container: '.elementor-widget-container',
				items: '.products.elementor-grid, .elementor-posts-container',
			},
		};
	}

	getDefaultElements() {
		const selectors = this.getSettings( 'selectors' );
		return {
			$container: this.$element.find( selectors.container ),
			$items: this.$element.find( selectors.items ),
		};
	}

	onInit( ...args ) {
		super.onInit( ...args );
		this.checkHandleHrLayout();
	}

	checkHandleHrLayout() {
		const isTouchDevice = window.VAMTAM.CUSTOM_ANIMATIONS.VamtamCustomAnimations.utils.isTouchDevice();

		// We don't want to add the navigation on touch devices.
		if ( isTouchDevice ) {
			return;
		}

		const hasHrLayout = this.$element.hasClass( 'vamtam-has-hr-layout' ),
			hasNav = this.$element.hasClass( 'vamtam-has-nav' );

		// No need to add the navigation if the element doesn't have the hr layout.
		if ( ! hasHrLayout ) {
			return;
		}

		// Has the hr layout and is not a touch device.
		// Add the navigation.
		if ( hasNav ) {
			this.handleHrLayoutNavigation();
		}

	}

	handleHrLayoutNavigation() {
		// Create and insert the nav elements.
		const addNavElements = () => {
			const $navigation = jQuery(
				`<div class="vamtam-nav">
					<span class="vamtam-nav-btn vamtam-nav-btn-prev">
						<i class="vamtamtheme- vamtam-theme-arrow-left"></i>
					</span>
					<span class="vamtam-nav-btn vamtam-nav-btn-next">
						<i class="vamtamtheme- vamtam-theme-arrow-right"></i>
					</span>
				</div>` );

			this.elements.$items.after( $navigation );
		};

		// Add the event listeners & handlers.
		const bindNavEvents = () => {
			const colGapPropName = this.getWidgetType().includes( 'posts' ) ? '--grid-column-gap' : '--vamtam-col-gap';

			const onNavBtnClick = ( e ) => {
				e.preventDefault();

				const $items     = this.elements.$items,
					visibleWidth = $items.width(),
					isNext       = jQuery( e.target ).hasClass( 'vamtam-nav-btn-next' );

				let	colGap  = parseFloat( this.getCachedCSSPropForDevice( colGapPropName ) ) || 0, // value is in pixels from El options.
					colHint = this.normalizeMultiUnitValue( this.getCachedCSSPropForDevice( '--vamtam-col-hint' ) ); // can be px, em or %.

				// colHint, if exists, negates the colGap effect in the calculation (observed this during testing).
				if ( colHint < 0 ) {
					colHint = 0;
				} else {
					colGap = 0;
				}

				if ( isNext ) {
					// Scrll to the right.
					$items.scrollLeft( $items.scrollLeft() + visibleWidth + colGap - colHint );
				} else {
					// Scroll to the left.
					$items.scrollLeft( $items.scrollLeft() - visibleWidth - colGap + colHint );
				}
			};

			// Add the navigation btn events.
			jQuery( this.$element.find( '.vamtam-nav-btn' ) ).off( 'click' ).on( 'click', onNavBtnClick );

			const onItemsScroll = ( e ) => {
				const items = this.elements.$items[0],
					$items = this.elements.$items;

				if ( items.scrollLeft === 0 ) {
					// Not scrolled.
					this.$element.find( '.vamtam-nav-btn-prev' ).addClass( 'disabled' );
					this.$element.find( '.vamtam-nav-btn-next' ).removeClass( 'disabled' );
				} else if ( items.scrollLeft === ( items.scrollWidth - $items.width() ) ) {
					// Fully scrolled.
					this.$element.find( '.vamtam-nav-btn-prev' ).removeClass( 'disabled' );
					this.$element.find( '.vamtam-nav-btn-next' ).addClass( 'disabled' );
				} else {
					// In-between scroll.
					this.$element.find( '.vamtam-nav-btn-prev' ).removeClass( 'disabled' );
					this.$element.find( '.vamtam-nav-btn-next' ).removeClass( 'disabled' );
				}
			};
			const onItemsScrollDebounced200 = window.VAMTAM.debounce( onItemsScroll, 200 );
			const onItemsScrollDebounced500 = window.VAMTAM.debounce( onItemsScroll, 500 );

			// Add the scroll event listener.
			this.elements.$items.off( 'scroll', onItemsScrollDebounced200 );
			this.elements.$items.on( 'scroll', onItemsScrollDebounced200 );

			// Add the resize event listener.
			jQuery( window ).off( 'resize', onItemsScrollDebounced500 );
			jQuery( window ).on( 'resize', onItemsScrollDebounced500 );

			// Trigger on render in case a previous (remembered by browser) scroll position is used.
			this.elements.$items.trigger( 'scroll' );
		};

		addNavElements();
		bindNavEvents();
	}

	// Converts a px,em,% value to float (coresponds to pixels).
	normalizeMultiUnitValue( valWithUnit ) {
		let normalizedVal = 0;

		// pixels
		if ( valWithUnit.includes( 'px' ) ) {
			normalizedVal = parseFloat( valWithUnit ) || 0;
			return normalizedVal;
		}

		// %
		if ( valWithUnit.includes( '%' ) ) {
			const width = this.$element.width();
			normalizedVal = ( parseFloat( valWithUnit ) / 100 ) * width;
			return normalizedVal;
		}

		// ems
		if ( valWithUnit.includes( 'em' ) ) {
			const fontSize = parseFloat( this.$element.parent().css( 'font-size' ) );
			normalizedVal = parseFloat( fontSize ) * parseFloat( valWithUnit );
			return normalizedVal;
		}

		// fallback
		return 0;
	}

	/*
		Gets or sets & returns (from the cache) a css prop value for the current device.
		CSS prop is searched on the main element (can be extended if needed).
	*/
	getCachedCSSPropForDevice( propName ) {
		if ( ! propName ) {
			return "";
		}

		const curDevice = jQuery( 'body' ).attr( 'data-elementor-device-mode' );

		// init cache
		if ( ! this.cachedProps ) {
			this.cachedProps = {};
		}

		// if device NOT in cache
		if ( ! this.cachedProps[ curDevice ] ) {
			// put in cache
			this.cachedProps[ curDevice ] = {};
		}

		// if prop NOT in cache
		if ( ! this.cachedProps[ curDevice ][ propName ] ) {
			// put in cache
			this.cachedProps[ curDevice ][ propName ] = getComputedStyle( this.$element[ 0 ] ).getPropertyValue( propName ); // jQuery's .css() errors if the prop doesn't exist.
		}

		return this.cachedProps[ curDevice ][ propName ];
	}
}

jQuery( window ).on( 'elementor/frontend/init', () => {

	const attachTo = [
		{ name: 'woocommerce-products', skin: 'default' },
		{ name: 'woocommerce-product-related', skin: 'default' },
		{ name: 'woocommerce-product-upsell', skin: 'default' },
		{ name: 'posts', skin: 'classic' },
		{ name: 'posts', skin: 'vamtam_classic' },
		{ name: 'archive-posts', skin: 'archive_classic' },
		{ name: 'archive-posts', skin: 'vamtam_classic' },
	];

	if ( ! elementorFrontend.elementsHandler || ! elementorFrontend.elementsHandler.attachHandler ) {
		const addHandler = ( $element ) => {
			elementorFrontend.elementsHandler.addHandler( VamtamHrScrolling, {
				$element,
			} );
		};

		attachTo.forEach( widget => {
			elementorFrontend.hooks.addAction( `frontend/element_ready/${widget.name}.${widget.skin}`, addHandler, 100 );
		} );
	} else {
		attachTo.forEach( widget => {
			elementorFrontend.elementsHandler.attachHandler( widget.name, VamtamHrScrolling, widget.skin );
		} );
	}
} );
