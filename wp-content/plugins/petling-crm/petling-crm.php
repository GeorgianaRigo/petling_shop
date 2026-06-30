<?php
/**
 * Plugin Name:       Petling CRM - Digital Vet Pass
 * Plugin URI:        https://petling.gr/
 * Description:       Το ολοκληρωμένο Ψηφιακό Βιβλιάριο: Προφίλ κατοικιδίων, έξυπνες υπενθυμίσεις τροφής, εμβόλια και Vet Pass.
 * Version:           2.0.0
 * Author:            Georgiana
 * Text Domain:       petling-crm
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PETLING_CRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'PETLING_CRM_URL', plugin_dir_url( __FILE__ ) );

/**
 * 1. ΕΝΕΡΓΟΠΟΙΗΣΗ PLUGIN: ΔΗΜΙΟΥΡΓΙΑ ΒΑΣΗΣ ΔΕΔΟΜΕΝΩΝ & CRON JOB
 */
function petling_crm_activate() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $charset_collate = $wpdb->get_charset_collate();

    // Πίνακας για Εμβόλια (Συνδέεται με το Unique ID του κατοικιδίου)
    $table_vaccines = $wpdb->prefix . 'petling_vaccines';
    $sql_vaccines = "CREATE TABLE $table_vaccines (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        pet_unique_id varchar(50) NOT NULL,
        vaccine_name varchar(150) NOT NULL,
        date_administered date NOT NULL,
        next_vaccine_date date DEFAULT NULL,
        vet_name varchar(150) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY pet_unique_id (pet_unique_id)
    ) $charset_collate;";
    dbDelta( $sql_vaccines );

    // Πίνακας για Επανεξετάσεις / Σημειώσεις
    $table_vet_notes = $wpdb->prefix . 'petling_vet_notes';
    $sql_vet_notes = "CREATE TABLE $table_vet_notes (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        pet_unique_id varchar(50) NOT NULL,
        weight decimal(5,2) DEFAULT NULL,
        vet_comment text DEFAULT '',
        next_exam_date date DEFAULT NULL,
        vet_name varchar(150) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_vet_notes );

    // Ενεργοποίηση του Cron Job
    if ( ! wp_next_scheduled( 'petling_daily_food_check' ) ) {
        wp_schedule_event( time(), 'daily', 'petling_daily_food_check' );
    }
}
register_activation_hook( __FILE__, 'petling_crm_activate' );

/**
 * Απενεργοποίηση του Cron Job αν το plugin απενεργοποιηθεί
 */
function petling_crm_deactivate() {
    wp_clear_scheduled_hook( 'petling_daily_food_check' );
}
register_deactivation_hook( __FILE__, 'petling_crm_deactivate' );

/**
 * 2. ΔΗΜΙΟΥΡΓΙΑ ΤΟΥ TAB "ΨΗΦΙΑΚΟ ΒΙΒΛΙΑΡΙΟ"
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
 * 3. ΦΟΡΤΩΣΗ ΑΡΧΕΙΩΝ (UI & Scripts)
 */
require_once PETLING_CRM_PATH . 'includes/crm-ui.php';

add_action( 'wp_enqueue_scripts', 'petling_crm_scripts' );
function petling_crm_scripts() {
    if ( is_account_page() ) {
        wp_enqueue_script( 'petling-crm-js', PETLING_CRM_URL . 'js/crm-scripts.js', ['jquery'], '2.0', true );
    }
}

/**
 * 4. ΣΥΝΔΕΣΗ ΤΟΥ CRON JOB ΜΕ ΤΗ ΒΑΣΙΚΗ ΣΥΝΑΡΤΗΣΗ ΕΛΕΓΧΟΥ (SMART REMINDERS)
 */
add_action( 'petling_daily_food_check', 'petling_process_food_reminders' );
function petling_process_food_reminders() {
    
    // Όριο ασφαλείας: Πόσες μέρες ΠΡΙΝ τελειώσει η τροφή θέλουμε να φύγει το mail;
    $days_notice = 7; 

    // Παίρνουμε όλους τους χρήστες που έχουν ενεργοποιήσει τις ειδοποιήσεις
    $users = get_users( array(
        'meta_key'   => 'petling_global_food_consent',
        'meta_value' => 'yes',
    ) );

    foreach ( $users as $user ) {
        $user_id = $user->ID;
        $user_email = $user->user_email;

        // Παίρνουμε τα κατοικίδια του χρήστη από το UI μας
        $pets = get_user_meta( $user_id, 'petling_pets', true );
        if ( ! is_array( $pets ) || empty( $pets ) ) {
            continue;
        }

        // Υπολογισμός συνολικών γραμμαρίων ανά ημέρα
        $total_daily_grams = 0;
        $pet_names = array();

        foreach ( $pets as $pet ) {
            if ( isset( $pet['calc_food'] ) && $pet['calc_food'] === 'yes' && ! empty( $pet['daily_food'] ) ) {
                $total_daily_grams += floatval( $pet['daily_food'] );
                $pet_names[] = esc_html( $pet['name'] );
            }
        }

        // Αν κανένα ζωάκι δεν έχει επιλεγμένο το "Αγοράζω από το Petling", πάμε στον επόμενο
        if ( $total_daily_grams <= 0 ) {
            continue;
        }

        // Παίρνουμε την τελευταία ολοκληρωμένη παραγγελία του χρήστη
        $last_order = wc_get_customer_last_order( $user_id );
        if ( ! $last_order || $last_order->get_status() !== 'completed' ) {
            continue;
        }

        $order_date = $last_order->get_date_created();
        if ( ! $order_date ) {
            continue;
        }
        $order_timestamp = $order_date->getTimestamp();

        // Ψάχνουμε αν στην παραγγελία υπάρχει προϊόν με βάρος
        $total_food_weight_kg = 0;
        $bought_product_name = '';
        $product_id = 0;

        foreach ( $last_order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $weight = floatval( $product->get_weight() ); // Επιστρέφει βάρος σε κιλά
                if ( $weight > 0 ) {
                    $qty = $item->get_quantity();
                    $total_food_weight_kg += ( $weight * $qty );
                    $bought_product_name = $product->get_name();
                    $product_id = $product->get_id();
                }
            }
        }

        // Αν η παραγγελία δεν είχε προϊόν με βάρος, προσπερνάμε
        if ( $total_food_weight_kg <= 0 ) {
            continue;
        }

        // Μετατροπή κιλών τροφής σε γραμμάρια
        $total_food_grams = $total_food_weight_kg * 1000;

        // Μαθηματικός υπολογισμός ημερών διάρκειας
        $days_duration = floor( $total_food_grams / $total_daily_grams );

        // Υπολογισμός ακριβούς ημερομηνίας που εξαντλείται η τροφή
        $runout_timestamp = $order_timestamp + ( $days_duration * DAY_IN_SECONDS );
        $today_timestamp = current_time( 'timestamp' );

        // Υπολογισμός πόσες μέρες απομένουν από σήμερα
        $days_left = floor( ( $runout_timestamp - $today_timestamp ) / DAY_IN_SECONDS );

        // ΕΛΕΓΧΟΣ: Αν οι μέρες που απομένουν είναι μέσα στο παράθυρο ειδοποίησης
        if ( $days_left <= $days_notice && $days_left >= 0 ) {
            
            // Αποφυγή διπλού email για την ίδια παραγγελία
            $already_sent = get_user_meta( $user_id, 'last_reminded_order_id', true );
            if ( $already_sent == $last_order->get_id() ) {
                continue; 
            }

            // Σύνταξη και αποστολή του Email
            petling_send_smart_reminder_email( $user_email, $pet_names, $bought_product_name, $days_left, $runout_timestamp, $product_id );

            // Ενημέρωση της βάσης για να μην ξανασταλεί για αυτή την αγορά
            update_user_meta( $user_id, 'last_reminded_order_id', $last_order->get_id() );
        }
    }
}

/**
 * 5. ΣΥΝΑΡΤΗΣΗ ΔΗΜΙΟΥΡΓΙΑΣ ΚΑΙ ΑΠΟΣΤΟΛΗΣ ΤΟΥ ΕΙΚΑΣΤΙΚΟΥ EMAIL
 */
function petling_send_smart_reminder_email( $to, $pet_names, $product_name, $days_left, $runout_timestamp, $product_id ) {
    $names_list = implode( ' και ', $pet_names );
    $runout_date = date( 'd/m/Y', $runout_timestamp );
    $product_link = get_permalink( $product_id );

    // Θέμα του Email
    $subject = '🐾 Ώρα για αναπλήρωση! Η τροφή των μικρών σου φίλων κοντεύει να τελειώσει'; 
    
    // Ανάκτηση του Custom Logo του Site
    $logo_id = get_theme_mod( 'custom_logo' );
    $logo_img = wp_get_attachment_image_src( $logo_id, 'full' );
    
    if ( $logo_img ) {
        $logo_html = '<img src="' . esc_url( $logo_img[0] ) . '" alt="Petling Logo" style="max-width: 150px; height: auto; display: inline-block;">';
    } else {
        $logo_html = '<h1 style="color: #43282F; margin: 0; font-family: Georgia, serif;">Petling</h1>';
    }

    // HTML Μορφή Email
    $message = '
    <div style="background-color: #ffffff; padding: 20px 15px; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;">
        <div style="max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #C7B297; border-radius: 8px; color: #333333;">
            
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eef0f2;">
                ' . $logo_html . '
            </div>
            
            <p style="font-size: 1.05em; margin-top: 0; color: #333333;">Γεια σου,</p>
            <p style="color: #333333;">Ελπίζουμε εσύ και οι τετράποδοι φίλοι σου (<strong style="color: #43282F;">' . $names_list . '</strong>) να είστε φανταστικά!</p>
            
            <p style="color: #333333;">Κάνουμε έναν αυτόματο υπολογισμό βάσει της ημερήσιας κατανάλωσης που έχεις δηλώσει στο προφίλ σου και βλέπουμε ότι η τροφή:</p>
            
            <div style="background: rgba(199, 178, 151, 0.12); padding: 15px; border-left: 4px solid #43282F; font-style: italic; border-radius: 0 4px 4px 0; color: #43282F; font-weight: 500; margin: 20px 0; font-size: 0.95em; line-height: 1.5;">
                ' . esc_html( $product_name ) . '
            </div>
            
            <p style="color: #333333;">πλησιάζει στο τέλος της! Υπολογίζουμε ότι θα εξαντληθεί σε περίπου <strong style="color: #43282F;">' . $days_left . ' ημέρες</strong> (γύρω στις <strong style="color: #43282F;">' . $runout_date . '</strong>).</p>
            
            <p style="color: #333333;">Για να μην ξεμείνουν οι μικροί μας φίλοι και να προλάβει να φτάσει η νέα σου παραγγελία στην ώρα της, μπορείς να ανανεώσεις το στοκ σου εύκολα με ένα κλικ:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . esc_url( $product_link ) . '" style="background-color: #C7B297; color: #ffffff; padding: 14px 35px; text-decoration: none; font-weight: bold; border-radius: 5px; display: inline-block; font-size: 1.05em;">🛒 Παράγγειλε Ξανά Εδώ</a>
            </div>
            
            <hr style="border: 0; border-top: 1px solid #eef0f2; margin-top: 30px;" />
            
            <p style="font-size: 0.85em; color: #8B6139; text-align: center; margin-bottom: 0; margin-top: 20px;">
                Με αγάπη,<br>
                <strong style="color: #43282F;">Η ομάδα του Petling.gr</strong>
            </p>
        </div>
    </div>
    ';

    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail( $to, $subject, $message, $headers );
}