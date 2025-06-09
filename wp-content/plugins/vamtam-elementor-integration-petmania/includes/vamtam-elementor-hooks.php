<?php
namespace VamtamElementor\ElementorHooks;

// Elementor actions.
add_action( 'elementor/editor/before_enqueue_scripts',   __NAMESPACE__ . '\enqueue_editor_scripts' );
add_action( 'elementor/frontend/before_enqueue_scripts', __NAMESPACE__ . '\frontend_before_enqueue_scripts' );
add_action( 'elementor/init', __NAMESPACE__ . '\elementor_init' );

// Elementor filters
add_filter( 'elementor/controls/animations/additional_animations', __NAMESPACE__ . '\vamtam_elementor_additional_animations' );
add_filter( 'elementor/image_size/get_attachment_image_html', __NAMESPACE__ . '\vamtam_add_no_lazy_class_to_img_element', 11, 4 );

function elementor_init() {
	// Theme-dependant.
	set_experiments_default_state();
}

/*
	Sets all Stable features to their default value & disables all Ongoing experiments by default.
	Happens only once (based on option).
*/
function set_experiments_default_state() {
	if ( get_option( 'vamtam-set-experiments-default-state', false ) ) {
		return;
	}

	$exps     = \Elementor\Plugin::$instance->experiments;
	$features = $exps->get_features();

	foreach ( $features as $fname => $fdata ) {
		if ( $fdata['release_status'] === 'stable' ) {
			// Stable experiments.

			// Features to force-disable.
			$fdisable = [
				'additional_custom_breakpoints',
				'e_font_icon_svg'
			];

			if ( in_array( $fname, $fdisable ) ) {
				// Force-disable.
				update_option( 'elementor_experiment-' . $fname, $exps::STATE_INACTIVE );
				continue;
			}

			// Force default state.
			update_option( 'elementor_experiment-' . $fname, $exps::STATE_DEFAULT );

		} else {
			// Ongoing experiments.

			// Force-disable.
			update_option( 'elementor_experiment-' . $fname, $exps::STATE_INACTIVE );

			// Set it's current default state to inactive
			$exps->set_feature_default_state( $fname, $exps::STATE_INACTIVE );
		}
	}

	update_option( 'vamtam-set-experiments-default-state', true );
}

function vamtam_elementor_additional_animations( $additional_anims ) {
	if ( vamtam_theme_supports( 'image--grow-with-scale-anims' ) && \Vamtam_Elementor_Utils::is_widget_mod_active( 'image', true ) ) {
		if ( ! isset( $additional_anims[ 'Vamtam' ] ) ) {
			$additional_anims[ 'Vamtam' ] = [];
		}
		$additional_anims[ 'Vamtam' ] = $additional_anims[ 'Vamtam' ] + [
			'imageGrowWithScaleLeft' => __( 'Image - Grow With Scale (Left)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleRight' => __( 'Image - Grow With Scale (Right)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleTop' => __( 'Image - Grow With Scale (Top)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleBottom' => __( 'Image - Grow With Scale (Bottom)', 'vamtam-elementor-integration' ),
		];
	}
	return $additional_anims;
}

function frontend_before_enqueue_scripts() {
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	// Enqueue JS for Elementor (frontend).
	wp_enqueue_script(
		'vamtam-elementor-frontend',
		VAMTAM_ELEMENTOR_INT_URL . 'assets/js/vamtam-elementor-frontend' . $suffix . '.js',
		[
			'elementor-frontend', // dependency
		],
		\VamtamElementorIntregration::PLUGIN_VERSION,
		true //in footer
	);
}

function enqueue_editor_scripts() {
	// Enqueue JS for Elementor editor.
	wp_enqueue_script( 'vamtam-elementor', VAMTAM_ELEMENTOR_INT_URL . 'assets/js/vamtam-elementor.js', [], \VamtamElementorIntregration::PLUGIN_VERSION, true );
}

/*
	Add the no-lazy class to the <img> element, if the root widget element has it.
	We need this cause Elementor adds the Custom CSS classes to the root widget element
	but caching plugins need it directly on the <img> element in order not to lazy-load it.
*/
function vamtam_add_no_lazy_class_to_img_element( $html, $settings, $image_size_key, $image_key ) {
	if ( ! isset( $settings[ '_css_classes' ] ) || empty( $settings[ '_css_classes' ] ) || strpos( $settings[ '_css_classes' ], 'no-lazy' ) === false ) {
		return $html;
	}

	$attrClass = strpos( $html, "class=" );
	if ( $attrClass ) {
		$html = preg_replace( '/class="(.*)"/', 'class="' . 'no-lazy' . ' \1"', $html );
	} else {
		$html = preg_replace( '/src="(.*)"/', 'class="' . 'no-lazy' . '" src="\1"', $html );
	}

	return $html;
}
