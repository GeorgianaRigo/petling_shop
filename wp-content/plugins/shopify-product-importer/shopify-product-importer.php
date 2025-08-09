<?php
/**
 * Plugin Name: Shopify Product Importer
 * Description: Εισαγωγή και ενημέρωση προϊόντων από το Custom Shopify Products API plugin σε batches με Ajax.
 * Version: 1.8
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
        <button id="start-update" class="button" style="margin-left:10px;">Ενημέρωση Προϊόντων</button>
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

    if (!class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce plugin is not active']);
    }

    global $wpdb;

    $products = $_POST['products'];
    $imported = 0;
    $errors = [];

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    foreach ($products as $product) {
        $raw_sku = $product['sku'] ?? '';
        $sku = strtolower(trim(sanitize_text_field($raw_sku)));
        $price = floatval($product['price'] ?? 0);
        $inventory = intval($product['inventory_quantity'] ?? 0);
        $title = sanitize_text_field($product['title'] ?? '');
        $description = sanitize_textarea_field($product['body_html'] ?? '');

        if (!$sku) {
            $errors[] = "Παραλείφθηκε προϊόν με άγνωστο SKU.";
            continue;
        }

        // Σφιχτός έλεγχος διπλοεγγραφής μέσω SQL
        $existing_product_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND LOWER(meta_value) = %s LIMIT 1",
            $sku
        ));

        if ($existing_product_id) {
            $errors[] = "Παραλείφθηκε διπλότυπο SKU: {$sku} (ID προϊόντος: {$existing_product_id})";
            continue;
        }

        if (!$title || $price <= 0) {
            $errors[] = "Παραλείφθηκε προϊόν χωρίς τίτλο ή με μη έγκυρη τιμή.";
            continue;
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'draft',
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

        if (is_array($image_urls)) {
            foreach ($image_urls as $index => $image_url) {
                $image_url = esc_url_raw($image_url);
                if (!$image_url) {
                    continue;
                }
                $media = media_sideload_image($image_url, $post_id, null, 'id');
                if (!is_wp_error($media)) {
                    $attachment_ids[] = $media;
                }
            }
        }

        if (!empty($attachment_ids)) {
            set_post_thumbnail($post_id, $attachment_ids[0]);
            if (count($attachment_ids) > 1) {
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

// Νέο AJAX action για update batch
add_action('wp_ajax_shopify_update_batch', 'shopify_update_batch');
function shopify_update_batch() {
    check_ajax_referer('shopify_import_nonce', 'nonce');

    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }

    if (!class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce plugin is not active']);
    }

    global $wpdb;

    $products = $_POST['products'];
    $updated = 0;
    $errors = [];

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    foreach ($products as $product) {
        $raw_sku = $product['sku'] ?? '';
        $sku = strtolower(trim(sanitize_text_field($raw_sku)));
        $price = floatval($product['price'] ?? 0);
        $inventory = intval($product['inventory_quantity'] ?? 0);
        $title = sanitize_text_field($product['title'] ?? '');
        $description = sanitize_textarea_field($product['body_html'] ?? '');

        if (!$sku) {
            $errors[] = "Παραλείφθηκε προϊόν με άγνωστο SKU.";
            continue;
        }

        // Βρες αν υπάρχει ήδη προϊόν με αυτό το SKU
        $existing_product_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND LOWER(meta_value) = %s LIMIT 1",
            $sku
        ));

        if (!$existing_product_id) {
            $errors[] = "Δεν βρέθηκε προϊόν με SKU: {$sku} για ενημέρωση.";
            continue;
        }

        if (!$title || $price <= 0) {
            $errors[] = "Παραλείφθηκε προϊόν χωρίς τίτλο ή με μη έγκυρη τιμή.";
            continue;
        }

        // Ενημέρωση post
        $update_result = wp_update_post([
            'ID'           => $existing_product_id,
            'post_title'   => $title,
            'post_content' => $description,
        ], true);

        if (is_wp_error($update_result)) {
            $errors[] = "❌ Αποτυχία ενημέρωσης: {$title}";
            continue;
        }

        // Ενημέρωση μεταδεδομένων
        update_post_meta($existing_product_id, '_price', $price);
        update_post_meta($existing_product_id, '_regular_price', $price);
        update_post_meta($existing_product_id, '_stock', $inventory);
        update_post_meta($existing_product_id, '_manage_stock', 'yes');
        update_post_meta($existing_product_id, '_stock_status', $inventory > 0 ? 'instock' : 'outofstock');

        // --- Ενημέρωση εικόνων ---
        $image_urls = $product['image_urls'] ?? [];
        $attachment_ids = [];

        if (is_array($image_urls)) {
            // Καθάρισε προηγούμενες εικόνες
            delete_post_thumbnail($existing_product_id);
            update_post_meta($existing_product_id, '_product_image_gallery', '');

            foreach ($image_urls as $index => $image_url) {
                $image_url = esc_url_raw($image_url);
                if (!$image_url) {
                    continue;
                }
                $media = media_sideload_image($image_url, $existing_product_id, null, 'id');
                if (!is_wp_error($media)) {
                    $attachment_ids[] = $media;
                }
            }
        }

        if (!empty($attachment_ids)) {
            set_post_thumbnail($existing_product_id, $attachment_ids[0]);
            if (count($attachment_ids) > 1) {
                $gallery_ids = array_slice($attachment_ids, 1);
                update_post_meta($existing_product_id, '_product_image_gallery', implode(',', $gallery_ids));
            }
        }
        // --- τέλος εικόνων ---

        $updated++;
    }

    wp_send_json_success([
        'updated' => $updated,
        'errors'  => $errors,
        'done'    => false,
    ]);
    wp_die();
}