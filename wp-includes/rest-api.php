<?php
/**
 * REST API functions.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.4.0
 */

/**
 * Version number for our API.
 *
 * @var string
 */
define( 'REST_API_VERSION', '2.0' );

/**
 * Registers a REST API route.
 *
 * @since 4.4.0
 *
 * @global WP_REST_Server $wp_rest_server ResponseHandler instance (usually WP_REST_Server).
 *
 * @param string $namespace The first URL segment after core prefix. Should be unique to your package/plugin.
 * @param string $route     The base URL for route you are adding.
 * @param array  $args      Optional. Either an array of options for the endpoint, or an array of arrays for
 *                          multiple methods. Default empty array.
 * @param bool   $override  Optional. If the route already exists, should we override it? True overrides,
 *                          false merges (with newer overriding if duplicate keys exist). Default false.
 * @return bool True on success, false on error.
 */
function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
	/** @var WP_REST_Server $wp_rest_server */
	global $wp_rest_server;

	if ( empty( $namespace ) ) {
		/*
		 * Non-namespaced routes are not allowed, with the exception of the main
		 * and namespace indexes. If you really need to register a
		 * non-namespaced route, call `WP_REST_Server::register_route` directly.
		 */
		_doing_it_wrong( 'register_rest_route', __( 'Routes must be namespaced with plugin or theme name and version.' ), '4.4.0' );
		return false;
	} else if ( empty( $route ) ) {
		_doing_it_wrong( 'register_rest_route', __( 'Route must be specified.' ), '4.4.0' );
		return false;
	}

	if ( isset( $args['args'] ) ) {
		$common_args = $args['args'];
		unset( $args['args'] );
	} else {
		$common_args = array();
	}

	if ( isset( $args['callback'] ) ) {
		// Upgrade a single set to multiple.
		$args = array( $args );
	}

	$defaults = array(
		'methods'         => 'GET',
		'callback'        => null,
		'args'            => array(),
	);
	foreach ( $args as $key => &$arg_group ) {
		if ( ! is_numeric( $key ) ) {
			// Route option, skip here.
			continue;
		}

		$arg_group = array_merge( $defaults, $arg_group );
		$arg_group['args'] = array_merge( $common_args, $arg_group['args'] );
	}

	$full_route = '/' . trim( $namespace, '/' ) . '/' . trim( $route, '/' );
	$wp_rest_server->register_route( $namespace, $full_route, $args, $override );
	return true;
}

/**
 * Registers a new field on an existing WordPress object type.
 *
 * @since 4.7.0
 *
 * @global array $wp_rest_additional_fields Holds registered fields, organized
 *                                          by object type.
 *
 * @param string|array $object_type Object(s) the field is being registered
 *                                  to, "post"|"term"|"comment" etc.
 * @param string $attribute         The attribute name.
 * @param array  $args {
 *     Optional. An array of arguments used to handle the registered field.
 *
 *     @type string|array|null $get_callback    Optional. The callback function used to retrieve the field
 *                                              value. Default is 'null', the field will not be returned in
 *                                              the response.
 *     @type string|array|null $update_callback Optional. The callback function used to set and update the
 *                                              field value. Default is 'null', the value cannot be set or
 *                                              updated.
 *     @type string|array|null $schema          Optional. The callback function used to create the schema for
 *                                              this field. Default is 'null', no schema entry will be returned.
 * }
 */
function register_rest_field( $object_type, $attribute, $args = array() ) {
	$defaults = array(
		'get_callback'    => null,
		'update_callback' => null,
		'schema'          => null,
	);

	$args = wp_parse_args( $args, $defaults );

	global $wp_rest_additional_fields;

	$object_types = (array) $object_type;

	foreach ( $object_types as $object_type ) {
		$wp_rest_additional_fields[ $object_type ][ $attribute ] = $args;
	}
}

/**
 * Registers rewrite rules for the API.
 *
 * @since 4.4.0
 *
 * @see rest_api_register_rewrites()
 * @global WP $wp Current WordPress environment instance.
 */
function rest_api_init() {
	rest_api_register_rewrites();

	global $wp;
	$wp->add_query_var( 'rest_route' );
}

/**
 * Adds REST rewrite rules.
 *
 * @since 4.4.0
 *
 * @see add_rewrite_rule()
 * @global WP_Rewrite $wp_rewrite
 */
function rest_api_register_rewrites() {
	global $wp_rewrite;

	add_rewrite_rule( '^' . rest_get_url_prefix() . '/?$','index.php?rest_route=/','top' );
	add_rewrite_rule( '^' . rest_get_url_prefix() . '/(.*)?','index.php?rest_route=/$matches[1]','top' );
	add_rewrite_rule( '^' . $wp_rewrite->index . '/' . rest_get_url_prefix() . '/?$','index.php?rest_route=/','top' );
	add_rewrite_rule( '^' . $wp_rewrite->index . '/' . rest_get_url_prefix() . '/(.*)?','index.php?rest_route=/$matches[1]','top' );
}

/**
 * Registers the default REST API filters.
 *
 * Attached to the {@see 'rest_api_init'} action
 * to make testing and disabling these filters easier.
 *
 * @since 4.4.0
 */
function rest_api_default_filters() {
	// Deprecated reporting.
	add_action( 'deprecated_function_run', 'rest_handle_deprecated_function', 10, 3 );
	add_filter( 'deprecated_function_trigger_error', '__return_false' );
	add_action( 'deprecated_argument_run', 'rest_handle_deprecated_argument', 10, 3 );
	add_filter( 'deprecated_argument_trigger_error', '__return_false' );

	// Default serving.
	add_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	add_filter( 'rest_post_dispatch', 'rest_send_allow_header', 10, 3 );

	add_filter( 'rest_pre_dispatch', 'rest_handle_options_request', 10, 3 );
}

/**
 * Registers default REST API routes.
 *
 * @since 4.7.0
 */
function create_initial_rest_routes() {
	foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
		$class = ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : 'WP_REST_Posts_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $post_type->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();

		if ( post_type_supports( $post_type->name, 'revisions' ) ) {
			$revisions_controller = new WP_REST_Revisions_Controller( $post_type->name );
			$revisions_controller->register_routes();
		}
	}

	// Post types.
	$controller = new WP_REST_Post_Types_Controller;
	$controller->register_routes();

	// Post statuses.
	$controller = new WP_REST_Post_Statuses_Controller;
	$controller->register_routes();

	// Taxonomies.
	$controller = new WP_REST_Taxonomies_Controller;
	$controller->register_routes();

	// Terms.
	foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'object' ) as $taxonomy ) {
		$class = ! empty( $taxonomy->rest_controller_class ) ? $taxonomy->rest_controller_class : 'WP_REST_Terms_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $taxonomy->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();
	}

	// Users.
	$controller = new WP_REST_Users_Controller;
	$controller->register_routes();

	// Comments.
	$controller = new WP_REST_Comments_Controller;
	$controller->register_routes();

	// Settings.
	$controller = new WP_REST_Settings_Controller;
	$controller->register_routes();
}

/**
 * Loads the REST API.
 *
 * @since 4.4.0
 *
 * @global WP             $wp             Current WordPress environment instance.
 * @global WP_REST_Server $wp_rest_server ResponseHandler instance (usually WP_REST_Server).
 */
function rest_api_loaded() {
	if ( empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		return;
	}

	/**
	 * Whether this is a REST Request.
	 *
	 * @since 4.4.0
	 * @var bool
	 */
	define( 'REST_REQUEST', true );

	// Initialize the server.
	$server = rest_get_server();

	// Fire off the request.
	$route = untrailingslashit( $GLOBALS['wp']->query_vars['rest_route'] );
	if ( empty( $route ) ) {
		$route = '/';
	}
	$server->serve_request( $route );

	// We're done.
	die();
}

/**
 * Retrieves the URL prefix for any API resource.
 *
 * @since 4.4.0
 *
 * @return string Prefix.
 */
function rest_get_url_prefix() {
	/**
	 * Filters the REST URL prefix.
	 *
	 * @since 4.4.0
	 *
	 * @param string $prefix URL prefix. Default 'wp-json'.
	 */
	return apply_filters( 'rest_url_prefix', 'wp-json' );
}

/**
 * Retrieves the URL to a REST endpoint on a site.
 *
 * Note: The returned URL is NOT escaped.
 *
 * @since 4.4.0
 *
 * @todo Check if this is even necessary
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int    $blog_id Optional. Blog ID. Default of null returns URL for current blog.
 * @param string $path    Optional. REST route. Default '/'.
 * @param string $scheme  Optional. Sanitization scheme. Default 'rest'.
 * @return string Full URL to the endpoint.
 */
function get_rest_url( $blog_id = null, $path = '/', $scheme = 'rest' ) {
	if ( empty( $path ) ) {
		$path = '/';
	}

	if ( is_multisite() && get_blog_option( $blog_id, 'permalink_structure' ) || get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;

		if ( $wp_rewrite->using_index_permalinks() ) {
			$url = get_home_url( $blog_id, $wp_rewrite->index . '/' . rest_get_url_prefix(), $scheme );
		} else {
			$url = get_home_url( $blog_id, rest_get_url_prefix(), $scheme );
		}

		$url .= '/' . ltrim( $path, '/' );
	} else {
		$url = trailingslashit( get_home_url( $blog_id, '', $scheme ) );
		// nginx only allows HTTP/1.0 methods when redirecting from / to /index.php
		// To work around this, we manually add index.php to the URL, avoiding the redirect.
		if ( 'index.php' !== substr( $url, 9 ) ) {
			$url .= 'index.php';
		}

		$path = '/' . ltrim( $path, '/' );

		$url = add_query_arg( 'rest_route', $path, $url );
	}

	if ( is_ssl() ) {
		// If the current host is the same as the REST URL host, force the REST URL scheme to HTTPS.
		if ( $_SERVER['SERVER_NAME'] === parse_url( get_home_url( $blog_id ), PHP_URL_HOST ) ) {
			$url = set_url_scheme( $url, 'https' );
		}
	}

	if ( is_admin() && force_ssl_admin() ) {
		// In this situation the home URL may be http:, and `is_ssl()` may be
		// false, but the admin is served over https: (one way or another), so
		// REST API usage will be blocked by browsers unless it is also served
		// over HTTPS.
		$url = set_url_scheme( $url, 'https' );
	}

	/**
	 * Filters the REST URL.
	 *
	 * Use this filter to adjust the url returned by the get_rest_url() function.
	 *
	 * @since 4.4.0
	 *
	 * @param string $url     REST URL.
	 * @param string $path    REST route.
	 * @param int    $blog_id Blog ID.
	 * @param string $scheme  Sanitization scheme.
	 */
	return apply_filters( 'rest_url', $url, $path, $blog_id, $scheme );
}

/**
 * Retrieves the URL to a REST endpoint.
 *
 * Note: The returned URL is NOT escaped.
 *
 * @since 4.4.0
 *
 * @param string $path   Optional. REST route. Default empty.
 * @param string $scheme Optional. Sanitization scheme. Default 'json'.
 * @return string Full URL to the endpoint.
 */
function rest_url( $path = '', $scheme = 'json' ) {
	return get_rest_url( null, $path, $scheme );
}

/**
 * Do a REST request.
 *
 * Used primarily to route internal requests through WP_REST_Server.
 *
 * @since 4.4.0
 *
 * @global WP_REST_Server $wp_rest_server ResponseHandler instance (usually WP_REST_Server).
 *
 * @param WP_REST_Request|string $request Request.
 * @return WP_REST_Response REST response.
 */
function rest_do_request( $request ) {
	$request = rest_ensure_request( $request );
	return rest_get_server()->dispatch( $request );
}

/**
 * Retrieves the current REST server instance.
 *
 * Instantiates a new instance if none exists already.
 *
 * @since 4.5.0
 *
 * @global WP_REST_Server $wp_rest_server REST server instance.
 *
 * @return WP_REST_Server REST server instance.
 */
function rest_get_server() {
	/* @var WP_REST_Server $wp_rest_server */
	global $wp_rest_server;

	if ( empty( $wp_rest_server ) ) {
		/**
		 * Filters the REST Server Class.
		 *
		 * This filter allows you to adjust the server class used by the API, using a
		 * different class to handle requests.
		 *
		 * @since 4.4.0
		 *
		 * @param string $class_name The name of the server class. Default 'WP_REST_Server'.
		 */
		$wp_rest_server_class = apply_filters( 'wp_rest_server_class', 'WP_REST_Server' );
		$wp_rest_server = new $wp_rest_server_class;

		/**
		 * Fires when preparing to serve an API request.
		 *
		 * Endpoint objects should be created and register their hooks on this action rather
		 * than another action to ensure they're only loaded when needed.
		 *
		 * @since 4.4.0
		 *
		 * @param WP_REST_Server $wp_rest_server Server object.
		 */
		do_action( 'rest_api_init', $wp_rest_server );
	}

	return $wp_rest_server;
}

/**
 * Ensures request arguments are a request object (for consistency).
 *
 * @since 4.4.0
 *
 * @param array|WP_REST_Request $request Request to check.
 * @return WP_REST_Request REST request instance.
 */
function rest_ensure_request( $request ) {
	if ( $request instanceof WP_REST_Request ) {
		return $request;
	}

	return new WP_REST_Request( 'GET', '', $request );
}

/**
 * Ensures a REST response is a response object (for consistency).
 *
 * This implements WP_HTTP_Response, allowing usage of `set_status`/`header`/etc
 * without needing to double-check the object. Will also allow WP_Error to indicate error
 * responses, so users should immediately check for this value.
 *
 * @since 4.4.0
 *
 * @param WP_Error|WP_HTTP_Response|mixed $response Response to check.
 * @return WP_REST_Response|mixed If response generated an error, WP_Error, if response
 *                                is already an instance, WP_HTTP_Response, otherwise
 *                                returns a new WP_REST_Response instance.
 */
function rest_ensure_response( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( $response instanceof WP_HTTP_Response ) {
		return $response;
	}

	return new WP_REST_Response( $response );
}

/**
 * Handles _deprecated_function() errors.
 *
 * @since 4.4.0
 *
 * @param string $function    The function that was called.
 * @param string $replacement The function that should have been called.
 * @param string $version     Version.
 */
function rest_handle_deprecated_function( $function, $replacement, $version ) {
	if ( ! WP_DEBUG || headers_sent() ) {
		return;
	}
	if ( ! empty( $replacement ) ) {
		/* translators: 1: function name, 2: WordPress version number, 3: new function name */
		$string = sprintf( __( '%1$s (since %2$s; use %3$s instead)' ), $function, $version, $replacement );
	} else {
		/* translators: 1: function name, 2: WordPress version number */
		$string = sprintf( __( '%1$s (since %2$s; no alternative available)' ), $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedFunction: %s', $string ) );
}

/**
 * Handles _deprecated_argument() errors.
 *
 * @since 4.4.0
 *
 * @param string $function    The function that was called.
 * @param string $message     A message regarding the change.
 * @param string $version     Version.
 */
function rest_handle_deprecated_argument( $function, $message, $version ) {
	if ( ! WP_DEBUG || headers_sent() ) {
		return;
	}
	if ( ! empty( $message ) ) {
		/* translators: 1: function name, 2: WordPress version number, 3: error message */
		$string = sprintf( __( '%1$s (since %2$s; %3$s)' ), $function, $version, $message );
	} else {
		/* translators: 1: function name, 2: WordPress version number */
		$string = sprintf( __( '%1$s (since %2$s; no alternative available)' ), $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedParam: %s', $string ) );
}

/**
 * Sends Cross-Origin Resource Sharing headers with API requests.
 *
 * @since 4.4.0
 *
 * @param mixed $value Response data.
 * @return mixed Response data.
 */
function rest_send_cors_headers( $value ) {
	$origin = get_http_origin();

	if ( $origin ) {
		// Requests from file:// and data: URLs send "Origin: null"
		if ( 'null' !== $origin ) {
			$origin = esc_url_raw( $origin );
		}
		header( 'Access-Contr