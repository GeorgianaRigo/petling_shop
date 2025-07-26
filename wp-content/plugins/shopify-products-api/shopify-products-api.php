<?php
/**
 * Plugin Name: Custom Shopify Products API
 * Description: Προσφέρει REST API endpoint για να εμφανίζει προϊόντα από Shopify.
 * Version: 1.0
 * Author: ChatGPT
 */

// Βασική συνάρτηση που καλεί το Shopify Admin API
function get_shopify_products_data() {
    // Cache key
    $cache_key = 'shopify_products_cache';

    // Προσπάθησε να πάρεις τα δεδομένα από την cache (transient)
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached; // Αν υπάρχει cache, επέστρεψέ την
    }

    // Αν δεν υπάρχει cache, χτύπα το Shopify API
    $shop = 'petmenu.gr'; // όχι petmenu.gr
    $shopify_token = getenv('SHOPIFY_TOKEN');

    $response = wp_remote_get("https://$shop/admin/api/2024-07/products.json?limit=50", [
        'headers' => [
            'X-Shopify-Access-Token' => $access_token,
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('shopify_api_error', 'API error', ['status' => 500]);
    }

    $body = wp_remote_retrieve_body($response);
    $products = json_decode($body, true);

    // Αποθήκευση στην cache για 15 λεπτά (900 δευτερόλεπτα)
    set_transient($cache_key, $products, 900);

    return $products;
}


// Δημιουργία REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('shopify/v1', '/products', [
        'methods' => 'GET',
        'callback' => 'get_shopify_products_data',
        'permission_callback' => '__return_true', // Προσοχή: δημόσιο endpoint
    ]);
});
