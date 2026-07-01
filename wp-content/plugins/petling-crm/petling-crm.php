<?php
/**
 * Plugin Name:       Petling CRM - Digital Vet Pass
 * Plugin URI:        https://petling.gr/
 * Description:       Το ολοκληρωμένο Ψηφιακό Βιβλιάριο: Προφίλ κατοικιδίων, έξυπνες υπενθυμίσεις τροφής βάσει ημερών, εμβόλια WSAVA και Vet Pass.
 * Version:           2.3.0
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
 * 2. ΔΗΜΙΟΥΡΓΙΑ TAB ΣΤΟ MY ACCOUNT
 */
add_filter( 'woocommerce_account_menu_items', 'petling_crm_add_tab' );
function petling_crm_add_tab( $items ) {
    $logout = array_pop($items);
    $items['pet-crm'] = '🐾 Ψηφιακό Βιβλιάριο';
    $items['logout'] = $logout;
    return $items;
}

add_action( 'init', 'petling_crm_register_endpoint' );
function petling_crm_register_endpoint() {
    add_rewrite_endpoint( 'pet-crm', EP_PAGES );
}

/**
 * 3. ΦΟΡΤΩΣΗ SCRIPTS
 */
require_once PETLING_CRM_PATH . 'includes/crm-ui.php';

add_action( 'wp_enqueue_scripts', 'petling_crm_scripts' );
function petling_crm_scripts() {
    if ( is_account_page() ) {
        wp_enqueue_script( 'petling-crm-js', PETLING_CRM_URL . 'js/crm-scripts.js', ['jquery'], '2.3', true );
    }
}

/**
 * 4. VET PASS INTERCEPTOR (/vet-pass/)
 */
add_action( 'template_redirect', 'petling_crm_vet_pass_interceptor' );
function petling_crm_vet_pass_interceptor() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/vet-pass/' ) !== false ) {
        require_once PETLING_CRM_PATH . 'includes/vet-pass-public.php';
        exit;
    }
}

/**
 * 5. ΜΗΧΑΝΙΣΜΟΣ ΥΠΕΝΘΥΜΙΣΕΩΝ (CRON JOB)
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

        // Ονόματα κατοικιδίων για το προσωποποιημένο email
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