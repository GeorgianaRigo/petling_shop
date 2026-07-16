<?php
/*
Plugin Name: Petling VIP Club
Plugin URI: https://petling.gr
Description: Δυναμικό σύστημα εκπτώσεων VIP (Tiers: Απλό Μέλος, Silver, Gold). Το welcome10 μπλοκάρεται για τα εγγεγραμμένα μέλη με σχετικό μήνυμα.
Version: 5.2
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 0. ΚΕΝΤΡΙΚΟ ΜΕΝΟΥ PETLING
// =========================================================================
add_action( 'admin_menu', 'ptl_vip_admin_menu' );
function ptl_vip_admin_menu() {
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'ptl_vip_admin_page_html', 'dashicons-pets', 55 );
        add_submenu_page( 'petling-main', 'Petling VIP Club', 'VIP Club', 'manage_options', 'petling-main', 'ptl_vip_admin_page_html' );
    } else {
        add_submenu_page( 'petling-main', 'Petling VIP Club', 'VIP Club', 'manage_options', 'petling-vip-rules', 'ptl_vip_admin_page_html' );
    }
}

// =========================================================================
// 1. ΣΕΛΙΔΑ ΡΥΘΜΙΣΕΩΝ ΚΑΙ ΠΕΛΑΤΩΝ
// =========================================================================
function ptl_vip_admin_page_html() {
    if ( isset( $_POST['ptl_vip_save_settings'] ) && wp_verify_nonce( $_POST['ptl_vip_nonce'], 'ptl_vip_save_action' ) ) {
        update_option( 'ptl_vip_base_discount', floatval( $_POST['ptl_vip_base_discount'] ) );
        update_option( 'ptl_vip_silver_spend', floatval( $_POST['ptl_vip_silver_spend'] ) );
        update_option( 'ptl_vip_silver_discount', floatval( $_POST['ptl_vip_silver_discount'] ) );
        update_option( 'ptl_vip_gold_spend', floatval( $_POST['ptl_vip_gold_spend'] ) );
        update_option( 'ptl_vip_gold_discount', floatval( $_POST['ptl_vip_gold_discount'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Οι ρυθμίσεις αποθηκεύτηκαν δυναμικά!</p></div>';
    }

    if ( isset( $_POST['ptl_vip_update_user_row'] ) && wp_verify_nonce( $_POST['ptl_vip_user_row_nonce'], 'update_user_row_action' ) ) {
        $u_id = intval( $_POST['edit_user_id'] );
        $new_spent = floatval( $_POST['new_spent_amount'] );
        $manual_tier = sanitize_text_field( $_POST['manual_tier'] );
        
        update_user_meta( $u_id, '_money_spent', $new_spent );
        if ( $manual_tier === 'auto' ) {
            delete_user_meta( $u_id, '_ptl_manual_vip_tier' );
        } else {
            update_user_meta( $u_id, '_ptl_manual_vip_tier', $manual_tier );
        }
        echo '<div class="notice notice-success is-dismissible"><p>Τα στοιχεία ενημερώθηκαν επιτυχώς!</p></div>';
    }

    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
    ?>
    <div class="wrap">
        <h1 style="color: #43282F; display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
            <span class="dashicons dashicons-star-filled" style="font-size: 28px; width: 28px; height: 28px; color: #C7B297;"></span> 
            Petling VIP Club
        </h1>
        <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="?page=<?php echo isset($_GET['page']) ? esc_attr($_GET['page']) : 'petling-vip-rules'; ?>&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">Ρυθμίσεις & Κανόνες</a>
            <a href="?page=<?php echo isset($_GET['page']) ? esc_attr($_GET['page']) : 'petling-vip-rules'; ?>&tab=customers" class="nav-tab <?php echo 'customers' === $active_tab ? 'nav-tab-active' : ''; ?>">Λίστα VIP Πελατών</a>
        </h2>
        <?php 
        if ( 'settings' === $active_tab ) ptl_vip_render_settings_tab();
        elseif ( 'customers' === $active_tab ) ptl_vip_render_customers_tab();
        ?>
    </div>
    <?php
}

function ptl_vip_render_settings_tab() {
    $base_disc   = get_option( 'ptl_vip_base_discount', 3 );
    $silver_spnd = get_option( 'ptl_vip_silver_spend', 1000 );
    $silver_disc = get_option( 'ptl_vip_silver_discount', 7 );
    $gold_spnd   = get_option( 'ptl_vip_gold_spend', 2000 );
    $gold_disc   = get_option( 'ptl_vip_gold_discount', 10 );
    ?>
    <form method="post" action="">
        <?php wp_nonce_field( 'ptl_vip_save_action', 'ptl_vip_nonce' ); ?>
        
        <div style="background: #fff; border: 1px solid #ccc; border-left: 4px solid #C7B297; padding: 20px; max-width: 800px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; color: #2271b1;">Αυτόματες Εκπτώσεις VIP (Tiers)</h2>
            <p>Ορίστε τα ποσοστά έκπτωσης που θα εφαρμόζονται αυτόματα στο καλάθι των εγγεγραμμένων χρηστών, ανάλογα με τον συνολικό τζίρο τους.</p>
            <table class="form-table">
                <tr>
                    <th style="width: 200px;">Απλό Μέλος (Εγγραφή)</th>
                    <td><span style="display:inline-flex; align-items:center; gap:8px;">Έκπτωση: <input type="number" step="0.1" name="ptl_vip_base_discount" value="<?php echo esc_attr($base_disc); ?>" style="width:70px;"> %</span></td>
                </tr>
                <tr>
                    <th>Silver VIP</th>
                    <td>
                        <span style="display:inline-flex; align-items:center; gap:8px; margin-right: 30px;">Τζίρος πάνω από: <input type="number" step="1" name="ptl_vip_silver_spend" value="<?php echo esc_attr($silver_spnd); ?>" style="width:80px;"> €</span>
                        <span style="display:inline-flex; align-items:center; gap:8px;">Έκπτωση: <input type="number" step="0.1" name="ptl_vip_silver_discount" value="<?php echo esc_attr($silver_disc); ?>" style="width:70px;"> %</span>
                    </td>
                </tr>
                <tr>
                    <th>Gold VIP</th>
                    <td>
                        <span style="display:inline-flex; align-items:center; gap:8px; margin-right: 30px;">Τζίρος πάνω από: <input type="number" step="1" name="ptl_vip_gold_spend" value="<?php echo esc_attr($gold_spnd); ?>" style="width:80px;"> €</span>
                        <span style="display:inline-flex; align-items:center; gap:8px;">Έκπτωση: <input type="number" step="0.1" name="ptl_vip_gold_discount" value="<?php echo esc_attr($gold_disc); ?>" style="width:70px;"> %</span>
                    </td>
                </tr>
            </table>
        </div>

        <p><button type="submit" name="ptl_vip_save_settings" class="button button-primary button-large">Αποθήκευση Ρυθμίσεων</button></p>
    </form>
    <?php
}

function ptl_vip_render_customers_tab() {
    $base_disc   = get_option( 'ptl_vip_base_discount', 3 );
    $silver_spnd = get_option( 'ptl_vip_silver_spend', 1000 );
    $silver_disc = get_option( 'ptl_vip_silver_discount', 7 );
    $gold_spnd   = get_option( 'ptl_vip_gold_spend', 2000 );
    $gold_disc   = get_option( 'ptl_vip_gold_discount', 10 );

    $search_query = isset( $_GET['s'] ) ? sanitize_text_field( strtolower( $_GET['s'] ) ) : '';
    $orderby      = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'spent';
    $order        = ( isset( $_GET['order'] ) && $_GET['order'] === 'asc' ) ? 'asc' : 'desc';
    $page_slug    = isset( $_GET['page'] ) ? $_GET['page'] : 'petling-vip-rules';

    $args = array( 'number' => -1 );
    if ( ! empty( $search_query ) ) {
        $args['search'] = '*' . $search_query . '*';
        $args['search_columns'] = array('user_login', 'user_email', 'user_nicename', 'display_name');
    } else {
        $args['meta_query'] = array(
            'relation' => 'OR',
            array( 'key' => '_money_spent', 'value' => 0, 'compare' => '>' ),
            array( 'key' => '_ptl_manual_vip_tier', 'compare' => 'EXISTS' )
        );
    }
    
    $user_query = new WP_User_Query( $args );
    $users = $user_query->get_results();

    $processed_users = array();
    $gold_count = 0; $silver_count = 0;

    foreach ( $users as $user ) {
        $spent  = (float) get_user_meta( $user->ID, '_money_spent', true );
        $manual = get_user_meta( $user->ID, '_ptl_manual_vip_tier', true );
        if ( empty( $manual ) ) $manual = 'auto';

        if ( empty( $search_query ) && $spent <= 0 && $manual === 'auto' ) continue;

        $first_name = get_user_meta( $user->ID, 'first_name', true );
        $last_name  = get_user_meta( $user->ID, 'last_name', true );
        $full_name  = trim( $first_name . ' ' . $last_name );
        if ( empty( $full_name ) ) $full_name = $user->display_name;

        $effective_tier = '';
        if ( $manual === 'none' ) $effective_tier = 'none';
        elseif ( $manual === 'gold' ) $effective_tier = 'gold';
        elseif ( $manual === 'silver' ) $effective_tier = 'silver';
        elseif ( $manual === 'base' ) $effective_tier = 'base';
        else {
            if ( $spent >= $gold_spnd ) $effective_tier = 'gold';
            elseif ( $spent >= $silver_spnd ) $effective_tier = 'silver';
            else $effective_tier = 'base';
        }

        if ( $effective_tier === 'none' ) {
            $level_name = 'Χωρίς Έκπτωση';
            $badge = '<span style="background:#f4f4f4; color:#666; padding:4px 10px; border-radius:12px; font-weight:bold; border:1px solid #ddd;">🚫 Χωρίς Έκπτωση (0%)</span>';
        } elseif ( $effective_tier === 'gold' ) {
            $gold_count++;
            $level_name = 'Gold VIP';
            $badge = '<span style="background:#fff4cc; color:#b8860b; padding:4px 10px; border-radius:12px; font-weight:bold; border:1px solid #ffe57f;">👑 Gold ('.esc_html($gold_disc).'%)</span>';
        } elseif ( $effective_tier === 'silver' ) {
            $silver_count++;
            $level_name = 'Silver VIP';
            $badge = '<span style="background:#f0f0f0; color:#555; padding:4px 10px; border-radius:12px; font-weight:bold; border:1px solid #ccc;">🥈 Silver ('.esc_html($silver_disc).'%)</span>';
        } else {
            $level_name = 'Απλό Μέλος';
            $badge = '<span style="background:#eef7ee; color:#5b9a68; padding:4px 10px; border-radius:12px; font-weight:bold; border:1px solid #c3e6cb;">🌱 Απλό ('.esc_html($base_disc).'%)</span>';
        }

        if ( $search_query ) {
            $target = strtolower( $full_name . ' ' . $user->user_email . ' ' . $level_name . ' ' . $spent );
            if ( strpos( $target, $search_query ) === false ) continue;
        }

        $processed_users[] = array(
            'id'        => $user->ID,
            'name'      => $full_name,
            'email'     => $user->user_email,
            'spent'     => $spent,
            'manual'    => $manual,
            'level'     => $level_name,
            'badge'     => $badge
        );
    }

    usort($processed_users, function($a, $b) use ($orderby, $order) {
        if ( $orderby === 'name' ) { $res = strcasecmp($a['name'], $b['name']); } 
        elseif ( $orderby === 'email' ) { $res = strcasecmp($a['email'], $b['email']); } 
        else { $res = $a['spent'] <=> $b['spent']; }
        return $order === 'asc' ? $res : -$res;
    });

    function ptl_get_sort_link( $column_key, $label, $current_orderby, $current_order, $search_query, $page_slug ) {
        $new_order = ( $current_orderby === $column_key && $current_order === 'desc' ) ? 'asc' : 'desc';
        $url = "?page={$page_slug}&tab=customers&orderby={$column_key}&order={$new_order}";
        if ( $search_query ) $url .= "&s=" . urlencode($search_query);
        $arrow = ($current_orderby === $column_key) ? ($current_order === 'desc' ? ' ↓' : ' ↑') : '';
        return '<a href="' . esc_url($url) . '" style="text-decoration:none; color:#2c3338;">' . esc_html($label) . $arrow . '</a>';
    }
    ?>
    
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 20px; flex-wrap:wrap; gap: 20px;">
        <div style="display:flex; gap: 20px;">
            <div style="background:#fff; padding:15px; border-left:4px solid #ffd700; border-radius:4px; box-shadow:0 1px 2px rgba(0,0,0,0.05); min-width: 180px;">
                <h4 style="margin:0 0 5px 0; color:#555;">Gold VIPs</h4>
                <span style="font-size: 24px; font-weight: bold; color: #43282F;"><?php echo $gold_count; ?></span>
            </div>
            <div style="background:#fff; padding:15px; border-left:4px solid #c0c0c0; border-radius:4px; box-shadow:0 1px 2px rgba(0,0,0,0.05); min-width: 180px;">
                <h4 style="margin:0 0 5px 0; color:#555;">Silver VIPs</h4>
                <span style="font-size: 24px; font-weight: bold; color: #43282F;"><?php echo $silver_count; ?></span>
            </div>
        </div>

        <form method="get" style="display:flex; gap:10px; align-items:center; background:#fff; padding:15px; border-radius:4px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
            <input type="hidden" name="tab" value="customers">
            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
            <input type="text" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="Αναζήτηση (Όνομα, Email...)" style="width:250px;">
            <button type="submit" class="button">Αναζήτηση</button>
            <?php if ( ! empty( $_GET['s'] ) ) : ?>
                <a href="?page=<?php echo esc_attr($page_slug); ?>&tab=customers" class="button">Καθαρισμός</a>
            <?php endif; ?>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th><?php echo ptl_get_sort_link( 'name', 'Ονοματεπώνυμο', $orderby, $order, $search_query, $page_slug ); ?></th>
                <th><?php echo ptl_get_sort_link( 'email', 'Email', $orderby, $order, $search_query, $page_slug ); ?></th>
                <th>Τρέχον Επίπεδο</th>
                <th><?php echo ptl_get_sort_link( 'spent', 'Συνολικός Τζίρος & Επεξεργασία', $orderby, $order, $search_query, $page_slug ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ( ! empty( $processed_users ) ) {
                foreach ( $processed_users as $u ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( $u['id'] ) . '</td>';
                    echo '<td><strong>' . esc_html( $u['name'] ) . '</strong></td>';
                    echo '<td><a href="mailto:' . esc_attr( $u['email'] ) . '">' . esc_html( $u['email'] ) . '</a></td>';
                    echo '<td>' . $u['badge'] . '<br><small style="color:#888;">(Από αγορές: ' . number_format( $u['spent'], 2, ',', '.' ) . '€)</small></td>';
                    echo '<td style="background: #f9fcf9; border-left: 2px solid #5b9a68;">';
                    ?>
                        <form method="post" action="" style="display:flex; align-items:center; gap:10px; margin:0;">
                            <?php wp_nonce_field( 'update_user_row_action', 'ptl_vip_user_row_nonce' ); ?>
                            <input type="hidden" name="edit_user_id" value="<?php echo esc_attr($u['id']); ?>">
                            <div style="display:flex; flex-direction:column; gap:3px;">
                                <span style="font-size:11px; color:#555;">Τζίρος (€):</span>
                                <input type="number" step="0.01" name="new_spent_amount" value="<?php echo esc_attr($u['spent']); ?>" style="width:85px; padding:2px 5px; min-height:26px;">
                            </div>
                            <div style="display:flex; flex-direction:column; gap:3px;">
                                <span style="font-size:11px; color:#555;">Χειροκίνητο VIP:</span>
                                <select name="manual_tier" style="padding:2px 5px; min-height:26px; font-size:13px; max-width: 140px;">
                                    <option value="auto" <?php selected($u['manual'], 'auto'); ?>>Βάσει Τζίρου</option>
                                    <option value="none" <?php selected($u['manual'], 'none'); ?>>Χωρίς Έκπτωση (0%)</option>
                                    <option value="base" <?php selected($u['manual'], 'base'); ?>>Απλό Μέλος (Force)</option>
                                    <option value="silver" <?php selected($u['manual'], 'silver'); ?>>Silver VIP (Force)</option>
                                    <option value="gold" <?php selected($u['manual'], 'gold'); ?>>Gold VIP (Force)</option>
                                </select>
                            </div>
                            <button type="submit" name="ptl_vip_update_user_row" class="button button-small button-primary" style="margin-top:16px;" title="Αποθήκευση Αλλαγών">💾</button>
                        </form>
                    <?php
                    echo '</td></tr>';
                }
            } else {
                echo '<tr><td colspan="5" style="padding:20px; text-align:center;">Δεν βρέθηκαν πελάτες.</td></tr>';
            }
            ?>
        </tbody>
    </table>
    <?php
}

// =========================================================================
// 2. SHORTCODES
// =========================================================================
add_shortcode( 'petling_vip', 'ptl_vip_dynamic_shortcode' );
function ptl_vip_dynamic_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'field' => '' ), $atts );
    $field = $atts['field'];

    if ( $field === 'base_discount' ) return get_option( 'ptl_vip_base_discount', 3 );
    if ( $field === 'silver_spend' ) return get_option( 'ptl_vip_silver_spend', 1000 );
    if ( $field === 'silver_discount' ) return get_option( 'ptl_vip_silver_discount', 7 );
    if ( $field === 'gold_spend' ) return get_option( 'ptl_vip_gold_spend', 2000 );
    if ( $field === 'gold_discount' ) return get_option( 'ptl_vip_gold_discount', 10 );
    return '';
}

add_shortcode( 'petling_vip_rules', 'ptl_vip_rules_shortcode' );
function ptl_vip_rules_shortcode() {
    $base_disc   = get_option( 'ptl_vip_base_discount', 3 );
    $silver_spnd = get_option( 'ptl_vip_silver_spend', 1000 );
    $silver_disc = get_option( 'ptl_vip_silver_discount', 7 );
    $gold_spnd   = get_option( 'ptl_vip_gold_spend', 2000 );
    $gold_disc   = get_option( 'ptl_vip_gold_discount', 10 );

    $html = '<div class="ptl-vip-rules-container" style="background: #fff; padding: 25px; border-radius: 8px; border: 2px dashed #C7B297;">';
    $html .= '<h3 style="color: #43282F; margin-top: 0;">🌟 Petling VIP Club</h3>';
    $html .= '<p style="color: #555;">Το σύστημα επιβραβεύει αυτόματα τις αγορές σας. Η έκπτωση εφαρμόζεται κατευθείαν στο καλάθι σας, ανάλογα με τον συνολικό σας τζίρο:</p>';
    $html .= '<ul style="list-style: none; padding: 0;">';
    $html .= '<li style="margin-bottom: 10px;">🌱 <strong>Απλό Μέλος:</strong> ' . $base_disc . '% μόνιμη έκπτωση (αποκτάται άμεσα με την εγγραφή σας)</li>';
    $html .= '<li style="margin-bottom: 10px;">🥈 <strong>Silver VIP:</strong> ' . $silver_disc . '% μόνιμη έκπτωση (για συνολικό τζίρο άνω των ' . $silver_spnd . '€)</li>';
    $html .= '<li style="margin-bottom: 10px;">👑 <strong>Gold VIP:</strong> ' . $gold_disc . '% μόνιμη έκπτωση (για συνολικό τζίρο άνω των ' . $gold_spnd . '€)</li>';
    $html .= '</ul>';
    $html .= '</div>';

    return $html;
}

// =========================================================================
// 3. ΣΥΣΤΗΜΑ VIP TIERS (ΑΥΤΟΜΑΤΗ ΕΚΠΤΩΣΗ ΣΤΟ ΚΑΛΑΘΙ)
// =========================================================================
add_action( 'woocommerce_cart_calculate_fees', 'ptl_apply_vip_club_discount', 10, 1 );
function ptl_apply_vip_club_discount( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( ! is_user_logged_in() ) return;

    $user_id = get_current_user_id();
    $total_spent = wc_get_customer_total_spent( $user_id );
    $manual_tier = get_user_meta( $user_id, '_ptl_manual_vip_tier', true );
    
    if ( $manual_tier === 'none' ) return;
    
    $base_disc   = get_option( 'ptl_vip_base_discount', 3 );
    $silver_spnd = get_option( 'ptl_vip_silver_spend', 1000 );
    $silver_disc = get_option( 'ptl_vip_silver_discount', 7 );
    $gold_spnd   = get_option( 'ptl_vip_gold_spend', 2000 );
    $gold_disc   = get_option( 'ptl_vip_gold_discount', 10 );
    
    if ( $manual_tier === 'gold' ) { $discount_percent = $gold_disc; $tier_name = 'Gold VIP'; }
    elseif ( $manual_tier === 'silver' ) { $discount_percent = $silver_disc; $tier_name = 'Silver VIP'; }
    elseif ( $manual_tier === 'base' ) { $discount_percent = $base_disc; $tier_name = 'Απλό Μέλος'; }
    else {
        if ( $total_spent >= $gold_spnd ) { $discount_percent = $gold_disc; $tier_name = 'Gold VIP'; }
        elseif ( $total_spent >= $silver_spnd ) { $discount_percent = $silver_disc; $tier_name = 'Silver VIP'; }
        else { $discount_percent = $base_disc; $tier_name = 'Απλό Μέλος'; }
    }
    
    $discount_amount = ( $cart->get_subtotal() * $discount_percent ) / 100;
    
    if ( $discount_amount > 0 ) {
        $cart->add_fee( sprintf( 'Petling Club (%s - %g%%)', $tier_name, $discount_percent ), -$discount_amount, true );
    }
}

// =========================================================================
// 4. ΜΠΛΟΚΑΡΙΣΜΑ ΤΟΥ "WELCOME10" ΓΙΑ ΤΑ ΕΓΓΕΓΡΑΜΜΕΝΑ ΜΕΛΗ
// =========================================================================
add_filter( 'woocommerce_coupon_is_valid', 'ptl_restrict_welcome10_for_vips', 10, 3 );
function ptl_restrict_welcome10_for_vips( $valid, $coupon, $discount ) {
    if ( ! $valid ) return $valid;
    
    // Αν το κουπόνι είναι το welcome10
    if ( strtolower( $coupon->get_code() ) === 'welcome10' ) {
        
        // Και ο πελάτης έχει κάνει σύνδεση (άρα είναι VIP και παίρνει μόνιμη έκπτωση)
        if ( is_user_logged_in() ) {
            throw new Exception( 'Είστε ήδη μέλος του Petling VIP Club! Το κουπόνι welcome10 αφορά μόνο νέους επισκέπτες, καθώς εσείς απολαμβάνετε ήδη τις δικές σας μόνιμες εκπτώσεις και προνόμια στο καλάθι.' );
        }
    }
    
    return $valid;
}