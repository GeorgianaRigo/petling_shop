<?php
/*
Plugin Name: Petling VIP Club & Referrals
Plugin URI: https://petling.gr
Description: Δυναμικό σύστημα εκπτώσεων VIP, Referral με Μοναδικούς Κωδικούς, Gamification και Επεξεργάσιμα Κείμενα. (Συνδεδεμένο με το Global Min Order).
Version: 2.7
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
        add_submenu_page( 'petling-main', 'Petling VIP & Referrals', 'VIP & Referrals', 'manage_options', 'petling-main', 'ptl_vip_admin_page_html' );
    } else {
        add_submenu_page( 'petling-main', 'Petling VIP & Referrals', 'VIP & Referrals', 'manage_options', 'petling-vip-rules', 'ptl_vip_admin_page_html' );
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
        update_option( 'ptl_vip_referral_discount', floatval( $_POST['ptl_vip_referral_discount'] ) );
        update_option( 'ptl_vip_referral_rules_text', wp_kses_post( wp_unslash( $_POST['ptl_vip_referral_rules_text'] ) ) );
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
            Petling VIP Club & Referrals
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
    $ref_disc    = get_option( 'ptl_vip_referral_discount', 5 );
    
    $default_rules = "Το πρόγραμμα Referral δημιουργήθηκε με σκοπό να βοηθήσουμε τα Απλά Μέλη μας για αρχή, προσφέροντάς τους μια επιπλέον έκπτωση.\n\n⚠️ Σημαντικοί Κανόνες:\n• Το κουπόνι σύστασης ισχύει για παραγγελίες ίσες ή μεγαλύτερες της ελάχιστης παραγγελίας του καταστήματος.\n• Εάν έχετε ήδη φτάσει στο επίπεδο Silver ή Gold VIP, δεν δικαιούστε την έκπτωση του referral, καθώς απολαμβάνετε ήδη μεγαλύτερη μόνιμη έκπτωση στο καλάθι σας.\n• Το κουπόνι σύστασης δεν συνδυάζεται με άλλες εκπτώσεις ή προσφορές.";
    $ref_rules = get_option( 'ptl_vip_referral_rules_text', $default_rules );
    ?>
    <form method="post" action="">
        <?php wp_nonce_field( 'ptl_vip_save_action', 'ptl_vip_nonce' ); ?>
        
        <div style="background: #fff; border: 1px solid #ccc; border-left: 4px solid #C7B297; padding: 20px; max-width: 800px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; color: #2271b1;">1. Αυτόματες Εκπτώσεις VIP (Tiers)</h2>
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

        <div style="background: #fff; border: 1px solid #ccc; border-left: 4px solid #5b9a68; padding: 20px; margin-top: 20px; max-width: 800px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; color: #2271b1;">2. Σύστημα Referral (Σύστησε ένα φίλο)</h2>
            <table class="form-table">
                <tr>
                    <th style="width: 200px;">Ανταμοιβή (Κουπόνι)</th>
                    <td><span style="display:inline-flex; align-items:center; gap:8px;">Έκπτωση: <input type="number" step="0.1" name="ptl_vip_referral_discount" value="<?php echo esc_attr($ref_disc); ?>" style="width:70px;"> %</span></td>
                </tr>
                <tr>
                    <th>Κείμενο Κανόνων<br><small>(Εμφανίζεται στο site)</small></th>
                    <td>
                        <textarea name="ptl_vip_referral_rules_text" rows="7" style="width: 100%; max-width: 500px; padding: 10px;"><?php echo esc_textarea( $ref_rules ); ?></textarea>
                        <p class="description">Γράψτε εδώ τους περιορισμούς. Μπορείτε να κάνετε αλλαγές γραμμής (Enter).</p>
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
    $default_rules = "Το πρόγραμμα Referral δημιουργήθηκε με σκοπό να βοηθήσουμε τα Απλά Μέλη μας για αρχή, προσφέροντάς τους μια επιπλέον έκπτωση.\n\n⚠️ Σημαντικοί Κανόνες:\n• Το κουπόνι σύστασης ισχύει για παραγγελίες ίσες ή μεγαλύτερες της ελάχιστης παραγγελίας του καταστήματος.\n• Εάν έχετε ήδη φτάσει στο επίπεδο Silver ή Gold VIP, δεν δικαιούστε την έκπτωση του referral, καθώς απολαμβάνετε ήδη μεγαλύτερη μόνιμη έκπτωση στο καλάθι σας.\n• Το κουπόνι σύστασης δεν συνδυάζεται με άλλες εκπτώσεις ή προσφορές.";

    if ( $field === 'base_discount' ) return get_option( 'ptl_vip_base_discount', 3 );
    if ( $field === 'silver_spend' ) return get_option( 'ptl_vip_silver_spend', 1000 );
    if ( $field === 'silver_discount' ) return get_option( 'ptl_vip_silver_discount', 7 );
    if ( $field === 'gold_spend' ) return get_option( 'ptl_vip_gold_spend', 2000 );
    if ( $field === 'gold_discount' ) return get_option( 'ptl_vip_gold_discount', 10 );
    if ( $field === 'referral_discount' ) return get_option( 'ptl_vip_referral_discount', 5 );
    if ( $field === 'referral_rules' ) return wp_kses_post( nl2br( get_option( 'ptl_vip_referral_rules_text', $default_rules ) ) );
    return '';
}

add_shortcode( 'petling_vip_rules', 'ptl_vip_rules_shortcode' );
function ptl_vip_rules_shortcode() {
    $base_disc   = get_option( 'ptl_vip_base_discount', 3 );
    $silver_spnd = get_option( 'ptl_vip_silver_spend', 1000 );
    $silver_disc = get_option( 'ptl_vip_silver_discount', 7 );
    $gold_spnd   = get_option( 'ptl_vip_gold_spend', 2000 );
    $gold_disc   = get_option( 'ptl_vip_gold_discount', 10 );
    $ref_disc    = get_option( 'ptl_vip_referral_discount', 5 );
    // Διαβάζει το όριο από το νέο plugin Global Min Order
    $ref_min     = get_option( 'ptl_global_min_order_amount', 20 );
    
    $default_rules = "Το πρόγραμμα Referral δημιουργήθηκε με σκοπό να βοηθήσουμε τα Απλά Μέλη μας για αρχή, προσφέροντάς τους μια επιπλέον έκπτωση.\n\n⚠️ Σημαντικοί Κανόνες:\n• Το κουπόνι σύστασης ισχύει για παραγγελίες ίσες ή μεγαλύτερες της ελάχιστης παραγγελίας του καταστήματος.\n• Εάν έχετε ήδη φτάσει στο επίπεδο Silver ή Gold VIP, δεν δικαιούστε την έκπτωση του referral, καθώς απολαμβάνετε ήδη μεγαλύτερη μόνιμη έκπτωση στο καλάθι σας.\n• Το κουπόνι σύστασης δεν συνδυάζεται με άλλες εκπτώσεις ή προσφορές.";
    $ref_rules = get_option( 'ptl_vip_referral_rules_text', $default_rules );

    $html = '<div class="ptl-vip-rules-container" style="background: #fff; padding: 25px; border-radius: 8px; border: 2px dashed #C7B297;">';
    $html .= '<h3 style="color: #43282F; margin-top: 0;">🌟 Petling VIP Club</h3>';
    $html .= '<p style="color: #555;">Το σύστημα επιβραβεύει αυτόματα τις αγορές σας. Η έκπτωση εφαρμόζεται κατευθείαν στο καλάθι σας, ανάλογα με τον συνολικό σας τζίρο:</p>';
    $html .= '<ul style="list-style: none; padding: 0;">';
    $html .= '<li style="margin-bottom: 10px;">🌱 <strong>Απλό Μέλος:</strong> ' . $base_disc . '% μόνιμη έκπτωση (αποκτάται άμεσα με την εγγραφή σας)</li>';
    $html .= '<li style="margin-bottom: 10px;">🥈 <strong>Silver VIP:</strong> ' . $silver_disc . '% μόνιμη έκπτωση (για συνολικό τζίρο άνω των ' . $silver_spnd . '€)</li>';
    $html .= '<li style="margin-bottom: 10px;">👑 <strong>Gold VIP:</strong> ' . $gold_disc . '% μόνιμη έκπτωση (για συνολικό τζίρο άνω των ' . $gold_spnd . '€)</li>';
    $html .= '</ul>';
    $html .= '<h3 style="color: #43282F; margin-top: 25px;">🎁 Πρόγραμμα "Σύστησε ένα Φίλο"</h3>';
    $html .= '<p style="color: #555;">Μοιραστείτε τον μοναδικό σας σύνδεσμο (τον οποίο θα βρείτε στον λογαριασμό σας) με τους φίλους σας. Μόλις ο φίλος σας ολοκληρώσει την πρώτη του παραγγελία <strong>άνω των '. $ref_min .'€</strong>, εσείς θα λάβετε αυτόματα στο email σας ένα κουπόνι έκπτωσης <strong>' . $ref_disc . '%</strong>.</p>';
    $html .= '<div style="background: #F5EDE3; border-left: 4px solid #C7B297; padding: 15px; margin-top: 15px; color: #555; font-size: 14px;">';
    $html .= wp_kses_post( nl2br( $ref_rules ) ) . '</div></div>';

    return $html;
}

// =========================================================================
// Α. ΣΥΣΤΗΜΑ VIP TIERS (ΑΥΤΟΜΑΤΗ ΕΚΠΤΩΣΗ ΣΤΟ ΚΑΛΑΘΙ)
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
// Β. REFERRAL SYSTEM & GAMIFICATION
// =========================================================================
function ptl_get_user_referral_code( $user_id ) {
    $code = get_user_meta( $user_id, '_ptl_referral_code', true );
    if ( empty( $code ) ) {
        $code = substr( str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 6 );
        while ( ! empty( get_users( array( 'meta_key' => '_ptl_referral_code', 'meta_value' => $code, 'fields' => 'ID' ) ) ) ) {
            $code = substr( str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 6 );
        }
        update_user_meta( $user_id, '_ptl_referral_code', $code );
    }
    return $code;
}

add_action( 'init', 'ptl_track_referral_link' );
function ptl_track_referral_link() {
    if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) ) {
        $ref_val = sanitize_text_field( $_GET['ref'] );
        $referrer_id = 0;
        $users = get_users( array( 'meta_key' => '_ptl_referral_code', 'meta_value' => $ref_val, 'number' => 1, 'fields' => 'ID' ) );
        if ( ! empty( $users ) ) { $referrer_id = $users[0]; }
        elseif ( is_numeric( $ref_val ) ) {
            $user_obj = get_userdata( intval( $ref_val ) );
            if ( $user_obj ) $referrer_id = $user_obj->ID;
        }
        if ( $referrer_id > 0 ) setcookie( 'petling_ref_id', $referrer_id, time() + (30 * DAY_IN_SECONDS), '/' );
    }
}

add_action( 'woocommerce_account_dashboard', 'ptl_show_referral_link_in_my_account' );
function ptl_show_referral_link_in_my_account() {
    $user_id = get_current_user_id();
    $ref_link = home_url( '/?ref=' . ptl_get_user_referral_code( $user_id ) );
    $ref_disc = get_option( 'ptl_vip_referral_discount', 5 );
    // Διαβάζει το όριο από το νέο plugin
    $ref_min  = get_option( 'ptl_global_min_order_amount', 20 );
    ?>
    <div style="background: #F5EDE3; padding: 20px; border: 2px dashed #C7B297; border-radius: 8px; margin-bottom: 25px; text-align: center;">
        <h3 style="margin-top: 0; color: #43282F;">🎁 Πρόγραμμα "Σύστησε ένα Φίλο"</h3>
        <p style="margin-bottom: 10px;">Μοιραστείτε τον παρακάτω σύνδεσμο με τους φίλους σας. Μόλις εγγραφούν και ολοκληρώσουν την πρώτη τους παραγγελία <strong>άνω των <?php echo esc_html($ref_min); ?>€</strong>, εσείς <strong>θα λάβετε ένα κουπόνι έκπτωσης <?php echo esc_html($ref_disc); ?>%!</strong></p>
        <input type="text" value="<?php echo esc_url( $ref_link ); ?>" readonly style="width: 100%; max-width: 400px; padding: 10px; text-align: center; font-weight: bold; color: #5b9a68; border: 1px solid #C7B297; border-radius: 4px;" onclick="this.select();">
    </div>
    <?php
}

add_action( 'user_register', 'ptl_save_referrer_on_registration' );
function ptl_save_referrer_on_registration( $user_id ) {
    if ( isset( $_COOKIE['petling_ref_id'] ) ) {
        $referrer_id = intval( $_COOKIE['petling_ref_id'] );
        if ( $referrer_id !== $user_id ) update_user_meta( $user_id, '_petling_referred_by', $referrer_id );
    }
}

// ΜΗΝΥΜΑΤΑ GAMIFICATION ΣΤΟ ΚΑΛΑΘΙ
add_action( 'woocommerce_before_cart', 'ptl_show_referral_status_in_cart' );
add_action( 'woocommerce_before_checkout_form', 'ptl_show_referral_status_in_cart' );
function ptl_show_referral_status_in_cart() {
    if ( is_admin() || is_null( WC()->cart ) ) return;
    
    $user_id = get_current_user_id();
    $referrer_id = 0;
    
    if ( $user_id ) {
        $referrer_id = get_user_meta( $user_id, '_petling_referred_by', true );
        if ( get_user_meta( $user_id, '_petling_referral_rewarded', true ) ) return; 
    } elseif ( isset( $_COOKIE['petling_ref_id'] ) ) {
        $referrer_id = intval( $_COOKIE['petling_ref_id'] );
    }
    
    if ( $referrer_id ) {
        // Διαβάζει το όριο από το νέο plugin
        $ref_min = get_option( 'ptl_global_min_order_amount', 20 );
        $subtotal = WC()->cart->get_subtotal();
        
        echo '<div style="background: #F5EDE3; border-left: 4px solid #C7B297; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #43282F; font-size: 15px;">';
        if ( $subtotal < $ref_min ) {
            $diff = $ref_min - $subtotal;
            echo '🎁 <strong>Ήρθατε μέσω σύστασης!</strong> Προσθέστε ακόμα <strong>' . number_format($diff, 2, ',', '.') . '€</strong> στο καλάθι σας για να ενεργοποιηθεί αυτόματα η επιβράβευση του φίλου που σας σύστησε!';
        } else {
            echo '🎁 <strong>Πρόγραμμα Σύστασης:</strong> Η παραγγελία σας πληροί τις προϋποθέσεις! Η επιβράβευση του φίλου σας θα ενεργοποιηθεί αυτόματα μόλις ολοκληρώσετε την αγορά.';
        }
        echo '</div>';
    }
}

// ΔΗΜΙΟΥΡΓΙΑ ΚΟΥΠΟΝΙΟΥ ΜΕΤΑ ΤΗΝ ΠΑΡΑΓΓΕΛΙΑ
add_action( 'woocommerce_order_status_completed', 'ptl_reward_referrer_with_coupon' );
function ptl_reward_referrer_with_coupon( $order_id ) {
    $order = wc_get_order( $order_id );
    $customer_id = $order->get_customer_id();
    if ( ! $customer_id ) return;

    $referrer_id = get_user_meta( $customer_id, '_petling_referred_by', true );
    if ( $referrer_id && ! get_user_meta( $customer_id, '_petling_referral_rewarded', true ) ) {
        
        // Διαβάζει το όριο από το νέο plugin
        $ref_min = get_option( 'ptl_global_min_order_amount', 20 );
        if ( $order->get_subtotal() < $ref_min ) return; // Πρέπει να ξεπερνά το κεντρικό όριο

        $referrer_user = get_userdata( $referrer_id );
        if ( $referrer_user ) {
            $ref_disc = get_option( 'ptl_vip_referral_discount', 5 );
            $coupon_code = 'ref-' . strtolower(substr(md5(uniqid(rand(), true)), 0, 6));
            
            $coupon = new WC_Coupon();
            $coupon->set_code( $coupon_code );
            $coupon->set_discount_type( 'percent' ); 
            $coupon->set_amount( $ref_disc ); 
            $coupon->set_usage_limit( 1 );
            $coupon->set_minimum_amount( $ref_min ); // Το κουπόνι εξαργυρώνεται μόνο πάνω από αυτό το ποσό
            $coupon->set_email_restrictions( array( $referrer_user->user_email ) ); 
            $coupon->set_description( 'Ανταμοιβή Referral για: ' . $referrer_user->user_email );
            $coupon->save();

            update_user_meta( $customer_id, '_petling_referral_rewarded', 'yes' );

            $subject = "🎉 Έχετε ένα νέο δώρο από το Petling!";
            $message = "Γεια σας!\n\nΈνας φίλος που καλέσατε, μόλις ολοκλήρωσε την πρώτη του αγορά στο Petling!\n\nΓια να σας ανταμείψουμε, σας κάνουμε δώρο $ref_disc% έκπτωση για την επόμενη παραγγελία σας.\n\nΟ μοναδικός σας κωδικός είναι: " . strtoupper($coupon_code) . "\n\n(Ο κωδικός ισχύει για 1 χρήση, για παραγγελίες άνω των {$ref_min}€ και λειτουργεί μόνο εφόσον δεν έχετε φτάσει στην έκπτωση Silver/Gold VIP).\n\nΣας ευχαριστούμε!";
            wp_mail( $referrer_user->user_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8', 'From: Petling <info@petling.gr>') );
        }
    }
}

// ΕΛΕΓΧΟΣ ΚΟΥΠΟΝΙΟΥ (Να μην συνδυάζεται με Silver/Gold)
add_filter( 'woocommerce_coupon_is_valid', 'ptl_restrict_referral_coupon_for_vips', 10, 3 );
function ptl_restrict_referral_coupon_for_vips( $valid, $coupon, $discount ) {
    if ( ! $valid ) return $valid;
    
    if ( strpos( strtolower( $coupon->get_code() ), 'ref-' ) === 0 ) {
        // Διαβάζει το όριο από το νέο plugin
        $ref_min = get_option( 'ptl_global_min_order_amount', 20 );
        if ( WC()->cart && WC()->cart->get_subtotal() < $ref_min ) {
            throw new Exception( sprintf( 'Το κουπόνι σύστασης ισχύει μόνο για παραγγελίες άνω των %g€.', $ref_min ) );
        }
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $manual_tier = get_user_meta( $user_id, '_ptl_manual_vip_tier', true );
            $total_spent = wc_get_customer_total_spent( $user_id );
            $silver_spnd = get_option( 'ptl_vip_silver_spend', 1000 );
            
            $is_vip = false;
            if ( $manual_tier === 'gold' || $manual_tier === 'silver' ) $is_vip = true;
            elseif ( $manual_tier !== 'none' && $manual_tier !== 'base' && $total_spent >= $silver_spnd ) $is_vip = true;
            
            if ( $is_vip ) {
                throw new Exception( 'Δεν μπορείτε να χρησιμοποιήσετε το κουπόνι σύστασης, καθώς απολαμβάνετε ήδη μεγαλύτερη μόνιμη έκπτωση (Silver/Gold VIP) στο καλάθι σας!' );
            }
        }
    }
    return $valid;
}