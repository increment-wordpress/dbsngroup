<?php

namespace ThemeIsle\GutenbergBlocks;

/**
 * Class Advanced_Columns_Server
 */
class Advanced_Columns_Server extends \WP_Rest_Controller {

	/**
	 * The main instance var.
	 *
	 * @var Advanced_Columns_Server
	 */
	public static $instance = null;

	/**
	 * Rest route namespace.
	 *
	 * @var Advanced_Columns_Server
	 */
	public $namespace = 'themeisle-gutenberg-blocks/';

	/**
	 * Rest route version.
	 *
	 * @var Advanced_Columns_Server
	 */
	public $version = 'v1';

	/**
	 * Initialize the class
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API route
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		register_rest_route(
			$namespace,
			'/fetch_templates',
			array(
				array(
					'methods'	=> \WP_REST_Server::READABLE,
					'callback'	=> array( $this, 'fetch_templates' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/import_template',
			array(
				array(
					'methods'	=> \WP_REST_Server::READABLE,
					'callback'	=> array( $this, 'import_template' ),
					'args'		=> array(
						'url'	=> array(
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'URL of the JSON file.', 'themeisle-companion' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Function to fetch templates.
	 *
	 * @return array|bool|\WP_Error
	 */
	public function fetch_templates( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$templates_list = array(
			array(
				'title'				=> __( 'Header with Video', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'header', 'video' ),
				'categories'		=> array( 'header' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'Services Simple', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'services', 'features' ),
				'categories'		=> array( 'services' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'Services Round Icons', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'services', 'features', 'icons' ),
				'categories'		=> array( 'services' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'Services Image Background', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'services', 'features' ),
				'categories'		=> array( 'services' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'Pricing Boxed', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'pricing' ),
				'categories'		=> array( 'pricing' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'Pricing Hestia', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'pricing', 'hestia' ),
				'categories'		=> array( 'pricing' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'Testimonials Simple', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'testimonials', 'quote' ),
				'categories'		=> array( 'testimonials' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'Testimonials Boxed', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'testimonials', 'quote', 'boxed' ),
				'categories'		=> array( 'testimonials' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
			array(
				'title'				=> __( 'About with Map', 'themeisle-companion' ),
				'type'				=> 'block',
				'author'			=> __( 'Otter', 'themeisle-companion' ),
				'keywords'			=> array( 'about', 'social', 'maps', 'footer' ),
				'categories'		=> array( 'about', 'footer' ),
				'template_url'		=> 'http://goodherbwebmart.com/',
				'screenshot_url'    => 'http://goodherbwebmart.com/',
			),
		);

		$templates = apply_filters( 'themeisle_gutenberg_templates', $templates_list );

		return rest_ensure_response( $templates );
	}

	/**
	 * Function to fetch template JSON.
	 *
	 * @return array|bool|\WP_Error
	 */
	public function import_template( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$url = $request->get_param( 'url' );
		$json = file_get_contents( $url );
		$obj = json_decode( $json );
		return rest_ensure_response( $obj );
	}

	/**
	 * The instance method for the static class.
	 * Defines and returns the instance of the static class.
	 *
	 * @static
	 * @since 1.0.0
	 * @access public
	 * @return Advanced_Columns_Server
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'themeisle-companion' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'themeisle-companion' ), '1.0.0' );
	}
}
