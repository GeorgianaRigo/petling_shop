<?php
namespace VamtamElementor\Widgets\TestimonialCarousel;

use \ElementorPro\Modules\Carousel\Widgets\Testimonial_Carousel as Elementor_Testimonial_Carousel;

// Extending the Testimonial Carousel widget.

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'testimonial-carousel' ) ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

if ( vamtam_theme_supports( 'testimonial-carousel--slide-triggers-inner-anims' ) ) {

	// Vamtam_Testimonial_Carousel.
	function widgets_registered() {

		if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
			return;
		}

		if ( ! class_exists( '\ElementorPro\Modules\Carousel\Widgets\Testimonial_Carousel' ) ) {
			return; // Elementor's autoloader acts weird sometimes.
		}

		class Vamtam_Testimonial_Carousel extends Elementor_Testimonial_Carousel {
			public $extra_depended_scripts = [
				'vamtam-testimonial-carousel',
			];

			public function get_script_depends(): array {
				return [
					'imagesloaded',
					'vamtam-testimonial-carousel',
				];
			}

			// Extend constructor.
			public function __construct($data = [], $args = null) {
				parent::__construct($data, $args);

				$this->register_assets();

				$this->add_extra_script_depends();
			}

			// Register the assets the widget depends on.
			public function register_assets() {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				wp_register_script(
					'vamtam-testimonial-carousel',
					VAMTAM_ELEMENTOR_INT_URL . 'assets/js/widgets/testimonial-carousel/vamtam-testimonial-carousel' . $suffix . '.js',
					[
						'elementor-frontend'
					],
					\VamtamElementorIntregration::PLUGIN_VERSION,
					true
				);
			}

			// Assets the widget depends upon.
			public function add_extra_script_depends() {
				// Scripts
				foreach ( $this->extra_depended_scripts as $script ) {
					$this->add_script_depends( $script );
				}
			}
		}

		// Replace current testimonial-carousel widget with our extended version.
		$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		$widgets_manager->unregister( 'testimonial-carousel' );
		$widgets_manager->register( new Vamtam_Testimonial_Carousel );
	}
	add_action( \Vamtam_Elementor_Utils::get_widgets_registration_hook(), __NAMESPACE__ . '\widgets_registered', 100 );
}
