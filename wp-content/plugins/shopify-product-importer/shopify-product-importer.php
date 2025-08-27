<?php
/**
 * Plugin Name: Shopify Product Importer
 * Description: Εισαγωγή και ενημέρωση προϊόντων από το Custom Shopify Products API plugin σε batches με Ajax και cron.
 * Version: 1.10
 * Author: Georgiana
 * με τιμες και περιγραφές
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

add_filter('cron_schedules', function($schedules){
    $schedules['every_ten_minutes'] = [
        'interval' => 10 * 60,
        'display'  => __('Every 10 Minutes')
    ];
    return $schedules;
});

// Cron activation
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('shopify_import_daily_event')) {
        wp_schedule_event(time(), 'daily', 'shopify_import_daily_event');
    }
    if (!wp_next_scheduled('shopify_update_every_10_minutes_event')) {
        wp_schedule_event(time(), 'every_ten_minutes', 'shopify_update_every_10_minutes_event');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('shopify_import_daily_event');
    wp_clear_scheduled_hook('shopify_update_every_10_minutes_event');
});

add_action('shopify_import_daily_event', 'shopify_cron_import');
add_action('shopify_update_every_10_minutes_event', 'shopify_cron_update');

// --- Fetch με περιγραφές σε chunks ---
function shopify_fetch_products_with_descriptions($batch_size = 50, $force_refresh = false) {

    // Καθαρισμός παλιών transient αν υπάρχει force refresh
    if ($force_refresh) {
        $i = 0;
        while (get_transient("shopify_light_products_cache_chunk_$i") !== false) {
            delete_transient("shopify_light_products_cache_chunk_$i");
            $i++;
        }
    }

    // Ανάκτηση από cache
    $all_products = [];
    $i = 0;
    while (($chunk = get_transient("shopify_light_products_cache_chunk_$i")) !== false) {
        $all_products = array_merge($all_products, $chunk);
        $i++;
    }
    if (!empty($all_products)) return $all_products;

    // Αν δεν υπάρχει cache, fetch από API
    $response = wp_remote_get(get_site_url(null, '/wp-json/shopify/v1/products?per_page=250'));
    if (is_wp_error($response)) return false;

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['products'])) return false;

    $all_products_raw = $data['products'];
    $results = [];

    $chunks = array_chunk($all_products_raw, $batch_size);
    foreach ($chunks as $idx => $chunk) {
        $ids = array_column($chunk, 'id');
        $resp = wp_remote_post(get_site_url(null, '/wp-json/shopify/v1/products-by-ids'), [
            'body' => json_encode(['ids' => $ids]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 60,
        ]);
        if (is_wp_error($resp)) continue;

        $body_chunk = wp_remote_retrieve_body($resp);
        $products_with_desc = json_decode($body_chunk, true);
        if (!is_array($products_with_desc)) continue;

        // Αποθήκευση κάθε chunk σε δικό του transient
        if (!empty($products_with_desc)) {
            set_transient("shopify_light_products_cache_chunk_$idx", $products_with_desc, 300);
        }

        $results = array_merge($results, $products_with_desc);
    }

    return $results;
}

function shopify_fetch_products_for_import() {
    return shopify_fetch_products_with_descriptions();
}

function shopify_fetch_products_for_update() {
    return shopify_fetch_products_with_descriptions();
}

// --- Εισαγωγή batch ---
function shopify_process_import_batch($products) {
    if (!class_exists('WooCommerce')) {
        return ['success' => false, 'message' => 'WooCommerce plugin is not active'];
    }

    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $imported = 0;
    $errors = [];

    foreach ($products as $product) {
        $raw_sku = $product['sku'] ?? '';
        $sku = strtolower(trim(sanitize_text_field($raw_sku)));
        $price = floatval($product['price'] ?? 0);
        $compare_at_price = floatval($product['compare_at_price'] ?? 0);
        $inventory = intval($product['inventory_quantity'] ?? 0);
        $title = sanitize_text_field($product['title'] ?? '');
        $description = $product['description'] ?? '';

        if (!$sku) { $errors[] = "Παραλείφθηκε προϊόν με άγνωστο SKU."; continue; }

        $existing_product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND LOWER(meta_value) = %s LIMIT 1", $sku
        ));
        if ($existing_product_id) { $errors[] = "Παραλείφθηκε διπλότυπο SKU: {$sku}"; continue; }

        if (!$title || $price <= 0) { $errors[] = "Παραλείφθηκε προϊόν χωρίς τίτλο ή με μη έγκυρη τιμή."; continue; }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'draft',
            'post_type'    => 'product',
        ]);
        if (is_wp_error($post_id) || !$post_id) { $errors[] = "❌ Αποτυχία εισαγωγής: {$title}"; continue; }

        wp_set_object_terms($post_id, 'simple', 'product_type');
        update_post_meta($post_id, '_sku', $sku);

        if ($compare_at_price > $price) {
            update_post_meta($post_id, '_regular_price', $compare_at_price);
            update_post_meta($post_id, '_sale_price', $price);
            update_post_meta($post_id, '_price', $price);
        } else {
            update_post_meta($post_id, '_regular_price', $price);
            update_post_meta($post_id, '_price', $price);
        }

        update_post_meta($post_id, '_stock', $inventory);
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_stock_status', $inventory > 0 ? 'instock' : 'outofstock');

        // --- Εισαγωγή εικόνων με ασφαλή τρόπο ---
        $image_urls = $product['image_urls'] ?? [];
        $attachment_ids = [];

        if (is_array($image_urls) && !empty($image_urls)) {
            foreach ($image_urls as $index => $image_url) {
                $image_url = esc_url_raw($image_url);
                if (!$image_url) continue;

                $tmp = download_url($image_url);
                if (is_wp_error($tmp)) {
                    $errors[] = "❌ Αποτυχία download εικόνας για SKU: {$sku}, URL: {$image_url} - " . $tmp->get_error_message();
                    continue;
                }

                $extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
                $filename = sanitize_file_name("{$sku}_{$index}.{$extension}");

                $file_array = [
                    'name' => $filename,
                    'tmp_name' => $tmp,
                ];

                $filetype = wp_check_filetype($file_array['name']);
                if (!$filetype['type']) {
                    @unlink($tmp);
                    $errors[] = "❌ Αποτυχία MIME type εικόνας για SKU: {$sku}, URL: {$image_url}";
                    continue;
                }

                $attach_id = media_handle_sideload($file_array, $post_id);
                if (is_wp_error($attach_id)) {
                    @unlink($tmp);
                    $errors[] = "❌ Αποτυχία εισαγωγής εικόνας για SKU: {$sku}, URL: {$image_url} - " . $attach_id->get_error_message();
                    continue;
                }

                $attachment_ids[] = $attach_id;
            }

            if (!empty($attachment_ids)) {
                set_post_thumbnail($post_id, $attachment_ids[0]);
                if (count($attachment_ids) > 1) {
                    update_post_meta($post_id, '_product_image_gallery', implode(',', array_slice($attachment_ids, 1)));
                }
            }
        }

        $imported++;
    }

    return ['success' => true, 'imported' => $imported, 'errors' => $errors];
}

// --- Ενημέρωση batch ---
function shopify_process_update_batch($products) {
    if (!class_exists('WooCommerce')) return ['success' => false, 'message' => 'WooCommerce plugin is not active'];

    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $updated = 0;
    $errors = [];

    foreach ($products as $product) {
        $raw_sku = $product['sku'] ?? '';
        $sku = strtolower(trim(sanitize_text_field($raw_sku)));
        $price = floatval($product['price'] ?? 0);
        $compare_at_price = floatval($product['compare_at_price'] ?? 0);
        $inventory = intval($product['inventory_quantity'] ?? 0);
        $title = sanitize_text_field($product['title'] ?? '');
        $description = $product['description'] ?? '';

        if (!$sku) { $errors[] = "Παραλείφθηκε προϊόν με άγνωστο SKU."; continue; }

        $existing_product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND LOWER(meta_value) = %s LIMIT 1", $sku
        ));
        if (!$existing_product_id) { $errors[] = "Δεν βρέθηκε προϊόν με SKU: {$sku} για ενημέρωση."; continue; }

        if (!$title || $price <= 0) { $errors[] = "Παραλείφθηκε προϊόν χωρίς τίτλο ή με μη έγκυρη τιμή."; continue; }

        // Ενημέρωση βασικών πεδίων
        $update_result = wp_update_post([
            'ID'           => $existing_product_id,
            'post_title'   => $title,
            'post_content' => $description,
        ], true);

        if (is_wp_error($update_result)) { $errors[] = "❌ Αποτυχία ενημέρωσης: {$title}"; continue; }

        // Τιμές και απόθεμα
        if ($compare_at_price > $price) {
            update_post_meta($existing_product_id, '_regular_price', $compare_at_price);
            update_post_meta($existing_product_id, '_sale_price', $price);
            update_post_meta($existing_product_id, '_price', $price);
        } else {
            update_post_meta($existing_product_id, '_regular_price', $price);
            update_post_meta($existing_product_id, '_price', $price);
        }

        update_post_meta($existing_product_id, '_stock', $inventory);
        update_post_meta($existing_product_id, '_manage_stock', 'yes');
        update_post_meta($existing_product_id, '_stock_status', $inventory > 0 ? 'instock' : 'outofstock');

        // --- Εισαγωγή εικόνων με ασφαλή τρόπο ---
        $image_urls = $product['image_urls'] ?? [];
        $attachment_ids = [];

        if (is_array($image_urls) && !empty($image_urls)) {
            // Καθαρισμός παλιάς featured και gallery εικόνας
            delete_post_thumbnail($existing_product_id);
            update_post_meta($existing_product_id, '_product_image_gallery', '');

            foreach ($image_urls as $index => $image_url) {
                $image_url = esc_url_raw($image_url);
                if (!$image_url) continue;

                $tmp = download_url($image_url);
                if (is_wp_error($tmp)) {
                    $errors[] = "❌ Αποτυχία download εικόνας για SKU: {$sku}, URL: {$image_url} - " . $tmp->get_error_message();
                    continue;
                }

                // Καθαρισμός ονόματος αρχείου: χρησιμοποιούμε SKU + index, χωρίς query string
                $extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
                $filename = sanitize_file_name("{$sku}_{$index}.{$extension}");

                $file_array = [
                    'name' => $filename,
                    'tmp_name' => $tmp,
                ];

                // Έλεγχος MIME type
                $filetype = wp_check_filetype($file_array['name']);
                if (!$filetype['type']) {
                    @unlink($tmp);
                    $errors[] = "❌ Αποτυχία MIME type εικόνας για SKU: {$sku}, URL: {$image_url}";
                    continue;
                }

                // Upload εικόνας
                $attach_id = media_handle_sideload($file_array, $existing_product_id);
                if (is_wp_error($attach_id)) {
                    @unlink($tmp);
                    $errors[] = "❌ Αποτυχία εισαγωγής εικόνας για SKU: {$sku}, URL: {$image_url} - " . $attach_id->get_error_message();
                    continue;
                }

                $attachment_ids[] = $attach_id;
            }

            // Αν έχουμε εικόνες, η πρώτη γίνεται featured, οι υπόλοιπες gallery
            if (!empty($attachment_ids)) {
                set_post_thumbnail($existing_product_id, $attachment_ids[0]);
                if (count($attachment_ids) > 1) {
                    update_post_meta($existing_product_id, '_product_image_gallery', implode(',', array_slice($attachment_ids, 1)));
                }
            }
        }

        $updated++;
    }

    return ['success' => true, 'updated' => $updated, 'errors' => $errors];
}

// --- AJAX εισαγωγή ---
add_action('wp_ajax_shopify_import_batch', function() {
    check_ajax_referer('shopify_import_nonce', 'nonce');
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }
    $result = shopify_process_import_batch($_POST['products']);
    wp_send_json_success(['imported' => $result['imported'], 'errors' => $result['errors'], 'done' => false]);
    wp_die();
});

// --- AJAX ενημέρωση ---
add_action('wp_ajax_shopify_update_batch', function() {
    check_ajax_referer('shopify_import_nonce', 'nonce');
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }
    $result = shopify_process_update_batch($_POST['products']);
    wp_send_json_success(['updated' => $result['updated'], 'errors' => $result['errors'], 'done' => false]);
    wp_die();
});
