<?php
/**
 * Plugin Name: Custom Shopify Products API
 * Description: Προσφέρει REST API endpoints για εμφάνιση και μαζική ανάκτηση προϊόντων από Shopify με φίλτρα.
 * Version: 1.7
 * Author: Georgiana
 */

add_action('rest_api_init', function () {
    register_rest_route('shopify/v1', '/products', [
        'methods' => 'GET',
        'callback' => 'get_filtered_shopify_products_data',
        'permission_callback' => '__return_true',
    ]);

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

function get_filtered_shopify_products_data($request) {
    load_env_variables();

    $shopify_access_token = $_ENV['SHOPIFY_TOKEN'] ?? '';
    $shopify_store = $_ENV['SHOPIFY_STORE'] ?? '';

    if (empty($shopify_access_token) || empty($shopify_store)) {
        return new WP_Error('shopify_env_missing', '🚫 Το .env δεν φορτώθηκε σωστά ή λείπουν μεταβλητές.', ['status' => 500]);
    }

    $force_refresh = boolval($request->get_param('force_refresh') ?? false);
    // ---- ΚΡΙΣΙΜΗ ΑΛΛΑΓΗ ΓΙΑ ΤΟ UPDATE ----
    // Ελέγχουμε αν η κλήση είναι για import ή update
    $context = $request->get_param('context') ?? 'import';

    // --- Fetch raw products ---
    $raw_products = [];
    $url = "https://{$shopify_store}/admin/api/2024-07/products.json?limit=250&fields=id,title,body_html,status,variants,images";

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
        $raw_products = array_merge($raw_products, $products);

        $headers = wp_remote_retrieve_headers($response);
        $link_header = $headers['link'] ?? $headers['Link'] ?? '';
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $link_header, $matches)) {
            $url = $matches[1];
        } else {
            $url = null;
        }
    }

    // --- Lightweight products ---
    $lightweight_products = [];
    foreach ($raw_products as $product) {
        $variant = $product['variants'][0] ?? null;
        if (!$variant) continue;

        $image_urls = [];
        if (!empty($product['images']) && is_array($product['images'])) {
            foreach ($product['images'] as $img) {
                if (!empty($img['src'])) $image_urls[] = $img['src'];
            }
        }

        $lightweight_products[] = [
            'id'       => $product['id'] ?? null,
            'title'    => $product['title'] ?? '',
            'description' => $product['body_html'] ?? '',
            'sku'      => $variant['sku'] ?? '',
            'price'    => $variant['price'] ?? '',
            'compare_at_price' => $variant['compare_at_price'] ?? null,
            'status'   => $product['status'] ?? '',
            'inventory_quantity' => $variant['inventory_quantity'] ?? 0,
            'image_urls' => $image_urls,
        ];
    }

    // --- Φιλτράρισμα προϊόντων ---
    $filtered_products = [];
    foreach ($lightweight_products as $product) {
        if (empty($product['sku'])) continue;

        // ---- ΚΡΙΣΙΜΗ ΑΛΛΑΓΗ ΓΙΑ ΤΟ UPDATE ----
        // Εφαρμόζουμε το φίλτρο ύπαρξης ΜΟΝΟ κατά την εισαγωγή (import)
        if ($context === 'import') {
            $existing_product_id = wc_get_product_id_by_sku($product['sku']);
            if ($existing_product_id && get_post_status($existing_product_id) !== 'trash') {
                continue; // Αν υπάρχει ήδη, το αγνοούμε
            }
        }

        // Αυτά τα φίλτρα εφαρμόζονται πάντα (και στο import και στο update)
        if (empty($product['title'])) continue;
        if (floatval($product['price']) <= 0) continue;
        if ($product['status'] !== 'active') continue;

        $filtered_products[] = $product;
    }

    // Pagination response
    $page = max(1, intval($request->get_param('page') ?? 1));
    $per_page = intval($request->get_param('per_page') ?? 100);
    $offset = ($page - 1) * $per_page;
    $paged_products = array_slice($filtered_products, $offset, $per_page);

    return rest_ensure_response([
        'products' => $paged_products,
        'total'    => count($filtered_products),
        'page'     => $page,
        'per_page' => $per_page,
        'has_more' => ($offset + $per_page) < count($filtered_products),
    ]);
}

function get_shopify_products_by_ids($request) {
    // This function is not changed
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
    return rest_ensure_response([]);
}

function load_env_variables($path = null) {
    if (!$path) $path = dirname(__DIR__, 3) . '/.env';
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}