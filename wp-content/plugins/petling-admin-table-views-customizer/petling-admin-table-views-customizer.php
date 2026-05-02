<?php
/**
 * Plugin Name: Petling Admin Table Views Customizer
 * Description: Διαχείριση και εξατομίκευση των πινάκων στο διαχειριστικό (π.χ. προσθήκη στήλης Προμηθευτή και φίλτρων).
 * Version: 1.1
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
    global $typenow;
    if ( $typenow == 'product' ) {
        $current_supplier = isset( $_GET['filter_supplier'] ) ? sanitize_text_field( $_GET['filter_supplier'] ) : '';
        
        // Λίστα προμηθευτών (μπορείς να προσθέσεις κι άλλους εδώ στο μέλλον)
        $suppliers = array( 'Petmenu', 'Yoggies', 'Georgiana' );
        
        echo '<select name="filter_supplier" id="filter_supplier">';
        echo '<option value="">Όλοι οι Προμηθευτές</option>';
        foreach ( $suppliers as $s ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $s ),
                selected( $current_supplier, $s, false ),
                esc_html( $s )
            );
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