<?php
/* Template Name: Shop Search */
get_header();
?>

<div class="elementor-container woocommerce"> 
    
    <div class="berocket-custom-filters">
        <?php
        // Εμφάνιση των φίλτρων αγνοώντας τις ρυθμίσεις του plugin που τα μπλοκάρουν
        echo do_shortcode('[br_filters_group group_id=5502 ignore_conditions=1]'); 
        ?>
    </div>

    <div class="shop-search-results">
    <?php
    // Αν υπάρχει search query
    if ( isset($_GET['s']) && !empty($_GET['s']) ) {
        $search_query = sanitize_text_field($_GET['s']);

        // WooCommerce query για προϊόντα
        $args = array(
            'post_type' => 'product',
            's' => $search_query,
            'posts_per_page' => 12, 
            'paged' => ( get_query_var('paged') ) ? get_query_var('paged') : 1,
            'berocket_custom_query' => true // Βοηθάει το plugin να "δει" το query
        );

        $loop = new WP_Query($args);

        if ($loop->have_posts()) {
            echo '<ul class="products">'; // Το BeRocket ψάχνει αυτή την κλάση
            while ($loop->have_posts()) : $loop->the_post();
                wc_get_template_part('content', 'product'); 
            endwhile;
            echo '</ul>';

            // Pagination
            echo '<nav class="woocommerce-pagination">';
            echo paginate_links( array(
                'total' => $loop->max_num_pages,
                'current' => max( 1, get_query_var('paged') )
            ) );
            echo '</nav>';

        } else {
            echo '<p class="woocommerce-info">Δεν βρέθηκαν προϊόντα με αυτόν τον όρο αναζήτησης.</p>';
        }

        wp_reset_postdata();
    } else {
        echo '<p>Πληκτρολογήστε κάτι για αναζήτηση.</p>';
    }
    ?>
    </div>
</div>

<?php get_footer(); ?>