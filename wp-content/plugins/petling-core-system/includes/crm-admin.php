<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// CUSTOM POST TYPE: QUIZ LEADS & ΕΝΙΑΙΟ ADMIN MENU
// =========================================================================
add_action( 'init', 'ptl_register_leads_cpt' );
function ptl_register_leads_cpt() {
    register_post_type( 'ptl_quiz_lead', array(
        'labels'       => array( 'name' => 'Petling CRM', 'singular_name' => 'Lead', 'all_items' => 'Λίστα Πελατών' ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => false,
        'supports'     => array( 'title' ),
        'capabilities' => array( 'create_posts' => 'do_not_allow' ),
        'map_meta_cap' => true
    ) );
}

add_action( 'admin_menu', 'petling_core_unified_admin_menu' );
function petling_core_unified_admin_menu() {
    if ( empty( $GLOBALS['admin_page_hooks']['petling-main'] ) ) {
        add_menu_page( 'Petling', 'Petling', 'manage_options', 'petling-main', 'ptl_core_fallback_page', 'dashicons-pets', 55 );
        add_submenu_page( 'petling-main', 'Λίστα Πελατών', 'Λίστα Πελατών', 'manage_options', 'edit.php?post_type=ptl_quiz_lead' );
        remove_submenu_page( 'petling-main', 'petling-main' );
    }

    add_submenu_page( null, 'Ρυθμίσεις Promo', 'Ρυθμίσεις', 'manage_options', 'ptl-promo-settings', 'ptl_render_settings_page' );
    add_submenu_page( null, 'QR Code', 'QR Code', 'manage_options', 'ptl-promo-qrcode', 'ptl_render_qrcode_page' );
}

function ptl_core_fallback_page() {
    echo '<div class="wrap"><h1 style="color:#43282F;"><span class="dashicons dashicons-pets" style="font-size:32px; width:32px; height:32px;"></span> Petling Control Panel</h1><p>Επιλέξτε τη Λίστα Πελατών από το μενού στα αριστερά.</p></div>';
}

// =========================================================================
// TABS ΠΛΟΗΓΗΣΗΣ CRM
// =========================================================================
function ptl_render_admin_tabs( $active_tab = 'leads' ) {
    ?>
    <h2 class="nav-tab-wrapper" style="margin-bottom: 15px;">
        <a href="edit.php?post_type=ptl_quiz_lead" class="nav-tab <?php echo 'leads' === $active_tab ? 'nav-tab-active' : ''; ?>">Εγγεγραμμένοι Πελάτες (CRM)</a>
        <a href="admin.php?page=ptl-promo-settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">Ρυθμίσεις Συνεργατών</a>
        <a href="admin.php?page=ptl-promo-qrcode" class="nav-tab <?php echo 'qrcode' === $active_tab ? 'nav-tab-active' : ''; ?>">Δημιουργία QR Code</a>
    </h2>
    <?php
}

add_filter( 'views_edit-ptl_quiz_lead', 'ptl_add_tabs_to_cpt_lead_views' );
function ptl_add_tabs_to_cpt_lead_views( $views ) {
    ptl_render_admin_tabs( 'leads' );
    return $views;
}

// =========================================================================
// CRM LEADS - ΣΤΗΛΕΣ, ΦΙΛΤΡΑ & ΑΠΟΘΗΚΕΥΣΗ
// =========================================================================
add_filter( 'manage_ptl_quiz_lead_posts_columns', 'ptl_set_custom_lead_columns' );
function ptl_set_custom_lead_columns( $columns ) {
    return array(
        'cb'         => $columns['cb'],
        'title'      => 'ID Εγγραφής',
        'ptl_email'  => 'Email Πελάτη',
        'ptl_name'   => 'Όνοματεπώνυμο',
        'ptl_type'   => 'Πηγή',
        'ptl_result' => 'Αποτέλεσμα / Κωδικός',
        'ptl_status' => 'Κατάσταση',
        'date'       => $columns['date']
    );
}

add_action( 'manage_ptl_quiz_lead_posts_custom_column', 'ptl_custom_lead_column', 10, 2 );
function ptl_custom_lead_column( $column,$post_id ) {
    switch ( $column ) {
        case 'ptl_email':
            $email = get_post_meta($post_id, 'ptl_lead_email', true );
            if ( ! $email ) {$title_parts = explode( ' - ', get_the_title( $post_id ) );$email = is_email( $title_parts[0] ) ?$title_parts[0] : '-';
            }
            echo '<strong><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></strong>';
            break;

        case 'ptl_name':
            echo esc_html( get_post_meta( $post_id, 'ptl_lead_name', true ) );
            break;

        case 'ptl_type':
            $type = get_post_meta($post_id, 'ptl_lead_type', true );
            if ( $type === 'VET_MANOLAKOU' ) { echo '<span style="color: #d63638; font-weight:bold;">Δρ. Μανωλάκου (VET)</span>'; }
            elseif ( $type === 'HOME_COOKED_RECIPE' ) { echo '<span style="color: #46b450; font-weight:bold;">Μαγειρευτή Συνταγή</span>'; }
            elseif ( $type === 'FOOD_YOGGIES' ) { echo '<span style="color: #2271b1; font-weight:bold;">Πρόταση Τροφής (Yoggies)</span>'; }
            elseif ( strpos( $type, 'PROMO_' ) === 0 ) {
                $partner_prefix = str_replace( 'PROMO_', '',$type );
                echo '<span style="color: #ff9800; font-weight:bold;">Συνεργάτης (' . esc_html( $partner_prefix ) . ')</span>';
            } else {
                echo '<span style="color: #666; font-weight:bold;">' . esc_html( $type ) . '</span>';
            }
            break;

        case 'ptl_result':
            echo esc_html( get_post_meta( $post_id, 'ptl_lead_result', true ) );
            $condition = get_post_meta($post_id, 'ptl_health_condition', true );
            $notes     = get_post_meta( $post_id, 'ptl_health_notes', true );
            if ( ! empty( $condition ) || ! empty( $notes ) ) {
                echo '<div style="margin-top:8px; padding:8px; background:#fff3f3; border-left:3px solid #d63638; font-size:12px; border-radius:4px;">';
                if ( ! empty( $condition ) ) echo '<strong>🩺 Πάθηση:</strong> ' . esc_html( $condition ) . '<br>';
                if ( ! empty( $notes ) ) echo '<strong style="display:inline-block; margin-top:4px;">📝 Ιστορικό:</strong><br>' . nl2br( esc_html( $notes ) );
                echo '</div>';
            }
            break;

        case 'ptl_status':
            global $wpdb;
            $email = get_post_meta($post_id, 'ptl_lead_email', true );
            if ( ! $email ) {$title_parts = explode( ' - ', get_the_title( $post_id ) );$email = is_email( $title_parts[0] ) ?$title_parts[0] : '';
            }
            $promo_table =$wpdb->prefix . 'petling_partner_leads';
            if ( ! empty( $email ) &&$wpdb->get_var( "SHOW TABLES LIKE '$promo_table'" ) === $promo_table ) {$promo = $wpdb->get_row($wpdb->prepare( "SELECT status FROM $promo_table WHERE email = %s ORDER BY created_at DESC LIMIT 1", $email ) );
                if ( $promo ) {
                    if ( $promo->status === 'redeemed' ) { echo '<span style="display:inline-block; padding:4px 8px; background:#f0f0f0; color:#555; border-radius:4px; font-size:11px; border:1px solid #ccc;">✔️ Εξαργυρώθηκε</span>'; }
                    else { echo '<span style="display:inline-block; padding:4px 8px; background:#eef7ee; color:#5b9a68; border-radius:4px; font-size:11px; border:1px solid #5b9a68;">🟢 Ενεργό Κουπόνι</span>'; }
                } else { echo '<span style="color:#999;">-</span>'; }
            } else { echo '<span style="color:#999;">-</span>'; }
            break;
    }
}

function ptl_save_quiz_lead_data( $email, $name,$type, $result ) {$post_title = 'Lead #' . strtoupper( substr( md5( uniqid() ), 0, 6 ) );
    $post_id = wp_insert_post( array( 'post_title' =>$post_title, 'post_type' => 'ptl_quiz_lead', 'post_status' => 'publish' ) );
    update_post_meta( $post_id, 'ptl_lead_email', sanitize_email($email ) );
    update_post_meta( $post_id, 'ptl_lead_name', sanitize_text_field($name ) );
    update_post_meta( $post_id, 'ptl_lead_type', sanitize_text_field($type ) );
    update_post_meta( $post_id, 'ptl_lead_result', sanitize_text_field($result ) );
    return $post_id;
}

add_action( 'restrict_manage_posts', 'ptl_add_customer_filter_dropdown' );
function ptl_add_customer_filter_dropdown( $post_type ) {
    if ( $post_type !== 'ptl_quiz_lead' ) return;

    global $wpdb;
    $emails   =$wpdb->get_col( "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'ptl_lead_email' AND meta_value != '' ORDER BY meta_value ASC" );
    $selected = isset( $_GET['ptl_customer_email'] ) ? sanitize_email( $_GET['ptl_customer_email'] ) : '';

    echo '<select name="ptl_customer_email" id="ptl_customer_email">';
    echo '<option value="">Όλοι οι Πελάτες</option>';
    foreach ( $emails as$email ) {
        printf( '<option value="%s" %s>%s</option>', esc_attr( $email ), selected($selected, $email, false ), esc_html($email ) );
    }
    echo '</select>';
}

add_action( 'pre_get_posts', 'ptl_filter_leads_by_customer' );
function ptl_filter_leads_by_customer( $query ) {
    global $pagenow;
    if ( $pagenow === 'edit.php' && isset($_GET['post_type'] ) && $_GET['post_type'] === 'ptl_quiz_lead' && ! empty( $_GET['ptl_customer_email'] ) ) {
        $meta_query =$query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) )$meta_query = array();
        $meta_query[] = array( 'key' => 'ptl_lead_email', 'value' => sanitize_email($_GET['ptl_customer_email'] ), 'compare' => '=' );
        $query->set( 'meta_query',$meta_query );
    }
}