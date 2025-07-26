<?php
/**
 * Plugin Name: Custom Shopify Product Importer
 * Description: Εισάγει τα 2 πρώτα προϊόντα από το Shopify API στο WooCommerce.
 * Version: 1.5
 * Author: ChatGPT
 */

add_action('admin_menu', function () {
    add_menu_page('Shopify Import', 'Shopify Import', 'manage_options', 'shopify-import', 'shopify_import_admin_page');
});

function shopify_import_admin_page() {
    echo '<div class="wrap"><h1>Εισαγωγή Προϊόντων από Shopify</h1>';

    if (isset($_POST['import_products'])) {
        list($imported_skus, $existing_skus) = shopify_import_products();

        if (!empty($imported_skus)) {
            echo '<div class="updated"><p>Τα 2 πρώτα προϊόντα εισήχθησαν!</p><ul>';
            foreach ($imported_skus as $item) {
                echo '<li>(SKU: <code>' . esc_html($item['sku']) . '</code>) <strong>: ' . esc_html($item['title']) . '</strong> </li><br>';
            }
            echo '</ul></div>';
        } else {
            echo '<div class="notice notice-warning"><p>⚠ Δεν εισήχθησαν προϊόντα. Ίσως υπάρχουν ήδη στο σύστημα.</p>';
            if (!empty($existing_skus)) {
                echo '<p>Τα παρακάτω SKU υπάρχουν ήδη:</p><ul>';
                foreach ($existing_skus as $sku) {
                    echo '<li><code>' . esc_html($sku) . '</code></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
    }

    echo '<form method="post">
            <input type="submit" name="import_products" class="button button-primary" value="Εισαγωγή 2 Προϊόντων">
        </form>
    </div>';
}

function shopify_import_products() {
    $response = wp_remote_get(home_url('/wp-json/shopify/v1/products'));

    if (is_wp_error($response)) {
        return [[], []];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data['products'])) {
        error_log('⚠ Shopify API response δεν περιέχει "products": ' . $body);
        return [[], []];
    }

    $products = array_slice($data['products'], 0, 2);
    $imported = [];
    $existing = [];

    foreach ($products as $product) {
        $title = $product['title'] ?? '';
        $description = $product['body_html'] ?? '';
        $sku = $product['variants'][0]['sku'] ?? '';
        $price = $product['variants'][0]['price'] ?? '';
        $inventory = $product['variants'][0]['inventory_quantity'] ?? 0;

        // Παίρνουμε το σωστό image URL
        $image_url = '';
        if (!empty($product['image']['src'])) {
            $image_url = $product['image']['src'];
        } elseif (!empty($product['images'][0]['src'])) {
            $image_url = $product['images'][0]['src'];
        }

        if (!$sku) continue;

        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            $existing[] = $sku;
            continue;
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_type'    => 'product',
        ]);

        if ($post_id) {
            update_post_meta($post_id, '_sku', $sku);
            update_post_meta($post_id, '_regular_price', $price);
            update_post_meta($post_id, '_price', $price);
            update_post_meta($post_id, '_stock_status', 'instock');
            update_post_meta($post_id, '_manage_stock', 'yes');
            update_post_meta($post_id, '_stock', $inventory);

            if (!empty($image_url)) {
                $image_id = shopify_import_image_from_url($image_url, $post_id);
                if ($image_id) {
                    set_post_thumbnail($post_id, $image_id);
                } else {
                    error_log("⚠ Δεν εισήχθη εικόνα για προϊόν SKU: $sku - URL: $image_url");
                }
            }

            $imported[] = [
                'sku' => $sku,
                'title' => $title,
                'post_id' => $post_id,
                'image_url' => $image_url,
            ];
        }
    }

    return [$imported, $existing];
}

function shopify_import_image_from_url($image_url, $post_id) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        error_log('⚠ Σφάλμα στο download_url: ' . $tmp->get_error_message());
        return false;
    }

    $file_array = [
        'name'     => basename(parse_url($image_url, PHP_URL_PATH)),
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($id)) {
        error_log('⚠ Σφάλμα στο media_handle_sideload: ' . $id->get_error_message());
        @unlink($tmp);
        return false;
    }

    return $id;
}
