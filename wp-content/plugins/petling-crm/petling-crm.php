<?php
/**
 * Plugin Name:       Petling CRM - Digital Vet Pass
 * Plugin URI:        https://petling.gr/
 * Description:       Το ολοκληρωμένο Ψηφιακό Βιβλιάριο: Προφίλ κατοικιδίων, έξυπνες υπενθυμίσεις τροφής, εμβόλια WSAVA, Vet Pass και VIP πρόσβαση.
 * Version:           2.5.0
 * Author:            Georgiana
 * Text Domain:       petling-crm
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PETLING_CRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'PETLING_CRM_URL', plugin_dir_url( __FILE__ ) );

/**
 * 1. ΕΝΕΡΓΟΠΟΙΗΣΗ PLUGIN & ΔΗΜΙΟΥΡΓΙΑ ΒΑΣΗΣ
 */
function petling_crm_activate() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $charset_collate = $wpdb->get_charset_collate();

    $table_vaccines = $wpdb->prefix . 'petling_vaccines';
    $sql_vaccines = "CREATE TABLE $table_vaccines (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        pet_unique_id varchar(50) NOT NULL,
        vaccine_name varchar(150) NOT NULL,
        date_administered date NOT NULL,
        next_vaccine_date date DEFAULT NULL,
        vet_name varchar(150) DEFAULT '',
        status varchar(20) DEFAULT 'draft',
        created_by varchar(20) DEFAULT 'owner',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY pet_unique_id (pet_unique_id)
    ) $charset_collate;";
    dbDelta( $sql_vaccines );

    $table_vet_notes = $wpdb->prefix . 'petling_vet_notes';
    $sql_vet_notes = "CREATE TABLE $table_vet_notes (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        pet_unique_id varchar(50) NOT NULL,
        weight decimal(5,2) DEFAULT NULL,
        vet_comment text DEFAULT '',
        next_exam_date date DEFAULT NULL,
        vet_name varchar(150) DEFAULT '',
        status varchar(20) DEFAULT 'draft',
        created_by varchar(20) DEFAULT 'owner',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_vet_notes );

    if ( ! wp_next_scheduled( 'petling_daily_order_check' ) ) {
        wp_schedule_event( time(), 'daily', 'petling_daily_order_check' );
    }
}
register_activation_hook( __FILE__, 'petling_crm_activate' );

function petling_crm_deactivate() {
    wp_clear_scheduled_hook( 'petling_daily_order_check' );
}
register_deactivation_hook( __FILE__, 'petling_crm_deactivate' );


/**
 * 2. ADMIN PANEL - ΡΥΘΜΙΣΕΙΣ & ΠΕΡΙΟΡΙΣΜΟΣ ΠΡΟΣΒΑΣΗΣ
 */
add_action( 'admin_menu', 'petling_crm_admin_menu' );
function petling_crm_admin_menu() {
    // Ελέγχουμε αν υπάρχει ήδη το κεντρικό μενού "Petling" (Η Πατούσα)
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        // Αν δεν υπάρχει, τη φτιάχνουμε
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'petling_crm_settings_page', 'dashicons-pets', 55 );
        // Και βάζουμε το CRM ως 1ο υπομενού
        add_submenu_page( 'petling-main', 'CRM', 'CRM', 'manage_options', 'petling-crm-settings', 'petling_crm_settings_page' );
        // Κρύβουμε το διπλό "Petling"
        remove_submenu_page( 'petling-main', 'petling-main' );
    } else {
        // Αν υπάρχει η Πατούσα, κουμπώνουμε το CRM από κάτω!
        add_submenu_page(
            'petling-main',               // Parent (Η πατούσα)
            'CRM',                // Τίτλος σελίδας
            'CRM',             // Ονομασία στο Dropdown
            'manage_options',             // Δικαιώματα
            'petling-crm-settings',       // Το Slug σου (έμεινε ίδιο!)
            'petling_crm_settings_page'   // Η function σου
        );
    }
}

function petling_crm_settings_page() {
    if ( isset( $_POST['petling_admin_nonce'] ) && wp_verify_nonce( $_POST['petling_admin_nonce'], 'petling_save_settings' ) ) {
        
        $restrict = isset($_POST['restrict_access']) ? 'yes' : 'no';
        update_option('petling_crm_restrict_access', $restrict);

        if ( !empty($_POST['new_user_emails']) ) {
            $emails = sanitize_textarea_field($_POST['new_user_emails']);
            $email_array = array_map('trim', explode(',', $emails));
            $allowed = get_option('petling_allowed_users', []);
            $revoked = get_option('petling_revoked_users', []);

            $added_count = 0;
            foreach ($email_array as $email) {
                if ( empty($email) || !is_email($email) ) continue;
                $user = get_user_by('email', $email);
                if ( $user ) {
                    if ( !in_array($user->ID, $allowed) ) {
                        $allowed[] = $user->ID;
                        $added_count++;
                    }
                    if ( ($key = array_search($user->ID, $revoked)) !== false ) {
                        unset($revoked[$key]);
                    }
                }
            }
            update_option('petling_allowed_users', array_values($allowed));
            update_option('petling_revoked_users', array_values($revoked));
            
            if ($added_count > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Προστέθηκαν ' . $added_count . ' νέοι χρήστες επιτυχώς!</p></div>';
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Οι ρυθμίσεις αποθηκεύτηκαν.</p></div>';
    }

    if ( isset( $_GET['petling_action'] ) && isset( $_GET['_wpnonce'] ) ) {
        if ( $_GET['petling_action'] === 'remove' && wp_verify_nonce( $_GET['_wpnonce'], 'remove_user_' . $_GET['uid'] ) ) {
            $uid = intval($_GET['uid']);
            $allowed = get_option('petling_allowed_users', []);
            $revoked = get_option('petling_revoked_users', []);

            if ( ($key = array_search($uid, $allowed)) !== false ) {
                unset($allowed[$key]);
                if ( !in_array($uid, $revoked) ) { $revoked[] = $uid; }
                update_option('petling_allowed_users', array_values($allowed));
                update_option('petling_revoked_users', array_values($revoked));
                wp_redirect( admin_url('admin.php?page=petling-crm-settings') ); exit;
            }
        }
        if ( $_GET['petling_action'] === 'clear_history' && wp_verify_nonce( $_GET['_wpnonce'], 'clear_history' ) ) {
            update_option('petling_revoked_users', []);
            wp_redirect( admin_url('admin.php?page=petling-crm-settings') ); exit;
        }
    }

    $is_restricted = get_option('petling_crm_restrict_access', 'yes');
    $allowed_users = get_option('petling_allowed_users', []);
    $revoked_users = get_option('petling_revoked_users', []);
    ?>
    <div class="wrap" style="max-width: 900px;">
        <h1 style="display:flex; align-items:center; gap:10px;"><span style="font-size:30px;">🐾</span> Petling CRM - Έλεγχος Πρόσβασης</h1>
        <p>Διαχειριστείτε ποιοι πελάτες μπορούν να δουν το Ψηφιακό Βιβλιάριο (VIP Testing).</p>

        <form method="post" action="" style="background:#fff; padding:20px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04); margin-bottom:20px;">
            <?php wp_nonce_field( 'petling_save_settings', 'petling_admin_nonce' ); ?>
            
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Γενικός Διακόπτης</h2>
            <label style="font-size: 15px; font-weight: 600; cursor:pointer;">
                <input type="checkbox" name="restrict_access" value="yes" <?php checked($is_restricted, 'yes'); ?>>
                Ενεργοποίηση Περιορισμένης Πρόσβασης (Προεπιλογή)
            </label>
            <p class="description">Όσο αυτό είναι τσεκαρισμένο, το βιβλιάριο το βλέπουν <strong>ΜΟΝΟ οι Διαχειριστές</strong> και τα email που θα προσθέσετε παρακάτω.</p>

            <h2 style="margin-top:30px; border-bottom:1px solid #eee; padding-bottom:10px;">Προσθήκη Χρηστών (VIPs)</h2>
            <label><strong>Email Πελατών (Χωρισμένα με κόμμα):</strong></label><br>
            <textarea name="new_user_emails" rows="3" style="width:100%; max-width:600px; margin-top:10px;" placeholder="π.χ. maria@gmail.com, kwstas@yahoo.com"></textarea>
            <p class="description">Τα email πρέπει να ανήκουν σε ήδη εγγεγραμμένους χρήστες του e-shop.</p>
            
            <p><button type="submit" class="button button-primary button-large">Αποθήκευση & Προσθήκη</button></p>
        </form>

        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:300px; background:#fff; padding:20px; border:1px solid #5b9a68; border-top:4px solid #5b9a68; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin-top:0;">✅ Ενεργοί Χρήστες (Έχουν πρόσβαση)</h3>
                <?php if (empty($allowed_users)): ?>
                    <p style="color:#666; font-style:italic;">Δεν υπάρχουν χρήστες στη λίστα.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>Email</th><th>Ενέργεια</th></tr></thead>
                        <tbody>
                        <?php foreach ($allowed_users as $uid): 
                            $u = get_userdata($uid); 
                            if(!$u) continue;
                            $del_url = wp_nonce_url( admin_url("admin.php?page=petling-crm-settings&petling_action=remove&uid={$uid}"), "remove_user_{$uid}" );
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($u->user_email); ?></strong></td>
                                <td><a href="<?php echo esc_url($del_url); ?>" style="color:#d63638;" onclick="return confirm('Να αφαιρεθεί η πρόσβαση;');">❌ Αφαίρεση</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div style="flex:1; min-width:300px; background:#fff; padding:20px; border:1px solid #ccd0d4; border-top:4px solid #d63638; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin-top:0;">📜 Ιστορικό (Αφαιρέθηκαν)</h3>
                    <?php if (!empty($revoked_users)): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=petling-crm-settings&petling_action=clear_history"), "clear_history")); ?>" class="button button-small" onclick="return confirm('Οριστική διαγραφή ιστορικού;');">Καθαρισμός</a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($revoked_users)): ?>
                    <p style="color:#666; font-style:italic;">Το ιστορικό είναι άδειο.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>Email (Πρώην VIPs)</th></tr></thead>
                        <tbody>
                        <?php foreach ($revoked_users as $uid): 
                            $u = get_userdata($uid); 
                            if(!$u) continue;
                        ?>
                            <tr><td style="color:#666;"><?php echo esc_html($u->user_email); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 3. ΠΕΡΙΟΡΙΣΜΟΣ ΣΤΟ MY ACCOUNT MENU & ENDPOINT
 */
add_filter( 'woocommerce_account_menu_items', 'petling_crm_add_tab' );
function petling_crm_add_tab( $items ) {
    $is_restricted = get_option('petling_crm_restrict_access', 'yes');
    $current_user = get_current_user_id();
    $allowed = get_option('petling_allowed_users', []);

    if ( $is_restricted === 'yes' && !current_user_can('manage_options') && !in_array($current_user, $allowed) ) {
        return $items;
    }

    $logout = array_pop($items);
    $items['pet-crm'] = '🐾 Ψηφιακό Βιβλιάριο';
    $items['logout'] = $logout;
    return $items;
}

add_action( 'template_redirect', 'petling_crm_restrict_endpoint_access' );
function petling_crm_restrict_endpoint_access() {
    if ( is_account_page() && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/pet-crm') !== false ) {
        $is_restricted = get_option('petling_crm_restrict_access', 'yes');
        $current_user = get_current_user_id();
        $allowed = get_option('petling_allowed_users', []);

        if ( $is_restricted === 'yes' && !current_user_can('manage_options') && !in_array($current_user, $allowed) ) {
            wp_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    }
}

add_action( 'init', 'petling_crm_register_endpoint' );
function petling_crm_register_endpoint() {
    add_rewrite_endpoint( 'pet-crm', EP_PAGES );
}

/**
 * 4. ΦΟΡΤΩΣΗ SCRIPTS, CSS & UI
 */
require_once PETLING_CRM_PATH . 'includes/crm-ui.php';

add_action( 'wp_enqueue_scripts', 'petling_crm_scripts' );
function petling_crm_scripts() {
    if ( is_account_page() || strpos( $_SERVER['REQUEST_URI'], '/vet-pass/' ) !== false ) {
        wp_enqueue_style( 'petling-crm-css', PETLING_CRM_URL . 'assets/css/crm-styles.css', array(), '2.6' );
        wp_enqueue_script( 'petling-crm-js', PETLING_CRM_URL . 'assets/js/crm-scripts.js', array('jquery'), '2.6', true );
    }
}

/**
 * 5. VET PASS INTERCEPTOR (/vet-pass/)
 */
add_action( 'template_redirect', 'petling_crm_vet_pass_interceptor' );
function petling_crm_vet_pass_interceptor() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/vet-pass/' ) !== false ) {
        require_once PETLING_CRM_PATH . 'includes/vet-pass-public.php';
        exit;
    }
}

/**
 * 6. ΜΗΧΑΝΙΣΜΟΣ ΥΠΕΝΘΥΜΙΣΕΩΝ (CRON JOB)
 */
add_action( 'petling_daily_order_check', 'petling_process_order_reminders' );
function petling_process_order_reminders() {
    $users = get_users();

    foreach ( $users as $user ) {
        $user_id = $user->ID;
        $user_email = $user->user_email;
        
        $interval_days = get_user_meta( $user_id, 'petling_order_reminder_interval', true );
        if ( empty($interval_days) || $interval_days === 'no' ) continue;
        
        $interval_seconds = intval($interval_days) * DAY_IN_SECONDS;

        $pets = get_user_meta( $user_id, 'petling_pets', true );
        $pet_names = array();
        if ( is_array( $pets ) ) {
            foreach ( $pets as $pet ) {
                if ( !empty($pet['name']) ) $pet_names[] = esc_html( $pet['name'] );
            }
        }

        $last_order = wc_get_customer_last_order( $user_id );
        if ( ! $last_order || $last_order->get_status() !== 'completed' ) continue;

        $order_date = $last_order->get_date_created();
        if ( ! $order_date ) continue;
        
        $order_timestamp = $order_date->getTimestamp();
        $target_timestamp = $order_timestamp + $interval_seconds;
        $today_timestamp = current_time( 'timestamp' );

        if ( $today_timestamp >= $target_timestamp ) {
            $already_sent = get_user_meta( $user_id, 'last_reminded_order_id', true );
            if ( $already_sent == $last_order->get_id() ) continue; 

            petling_send_smart_reminder_email( $user_email, $pet_names, intval($interval_days) );
            update_user_meta( $user_id, 'last_reminded_order_id', $last_order->get_id() );
        }
    }
}

function petling_send_smart_reminder_email( $to, $pet_names, $interval_days ) {
    if ( empty($pet_names) ) {
        $names_string = 'Τα φιλαράκια σου';
        $emoji = '🐶🐱';
    } else {
        $names_string = implode( ' και ', $pet_names );
        $emoji = '🐾';
    }

    $subject = 'Γουφ/Νιάου! Μήπως τελειώνουν οι λιχουδιές μας;'; 
    $reorder_link = wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ); 
    
    $logo_id = get_theme_mod( 'custom_logo' );
    $logo_img = wp_get_attachment_image_src( $logo_id, 'full' );
    if ( $logo_img ) {
        $logo_html = '<img src="' . esc_url( $logo_img[0] ) . '" alt="Petling Logo" style="max-width: 150px; height: auto; display: inline-block;">';
    } else {
        $logo_html = '<h1 style="color: #43282F; margin: 0; font-family: Georgia, serif;">Petling</h1>';
    }

    $message = '
    <div style="background-color: #ffffff; padding: 20px 15px; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;">
        <div style="max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #C7B297; border-radius: 8px; color: #333333;">
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eef0f2;">' . $logo_html . '</div>
            <p style="font-size: 1.05em; margin-top: 0; color: #333333;">Γεια σου!</p>
            <p style="color: #333333; font-size: 1.05em; line-height: 1.6;">
                Είμαστε η <strong style="color: #43282F;">Ίρις</strong> και η <strong style="color: #43282F;">Ντάρσι</strong> ' . $emoji . '<br>
                Έχουν περάσει περίπου ' . $interval_days . ' μέρες από την τελευταία φορά που μας πήρες τα αγαπημένα μας πράγματα και νομίζουμε ότι το ντουλάπι μας αδειάζει!
            </p>
            <div style="background: rgba(199, 178, 151, 0.12); padding: 15px; border-left: 4px solid #43282F; font-style: italic; border-radius: 0 4px 4px 0; color: #43282F; font-weight: 500; margin: 25px 0; font-size: 1em; line-height: 1.5; text-align: center;">
                Η Ίρις και η Ντάρσι ανυπομονούν για την επόμενη παραγγελία σας! 🥺
            </div>
            <p style="color: #333333;">Μπορείς να δεις τι μας πήρες την τελευταία φορά και να επαναλάβεις την παραγγελία σου εύκολα και γρήγορα με ένα κλικ εδώ:</p>
            <div style="text-align: center; margin: 35px 0;">
                <a href="' . esc_url( $reorder_link ) . '" style="background-color: #C7B297; color: #43282F; padding: 14px 35px; text-decoration: none; font-weight: bold; border-radius: 5px; display: inline-block; font-size: 1.05em;">🛒 Επανάληψη Τελευταίας Παραγγελίας</a>
            </div>
            <hr style="border: 0; border-top: 1px solid #eef0f2; margin-top: 30px;" />
            <p style="font-size: 0.85em; color: #8B6139; text-align: center; margin-bottom: 0; margin-top: 20px;">Με αγάπη,<br><strong style="color: #43282F;">Η ομάδα του Petling.gr</strong></p>
        </div>
    </div>';

    wp_mail( $to, $subject, $message, array('Content-Type: text/html; charset=UTF-8') );
}
?>