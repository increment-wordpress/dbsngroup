<?php
/**
 * Contact Form Elementor custom widget.
 *
 * @link       http://goodherbwebmart.com/
 * @since      1.0.0
 *
 * @package    ThemeIsle\ContentForms
 */

namespace ThemeIsle\ContentForms;

use Elementor\Controls_Manager;
use Exception;

/**
 * Class Elementor_Contact_Widget
 *
 * @package ThemeIsle\ContentForms
 */
class Elementor_Contact_Widget extends ElementorWidget {

	/**
	 * Elementor_Contact_Widget constructor.
	 *
	 * @param array $data Widget data.
	 * @param array|null $args Widget arguments.
	 *
	 * @throws Exception
	 * @since 1.0.1
	 *
	 */
	public function __construct( $data = [], $args = null ) {
		parent::setup_attributes();
		try {
			parent::__construct( $data, $args );
		} catch ( Exception $exception ) {
			error_log( $exception->getMessage() );
		}
	}

	/**
	 * Get widget name.
	 *
	 * Retrieve oEmbed widget name.
	 *
	 * @return string Widget name.
	 * @since 1.0.0
	 * @access public
	 *
	 */
	public function get_name() {
		return 'content_form_contact';
	}

	/**
	 * Get widget title.
	 *
	 * Retrieve oEmbed widget title.
	 *
	 * @return string Widget title.
	 * @since 1.0.0
	 * @access public
	 *
	 */
	public function get_title() {
		return esc_html__( 'Contact Form', 'themeisle-companion' );
	}

	/**
	 * Get widget icon.
	 *
	 * Retrieve oEmbed widget icon.
	 *
	 * @return string Widget icon.
	 * @since 1.0.0
	 * @access public
	 *
	 */
	public function get_icon() {
		return 'eicon-text-align-left';
	}

	/**
	 * Set form type.
	 *
	 * @return void
	 * @since 1.0.1
	 * @access protected
	 */
	protected function set_form_type() {
		$this->form_type = 'contact';
	}

	/**
	 * Set form configuration.
	 *
	 * @return void
	 * @since 1.0.1
	 * @access protected
	 */
	protected function set_form_configuration() {
		$this->forms_config = array(
			'fields' => array(
				'name'    => array(
					'type'        => 'text',
					'label'       => esc_html__( 'Name', 'themeisle-companion' ),
					'default'     => esc_html__( 'Name', 'themeisle-companion' ),
					'placeholder' => esc_html__( 'Your Name', 'themeisle-companion' ),
					'require'     => 'required'
				),
				'email'   => array(
					'type'        => 'email',
					'label'       => esc_html__( 'Email', 'themeisle-companion' ),
					'default'     => esc_html__( 'Email', 'themeisle-companion' ),
					'placeholder' => esc_html__( 'Email address', 'themeisle-companion' ),
					'require'     => 'required'
				),
				'phone'   => array(
					'type'        => 'number',
					'label'       => esc_html__( 'Phone', 'themeisle-companion' ),
					'default'     => esc_html__( 'Phone', 'themeisle-companion' ),
					'placeholder' => esc_html__( 'Phone Nr', 'themeisle-companion' ),
					'require'     => 'optional'
				),
				'message' => array(
					'type'        => 'textarea',
					'label'       => esc_html__( 'Message', 'themeisle-companion' ),
					'default'     => esc_html__( 'Message', 'themeisle-companion' ),
					'placeholder' => esc_html__( 'Your message', 'themeisle-companion' ),
					'require'     => 'required'
				)
			),

			'controls' /* or settings? */ => array(
				'to_send_email' => array(
					'type'        => 'text',
					'label'       => esc_html__( 'Send to', 'themeisle-companion' ),
					'description' => esc_html__( 'Where should we send the email?', 'themeisle-companion' ),
					'default'     => get_bloginfo( 'admin_email' )
				),
				'submit_label'  => array(
					'type'        => 'text',
					'label'       => esc_html__( 'Submit', 'themeisle-companion' ),
					'default'     => esc_html__( 'Submit', 'themeisle-companion' ),
					'description' => esc_html__( 'The Call To Action label', 'themeisle-companion' )
				)
			)
		);
	}

	/**
	 * Add widget specific controls.
	 *
	 * @return bool|void
	 * @since 1.0.1
	 * @access protected
	 */
	protected function add_widget_specific_controls() {
		$this->add_submit_button_align();
	}

	/**
	 * Add alignment control for button.
	 *
	 * @return void
	 * @since 1.0.1
	 * @access private
	 */
	private function add_submit_button_align() {
		$this->add_responsive_control(
			'align_submit',
			[
				'label'     => __( 'Alignment', 'elementor-addon-widgets', 'themeisle-companion' ),
				'type'      => Controls_Manager::CHOOSE,
				'toggle'    => false,
				'default'   => 'left',
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'elementor-addon-widgets', 'themeisle-companion' ),
						'icon'  => 'fa fa-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'elementor-addon-widgets', 'themeisle-companion' ),
						'icon'  => 'fa fa-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'elementor-addon-widgets', 'themeisle-companion' ),
						'icon'  => 'fa fa-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .content-form .submit-form' => 'text-align: {{VALUE}};',
				],
			]
		);
	}
}
