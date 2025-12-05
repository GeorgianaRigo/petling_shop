jQuery(document).ready(function($) {

    /* ============================================
     *  DESKTOP MEGA MENU LOGIC
     * ============================================ */
    const $menuItems = $('li.vamtam-menu-click-on-hover'); 
    const $targetSection = $('.vamtam-header-mega-menuvamtam-header-mega-menu'); 
    
    let menuTimer;

    if ($targetSection.length) {
        $menuItems.on('mouseenter', function() {
            clearTimeout(menuTimer);
            $targetSection.addClass('hover-active');
        });

        $menuItems.on('mouseleave', function() {
            menuTimer = setTimeout(function() {
                $targetSection.removeClass('hover-active');
            }, 200);
        });
        
        $targetSection.on('mouseenter', function() {
            clearTimeout(menuTimer);
        });

        $targetSection.on('mouseleave', function() {
            menuTimer = setTimeout(function() {
                $targetSection.removeClass('hover-active');
            }, 200);
        });
    }

    /* ============================================
     *  MOBILE FILTER LOGIC (only < 992px)
     * ============================================ */
    if (window.innerWidth < 992) {

        if ($('.vamtam-products-filter').length && !$('.mobile-filter-wrapper').length) {
            $('.vamtam-products-filter').wrap('<div class="mobile-filter-wrapper"></div>');
        }

        if (!$('body').find('.mobile-filter-overlay').length) {
            $('body').append('<div class="mobile-filter-overlay"></div>');
        }

        if (!$('body').find('.mobile-filter-icon').length) {
            $('body').append('<div class="mobile-filter-icon">⚙️</div>');
        }

        $('.mobile-filter-icon').on('click', function() {
            $('body').toggleClass('filters-open');
        });

        $('.mobile-filter-overlay').on('click', function() {
            $('body').removeClass('filters-open');
        });
    }



    /* ============================================
     *   ELEMENTOR LOOP CAROUSEL – CENTER SLIDE LOGIC
     *   (dynamic detection + class "center-slide")
     * ============================================ */

    function initCenterSlideEffect() {

        const carousels = $('.elementor-widget-loop-carousel .swiper');

        if (!carousels.length) return;

        carousels.each(function() {

            let swiper = this.swiper;

            if (!swiper) return;

            // Force centeredSlides to behave correctly
            swiper.params.centeredSlides = true;
            swiper.update();

            function updateCenter() {

                $(".swiper-slide", swiper.el).removeClass("center-slide");

                let slidesPerView = swiper.params.slidesPerView || 1;

                let centerIndex = swiper.activeIndex + Math.floor(slidesPerView / 2);

                if (centerIndex >= swiper.slides.length)
                    centerIndex = centerIndex % swiper.slides.length;

                $(swiper.slides[centerIndex]).addClass("center-slide");
            }

            // initial run
            updateCenter();

            // on slide change
            swiper.on("slideChange transitionEnd", updateCenter);

        });
    }

    // Elementor / Swiper loads async → small delay
    setTimeout(initCenterSlideEffect, 400);

});
    