<?php
/*
Plugin Name: Petling Mass Mailer & CRM
Description: Συγκεντρώνει emails για μαζική αποστολή, σύστημα Φεστιβάλ (Lead Gen) και ένα πλήρες CRM (Όλα τα Emails) με φίλτρα, ταξινόμηση και διαγραφή.
Version: 3.0
Author: Petling Custom
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// 1. ΣΥΣΤΗΜΑ ΑΥΤΟΜΑΤΗΣ ΔΙΑΓΡΑΦΗΣ (UNSUBSCRIBE)
// =========================================================================
add_action( 'init', 'petling_handle_unsubscribe' );
function petling_handle_unsubscribe() {
    if ( isset( $_GET['petling_unsubscribe'] ) ) {
        $email = sanitize_email( base64_decode( $_GET['petling_unsubscribe'] ) );
        if ( is_email( $email ) ) {
            $unsubscribed = get_option( 'petling_unsubscribed_emails', array() );
            if ( ! in_array( $email, $unsubscribed ) ) {
                $unsubscribed[] = $email;
                update_option( 'petling_unsubscribed_emails', $unsubscribed );
            }
            wp_die( 
                'Το email <strong>' . esc_html( $email ) . '</strong> διαγράφηκε επιτυχώς από τη λίστα ενημερώσεων μας. Δεν θα λαμβάνετε πλέον μαζικά emails από εμάς.', 
                'Επιτυχής Διαγραφή', 
                array( 'response' => 200 ) 
            );
        }
    }
}

// =========================================================================
// 2. ΠΡΟΣΘΗΚΗ ΜΕΝΟΥ ΣΤΟ ΔΙΑΧΕΙΡΙΣΤΙΚΟ
// =========================================================================
add_action( 'admin_menu', 'petling_mass_mailer_admin_menu' );
function petling_mass_mailer_admin_menu() {
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'petling_mass_mailer_page', 'dashicons-pets', 55 );
        add_submenu_page( 'petling-main', 'Μαζικά Email & CRM', 'Μαζικά Email', 'manage_options', 'petling-mass-mailer', 'petling_mass_mailer_page' );
    } else {
        add_submenu_page( 'petling-main', 'Μαζικά Email & CRM', 'Μαζικά Email', 'manage_options', 'petling-mass-mailer', 'petling_mass_mailer_page' );
    }
}

// =========================================================================
// 3. ΣΕΛΙΔΑ PLUGIN (ΔΙΑΧΕΙΡΙΣΤΙΚΟ) - ΜΕ ΚΑΡΤΕΛΕΣ (TABS)
// =========================================================================
function petling_mass_mailer_page() {
    global $wpdb;

    $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'mailer';

    echo '<div class="wrap">';
    echo '<h1>Petling Mass Mailer & CRM</h1>';
    
    // --- ΚΑΡΤΕΛΕΣ (TABS) ---
    echo '<h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">';
    echo '<a href="?page=petling-mass-mailer&tab=mailer" class="nav-tab ' . ($active_tab == 'mailer' ? 'nav-tab-active' : '') . '">✉️ Αποστολή Emails</a>';
    echo '<a href="?page=petling-mass-mailer&tab=crm" class="nav-tab ' . ($active_tab == 'crm' ? 'nav-tab-active' : '') . '">🗂️ CRM (Όλα τα Emails)</a>';
    echo '</h2>';

    // =====================================================================
    // TAB 1: ΑΠΟΣΤΟΛΗ EMAILS
    // =====================================================================
    if ( $active_tab == 'mailer' ) {

        if ( isset( $_POST['petling_send_emails'] ) && check_admin_referer( 'petling_send_action' ) ) {
            $recipients = isset( $_POST['recipients'] ) ? $_POST['recipients'] : array();
            $subject    = sanitize_text_field( $_POST['email_subject'] );
            $message    = wp_kses_post( wp_unslash( $_POST['email_message'] ) );

            if ( empty( $recipients ) || empty( $subject ) || empty( $message ) ) {
                echo '<div class="notice notice-error"><p>Σφάλμα: Παρακαλώ επιλέξτε παραλήπτες, γράψτε θέμα και μήνυμα.</p></div>';
            } else {
                $sent_count = 0;
                $headers = array('Content-Type: text/html; charset=UTF-8');
                
                $custom_logo_id = get_theme_mod( 'custom_logo' );
                $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );

                $base_template = '<div style="background-color: #f4f4f4; padding: 40px 20px; font-family: Arial, Helvetica, sans-serif;">';
                $base_template .= '<div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">';
                
                if ( $logo_url ) {
                    $base_template .= '<div style="text-align: center; margin-bottom: 25px;">';
                    $base_template .= '<img src="' . esc_url( $logo_url ) . '" alt="' . get_bloginfo('name') . '" style="max-width: 200px; height: auto;" />';
                    $base_template .= '</div>';
                }
                
                $base_template .= '<div style="color: #444444; font-size: 16px; line-height: 1.6;">';
                $base_template .= $message;
                $base_template .= '</div></div>';

                foreach ( $recipients as $email ) {
                    $email = sanitize_email( $email );
                    if ( is_email( $email ) ) {
                        $unsub_link = site_url( '/?petling_unsubscribe=' . base64_encode( $email ) );
                        
                        $final_email_content = $base_template;
                        $final_email_content .= '<div style="text-align: center; margin-top: 20px; color: #999999; font-size: 12px;">';
                        $final_email_content .= '<p>Λάβατε αυτό το email επειδή είστε εγγεγραμμένοι στο ' . get_bloginfo('name') . '.</p>';
                        $final_email_content .= '<p><a href="' . esc_url( $unsub_link ) . '" style="color: #999999; text-decoration: underline;">Διαγραφή από τη λίστα (Unsubscribe)</a></p>';
                        $final_email_content .= '</div></div>';

                        wp_mail( $email, $subject, $final_email_content, $headers );
                        $sent_count++;
                    }
                }
                echo '<div class="notice notice-success is-dismissible"><p>Επιτυχία! Στάλθηκαν ' . $sent_count . ' emails.</p></div>';
            }
        }

        $all_emails = array();
        $users = get_users();
        foreach ( $users as $user ) { $all_emails[] = $user->user_email; }

        if ( class_exists( 'WooCommerce' ) ) {
            $legacy_emails = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_billing_email' AND meta_value != ''");
            if ( is_array( $legacy_emails ) ) $all_emails = array_merge( $all_emails, $legacy_emails );

            $hpos_table = $wpdb->prefix . 'wc_orders';
            if ( $wpdb->get_var("SHOW TABLES LIKE '$hpos_table'") == $hpos_table ) {
                $hpos_emails = $wpdb->get_col("SELECT DISTINCT billing_email FROM {$hpos_table} WHERE billing_email != ''");
                if ( is_array( $hpos_emails ) ) $all_emails = array_merge( $all_emails, $hpos_emails );
            }
        }

        $elementor_table = $wpdb->prefix . 'e_submissions_values';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$elementor_table'") == $elementor_table ) {
            $results = $wpdb->get_results( "SELECT value FROM $elementor_table WHERE value LIKE '%@%.%'" );
            foreach ( $results as $row ) { $all_emails[] = $row->value; }
        }

        $festival_leads = get_option('petling_festival_leads', array());
        $festival_emails = array_keys($festival_leads);
        if ( !empty($festival_emails) ) {
            $all_emails = array_merge( $all_emails, $festival_emails );
        }

        $all_emails = array_filter( $all_emails, 'is_email' );
        $all_emails = array_unique( $all_emails );
        $unsubscribed = get_option( 'petling_unsubscribed_emails', array() );
        $all_emails = array_diff( $all_emails, $unsubscribed );
        sort($all_emails); 

        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'petling_send_action' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Τίτλος (Θέμα) Email:</th>
                    <td><input type="text" name="email_subject" class="regular-text" required style="width: 100%;" /></td>
                </tr>
                <tr>
                    <th scope="row">Μήνυμα:</th>
                    <td><?php wp_editor( '', 'email_message', array('media_buttons' => true, 'textarea_name' => 'email_message', 'textarea_rows' => 12) ); ?></td>
                </tr>
                <tr>
                    <th scope="row">Παραλήπτες: <br><br>
                        <small><a href="#" onclick="jQuery('.email-checkbox').prop('checked', true); return false;" style="display:block; margin-bottom:5px;">✅ Επιλογή όλων</a></small>
                        <small><a href="#" onclick="jQuery('.email-checkbox').prop('checked', false); return false;" style="display:block; margin-bottom:15px; color:#d63638;">❌ Καθαρισμός</a></small>
                        <small><a href="#" onclick="jQuery('.email-checkbox').prop('checked', false); jQuery('.source-festival').prop('checked', true); return false;" style="display:block; padding: 5px; background: #C7B297; color: #fff; border-radius: 4px; text-align: center; text-decoration: none; font-weight: bold;">🎯 Επιλογή ΜΟΝΟ <br>χρηστών Φεστιβάλ</a></small>
                    </th>
                    <td style="max-height: 400px; overflow-y: auto; display: block; border: 1px solid #ccc; padding: 10px; background: #fff;">
                        <?php if ( ! empty( $all_emails ) ) : ?>
                            <?php foreach ( $all_emails as $email ) : 
                                $is_fest = in_array($email, $festival_emails);
                                $class = $is_fest ? 'email-checkbox source-festival' : 'email-checkbox';
                                $tag = $is_fest ? ' <strong style="color:#C7B297; font-size:11px;">(Φεστιβάλ)</strong>' : '';
                            ?>
                                <label style="display: block; margin-bottom: 5px; padding: 3px; border-bottom: 1px solid #eee;">
                                    <input type="checkbox" name="recipients[]" value="<?php echo esc_attr( $email ); ?>" class="<?php echo esc_attr($class); ?>" />
                                    <?php echo esc_html( $email ) . $tag; ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p>Δεν βρέθηκαν emails.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="petling_send_emails" class="button button-primary" value="Αποστολή Email"></p>
        </form>
        <?php
    }

    // =====================================================================
    // TAB 2: CRM OLA TA EMAILS (Ο ΠΙΝΑΚΑΣ)
    // =====================================================================
    elseif ( $active_tab == 'crm' ) {
        
        // Λογική Διαγραφής (Unsubscribe)
        if ( isset($_GET['delete_lead']) && isset($_GET['_wpnonce']) ) {
            if ( wp_verify_nonce($_GET['_wpnonce'], 'delete_lead_' . $_GET['delete_lead']) ) {
                $del_email = sanitize_email($_GET['delete_lead']);
                
                // Προσθήκη στα Unsubscribed
                $unsub = get_option('petling_unsubscribed_emails', array());
                if (!in_array($del_email, $unsub)) {
                    $unsub[] = $del_email;
                    update_option('petling_unsubscribed_emails', $unsub);
                }
                
                // Διαγραφή και από τα Leads Φεστιβάλ αν υπήρχε εκεί
                $f_leads = get_option('petling_festival_leads', array());
                if (isset($f_leads[$del_email])) {
                    unset($f_leads[$del_email]);
                    update_option('petling_festival_leads', $f_leads);
                }

                echo '<div class="notice notice-success is-dismissible"><p>Το email <strong>' . esc_html($del_email) . '</strong> διεγράφη από το CRM και προστέθηκε στη λίστα Unsubscribed.</p></div>';
            }
        }

        // --- ΣΥΓΚΕΝΤΡΩΣΗ ΟΛΩΝ ΤΩΝ ΔΕΔΟΜΕΝΩΝ ΓΙΑ ΤΟ CRM ---
        $crm_leads = array();

        // 1. Φεστιβάλ
        $festival_leads = get_option('petling_festival_leads', array());
        foreach ($festival_leads as $em => $data) {
            $em = strtolower(sanitize_email($em));
            $crm_leads[$em] = array('name' => $data['name'], 'sources' => array('Φεστιβάλ'), 'date' => $data['date']);
        }

        // 2. Χρήστες WP
        $users = get_users();
        foreach ($users as $u) {
            $em = strtolower(sanitize_email($u->user_email));
            if (!isset($crm_leads[$em])) { $crm_leads[$em] = array('name' => $u->display_name, 'sources' => array(), 'date' => $u->user_registered); }
            if (!in_array('Χρήστης', $crm_leads[$em]['sources'])) { $crm_leads[$em]['sources'][] = 'Χρήστης'; }
        }

        // 3. WooCommerce
        $woo_emails = array();
        if ( class_exists( 'WooCommerce' ) ) {
            $legacy_emails = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_billing_email' AND meta_value != ''");
            if (is_array($legacy_emails)) $woo_emails = array_merge($woo_emails, $legacy_emails);
            
            $hpos_table = $wpdb->prefix . 'wc_orders';
            if ( $wpdb->get_var("SHOW TABLES LIKE '$hpos_table'") == $hpos_table ) {
                $hpos_emails = $wpdb->get_col("SELECT DISTINCT billing_email FROM {$hpos_table} WHERE billing_email != ''");
                if (is_array($hpos_emails)) $woo_emails = array_merge($woo_emails, $hpos_emails);
            }
        }
        foreach ($woo_emails as $em) {
            $em = strtolower(sanitize_email($em));
            if (empty($em) || !is_email($em)) continue;
            if (!isset($crm_leads[$em])) { $crm_leads[$em] = array('name' => '-', 'sources' => array(), 'date' => '-'); }
            if (!in_array('WooCommerce', $crm_leads[$em]['sources'])) { $crm_leads[$em]['sources'][] = 'WooCommerce'; }
        }

        // 4. Φόρμες (Elementor)
        $elementor_table = $wpdb->prefix . 'e_submissions_values';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$elementor_table'") == $elementor_table ) {
            $results = $wpdb->get_results( "SELECT value FROM $elementor_table WHERE value LIKE '%@%.%'" );
            foreach ( $results as $row ) {
                $em = strtolower(sanitize_email($row->value));
                if (empty($em) || !is_email($em)) continue;
                if (!isset($crm_leads[$em])) { $crm_leads[$em] = array('name' => '-', 'sources' => array(), 'date' => '-'); }
                if (!in_array('Φόρμα', $crm_leads[$em]['sources'])) { $crm_leads[$em]['sources'][] = 'Φόρμα'; }
            }
        }

        // Αφαίρεση όσων έχουν διαγραφεί (Unsubscribed)
        $unsubscribed = get_option( 'petling_unsubscribed_emails', array() );
        foreach ($unsubscribed as $un) {
            $un = strtolower(sanitize_email($un));
            if (isset($crm_leads[$un])) unset($crm_leads[$un]);
        }

        ?>
        <style>
            th.sortable { cursor: pointer; transition: 0.2s; }
            th.sortable:hover { background: #f0f0f1; }
            .source-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; margin: 2px; }
            .tag-fest { background: #eef7eb; color: #5b9a68; border: 1px solid #5b9a68; }
            .tag-woo { background: #f0ebf7; color: #6e459c; border: 1px solid #6e459c; }
            .tag-user { background: #e6f0f9; color: #2271b1; border: 1px solid #2271b1; }
            .tag-form { background: #fcf0f1; color: #d63638; border: 1px solid #d63638; }
        </style>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap:10px;">
                <h3 style="margin: 0;">Διαχείριση Πελατολογίου (<?php echo count($crm_leads); ?> Ενεργά Emails)</h3>
                
                <div style="display:flex; gap:10px; align-items:center;">
                    <!-- Dropdown Φίλτρο Πηγής -->
                    <select id="ptl-crm-filter" style="padding: 5px;">
                        <option value="">Όλες οι Πηγές</option>
                        <option value="Φεστιβάλ">Μόνο Φεστιβάλ</option>
                        <option value="WooCommerce">Μόνο WooCommerce</option>
                        <option value="Χρήστης">Μόνο Εγγεγραμμένοι Χρήστες</option>
                        <option value="Φόρμα">Μόνο από Φόρμες</option>
                    </select>

                    <!-- Ζωντανό Φίλτρο / Αναζήτηση -->
                    <input type="text" id="ptl-crm-search" placeholder="🔍 Αναζήτηση..." style="width: 250px; padding: 5px;">
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped" id="ptl-crm-table">
                <thead>
                    <tr>
                        <th class="sortable" style="width: 20%;">Όνομα <span>↕</span></th>
                        <th class="sortable" style="width: 25%;">Email <span>↕</span></th>
                        <th style="width: 25%;">Πηγή / Ετικέτες</th>
                        <th class="sortable" style="width: 15%;">Ημερομηνία <span>↕</span></th>
                        <th style="width: 15%;">Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($crm_leads) ) : ?>
                        <tr><td colspan="5" style="text-align:center;">Δεν υπάρχουν επαφές στο CRM.</td></tr>
                    <?php else : 
                        foreach ( $crm_leads as $email => $data ) : 
                            $date_formatted = ($data['date'] != '-') ? date_i18n('d/m/Y', strtotime($data['date'])) : '-';
                            $delete_url = wp_nonce_url( admin_url("admin.php?page=petling-mass-mailer&tab=crm&delete_lead=" . urlencode($email)), 'delete_lead_' . $email );
                    ?>
                        <tr class="crm-row">
                            <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                            <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td>
                            <td class="crm-source-cell">
                                <?php foreach($data['sources'] as $src) {
                                    $class = 'source-tag ';
                                    if ($src == 'Φεστιβάλ') $class .= 'tag-fest';
                                    elseif ($src == 'WooCommerce') $class .= 'tag-woo';
                                    elseif ($src == 'Χρήστης') $class .= 'tag-user';
                                    else $class .= 'tag-form';
                                    echo '<span class="' . $class . '">' . esc_html($src) . '</span> ';
                                } ?>
                            </td>
                            <td><?php echo esc_html($date_formatted); ?></td>
                            <td>
                                <a href="<?php echo esc_url($delete_url); ?>" class="button button-small" style="color:#d63638; border-color:#d63638;" onclick="return confirm('Να διαγραφεί από το CRM και τη λίστα μαζικών email;');">❌ Διαγραφή</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Script για Ταξινόμηση και Αναζήτηση -->
        <script>
            jQuery(document).ready(function($){
                
                // Συνδυαστικό Φιλτράρισμα (Αναζήτηση + Dropdown)
                function filterTable() {
                    var searchTerm = $('#ptl-crm-search').val().toLowerCase();
                    var sourceTerm = $('#ptl-crm-filter').val().toLowerCase();
                    
                    $('#ptl-crm-table tbody .crm-row').each(function() {
                        var text = $(this).text().toLowerCase();
                        var sources = $(this).find('.crm-source-cell').text().toLowerCase();
                        
                        var matchSearch = text.indexOf(searchTerm) > -1;
                        var matchSource = sourceTerm === "" || sources.indexOf(sourceTerm) > -1;
                        
                        $(this).toggle(matchSearch && matchSource);
                    });
                }
                
                $('#ptl-crm-search').on('keyup', filterTable);
                $('#ptl-crm-filter').on('change', filterTable);

                // Ταξινόμηση Στηλών (Αύξουσα / Φθίνουσα)
                $('th.sortable').on('click', function(){
                    var table = $(this).parents('table').eq(0);
                    var rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()));
                    this.asc = !this.asc;
                    if (!this.asc){rows = rows.reverse()}
                    for (var i = 0; i < rows.length; i++){table.append(rows[i])}
                    
                    // Reset arrows
                    $('th.sortable span').text('↕');
                    $(this).find('span').text(this.asc ? '▲' : '▼');
                });
                
                function comparer(index) {
                    return function(a, b) {
                        var valA = getCellValue(a, index), valB = getCellValue(b, index);
                        return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.toString().localeCompare(valB);
                    }
                }
                function getCellValue(row, index){ return $(row).children('td').eq(index).text() }
            });
        </script>
        <?php
    }
    
    echo '</div>'; 
}

// =========================================================================
// 4. SHORTCODE ΓΙΑ ΤΟ ΦΕΣΤΙΒΑΛ (LEAD GENERATION)
// Χρήση: [petling_festival_promo redirect="/dog-festival"]
// =========================================================================

add_shortcode('petling_festival_promo', 'ptl_festival_promo_shortcode');
function ptl_festival_promo_shortcode($atts) {
    $atts = shortcode_atts(array('redirect' => home_url()), $atts, 'petling_festival_promo');
    $redirect_url = esc_url($atts['redirect']);
    $ajax_url = admin_url('admin-ajax.php');

    $html = '<style>
        .ptl-festival-box { max-width: 550px; margin: 40px auto; background: #fffaf1; padding: 40px; border-radius: 12px; border: 2px dashed #C7B297; font-family: sans-serif; box-shadow: 0 5px 20px rgba(0,0,0,0.05); text-align: center; }
        .ptl-festival-box h3 { color: #43282F; margin-top: 0; font-size: 24px; margin-bottom:10px; }
        .ptl-festival-box p.ptl-desc { color: #555; font-size: 15px; margin-bottom: 25px; line-height: 1.5; }
        .ptl-festival-box label.ptl-label { display: block; font-weight: bold; color: #43282F; margin-bottom: 8px; margin-top: 20px; text-align: left; }
        .ptl-festival-box input[type="text"], .ptl-festival-box input[type="email"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 15px; background: #ebf0fa; }
        .ptl-festival-checkboxes { margin-top: 25px; font-size: 13px; color: #555; text-align: left; border-top: 1px dashed #ccc; padding-top: 20px; }
        .ptl-festival-checkboxes label { font-weight: normal; display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer; }
        .ptl-btn-submit { background: #C7B297; color: #fff; padding: 14px 30px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; transition: 0.2s; margin-top: 20px; width: 100%; }
        .ptl-btn-submit:hover { background: #43282F; }
        .ptl-loader { display: none; margin-top: 15px; font-weight: bold; color: #43282F; }
    </style>';

    $html .= '<div class="ptl-festival-box"><form id="ptl-festival-form">';
    $html .= wp_nonce_field('ptl_festival_nonce', 'security', true, false);
    $html .= '<input type="hidden" name="action" value="ptl_save_festival_lead">';
    $html .= '<h3>📸 Βρες τη φωτογραφία σου!</h3><p class="ptl-desc">Συμπλήρωσε τα στοιχεία σου για να ξεκλειδώσεις το άλμπουμ του φεστιβάλ και να λάβεις μια μοναδική έκπληξη στο email σου!</p>';
    $html .= '<label class="ptl-label">Το Όνομά σου:</label><input type="text" name="user_name" placeholder="π.χ. Γιώργος" required>';
    $html .= '<label class="ptl-label">Το Email σου:</label><input type="email" name="user_email" placeholder="Το email σου εδώ..." required>';
    $html .= '<div class="ptl-festival-checkboxes"><label><input type="checkbox" name="consent_email" required> Συμφωνώ να λάβω το link και την έκπτωσή μου σε αυτό το email.</label><label><input type="checkbox" name="consent_marketing"> Θέλω να λαμβάνω νέα και προσφορές από το Petling.</label></div>';
    $html .= '<button type="submit" class="ptl-btn-submit">Ξεκλείδωμα Άλμπουμ!</button><div class="ptl-loader" id="ptl-festival-loader">⏳ Γίνεται ξεκλείδωμα...</div></form></div>';

    $html .= "<script>
        jQuery(document).ready(function($) {
            $('#ptl-festival-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this), btn = form.find('.ptl-btn-submit'), loader = form.find('#ptl-festival-loader');
                btn.hide(); loader.show();
                $.post('{$ajax_url}', form.serialize(), function(response) {
                    if(response.success) { window.location.href = '{$redirect_url}'; } 
                    else { alert('Σφάλμα. Δοκιμάστε ξανά.'); loader.hide(); btn.show(); }
                }).fail(function() { alert('Σφάλμα σύνδεσης.'); loader.hide(); btn.show(); });
            });
        });
    </script>";

    return $html;
}

// =========================================================================
// 5. ΑΠΟΘΗΚΕΥΣΗ TOY LEAD ΣΤΗ ΒΑΣΗ ΔΕΔΟΜΕΝΩΝ (AJAX)
// =========================================================================
add_action('wp_ajax_ptl_save_festival_lead', 'ptl_save_festival_lead_ajax');
add_action('wp_ajax_nopriv_ptl_save_festival_lead', 'ptl_save_festival_lead_ajax');

function ptl_save_festival_lead_ajax() {
    // Αφαιρούμε το αυστηρό check_ajax_referer γιατί δημιουργεί 403 errors
    // με τα πρόσθετα Cache (μοιράζει το κλειδί του admin στους επισκέπτες).
    // check_ajax_referer('ptl_festival_nonce', 'security');

    $name = isset($_POST['user_name']) ? sanitize_text_field($_POST['user_name']) : '';
    $email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';

    if ( is_email($email) ) {
        $festival_leads = get_option('petling_festival_leads', array());
        
        $festival_leads[$email] = array(
            'name'  => $name,
            'email' => $email,
            'date'  => current_time('mysql')
        );
        
        update_option('petling_festival_leads', $festival_leads);
        
        wp_send_json_success();
    } else {
        wp_send_json_error('Παρακαλώ εισάγετε ένα έγκυρο email.');
    }
}