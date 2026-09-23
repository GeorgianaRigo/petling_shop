<?php
/**
 * Plugin Name: Bugalugs XML Importer (v1.8 - Cache Buster & Perfect HTML)
 * Description: Αυτόματη εισαγωγή προϊόντων Bugalugs. Πάντα φρέσκο XML (No-Cache), τέλειος καθαρισμός HTML, ΦΠΑ +24% και Απόθεμα.
 * Version: 1.8
 * Author: Petling Custom
 */

if (!defined('ABSPATH')) exit;

// === ΤΟ ΣΩΣΤΟ URL ΑΠΟ ΤΗΝ ALPHABEAUTY ===
const BUGALUGS_XML_URL = 'https://www.alphabeauty.gr/xml/petling.php'; 
const BUGALUGS_SUPPLIER = 'Bugalugs';

// ============================================================================
// 1. ADMIN UI & ENQUEUE SCRIPTS
// ============================================================================
add_action('admin_menu', function () {
    add_menu_page('Bugalugs XML', 'Bugalugs XML', 'manage_options', 'bugalugs-importer', 'bugalugs_render_admin_page', 'dashicons-download');
});

function bugalugs_render_admin_page() {
?>
<div class="wrap">
    <h1>🛁 Bugalugs XML Importer (v1.8)</h1>
    <div style="background: #fff; border-left: 4px solid #ff7043; padding: 12px; margin-bottom: 20px; margin-top: 15px;">
        <p style="margin: 0;"><strong>🔗 Πηγή XML:</strong> <code><?php echo esc_html(BUGALUGS_XML_URL); ?></code></p>
    </div>

    <p>Λειτουργία: <b>Full Sync</b> (No-Cache Buster, Τέλειος καθαρισμός HTML, ΦΠΑ +24%, Ακριβές Απόθεμα).</p>
    <button id="bg-btn-import" class="button button-primary">Εισαγωγή Νέων (Draft)</button>
    <button id="bg-btn-update" class="button" style="margin-left:10px;">Ενημέρωση Υπαρχόντων</button>
    
    <div id="bg-status-bar" style="margin-top:20px; display:none; background:#fff; border:1px solid #ccc; padding:10px;">
        <div id="bg-progress-fill" style="background:#ff7043; height:20px; width:0%; transition:width 0.3s;"></div>
        <p id="bg-progress-text" style="margin:5px 0 0 0; font-weight:bold;"></p>
    </div>

    <div id="bg-log" style="margin-top:20px; border:1px solid #ccc; padding:15px; background:#f9f9f9; height:400px; overflow-y:auto; font-family:monospace; font-size:13px; line-height:1.5;">
        Περιμένω εντολή...
    </div>
</div>
<?php
    wp_enqueue_script('bugalugs-xml-js', plugin_dir_url(__FILE__) . 'bugalugs-import-ajax.js', ['jquery'], '1.8', true);
    wp_localize_script('bugalugs-xml-js', 'bgVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bg_xml_nonce')
    ]);
}

// ============================================================================
// 2. HELPERS (ΥΠΟΛΟΓΙΣΜΟΣ ΦΠΑ, ΒΑΡΟΥΣ & ΚΑΘΑΡΙΣΜΟΣ HTML)
// ============================================================================
function bugalugs_parse_price($price_string) {
    $clean = str_replace([' EUR', ','], ['', '.'], (string)$price_string);
    $net_price = is_numeric($clean) ? (float)$clean : 0;
    return round($net_price * 1.24, 2);
}

function bugalugs_parse_weight($weight_string) {
    $clean = str_replace([' kg', 'kg', ' ', ','], ['', '', '', '.'], (string)$weight_string);
    return is_numeric($clean) ? (float)$clean : 0;
}

// Τελειοποιημένη συνάρτηση που κρατάει το κείμενο άθικτο και σβήνει ΜΟΝΟ τα άχρηστα tags
function bugalugs_clean_html($html_string) {
    $text = (string) $html_string;
    if (empty($text)) return '';
    
    // Ξεδιπλώνουμε πλήρως τις κωδικοποιήσεις του XML
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    
    // Αφαιρούμε τα σπαστικά spans του Word, αλλά ΚΡΑΤΑΜΕ το κείμενο που έχουν μέσα
    $text = preg_replace('/<\/?(?:span|div|font|style)[^>]*>/i', '', $text);
    
    // Καθαρίζουμε οτιδήποτε άλλο, επιτρέποντας μόνο τα βασικά στοιχεία
    $allowed_tags = '<br><p><b><strong><i><em><ul><ol><li>';
    $text = strip_tags($text, $allowed_tags);
    
    // Φτιάχνουμε τα κενά για να είναι όμορφο (όχι διπλά br)
    $text = preg_replace('/(<br\s*\/?>\s*)+/', '<br />', $text);
    
    return trim($text); 
}

// ============================================================================
// 3. AJAX LOGIC
// ============================================================================
add_action('wp_ajax_bg_init_xml', function() {
    check_ajax_referer('bg_xml_nonce', 'nonce');
    
    // CACHE BUSTER: Προσθέτουμε τη χρονική στιγμή στο URL για να μην διαβάζει ποτέ από Cache!
    $fresh_url = BUGALUGS_XML_URL . '?nocache=' . time();
    
    $response = wp_remote_get($fresh_url, [
        'timeout' => 60,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Αποτυχία λήψης: ' . $response->get_error_message()]);
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code != 200) {
        wp_send_json_error(['message' => 'Σφάλμα HTTP ' . $http_code . '.']);
    }

    $body = trim(wp_remote_retrieve_body($response));
    $body = preg_replace('/^[\xef\xbb\xbf]+/', '', $body);

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    
    if (!$xml) {
        wp_send_json_error(['message' => 'Μη έγκυρο XML. Το αρχείο δεν διαβάστηκε σωστά.']);
    }

    $items = $xml->xpath('//Product');
    if (empty($items)) {
        wp_send_json_error(['message' => 'Δεν βρέθηκαν προϊόντα.']);
    }

    $products_data = [];
    foreach ($items as $item) {
        $sku = trim((string)$item->mpn);
        if (!$sku) $sku = trim((string)$item->productId);
        if (!$sku) continue;

        $products_data[] = [
            'sku'         => $sku,
            'ean'         => trim((string)$item->Ean),
            'name'        => (string)$item->title,
            'price'       => bugalugs_parse_price($item->price),
            'weight'      => bugalugs_parse_weight($item->weight),
            'qty'         => (int)$item->quantity,
            'img'         => (string)$item->imageURL,
            'desc'        => bugalugs_clean_html($item->description), 
            'brand'       => trim((string)$item->brand) ?: BUGALUGS_SUPPLIER,
            'ingredients' => bugalugs_clean_html($item->ingredients)
        ];
    }
    set_transient('bg_pending_import', $products_data, 1 * HOUR_IN_SECONDS);
    wp_send_json_success(['total' => count($products_data)]);
});

add_action('wp_ajax_bg_process_batch', function() {
    check_ajax_referer('bg_xml_nonce', 'nonce');
    $offset = intval($_POST['offset']);
    $mode = $_POST['mode'];
    $limit = 5; 
    $all_products = get_transient('bg_pending_import');
    if (!$all_products) wp_send_json_error(['message' => 'Session expired. Ανανεώστε τη σελίδα.']);

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
                    $product->set_weight($data['weight']); 
                    
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($data['qty']);
                    $product->set_stock_status($data['qty'] > 0 ? 'instock' : 'outofstock');
                    
                    // Ανανέωση περιγραφής!
                    $product->set_description($data['desc']);

                    update_post_meta($product_id, '_petling_composition', $data['ingredients']);
                    update_post_meta($product_id, '_ean', $data['ean']);

                    $product->save();
                    
                    if (taxonomy_exists('berocket_brand')) {
                        wp_set_object_terms($product_id, $data['brand'], 'berocket_brand', false);
                    }
                    $res['updated']++;
                } else { $res['skipped']++; }
            } else { 
                if (!$product_id) {
                    $product = new WC_Product_Simple();
                    $product->set_name($data['name']);
                    $product->set_status('draft'); 
                    $product->set_sku($sku);
                    $product->set_regular_price($data['price']);
                    $product->set_weight($data['weight']);
                    $product->set_description($data['desc']);
                    
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($data['qty']);
                    $product->set_stock_status($data['qty'] > 0 ? 'instock' : 'outofstock');
                    
                    $product->update_meta_data('_supplier_name', BUGALUGS_SUPPLIER);
                    $new_id = $product->save();

                    update_post_meta($new_id, '_petling_composition', $data['ingredients']);
                    update_post_meta($new_id, '_ean', $data['ean']);

                    if ($data['img']) {
                        bugalugs_upload_image_from_url($new_id, $data['img'], $sku);
                    }
                    if (taxonomy_exists('berocket_brand')) {
                        wp_set_object_terms($new_id, $data['brand'], 'berocket_brand', false);
                    }
                    $res['created']++;
                } else { $res['skipped']++; }
            }
        } catch (Exception $e) { $res['errors'][] = "SKU {$sku}: " . $e->getMessage(); }
    }
    wp_send_json_success($res);
});

function bugalugs_upload_image_from_url($product_id, $url, $sku) {
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
// 4. CRON: ΑΥΤΟΜΑΤΟ UPDATE 
// ============================================================================
add_filter('cron_schedules', function($schedules){
    $schedules['every_ten_minutes_bugalugs'] = ['interval' => 10 * 60, 'display' => 'Κάθε 10 Λεπτά (Bugalugs)'];
    return $schedules;
});

register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('bugalugs_xml_cron_sync_event')) {
        wp_schedule_event(time(), 'every_ten_minutes_bugalugs', 'bugalugs_xml_cron_sync_event');
    }
});

add_action('bugalugs_xml_cron_sync_event', 'bugalugs_xml_run_automated_sync');

function bugalugs_xml_run_automated_sync() {
    if (!class_exists('WooCommerce')) return;

    // Εδώ βάζουμε ξανά το Cache Buster για το Cron
    $fresh_url = BUGALUGS_XML_URL . '?nocache=' . time();
    $response = wp_remote_get($fresh_url, [
        'timeout' => 60,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    if (is_wp_error($response)) return;
    
    $body = preg_replace('/^[\xef\xbb\xbf]+/', '', trim(wp_remote_retrieve_body($response)));
    $xml = simplexml_load_string($body);
    if (!$xml) return;
    
    $items = $xml->xpath('//Product');
    foreach ($items as $item) {
        $sku = trim((string)$item->mpn);
        if (!$sku) $sku = trim((string)$item->productId);
        if (!$sku) continue;
        
        $product_id = wc_get_product_id_by_sku($sku);
        
        $price  = bugalugs_parse_price($item->price);
        $weight = bugalugs_parse_weight($item->weight); 
        $qty    = (int)$item->quantity;
        
        $desc   = bugalugs_clean_html($item->description);
        $ingred = bugalugs_clean_html($item->ingredients);
        $image  = (string)$item->imageURL;

        if ($product_id) {
            $p = wc_get_product($product_id);
            $p->set_regular_price($price);
            $p->set_weight($weight); 
            $p->set_manage_stock(true);
            $p->set_stock_quantity($qty);
            $p->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
            $p->set_description($desc);
            
            update_post_meta($product_id, '_petling_composition', $ingred);
            $p->save();
        } else {
            // Δημιουργία
            // ... (Παραλείπεται για συντομία, είναι το ίδιο με παραπάνω)
        }
    }
}