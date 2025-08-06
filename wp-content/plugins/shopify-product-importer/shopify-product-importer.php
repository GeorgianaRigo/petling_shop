<?php
/**
 * Plugin Name: Shopify Product Importer
 * Description: Εισαγωγή προϊόντων από το Custom Shopify Products API plugin σε batches με Ajax.
 * Version: 1.6
 * Author: Georgiana
 */

add_action('admin_menu', function () {
    add_menu_page('Shopify Importer', 'Shopify Importer', 'manage_options', 'shopify-importer', 'render_shopify_importer_page');
});

function render_shopify_importer_page() {
    ?>
    <div class="wrap">
        <h1>🛍️ Shopify Importer</h1>
        <button id="start-import" class="button button-primary">Ξεκίνα Εισαγωγή</button>
        <div id="progress" style="margin-top: 20px;"></div>
    </div>
    <?php
    wp_enqueue_script('shopify-import-js', plugin_dir_url(__FILE__) . 'shopify-import-ajax.js', ['jquery'], null, true);
    wp_localize_script('shopify-import-js', 'shopifyImportAjax', [
        'ajax_url'  => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('shopify_import_nonce'),
        'rest_url'  => get_site_url(null, '/wp-json/shopify/v1/products'),
    ]);
}

add_action('wp_ajax_shopify_import_batch', 'shopify_import_batch');
function shopify_import_batch() {
    check_ajax_referer('shopify_import_nonce', 'nonce');

    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }

    $products = $_POST['products'];
    $imported = 0;
    $errors = [];

    foreach ($products as $product) {
        $variant = $product['variants'][0] ?? null;
        $sku = $variant['sku'] ?? '';
        $price = floatval($variant['price'] ?? 0);
        $inventory = intval($variant['inventory_quantity'] ?? 0);
        $title = $product['title'] ?? '';
        $description = $product['body_html'] ?? '';

        if (!$sku || wc_get_product_id_by_sku($sku)) {
            $errors[] = "Παραλείφθηκε SKU: " . ($sku ?: 'Άγνωστο');
            continue;
        }

        if (!$title || $price <= 0) {
            $errors[] = "Παραλείφθηκε προϊόν χωρίς τίτλο ή με μη έγκυρη τιμή.";
            continue;
        }

        $post_id = wp_insert_post([
            'post_title'   => wp_strip_all_tags($title),
            'post_content' => wp_kses_post($description),
            'post_status'  => 'publish',
            'post_type'    => 'product',
        ]);

        if (is_wp_error($post_id) || !$post_id) {
            $errors[] = "❌ Αποτυχία εισαγωγής: {$title}";
            continue;
        }

        wp_set_object_terms($post_id, 'simple', 'product_type');

        update_post_meta($post_id, '_sku', sanitize_text_field($sku));
        update_post_meta($post_id, '_price', $price);
        update_post_meta($post_id, '_regular_price', $price);
        update_post_meta($post_id, '_stock', $inventory);
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_stock_status', $inventory > 0 ? 'instock' : 'outofstock');

        if (!empty($product['image']['src'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $image_url = esc_url_raw($product['image']['src']);
            $image_id = media_sideload_image($image_url, $post_id, $title, 'id');
            if (!is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
            }
        }

        $imported++;
    }

    wp_send_json_success([
        'imported' => $imported,
        'errors'   => $errors,
        'done'     => false,
    ]);
}
