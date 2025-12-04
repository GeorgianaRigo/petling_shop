jQuery(document).ready(function($) {

    // ----- DESKTOP MEGA MENU LOGIC (always active) -----
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

    // ================================
    //       MOBILE FILTER LOGIC
    //   ONLY UNDER 992px SCREEN WIDTH
    // ================================
    if (window.innerWidth < 992) {

        // Wrap filters into slide panel
        if ($('.vamtam-products-filter').length && !$('.mobile-filter-wrapper').length) {
            $('.vamtam-products-filter').wrap('<div class="mobile-filter-wrapper"></div>');
        }

        // Add overlay
        if (!$('body').find('.mobile-filter-overlay').length) {
            $('body').append('<div class="mobile-filter-overlay"></div>');
        }

        // Add floating icon
        if (!$('body').find('.mobile-filter-icon').length) {
            $('body').append('<div class="mobile-filter-icon">⚙️</div>');
        }

        // Open/Close filters
        $('.mobile-filter-icon').on('click', function() {
            $('body').toggleClass('filters-open');
        });

        $('.mobile-filter-overlay').on('click', function() {
            $('body').removeClass('filters-open');
        });
    }

});
