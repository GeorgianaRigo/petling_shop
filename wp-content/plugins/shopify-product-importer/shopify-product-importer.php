<?php
/**
 * Plugin Name: Custom Shopify Product Importer with Batch
 * Description: Εισάγει ολα τα πρώτα 20 προϊόντα από το Shopify API στο WooCommerce σε batch των x, με όλους τους βασικούς ελέγχους.
 * 
 * - Το προϊόν πρέπει να έχει SKU (αν λείπει, απορρίπτεται).
 * - Το SKU δεν πρέπει να υπάρχει ήδη στη βάση (προστασία διπλοεγγραφής).
 * - Το προϊόν πρέπει να έχει τίτλο.
 * - Η τιμή πρέπει να είναι μεγαλύτερη από 0.
 * - Η κατάσταση του προϊόντος πρέπει να είναι 'active'.
 * - Το απόθεμα πρέπει να είναι μεγαλύτερο από 0.
 * 
 * Version: 1.6
 * Author: Georgiana
 */

// Προσθήκη admin menu
add_action('admin_menu', function () {
    add_menu_page('Shopify Batch Import', 'Shopify Batch Import', 'manage_options', 'shopify-batch-import', 'shopify_batch_import_page');
});

function shopify_batch_import_page() {
    echo '<div class="wrap"><h1>Εισαγωγή προϊόντων από Shopify (Batch)</h1>';

    if (isset($_POST['start_import'])) {
        // Παίρνουμε όλα τα προϊόντα και κρατάμε τα πρώτα 20
        // $products = array_slice(shopify_get_all_products(), 0, 20);
        
        $products = shopify_get_all_products();

        if (empty($products)) {
            echo '<p>Δεν βρέθηκαν προϊόντα από το Shopify API.</p>';
        } else {
            echo '<p>Συνολικά προς εισαγωγή: ' . count($products) . '</p>';

            // Αποθηκεύουμε προϊόντα σε transient για batch επεξεργασία
            set_transient('shopify_batch_products', $products, 3600);

            // Ξεκινάμε batch import από το 0
            echo '<form method="post">';
            echo '<input type="hidden" name="batch_index" value="0" />';
            echo '<input type="submit" name="do_batch" value="Ξεκίνησε εισαγωγή batch" class="button button-primary" />';
            echo '</form>';
        }
    }
    elseif (isset($_POST['do_batch'])) {
        $batch_index = intval($_POST['batch_index']);
        $batch_size = 150;

        $products = get_transient('shopify_batch_products');
        if (!$products) {
            echo '<p>Λήξη χρόνου ή δεν υπάρχουν προϊόντα για εισαγωγή.</p>';
            return;
        }

        $batch = array_slice($products, $batch_index * $batch_size, $batch_size);

        if (empty($batch)) {
            echo '<p>✅ Ολοκληρώθηκε η εισαγωγή όλων των προϊόντων!</p>';
            delete_transient('shopify_batch_products');
            return;
        }

        $imported = [];
        $errors = [];

        foreach ($batch as $product) {
            $result = shopify_import_single_product($product);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $imported[] = $result;
            }
        }

        echo '<p>🔁 Batch #' . ($batch_index + 1) . ' ολοκληρώθηκε.</p>';
        echo '<p>✅ Εισήχθησαν: <b>' . count($imported) . '</b> προϊόντα.</p>';

        if (!empty($errors)) {
            echo '<p><strong>⚠ Σφάλματα:</strong></p><ul>';
            foreach ($errors as $err) {
                echo '<li>' . esc_html($err) . '</li>';
            }
            echo '</ul>';
        }

        // Κουμπί για επόμενο batch
        echo '<form method="post">';
        echo '<input type="hidden" name="batch_index" value="' . ($batch_index + 1) . '" />';
        echo '<input type="submit" name="do_batch" value="Επόμενο batch" class="button button-primary" />';
        echo '</form>';
    }
    else {
        // Αρχικό κουμπί εκκίνησης εισαγωγής
        echo '<form method="post">';
        echo '<input type="submit" name="start_import" class="button button-primary" value="Ξεκίνησε εισαγωγή όλων των προϊόντων" />';
        echo '</form>';
    }

    echo '</div>';
}

// Φέρνει όλα τα προϊόντα από Shopify API
function shopify_get_all_products() {
    $response = wp_remote_get(home_url('/wp-json/shopify/v1/products'));

    if (is_wp_error($response)) {
        print_r('Shopify API error: ' . $response->get_error_message());
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data['products'])) {
        print_r('Shopify API response δεν περιέχει products ή είναι άκυρο.');
        return [];
    }

    return $data['products'];
}

// Εισαγωγή ενός προϊόντος (με ελέγχους)
function shopify_import_single_product($product) {
    $sku = $product['variants'][0]['sku'] ?? '';
    $title = $product['title'] ?? '';
    $price = $product['variants'][0]['price'] ?? 0;
    $inventory = $product['variants'][0]['inventory_quantity'] ?? 0;
    $status = $product['status'] ?? 'active';

    // Debug SKU συγκεκριμένο
    if ($sku === '0710025') {
        print_r("🔍 DEBUG SKU 0710025 – Τίτλος: $title | Τιμή: $price | Απόθεμα: $inventory | Κατάσταση: $status");
    }

    // Έλεγχοι
    if (!$sku) {
        return new WP_Error('missing_sku', 'Το προϊόν δεν έχει SKU.');
    }
    if (wc_get_product_id_by_sku($sku)) {
        return new WP_Error('sku_exists', "Το SKU $sku υπάρχει ήδη.");
    }
    if (!$title) {
        return new WP_Error('missing_title', "Το προϊόν SKU $sku δεν έχει τίτλο.");
    }
    if ($price <= 0) {
        return new WP_Error('invalid_price', "Το προϊόν SKU $sku έχει τιμή <= 0.");
    }
    if ($status !== 'active') {
        return new WP_Error('inactive_status', "Το προϊόν SKU $sku δεν είναι ενεργό.");
    }
    if ($inventory <= 0) {
        return new WP_Error('no_stock', "Το προϊόν SKU $sku δεν έχει απόθεμα.");
    }

    $post_id = wp_insert_post([
        'post_title' => $title,
        'post_content' => $product['body_html'] ?? '',
        'post_status' => 'publish',
        'post_type' => 'product',
    ]);

    if (!$post_id) {
        return new WP_Error('insert_failed', "Απέτυχε η εισαγωγή προϊόντος SKU $sku.");
    }

    update_post_meta($post_id, '_sku', $sku);
    update_post_meta($post_id, '_regular_price', $price);
    update_post_meta($post_id, '_price', $price);
    update_post_meta($post_id, '_stock_status', 'instock');
    update_post_meta($post_id, '_manage_stock', 'yes');
    update_post_meta($post_id, '_stock', $inventory);

    // --- Εικόνα ---
    $image_url = '';
    if (!empty($product['image']['src'])) {
        $image_url = $product['image']['src'];
    } elseif (!empty($product['images'][0]['src'])) {
        $image_url = $product['images'][0]['src'];
    }

    if ($sku === '0710025') {
        print_r("🖼️ SKU 0710025 – Εικόνα URL: " . ($image_url ?: 'Καμία διαθέσιμη'));
    }

    if ($image_url && shopify_is_image_url_valid_debug($image_url, $sku)) {
        $image_id = shopify_import_image_from_url($image_url, $post_id, $sku);

        if ($image_id) {
            set_post_thumbnail($post_id, $image_id);
            print_r("✅ SKU 0710025 – Τοποθετήθηκε εικόνα με ID $image_id");
        } else {
            print_r("❌ SKU 0710025 – Η εικόνα απέτυχε να τοποθετηθεί.");
        }
    } else {
        print_r("⚠️ SKU 0710025 – Δεν περάστηκε ο έλεγχος URL εικόνας.");
    }

    return $post_id;
}

// Έλεγχος URL εικόνας
function shopify_is_image_url_valid($url) {
    $response = wp_remote_head($url);
    if (is_wp_error($response)) return false;

    $code = wp_remote_retrieve_response_code($response);
    return ($code === 200);
}

// Εισαγωγή εικόνας από URL (download + sideload)
function shopify_import_image_from_url($image_url, $post_id, $sku = '') {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        error_log('Σφάλμα download_url: ' . $tmp->get_error_message());
        return false;
    }

    // Δημιουργούμε καθαρό όνομα αρχείου
    $filename = 'product-' . sanitize_file_name($sku) . '.jpg';

    $file_array = [
        'name'     => $filename,
        'tmp_name' => $tmp,
    ];

    // Τελική προσπάθεια για sideload
    $id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($id)) {
        error_log("❌ [$sku] media_handle_sideload ERROR: " . $id->get_error_message());
        @unlink($tmp);
        return false;
    }

    return $id;
}

function shopify_is_image_url_valid_debug($url, $sku = '') {
    $response = wp_remote_head($url);
    if (is_wp_error($response)) {
        print_r("❌ [$sku] wp_remote_head ERROR: " . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        print_r("⚠️ [$sku] Εικόνα URL επέστρεψε HTTP $code – $url");
    }

    return ($code === 200);
}

function shopify_import_image_from_url_debug($image_url, $post_id, $sku = '') {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        print_r("❌ [$sku] download_url ERROR: " . $tmp->get_error_message());
        return false;
    }

    $file_array = [
        'name' => basename(parse_url($image_url, PHP_URL_PATH)),
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($id)) {
        print_r("❌ [$sku] media_handle_sideload ERROR: " . $id->get_error_message());
        @unlink($tmp);
        return false;
    }

    return $id;
}
