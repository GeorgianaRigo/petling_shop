<?php
/**
 * Plugin Name: Custom Shopify Products API
 * Description: Προσφέρει REST API endpoint για να εμφανίζει προϊόντα από Shopify.
 * Version: 1.0
 * Author: Georgiana
 * URL δοκιμής: http://localhost/petling_shop/wp-json/shopify/v1/products
 */

// Βασική συνάρτηση που καλεί το Shopify Admin API
function get_shopify_products_data() {
    // Cache key
    $cache_key = 'shopify_products_cache';

    // Προσπάθησε να πάρεις τα δεδομένα από την cache (transient)
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // Αν δεν υπάρχει cache, φόρτωσε μεταβλητές περιβάλλοντος
    load_env_variables();

    $shopify_access_token = $_ENV['SHOPIFY_TOKEN'] ?? '';
    $shopify_store = $_ENV['SHOPIFY_STORE'] ?? '';

    // print_r("🔍 Token loaded: " . ($shopify_access_token ? '✅' : '❌'));
    // print_r("🔍 Store loaded: " . $shopify_store);
    // print_r("📄 Env path used: " . dirname(__DIR__, 3) . '/.env');

    if (empty($shopify_access_token) || empty($shopify_store)) {
        return new WP_Error('shopify_env_missing', '🚫 Το .env δεν φορτώθηκε σωστά ή λείπουν μεταβλητές.', ['status' => 500]);
    }

    $url = "https://$shopify_store/admin/api/2024-07/products.json?limit=50";
    print_r("🌐 Shopify request URL: $url");

    $response = wp_remote_get($url, [
        'headers' => [
            'X-Shopify-Access-Token' => $shopify_access_token,
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        // print_r('🛑 Shopify API error: ' . $response->get_error_message());
        return new WP_Error('shopify_api_error', $response->get_error_message(), ['status' => 500]);
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

// Συνάρτηση για φόρτωμα .env αρχείου
function load_env_variables($path = null) {
    if (!$path) {
        $path = dirname(__DIR__, 3) . '/.env'; // 3 επίπεδα πάνω από plugin → WordPress root
    }

    if (!file_exists($path)) {
        print_r("⚠ Δεν βρέθηκε το .env αρχείο: $path");
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // σχόλια
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}
