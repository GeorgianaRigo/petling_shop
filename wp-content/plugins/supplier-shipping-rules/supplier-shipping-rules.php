<?php
/**
 * Plugin Name:       Supplier Shipping Rules
 * Description:       Ορίζει κανόνες αποστολής βάσει προμηθευτή και ενημερώνει για ξεχωριστές αποστολές.
 * Version:           2.2.4
 * Author:            Georgiana
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. ΔΗΜΙΟΥΡΓΙΑ ΤΗΣ ΣΕΛΙΔΑΣ ΡΥΘΜΙΣΕΩΝ
 */
add_action( 'admin_menu', 'ssr_add_admin_menu' );
add_action( 'admin_init', 'ssr_settings_init' );
function ssr_add_admin_menu() { add_options_page('Supplier Shipping Rules', 'Supplier Shipping', 'manage_options', 'supplier_shipping_rules', 'ssr_options_page_html'); }
function ssr_settings_init() {
    register_setting( 'ssr_settings_group', 'ssr_free_shipping_minimum_amount' );
    register_setting( 'ssr_settings_group', 'ssr_split_shipping_message' );
    add_settings_section( 'ssr_minimum_amount_section', '1. Ρύθμιση Ορίου Δωρεάν Αποστολής', null, 'supplier_shipping_rules' );
    add_settings_field( 'ssr_minimum_amount_field', 'Ελάχιστο Ποσό (€)', 'ssr_minimum_amount_callback', 'supplier_shipping_rules', 'ssr_minimum_amount_section' );
    add_settings_section( 'ssr_message_section', '2. Μήνυμα Ξεχωριστής Αποστολής', null, 'supplier_shipping_rules' );
    add_settings_field( 'ssr_message_editor', 'Περιεχόμενο Μηνύματος', 'ssr_message_editor_callback', 'supplier_shipping_rules', 'ssr_message_section' );
}
function ssr_minimum_amount_callback() {
    $amount = get_option('ssr_free_shipping_minimum_amount', '50');
    echo '<input type="number" step="0.01" name="ssr_free_shipping_minimum_amount" value="' . esc_attr($amount) . '" />';
    echo '<p class="description">Ορίστε το ελάχιστο ποσό παραγγελίας για τους κανόνες δωρεάν αποστολής.</p>';
}
function ssr_message_editor_callback() {
    $content = get_option( 'ssr_split_shipping_message', '<strong>Προσοχή:</strong> Η παραγγελία σας περιλαμβάνει προϊόντα από διαφορετικούς προμηθευτές και θα αποσταλεί σε ξεχωριστά δέματα.' );
    wp_editor( $content, 'ssrspliteditor', array( 'textarea_name' => 'ssr_split_shipping_message' ) );
}
function ssr_options_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <div style="padding: 1em; background-color: #f6f7f7; border: 1px solid #ccd0d4; margin-top: 1em; border-radius: 4px;">
            <h2 style="margin-top: 0;">Περίληψη Ενεργών Κανόνων Αποστολής</h2>
            <p>Αυτή η σελίδα ρυθμίζει τους κανόνες αποστολής του site. Η λογική που εφαρμόζεται στο checkout είναι η εξής:</p>
            <ul style="list-style-type: disc; padding-left: 20px;">
                <li><strong>Όταν το καλάθι περιέχει <u>ΜΟΝΟ</u> προϊόντα "Petmenu":</strong>
                    <ul style="list-style-type: circle; padding-left: 20px;">
                        <li>Αν η αξία του καλαθιού είναι <u>κάτω</u> από το "Ελάχιστο Ποσό", εμφανίζονται οι μέθοδοι: <strong>Courier</strong> και <strong>Box Now (με χρέωση)</strong>.</li>
                        <li>Αν η αξία του καλαθιού είναι <u>πάνω</u> από το όριο, εμφανίζονται οι μέθοδοι: <strong>Courier Free shipping</strong> και <strong>Box now Free</strong>.</li>
                    </ul>
                </li>
                <li><strong>Όταν το καλάθι περιέχει <u>ΜΟΝΟ</u> προϊόντα "Georgiana":</strong>
                    <ul style="list-style-type: circle; padding-left: 20px;">
                        <li>Εμφανίζεται <strong>μόνο</strong> η μέθοδος <strong>Box now Free</strong>.</li>
                    </ul>
                </li>
                <li><strong>Όταν το καλάθι περιέχει <u>ΚΑΙ "Petmenu" ΚΑΙ "Georgiana"</u>:</strong>
                    <ul style="list-style-type: circle; padding-left: 20px;">
                        <li>Ελέγχεται η αξία <u>μόνο των προϊόντων Petmenu</u>.</li>
                        <li>Αν η αξία τους είναι <u>κάτω</u> από το όριο, εμφανίζεται <strong>μόνο</strong> το <strong>Courier</strong>.</li>
                        <li>Αν η αξία τους είναι <u>πάνω</u> από το όριο, εμφανίζονται τα <strong>Courier Free shipping</strong> και <strong>Box now Free</strong>.</li>
                    </ul>
                </li>
                <li><strong>Για όλες τις άλλες περιπτώσεις</strong>, εμφανίζονται οι προκαθορισμένες μέθοδοι του WooCommerce.</li>
                <li>Όταν το καλάθι περιέχει προϊόντα από <strong>2 ή περισσότερους προμηθευτές</strong>, εμφανίζεται το παρακάτω προσαρμοσμένο μήνυμα.</li>
            </ul>
             <p style="margin-top: 1em; padding-top: 1em; border-top: 1px solid #ddd;">
                Για να επεξεργαστείτε τις μεθόδους αποστολής και να επιβεβαιώσετε τα IDs τους, μεταβείτε στις ρυθμίσεις του WooCommerce:
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=1')); ?>" class="button button-secondary" style="vertical-align: middle; margin-left: 10px;">Ρυθμίσεις Ζώνης Αποστολής</a>
            </p>
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
 * Κεντρική Λογική: Φιλτράρισμα μεθόδων αποστολής
 */
add_filter( 'woocommerce_package_rates', 'ssr_filter_shipping_methods_final', 100, 2 );
function ssr_filter_shipping_methods_final( $rates, $package ) {
    if ( ! WC()->cart || WC()->cart->is_empty() ) return $rates;

    $courier_id        = 'flat_rate:1';
    $box_now_id        = 'box_now_paid_id';
    $free_shipping_id  = 'free_shipping:2';
    $box_now_free_id   = 'free_shipping:3';
    $minimum_amount    = (float) get_option('ssr_free_shipping_minimum_amount', 50);

    $supplier_subtotals = [];
    foreach ($package['contents'] as $item) {
        $supplier = get_post_meta($item['product_id'], '_supplier_name', true);
        $supplier_key = !empty($supplier) ? $supplier : '_none_';
        
        if (!isset($supplier_subtotals[$supplier_key])) $supplier_subtotals[$supplier_key] = 0;
        $supplier_subtotals[$supplier_key] += $item['line_total'];
    }
    
    $allowed_rates = [];
    $has_petmenu   = isset($supplier_subtotals['Petmenu']);
    $has_georgiana = isset($supplier_subtotals['Georgiana']);
    $supplier_count = count(array_filter(array_keys($supplier_subtotals), function($key) { return $key !== '_none_'; }));

    if ($has_petmenu && $has_georgiana) {
        $petmenu_total = $supplier_subtotals['Petmenu'];
        if ($petmenu_total < $minimum_amount) {
            if (isset($rates[$courier_id])) $allowed_rates[$courier_id] = $rates[$courier_id];
        } else {
            if (isset($rates[$free_shipping_id])) $allowed_rates[$free_shipping_id] = $rates[$free_shipping_id];
            if (isset($rates[$box_now_free_id])) $allowed_rates[$box_now_free_id] = $rates[$box_now_free_id];
        }
        return $allowed_rates;
    }
    
    if ($has_petmenu && $supplier_count === 1) {
        $cart_total = WC()->cart->get_subtotal();
        if ($cart_total < $minimum_amount) {
            if (isset($rates[$courier_id])) $allowed_rates[$courier_id] = $rates[$courier_id];
            if (isset($rates[$box_now_id])) $allowed_rates[$box_now_id] = $rates[$box_now_id];
        } else {
            if (isset($rates[$free_shipping_id])) $allowed_rates[$free_shipping_id] = $rates[$free_shipping_id];
            if (isset($rates[$box_now_free_id])) $allowed_rates[$box_now_free_id] = $rates[$box_now_free_id];
        }
        return $allowed_rates;
    }

    if ($has_georgiana && $supplier_count === 1) {
        if (isset($rates[$box_now_free_id])) {
            $allowed_rates[$box_now_free_id] = $rates[$box_now_free_id];
        }
        return $allowed_rates;
    }
    
    return $rates;
}

// Λογική για το μήνυμα ξεχωριστής αποστολής
add_action( 'woocommerce_checkout_after_order_review', 'ssr_show_split_shipping_notice', 20 );
function ssr_show_split_shipping_notice() {
    if ( ! WC()->cart || WC()->cart->is_empty() ) return;
    $suppliers_in_cart = [];
    foreach ( WC()->cart->get_cart() as $item ) {
        $supplier = get_post_meta( $item['data']->get_id(), '_supplier_name', true );
        if ( ! empty( $supplier ) ) { 
            $suppliers_in_cart[ $supplier ] = true; 
        }
    }
    if ( count( $suppliers_in_cart ) > 1 ) {
        $message = get_option( 'ssr_split_shipping_message' );
        echo '<div class="supplier-split-notice" style="padding: 1em; margin-top: 1.5em; border: 2px solid #C7B297; border-radius: 5px;">' . wpautop( $message ) . '</div>';
    }
}