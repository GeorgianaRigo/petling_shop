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

});
    