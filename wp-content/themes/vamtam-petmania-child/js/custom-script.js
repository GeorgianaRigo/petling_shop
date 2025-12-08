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
    
            if (!$('.mobile-filter-wrapper').find('.mobile-filter-close').length) {
                $('.mobile-filter-wrapper').prepend('<div class="mobile-filter-close">✕</div>');
            }
    
            // Toggle panel + overlay
            function openFilters() {
                $('body').addClass('filters-open');
                $('.mobile-filter-icon').text('✕');
            }
    
            function closeFilters() {
                $('body').removeClass('filters-open');
                $('.mobile-filter-icon').text('⚙️');
            }
    
            $('.mobile-filter-icon').on('click', function() {
                if ($('body').hasClass('filters-open')) {
                closeFilters();
                } else {
                    openFilters();
                }
            });
    
            $('.mobile-filter-overlay, .mobile-filter-close').on('click', function() {
                closeFilters();
            });
        }

    /* ============================================
    * GLOBAL LOADER (Με προστασία 5 δευτερολέπτων)
    * ============================================ */
  
/* ============================================
     * GLOBAL LOADER (Με Failsafe 4 δευτερολέπτων)
     * ============================================ */

    // 1. Αν δεν υπάρχει ο loader στο HTML, τον φτιάχνουμε τώρα
    if ($('#global-loader').length === 0) {
        $('body').append('<div id="global-loader"></div>');
    }

    var $loader = $('#global-loader');
    var safetyTimer; 

    $loader.hide(); 
    function showLoader() {
        // Σταματάμε τυχόν παλιά animations για να μην μπερδευτεί
        $loader.stop(true, true).show();
        
        clearTimeout(safetyTimer);
        
        safetyTimer = setTimeout(function(){
            $loader.fadeOut(200);
            console.log('Loader: Έκλεισε από το χρονόμετρο ασφαλείας.');
        }, 4000); 
    }
    function hideLoader() {
        clearTimeout(safetyTimer);
        $loader.stop(true, true).fadeOut(200);
    }

    // 1. AJAX Events (Φίλτρα, Καλάθι, Checkout)
    $(document).ajaxStart(function() {
        showLoader();
    });

    $(document).ajaxStop(function() {
        hideLoader();
    });

    // 2. BeRocket Filters (Ειδικά για τα φίλτρα σου)
    $(document).on('bapf_before_update', function(){
        showLoader();
    });
    $(document).on('bapf_after_update', function(){
        hideLoader();
    });

    // 3. Κλικ στη σελιδοποίηση (Pagination)
    $(document).on('click', '.woocommerce-pagination a', function() {
        showLoader();
    });
    
    // 4. Extra Ασφάλεια: Μόλις φορτώσει πλήρως η σελίδα (εικόνες κλπ), κλείσε τον.
    $(window).on('load', function(){
        hideLoader();
    });

    // FIX για τα φίλτρα στα προϊόντα
    // Όταν κάνεις sort, δεν χάνουν οι επιλογές των φίλτρων
    $('.woocommerce-ordering__submenu a').on('click', function(e) {
        e.preventDefault();

        const currentUrl = new URL(window.location.href);
        const clickedUrl = new URL($(this).attr('href'), window.location.origin);
        const newOrderby = clickedUrl.searchParams.get('orderby');

        // Βάζουμε νέο orderby χωρίς να χαθούν τα φίλτρα
        currentUrl.searchParams.set('orderby', newOrderby);
        currentUrl.searchParams.set('paged', '1'); // Reset pagination

        window.location.href = currentUrl.toString();
    });
});