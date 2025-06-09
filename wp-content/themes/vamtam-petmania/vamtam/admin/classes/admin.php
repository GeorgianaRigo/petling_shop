<?php

/**
 * Framework admin enhancements
 *
 * @author Nikolay Yordanov <me@nyordanov.com>
 * @package vamtam/petmania
 */

/**
 * class VamtamAdmin
 */
class VamtamAdmin {
	/**
	 * Initialize the theme admin
	 */
	public static function actions() {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			add_action( 'admin_init', array( 'VamtamUpdateNotice', 'check' ) );
		}

		add_action( 'admin_footer', array( __CLASS__, 'icons_selector' ) );

		add_filter( 'admin_notices', array( __CLASS__, 'update_warning' ) );

		add_action( 'admin_init', array( __CLASS__, 'setup_settings' ) );

		add_filter( 'upgrader_pre_download', array( __CLASS__, 'theme_upgrade_no_pkg' ) , 10, 3 );

		add_filter( 'wp_prepare_themes_for_js', array( __CLASS__, 'theme_upgrade_update_message' ), 10, 1);

		add_filter( 'option_auto_update_themes', array( __CLASS__, 'theme_auto_update_filter' ), 10, 3 );

		self::load_functions();
		self::inactive_vamtam_plugins_updates();

		new VamtamPurchaseHelper;
		new VamtamHelpPage;
		new VamtamDiagnostics;
		new VamtamMigrations;

		require_once VAMTAM_ADMIN_HELPERS . 'updates/version-checker.php';

		if ( ! get_option( VAMTAM_THEME_SLUG . '_vamtam_theme_activated', false ) ) {
			update_option( VAMTAM_THEME_SLUG . '_vamtam_theme_activated', true );
			delete_option( 'default_comment_status' );
		}
	}

	public static function theme_auto_update_filter( $value, $option ) {
		foreach ( $value as $i => $theme ) {
			if ( $theme === VAMTAM_THEME_SLUG ) {
				$valid_pc = Version_Checker::is_valid_purchase_code() && ! get_option( VamtamFramework::get_token_option_key() );

				if ( ! $valid_pc ) {
					unset( $value[ $i ] );
				}
			}
		}

		return $value;
	}


	public static function setup_settings() {}

	public static function update_warning() {
		if ( did_action( 'load-update-core.php' ) ) {
			echo '<div class="updated notice fade is-dismissible"><p><strong>';
			esc_html_e( 'Hey, just a polite reminder that if you update WordPress you will also need to update your theme and plugins.', 'vamtam-petmania' );
			echo '</strong>';
			echo '</p></div>';
		}

		if ( did_action( 'load-update-core.php' ) || did_action( 'load-themes.php' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>';
			esc_html_e( 'VamTam theme resources: ', 'vamtam-petmania' );
			echo '</strong>';
			echo '<a href="https://vamtam.com/child-themes" target="_blank">';
			esc_html_e( 'Sample child themes', 'vamtam-petmania' );
			echo '</a>; ';
			echo '<a href="https://vamtam.com/changelog" target="_blank">';
			esc_html_e( 'Changelog', 'vamtam-petmania' );
			echo '</a>';
			echo '</p></div>';
		}
	}

	public static function icons_selector() {
		?>
		<div class="vamtam-config-icons-selector hidden">
			<input type="search" placeholder="<?php esc_attr_e( 'Filter icons', 'vamtam-petmania' ) ?>" class="icons-filter"/>
			<div class="icons-wrapper spinner">
				<input type="radio" value="" checked="checked"/>
			</div>
		</div>
		<?php
	}

	public static function theme_upgrade_no_pkg( $reply, $package, $upgrader ) {
		if ( isset( $upgrader->skin->theme_info ) && false !== $upgrader->skin->theme_info ) {
			$theme_slug = $upgrader->skin->theme_info->get_stylesheet();

			if ( $theme_slug === VAMTAM_THEME_SLUG && empty( $package ) ) {
				return new WP_Error( 'no_package', __( 'Only Envato Market clients with a valid purchase code are entitled to automatic updates. Envato Elements clients must use FTP.', 'vamtam-petmania' ) );
			}
		}

		return $reply;
	}

	public static function theme_upgrade_update_message( $themes ) {
		$theme_name = wp_get_theme()->get_template();

		if ( isset( $themes[ $theme_name ] ) ) {
			// Changelog link to open in new tab.
			$themes[ $theme_name ][ 'update' ] = preg_replace( '/<a href="[^"]+"/', '<a target=_blank href="https://vamtam.com/changelog"', $themes[ $theme_name ][ 'update' ] );
			$themes[ $theme_name ][ 'update' ] = str_replace( 'thickbox ', '', $themes[ $theme_name ][ 'update' ] );

			if ( ! Version_Checker::is_valid_purchase_code() || get_option( VamtamFramework::get_token_option_key() ) ) { // Not valid pc or token.
				// Append custom msg.
				$themes[ $theme_name ][ 'update' ] .=  '<hr/><p><strong>' . __( 'Note: Only Envato Market clients with a valid purchase code are entitled to automatic updates. Envato Elements clients must use FTP.', 'vamtam-fiore' ) . '</p></strong>';

				// Disable auto-update
				$themes[ $theme_name ][ 'autoupdate' ][ 'forced' ] =  false;
				$themes[ $theme_name ][ 'autoupdate' ][ 'enabled' ] =  false;
				$themes[ $theme_name ][ 'actions' ][ 'autoupdate' ] =  false;
			}
		}

		return $themes;
	}

	/**
	 * Admin helper functions
	 */
	private static function load_functions() {
		require_once VAMTAM_ADMIN_HELPERS . 'base.php';
	}

	/**
	 * Function to check inactive plugins and initialize Vamtam Updates if necessary.
	 */
	private static function inactive_vamtam_plugins_updates() {
		global $pagenow;

		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			if ( in_array( $plugin_path, $active_plugins, true ) ) {
				continue; // Skip active plugins.
			}

			$plugin_dir = dirname( WP_PLUGIN_DIR . '/' . $plugin_path );
			$main_file  = WP_PLUGIN_DIR . '/' . $plugin_path;

			if ( file_exists( $main_file ) ) {
				$content = file_get_contents( $main_file );

				if ( preg_match( '/new\s+Vamtam_Updates_(\d+)\s*\(\s*__FILE__\s*\)/', $content, $matches ) ) {
					$update_class = 'Vamtam_Updates_' . $matches[1];

					if ( ! class_exists( $update_class ) ) {
						$class_file = $plugin_dir . '/vamtam-updates/class-vamtam-updates.php';
						if ( file_exists( $class_file ) ) {
							include_once $class_file;
						}
					}

					if ( class_exists( $update_class ) ) {
						new $update_class( $main_file );
					}
				}
			}
		}
	}
}


