<?php
/**
 * Plugin Name: Shopify Product Importer
 * Description: Εισαγωγή και ενημέρωση προϊόντων από το Custom Shopify Products API plugin σε batches με Ajax και cron.
 * Version: 1.9
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

add_filter('cron_schedules', function($schedules){
    $schedules['every_ten_minutes'] = [
        'interval' => 10 * 60,
        'display'  => __('Every 10 Minutes')
    ];
    return $schedules;
});

// Προγραμματισμός cron κατά ενεργοποίηση plugin
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('shopify_import_daily_event')) {
        wp_schedule_event(time(), 'daily', 'shopify_import_daily_event');
    }
    if (!wp_next_scheduled('shopify_update_every_10_minutes_event')) {
        wp_schedule_event(time(), 'every_ten_minutes', 'shopify_update_every_10_minutes_event');
    }
});

// Καθαρισμός cron κατά απενεργοποίηση plugin
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('shopify_import_daily_event');
    wp_clear_scheduled_hook('shopify_update_every_10_minutes_event');
});

// Cron hooks που καλούν τις αντίστοιχες λειτουργίες
add_action('shopify_import_daily_event', 'shopify_cron_import');
add_action('shopify_update_every_10_minutes_event', 'shopify_cron_update');

// --- Βοηθητική συνάρτηση για εισαγωγή batch ---
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
        $inventory = intval($product['inventory_quantity'] ?? 0);
        $title = sanitize_text_field($product['title'] ?? '');
        $description = sanitize_textarea_field($product['body_html'] ?? '');

        if (!$sku) {
            $errors[] = "Παραλείφθηκε προϊόν με άγνωστο SKU.";
            continue;
        }

        // Έλεγχος διπλοεγγραφής
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

        // Εισαγωγή εικόνων
        $image_urls = $product['image_urls'] ?? [];
        $attachment_ids = [];

        if (is_array($image_urls)) {
            foreach ($image_urls as $index => $image_url) {
                $image_url = esc_url_raw($image_url);
                if (!$image_url) continue;

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

        $imported++;
    }

    return ['success' => true, 'imported' => $imported, 'errors' => $errors];
}

// --- Βοηθητική συνάρτηση για ενημέρωση batch ---
function shopify_process_update_batch($products) {
    if (!class_exists('WooCommerce')) {
        return ['success' => false, 'message' => 'WooCommerce plugin is not active'];
    }

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
        $inventory = intval($product['inventory_quantity'] ?? 0);
        $title = sanitize_text_field($product['title'] ?? '');
        $description = sanitize_textarea_field($product['body_html'] ?? '');

        if (!$sku) {
            $errors[] = "Παραλείφθηκε προϊόν με άγνωστο SKU.";
            continue;
        }

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

        $update_result = wp_update_post([
            'ID'           => $existing_product_id,
            'post_title'   => $title,
            'post_content' => $description,
        ], true);

        if (is_wp_error($update_result)) {
            $errors[] = "❌ Αποτυχία ενημέρωσης: {$title}";
            continue;
        }

        update_post_meta($existing_product_id, '_price', $price);
        update_post_meta($existing_product_id, '_regular_price', $price);
        update_post_meta($existing_product_id, '_stock', $inventory);
        update_post_meta($existing_product_id, '_manage_stock', 'yes');
        update_post_meta($existing_product_id, '_stock_status', $inventory > 0 ? 'instock' : 'outofstock');

        // Ενημέρωση εικόνων
        $image_urls = $product['image_urls'] ?? [];
        $attachment_ids = [];

        if (is_array($image_urls)) {
            delete_post_thumbnail($existing_product_id);
            update_post_meta($existing_product_id, '_product_image_gallery', '');

            foreach ($image_urls as $index => $image_url) {
                $image_url = esc_url_raw($image_url);
                if (!$image_url) continue;

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

        $updated++;
    }

    return ['success' => true, 'updated' => $updated, 'errors' => $errors];
}

// --- AJAX για εισαγωγή batch ---
add_action('wp_ajax_shopify_import_batch', function() {
    check_ajax_referer('shopify_import_nonce', 'nonce');

    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }

    $result = shopify_process_import_batch($_POST['products']);

    if (!$result['success']) {
        wp_send_json_error(['message' => $result['message'] ?? 'Unknown error']);
    }

    wp_send_json_success([
        'imported' => $result['imported'],
        'errors'   => $result['errors'],
        'done'     => false,
    ]);
    wp_die();
});

// --- AJAX για ενημέρωση batch ---
add_action('wp_ajax_shopify_update_batch', function() {
    check_ajax_referer('shopify_import_nonce', 'nonce');

    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }

    $result = shopify_process_update_batch($_POST['products']);

    if (!$result['success']) {
        wp_send_json_error(['message' => $result['message'] ?? 'Unknown error']);
    }

    wp_send_json_success([
        'updated' => $result['updated'],
        'errors'  => $result['errors'],
        'done'    => false,
    ]);
    wp_die();
});

// --- Cron function για εισαγωγή ---
function shopify_cron_import() {
    // Φόρτωσε τα δεδομένα προϊόντων από το REST API ή από άλλη πηγή
    $products = shopify_fetch_products_for_import();

    if (!$products) {
        error_log('Shopify Importer Cron: Δεν βρέθηκαν προϊόντα για εισαγωγή');
        return;
    }

    $result = shopify_process_import_batch($products);

    if (!$result['success']) {
        error_log('Shopify Importer Cron: Σφάλμα εισαγωγής - ' . ($result['message'] ?? ''));
    } else {
        error_log("Shopify Importer Cron: Εισήχθησαν {$result['imported']} προϊόντα με " . count($result['errors']) . " σφάλματα.");
    }
}

// --- Cron function για ενημέρωση ---
function shopify_cron_update() {
    // Φόρτωσε τα δεδομένα προϊόντων από το REST API ή από άλλη πηγή
    $products = shopify_fetch_products_for_update();

    if (!$products) {
        error_log('Shopify Importer Cron: Δεν βρέθηκαν προϊόντα για ενημέρωση');
        return;
    }

    $result = shopify_process_update_batch($products);

    if (!$result['success']) {
        error_log('Shopify Importer Cron: Σφάλμα ενημέρωσης - ' . ($result['message'] ?? ''));
    } else {
        error_log("Shopify Importer Cron: Ενημερώθηκαν {$result['updated']} προϊόντα με " . count($result['errors']) . " σφάλματα.");
    }
}

// --- Βοηθητικές συναρτήσεις για φόρτωση προϊόντων ---
// Εδώ πρέπει να προσθέσεις τη λογική που φέρνει τα προϊόντα από το Custom Shopify Products API

function shopify_fetch_products_for_import() {
    // Παράδειγμα: κάνε GET request στο REST endpoint και επέστρεψε πίνακα προϊόντων
    $response = wp_remote_get(get_site_url(null, '/wp-json/shopify/v1/products?import=1'));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
        return false;
    }

    return $data;
}

function shopify_fetch_products_for_update() {
    // Παράδειγμα: κάνε GET request στο REST endpoint και επέστρεψε πίνακα προϊόντων
    $response = wp_remote_get(get_site_url(null, '/wp-json/shopify/v1/products?update=1'));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
        return false;
    }

    return $data;
}
