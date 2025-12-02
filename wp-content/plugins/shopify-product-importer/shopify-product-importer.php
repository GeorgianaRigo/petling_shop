<?php
/**
 * Plugin Name: Shopify Product Importer
 * Description: Εισαγωγή και ενημέρωση προϊόντων από το Custom Shopify Products API plugin σε batches με Ajax και cron.
 * Version: 2.1
 * Author: Georgiana
 */

//TODO: να κάνουμε ένα νεο πεδίο όπως τον προηθευτή και να βάζουμε και εκεί τον τίτλο
 // και στο update να μην κάνουμε update οτον τίτλο του wordpress ούτε την περιγραφή
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

// Δημιουργία του πεδίου "Προμηθευτής"

/**
 * 1. Προσθέτει το πεδίο "Προμηθευτής" στην καρτέλα "Γενικά" των δεδομένων προϊόντος.
 */
add_action('woocommerce_product_options_general_product_data', 'add_supplier_custom_field_to_products');
function add_supplier_custom_field_to_products() {
    echo '<div class="options_group">';
    woocommerce_wp_text_input(array(
        'id'          => '_supplier_name', // Το όνομα του πεδίου στη βάση δεδομένων
        'label'       => __('Προμηθευτής', 'woocommerce'),
        'placeholder' => 'Εισάγετε το όνομα του προμηθευτή',
        'desc_tip'    => 'true',
        'description' => __('Το όνομα του προμηθευτή για αυτό το προϊόν.', 'woocommerce'),
    ));
    echo '</div>';
}

/**
 * Αποθηκεύει την τιμή του πεδίου "Προμηθευτής" όταν αποθηκεύεται το προϊόν.
 */
add_action('woocommerce_process_product_meta', 'save_supplier_custom_field_value');
function save_supplier_custom_field_value($post_id) {
    $product = wc_get_product($post_id);
    $supplier_name = isset($_POST['_supplier_name']) ? sanitize_text_field($_POST['_supplier_name']) : '';
    $product->update_meta_data('_supplier_name', $supplier_name);
    $product->save_meta_data();
}


function _populate_wc_product_from_shopify_data(WC_Product $product, array $data) {
    // Ενημερώνουμε Τίτλο και Περιγραφή μόνο αν είναι κενά 
    if (empty($product->get_name())) {
        $product->set_name(sanitize_text_field($data['title'] ?? ''));
    }
    if (empty($product->get_description())) {
        $product->set_description($data['description'] ?? '');
    }
    
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

    // Αυτόματη συμπλήρωση του πεδίου "Προμηθευτής"
    $supplier_name_to_set = 'Petmenu'; 
    $product->update_meta_data('_supplier_name', $supplier_name_to_set);
    
    return $product;
}

function _handle_product_images(int $product_id, string $sku, array $image_urls) {
    if (empty($image_urls) || !is_array($image_urls)) {
        return [];
    }

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $errors = [];
    $new_attachment_ids = [];
    
    // Περιορίζουμε τις εικόνες στην πρώτη (featured) αν τρέχει το cron
    if (!defined('DOING_AJAX') || DOING_AJAX === false) {
        $image_urls = array_slice($image_urls, 0, 1);
    }

    foreach ($image_urls as $index => $image_url) {
        $image_url = esc_url_raw($image_url);
        if (!$image_url) continue;

        $tmp = download_url($image_url, 15);

        if (is_wp_error($tmp)) {
            $errors[] = "❌ Αποτυχία download εικόνας για SKU: {$sku}, URL: {$image_url} - " . $tmp->get_error_message();
            @unlink($tmp);
            continue;
        }

        $file_name_part = sanitize_title($sku) . '-' . $index;
        $extension = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (empty($extension)) $extension = 'jpg';
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
            $new_product->set_status('draft');
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

            // ΒΕΛΤΙΣΤΟΠΟΙΗΣΗ: Εικόνες (Ενημέρωση μόνο αν δεν υπάρχουν)
            $current_thumbnail_id = get_post_thumbnail_id($product_id);
            
            if (empty($current_thumbnail_id)) {
                 $image_errors = _handle_product_images($product_id, $sku, $product_data['image_urls'] ?? []);
                 if (!empty($image_errors)) {
                     $errors = array_merge($errors, $image_errors);
                 }
            }
            
            $updated++;
        } catch (Exception $e) {
            $errors[] = "Σφάλμα ενημέρωσης για SKU '{$sku}': " . $e->getMessage();
        }
    }

    return ['success' => true, 'updated' => $updated, 'errors' => $errors];
}

// ΝΕΑ ΣΥΝΑΡΤΗΣΗ ΓΙΑ ΕΙΣΑΓΩΓΗ ΠΡΟΪΟΝΤΩΝ ΜΕ CRON
add_action('shopify_import_daily_event', 'shopify_cron_import_products_task');
function shopify_cron_import_products_task() {
    if (!class_exists('WooCommerce')) return;

    $page = 1;
    $has_more = true;
    $per_page = 50; 
    $log_errors = [];

    // Δεν χρησιμοποιούμε updated_at_min για την εισαγωγή,
    // καθώς θέλουμε να τραβήξουμε όλα τα προϊόντα του Shopify για να βρούμε τα νέα
    $start_time = current_time('mysql', 1);

    while ($has_more) {
        $rest_url = get_site_url(null, '/wp-json/shopify/v1/products');
        // ΣΗΜΑΝΤΙΚΟ: Χρησιμοποιούμε context=import
        $full_url = add_query_arg(['page' => $page, 'per_page' => $per_page, 'context' => 'import', 'force_refresh' => 1], $rest_url);
        
        $response = wp_remote_get($full_url, ['timeout' => 60]);
        
        if (is_wp_error($response)) {
            $log_errors[] = "CRON (IMPORT): Σφάλμα ανάκτησης δεδομένων από API (page: {$page}): " . $response->get_error_message();
            $has_more = false; 
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $products = $data['products'] ?? [];
        $has_more = $data['has_more'] ?? false;

        if (empty($products)) {
            $has_more = false;
            continue;
        }

        $result = shopify_process_import_batch($products);

        if (!empty($result['errors'])) {
            $log_errors = array_merge($log_errors, $result['errors']);
        }
        
        $page++;
    }
}


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

add_action('shopify_update_every_10_minutes_event', 'shopify_cron_update_products_task');
function shopify_cron_update_products_task() {
    if (!class_exists('WooCommerce')) return;

    $page = 1;
    $has_more = true;
    $per_page = 50; 
    $log_errors = [];

    $start_time = current_time('mysql', 1);

    while ($has_more) {
        $rest_url = get_site_url(null, '/wp-json/shopify/v1/products');
        $full_url = add_query_arg(['page' => $page, 'per_page' => $per_page, 'context' => 'update'], $rest_url);
        
        $response = wp_remote_get($full_url, ['timeout' => 60]);
        
        if (is_wp_error($response)) {
            $log_errors[] = "CRON: Σφάλμα ανάκτησης δεδομένων από API (page: {$page}): " . $response->get_error_message();
            $has_more = false; 
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $products = $data['products'] ?? [];
        $has_more = $data['has_more'] ?? false;

        if (empty($products)) {
            $has_more = false;
            continue;
        }

        $result = shopify_process_update_batch($products);

        if (!empty($result['errors'])) {
            $log_errors = array_merge($log_errors, $result['errors']);
        }
        
        $page++;
    }

    update_option('shopify_last_update_timestamp', $start_time);
}


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