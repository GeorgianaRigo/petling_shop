<?php
/**
 * Plugin Name: Custom Shopify Products API
 * Description: Προσφέρει REST API endpoints για εμφάνιση και μαζική ανάκτηση προϊόντων από Shopify με φίλτρα.
 * Version: 1.2
 * Author: Georgiana
 */

// Δημιουργία REST API routes
add_action('rest_api_init', function () {
    // GET /products
    register_rest_route('shopify/v1', '/products', [
        'methods' => 'GET',
        'callback' => 'get_filtered_shopify_products_data',
        'permission_callback' => '__return_true',
    ]);

    // POST /products-by-ids
    register_rest_route('shopify/v1', '/products-by-ids', [
        'methods' => 'POST',
        'callback' => 'get_shopify_products_by_ids',
        'permission_callback' => '__return_true',
        'args' => [
            'ids' => [
                'required' => true,
                'type' => 'array',
                'items' => ['type' => 'integer'],
            ],
        ],
    ]);
});

/**
 * GET /products - Φέρνει όλα τα προϊόντα από Shopify με φίλτρα και cursor pagination
 */
function get_filtered_shopify_products_data() {
    load_env_variables();

    $shopify_access_token = $_ENV['SHOPIFY_TOKEN'] ?? '';
    $shopify_store = $_ENV['SHOPIFY_STORE'] ?? '';

    if (empty($shopify_access_token) || empty($shopify_store)) {
        return new WP_Error('shopify_env_missing', '🚫 Το .env δεν φορτώθηκε σωστά ή λείπουν μεταβλητές.', ['status' => 500]);
    }

    $all_products = [];
    $url = "https://{$shopify_store}/admin/api/2024-07/products.json?limit=250";

    while ($url) {
        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $shopify_access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('shopify_api_error', $response->get_error_message(), ['status' => 500]);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $products = $data['products'] ?? [];

        $all_products = array_merge($all_products, $products);

        // Αν υπάρχει επόμενο batch μέσω link header (cursor pagination)
        $headers = wp_remote_retrieve_headers($response);
        $link_header = $headers['link'] ?? $headers['Link'] ?? '';

        if (preg_match('/<([^>]+)>;\s*rel="next"/', $link_header, $matches)) {
            $url = $matches[1];
        } else {
            $url = null;
        }
    }

    // Φιλτράρισμα προϊόντων σύμφωνα με κριτήρια
    $filtered_products = [];

    foreach ($all_products as $product) {
        $variant = $product['variants'][0] ?? null;
        $sku = $variant['sku'] ?? '';
        $title = $product['title'] ?? '';
        $price = floatval($variant['price'] ?? 0);
        $inventory = intval($variant['inventory_quantity'] ?? 0);
        $status = $product['status'] ?? '';

        if (!$sku) continue;
        if (wc_get_product_id_by_sku($sku)) continue; // SKU υπάρχει ήδη στη βάση
        if (!$title) continue;
        if ($price <= 0) continue;
        if ($status !== 'active') continue;
        if ($inventory <= 0) continue;

        $filtered_products[] = $product;
    }

    return rest_ensure_response($filtered_products);
}

/**
 * POST /products-by-ids - Φέρνει συγκεκριμένα προϊόντα βάσει Shopify IDs
 */
function get_shopify_products_by_ids($request) {
    load_env_variables();

    $shopify_access_token = $_ENV['SHOPIFY_TOKEN'] ?? '';
    $shopify_store = $_ENV['SHOPIFY_STORE'] ?? '';
    $ids = $request->get_param('ids');

    if (empty($shopify_access_token) || empty($shopify_store)) {
        return new WP_Error('shopify_env_missing', '🚫 Το .env δεν φορτώθηκε σωστά ή λείπουν μεταβλητές.', ['status' => 500]);
    }

    if (empty($ids) || !is_array($ids)) {
        return new WP_Error('invalid_ids', '🚫 Πρέπει να παρέχονται έγκυρα IDs.', ['status' => 400]);
    }

    $results = [];

    foreach ($ids as $id) {
        $url = "https://{$shopify_store}/admin/api/2024-07/products/{$id}.json";

        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $shopify_access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $product = $data['product'] ?? null;

        if (!$product) continue;

        $variant = $product['variants'][0] ?? null;
        $sku = $variant['sku'] ?? '';
        $title = $product['title'] ?? '';
        $price = floatval($variant['price'] ?? 0);
        $inventory = intval($variant['inventory_quantity'] ?? 0);
        $status = $product['status'] ?? '';

        if (!$sku) continue;
        if (wc_get_product_id_by_sku($sku)) continue;
        if (!$title) continue;
        if ($price <= 0) continue;
        if ($status !== 'active') continue;
        if ($inventory <= 0) continue;

        $results[] = $product;
    }

    return rest_ensure_response($results);
}

/**
 * Φόρτωμα μεταβλητών από .env αρχείο (π.χ. SHOPIFY_STORE, SHOPIFY_TOKEN)
 */
function load_env_variables($path = null) {
    if (!$path) {
        // Υποθέτω το .env είναι 3 επίπεδα πάνω από το plugin
        $path = dirname(__DIR__, 3) . '/.env';
    }

    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Παράλειψη σχολίων
        if (strpos($line, '=') === false) continue;  // Παράλειψη αν δεν υπάρχει '='

        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}
