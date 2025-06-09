<?php

namespace VamtamElementor\DynamicTags;

use ElementorPro\Modules\DynamicTags\Tags\Base\Data_Tag;
use ElementorPro\Core\Utils;
use ElementorPro\Modules\DynamicTags\Module;
use ElementorPro\Modules\DynamicTags\Tags\Author_Profile_Picture as Elementor_Author_Profile_Picture;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

if ( ! class_exists( 'ElementorPro\Modules\DynamicTags\Tags\Base\Tag') ) {
	return; // Elementor's autoloader acts weird sometimes.
}

class Author_Profile_Picture extends Elementor_Author_Profile_Picture {

	public function get_name() {
		return 'vamtam-author-profile-picture';
	}

	public function get_title() {
		return __( 'Author Profile Picture (Large)', 'vamtam-elementor-integration' );
	}

	public function get_value( array $options = [] ) {
		Utils::set_global_authordata();

		return [
			'id' => '',
			'url' => get_avatar_url( (int) get_the_author_meta( 'ID' ), [
				'size' => 1000,
			] ),
		];
	}
}

// Register tag.
add_action( \Vamtam_Elementor_Utils::get_dynamic_tags_registration_hook(), function( $dynamic_tags ) {
	$class_name = __NAMESPACE__ . '\Author_Profile_Picture';
	$dynamic_tags->register( new $class_name() );
}, 100 );
