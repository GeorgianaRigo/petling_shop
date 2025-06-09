<?php

/**
 * Enqueue styles and scripts used by the theme
 *
 * @package vamtam/petmania
 */

/**
 * class VamtamEnqueues
 */
class VamtamEnqueues {
	private static $use_min;

	private static $widget_styles = array(
		'WP_Nav_Menu_Widget'       => 'nav-menu',
		'WP_Widget_Tag_Cloud'      => 'tagcloud',
		'WP_Widget_RSS'            => 'rss',
		'WP_Widget_Search'         => 'search',
		'WC_Widget_Product_Search' => 'search',
		'WP_Widget_Calendar'       => 'calendar',
	);

	/**
	 * Hook the relevant actions
	 */
	public static function actions() {
		self::$use_min = ! ( WP_DEBUG || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'VAMTAM_SCRIPT_DEBUG' ) && VAMTAM_SCRIPT_DEBUG ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ), 999 );

		add_action( 'wp_print_styles', array( __CLASS__, 'print_theme_options' ), 1 );
		add_action( 'wp_print_styles', array( __CLASS__, 'reorder_elementor_stylesheets' ), 999 );

		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );

		add_action( 'wp', array( __CLASS__, 'wp' ) );

		if ( ! is_admin() ) {
			add_action( 'the_widget', array( __CLASS__, 'widget_styles' ) );
			add_action( 'dynamic_sidebar', array( __CLASS__, 'widget_styles_dynamic_sidebar' ) );
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_styles' ), 999 );

		add_action( 'wp_ajax_vamtam_dismiss_admin_notice', array( __CLASS__, 'handle_dismiss_admin_notice' ) );
	}

	private static function is_our_admin_page() {
		if ( ! is_admin() ) return false;

		$screen = get_current_screen();

		return
			in_array( $screen->base, array( 'post', 'widgets', 'themes', 'upload' ) ) ||
			strpos( $screen->base, 'vamtam_' ) !== false ||
			strpos( $screen->base, 'toplevel_page_vamtam' ) === 0 ||
			strpos( $screen->base, 'toplevel_page_vamtam' ) === 0 ||
			$screen->base === 'media_page_vamtam_icons';
	}

	private static function inject_dependency_common( $all_deps, $handle, $dep ) {
		$script = $all_deps->query( $handle, 'registered' );

		if ( ! $script )
			return false;

		if ( ! in_array( $dep, $script->deps ) ) {
			$script->deps[] = $dep;
		}

		return true;
	}

	private static function inject_dependency_script( $handle, $dep ) {
		self::inject_dependency_common( $GLOBALS['wp_scripts'], $handle, $dep );
	}

	private static function inject_dependency_style( $handle, $dep ) {
		self::inject_dependency_common( $GLOBALS['wp_styles'], $handle, $dep );
	}

	/**
	 * Prints the <link> tag immediately after enqueueing the style
	 *
	 * @param  string $handle passed to wp_enqueue_style
	 */
	public static function enqueue_style_and_print( $handle ) {
		wp_enqueue_style( $handle );

		// print late styles, otherwise Beaver will skip over some of them
		if ( ! doing_filter( 'get_the_excerpt' ) ) {
			print_late_styles();
		}
	}

	/**
	 * Front-end scripts
	 */
	public static function scripts() {
		global $content_width;

		if ( is_admin() ) return;

		$cache_timestamp = get_option( 'vamtam-css-cache-timestamp' );

		if ( is_singular() && comments_open() ) {
			wp_enqueue_script( 'comment-reply', false, false, false, true );
		}

		$all_js_path = self::$use_min ? 'all.min.js' : 'all.js';
		$all_js_deps = array(
			'jquery',
		);

		if ( current_user_can( 'edit_posts' ) &&
			VamtamElementorBridge::elementor_is_v3_24_or_greater() &&
			! is_admin() &&
			! \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			$all_js_deps = array_merge( $all_js_deps, [ 'elementor-app-loader', 'elementor-webpack-runtime' ] );
		}

		wp_enqueue_script( 'vamtam-all', VAMTAM_JS . $all_js_path, $all_js_deps, $cache_timestamp, true );

		self::inject_dependency_script( 'wc-cart-fragments', 'vamtam-all' );

		$script_vars = array(
			'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
			'jspath'                   => VAMTAM_JS,
			'max_breakpoint'           => VamtamElementorBridge::get_site_breakpoints( 'lg' ),
			'medium_breakpoint'        => VamtamElementorBridge::get_site_breakpoints( 'md' ),
			'content_width'            => (int) $content_width,
			'enable_ajax_add_to_cart'  => get_option( 'woocommerce_enable_ajax_add_to_cart' ),
			'widget_mods_list'         => VamtamElementorBridge::get_widget_mods_list(),
		);


		wp_localize_script( 'vamtam-all', 'VAMTAM_FRONT', $script_vars );

		wp_register_script( 'vamtam-fallback', VAMTAM_JS . 'fallback.js', [ 'vamtam-all', 'masonry' ], $cache_timestamp, true );

		wp_register_script( 'vamtam-wc-gallery', VAMTAM_JS . 'wc-gallery' . ( self::$use_min ? '.min' : '' ) . '.js', [ 'vamtam-all' ], $cache_timestamp, ['strategy' => 'defer'] );
	}

	/**
	 * Admin scripts
	 */
	public static function admin_scripts() {
		self::services_link();
		self::notice_dismiss();
		self::updates_page_extra_js();

		$cache_timestamp = VamtamFramework::get_version();

		wp_enqueue_script( 'vamtam-admin-all-pages', VAMTAM_ADMIN_ASSETS_URI . 'js/admin-all-pages.js', array( 'jquery' ), $cache_timestamp, true );

		if ( ! self::is_our_admin_page() ) return;

		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'editor' );
		wp_enqueue_script( 'jquery-ui-tabs' );

		wp_enqueue_script( 'farbtastic' );

		wp_enqueue_media();

		wp_enqueue_script( 'vamtam-admin', VAMTAM_ADMIN_ASSETS_URI . 'js/admin-all.js', array( 'jquery', 'underscore', 'backbone' ), $cache_timestamp, true );

		wp_localize_script(
			'vamtam-admin', 'VAMTAM_ADMIN', array(
				'addNewIcon' => esc_html__( 'Add New Icon', 'vamtam-petmania' ),
				'iconName'   => esc_html__( 'Icon', 'vamtam-petmania' ),
				'iconText'   => esc_html__( 'Text', 'vamtam-petmania' ),
				'iconLink'   => esc_html__( 'Link', 'vamtam-petmania' ),
				'iconChange' => esc_html__( 'Change', 'vamtam-petmania' ),
				'fonts'      => $GLOBALS['vamtam_fonts'],
				'home_url'   => home_url(),
				'elementsTxt' => esc_html__( 'Enter your Envato Elements Token.', 'vamtam-petmania' ),
				'tfPcTxt' => esc_html__( 'Enter your purchase code from ThemeForest to receive theme updates and support.', 'vamtam-petmania' ),
				'unRegPcTxt' => esc_html__( 'You are about to unregister your purchase code. Are you sure you want to continue?', 'vamtam-petmania' ),
				'unRegTokenTxt' => esc_html__( 'You are about to unregister your Envato Elements Token. Are you sure you want to continue?', 'vamtam-petmania' ),
				'isElementsToken' => get_option( VamtamFramework::get_token_option_key() )
			)
		);
	}

	public static function updates_page_extra_js() {
		global $pagenow;

		if ( $pagenow === 'update-core.php' ) {
			?>
			<script type="text/javascript">
				document.addEventListener( 'DOMContentLoaded', function() {
					// Changelog links.
					jQuery( 'a[href*="https://vamtam.com/changelog"]' ).each( function() {
						jQuery( this ).attr( 'target', '_blank' ).removeClass( 'thickbox' );
					} );
					<?php
					$valid_pc = Version_Checker::is_valid_purchase_code() && ! get_option( VamtamFramework::get_token_option_key() );
					if ( ! $valid_pc ) {
						$update_notice  = '<br id="vamtam-notice-br" />' . __( 'Note: Only Envato Market clients with a valid purchase code are entitled to automatic updates. Envato Elements clients must use FTP.', 'vamtam-petmania' );
						$changelog_link = '<span><a href="https://vamtam.com/changelog" target="_blank">' . __('View Changelog', 'vamtam-petmania') . '</a></span>';
						$theme_name     = wp_get_theme()->get_template();
						?>
						// Plugins update text.
						jQuery( '#update-plugins-table a[href="https://vamtam.com/changelog"]' ).each( function() {
							const added = jQuery( this ).find( '#vamtam-notice-br' ).length > 0;
							if ( ! added ) {
								jQuery( this ).parent().append( '<?php echo $update_notice; ?>' );
							}
						} );
						// Theme update text.
						jQuery( '#update-themes-table img[src*="themes/<?php echo $theme_name ?>"]' ).parent().append( '<?php echo "{$changelog_link}{$update_notice}"; ?>' );
						<?php
					}
					?>
				} );
			</script>
			<?php
		}
	}

	public static function services_link() {
		?>
		<script type="text/javascript">
			document.addEventListener( 'DOMContentLoaded', function() {
				const $services_link = jQuery( 'a[href="admin.php?page=vamtam_theme_services"' );
				if ( $services_link.length ) {
					$services_link.on( 'click', function( event ) {
						event.preventDefault(); // Prevent the default link behavior
						window.open( 'https://vamtam.com/services', '_blank' );
					} );
				}
			} );
		</script>
		<?php
	}

	public static function handle_dismiss_admin_notice() {
		// check nonce
		check_ajax_referer( 'vamtam_dismiss_admin_notice', $_POST['_wpnonce'] );

		// Check if the user is allowed to perform this action
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
			wp_die();
		}

		$notice_type = isset( $_POST['notice_type'] ) ? sanitize_text_field( $_POST['notice_type'] ) : false;

		if ( ! isset( $notice_type ) ) {
			wp_send_json_error();
			wp_die();
		}

		$option_name = 'vamtam_dismissed_' . $notice_type . '_notice';

		// set option that expires
		set_transient( $option_name, true, 2 * DAY_IN_SECONDS ); // 2 days

		wp_send_json_success();

		wp_die();
	}

	public static function notice_dismiss() {
		?>
		<script type="text/javascript">
			document.addEventListener( 'DOMContentLoaded', function() {
				jQuery( document ).on( 'click', '.vamtam-notice .notice-dismiss', function() {
					const $this = jQuery( this );
					const $notice = $this.closest( '.is-dismissible' );

					const notice_type = $notice[0].classList.value.match( /vamtam-([^\-]+)-notice/ );

					if ( !! notice_type ) {
						jQuery.ajax( {
							url: window.ajaxurl,
							type: 'POST',
							data: {
								action: 'vamtam_dismiss_admin_notice',
								notice_type: notice_type[1],
								_wpnonce: '<?php echo wp_create_nonce( 'vamtam_dismiss_admin_notice' ); ?>',
							}
						} );
					}
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Front-end styles
	 */
	public static function styles() {
		$is_fallback = ! vamtam_extra_features();

		if ( $is_fallback ) {
			self::enqueue_fallback_styles();
		} else {
			self::enqueue_elementor_styles();
		}
	}

	public static function enqueue_elementor_styles() {
		global $content_width;

		if ( is_admin() ) {
			return;
		}

		$cache_timestamp      = get_option( 'vamtam-css-cache-timestamp' );
		$is_wc_page           = vamtam_has_woocommerce() && ( is_woocommerce() || is_shop() || is_cart() || is_checkout() || is_account_page() || is_checkout_pay_page() || is_view_order_page() );
		$is_account           = vamtam_has_woocommerce() && is_account_page();
		$built_with_elementor = VamtamElementorBridge::is_build_with_elementor( $check_body_class = ! $is_account );
		$medium_breakpoint    = VamtamElementorBridge::get_site_breakpoints( 'lg' );
		$small_breakpoint     = VamtamElementorBridge::get_site_breakpoints( 'md' );
		$generated_deps       = [];

		// For pure WC pages we also enqueue the WC-related styles.
		if ( $is_wc_page && ! $built_with_elementor ) {
			$generated_deps[] = 'woocommerce-layout';
			$generated_deps[] = 'woocommerce-smallscreen';
			$generated_deps[] = 'woocommerce-general';

			if ( wp_style_is( 'jquery-ui-style', 'enqueued' ) && vamtam_has_woocommerce() ) {
				wp_dequeue_style( 'jquery-ui-style' );
				wp_deregister_style( 'jquery-ui-style' );

				wp_enqueue_style( 'jquery-ui-style', VAMTAM_ASSETS_URI . 'css/src/smoothness.css' );

				self::inject_dependency_style( 'wc-bookings-styles', 'jquery-ui-style' );
			}
		}

		wp_enqueue_style( 'vamtam-front-all', VAMTAM_ASSETS_URI . 'css/dist/elementor/elementor-all.css', $generated_deps, $cache_timestamp );

		wp_add_inline_style( 'vamtam-front-all', self::get_custom_fonts_css() );

		// content paddings
		ob_start();

		wp_add_inline_style( 'vamtam-front-all', ob_get_clean() );

		$responsive_stylesheets = array(
			'elementor-max-low'   => "(min-width: {$medium_breakpoint}px) and (max-width: {$content_width}px)",
			'elementor-max'       => "(min-width: {$medium_breakpoint}px)",
			'elementor-below-max' => '(max-width: ' . ( $medium_breakpoint - 1 ) . 'px)',
			'elementor-small'     => '(max-width: ' . ( $small_breakpoint - 1 ) . 'px)',
		);

		$url_prefix  = VAMTAM_ASSETS_URI . 'css/dist/elementor/responsive/';
		$file_prefix = VAMTAM_ASSETS_DIR . 'css/dist/elementor/responsive/';

		foreach ( $responsive_stylesheets as $file => $media ) {
			if ( file_exists( $file_prefix . $file . '.css' ) ) {
				wp_enqueue_style( 'vamtam-theme-'. $file, $url_prefix . $file . '.css', array( 'vamtam-front-all' ), $cache_timestamp, $media );
			}
		}
	}

	public static function enqueue_fallback_styles() {
		global $content_width;

		if ( is_admin() ) {
			return;
		}

		$cache_timestamp = get_option( 'vamtam-css-cache-timestamp' );

		wp_register_style( 'vamtam-not-found', VAMTAM_ASSETS_URI . 'css/dist/fallback/not-found.css' , array( 'vamtam-front-all' ), $cache_timestamp );
		wp_register_style( 'vamtam-blog', VAMTAM_ASSETS_URI . 'css/dist/fallback/blog.css' , array( 'vamtam-front-all' ), $cache_timestamp );
		wp_register_style( 'vamtam-header', VAMTAM_ASSETS_URI . 'css/dist/fallback/header.css' , array( 'vamtam-front-all' ), $cache_timestamp );
		wp_register_style( 'vamtam-theme-mobile-header', VAMTAM_ASSETS_URI . 'css/dist/fallback/responsive/mobile-header.css' , array( 'vamtam-front-all' ), $cache_timestamp, '(max-width: 959px)' );
		wp_register_style( 'vamtam-wc-cart-checkout', VAMTAM_ASSETS_URI . 'css/dist/fallback/woocommerce/cart-checkout.css' , array( 'vamtam-front-all' ), $cache_timestamp );
		wp_register_style( 'vamtam-wc-styles', VAMTAM_ASSETS_URI . 'css/dist/fallback/woocommerce/main.css' , array( 'vamtam-front-all' ), $cache_timestamp );

		$generated_deps = array();

		if ( vamtam_has_woocommerce() ) {
			$generated_deps[] = 'woocommerce-layout';
			$generated_deps[] = 'woocommerce-smallscreen';
			$generated_deps[] = 'woocommerce-general';

			if ( is_cart() || is_checkout() ) {
				wp_enqueue_style( 'vamtam-wc-cart-checkout' );
			}

			if ( wp_style_is( 'jquery-ui-style', 'enqueued' ) && vamtam_has_woocommerce() ) {
				wp_dequeue_style( 'jquery-ui-style' );
				wp_deregister_style( 'jquery-ui-style' );

				wp_enqueue_style( 'jquery-ui-style', VAMTAM_ASSETS_URI . 'css/src/smoothness.css' );

				self::inject_dependency_style( 'wc-bookings-styles', 'jquery-ui-style' );
			}

			wp_enqueue_style( 'vamtam-wc-styles' );
		}

		wp_enqueue_style( 'vamtam-front-all', VAMTAM_ASSETS_URI . 'css/dist/fallback/all.css', $generated_deps, $cache_timestamp );

		wp_add_inline_style( 'vamtam-front-all', self::get_custom_fonts_css() );

		// content paddings
		ob_start();

		$medium_breakpoint = VamtamElementorBridge::get_site_breakpoints( 'lg' );
		$small_breakpoint  = VamtamElementorBridge::get_site_breakpoints( 'md' );

		include VAMTAM_FB_CSS_DIR . 'outer-whitespace.php';

		wp_add_inline_style( 'vamtam-front-all', ob_get_clean() );

		$responsive_stylesheets = array(
			'layout-max-low'   => "(min-width: {$medium_breakpoint}px) and (max-width: {$content_width}px)",
			'layout-max'       => "(min-width: {$medium_breakpoint}px)",
			'layout-below-max' => '(max-width: ' . ( $medium_breakpoint - 1 ) . 'px)',
			'layout-small'     => '(max-width: ' . ( $small_breakpoint - 1 ) . 'px)',
		);

		$url_prefix = VAMTAM_ASSETS_URI . 'css/dist/fallback/responsive/';
		foreach ( $responsive_stylesheets as $file => $media ) {
			wp_enqueue_style( 'vamtam-theme-'. $file, $url_prefix . $file . '.css', array( 'vamtam-front-all' ), $cache_timestamp, $media );
		}

		if ( vamtam_has_woocommerce() ) {
			$wc_small_screen_media = 'only screen and (max-width: ' . apply_filters( 'woocommerce_style_smallscreen_breakpoint', $breakpoint = '768px' ) . ')';
			wp_enqueue_style( 'vamtam-theme-wc-small-screen', $url_prefix . 'wc-small-screen.css', array( 'vamtam-front-all' ), $cache_timestamp, $wc_small_screen_media );
		}

		wp_register_style( 'vamtam-widgets-general', VAMTAM_ASSETS_URI . 'css/dist/fallback/widgets/general.css' , array( 'vamtam-front-all' ), $cache_timestamp );

		foreach ( array_unique( self::$widget_styles ) as $class => $file ) {
			wp_register_style( 'vamtam-widget-' . $file, VAMTAM_ASSETS_URI . 'css/dist/fallback/widgets/' . $file . '.css' , array( 'vamtam-front-all', 'vamtam-widgets-general' ), $cache_timestamp );
		}
	}

	/**
	 * Gutenberg styles
	 */
	public static function admin_init() {
		add_editor_style( 'vamtam/assets/css/dist/fallback/editor.css' );
		add_filter('tiny_mce_before_init', array( __CLASS__, 'add_theme_options_to_editor') );
	}

	public static function add_theme_options_to_editor( $mceInit ) {
		// On Elementor editor, we need to add the theme options to TinyMCE.
		if ( ! VamtamElementorBridge::is_build_with_elementor() ) {
			return $mceInit;
		}

		ob_start();

		self::print_theme_options();

		$styles = preg_replace("/\r|\n/", "", strip_tags( ob_get_clean() ) ); // No new lines, no double quotes.

		if ( isset( $mceInit['content_style'] ) ) {
			$mceInit['content_style'] .= ' ' . $styles . ' ';
		} else {
			$mceInit['content_style'] = $styles . ' ';
		}

		return $mceInit;
	}

	/**
	 * wp action callback
	 */
	public static function wp() {
		add_editor_style( 'vamtam/assets/css/dist/fallback/editor.css' );
	}

	/**
	 * Enqueue widget styles, hooked to the_widget
	 */
	public static function widget_styles( $widget ) {
		// this one is for all widgets, anywhere
		wp_enqueue_style( 'vamtam-widgets-general' );

		// some widgets have their own style sheets
		if ( isset( self::$widget_styles[ $widget ] ) ) {
			wp_enqueue_style( 'vamtam-widget-' . self::$widget_styles[ $widget ] );
		}

		// avoids FOUT
		if ( ! doing_filter( 'get_the_excerpt' ) ) {
			print_late_styles();
		}
	}

	/**
	 * Enqueue widget styles, hooked to dynamic_sidebar
	 */
	public static function widget_styles_dynamic_sidebar( $widget ) {
		self::widget_styles( get_class( $widget['callback'][0] ) );
	}

	/**
	 * Admin styles
	 */
	public static function admin_styles() {
		wp_enqueue_style( 'vamtam-admin-all', VAMTAM_ADMIN_ASSETS_URI . 'css/vamtam-admin-all.css' );

		if ( ! self::is_our_admin_page() ) return;

		$cache_timestamp = VamtamFramework::get_version();

		wp_enqueue_style( 'vamtam-admin', VAMTAM_ADMIN_ASSETS_URI . 'css/vamtam-admin.css' );
		wp_enqueue_style( 'farbtastic' );

		wp_enqueue_style( 'vamtam-gfonts', vamtam_get_option( 'google_fonts' ), array(), $cache_timestamp );

		wp_add_inline_style( 'vamtam-admin', self::get_custom_fonts_css() );

		self::print_theme_options();
	}

	/**
	 * Generates the @font-face blocks for any custom fonts
	 *
	 * @return string
	 */
	public static function get_custom_fonts_css() {
		$font_faces = '';
		//Custom theme icons
		$font_faces .= self::get_theme_icons_css();

		//Custom Elementor icons
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$font_faces .= self::get_custom_elementor_fonts_css();
		}

		return $font_faces;
	}

	/**
	 * Generates the @font-face blocks for the custom Elementor fonts
	 *
	 * @return string
	 */
	public static function get_custom_elementor_fonts_css() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) || ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return '';
		}

		if ( ! class_exists( 'ElementorPro\Modules\AssetsManager\AssetTypes\Fonts_Manager' ) ) {
			return ''; // Elementor's autoloader acts weird sometimes.
		}

		$elementor_custom_fonts = get_option( ElementorPro\Modules\AssetsManager\AssetTypes\Fonts_Manager::FONTS_OPTION_NAME, false );

		if ( empty( $elementor_custom_fonts ) ) {
			return '';
		}

		$font_faces = '';
		foreach ( $elementor_custom_fonts as $font ) {
			$font_faces .= $font['font_face'];
		}

		return PHP_EOL . str_replace( 'font-display: auto', 'font-display: swap', $font_faces );
	}


	/**
	 * Generates the @font-face blocks for the icons fonts
	 *
	 * @return string
	 */
	public static function get_theme_icons_css() {
		$theme_url       = VAMTAM_THEME_URI;
		$theme_icons_css = "
			@font-face {
				font-family: 'icomoon';
				src: url({$theme_url}vamtam/assets/fonts/icons/icomoon.woff2) format('woff2'),
					 url( {$theme_url}vamtam/assets/fonts/icons/icomoon.woff) format('woff'),
					 url({$theme_url}vamtam/assets/fonts/icons/icomoon.ttf) format('ttf');
				font-weight: normal;
				font-style: normal;
				font-display: swap;
			}
		";

		$theme_icons_css .= "
			@font-face {
				font-family: 'vamtam-theme';
				src: url({$theme_url}vamtam/assets/fonts/theme-icons/theme-icons.woff2) format('woff2'),
					url({$theme_url}vamtam/assets/fonts/theme-icons/theme-icons.woff) format('woff');
				font-weight: normal;
				font-style: normal;
				font-display: swap;
			}
		";

		return $theme_icons_css;
	}

	public static function print_theme_options() {
		$options = [];

		if ( class_exists( 'VamtamElementorBridge' ) && VamtamElementorBridge::is_elementor_active() ) {
			$options = VamtamLessBridge::prepare_vars_for_export( VamtamElementorBridge::get_translated_kit() );
		} elseif ( ! is_admin() ) {
			wp_enqueue_style( 'vamtam-elementor-fallback', VAMTAM_THEME_URI . 'samples/elementor-styles-fallback.css', [], VamtamFramework::get_version() );
			wp_enqueue_style( 'vamtam-elementor-fallback-hardcoded', VAMTAM_THEME_URI . 'samples/elementor-styles-fallback-hardcoded.css', [], VamtamFramework::get_version() );
		}

		echo '<style id="vamtam-theme-options">';

		echo 'body {';

		foreach ( $options as $name => $value ) {
			echo '--vamtam-' . esc_html( $name ) . ':' . wp_kses_data( $value ) . ";\n";
		}

		echo "--vamtam-loading-animation:url('" . esc_attr( VAMTAM_IMAGES . 'loader-ring.gif') . "');\n";

		echo '}';

		echo '</style>';
	}

	/**
	 * Re-ordering $wp_styles->queue so default kit styles get printed directly before widget styles.
	 *
	 * @todo Remove when fixed: https://github.com/elementor/elementor/issues/29139
	 */
	public static function reorder_elementor_stylesheets() {
		global $wp_styles;

		if ( ! VamtamElementorBridge::is_elementor_active() ) {
			return false;
		}

		$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $active_kit_id ) {
			// Active kit not found. nothing to do.
			return false;
		}

		$kit_styles_id = "elementor-post-{$active_kit_id}";

		// Check if the style is registered.
		if ( ! isset( $wp_styles->registered[ $kit_styles_id ] ) ) {
			return false;
		}

		// Properly indexed styles queue.
		$old_queue = array_values( $wp_styles->queue );

		// Find the position of the first widget- style.
		$widget_pos = false;
		foreach ( $old_queue as $index => $handle ) {
			if ( 0 === strpos( $handle, 'widget-' ) ) {
				$widget_pos = $index;
				break;
			}
		}

		if ( false !== $widget_pos ) {
			// Remove kit styles from its current position.
			$kit_styles_pos = array_search( $kit_styles_id, $old_queue );
			if ( false !== $kit_styles_pos && $kit_styles_pos > $widget_pos ) {
				unset( $old_queue[ $kit_styles_pos ] );
			} else {
				// Kit styles are already before the first widget- style.
				return false;
			}

			// Re-index the array.
			$new_queue = array_values( $old_queue );

			// Insert kit at the target index.
			array_splice( $new_queue, $widget_pos, 0, $kit_styles_id );

			// Replace wp styles queue with the new one.
			$wp_styles->queue = $new_queue;
		}
	}
}


