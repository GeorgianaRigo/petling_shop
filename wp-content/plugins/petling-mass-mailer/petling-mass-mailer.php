<?php
/*
Plugin Name: Petling Mass Mailer
Description: Συγκεντρώνει emails για μαζική αποστολή, με επαγγελματικό HTML template, λογότυπο και αυτόματο σύστημα Unsubscribe (GDPR Compliant).
Version: 1.5
Author: Petling Custom
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// --- Σύστημα Αυτόματης Διαγραφής (Unsubscribe) ---
add_action( 'init', 'petling_handle_unsubscribe' );
function petling_handle_unsubscribe() {
    if ( isset( $_GET['petling_unsubscribe'] ) ) {
        // Διαβάζουμε το email που είναι κρυπτογραφημένο στο link
        $email = sanitize_email( base64_decode( $_GET['petling_unsubscribe'] ) );
        if ( is_email( $email ) ) {
            // Παίρνουμε τη λίστα με όσους έχουν διαγραφεί
            $unsubscribed = get_option( 'petling_unsubscribed_emails', array() );
            // Αν δεν είναι ήδη στη λίστα, τον προσθέτουμε
            if ( ! in_array( $email, $unsubscribed ) ) {
                $unsubscribed[] = $email;
                update_option( 'petling_unsubscribed_emails', $unsubscribed );
            }
            // Εμφανίζουμε μήνυμα στον χρήστη
            wp_die( 
                'Το email <strong>' . esc_html( $email ) . '</strong> διαγράφηκε επιτυχώς από τη λίστα ενημερώσεων μας. Δεν θα λαμβάνετε πλέον μαζικά emails από εμάς.', 
                'Επιτυχής Διαγραφή', 
                array( 'response' => 200 ) 
            );
        }
    }
}

// 1. Προσθήκη ως Υπομενού στο κεντρικό "Petling"
add_action( 'admin_menu', 'petling_mass_mailer_admin_menu' );
function petling_mass_mailer_admin_menu() {
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'petling_mass_mailer_page', 'dashicons-pets', 55 );
        add_submenu_page( 'petling-main', 'Μαζικά Email', 'Μαζικά Email', 'manage_options', 'petling-mass-mailer', 'petling_mass_mailer_page' );
    } else {
        add_submenu_page( 'petling-main', 'Μαζικά Email', 'Μαζικά Email', 'manage_options', 'petling-mass-mailer', 'petling_mass_mailer_page' );
    }
}

// 2. Η σελίδα του Plugin
function petling_mass_mailer_page() {
    global $wpdb;

    // --- Έλεγχος αν πατήθηκε το κουμπί Αποστολής ---
    if ( isset( $_POST['petling_send_emails'] ) && check_admin_referer( 'petling_send_action' ) ) {
        $recipients = isset( $_POST['recipients'] ) ? $_POST['recipients'] : array();
        $subject    = sanitize_text_field( $_POST['email_subject'] );
        $message    = wp_kses_post( wp_unslash( $_POST['email_message'] ) );

        if ( empty( $recipients ) || empty( $subject ) || empty( $message ) ) {
            echo '<div class="notice notice-error"><p>Σφάλμα: Παρακαλώ επιλέξτε παραλήπτες, γράψτε θέμα και μήνυμα.</p></div>';
        } else {
            $sent_count = 0;
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            // --- Δημιουργία Βασικού HTML Template ---
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
            $base_template .= '</div></div>'; // Τέλος λευκού κουτιού

            // Αποστολή (προσωποποιημένη για να μπει το σωστό unsubscribe link στον καθένα)
            foreach ( $recipients as $email ) {
                $email = sanitize_email( $email );
                if ( is_email( $email ) ) {
                    // Δημιουργία Μοναδικού Σύνδεσμου Διαγραφής
                    $unsub_link = site_url( '/?petling_unsubscribe=' . base64_encode( $email ) );
                    
                    $final_email_content = $base_template;
                    $final_email_content .= '<div style="text-align: center; margin-top: 20px; color: #999999; font-size: 12px;">';
                    $final_email_content .= '<p>Λάβατε αυτό το email επειδή είστε εγγεγραμμένοι στο ' . get_bloginfo('name') . '.</p>';
                    $final_email_content .= '<p><a href="' . esc_url( $unsub_link ) . '" style="color: #999999; text-decoration: underline;">Διαγραφή από τη λίστα (Unsubscribe)</a></p>';
                    $final_email_content .= '</div></div>'; // Τέλος γκρι φόντου

                    wp_mail( $email, $subject, $final_email_content, $headers );
                    $sent_count++;
                }
            }
            echo '<div class="notice notice-success"><p>Επιτυχία! Στάλθηκαν ' . $sent_count . ' emails.</p></div>';
        }
    }

    $all_emails = array();

    // A. Εγγεγραμμένοι Χρήστες
    $users = get_users();
    foreach ( $users as $user ) {
        $all_emails[] = $user->user_email;
    }

    // B. Πελάτες WooCommerce
    if ( class_exists( 'WooCommerce' ) ) {
        $legacy_emails = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_billing_email' AND meta_value != ''");
        if ( is_array( $legacy_emails ) ) $all_emails = array_merge( $all_emails, $legacy_emails );

        $hpos_table = $wpdb->prefix . 'wc_orders';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$hpos_table'") == $hpos_table ) {
            $hpos_emails = $wpdb->get_col("SELECT DISTINCT billing_email FROM {$hpos_table} WHERE billing_email != ''");
            if ( is_array( $hpos_emails ) ) $all_emails = array_merge( $all_emails, $hpos_emails );
        }
    }

    // C. Elementor Forms
    $elementor_table = $wpdb->prefix . 'e_submissions_values';
    if ( $wpdb->get_var("SHOW TABLES LIKE '$elementor_table'") == $elementor_table ) {
        $results = $wpdb->get_results( "SELECT value FROM $elementor_table WHERE value LIKE '%@%.%'" );
        foreach ( $results as $row ) {
            $all_emails[] = $row->value;
        }
    }

    // --- ΚΑΘΑΡΙΣΜΟΣ & ΦΙΛΤΡΑΡΙΣΜΑ ---
    $all_emails = array_filter( $all_emails, 'is_email' );
    $all_emails = array_unique( $all_emails );
    
    // ΑΦΑΙΡΕΣΗ ΟΣΩΝ ΕΧΟΥΝ ΠΑΤΗΣΕΙ "UNSUBSCRIBE"
    $unsubscribed = get_option( 'petling_unsubscribed_emails', array() );
    $all_emails = array_diff( $all_emails, $unsubscribed );
    
    sort($all_emails); 

    // Εμφάνιση
    ?>
    <div class="wrap">
        <h1>Αποστολή Μαζικών Emails (Petling)</h1>
        <p>Επιλέξτε τους χρήστες στους οποίους θέλετε να στείλετε το μήνυμα. <br>
        <small><em>Σημείωση: Τα emails που βλέπετε είναι "καθαρά". Όσοι πελάτες έχουν κάνει Unsubscribe αφαιρούνται αυτόματα.</em></small></p>

        <form method="post" action="">
            <?php wp_nonce_field( 'petling_send_action' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Τίτλος (Θέμα) Email:</th>
                    <td><input type="text" name="email_subject" value="" class="regular-text" required style="width: 100%;" /></td>
                </tr>
                <tr>
                    <th scope="row">Μήνυμα:</th>
                    <td>
                        <?php 
                        wp_editor( '', 'email_message', array(
                            'media_buttons' => true,
                            'textarea_name' => 'email_message',
                            'textarea_rows' => 15,
                        ) ); 
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Παραλήπτες: <br>
                        <small><a href="#" onclick="jQuery('.email-checkbox').prop('checked', true); return false;">Επιλογή όλων</a></small> | 
                        <small><a href="#" onclick="jQuery('.email-checkbox').prop('checked', false); return false;">Καθαρισμός</a></small>
                    </th>
                    <td style="max-height: 300px; overflow-y: auto; display: block; border: 1px solid #ccc; padding: 10px; background: #fff;">
                        <?php if ( ! empty( $all_emails ) ) : ?>
                            <?php foreach ( $all_emails as $email ) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="recipients[]" value="<?php echo esc_attr( $email ); ?>" class="email-checkbox" />
                                    <?php echo esc_html( $email ); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p>Δεν βρέθηκαν emails.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="petling_send_emails" id="submit" class="button button-primary" value="Αποστολή Email">
            </p>
        </form>
    </div>
    <?php
}