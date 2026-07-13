<?php
/**
 * Petmania Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Petmania Child
 */

add_action( 'wp_enqueue_scripts', 'petmania_child_enqueue_assets' );
/**
 * Enqueue scripts and styles.
 */
function petmania_child_enqueue_assets() {
    $parent_style = 'vamtam-petmania-style'; // Το handle του CSS του γονικού θέματος.

    // Φόρτωση του CSS του γονικού θέματος
    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );

    // Φόρτωση του CSS του child θέματος
    wp_enqueue_style( 
        'petmania-child-style',
        get_stylesheet_uri(),
        array( $parent_style )
    );

    // Φόρτωση του custom JavaScript αρχείου
    wp_enqueue_script(
        'petmania-child-custom-script', // Όνομα (handle) για το script σου
        get_stylesheet_directory_uri() . '/js/custom-script.js', // Η διαδρομή προς το αρχείο
        array( 'jquery' ), // Δήλωση ότι εξαρτάται από το jQuery
        '1.0.0', // Αριθμός έκδοσης (καλό για caching)
        true // Φόρτωση του script στο footer της σελίδας για καλύτερη απόδοση
    );
}
\add_filter( 'gettext', 'change_elementor_pro_coupon_text', 20, 3 );

function change_elementor_pro_coupon_text( $translated_text, $text, $domain ) {
    if ( 'elementor-pro' === $domain ) {
        // Εδώ "πιάνουμε" τις φράσεις που θέλουμε και τις αλλάζουμε
        switch ( $text ) {
            case 'If you have a coupon code, please apply it below.':
                $translated_text = 'Αν έχετε κάποιο κουπόνι, εισάγετε τον κωδικό παρακάτω.';
                break;
            
            // Μπορείς να προσθέσεις κι άλλες φράσεις εδώ αν χρειαστεί
            // case 'Another English Text':
            //     $translated_text = 'Η Ελληνική Μετάφραση';
            //     break;
        }
    }
    return $translated_text;
}

function petling_secure_logout_shortcode() {
    if ( is_user_logged_in() ) {
        $redirect_url = home_url(); 
        $logout_url = wp_logout_url( $redirect_url );

        $html = '<a href="' . esc_url( $logout_url ) . '" title="' . esc_attr__( 'Αποσύνδεση', 'woocommerce' ) . '" class="vamtam-logout-link">';
        $html .= '<i aria-hidden="true" class="fas fa-sign-out-alt petling-logout-icon"></i>';
        $html .= '</a>';
        
        return $html;
    }
    return ''; 
}
add_shortcode( 'petling_logout_icon', 'petling_secure_logout_shortcode' );


// ---- Add responsive CSS ----
function petling_logout_icon_css() {
    ?>
    <style>
        .petling-logout-icon {
            font-size: 28px;
            color: var(--e-global-color-vamtam_accent_2);
        }

        /* Tablet (μέχρι 1024px) */
        @media (max-width: 1024px) {
            .petling-logout-icon {
                font-size: 24px !important;
            }
        }
    </style>
    <?php
}
add_action( 'wp_head', 'petling_logout_icon_css' );

// Προβολή SKU σε σελίδες προϊόντων (shop, category, archives)
add_action( 'woocommerce_after_shop_loop_item_title', 'show_sku_in_loop', 9 );
function show_sku_in_loop() {
    global $product;

    if ( $product && $product->get_sku() ) {
        echo '<div class="product-sku" style="font-size:12px; color:#59606D;">SKU: ' . $product->get_sku() . '</div>';
    }
}

add_filter( 'woocommerce_sale_flash', 'unified_percentage_badge', 9999, 3 );
function unified_percentage_badge( $html, $post, $product ) {

    $percentage = 0;

    // Simple product
    if ( $product->is_type('simple') && $product->is_on_sale() ) {
        $regular = (float) $product->get_regular_price();
        $sale    = (float) $product->get_sale_price();

        if ( $regular > 0 && $sale > 0 ) {
            $percentage = round( ( ( $regular - $sale ) / $regular ) * 100 );
        }
    }

    // Variable product
    if ( $product->is_type('variable') ) {
        $percentages = [];

        foreach ( $product->get_children() as $child_id ) {
            $variation = wc_get_product( $child_id );
            if ( $variation->is_on_sale() ) {
                $regular = (float) $variation->get_regular_price();
                $sale    = (float) $variation->get_sale_price();
                if ( $regular > 0 && $sale > 0 ) {
                    $percentages[] = round( ( ( $regular - $sale ) / $regular ) * 100 );
                }
            }
        }

        if ( ! empty( $percentages ) ) {
            $percentage = max( $percentages );
        }
    }

    // Αν δεν έχει έκπτωση → δείχνουμε το default
    if ( $percentage <= 0 ) {
        return $html;
    }

    // Κρατάμε ΑΚΡΙΒΩΣ το ίδιο style που έχεις (ίδια τάξη CSS!)
    return '<span class="onsale">-' . $percentage . '%</span>';
}

// Badge "Εξαντλήθηκε" για προϊόντα χωρίς απόθεμα
add_action( 'woocommerce_before_shop_loop_item_title', 'show_out_of_stock_badge_archives', 9 );
function show_out_of_stock_badge_archives() {
    global $product;

    if ( ! $product->is_in_stock() ) {
        echo '<span class="soldout-overlay-badge">Εξαντλήθηκε</span>';
    }
}

add_filter( 'posts_clauses', 'instock_brand_priority_sales', 20, 2 );
function instock_brand_priority_sales( $clauses, $query ) {
    global $wpdb;

    // Μόνο frontend, main query, shop/archive
    if ( ! is_admin() && $query->is_main_query() && ( is_shop() || is_product_category() || is_product_tag() ) ) {

        // Αν υπάρχει ήδη κάποιο orderby στο URL (π.χ. ?orderby=rating), μην τρέχει
        if ( isset($_GET['orderby']) && ! empty($_GET['orderby']) ) {
            return $clauses;
        }

        // Αν έχουν ενεργοποιηθεί φίλτρα BeRocket, μην τρέχει
        if ( ! empty($_GET['filter']) || ! empty($_GET['filter_brand']) || ! empty($_GET['filter_price']) ) {
            return $clauses;
        }

        // JOIN για stock status
        if ( strpos( $clauses['join'], "_stock_status" ) === false ) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS stockmeta ON ({$wpdb->posts}.ID = stockmeta.post_id AND stockmeta.meta_key = '_stock_status') ";
        }

        // JOIN για total_sales
        if ( strpos( $clauses['join'], "_total_sales" ) === false ) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS salesmeta ON ({$wpdb->posts}.ID = salesmeta.post_id AND salesmeta.meta_key = 'total_sales') ";
        }

        // JOIN για berocket_brand
        if ( strpos( $clauses['join'], "brandmeta" ) === false ) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS brandmeta ON ({$wpdb->posts}.ID = brandmeta.post_id AND brandmeta.meta_key = 'berocket_brand') ";
        }

        // ORDER BY
        $clauses['orderby'] = "
            CASE WHEN stockmeta.meta_value = 'instock' THEN 0 ELSE 1 END ASC,
            CASE 
                WHEN brandmeta.meta_value IN ('Platinum','Prins','Sanadog','Grau') THEN 0
                ELSE 1
            END ASC,
            CAST(salesmeta.meta_value AS UNSIGNED) DESC,
            {$wpdb->posts}.menu_order ASC
        ";
    }

    return $clauses;
}

add_filter( 'woocommerce_related_products', 'petling_related_instock_first', 20, 3 );
function petling_related_instock_first( $related_posts, $product_id, $args ) {
    $instock = [];
    $outofstock = [];

    foreach ( $related_posts as $rid ) {
        $p = wc_get_product( $rid );
        if ( $p && $p->is_in_stock() ) {
            $instock[] = $rid;
        } else {
            $outofstock[] = $rid;
        }
    }

    // Ενώνουμε πρώτα τα in-stock και μετά τα out-of-stock
    $sorted = array_merge( $instock, $outofstock );

    // Περιορίζουμε στο posts_per_page που θέλει το WooCommerce
    $limit = !empty($args['posts_per_page']) ? intval($args['posts_per_page']) : 4;

    return array_slice( $sorted, 0, $limit );
}

add_filter( 'gettext', 'petling_translate_cart_title', 20, 3 );

function petling_translate_cart_title( $translated_text, $text, $domain ) {
    // Μετάφραση του "Your Cart"
    if ( $text === 'Your Cart' ) {
        $translated_text = 'Το Καλάθι μου';
    }
    // Μετάφραση του σκέτου "Cart" αν χρειαστεί
    if ( $text === 'Cart' && $domain === 'woocommerce' ) {
         $translated_text = 'Καλάθι';
    }
    
    return $translated_text;
}

add_filter( 'gettext', 'change_specific_text_strings', 10, 3 );
add_filter( 'ngettext', 'change_specific_text_strings', 10, 3 );

function change_specific_text_strings( $translated_text, $text, $domain ) {
    
    // Εδώ ορίζεις τι θες να αλλάξεις. 
    // Αριστερά το Αγγλικό (ακριβώς όπως φαίνεται), Δεξιά το Ελληνικό.
    $replacements = array(
        'Create Account' => 'Δημιουργία Λογαριασμού',
        'Create account' => 'Δημιουργία Λογαριασμού', // Για σιγουριά αν είναι με μικρά
        
        // --- ΠΡΟΣΘΗΚΕΣ ΓΙΑ ΤΟ ΚΟΥΠΟΝΙ ---
        'Coupon code'    => 'Κωδικός κουπονιού',
        'Apply coupon'   => 'Εφαρμογή',
        'Apply'          => 'Εφαρμογή',
    );

    if ( array_key_exists( $text, $replacements ) ) {
        return $replacements[ $text ];
    }

    return $translated_text;
}

/**
 * Elementor Form: Απαγόρευση διπλής εγγραφής (Δυναμικός Έλεγχος)
 * Ελέγχει αν το email υπάρχει ήδη στη βάση για τη συγκεκριμένη φόρμα.
 */
add_action( 'elementor_pro/forms/validation/email', function( $field, $record, $ajax_handler ) {
    $value = $field['value'];
    $field_id = $field['id'];

    global $wpdb;
    $table_name = $wpdb->prefix . 'e_submissions_values';

    $exists = $wpdb->get_var( $wpdb->prepare( 
        "SELECT COUNT(*) FROM $table_name WHERE `value` = %s AND `key` = %s", 
        $value, 
        $field_id
    ) );

    if ( $exists > 0 ) {
        $ajax_handler->add_error( 
            $field['id'], 
            'Το email αυτό είναι ήδη εγγεγραμμένο!' 
        );
    }

}, 10, 3 );



// ==============================================================================
// 1. PRELOAD HERO IMAGE (ΣΩΣΤΟ & ΣΥΓΧΡΟΝΙΣΜΕΝΟ)
// ==============================================================================
add_action( 'wp_head', function () {
    if ( is_front_page() ) {
        echo '<link rel="preload" as="image" 
              href="https://petling.gr/wp-content/uploads/2025/12/heroGirlsIrisDarcy-2.jpg" 
              imagesrcset="https://petling.gr/wp-content/uploads/2025/12/heroGirlsIrisDarcy-2.jpg 800w, https://petling.gr/wp-content/uploads/2025/12/heroGirlsIrisDarcy-2-300x264.jpg 300w, https://petling.gr/wp-content/uploads/2025/12/heroGirlsIrisDarcy-2-768x675.jpg 768w, https://petling.gr/wp-content/uploads/2025/12/heroGirlsIrisDarcy-2-640x562.jpg 640w" 
              imagesizes="(max-width: 800px) 100vw, 800px">';
    }
}, 1 );

// ==============================================================================
// 2. LCP IMAGE PRIORITY
// ==============================================================================
add_filter( 'wp_get_attachment_image_attributes', function ( $attr ) {
    if ( isset( $attr['src'] ) && strpos( $attr['src'], 'heroGirlsIrisDarcy-2' ) !== false ) {
        $attr['fetchpriority'] = 'high';
        $attr['decoding']      = 'sync';
        unset( $attr['loading'] );
    }
    return $attr;
}, 20 );

// ==============================================================================
// 3. CSS "ΑΣΠΙΔΑ": ΑΠΑΓΟΡΕΥΣΗ BACKGROUND ΣΕ ΚΡΥΦΑ SECTIONS
// ==============================================================================
add_action( 'wp_head', function () {
    if ( is_front_page() ) {
        ?>
        <style>
        /* Αυτός ο κανόνας ισχύει ΠΑΝΤΑ */
        @media (max-width: 767px) {
            
            /* 1. ΣΤΟΧΕΥΜΕΝΗ ΕΠΙΘΕΣΗ στο beef101 (Αν υπάρχει ακόμα) */
            .elementor-element-beef101,
            [data-id="beef101"] {
                background-image: none !important;
                display: none !important;
            }

            /* 2. ΓΕΝΙΚΗ ΕΠΙΘΕΣΗ: Οποιοδήποτε section είναι "Hidden Mobile" */
            .elementor-hidden-mobile,
            .elementor-hidden-phone {
                background-image: none !important;
                display: none !important;
            }

            /* 3. MOBILE HERO FIXES (531bc53) */
            section[data-id="531bc53"] {
                contain: layout paint style;
                min-height: 250px;
            }
            
            /* Kill Animations */
            .elementor-motion-effects-layer,
            .elementor-motion-effects-element,
            [data-settings*="motion_fx"] {
                animation: none !important;
                transition: none !important;
                transform: none !important;
            }
        }

        /* Elementor Fix */
        .elementor-invisible { 
            visibility: visible !important; 
            opacity: 1 !important; 
            animation: none !important; 
        }
        </style>
        <?php
    }
}, 1 );

// ==============================================================================
// 4. FONTS
// ==============================================================================
add_filter( 'style_loader_tag', function ( $html ) {
    if ( is_admin() ) return $html;
    return str_replace( 'googleapis.com/css', 'googleapis.com/css?display=swap', $html );
}, 10 );


add_filter( 'woocommerce_thankyou_order_received_text', 'force_viva_success_message', 20, 2 );

function force_viva_success_message( $text, $order ) {
    if ( ! $order ) return $text;

    // Αν η μέθοδος είναι Viva Wallet
    if ( strpos( $order->get_payment_method(), 'viva' ) !== false ) {
        // Επιστρέφουμε ΠΑΝΤΑ το μήνυμα επιτυχίας, αγνοώντας το UI του WooCommerce
        return 'Σας ευχαριστούμε! Η παραγγελία σας ελήφθη επιτυχώς και η πληρωμή έχει δρομολογηθεί.';
    }
    return $text;
}

// ---------------------------------------------------------
// CSS: Κρύβει τα κουμπιά Πληρωμή/Ακύρωση στη Thank You Page
// ---------------------------------------------------------
add_action('wp_head', 'vamtam_hide_thankyou_buttons_css');

function vamtam_hide_thankyou_buttons_css() {
    // Ελέγχουμε αν είμαστε στη σελίδα "Order Received" (Thank You page)
    if ( is_checkout() && !empty( is_wc_endpoint_url('order-received') ) ) {
        ?>
        <style type="text/css">
            /* Εξαφάνιση κουμπιών και μηνυμάτων λάθους */
            .woocommerce-checkout.woocommerce-order-received .order-again,
            .woocommerce-checkout.woocommerce-order-received .pay,
            .woocommerce-checkout.woocommerce-order-received .cancel,
            .woocommerce-checkout.woocommerce-order-received .woocommerce-error,
            .woocommerce-checkout.woocommerce-order-received .woocommerce-info {
                display: none !important;
            }
        </style>
        <?php
    }
}

add_filter( 'gettext', 'force_translate_thank_you', 999, 3 );

function force_translate_thank_you( $translated_text, $text, $domain ) {
    // Ελέγχουμε και τις δύο πιθανές γραφές (μικρά/κεφαλαία)
    if ( $text === 'Thank you. Your order has been received.' || $text === 'Thank You. Your order has been received.' ) {
        return 'Σας ευχαριστούμε. Η παραγγελία σας ελήφθη.';
    }
    return $translated_text;
}

add_filter( 'gettext', 'fix_pending_payment_message_greek', 9999, 3 );

function fix_pending_payment_message_greek( $translated_text, $text, $domain ) {
    
    // Η φράση που θέλουμε να πιάσουμε (ακριβώς όπως εμφανίζεται)
    $target_phrase = 'Η παραγγελία βρίσκεται σε αναμονή πληρωμής. Μετά την επιτυχή πληρωμή, θα σας στείλουμε ένα email επιβεβαίωσης.';

    // Έλεγχος αν το κείμενο ταιριάζει (ακριβής αντιστοιχία ή αν περιέχεται)
    if ( $translated_text === $target_phrase || strpos($translated_text, 'Η παραγγελία βρίσκεται σε αναμονή πληρωμής') !== false ) {
        
        // Επιστρέφουμε το νέο μήνυμα που θέλουμε να βλέπει ο πελάτης
        return 'Η πληρωμή σας ολοκληρώθηκε επιτυχώς και η παραγγελία σας ετοιμάζεται!';
        
        // ΣΗΜΕΙΩΣΗ: Αν θες να μην φαίνεται ΤΙΠΟΤΑ, βάλε απλά: return '';
    }

    return $translated_text;
}

// ---------------------------------------------------------
// GOOGLE-FRIENDLY FAVICON (UPDATED WITH SVG)
// ---------------------------------------------------------
add_action( 'wp_head', 'petling_favicon_google' );
add_action( 'login_head', 'petling_favicon_google' );
add_action( 'admin_head', 'petling_favicon_google' );

function petling_favicon_google() {
    ?>
    <link rel="icon" href="https://petling.gr/logo.svg" type="image/svg+xml">

    <link rel="icon" href="https://petling.gr/favicon.ico" sizes="any">

    <link rel="apple-touch-icon" sizes="180x180" href="https://petling.gr/apple-touch-icon.png">

    <meta name="theme-color" content="#ffffff">
    <?php
}

// ==============================================================================
// ΜΕΡΟΣ 1: Καθαρισμός URL για το Facebook (Fix 410 Error)
// ==============================================================================
add_action( 'template_redirect', 'petling_clean_search_url_redirect', 1 );

function petling_clean_search_url_redirect() {
    // Αν υπάρχει το post_type=product στο URL, κάνουμε ανακατεύθυνση στο καθαρό
    if ( is_search() && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'product' ) {
        
        $search_query = get_search_query();
        $clean_url = home_url( '/' ) . '?s=' . urlencode( $search_query );
        
        wp_redirect( $clean_url, 301 );
        exit;
    }
}

// ==============================================================================
// ΜΕΡΟΣ 2: Διόρθωση Template (Fix Εμφάνισης)
// Λέμε στο WordPress να δείχνει ΠΑΝΤΑ προϊόντα στην αναζήτηση
// ==============================================================================
add_action( 'pre_get_posts', 'petling_force_product_layout_in_search' );

function petling_force_product_layout_in_search( $query ) {
    // Τρέχουμε μόνο στην κεντρική αναζήτηση (όχι στο admin, όχι σε μενού)
    if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
        
        // Αναγκάζουμε το αποτέλεσμα να είναι ΠΡΟΪΟΝΤΑ (Product Grid)
        $query->set( 'post_type', 'product' );
        
        // (Προαιρετικά) Ορίζουμε πόσα προϊόντα να δείχνει ανά σελίδα
        // $query->set( 'posts_per_page', 12 ); 
    }
}

// ==============================================================================
// ΕΙΔΟΠΟΙΗΣΗ ΞΕΧΩΡΙΣΤΩΝ ΑΠΟΣΤΟΛΩΝ ΣΤΟ CHECKOUT (DROPSHIPPING)
// ==============================================================================
add_action( 'woocommerce_checkout_before_order_review', 'petling_split_shipping_notice' );

function petling_split_shipping_notice() {
    // Σιγουρευόμαστε ότι υπάρχει καλάθι και δεν είναι άδειο
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }

    $suppliers = array();

    // Διατρέχουμε τα προϊόντα του καλαθιού
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product_id = $cart_item['product_id'];
        
        // Παίρνουμε το όνομα του προμηθευτή από το custom field που είχαμε φτιάξει
        $supplier_name = get_post_meta( $product_id, '_supplier_name', true );
        
        // Αν ένα προϊόν δεν έχει συμπληρωμένο προμηθευτή, θεωρούμε ότι είναι του petling
        if ( empty( $supplier_name ) ) {
            $supplier_name = 'petling'; 
        }

        // Προσθέτουμε τον προμηθευτή στη λίστα μας
        $suppliers[ $supplier_name ] = true;
    }

    // Αν βρούμε περισσότερους από 1 διαφορετικούς προμηθευτές στο καλάθι...
    if ( count( $suppliers ) > 1 ) {
        
        // Custom εμφάνιση χωρίς το εικονίδιο του WooCommerce
        echo '<div style="border-top: 3px solid #C7B297; background-color: #fcfbf9; padding: 15px 20px; margin-bottom: 2em; border-radius: 4px; color: #515151; font-size: 0.95em; line-height: 1.5;">';
        
        // Το τελικό λεκτικό
        echo '📦 <strong>Ενημέρωση Αποστολής:</strong> Η παραγγελία σας περιλαμβάνει προϊόντα από διαφορετικές αποθήκες και θα αποσταλεί σε <strong>ξεχωριστά δέματα</strong>. Δεν προκύπτει καμία πρόσθετη οικονομική επιβάρυνση.';
        
        echo '</div>';
    }
}   

// ==============================================================================
// ΕΜΦΑΝΙΣΗ BADGE "VET" (ΙΔΙΟ ΜΕ ΤΗΣ ΕΚΠΤΩΣΗΣ) ΓΙΑ ΠΡΟΪΟΝΤΑ ΜΕ ΕΤΙΚΕΤΑ 'vet'
// ==============================================================================

// 1. Εμφάνιση στο Grid (Σελίδα Καταστήματος / Κατηγοριών)
add_action( 'woocommerce_before_shop_loop_item_title', 'petling_show_vet_badge_like_sale', 10 );

// 2. Εμφάνιση μέσα στη σελίδα του ίδιου του προϊόντος
add_action( 'woocommerce_before_single_product_summary', 'petling_show_vet_badge_like_sale', 10 );

function petling_show_vet_badge_like_sale() {
    global $product;

    // Ελέγχουμε αν το προϊόν έχει την "κρυφή" ετικέτα 'vet' στο διαχειριστικό
    if ( $product && has_term( 'vet', 'product_tag', $product->get_id() ) ) {
        
        // Χρησιμοποιούμε την κλάση "onsale" του θέματός σου για να πάρει το ίδιο σχήμα/στυλ.
        // Προσθέτουμε και την κλάση "vet-badge" για να του αλλάξουμε απλά το χρώμα και τη θέση.
        echo '<span class="onsale vet-badge">VET</span>';
    }
}

// 3. CSS για να του δώσουμε ιατρικό χρώμα και να μην πέφτει πάνω στην έκπτωση
add_action( 'wp_head', 'petling_vet_badge_css_fix' );
function petling_vet_badge_css_fix() {
    ?>
    <style>
        /* Κληρονομεί όλο το στυλ από το .onsale του θέματός σου, αλλά του αλλάζουμε χρώμα και θέση */
        span.onsale.vet-badge {
            background-color: #4a7c59 !important; /* Ένα premium ιατρικό πράσινο */
            color: #ffffff !important;
            
            /* Το μετακινούμε αριστερά, ώστε αν το προϊόν έχει ΚΑΙ έκπτωση, 
               να φαίνονται και τα 2 σηματάκια (ένα δεξιά, ένα αριστερά) */
            right: auto !important;
            left: 10px !important; 
        }
    </style>
    <?php
}

// ==============================================================================
// 1. Δημιουργία των πεδίων στο διαχειριστικό (ΜΕ HTML EDITOR - WYSIWYG)
// ==============================================================================
add_action( 'woocommerce_product_options_general_product_data', 'petling_add_custom_product_fields_with_editor' );
function petling_add_custom_product_fields_with_editor() {
    global $post;
    
    echo '<div class="options_group" style="padding: 10px 20px;">';
    
    // Ρυθμίσεις για τον Editor
    $editor_settings = array(
        'media_buttons' => false, 
        'textarea_rows' => 5,
        'tinymce'       => true,
        'quicktags'     => true
    );

    // --- ΠΕΔΙΟ 1: Σύνθεση ---
    echo '<p class="form-field _petling_composition_field">';
    echo '<label for="_petling_composition" style="display:block; float:none; width:100%; font-weight:bold; margin-bottom:5px;">Σύνθεση (Συστατικά)</label>';
    $composition_val = get_post_meta( $post->ID, '_petling_composition', true );
    $editor_settings['textarea_name'] = '_petling_composition';
    wp_editor( $composition_val, '_petling_composition_editor', $editor_settings );
    echo '</p>';

    // --- ΠΕΔΙΟ 2: Δοσολογία ---
    echo '<p class="form-field _petling_dosage_field" style="margin-top: 20px;">';
    echo '<label for="_petling_dosage" style="display:block; float:none; width:100%; font-weight:bold; margin-bottom:5px;">Δοσολογία</label>';
    $dosage_val = get_post_meta( $post->ID, '_petling_dosage', true );
    $editor_settings['textarea_name'] = '_petling_dosage';
    wp_editor( $dosage_val, '_petling_dosage_editor', $editor_settings );
    echo '</p>';

    // --- ΠΕΔΙΟ 3: Αποθήκευση ---
    echo '<p class="form-field _petling_storage_field" style="margin-top: 20px;">';
    echo '<label for="_petling_storage" style="display:block; float:none; width:100%; font-weight:bold; margin-bottom:5px;">Αποθήκευση Τροφής</label>';
    $storage_val = get_post_meta( $post->ID, '_petling_storage', true );
    $editor_settings['textarea_name'] = '_petling_storage';
    wp_editor( $storage_val, '_petling_storage_editor', $editor_settings );
    echo '</p>';
    
    echo '</div>';
}

// ==============================================================================
// 2. Αποθήκευση των δεδομένων
// ==============================================================================
add_action( 'woocommerce_process_product_meta', 'petling_save_custom_product_fields_editor' );
function petling_save_custom_product_fields_editor( $post_id ) {
    if ( isset( $_POST['_petling_composition'] ) ) {
        update_post_meta( $post_id, '_petling_composition', wp_kses_post( wp_unslash( $_POST['_petling_composition'] ) ) );
    }
    if ( isset( $_POST['_petling_dosage'] ) ) {
        update_post_meta( $post_id, '_petling_dosage', wp_kses_post( wp_unslash( $_POST['_petling_dosage'] ) ) );
    }
    if ( isset( $_POST['_petling_storage'] ) ) {
        update_post_meta( $post_id, '_petling_storage', wp_kses_post( wp_unslash( $_POST['_petling_storage'] ) ) );
    }
}

// =========================================================================
// ΝΕΟ SHORTCODE: Πίνακας Ελέγχου Κτηνιάτρου (Απλοποιημένο)
// Χρήση: [petling_vet_dashboard prefix="VET"]
// =========================================================================
add_shortcode('petling_vet_dashboard', 'ptl_vet_dashboard_shortcode');
function ptl_vet_dashboard_shortcode($atts) {
    global $wpdb;
    $atts = shortcode_atts(array('prefix' => 'VET'), $atts);
    $prefix = sanitize_text_field($atts['prefix']);
    $promo_table = $wpdb->prefix . 'petling_partner_leads';
    
    $html = '<style>
        .ptl-dash-container { max-width: 800px; margin: 40px auto; background: #fffaf1; padding: 30px; border-radius: 12px; border: 2px dashed #C7B297; font-family: sans-serif; }
        .ptl-dash-container h3 { color: #43282F; text-align: center; margin-top:0; }
        .ptl-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .ptl-table th, .ptl-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .ptl-table th { background: #C7B297; color: #43282F; font-weight: bold; }
        .ptl-btn-redeem { background: #5b9a68; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .ptl-btn-redeem:hover { background: #4a8255; }
        .ptl-search-bar { width: 100%; padding: 12px 15px; border: 2px solid #C7B297; border-radius: 8px; font-size: 16px; margin-bottom: 20px; box-sizing: border-box; }
    </style>';

    $html .= '<div class="ptl-dash-container">';

    // 1. Έλεγχος αν πατήθηκε το κουμπί Εξαργύρωσης
    if (isset($_POST['ptl_redeem_id'])) {
        $redeem_id = intval($_POST['ptl_redeem_id']);
        $wpdb->update(
            $promo_table, 
            array('status' => 'redeemed', 'redeemed_at' => current_time('mysql')), 
            array('id' => $redeem_id)
        );
        $html .= '<div style="background:#eef7ee; color:#5b9a68; padding:10px; border-radius:5px; margin-bottom:15px; border:1px solid #5b9a68; text-align:center; font-weight:bold;">Ο κωδικός διαγράφηκε (εξαργυρώθηκε) επιτυχώς!</div>';
    }

    // 2. Λήψη όλων των ενεργών κωδικών από τη βάση
    $active_codes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $promo_table WHERE partner_prefix = %s AND status = 'active' ORDER BY created_at DESC", 
        $prefix
    ));

    $html .= '<h3>📋 Λίστα Ενεργών Κωδικών (' . esc_html($prefix) . ')</h3>';
    $html .= '<p style="color:#666; font-size:14px; text-align:center;">Εδώ βλέπετε όλους τους πελάτες που έλαβαν κωδικό. Πατήστε "Εξαργύρωση" για να τον σβήσετε από τη λίστα.</p>';
    
    // Μπάρα αναζήτησης
    $html .= '<input type="text" id="ptl-search-input" class="ptl-search-bar" placeholder="🔍 Αναζήτηση με email, κωδικό ή ημερομηνία..." onkeyup="ptlFilterTable()">';

    // 3. Εμφάνιση του Πίνακα
    if (empty($active_codes)) {
        $html .= '<p style="text-align:center; padding:30px; background:#fff; border-radius:8px;">Δεν υπάρχουν ενεργοί κωδικοί αυτή τη στιγμή.</p>';
    } else {
        $html .= '<table class="ptl-table" id="ptl-codes-table">';
        $html .= '<thead><tr><th>Κωδικός</th><th>Email Πελάτη</th><th>Ημερομηνία</th><th>Ενέργεια</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($active_codes as $row) {
            $date = date('d/m/Y H:i', strtotime($row->created_at));
            $html .= '<tr>';
            $html .= '<td style="font-weight:bold; color:#43282F;">' . esc_html($row->coupon_code) . '</td>';
            $html .= '<td>' . esc_html($row->email) . '</td>';
            $html .= '<td style="font-size:13px; color:#777;">' . $date . '</td>';
            $html .= '<td>
                        <form method="post" style="margin:0;" onsubmit="return confirm(\'Σίγουρα θέλετε να σβήσετε (εξαργυρώσετε) αυτόν τον κωδικό;\');">
                            <input type="hidden" name="ptl_redeem_id" value="' . $row->id . '">
                            <button type="submit" class="ptl-btn-redeem">✔️ Εξαργύρωση</button>
                        </form>
                      </td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    // JS για τη Ζωντανή Αναζήτηση
    $html .= '<script>
    function ptlFilterTable() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("ptl-search-input");
        filter = input.value.toUpperCase();
        table = document.getElementById("ptl-codes-table");
        if(!table) return;
        tr = table.getElementsByTagName("tr");
        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length - 1; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }
    }
    </script>';
    
    $html .= '</div>';
    return $html;
}

// =========================================================================
// 1. ΕΛΕΓΧΟΣ ΚΟΥΠΟΝΙΟΥ ΚΑΙ ΜΕΓΑΛΟ, ΓΕΝΙΚΟ ΜΗΝΥΜΑ ΣΦΑΛΜΑΤΟΣ
// =========================================================================
// newsletter - welcome10 - user
add_action( 'woocommerce_after_checkout_validation', 'ptl_restrict_coupon_by_strict_identity', 10, 2 );

function ptl_restrict_coupon_by_strict_identity( $data, $errors ) {
    // ΒΑΛΕ ΕΔΩ ΤΟ ΟΝΟΜΑ ΤΟΥ ΚΟΥΠΟΝΙΟΥ ΣΟΥ (με μικρά γράμματα)
    $newsletter_coupon = 'welcome10'; 

    $applied_coupons = WC()->cart->get_applied_coupons();
    
    if ( ! in_array( $newsletter_coupon, $applied_coupons ) ) {
        return;
    }

    global $wpdb;

    $address   = isset( $data['billing_address_1'] ) ? strtolower( trim( $data['billing_address_1'] ) ) : '';
    $last_name = isset( $data['billing_last_name'] ) ? strtolower( trim( $data['billing_last_name'] ) ) : '';
    $raw_phone = isset( $data['billing_phone'] ) ? preg_replace( '/[^0-9]/', '', $data['billing_phone'] ) : '';
    $phone_10  = strlen( $raw_phone ) >= 10 ? substr( $raw_phone, -10 ) : '';

    $is_duplicate = false;

    // Έλεγχος Α: Ίδια Διεύθυνση + Ίδιο Επίθετο
    if ( ! empty( $address ) && ! empty( $last_name ) ) {
        $match_address_name = $wpdb->get_var( $wpdb->prepare( "
            SELECT p.ID FROM {$wpdb->prefix}posts AS p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}postmeta AS pm_address ON p.ID = pm_address.post_id AND pm_address.meta_key = '_billing_address_1'
            INNER JOIN {$wpdb->prefix}postmeta AS pm_lastname ON p.ID = pm_lastname.post_id AND pm_lastname.meta_key = '_billing_last_name'
            WHERE p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
            AND oi.order_item_type = 'coupon'
            AND oi.order_item_name = %s
            AND LOWER(pm_address.meta_value) = %s
            AND LOWER(pm_lastname.meta_value) = %s
            LIMIT 1
        ", $newsletter_coupon, $address, $last_name ) );

        if ( $match_address_name ) { $is_duplicate = true; }
    }

    // Έλεγχος Β: Ίδιο Τηλέφωνο
    if ( ! $is_duplicate && ! empty( $phone_10 ) ) {
        $match_phone = $wpdb->get_var( $wpdb->prepare( "
            SELECT p.ID FROM {$wpdb->prefix}posts AS p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}postmeta AS pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
            WHERE p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
            AND oi.order_item_type = 'coupon'
            AND oi.order_item_name = %s
            AND pm_phone.meta_value LIKE %s
            LIMIT 1
        ", $newsletter_coupon, '%' . $wpdb->esc_like( $phone_10 ) . '%' ) );

        if ( $match_phone ) { $is_duplicate = true; }
    }

    // Αν πιαστεί από οποιονδήποτε έλεγχο, βγάζουμε το γενικό μήνυμα
    if ( $is_duplicate ) {
        $error_message = '<span class="ptl-coupon-error" style="font-size: 18px; font-weight: bold; line-height: 1.5; display: block;">Η έκπτωση νέας εγγραφής έχει ήδη αξιοποιηθεί.<br>Παρακαλώ αφαιρέστε το κουπόνι για να μπορέσετε να ολοκληρώσετε την πληρωμή σας.</span>';
        $errors->add( 'coupon_error', $error_message );
    }
}

// =========================================================================
// 2. ΕΜΦΑΝΙΣΗ ΤΟΥ ΜΗΝΥΜΑΤΟΣ ΚΑΤΩ ΑΠΟ ΤΟ ΚΟΥΜΠΙ ΠΛΗΡΩΜΗΣ
// =========================================================================
add_action('woocommerce_review_order_after_submit', 'ptl_add_error_box_under_button');
function ptl_add_error_box_under_button() {
    // Δημιουργούμε ένα κρυφό κουτί κάτω από το κουμπί
    echo '<div id="ptl-bottom-error-box" style="display:none; margin-top:15px; padding:15px; background-color:#ffe6e6; border-left:4px solid #d63638; color:#d63638; border-radius:4px;"></div>';
}

add_action('wp_footer', 'ptl_checkout_error_js_script');
function ptl_checkout_error_js_script() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Μόλις το WooCommerce βγάλει error στο checkout...
            $(document.body).on('checkout_error', function() {
                // Ψάχνουμε αν μέσα στα errors υπάρχει το δικό μας "ptl-coupon-error"
                var couponError = $('.woocommerce-error .ptl-coupon-error').html();
                
                if (couponError) {
                    // Αν υπάρχει, το εμφανίζουμε ΚΑΙ κάτω από το κουμπί της πληρωμής
                    $('#ptl-bottom-error-box').html(couponError).slideDown();
                } else {
                    $('#ptl-bottom-error-box').slideUp();
                }
            });
        });
        </script>
        <?php
    }
}