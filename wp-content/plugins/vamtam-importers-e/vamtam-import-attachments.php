<?php

class Vamtam_Import_Attachments extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'vamtam_import_attachments';

	private $base_url = '';

	private function log( $message ) {
		error_log( $message );
	}

	public function get_queue_length() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';
		$data_in = 'option_value';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
			$data_in = 'meta_value';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$batches = $wpdb->get_results( $wpdb->prepare( "
		SELECT *
		FROM {$table}
		WHERE {$column} LIKE %s
	", $key ) );

		$count = 0;

		foreach ( $batches as $batch ) {
			if ( isset( $batch->$data_in ) ) {
				$data = maybe_unserialize( $batch->$data_in );
				if ( is_countable( $data ) ) {
					$count += count( $data );
				}
			}
		}

		return $count;
	}

	public static function enable_mimes( $mimes ) {
		$mimes['svg']   = 'image/svg+xml';
		$mimes['ttf']   = 'font/ttf';
		$mimes['woff']  = 'font/woff';
		$mimes['woff2'] = 'font/woff2';
		$mimes['eot']   = 'application/vnd.ms-fontobject';
		$mimes['json']  = 'application/json';

		return $mimes;
	}

	/**
	 * Generate thumbnails for each imported image
	 * @return false
	 */
	protected function task( $task ) {
		add_filter( 'upload_mimes', [ __CLASS__, 'enable_mimes' ] );

		// import, generate thumbnails, replace URLs
		if ( is_array( $task ) && isset( $task['data'] ) ) {

			$data                 = $task['data'];
			$this->base_url       = $data['base_url'];
			$imported             = $this->process_attachment( $data['attachment'][0], $data['attachment'][1] );
			$attachments_imported = get_option( 'vamtam_attachments_imported', [] );

			if ( is_wp_error( $imported ) ) {

				$attachments_imported[] = [
					'status' => 'failed',
					'time'   => current_time("d-m-Y H:i:s"),
					'url'    => $data['attachment'][1],
					'error'  => $imported->get_error_message(),
				];

				update_option( 'vamtam_attachments_imported', $attachments_imported );

				return false;
			}

			$old_remap = get_option( 'vamtam_import_attachments_url_remap', [] );

			update_option( 'vamtam_import_attachments_url_remap', array_merge( $old_remap, $imported['url_remap'] ) );

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				include ABSPATH . 'wp-admin/includes/image.php';
			}

			$new_metadata = wp_generate_attachment_metadata( $imported['post_id'], get_attached_file( $imported['post_id'] ) );
			$updated_meta = (int)wp_update_attachment_metadata( $imported['post_id'], $new_metadata );

			remove_filter( 'upload_mimes', [ __CLASS__, 'enable_mimes' ] );

			$attachments_imported[] = [
				'status'       => 'imported',
				'time'         => current_time("d-m-Y H:i:s"),
				'updated_meta' => $updated_meta ? 'yes' : 'no',
				'data'         => $imported,
			];

			update_option( 'vamtam_attachments_imported', $attachments_imported );

			// Remove the item from the queue.
			return false;
		}

		remove_filter( 'upload_mimes', [ __CLASS__, 'enable_mimes' ] );

		return false;
	}

	protected function complete() {
		parent::complete();

		require 'vamtam-import-search-replace.php';

		$map = get_option( 'vamtam_last_import_map', [] );

		$this->remap_featured_images( $map['featured_images'], $map );

		if ( function_exists( 'wc_get_products' ) )	{
			$this->remap_product_gallery_images( $map );
		}

		$this->backfill_attachment_urls( get_option( 'vamtam_import_attachments_url_remap', [] ) );

		do_action( 'vamtam_attacments_import_completed' );
	}

	/**
	 * @override
	 * Maybe process queue
	 *
	 * Checks whether data exists within the queue and that
	 * the process is not already running.
	 */
	public function maybe_handle() {
		// Don't lock up other requests while processing
		session_write_close();

		$is_vamtam_importer = $this->action === 'vamtam_import_attachments';
		if ( $this->is_process_running() && ! $is_vamtam_importer ) {
			// Background process already running.
			wp_die();
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			wp_die();
		}

		check_ajax_referer( $this->identifier, 'nonce' );

		$this->handle();

		wp_die();
	}

	/**
	 * Update _thumbnail_id meta to new, imported attachment IDs
	 */
	protected function remap_featured_images( $featured_images, $map ) {
		// cycle through posts that have a featured image
		foreach ( $featured_images as $post_id => $value ) {
			if ( isset( $map['posts'][$value] ) ) {
				$new_id = $map['posts'][$value];
				// only update if there's a difference
				if ( $new_id != $value ) {
					update_post_meta( $post_id, '_thumbnail_id', $new_id );
				}
			}
		}

		// same, but for terms
		foreach ( $map['terms'] as $term_id ) {
			$oldthumb = (int)get_term_meta( $term_id, 'thumbnail_id', true );

			if ( $oldthumb && isset( $map['posts'][ $oldthumb ] ) && $oldthumb != $map['posts'][ $oldthumb ] ) {
				update_term_meta( $term_id, 'thumbnail_id', $map['posts'][ $oldthumb ] );
			}
		}
	}

	/**
	 * Update _product_image_gallery meta to new, imported attachment IDs
	 */
	protected function remap_product_gallery_images( $map ) {
		if (  ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		$products = wc_get_products(array(
			'limit'  => -1,
		) );

		foreach ( $products as $product ) {
			$gallery_img_ids = $product->get_gallery_image_ids();

			if ( empty( $gallery_img_ids ) ) {
				continue;
			}

			$new_gallery_img_ids = [];

			// Re-map gallery ids.
			foreach ( $gallery_img_ids as $i => $img_id ) {
				$map_id = isset( $map['posts'][ (int) $img_id ] ) ? $map['posts'][ (int) $img_id ] : null;

				if ( isset( $map_id ) && is_numeric( $map_id ) ) {
					$new_gallery_img_ids[] = (int) $map_id;
				} else if ( ! isset( $map_id ) ) {
					$new_gallery_img_ids[] = 987654;
				}
			}

			// Update only if there are diffs.
			if ( $gallery_img_ids !== $new_gallery_img_ids ) {
				$product->set_gallery_image_ids( $new_gallery_img_ids );
				$product->save();
			}
		}
	}

	/**
	 * Use stored mapping information to update old attachment URLs
	 *
	 * With Beaver support - see https://stackoverflow.com/a/3277781/635882
	 */
	protected function backfill_attachment_urls( $url_remap ) {
		global $wpdb;

		// make sure we do the longest urls first, in case one is a substring of another
		uksort( $url_remap, function( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );

		foreach ( $url_remap as $from_url => $to_url ) {
			// remap urls in post_content
			$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $from_url, $to_url) );

			// replace serialized data in the postmeta and options tables
			$search = 's:' . strlen( $from_url) . ':"' . $from_url . '"';
			$replace = 's:' . strlen( $to_url ) . ':"' . $to_url . '"';

			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)", $search, $replace ) );
			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = REPLACE(option_value, %s, %s)", $search, $replace ) );

			$meta_to_repace = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '%%%s%%'", $from_url ) );

			foreach ( $meta_to_repace as $meta ) {
				$meta->meta_value = Vamtam_Import_Search_Replace::recursive_unserialize_replace( $from_url, $to_url, $meta->meta_value );

				if ( ! is_string( $meta->meta_value ) ) {
					$meta->meta_value = serialize( $meta->meta_value );
				}

				$wpdb->update(
					$wpdb->postmeta,
					array(
						'meta_value' => $meta->meta_value,
					),
					array( 'meta_id' => $meta->meta_id ),
					array(
						'%s',
					),
					array( '%d' )
				);
			}

			// any remaining metas
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)", $from_url, $to_url ) );

			// for json-encoded strings
			$from_url_json = substr( json_encode( $from_url ), 1, -1 );
			$to_url_json   = substr( json_encode( $to_url ), 1, -1 );
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)", $from_url_json, $to_url_json ) );
		}
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array $post Attachment post details from WXR
	 * @param string $url URL to fetch attachment from
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	protected function process_attachment( $post, $url ) {
		if ( ! apply_filters( 'import_allow_fetch_attachments', true ) ) {
			return new WP_Error( 'attachment_processing_error',
				__( 'Fetching attachments is not enabled', 'wordpress-importer' ) );
		}

		if ( ! function_exists( 'post_exists' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );

		/**
		* Filter ID of the existing post corresponding to post currently importing.
		*
		* Return 0 to force the post to be imported. Filter the ID to be something else
		* to override which existing post is mapped to the imported post.
		*
		* @see post_exists()
		* @since 0.6.2
		*
		* @param int   $post_exists  Post ID, or 0 if post did not exist.
		* @param array $post         The post array to be inserted.
		*/
		$post_exists = apply_filters( 'wp_import_existing_post', $post_exists, $post );

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) ) {
			$url = rtrim( $this->base_url, '/' ) . $url;
		}

		$map = get_option( 'vamtam_last_import_map' );

		$fetched = $this->fetch_remote_file( $url, $post );
		if ( is_wp_error( $fetched ) ) {
			$map['posts'][ $post[ 'import_id' ] ] = 'ERRORED: ' . $fetched->get_error_message();
			update_option( 'vamtam_last_import_map', $map );
			return $fetched;
		}

		extract( $fetched );

		if ( $info = wp_check_filetype( $upload['file'] ) ) {
			$post['post_mime_type'] = $info['type'];
		} else {
			$map['posts'][ $post[ 'import_id' ] ] = 'ERRORED: Invalid file type';
			update_option( 'vamtam_last_import_map', $map );
			return new WP_Error( 'attachment_processing_error', __('Invalid file type', 'wordpress-importer') );
		}

		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			include ABSPATH . 'wp-admin/includes/image.php';
		}

		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );
		remove_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		$result = [
			'post_id'   => $post_id,
			'url_remap' => $url_remap,
		];

		// remap resized image URLs, works by stripping the extension and remapping the URL stub.
		if ( preg_match( '!^image/!', $info['type'] ) ) {
			$parts = pathinfo( $url );
			$name = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

			$parts_new = pathinfo( $upload['url'] );
			$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

			$result['url_remap'][ $parts['dirname'] . '/' . $name ] = $parts_new['dirname'] . '/' . $name_new;
		}

		// Add the imported attachment to the import map.
		$map['posts'][ $post['import_id'] ] = $post_id;
		update_option( 'vamtam_last_import_map', $map );

		Vamtam_Importers_E::process_post_additional_data( $post, $post_id, $post_exists, $map['authors'] );

		return $result;
	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param string $url URL of item to fetch
	 * @param array $post Attachment details
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise
	 */
	protected function fetch_remote_file( $url, $post ) {
		// extract the file name and extension from the url
		$file_name = basename( $url );

		$split_url = preg_split( '#/elementor/custom-icons//?#', $url );

		$filter = false;

		if ( count( $split_url ) === 2 ) {
			$dir_name = dirname( $split_url[1] );

			$filter = function( $uploads ) use ( $dir_name ) {
				$uploads['path']   .= '/' . $dir_name;
				$uploads['url']    .= '/' . $dir_name;
				$uploads['subdir'] .= '/' . $dir_name;

				return $uploads;
			};

			add_filter( 'upload_dir', $filter );
		}

		// get placeholder file in the upload dir with a unique, sanitized filename
		$upload = wp_upload_bits( $file_name, 0, '', $post['upload_date'] );

		if ( $filter !== false ) {
			remove_filter( 'upload_dir', $filter );
		}

		if ( $upload['error'] ) {
			return new WP_Error( 'upload_dir_error', $upload['error'] );
		}

		// fetch the remote url and write it to the placeholder file
		$remote_response = wp_safe_remote_get( $url, array(
			'timeout' => 300,
			'stream' => true,
			'filename' => $upload['file'],
		) );
		$headers = wp_remote_retrieve_headers( $remote_response );

		// request failed
		if ( ! $headers ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Remote server did not respond', 'wordpress-importer') );
		}

		$remote_response_code = wp_remote_retrieve_response_code( $remote_response );

		// make sure the fetch was successful
		if ( $remote_response_code != '200' ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', sprintf( __('Remote server returned error response %1$d %2$s', 'wordpress-importer'), esc_html($remote_response_code), get_status_header_desc($remote_response_code) ) );
		}

		$filesize = filesize( $upload['file'] );

		if ( ! isset( $headers['content-encoding'] ) && isset( $headers['content-length'] ) && $filesize !== (int) $headers['content-length'] ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Remote file is incorrect size', 'wordpress-importer') );
		}

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Zero size file downloaded', 'wordpress-importer') );
		}

		$max_size = (int) apply_filters( 'import_attachment_size_limit', 0 );
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', sprintf(__('Remote file is too large, limit is %s', 'wordpress-importer'), size_format($max_size) ) );
		}

		// keep track of the old and new urls so we can substitute them later
		$result = [
			'upload'    => $upload,
			'url_remap' => [
				$url          => $upload['url'],
				$post['guid'] => $upload['url'],
			]
		];
		// keep track of the destination if the remote url is redirected somewhere else
		if ( isset($headers['x-final-location']) && $headers['x-final-location'] != $url ) {
			$result['url_remap'][ $headers['x-final-location'] ] = $upload['url'];
		}

		return $result;
	}
}
