<?php
/**
 * Plugin Name: Custom Shopify Products API
 * Description: Προσφέρει REST API endpoints για εμφάνιση και μαζική ανάκτηση προϊόντων από Shopify με φίλτρα.
 * Version: 1.3
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

    $cache_key = 'shopify_light_products_cache';
    $force_refresh = boolval($request->get_param('force_refresh') ?? false);
    $cached_products = get_transient($cache_key);

    if (!$force_refresh && $cached_products !== false) {
        $all_products = $cached_products;
    } else {
        $raw_products = [];
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

            $raw_products = array_merge($raw_products, $products);

            $headers = wp_remote_retrieve_headers($response);
            $link_header = $headers['link'] ?? $headers['Link'] ?? '';

            if (preg_match('/<([^>]+)>;\s*rel="next"/', $link_header, $matches)) {
                $url = $matches[1];
            } else {
                $url = null;
            }
        }

        // Εξαγωγή μόνο βασικών στοιχείων για caching
        $lightweight_products = [];

        foreach ($raw_products as $product) {
            $variant = $product['variants'][0] ?? null;
            if (!$variant) continue;

            // Πάρε όλα τα URLs των εικόνων
            $image_urls = [];
            if (!empty($product['images']) && is_array($product['images'])) {
                foreach ($product['images'] as $img) {
                    if (!empty($img['src'])) {
                        $image_urls[] = $img['src'];
                    }
                }
            }

            $lightweight_products[] = [
                'id'       => $product['id'] ?? null,
                'title'    => $product['title'] ?? '',
                'sku'      => $variant['sku'] ?? '',
                'price'    => $variant['price'] ?? '',
                'status'   => $product['status'] ?? '',
                'inventory_quantity' => $variant['inventory_quantity'] ?? 0,
                'image_urls' => $image_urls,
            ];
        }

        // Κράτα cache μόνο αν έχει περιεχόμενο
        if (!empty($lightweight_products)) {
            set_transient($cache_key, $lightweight_products, 300);
        }

        $all_products = $lightweight_products;
    }

    // Φιλτράρισμα
    $filtered_products = [];

    foreach ($all_products as $product) {
        if (empty($product['sku'])) continue;
        if (wc_get_product_id_by_sku($product['sku'])) continue;
        if (empty($product['title'])) continue;
        if (floatval($product['price']) <= 0) continue;
        if ($product['status'] !== 'active') continue;
        if (intval($product['inventory_quantity']) <= 0) continue;

        $filtered_products[] = $product;
    }

    // Pagination
    $page = max(1, intval($request->get_param('page') ?? 1));
    $per_page = intval($request->get_param('per_page') ?? 150);
    if ($per_page > 250) $per_page = 250;

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

        if (is_wp_error($response)) continue;

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

        // Πάρε όλα τα URLs εικόνων
        $image_urls = [];
        if (!empty($product['images']) && is_array($product['images'])) {
            foreach ($product['images'] as $img) {
                if (!empty($img['src'])) {
                    $image_urls[] = $img['src'];
                }
            }
        }

        // Πρόσθεσε τα URLs εικόνων στο προϊόν
        $product['image_urls'] = $image_urls;

        $results[] = $product;
    }

    return rest_ensure_response($results);
}

function load_env_variables($path = null) {
    if (!$path) {
        $path = dirname(__DIR__, 3) . '/.env';
    }

    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}
