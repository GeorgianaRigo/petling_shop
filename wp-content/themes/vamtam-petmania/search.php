<?php
/**
 * Search results template
 *
 * @package vamtam/petmania
 */

VamtamFramework::set( 'page_title', sprintf( esc_html__( 'Search Results for: %s', 'vamtam-petmania' ), '<span>' . get_search_query() . '</span>' ) );

get_header(); ?>

<div class="page-wrapper">
	<?php if ( have_posts() ) : the_post(); ?>
		<?php VamtamTemplates::$in_page_wrapper = true; ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( VamtamTemplates::get_layout() ); ?>>
			<div class="page-content clearfix">
				<?php
				rewind_posts();
				get_template_part( 'loop', 'category' );
				get_template_part( 'templates/share' );
				?>
			</div>
		</article>
		<?php get_template_part( 'sidebar' ) ?>
	<?php else : ?>
	<article id="vamtam-no-search-results">
		<?php VamtamEnqueues::enqueue_style_and_print( 'vamtam-not-found' ); ?>

		<h3><?php esc_html_e( 'Sorry, nothing found', 'vamtam-petmania' ) ?></h3>

		<div><?php esc_html_e( 'Maybe you should check your spelling...', 'vamtam-petmania' ) ?></div>

		<div class="page-404">
			<?php get_search_form(); ?>
		</div>
	</article>
	<?php endif ?>
</div>

<?php get_footer(); ?>
