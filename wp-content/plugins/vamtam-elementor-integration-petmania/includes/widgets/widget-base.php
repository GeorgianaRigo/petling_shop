<?php
namespace VamtamElementor\Widgets\WidgetBase;

function check_base_theme_styles_enabled_for_widget_type( $widget ) {
    $widget_name = $widget->get_name();
    if ( $widget->get_name() === 'global' ) {
        $widget_name = $widget->get_original_element_instance()->get_name();
    }
    if ( \Vamtam_Elementor_Utils::is_widget_mod_active( $widget_name ) ) {
        $widget->add_render_attribute( '_wrapper', 'class', 'vamtam-has-theme-widget-styles' );
    }
}

// All widgets, before render.
function widget_base_before_render( $widget ) {
    check_base_theme_styles_enabled_for_widget_type( $widget );
}

add_action( 'elementor/frontend/widget/before_render', __NAMESPACE__ . '\widget_base_before_render', 10, 1 );