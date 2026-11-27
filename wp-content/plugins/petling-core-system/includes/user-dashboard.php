<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'woocommerce_account_menu_items', 'ptl_recipes_menu_item' );
function ptl_recipes_menu_item( $items ) {
    $allowed_emails = array( 'georgianarig@gmail.com', 'manolakouvet@gmail.com' );
    $current_user   = wp_get_current_user();
    if ( ! is_user_logged_in() || ! in_array( $current_user->user_email, $allowed_emails ) ) { return $items; }

    $new_items = array();
    foreach ( $items as $key => $value ) {
        $new_items[ $key ] = $value;
        if ( 'dashboard' === $key ) { $new_items['diatrofi-link'] = '👨‍🍳 Οι Συνταγές μου'; }
    }
    if ( ! array_key_exists( 'diatrofi-link', $new_items ) ) { $new_items['diatrofi-link'] = '👨‍🍳 Οι Συνταγές μου'; }
    return $new_items;
}

add_filter( 'woocommerce_get_endpoint_url', 'ptl_custom_recipes_link', 10, 4 );
function ptl_custom_recipes_link( $url, $endpoint, $value, $permalink ) {
    if ( $endpoint === 'diatrofi-link' ) { $url = home_url( '/diatrofi/' ); }
    return $url;
}

add_shortcode( 'petling_user_dashboard', 'ptl_custom_user_dashboard_shortcode' );
function ptl_custom_user_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div style="background:#fffaf1; padding:30px; text-align:center; border:2px dashed #C7B297; border-radius:12px;"><h3>🔒 Απαιτείται Σύνδεση</h3><p>Παρακαλώ συνδεθείτε για να δείτε το προσωπικό σας προφίλ.</p><a href="' . wp_login_url( get_permalink() ) . '" class="button" style="background:#43282F; color:#fff;">Σύνδεση</a></div>';
    }

    wp_enqueue_style( 'ptl-dashboard-css', PTL_CORE_URL . 'assets/css/dashboard.css', array(), PTL_CORE_VERSION );
    wp_enqueue_script( 'ptl-dashboard-js', PTL_CORE_URL . 'assets/js/dashboard.js', array(), PTL_CORE_VERSION, true );

    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID;
    $user_email   = $current_user->user_email;
    $logo_url     = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
    if ( empty( $logo_url ) ) { $logo_url = home_url( '/wp-content/uploads/logo.png' ); }

    $suf_p1 = " <span style='font-size:11px; color:#888;'>(βραστό, χωρίς πέτσα/κόκκαλα)</span>";
    $suf_c1 = " <span style='font-size:11px; color:#888;'>(μαγειρεμένο)</span>";
    $suf_c2 = " <span style='font-size:11px; color:#888;'>(βραστά, πουρέ, χωρίς φλούδα)</span>";
    $suf_veg = " <span style='font-size:11px; color:#888;'>(βραστά, πολτοποιημένα)</span>";
    $suf_fruit = " <span style='font-size:11px; color:#888;'>(τεμαχισμένο, χωρίς φλούδα/κουκούτσια)</span>";
    $suf_oil = " <span style='font-size:11px; color:#888;'>(στο τέλος)</span>";

    $pdf_suf_p1 = " <span style='font-size:12px; color:#666;'>(βραστό, χωρίς πέτσα/κόκκαλα)</span>";
    $pdf_suf_c1 = " <span style='font-size:12px; color:#666;'>(μαγειρεμένο)</span>";
    $pdf_suf_c2 = " <span style='font-size:12px; color:#666;'>(βραστά, πουρέ, χωρίς φλούδα)</span>";
    $pdf_suf_veg = " <span style='font-size:12px; color:#666;'>(βραστά, πολτοποιημένα)</span>";
    $pdf_suf_fruit = " <span style='font-size:12px; color:#666;'>(τεμαχισμένο, χωρίς φλούδα/κουκούτσια)</span>";
    $pdf_suf_oil = " <span style='font-size:12px; color:#666;'>(στο τέλος)</span>";

    ob_start();
    ?>
    <div class="ptl-dash-container">
        <div class="ptl-dash-menu">
            <button class="ptl-tab-btn active" onclick="ptlSwitchTab('nutrition', this)">🥣 Διατροφή</button>
            <a href="<?php echo esc_url( home_url( '/ti-mageireuw-simera/' ) ); ?>" class="ptl-menu-link">➕ Νέα Συνταγή</a>
            <button class="ptl-tab-btn" onclick="ptlSwitchTab('events', this)">🗓️ Σεμινάρια &amp; Events</button>
        </div>

        <div class="ptl-dash-content">
            <div id="tab-nutrition" class="ptl-tab-pane active">
                <div class="ptl-filter-bar">
                    <h3 style="margin:0; color:#43282F;">Οι Συνταγές μου</h3>
                    <div>
                        <select id="ptl-protein-filter" onchange="ptlFilterRecipes()">
                            <option value="all">Όλες οι Πρωτεΐνες</option>
                            <option value="Κοτόπουλο">Κοτόπουλο</option>
                            <option value="Γαλοπούλα">Γαλοπούλα</option>
                            <option value="Αρνί">Αρνί</option>
                            <option value="Μοσχάρι">Μοσχάρι</option>
                            <option value="Χοιρινό">Χοιρινό</option>
                            <option value="Κουνέλι">Κουνέλι</option>
                            <option value="Πάπια">Πάπια</option>
                            <option value="Πέρκα">Πέρκα</option>
                            <option value="Τσιπούρα">Τσιπούρα</option>
                            <option value="Λαβράκι">Λαβράκι</option>
                            <option value="Μπακαλιάρος">Μπακαλιάρος</option>
                            <option value="Σολομός">Σολομός</option>
                            <option value="Σαρδέλα">Σαρδέλα</option>
                        </select>
                    </div>
                </div>

                <div id="ptl-recipes-list">
                <?php
                $recipes = new WP_Query( array(
                    'post_type'      => 'ptl_quiz_lead',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'meta_query'     => array(
                        array( 'key' => 'ptl_user_id', 'value' => $user_id, 'compare' => '=' ),
                        array( 'key' => 'ptl_lead_type', 'value' => 'HOME_COOKED_RECIPE', 'compare' => '=' )
                    )
                ) );

                if ( $recipes->have_posts() ) {
                    $recipe_counter  = 1;
                    $printed_recipes = 0;
                    $stage_map = array( 'puppy' => 'Κουτάβι', 'adult' => 'Ενήλικο', 'senior' => 'Ηλικιωμένο' );
                    $neut_map  = array( 'yes' => 'Στειρωμένο', 'no' => 'Μη Στειρωμένο' );
                    $act_map   = array( 'low' => 'Χαμηλή Δραστηριότητα', 'medium' => 'Κανονική Δραστηριότητα', 'high' => 'Υψηλή Δραστηριότητα' );

                    while ( $recipes->have_posts() ) {
                        $recipes->the_post();
                        $post_id = get_the_ID();
                        if ( get_post_meta( $post_id, 'ptl_locked_recipe', true ) === 'yes' ) { continue; }

                        $recipe_data_json = get_post_meta( $post_id, 'ptl_recipe_data', true );
                        $result_text      = get_post_meta( $post_id, 'ptl_lead_result', true );
                        $recipe_date      = get_the_date( 'd/m/Y' );
                        $printed_recipes++;

                        if ( strpos( $result_text, 'VET' ) !== false ) {
                            continue;
                        }

                        if ( $recipe_data_json ) {
                            $printed_recipes++;
                            $data     = json_decode( $recipe_data_json, true );
                            $protein  = $data['p1'] ?? 'Ειδική';
                            $total_g  = isset( $data['total_g'] ) ? floatval( $data['total_g'] ) : 300;
                            $veg_str  = is_array( $data['veggies'] ) ? implode( ' & ', $data['veggies'] ) : ( $data['veggies'] ?? '' );
                            $p_name   = ! empty( $data['pet_name'] ) ? esc_html( $data['pet_name'] ) : 'τον σκύλο σας';
                            $p_weight = isset( $data['pet_weight'] ) ? floatval( $data['pet_weight'] ) : '-';
                            $p_stage  = $stage_map[ $data['pet_stage'] ?? '' ] ?? 'Ενήλικο';
                            $p_neut   = $neut_map[ $data['pet_neutered'] ?? '' ] ?? 'Μη Στειρωμένο';
                            $p_act    = $act_map[ $data['pet_activity'] ?? '' ] ?? 'Κανονική Δραστηριότητα';

                            $pet_profile_text = "{$p_stage}, {$p_neut} ({$p_weight} kg) - {$p_act}";
                            $title_text       = "Συνταγή για τον/την {$p_name} (με {$protein})";
                            $g_p1 = round( $total_g * 0.50 ); $g_p2 = round( $total_g * 0.05 ); $g_c1 = round( $total_g * 0.15 ); $g_c2 = round( $total_g * 0.10 ); $g_veg = round( $total_g * 0.13 ); $g_fruit = round( $total_g * 0.05 ); $g_oil = round( $total_g * 0.02 );

                            echo '<div class="ptl-recipe-card" data-protein="' . esc_attr( $protein ) . '">';
                            echo '<div class="ptl-recipe-header" onclick="ptlToggleCard(this)">';
                            echo '<span class="ptl-recipe-title" style="flex:1;">🥩 ' . esc_html( $title_text ) . '</span>';
                            echo '<div style="display:flex; align-items:center; gap:8px;"><span style="font-size:12px; color:#888;">' . $recipe_date . '</span><span class="ptl-recipe-arrow">▼</span></div>';
                            echo '</div><div class="ptl-recipe-body">';
                            echo '<p style="font-size:12px; color:#666; margin:0 0 15px 0; font-style:italic;">Εξατομικευμένη για: ' . esc_html( $pet_profile_text ) . '</p>';
                            echo '<p style="font-size:14px; font-weight:bold; color:#43282F; border-bottom:1px solid #e0d5c1; padding-bottom:5px;">Ημερήσια Ποσότητα: ' . $total_g . 'γρ.</p>';
                            echo '<ul style="list-style:none; padding:5px 0 10px 0; margin:0 0 15px 0; font-size:14px;">';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥩 <strong>' . $g_p1 . 'γρ</strong> ' . esc_html( $data['p1'] ) . $suf_p1 . '</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥚 <strong>' . $g_p2 . 'γρ</strong> ' . esc_html( $data['p2'] ) . '</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🌾 <strong>' . $g_c1 . 'γρ</strong> ' . esc_html( $data['c1'] ) . $suf_c1 . '</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥔 <strong>' . $g_c2 . 'γρ</strong> ' . esc_html( $data['c2'] ) . $suf_c2 . '</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🥦 <strong>' . $g_veg . 'γρ</strong> ' . esc_html( $veg_str ) . $suf_veg . '</li>';
                            echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">🍎 <strong>' . $g_fruit . 'γρ</strong> ' . esc_html( $data['fruit'] ) . $suf_fruit . '</li>';
                            echo '<li style="padding:4px 0;">🫒 <strong>' . $g_oil . 'γρ</strong> ' . esc_html( $data['oil'] ) . $suf_oil . '</li>';
                            echo '</ul>';
                            echo '<button class="button" style="background:#43282F; color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer;" onclick="ptlDownloadPDF(\'pdf-content-' . $recipe_counter . '\')">🖨️ Λήψη σε PDF / Εκτύπωση</button>';
                            echo '</div></div>';

                            echo '<div id="pdf-content-' . $recipe_counter . '" style="display:none;">';
                            echo '<div style="text-align:center; border-bottom:2px solid #C7B297; padding-bottom:15px; margin-bottom:20px;">';
                            echo '<img src="' . esc_url( $logo_url ) . '" style="display:block; margin:0 auto 10px auto; max-width:220px; height:auto;" alt="Petling Logo">';
                            echo '<h2 style="color:#43282F; margin:0; font-size:22px;">Σπιτική Συνταγή Σκύλου</h2>';
                            echo '<p style="color:#666; margin:5px 0 0 0; font-size:13px;">Εξατομικευμένο πρόγραμμα από το Petling</p></div>';
                            echo '<h3 style="color:#43282F; margin-top:0;">Προτεινόμενο Γεύμα για τον/την ' . esc_html( $p_name ) . ' (με ' . esc_html( $data['p1'] ) . ')</h3>';
                            echo '<p style="font-size:13px; color:#555; margin:0 0 10px 0; font-style:italic;">Προφίλ: ' . esc_html( $pet_profile_text ) . '</p>';
                            echo '<p style="font-size:15px; font-weight:bold; margin-bottom: 15px; padding-bottom:5px; border-bottom:1px solid #C7B297; display:inline-block;">Συνολική Ημερήσια Ποσότητα: ' . $total_g . ' γραμμάρια</p>';
                            echo '<ul style="list-style:none; padding:0; margin:0 0 30px 0; font-size:15px; color:#333;">';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥩 <strong>' . $g_p1 . 'γρ</strong> ' . esc_html( $data['p1'] ) . $pdf_suf_p1 . '</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥚 <strong>' . $g_p2 . 'γρ</strong> ' . esc_html( $data['p2'] ) . '</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🌾 <strong>' . $g_c1 . 'γρ</strong> ' . esc_html( $data['c1'] ) . $pdf_suf_c1 . '</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥔 <strong>' . $g_c2 . 'γρ</strong> ' . esc_html( $data['c2'] ) . $pdf_suf_c2 . '</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🥦 <strong>' . $g_veg . 'γρ</strong> ' . esc_html( $veg_str ) . $pdf_suf_veg . '</li>';
                            echo '<li style="padding:8px 0; border-bottom:1px solid #eee;">🍎 <strong>' . $g_fruit . 'γρ</strong> ' . esc_html( $data['fruit'] ) . $pdf_suf_fruit . '</li>';
                            echo '<li style="padding:8px 0;">🫒 <strong>' . $g_oil . 'γρ</strong> ' . esc_html( $data['oil'] ) . $pdf_suf_oil . '</li>';
                            echo '</ul>';
                            echo '<div style="padding:15px; background:#fffaf1; border-left:4px solid #d63638; border-radius:4px; margin-bottom: 30px;"><h4 style="margin-top:0; color:#d63638; font-size:15px;">⚠️ Σημαντική Επιστημονική Επισήμανση</h4><p style="font-size:12px; line-height:1.5; color:#555; margin:0;">Οι ποσότητες είναι ενδεικτικές και ενδέχεται να χρειαστούν προσαρμογή. Η συγκεκριμένη διατροφή <strong>δεν πρέπει να εφαρμόζεται εφ’ όρου ζωής χωρίς επίβλεψη</strong>.</p></div>';
                            echo '<div style="text-align:right;"><p style="margin:0; font-size:14px; color:#333;"><strong>Δρ. Σταυριάννα Μανωλάκου</strong></p><p style="margin:0; font-size:12px; color:#666;">Κτηνίατρος - Ειδική Διατροφολόγος</p></div></div>';
                            $recipe_counter++;
                        }
                    }
                    wp_reset_postdata();
                    if ( $printed_recipes === 0 ) { echo '<p>Δεν έχετε δημιουργήσει ακόμα κάποια συνταγή.</p>'; }
                } else {
                    echo '<p>Δεν έχετε δημιουργήσει ακόμα κάποια συνταγή.</p>';
                }
                ?>
                </div>
                <div class="ptl-pager" id="ptl-pager-controls"></div>
            </div>

            <div id="tab-events" class="ptl-tab-pane">
                <h3 style="margin-top:0; color:#43282F;">Σεμινάρια &amp; Αποκλειστικά Events</h3>
                <div class="ptl-event-card">
                    <h4 style="color:#C7B297; margin-bottom:5px;">Προσεχώς...</h4>
                    <p>Μείνετε συντονισμένοι! Ετοιμάζουμε αποκλειστικά Q&amp;A webinars και σεμινάρια διατροφής με την <strong>Δρ. Σταυριάννα Μανωλάκου</strong>.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}