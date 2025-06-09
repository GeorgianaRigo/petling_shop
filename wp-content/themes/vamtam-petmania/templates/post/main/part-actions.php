<?php
/**
 * Post (in loop) actions - inner part
 *
 * @package vamtam/petmania
 */

if ( ! (
		( is_single() && current_user_can( 'edit_post', get_the_ID() ) ) ||
		comments_open() ||
		is_customize_preview()
	)
	)
	return;
?>

<div class="post-actions">
	<?php if ( ! post_password_required() ) :  ?>
		<?php get_template_part( 'templates/post/meta/comments' ); ?>

		<?php if ( ! is_single() ) : ?>
			<?php edit_post_link( '<span class="icon">' . vamtam_get_icon( 'pencil' ) . '</span><span class="visuallyhidden">' . esc_html__( 'Edit', 'vamtam-petmania' ) . '</span>' ) ?>
		<?php endif ?>
	<?php endif ?>
</div>


