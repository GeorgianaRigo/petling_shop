jQuery(document).ready(function($) {

    // --- ΚΕΝΤΡΙΚΗ ΕΚΤΕΛΕΣΗ (Main Execution) ---
    initMegaMenu();
    initMobileFilters();
    initGlobalLoader();
    initSortingFix();
    initMobileAutoScroll();


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
 /* ==========================================================================
       2. MOBILE FILTER LOGIC (< 992px)
       ΜΟΝΟ ΣΕ ΣΕΛΙΔΕΣ ΚΑΤΗΓΟΡΙΩΝ / SHOP
       ========================================================================== */
       function initMobileFilters() {
        // 1. Έλεγχος Οθόνης: Τρέχει μόνο σε κινητά/tablet
        if (window.innerWidth >= 992) {
            return;
        }

        // 2. Έλεγχος Σελίδας: Τρέχει ΜΟΝΟ αν είμαστε σε Κατηγορία ή στο Shop
        // 'tax-product_cat' = Σελίδα Κατηγορίας
        // 'post-type-archive-product' = Σελίδα Shop (όλα τα προϊόντα)
        if ( !$('body').hasClass('tax-product_cat') && !$('body').hasClass('post-type-archive-product') ) {
            return; // Αν δεν είναι κατηγορία, σταματάει εδώ.
        }

        // 3. Έλεγχος Περιεχομένου: Υπάρχουν όντως φίλτρα στη σελίδα;
        // Αν η σελίδα είναι κατηγορία αλλά δεν έχει sidebar/φίλτρα, δεν κάνουμε τίποτα.
        if ($('.vamtam-products-filter').length === 0) {
            return;
        }

        // --- ΑΠΟ ΕΔΩ ΚΑΙ ΚΑΤΩ Ο ΚΩΔΙΚΑΣ ΕΚΤΕΛΕΙΤΑΙ ΜΟΝΟ ΣΤΙΣ ΣΩΣΤΕΣ ΣΕΛΙΔΕΣ ---

        // Δημιουργία Wrapper
        if ($('.vamtam-products-filter').length && !$('.mobile-filter-wrapper').length) {
            $('.vamtam-products-filter').wrap('<div class="mobile-filter-wrapper"></div>');
        }

        // Δημιουργία Overlay
        if (!$('body').find('.mobile-filter-overlay').length) {
            $('body').append('<div class="mobile-filter-overlay"></div>');
        }

        // Δημιουργία Εικονιδίου (ΔΙΟΡΘΩΣΗ: Είχε λάθος selector πριν)
        if (!$('body').find('.mobile-filter-icon').length) {
            $('body').append('<div class="mobile-filter-icon">⚙️</div>');
        }

        // Δημιουργία Κουμπιού Κλεισίματος
        if (!$('.mobile-filter-wrapper').find('.mobile-filter-close').length) {
            $('.mobile-filter-wrapper').prepend('<div class="mobile-filter-close">✕</div>');
        }

        // Συναρτήσεις λειτουργίας
        function openFilters() {
            $('body').addClass('filters-open');
            $('.mobile-filter-icon').text('✕');
        }

        function closeFilters() {
            $('body').removeClass('filters-open');
            $('.mobile-filter-icon').text('⚙️');
        }

        // Events (Clicks)
        // Χρησιμοποιούμε .off() για να μην μπαίνουν διπλά click events αν ξαναφορτώσει το JS
        $('.mobile-filter-icon').off('click').on('click', function() {
            if ($('body').hasClass('filters-open')) {
                closeFilters();
            } else {
                openFilters();
            }
        });

        $('.mobile-filter-overlay, .mobile-filter-close').off('click').on('click', function() {
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

    /* ============================================
     * MOBILE AUTO-SCROLL CAROUSEL
     * Για τα Info Boxes (BoxNow, Τηλέφωνο, κλπ)
     * ============================================ */
    function initMobileAutoScroll() {
        if (window.innerWidth >= 768) return;

        var $container = $('.elementor-element-e7532ed .elementor-widget-wrap');
        if (!$container.length) return;

        var currentIndex = 0;
        var $items = $container.children('.elementor-widget-icon-box');
        var totalSlides = $items.length;
        var slideInterval;

        setTimeout(function(){
            $container.scrollLeft(0);
        }, 100);

        function startSlider() {
            if (slideInterval) clearInterval(slideInterval);

            slideInterval = setInterval(function() {
                currentIndex++;

                if (currentIndex >= totalSlides) {
                    currentIndex = 0;
                }

                var containerWidth = $container.width(); // Υπολογισμός κάθε φορά για ακρίβεια
                var scrollPos = currentIndex * containerWidth;

                $container.stop().animate({
                    scrollLeft: scrollPos
                }, 500);

            }, 3000); 
        }

        startSlider();

        $container.on('touchstart', function() {
            clearInterval(slideInterval);
        });
        
        $container.on('touchend', function() {
            setTimeout(startSlider, 5000);
        });
        
        $(window).on('resize', function(){
             if (window.innerWidth < 768) {
                 $container.scrollLeft(currentIndex * $container.width());
             }
        });
    }
    
});