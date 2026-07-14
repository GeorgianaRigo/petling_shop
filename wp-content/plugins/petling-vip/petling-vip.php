<?php
/*
Plugin Name: Petling VIP Club & Referrals
Plugin URI: https://petling.gr
Description: Αυτόματο σύστημα εκπτώσεων VIP (Tiers) βάσει τζίρου, ανταμοιβή Referral (Σύστησε ένα φίλο) και Κεντρικό Μενού Petling.
Version: 1.3
Author: Petling
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 0. ΚΕΝΤΡΙΚΟ ΜΕΝΟΥ PETLING & ΥΠΟΜΕΝΟΥ VIP CLUB
// =========================================================================
add_action( 'admin_menu', 'ptl_vip_admin_menu' );
function ptl_vip_admin_menu() {
    
    // 1. Δημιουργούμε το Κεντρικό Μενού "Petling" (με εικονίδιο πατούσας)
    if ( empty ( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        add_menu_page(
            'Petling', 
            'Petling', 
            'manage_options', 
            'petling-main', 
            'ptl_vip_admin_page_html', // Το κεντρικό κλικ φορτώνει ΚΑΤΕΥΘΕΙΑΝ τους κανόνες VIP!
            'dashicons-pets', // Εικονίδιο πατούσας
            55 // Θέση στο μενού
        );
        
        // 2. Μετονομάζουμε το 1ο αυτόματο υπομενού για να μη λέει πάλι "Petling"
        add_submenu_page(
            'petling-main',               
            'Petling VIP & Referrals',    
            'VIP & Referrals',            
            'manage_options',
            'petling-main',          
            'ptl_vip_admin_page_html'     
        );
    } else {
        // Αν το Petling μενού υπάρχει ήδη (αν βάλεις άλλα plugins αργότερα), το προσθέτουμε ως απλό υπομενού
        add_submenu_page(
            'petling-main',               
            'Petling VIP & Referrals',    
            'VIP & Referrals',            
            'manage_options',
            'petling-vip-rules',          
            'ptl_vip_admin_page_html'     
        );
    }
}

// Σελίδα των κανόνων του VIP Club & Referrals
function ptl_vip_admin_page_html() {
    ?>
    <div class="wrap">
        <h1 style="color: #43282F; display: flex; align-items: center; gap: 10px;">
            <span class="dashicons dashicons-star-filled" style="font-size: 28px; width: 28px; height: 28px; color: #C7B297;"></span> 
            Κανόνες Petling VIP Club & Referrals
        </h1>
        
        <div style="background: #fff; border: 1px solid #ccc; border-left: 4px solid #C7B297; padding: 20px; margin-top: 20px; max-width: 800px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; color: #2271b1;">1. Αυτόματες Εκπτώσεις VIP (Tiers)</h2>
            <p>Το σύστημα ελέγχει τον συνολικό τζίρο (ιστορικό παραγγελιών) του συνδεδεμένου πελάτη και εφαρμόζει αυτόματα την αντίστοιχη έκπτωση στο καλάθι του.</p>
            <ul style="list-style-type: disc; margin-left: 20px; font-size: 15px;">
                <li><strong>Απλό Μέλος (Απλή Εγγραφή):</strong> 5% Μόνιμη Έκπτωση</li>
                <li><strong>Silver VIP (Τζίρος >= 1.000€):</strong> 7% Μόνιμη Έκπτωση</li>
                <li><strong>Gold VIP (Τζίρος >= 2.000€):</strong> 10% Μόνιμη Έκπτωση</li>
            </ul>
        </div>

        <div style="background: #fff; border: 1px solid #ccc; border-left: 4px solid #5b9a68; padding: 20px; margin-top: 20px; max-width: 800px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; color: #2271b1;">2. Σύστημα Referral (Σύστησε ένα φίλο)</h2>
            <p>Οι πελάτες βλέπουν ένα μοναδικό Link (π.χ. <code>petling.gr/?ref=125</code>) στη σελίδα <em>"Ο Λογαριασμός μου"</em>. Αν κάποιος φίλος τους πατήσει το link και κάνει παραγγελία:</p>
            <ul style="list-style-type: disc; margin-left: 20px; font-size: 15px;">
                <li>Μόλις η <strong>ΠΡΩΤΗ</strong> παραγγελία του φίλου αλλάξει σε <em>"Ολοκληρωμένη"</em>, δημιουργείται αυτόματα ένα κουπόνι έκπτωσης <strong>5%</strong>.</li>
                <li>Το κουπόνι αποστέλλεται αυτόματα με <strong>email</strong> σε αυτόν που έκανε τη σύσταση.</li>
                <li><strong>Περιορισμός Ασφαλείας:</strong> Το κουπόνι σύστασης λειτουργεί ΜΟΝΟ για τα "Απλά Μέλη" (που έχουν 5% VIP). Αν ο πελάτης έχει φτάσει στο Silver (7%) ή Gold (10%), το σύστημα μπλοκάρει το κουπόνι.</li>
            </ul>
        </div>
        <p style="margin-top: 20px; font-style: italic; color: #666;">Όλες οι λειτουργίες εκτελούνται αυτόματα στο παρασκήνιο. Δεν απαιτείται καμία χειροκίνητη ενέργεια.</p>
    </div>
    <?php
}

// =========================================================================
// Α. ΣΥΣΤΗΜΑ VIP TIERS (ΑΥΤΟΜΑΤΗ ΕΚΠΤΩΣΗ ΣΤΟ ΚΑΛΑΘΙ)
// =========================================================================
add_action( 'woocommerce_cart_calculate_fees', 'ptl_apply_vip_club_discount', 10, 1 );
function ptl_apply_vip_club_discount( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    
    // Η έκπτωση ισχύει ΜΟΝΟ για όσους έχουν κάνει εγγραφή/σύνδεση
    if ( ! is_user_logged_in() ) return;
    
    $user_id = get_current_user_id();
    $total_spent = wc_get_customer_total_spent( $user_id );
    
    $discount_percent = 5; // Προεπιλογή: 5% για απλή εγγραφή
    $tier_name = 'Απλό Μέλος';
    
    // Έλεγχος Τζίρου
    if ( $total_spent >= 2000 ) {
        $discount_percent = 10;
        $tier_name = 'Gold VIP';
    } elseif ( $total_spent >= 1000 ) {
        $discount_percent = 7;
        $tier_name = 'Silver VIP';
    }
    
    // Υπολογισμός Έκπτωσης επί του υποσυνόλου (χωρίς τα μεταφορικά)
    $discount_amount = ( $cart->get_subtotal() * $discount_percent ) / 100;
    
    if ( $discount_amount > 0 ) {
        // Προσθήκη Αρνητικού Fee (Έκπτωση)
        $cart->add_fee( sprintf( 'Petling Club (%s - %d%%)', $tier_name, $discount_percent ), -$discount_amount, true );
    }
}

// =========================================================================
// Β. REFERRAL SYSTEM (ΣΥΣΤΗΣΕ ΕΝΑ ΦΙΛΟ)
// =========================================================================

// 1. Αποθήκευση του Referrer σε Cookie
add_action( 'init', 'ptl_track_referral_link' );
function ptl_track_referral_link() {
    if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) ) {
        $referrer_id = intval( $_GET['ref'] );
        setcookie( 'petling_ref_id', $referrer_id, time() + (30 * DAY_IN_SECONDS), '/' );
    }
}

// 2. Εμφάνιση του Referral Link στον Πίνακα Ελέγχου του Πελάτη
add_action( 'woocommerce_account_dashboard', 'ptl_show_referral_link_in_my_account' );
function ptl_show_referral_link_in_my_account() {
    $user_id = get_current_user_id();
    $ref_link = home_url( '/?ref=' . $user_id );
    ?>
    <div style="background: #F5EDE3; padding: 20px; border: 2px dashed #C7B297; border-radius: 8px; margin-bottom: 25px; text-align: center;">
        <h3 style="margin-top: 0; color: #43282F;">🎁 Πρόγραμμα "Σύστησε ένα Φίλο"</h3>
        <p style="margin-bottom: 10px;">Μοιραστείτε τον παρακάτω σύνδεσμο με τους φίλους σας. Μόλις εγγραφούν και ολοκληρώσουν την πρώτη τους παραγγελία, εσείς <strong>θα λάβετε ένα κουπόνι έκπτωσης 5% στο email σας!</strong></p>
        <input type="text" value="<?php echo esc_url( $ref_link ); ?>" readonly style="width: 100%; max-width: 400px; padding: 10px; text-align: center; font-weight: bold; color: #5b9a68; background: #fff; border: 1px solid #C7B297; border-radius: 4px;" onclick="this.select();">
    </div>
    <?php
}

// 3. Σύνδεση του νέου πελάτη με αυτόν που τον σύστησε
add_action( 'user_register', 'ptl_save_referrer_on_registration' );
function ptl_save_referrer_on_registration( $user_id ) {
    if ( isset( $_COOKIE['petling_ref_id'] ) ) {
        $referrer_id = intval( $_COOKIE['petling_ref_id'] );
        if ( $referrer_id !== $user_id ) {
            update_user_meta( $user_id, '_petling_referred_by', $referrer_id );
        }
    }
}

// 4. Δημιουργία Κουπονιού & Email στον Referrer με την ΠΡΩΤΗ αγορά
add_action( 'woocommerce_order_status_completed', 'ptl_reward_referrer_with_coupon' );
function ptl_reward_referrer_with_coupon( $order_id ) {
    $order = wc_get_order( $order_id );
    $customer_id = $order->get_customer_id();

    if ( ! $customer_id ) return;

    $referrer_id = get_user_meta( $customer_id, '_petling_referred_by', true );
    
    if ( $referrer_id ) {
        $already_rewarded = get_user_meta( $customer_id, '_petling_referral_rewarded', true );
        
        if ( ! $already_rewarded ) {
            $referrer_user = get_userdata( $referrer_id );
            
            if ( $referrer_user ) {
                $referrer_email = $referrer_user->user_email;
                
                // Δημιουργία μοναδικού κωδικού κουπονιού που ξεκινάει με ref-
                $coupon_code = 'ref-' . strtolower(substr(md5(uniqid(rand(), true)), 0, 6));
                
                // Ρυθμίσεις του νέου κουπονιού
                $coupon = new WC_Coupon();
                $coupon->set_code( $coupon_code );
                $coupon->set_discount_type( 'percent' ); 
                $coupon->set_amount( 5 ); // 5% έκπτωση
                $coupon->set_usage_limit( 1 ); // Μίας χρήσης
                $coupon->set_email_restrictions( array( $referrer_email ) ); // ΜΟΝΟ για το email του
                $coupon->set_description( 'Ανταμοιβή Referral για τον/την ' . $referrer_email );
                $coupon->save();

                // "Κλειδώνουμε" την επιβράβευση
                update_user_meta( $customer_id, '_petling_referral_rewarded', 'yes' );

                // Αποστολή Email
                $subject = "🎉 Έχετε ένα νέο δώρο από το Petling!";
                $message = "Γεια σας!\n\nΈνας φίλος που καλέσατε, μόλις ολοκλήρωσε την πρώτη του αγορά στο Petling!\n\nΓια να σας ανταμείψουμε, σας κάνουμε δώρο 5% έκπτωση για την επόμενη παραγγελία σας.\n\nΟ μοναδικός σας κωδικός είναι: " . strtoupper($coupon_code) . "\n\n(Ο κωδικός ισχύει για 1 χρήση και λειτουργεί μόνο εφόσον δεν έχετε φτάσει στην έκπτωση Silver/Gold VIP).\n\nΣας ευχαριστούμε για την υποστήριξη!";
                
                $headers = array('Content-Type: text/plain; charset=UTF-8', 'From: Petling <info@petling.gr>');
                wp_mail( $referrer_email, $subject, $message, $headers );
            }
        }
    }
}

// 5. Απαγόρευση Χρήσης του Κουπονιού (Referral) για τους χρήστες > 1000€ (Silver/Gold)
add_filter( 'woocommerce_coupon_is_valid', 'ptl_restrict_referral_coupon_for_vips', 10, 3 );
function ptl_restrict_referral_coupon_for_vips( $valid, $coupon, $discount ) {
    if ( ! $valid ) return $valid;
    
    $coupon_code = strtolower( $coupon->get_code() );
    
    // Ελέγχουμε αν είναι κουπόνι σύστασης (ξεκινάει με ref-)
    if ( strpos( $coupon_code, 'ref-' ) === 0 ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $total_spent = wc_get_customer_total_spent( $user_id );
            
            // Αν ο πελάτης έχει τζίρο >= 1000 (δηλαδή 7% ή 10%), το μπλοκάρουμε
            if ( $total_spent >= 1000 ) {
                throw new Exception( 'Δεν μπορείτε να χρησιμοποιήσετε το κουπόνι σύστασης, καθώς απολαμβάνετε ήδη μεγαλύτερη μόνιμη έκπτωση (Silver/Gold VIP) στο καλάθι σας!' );
            }
        }
    }
    
    return $valid;
}