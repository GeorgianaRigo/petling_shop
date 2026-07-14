<?php
/**
 * Plugin Name: Yoggies XML Importer (v5.16 - Fixed Stock Availability)
 * Description: Αυτόματη εισαγωγή και ενημέρωση προϊόντων Yoggies με πλήρη υποστήριξη για το βάρος (weight) και διορθωμένο έλεγχο in_stock.
 * Version: 5.16
 * Author: Georgiana
 */

if (!defined('ABSPATH')) exit;

const YOGGIES_XML_URL = 'https://yoggies.gr/wp-content/uploads/woo-feed/custom/xml/georgianav2.xml';
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
        <h1>📦 Yoggies XML Importer (v5.16)</h1>
        
        <div style="background: #fff; border-left: 4px solid #00a0d2; padding: 12px; margin-bottom: 20px; margin-top: 15px;">
            <p style="margin: 0;"><strong>🔗 Πηγή XML:</strong> <code><?php echo esc_html(YOGGIES_XML_URL); ?></code></p>
        </div>

        <p>Λειτουργία: <b>Full Sync</b> (Διορθωμένη ανάγνωση Custom Tabs JSON, Cron Images, Βάρους & Διαθεσιμότητας in_stock).</p>
        
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
    wp_enqueue_script('yoggies-xml-js', plugin_dir_url(__FILE__) . 'yoggies-import-ajax.js', ['jquery'], '5.4', true);
    wp_localize_script('yoggies-xml-js', 'ygVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('yg_xml_nonce')
    ]);
}

// ============================================================================
// HELPERS
// ============================================================================
function yoggies_parse_price($price_string) {
    $clean = str_replace([' EUR', ','], ['', '.'], (string)$price_string);
    return is_numeric($clean) ? (float)$clean : 0;
}

function yoggies_parse_weight($weight_string) {
    // Αφαιρεί το ' kg' ή οποιοδήποτε άλλο κείμενο και κρατάει μόνο τον αριθμό
    $clean = str_replace([' kg', 'kg', ' ', ','], ['', '', '', '.'], (string)$weight_string);
    return is_numeric($clean) ? (float)$clean : 0;
}

function yoggies_get_slug_from_link($link) {
    $url_path = parse_url((string)$link, PHP_URL_PATH);
    return sanitize_title(basename(rtrim($url_path, '/')));
}

function yoggies_extract_custom_tabs($json_string) {
    $result = ['composition' => '', 'dosage' => '', 'storage' => ''];
    if (empty($json_string)) return $result;

    $tabs = json_decode((string)$json_string, true);
    if (!is_array($tabs)) return $result;

    foreach ($tabs as $tab) {
        $title = isset($tab['title']) ? $tab['title'] : '';
        $content = wp_kses_post($tab['content']);

        if (mb_stripos($title, 'Συστατικά', 0, 'UTF-8') !== false || mb_stripos($title, 'Σύνθεση', 0, 'UTF-8') !== false) {
            $result['composition'] = $content;
        } elseif (mb_stripos($title, 'Δοσολογία', 0, 'UTF-8') !== false) {
            $result['dosage'] = $content;
        } elseif (mb_stripos($title, 'Αποθήκευση', 0, 'UTF-8') !== false) {
            $result['storage'] = $content;
        }
    }
    return $result;
}

// ============================================================================
// AJAX LOGIC
// ============================================================================
add_action('wp_ajax_yg_init_xml', function() {
    check_ajax_referer('yg_xml_nonce', 'nonce');
    $response = wp_remote_get(YOGGIES_XML_URL, ['timeout' => 60]);
    if (is_wp_error($response)) wp_send_json_error(['message' => 'Αποτυχία λήψης: ' . $response->get_error_message()]);

    $xml = simplexml_load_string(wp_remote_retrieve_body($response));
    if (!$xml) wp_send_json_error(['message' => 'Μη έγκυρο XML.']);

    $items = $xml->xpath('//product');
    if (empty($items)) wp_send_json_error(['message' => 'Δεν βρέθηκαν προϊόντα στο XML. Ελέγξτε τη δομή.']);

    $products_data = [];
    foreach ($items as $item) {
        $sku = trim((string)$item->sku) ?: trim((string)$item->part_number);
        if (!$sku) continue;

        $regular_price = yoggies_parse_price($item->price);
        $sale_price = yoggies_parse_price($item->sales_price);
        $weight = yoggies_parse_weight($item->weight);
        
        // ΔΙΟΡΘΩΣΗ: Έλεγχος και για "in_stock" και για "in stock"
        $availability = strtolower(trim((string)$item->availability));
        $is_in_stock = in_array($availability, ['in_stock', 'in stock']);

        $extracted_tabs = yoggies_extract_custom_tabs((string)$item->custom_tabs);

        $products_data[] = [
            'sku'        => $sku,
            'name'       => (string)$item->name,
            'slug'       => yoggies_get_slug_from_link($item->link),
            'reg_price'  => $regular_price,
            'sale_price' => ($sale_price > 0 && $sale_price < $regular_price) ? $sale_price : '',
            'weight'     => $weight,
            'stock'      => $is_in_stock,
            'img'        => (string)$item->image_link,
            'desc'       => (string)$item->html_description, 
            'short_desc' => (string)$item->short_description,
            'brand'      => trim((string)$item->manufacturer) ?: 'Yoggies',
            'composition'=> $extracted_tabs['composition'], 
            'dosage'     => $extracted_tabs['dosage'],
            'storage'    => $extracted_tabs['storage'] 
        ];
    }
    set_transient('yg_pending_import', $products_data, 1 * HOUR_IN_SECONDS);
    wp_send_json_success(['total' => count($products_data)]);
});

add_action('wp_ajax_yg_process_batch', function() {
    check_ajax_referer('yg_xml_nonce', 'nonce');
    $offset = intval($_POST['offset']);
    $mode = $_POST['mode'];
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
                    $product->set_regular_price($data['reg_price']);
                    $product->set_sale_price($data['sale_price']);
                    $product->set_weight($data['weight']); 
                    $product->set_stock_status($data['stock'] ? 'instock' : 'outofstock');
                    
                    $product->set_description($data['desc']);
                    $product->set_short_description($data['short_desc']);

                    if (!empty($data['slug']) && $product->get_slug() !== $data['slug']) {
                        $product->set_slug($data['slug']);
                    }

                    update_post_meta($product_id, '_petling_composition', $data['composition']);
                    update_post_meta($product_id, '_petling_dosage', $data['dosage']);
                    update_post_meta($product_id, '_petling_storage', $data['storage']);

                    $product->save();
                    if (taxonomy_exists('berocket_brand')) wp_set_object_terms($product_id, $data['brand'], 'berocket_brand', false);
                    $res['updated']++;
                } else { $res['skipped']++; }
            } else { 
                if (!$product_id) {
                    $product = new WC_Product_Simple();
                    $product->set_name($data['name']);
                    $product->set_status('draft');
                    $product->set_sku($sku);
                    if (!empty($data['slug'])) $product->set_slug($data['slug']);
                    
                    $product->set_regular_price($data['reg_price']);
                    $product->set_sale_price($data['sale_price']);
                    $product->set_weight($data['weight']);
                    
                    $product->set_description($data['desc']);
                    $product->set_short_description($data['short_desc']);
                    $product->set_stock_status($data['stock'] ? 'instock' : 'outofstock');
                    $product->update_meta_data('_supplier_name', YOGGIES_SUPPLIER);
                    $new_id = $product->save();

                    update_post_meta($new_id, '_petling_composition', $data['composition']);
                    update_post_meta($new_id, '_petling_dosage', $data['dosage']);
                    update_post_meta($new_id, '_petling_storage', $data['storage']);

                    if ($data['img']) yoggies_upload_image_from_url($new_id, $data['img'], $sku);
                    if (taxonomy_exists('berocket_brand')) wp_set_object_terms($new_id, $data['brand'], 'berocket_brand', false);
                    $res['created']++;
                } else { $res['skipped']++; }
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

// ============================================================================
// CRON: ΑΥΤΟΜΑΤΟ UPDATE & IMPORT (ΚΑΘΕ 10 ΛΕΠΤΑ)
// ============================================================================
add_filter('cron_schedules', function($schedules){
    $schedules['every_ten_minutes'] = ['interval' => 10 * 60, 'display'  => 'Κάθε 10 Λεπτά (Yoggies)'];
    return $schedules;
});

register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('yoggies_xml_cron_sync_event')) wp_schedule_event(time(), 'every_ten_minutes', 'yoggies_xml_cron_sync_event');
});

add_action('yoggies_xml_cron_sync_event', 'yoggies_xml_run_automated_sync');

function yoggies_xml_run_automated_sync() {
    if (!class_exists('WooCommerce')) return;

    $response = wp_remote_get(YOGGIES_XML_URL);
    if (is_wp_error($response)) return;
    $xml = simplexml_load_string(wp_remote_retrieve_body($response));
    if (!$xml) return;
    
    $items = $xml->xpath('//product');
    foreach ($items as $item) {
        $sku = trim((string)$item->sku) ?: trim((string)$item->part_number);
        if (!$sku) continue;
        
        $product_id = wc_get_product_id_by_sku($sku);
        
        $reg = yoggies_parse_price($item->price);
        $sale = yoggies_parse_price($item->sales_price);
        $weight = yoggies_parse_weight($item->weight); 
        $slug = yoggies_get_slug_from_link($item->link);
        $desc = (string)$item->html_description;
        $short_desc = (string)$item->short_description;
        $image_link = (string)$item->image_link;
        
        // ΔΙΟΡΘΩΣΗ CRON
        $availability = strtolower(trim((string)$item->availability));
        $is_in_stock = in_array($availability, ['in_stock', 'in stock']);
        
        $extracted_tabs = yoggies_extract_custom_tabs((string)$item->custom_tabs);

        if ($product_id) {
            $p = wc_get_product($product_id);
            $p->set_regular_price($reg);
            $p->set_sale_price(($sale > 0 && $sale < $reg) ? $sale : '');
            $p->set_weight($weight); 
            $p->set_stock_status($is_in_stock ? 'instock' : 'outofstock');
            
            $p->set_description($desc);
            $p->set_short_description($short_desc);

            if (!empty($slug) && $p->get_slug() !== $slug) $p->set_slug($slug);
            
            update_post_meta($product_id, '_petling_composition', $extracted_tabs['composition']);
            update_post_meta($product_id, '_petling_dosage', $extracted_tabs['dosage']);
            update_post_meta($product_id, '_petling_storage', $extracted_tabs['storage']);

            $p->save();
        } else {
            $p = new WC_Product_Simple();
            $p->set_name((string)$item->name);
            $p->set_status('draft');
            $p->set_sku($sku);
            if (!empty($slug)) $p->set_slug($slug);
            $p->set_regular_price($reg);
            $p->set_sale_price(($sale > 0 && $sale < $reg) ? $sale : '');
            $p->set_weight($weight); 
            
            $p->set_description($desc);
            $p->set_short_description($short_desc);

            $p->set_stock_status($is_in_stock ? 'instock' : 'outofstock');
            $p->update_meta_data('_supplier_name', YOGGIES_SUPPLIER);
            $new_id = $p->save();

            update_post_meta($new_id, '_petling_composition', $extracted_tabs['composition']);
            update_post_meta($new_id, '_petling_dosage', $extracted_tabs['dosage']);
            update_post_meta($new_id, '_petling_storage', $extracted_tabs['storage']);
            
            if (!empty($image_link)) {
                yoggies_upload_image_from_url($new_id, $image_link, $sku);
            }
        }
    }
}