<?php
namespace VamtamElementor\Widgets\Posts;

use \ElementorPro\Modules\Posts\Widgets\Posts as Elementor_Posts;

// Extending the Posts widget.

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'posts' ) ) {
	return;
}

if ( vamtam_theme_supports( [ 'posts-base--load-more-masonry-fix', 'posts-base--404-handling-fix', 'posts-base--display-categories' ] ) ) {
	// Vamtam_Widget_Posts.
	function widgets_registered() {
		// Is Pro Widget.
		if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
			return;
		}

		if ( ! class_exists( '\ElementorPro\Modules\Posts\Widgets\Posts' ) ) {
			return; // Elementor's autoloader acts weird sometimes.
		}

		class Vamtam_Widget_Posts extends Elementor_Posts {
			public $extra_depended_scripts = [
				'vamtam-posts-base',
			];

			/*
				We override the get_script_depends method directly because Elementor's
				Posts_Base class returns the array directly, like so:

					public function get_script_depends() {
						return [ 'imagesloaded' ];
					}

				If this changes, we should update this and probably filter the script in the
				add_extra_script_depends method.
			*/
			public function get_script_depends() {
				$script_depends = [
					'imagesloaded',
				];

				if (  vamtam_theme_supports( 'posts-base--display-categories', 'posts-base--404-handling-fix', 'posts-base--load-more-masonry-fix' ) ) {
					$script_depends[] = 'vamtam-posts-base';
				}

				if (  vamtam_theme_supports( 'posts-base--horizontal-layout' ) ) {
					$script_depends[] = 'vamtam-hr-scrolling';
				}

				return $script_depends;
			}

			// Extend constructor.
			public function __construct($data = [], $args = null) {
				parent::__construct($data, $args);

				$this->register_assets();

				$this->add_extra_script_depends();
			}

			/*
				Skins (and their controls) are already registered in the parent class.

				Registering them again (by calling parent::__construct()), would trigger the re-addition of their options, which have already
				been registered at this point, leading to $control_stack issues (adding exisitng control options).
			*/
			protected function register_skins() {
				if (  vamtam_theme_supports( 'posts-base--display-categories' ) ) {
					$this->add_skin( new \VamtamElementor\Widgets\PostsBase\Skin_Vamtam_Posts_Classic( $this ) );
				}
			}

			// Register the assets the widget depends on.
			public function register_assets() {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				if ( vamtam_theme_supports( 'posts-base--display-categories', 'posts-base--404-handling-fix', 'posts-base--load-more-masonry-fix' ) ) {
					wp_register_script(
						'vamtam-posts-base',
						VAMTAM_ELEMENTOR_INT_URL . '/assets/js/widgets/posts-base/vamtam-posts-base' . $suffix . '.js',
						[
							'elementor-frontend'
						],
						\VamtamElementorIntregration::PLUGIN_VERSION,
						true
					);
				}
			}

			// Assets the widget depends upon.
			public function add_extra_script_depends() {
				// Scripts
				foreach ( $this->extra_depended_scripts as $script ) {
					$this->add_script_depends( $script );
				}
			}
		}

		// Replace current posts widget with our extended version.
		$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		$widgets_manager->unregister( 'posts' );
		$widgets_manager->register( new Vamtam_Widget_Posts );
	}
	add_action( \Vamtam_Elementor_Utils::get_widgets_registration_hook(), __NAMESPACE__ . '\widgets_registered', 100 );
}
