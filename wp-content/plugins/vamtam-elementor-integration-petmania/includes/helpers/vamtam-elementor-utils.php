<?php

class Vamtam_Elementor_Utils {
	public static function add_control_options( $controls_manager, $element, string $control_id, array $options, string $control_group = '', bool $replace = false ) {
		$is_group_control = ! empty( $control_group );

		if ( empty( $options ) ) {
			return;
		}

		if ( $is_group_control ) {
			$group_types = array_keys( \Elementor\Plugin::$instance->controls_manager->get_control_groups() );

			if (  ! in_array( $control_group, $group_types ) ) {
				throw new \Exception( 'Invalid group control type!' );
			}

			$control_group        = $controls_manager->get_control_groups( $control_group );
			$group_fields_updated = [];

			foreach ( $control_group->get_fields() as $key => $value ) {
				$cid = $control_id . '_' . $key;
				self::update_control_options( $controls_manager, $element, $cid, $options, $replace );
				$group_fields_updated[ $cid ] = $cid;
			}

			$base_group_control_id = "{$control_id}_{$control_group->get_type()}";
			if ( ! isset( $group_fields_updated[ $base_group_control_id ] ) ) {
				// Update group base control.
				self::update_control_options( $controls_manager, $element, $base_group_control_id, $options, $replace );
			}
		} else {
			self::update_control_options( $controls_manager, $element, $control_id, $options, $replace );
		}
	}

	public static function replace_control_options( $controls_manager, $element, string $control_id, array $options, string $control_group = '' ) {
		self::add_control_options( $controls_manager, $element, $control_id, $options, $control_group, true );
	}

	public static function remove_tabs( $controls_manager, $element, string $tab_id ) {
		if ( empty( $tab_id ) || empty( $element ) ) {
			return false;
		}

		$el_stack    = $element->get_stack();
		$el_controls = ! empty( $el_stack ) ? $el_stack['controls'] : [];

		if ( empty( $el_controls ) ) {
			return false;
		}

		// Remove controls belonging to the tabs.
		foreach ( $el_controls as $control_id => $control_data ) {
			$is_tab_control = isset( $control_data[ 'tabs_wrapper' ] ) && $control_data[ 'tabs_wrapper' ] === $tab_id;
			if ( $is_tab_control ) {
				self::remove_control( $controls_manager, $element, $control_data[ 'name' ] );
			}
		}

		// Remove tab control.
		self::remove_control( $controls_manager, $element, $tab_id );
		return true;
	}

	public static function remove_section( $controls_manager, $element, string $section_id, $only_controls ) {
		if ( empty( $section_id ) || empty( $element ) ) {
			return false;
		}

		$el_stack    = $element->get_stack();
		$el_controls = ! empty( $el_stack ) ? $el_stack['controls'] : [];

		if ( empty( $el_controls ) ) {
			return false;
		}

		// Remove controls belonging to the section.
		foreach ( $el_controls as $control_id => $control_data ) {
			$is_section_control = isset( $control_data[ 'section' ] ) && $control_data[ 'section' ] === $section_id;
			if ( $is_section_control ) {
				self::remove_control( $controls_manager, $element, $control_data[ 'name' ] );
			}
		}

		if ( $only_controls ) {
			return true;
		}

		// Remove section control.
		self::remove_control( $controls_manager, $element, $section_id );
		return true;
	}

	public static function remove_section_controls( $controls_manager, $element, string $section_id ) {
		self::remove_section( $controls_manager, $element, $section_id, true );
	}

	public static function remove_control( $controls_manager, $element, string $control_id, string $control_group = '' ) {
		$is_group_control = ! empty( $control_group );

		if ( $is_group_control ) {
			self::remove_group_control( $controls_manager, $element, $control_id, $control_group );
			return;
		} else {
			$control_data = $controls_manager->get_control_from_stack( $element->get_unique_name(), $control_id );

			if ( is_wp_error( $control_data ) ) {
				return;
			}

			unset( $control_data['section'] );
			unset( $control_data['tab'] );

			$is_responsive = isset( $control_data['is_responsive'] ) ? $control_data['is_responsive'] === true : isset( $control_data['responsive'] ) && ( $control_data['responsive'] === true || ! empty( $control_data['responsive'] ) );

			if ( $is_responsive ) {
				self::remove_responsive_control( $element, $control_id, $controls_manager );
			} else {
				$element->remove_control( $control_id );
			}

			return $control_data;
		}

		return false;
	}

	public static function remove_group_control( $controls_manager, $element, string $control_id, string $control_group = '' ) {
		if ( empty( $control_group ) ) {
			throw new \Exception( 'No group control type given!' );
			return false;
		}

		$group_types = array_keys( \Elementor\Plugin::$instance->controls_manager->get_control_groups() );

		if (  ! in_array( $control_group, $group_types ) ) {
			throw new \Exception( 'Invalid group control type!' );
			return false;
		}

		$control_group = $controls_manager->get_control_groups( $control_group );

		foreach ( $control_group->get_fields() as $key => $value ) {
			$cid = $control_id . '_' . $key;
			self::remove_control( $controls_manager, $element, $cid );
		}
		// Remove group single control.
		self::remove_control( $controls_manager, $element, "{$control_id}_{$control_group->get_type()}" );
	}

	/**
	 * Remove responsive control from stack.
	 * Includes logic for additional breakpoints feature.
	 *
	 * Unregister an existing responsive control and remove it from the stack.
	 *
	 * @since 1.4.0
	 * @access public
	 *
	 * @param string $id Responsive control ID.
	 */
	public static function remove_responsive_control( $element, $control_id, $controls_manager ) {
		$additional_breakpoints_active = \Elementor\Plugin::$instance->experiments->is_feature_active( 'additional_custom_breakpoints' );

		if ( ! $additional_breakpoints_active ) {
			$element->remove_responsive_control( $control_id );
			return;
		}

		$devices = \Elementor\Plugin::$instance->breakpoints->get_active_devices_list();

		foreach ( $devices as $device_name ) {
			$id_suffix    = Elementor\Plugin::$instance->breakpoints::BREAKPOINT_KEY_DESKTOP === $device_name ? '' : '_' . $device_name;
			$control_name = $control_id . $id_suffix;

			if ( self::control_exists( $controls_manager, $element, $control_name ) ) {
				$element->remove_control( $control_name );
			}
		}
	}

	public static function control_exists( $controls_manager, $element, string $control_id ) {
		$control_data = $controls_manager->get_control_from_stack( $element->get_unique_name(), $control_id );

		if ( is_wp_error( $control_data ) ) {
			return false;
		}

		return true;
	}

	private static function get_sanitized_theme_mod_value( $kit_data = '', $setting_id = '' ) {
		if ( empty( $kit_data ) || empty( $setting_id ) ) {
			return false;
		}

        $theme_prefix  = 'vamtam_theme_';

		$kit_data = array_filter(
			$kit_data,
			function ( $key ) use ( $theme_prefix ) {
				return strpos( $key, $theme_prefix ) === 0;
			},
			ARRAY_FILTER_USE_KEY
		);

		// TODO: Extract defaults to statics or meta and use everywhere from there.
		$widget_mods_defaults = [
			"{$theme_prefix}enable_all_widget_mods" => 'yes',
			"{$theme_prefix}disable_all_widget_mods" => '',
			"{$theme_prefix}enable_all_wc_mods" => 'yes',
			"{$theme_prefix}disable_all_wc_mods" => '',
		];

		// Get setting value from kit data.
		if ( in_array( $setting_id, array_keys( $widget_mods_defaults ) ) ) {
			if ( ! isset( $kit_data[ $setting_id ] ) ) {
				return $widget_mods_defaults[ $setting_id ];
			} else {
				return $kit_data[ $setting_id ];
			}
		} else {
			if ( ! isset( $kit_data[ $setting_id ] ) ) {
				return 'yes'; // Individual widget and wc mods default to 'yes'.
			} else {
				return $kit_data[ $setting_id ];
			}
		}
	}

	public static function get_theme_site_settings( $setting_id = '', $from_db = false ) {
		if ( $from_db ) {
			// From db.
			$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();

			if ( ! $active_kit_id ) {
				// Active kit not found.
				return false;
			}

			$kit_data = get_post_meta( $active_kit_id, '_elementor_page_settings', true );

			return self::get_sanitized_theme_mod_value( $kit_data, $setting_id );
		} else {
			// From kit.
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

			if ( ! $kit ) {
				// Active kit not found.
				return false;
			}

			return $kit->get_settings( $setting_id );
		}
	}

	public static function is_widget_mod_active( $widget_name = '', $from_db = true ) {
		$widget_mods_list = \VamtamElementorBridge::get_widget_mods_list();
		// Do we have a master toggle for this widget?
		if( ! array_key_exists( $widget_name, $widget_mods_list ) ) {
			return false;
		}

		$theme_setting_prefix = 'vamtam_theme_';
		if ( ! empty( \Vamtam_Elementor_Utils::get_theme_site_settings( "{$theme_setting_prefix}enable_all_widget_mods", $from_db ) ) ) {
			// All theme widget mods enabled by user pref.
			return true;
		} else if ( ! empty( \Vamtam_Elementor_Utils::get_theme_site_settings( "{$theme_setting_prefix}disable_all_widget_mods", $from_db ) ) ) {
			// All theme widget mods disabled by user pref.
			return false;
		} else {
			// User pref for current widget.
			return ! empty( \Vamtam_Elementor_Utils::get_theme_site_settings( $theme_setting_prefix . $widget_name, $from_db ) );
		}
	}

	public static function is_wc_mod_active( $wc_mod_name = '', $from_db = true ) {
		$wc_mods_list = \VamtamElementorBridge::get_wc_mods_list();
		// Do we have a master toggle for this wc mod?
		if( ! array_key_exists( $wc_mod_name, $wc_mods_list ) ) {
			return false;
		}

		return ! empty( \Vamtam_Elementor_Utils::get_theme_site_settings( 'vamtam_theme_' . $wc_mod_name, $from_db ) );
	}

	public static function elementor_is_v3_5_or_greater() {
		return defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5', '>=' );
	}

	public static function get_widgets_registration_hook() {
		if ( self::elementor_is_v3_5_or_greater() ) {
			return 'elementor/widgets/register';
		} else {
			return 'elementor/widgets/widgets_registered';
		}
	}

	public static function get_controls_registration_hook() {
		if ( self::elementor_is_v3_5_or_greater() ) {
			return 'elementor/controls/register';
		} else {
			return 'elementor/controls/controls_registered';
		}
	}

	public static function get_dynamic_tags_registration_hook() {
		if ( self::elementor_is_v3_5_or_greater() ) {
			return 'elementor/dynamic_tags/register';
		} else {
			return 'elementor/dynamic_tags/register_tags';
		}
	}

	protected static function handle_selector_option( &$control_data, $selectors, $replace ) {
		$control_selectors_val = isset( $control_data['selectors'] ) && is_array( $control_data[ 'selectors' ] ) ? reset( $control_data[ 'selectors' ] ) : '';
		$selector_val          = isset( $control_data['selector_value'] ) ? $control_data['selector_value'] : $control_selectors_val;
		$selector              = is_array( $selectors ) ? isset( $selectors[ 0 ] ) ? $selectors[ 0 ] : $selectors[ 'selector' ] : $selectors;

		if ( ! isset( $selector_val ) || empty( $selector_val ) || empty( $selector ) ) {
			return;
		}

		if ( $replace ) {
			$control_data[ 'selectors' ] = [
				$selector => $selector_val,
			];
		} else {
			$control_data[ 'selectors' ] = $control_data[ 'selectors' ] + [
				$selector => $selector_val,
			];
		}
	}

	protected static function handle_selectors_option( $control_data, &$selectors  ) {
		foreach ( $selectors as $selector => $value ) {
			// Replace some placeholder values.
			if ( $value === '{{_RESET_}}'  ) {
				$selector_val = isset( $control_data['selector_value'] ) ? $control_data['selector_value'] : ( isset( $control_data[ 'selectors' ] ) ? reset( $control_data[ 'selectors' ] ) : null );
				if ( ! isset( $selector_val ) ) {
					// There's no value to set, don't set anything.
					unset( $selectors[ $selector ] );
				} else {
					$selectors[ $selector ] = $selector_val;
				}
			}
		}
	}

	protected static function maybe_normalize_selectors_values( &$control_data  ) {
		// Normalizes same selector values that could potentially end up as arrays (instead of strings) due to nested arrays created by array_merge_recursive().
		foreach ( $control_data[ 'selectors' ] as $key => $value ) {
			if ( is_array( $value ) ) {
				// Ensure each value ends with ";" and concatenate array values into a single string.
				$value = array_unique(
					array_map( function ( $val ) {
							return str_ends_with( $val, ';' ) ? $val : $val . ';';
						},
						$value
					)
				);

				$control_data[ 'selectors' ][ $key ] = implode( '', $value );
			}
		}
	}

	protected static function maybe_handle_responsive_prefix_class_option( $controls_manager, $element, $control_id, &$control_data ) {
		$is_frontend = ! is_admin() && ! \Elementor\Plugin::$instance->preview->is_preview_mode();

		if ( ! $is_frontend ) {
			return; // On editor responsive controls are duplicated no matter what.
		}

		$existing_control_data = $controls_manager->get_control_from_stack( $element->get_unique_name(), $control_id );

		if ( is_wp_error( $existing_control_data ) ) {
			return;
		}

		$responsive_duplication_mode   = \Elementor\Plugin::$instance->breakpoints->get_responsive_control_duplication_mode();
		$additional_breakpoints_active = \Elementor\Plugin::$instance->experiments->is_feature_active( 'additional_custom_breakpoints' );
		$control_is_dynamic            = ! empty( $existing_control_data[ 'dynamic' ][ 'active' ] );
		$is_frontend_available         = ! empty( $existing_control_data[ 'frontend_available' ] );
		$has_prefix_class              = ! empty( $existing_control_data[ 'prefix_class' ] );

		if (
			! ( $additional_breakpoints_active
			&& ( 'off' === $responsive_duplication_mode || ( 'dynamic' === $responsive_duplication_mode && ! $control_is_dynamic ) )
			&& ! $is_frontend_available
			&& ! $has_prefix_class )
		) {
			// Will be handled by update_responsive_control() correctly.
			return;
		}

		// We need to remove and re-add the responsive control, to properly apply the 'prefix_class'
		self::remove_control( $controls_manager, $element, $control_id );

		$existing_control_data[ 'prefix_class' ] = $control_data[ 'prefix_class' ];

		// Note that the control won't be injected in the exact index in the control stack that we removed it from, but we don't mind cause it's only for frontend use(accessing & rendering it's data not placing a control on the editor).
		if ( ! is_wp_error( $element->add_responsive_control( $control_id, $control_data ) ) ) {
			unset( $control_data[ 'prefix_class' ] );
		} else {
			return false;
		}
	}

	protected static function update_control_options( $controls_manager, $element, string $control_id, array $options, bool $replace ) {
		$control_data = $controls_manager->get_control_from_stack( $element->get_unique_name(), $control_id );

		if ( is_wp_error( $control_data ) ) {
			return;
		}

		$is_responsive = isset( $control_data['is_responsive'] ) ? $control_data['is_responsive'] === true : isset( $control_data['responsive'] ) && ( $control_data['responsive'] === true || ! empty( $control_data['responsive'] ) );

		$modified_opts = [];
		foreach ( $options as $option => $option_value ) {
			if ( $option === 'selector' ) {
				self::handle_selector_option( $control_data, $option_value, $replace );
				$modified_opts[] = 'selectors';
				continue;
			}

			if ( $option === 'selectors' ) {
				self::handle_selectors_option( $control_data, $option_value );
			}

			if ( is_array( $option_value ) && empty( $option_value ) ) {
				continue;
			}

			if ( is_array( $option_value ) && ! isset( $control_data[ $option ] ) ) {
				$control_data[ $option ] = [];
			}

			if ( $replace ) {
				$control_data[ $option ] = $option_value;
			} else {
				if ( isset( $control_data[ $option ] ) ) {
					if (  is_array( $control_data[ $option ] ) && is_array( $option_value ) ) {
						// Both are arrays, merge them recursively (values are being added to same keys).
						$control_data[ $option ] = array_merge_recursive($control_data[ $option ], $option_value );

						if ( $option === 'selectors' ) {
							self::maybe_normalize_selectors_values( $control_data );
						}
					} else {
						// Add the new option value to the exisiting array.
						$control_data[ $option ] = $control_data[ $option ] + $option_value;
					}
				} else {
					$control_data[ $option ] = $option_value;
				}
			}

			$modified_opts[] = $option;
		}

		// Remove redundant options and leave only the ones we added/modified.
		// They will get merged with the $old_control_data during the update process.
		foreach ( $control_data as $option => $option_val ) {
			if ( ! in_array( $option, $modified_opts ) ) {
				unset( $control_data[ $option ] );
			}
		}

		if ( empty( $control_data ) ) {
			return;
		}

		if ( $is_responsive ) {
			if ( isset( $control_data[ 'prefix_class' ] ) ) {
				// handle_responsive_prefix_class_option() should be called before update_responsive_control() so it is handled properly by add_responsive_control().
				self::maybe_handle_responsive_prefix_class_option( $controls_manager, $element, $control_id, $control_data );

				if ( empty( $control_data ) ) {
					return;
				}
			}

			$element->update_responsive_control( $control_id, $control_data );
		} else {
			$element->update_control( $control_id, $control_data );
		}
	}
}
