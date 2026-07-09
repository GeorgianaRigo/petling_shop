<?php
/*
Plugin Name: Petling Partners Promo
Plugin URI: https://petling.gr
Description: Έξυπνος μηχανισμός κουπονιών (Lead Generation) για τους συνεργάτες του Petling. Δημιουργεί μοναδικά κουπόνια WooCommerce και μαζεύει emails.
Version: 1.4
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Ασφάλεια

// =========================================================================
// 1. ΔΗΜΙΟΥΡΓΙΑ & ΕΝΗΜΕΡΩΣΗ ΒΑΣΗΣ ΔΕΔΟΜΕΝΩΝ 
// =========================================================================
function petling_promo_update_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'petling_partner_leads';
    
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        coupon_code varchar(50) NOT NULL,
        last_claimed datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('plugins_loaded', 'petling_promo_update_db'); 


// =========================================================================
// 2. ΚΕΝΤΡΙΚΟ ΜΕΝΟΥ ΣΤΟ ADMIN TOY WORDPRESS & TABS
// =========================================================================
add_action('admin_menu', 'petling_promo_admin_menu');
function petling_promo_admin_menu() {
    add_menu_page('Ρυθμίσεις Petling Promo', 'Petling Promo', 'manage_options', 'petling-partners-promo', 'petling_promo_settings_page', 'dashicons-pets', 56);
}

add_action('admin_init', 'petling_promo_register_settings');
function petling_promo_register_settings() {
    register_setting('petling_promo_settings_group', 'petling_promo_discount_amount');
    register_setting('petling_promo_settings_group', 'petling_promo_min_order');
    register_setting('petling_promo_settings_group', 'petling_promo_lock_days');

    // Λογική Διαγραφής Πελάτη από τον Πίνακα
    if (isset($_GET['page']) && $_GET['page'] === 'petling-partners-promo' && isset($_GET['delete_lead']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_lead_' . $_GET['delete_lead'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'petling_partner_leads';
        $wpdb->delete($table_name, array('id' => intval($_GET['delete_lead'])));
        wp_redirect(admin_url('admin.php?page=petling-partners-promo&tab=leads'));
        exit;
    }
}

function petling_promo_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'petling_partner_leads';
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
    ?>
    <div class="wrap">
        <h2>🐾 Petling Promo - Διαχείριση Συνεργατών</h2>
        
        <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="?page=petling-partners-promo&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Ρυθμίσεις & Οδηγίες</a>
            <a href="?page=petling-partners-promo&tab=leads" class="nav-tab <?php echo $active_tab == 'leads' ? 'nav-tab-active' : ''; ?>">Εγγεγραμμένοι Πελάτες</a>
            <a href="?page=petling-partners-promo&tab=qrcode" class="nav-tab <?php echo $active_tab == 'qrcode' ? 'nav-tab-active' : ''; ?>">Δημιουργία QR Code</a>
        </h2>

        <?php if ($active_tab == 'settings'): ?>
            <!-- TAB 1: ΡΥΘΜΙΣΕΙΣ -->
            <div class="notice notice-info" style="padding: 15px; margin-top: 15px; margin-bottom: 25px; border-left-color: #5b9a68;">
                <h3 style="margin-top: 0; color: #5b9a68;">💡 Οδηγίες Χρήσης (Shortcodes στο Elementor)</h3>
                <p>Για να εμφανίσετε τη φόρμα έκπτωσης στη σελίδα κάποιου συνεργάτη, προσθέστε το widget <strong>"Σορτκόντ (Shortcode)"</strong> στο Elementor και γράψτε τον παρακάτω κώδικα, αλλάζοντας το όνομα και το πρόθεμα:</p>
                <ul style="list-style-type: disc; margin-left: 25px; margin-top: 10px;">
                    <li style="margin-bottom: 5px;"><strong>Για την Joy:</strong> <code>[petling_partner_promo partner="την Joy" prefix="JOY"]</code></li>
                    <li><strong>Για Κτηνίατρο:</strong> <code>[petling_partner_promo partner="τον Κτηνίατρο" prefix="VET"]</code></li>
                </ul>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('petling_promo_settings_group'); ?>
                <table class="form-table" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px;">
                    <tr valign="top">
                        <th scope="row">Ποσό Έκπτωσης (σε €)</th>
                        <td><input type="number" step="0.5" name="petling_promo_discount_amount" value="<?php echo esc_attr(get_option('petling_promo_discount_amount', 2)); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Ελάχιστη Παραγγελία (σε €)</th>
                        <td><input type="number" step="1" name="petling_promo_min_order" value="<?php echo esc_attr(get_option('petling_promo_min_order', 20)); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Ημέρες "Κλειδώματος" ανά Email</th>
                        <td>
                            <input type="number" step="1" name="petling_promo_lock_days" value="<?php echo esc_attr(get_option('petling_promo_lock_days', 20)); ?>" />
                            <p class="description">Πόσες μέρες πρέπει να περιμένει ο πελάτης για να ξαναπάρει κουπόνι.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Αποθήκευση Ρυθμίσεων'); ?>
            </form>

        <?php elseif ($active_tab == 'leads'): ?>
            <!-- TAB 2: ΛΙΣΤΑ ΠΕΛΑΤΩΝ -->
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; margin-top:20px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email Πελάτη</th>
                            <th>Κωδικός που πήρε</th>
                            <th>Ημ/νία Εγγραφής</th>
                            <th>Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $leads = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_claimed DESC");
                        if ($leads) {
                            foreach ($leads as $lead) {
                                $del_url = wp_nonce_url(admin_url("admin.php?page=petling-partners-promo&delete_lead={$lead->id}"), 'delete_lead_'.$lead->id);
                                echo '<tr>';
                                echo '<td>' . esc_html($lead->id) . '</td>';
                                echo '<td><strong>' . esc_html($lead->email) . '</strong></td>';
                                echo '<td><code style="background:#eef7ee; color:#5b9a68; padding:3px 6px;">' . esc_html($lead->coupon_code) . '</code></td>';
                                echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($lead->last_claimed))) . '</td>';
                                echo '<td><a href="' . esc_url($del_url) . '" onclick="return confirm(\'Σίγουρα θέλετε να διαγράψετε αυτή την εγγραφή;\');" style="color:#d63638; text-decoration:none;">🗑️ Διαγραφή</a></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" style="padding:20px; text-align:center;">Δεν υπάρχουν ακόμα εγγραφές.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($active_tab == 'qrcode'): ?>
            <!-- TAB 3: QR CODE GENERATOR -->
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:25px; max-width: 600px; margin-top:20px;">
                <h3 style="margin-top:0; color:#43282F;">📱 Γεννήτρια QR Code για Συνεργάτες</h3>
                <p style="color:#555; font-size:14px; margin-bottom:20px;">Δημιουργήστε εύκολα και γρήγορα το QR Code που θα τυπώσετε για το φυσικό κατάστημα του συνεργάτη (π.χ. σε stand ή κάρτες). Όταν ο πελάτης το σκανάρει, θα ανοίγει κατευθείαν η σελίδα για να πάρει την έκπτωσή του!</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">Όνομα Συνεργάτη<br><small style="color:#999; font-weight:normal;">(Μόνο για το όνομα του αρχείου)</small></th>
                        <td><input type="text" id="qr_partner_name" class="regular-text" placeholder="π.χ. Joy"></td>
                    </tr>
                    <tr>
                        <th scope="row">URL Σελίδας<br><small style="color:#999; font-weight:normal;">(Η σελίδα που φτιάξατε στο site)</small></th>
                        <td><input type="url" id="qr_partner_url" class="regular-text" placeholder="https://petling.gr/joy"></td>
                    </tr>
                </table>
                
                <p style="margin-top:20px;">
                    <button type="button" class="button button-primary" style="background:#5b9a68; border-color:#4a8255;" onclick="generatePetlingQRCode()">Δημιουργία QR Code</button>
                </p>

                <!-- Περιοχή Εμφάνισης του QR -->
                <div id="qr_result_area" style="display:none; margin-top: 30px; text-align: center; border-top: 1px dashed #ccc; padding-top: 25px;">
                    <canvas id="qr_canvas" style="border: 1px solid #eee; padding: 10px; border-radius: 8px; background: #fff;"></canvas>
                    <br><br>
                    <a id="qr_download_btn" class="button button-secondary" download="qr-code.png">⬇️ Λήψη σε μορφή PNG</a>
                </div>
            </div>

            <!-- Φόρτωση βιβλιοθήκης QRious για τη δημιουργία του QR -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
            <script>
            function generatePetlingQRCode() {
                var url = document.getElementById('qr_partner_url').value;
                var name = document.getElementById('qr_partner_name').value;
                
                if (!url) {
                    alert('Παρακαλώ εισάγετε το URL της σελίδας!');
                    return;
                }

                // Δημιουργία QR Code στον Καμβά
                var qr = new QRious({
                    element: document.getElementById('qr_canvas'),
                    value: url,
                    size: 300, // Μεγάλο μέγεθος για εκτύπωση (υψηλή ανάλυση)
                    level: 'H' // High error correction (Διαβάζεται εύκολα κι αν λερωθεί λίγο)
                });

                document.getElementById('qr_result_area').style.display = 'block';
                
                // Ρύθμιση του κουμπιού "Λήψη"
                var canvas = document.getElementById('qr_canvas');
                var dataURL = canvas.toDataURL('image/png');
                var dlBtn = document.getElementById('qr_download_btn');
                
                dlBtn.href = dataURL;
                var safeName = name ? name.toLowerCase().replace(/[^a-z0-9]/g, '-') : 'partner';
                dlBtn.download = 'qr-petling-' + safeName + '.png';
            }
            </script>
        <?php endif; ?>
    </div>
    <?php
}


// =========================================================================
// 3. AJAX HANDLER (ΓΙΑ ΝΑ ΜΗΝ ΚΑΝΕΙ REFRESH Η ΣΕΛΙΔΑ)
// =========================================================================
add_action('wp_ajax_petling_process_promo', 'petling_process_promo_ajax');
add_action('wp_ajax_nopriv_petling_process_promo', 'petling_process_promo_ajax');

function petling_process_promo_ajax() {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'petling_promo_nonce')) {
        wp_send_json_error('<div class="ptl-alert ptl-error">Σφάλμα ασφαλείας. Ανανεώστε τη σελίδα.</div>');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'petling_partner_leads';
    
    $email = sanitize_email($_POST['promo_email']);
    $prefix = sanitize_text_field(strtoupper($_POST['promo_prefix']));
    $partner_name = sanitize_text_field($_POST['promo_partner']);

    if (!is_email($email)) {
        wp_send_json_error('<div class="ptl-alert ptl-error">⚠️ Παρακαλώ εισάγετε ένα έγκυρο email.</div>');
    }

    $discount_amount = get_option('petling_promo_discount_amount', 2);
    $min_order       = get_option('petling_promo_min_order', 20);
    $lock_days       = get_option('petling_promo_lock_days', 20);

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE email = %s", $email));
    
    // Έλεγχος Ημερών
    if ($row) {
        $days_passed = (current_time('timestamp') - strtotime($row->last_claimed)) / 86400;
        if ($days_passed < $lock_days) {
            wp_send_json_error('<div class="ptl-alert ptl-error">🐶 Έχεις ήδη λάβει τον κωδικό σου πρόσφατα! Μπορείς να πάρεις νέο δωράκι σε ' . ceil($lock_days - $days_passed) . ' μέρες. Σε ευχαριστούμε!</div>');
        }
    }

    // Δημιουργία Μοναδικού Κουπονιού
    $unique_code = $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 5));
    
    if ( class_exists( 'WC_Coupon' ) ) {
        $coupon = new WC_Coupon();
        $coupon->set_code($unique_code);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount($discount_amount);
        $coupon->set_minimum_amount($min_order);
        $coupon->set_individual_use(true); 
        $coupon->set_usage_limit(1); 
        $coupon->set_email_restrictions(array($email)); 
        $coupon->save();
    }

    // Ενημέρωση Βάσης
    if ($row) {
        $wpdb->update($table_name, ['last_claimed' => current_time('mysql'), 'coupon_code' => $unique_code], ['email' => $email]);
    } else {
        $wpdb->insert($table_name, ['email' => $email, 'coupon_code' => $unique_code, 'last_claimed' => current_time('mysql')]);
    }

    // Αποστολή Email (ΕΝΣΩΜΑΤΩΜΕΝΟ ΣΤΟ TEMPLATE ΤΟΥ WOOCOMMERCE ΓΙΑ ΟΜΟΡΦΟ ΣΤΥΛ)
    $subject = "Το δωράκι σου από το Petling & $partner_name! 🎁";
    $heading = "Καλώς ήρθες στην παρέα μας!";
    
    $email_body = "<p>Γεια σου! 🐾</p>
    <p>Σε ευχαριστούμε που επισκέφθηκες $partner_name!</p>
    <p>Πάρε τον παρακάτω κωδικό για να γνωρίσεις την ποιότητα του Petling και να διαλέξεις ό,τι λαχταράς με έκπτωση <strong>-{$discount_amount}€</strong> (για αγορές άνω των {$min_order}€).</p>
    <div style='background:#f3f4f6; padding:15px; border-left:4px solid #C7B297; margin:20px 0;'>
        <strong>Ο Κωδικός σου:</strong> <span style='font-size:20px; color:#43282F;'>$unique_code</span>
    </div>
    <p><em>*Ο κωδικός είναι συνδεδεμένος αυστηρά με το email σου και ισχύει για 1 χρήση. Δεν συνδυάζεται με άλλες προσφορές.</em></p>
    <p>Σε περιμένουμε στο <a href='" . site_url() . "' style='color:#C7B297; font-weight:bold;'>petling.gr</a>!</p>";

    if ( function_exists('wc') ) {
        $mailer = wc()->mailer();
        $wrapped_message = $mailer->wrap_message($heading, $email_body);
        $mailer->send($email, $subject, $wrapped_message, array('Content-Type: text/html; charset=UTF-8'));
    } else {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $headers = array('Content-Type: text/html; charset=UTF-8', 'From: ' . $site_name . ' <' . $admin_email . '>');
        wp_mail($email, $subject, $email_body, $headers);
    }

    // Το μήνυμα που θα εμφανιστεί στην οθόνη
    $success_html = '<div class="ptl-alert ptl-success">
        <h3 style="margin-top:0; color:#5b9a68;">🎉 Ο κωδικός σου είναι έτοιμος!</h3>
        <p style="margin-bottom:10px;">Τσέκαρε το email σου (δες και στα Spam/Ανεπιθύμητα) για το δωράκι σου. Εναλλακτικά, κάνε αντιγραφή τον κωδικό σου από εδώ:</p>
        <div class="ptl-coupon-box">' . $unique_code . '</div><br>
        <a href="' . site_url() . '" class="ptl-btn-shop">Πάμε για ψώνια! &rarr;</a>
    </div>';

    wp_send_json_success($success_html);
}


// =========================================================================
// 4. ΤΟ DYNAMIC SHORTCODE ΓΙΑ ΤΗ ΣΕΛΙΔΑ
// =========================================================================
add_shortcode('petling_partner_promo', 'petling_partner_promo_shortcode');
function petling_partner_promo_shortcode($atts) {
    
    $a = shortcode_atts( array(
        'partner' => 'τον Συνεργάτη μας', 
        'prefix'  => 'PTL',               
    ), $atts );

    $partner_name = sanitize_text_field($a['partner']);
    $prefix       = sanitize_text_field(strtoupper($a['prefix']));
    $discount_amount = get_option('petling_promo_discount_amount', 2);

    $html = '<style>
        .ptl-promo-container { max-width: 400px; margin: 30px auto; background: #fffaf1; padding: 25px; border-radius: 12px; border: 1px dashed #C7B297; text-align: center; font-family: sans-serif; box-shadow: 0 4px 15px rgba(0,0,0,0.03); position: relative; }
        .ptl-promo-container h3 { color: #43282F; margin-top: 0; font-size: 20px; }
        .ptl-promo-container p { color: #555; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        .ptl-promo-form input[type="email"] { width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; margin-bottom: 15px; font-size: 15px; text-align: center; }
        .ptl-promo-form input[type="email"]:focus { outline: none; border-color: #C7B297; box-shadow: 0 0 5px rgba(199, 178, 151, 0.3); }
        .ptl-checkbox { display: flex; align-items: flex-start; text-align: left; font-size: 12px; color: #666; margin-bottom: 20px; gap: 8px; line-height: 1.4; }
        .ptl-btn-submit { width: 100%; padding: 15px; background: #5b9a68; color: #fff; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; transition: background 0.2s; }
        .ptl-btn-submit:hover { background: #4a8255; }
        .ptl-alert { padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: left; }
        .ptl-error { background: #fdf2f2; border: 1px solid #f8b4b4; color: #e62121; font-size: 14px; }
        .ptl-success { background: #eef7ee; border: 1px solid #5b9a68; color: #333; text-align: center; }
        .ptl-coupon-box { font-size: 24px; font-weight: bold; color: #43282F; background: #fff; padding: 10px; border: 2px dashed #43282F; display: inline-block; margin: 15px 0; letter-spacing: 2px; }
        .ptl-btn-shop { display: inline-block; background: #43282F; color: #fff !important; padding: 14px 25px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 10px; transition: background 0.2s; }
        .ptl-btn-shop:hover { background: #2a191d; color: #fff !important; }
        .ptl-loader { display: none; margin-top: 15px; font-weight: bold; color: #5b9a68; font-size: 15px; }
    </style>';

    $html .= '<div class="ptl-promo-container" id="ptl-promo-' . strtolower($prefix) . '">';
    $html .= '<div class="ptl-promo-inner">';
    
    $html .= '<h3>🎁 Πάρε την Έκπτωσή σου!</h3>';
    $html .= '<p>Βάλε το email σου για να λάβεις τον αποκλειστικό σου κωδικό <strong>-' . $discount_amount . '€</strong> από ' . esc_html($partner_name) . ' & το Petling!</p>';
    
    $html .= '<form class="ptl-promo-form">';
    $html .= wp_nonce_field('petling_promo_nonce', 'security', true, false); 
    $html .= '<input type="hidden" name="promo_prefix" value="' . esc_attr($prefix) . '">';
    $html .= '<input type="hidden" name="promo_partner" value="' . esc_attr($partner_name) . '">';
    $html .= '<input type="email" name="promo_email" placeholder="Το email σου εδώ..." required>';
    $html .= '<label class="ptl-checkbox"><input type="checkbox" name="ptl_consent" required> <span>Συμφωνώ να λαμβάνω νέα και προσφορές από το Petling.gr. Τα στοιχεία μου είναι ασφαλή.</span></label>';
    $html .= '<button type="submit" class="ptl-btn-submit">Λήψη Κωδικού</button>';
    
    $html .= '<div class="ptl-loader">⏳ Δημιουργία κωδικού...</div>';
    $html .= '<div class="ptl-message-area" style="margin-top:15px;"></div>';
    $html .= '</form>';
    
    $html .= '</div></div>';

    $html .= "<script>
    jQuery(document).ready(function($) {
        $('.ptl-promo-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var btn = form.find('.ptl-btn-submit');
            var loader = form.find('.ptl-loader');
            var msgArea = form.find('.ptl-message-area');
            
            btn.hide();
            loader.show();
            msgArea.html('');

            var data = form.serialize() + '&action=petling_process_promo';
            
            $.post('" . admin_url('admin-ajax.php') . "', data, function(response) {
                loader.hide();
                if(response.success) {
                    form.closest('.ptl-promo-inner').html(response.data);
                } else {
                    btn.show();
                    msgArea.html(response.data);
                }
            }).fail(function() {
                loader.hide();
                btn.show();
                msgArea.html('<div class=\"ptl-alert ptl-error\">Σφάλμα διακομιστή. Δοκιμάστε ξανά.</div>');
            });
        });
    });
    </script>";

    return $html;
}
?>