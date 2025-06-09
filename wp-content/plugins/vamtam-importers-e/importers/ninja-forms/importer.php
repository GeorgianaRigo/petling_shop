<?php
/**
 * VamTam Ninja Forms Importer
 */

if ( ! defined( 'WP_LOAD_IMPORTERS' ) )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

/**
 * WordPress Importer class for managing the import process of a WXR file
 *
 * @package Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class Vamtam_NinjaForms_Import extends WP_Importer {
	private $dir;

	public function __construct() {
		$this->dir = VAMTAM_SAMPLES_DIR . 'ninja-forms';
	}

	/**
	 * Registered callback function for the WordPress Importer
	 *
	 * Manages the three separate stages of the WXR import process
	 */
	public function dispatch() {
		$this->header();

		check_admin_referer( 'vamtam-import-ninja-forms' );

		set_time_limit( 0 );
		$this->import( );

		$this->footer();
	}

	/**
	 * The main controller for the actual import stage.
	 */
	public function import() {
		add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

		$this->import_start();

		wp_suspend_cache_invalidation( true );

		$this->import_forms();

		wp_suspend_cache_invalidation( false );

		$this->import_end();
	}

	private function import_forms() {
		$dir = opendir( $this->dir );

		while ( $file = readdir( $dir ) ) {
			if ( $file != '.' && $file != '..' && preg_match( '/\.nff$/', $file ) ) {
				$origpath = $this->dir . '/' . $file;

				if ( WP_DEBUG ) {
					echo wp_kses_post( sprintf( __( 'Importing %s <br>', 'wpv' ), $origpath ) );
				}

				Ninja_Forms()->form()->import_form( file_get_contents( $origpath ), true, (int) pathinfo( $file, PATHINFO_FILENAME ) );
			}
		}
	}

	private function import_start() {
		if ( ! is_dir( $this->dir ) ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'wordpress-importer' ) . '</strong><br />';
			echo esc_html__( 'The file does not exist, please try again.', 'wordpress-importer' ) . '</p>';
			$this->footer();
			die();
		}

		do_action( 'import_start' );
	}

	/**
	 * Performs post-import cleanup of files and the cache
	 */
	private function import_end() {
		$redirect = admin_url( '' );

		// disable opinionated styles
		$nf_settings = get_option( 'ninja_forms_settings', [] );

		$nf_settings['opinionated_styles'] = '';

		update_option( 'ninja_forms_settings', $nf_settings );

		echo '<p>' . esc_html__( 'All done.', 'wordpress-importer' ) . ' <a href="' . esc_url( $redirect ) . '">' . esc_html__( 'Have fun!', 'wordpress-importer' ) . '</a></p>';

		echo '<!-- all done -->';

		do_action( 'import_end' );
	}

	// Display import page title
	private function header() {
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Import Ninja Forms Samples', 'wordpress-importer' ) . '</h2>'; }

	// Close div.wrap
	private function footer() {
		echo '</div>';
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 120 seconds during import
	 * @return int 120
	 */
	public function bump_request_timeout( $imp ) {
		return 120;
	}
}

} // class_exists( 'WP_Importer' )

function vamtam_ninja_forms_importer_init() {
	$GLOBALS['vamtam_ninja_forms_import'] = new Vamtam_NinjaForms_Import();
	register_importer( 'vamtam_ninjaforms', 'Vamtam Ninja Forms Importer', sprintf( esc_html__( 'Import Ninja Forms samples bundled with VamTam themes, not to be used as a stand-alone product.', 'wpv' ), VAMTAM_THEME_NAME ), array( $GLOBALS['vamtam_ninja_forms_import'], 'dispatch' ) );
}
add_action( 'admin_init', 'vamtam_ninja_forms_importer_init' );
