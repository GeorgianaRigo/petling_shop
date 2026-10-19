<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Έλεγχος πρόσβασης (ελέγχει αν το email υπάρχει στη λίστα)
function petling_vet_history_has_access( $user_id ) {
    if ( ! $user_id ) return false;
    
    // Οι Διαχειριστές βλέπουν πάντα τη λειτουργία
    if ( current_user_can( 'manage_options' ) ) return true;

    $user = get_userdata( $user_id );
    if ( ! $user ) return false;

    // Παίρνουμε τα επιτρεπόμενα emails από τη ΒΔ
    $allowed_emails_raw = get_option( 'petling_vet_history_allowed_emails', '' );
    $allowed_emails = array_filter( array_map( 'trim', explode( "\n", $allowed_emails_raw ) ) );

    return in_array( strtolower($user->user_email), array_map('strtolower', $allowed_emails), true );
}

// 2. Προσθήκη Υπομενού στο Κεντρικό "Petling" Menu
add_action( 'admin_menu', 'petling_vet_history_admin_menu', 99 );
function petling_vet_history_admin_menu() {
    // Το 'petling-main' είναι το slug του Petling Core System
    add_submenu_page(
        'petling-main',
        'Ιστορικό & Vet Pass (Beta)',
        'Ιστορικό / Vet Pass',
        'manage_options',
        'petling-vet-history-access',
        'petling_vet_history_settings_page'
    );
}

// 3. Η σελίδα Ρυθμίσεων στο Admin
function petling_vet_history_settings_page() {
    if ( isset( $_POST['petling_vet_history_save'] ) && check_admin_referer( 'petling_vet_history_nonce' ) ) {
        $emails = sanitize_textarea_field( $_POST['allowed_emails'] );
        update_option( 'petling_vet_history_allowed_emails', $emails );
        echo '<div class="notice notice-success" style="padding:12px;"><strong>Αποθηκεύτηκε!</strong> Η λίστα πρόσβασης ενημερώθηκε.</div>';
    }
    $current_emails = get_option( 'petling_vet_history_allowed_emails', '' );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Διαχείριση Πρόσβασης - Ιστορικό Vet Pass (Beta)</h1>
        <hr class="wp-header-end">
        <div class="notice notice-info" style="padding: 15px; border-left-color: #C7B297; margin-top:20px;">
            <h3 style="margin-top: 0; color: #43282F;">🔒 Λειτουργία σε φάση Beta</h3>
            <p>Επειδή τα πεδία των κατοικιδίων είναι υπό δοκιμή, δεν είναι ορατά σε όλους. <strong>Οι Διαχειριστές τα βλέπουν πάντα.</strong> Αν θέλετε να δώσετε πρόσβαση σε συγκεκριμένους πελάτες, γράψτε τα Email τους παρακάτω (ένα ανά γραμμή).</p>
        </div>
        <form method="post" action="" style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:600px;">
            <?php wp_nonce_field( 'petling_vet_history_nonce' ); ?>
            <label for="allowed_emails" style="font-weight:bold; display:block; margin-bottom:10px;">Επιτρεπόμενα Emails Πελατών:</label>
            <textarea name="allowed_emails" id="allowed_emails" rows="10" style="width:100%; border:1px solid #ddd; font-family:monospace; padding:10px;" placeholder="pelatis1@example.com&#10;pelatis2@example.com"><?php echo esc_textarea( $current_emails ); ?></textarea>
            <p class="description">Ο χρήστης πρέπει να είναι εγγεγραμμένος στο e-shop με αυτό το email.</p>
            <p style="margin-top:20px;"><?php submit_button( 'Αποθήκευση Λίστας', 'primary', 'petling_vet_history_save', false ); ?></p>
        </form>
    </div>
    <?php
}