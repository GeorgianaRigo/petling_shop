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
     *  GLOBAR LOADER
     * ============================================ */
    // Αν ο χρήστης είναι logged-in, σταματάμε εδώ.
    if ($('body').hasClass('logged-in')) {
        return;
    }
    
    // Δημιουργία του overlay loader αν δεν υπάρχει ήδη
    if($('#global-loader').length === 0){
        $('body').append('<div id="global-loader"></div>');
    }
    var loader = $('#global-loader');
    
    // Εμφάνιση loader
    loader.show();
    
    $(window).on('load', function(){
        loader.fadeOut(400);
    });
    
    $(document).ajaxStart(function(){
        loader.show();
    }).ajaxStop(function(){
        loader.fadeOut(200);
    });
    
    $(document).on('bapf_before_update', function(){
        loader.show();
    });
    $(document).on('bapf_after_update', function(){
        loader.fadeOut(200);
    });
    
    $(document).on('click', '.woocommerce-pagination a', function(){
        loader.show();
    });
});