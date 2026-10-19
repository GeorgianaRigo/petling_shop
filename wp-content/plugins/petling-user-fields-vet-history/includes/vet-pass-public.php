<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', 'petling_vet_pass_public_interceptor' );
function petling_vet_pass_public_interceptor() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/vet-pass/' ) !== false ) {
        
        $pet_id = isset( $_GET['pet'] ) ? sanitize_text_field( $_GET['pet'] ) : '';
        $time_t = isset( $_GET['t'] ) ? intval( $_GET['t'] ) : 0;
        $token  = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

        if ( time() - $time_t > 86400 ) {
            wp_die( 'Ο σύνδεσμος Vet Pass έχει λήξει (ισχύει για 24 ώρες). Παρακαλώ ζητήστε από τον κηδεμόνα να δημιουργήσει νέο QR Code.', 'Petling Vet Pass' );
        }

        $expected_token = md5( $pet_id . $time_t . wp_salt() );
        if ( empty( $pet_id ) || empty( $token ) || $token !== $expected_token ) {
            wp_die( 'Άκυρος σύνδεσμος. Παρακαλώ σκανάρετε ξανά το QR Code.', 'Petling Vet Pass' );
        }

        global $wpdb;

        $parasite_options = array(
            'Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)',
            'Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)',
            'Combo (Εσωτερικά & Εξωτερικά)',
            'Εξωπαράσιτα (Αμπούλα/Spray/Κολάρο)',
            'Εξωπαράσιτα (Μασώμενο Χάπι)',
            'Ενδοπαράσιτα (Χάπι για Σκουλήκια)'
        );

        /* POST ACTIONS (Από τον Ιατρό) */
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vet_action']) ) {
            
            if ( $_POST['vet_action'] === 'log_weight' ) {
                $new_weight = sanitize_text_field($_POST['weight']);
                $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'weight' => $new_weight, 'vet_comment' => 'Τακτική Ζύγιση (Ιατρός)', 'status' => 'verified', 'created_by' => 'vet' ), array( '%s', '%f', '%s', '%s', '%s' ) );
                
                $user_query = $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'petling_pets' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $pet_id ) . '%' );
                $u_results = $wpdb->get_results( $user_query );
                if ($u_results) {
                    foreach ($u_results as $ur) {
                        $p_array = maybe_unserialize($ur->meta_value);
                        $updated = false;
                        foreach ($p_array as &$p_item) {
                            if ($p_item['id'] === $pet_id) { $p_item['weight'] = $new_weight; $updated = true; break; }
                        }
                        if ($updated) update_user_meta($ur->user_id, 'petling_pets', $p_array);
                    }
                }
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }

            if ( $_POST['vet_action'] === 'verify_vaccine' || $_POST['vet_action'] === 'add_vaccine' ) {
                $v_name = sanitize_text_field($_POST['vaccine_name']);

                if (in_array($v_name, $parasite_options)) {
                    $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s", $pet_id, $v_name) );
                    $d_admin = date('Y-m-d');
                } else {
                    $d_admin = sanitize_text_field($_POST['date_administered']);
                }

                if ( $_POST['vet_action'] === 'verify_vaccine' ) {
                    $vac_id = intval($_POST['vac_id']);
                    $wpdb->update( $wpdb->prefix . 'petling_vaccines', array('status' => 'verified', 'vaccine_name' => $v_name, 'date_administered' => $d_admin, 'next_vaccine_date' => sanitize_text_field($_POST['next_vaccine_date']), 'vet_name' => sanitize_text_field($_POST['vet_name'])), array('id' => $vac_id) );
                } else {
                    $wpdb->insert( $wpdb->prefix . 'petling_vaccines', array( 'pet_unique_id' => $pet_id, 'vaccine_name' => $v_name, 'date_administered' => $d_admin, 'next_vaccine_date' => sanitize_text_field($_POST['next_vaccine_date']), 'vet_name' => sanitize_text_field($_POST['vet_name']), 'status' => 'verified', 'created_by' => 'vet' ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
                }
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
            
            if ( $_POST['vet_action'] === 'verify_note' ) {
                $note_id = intval($_POST['note_id']);
                $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array('status' => 'verified', 'vet_comment' => sanitize_textarea_field($_POST['vet_comment']), 'next_exam_date' => sanitize_text_field($_POST['next_exam_date']), 'vet_name' => sanitize_text_field($_POST['vet_name'])), array('id' => $note_id) );
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
            if ( $_POST['vet_action'] === 'add_note' ) {
                $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => sanitize_textarea_field($_POST['vet_comment']), 'next_exam_date'=> sanitize_text_field($_POST['next_exam_date']), 'vet_name' => sanitize_text_field($_POST['vet_name']), 'status' => 'verified', 'created_by' => 'vet' ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
        }

        if ( isset( $_GET['action'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( $_GET['action'] === 'vet_del_vac' && isset( $_GET['vac_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'vet_del_vac_' . $_GET['vac_id'] ) ) {
                $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ) ) );
                wp_redirect( add_query_arg('saved', '1', remove_query_arg( array( 'action', 'vac_id', '_wpnonce', 'saved' ) )) ); exit;
            }
            if ( $_GET['action'] === 'vet_del_note' && isset( $_GET['note_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'vet_del_note_' . $_GET['note_id'] ) ) {
                $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ) ) );
                wp_redirect( add_query_arg('saved', '1', remove_query_arg( array( 'action', 'note_id', '_wpnonce', 'saved' ) )) ); exit;
            }
        }

        /* GET DATA */
        $query = $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'petling_pets' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $pet_id ) . '%' );
        $results = $wpdb->get_results( $query );
        $current_pet = null;
        if ( $results ) {
            foreach ( $results as $row ) {
                $pets_array = maybe_unserialize( $row->meta_value );
                if ( is_array( $pets_array ) ) {
                    foreach ( $pets_array as $p ) { if ( isset( $p['id'] ) && $p['id'] === $pet_id ) { $current_pet = $p; break 2; } }
                }
            }
        }
        if ( ! $current_pet ) wp_die( 'Το κατοικίδιο δεν βρέθηκε.', 'Petling Vet Pass' );

        $one_year_ago = date('Y-m-d', strtotime('-1 year'));
        foreach ($parasite_options as $p_opt) {
            $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s AND date_administered < %s", $pet_id, $p_opt, $one_year_ago) );
        }

        $all_records = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s ORDER BY date_administered DESC", $pet_id ) );
        $vet_notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vet_notes WHERE pet_unique_id = %s ORDER BY created_at DESC", $pet_id ) );

        $vaccines = [];
        $parasites = [];
        foreach ($all_records as $rec) {
            if (in_array($rec->vaccine_name, $parasite_options)) { $parasites[] = $rec; } else { $vaccines[] = $rec; }
        }

        $pet_title = ($current_pet['type'] === 'dog') ? 'Σκυλόπαιδο 🐶' : 'Γατόπαιδο 🐱';
        if ( $current_pet['type'] === 'dog' ) { $vaccine_options = array( 'Παρβοϊός / Μόρβα / Ηπατίτιδα (Core)', 'Λύσσα (Core)', 'Λεπτοσπείρωση (Core)', 'Βήχας Κυνοκομείου (Non-core)', 'Νόσος Lyme (Non-core)', 'Γρίπη Σκύλων (Non-core)', 'Άλλο' ); } 
        else { $vaccine_options = array( 'Τριπλό: FPV/FHV/FCV (Core)', 'Λύσσα (Core)', 'Λευχαιμία - FeLV (Core)', 'Χλαμυδίαση (Non-core)', 'Ανοσοανεπάρκεια - FIV (Non-core)', 'Άλλο' ); }

        $min_future_date = date('Y-m-d');
        $logo_id = get_theme_mod( 'custom_logo' );
        $logo_img = wp_get_attachment_image_src( $logo_id, 'full' );
        $logo_html = $logo_img ? '<img src="'.esc_url($logo_img[0]).'" alt="Petling Logo" style="max-height: 55px; margin-bottom: 5px;">' : '<h1 style="margin:0; font-size:26px; color:#F3F0DF;">Petling</h1>';

        ?>
        <!DOCTYPE html>
        <html lang="el">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Vet Pass | <?php echo esc_html($current_pet['name']); ?></title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <style>
                body { background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; color: #333; }
                .header { background: #43282F; color: #fff; padding: 25px 20px 15px; text-align: center; border-bottom: 5px solid #C7B297; }
                .header p { margin: 5px 0 0 0; color: #C7B297; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; }
                .info-bar { background: #eef7ee; color: #333; font-size: 13px; padding: 15px; text-align: center; border-bottom: 1px solid #5b9a68; }
                .container { padding: 15px; max-width: 600px; margin: 0 auto; }
                .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
                .card h2 { margin-top: 0; font-size: 18px; color: #43282F; border-bottom: 2px solid #f3f4f6; padding-bottom: 10px; }
                .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #eee; }
                .info-row:last-child { border-bottom: none; }
                .info-label { font-weight: bold; color: #666; }
                details.vet-accordion { background: #fff; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; }
                details.vet-accordion summary { background: #fff; padding: 20px; font-size: 18px; font-weight: bold; color: #43282F; cursor: pointer; user-select: none; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid transparent; list-style: none; outline: none; }
                details.vet-accordion summary::-webkit-details-marker { display: none; }
                details.vet-accordion[open] summary { border-bottom: 2px solid #f3f4f6; }
                details.vet-accordion .accordion-content { padding: 20px; }
                .item-box { background: #fdfaf5; border: 1px solid #e2d4c0; padding: 12px; border-radius: 8px; margin-bottom: 10px; }
                .vet-form { background: #fdfdfd; border: 1px dashed #C7B297; padding: 15px; border-radius: 8px; margin-top: 15px; }
                .vet-form label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #555; }
                .vet-form input, .vet-form select, .vet-form textarea { width: 100%; box-sizing: border-box; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; }
                .vet-form button { width: 100%; padding: 14px; background: #5b9a68; color: white; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer; }
                .btn-verify { background: #5b9a68; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; width: 100%; margin-top: 10px; }
                .sub-accordion { font-weight: bold; color: #43282F; padding: 10px 0; cursor: pointer; outline: none; border-top: 1px dashed #ccc; margin-top: 10px; display:block; }
                .del-btn { color: #e62121; text-decoration: none; font-size: 14px; font-weight: bold; display: block; text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
                .weight-year-group { border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden; margin-bottom: 15px; }
                .weight-year-group h5 { margin: 0; padding: 10px 15px; background: #fdfaf5; border-bottom: 1px solid #e5e5e5; color: #8a6a43; font-size: 14px; }
                .weight-timeline { display:flex; gap:10px; overflow-x:auto; padding: 10px 15px; background: #fff; }
                .weight-card { background: #f9f9f9; border: 1px solid #e5e5e5; padding: 10px; border-radius: 6px; min-width: 80px; text-align: center; flex-shrink: 0; position: relative; }
                .w-del-corner { position: absolute; top: -14px; right: -7px; color: #e62121; font-size: 28px; text-decoration: none; line-height: 1; font-weight: bold; cursor: pointer; }
                .petling-toast-notification { position: fixed; top: 20px; right: 20px; background: #5b9a68; color: #fff; padding: 15px 25px; border-radius: 8px; font-weight: bold; font-size: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 999999; animation: slideInRight 0.4s ease-out forwards, fadeOut 0.4s ease-in 3.5s forwards; }
                @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
                @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; visibility: hidden; } }
            </style>
        </head>
        <body>
            <div class="petling-toast-notification" id="petling-toast" style="display: none;">✅ Η αλλαγή αποθηκεύτηκε επιτυχώς!</div>

            <div class="header">
                <?php echo $logo_html; ?>
                <p>Ψηφιακο Vet Pass</p>
            </div>
            <div class="info-bar">
                <strong>Καλώς ήρθατε Ιατρέ!</strong><br>Εδώ μπορείτε να επιβεβαιώσετε/διορθώσετε τα προσχέδια του πελάτη ή να δημιουργήσετε νέες εγγραφές (Ισχύς: 24h).
            </div>

            <div class="container">
                <div class="card">
                    <h2 style="border-bottom:none; margin-bottom:0; font-size: 20px;"><?php echo $pet_title; ?>: <?php echo esc_html($current_pet['name']); ?></h2>
                    <div style="margin-top: 15px;">
                        <div class="info-row"><span class="info-label">Φυλή:</span> <span><?php echo esc_html($current_pet['breed'] ?: '-'); ?></span></div>
                        <div class="info-row"><span class="info-label">Ημ. Γέννησης:</span> <span><?php echo esc_html($current_pet['birthday'] ? date('d/m/Y', strtotime($current_pet['birthday'])) : '-'); ?></span></div>
                        <div class="info-row"><span class="info-label">Microchip:</span> <span><?php echo esc_html($current_pet['microchip'] ?: '-'); ?></span></div>
                        <?php if ( !empty($current_pet['daily_food']) ) { ?>
                            <div class="info-row"><span class="info-label">Ημερήσια Τροφή:</span> <span><?php echo esc_html($current_pet['daily_food']); ?> γρ.</span></div>
                        <?php } ?>
                    </div>
                </div>

                <details class="vet-accordion" open>
                    <summary>📈 Ζύγιση & Ιστορικό Βάρους</summary>
                    <div class="accordion-content">
                        <form method="POST" action="" class="vet-form" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; background:#fdfaf5; margin-top: 0; margin-bottom: 20px;">
                            <input type="hidden" name="vet_action" value="log_weight">
                            <div style="flex:1; min-width:120px;">
                                <label style="display:block; margin-bottom:5px;">Τρέχον Βάρος (kg) *</label>
                                <input type="number" step="0.01" name="weight" required style="margin-bottom: 0; height: 44px;">
                            </div>
                            <button type="submit" style="background:#C7B297; color:#43282F; height:44px; width:auto; padding: 0 20px;">Ενημέρωση</button>
                        </form>

                        <?php 
                        $weight_history = [];
                        foreach ($vet_notes as $n) {
                            if (!empty($n->weight)) { 
                                $year = date('Y', strtotime($n->created_at));
                                if(!isset($weight_history[$year])) { $weight_history[$year] = []; }
                                $weight_history[$year][] = array('id' => $n->id, 'date' => $n->created_at, 'weight' => $n->weight); 
                            }
                        }
                        
                        if (!empty($weight_history)) {
                            $global_index = 0;
                            foreach($weight_history as $year => $weights) {
                                $year_display = ($global_index >= 5) ? 'display:none;' : '';
                                echo '<div class="weight-year-group" style="' . $year_display . '">';
                                echo '<h5>📅 Έτος ' . $year . '</h5><div class="weight-timeline">';
                                
                                foreach($weights as $wh) {
                                    $item_display = ($global_index >= 5) ? 'display:none;' : '';
                                    $delete_weight_url = wp_nonce_url( add_query_arg( array( 'action' => 'vet_del_note', 'note_id' => $wh['id'] ) ), 'vet_del_note_' . $wh['id'] );
                                    echo '<div class="weight-card" style="' . $item_display . '">';
                                    echo '<a href="' . esc_url($delete_weight_url) . '" class="w-del-corner" onclick="return confirm(\'Θέλετε σίγουρα να διαγράψετε αυτή τη ζύγιση;\');">&times;</a>';
                                    echo '<div style="font-size:11px; color:#666; margin-bottom:4px;">' . date('d/m', strtotime($wh['date'])) . '</div>';
                                    echo '<div style="font-weight:bold; color:#43282F; font-size:15px; margin-bottom: 0;">' . esc_html($wh['weight']) . ' <span style="font-size:11px;">kg</span></div>';
                                    echo '</div>';
                                    $global_index++;
                                }
                                echo '</div></div>';
                            }
                        } else { echo '<p style="color: #999; font-style: italic; font-size: 14px;">Κανένα ιστορικό βάρους.</p>'; }
                        ?>
                    </div>
                </details>

                <details class="vet-accordion">
                    <summary>💉 Εμβολιασμοί (WSAVA)</summary>
                    <div class="accordion-content">
                        <?php if ( $vaccines ) { foreach ( $vaccines as $vac ) { ?>
                            <div class="item-box" style="<?php if($vac->status==='verified') echo 'border-left:4px solid #5b9a68;'; else echo 'border-left:4px solid #e6a23c;'; ?>">
                                <?php if ( $vac->status === 'verified' ) { ?>
                                    <div><strong><?php echo esc_html($vac->vaccine_name); ?></strong></div>
                                    <div style="font-size: 13px; color: #666; margin-top: 5px;">
                                        Ημ/νία: <?php echo date('d/m/Y', strtotime($vac->date_administered)); ?> | Ιατρός: <?php echo esc_html($vac->vet_name); ?><br>
                                        <?php if ( !empty($vac->next_vaccine_date) && $vac->next_vaccine_date !== '0000-00-00' ) {
                                            echo '<span style="color:#d63638; font-weight:bold;">Επόμενο: ' . date('d/m/Y', strtotime($vac->next_vaccine_date)) . '</span>';
                                        } ?>
                                    </div>
                                <?php } else { ?>
                                    <div style="color:#e6a23c; font-weight:bold; margin-bottom:10px;">⏳ Προσχέδιο Πελάτη:</div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="vet_action" value="verify_vaccine">
                                        <input type="hidden" name="vac_id" value="<?php echo $vac->id; ?>">
                                        <label>Εμβόλιο *</label>
                                        <select name="vaccine_name">
                                            <?php 
                                            if(!in_array($vac->vaccine_name, $vaccine_options)) { $vaccine_options[] = $vac->vaccine_name; }
                                            foreach($vaccine_options as $option) { echo '<option value="' . esc_attr($option) . '" ' . selected($vac->vaccine_name, $option, false) . '>' . esc_html($option) . '</option>'; } 
                                            ?>
                                        </select>
                                        <label>Ημερομηνία *</label><input type="date" name="date_administered" value="<?php echo esc_attr($vac->date_administered); ?>" required>
                                        <label>Επόμενο Εμβόλιο</label><input type="date" name="next_vaccine_date" value="<?php echo esc_attr($vac->next_vaccine_date); ?>" min="<?php echo $min_future_date; ?>">
                                        <label>Ιατρός / Κλινική *</label><input type="text" name="vet_name" value="<?php echo esc_attr($vac->vet_name); ?>" required>
                                        <button type="submit" class="btn-verify">Επιβεβαίωση & Κλείδωμα</button>
                                        <?php $del_url = wp_nonce_url(add_query_arg(array('action'=>'vet_del_vac','vac_id'=>$vac->id)), 'vet_del_vac_'.$vac->id); ?>
                                        <a href="<?php echo esc_url($del_url); ?>" class="del-btn" onclick="return confirm('Θέλετε σίγουρα να διαγράψετε αυτό το εμβόλιο;');">🗑️ Διαγραφή Εγγραφής</a>
                                    </form>
                                <?php } ?>
                            </div>
                        <?php } } else { echo '<p style="color: #999; font-style: italic;">Κανένα εμβόλιο δεν βρέθηκε.</p>'; } ?>
                        <details><summary class="sub-accordion">+ Νέος Εμβολιασμός (Από Ιατρό)</summary>
                            <form class="vet-form" method="POST" action="">
                                <input type="hidden" name="vet_action" value="add_vaccine">
                                <label>Εμβόλιο *</label><select name="vaccine_name" required><?php foreach($vaccine_options as $option) { echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>'; } ?></select>
                                <label>Όνομα Ιατρού / Κλινικής *</label><input type="text" name="vet_name" required>
                                <label>Ημερομηνία *</label><input type="date" name="date_administered" required value="<?php echo date('Y-m-d'); ?>">
                                <label>Επόμενο Εμβόλιο 📅</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>">
                                <button type="submit">Καταχώρηση (Κλείδωμα)</button>
                            </form>
                        </details>
                    </div>
                </details>

                <details class="vet-accordion">
                    <summary>🪱 Τρέχουσα Αποπαρασίτωση</summary>
                    <div class="accordion-content">
                        <?php if ( $parasites ) { foreach ( $parasites as $vac ) { ?>
                            <div class="item-box" style="<?php if($vac->status==='verified') echo 'border-left:4px solid #5b9a68;'; else echo 'border-left:4px solid #e6a23c;'; ?>">
                                <?php if ( $vac->status === 'verified' ) { ?>
                                    <div><strong><?php echo esc_html($vac->vaccine_name); ?></strong></div>
                                    <div style="font-size: 13px; color: #666; margin-top: 5px;">
                                        Σκεύασμα: <strong><?php echo esc_html($vac->vet_name ?: '-'); ?></strong><br>
                                        <?php if ( !empty($vac->next_vaccine_date) && $vac->next_vaccine_date !== '0000-00-00' ) {
                                            echo '<span style="color:#d63638; font-weight:bold; display:block; margin-top:5px;">Επόμενη Δόση: ' . date('d/m/Y', strtotime($vac->next_vaccine_date)) . '</span>';
                                        } ?>
                                    </div>
                                <?php } else { ?>
                                    <div style="color:#e6a23c; font-weight:bold; margin-bottom:10px;">⏳ Προσχέδιο Πελάτη:</div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="vet_action" value="verify_vaccine">
                                        <input type="hidden" name="vac_id" value="<?php echo $vac->id; ?>">
                                        <label>Τύπος Παρασίτων *</label>
                                        <select name="vaccine_name">
                                            <?php 
                                            $all_parasite_opts = array('Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)', 'Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)', 'Combo (Εσωτερικά & Εξωτερικά)');
                                            if(!in_array($vac->vaccine_name, $all_parasite_opts)) { $all_parasite_opts[] = $vac->vaccine_name; }
                                            foreach($all_parasite_opts as $option) { echo '<option value="' . esc_attr($option) . '" ' . selected($vac->vaccine_name, $option, false) . '>' . esc_html($option) . '</option>'; } 
                                            ?>
                                        </select>
                                        <label>Σκεύασμα</label><input type="text" name="vet_name" value="<?php echo esc_attr($vac->vet_name); ?>">
                                        <label>Επόμενη Δόση 📅 *</label><input type="date" name="next_vaccine_date" value="<?php echo esc_attr($vac->next_vaccine_date); ?>" min="<?php echo $min_future_date; ?>" required>
                                        <button type="submit" class="btn-verify">Επιβεβαίωση & Κλείδωμα</button>
                                        <?php $del_url = wp_nonce_url(add_query_arg(array('action'=>'vet_del_vac','vac_id'=>$vac->id)), 'vet_del_vac_'.$vac->id); ?>
                                        <a href="<?php echo esc_url($del_url); ?>" class="del-btn" onclick="return confirm('Θέλετε σίγουρα να διαγράψετε αυτή την προστασία;');">🗑️ Διαγραφή Εγγραφής</a>
                                    </form>
                                <?php } ?>
                            </div>
                        <?php } } else { echo '<p style="color: #999; font-style: italic;">Καμία καταγραφή αποπαρασίτωσης.</p>'; } ?>
                        <details><summary class="sub-accordion">+ Νέα Αποπαρασίτωση</summary>
                            <form class="vet-form" method="POST" action="">
                                <input type="hidden" name="vet_action" value="add_vaccine">
                                <label>Τύπος *</label>
                                <select name="vaccine_name" required>
                                    <option value="Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)">Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)</option>
                                    <option value="Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)">Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)</option>
                                    <option value="Combo (Εσωτερικά & Εξωτερικά)">Combo (Εσωτερικά & Εξωτερικά)</option>
                                </select>
                                <label>Σκεύασμα</label><input type="text" name="vet_name">
                                <label>Επόμενη Δόση 📅 *</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>" required>
                                <button type="submit">Καταχώρηση</button>
                            </form>
                        </details>
                    </div>
                </details>

                <details class="vet-accordion">
                    <summary>🩺 Ιατρικές Σημειώσεις</summary>
                    <div class="accordion-content">
                        <?php 
                        $found_notes = false;
                        if ( $vet_notes ) {
                            foreach ( $vet_notes as $note ) {
                                if (empty($note->vet_comment) || strpos($note->vet_comment, 'Τακτική Ζύγιση') !== false) continue;
                                $found_notes = true;
                        ?>
                            <div class="item-box" style="<?php if($note->status==='verified') echo 'border-left:4px solid #5b9a68;'; else echo 'border-left:4px solid #e6a23c;'; ?>">
                                <?php if ( $note->status === 'verified' ) { ?>
                                    <div style="font-size: 13px; color: #666; margin-bottom: 8px;"><?php echo date('d/m/Y', strtotime($note->created_at)); ?> | <strong><?php echo esc_html($note->vet_name); ?></strong></div>
                                    <div style="font-size: 14px;"><?php echo nl2br(esc_html($note->vet_comment)); ?></div>
                                    <?php if ( !empty($note->next_exam_date) && $note->next_exam_date !== '0000-00-00' ) {
                                        echo '<div style="margin-top: 8px; color:#d63638; font-size: 13px; font-weight:bold;">Επανεξέταση: ' . date('d/m/Y', strtotime($note->next_exam_date)) . '</div>';
                                    } ?>
                                <?php } else { ?>
                                    <div style="color:#e6a23c; font-weight:bold; margin-bottom:10px;">⏳ Προσχέδιο Πελάτη:</div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="vet_action" value="verify_note">
                                        <input type="hidden" name="note_id" value="<?php echo $note->id; ?>">
                                        <label>Σχόλιο / Αγωγή *</label>
                                        <textarea name="vet_comment" required rows="3"><?php echo esc_textarea($note->vet_comment); ?></textarea>
                                        <label>Επανεξέταση</label><input type="date" name="next_exam_date" value="<?php echo esc_attr($note->next_exam_date); ?>" min="<?php echo $min_future_date; ?>">
                                        <label>Ιατρός / Κλινική *</label><input type="text" name="vet_name" value="<?php echo esc_attr($note->vet_name); ?>" required>
                                        <button type="submit" class="btn-verify">Ενημέρωση & Κλείδωμα</button>
                                        <?php $del_url = wp_nonce_url(add_query_arg(array('action'=>'vet_del_note','note_id'=>$note->id)), 'vet_del_note_'.$note->id); ?>
                                        <a href="<?php echo esc_url($del_url); ?>" class="del-btn" onclick="return confirm('Θέλετε σίγουρα να διαγράψετε αυτή τη σημείωση;');">🗑️ Διαγραφή Εγγραφής</a>
                                    </form>
                                <?php } ?>
                            </div>
                        <?php } } 
                        if (!$found_notes) echo '<p style="color: #999; font-style: italic;">Καμία σημείωση δεν βρέθηκε.</p>';
                        ?>
                        <details><summary class="sub-accordion">+ Νέα Διάγνωση / Σημείωση</summary>
                            <form class="vet-form" method="POST" action="">
                                <input type="hidden" name="vet_action" value="add_note">
                                <label>Όνομα Ιατρού / Κλινικής *</label><input type="text" name="vet_name" required>
                                <label>Ιατρικό Σχόλιο / Αγωγή *</label><textarea name="vet_comment" required rows="3"></textarea>
                                <label>Επανεξέταση 📅</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>">
                                <button type="submit">Καταχώρηση</button>
                            </form>
                        </details>
                    </div>
                </details>
            </div>
            
            <script>
                // Καθαρισμός URL
                if (window.history.replaceState) {
                    const url = new URL(window.location.href);
                    if (url.searchParams.has('saved')) {
                        $('#petling-toast').fadeIn(400).delay(3000).fadeOut(400);
                        url.searchParams.delete('saved');
                        window.history.replaceState({path: url.href}, '', url.href);
                    }
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}