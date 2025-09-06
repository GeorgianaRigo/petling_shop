<?php
/**
 * Plugin Name:       Supplier Shipping Rules
 * Description:       Ορίζει κανόνες αποστολής βάσει προμηθευτή και ενημερώνει για ξεχωριστές αποστολές.
 * Version:           1.4.0
 * Author:            Georgiana
 */

// Αποτροπή άμεσης πρόσβασης στο αρχείο
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. ΔΗΜΙΟΥΡΓΙΑ ΤΗΣ ΣΕΛΙΔΑΣ ΡΥΘΜΙΣΕΩΝ ΣΤΟΝ ΠΙΝΑΚΑ ΕΛΕΓΧΟΥ
 */
add_action( 'admin_menu', 'ssr_add_admin_menu' );
add_action( 'admin_init', 'ssr_settings_init' );

function ssr_add_admin_menu() {
    add_options_page(
        'Supplier Shipping Rules',
        'Supplier Shipping',
        'manage_options',
        'supplier_shipping_rules',
        'ssr_options_page_html'
    );
}

// Ενημερωμένη συνάρτηση για να προσθέσουμε τις νέες ρυθμίσεις
function ssr_settings_init() {
    // Group 1: Κανόνες Αποστολής
    register_setting( 'ssr_settings_group', 'ssr_supplier_rules' );

    add_settings_section(
        'ssr_rules_section',
        '1. Κανόνες Αποστολής',
        null,
        'supplier_shipping_rules'
    );

    add_settings_field(
        'ssr_rules_textarea',
        'Ορισμός Κανόνων',
        'ssr_rules_textarea_callback',
        'supplier_shipping_rules',
        'ssr_rules_section'
    );

    // Group 2: Μήνυμα Ξεχωριστής Αποστολής (ΝΕΟ)
    register_setting( 'ssr_settings_group', 'ssr_split_shipping_message' );

    add_settings_section(
        'ssr_message_section',
        '2. Μήνυμα Ξεχωριστής Αποστολής',
        null,
        'supplier_shipping_rules'
    );

    add_settings_field(
        'ssr_message_editor',
        'Περιεχόμενο Μηνύματος',
        'ssr_message_editor_callback',
        'supplier_shipping_rules',
        'ssr_message_section'
    );
}

function ssr_rules_textarea_callback() {
    $options = get_option( 'ssr_supplier_rules' );
    ?>
    <textarea cols='80' rows='10' name='ssr_supplier_rules'><?php echo esc_textarea( $options ); ?></textarea>
    <p class="description">
        Εισάγετε έναν κανόνα ανά γραμμή με την εξής μορφή: <br>
        <code>ΌνομαΠρομηθευτή,ID_Μεθόδου_Αποστολής</code><br>
        <b>Παράδειγμα:</b><br>
        <code>SupplierA,flat_rate:1</code><br>
        <code>SupplierB,flat_rate:2</code>
    </p>
    <?php
}

// ΝΕΑ συνάρτηση για την εμφάνιση του HTML editor
function ssr_message_editor_callback() {
    $content = get_option( 'ssr_split_shipping_message' );
    // Αν το πεδίο είναι άδειο, βάζουμε ένα προκαθορισμένο κείμενο
    if ( empty( $content ) ) {
        $content = '<strong>Προσοχή:</strong> Η παραγγελία σας περιλαμβάνει προϊόντα από διαφορετικούς προμηθευτές και θα αποσταλεί σε ξεχωριστά δέματα.';
    }
    wp_editor( $content, 'ssrspliteditor', array( 'textarea_name' => 'ssr_split_shipping_message' ) );
}

// Ενημερωμένη συνάρτηση για την εμφάνιση της σελίδας με την περιγραφή
function ssr_options_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <div style="padding: 10px; background-color: #f0f6fc; border-left: 4px solid #2271b1; margin: 15px 0;">
            <p>Αυτό το plugin διαχειρίζεται τις αποστολές προϊόντων από διαφορετικούς προμηθευτές με δύο τρόπους:</p>
            <ul style="list-style-type: disc; padding-left: 20px;">
                <li><b>Εμφάνιση μηνύματος:</b> Εάν το καλάθι περιέχει προϊόντα από 2 ή περισσότερους προμηθευτές, εμφανίζεται αυτόματα ένα προσαρμοσμένο μήνυμα στο checkout (το ρυθμίζετε παρακάτω). Αυτό λειτουργεί πάντα.</li>
                <li><b>Περιορισμός Μεθόδων Αποστολής:</b> Εάν το καλάθι περιέχει προϊόντα ΜΟΝΟ από έναν προμηθευτή για τον οποίο έχετε ορίσει κανόνα παρακάτω, τότε στο checkout θα εμφανιστεί ΜΟΝΟ η συγκεκριμένη μέθοδος αποστολής που του αντιστοιχεί. Αν δεν ορίσετε κανόνες, αυτή η λειτουργία δεν ενεργοποιείται.</li>
            </ul>
        </div>
        
        <form action="options.php" method="post">
            <?php
            settings_fields( 'ssr_settings_group' );
            do_settings_sections( 'supplier_shipping_rules' );
            submit_button( 'Αποθήκευση Ρυθμίσεων' );
            ?>
        </form>
    </div>
    <?php
}

/**
 * 2. ΕΦΑΡΜΟΓΗ ΤΗΣ ΛΟΓΙΚΗΣ ΣΤΟ ΚΑΛΑΘΙ ΚΑΙ ΤΟ CHECKOUT
 */
add_filter( 'woocommerce_package_rates', 'ssr_filter_shipping_methods', 100, 2 );
function ssr_filter_shipping_methods( $rates, $package ) {
    $rules_string = get_option( 'ssr_supplier_rules' );
    if ( empty( $rules_string ) ) { return $rates; }
    $rules = [];
    $lines = explode( "\n", $rules_string );
    foreach ( $lines as $line ) {
        $parts = explode( ',', trim( $line ) );
        if ( count( $parts ) === 2 ) {
            $rules[ trim( $parts[0] ) ] = trim( $parts[1] );
        }
    }
    if ( empty( $rules ) ) { return $rates; }
    $suppliers_in_cart = [];
    foreach ( $package['contents'] as $item ) {
        $supplier = get_post_meta( $item['product_id'], '_supplier_name', true );
        if ( ! empty( $supplier ) && array_key_exists( $supplier, $rules ) ) {
            $suppliers_in_cart[ $supplier ] = true;
        }
    }
    if ( count( $suppliers_in_cart ) === 1 ) {
        $supplier_name = key( $suppliers_in_cart );
        $allowed_method = $rules[ $supplier_name ];
        foreach ( $rates as $rate_id => $rate ) {
            if ( $rate_id !== $allowed_method ) { unset( $rates[ $rate_id ] ); }
        }
    }
    return $rates;
}

// Ενημερωμένη συνάρτηση που παίρνει το μήνυμα από τις ρυθμίσεις
add_action( 'woocommerce_checkout_after_order_review', 'ssr_show_split_shipping_notice_custom_html', 10 );
function ssr_show_split_shipping_notice_custom_html() {
    if ( ! WC()->cart || WC()->cart->is_empty() ) { return; }
    $suppliers_in_cart = [];
    foreach ( WC()->cart->get_cart() as $item ) {
        $supplier = get_post_meta( $item['data']->get_id(), '_supplier_name', true );
        if ( ! empty( $supplier ) ) { $suppliers_in_cart[ $supplier ] = true; }
    }

    if ( count( $suppliers_in_cart ) > 1 ) {
        $message = get_option( 'ssr_split_shipping_message' );
        // Αν ο χρήστης δεν έχει αποθηκεύσει κάτι, βάζουμε το προκαθορισμένο
        if ( empty( $message ) ) {
            $message = '<strong>Προσοχή:</strong> Η παραγγελία σας περιλαμβάνει προϊόντα από διαφορετικούς προμηθευτές και θα αποσταλεί σε ξεχωριστά δέματα.';
        }
        
        // Χρησιμοποιούμε τη συνάρτηση wpautop για να μετατρέψει τις αλλαγές γραμμής σε παραγράφους <p>, όπως κάνει ο editor
        echo '<div class="supplier-split-notice" style="padding: 1em; margin-top: 1.5em; border: 2px solid #C7B297; border-radius: 5px;">' . wpautop( $message ) . '</div>';
    }
}
