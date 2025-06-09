class VamtamTestimonialCarouselHandler extends elementorModules.frontend.handlers.Base {
	getDefaultSettings() {
		return {
		  selectors: {
			slider: '.swiper-container, .swiper',
			slide: '.swiper-slide',
			activeSlide: '.swiper-slide-active',
			activeDuplicate: '.swiper-slide-duplicate-active'
		  },
		  classes: {
			animated: 'animated',
		  },
		  attributes: {
			dataSliderOptions: 'slider_options',
			dataAnimation: 'animation'
		  }
		};
	}

	getDefaultElements() {
		var selectors = this.getSettings('selectors');
		var elements = {
			$slider: this.$element.find(selectors.slider)
		};
		elements.$mainSwiperSlides = elements.$slider.find(selectors.slide);
		return elements;
	}

	onInit() {
		elementorModules.frontend.handlers.Base.prototype.onInit.apply( this, arguments );
		this.ensureSwiperInitialized();
	}

	onSwiperImagesLoaded() {
		const _this = this;
		this.elements.$slider.imagesLoaded().
		always( function( instance ) {
			// All images loaded.
			_this.elements.$slider.addClass( 'vamtam-imgs-loaded' );
		} );
	}

	ensureSwiperInitialized() {
		const checkSwiperReady = () => {
			this.swiper = this.elements.$slider.data( 'swiper' );

			if ( this.swiper ) {
				// Swiper instance is ready.
				clearInterval( intervalID );
				this.onSwiperReady();
			}
		};
		const intervalID = setInterval( checkSwiperReady.bind( this ), 50 );
	}

	onSwiperReady() {
		this.onSwiperImagesLoaded();

		// Run Inner Anims for active (initial).
		this.triggerInnerAnimsForActiveSlide();

		const _self = this;
		this.swiper.on( 'slideChangeTransitionStart', () => {
			_self.onSlideChangeTransitionStart();
		} );
	}

	onSlideChangeTransitionStart () {
		this.$activeSlide = this.elements.$slider.find( '.swiper-slide-active' );
		this.triggerInnerAnimsForActiveSlide();
	}

	triggerInnerAnimsForActiveSlide() {
		// Determine visible slides.
		this.$activeSlide = this.elements.$slider.find( '.swiper-slide-active' );
		const isDuplicate = this.$activeSlide.hasClass( 'swiper-slide-duplicate' );

		this.triggerInnerAnims( this.$activeSlide );

		// Also trigger for original slide.
		if ( isDuplicate ) {
			const $originalActiveSlide = this.elements.$slider.find( '.swiper-slide.swiper-slide-duplicate-active' );
			this.triggerInnerAnims( $originalActiveSlide, false );
		}
	}

	triggerInnerAnims( $activeSlide, hideOtherSlides = true ) {
		const activeSlideIndex  = $activeSlide.data( 'swiper-slide-index' ),
			$animsInSlide       = $activeSlide.find( '[data-settings*="animation"]' );

		if ( this.slidesAnimated ) {
			if ( this.slidesAnimated.includes( activeSlideIndex ) ) {
				// Already animated the current slide once.
				// return;
			}
		} else {
			this.slidesAnimated = [];
		}

		if ( hideOtherSlides ) {
			this.hideInnerAnimElementsOfOtherSlides( activeSlideIndex );
		}

		const _this = this;
		$animsInSlide.each( function ( i, el ) {
			const $el      = jQuery( el ),
				settings   = $el.data( 'settings' ),
				anim       = settings && _this.getAnimation( settings ),
				animDelay  = settings && _this.getAnimationDelay( settings ),
				validAnim  = anim && anim !== 'none';

			if ( validAnim ) {
				_this.slidesAnimated.push( activeSlideIndex );
				$el.addClass( 'vamtam-invisible' ).removeClass( 'animated' ).removeClass( anim );
				setTimeout( function() {
					$el.removeClass( 'vamtam-invisible' ).addClass( 'animated ' + anim );
				}, animDelay );
			}
		} );

	}

	hideInnerAnimElementsOfOtherSlides( activeSlideIndex ) {
		const _this = this;
		this.elements.$slider.find( '.swiper-slide' ).each( function ( i, slide ) {
			const $slide = jQuery( slide ),
				slideIndex = $slide.data( 'swiper-slide-index' );

			if ( slideIndex !== activeSlideIndex ) {
				const $animsInSlide = $slide.find( '[data-settings*="animation"]' );

				$animsInSlide.each( function ( i, animEl ) {
					const $animEl  = jQuery( animEl ),
						settings   = $animEl.data( 'settings' ),
						anim       = settings && _this.getAnimation( settings ),
						validAnim  = anim && anim !== 'none';

					if ( validAnim ) {
						$animEl.addClass( 'vamtam-invisible' )
							.removeClass( 'animated' )
							.removeClass( anim );
					}
				} );
			}
		} );
	}

	getAnimation( settings ) {
		return elementorFrontend.getCurrentDeviceSetting( settings, 'animation' ) || elementorFrontend.getCurrentDeviceSetting( settings, '_animation' );
	}

	getAnimationDelay( settings ) {
		return elementorFrontend.getCurrentDeviceSetting( settings, 'animation_delay' ) || elementorFrontend.getCurrentDeviceSetting( settings, '_animation_delay' ) || 0;
	}
}

jQuery( window ).on( 'elementor/frontend/init', () => {
	if ( ! elementorFrontend.elementsHandler || ! elementorFrontend.elementsHandler.attachHandler ) {
		const addHandler = ( $element ) => {
			elementorFrontend.elementsHandler.addHandler( VamtamTestimonialCarouselHandler, {
				$element,
			} );
		};

		elementorFrontend.hooks.addAction( 'frontend/element_ready/testimonial-carousel.default', addHandler, 9999 );
	} else {
		elementorFrontend.elementsHandler.attachHandler( 'testimonial-carousel', VamtamTestimonialCarouselHandler );
	}
} );
