<?php
namespace VamtamElementor\Widgets\Login;

use ElementorPro\Modules\Forms\Widgets\Login as Elementor_Login;

// Extending the Login widget.

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'login' ) ) {
	return;
}

// Vamtam_Widget_Login.
function widgets_registered() {
	class Vamtam_Widget_Login extends Elementor_Login {
		public $extra_depended_scripts = [
			'vamtam-login',
		];

		public function get_script_depends() {
			return [
				'vamtam-login',
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
				'vamtam-login',
				VAMTAM_ELEMENTOR_INT_URL . '/assets/js/widgets/login/vamtam-login' . $suffix . '.js',
				[
					'elementor-frontend'
				],
				\VamtamElementorIntregration::PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'vamtam-login', 'VamtamLoginStrings', array(
					'account' => __( 'Don\'t have an account?', 'vamtam-elementor-integration' ),
					'register' => __( 'Create account', 'vamtam-elementor-integration' ),
				)
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

	// Replace current tabs widget with our extended version.
	$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
	$widgets_manager->unregister( 'login' );
	$widgets_manager->register( new Vamtam_Widget_Login );
}
add_action( \Vamtam_Elementor_Utils::get_widgets_registration_hook(), __NAMESPACE__ . '\widgets_registered', 100 );
