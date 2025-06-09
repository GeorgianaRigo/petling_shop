<?php

/*
Plugin Name: VamTam Importers (E)
Description: This plugin is used in order to import the sample content for VamTam themes
Version: 1.3.4
Author: VamTam
Author URI: http://vamtam.com
*/

class Vamtam_Importers_E {
	public static $import_attachments;

	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ), 1 );
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_action( 'vamtam_attacments_import_completed', array( __CLASS__, 'elementor_remap' ) );
		add_action( 'wp_ajax_vamtam_attachment_progress', [ __CLASS__, 'vamtam_attachment_progress' ] );

		if ( ! class_exists( 'Vamtam_Updates_4' ) ) {
			require 'vamtam-updates/class-vamtam-updates.php';
		}

		new Vamtam_Updates_4( __FILE__ );
	}

	public static function admin_init() {
		add_action( 'vamtam_before_content_import', array( __CLASS__, 'before_content_import' ) );
		add_action( 'vamtam_after_content_import', array( __CLASS__, 'after_content_import' ) );

		require_once 'importers/importer/importer.php';
		require 'importers/revslider/importer.php';
		require 'importers/ninja-forms/importer.php';

		if ( isset( $_GET['vamtam_continue_import' ] ) ) {
			self::queue_attachments_for_import();
		}
	}

	public static function plugins_loaded() {
		include 'wp-background-process/wp-async-request.php';
		include 'wp-background-process/wp-background-process.php';
		include 'vamtam-import-attachments.php';

		self::$import_attachments = new Vamtam_Import_Attachments();
	}

	/**
	 * Initialize thumbnail generation
	 */
	protected static function process_attachments() {
		delete_option( 'vamtam_import_attachments_url_remap' );
		update_option( 'vamtam_import_attachments_done', 0 );

		self::queue_attachments_for_import();
	}

	protected static function queue_attachments_for_import() {
		$attachments = get_option( 'vamtam_import_attachments_todo' );

		foreach ( $attachments['attachments'] as $attachment_data ) {
			self::$import_attachments->push_to_queue( [
				'data' => [
					'attachment' => $attachment_data,
					'base_url'   => $attachments['base_url'],
				],
			] );
		}

		self::$import_attachments->save()->dispatch();
	}

	public static function get_attachment_progress( $total_to_import ) {
		$imported       = get_option( 'vamtam_attachments_imported', [] );
		$total_imported = is_countable( $imported ) ? count( $imported ) : 0;
		$remaining      = $total_to_import - $total_imported;

		$text = $remaining > 0 ?
			sprintf( esc_html__( '%d remaining', 'wpv' ), $remaining ) :
			esc_html__( 'all done', 'wpv' );

		return compact( 'text', 'remaining' );
	}

	public static function vamtam_attachment_progress() {
		check_ajax_referer( 'vamtam_attachment_progress' );

		$attachments = get_option( 'vamtam_import_attachments_todo' )['attachments'];

		$total = is_countable( $attachments ) ? count( $attachments ) : 0;

		$progress = self::get_attachment_progress( $total );

		$response['vamtam_attachment_import_progress'] = $progress['text'];

		// Done
		if ( $progress['remaining'] <= 0 ) {
			$response['done'] = true;
		}

		echo wp_send_json( $response );

		wp_die();
	}

	public static function process_post_additional_data( $post, $post_id, $post_exists, $processed_authors, &$featured_images = null ) {
		$comment_post_ID = $post_id;

		// take care of the duplicated metadata stored by the woocommerce-ajax-filters plugin

		delete_post_meta( $post_id, 'br_filters_group' );
		delete_post_meta( $post_id, 'br_product_filter' );

		if ( ! isset( $post['terms'] ) ) {
			$post['terms'] = array();
		}

		$post['terms'] = apply_filters( 'wp_import_post_terms', $post['terms'], $post_id, $post );

		// add categories, tags and other terms
		if ( ! empty( $post['terms'] ) ) {
			$terms_to_set = array();
			foreach ( $post['terms'] as $term ) {
				// back compat with WXR 1.0 map 'tag' to 'post_tag'
				$taxonomy = ( 'tag' == $term['domain'] ) ? 'post_tag' : $term['domain'];
				$term_exists = term_exists( $term['slug'], $taxonomy );
				$term_id = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;
				if ( ! $term_id ) {
					$t = wp_insert_term( $term['name'], $taxonomy, array( 'slug' => $term['slug'] ) );
					if ( ! is_wp_error( $t ) ) {
						$term_id = $t['term_id'];
						do_action( 'wp_import_insert_term', $t, $term, $post_id, $post );
					} else {
						printf( __( 'Failed to import %s %s', 'wordpress-importer' ), esc_html($taxonomy), esc_html($term['name']) );
						if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
							echo ': ' . $t->get_error_message();
						echo '<br />';
						do_action( 'wp_import_insert_term_failed', $t, $term, $post_id, $post );
						continue;
					}
				}
				$terms_to_set[$taxonomy][] = intval( $term_id );
			}

			foreach ( $terms_to_set as $tax => $ids ) {
				$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
				do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $post );
			}
			unset( $post['terms'], $terms_to_set );
		}

		if ( ! isset( $post['comments'] ) )
			$post['comments'] = array();

		$post['comments'] = apply_filters( 'wp_import_post_comments', $post['comments'], $post_id, $post );

		// add/update comments
		if ( ! empty( $post['comments'] ) ) {
			$num_comments = 0;
			$inserted_comments = array();
			foreach ( $post['comments'] as $comment ) {
				$comment_id	= $comment['comment_id'];
				$newcomments[$comment_id]['comment_post_ID']      = $comment_post_ID;
				$newcomments[$comment_id]['comment_author']       = $comment['comment_author'];
				$newcomments[$comment_id]['comment_author_email'] = $comment['comment_author_email'];
				$newcomments[$comment_id]['comment_author_IP']    = $comment['comment_author_IP'];
				$newcomments[$comment_id]['comment_author_url']   = $comment['comment_author_url'];
				$newcomments[$comment_id]['comment_date']         = $comment['comment_date'];
				$newcomments[$comment_id]['comment_date_gmt']     = $comment['comment_date_gmt'];
				$newcomments[$comment_id]['comment_content']      = $comment['comment_content'];
				$newcomments[$comment_id]['comment_approved']     = $comment['comment_approved'];
				$newcomments[$comment_id]['comment_type']         = $comment['comment_type'];
				$newcomments[$comment_id]['comment_parent'] 	  = $comment['comment_parent'];
				$newcomments[$comment_id]['commentmeta']          = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();

				if ( isset( $processed_authors[$comment['comment_user_id']] ) )
					$newcomments[$comment_id]['user_id'] = $processed_authors[$comment['comment_user_id']];
			}
			ksort( $newcomments );

			foreach ( $newcomments as $key => $comment ) {
				// if this is a new post we can skip the comment_exists() check
				if ( ! $post_exists || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
					if ( isset( $inserted_comments[$comment['comment_parent']] ) )
						$comment['comment_parent'] = $inserted_comments[$comment['comment_parent']];
					$comment = wp_filter_comment( $comment );
					$inserted_comments[$key] = wp_insert_comment( $comment );
					do_action( 'wp_import_insert_comment', $inserted_comments[$key], $comment, $comment_post_ID, $post );

					foreach( $comment['commentmeta'] as $meta ) {
						$value = maybe_unserialize( $meta['value'] );
						add_comment_meta( $inserted_comments[$key], $meta['key'], $value );
					}

					$num_comments++;
				}
			}
			unset( $newcomments, $inserted_comments, $post['comments'] );
		}

		if ( ! isset( $post['postmeta'] ) ) {
			$post['postmeta'] = array();
		}

		$post['postmeta'] = apply_filters( 'wp_import_post_meta', $post['postmeta'], $post_id, $post );

		// add/update post meta
		if ( ! empty( $post['postmeta'] ) ) {
			foreach ( $post['postmeta'] as $meta ) {
				$key = apply_filters( 'import_post_meta_key', $meta['key'], $post_id, $post );
				$value = false;

				if ( '_edit_last' == $key ) {
					if ( isset( $processed_authors[intval($meta['value'])] ) ) {
						$value = $processed_authors[intval($meta['value'])];
					} else {
						$key = false;
					}
				}

				if ( $key ) {
					// export gets meta straight from the DB so could have a serialized string
					if ( ! $value ) {
						$value = maybe_unserialize( $meta['value'] );
					}

					if ( is_string( $value ) ) {
						$result = json_decode( $value );
						if ( json_last_error() === JSON_ERROR_NONE ) {
							// usage of wp_slash()/wp_json_encode copied from Elementor\Core\Base\Document::save_elements()
							// do not change without checking what Elementor does
							$value = wp_slash( $value );
						}
					}

					add_post_meta( $post_id, $key, $value ); // always add, don't update - because of the woocommerce-ajax-filters plugin

					do_action( 'import_post_meta', $post_id, $key, $value );

					// if the post has a featured image, take note of this in case of remap
					if ( '_thumbnail_id' == $key && is_array( $featured_images ) ) {
						printf( __( 'Setting featured image for %s', 'wpv' ), $post_id );
						$featured_images[$post_id] = (int) $value;
					}
				}
			}
		}
	}

	public static function before_content_import() {
		wp_suspend_cache_invalidation( true );

		self::generic_option_import( 'jetpack', array( __CLASS__, 'jetpack_import' ) );
		self::generic_option_import( 'the-events-calendar', array( __CLASS__, 'tribe_events_import' ) );
		self::generic_option_import( 'instagram-feed', [ __CLASS__, 'instagram_feed_import' ] );

		self::generic_option_import( 'elementor-settings' );

		self::check_remove_elementor_wc_hooks();
		self::generic_option_import( 'woocommerce-settings' );
		self::generic_option_import( 'wpc-smart-wishlist' );
		self::generic_option_import( 'ajax-search-for-woocommerce' );
		self::generic_option_import( 'woocommerce-ajax-filters' );
		self::generic_serialized_option_import( 'woo-extra-product-options' );

		self::wc_attributes();

		self::clear_initial_content();

		wp_suspend_cache_invalidation( false );
	}

	/*
		Elementor Pro v3.5 added some update_option hooks to WC settings for updating the kit's WC settings.
		Triggering these hooks now will result in Invalid Post Exception and hang the import process.
	*/
	public static function check_remove_elementor_wc_hooks() {
		if ( \VamtamElementorBridge::elementor_pro_is_v3_5_or_greater() ) {
			// They use anonymous functions so we can't remove them by function name reference.
			remove_all_actions( 'update_option_woocommerce_cart_page_id', 10 );
			remove_all_actions( 'update_option_woocommerce_checkout_page_id', 10 );
			remove_all_actions( 'update_option_woocommerce_myaccount_page_id', 10 );
			remove_all_actions( 'update_option_woocommerce_terms_page_id', 10 );
		}
	}

	// Deletes initial content so it can't conflict with the import procedure.
	public static function clear_initial_content() {
		// Posts
		$posts = get_posts( array(
			'numberposts' => -1,
			'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
		) );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
		// Pages
		$pages = get_posts( array(
			'post_type'   => 'page',
			'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			'numberposts' => -1,
		) );
		foreach ( $pages as $page ) {
			wp_delete_post( $page->ID, true );
		}
		// Revisions
		$revs = get_posts( array(
			'post_type'   => 'revision',
			'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			'numberposts' => -1,
		) );
		foreach ( $revs as $rev ) {
			wp_delete_post( $rev->ID, true );
		}
		// Nav Menus
		$menus = get_posts( array(
			'post_type'   => 'nav_menu_item',
			'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			'numberposts' => -1,
		) );
		foreach ( $menus as $menu ) {
			wp_delete_post( $menu->ID, true );
		}
	}

	public static function after_content_import() {
		wp_set_sidebars_widgets( [
			'wp_inactive_widgets' => [],
			'array_version' => 3,
		] );

		$map = get_option( 'vamtam_last_import_map' );

		$wpforms = get_option( 'wpforms_settings', [] );
		$wpforms['disable-css'] = 2;
		update_option( 'wpforms_settings', $wpforms );

		self::process_attachments();

		self::elementor_import();

		self::check_remove_elementor_wc_hooks();
		self::elementor_remap();

		self::wc_remap();

		self::wc_booking();

		self::wpc_smart_wishlist_remap();

		self::give_wp( $map );

		self::woocommerce_ajax_filters_remap();
	}

	public static function set_menu_locations() {
		$map  = get_option( 'vamtam_last_import_map', false );
		$path = VAMTAM_SAMPLES_DIR . 'theme-mods.json';

		if ( $map && ! get_theme_mod( 'vamtam_force_demo_menu', false ) && file_exists( $path ) ) {
			$theme_mods = json_decode( file_get_contents( $path ), true );

			if ( isset( $theme_mods['nav_menu_locations'] ) ) {
				foreach ( $theme_mods['nav_menu_locations'] as $location => $term_id ) {
					if ( isset( $map['terms'][ (int)$term_id ] ) ) {
						$theme_mods['nav_menu_locations'][ $location ] = $map['terms'][ (int)$term_id ];
					}
				}
			}

			foreach ( $theme_mods as $opt_name => $mod_val ) {
				set_theme_mod( $opt_name, $mod_val );
			}

			set_theme_mod( 'vamtam_force_demo_menu', true );
		}
	}

	public static function generic_option_import( $file, $callback = null ) {
		$path = VAMTAM_SAMPLES_DIR . $file . '.json';

		if ( file_exists( $path ) ) {
			$settings = json_decode( file_get_contents( $path ), true );

			foreach ( $settings as $opt_name => $opt_val ) {
				update_option( $opt_name, $opt_val );
			}

			if ( ! is_null( $callback ) ) {
				call_user_func( $callback );
			}
		}
	}

	public static function generic_serialized_option_import( $file, $callback = null ) {
		$path = VAMTAM_SAMPLES_DIR . $file . '.ser.json';

		if ( file_exists( $path ) ) {
			$settings = json_decode( file_get_contents( $path ), true );

			foreach ( $settings as $i => $opt ) {
				update_option( $opt[ 'option_name' ], maybe_unserialize( $opt[ 'option_value' ] ) );
			}

			if ( ! is_null( $callback ) ) {
				call_user_func( $callback );
			}
		}
	}

	public static function instagram_feed_import() {
		$opts = [];
		if ( function_exists( 'sbi_get_database_settings' ) ) {
			$opts = sbi_get_database_settings();
		} else {
			$opts = get_option( 'sb_instagram_settings', [] );
		}

		if ( isset( $opts['connected_accounts'] ) ) {
			foreach ( $opts['connected_accounts'] as $acc => $acc_data) {
				$username = $acc_data['username'];
				if ( $username === 'vamtam.themes' ) {
					$url = $acc_data['profile_picture'];
					if ( ! empty( $url ) ) {
						$folder_name = '';
						if ( ! defined( 'SBI_UPLOADS_NAME' ) ) {
							$folder_name = 'sb-instagram-feed-images';
						} else {
							$folder_name = SBI_UPLOADS_NAME;
						}

						$upload         = wp_upload_dir();
						$full_file_name = trailingslashit( $upload['basedir'] ) . trailingslashit( $folder_name ) . $username  . '.jpg';
						$samples_logo   = VAMTAM_SAMPLES_DIR . 'vamtam.themes.jpg';

						// Get profile pic from fb/insta.
						$image_editor = wp_get_image_editor( $url );
						if ( ! is_wp_error( $image_editor ) ) {
							$saved_image = $image_editor->save( $full_file_name );

							if ( ! $saved_image ) {
								// Try local.
								if ( file_exists( $samples_logo ) ) {
									$image_editor = wp_get_image_editor( $samples_logo );

									if ( ! is_wp_error( $image_editor ) ) {
										$saved_image = $image_editor->save( $full_file_name );
									}
								}
							}
						} else {
							// Try local.
							if ( file_exists( $samples_logo ) ) {
								$image_editor = wp_get_image_editor( $samples_logo );

								if ( ! is_wp_error( $image_editor ) ) {
									$saved_image = $image_editor->save( $full_file_name );
								}
							}
						}

						break;
					}
				}
			}
		}
	}

	public static function jetpack_import() {
		Jetpack::load_modules();

		if ( class_exists( 'Jetpack_Portfolio' ) ) {
			Jetpack_Portfolio::init()->register_post_types();
		}

		if ( class_exists( 'Jetpack_Testimonial' ) ) {
			Jetpack_Testimonial::init()->register_post_types();
		}
	}

	public static function elementor_import() {
		$conditions = get_option( 'elementor_pro_theme_builder_conditions' );
		$map        = get_option( 'vamtam_last_import_map', false );

		foreach ( $conditions as $location => $cnd ) {
			$new_cnd = [];

			foreach ( $cnd as $old_id => $cnd_path_arr ) {

				// Update condition paths.
				foreach ( $cnd_path_arr as $i => $val ) {
					// No id.
					if ( ! preg_match( '/(?<=\/)\d+$/', $val, $m ) ) {
						continue;
					}

					if ( preg_match( '/(^.*(in_category.*|in_post_tag|product_cat|in_product_tag)\/)(\d+)$/', $val ) ) {
						// terms.
						if ( isset( $map['terms'][ (int) $m[0] ] ) ) {
							$cnd_path_arr[ $i ] = str_replace( $m[0], $map['terms'][ (int) $m[0] ], $val );
						}
					} elseif ( preg_match( '/(^.*by_author\/)(\d+)$/', $val ) ) {
						// authors.
						if ( isset( $map['authors'][ (int) $m[0] ] ) ) {
							$cnd_path_arr[ $i ] = str_replace( $m[0], $map['authors'][ (int) $m[0] ], $val );
						}
					} else {
						// posts.
						if ( isset( $map['posts'][ (int) $m[0] ] ) ) {
							$cnd_path_arr[ $i ] = str_replace( $m[0], $map['posts'][ (int) $m[0] ], $val );
						}
					}
				}

				// Update condition id.
				$new_cnd[ $map['posts'][ (int) $old_id ] ] = $cnd_path_arr;
			}

			$conditions[ $location ] = $new_cnd;
		}

		update_option( 'elementor_pro_theme_builder_conditions', $conditions );

		$path = VAMTAM_SAMPLES_DIR . 'elementor-settings.json';

		if ( file_exists( $path ) ) {
			$settings = json_decode( file_get_contents( $path ), true );

			$kit = $settings['elementor_active_kit'];

			update_option( 'elementor_active_kit', $map['posts'][ (int) $kit ] );
		}

		// Import theme-icons from local .zip
		\VamtamElementorBridge::import_theme_icons();
	}

	/*
		Actions:
			'vamtam_after_content_import',
			'vamtam_attacments_import_completed',
	*/
	public static function elementor_remap() {
		$map = get_option( 'vamtam_last_import_map', false );

		$posts = get_posts( array(
			'post_type'      => get_post_types(),
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation'  => 'AND',
				'existance' => [
					'key'     => '_elementor_data',
					'compare' => 'EXISTS',
				],
				'notempty' => [
					'key'     => '_elementor_data',
					'compare' => '!=',
					'value'   => '',
				],
			],
			'orderby' => 'ID',
			'order' => 'ASC',
		) );

		// loop through the Elementor data for all pages and map old post/term IDs to the new ones (after import)
		foreach ( $posts as $post ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );

			if ( ! $data ) {
				$meta = get_post_meta( $post->ID );

				if ( isset( $meta[ '_elementor_data' ] ) ) {
					$data = $meta[ '_elementor_data' ][0];
				} else {
					echo "missing _elementor_data for {$post->ID} {$post->post_type}\n";
					var_dump($data);
					unset( $data );
				}
			}

			if ( isset( $data ) ) {
				$data = json_decode( $data, true );

				if ( is_array( $data ) ) {
					$data = self::process_elementor_remap( $data, $map );

					// usage of wp_slash()/wp_json_encode copied from Elementor\Core\Base\Document::save_elements()
					// do not change without checking what Elementor does
					$data = wp_slash( wp_json_encode( $data ) );

					// handle templates inserted as shortcodes
					$data = preg_replace_callback( '/(?<=\[elementor-template id=.")\d+/', function( $matches ) use ( $map ) {
						return $map['posts'][ (int) $matches[0] ];
					}, $data );

					update_post_meta( $post->ID, '_elementor_data', $data );
				}
			}

			/* Post conditions - Remaped once. */
			if ( current_action() === 'vamtam_after_content_import' ) {
				$conditions = get_post_meta( $post->ID, '_elementor_conditions', true );

				if ( is_array( $conditions ) && ! empty( $conditions ) ) {

					foreach ( $conditions as $i => $val ) {
						// No id.
						if ( ! preg_match( '/(?<=\/)\d+$/', $val, $m ) ) {
							continue;
						}

						if ( preg_match( '/(^.*(in_category.*|in_post_tag|product_cat|in_product_tag)\/)(\d+)$/', $val ) ) {
							// terms.
							if ( isset( $map['terms'][ (int) $m[0] ] ) ) {
								$conditions[ $i ] = str_replace( $m[0], $map['terms'][ (int) $m[0] ], $val );
							}
						} elseif ( preg_match( '/(^.*by_author\/)(\d+)$/', $val ) ) {
							// authors.
							if ( isset( $map['authors'][ (int) $m[0] ] ) ) {
								$conditions[ $i ] = str_replace( $m[0], $map['authors'][ (int) $m[0] ], $val );
							}
						} else {
							// posts.
							if ( isset( $map['posts'][ (int) $m[0] ] ) ) {
								$conditions[ $i ] = str_replace( $m[0], $map['posts'][ (int) $m[0] ], $val );
							}
						}
					}

					update_post_meta( $post->ID, '_elementor_conditions', $conditions );
				}
			}
		}
	}

	public static function process_elementor_remap( $elements, &$map ) {
		foreach ( $elements as &$el ) {
			if ( isset( $el['elements'] ) ) {
				$el['elements'] = self::process_elementor_remap( $el['elements'], $map );
			}

			/* Remaped once. */
			if ( current_action() === 'vamtam_after_content_import' ) {
				// WC Categories
				if ( $el['elType'] === 'widget' && $el['widgetType'] === 'wc-categories' && ! empty( $el['settings']['categories'] ) ) {
					$el['settings']['categories'] = array_map( function( $cid ) use ($map) {
						return $map['terms'][ (int) $cid ];
					}, $el['settings']['categories'] );
				}
				// WC Products
				if ( $el['elType'] === 'widget' && $el['widgetType'] === 'woocommerce-products' ) {
					foreach ( [ 'include', 'exclude' ] as $option ) {
						if ( ! empty( $el['settings']["query_{$option}_term_ids"] ) ) {
							foreach ( $el['settings']["query_{$option}_term_ids"] as $index => $cid ) {
								if ( isset( $map['terms'][ (int) $cid ] ) ) {
									$el['settings']["query_{$option}_term_ids"][ $index ] = strval( $map['terms'][ (int) $cid ] );
								}
							}
						}
					}
				}
				// Posts
				if ( $el['elType'] === 'widget' && $el['widgetType'] === 'posts' ) {
					foreach ( [ 'include', 'exclude' ] as $option ) {
						if ( ! empty( $el['settings']["posts_{$option}_term_ids"] ) ) {
							foreach ( $el['settings']["posts_{$option}_term_ids"] as $index => $cid ) {
								if ( isset( $map['terms'][ (int) $cid ] ) ) {
									$el['settings']["posts_{$option}_term_ids"][ $index ] = strval( $map['terms'][ (int) $cid ] );
								}
							}
						}
					}
				}
				// Loop Grid/Carousel
				if ( $el['elType'] === 'widget' && in_array( $el['widgetType'], [ 'loop-grid', 'loop-carousel' ] ) ) {
					foreach ( [ 'include', 'exclude' ] as $option ) {
						foreach ( [ 'post', 'product' ] as $post_type ) {
							if ( ! empty( $el['settings']["{$post_type}_query_{$option}_term_ids"] ) ) {
								foreach ( $el['settings']["{$post_type}_query_{$option}_term_ids"] as $index => $cid ) {
									if ( isset( $map['terms'][ (int) $cid ] ) ) {
										$el['settings']["{$post_type}_query_{$option}_term_ids"][ $index ] = strval( $map['terms'][ (int) $cid ] );
									}
								}
							}
						}
					}
				}
			}

			/* 2-fold remap. */

			// Image Carousel.
			if ( $el['elType'] === 'widget' && $el['widgetType'] === 'image-carousel' && ! empty( $el['settings']['carousel'] ) ) {
				$el['settings']['carousel'] = array_map( function( $item ) use ($map) {
					$map_id = isset( $map['posts'][ (int) $item['id'] ] ) ? $map['posts'][ (int) $item['id'] ] : null;
					if ( isset( $map_id ) && is_numeric( $map_id ) ) {
						$item['id'] = (int) $map_id;
					} else if ( ! isset( $map_id ) ) {
						$item['id'] = 987654;
					}

					return $item;
				}, $el['settings']['carousel'] );
			}
			// Gallery.
			if ( $el['elType'] === 'widget' && $el['widgetType'] === 'gallery' && ! empty( $el['settings']['gallery'] ) ) {
				$el['settings']['gallery'] = array_map( function( $item ) use ($map) {
					$map_id = isset( $map['posts'][ (int) $item['id'] ] ) ? $map['posts'][ (int) $item['id'] ] : null;
					if ( isset( $map_id ) && is_numeric( $map_id ) ) {
						$item['id'] = (int) $map_id;
					} else if ( ! isset( $map_id ) ) {
						$item['id'] = 987654;
					}

					return $item;
				}, $el['settings']['gallery'] );
			}
			// Any widget that contains an icon option that could potentially be an SVG.
			if ( $el['elType'] === 'widget' ) {
				$el_settings = $el[ 'settings' ];

				// Filter '*icon*' keys.
				$icon_keys = array_filter( array_keys ( $el_settings ), function ( $key ) {
					return strpos( $key, 'icon' ) !== false;
				} );

				// If icon is an SVG, remap the ID.
				foreach ( $icon_keys as $icon_key ) {
					if ( ! empty( $el_settings[ $icon_key ] ) &&
						( isset( $el_settings[ $icon_key ][ 'library' ] ) && $el_settings[ $icon_key ][ 'library' ] === 'svg' ) &&
						! empty( $el_settings[ $icon_key ][ 'value' ] ) ) {
							$cid    = $el_settings[ $icon_key ][ 'value' ][ 'id' ] ?? null;
							$map_id = $cid ? $map[ 'posts' ][ $cid ] : null;
							if ( is_numeric( $cid ) && is_numeric( $map_id ) && $cid !== 987654 && $map_id !== 987654 ) {
								$el[ 'settings' ][ $icon_key ][ 'value' ][ 'id' ] = $map_id;
							}
					}
				}
			}
		}

		return $elements;
	}

	public static function wpc_smart_wishlist_remap() {
		$path = VAMTAM_SAMPLES_DIR . 'wpc-smart-wishlist.json';

		if ( file_exists( $path ) ) {
			$map = get_option( 'vamtam_last_import_map' );

			// Re-mapping some woosw options.
			$woosw_opts = [
				'woosw_page_id',
			];

			foreach ( $woosw_opts as $woosw_opt ) {
				$opt_val = get_option( $woosw_opt );
				if ( ! empty( $opt_val ) && isset( $map['posts'][ (int) $opt_val ] ) ) {
					// re-map id
					update_option( $woosw_opt, $map['posts'][ (int) $opt_val ] );
				}
			}
		}
	}

	public static function wc_attributes() {
		$path = VAMTAM_SAMPLES_DIR . 'woocommerce-attributes.json';

		if ( file_exists( $path ) && function_exists( 'wc_create_attribute' ) ) {
			$attrs_map = [];
			$settings  = json_decode( file_get_contents( $path ), true );

			global $wpdb;

			foreach ( $settings as $row ) {
				$args = array(
					'name'         => $row['attribute_label'],
					'slug'         => $row['attribute_name'],
					'type'         => $row['attribute_type'],
					'order_by'     => $row['attribute_orderby'],
					'has_archives' => $row['attribute_public'],
				);

				$id = wc_create_attribute( $args );

				if ( is_wp_error( $id ) ) {
					return $id;
				}

				$attrs_map[ $row['attribute_id'] ] = $id;
			}

			update_option( 'vamtam_wc_attrs_map', $attrs_map );

			/*
				We need to register those newly added attributes (taxonomies)
				so process_terms() can see them and make the proper term relationships.

				Those taxonomies are normally added by WC on "init" but since this action already
				happened, we register them here.
			*/
			global $wc_product_attributes;

			$permalinks            = wc_get_permalink_structure();
			$wc_product_attributes = array();
			$attribute_taxonomies  = wc_get_attribute_taxonomies();

			if ( $attribute_taxonomies ) {
				foreach ( $attribute_taxonomies as $tax ) {
					$name = wc_attribute_taxonomy_name( $tax->attribute_name );

					if ( $name ) {
						$tax->attribute_public          = absint( isset( $tax->attribute_public ) ? $tax->attribute_public : 1 );
						$label                          = ! empty( $tax->attribute_label ) ? $tax->attribute_label : $tax->attribute_name;
						$wc_product_attributes[ $name ] = $tax;
						$taxonomy_data                  = array(
							'hierarchical'          => false,
							'update_count_callback' => '_update_post_term_count',
							'labels'                => array(
								/* translators: %s: attribute name */
								'name'              => sprintf( _x( 'Product %s', 'Product Attribute', 'wpv' ), $label ),
								'singular_name'     => $label,
								/* translators: %s: attribute name */
								'search_items'      => sprintf( __( 'Search %s', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'all_items'         => sprintf( __( 'All %s', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'parent_item'       => sprintf( __( 'Parent %s', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'parent_item_colon' => sprintf( __( 'Parent %s:', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'edit_item'         => sprintf( __( 'Edit %s', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'update_item'       => sprintf( __( 'Update %s', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'add_new_item'      => sprintf( __( 'Add new %s', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'new_item_name'     => sprintf( __( 'New %s', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'not_found'         => sprintf( __( 'No &quot;%s&quot; found', 'wpv' ), $label ),
								/* translators: %s: attribute name */
								'back_to_items'     => sprintf( __( '&larr; Back to "%s" attributes', 'wpv' ), $label ),
							),
							'show_ui'               => true,
							'show_in_quick_edit'    => false,
							'show_in_menu'          => false,
							'meta_box_cb'           => false,
							'query_var'             => 1 === $tax->attribute_public,
							'rewrite'               => false,
							'sort'                  => false,
							'public'                => 1 === $tax->attribute_public,
							'show_in_nav_menus'     => 1 === $tax->attribute_public && apply_filters( 'woocommerce_attribute_show_in_nav_menus', false, $name ),
							'capabilities'          => array(
								'manage_terms' => 'manage_product_terms',
								'edit_terms'   => 'edit_product_terms',
								'delete_terms' => 'delete_product_terms',
								'assign_terms' => 'assign_product_terms',
							),
						);

						if ( 1 === $tax->attribute_public && sanitize_title( $tax->attribute_name ) ) {
							$taxonomy_data['rewrite'] = array(
								'slug'         => trailingslashit( $permalinks['attribute_rewrite_slug'] ) . urldecode( sanitize_title( $tax->attribute_name ) ),
								'with_front'   => false,
								'hierarchical' => true,
							);
						}

						register_taxonomy( $name, apply_filters( "woocommerce_taxonomy_objects_{$name}", array( 'product' ) ), apply_filters( "woocommerce_taxonomy_args_{$name}", $taxonomy_data ) );
					}
				}
			}
		}
	}

	public static function woocommerce_ajax_filters_remap() {
		$path = VAMTAM_SAMPLES_DIR . 'woocommerce-ajax-filters.json';

		if ( file_exists( $path ) ) {

			// Template Styles - Url remap.
			$br_template_styles = get_option( 'BeRocket_AAPF_getall_Template_Styles', false );

			if ( ! empty( $br_template_styles ) ) {
				foreach ( $br_template_styles as $key => $value ) {
					if ( ! empty( $value[ 'file' ] ) ) {
						$val = $br_template_styles[ $key ][ 'file' ];
						$br_template_styles[ $key ][ 'file' ] = WP_PLUGIN_DIR . strstr( $val, '/woocommerce-ajax-filters/' );
					}
					if ( ! empty( $value[ 'image' ] ) ) {
						$val = $br_template_styles[ $key ][ 'image' ];
						$br_template_styles[ $key ][ 'image' ] = plugins_url() . strstr( $val, '/woocommerce-ajax-filters/' );
					}
					if ( ! empty( $value[ 'image_price' ] ) ) {
						$val = $br_template_styles[ $key ][ 'image_price' ];
						$br_template_styles[ $key ][ 'image_price' ] = plugins_url() . strstr( $val, '/woocommerce-ajax-filters/' );
					}
				}
				update_option( 'BeRocket_AAPF_getall_Template_Styles', $br_template_styles );
			}

			// Ajax Load Icon - Url remap.
			$br_filters_options = get_option( 'br_filters_options', false );

			if ( ! empty( $br_filters_options ) ) {
				$val        = $br_filters_options[ 'ajax_load_icon' ];
				$upload_dir = wp_upload_dir();
				$br_filters_options[ 'ajax_load_icon' ] = $upload_dir[ 'baseurl' ] . str_replace( '/wp-content/uploads', '', strstr( $val, '/wp-content/uploads/' ) );
				update_option( 'br_filters_options', $br_filters_options );
			}

		}
	}

	public static function wc_remap() {
		$map = get_option( 'vamtam_last_import_map' );

		// Re-mapping some WC options.
		$wc_opts = [
			'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id',
			'woocommerce_myaccount_page_id',
			'woocommerce_terms_page_id',
		];

		foreach ( $wc_opts as $wc_opt ) {
			$opt_val = get_option( $wc_opt );
			if ( ! empty( $opt_val ) && isset( $map['posts'][ (int) $opt_val ] ) ) {
				// re-map id
				update_option( $wc_opt, $map['posts'][ (int) $opt_val ] );
			}
		}
	}

	public static function wc_booking() {
		$path = VAMTAM_SAMPLES_DIR . 'woocommerce-booking.json';

		if ( file_exists( $path ) ) {
			$map = get_option( 'vamtam_last_import_map', false );

			$settings = json_decode( file_get_contents( $path ), true );

			global $wpdb;

			foreach ( $settings as $row ) {
				$row = array_map( 'intval', $row  );

				if ( isset( $map['posts'][ $row['product_id'] ] ) ) {
					unset( $row['ID'] );

					$row['product_id'] = $map['posts'][ $row['product_id'] ];
					$row['resource_id'] = $map['posts'][ $row['resource_id'] ];

					$wpdb->insert( "{$wpdb->prefix}wc_booking_relationships", $row, [ '%d', '%d', '%d' ] );
				} else {
					echo "product {$row['product_id']} not in content.xml, skipping\n";
				}
			}
		}
	}

	public static function give_wp( $map ) {
		$path = VAMTAM_SAMPLES_DIR . 'give-data.json';

		if ( file_exists( $path ) ) {
			$data = json_decode( file_get_contents( $path ), true );

			global $wpdb;

			foreach ( $data as $table => $rows ) {
				foreach ( $rows as $row ) {
					if ( isset( $map['posts'][ $row['form_id'] ] ) ) {
						unset( $row['meta_id'] );
						$row['form_id'] = $map['posts'][ $row['form_id'] ];

						$wpdb->insert( "{$wpdb->prefix}give_{$table}", $row, [ '%d', '%s', '%s' ] );
					} else {
						echo "give-wp form {$row['form_id']} not in content.xml, skipping\n";
					}
				}
			}
		}
	}

	public static function tribe_events_import() {
		// no cache to regenerate at this time
	}

	/**
	 * @return string
	 */
	static public function fix_serialized( $src ) {
		if ( empty( $src ) ) {
			return $src;
		}

		$data = maybe_unserialize( $src );

		// return if maybe_unserialize() returns an object or array, this is good.
		if( is_object( $data ) || is_array( $data ) ) {
			return $data;
		}

		$data = preg_replace_callback( '!s:(\d+):([\\\\]?"[\\\\]?"|[\\\\]?"((.*?)[^\\\\])[\\\\]?");!s', array( __CLASS__, 'fix_serial_callback' ), $src );

		if ( ! isset( $data ) && strlen( $data ) === 0 ) {
			return $src;
		}

		return $data;
	}

	/**
	 * @return string
	 */
	static public function fix_serial_callback( $matches ) {
		if ( ! isset( $matches[3] ) ) {
			return $matches[0];
		}

		return 's:' . strlen( self::unescape_mysql( $matches[3] ) ) . ':"' . self::unescape_quotes( $matches[3] ) . '";';
	}

	/**
	 * Unescape to avoid dump-text issues.
	 *
	 * @access private
	 * @return string
	 */
	static private function unescape_mysql( $value ) {
		return str_replace( array( "\\\\", "\\0", "\\n", "\\r", "\Z", "\'", '\"' ),
			array( "\\",   "\0",  "\n",  "\r",  "\x1a", "'", '"' ),
		$value );
	}

	/**
	 * Fix strange behaviour if you have escaped quotes in your replacement.
	 *
	 * @access private
	 * @return string
	 */
	static private function unescape_quotes( $value ) {
		return str_replace( '\"', '"', $value );
	}
}

new Vamtam_Importers_E;
