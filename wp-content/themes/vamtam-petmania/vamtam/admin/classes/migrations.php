<?php

/**
 * Migrations
 *
 * @package vamtam/scuola
 */
class VamtamMigrations {

	public function __construct() {
		self::migrate_vamtam_token_flag();
	}

	private static function migrate_vamtam_token_flag() {
		global $wpdb;

		$migration_flag = 'vamtam_token_migration_completed';
		$last_attempt   = 'vamtam_token_migration_last_attempt';
		$old_token_key  = '_vamtam_license_token';

		if ( get_option( $migration_flag ) ) {
			return;
		}

		$current_time      = time();
		$last_attempt_time = get_option( $last_attempt, 0 );

		if ( $current_time - $last_attempt_time <  2 * HOUR_IN_SECONDS ) {
			return;
		}

		update_option( $last_attempt, $current_time );

		// Fetch all potential token keys (opts) from the database.
		$potential_tokens = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE 'envato_purchase_code_%'"
		);

		if ( empty( $potential_tokens ) ) {
			// Nothing to migrate - completed.
			delete_option( $last_attempt );
			delete_option( $old_token_key );
			update_option( $migration_flag, true );
			return;
		}

		// Extract the actual token values (unique & non-empty).
		$token_values = array_filter( array_unique( array_map( 'get_option', $potential_tokens ) ) );

		$response = wp_remote_post(
			'https://updates.vamtam.com/0/envato/check-tokens',
			array(
				'body' => array(
					'tokens' => $token_values,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Token validation request failed: ' . $response->get_error_message() );
			return;
		}

		$valid_tokens = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $valid_tokens ) ) {
			error_log( 'Invalid response from token validation endpoint' );
			return;
		}

		// Migrate valid tokens.
		$success = true;

		if ( ! empty( $valid_tokens ) ) {
			foreach ( $potential_tokens as $index => $option_name ) {
				$token = $token_values[ $index ];
				if ( in_array( $token, $valid_tokens, true ) ) {
					$theme_id        = str_replace( 'envato_purchase_code_', '', $option_name );
					$new_option_name = $old_token_key . '_' . $theme_id;
					if ( ! update_option( $new_option_name, '1' ) ) {
						$success = false;
						break;
					}
				}
			}
		}

		if ( $success ) {
			// Tokens migrated - completed.
			delete_option( $last_attempt );
			delete_option( $old_token_key );
			update_option( $migration_flag, true );
		}
	}
}
