<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', 'petling_vet_pass_public_interceptor' );
function petling_vet_pass_public_interceptor() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/vet-pass/' ) !== false ) {
        
        $pet_id = isset( $_GET['pet'] ) ? sanitize_text_field( $_GET['pet'] ) : '';
        $time_t = isset( $_GET['t'] ) ? intval( $_GET['t'] ) : 0;
        $token  = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

        if ( time() - $time_t > 86400 ) { wp_die( 'Ο σύνδεσμος Vet Pass έχει λήξει (ισχύει για 24 ώρες). Παρακαλώ ζητήστε από τον κηδεμόνα να δημιουργήσει νέο QR Code.', 'Petling Vet Pass' ); }
        $expected_token = md5( $pet_id . $time_t . wp_salt() );
        if ( empty( $pet_id ) || empty( $token ) || $token !== $expected_token ) { wp_die( 'Άκυρος σύνδεσμος. Παρακαλώ σκανάρετε ξανά το QR Code.', 'Petling Vet Pass' ); }

        global $wpdb;
        $parasite_options = array('Εξωπαράσιτα (Ψύλλοι/Τσιμπούρια/Σκνίπες)', 'Ενδοπαράσιτα (Σκουλήκια εντέρου/καρδιάς)', 'Combo (Εσωτερικά & Εξωτερικά)');

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vet_action']) ) {
            if ( $_POST['vet_action'] === 'log_weight' ) {
                $new_weight = sanitize_text_field($_POST['weight']);
                $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
                $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
                if ($edit_id > 0) { $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'weight' => $new_weight, 'created_at' => $incident_date ), array('id' => $edit_id) ); } 
                else { $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'weight' => $new_weight, 'vet_comment' => 'Τακτική Ζύγιση (Ιατρός)', 'status' => 'verified', 'created_by' => 'vet', 'created_at' => $incident_date ), array( '%s', '%f', '%s', '%s', '%s', '%s' ) ); }
                
                $user_query = $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'petling_pets' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $pet_id ) . '%' );
                $u_results = $wpdb->get_results( $user_query );
                if ($u_results) {
                    foreach ($u_results as $ur) {
                        $p_array = maybe_unserialize($ur->meta_value); $updated = false;
                        foreach ($p_array as &$p_item) { if ($p_item['id'] === $pet_id) { $p_item['weight'] = $new_weight; $updated = true; break; } }
                        if ($updated) update_user_meta($ur->user_id, 'petling_pets', $p_array);
                    }
                }
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
            if ( $_POST['vet_action'] === 'add_vaccine' ) {
                $v_name = sanitize_text_field($_POST['vaccine_name']);
                $next_date = sanitize_text_field($_POST['next_vaccine_date']);
                $vet_name = sanitize_text_field($_POST['vet_name'] ?? '');
                $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
                if ($edit_id > 0) { $wpdb->update( $wpdb->prefix . 'petling_vaccines', array( 'vaccine_name' => $v_name, 'next_vaccine_date' => $next_date, 'vet_name' => $vet_name ), array('id' => $edit_id) ); } 
                else {
                    if (in_array($v_name, $parasite_options)) { $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s AND vaccine_name = %s", $pet_id, $v_name) ); }
                    $wpdb->insert( $wpdb->prefix . 'petling_vaccines', array( 'pet_unique_id' => $pet_id, 'vaccine_name' => $v_name, 'date_administered' => date('Y-m-d'), 'next_vaccine_date' => $next_date, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'vet' ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
                }
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
            if ( $_POST['vet_action'] === 'add_note' ) {
                $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
                $next_exam = sanitize_text_field($_POST['next_exam_date']);
                $vet_comment = sanitize_textarea_field($_POST['vet_comment']);
                $vet_name = sanitize_text_field($_POST['vet_name'] ?? '');
                $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
                if ($edit_id > 0) { $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'vet_comment' => $vet_comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'created_at' => $incident_date ), array('id' => $edit_id) ); } 
                else { $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $vet_comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'vet', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ); }
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
            if ( $_POST['vet_action'] === 'add_med' ) {
                $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
                $next_exam = sanitize_text_field($_POST['next_exam_date'] ?? '');
                $med_end_date = sanitize_text_field($_POST['med_end_date'] ?? '');
                $vet_name = sanitize_text_field($_POST['vet_name'] ?? '');
                $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
                $med_name = sanitize_text_field($_POST['med_name']);
                $med_condition = sanitize_text_field($_POST['med_condition']);
                $med_dosage = sanitize_textarea_field($_POST['med_dosage']);
                $comment = "[MED]\n**Φάρμακο:** " . $med_name . "\n**Πάθηση:** " . $med_condition;
                if (!empty($med_end_date)) { $comment .= "\n**Λήξη Αγωγής:** " . date('d/m/Y', strtotime($med_end_date)); }
                $comment .= "\n**Χορήγηση:**\n" . $med_dosage;
                if ($edit_id > 0) { $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'vet_comment' => $comment, 'vet_name' => $vet_name, 'next_exam_date' => $next_exam, 'created_at' => $incident_date ), array('id' => $edit_id) ); } 
                else { $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'vet', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ); }
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
            if ( $_POST['vet_action'] === 'add_blood' ) {
                $incident_date = !empty($_POST['incident_date']) ? sanitize_text_field($_POST['incident_date']) . ' ' . date('H:i:s', current_time('timestamp')) : current_time('mysql');
                $next_exam = sanitize_text_field($_POST['next_exam_date'] ?? '');
                $vet_name = sanitize_text_field($_POST['vet_name'] ?? '');
                $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
                $comment = "[BLOOD]";
                if (!empty($_POST['blood_name']) && is_array($_POST['blood_name'])) {
                    foreach ($_POST['blood_name'] as $idx => $b_name) {
                        $b_val = $_POST['blood_value'][$idx] ?? '';
                        $b_lim = $_POST['blood_limit'][$idx] ?? '';
                        if (!empty($b_name) && !empty($b_val)) {
                            $line = "\n- **" . sanitize_text_field($b_name) . ":** " . sanitize_text_field($b_val);
                            if (!empty($b_lim)) { $line .= " (Όριο: " . sanitize_text_field($b_lim) . ")"; }
                            $comment .= $line;
                        }
                    }
                }
                if ($edit_id > 0) { $wpdb->update( $wpdb->prefix . 'petling_vet_notes', array( 'vet_comment' => $comment, 'vet_name' => $vet_name, 'next_exam_date' => $next_exam, 'created_at' => $incident_date ), array('id' => $edit_id) ); } 
                else { $wpdb->insert( $wpdb->prefix . 'petling_vet_notes', array( 'pet_unique_id' => $pet_id, 'vet_comment' => $comment, 'next_exam_date'=> $next_exam, 'vet_name' => $vet_name, 'status' => 'verified', 'created_by' => 'vet', 'created_at' => $incident_date ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ); }
                wp_redirect( add_query_arg('saved', '1', remove_query_arg('saved', $_SERVER['REQUEST_URI'])) ); exit;
            }
        }

        /* ACTIONS: SKIP / DELETE */
        if ( isset( $_GET['action'] ) ) {
            if ( $_GET['action'] === 'skip_next' && isset( $_GET['item_id'] ) && isset( $_GET['type'] ) ) {
                $item_id = intval($_GET['item_id']);
                if ($_GET['type'] === 'vaccine' || $_GET['type'] === 'parasite') { $wpdb->update( $wpdb->prefix . 'petling_vaccines', ['next_vaccine_date' => ''], ['id' => $item_id] ); } 
                else { $wpdb->update( $wpdb->prefix . 'petling_vet_notes', ['next_exam_date' => ''], ['id' => $item_id] ); }
                $redirect = remove_query_arg( ['action', 'item_id', 'type', 'open_modal', 'saved'] );
                if (isset($_GET['open_modal'])) { $redirect = add_query_arg('open_modal', sanitize_text_field($_GET['open_modal']), $redirect); }
                wp_redirect( $redirect ); exit;
            }
            if ( $_GET['action'] === 'vet_del_vac' && isset( $_GET['vac_id'] ) ) {
                $wpdb->delete( $wpdb->prefix . 'petling_vaccines', array( 'id' => intval( $_GET['vac_id'] ) ) );
                wp_redirect( add_query_arg('saved', '1', remove_query_arg( array( 'action', 'vac_id', 'saved' ) )) ); exit;
            }
            if ( $_GET['action'] === 'vet_del_note' && isset( $_GET['note_id'] ) ) {
                $wpdb->delete( $wpdb->prefix . 'petling_vet_notes', array( 'id' => intval( $_GET['note_id'] ) ) );
                wp_redirect( add_query_arg('saved', '1', remove_query_arg( array( 'action', 'note_id', 'saved' ) )) ); exit;
            }
        }

        /* GET DATA & VET DATALIST */
        $query = $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'petling_pets' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $pet_id ) . '%' );
        $results = $wpdb->get_results( $query );
        $current_pet = null;
        $pet_owner_id = 0;
        if ( $results ) {
            foreach ( $results as $row ) {
                $pets_array = maybe_unserialize( $row->meta_value );
                if ( is_array( $pets_array ) ) {
                    foreach ( $pets_array as $p ) { if ( isset( $p['id'] ) && $p['id'] === $pet_id ) { $current_pet = $p; $pet_owner_id = $row->user_id; break 2; } }
                }
            }
        }
        if ( ! $current_pet ) wp_die( 'Το κατοικίδιο δεν βρέθηκε.', 'Petling Vet Pass' );

        $user_vets = get_user_meta($pet_owner_id, 'petling_vets', true);
        $all_records = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vaccines WHERE pet_unique_id = %s ORDER BY date_administered DESC", $pet_id ) );
        $vet_notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}petling_vet_notes WHERE pet_unique_id = %s ORDER BY created_at DESC", $pet_id ) );
        
        if ( $current_pet['type'] === 'dog' ) { $vaccine_options = array( 'Παρβοϊός / Μόρβα / Ηπατίτιδα (Core)', 'Λύσσα (Core)', 'Λεπτοσπείρωση (Core)', 'Βήχας Κυνοκομείου (Non-core)', 'Νόσος Lyme (Non-core)', 'Γρίπη Σκύλων (Non-core)', 'Άλλο' ); } 
        else { $vaccine_options = array( 'Τριπλό: FPV/FHV/FCV (Core)', 'Λύσσα (Core)', 'Λευχαιμία - FeLV (Core)', 'Χλαμυδίαση (Non-core)', 'Ανοσοανεπάρκεια - FIV (Non-core)', 'Άλλο' ); }

        $min_future_date = date('Y-m-d');
        $logo_id = get_theme_mod( 'custom_logo' ); $logo_img = wp_get_attachment_image_src( $logo_id, 'full' );
        $logo_html = $logo_img ? '<img src="'.esc_url($logo_img[0]).'" alt="Petling Logo" style="max-height: 55px; margin-bottom: 5px;">' : '<h1 style="margin:0; font-size:26px; color:#F3F0DF;">Petling</h1>';

        $timeline = []; $upcoming_actions = []; $chart_labels = []; $chart_data = [];
        $today_obj = new DateTime('today', wp_timezone()); $today_date_str = $today_obj->format('Y-m-d');

        foreach ($all_records as $rec) {
            $is_parasite = in_array($rec->vaccine_name, $parasite_options);
            $type = $is_parasite ? 'parasite' : 'vaccine';
            $timeline[] = [ 'type' => $type, 'icon' => ($is_parasite ? '🪱' : '💉'), 'date' => $rec->date_administered, 'title' => $rec->vaccine_name, 'desc' => $rec->vet_name ? 'Σκεύασμα/Κτηνίατρος: ' . $rec->vet_name : '', 'next' => $rec->next_vaccine_date, 'id' => $rec->id, 'delete_action' => 'del_vac', 'raw_comment' => '', 'raw_vet' => $rec->vet_name, 'raw_weight' => '' ];
            if (!empty($rec->next_vaccine_date) && $rec->next_vaccine_date !== '0000-00-00') {
                $next_obj = new DateTime($rec->next_vaccine_date, wp_timezone()); $diff = $today_obj->diff($next_obj)->days; $invert = $today_obj->diff($next_obj)->invert; 
                if ($invert && $diff <= 180) { $upcoming_actions[] = ['status' => 'red', 'msg' => 'Έληξε: ' . $rec->vaccine_name, 'type' => $type, 'id' => $rec->id]; } elseif (!$invert && $diff <= 30) { $upcoming_actions[] = ['status' => 'orange', 'msg' => 'Σε '.$diff.' μέρες: ' . $rec->vaccine_name, 'type' => $type, 'id' => $rec->id]; }
            }
        }

        $weights_for_chart = [];
        foreach ($vet_notes as $note) {
            if (!empty($note->weight)) {
                $timeline[] = [ 'type' => 'weight', 'icon' => '⚖️', 'date' => $note->created_at, 'title' => 'Ζύγιση: ' . $note->weight . ' kg', 'desc' => '', 'next' => '', 'id' => $note->id, 'delete_action' => 'del_note', 'raw_comment' => '', 'raw_vet' => '', 'raw_weight' => $note->weight ];
                $weights_for_chart[] = ['date' => $note->created_at, 'weight' => $note->weight];
            } else {
                $note_type = 'note'; $title = 'Ιατρική Σημείωση / Επίσκεψη';
                if (strpos($note->vet_comment, '[BLOOD]') !== false) {
                    $note_type = 'blood'; $title = 'Αιματολογικές / Εξετάσεις';
                    $html_desc = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', str_replace('[BLOOD]', '', $note->vet_comment));
                    $timeline[] = [ 'type' => 'blood', 'icon' => '🩸', 'date' => $note->created_at, 'title' => $title, 'desc' => nl2br(trim($html_desc)) . ($note->vet_name ? '<br><small style="color:#888; display:inline-block; margin-top:5px;">Κτηνίατρος: ' . esc_html($note->vet_name) . '</small>' : ''), 'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note', 'raw_comment' => $note->vet_comment, 'raw_vet' => $note->vet_name, 'raw_weight' => '' ];
                } elseif (strpos($note->vet_comment, '[MED]') !== false) {
                    $note_type = 'meds'; $title = 'Φαρμακευτική Αγωγή';
                    $desc = str_replace('[MED]', '', $note->vet_comment);
                    $html_desc = preg_replace_callback('/\*\*Λήξη Αγωγής:\*\* ([\d-]+)/', function($m) { return '**Λήξη Αγωγής:** ' . date('d/m/Y', strtotime($m[1])); }, $desc);
                    $html_desc = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html_desc);
                    $timeline[] = [ 'type' => 'meds', 'icon' => '💊', 'date' => $note->created_at, 'title' => $title, 'desc' => nl2br(trim($html_desc)) . ($note->vet_name ? '<br><small style="color:#888; display:inline-block; margin-top:5px;">Κτηνίατρος: ' . esc_html($note->vet_name) . '</small>' : ''), 'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note', 'raw_comment' => $note->vet_comment, 'raw_vet' => $note->vet_name, 'raw_weight' => '' ];
                } else {
                    $timeline[] = [ 'type' => 'note', 'icon' => '🩺', 'date' => $note->created_at, 'title' => $title, 'desc' => nl2br(esc_html($note->vet_comment)) . ($note->vet_name ? '<br><small style="color:#888;">Κτηνίατρος: ' . esc_html($note->vet_name) . '</small>' : ''), 'next' => $note->next_exam_date, 'id' => $note->id, 'delete_action' => 'del_note', 'raw_comment' => $note->vet_comment, 'raw_vet' => $note->vet_name, 'raw_weight' => '' ];
                }
                if (!empty($note->next_exam_date) && $note->next_exam_date !== '0000-00-00') {
                    $next_obj = new DateTime($note->next_exam_date, wp_timezone()); $diff = $today_obj->diff($next_obj)->days; $invert = $today_obj->diff($next_obj)->invert;
                    if ($invert && $diff <= 180) { $upcoming_actions[] = ['status' => 'red', 'msg' => 'Επανεξέταση / Λήξη που πέρασε!', 'type' => $note_type, 'id' => $note->id]; } elseif (!$invert && $diff <= 30) { $upcoming_actions[] = ['status' => 'orange', 'msg' => 'Επανεξέταση / Λήξη σε '.$diff.' μέρες', 'type' => $note_type, 'id' => $note->id]; }
                }
            }
        }

        usort($timeline, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
        usort($weights_for_chart, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });
        foreach ($weights_for_chart as $w) { $chart_labels[] = date('d/m/y', strtotime($w['date'])); $chart_data[] = $w['weight']; }

        $age_str = '';
        if ( !empty($current_pet['birthday']) ) {
            try { $bday = new DateTime($current_pet['birthday']); $diff = $bday->diff($today_obj);
                if ( $diff->y > 0 ) { $age_str = $diff->y . ' ετών'; } elseif ( $diff->m > 0 ) { $age_str = $diff->m . ' μηνών'; } else { $age_str = 'Νεογέννητο'; }
            } catch (Exception $e) { $age_str = ''; }
        }
        $neutered_text = '-';
        if ( isset($current_pet['neutered']) && $current_pet['neutered'] === 'yes' ) { $neutered_text = !empty($current_pet['neutered_date']) ? date('d/m/Y', strtotime($current_pet['neutered_date'])) : 'Ναι (Άγνωστη Ημ/νία)'; }
        ?>
        <!DOCTYPE html>
        <html lang="el">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Vet Pass | <?php echo esc_html($current_pet['name']); ?></title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                body { background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; color: #333; }
                .header { background: #43282F; color: #fff; padding: 25px 20px 15px; text-align: center; border-bottom: 5px solid #C7B297; }
                .header p { margin: 5px 0 0 0; color: #C7B297; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; }
                .info-bar { background: #eef7ee; color: #333; font-size: 13px; padding: 15px; text-align: center; border-bottom: 1px solid #5b9a68; }
                .container { padding: 15px; max-width: 600px; margin: 0 auto; padding-bottom: 120px; }
                .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
                .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #eee; }
                .info-row:last-child { border-bottom: none; }
                .info-label { font-weight: bold; color: #666; }
                
                .petling-dashboard-card { background: #fff; border: 1px solid #e2d4c0; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
                .dashboard-header { font-weight: bold; font-size: 16px; color: #43282F; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
                .dashboard-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed #f0f0f0; gap: 15px; flex-wrap: wrap; }
                .dashboard-item:last-child { border-bottom: none; padding-bottom: 0; }
                .dashboard-item-info { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px; }
                .dashboard-item-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
                .dashboard-item-dot.red { background: #e62121; box-shadow: 0 0 5px rgba(230,33,33,0.4); }
                .dashboard-item-dot.orange { background: #f59e0b; box-shadow: 0 0 5px rgba(245,158,11,0.4); }
                .dashboard-item-title { font-size: 14px; color: #333; font-weight: 500; line-height: 1.3; }
                .dashboard-item-actions { display: flex; gap: 8px; flex-shrink: 0; }
                .dash-action-btn { background: #fdfaf5; border: 1px solid #e2d4c0; padding: 6px 12px; border-radius: 6px; font-size: 12px; color: #43282F; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; font-weight: bold; }
                .dash-action-btn.done:hover { background: #e6f4ea; border-color: #5b9a68; color: #2e663a; }
                .dash-action-btn.edit:hover { background: #fff5e6; border-color: #f59e0b; color: #b47400; }
                .dash-action-btn.skip:hover { background: #fbe9e7; border-color: #e62121; color: #a11212; padding: 6px 8px; }
                
                .petling-filters { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 20px; }
                .petling-chip { background: #fdfdfd; border: 1px solid #ddd; padding: 8px 16px; border-radius: 20px; font-size: 14px; cursor: pointer; white-space: nowrap; color: #555; transition: all 0.2s; }
                .petling-chip.active { background: #43282F; color: #fff; border-color: #43282F; font-weight: bold; }
                
                .petling-timeline { position: relative; padding-left: 30px; margin-bottom: 40px; }
                .petling-timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e2d4c0; }
                .timeline-item { position: relative; margin-bottom: 25px; padding: 15px; background: #fff; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
                .timeline-icon { position: absolute; left: -35px; top: 15px; width: 30px; height: 30px; background: #fff; border: 2px solid #C7B297; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 2; }
                .timeline-date { font-size: 12px; color: #888; margin-bottom: 5px; font-weight: bold; }
                .timeline-title { font-size: 16px; color: #43282F; margin: 0 0 5px 0; }
                .timeline-desc { font-size: 14px; color: #555; margin: 0; line-height: 1.5; }
                .timeline-next { margin-top: 10px; font-size: 13px; color: #d63638; font-weight: bold; background: #fff5f5; display: inline-block; padding: 4px 8px; border-radius: 4px; }
                .timeline-next-past { margin-top: 10px; font-size: 13px; color: #888; font-weight: normal; background: #f9f9f9; border: 1px dashed #ccc; display: inline-block; padding: 4px 8px; border-radius: 4px; }
                .timeline-year-divider { background: #f3f4f6; color: #888; font-weight: bold; text-align: center; padding: 5px 15px; border-radius: 20px; display: inline-block; margin-bottom: 20px; border: 1px solid #ddd; position: relative; z-index: 2; margin-left: -5px; }
                
                .petling-fab { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background: #43282F; color: #fff; border-radius: 50%; font-size: 30px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer; z-index: 999; transition: transform 0.2s; border: none; }
                .petling-fab:hover { transform: scale(1.05); }
                .petling-fab-menu { position: fixed; bottom: 100px; right: 30px; display: flex; flex-direction: column; gap: 10px; z-index: 998; opacity: 0; pointer-events: none; transition: all 0.3s ease; transform: translateY(20px); }
                .petling-fab-menu.active { opacity: 1; pointer-events: auto; transform: translateY(0); }
                .fab-btn { background: #fff; border: 1px solid #ddd; padding: 12px 20px; border-radius: 30px; font-weight: bold; color: #43282F; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: pointer; text-align: right; }
                
                .petling-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; }
                .petling-modal { background: #fff; padding: 25px; border-radius: 12px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; position: relative; }
                .petling-modal-close { position: absolute; top: 15px; right: 15px; font-size: 24px; cursor: pointer; color: #999; line-height: 1; border: none; background: none; }
                .btn-brown { background: #C7B297; color: #43282F; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 15px; }
                
                .petling-toast-notification { position: fixed; top: 20px; right: 20px; background: #5b9a68; color: #fff; padding: 15px 25px; border-radius: 8px; font-weight: bold; font-size: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 999999; animation: slideInRight 0.4s ease-out forwards, fadeOut 0.4s ease-in 3.5s forwards; }
                @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
                @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; visibility: hidden; } }

                /* Φόρμες μέσα στα Modals (MOBILE CSS FIXES) */
                .petling-modal label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 14px; line-height: 1.2; }
                .petling-modal input, .petling-modal select, .petling-modal textarea { width: 100%; box-sizing: border-box; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; background-color: #fff; color: #333; font-family: inherit; -webkit-appearance: none; appearance: none; }
                .petling-modal input[type="date"] { min-height: 42px; }
            </style>
        </head>
        <body>
            <div class="petling-toast-notification" id="petling-toast" style="display: none;">✅ Η αλλαγή αποθηκεύτηκε επιτυχώς!</div>

            <!-- DATALIST ΚΤΗΝΙΑΤΡΩΝ ΓΙΑ ΤΑ MODALS (HTML5 Native Dropdown) -->
            <datalist id="saved_vets_list">
                <?php 
                if (is_array($user_vets)) {
                    foreach ($user_vets as $uv) {
                        if (!empty($uv['name'])) echo '<option value="' . esc_attr($uv['name']) . '">';
                    }
                }
                ?>
            </datalist>

            <div class="header">
                <?php echo $logo_html; ?>
                <p>Ψηφιακο Vet Pass</p>
            </div>
            <div class="info-bar">
                <strong>Καλώς ήρθατε!</strong> Μπορείτε να ενημερώσετε το ιστορικό απευθείας από εδώ (Ισχύς 24h).
            </div>

            <div class="container">
                <div class="card">
                    <div style="display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #43282F; font-size: 20px;">
                            <?php echo ($current_pet['type'] === 'dog' ? '🐶' : '🐱') . ' ' . esc_html($current_pet['name']); ?>
                            <?php if (!empty($age_str)) echo '<span style="font-size: 15px; color: #666; font-weight: normal; margin-left: 5px;">(' . $age_str . ')</span>'; ?>
                        </h3>
                    </div>
                    <div class="info-row"><span class="info-label">Στειρωμένο:</span> <span><?php echo $neutered_text; ?></span></div>
                    <div class="info-row"><span class="info-label">Φυλή:</span> <span><?php echo esc_html($current_pet['breed'] ?: '-'); ?></span></div>
                    <div class="info-row"><span class="info-label">Ημ. Γέννησης:</span> <span><?php echo esc_html($current_pet['birthday'] ? date('d/m/Y', strtotime($current_pet['birthday'])) : '-'); ?></span></div>
                    <?php if(!empty($current_pet['microchip'])): ?>
                        <div class="info-row"><span class="info-label">Microchip:</span> <span style="font-family: monospace; font-size:14px; font-weight:bold;"><?php echo esc_html($current_pet['microchip']); ?></span></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($upcoming_actions)) : ?>
                    <div class="petling-dashboard-card">
                        <div class="dashboard-header">📋 Πρέπει να γίνουν:</div>
                        <div class="dashboard-list">
                            <?php foreach ($upcoming_actions as $act) : 
                                $skip_url = add_query_arg( ['action' => 'skip_next', 'item_id' => $act['id'], 'type' => $act['type']] );
                                $done_modal = ($act['type'] === 'vaccine' || $act['type'] === 'parasite') ? 'modal-vaccine' : ($act['type'] === 'blood' ? 'modal-blood' : ($act['type'] === 'meds' ? 'modal-meds' : 'modal-note'));
                                $done_url = add_query_arg( 'open_modal', $done_modal, $skip_url );
                            ?>
                                <div class="dashboard-item">
                                    <div class="dashboard-item-info">
                                        <div class="dashboard-item-dot <?php echo esc_attr($act['status']); ?>"></div>
                                        <div class="dashboard-item-title"><?php echo esc_html($act['msg']); ?></div>
                                    </div>
                                    <div class="dashboard-item-actions">
                                        <a href="<?php echo esc_url($done_url); ?>" class="dash-action-btn done" title="Αποθήκευση νέας εγγραφής (Σβήνει το παλιό)">✔️ Έγινε</a>
                                        <button type="button" class="dash-action-btn edit" onclick="jQuery('.timeline-item[data-id=\'<?php echo esc_attr($act['id']); ?>\'] .edit-timeline-btn').click();" title="Αλλαγή ημερομηνίας">📅 Αλλαγή</button>
                                        <a href="<?php echo esc_url($skip_url); ?>" class="dash-action-btn skip" onclick="return confirm('Να σβηστεί η υπενθύμιση;');" title="Παράλειψη">✖️</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="petling-filters">
                    <div class="petling-chip active" data-filter="all">🐾 Όλα</div>
                    <div class="petling-chip" data-filter="weight">⚖️ Βάρος</div>
                    <div class="petling-chip" data-filter="vaccine">💉 Εμβόλια</div>
                    <div class="petling-chip" data-filter="parasite">🪱 Αποπαρασίτωση</div>
                    <div class="petling-chip" data-filter="meds">💊 Φάρμακα</div>
                    <div class="petling-chip" data-filter="blood">🩸 Εξετάσεις</div>
                    <div class="petling-chip" data-filter="note">🩺 Σημειώσεις</div>
                </div>

                <div id="weight-chart-container" style="display: none; background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 12px; margin-bottom: 25px;">
                    <canvas id="weightChart" height="200"></canvas>
                </div>

                <?php if (empty($timeline)): ?>
                    <p style="color:#888; font-style:italic; text-align:center; padding:30px;">Δεν υπάρχουν ακόμα εγγραφές. Πατήστε το <b>+</b> κάτω δεξιά για να ξεκινήσετε!</p>
                <?php else: ?>
                    <div class="petling-timeline">
                        <?php 
                        $current_year = '';
                        foreach ($timeline as $item): 
                            $item_year = date('Y', strtotime($item['date']));
                            if ($item_year !== $current_year) { $current_year = $item_year; echo '<div class="timeline-year-divider">' . $current_year . '</div>'; }
                        ?>
                            <div class="timeline-item" data-type="<?php echo esc_attr($item['type']); ?>" data-id="<?php echo esc_attr($item['id']); ?>" data-raw-date="<?php echo date('Y-m-d', strtotime($item['date'])); ?>" data-raw-next="<?php echo esc_attr($item['next']); ?>" data-vet="<?php echo esc_attr($item['raw_vet']); ?>" data-weight="<?php echo esc_attr($item['raw_weight']); ?>" data-title="<?php echo esc_attr($item['title']); ?>">
                                <div class="raw-comment" style="display:none;"><?php echo esc_html($item['raw_comment']); ?></div>
                                <div class="timeline-icon"><?php echo $item['icon']; ?></div>
                                <div class="timeline-date"><?php echo date('d/m/Y', strtotime($item['date'])); ?></div>
                                <h4 class="timeline-title"><?php echo esc_html($item['title']); ?></h4>
                                <?php if (!empty($item['desc'])): ?><p class="timeline-desc"><?php echo $item['desc']; ?></p><?php endif; ?>
                                
                                <?php if (!empty($item['next']) && $item['next'] !== '0000-00-00'): 
                                    $is_past = ($item['next'] < $today_date_str);
                                    if ($is_past): ?>
                                        <div class="timeline-next-past">📅 Είχε οριστεί: <?php echo date('d/m/Y', strtotime($item['next'])); ?></div>
                                    <?php else: ?>
                                        <div class="timeline-next">📅 Επόμενο: <?php echo date('d/m/Y', strtotime($item['next'])); ?></div>
                                    <?php endif; 
                                endif; ?>
                                
                                <div style="position:absolute; top:10px; right:15px; display:flex; gap:10px; align-items:center;">
                                    <a href="#" class="copy-timeline-btn" style="color:#43282F; text-decoration:none; font-size:16px;" title="Αντιγραφή">📋</a>
                                    <a href="#" class="edit-timeline-btn" style="color:#5b9a68; text-decoration:none; font-size:16px;" title="Επεξεργασία">✏️</a>
                                    <?php $del_url = add_query_arg( ['action' => 'vet_' . $item['delete_action'], $item['delete_action'] == 'del_weight' ? 'weight_id' : ($item['delete_action'] == 'del_vac' ? 'vac_id' : 'note_id') => $item['id']] ); ?>
                                    <a href="<?php echo esc_url($del_url); ?>" class="no-loader" onclick="return confirm('Είστε σίγουροι; Η εγγραφή θα διαγραφεί οριστικά.');" style="color:#e62121; text-decoration:none; font-size:20px; line-height:0.9;" title="Διαγραφή">&times;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- 6. FLOATING ACTION BUTTON (FAB) & MENU -->
            <div class="petling-fab-menu" id="fab-menu">
                <button class="fab-btn" onclick="openModal('modal-weight')">⚖️ Νέα Ζύγιση</button>
                <button class="fab-btn" onclick="openModal('modal-vaccine')">💉 Νέο Εμβόλιο / Αποπαρασίτωση</button>
                <button class="fab-btn" onclick="openModal('modal-meds')">💊 Νέο Φάρμακο / Αγωγή</button>
                <button class="fab-btn" onclick="openModal('modal-blood')">🩸 Νέες Αιματολογικές</button>
                <button class="fab-btn" onclick="openModal('modal-note')">🩺 Νέα Ιατρική Σημείωση</button>
            </div>
            <button class="petling-fab" id="main-fab">+</button>

            <!-- MODALS (VET PASS VERSION) -->
            <div class="petling-modal-overlay" id="modal-weight">
                <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
                    <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">⚖️ Προσθήκη Βάρους</h3>
                    <form method="post" action="">
                        <input type="hidden" name="vet_action" value="log_weight">
                        <input type="hidden" name="edit_id" value="">
                        <label>Ημερομηνία *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                        <label>Βάρος (kg) *</label><input type="number" step="0.01" min="0" name="weight" required>
                        <button type="submit" class="btn-brown" style="width:100%;">Αποθήκευση</button>
                    </form>
                </div>
            </div>

            <div class="petling-modal-overlay" id="modal-vaccine">
                <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
                    <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">💉 Εμβόλιο/Αποπαρασίτωση</h3>
                    <form method="post" action="">
                        <input type="hidden" name="vet_action" value="add_vaccine">
                        <input type="hidden" name="edit_id" value="">
                        <label>Τύπος / Εμβόλιο *</label>
                        <select name="vaccine_name" required>
                            <optgroup label="Αποπαρασίτωση"><?php foreach($parasite_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?></optgroup>
                            <optgroup label="Εμβολιασμός"><?php foreach($vaccine_options as $option) { echo '<option value="'.$option.'">'.$option.'</option>'; } ?></optgroup>
                        </select>
                        <label>Σκεύασμα / Κτηνίατρος (Προαιρετικό)</label>
                        <input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off">
                        <label style="color:#d63638;">Επόμενη Δόση 📅 *</label><input type="date" name="next_vaccine_date" min="<?php echo $min_future_date; ?>" required>
                        <button type="submit" class="btn-brown" style="width:100%;">Αποθήκευση</button>
                    </form>
                </div>
            </div>

            <div class="petling-modal-overlay" id="modal-meds">
                <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
                    <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">💊 Φαρμακευτική Αγωγή</h3>
                    <form method="post" action="">
                        <input type="hidden" name="vet_action" value="add_med">
                        <input type="hidden" name="edit_id" value="">
                        <div style="display:flex; gap:10px; margin-bottom:15px; width:100%; align-items:flex-start;">
                            <div style="flex:1; min-width:0;"><label>Έναρξη *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="margin-bottom:0;"></div>
                            <div style="flex:1; min-width:0;"><label>Λήξη Αγωγής</label><input type="date" name="med_end_date" style="margin-bottom:0;"></div>
                        </div>
                        <label>Κτηνίατρος (Προαιρετικό)</label>
                        <input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off">
                        <label>Φάρμακο / Ουσία *</label><input type="text" name="med_name" required placeholder="π.χ. Augmentin 500">
                        <label>Πάθηση / Αιτία *</label><input type="text" name="med_condition" required placeholder="π.χ. Ουρολοίμωξη">
                        <label>Οδηγίες Χορήγησης *</label><textarea name="med_dosage" required rows="2" placeholder="π.χ. 3 φορές τη μέρα με φαΐ"></textarea>
                        <label style="color:#d63638;">Επανεξέταση 📅 (Προαιρετικό)</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>">
                        <button type="submit" class="btn-brown" style="width:100%;">Αποθήκευση</button>
                    </form>
                </div>
            </div>

            <div class="petling-modal-overlay" id="modal-blood">
                <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
                    <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">🩸 Αιματολογικές (Δείκτες)</h3>
                    <form method="post" action="" id="blood-form">
                        <input type="hidden" name="vet_action" value="add_blood">
                        <input type="hidden" name="edit_id" value="">
                        <div style="display:flex; gap:10px; margin-bottom:15px; width:100%; align-items:flex-start;">
                            <div style="flex:1; min-width:0;"><label>Ημερομηνία Εξέτασης *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="margin-bottom:0;"></div>
                            <div style="flex:1; min-width:0;"><label>Εργαστήριο/Κτηνίατρος</label><input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off" style="margin-bottom:0;"></div>
                        </div>
                        <label>Δείκτες εκτός ορίων:</label>
                        <div style="display:flex; gap:10px; margin-bottom:5px; font-weight:bold; font-size:13px; color:#555; padding-right: 34px;">
                            <div style="flex:2.5;">Εξέταση</div><div style="flex:1;">Αποτέλεσμα</div><div style="flex:1;">Όρια</div>
                        </div>
                        <div id="blood-markers-container"></div>
                        <button type="button" id="add-blood-marker" style="margin-bottom:15px; margin-top:5px; background:none; border:1px dashed #C7B297; color:#43282F; padding:8px; width:100%; border-radius:6px; font-weight:bold; cursor:pointer;">+ Προσθήκη Δείκτη</button>
                        <label style="color:#d63638;">Επανεξέταση 📅 (Προαιρετικό)</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>">
                        <button type="submit" class="btn-brown" style="width:100%;">Αποθήκευση</button>
                    </form>
                </div>
            </div>

            <div class="petling-modal-overlay" id="modal-note">
                <div class="petling-modal"><button class="petling-modal-close" onclick="closeModals()">&times;</button>
                    <h3 style="margin-top:0; color:#43282F;" class="modal-title-dynamic">🩺 Ιατρική Σημείωση</h3>
                    <form method="post" action="">
                        <input type="hidden" name="vet_action" value="add_note">
                        <input type="hidden" name="edit_id" value="">
                        <label>Ημερομηνία Συμβάντος *</label><input type="date" name="incident_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                        <label>Κτηνίατρος (Προαιρετικό)</label>
                        <input type="text" name="vet_name" list="saved_vets_list" placeholder="Επιλέξτε ή πληκτρολογήστε..." autocomplete="off">
                        <label>Πάθηση, Αγωγή & Σχόλια *</label><textarea name="vet_comment" required rows="3"></textarea>
                        <label style="color:#d63638;">Επανεξέταση 📅 (Προαιρετικό)</label><input type="date" name="next_exam_date" min="<?php echo $min_future_date; ?>">
                        <button type="submit" class="btn-brown" style="width:100%;">Αποθήκευση</button>
                    </form>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('open_modal')) { openModal(urlParams.get('open_modal')); urlParams.delete('open_modal'); window.history.replaceState(null, '', window.location.pathname + '?' + urlParams.toString()); }
                if (urlParams.has('saved')) { $('#petling-toast').fadeIn(400).delay(3000).fadeOut(400); urlParams.delete('saved'); window.history.replaceState(null, '', window.location.pathname + '?' + urlParams.toString()); }

                $('#add-blood-marker').click(function(e) {
                    e.preventDefault();
                    $('#blood-markers-container').append('<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;"><input type="text" name="blood_name[]" placeholder="Εξέταση" required style="flex:2.5; margin-bottom:0;"><input type="text" name="blood_value[]" placeholder="Αποτέλεσμα" required style="flex:1; margin-bottom:0;"><input type="text" name="blood_limit[]" placeholder="Όρια" style="flex:1; margin-bottom:0;"><button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>');
                });
                $(document).on('click', '.remove-blood-btn', function(e) { e.preventDefault(); $(this).closest('.blood-marker-row').remove(); });
                $('#blood-form').on('submit', function(e) { if ($('.blood-marker-row').length === 0) { e.preventDefault(); alert('Παρακαλώ προσθέστε τουλάχιστον μία Εξέταση (Δείκτη).'); } });

                function parseGreekDate(str) {
                    var parts = str.split('/');
                    if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
                    return '';
                }

                // --- SMART EDIT BUTTON ---
                $('.edit-timeline-btn').click(function(e) {
                    e.preventDefault();
                    var $item = $(this).closest('.timeline-item');
                    var type = $item.data('type');
                    var modalId = '';
                    
                    if (type === 'weight') {
                        modalId = 'modal-weight'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Βάρους');
                        $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="weight"]').val($item.data('weight'));
                    } else if (type === 'vaccine' || type === 'parasite') {
                        modalId = 'modal-vaccine'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Υπενθύμισης');
                        $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' select[name="vaccine_name"]').val($item.data('title')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' input[name="next_vaccine_date"]').val($item.data('raw-next'));
                    } else if (type === 'note') {
                        modalId = 'modal-note'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Σημείωσης');
                        $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' textarea[name="vet_comment"]').val($item.find('.raw-comment').text()); $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next'));
                    } else if (type === 'meds') {
                        modalId = 'modal-meds'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Φαρμάκου');
                        $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                        var lines = $item.find('.raw-comment').text().split('\n'); var dosageLines = []; var isDosage = false;
                        lines.forEach(function(line) {
                            if (line.indexOf('**Φάρμακο:**') > -1) { $('#' + modalId + ' input[name="med_name"]').val(line.replace('**Φάρμακο:**', '').trim()); } 
                            else if (line.indexOf('**Πάθηση:**') > -1) { $('#' + modalId + ' input[name="med_condition"]').val(line.replace('**Πάθηση:**', '').trim()); } 
                            else if (line.indexOf('**Λήξη Αγωγής:**') > -1) { $('#' + modalId + ' input[name="med_end_date"]').val(parseGreekDate(line.replace('**Λήξη Αγωγής:**', '').trim())); }
                            else if (line.indexOf('**Χορήγηση:**') > -1) { isDosage = true; dosageLines.push(line.replace('**Χορήγηση:**', '').trim()); } 
                            else if (isDosage && line.trim() !== '' && line.indexOf('[MED]') === -1) { dosageLines.push(line.trim()); }
                        });
                        $('#' + modalId + ' textarea[name="med_dosage"]').val(dosageLines.join('\n').trim());
                    } else if (type === 'blood') {
                        modalId = 'modal-blood'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('✏️ Επεξεργασία Αιματολογικών');
                        $('#' + modalId + ' input[name="edit_id"]').val($item.data('id')); $('#' + modalId + ' input[name="incident_date"]').val($item.data('raw-date')); $('#' + modalId + ' input[name="next_exam_date"]').val($item.data('raw-next')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                        $('#blood-markers-container').empty(); var lines = $item.find('.raw-comment').text().split('\n'); var found = false;
                        lines.forEach(function(line) {
                            if (line.indexOf('- **') === 0) {
                                var match = line.match(/- \*\*(.*?):\*\* (.*?)(?: \(Όριο: (.*?)\))?$/);
                                if (match) { found = true; $('#blood-markers-container').append('<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;"><input type="text" name="blood_name[]" value="'+match[1]+'" required style="flex:2.5; margin-bottom:0;"><input type="text" name="blood_value[]" value="'+match[2]+'" required style="flex:1; margin-bottom:0;"><input type="text" name="blood_limit[]" value="'+(match[3] || '')+'" style="flex:1; margin-bottom:0;"><button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>'); }
                            }
                        });
                        if(!found) $('#add-blood-marker').click();
                    }
                    if (modalId) { document.getElementById('fab-menu').classList.remove('active'); document.getElementById('main-fab').style.transform = 'rotate(0)'; document.getElementById(modalId).style.display = 'flex'; }
                });

                // --- SMART COPY BUTTON ---
                $('.copy-timeline-btn').click(function(e) {
                    e.preventDefault();
                    var $item = $(this).closest('.timeline-item');
                    var type = $item.data('type');
                    var modalId = '';
                    if (type === 'weight') {
                        modalId = 'modal-weight'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Βάρους');
                        $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="weight"]').val($item.data('weight'));
                    } else if (type === 'vaccine' || type === 'parasite') {
                        modalId = 'modal-vaccine'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Υπενθύμισης');
                        $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' select[name="vaccine_name"]').val($item.data('title')); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' input[name="next_vaccine_date"]').val(''); 
                    } else if (type === 'note') {
                        modalId = 'modal-note'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Σημείωσης');
                        $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet')); $('#' + modalId + ' textarea[name="vet_comment"]').val($item.find('.raw-comment').text()); $('#' + modalId + ' input[name="next_exam_date"]').val(''); 
                    } else if (type === 'meds') {
                        modalId = 'modal-meds'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Φαρμάκου');
                        $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="med_end_date"]').val(''); $('#' + modalId + ' input[name="next_exam_date"]').val(''); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                        var lines = $item.find('.raw-comment').text().split('\n'); var dosageLines = []; var isDosage = false;
                        lines.forEach(function(line) {
                            if (line.indexOf('**Φάρμακο:**') > -1) { $('#' + modalId + ' input[name="med_name"]').val(line.replace('**Φάρμακο:**', '').trim()); } 
                            else if (line.indexOf('**Πάθηση:**') > -1) { $('#' + modalId + ' input[name="med_condition"]').val(line.replace('**Πάθηση:**', '').trim()); } 
                            else if (line.indexOf('**Λήξη Αγωγής:**') > -1) { $('#' + modalId + ' input[name="med_end_date"]').val(parseGreekDate(line.replace('**Λήξη Αγωγής:**', '').trim())); }
                            else if (line.indexOf('**Χορήγηση:**') > -1) { isDosage = true; dosageLines.push(line.replace('**Χορήγηση:**', '').trim()); } 
                            else if (isDosage && line.trim() !== '' && line.indexOf('[MED]') === -1) { dosageLines.push(line.trim()); }
                        });
                        $('#' + modalId + ' textarea[name="med_dosage"]').val(dosageLines.join('\n').trim());
                    } else if (type === 'blood') {
                        modalId = 'modal-blood'; $('#' + modalId + ' form')[0].reset(); $('#' + modalId + ' .modal-title-dynamic').text('📋 Αντιγραφή Αιματολογικών');
                        $('#' + modalId + ' input[name="edit_id"]').val(''); $('#' + modalId + ' input[name="incident_date"]').val(''); $('#' + modalId + ' input[name="next_exam_date"]').val(''); $('#' + modalId + ' input[name="vet_name"]').val($item.data('vet'));
                        $('#blood-markers-container').empty(); var lines = $item.find('.raw-comment').text().split('\n'); var found = false;
                        lines.forEach(function(line) {
                            if (line.indexOf('- **') === 0) {
                                var match = line.match(/- \*\*(.*?):\*\* (.*?)(?: \(Όριο: (.*?)\))?$/);
                                if (match) { found = true; $('#blood-markers-container').append('<div class="blood-marker-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;"><input type="text" name="blood_name[]" value="'+match[1]+'" required style="flex:2.5; margin-bottom:0;"><input type="text" name="blood_value[]" value="'+match[2]+'" required style="flex:1; margin-bottom:0;"><input type="text" name="blood_limit[]" value="'+(match[3] || '')+'" style="flex:1; margin-bottom:0;"><button type="button" class="remove-blood-btn" style="color:#e62121; background:none; border:none; font-size:24px; cursor:pointer; line-height:1; width:24px; padding:0; text-align:center;">&times;</button></div>'); }
                            }
                        });
                        if(!found) $('#add-blood-marker').click();
                    }
                    if (modalId) { document.getElementById('fab-menu').classList.remove('active'); document.getElementById('main-fab').style.transform = 'rotate(0)'; document.getElementById(modalId).style.display = 'flex'; }
                });

                // --- FILTER CHIPS LOGIC ---
                $('.petling-chip').click(function() {
                    $('.petling-chip').removeClass('active'); $(this).addClass('active'); var filter = $(this).data('filter');
                    if (filter === 'weight') { $('#weight-chart-container').fadeIn(); setTimeout(initChart, 100); } else { $('#weight-chart-container').hide(); }
                    if (filter === 'all') { $('.timeline-item').fadeIn(); $('.timeline-year-divider').show(); } else { $('.timeline-year-divider').hide(); $('.timeline-item').hide(); $('.timeline-item[data-type="' + filter + '"]').fadeIn(); }
                });

                var weightChart = null;
                function initChart() {
                    if (weightChart) return; 
                    var ctx = document.getElementById('weightChart').getContext('2d');
                    var labels = <?php echo json_encode($chart_labels); ?>;
                    var data = <?php echo json_encode($chart_data); ?>;
                    if(labels.length === 0) { $('#weight-chart-container').html('<p style="text-align:center; color:#888;">Δεν υπάρχουν αρκετά δεδομένα για το γράφημα.</p>'); return; }
                    weightChart = new Chart(ctx, { type: 'line', data: { labels: labels, datasets: [{ label: 'Βάρος (kg)', data: data, borderColor: '#43282F', backgroundColor: 'rgba(199, 178, 151, 0.3)', borderWidth: 2, tension: 0.3, fill: true, pointBackgroundColor: '#C7B297', pointRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false } } } });
                }
                $('#main-fab').click(function() { $('#fab-menu').toggleClass('active'); $(this).css('transform', $('#fab-menu').hasClass('active') ? 'rotate(45deg)' : 'rotate(0)'); });
            });

            function openModal(id) {
                document.getElementById('fab-menu').classList.remove('active'); document.getElementById('main-fab').style.transform = 'rotate(0)';
                var form = document.querySelector('#' + id + ' form');
                if (form) {
                    form.reset();
                    var editIdField = form.querySelector('input[name="edit_id"]'); if (editIdField) editIdField.value = '';
                    if (id === 'modal-weight') jQuery('#' + id + ' .modal-title-dynamic').text('⚖️ Προσθήκη Βάρους');
                    if (id === 'modal-vaccine') jQuery('#' + id + ' .modal-title-dynamic').text('💉 Νέα Υπενθύμιση (Εμβόλιο/Αποπαρασίτωση)');
                    if (id === 'modal-meds') jQuery('#' + id + ' .modal-title-dynamic').text('💊 Νέο Φάρμακο / Αγωγή');
                    if (id === 'modal-note') jQuery('#' + id + ' .modal-title-dynamic').text('🩺 Νέα Ιατρική Σημείωση');
                    if (id === 'modal-blood') { jQuery('#' + id + ' .modal-title-dynamic').text('🩸 Νέες Αιματολογικές (Δείκτες)'); jQuery('#blood-markers-container').empty(); jQuery('#add-blood-marker').click(); }
                }
                document.getElementById(id).style.display = 'flex';
            }
            function closeModals() { var modals = document.getElementsByClassName('petling-modal-overlay'); for (var i = 0; i < modals.length; i++) { modals[i].style.display = 'none'; } }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}