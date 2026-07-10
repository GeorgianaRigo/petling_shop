<?php
/*
 * Το μενού στο wp-admin, με 3 tabs:
 *   - Ρυθμίσεις & Οδηγίες  (changelog, οδηγίες shortcode, πίνακας συνεργατών)
 *   - Εγγεγραμμένοι Πελάτες (λίστα από leads/κωδικούς, με φίλτρο συνεργάτη/κατάστασης)
 *   - Δημιουργία QR Code    (όπως πριν, αμετάβλητο)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'petling_promo_admin_menu' );
function petling_promo_admin_menu() {
    add_menu_page( 'Ρυθμίσεις Petling Promo', 'Petling Promo', 'manage_options', 'petling-partners-promo', 'petling_promo_settings_page', 'dashicons-pets', 56 );
}

add_action( 'admin_init', 'petling_promo_handle_admin_actions' );
function petling_promo_handle_admin_actions() {
    if ( ! isset( $_GET['page'] ) || 'petling-partners-promo' !== $_GET['page'] ) {
        return;
    }

    // Διαγραφή μιας εγγραφής από το tab "Εγγεγραμμένοι Πελάτες"
    if ( isset( $_GET['delete_lead'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_lead_' . $_GET['delete_lead'] ) ) {
        global $wpdb;
        $table = $wpdb->prefix . 'petling_partner_leads';
        $wpdb->delete( $table, array( 'id' => intval( $_GET['delete_lead'] ) ) );
        wp_redirect( admin_url( 'admin.php?page=petling-partners-promo&tab=leads' ) );
        exit;
    }

    // Αποθήκευση πίνακα συνεργατών (tab "Ρυθμίσεις")
    if ( isset( $_POST['petling_promo_save_partners'] ) && isset( $_POST['petling_promo_partners_nonce'] )
        && wp_verify_nonce( $_POST['petling_promo_partners_nonce'], 'petling_promo_save_partners_action' ) ) {

        $prefixes  = isset( $_POST['p_prefix'] ) ? (array) $_POST['p_prefix'] : array();
        $new_partners = array();

        foreach ( $prefixes as $i => $prefix ) {
            $prefix = strtoupper( sanitize_text_field( $prefix ) );
            if ( '' === $prefix ) {
                continue; // κενή γραμμή -> αγνοείται
            }
            if ( isset( $_POST['p_delete'][ $i ] ) ) {
                continue; // μαρκαρισμένη για διαγραφή
            }

            $existing_password = sanitize_text_field( $_POST['p_password'][ $i ] ?? '' );

            $new_partners[] = array(
                'prefix'        => $prefix,
                'label'         => sanitize_text_field( $_POST['p_label'][ $i ] ?? $prefix ),
                'type'          => in_array( $_POST['p_type'][ $i ] ?? '', array( 'shop', 'appointment' ), true ) ? $_POST['p_type'][ $i ] : 'shop',
                'discount_type' => in_array( $_POST['p_discount_type'][ $i ] ?? '', array( 'fixed', 'percent' ), true ) ? $_POST['p_discount_type'][ $i ] : 'fixed',
                'amount'        => floatval( $_POST['p_amount'][ $i ] ?? 0 ),
                'min_order'     => floatval( $_POST['p_min_order'][ $i ] ?? 0 ),
                'lock_days'     => intval( $_POST['p_lock_days'][ $i ] ?? 20 ),
                'password'      => $existing_password,
            );
        }

        update_option( 'petling_promo_partners', $new_partners );
        wp_redirect( admin_url( 'admin.php?page=petling-partners-promo&tab=settings&saved=1' ) );
        exit;
    }
}

function petling_promo_settings_page() {
    global $wpdb;
    $table      = $wpdb->prefix . 'petling_partner_leads';
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
    ?>
    <div class="wrap">
        <h2>🐾 Petling Promo - Διαχείριση Συνεργατών</h2>

        <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="?page=petling-partners-promo&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">Ρυθμίσεις &amp; Οδηγίες</a>
            <a href="?page=petling-partners-promo&tab=leads" class="nav-tab <?php echo 'leads' === $active_tab ? 'nav-tab-active' : ''; ?>">Εγγεγραμμένοι Πελάτες</a>
            <a href="?page=petling-partners-promo&tab=qrcode" class="nav-tab <?php echo 'qrcode' === $active_tab ? 'nav-tab-active' : ''; ?>">Δημιουργία QR Code</a>
        </h2>

        <?php if ( 'settings' === $active_tab ) : petling_promo_render_settings_tab(); ?>
        <?php elseif ( 'leads' === $active_tab ) : petling_promo_render_leads_tab( $wpdb, $table ); ?>
        <?php elseif ( 'qrcode' === $active_tab ) : petling_promo_render_qrcode_tab(); ?>
        <?php endif; ?>
    </div>
    <?php
}

// =========================================================================
// TAB 1: Ρυθμίσεις & Οδηγίες
// =========================================================================
function petling_promo_render_settings_tab() {
    $partners = petling_promo_get_partners();
    ?>
    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success" style="padding:12px;"><strong>Αποθηκεύτηκε!</strong> Οι ρυθμίσεις συνεργατών ενημερώθηκαν.</div>
    <?php endif; ?>

    <div class="notice notice-info" style="padding: 15px; margin-top: 15px; margin-bottom: 20px; border-left-color: #5b9a68;">
        <h3 style="margin-top: 0; color: #5b9a68;">📌 Τι κάνει αυτό το plugin (ιστορικό ενημερώσεων)</h3>
        <p><strong>v1.0 - 1.4:</strong> Ένα κοινό ποσό έκπτωσης για όλους τους συνεργάτες. Κάθε φόρμα δημιουργούσε πάντα πραγματικό κουπόνι WooCommerce. Μία μόνο ενεργή εγγραφή ανά email συνολικά.</p>
        <p><strong>v2.0 (τρέχουσα):</strong></p>
        <ul style="list-style-type: disc; margin-left: 25px;">
            <li>Ρυθμίσεις <strong>ξεχωριστές ανά συνεργάτη</strong> (ποσό, τύπος, ελάχιστη παραγγελία, μέρες αναμονής, password) - πίνακας παρακάτω.</li>
            <li>Νέος τύπος συνεργάτη <strong>"Ραντεβού"</strong> (π.χ. κτηνίατρος): δεν δημιουργεί κουπόνι WooCommerce (δεν ξοδεύεται στο site μας) - μόνο κωδικό-απόδειξη για έκπτωση σε δική του υπηρεσία.</li>
            <li>Νέο shortcode <code>[petling_partner_redeem prefix="VET"]</code>: ο ίδιος ο συνεργάτης ελέγχει/επιβεβαιώνει τον κωδικό του πελάτη στο ραντεβού, ώστε να μην ξαναχρησιμοποιηθεί.</li>
            <li>Η βάση κρατάει πλέον <strong>ιστορικό</strong> (πολλές εγγραφές ανά email), με κατάσταση Ενεργός/Χρησιμοποιημένος.</li>
        </ul>
    </div>

    <div class="notice notice-info" style="padding: 15px; margin-bottom: 25px; border-left-color: #C7B297;">
        <h3 style="margin-top: 0;">💡 Οδηγίες Χρήσης (Shortcodes στο Elementor)</h3>
        <p>Πρόσθεσε το widget <strong>"Σορτκόντ (Shortcode)"</strong> στο Elementor:</p>
        <ul style="list-style-type: disc; margin-left: 25px; margin-top: 10px;">
            <li style="margin-bottom: 5px;"><strong>Φόρμα για τον πελάτη</strong> (σελίδα συνεργάτη, π.χ. Dogs by Joy): <code>[petling_partner_promo prefix="JOY"]</code></li>
            <li><strong>Σελίδα ελέγχου/εξαργύρωσης</strong> (μόνο για συνεργάτες τύπου "Ραντεβού", π.χ. δική της ιδιωτική σελίδα που θα δίνεις στην κτηνίατρο): <code>[petling_partner_redeem prefix="VET"]</code> ή <code>[petling_vet_dashboard prefix="VET"]</code></li>
        </ul>
        <p style="color:#a55;">Η σελίδα εξαργύρωσης δεν χρειάζεται να είναι δημόσια συνδεδεμένη πουθενά στο μενού - φτιάξε μια κρυφή σελίδα (χωρίς link από αλλού) και στείλε το link απευθείας στον συνεργάτη.</p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'petling_promo_save_partners_action', 'petling_promo_partners_nonce' ); ?>
        <h3>Συνεργάτες</h3>
        <table class="widefat striped" style="background:#fff;">
            <thead>
                <tr>
                    <th>Prefix</th>
                    <th>Ετικέτα (όνομα)</th>
                    <th>Τύπος</th>
                    <th>Είδος έκπτωσης</th>
                    <th>Ποσό</th>
                    <th>Ελάχ. παραγγελία (€)</th>
                    <th>Μέρες αναμονής</th>
                    <th>Password (μόνο για "Ραντεβού")</th>
                    <th>Διαγραφή</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = $partners;
                $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' );
                $rows[] = array( 'prefix' => '', 'label' => '', 'type' => 'shop', 'discount_type' => 'fixed', 'amount' => '', 'min_order' => '', 'lock_days' => 20, 'password' => '' );

                foreach ( $rows as $i => $p ) :
                    ?>
                    <tr>
                        <td><input type="text" name="p_prefix[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['prefix'] ); ?>" style="width:80px;" placeholder="π.χ. VET"></td>
                        <td><input type="text" name="p_label[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['label'] ); ?>" style="width:180px;" placeholder="Εμφανιζόμενο όνομα"></td>
                        <td>
                            <select name="p_type[<?php echo $i; ?>]">
                                <option value="shop" <?php selected( $p['type'], 'shop' ); ?>>Shop (κουπόνι στο site)</option>
                                <option value="appointment" <?php selected( $p['type'], 'appointment' ); ?>>Ραντεβού (εξωτερική υπηρεσία)</option>
                            </select>
                        </td>
                        <td>
                            <select name="p_discount_type[<?php echo $i; ?>]">
                                <option value="fixed" <?php selected( $p['discount_type'], 'fixed' ); ?>>Σταθερό ποσό (€)</option>
                                <option value="percent" <?php selected( $p['discount_type'], 'percent' ); ?>>Ποσοστό (%)</option>
                            </select>
                        </td>
                        <td><input type="number" step="0.5" name="p_amount[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['amount'] ); ?>" style="width:70px;"></td>
                        <td><input type="number" step="1" name="p_min_order[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['min_order'] ); ?>" style="width:70px;"></td>
                        <td><input type="number" step="1" name="p_lock_days[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['lock_days'] ); ?>" style="width:70px;"></td>
                        <td><input type="text" name="p_password[<?php echo $i; ?>]" value="<?php echo esc_attr( $p['password'] ); ?>" style="width:110px;" placeholder="π.χ. manolakou24"></td>
                        <td style="text-align:center;"><input type="checkbox" name="p_delete[<?php echo $i; ?>]" value="1"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description" style="margin-top:8px;">Άφησε το Prefix κενό σε μια γραμμή για να την αγνοήσει. Μπορείς να προσθέσεις μέχρι 2 νέους συνεργάτες κάθε φορά που αποθηκεύεις - αν χρειαστείς παραπάνω, αποθήκευσε και ξαναμπές στη σελίδα για νέες κενές γραμμές.</p>
        <p class="description">Το Password χρησιμοποιείται μόνο από συνεργάτες τύπου "Ραντεβού" στη σελίδα <code>[petling_partner_redeem]</code> - δώσ' το στον συνεργάτη μαζί με το link της σελίδας του.</p>
        <?php submit_button( 'Αποθήκευση Συνεργατών', 'primary', 'petling_promo_save_partners' ); ?>
    </form>
    <?php
}

// =========================================================================
// TAB 2: Εγγεγραμμένοι Πελάτες (Με Φίλτρα)
// =========================================================================
function petling_promo_render_leads_tab( $wpdb, $table ) {
    $partners_by_prefix = array();
    foreach ( petling_promo_get_partners() as $p ) {
        $partners_by_prefix[ strtoupper( $p['prefix'] ) ] = $p['label'];
    }
    
    // Λήψη παραμέτρων φίλτρων από το URL
    $filter_prefix = isset($_GET['filter_prefix']) ? sanitize_text_field($_GET['filter_prefix']) : '';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $order = (isset($_GET['order']) && $_GET['order'] === 'asc') ? 'ASC' : 'DESC';
    $new_order = ($order === 'DESC') ? 'asc' : 'desc';

    // Χτίσιμο του Query δυναμικά
    $query = "SELECT * FROM $table WHERE 1=1";
    if ( !empty($filter_prefix) ) {
        $query .= $wpdb->prepare(" AND partner_prefix = %s", $filter_prefix);
    }
    if ( !empty($filter_status) ) {
        $query .= $wpdb->prepare(" AND status = %s", $filter_status);
    }
    $query .= " ORDER BY created_at $order";

    $leads = $wpdb->get_results( $query );
    ?>
    
    <!-- Φόρμα Φίλτρων -->
    <form method="GET" style="margin: 20px 0; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
        <input type="hidden" name="page" value="petling-partners-promo">
        <input type="hidden" name="tab" value="leads">
        
        <div>
            <label style="font-weight:bold; margin-right:5px;">Συνεργάτης:</label>
            <select name="filter_prefix">
                <option value="">Όλοι οι συνεργάτες</option>
                <?php foreach ($partners_by_prefix as $prefix => $label): ?>
                    <option value="<?php echo esc_attr($prefix); ?>" <?php selected($filter_prefix, $prefix); ?>><?php echo esc_html($label); ?> (<?php echo esc_html($prefix); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-weight:bold; margin-right:5px;">Κατάσταση:</label>
            <select name="filter_status">
                <option value="">Όλες οι καταστάσεις</option>
                <option value="active" <?php selected($filter_status, 'active'); ?>>Ενεργός</option>
                <option value="redeemed" <?php selected($filter_status, 'redeemed'); ?>>Χρησιμοποιήθηκε</option>
            </select>
        </div>

        <input type="submit" class="button button-primary" value="Φιλτράρισμα">
        <a href="?page=petling-partners-promo&tab=leads" class="button">Καθαρισμός</a>
    </form>

    <!-- Πίνακας Δεδομένων -->
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; margin-top:10px;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Email Πελάτη</th>
                    <th>Συνεργάτης</th>
                    <th>Τύπος</th>
                    <th>Κωδικός</th>
                    <th>Κατάσταση</th>
                    <th>
                        <a href="?page=petling-partners-promo&tab=leads&filter_prefix=<?php echo esc_attr($filter_prefix); ?>&filter_status=<?php echo esc_attr($filter_status); ?>&order=<?php echo $new_order; ?>" style="color:#2c3338; text-decoration:none;">
                            Ημ/νία Λήψης <span class="sorting-indicator <?php echo $order === 'DESC' ? 'desc' : 'asc'; ?>"></span>
                        </a>
                    </th>
                    <th>Ημ/νία Εξαργύρωσης</th>
                    <th>Ενέργειες</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ( $leads ) {
                    foreach ( $leads as $lead ) {
                        // Διατηρούμε τις παραμέτρους του φίλτρου στο URL διαγραφής
                        $del_args = array(
                            'page' => 'petling-partners-promo',
                            'tab' => 'leads',
                            'delete_lead' => $lead->id,
                            'filter_prefix' => $filter_prefix,
                            'filter_status' => $filter_status,
                            'order' => strtolower($order)
                        );
                        $del_url      = wp_nonce_url( add_query_arg( $del_args, admin_url( "admin.php" ) ), 'delete_lead_' . $lead->id );
                        $partner_name = $partners_by_prefix[ strtoupper( $lead->partner_prefix ) ] ?? $lead->partner_prefix;
                        $is_redeemed  = 'redeemed' === $lead->status;
                        $status_badge = $is_redeemed
                            ? '<span style="background:#eef2f2;color:#555;padding:2px 8px;border-radius:10px;">Χρησιμοποιήθηκε</span>'
                            : '<span style="background:#eef7ee;color:#5b9a68;padding:2px 8px;border-radius:10px; font-weight:bold;">Ενεργός</span>';
                        
                        echo '<tr>';
                        echo '<td>' . esc_html( $lead->id ) . '</td>';
                        echo '<td><strong>' . esc_html( $lead->email ) . '</strong></td>';
                        echo '<td>' . esc_html( $partner_name ) . '</td>';
                        echo '<td>' . ( 'appointment' === $lead->type ? 'Ραντεβού' : 'Shop' ) . '</td>';
                        echo '<td><code style="background:#eef7ee; color:#5b9a68; padding:3px 6px;">' . esc_html( $lead->coupon_code ) . '</code></td>';
                        echo '<td>' . $status_badge . '</td>';
                        echo '<td>' . esc_html( date( 'd/m/Y H:i', strtotime( $lead->created_at ) ) ) . '</td>';
                        echo '<td>' . ( $lead->redeemed_at ? esc_html( date( 'd/m/Y H:i', strtotime( $lead->redeemed_at ) ) ) : '—' ) . '</td>';
                        echo '<td><a href="' . esc_url( $del_url ) . '" onclick="return confirm(\'Σίγουρα θέλετε να διαγράψετε αυτή την εγγραφή;\');" style="color:#d63638; text-decoration:none;">🗑️ Διαγραφή</a></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="9" style="padding:20px; text-align:center;">Δεν βρέθηκαν εγγραφές με αυτά τα κριτήρια.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// =========================================================================
// TAB 3: Δημιουργία QR Code (αμετάβλητο από πριν)
// =========================================================================
function petling_promo_render_qrcode_tab() {
    ?>
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:25px; max-width: 600px; margin-top:20px;">
        <h3 style="margin-top:0; color:#43282F;">📱 Γεννήτρια QR Code για Συνεργάτες</h3>
        <p style="color:#555; font-size:14px; margin-bottom:20px;">Δημιουργήστε εύκολα και γρήγορα το QR Code που θα τυπώσετε για το φυσικό κατάστημα του συνεργάτη (π.χ. σε stand ή κάρτες). Όταν ο πελάτης το σκανάρει, θα ανοίγει κατευθείαν η σελίδα για να πάρει την έκπτωσή του!</p>

        <table class="form-table">
            <tr>
                <th scope="row">Όνομα Συνεργάτη<br><small style="color:#999; font-weight:normal;">(Μόνο για το όνομα του αρχείου)</small></th>
                <td><input type="text" id="qr_partner_name" class="regular-text" placeholder="π.χ. Joy"></td>
            </tr>
            <tr>
                <th scope="row">URL Σελίδας<br><small style="color:#999; font-weight:normal;">(Η σελίδα που φτιάξατε στο site)</small></th>
                <td><input type="url" id="qr_partner_url" class="regular-text" placeholder="https://petling.gr/joy"></td>
            </tr>
        </table>

        <p style="margin-top:20px;">
            <button type="button" class="button button-primary" style="background:#5b9a68; border-color:#4a8255;" onclick="generatePetlingQRCode()">Δημιουργία QR Code</button>
        </p>

        <div id="qr_result_area" style="display:none; margin-top: 30px; text-align: center; border-top: 1px dashed #ccc; padding-top: 25px;">
            <canvas id="qr_canvas" style="border: 1px solid #eee; padding: 10px; border-radius: 8px; background: #fff;"></canvas>
            <br><br>
            <a id="qr_download_btn" class="button button-secondary" download="qr-code.png">⬇️ Λήψη σε μορφή PNG</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <script>
    function generatePetlingQRCode() {
        var url = document.getElementById('qr_partner_url').value;
        var name = document.getElementById('qr_partner_name').value;

        if (!url) {
            alert('Παρακαλώ εισάγετε το URL της σελίδας!');
            return;
        }

        var qr = new QRious({
            element: document.getElementById('qr_canvas'),
            value: url,
            size: 300,
            level: 'H'
        });

        document.getElementById('qr_result_area').style.display = 'block';

        var canvas = document.getElementById('qr_canvas');
        var dataURL = canvas.toDataURL('image/png');
        var dlBtn = document.getElementById('qr_download_btn');

        dlBtn.href = dataURL;
        var safeName = name ? name.toLowerCase().replace(/[^a-z0-9]/g, '-') : 'partner';
        dlBtn.download = 'qr-petling-' + safeName + '.png';
    }
    </script>
    <?php
}