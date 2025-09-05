<?php
/**
 * Plugin Name: Shopify Product Importer
 * Description: Εισαγωγή και ενημέρωση προϊόντων από το Custom Shopify Products API plugin σε batches με Ajax και cron.
 * Version: 2.1
 * Author: Georgiana
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// --- Admin Menu & Scripts ---
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
            <input type="checkbox" id="force-refresh"> Εξαναγκασμένη επαναφορά (bypass cache)
        </label>
        <div id="progress" style="margin-top:20px; border: 1px solid #ccc; padding: 10px; background: #f9f9f9; height: 400px; overflow-y: scroll; white-space: pre-wrap; font-family: monospace;"></div>
    </div>
    <?php
    wp_enqueue_script('shopify-import-js', plugin_dir_url(__FILE__) . 'shopify-import-ajax.js', ['jquery'], '2.1', true);
    wp_localize_script('shopify-import-js', 'shopifyImportAjax', [
        'ajax_url'  => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('shopify_import_nonce'),
        'rest_url'  => get_site_url(null, '/wp-json/shopify/v1/products'),
    ]);
}


// --- ΒΕΛΤΙΩΣΗ: Ενοποιημένη συνάρτηση για την επεξεργασία δεδομένων προϊόντος ---
function _populate_wc_product_from_shopify_data(WC_Product $product, array $data) {
    $product->set_name(sanitize_text_field($data['title'] ?? ''));
    $product->set_description($data['description'] ?? '');
    
    // --- Τιμές ---
    $price = floatval($data['price'] ?? 0);
    $compare_at_price = floatval($data['compare_at_price'] ?? 0);

    if ($compare_at_price > $price) {
        $product->set_regular_price($compare_at_price);
        $product->set_sale_price($price);
    } else {
        $product->set_regular_price($price);
        $product->set_sale_price('');
    }
    
    // --- Απόθεμα ---
    $inventory = intval($data['inventory_quantity'] ?? 0);
    $product->set_manage_stock(true);
    $product->set_stock_quantity($inventory);
    $product->set_stock_status($inventory > 0 ? 'instock' : 'outofstock');
    
    return $product;
}

// --- ΒΕΛΤΙΩΣΗ: Ασφαλής διαχείριση εικόνων (v2.1) ---
function _handle_product_images(int $product_id, string $sku, array $image_urls) {
    if (empty($image_urls) || !is_array($image_urls)) {
        return [];
    }

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $errors = [];
    $new_attachment_ids = [];

    foreach ($image_urls as $index => $image_url) {
        $image_url = esc_url_raw($image_url);
        if (!$image_url) continue;

        // --- ΔΙΟΡΘΩΣΗ v2.1: Χειροκίνητος χειρισμός για καλύτερο έλεγχο και debugging ---
        $tmp = download_url($image_url, 15); // 15 δευτερόλεπτα timeout

        if (is_wp_error($tmp)) {
            $errors[] = "❌ Αποτυχία download εικόνας για SKU: {$sku}, URL: {$image_url} - " . $tmp->get_error_message();
            @unlink($tmp);
            continue;
        }

        // Δημιουργία καθαρού ονόματος αρχείου
        $file_name_part = sanitize_title($sku) . '-' . $index; // π.χ. 'we-0020-0'
        $extension = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (empty($extension)) $extension = 'jpg'; // Default extension
        $file_name = "{$file_name_part}.{$extension}";

        $file_array = [
            'name' => $file_name,
            'tmp_name' => $tmp
        ];

        $attach_id = media_handle_sideload($file_array, $product_id, get_the_title($product_id));

        if (is_wp_error($attach_id)) {
            $errors[] = "❌ Αποτυχία εισαγωγής εικόνας για SKU: {$sku}, URL: {$image_url} - " . $attach_id->get_error_message();
            @unlink($file_array['tmp_name']);
            continue;
        }
        
        $new_attachment_ids[] = $attach_id;
    }

    if (!empty($new_attachment_ids)) {
        $thumbnail_id = array_shift($new_attachment_ids);
        set_post_thumbnail($product_id, $thumbnail_id);

        if (!empty($new_attachment_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $new_attachment_ids));
        } else {
            delete_post_meta($product_id, '_product_image_gallery');
        }
    }
    
    return $errors;
}


// --- Εισαγωγή batch ---
function shopify_process_import_batch($products) {
    if (!class_exists('WooCommerce')) {
        return ['success' => false, 'message' => 'WooCommerce plugin is not active'];
    }

    $imported = 0;
    $errors = [];

    foreach ($products as $product_data) {
        $raw_sku = $product_data['sku'] ?? '';
        $sku = trim(sanitize_text_field($raw_sku));

        if (!$sku) {
            $errors[] = "Παραλείφθηκε προϊόν με άδειο SKU.";
            continue;
        }

        $existing_product_id = wc_get_product_id_by_sku($sku);
        if ($existing_product_id) {
            $errors[] = "Παραλείφθηκε: Το προϊόν με SKU '{$sku}' υπάρχει ήδη.";
            continue;
        }

        try {
            $new_product = new WC_Product_Simple();
            $new_product->set_status('publish');
            $new_product->set_sku($sku);

            $new_product = _populate_wc_product_from_shopify_data($new_product, $product_data);

            $product_id = $new_product->save();
            if (!$product_id) {
                throw new Exception("Η αποθήκευση του προϊόντος απέτυχε.");
            }

            $image_errors = _handle_product_images($product_id, $sku, $product_data['image_urls'] ?? []);
            if (!empty($image_errors)) {
                $errors = array_merge($errors, $image_errors);
            }

            $imported++;

        } catch (Exception $e) {
            $errors[] = "Σφάλμα εισαγωγής για SKU '{$sku}': " . $e->getMessage();
        }
    }

    return ['success' => true, 'imported' => $imported, 'errors' => $errors];
}


// --- Ενημέρωση batch ---
function shopify_process_update_batch($products) {
    if (!class_exists('WooCommerce')) return ['success' => false, 'message' => 'WooCommerce plugin is not active'];

    $updated = 0;
    $errors = [];

    foreach ($products as $product_data) {
        $raw_sku = $product_data['sku'] ?? '';
        $sku = trim(sanitize_text_field($raw_sku));

        if (!$sku) {
            $errors[] = "Παραλείφθηκε προϊόν ενημέρωσης με άδειο SKU.";
            continue;
        }

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            $errors[] = "Δεν βρέθηκε προϊόν με SKU: '{$sku}' για ενημέρωση.";
            continue;
        }

        try {
            $product_to_update = wc_get_product($product_id);
            if (!$product_to_update) {
                throw new Exception("Δεν ήταν δυνατή η φόρτωση του προϊόντος.");
            }

            $product_to_update = _populate_wc_product_from_shopify_data($product_to_update, $product_data);
            
            if (!$product_to_update->save()) {
                throw new Exception("Η αποθήκευση του προϊόντος απέτυχε.");
            }

            $image_errors = _handle_product_images($product_id, $sku, $product_data['image_urls'] ?? []);
            if (!empty($image_errors)) {
                $errors = array_merge($errors, $image_errors);
            }

            $updated++;
        } catch (Exception $e) {
            $errors[] = "Σφάλμα ενημέρωσης για SKU '{$sku}': " . $e->getMessage();
        }
    }

    return ['success' => true, 'updated' => $updated, 'errors' => $errors];
}


// --- AJAX Handlers ---
add_action('wp_ajax_shopify_import_batch', function() {
    check_ajax_referer('shopify_import_nonce', 'nonce');
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }
    $result = shopify_process_import_batch($_POST['products']);
    wp_send_json_success(['imported' => $result['imported'], 'errors' => $result['errors']]);
});

add_action('wp_ajax_shopify_update_batch', function() {
    check_ajax_referer('shopify_import_nonce', 'nonce');
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        wp_send_json_error(['message' => 'Missing or invalid products data']);
    }
    $result = shopify_process_update_batch($_POST['products']);
    wp_send_json_success(['updated' => $result['updated'], 'errors' => $result['errors']]);
});

// CRON functions... (rest of the file is the same)
add_filter('cron_schedules', function($schedules){
    $schedules['every_ten_minutes'] = [
        'interval' => 10 * 60,
        'display'  => __('Every 10 Minutes')
    ];
    return $schedules;
});

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



// --- ΠΡΟΣΩΡΙΝΟΣ ΔΙΑΓΝΩΣΤΙΚΟΣ ΚΩΔΙΚΑΣ ---
// Αφαιρέστε αυτόν τον κώδικα μετά τον έλεγχο
// add_action('admin_notices', function() {
//     // Το προβληματικό SKU που θέλουμε να ελέγξουμε
//     $sku_to_check = 'WE-0078';

//     // Χρησιμοποιούμε την ίδια ακριβώς συνάρτηση που χρησιμοποιεί το script
//     $product_id = wc_get_product_id_by_sku($sku_to_check);

//     echo '<div class="notice notice-info is-dismissible"><p><b>--- DIAGNOSTIC CHECK ---</b><br>';

//     if ($product_id) {
//         $product = wc_get_product($product_id);
//         if ($product) {
//             $product_type = $product->get_type();
//             echo "SKU: <b>{$sku_to_check}</b><br>";
//             echo "Result: <b>Product Found!</b><br>";
//             echo "Product ID: <b>{$product_id}</b><br>";
//             echo "Product Type: <b>{$product_type}</b>";
//         } else {
//             echo "SKU: <b>{$sku_to_check}</b><br>";
//             echo "Result: <b>Error!</b><br>";
//             echo "Found Product ID {$product_id}, but could not load WC_Product object.";
//         }
//     } else {
//         echo "SKU: <b>{$sku_to_check}</b><br>";
//         echo "Result: <b>Product NOT Found</b> by wc_get_product_id_by_sku().";
//     }

//     echo '</p></div>';
// });
// --- ΤΕΛΟΣ ΠΡΟΣΩΡΙΝΟΥ ΚΩΔΙΚΑ ---