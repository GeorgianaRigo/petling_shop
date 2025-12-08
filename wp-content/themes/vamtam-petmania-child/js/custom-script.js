jQuery(document).ready(function($) {

    // --- ΚΕΝΤΡΙΚΗ ΕΚΤΕΛΕΣΗ (Main Execution) ---
    initMegaMenu();
    initMobileFilters();
    initGlobalLoader();
    initSortingFix();


    /* ==========================================================================
       1. DESKTOP MEGA MENU LOGIC
       Διαχειρίζεται το άνοιγμα/κλείσιμο του μενού με καθυστέρηση (delay)
       ========================================================================== */
    function initMegaMenu() {
        const $menuItems = $('li.vamtam-menu-click-on-hover'); 
        const $targetSection = $('.vamtam-header-mega-menuvamtam-header-mega-menu'); 
        let menuTimer;

        if ($targetSection.length) {
            // Όταν το ποντίκι μπαίνει στο μενού
            $menuItems.on('mouseenter', function() {
                clearTimeout(menuTimer);
                $targetSection.addClass('hover-active');
            });

            // Όταν το ποντίκι φεύγει από το μενού
            $menuItems.on('mouseleave', function() {
                menuTimer = setTimeout(function() {
                    $targetSection.removeClass('hover-active');
                }, 200);
            });
            
            // Όταν το ποντίκι είναι πάνω στο ίδιο το Mega Menu
            $targetSection.on('mouseenter', function() {
                clearTimeout(menuTimer);
            });

            $targetSection.on('mouseleave', function() {
                menuTimer = setTimeout(function() {
                    $targetSection.removeClass('hover-active');
                }, 200);
            });
        }
    }


    /* ==========================================================================
       2. MOBILE FILTER LOGIC (< 992px)
       Δημιουργεί και διαχειρίζεται τα φίλτρα σε κινητά
       ========================================================================== */
    function initMobileFilters() {
        // Τρέχει μόνο σε μικρές οθόνες
        if (window.innerWidth >= 992) {
            return;
        }

        // 1. Δημιουργία HTML στοιχείων αν δεν υπάρχουν
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

        // 2. Συναρτήσεις ανοίγματος/κλεισίματος
        function openFilters() {
            $('body').addClass('filters-open');
            $('.mobile-filter-icon').text('✕');
        }

        function closeFilters() {
            $('body').removeClass('filters-open');
            $('.mobile-filter-icon').text('⚙️');
        }

        // 3. Events
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

    /* ==========================================================================
       3. GLOBAL LOADER LOGIC
       Loader με ασφάλεια (failsafe) 4 δευτερολέπτων
       ========================================================================== */
    function initGlobalLoader() {
        // Δημιουργία του loader στο DOM
        if ($('#global-loader').length === 0) {
            $('body').append('<div id="global-loader"></div>');
        }

        var $loader = $('#global-loader');
        var safetyTimer; 

        // Αρχικό κρύψιμο για ασφάλεια
        $loader.hide(); 

        // Εσωτερική συνάρτηση εμφάνισης
        function showLoader() {
            $loader.stop(true, true).show();
            clearTimeout(safetyTimer);
            
            // Failsafe: Κλείνει αυτόματα σε 4 δευτερόλεπτα
            safetyTimer = setTimeout(function(){
                $loader.fadeOut(200);
                console.log('Loader: Έκλεισε από το χρονόμετρο ασφαλείας.');
            }, 4000); 
        }

        // Εσωτερική συνάρτηση απόκρυψης
        function hideLoader() {
            clearTimeout(safetyTimer);
            $loader.stop(true, true).fadeOut(200);
        }

        // --- Event Listeners για τον Loader ---

        // AJAX Events
        $(document).ajaxStart(function() { showLoader(); });
        $(document).ajaxStop(function() { hideLoader(); });

        // BeRocket Filters
        $(document).on('bapf_before_update', function(){ showLoader(); });
        $(document).on('bapf_after_update', function(){ hideLoader(); });

        // Pagination Click
        $(document).on('click', '.woocommerce-pagination a', function() { showLoader(); });
        
        // Window Load (Τελική ασφάλεια)
        $(window).on('load', function(){ hideLoader(); });
    }

    /* ==========================================================================
       4. WOOCOMMERCE SORTING FIX
       Διατηρεί τα φίλτρα όταν αλλάζει η ταξινόμηση (sorting)
       ========================================================================== */
    function initSortingFix() {
        $('.woocommerce-ordering__submenu a').on('click', function(e) {
            e.preventDefault();

            const currentUrl = new URL(window.location.href);
            const clickedUrl = new URL($(this).attr('href'), window.location.origin);
            const newOrderby = clickedUrl.searchParams.get('orderby');

            // Ενημέρωση παραμέτρων URL
            if (newOrderby) {
                currentUrl.searchParams.set('orderby', newOrderby);
            }
            currentUrl.searchParams.set('paged', '1'); // Reset pagination

            // Ανακατεύθυνση
            window.location.href = currentUrl.toString();
        });
    }
});