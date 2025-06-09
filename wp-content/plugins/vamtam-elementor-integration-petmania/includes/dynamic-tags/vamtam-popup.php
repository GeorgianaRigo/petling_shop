<?php
namespace VamtamElementor\DynamicTags;

use \ElementorPro\Plugin;
use \Elementor\Controls_Manager;
use \ElementorPro\Modules\Popup\Module;
use \ElementorPro\Modules\QueryControl\Module as QueryControlModule;
use \Elementor\Core\Base\Document;
use \ElementorPro\Modules\DynamicTags\Tags\Base\Tag as DynamicTagsTag;
use \ElementorPro\Modules\DynamicTags\Module as DynamicTagsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

if ( ! class_exists( 'ElementorPro\Modules\DynamicTags\Tags\Base\Tag') ) {
	return; // Elementor's autoloader acts weird sometimes.
}

class Vamtam_Popup extends DynamicTagsTag {

	public function get_name() {
		return 'popup';
	}

	public function get_title() {
		return __( 'Popup', 'vamtam-elementor-integration' );
	}

	public function get_group() {
		return DynamicTagsModule::ACTION_GROUP;
	}

	public function get_categories() {
		return [ DynamicTagsModule::URL_CATEGORY ];
	}

	public function register_controls() {
		$this->add_control(
			'action',
			[
				'label' => __( 'Action', 'vamtam-elementor-integration' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'open',
				'options' => [
					'open' => __( 'Open Popup', 'vamtam-elementor-integration' ),
					'close' => __( 'Close Popup', 'vamtam-elementor-integration' ),
					'toggle' => __( 'Toggle Popup', 'vamtam-elementor-integration' ),
				],
			]
		);

		$this->add_control(
			'popup',
			[
				'label' => __( 'Popup', 'vamtam-elementor-integration' ),
				'type' => QueryControlModule::QUERY_CONTROL_ID,
				'autocomplete' => [
					'object' => QueryControlModule::QUERY_OBJECT_LIBRARY_TEMPLATE,
					'query' => [
						'posts_per_page' => 20,
						'post_status' => [ 'publish', 'private' ],
						'meta_query' => [
							[
								'key' => Document::TYPE_META_KEY,
								'value' => 'popup',
							],
						],
					],
				],
				'label_block' => true,
				'condition' => [
					'action' => [ 'open', 'toggle' ],
				],
			]
		);

		$this->add_control(
			'do_not_show_again',
			[
				'label' => __( 'Don\'t Show Again', 'vamtam-elementor-integration' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'action' => 'close',
				],
			]
		);

		$this->add_control(
			'align_with_parent',
			[
				'label' => __( 'Align With Parent', 'vamtam-elementor-integration' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'align_with_parent_notice',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => __( '<i style="font-size:11px;line-height:1.4;color:#a4afb7;">On desktop, the popup will be positioned relative to it\'s parent.</i>', 'vamtam-elementor-integration' ),
				'condition' => [
					'align_with_parent!' => '',
				],
			]
		);
	}

	public function render() {
		$settings = $this->get_active_settings();

		if ( 'close' === $settings['action'] ) {
			$this->print_close_popup_link( $settings );

			return;
		}

		$this->print_open_popup_link( $settings );
	}

	// Keep Empty to avoid default advanced section
	protected function register_advanced_section() {}

	private function print_open_popup_link( array $settings ) {
		if ( ! $settings['popup'] ) {
			return;
		}

		$link_action_url = Plugin::elementor()->frontend->create_action_hash( 'popup:open', [
			'id' => $settings['popup'],
			'toggle' => 'toggle' === $settings['action'],
			'align_with_parent' => $settings['align_with_parent'],
		] );

		Module::add_popup_to_location( $settings['popup'] );

		echo $link_action_url;
	}

	private function print_close_popup_link( array $settings ) {
		echo Plugin::elementor()->frontend->create_action_hash( 'popup:close', [ 'do_not_show_again' => $settings['do_not_show_again'] ] );
	}
}

// Register tag.
add_action( \Vamtam_Elementor_Utils::get_dynamic_tags_registration_hook(), function( $dynamic_tags ) {
	$class_name = __NAMESPACE__ . '\Vamtam_Popup';
	$dynamic_tags->register( new $class_name() );
}, 100 );

