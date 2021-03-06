<?php
/**
 * Elementor Widget main class.
 *
 * @link       http://goodherbwebmart.com/
 * @since      1.0.0
 *
 * @package    ThemeIsle\ContentForms
 */

namespace ThemeIsle\ContentForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;
use Elementor\Repeater;
use Elementor\Scheme_Color;
use Elementor\Scheme_Typography;
use Elementor\Widget_Base;

/**
 * This class is used to create an Elementor widget based on a ContentForms config.
 * @package ThemeIsle\ContentForms
 */
abstract class ElementorWidget extends Widget_Base {

	/**
	 * Type of form: contact, newsletter or registration.
	 *
	 * @var string
	 */
	public $form_type;

	/**
	 * Configuration of form.
	 *
	 * @var array
	 */
	public $forms_config = array();

	/**
	 * Extract widget settings based on a widget id and a page id
	 *
	 * @param $post_id
	 * @param $widget_id
	 *
	 * @return bool
	 */
	static function get_widget_settings( $widget_id, $post_id ) {

		$document = Plugin::$instance->documents->get( $post_id );
		$el_data = $document->get_elements_data();
		$el_data = apply_filters( 'elementor/frontend/builder_content_data', $el_data, $post_id );

		if ( ! empty( $el_data ) ) {
			return self::get_widget_data_by_id( $widget_id, $el_data );
		}

		return $el_data;
	}

	/**
	 * Recursively look through Elementor data and extract the settings for a specific
	 *
	 * @param $widget_id
	 * @param $el_data
	 *
	 * @return bool
	 */
	static function get_widget_data_by_id( $widget_id, $el_data ) {

		if ( ! empty( $el_data ) ) {
			foreach ( $el_data as $el ) {

				if ( $el['elType'] === 'widget' && $el['id'] === $widget_id ) {
					return $el;
				} elseif ( ! empty( $el['elements'] ) ) {
					$el = self::get_widget_data_by_id( $widget_id, $el['elements'] );

					if ( $el ) {
						return $el;
					}
				}
			}
		}

		return false;
	}

	/**
	 * This method takes the given attributes and sets them as properties
	 *
	 * @access public
	 */
	public function setup_attributes() {
		$this->set_form_type();
		$this->set_form_configuration();
	}

	/**
	 * Set form type.
	 *
	 * @return void
	 */
	abstract protected function set_form_type();

	/**
	 * Set form configuration array.
	 *
	 * @return void
	 */
	abstract protected function set_form_configuration();

	/**
	 * Widget Category.
	 *
	 * @return array
	 */
	public function get_categories() {
		$category_args = apply_filters( 'content_forms_category_args', array() );
		$slug          = isset( $category_args['slug'] ) ? $category_args['slug'] : 'obfx-elementor-widgets';

		return [ $slug ];
	}

	/**
	 * Register widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @return bool|void
	 * @since 1.0.0
	 * @access protected
	 */
	protected function _register_controls() {
		// first we need to make sure that we have some fields to build on
		if ( empty( $this->forms_config['fields'] ) ) {
			return false;
		}

		// is important to keep the order of fields from the main config
		foreach ( $this->forms_config as $key => $val ) {
			if ( 'fields' === $key ) {
				$this->_register_fields_controls();
				continue;
			} elseif ( 'controls' === $key ) {
				$this->_register_settings_controls();
			}
		}
	}

	/**
	 * Register form fields controls.
	 *
	 * @access protected
	 * @return void
	 * @since 1.0.0
	 */
	protected function _register_fields_controls() {

		$this->start_controls_section(
			$this->form_type . '_form_fields',
			array( 'label' => __( 'Fields', 'themeisle-companion' ) )
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'label',
			array(
				'label'   => __( 'Label', 'themeisle-companion' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$repeater->add_control(
			'placeholder',
			array(
				'label'   => __( 'Placeholder', 'themeisle-companion' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$repeater->add_control(
			'requirement',
			array(
				'label'        => __( 'Required', 'themeisle-companion' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'required',
				'default'      => '',
			)
		);

		$field_types = array(
			'text'     => __( 'Text', 'themeisle-companion' ),
			'password' => __( 'Password', 'themeisle-companion' ),
			'email'    => __( 'Email', 'themeisle-companion' ),
			'textarea' => __( 'Textarea', 'themeisle-companion' ),
		);

		$repeater->add_control(
			'type',
			array(
				'label'   => __( 'Type', 'themeisle-companion' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $field_types,
				'default' => 'text'
			)
		);

		$repeater->add_control(
			'key',
			array(
				'label' => __( 'Key', 'themeisle-companion' ),
				'type'  => Controls_Manager::HIDDEN
			)
		);

		$repeater->add_responsive_control(
			'field_width',
			[
				'label'   => __( 'Field Width', 'themeisle-companion' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'100' => '100%',
					'75'  => '75%',
					'66'  => '66%',
					'50'  => '50%',
					'33'  => '33%',
					'25'  => '25%',
				],
				'default' => '100',
			]
		);

		$fields = $this->forms_config['fields'];

		$default_fields = array();

		foreach ( $fields as $field_name => $field ) {
			$default_fields[] = array(
				'key'         => $field_name,
				'type'        => $field['type'],
				'label'       => $field['label'],
				'requirement' => $field['require'],
				'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : $field['label'],
				'field_width' => '100',
			);
		}

		$this->add_control(
			'form_fields',
			array(
				'label'       => __( 'Form Fields', 'themeisle-companion' ),
				'type'        => Controls_Manager::REPEATER,
				'show_label'  => false,
				'separator'   => 'before',
				'fields'      => array_values( $repeater->get_controls() ),
				'default'     => $default_fields,
				'title_field' => '{{{ label }}}',
			)
		);

		$this->add_widget_specific_fields();

		$this->end_controls_section();
	}

	/**
	 * Add widget specific fields.
	 *
	 * @return bool|void
	 */
	protected function add_widget_specific_fields() {
		return false;
	}

	/**
	 * Register form settings controls.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function _register_settings_controls() {
		$this->start_controls_section(
			'section_form_settings',
			array(
				'label' => __( 'Form Settings', 'themeisle-companion' ),
			)
		);

		$controls = $this->forms_config['controls'];

		foreach ( $controls as $control_name => $control ) {

			$control_args = array(
				'label'   => $control['label'],
				'type'    => $control['type'],
				'default' => isset( $control['default'] ) ? $control['default'] : '',
			);

			if ( isset( $control['options'] ) ) {
				$control_args['options'] = $control['options'];
			}

			$this->add_control(
				$control_name,
				$control_args
			);
		}

		$this->add_widget_specific_controls();

		$this->end_controls_section();

		$this->add_style_controls();
	}

	/**
	 * Add additional controls.
	 *
	 * This method is used to add specific controls in each widget.
	 *
	 * @access protected
	 * @return bool|void
	 * @since 1.0.1
	 */
	protected function add_widget_specific_controls() {
		return false;
	}

	/**
	 * Add style controls.
	 *
	 * @access protected
	 * @return void
	 * @since 1.0,0
	 */
	protected function add_style_controls() {
		$this->start_controls_section(
			'section_form_style',
			[
				'label' => __( 'Form', 'themeisle-companion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'column_gap',
			[
				'label'     => __( 'Columns Gap', 'themeisle-companion' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [
					'size' => 10,
				],
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 60,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-column'          => 'padding-right: calc( {{SIZE}}{{UNIT}}/2 ); padding-left: calc( {{SIZE}}{{UNIT}}/2 );',
					'{{WRAPPER}} .content-form .submit-form' => 'padding-right: calc( {{SIZE}}{{UNIT}}/2 ); padding-left: calc( {{SIZE}}{{UNIT}}/2 );',
				],
			]
		);

		$this->add_control(
			'row_gap',
			[
				'label'     => __( 'Rows Gap', 'themeisle-companion' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [
					'size' => 10,
				],
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 60,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-column'          => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .content-form .submit-form' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'heading_label',
			[
				'label'     => __( 'Label', 'themeisle-companion' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'label_spacing',
			[
				'label'     => __( 'Spacing', 'themeisle-companion' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [
					'size' => 0,
				],
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 60,
					],
				],
				'selectors' => [
					'body.rtl {{WRAPPER}} fieldset > label'       => 'padding-left: {{SIZE}}{{UNIT}};',
					// for the label position = inline option
					'body:not(.rtl) {{WRAPPER}} fieldset > label' => 'padding-right: {{SIZE}}{{UNIT}};',
					// for the label position = inline option
					'body {{WRAPPER}} fieldset > label'           => 'padding-bottom: {{SIZE}}{{UNIT}};',
					// for the label position = above option
				],
			]
		);

		$this->add_control(
			'label_color',
			[
				'label'     => __( 'Text Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > label, {{WRAPPER}} .elementor-field-subgroup label' => 'color: {{VALUE}};',
				],
				'scheme'    => [
					'type'  => Scheme_Color::get_type(),
					'value' => Scheme_Color::COLOR_3,
				],
			]
		);

		$this->add_control(
			'mark_required_color',
			[
				'label'     => __( 'Mark Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .required-mark' => 'color: {{COLOR}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'label_typography',
				'selector' => '{{WRAPPER}} fieldset > label',
				'scheme'   => Scheme_Typography::TYPOGRAPHY_3,
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_field_style',
			[
				'label' => __( 'Field', 'themeisle-companion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'field_typography',
				'selector' => '{{WRAPPER}} fieldset > input, {{WRAPPER}} fieldset > textarea, {{WRAPPER}} fieldset > button',
				'scheme'   => Scheme_Typography::TYPOGRAPHY_3,
			]
		);

		$this->add_responsive_control(
			'align_field_text',
			[
				'label'     => __( 'Text alignment', 'themeisle-companion' ),
				'type'      => Controls_Manager::CHOOSE,
				'toggle'    => false,
				'default'   => 'left',
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'themeisle-companion' ),
						'icon'  => 'fa fa-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'themeisle-companion' ),
						'icon'  => 'fa fa-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'themeisle-companion' ),
						'icon'  => 'fa fa-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}} fieldset > input'    => 'text-align: {{VALUE}}',
					'{{WRAPPER}} fieldset > textarea' => 'text-align: {{VALUE}}'
				],
			]
		);

		$this->add_responsive_control(
			'field-text-padding', [
				'label'      => __( 'Text Padding', 'themeisle-companion' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} fieldset > input'    => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} fieldset > textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'tabs_field_style' );

		$this->start_controls_tab(
			'tab_field_normal',
			[
				'label' => __( 'Normal', 'themeisle-companion' ),
			]
		);

		$this->add_control(
			'field_text_color',
			[
				'label'     => __( 'Text Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > input'                 => 'color: {{VALUE}};',
					'{{WRAPPER}} fieldset > input::placeholder'    => 'color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea'              => 'color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea::placeholder' => 'color: {{VALUE}};',
				],
				'scheme'    => [
					'type'  => Scheme_Color::get_type(),
					'value' => Scheme_Color::COLOR_3,
				],
			]
		);


		$this->add_control(
			'field_background_color',
			[
				'label'     => __( 'Background Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} fieldset > input'    => 'background-color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea' => 'background-color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'field_border_color',
			[
				'label'     => __( 'Border Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > input'    => 'border-color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea' => 'border-color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'field_border_style',
			[
				'label'     => _x( 'Border Type', 'Border Control', 'themeisle-companion' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => [
					''       => __( 'None', 'themeisle-companion' ),
					'solid'  => _x( 'Solid', 'Border Control', 'themeisle-companion' ),
					'double' => _x( 'Double', 'Border Control', 'themeisle-companion' ),
					'dotted' => _x( 'Dotted', 'Border Control', 'themeisle-companion' ),
					'dashed' => _x( 'Dashed', 'Border Control', 'themeisle-companion' ),
					'groove' => _x( 'Groove', 'Border Control', 'themeisle-companion' ),
				],
				'selectors' => [
					'{{WRAPPER}} fieldset > input'    => 'border-style: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea' => 'border-style: {{VALUE}};'
				],
			]
		);

		$this->add_control(
			'field_border_width',
			[
				'label'       => __( 'Border Width', 'themeisle-companion' ),
				'type'        => Controls_Manager::DIMENSIONS,
				'placeholder' => '',
				'size_units'  => [ 'px' ],
				'selectors'   => [
					'{{WRAPPER}} fieldset > input'    => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} fieldset > textarea' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'field_border_radius',
			[
				'label'      => __( 'Border Radius', 'themeisle-companion' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} fieldset > input'    => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} fieldset > textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_field_focus',
			[
				'label' => __( 'Focus', 'themeisle-companion' ),
			]
		);

		$this->add_control(
			'field_focus_text_color',
			[
				'label'     => __( 'Text Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > input:focus'                 => 'color: {{VALUE}};',
					'{{WRAPPER}} fieldset > input::placeholder:focus'    => 'color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea:focus'              => 'color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea::placeholder:focus' => 'color: {{VALUE}};',
				],
				'scheme'    => [
					'type'  => Scheme_Color::get_type(),
					'value' => Scheme_Color::COLOR_3,
				],
			]
		);

		$this->add_control(
			'field_focus_background_color',
			[
				'label'     => __( 'Background Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} fieldset > input:focus'    => 'background-color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea:focus' => 'background-color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'field_focus_border_color',
			[
				'label'     => __( 'Border Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > input:focus'    => 'border-color: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea:focus' => 'border-color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'field_focus_border_style',
			[
				'label'     => _x( 'Border Type', 'Border Control', 'themeisle-companion' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => [
					''       => __( 'None', 'themeisle-companion' ),
					'solid'  => _x( 'Solid', 'Border Control', 'themeisle-companion' ),
					'double' => _x( 'Double', 'Border Control', 'themeisle-companion' ),
					'dotted' => _x( 'Dotted', 'Border Control', 'themeisle-companion' ),
					'dashed' => _x( 'Dashed', 'Border Control', 'themeisle-companion' ),
					'groove' => _x( 'Groove', 'Border Control', 'themeisle-companion' ),
				],
				'selectors' => [
					'{{WRAPPER}} fieldset > input:focus'    => 'border-style: {{VALUE}};',
					'{{WRAPPER}} fieldset > textarea:focus' => 'border-style: {{VALUE}};'
				],
			]
		);

		$this->add_control(
			'field_focus_border_width',
			[
				'label'       => __( 'Border Width', 'themeisle-companion' ),
				'type'        => Controls_Manager::DIMENSIONS,
				'placeholder' => '',
				'size_units'  => [ 'px' ],
				'selectors'   => [
					'{{WRAPPER}} fieldset > input:focus'    => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} fieldset > textarea:focus' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'field_focus_border_radius',
			[
				'label'      => __( 'Border Radius', 'themeisle-companion' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} fieldset > input:focus'    => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} fieldset > textarea:focus' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_button_style',
			[
				'label' => __( 'Button', 'themeisle-companion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			[
				'label' => __( 'Normal', 'themeisle-companion' ),
			]
		);

		$this->add_control(
			'button_background_color',
			[
				'label'     => __( 'Background Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'scheme'    => [
					'type'  => Scheme_Color::get_type(),
					'value' => Scheme_Color::COLOR_4,
				],
				'selectors' => [
					'{{WRAPPER}} fieldset > button' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label'     => __( 'Text Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} fieldset > button' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'button_typography',
				'scheme'   => Scheme_Typography::TYPOGRAPHY_4,
				'selector' => '{{WRAPPER}} fieldset > button',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(), [
				'name'        => 'button_border',
				'placeholder' => '1px',
				'default'     => '1px',
				'selector'    => '{{WRAPPER}} fieldset > button',
				'separator'   => 'before',
			]
		);

		$this->add_control(
			'button_border_radius',
			[
				'label'      => __( 'Border Radius', 'themeisle-companion' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} fieldset > button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'button_text_padding',
			[
				'label'      => __( 'Text Padding', 'themeisle-companion' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} fieldset > button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			[
				'label' => __( 'Hover', 'themeisle-companion' ),
			]
		);

		$this->add_control(
			'button_background_hover_color',
			[
				'label'     => __( 'Background Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > button:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_hover_color',
			[
				'label'     => __( 'Text Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_hover_border_color',
			[
				'label'     => __( 'Border Color', 'themeisle-companion' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} fieldset > button:hover' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'button_border_border!' => '',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Render content form widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {
		$form_id  = $this->get_data( 'id' );
		$settings = $this->get_settings();
		$instance = $this->get_settings();

		$this->maybe_load_widget_style();

		if ( empty( $this->forms_config['fields'] ) ) {
			return;
		}

		$fields   = $settings['form_fields'];
		$controls = $this->forms_config['controls'];

		foreach ( $controls as $control_name => $control ) {
			$control_value = '';

			if ( isset( $settings[ $control_name ] ) ) {
				$control_value = $settings[ $control_name ];
			}
			if ( isset( $control['required'] ) && $control['required'] && empty( $control_value ) ) {
				echo '<div class="content-forms-required">';
				printf(
					esc_html__( 'The %s setting is required!', 'themeisle-companion' ),
					'<strong>' . esc_html( $control['label'] ) . '</strong>'
				);
				echo '</div>';
			}
		}

		$this->render_form_header( $form_id );
		foreach ( $fields as $index => $field ) {
			$this->render_form_field( $field );
		}

		$btn_label = esc_html__( 'Submit', 'themeisle-companion' );

		if ( ! empty( $controls['submit_label'] ) ) {
			$btn_label = $this->get_settings( 'submit_label' );
		}
		echo '<fieldset class="submit-form ' . esc_attr( $this->form_type ) . '">';
		echo '<button type="submit" name="submit" value="submit-' . esc_attr( $this->form_type ) . '-' . esc_attr( $form_id ) . '" class="' . $this->get_render_attribute_string( 'button' ) . '">';
		echo esc_html( $btn_label );
		if ( ! empty( $instance['button_icon'] ) ) {
			echo '<span ' . $this->get_render_attribute_string( 'content-wrapper' ) . '>';
			echo '<span ' . $this->get_render_attribute_string( 'icon-align' ) . '>';
			echo '<i class="' . esc_attr( $instance['button_icon'] ) . '"></i>';
			echo '</span>';
			echo '</span>';
		}
		echo '</button>';
		echo '</fieldset>';
		$this->render_form_footer();
	}

	/**
	 * Either enqueue the widget style registered by the library
	 * or load an inline version for the preview only
	 *
	 * @return void
	 * @since 1.0.0
	 * @access protected
	 */
	protected function maybe_load_widget_style() {
		if ( Plugin::$instance->editor->is_edit_mode() === true && apply_filters( 'themeisle_content_forms_register_default_style', true ) ) {
			echo '<style>';
			echo file_get_contents( plugin_dir_path( __FILE__ ) . '/assets/content-forms.css' );
			echo '</style>';
		} else {
			// if `themeisle_content_forms_register_default_style` is false, the style won't be registered anyway
			wp_enqueue_script( 'content-forms' );
			wp_enqueue_style( 'content-forms' );
		}
	}

	/**
	 * Display method for the form's header
	 * It is also takes care about the form attributes and the regular hidden fields
	 *
	 * @param string $id Form id.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function render_form_header( $id ) {
		// create an url for the form's action
		$url = admin_url( 'admin-post.php' );

		echo '<form action="' . esc_url( $url ) . '" method="post" name="content-form-' . esc_attr( $id ) . '" id="content-form-' . esc_attr( $id ) . '" class="content-form content-form-' . esc_attr( $this->form_type ) . ' ' . esc_attr( $this->get_name() ) . '">';

		wp_nonce_field( 'content-form-' . esc_attr( $id ), '_wpnonce_' . esc_attr( $this->form_type ) );

		echo '<input type="hidden" name="action" value="content_form_submit" />';
		// there could be also the possibility to submit by type
		// echo '<input type="hidden" name="action" value="content_form_{type}_submit" />';
		echo '<input type="hidden" name="form-type" value="' . esc_attr( $this->form_type ) . '" />';
		echo '<input type="hidden" name="form-builder" value="elementor" />';
		echo '<input type="hidden" name="post-id" value="' . get_the_ID() . '" />';
		echo '<input type="hidden" name="form-id" value="' . esc_attr( $id ) . '" />';
	}

	/**
	 * Print the output of an individual field
	 *
	 * @param array $field Field settings.
	 * @param bool $is_preview Is preview flag.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_form_field( $field, $is_preview = false ) {
		$item_index  = $field['_id'];
		$key         = ! empty( $field['key'] ) ? $field['key'] : sanitize_title( $field['label'] );
		$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';

		$required = '';
		$form_id  = $this->get_data( 'id' );

		if ( $field['requirement'] === 'required' ) {
			$required = 'required="required"';
		}

		//in case this is a preview, we need to disable the actual inputs and transform the labels in inputs
		$disabled = '';
		if ( $is_preview ) {
			$disabled = 'disabled="disabled"';
		}

		$field_name = 'data[' . $form_id . '][' . $key . ']';

		$this->add_render_attribute( 'fieldset' . $field['_id'], 'class', 'content-form-field-' . $field['type'] );
		$this->add_render_attribute( 'fieldset' . $field['_id'], 'class', 'elementor-column elementor-col-' . $field['field_width'] );
		$this->add_render_attribute( [
			'icon-align' => [
				'class' => [
					empty( $instance['button_icon_align'] ) ? '' :
						'elementor-align-icon-' . $instance['button_icon_align'],
					'elementor-button-icon',
				],
			]
		] );

		$this->add_inline_editing_attributes( $item_index . '_label', 'none' );


		echo '<fieldset ' . $this->get_render_attribute_string( 'fieldset' . $field['_id'] ) . '>';

		echo '<label for="' . esc_attr( $field_name ) . '" ' . $this->get_render_attribute_string( 'label' . $item_index ) . '>';
		echo wp_kses_post( $field['label'] );
		if ( $field['requirement'] === 'required' ) {
			echo '<span class="required-mark"> *</span>';
		}
		echo '</label>';

		switch ( $field['type'] ) {
			case 'textarea':
				echo '<textarea name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" ' . $disabled . ' ' . $required . ' placeholder="' . esc_attr( $placeholder ) . '" cols="30" rows="5"></textarea>';
				break;
			case 'password':
				echo '<input type="password" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" ' . $required . ' ' . $disabled . '>';
				break;
			default:
				echo '<input type="text" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" ' . $required . ' ' . $disabled . ' placeholder="' . esc_attr( $placeholder ) . '">';
				break;
		}
		echo '</fieldset>';
	}

	/**
	 * Display method for the form's footer
	 *
	 * @return void
	 * @since 1.0.0
	 * @access private
	 */
	private function render_form_footer() {
		echo '</form>';
	}

}
