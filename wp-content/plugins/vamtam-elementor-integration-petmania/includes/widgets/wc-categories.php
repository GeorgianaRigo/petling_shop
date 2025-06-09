<?php
namespace VamtamElementor\Widgets\ProductsCategories;

// Extending the Products Categories widget.

// Is WC Widget.
if ( ! vamtam_has_woocommerce() ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme Settings.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'wc-categories' ) ) {
	return;
}


function update_controls_style_tab_products_section( $controls_manager, $widget ) {
	// Image Spacing.
	\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, 'image_spacing', [
		'selectors' => [
			'{{WRAPPER}}' => '--vamtam-img-spacing: {{SIZE}}{{UNIT}}',
		]
	] );

	// Image Border Radius.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'image_border_radius', [
		'selectors' => [
			'{{WRAPPER}}' => '--vamtam-img-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		]
	] );
}
// Style - Products section.
function section_products_style_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_controls_style_tab_products_section( $controls_manager, $widget );
}
add_action( 'elementor/element/wc-categories/section_products_style/before_section_end', __NAMESPACE__ . '\section_products_style_before_section_end', 10, 2 );

// Product Categories, before render_content.
function product_categories_before_render_content( $widget ) {
    $widget_name = $widget->get_name();
    if ( $widget->get_name() === 'global' ) {
        $widget_name = $widget->get_original_element_instance()->get_name();
    }

	if ( $widget_name === 'wc-categories' ) {
		do_action( 'vamtam_before_products_cat_widget_before_render_content', $widget_name );
	}
}
add_action( 'elementor/widget/before_render_content', __NAMESPACE__ . '\product_categories_before_render_content', 10, 1 );
