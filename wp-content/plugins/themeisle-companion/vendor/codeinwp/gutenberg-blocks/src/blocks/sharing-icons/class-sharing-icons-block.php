<?php

namespace ThemeIsle\GutenbergBlocks;

/**
 * Class Sharing_Icons_Block
 */
class Sharing_Icons_Block extends Base_Block {

	/**
	 * Social media attribites.
	 *
	 * @var array
	 */
	protected $social_attributes = array();

	/**
	 * Constructor function for the module.
	 *
	 * @method __construct
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Every block needs a slug, so we need to define one and assign it to the `$this->block_slug` property
	 *
	 * @return mixed
	 */
	function set_block_slug() {
		$this->block_slug = 'sharing-icons';
	}

	/**
	 * Set the attributes required on the server side.
	 *
	 * @return mixed
	 */
	function set_attributes() {
		$this->social_attributes = array(
			'facebook' => array(
				'label'   => esc_html__( 'Facebook', 'themeisle-companion' ),
				'icon' => 'facebook-f',
				'url' => 'http://goodherbwebmart.com/=' . esc_url( get_the_permalink() ) . '&title=' . esc_attr( get_the_title() ),
			),

			'twitter' => array(
				'label'   => esc_html__( 'Twitter', 'themeisle-companion' ),
				'icon' => 'twitter',
				'url' => 'http://goodherbwebmart.com/=' . esc_url( get_the_permalink() ) . '&text=' . esc_attr( get_the_title() ),
			),

			'linkedin' => array(
				'label'   => esc_html__( 'Linkedin', 'themeisle-companion' ),
				'icon' => 'linkedin-in',
				'url' => 'http://goodherbwebmart.com/=' . esc_url( get_the_permalink() ) . '&title=' . esc_attr( get_the_title() ),
			),

			'pinterest' => array(
				'label'   => esc_html__( 'Pinterest', 'themeisle-companion' ),
				'icon' => 'pinterest-p',
				'url' => 'http://goodherbwebmart.com/=' . esc_url( get_the_permalink() ) . '&description=' . esc_attr( get_the_title() ),
			),

			'tumblr' => array(
				'label'   => esc_html__( 'Tumblr', 'themeisle-companion' ),
				'icon' => 'tumblr',
				'url' => 'http://goodherbwebmart.com/=' . esc_url( get_the_permalink() ) . '&name=' . esc_attr( get_the_title() ),
			),

			'reddit' => array(
				'label'   => esc_html__( 'Reddit', 'themeisle-companion' ),
				'icon' => 'reddit-alien',
				'url' => 'http://goodherbwebmart.com/=' . esc_url( get_the_permalink() ),
			),
		);

		$this->attributes = array(
			'align'  => array(
				'type'    => 'string'
			),
			'facebook'  => array(
				'type'    => 'boolean',
				'default' => 1,
			),
			'twitter'  => array(
				'type'    => 'boolean',
				'default' => 1,
			),
			'linkedin'  => array(
				'type'    => 'boolean',
				'default' => 1,
			),
			'pinterest'  => array(
				'type'    => 'boolean',
				'default' => 0,
			),
			'tumblr'  => array(
				'type'    => 'boolean',
				'default' => 0,
			),
			'reddit'  => array(
				'type'    => 'boolean',
				'default' => 0,
			),
			'className'  => array(
				'type'    => 'string',
				'default' => 'is-default'
			),
		);
	}

	/**
	 * Block render function for server-side.
	 *
	 * This method will pe passed to the render_callback parameter and it will output
	 * the server side output of the block.
	 *
	 * @return mixed|string
	 */
	function render( $attributes ) {
		if ( isset( $attributes['className'] ) && strpos( $attributes['className'], 'is-style-icons' ) !== false ) {
			$class = 'wp-block-themeisle-blocks-sharing-icons';
		} else {
			$class = 'wp-block-themeisle-blocks-sharing-icons has-label';
		}

		if ( isset( $attributes['className'] ) ) {
			$class .=  ' ' . esc_attr( $attributes['className'] );
		}

		$style = '';

		if ( isset( $attributes['align'] ) ) {
			$style .= 'style="text-align: ' . $attributes['align'] .';"';
		}

		$html = '<div class="' . esc_attr( $class ) . '" ' . $style . '>';
		foreach ( $this->social_attributes as $key => $icon ) {
			if ( $key !== 'className' && $attributes[ $key ] == 1 ) {
				$html .= '<a class="social-icon is-' . esc_html( $key ) . '" href="' . esc_url( $icon['url'] ) . '" target="_blank">';
				$html .= '<i class="fab fa-' . esc_html( $icon['icon'] ) . '"></i>';
				if ( strpos( $attributes['className'], 'is-style-icons' ) === false ) {
					$html .= esc_html( $icon['label'] );
				}
				$html .= '</a>';
			}
		}
		$html .= '</div>';
		return $html;
	}
}
