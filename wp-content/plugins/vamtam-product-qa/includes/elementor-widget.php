<?php

namespace VamtamProductQA;

use \Elementor\Controls_Manager;
use \Elementor\Group_Control_Typography;

class Widget_QA_Link extends \Elementor\Widget_Base {
	public function get_name() {
		return 'vamtam-product-qa-link';
	}

	public function get_title() {
		return esc_html__( 'Product Q&A - link', 'vamtam-product-qa' );
	}

	public function get_icon() {
		return 'eicon-accordion';
	}

	public function get_categories() {
		return [ 'woocommerce-elements-single' ];
	}

	public function get_group_name() {
		return 'woocommerce';
	}

	public function get_keywords() {
		return [ 'product', 'q&a', 'question', 'answer' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_editor',
			[
				'label' => esc_html__( 'General', 'vamtam-product-qa' ),
			]
		);

		$this->add_responsive_control(
			'display',
			[
				'label'   => esc_html__( 'Display', 'vamtam-product-qa' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'always',
				'options' => [
					'always' => esc_html__( 'Always', 'vamtam-product-qa' ),
					'if-any' => esc_html__( 'If there are questions', 'vamtam-product-qa' ),
				],
			]
		);

		$this->add_control(
			'link_color',
			[
				'label' => __( 'Link Color', 'vamtam-product-qa' ),
				'type'  => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .vamtam-product-qa-force-open' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'text_typography',
				'selector' => '{{WRAPPER}} .vamtam-product-qa-force-open',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$display = $this->get_settings_for_display( 'display' );

		$q_count = count( USER_QUESTION_ANSWER::get_questions() );

		if ( $display === 'always' || $q_count > 0 ) {
			echo '<a href="#" class="vamtam-product-qa-force-open">';

			if( $q_count > 0 ) {
				printf( esc_html( _n( '%d question', '%d questions', $q_count, 'vamtam-product-qa' ) ), $q_count );
			} else {
				esc_html_e( 'No questions','vamtam-product-qa' );
			}

			echo '</a>';
		}
	}
}

function register() {
	$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
	$widgets_manager->register( new Widget_QA_Link() );
}
add_action( 'elementor/widgets/widgets_registered', __NAMESPACE__ . '\register' );
