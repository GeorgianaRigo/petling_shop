<?php
/**
 * Plugin Name: Product Questions & Answers for WooCommerce
 * Plugin URI:  https://vamtam.com
 * Version: 1.2.8.2
 * Author: VamTam
 * Author URI: https://vamtam.com
 * Requires at least: 5.6
 * WC tested up to: 8.1
 * Requires PHP: 7.0
 * Text Domain: vamtam-product-qa
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VAMTAM_PRODUCT_QA_PATH', plugin_dir_url( __FILE__ ) );

class Vamtam_Product_QA {
	public function __construct() {
		if ( ! class_exists( 'Vamtam_Updates_4' ) ) {
			require 'vamtam-updates/class-vamtam-updates.php';
		}

		new Vamtam_Updates_4( __FILE__ );

		require 'includes/ets_admin_qa_function.php';
		require 'includes/ets_user_qa_function.php';

		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		add_action( 'elementor/init', [ __CLASS__, 'load_elementor_widget' ] );
	}

	public static function load_elementor_widget() {
		require_once 'includes/elementor-widget.php';
	}

	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
}

new Vamtam_Product_QA();
