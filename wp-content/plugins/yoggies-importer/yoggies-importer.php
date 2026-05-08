<?php
/**
 * Plugin Name: Yoggies XML Importer (Import & Update Split)
 * Description: Διαχωρισμός Εισαγωγής και Ενημέρωσης προϊόντων Yoggies (Batches & Cron).
 * Version: 5.3
 * Author: Georgiana
 */

if (!defined('ABSPATH')) exit;

const YOGGIES_XML_URL = 'https://yoggies.gr/wp-content/uploads/woo-feed/skroutz/xml/georgiana.xml';
const YOGGIES_SUPPLIER = 'Yoggies';

// ============================================================================
// ADMIN UI
// ============================================================================
add_action('admin_menu', function () {
    add_menu_page('Yoggies XML', 'Yoggies XML', 'manage_options', 'yoggies-importer', 'yoggies_render_admin_page', 'dashicons-update');
});

function yoggies_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>📦 Yoggies XML Importer</h1>
        
        <div style="background: #fff; border-left: 4px solid #00a0d2; padding: 12px; margin-bottom: 20px; margin-top: 15px;">
            <p style="margin: 0;"><strong>🔗 Πηγή:</strong> <code><?php echo esc_html(YOGGIES_XML_URL); ?></code></p>
        </div>

        <p>Επιλέξτε τη δουλειά που θέλετε να κάνετε:</p>
        
        <button id="yg-btn-import" class="button button-primary">Εισαγωγή Νέων (Draft)</button>
        <button id="yg-btn-update" class="button" style="margin-left:10px;">Ενημέρωση Υπαρχόντων</button>
        
        <div id="yg-status-bar" style="margin-top:20px; display:none; background:#fff; border:1px solid #ccc; padding:10px;">
            <div id="yg-progress-fill" style="background:#0073aa; height:20px; width:0%; transition:width 0.3s;"></div>
            <p id="yg-progress-text" style="margin:5px 0 0 0; font-weight:bold;"></p>
        </div>

        <div id="yg-log" style="margin-top:20px; border:1px solid #ccc; padding:15px; background:#f9f9f9; height:400px; overflow-y:auto; font-family:monospace; font-size:12px;">
            Περιμένω εντολή...
        </div>
    </div>
    <?php
    wp_enqueue_script('yoggies-xml-js', plugin_dir_url(__FILE__) . 'yoggies-import-ajax.js', ['jquery'], '5.3', true);
    wp_localize_script('yoggies-xml-js', 'ygVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('yg_xml_nonce')
    ]);
}

// ============================================================================
// AJAX LOGIC
// ============================================================================
add_action('wp_ajax_yg_init_xml', function() {
    check_ajax_referer('yg_xml_nonce', 'nonce');
    $response = wp_remote_get(YOGGIES_XML_URL, ['timeout' => 60]);
    if (is_wp_error($response)) wp_send_json_error(['message' => 'Αποτυχία λήψης XML.']);

    $xml = simplexml_load_string(wp_remote_retrieve_body($response));
    if (!$xml) wp_send_json_error(['message' => 'Μη έγκυρο XML.']);

    $products_data = [];
    foreach ($xml->products->product as $item) {
        $sku = trim((string)$item->mpn) ?: trim((string)$item->ean);
        if (!$sku) continue;

        $manufacturer = trim((string)$item->manufacturer);
        if (!$manufacturer) {
            $name_lower = strtolower((string)$item->name);
            if (strpos($name_lower, 'bailu') !== false) $manufacturer = 'Bailu';
            elseif (strpos($name_lower, 'sodapup') !== false) $manufacturer = 'SodaPup';
            else $manufacturer = 'Yoggies';
        }

        $products_data[] = [
            'sku'   => $sku,
            'name'  => (string)$item->name,
            'price' => (float)str_replace(',', '.', (string)$item->price_with_vat),
            'stock' => ((string)$item->instock === 'Y'),
            'img'   => (string)$item->image,
            'desc'  => (string)$item->description,
            'brand' => $manufacturer
        ];
    }
    set_transient('yg_pending_import', $products_data, 1 * HOUR_IN_SECONDS);
    wp_send_json_success(['total' => count($products_data)]);
});

add_action('wp_ajax_yg_process_batch', function() {
    check_ajax_referer('yg_xml_nonce', 'nonce');
    $offset = intval($_POST['offset']);
    $mode = $_POST['mode']; // 'import' ή 'update'
    $limit = 5; 
    
    $all_products = get_transient('yg_pending_import');
    if (!$all_products) wp_send_json_error(['message' => 'Session expired.']);

    $batch = array_slice($all_products, $offset, $limit);
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

    foreach ($batch as $data) {
        $sku = $data['sku'];
        $product_id = wc_get_product_id_by_sku($sku);

        try {
            if ($mode === 'update') {
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    $product->set_regular_price($data['price']);
                    $product->set_stock_status($data['stock'] ? 'instock' : 'outofstock');
                    $product->save();
                    if ($data['brand'] && taxonomy_exists('berocket_brand')) wp_set_object_terms($product_id, $data['brand'], 'berocket_brand', false);
                    $res['updated']++;
                } else {
                    $res['skipped']++;
                }
            } else { // Mode: Import
                if (!$product_id) {
                    $product = new WC_Product_Simple();
                    $product->set_name($data['name']);
                    $product->set_status('draft');
                    $product->set_sku($sku);
                    $product->set_regular_price($data['price']);
                    $product->set_description($data['desc']);
                    $product->set_stock_status($data['stock'] ? 'instock' : 'outofstock');
                    $product->update_meta_data('_supplier_name', YOGGIES_SUPPLIER);
                    $new_id = $product->save();

                    if ($data['img']) yoggies_upload_image_from_url($new_id, $data['img'], $sku);
                    if ($data['brand'] && taxonomy_exists('berocket_brand')) wp_set_object_terms($new_id, $data['brand'], 'berocket_brand', false);
                    $res['created']++;
                } else {
                    $res['skipped']++;
                }
            }
        } catch (Exception $e) { $res['errors'][] = "SKU {$sku}: " . $e->getMessage(); }
    }
    wp_send_json_success($res);
});

function yoggies_upload_image_from_url($product_id, $url, $sku) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $tmp = download_url($url);
    if (is_wp_error($tmp)) return;
    $file_array = ['name' => $sku . '.jpg', 'tmp_name' => $tmp];
    $id = media_handle_sideload($file_array, $product_id);
    if (!is_wp_error($id)) set_post_thumbnail($product_id, $id);
}

// (Το Cron παραμένει το ίδιο για να κάνει αυτόματο update/import στο παρασκήνιο)
add_action('yoggies_xml_cron_sync_event', 'yoggies_xml_run_automated_sync');
function yoggies_xml_run_automated_sync() {
    $response = wp_remote_get(YOGGIES_XML_URL);
    if (is_wp_error($response)) return;
    $xml = simplexml_load_string(wp_remote_retrieve_body($response));
    if (!$xml) return;
    foreach ($xml->products->product as $item) {
        $sku = trim((string)$item->mpn) ?: trim((string)$item->ean);
        $product_id = wc_get_product_id_by_sku($sku);
        $price = (float)str_replace(',', '.', (string)$item->price_with_vat);
        $instock = ((string)$item->instock === 'Y');
        if ($product_id) {
            $p = wc_get_product($product_id);
            $p->set_regular_price($price);
            $p->set_stock_status($instock ? 'instock' : 'outofstock');
            $p->save();
        } else {
            $p = new WC_Product_Simple();
            $p->set_name((string)$item->name); $p->set_status('draft'); $p->set_sku($sku);
            $p->set_regular_price($price); $p->set_stock_status($instock ? 'instock' : 'outofstock');
            $p->update_meta_data('_supplier_name', YOGGIES_SUPPLIER); $p->save();
        }
    }
}