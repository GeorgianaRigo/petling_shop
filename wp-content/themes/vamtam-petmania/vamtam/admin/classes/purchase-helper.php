<?php

/**
 * Purchase Helper
 *
 * @package vamtam/petmania
 */
/**
 * class VamtamPurchaseHelper
 */
class VamtamPurchaseHelper extends VamtamAjax {

	public static $storage_path;

	/**
	 * Hook ajax actions
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'admin_body_class', array( __CLASS__, 'vamtam_admin_body_class' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu_1'), 11 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu_2' ), 22 ); // after "Help"

		add_action( 'after_setup_theme', array( __CLASS__, 'after_setup_theme' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_early_init' ), 5 );
		add_action( 'admin_notices', array( __CLASS__, 'notice_early' ), 5 ); // after TGMPA registers its notices, but before printing
		add_action( 'admin_notices', array( __CLASS__, 'services_notice' ), 6 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );

		add_filter( 'tgmpa_update_bulk_plugins_complete_actions', array( __CLASS__, 'tgmpa_plugins_complete_actions' ), 10, 2 );
	}

	public static function vamtam_admin_body_class( $classes )
	{
		// Adds a class to the body tag to hint for pending verification.
		if ( ! Version_Checker::is_valid_purchase_code() ) {
			$classes .= ' vamtam-not-verified';
		}
		return $classes;
	}

	public static function notice_early() {
		$screen = get_current_screen();
		if ( ! self::is_theme_setup_page() && $screen->id !== 'plugins' ) {
			remove_action( 'admin_notices', array( $GLOBALS['tgmpa'], 'notices' ), 10 );
		}

		$valid_key = Version_Checker::is_valid_purchase_code();

		$is_updates_page = $screen->id === 'update-core';

		if ( ! $valid_key && ! $is_updates_page ) {
			VamtamFramework::license_register();
		}
	}

	private static function server_tests() {
		$timeout = (int) ini_get( 'max_execution_time' );
		$memory  = ini_get( 'memory_limit' );
		$memoryB = str_replace( array( 'G', 'M', 'K' ), array( '000000000', '000000', '000' ), $memory );

		$tests = array(
			array(
				'name'  => esc_html__( 'PHP Version', 'vamtam-petmania' ),
				'test'  => version_compare( phpversion(), '5.5', '<' ),
				'value' => phpversion(),
				'desc'  => esc_html__( 'While this theme works with all PHP versions supported by WordPress Core, PHP versions 5.5 and older are no longer maintained by their developers. Consider switching your server to PHP 5.6 or newer.', 'vamtam-petmania' ),
			),
			array(
				'name'  => esc_html__( 'PHP Time Limit', 'vamtam-petmania' ),
				'test'  => $timeout > 0 && $timeout < 30,
				'value' => $timeout,
				'desc'  => esc_html__( 'The PHP time limit should be at least 30 seconds. Note that in some configurations your server (Apache/nginx) may have a separate time limit. Please consult with your hosting provider if you get a time out while importing the demo content.', 'vamtam-petmania' ),
			),
			array(
				'name'  => esc_html__( 'PHP Memory Limit', 'vamtam-petmania' ),
				'test'  => (int) $memory > 0 && $memoryB < 96 * 1024 * 1024,
				'value' => $memory,
				'desc'  => esc_html__( 'You need a minimum of 96MB memory to use the theme and the bundled plugins. For non-US English websites you need a minimum of 128MB in order to accomodate the translation features which are otherwise disabled.', 'vamtam-petmania' ),
			),
			array(
				'name'  => esc_html__( 'PHP ZipArchive Extension', 'vamtam-petmania' ),
				'test'  => ! class_exists( 'ZipArchive' ),
				'value' => '',
				'desc'  => esc_html__( 'ZipArchive is a requirement for importing the demo sliders.', 'vamtam-petmania' ),
			),
		);

		$fail = 0;

		foreach ( $tests as $test ) {
			$fail += (int) $test['test'];
		}

		return array(
			'fail'  => $fail,
			'tests' => $tests,
		);
	}

	private static function is_theme_setup_page() {
		return isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'vamtam_theme_setup' ) );
	}

	public static function admin_scripts() {
		$theme_version = VamtamFramework::get_version();

		wp_register_script( 'vamtam-check-license', VAMTAM_ADMIN_ASSETS_URI . 'js/check-license.js', array( 'jquery' ), $theme_version, true );
		wp_register_script( 'vamtam-import-buttons', VAMTAM_ADMIN_ASSETS_URI . 'js/import-buttons.js', array( 'jquery' ), $theme_version, true );
	}

	public static function tgmpa_plugins_complete_actions( $update_actions, $plugin_info ) {
		if ( isset( $update_actions['dashboard'] ) ) {
			$update_actions['dashboard'] = sprintf(
				esc_html__( 'All plugins installed and activated successfully. %1$s', 'vamtam-petmania' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=vamtam_theme_setup_import_content' ) ) . '" class="button button-primary">' . esc_html__( 'Continue with theme setup.', 'vamtam-petmania' ) . '</a>'
			);

			$update_actions['dashboard'] .= '
                <script>
                    window.scroll( 0, 10000000 );
                </script>
            ';
		}

		return $update_actions;
	}

	public static function admin_menu() {
		add_menu_page( esc_html__( 'VamTam', 'vamtam-petmania' ), esc_html__( 'VamTam', 'vamtam-petmania' ), 'edit_theme_options', 'vamtam_theme_setup', array( __CLASS__, 'page' ), '', 2 );
		add_submenu_page( 'vamtam_theme_setup', esc_html__( 'Dashboard', 'vamtam-petmania' ), esc_html__( 'Dashboard', 'vamtam-petmania' ), 'edit_theme_options', 'vamtam_theme_setup', array( __CLASS__, 'page' ) );
		remove_submenu_page('vamtam_theme_setup','vamtam_theme_setup');
		add_submenu_page( 'vamtam_theme_setup', esc_html__( 'Dashboard', 'vamtam-petmania' ), esc_html__( 'Dashboard', 'vamtam-petmania' ), 'edit_theme_options', 'vamtam_theme_setup', array( __CLASS__, 'page' ) );
	}

	public static function admin_menu_1() {
		//Called with a lower priority so 'Installed Plugins' menu item has been registered (tgmpa).
		add_submenu_page( 'vamtam_theme_setup', esc_html__( 'Import Demo Content', 'vamtam-petmania' ), esc_html__( 'Import Demo Content', 'vamtam-petmania' ), 'edit_theme_options', 'vamtam_theme_setup_import_content', array( __CLASS__, 'vamtam_theme_setup_import_content' ) );
	}

	public static function admin_menu_2() {
		add_submenu_page(
			'vamtam_theme_setup',
			esc_html__( 'Services', 'vamtam-petmania' ),
			esc_html__( 'Services', 'vamtam-petmania' ) .
			'<span id="vamtam-premium-services">' .
			'<?xml version="1.0" encoding="UTF-8"?>
			<svg viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg">
			<path d="m309 106c11.4-7 19-19.7 19-34 0-22.1-17.9-40-40-40s-40 17.9-40 40c0 14.4 7.6 27 19 34l-57.3 114.6c-9.1 18.2-32.7 23.4-48.6 10.7l-89.1-71.3c5-6.7 8-15 8-24 0-22.1-17.9-40-40-40s-40 17.9-40 40 17.9 40 40 40h0.7l45.7 251.4c5.5 30.4 32 52.6 63 52.6h277.2c30.9 0 57.4-22.1 63-52.6l45.7-251.4h0.7c22.1 0 40-17.9 40-40s-17.9-40-40-40-40 17.9-40 40c0 9 3 17.3 8 24l-89.1 71.3c-15.9 12.7-39.5 7.5-48.6-10.7l-57.3-114.6z"/>
			</svg>' . __( 'Premium', 'vamtam-petmania' ) .
			'</span>',
			'edit_theme_options',
			'vamtam_theme_services',
			array( __CLASS__, 'services_menu_item' )
		);
	}

	public static function services_menu_item() {
		wp_redirect( 'https://vamtam.com/services/' );
		exit;
	}

	public static function services_notice() {
		if ( get_transient( 'vamtam_dismissed_services_notice' ) ) {
			return;
		}

		$is_updates_page = get_current_screen()->id === 'update-core';

		if ( ! $is_updates_page ) {
			return;
		}

		?>
		<div class="vamtam-ts-notice">
				<div class="vamtam-notice vamtam-services-notice notice cta is-dismissible">
					<div class="vamtam-notice-aside">
						<div class="vamtam-notice-icon-wrapper">
							<img id="vamtam-logo" src="<?php echo esc_attr( VAMTAM_ADMIN_ASSETS_URI . 'images/vamtam-logo.png' ); ?>"></img>
						</div>
					</div>
					<div class="vamtam-notice-content">
						<h3><?php echo __( 'Make updates easy with our Premium Service', 'vamtam-petmania' ); ?></h3>
						<p><?php echo __( 'Enjoy hassle-free updates with our Premium Services. Keep your software up-to-date effortlessly.', 'vamtam-petmania' ); ?></p>
						<p>
							<a class="button btn-cta" target="_blank" href="https://vamtam.com/services/">Get Premium Service</a>
						</p>
					</div>
				</div>
			</div>
		<?php
	}

	public static function registration_warning() {
		?>
		<div class="vamtam-notice-wrap">
			<div class="vamtam-notice">
				<p>
					<?php echo esc_html__( 'Please activate your license to get theme updates, premium support, and access to demo content.', 'vamtam-petmania' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ); ?>">
						<?php echo esc_html__( 'Register Now', 'vamtam-petmania' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	public static function vamtam_theme_setup_import_content() {
		wp_enqueue_script( 'vamtam-check-license' );
		$valid_key = Version_Checker::is_valid_purchase_code();
		?>
		<div id="vamtam-ts-import-content" class="vamtam-ts">
			<div id="vamtam-ts-side">
				<?php self::dashboard_navigation(); ?>
			</div>
			<div id="vamtam-ts-main">
				<?php if ( $valid_key ) : ?>
					<?php self::import_buttons() ?>
				<?php else : ?>
					<?php self::registration_warning(); ?>
				<?php endif ?>
			</div>
		</div>
		<?php
	}

	public static function after_setup_theme() {
		if ( self::is_theme_setup_page() ) {
			add_filter( 'heartbeat_settings', [ __CLASS__, 'heartbeat_settings' ] );
		}

		add_filter( 'fs_redirect_on_activation_ajax-search-for-woocommerce', '__return_false', 100 );
	}

	public static function admin_early_init() {
		add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_true' );

		if ( class_exists( 'Elementor\Plugin' ) ) {
			remove_action( 'admin_init', [ Elementor\Plugin::instance()->admin, 'maybe_redirect_to_getting_started' ] );
		}

		if ( get_transient( '_fp_activation_redirect' ) ) {
			delete_transient( '_fp_activation_redirect' );
		}

		if ( get_transient( '_booked_welcome_screen_activation_redirect' ) ) {
			delete_transient( '_booked_welcome_screen_activation_redirect' );
		}

		if ( get_option( 'sbi_plugin_do_activation_redirect', false ) ) {
			remove_action( 'admin_init', 'sbi_activation_plugin_redirect' );
			delete_option( 'sbi_plugin_do_activation_redirect' );
		}

		add_filter( 'fs_redirect_on_activation_ajax-search-for-woocommerce', '__return_false', 100 );
	}

	public static function admin_init() {
		$purchase_code_option_id = VamtamFramework::get_purchase_code_option_key();

		add_settings_section(
			'vamtam_purchase_settings_section',
			'',
			array( __CLASS__, 'settings_section' ),
			'vamtam_theme_setup'
		);
		add_settings_field(
			$purchase_code_option_id,
			esc_html__( 'Enter your purchase code from ThemeForest to receive theme updates and support.', 'vamtam-petmania' ),
			array( __CLASS__, 'purchase_key' ),
			'vamtam_theme_setup',
			'vamtam_purchase_settings_section',
			array(
				$purchase_code_option_id,
			)
		);

		register_setting(
			'vamtam_theme_setup',
			$purchase_code_option_id,
			array( __CLASS__, 'sanitize_license_key' )
		);
	}

	public static function sanitize_license_key( $value ) {
		return preg_replace( '/[^-\w\d]/', '', $value );
	}

	public static function settings_section() {
	}

	public static function heartbeat_settings( $settings ) {
		$settings['interval'] = 15;
		return $settings;
	}

	public static function page() {
		wp_enqueue_script( 'vamtam-check-license' );

		$status        = self::server_tests();
		$theme_name    = ucfirst( wp_get_theme()->get_template() );
		$theme_version = VamtamFramework::get_version();
		$valid_key     = Version_Checker::is_valid_purchase_code();
		$is_token      = get_option( VamtamFramework::get_token_option_key() );

		?>
		<h2></h2>

		<div id="vamtam-ts-homepage" class="vamtam-ts">
			<div id="vamtam-ts-side">
				<?php self::dashboard_navigation(); ?>
			</div>
			<div id="vamtam-ts-main">
				<?php do_action( 'vamtam_theme_setup_notices' ); ?>
				<div id="vamtam-ts-dash-register">
					<div id="vamtam-ts-register-product">
						<?php
							if ( defined( 'ENVATO_HOSTED_SITE' ) ) :
								esc_html_e( 'All done.', 'vamtam-petmania' );
							else :
						?>
							<form id="vamtam-register-form" method="post" action="options.php" autocomplete="off">
								<?php if ( $valid_key ) : ?>
									<div id="vamtam-verified-code">
										<p>
											<?php
												$license_type = $is_token ? 'token' : 'purchase code';
												esc_html_e( 'Thanks for verifying your ' . $license_type . '!', 'skole' )
											?>
											<br />
											<?php echo esc_html( sprintf( __( 'You can now enjoy %s and build great websites.', 'vamtam-petmania' ) , $theme_name ) ); ?>
										</p>
									</div>
								<?php else : ?>
									<div id="vamtam-envato-market-radios">
										<div>
											<label>
												<input type="radio" id="vamtam-envato-market-radio" name="vamtam_envato_elements" checked="">
												<span><?php echo esc_html__( 'Envato Market', 'vamtam-petmania' ); ?></span>
											</label>
											<label>
												<input type="radio" id="vamtam-envato-elements-radio" name="vamtam_envato_elements">
												<span><?php echo esc_html__( 'Envato Elements', 'vamtam-petmania' ); ?></span>
											</label>
										</div>
									</div>
									<div id="vamtam-envato-logo-wrap" >
										<svg id="vamtam-envato-market-logo" width="190" height="26" xmlns="http://www.w3.org/2000/svg"><g fill-rule="nonzero" fill="none"><path d="M29.477 5.025c3.849 0 7.613 2.269 7.613 7.325 0 .402-.022 1.024-.066 1.462a.197.197 0 0 1-.196.177H26.03c.316 1.81 1.58 2.987 3.562 2.987 1.315 0 2.153-.726 2.609-1.595a.273.273 0 0 1 .303-.14l4.037.88c.123.027.188.16.138.275-.944 2.138-3.092 4.256-7.117 4.256-5.285 0-8.1-3.447-8.1-7.813 0-4.367 2.93-7.814 8.014-7.814Zm3.101 6.204c-.2-1.724-1.35-2.643-3.016-2.643-2.184 0-3.103 1.12-3.447 2.643h6.463ZM38.468 19.996V5.683c0-.109.088-.198.198-.198h4.2c.109 0 .198.088.198.198v1.699c1.006-1.58 2.5-2.356 4.424-2.356 2.816 0 5.228 1.925 5.228 6.234v8.737a.198.198 0 0 1-.198.198h-4.2a.198.198 0 0 1-.198-.198V11.92c0-1.925-1.006-2.987-2.472-2.987-1.58 0-2.585 1.034-2.585 3.39v7.673a.198.198 0 0 1-.198.198h-4.2a.198.198 0 0 1-.199-.198l.002-.001ZM53 5.484h4.455c.088 0 .164.057.19.14l3.347 11.093 3.347-11.093a.197.197 0 0 1 .19-.14h4.455c.137 0 .233.135.185.264L64.044 20.01a.276.276 0 0 1-.26.183h-5.586a.279.279 0 0 1-.26-.183L52.811 5.75a.198.198 0 0 1 .186-.265h.001ZM78.917 19.996v-2.244c-.718 1.493-2.326 2.902-4.826 2.902-2.902 0-5.056-1.838-5.056-4.424 0-2.729 1.81-4.768 5.774-4.768h2.298c1.264 0 1.61-.919 1.494-1.523-.173-1.035-1.092-1.58-2.385-1.58-1.633 0-2.62.903-2.744 2.145a.198.198 0 0 1-.23.176l-3.897-.65a.198.198 0 0 1-.163-.229c.634-3.39 3.85-4.773 7.149-4.773 3.298 0 6.951.804 6.951 6.894v8.076a.198.198 0 0 1-.198.198h-3.971a.198.198 0 0 1-.198-.198l.002-.002Zm-3.476-2.677c1.838 0 3.103-1.379 3.246-3.103h-2.786c-1.694 0-2.298.69-2.269 1.638.03 1.005.833 1.465 1.81 1.465h-.001ZM83.974 8.963V5.682c0-.11.088-.198.198-.198h1.642a1.38 1.38 0 0 0 1.379-1.38v-2.56c0-.109.088-.198.198-.198h3.742c.108 0 .198.088.198.198v3.94h3.019c.109 0 .198.087.198.198v3.281a.198.198 0 0 1-.198.198h-3.02v5.315c0 1.732 1.473 2.437 3.009 1.89.101-.035.209.041.209.15v3.463c0 .127-.087.24-.21.269a7.09 7.09 0 0 1-1.6.176c-3.562 0-6.004-1.207-6.004-6.378V9.162h-2.56a.198.198 0 0 1-.198-.198l-.002-.001ZM111.323 12.839c0 4.309-3.044 7.813-8.044 7.813s-8.044-3.504-8.044-7.813c0-4.31 3.045-7.814 8.044-7.814 5 0 8.044 3.504 8.044 7.814Zm-4.596 0c0-2.126-1.179-3.908-3.448-3.908s-3.447 1.78-3.447 3.908c0 2.126 1.178 3.907 3.447 3.907 2.27 0 3.448-1.78 3.448-3.907Z" fill="#000"/><path d="M10.258 25.685a1.15 1.15 0 1 0 0-2.298 1.15 1.15 0 0 0 0 2.298ZM16.856 16.714l-6.472.693c-.119.013-.18-.138-.085-.212l6.334-4.931c.411-.336.673-.86.56-1.421-.111-.86-.822-1.421-1.719-1.308l-6.882 1.008c-.122.018-.187-.137-.09-.212l6.823-5.209c1.345-1.047 1.458-3.103.224-4.3-1.121-1.12-2.916-1.084-4.038.039L.518 12.039a1.948 1.948 0 0 0-.486 1.682c.187 1.01 1.196 1.682 2.206 1.495l5.926-1.209c.128-.026.198.145.087.216l-6.574 4.208c-.822.523-1.196 1.459-.934 2.393.262 1.234 1.495 1.944 2.692 1.645l9.827-2.42c.11-.027.193.101.12.19l-1.535 1.893c-.412.523.262 1.234.822.823l5.047-4.15c.897-.748.299-2.207-.86-2.094v.003Z" fill="#87E64B"/><path d="M115.921 19.996V6.145c0-.11.088-.198.198-.198h1.444c.108 0 .198.087.198.198v1.87c.89-1.551 2.068-2.299 3.676-2.299 2.068 0 3.303.949 4.107 2.873.919-1.924 2.182-2.873 4.165-2.873 2.442 0 4.595 1.321 4.595 5.717v8.563a.198.198 0 0 1-.198.198h-1.443a.198.198 0 0 1-.198-.198v-8.88c0-2.613-1.236-3.676-3.16-3.676-1.925 0-3.275 1.407-3.275 3.936v8.62a.198.198 0 0 1-.198.198h-1.443a.198.198 0 0 1-.198-.198v-8.88c0-2.613-1.236-3.676-3.16-3.676-1.925 0-3.275 1.407-3.275 3.705v8.85a.198.198 0 0 1-.198.199h-1.443a.198.198 0 0 1-.198-.198h.004ZM145.679 19.996v-2.79c-.92 1.982-2.73 3.218-5.055 3.218-2.643 0-4.596-1.667-4.596-4.194 0-3.016 2.212-4.48 5.113-4.48h3.074c1.092 0 1.407-.604 1.292-1.551-.143-1.236-1.092-2.873-3.59-2.873-2.497 0-3.768 1.49-3.97 2.898a.196.196 0 0 1-.231.164l-1.38-.266a.198.198 0 0 1-.157-.23c.562-2.853 3.012-4.173 5.71-4.173 2.697 0 5.63 1.15 5.63 6.348v7.931a.198.198 0 0 1-.199.198h-1.443a.198.198 0 0 1-.198-.198v-.002Zm-4.422-6.697c-2.413 0-3.39 1.263-3.39 2.786 0 1.407.92 2.73 2.787 2.73 2.844 0 4.912-2.098 5.026-5.515h-4.423V13.3ZM156.163 7.545a.197.197 0 0 1-.227.195c-2.604-.442-4.368 1.58-4.368 4.008v8.248a.198.198 0 0 1-.198.198h-1.443a.198.198 0 0 1-.198-.198V6.145c0-.11.088-.198.198-.198h1.443c.109 0 .198.087.198.198v2.501c.661-1.867 2.183-2.93 4.08-2.93.11 0 .234.013.345.028a.2.2 0 0 1 .172.197v1.605l-.002-.001ZM169.31 20.192h-1.905a.196.196 0 0 1-.156-.077l-5.743-7.363-1.895 1.694v5.548a.198.198 0 0 1-.198.198h-1.444a.198.198 0 0 1-.198-.198V2.008c0-.109.088-.198.198-.198h1.444c.109 0 .198.088.198.198v10.23l7.009-6.241a.197.197 0 0 1 .131-.05h1.987c.183 0 .268.225.131.345l-6.013 5.314 6.609 8.267c.103.13.011.32-.155.32Z" fill="#000"/><path d="M175.639 5.716c3.158 0 6.462 2.04 6.462 6.866 0 .291-.018.548-.039.77a.197.197 0 0 1-.197.178h-11.282c.258 3.131 2.27 5.17 5.142 5.17 2.328 0 3.72-1.39 4.305-2.83a.198.198 0 0 1 .226-.121l1.349.293a.197.197 0 0 1 .145.256c-.624 1.91-2.564 4.126-6.025 4.126-4.51 0-6.95-3.418-6.95-7.354 0-4.366 2.815-7.354 6.865-7.354h-.001Zm4.623 6.205c-.201-2.93-2.068-4.595-4.568-4.595-2.786 0-4.51 1.523-5.055 4.595h9.623ZM182.188 7.356V6.143c0-.109.087-.198.198-.198h1.295c.762 0 1.378-.618 1.378-1.378v-2.56c0-.109.088-.198.198-.198h1.328c.108 0 .198.088.198.198v3.938h3.019c.109 0 .198.088.198.198v1.213a.198.198 0 0 1-.198.199h-3.02v8.33c0 2.801 1.694 2.95 2.97 2.625a.197.197 0 0 1 .247.191v1.286a.2.2 0 0 1-.15.193 4.629 4.629 0 0 1-1.143.128c-2.73 0-3.763-1.494-3.763-4.625V7.555h-2.559a.198.198 0 0 1-.198-.199h.002Z" fill="#000"/></g></svg>
										<svg class="hidden" id="vamtam-envato-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280.28 64"><defs><style>.cls-1,.cls-2{fill:#191919;stroke-width:0}.cls-2{fill:#87e64b}</style></defs><path class="cls-1" d="M76.34 12.52c9.59 0 18.97 5.65 18.97 18.25 0 1-.05 2.55-.16 3.64-.03.25-.24.44-.49.44h-26.9c.79 4.51 3.94 7.44 8.88 7.44 3.28 0 5.37-1.81 6.5-3.97.14-.27.45-.41.75-.35l10.06 2.19c.31.07.47.4.34.69-2.35 5.33-7.7 10.61-17.73 10.61-13.17 0-20.19-8.59-20.19-19.47s7.3-19.47 19.97-19.47Zm7.73 15.46c-.5-4.29-3.36-6.59-7.52-6.59-5.44 0-7.73 2.79-8.59 6.59h16.11ZM98.75 49.82V14.16c0-.27.22-.49.49-.49h10.47c.27 0 .49.22.49.49v4.23c2.51-3.94 6.23-5.87 11.02-5.87 7.01 0 13.03 4.8 13.03 15.53v21.77c0 .27-.22.49-.49.49h-10.47a.49.49 0 0 1-.49-.49V29.7c0-4.8-2.51-7.44-6.16-7.44-3.94 0-6.44 2.58-6.44 8.45v19.12c0 .27-.22.49-.49.49H99.24a.49.49 0 0 1-.49-.49ZM134.95 13.66h11.1c.22 0 .41.14.47.35l8.34 27.64 8.34-27.64c.06-.21.25-.35.47-.35h11.1c.34 0 .58.34.46.66l-12.77 35.53c-.1.27-.36.46-.65.46h-13.92c-.29 0-.55-.18-.65-.46l-12.77-35.53a.49.49 0 0 1 .46-.66ZM199.53 49.82v-5.59c-1.79 3.72-5.8 7.23-12.03 7.23-7.23 0-12.6-4.58-12.6-11.02 0-6.8 4.51-11.88 14.39-11.88h5.73c3.15 0 4.01-2.29 3.72-3.79-.43-2.58-2.72-3.94-5.94-3.94-4.07 0-6.53 2.25-6.84 5.34-.03.28-.29.48-.57.44l-9.71-1.62a.49.49 0 0 1-.41-.57c1.58-8.45 9.59-11.89 17.81-11.89s17.32 2 17.32 17.18v20.12c0 .27-.22.49-.49.49h-9.9a.49.49 0 0 1-.49-.49Zm-8.66-6.66c4.58 0 7.73-3.44 8.09-7.73h-6.94c-4.22 0-5.73 1.72-5.65 4.08.07 2.51 2.08 3.65 4.51 3.65ZM212.13 22.33v-8.18c0-.27.22-.49.49-.49h4.09c1.9 0 3.44-1.54 3.44-3.44V3.85c0-.27.22-.49.49-.49h9.32c.27 0 .49.22.49.49v9.81h7.52c.27 0 .49.22.49.49v8.18c0 .27-.22.49-.49.49h-7.52v13.24c0 4.31 3.67 6.07 7.5 4.71.25-.09.52.1.52.37v8.63c0 .32-.21.6-.52.67-.99.23-2.36.44-3.99.44-8.88 0-14.96-3.01-14.96-15.89V22.82h-6.38a.49.49 0 0 1-.49-.49ZM280.28 31.99c0 10.74-7.59 19.47-20.04 19.47s-20.04-8.73-20.04-19.47 7.59-19.47 20.04-19.47 20.04 8.73 20.04 19.47Zm-11.46 0c0-5.3-2.93-9.73-8.59-9.73s-8.59 4.44-8.59 9.73 2.93 9.73 8.59 9.73 8.59-4.44 8.59-9.73Z"/><circle class="cls-2" cx="25.56" cy="61.14" r="2.86"/><path class="cls-2" d="m42 41.64-16.13 1.73c-.3.03-.45-.34-.21-.53l15.78-12.29c1.02-.84 1.68-2.14 1.4-3.54-.28-2.14-2.05-3.54-4.29-3.26L21.4 26.26c-.3.04-.46-.34-.22-.53l17-12.98c3.35-2.61 3.63-7.73.56-10.71-2.79-2.79-7.27-2.7-10.06.09L1.29 30a4.863 4.863 0 0 0-1.21 4.19c.47 2.52 2.98 4.19 5.5 3.73l14.77-3.01c.32-.07.49.36.22.54L4.19 45.94c-2.05 1.3-2.98 3.63-2.33 5.96.65 3.07 3.73 4.84 6.71 4.1l24.49-6.03c.28-.07.48.25.3.47l-3.82 4.72c-1.02 1.3.65 3.07 2.05 2.05l12.58-10.34c2.24-1.86.75-5.5-2.14-5.22Z"/></svg>
									</div>
								<?php endif ?>
							<?php
								settings_fields( 'vamtam_theme_setup' );
								do_settings_sections( 'vamtam_theme_setup' );
							?>
							</form>
						<?php endif; ?>
					</div>
				</div>
				<div id="vamtam-check-license-disclaimer">
					<h5><?php esc_html_e( 'Licensing Terms', 'vamtam-petmania' ); ?></h5>
					<p>
						<?php esc_html_e( 'You need to register a separate license for each domain on which you will use the theme. A single license is limited to a single domain/application. For more information, please refer to these articles - ', 'vamtam-petmania' ); ?>
						<a href="http://themeforest.net/licenses" target="_blank">
							<?php esc_html_e( 'Licensing Terms Envato Market', 'vamtam-petmania' ); ?>
						</a>,
						<a href="https://elements.envato.com/license-terms" target="_blank">
							<?php esc_html_e( 'Licensing Terms Envato Elements', 'vamtam-petmania' ); ?>
						</a>
						.
					</p>
				</div>
				<?php if ( current_user_can( 'switch_themes' ) ) : ?>
					<?php if ( ! defined( 'ENVATO_HOSTED_SITE' ) ) : ?>
						<div id="vamtam-server-tests">
							<h3>
								<?php if ( $status['fail'] > 0 ) : ?>
									<?php esc_html_e( 'System Status', 'vamtam-petmania' ) ?>
									<?php $fail = $status['fail']; ?>
									<small><?php printf( esc_html( _n( '(%d potential issue)', '(%d potential issues)', $fail, 'vamtam-petmania' ) ), $fail ) ?></small>
								<?php endif ?>
							</h3>
						</div>
					<?php endif ?>
				<?php endif ?>
			</div>
		</div>
		<?php
	}

	public static function dashboard_navigation() {
		$theme_name       = str_replace( 'VAMTAM-', '', strtoupper( wp_get_theme()->get_template() ) );
		$theme_version    = VamtamFramework::get_version();
		$valid_key        = Version_Checker::is_valid_purchase_code();
		$plugin_status    = VamtamPluginManager::get_required_plugins_status();
		$content_imported = ! ! get_option( 'vamtam_last_import_map', false );

		$routes = [
			'vamtam_theme_setup',
			'tgmpa-install-plugins',
			'vamtam_theme_setup_import_content',
			'vamtam_theme_help',
		];

		$cur_route = get_current_screen()->id;
		?>
		<nav id="vamtam-ts-nav-menu">
			<div id="vamtam-theme-title">
				<span id="vamtam-ts-greeter"><?php esc_html_e( 'WELCOME TO', 'vamtam-petmania' ); ?></span>
				<span id="vamtam-ts-greeter-title"><?php echo esc_html( $theme_name ); ?></span>
				<span id="vamtam-ts-greeter-ver"><?php echo sprintf( esc_html__( 'VER. %s', 'vamtam-petmania' ), $theme_version ); ?></span>
			</div>
			<ul>
				<li class="<?php echo esc_attr( $cur_route === 'toplevel_page_' . $routes[0] ? 'is-active' : '' ); ?>" >
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[0] ) ); ?>">
						<svg class="ts-icon" xmlns="http://www.w3.org/2000/svg" width="21" height="30" viewBox="0 0 21 30"><path fill-rule="evenodd" d="M2.4 11.3l-.1.1V6.5C2.5 2.7 5.5 0 9.6 0h.2c2.3 0 5 0 7.1 2.1 2.3 2.2 2 5.8 1.9 8.8v.6c-.8-.8-1.6-1.4-2.5-2V6.8l-.1-.1a3.2 3.2 0 0 0 0-.3L16 6v-.2l-.1-.3v-.2h-.1V5a4.3 4.3 0 0 0-.3-.5 1.7 1.7 0 0 0-.2-.3.7.7 0 0 0-.1-.1l-.2-.2c-1.4-1.4-2.7-1.4-5.3-1.4h-.2C6.9 2.5 5 4 4.9 6.5v3.1l-.6.3-.1.1-1 .6-.1.2H3l-.6.5zM10.5 30A10.5 10.5 0 0 1 0 19.9a9 9 0 0 1 2.5-6.4 11.4 11.4 0 0 1 8.3-3.7c1.3 0 2.6.3 3.9.8A10.5 10.5 0 0 1 21 20c.1 5.3-4.7 10-10.5 10.1zm0-12.3c-.9 0-1.6.7-1.6 1.6 0 .5.3 1 .8 1.3v1.9h1.6v-1.9c.5-.2.9-.8.9-1.3 0-1-.8-1.6-1.7-1.6z"/></svg>
						<span><?php echo esc_html__( 'Register' , 'vamtam-petmania' ); ?></span>
						<span class="vamtam-step-status <?php echo esc_attr( $valid_key ? 'success' : 'error' ); ?>"></span>
					</a>
				</li>
				<?php $tgmpa_instance 	= call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) ); ?>
				<?php if ( isset( $tgmpa_instance ) && isset( $tgmpa_instance->page_hook ) ) : ?>
					<li class="<?php echo esc_attr( $cur_route === 'vamtam_page_' . $routes[1] ? 'is-active' : '' ); ?>" >
						<a <?php echo esc_attr( ! $valid_key ? 'class=disabled' : '' ); ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[1] ) ); ?>">
							<span class="ts-icon dashicons dashicons-admin-plugins"></span>
							<span><?php echo esc_html__( 'Install Plugins' , 'vamtam-petmania' ); ?></span>
							<span class="vamtam-step-status <?php echo esc_attr( $valid_key ? $plugin_status : 'error' ); ?>"></span>
						</a>
					</li>
				<?php endif ?>
				<li class="<?php echo esc_attr( $cur_route === 'vamtam_page_' . $routes[2] ? 'is-active' : '' ); ?>" >
					<a <?php echo esc_attr( ! $valid_key || $plugin_status !== 'success' ? 'class=disabled' : '' ); ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[2] ) ); ?>">
						<svg class="ts-icon" xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><path fill-rule="evenodd" d="M25.6 25.6A15 15 0 0 0 4.4 4.4l2 2a12.2 12.2 0 1 1 0 17.2l-2 2a15 15 0 0 0 21.2 0zM0 13.7v2.8h16.7l-4.2 4.2 2 2 7.6-7.6-7.6-7.5-2 2 4.2 4.1H0z"/></svg>
						<span><?php echo esc_html__( 'Import Demo' , 'vamtam-petmania' ); ?></span>
						<span class="vamtam-step-status <?php echo esc_attr( $valid_key && $content_imported ? 'success' : 'error' ); ?>"></span>
					</a>
				</li>
				<li>
					<a id="vamtam-hs-btn" class="<?php echo esc_attr( $cur_route === 'vamtam_page_' . $routes[3] ? 'is-active' : ''); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $routes[3] ) ); ?>">
						<svg class="ts-icon" width="30" height="30" viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg"><path d="M208 352c114.9 0 208-78.8 208-176S322.9 0 208 0S0 78.8 0 176c0 38.6 14.7 74.3 39.6 103.4c-3.5 9.4-8.7 17.7-14.2 24.7c-4.8 6.2-9.7 11-13.3 14.3c-1.8 1.6-3.3 2.9-4.3 3.7c-.5 .4-.9 .7-1.1 .8l-.2 .2s0 0 0 0s0 0 0 0C1 327.2-1.4 334.4 .8 340.9S9.1 352 16 352c21.8 0 43.8-5.6 62.1-12.5c9.2-3.5 17.8-7.4 25.2-11.4C134.1 343.3 169.8 352 208 352zM448 176c0 112.3-99.1 196.9-216.5 207C255.8 457.4 336.4 512 432 512c38.2 0 73.9-8.7 104.7-23.9c7.5 4 16 7.9 25.2 11.4c18.3 6.9 40.3 12.5 62.1 12.5c6.9 0 13.1-4.5 15.2-11.1c2.1-6.6-.2-13.8-5.8-17.9c0 0 0 0 0 0s0 0 0 0l-.2-.2c-.2-.2-.6-.4-1.1-.8c-1-.8-2.5-2-4.3-3.7c-3.6-3.3-8.5-8.1-13.3-14.3c-5.5-7-10.7-15.4-14.2-24.7c24.9-29 39.6-64.7 39.6-103.4c0-92.8-84.9-168.9-192.6-175.5c.4 5.1 .6 10.3 .6 15.5z"/></svg>
						<span><?php echo esc_html__( 'Help & Support' , 'vamtam-petmania' ); ?></span>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( 'https://vamtam.com/services/' ); ?>" target="_blank">
						<svg class="ts-icon" xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 576 512"><path d="m309 106c11.4-7 19-19.7 19-34 0-22.1-17.9-40-40-40s-40 17.9-40 40c0 14.4 7.6 27 19 34l-57.3 114.6c-9.1 18.2-32.7 23.4-48.6 10.7l-89.1-71.3c5-6.7 8-15 8-24 0-22.1-17.9-40-40-40s-40 17.9-40 40 17.9 40 40 40h0.7l45.7 251.4c5.5 30.4 32 52.6 63 52.6h277.2c30.9 0 57.4-22.1 63-52.6l45.7-251.4h0.7c22.1 0 40-17.9 40-40s-17.9-40-40-40-40 17.9-40 40c0 9 3 17.3 8 24l-89.1 71.3c-15.9 12.7-39.5 7.5-48.6-10.7l-57.3-114.6z"/></svg>
						<span><?php echo esc_html__( 'Premium Services' , 'vamtam-petmania' ); ?></span>
					</a>
				</li>
			</ul>
			<div id="vamtam-menu-logo">
			<a href="https://vamtam.com" target="_blank" rel="noopener noreferrer">
				<svg viewBox="0 0 113 24" xmlns="http://www.w3.org/2000/svg"><path d="m20.602 1.2318c0.23665-0.51665 0.89715-0.52363 1.1179-0.038075 0.19214 0.42275 2.1699 4.774 4.1605 9.1531l0.33157 0.72945c1.9865 4.3702 3.8872 8.5518 3.9292 8.6441 0.22551 0.49608 0.89348 0.68976 1.2103-0.0072232 0.3128-0.68817 8.1933-17.945 8.3972-18.475 0.18572-0.48261 0.86997-0.55839 1.1107-0.037218 0.17604 0.3813 2.1863 4.7837 4.3113 9.4432l0.51824 1.1365c2.3052 5.0557 4.5815 10.055 4.6563 10.243 0.51603 1.2944-1.2931 1.8869-1.7721 0.82541-0.34035-0.754-1.8245-4.0201-3.3989-7.483l-0.31576-0.69452-0.3163-0.69565c-1.843-4.0533-3.6176-7.9543-3.6507-8.0263-0.25587-0.55533-0.92568-0.51028-1.1708-0.0044074-0.20653 0.42628-1.7911 3.9076-3.4547 7.5715l-0.50013 1.1018c-1.7774 3.9165-3.4968 7.714-3.5814 7.9063-0.45519 1.0348-2.1988 1.5308-2.9405-0.10088-1.0618-2.3359-7.3794-16.235-7.5168-16.537-0.19711-0.43364-0.8832-0.50367-1.1213 0.022772-0.26995 0.59659-1.9783 4.3824-1.9783 4.3824-0.60835 1.1448-2.3296 0.28256-1.7303-0.90352 1.0268-2.0324 3.6167-7.9636 3.7048-8.1562zm-20.068 0.1545c-0.52583-1.1907 1.237-1.9893 1.7642-0.79872 0.12341 0.27852 8.4909 18.649 8.7376 19.192 0.25098 0.55203 0.90376 0.54921 1.1449 0.031219 0.13896-0.29836 2.6265-5.8242 2.9352-6.4701 0.10884-0.22759 0.27583-0.43474 0.63014-0.43474h6.4128c1.249 0 1.4335 1.945-0.02069 1.9451h-4.5712c-0.94478 0-1.3379 0.69723-1.5179 1.0932-0.17997 0.39593-2.0475 4.6722-2.9047 6.4729-0.62757 1.3181-2.3503 1.5078-3.0612-0.05595-0.098598-0.21689-2.2165-4.8585-4.4366-9.7292l-0.34209-0.75057c-2.2255-4.8831-4.4547-9.78-4.7705-10.495zm57.406 6.9133h1.56l2.148 6.78h0.024l2.196-6.78h1.524l-2.928 8.568h-1.668zm10.936 0h1.596l3.3 8.568h-1.608l-0.804-2.268h-3.42l-0.804 2.268h-1.548zm-0.528 5.16h2.616l-1.284-3.684h-0.036zm7.216-5.16h2.112l2.364 6.708h0.024l2.304-6.708h2.088v8.568h-1.428v-6.612h-0.024l-2.376 6.612h-1.236l-2.376-6.612h-0.024v6.612h-1.428zm17.812 0v1.296h-2.724v7.272h-1.5v-7.272h-2.712v-1.296zm4.78 0 3.3 8.568h-1.608l-0.804-2.268h-3.42l-0.804 2.268h-1.548l3.288-8.568zm-0.792 1.476h-0.036l-1.296 3.684h2.616zm5.884-1.476h2.112l2.364 6.708h0.024l2.304-6.708h2.088v8.568h-1.428v-6.612h-0.024l-2.376 6.612h-1.236l-2.376-6.612h-0.024v6.612h-1.428z"/></svg>
			</a>
			</div>
		</nav>
		<?php
	}

	public static function import_buttons() {
		wp_enqueue_script( 'vamtam-import-buttons' );

		wp_localize_script( 'vamtam-import-buttons', 'vamtamImportButtonsVars', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'vamtam_attachment_progress' )
		));

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$content_allowed = defined( 'ELEMENTOR_PRO__FILE__' );

		$content_imported = ! ! get_option( 'vamtam_last_import_map', false );

		$messages = array(
			'success-msg' => esc_html__( 'Imported.', 'vamtam-petmania' ),
			'error-msg  ' => esc_html__( 'Failed to import. Please <a href="{fullimport}" target="_blank">click here</a> in order to see the full error message.', 'vamtam-petmania' ),
		);

		$import_tests = array(
			array(
				'test'   => defined( 'ELEMENTOR_PRO__FILE__' ),
				'title'  => esc_html__( 'Posts, Pages and Site Layout', 'vamtam-petmania' ),
				'failed' => wp_kses( __( "This theme requires Elementor Pro. If you don't have Elementor Pro, please <a href='https://be.elementor.com/visit/?bta=13981&nci=5383' target='_blank'>download it here</a>. Install and activate it, and then proceed with importing the demo content. If you have any issues with the importer please <a href='https://elementor.support.vamtam.com/support/solutions/articles/245218-vamtam-elementor-themes-how-to-install-the-theme-via-the-admin-panel-' target='_blank'>read this article</a> or reach out to us using <a href='https://vamtam.com/contact-us/' target='_blank'>the form on this page</a>.", 'vamtam-petmania' ), 'vamtam-a-span' ),
			),
		);

		$will_import = array();

		foreach ( $import_tests as $test ) {
			if ( ! $test['test'] ) {
				$will_import[] = '<li><div class="vamtam-message">' . $test['failed'] . '</div></li>';
			}
		}

		$attachments_todo   = get_option( 'vamtam_import_attachments_todo', [ 'attachments' => '' ] )['attachments'];
		$total_attachements = is_countable( $attachments_todo ) ? count( $attachments_todo ) : 0;

		$img_progress = $total_attachements > 0 && class_exists( 'Vamtam_Importers_E' ) && is_callable( [ 'Vamtam_Importers_E', 'get_attachment_progress' ] ) ?
			Vamtam_Importers_E::get_attachment_progress( $total_attachements )['text'] :
			esc_html__( 'checking...', 'vamtam-petmania' );

		$import_disabled_msg = empty( $will_import ) ? '' : '<div id="vamtam-recommended-plugins-notice" class="visible wide"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="50"><path fill-rule="evenodd" d="M7 33.3a5.4 5.4 0 01-5.4-5L0 5.8A5.1 5.1 0 011.1 2c.2-.2.5-.3.8-.1.2.2.3.5.1.8a4.1 4.1 0 00-.9 2.8l1.6 22.7a4.3 4.3 0 005 3.9c.3 0 .6.1.7.4 0 .3-.2.6-.5.7H7zm4.7-3.6h-.1a.6.6 0 01-.4-.7v-.7L13 5.6v-.3c0-2.3-2-4.2-4.3-4.2h-2A.6.6 0 016 .6c0-.4.3-.6.6-.6h2A5.4 5.4 0 0114 5.7l-1.6 22.7-.1.9c-.1.2-.3.4-.6.4zM7 50a6.2 6.2 0 01-6.2-6.1A6.2 6.2 0 1113 42.2c0 .3-.1.6-.4.7-.3 0-.6-.1-.7-.4a5.1 5.1 0 00-10 1.4 5 5 0 005.1 5 5 5 0 005-5c0-.3.3-.6.7-.6.3 0 .5.3.5.6 0 3.4-2.8 6.1-6.2 6.1z"/></svg><ul>' . implode( '<br>', $will_import ) . '</ul></div>';

		$buttons = array(
			array(
				'label'          => esc_html__( 'Dummy Content Import', 'vamtam-petmania' ),
				'id'             => 'content-import-button',
				'description'    => esc_html__( 'You are advised to use this importer only on new WordPress sites.', 'vamtam-petmania' ),
				'button_title'   => esc_html__( 'Import', 'vamtam-petmania' ),
				'href'           => $content_allowed ? wp_nonce_url( admin_url( 'admin.php?import=wpv&step=2' ), 'vamtam-import' ) : 'javascript:void( 0 )',
				'type'           => 'button',
				'class'          => $content_allowed ? 'button-primary vamtam-import-button' : 'disabled',
				'data'           => array_merge( $messages, [
					'content-imported' => $content_imported,
					'success-msg'      => sprintf( esc_html__( 'Main content imported. Image import progress: <span class="vamtam-image-import-progress">%s</span>.', 'vamtam-petmania' ), $img_progress ),
					'fail-msg'         => esc_html__( 'Failed to import. We recommend that you contact your hosting provider for advice, as solving this issue is often specific to each server.', 'vamtam-petmania' ),
					'timeout-msg'      => esc_html__( 'Failed to import. This is most likely caused by a timeout. Please contact your hosting provider for advice as to how you can increase the time limit on your server.', 'vamtam-petmania' ),
				] ),
				'additional_msg' => $import_disabled_msg . wp_kses( sprintf( __( '<p class="vamtam-description">Please make sure to <a href="%s" target="_blank">backup</a> any existing content that you need as it will be removed by the import procedure (affects Posts, Pages and Menus).</p><p class="vamtam-description">We recommend that you use the <a href="%s" target="_blank">Post Name permalink structure</a></p><p class="vamtam-description">Images will be downloaded in the background after the main import is complete. Depending on your server, this may take several minutes.<br> In the meantime you may notice that some images are not visible.', 'vamtam-petmania' ), esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=updraftplus&TB_iframe=true&width=772&height=921' ) ), esc_url( admin_url( 'options-permalink.php' ) ) ), 'vamtam-admin' ),
				'disabled_msg_plain' => '',
			),
		);

		echo '<div class="main-content">';

		VamtamDiagnostics::print();

		foreach ( $buttons as $button ) {
			self::render_button( $button );
		}

		echo '</div>';
	}

	public static function render_button( $button ) {
		echo '<div class="vamtam-box-wrap">';
		echo '<header><h3>' . esc_html( $button['label'] ) . '</h3></header>';

		$data = array();

		if ( isset( $button['data'] ) ) {
			foreach ( $button['data'] as $attr_name => $attr_value ) {
				$data[] = 'data-' . sanitize_title_with_dashes( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
			}
		}

		$data = implode( ' ', $data );

		echo '<div class="content">';

		if ( strpos( $button['class'], 'disabled' ) !== false ) {
			if ( isset( $button['disabled_msg'] ) ) {
				$href = isset( $button['disabled_msg_href'] ) ? $button['disabled_msg_href'] : admin_url( 'admin.php?page=tgmpa-install-plugins&plugin_status=required' );
				echo '<p class="vamtam-description">';
				if ( $href !== 'nolink' ) {
					echo '<a href="' . esc_html( $href ) . '">' . wp_kses_data( $button['disabled_msg'] ) . '</a>';
				} else {
					echo wp_kses_data( $button['disabled_msg'] );
				}
				echo '</p>';
			}

			if ( isset( $button['disabled_msg_plain'] ) ) {
				echo '<p class="vamtam-description">' . wp_kses_data( $button['disabled_msg_plain'] ) . '</p>';
			}
		} else {
			if ( isset( $button['description'] ) ) {
				echo '<p class="vamtam-description">' . wp_kses_data( $button['description'] ) . '</p>';
			}
			if ( isset( $button['warning'] ) ) {
				echo '<p class="vamtam-description warning">' . $button['warning'] . '</p>'; // xss ok
			}
		}

		if ( isset( $button['additional_msg'] ) ) {
			echo '<p class="vamtam-description">' . $button['additional_msg'] . '</p>'; // xss ok
		}

		echo '<div class="import-btn-wrap">';
		echo '<a href="' . ( isset( $button['href'] ) ? esc_attr( $button['href'] ) : '#' ) . '" id="' . esc_attr( $button['id'] ) . '" title="' . esc_attr( $button['button_title'] ) . '" class="button-primary vamtam-ts-button ' . esc_attr( $button['class'] ) . '" ' . $data . '>' . esc_html( $button['button_title'] ) . '</a>'; // xss ok - $data escaped above
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	public static function purchase_key( $args ) {
		$valid_key = Version_Checker::is_valid_purchase_code();
		$option_value = get_option( $args[0] );
		$placeholder = __( 'XXXXXX-XXX-XXXX-XXXX-XXXXXXXX', 'vamtam-petmania' );
		$plugin_status = VamtamPluginManager::get_required_plugins_status();
		$content_imported = ! ! get_option( 'vamtam_last_import_map', false );


		$button_data = '';

		$data = array(
			'nonce'     => wp_create_nonce( 'vamtam-check-license' ),
		);

		if ( ! defined( 'ENVATO_HOSTED_SITE' ) ) {
			echo '<div id="vamtam-check-license-result"></div>';
		}
		echo '<div class="vamtam-licence-wrap">';
		if ( $valid_key ) {
			echo '<span id="vamtam-license-result"';
			echo 'class="valid">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 30 30"><path fill-rule="evenodd" d="M30 15a15 15 0 1 1-30 0 15 15 0 0 1 30 0zm-2.7-4.4L15.7 22.3a1 1 0 0 1-1.4 0L7 13.7a1 1 0 0 1 1.4-1.3l6.6 7.7L26.5 8.7a13 13 0 1 0 .8 1.9z"/></svg>';
			esc_html_e( 'Valid', 'vamtam-petmania' );
			echo '</span>';
		} else {
			echo '<span id="vamtam-license-result-wrap">';
			echo '<span class="valid">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 30 30"><path fill-rule="evenodd" d="M30 15a15 15 0 1 1-30 0 15 15 0 0 1 30 0zm-2.7-4.4L15.7 22.3a1 1 0 0 1-1.4 0L7 13.7a1 1 0 0 1 1.4-1.3l6.6 7.7L26.5 8.7a13 13 0 1 0 .8 1.9z"/></svg>';
			esc_html_e( 'Valid', 'vamtam-petmania' );
			echo '</span>';
			echo '<span class="invalid">';
			echo '<span class="dashicons dashicons-no-alt"></span>';
			esc_html_e( 'Invalid', 'vamtam-petmania' );
			echo '</span>';
			echo '</span>';
		}
		echo '<input type="text" id="vamtam-envato-license-key" name="' . esc_attr( $args[0] ) . '" value="' . ( $valid_key && vamtam_sanitize_bool( $option_value ) ? esc_attr( $option_value ) : '' ) . '" size="64" ' . ( defined( 'SUBSCRIPTION_CODE' ) ? 'disabled' : '' ) . 'placeholder="' . $placeholder . '"' . '/>';
		if ( $valid_key ) {
			echo '<button id="vamtam-check-license" class="button button-primary unregister" data-nonce="'. esc_attr( $data['nonce'] ) .'">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 20 20"><path fill="white" d="M15.6 3.1h-4.1V1.5c0-.4-.2-.7-.4-1-.3-.2-.6-.3-1-.3H6.9c-.4 0-.7.1-1 .4-.2.2-.4.5-.4 1V3H1.4l-.5.2-.1.4.1.4c.2.2.3.2.5.2h.8L3.5 18c0 .3.2.5.5.8.2.2.5.3.8.3h7.4a1.2 1.2 0 0 0 1.2-1.2l1.4-13.7h.8c.2 0 .3 0 .5-.2l.1-.4-.1-.4a.6.6 0 0 0-.5-.2zM6.7 1.5v-.1h3.6V3H6.8V1.5zm7 2.8L12.2 18v.1H4.7L3.3 4.2h10.2z"/></svg>';
			echo '</button>';
		}
		echo '</div>';

		if ( ! defined( 'ENVATO_HOSTED_SITE' ) ) {
			echo '<span style="display: block">';

			if ( ! $valid_key ) {
				echo '<p id="vamtam-code-help">' .
					wp_kses( sprintf( __( ' <a href="%s" target="_blank">Where can I find my Item Purchase Code?</a>', 'vamtam-petmania' ), 'https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-' ), 'vamtam-a-span' ) .
					wp_kses( sprintf( __( ' <a href="%s" target="_blank">Cannot validate Purchase Code?</a>', 'vamtam-petmania' ), 'https://elementor.support.vamtam.com/support/solutions/articles/252289-cannot-validate-purchase-key-or-token' ), 'vamtam-a-span' ) .
				'</p>';

				echo '<p id="vamtam-code-help-elements" class="hidden">';
				echo wp_kses( sprintf( __( ' <a href="%s" target="_blank">Follow this link to generate a new Envato Elements Token.</a>', 'vamtam-petmania' ), esc_url( 'https://api.extensions.envato.com/extensions/begin_activation'
					. '?extension_id=' . md5( get_site_url() )
					. '&extension_type=envato-wordpress'
					. '&extension_description=' . wp_get_theme()->get( 'Name' ) . ' (' . get_home_url() . ')'
					) ), 'vamtam-a-span' );
				echo wp_kses( sprintf( __( ' <a href="%s" target="_blank">Cannot validate Token?</a>', 'vamtam-petmania' ), 'https://elementor.support.vamtam.com/support/solutions/articles/252289-cannot-validate-purchase-key-or-token' ), 'vamtam-a-span' );
				echo '</p>';

				echo '<button id="vamtam-check-license" class="button button-primary" ';

				foreach ( $data as $key => $value ) {
					echo ' data-' . $key . '="' . esc_attr( $value ) . '"';
				}

				echo '>' . esc_html__( 'Register', 'vamtam-petmania' );
				echo '</button>';
			} else if ( $plugin_status !== 'success' ) {
				echo '<a id="vamtam-plugin-step" class="button-primary vamtam-ts-button" href="' . esc_url( admin_url( 'admin.php?page=tgmpa-install-plugins' ) ) . '">';
				echo esc_html__( 'Continue to required plguins', 'vamtam-petmania' );
				echo '</a>';
			} elseif ( ! $content_imported ) {
				echo '<a id="vamtam-import-step" class="button-primary vamtam-ts-button" href="' . esc_url( admin_url( 'admin.php?page=vamtam_theme_setup_import_content' ) ) . '">';
				echo esc_html__( 'Continue to demo import', 'vamtam-petmania' );
				echo '</a>';
			}

			echo '</span>';
		}
	}
}
