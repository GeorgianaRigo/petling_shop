<?php
/**
 * Plugin Name: Yoggies XML Importer (v5.25 - Isolated CSV Paster)
 * Description: Αυτόματη εισαγωγή και ενημέρωση προϊόντων Yoggies με έξυπνο φίλτρο εκπτώσεων και ακριβή συγχρονισμό ποσοτήτων (manage stock).
 * Version: 5.25
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
    <h1>📦 Yoggies XML Importer (v5.25)</h1>
    <div style="background: #fff; border-left: 4px solid #00a0d2; padding: 12px; margin-bottom: 20px; margin-top: 15px;">
        <p style="margin: 0;"><strong>🔗 Πηγή XML:</strong> <code><?php echo esc_html(YOGGIES_XML_URL); ?></code></p>
    </div>

    <p>Λειτουργία: <b>Full Sync</b> (Έξυπνο φίλτρο εκπτώσεων, Custom Tabs, Cron Images, Ακριβές Απόθεμα / Quantity).</p>
    
    <div style="margin-bottom:20px;">
        <button id="yg-btn-import" class="button button-primary">Εισαγωγή Νέων (Draft)</button>
        <button id="yg-btn-update" class="button" style="margin-left:10px;">Ενημέρωση Υπαρχόντων</button>
    </div>

    <!-- [ΝΕΟ] - ΑΝΕΞΑΡΤΗΤΟ ΠΕΔΙΟ COPY-PASTE CSV (ΜΟΝΟ YOGGIES) -->
    <div style="background: #fdfaf5; border: 1px solid #e2d4c0; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #43282F;">🛠️ Έξυπνη Ενημέρωση Συστατικών (Copy/Paste)</h3>
        <p style="font-size: 13px; color: #555;">Επικόλλησε εδώ το CSV (product_id, product_name, composition). Ο κώδικας θα φιλτράρει <strong>ΜΟΝΟ τα Yoggies</strong> και θα μοιράσει τα συστατικά αυτόματα σε όλες τις συσκευασίες (Γονέας -> Παιδί)!</p>
        <textarea id="yg-csv-data" rows="5" style="width: 100%; font-family: monospace; border: 1px solid #ccc; padding: 10px; border-radius: 4px;" placeholder='"product_id","product_name","composition"'></textarea>
        <button id="yg-btn-pasted-csv" class="button" style="background: #28a745; color: white; border-color: #218838; margin-top: 10px; font-weight: bold;">Ενημέρωση από το πεδίο</button>
    </div>
    <!-- [/ΝΕΟ] -->

    <div id="yg-status-bar" style="margin-top:20px; display:none; background:#fff; border:1px solid #ccc; padding:10px;">
        <div id="yg-progress-fill" style="background:#0073aa; height:20px; width:0%; transition:width 0.3s;"></div>
        <p id="yg-progress-text" style="margin:5px 0 0 0; font-weight:bold;"></p>
    </div>

    <div id="yg-log" style="margin-top:20px; border:1px solid #ccc; padding:15px; background:#f9f9f9; height:400px; overflow-y:auto; font-family:monospace; font-size:12px;">
        Περιμένω εντολή...
    </div>
</div>
<?php
    wp_enqueue_script('yoggies-xml-js', plugin_dir_url(__FILE__) . 'yoggies-import-ajax.js', ['jquery'], '5.25', true);
    wp_localize_script('yoggies-xml-js', 'ygVars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yg_xml_nonce')
    ]);
}

// ============================================================================
// HELPERS & VALIDATION (ΑΘΙΚΤΑ)
// ============================================================================
function yoggies_parse_price($price_string) {
    $clean = str_replace([' EUR', ','], ['', '.'], (string)$price_string);
    return is_numeric($clean) ? (float)$clean : 0;
}

function yoggies_get_valid_sale_price($regular_price, $sale_price, $max_discount_percent = 40) {
    $reg = (float) $regular_price;
    $sale = (float) $sale_price;
    if ($sale <= 0 || $sale >= $reg) {
        return '';
    }
    $discount_percent = (($reg - $sale) / $reg) * 100;
    if ($discount_percent > $max_discount_percent) {
        return '';
    }
    return $sale;
}

function yoggies_parse_weight($weight_string) {
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
// AJAX LOGIC (ΑΘΙΚΤΟ XML IMPORT)
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
        $raw_sale_price = yoggies_parse_price($item->sales_price);
        $sale_price = yoggies_get_valid_sale_price($regular_price, $raw_sale_price, 40);
        $weight = yoggies_parse_weight($item->weight);
        $availability = strtolower(trim((string)$item->availability));
        $is_in_stock = in_array($availability, ['in_stock', 'in stock']);
        $quantity = isset($item->quantity) ? (int)$item->quantity : 0;
        $extracted_tabs = yoggies_extract_custom_tabs((string)$item->custom_tabs);

        $products_data[] = [
            'sku' => $sku, 'name' => (string)$item->name, 'slug' => yoggies_get_slug_from_link($item->link),
            'reg_price' => $regular_price, 'sale_price' => $sale_price, 'weight' => $weight,
            'stock' => $is_in_stock, 'qty' => $quantity, 'img' => (string)$item->image_link,
            'desc' => (string)$item->html_description, 'short_desc' => (string)$item->short_description,
            'brand' => trim((string)$item->manufacturer) ?: 'Yoggies',
            'composition'=> $extracted_tabs['composition'], 'dosage' => $extracted_tabs['dosage'], 'storage' => $extracted_tabs['storage'] 
        ];
    }
    set_transient('yg_pending_import', $products_data, HOUR_IN_SECONDS);
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
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($data['qty']);
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
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($data['qty']);
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

add_filter('cron_schedules', function($schedules){
    $schedules['every_ten_minutes'] = ['interval' => 600, 'display' => 'Κάθε 10 Λεπτά (Yoggies)'];
    return $schedules;
});

add_action('init', function() {
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
    $xml_skus = []; // [ΝΕΟ] Εδώ θα αποθηκεύσουμε όλα τα SKU που ΗΡΘΑΝ σήμερα από το XML

    foreach ($items as $item) {
        $sku = trim((string)$item->sku) ?: trim((string)$item->part_number);
        if (!$sku) continue;
        
        $xml_skus[] = $sku; // [ΝΕΟ] Καταγράφουμε το SKU ότι υπάρχει στο XML

        $product_id = wc_get_product_id_by_sku($sku);
        $reg = yoggies_parse_price($item->price);
        $raw_sale = yoggies_parse_price($item->sales_price);
        $sale = yoggies_get_valid_sale_price($reg, $raw_sale, 40);
        $weight = yoggies_parse_weight($item->weight); 
        $slug = yoggies_get_slug_from_link($item->link);
        $desc = (string)$item->html_description;
        $short_desc = (string)$item->short_description;
        $image_link = (string)$item->image_link;
        $availability = strtolower(trim((string)$item->availability));
        $is_in_stock = in_array($availability, ['in_stock', 'in stock']);
        $quantity = isset($item->quantity) ? (int)$item->quantity : 0;
        $extracted_tabs = yoggies_extract_custom_tabs((string)$item->custom_tabs);

        if ($product_id) {
            $p = wc_get_product($product_id);
            $p->set_regular_price($reg);
            $p->set_sale_price($sale);
            $p->set_weight($weight); 
            $p->set_manage_stock(true);
            $p->set_stock_quantity($quantity);
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
            $p->set_sale_price($sale);
            $p->set_weight($weight); 
            $p->set_description($desc);
            $p->set_short_description($short_desc);
            $p->set_manage_stock(true);
            $p->set_stock_quantity($quantity);
            $p->set_stock_status($is_in_stock ? 'instock' : 'outofstock');
            $p->update_meta_data('_supplier_name', YOGGIES_SUPPLIER);
            $new_id = $p->save();
            update_post_meta($new_id, '_petling_composition', $extracted_tabs['composition']);
            update_post_meta($new_id, '_petling_dosage', $extracted_tabs['dosage']);
            update_post_meta($new_id, '_petling_storage', $extracted_tabs['storage']);
            if (!empty($image_link)) yoggies_upload_image_from_url($new_id, $image_link, $sku);
        }
    }

    // ============================================================================
    // [ΝΕΟ] ΜΗΧΑΝΙΣΜΟΣ ZERO-STOCK ΓΙΑ ΤΑ ΠΡΟΪΟΝΤΑ ΠΟΥ ΑΦΑΙΡΕΘΗΚΑΝ ΑΠΟ ΤΟ XML
    // ============================================================================
    if (!empty($xml_skus)) {
        global $wpdb;
        // Βρίσκουμε όλα τα προϊόντα που έχουμε αποθηκεύσει στο παρελθόν ως Yoggies
        $query = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", '_supplier_name', YOGGIES_SUPPLIER);
        $yoggies_db_products = $wpdb->get_col($query);

        foreach ($yoggies_db_products as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;

            $db_sku = $product->get_sku();
            
            // Αν το SKU του e-shop ΔΕΝ υπάρχει στη λίστα με τα σημερινά SKU του XML
            if ($db_sku && !in_array($db_sku, $xml_skus)) {
                // Αν έχει ήδη απόθεμα 0, το προσπερνάμε για οικονομία πόρων
                if ($product->get_stock_quantity() > 0 || $product->get_stock_status() === 'instock') {
                    $product->set_stock_quantity(0);
                    $product->set_stock_status('outofstock');
                    $product->save();
                }
            }
        }
    }
}

// ============================================================================
// [ΝΕΟ] - ΑΠΟΜΟΝΩΜΕΝΗ ΛΕΙΤΟΥΡΓΙΑ ΕΝΗΜΕΡΩΣΗΣ ΑΠΟ COPY/PASTE
// ============================================================================
function yg_clean_name_for_match($name) {
    $clean = html_entity_decode((string)$name, ENT_QUOTES, 'UTF-8');
    // Κόβουμε μεγέθη, Mini, Normal κλπ για να βρούμε τον πατέρα
    $clean = preg_replace('/-\s*(Mini|Normal|Active|XXL)?\s*,?\s*\d+\s*(kg|g|ml|l|τεμ|cm)\b/i', '', $clean);
    $clean = preg_replace('/,?\s*\d+\s*(kg|g|ml|l|τεμ|cm)\b/i', '', $clean);
    $clean = preg_replace('/Πακέτο\s*\d+\s*x\s*\d+\s*g/i', '', $clean);
    return trim(rtrim($clean, " -,\t\n\r\0\x0B"));
}

add_action('wp_ajax_yg_process_pasted_csv', 'yg_process_pasted_csv_handler');
function yg_process_pasted_csv_handler() {
    check_ajax_referer('yg_xml_nonce', 'nonce');
    $csv_data = stripslashes($_POST['csv_text']);
    if (empty($csv_data)) wp_send_json_error(['message' => 'Το πεδίο είναι άδειο.']);

    // Δημιουργούμε stream για ασφαλή ανάγνωση multiline CSV από την PHP
    $stream = fopen("php://temp", 'r+');
    fwrite($stream, $csv_data);
    rewind($stream);
    
    $header = fgetcsv($stream, 0, ","); // Υποθέτουμε delimiter το κόμμα (όπως στο copy-paste)
    
    $dict = [];
    while (($row = fgetcsv($stream, 0, ",")) !== false) {
        if (count($row) >= 3) {
            $p_name = trim($row[1]);
            $comp   = trim($row[2]);
            
            // ΦΙΛΤΡΟ 1: ΑΓΝΟΟΥΜΕ ΤΑ ΠΑΝΤΑ ΕΚΤΟΣ ΑΠΟ YOGGIES
            if (stripos($p_name, 'yoggies') === false) continue;
            if (empty($comp)) continue;

            // Αν κατά λάθος υπάρχει χαλασμένο serialize "a:4:{...", το παρακάμπτουμε
            if (strpos($comp, 'a:') === 0 && preg_match('/"title";s:\d+:"(?:Συστατικά|Σύνθεση)".*?"content";s:\d+:"(.*?)"(?:;\}i:|;\}\}$)/is', $comp, $m)) {
                $comp = $m[1];
            } elseif (strpos($comp, 'a:') === 0) {
                continue; // Σκουπίδι, το αγνοούμε
            }

            // Βρίσκουμε το Καθαρό Γονικό Όνομα (π.χ. Ξηρά Τροφή Ιβηρικό Χοιρινό)
            $clean_name = yg_clean_name_for_match($p_name);
            $dict[$clean_name] = wp_kses_post($comp);
        }
    }
    fclose($stream);

    if (empty($dict)) wp_send_json_error(['message' => 'Δεν βρέθηκαν έγκυρα συστατικά στο κείμενο.']);

    // ΦΙΛΤΡΟ 2: Ζητάμε από τη βάση ΜΟΝΟ τα προϊόντα Yoggies
    global $wpdb;
    $query = $wpdb->prepare("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type IN ('product', 'product_variation') AND post_status IN ('publish', 'draft') AND post_title LIKE %s", '%yoggies%');
    $products = $wpdb->get_results($query);
    
    $updated = 0;
    foreach ($products as $p) {
        $base = yg_clean_name_for_match($p->post_title);
        // Αν το Yoggies προϊόν ταιριάζει με τον "Πατέρα" του λεξικού, πέρνα τα συστατικά!
        if (isset($dict[$base])) {
            update_post_meta($p->ID, '_petling_composition', $dict[$base]);
            $updated++;
        }
    }
    
    wp_send_json_success(['updated' => $updated, 'total_checked' => count($products)]);
}