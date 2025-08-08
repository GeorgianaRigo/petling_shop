<?php
/**
 * Plugin Name: Shopify Product Importer
 * Description: Εισαγωγή προϊόντων από το Custom Shopify Products API plugin σε batches με Ajax.
 * Version: 1.7
 * Author: Georgiana
 */

add_action('admin_menu', function () {
    add_menu_page('Shopify Importer', 'Shopify Importer', 'manage_options', 'shopify-importer', 'render_shopify_importer_page');
});

function render_shopify_importer_page() {
    ?>
    <div class="wrap">
        <h1>🏭️ Shopify Importer</h1>
        <button id="start-import" class="button button-primary">Ξεκίνα Εισαγωγή</button>
        <label style="margin-left:15px;">
            <input type="checkbox" id="force-refresh"> Εξαναγκασμένα επαναφορά (bypass cache)
        </label>
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

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    foreach ($products as $product) {
        $sku = sanitize_text_field($product['sku'] ?? '');
        $price = floatval($product['price'] ?? 0);
        $inventory = intval($product['inventory_quantity'] ?? 0);
        $title = sanitize_text_field($product['title'] ?? '');
        $description = sanitize_textarea_field($product['body_html'] ?? '');

        if (!$sku || wc_get_product_id_by_sku($sku)) {
            $errors[] = "Παραλείφθηκε SKU: " . ($sku ?: 'Άγνωστο');
            continue;
        }

        if (!$title || $price <= 0) {
            $errors[] = "Παραλείφθηκε προϊόν χωρίς τίτλο ή με μη έγκυρη τιμή.";
            continue;
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_type'    => 'product',
        ]);

        if (is_wp_error($post_id) || !$post_id) {
            $errors[] = "❌ Αποτυχία εισαγωγής: {$title}";
            continue;
        }

        wp_set_object_terms($post_id, 'simple', 'product_type');

        update_post_meta($post_id, '_sku', $sku);
        update_post_meta($post_id, '_price', $price);
        update_post_meta($post_id, '_regular_price', $price);
        update_post_meta($post_id, '_stock', $inventory);
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_stock_status', $inventory > 0 ? 'instock' : 'outofstock');

        // --- Εισαγωγή εικόνων ---
        $image_urls = $product['image_urls'] ?? [];
        $attachment_ids = [];

        foreach ($image_urls as $index => $image_url) {
            // Κατέβασε και πρόσθεσε ως attachment
            $media = media_sideload_image($image_url, $post_id, null, 'id');
            if (!is_wp_error($media)) {
                $attachment_ids[] = $media;
            }
        }

        if (!empty($attachment_ids)) {
            // Featured image = πρώτη εικόνα
            set_post_thumbnail($post_id, $attachment_ids[0]);

            // Gallery = οι υπόλοιπες εικόνες
            if (count($attachment_ids) > 1) {
                // Στο WooCommerce gallery αποθηκεύουμε ως comma-separated string
                $gallery_ids = array_slice($attachment_ids, 1);
                update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery_ids));
            }
        }
        // --- τέλος εικόνων ---

        $imported++;
    }

    wp_send_json_success([
        'imported' => $imported,
        'errors'   => $errors,
        'done'     => false,
    ]);
    wp_die();
}
