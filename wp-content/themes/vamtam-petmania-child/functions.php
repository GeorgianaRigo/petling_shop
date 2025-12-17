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