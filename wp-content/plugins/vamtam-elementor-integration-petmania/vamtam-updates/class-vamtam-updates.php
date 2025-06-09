<?php

if ( class_exists( 'Vamtam_Updates_4' ) ) {
	return;
}

class Vamtam_Updates_4 {
	private $slug;
	private $main_file;
	private $full_path;

	private $api_url;

	public function __construct( $file ) {
		$this->slug      = basename( dirname( $file ) );
		$this->main_file = trailingslashit( $this->slug ) . basename( $file );
		$this->full_path = $file;

		$this->api_url = 'https://updates.vamtam.com/0/envato/check';

		// delete_site_transient( 'update_plugins' );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ), 999 );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 999, 3 );
		add_filter( 'plugin_action_links_' . $this->main_file, array( $this, 'remove_update_action' ), 999);
		add_action( 'after_plugin_row', array( $this, 'update_handler' ), 999, 3);
		add_filter( 'plugin_row_meta', array( $this, 'vamtam_plugin_row_meta' ), 10, 4);
		add_filter( 'admin_url', array( $this, 'plugin_admin_url_on_updates_page' ), 10, 1 );
		add_filter( 'upgrader_pre_download', array( $this, 'plugin_upgrade_no_pkg' ), 10, 3 );
	}

	public function plugin_upgrade_no_pkg( $reply, $package, $upgrader ) {
		if ( isset( $upgrader->skin->plugin_info ) && false !== $upgrader->skin->plugin_info ) {
			$plugin_name = $upgrader->skin->plugin_info[ 'Name' ];
			if ( $plugin_name === get_plugin_data( $this->full_path )[ 'Name' ] && empty( $package ) ) {
				return new WP_Error( 'no_package', __( 'Only Envato Market clients with a valid purchase code are entitled to automatic updates. Envato Elements clients must use FTP.', 'wpv' ) );
			}
		}

		return $reply;
	}

	public function plugin_admin_url_on_updates_page( $url ) {
		global $pagenow;

		if ( $pagenow === 'update-core.php' ) {
			if  ( strpos( $url, 'plugin-install.php?tab=plugin-information' ) !== false && strpos( $url, $this->slug ) !== false ) {
				$url = 'https://vamtam.com/changelog';
			}
		}
		return $url;
	}

	public function vamtam_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( $plugin_file === $this->main_file ) {
			foreach ( $plugin_meta as $key => $meta ) {
				if ( strpos( $meta, 'View details' ) !== false ) {
					unset( $plugin_meta[ $key ] );
				} else if ( strpos( $meta, 'By <a' ) !== false ) {
					$plugin_meta[ $key ] = str_replace( 'By <a', 'By <a target="_blank"', $meta );
				}
			}
		}

		return $plugin_meta;
	}

	public function remove_update_action( $links ) {
		unset( $links['update'] ); // tgm filters this
		unset( $links['upgrade'] );
		return $links;
	}

	public function update_handler( $plugin_file, $plugin_data, $status ) {
		if ( $plugin_file === $this->main_file ) {
			remove_all_actions( "after_plugin_row_{$plugin_file}" );
			if ( defined( 'VAMTAM_ENVATO_THEME_ID' ) ) {
				add_action( "after_plugin_row_{$plugin_file}", array( $this, 'version_update_notice'), 10, 2);
			}
		}
	}

	public function version_update_notice( $plugin_file, $plugin_data ){
		$plugin_file   = $this->main_file;
		$valid_key     = Version_Checker::is_valid_purchase_code();
		$is_token      = get_option( VamtamFramework::get_token_option_key() );
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$new_version   = $plugin_data[ 'new_version' ] ?? null;
		$name          = $plugin_data[ 'Name' ];

		if ( ! $new_version ) {
			return;
		}

		$plugin_active = in_array( $plugin_file, get_option( 'active_plugins', [] ) );
		$active_class  = $plugin_active ? ' active' : '';

		?>
		<tr class="plugin-update-tr<?php echo esc_attr( $active_class ); ?>" id="<?php echo esc_attr( $this->slug . '-update' ); ?>" data-slug="<?php echo esc_attr( $this->slug ); ?>" data-plugin="<?php echo esc_attr( $this->main_file ); ?>">
            <td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="plugin-update colspanchange">
                <div class="update-message notice inline notice-warning notice-alt">
				<p>
					<?php echo __( 'There is a new version of ' . $name . ' available ' . '(<strong>' . $new_version . '</strong>).', 'wpv'); ?>
					<a href="<?php echo esc_url( 'https://vamtam.com/changelog/' ); ?> " target="_blank">
						<?php echo esc_html__( ' View changelog', 'wpv' ); ?>
					</a>
				</p>
				<?php
					if ( ! $valid_key || $is_token ) {
						?>
							<hr class="vamtam-update-warning__separator" />
							<div class="vamtam-update-warning">
								<div class="vamtam-update-warning__icon">
									<span class="dashicons dashicons-warning"></span>
								</div>
								<div>
									<div class="vamtam-update-warning__title">
										<?php
											if ( ! $valid_key ) {
												echo esc_html__( 'Heads up, no valid license found!', 'wpv' );
											} else {
												echo esc_html__( 'Heads up, you\'re missing out on automatic updates!', 'wpv' );
											}
										?>
									</div>
									<div class="vamtam-update-warning__message">
										<?php
											if ( ! $valid_key ) {
												echo '<div>';
												echo esc_html__('Please activate your license to get automatic plugin updates!', 'wpv');
												echo '</div>';
												?>
													<a class="button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ); ?>">
														<?php echo esc_html__( 'Register Now', 'wpv' ); ?>
													</a>
												<?php
											} else {
												echo esc_html__('Note: Only Envato Market clients with a valid purchase code are entitled to automatic updates. Envato Elements clients must use FTP.', 'wpv');
											}
											?>
									</div>
								</div>
							</div>
						<?php
					} else {
						?>
						<a class="button-primary vamtam-update-btn" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin_file, 'upgrade-plugin_' . $plugin_file ) ); ?>">
							<?php echo esc_html__( 'Update Now', 'wpv' ); ?>
						</a>
						<?php
					}
				?>
                </div>
			</td>
        </tr>
		<?php
	}

	public function check( $updates ) {
		$response = $this->api_request();

		if ( false === $response ) {
			return $updates;
		}

		if ( ! isset( $updates->response ) ) {
			$updates->response = array();
		}

		$updates->response = array_merge( $updates->response, $response );

		// Small trick to ensure the updates get shown in the network admin
		if( is_multisite() && ! is_main_site() ) {
			global $current_site;

			switch_to_blog( $current_site->blog_id );
			set_site_transient( 'update_plugins', $updates );
			restore_current_blog();
		}

		return $updates;
	}

	public function plugins_api( $data, $action = '', $args = null ) {
		if ( 'plugin_information' !== $action ) {
			return $data;
		}

		if ( ! isset( $args->slug ) || ( $args->slug !== $this->slug ) ) {
			return $data;
		}

		$data = new stdClass;

		return $data;
	}

	private function api_request() {
		global $wp_version;

		$update_cache = get_site_transient( 'update_plugins' );

		$plugin_data = get_plugin_data( $this->full_path );

		$raw_response = wp_remote_post( $this->api_url, array(
			'body' => array(
				'slug' => $this->slug,
				'main_file' => $this->main_file,
				'version' => $plugin_data[ 'Version' ],
				'purchase_key' => apply_filters( 'wpv_purchase_code', '' ),
				'theme_name' => defined( 'VAMTAM_THEME_NAME' ) ? VAMTAM_THEME_NAME : '',
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
		) );

		if ( is_wp_error( $raw_response ) || 200 !== wp_remote_retrieve_response_code( $raw_response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );
		foreach ( $response['plugins'] as &$plugin ) {
			$plugin = (object) $plugin;
		}
		unset( $plugin );

		return $response['plugins'];
	}
}
