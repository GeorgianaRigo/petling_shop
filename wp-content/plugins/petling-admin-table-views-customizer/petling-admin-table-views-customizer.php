<?php
/**
 * Plugin Name: Petling Admin Table Views Customizer
 * Description: Διαχείριση και εξατομίκευση των πινάκων στο διαχειριστικό (π.χ. δυναμική προσθήκη στήλης Προμηθευτή και φίλτρων).
 * Version: 1.2
 * Author: Georgiana
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. ΠΡΟΣΘΗΚΗ ΣΤΗΛΗΣ "ΠΡΟΜΗΘΕΥΤΗΣ" ΣΤΟΝ ΠΙΝΑΚΑ ΠΡΟΪΟΝΤΩΝ
 */

// Προσθήκη της στήλης με υψηλή προτεραιότητα (999) για να εμφανίζεται σίγουρα
add_filter( 'manage_edit-product_columns', 'petling_admin_add_supplier_column', 999 );
function petling_admin_add_supplier_column( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $title ) {
        // Τοποθέτηση πριν από την ημερομηνία
        if ( $key == 'date' ) {
            $new_columns['supplier_name'] = 'Προμηθευτής';
        }
        $new_columns[$key] = $title;
    }
    
    // Ασφαλιστική δικλείδα αν δεν υπάρχει στήλη ημερομηνίας
    if ( ! isset( $new_columns['supplier_name'] ) ) {
        $new_columns['supplier_name'] = 'Προμηθευτής';
    }
    
    return $new_columns;
}

// Εμφάνιση της τιμής (meta data) στην κάθε γραμμή του πίνακα
add_action( 'manage_product_posts_custom_column', 'petling_admin_populate_supplier_column', 999, 2 );
function petling_admin_populate_supplier_column( $column, $postid ) {
    if ( $column == 'supplier_name' ) {
        $supplier = get_post_meta( $postid, '_supplier_name', true );
        if ( ! empty( $supplier ) ) {
            // Στυλ για να ξεχωρίζει ο προμηθευτής
            echo '<mark style="background: #e5f5fa; color: #007cba; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;">' . esc_html( $supplier ) . '</mark>';
        } else {
            echo '<span style="color: #bbb;">—</span>';
        }
    }
}

/**
 * 2. ΠΡΟΣΘΗΚΗ ΦΙΛΤΡΟΥ ΠΡΟΜΗΘΕΥΤΗ (DROPDOWN)
 */

// Δημιουργία του μενού επιλογής (Dropdown) πάνω από τον πίνακα
add_action( 'restrict_manage_posts', 'petling_admin_add_supplier_filter_dropdown', 999 );
function petling_admin_add_supplier_filter_dropdown() {
    global $typenow, $wpdb;
    if ( $typenow == 'product' ) {
        $current_supplier = isset( $_GET['filter_supplier'] ) ? sanitize_text_field( $_GET['filter_supplier'] ) : '';
        
        // Δυναμική άντληση των προμηθευτών από τη βάση δεδομένων
        $suppliers = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_supplier_name' 
            AND meta_value != '' 
            ORDER BY meta_value ASC
        ");
        
        echo '<select name="filter_supplier" id="filter_supplier">';
        echo '<option value="">Όλοι οι Προμηθευτές</option>';
        
        if ( ! empty( $suppliers ) ) {
            foreach ( $suppliers as $s ) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $s ),
                    selected( $current_supplier, $s, false ),
                    esc_html( $s )
                );
            }
        }
        echo '</select>';
    }
}

// Εφαρμογή του φίλτρου στο ερώτημα (Query) της βάσης δεδομένων
add_filter( 'parse_query', 'petling_admin_apply_supplier_filter_query' );
function petling_admin_apply_supplier_filter_query( $query ) {
    global $pagenow, $typenow;
    
    if ( $pagenow == 'edit.php' && $typenow == 'product' && isset( $_GET['filter_supplier'] ) && ! empty( $_GET['filter_supplier'] ) ) {
        
        $meta_query = (array) $query->get( 'meta_query' );
        
        $meta_query[] = array(
            'key'     => '_supplier_name',
            'value'   => sanitize_text_field( $_GET['filter_supplier'] ),
            'compare' => '='
        );
        
        $query->set( 'meta_query', $meta_query );
    }
}

/**
 * 3. ΑΠΟΚΡΥΨΗ ΑΔΕΙΩΝ TABS (ΕΝΑΛΛΑΓΗΣ) ΣΤΟ ELEMENTOR
 * Εξαφανίζει τις καρτέλες (Περιγραφή, Δοσολογία κ.λπ.) αν το πεδίο είναι κενό.
 */
add_action( 'wp_footer', 'petling_hide_empty_elementor_toggles' );
function petling_hide_empty_elementor_toggles() {
    // Θέλουμε να τρέχει ΜΟΝΟ στις σελίδες των προϊόντων για να μην βαραίνει το υπόλοιπο site
    if ( ! is_product() ) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Ψάχνει όλα τα στοιχεία "Εναλλαγής" (Toggles) και "Ακορντεόν" στη σελίδα
        $('.elementor-toggle-item, .elementor-accordion-item').each(function() {
            
            // Παίρνει το καθαρό κείμενο μέσα από το περιεχόμενο της καρτέλας
            var content = $(this).find('.elementor-tab-content').text().trim();
            
            // Αν το περιεχόμενο είναι εντελώς κενό (ή έχει λιγότερους από 2 χαρακτήρες)
            if (content.length < 2) {
                // Εξαφανίζει ολόκληρη την καρτέλα (Μαζί με τον Τίτλο της)
                $(this).hide(); 
            }
        });
    });
    </script>
    <?php
}