<?php

class VamtamDiagnostics {
	private static $test_results = [];

	public function __construct() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ), 5 );
	}

	public static function notice() {
		if ( get_transient( 'vamtam_dismissed_diagnostics_notice' ) ) {
			return;
		}

		$tests = VamtamDiagnostics::tests();

		if ( empty( $tests ) || get_current_screen()->id !== 'dashboard' ) {
			return;
		}

		?>
		<div class="vamtam-ts-notice">
			<div class="vamtam-notice vamtam-diagnostics-notice warning notice is-dismissible">
				<div class="vamtam-notice-aside">
					<div class="vamtam-notice-icon-wrapper">
						<img id="vamtam-logo" src="<?php echo esc_attr( VAMTAM_ADMIN_ASSETS_URI . 'images/vamtam-logo.png' ); ?>"></img>
					</div>
				</div>
				<div class="vamtam-notice-content">
					<?php self::print_content() ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function print() {
?>
		<div class="vamtam-box-wrap vamtam-diagnostics-box">
			<header>
				<h3><?php esc_html_e( 'Diagnostics', 'vamtam-petmania' ); ?></h3>
			</header>
			<div class="content">
				<?php VamtamDiagnostics::print_content() ?>
			</div>
		</div>
<?php
	}

	public static function print_content() {
		$tests = VamtamDiagnostics::tests();

		if ( ! empty( $tests ) ) :
			if ( doing_action( 'admin_notices' ) ) : ?>
				<h3><?php esc_html_e( "We detected some problems with your server. This may cause parts of the demo content to not work as expected:", 'vamtam-petmania' ) ?></h3>
			<?php else : ?>
				<div>
					<span class="dashicons dashicons-warning" style="color:#D03032"></span>
					<?php esc_html_e( "We detected some problems with your server. Please resolve them before importing the demo content:", 'vamtam-petmania' ) ?>
				</div>
			<?php endif ?>

			<table>
				<?php foreach( $tests as $id => $test ) : ?>
					<tr id="<?= esc_attr( $id ) ?>" data-pass="<?= esc_attr( $test['pass'] ) ?>">
						<td><strong><?= $test['title'] ?></strong></td>
						<td class="result"><?= $test['result'] ?></td>
						<td>
							<?php if ( ! $test['pass'] ) : ?>
								<?= $test['msg'] // xss ok ?>
							<?php endif ?>
						</td>
					</tr>
				<?php endforeach ?>
			</table>
		<?php else : ?>
			<div>
				<span class="dashicons dashicons-yes-alt" style="color:#039406"></span>
				<?php esc_html_e( "We haven't detected any problems with your server. You may proceed with the demo content import", 'vamtam-petmania' ); ?>
			</div>
		<?php endif;
	}

	private static function is_https() {
		if ( array_key_exists("HTTPS", $_SERVER) && 'on' === $_SERVER["HTTPS"] ) {
			return true;
		}

		if ( array_key_exists("SERVER_PORT", $_SERVER) && 443 === (int)$_SERVER["SERVER_PORT"] ) {
			return true;
		}

		if ( array_key_exists("HTTP_X_FORWARDED_SSL", $_SERVER) && 'on' === $_SERVER["HTTP_X_FORWARDED_SSL"] ) {
			return true;
		}

		if ( array_key_exists("HTTP_X_FORWARDED_PROTO", $_SERVER) && 'https' === $_SERVER["HTTP_X_FORWARDED_PROTO"] ) {
			return true;
		}

		return false;
	}

	/**
	 * @return bool true if mixed content detected
	 */
	private static function mixed_content_test() {
		// this page was loaded over https
		if ( self::is_https() ) {
			global $wpdb;

			// home or site url is not using https
			if ( ! wp_is_using_https() ) {
				return 'Settings/General';
			}

			$home_url_raw = '%' . str_replace( 'https', 'http', get_option( 'home' ) ) . '%';
			$site_url_raw = '%' . str_replace( 'https', 'http', get_option( 'siteurl' ) ) . '%';
			$home_url_raw_json = substr( json_encode( $home_url_raw ), 1, -1 );
			$site_url_raw_json = substr( json_encode( $site_url_raw ), 1, -1 );

			$faulty_options = $wpdb->get_col( $wpdb->prepare( "
					select option_name from $wpdb->options where
					option_name not in (
						'_transient_woocommerce_blocks_asset_api_script_data',
						'vamtam_import_attachments_url_remap',
						'vamtam_attachments_imported'
					)
					and
					(
						option_value like %s or option_value like %s or
						option_value like %s or option_value like %s
					)
				",
					[
					  $home_url_raw, $home_url_raw_json,
					  $site_url_raw, $site_url_raw_json,
					]
				) );

			if ( count( $faulty_options ) > 0 ){
				return "$wpdb->options table (options: " . implode( ', ', $faulty_options ) . ")";
			}


			if ( (int) $wpdb->get_var( $wpdb->prepare( "
					select count(*) from $wpdb->postmeta where
					meta_value like %s or meta_value like %s or
					meta_value like %s or meta_value like %s
				",
					[ $home_url_raw, $home_url_raw_json,
					  $site_url_raw, $site_url_raw_json ]
				) )
			) {
				return "$wpdb->postmeta table";
			}

			if ( (int) $wpdb->get_var( $wpdb->prepare( "
					select count(*) from $wpdb->posts where
					post_content like %s or post_content like %s or
					post_content like %s or post_content like %s
				",
					[ $home_url_raw, $home_url_raw_json,
					  $site_url_raw, $site_url_raw_json ]
				) )
			) {
				return "post content";
			}
		}

		return false;
	}

	private static function memory_in_mbytes( $memory ) {
		return (int)preg_replace_callback( '/(\-?\d+)(.?)/', function ( $m ) {
			return $m[1] * pow( 1024, strpos( 'BKMG', $m[2] ) );
		}, strtoupper( $memory ) ) / 1024 / 1024;
	}

	private static function test_cron_spawn() {
		global $wp_version;

		$cached_status = get_transient( 'vamtam-cron-test-ok' );

		if ( $cached_status ) {
			return true;
		}

		$sslverify     = version_compare( $wp_version, '4.0', '<' );
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );

		$cron_request = apply_filters( 'cron_request', array(
			'url'  => add_query_arg( 'doing_wp_cron', $doing_wp_cron, site_url( 'wp-cron.php' ) ),
			'key'  => $doing_wp_cron,
			'args' => array(
				'timeout'   => 3,
				'blocking'  => true,
				'sslverify' => apply_filters( 'https_local_ssl_verify', $sslverify ),
			),
		), $doing_wp_cron );

		$result = wp_remote_post( $cron_request['url'], $cron_request['args'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		} elseif ( wp_remote_retrieve_response_code( $result ) >= 300 ) {
			return new WP_Error( intval( wp_remote_retrieve_response_code( $result ) ), sprintf(
				esc_html__( 'Unexpected HTTP response code: %s', 'vamtam-petmania' ),
				intval( wp_remote_retrieve_response_code( $result ) )
			) );
		} else {
			set_transient( 'vamtam-cron-test-ok', 1, 3600 );
			return true;
		}
	}

	public static function tests( $full = false ) {
		if ( ! empty( self::$test_results ) ) {
			if ( ! $full ) {
				return array_filter( self::$test_results, function( $test ) {
					return ! $test['pass'];
				} );
			}

			return self::$test_results;
		}

		$phpversion         = phpversion();
		$phpversion_minimum = '8.0';

		$mixed_content = self::mixed_content_test();

		$has_mbstring = extension_loaded('mbstring');
		$has_zip      = extension_loaded('zip');

		self::$test_results = [
			'phpversion' => [
				'title'  => esc_html__( 'PHP Version', 'vamtam-petmania' ),
				'result' => $phpversion,
				'pass'   => version_compare( $phpversion, $phpversion_minimum, '>=' ),
				'msg'    => sprintf( esc_html__( 'PHP version %s is below %s, which is the minimum recommended', 'vamtam-petmania' ), $phpversion, $phpversion_minimum ),
			],
			'mixedcontent' => [
				'title'  => esc_html__( 'Mixed content', 'vamtam-petmania' ),
				'result' => $mixed_content ? "Detected in $mixed_content" : 'passed',
				'pass'   => ! $mixed_content,
				'msg'    => esc_html__( 'This page was loaded over HTTPS, however URLs using HTTP were found in the database. Please replace all HTTP links with their HTTPS equivalents.', 'vamtam-petmania' ),
			],
			'mbstring' => [
				'title'  => esc_html__( 'mbstring extension', 'vamtam-petmania' ),
				'result' => $has_mbstring ? 'active' : 'inactive',
				'pass'   => $has_mbstring,
				'msg'    => wp_kses_post( __( 'The <strong>mbstring</strong> extension must be enabled for all features to work correctly. Please ask your hosting provider to enable this if you cannot do it yourself. Most servers already have mbstring installed, even if it is disabled by default.', 'vamtam-petmania' ) ),
			],
			'zip' => [
				'title'  => esc_html__( 'zip extension', 'vamtam-petmania' ),
				'result' => $has_zip ? 'active' : 'inactive',
				'pass'   => $has_zip,
				'msg'    => wp_kses_post( __( 'The <strong>zip</strong> extension must be enabled before uploading custom icons. Please ask your hosting provider to enable this if you cannot do it yourself. Most servers already have this extension installed, even if it is disabled by default.', 'vamtam-petmania' ) ),
			],
		];

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/class-wp-site-health.php' );
		}

		if ( class_exists( 'WP_Site_Health' ) ) {
			self::$test_results['memory_frontend'] = [
				'title'  => esc_html__( 'Memory limit (frontend)', 'vamtam-petmania' ),
				'result' => WP_Site_Health::get_instance()->php_memory_limit,
				'pass'   => self::memory_in_mbytes( WP_Site_Health::get_instance()->php_memory_limit ) >= 256,
				'msg'    => esc_html__( 'The memory limit for this site is too low, we recommend a minimum of 256MB. Please contact your hosting provider if you are unsure how to change this.', 'vamtam-petmania' ),
			];
		}

		if ( function_exists( 'ini_get' ) ) {
			$post_max_size       = ini_get( 'post_max_size' );
			$upload_max_filesize = ini_get( 'upload_max_filesize' );
			$memory_limit        = ini_get( 'memory_limit' );

			self::$test_results = array_merge( self::$test_results, [
				'memory' => [
					'title'  => esc_html__( 'Memory limit (admin)', 'vamtam-petmania' ),
					'result' => $memory_limit,
					'pass'   => self::memory_in_mbytes( $memory_limit ) >= 256,
					'msg'    => esc_html__( 'The memory limit for this site is too low, we recommend a minimum of 256MB. Please contact your hosting provider if you are unsure how to change this.', 'vamtam-petmania' ),
				],
				'post_max_size' => [
					'title'  => esc_html__( 'Post Max Size', 'vamtam-petmania' ),
					'result' => $post_max_size,
					'pass'   => self::memory_in_mbytes( $post_max_size ) >= 32,
					'msg'    => esc_html__( 'post_max_size is too low, we recommend setting it to at least 32MB to avoid problems with large pages. Please contact your hosting provider if you are unsure how to change this.', 'vamtam-petmania' ),
				],
				'upload_max_filesize' => [
					'title'  => esc_html__( 'Upload Max File Size', 'vamtam-petmania' ),
					'result' => $upload_max_filesize,
					'pass'   => self::memory_in_mbytes( $upload_max_filesize ) >= 32,
					'msg'    => esc_html__( 'upload_max_filesize is too low, we recommend setting it to at least 32MB so that you can upload large images and videos. Please contact your hosting provider if you are unsure how to change this.', 'vamtam-petmania' ),
				],
			] );
		}

		$attachments_todo     = get_option( 'vamtam_import_attachments_todo' );
		$attachments_imported = get_option( 'vamtam_attachments_imported', [] );

		$attachments_todo = is_array( $attachments_todo ) && is_array( $attachments_todo['attachments'] ) ? $attachments_todo['attachments'] : [];

		$not_yet_imported = is_countable( $attachments_todo ) ? count( $attachments_todo ) : 0;
		$already_imported = is_countable( $attachments_imported ) ? count( $attachments_imported ) : 0;

		if ( $already_imported === 0 || $not_yet_imported > 0 ) {
			$cron_runner_plugins = array(
				'\HM\Cavalcade\Plugin\Job'         => 'Cavalcade',
				'\Automattic\WP\Cron_Control\Main' => 'Cron Control',
			);

			foreach ( $cron_runner_plugins as $class => $plugin ) {
				if ( class_exists( $class ) ) {
					self::$test_results['cron_plugin'] = [
						'title'  => esc_html__( 'WP-Cron replacement', 'vamtam-petmania' ),
						'result' => $plugin,
						'pass'   => false,
						'msg'    => esc_html__( 'WP-Cron is managed by the %s plugin. This is usually not an issue. However, please disable the plugin if you have any problems importing the demo content images.', 'vamtam-petmania' ),
					];
					break;
				}
			}

			$wp_cron_disabled  = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
			$alternate_wp_cron = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;

			self::$test_results = array_merge( self::$test_results, [
				'disable_wp_cron' => [
					'title'  => esc_html__( 'WP-Cron active', 'vamtam-petmania' ),
					'result' => $wp_cron_disabled ? 'no' : 'yes',
					'pass'   => ! $wp_cron_disabled,
					'msg'    => wp_kses_post( __( 'The <strong>DISABLE_WP_CRON</strong> constant is set to true. This will prevent the demo content images from being imported.', 'vamtam-petmania' ) ),
				],
				'alternate_wp_cron' => [
					'title'  => esc_html__( 'Standard WP-Cron', 'vamtam-petmania' ),
					'result' => $alternate_wp_cron ? 'no' : 'yes',
					'pass'   => ! $alternate_wp_cron,
					'msg'    => wp_kses_post( __( 'The <strong>ALTERNATE_WP_CRON</strong> constant is set to true. Non-standard WP-Cron may prevent the demo content images from being imported.', 'vamtam-petmania' ) ),
				],
				'basicauth' => [
					'title'  => esc_html__( 'Basic Auth', 'vamtam-petmania' ),
					'result' => isset( $_SERVER['REMOTE_USER'] ) ? $_SERVER['REMOTE_USER'] : 'none',
					'pass'   => ! isset( $_SERVER['REMOTE_USER'] ) || empty( $_SERVER['REMOTE_USER'] ),
					'msg'    => wp_kses_post( sprintf( __( 'Basic access authentication detected. Please ensure that <strong>%s</strong>. <strong>%s</strong>, and <strong>%s</strong> are accessible without a password.', 'vamtam-petmania' ), get_option( 'home' ), admin_url( 'admin-ajax.php' ), site_url( 'wp-cron.php' ) ) ),
				],
			] );

			$cron_test = self::test_cron_spawn();

			if ( is_wp_error( $cron_test ) ) {
				self::$test_results['cron_spawn'] = [
					'title'  => esc_html__( 'WP-Cron test call', 'vamtam-petmania' ),
					'result' => $cron_test->get_error_code(),
					'pass'   => false,
					'msg'    => wp_kses_post( sprintf( __( "We couldn't perform a test call to WP-Cron on this server. We received the following error message when attempting to load %s: <strong>%s</strong><br><br>Please contact your hosting provider for assistance.", 'vamtam-petmania' ), site_url( 'wp-cron.php' ), $cron_test->get_error_message() ) ),
				];
			}
		}

		if ( ! $full ) {
			return array_filter( self::$test_results, function( $test ) {
				return ! $test['pass'];
			} );
		}
		return self::$test_results;
	}
}