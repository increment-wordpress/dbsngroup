<?php
/**
 * Core User API
 *
 * @package WordPress
 * @subpackage Users
 */

/**
 * Authenticates and logs a user in with 'remember' capability.
 *
 * The credentials is an array that has 'user_login', 'user_password', and
 * 'remember' indices. If the credentials is not given, then the log in form
 * will be assumed and used if set.
 *
 * The various authentication cookies will be set by this function and will be
 * set for a longer period depending on if the 'remember' credential is set to
 * true.
 *
 * @since 2.5.0
 *
 * @global string $auth_secure_cookie
 *
 * @param array       $credentials   Optional. User info in order to sign on.
 * @param string|bool $secure_cookie Optional. Whether to use secure cookie.
 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
 */
function wp_signon( $credentials = array(), $secure_cookie = '' ) {
	if ( empty($credentials) ) {
		$credentials = array(); // Back-compat for plugins passing an empty string.

		if ( ! empty($_POST['log']) )
			$credentials['user_login'] = $_POST['log'];
		if ( ! empty($_POST['pwd']) )
			$credentials['user_password'] = $_POST['pwd'];
		if ( ! empty($_POST['rememberme']) )
			$credentials['remember'] = $_POST['rememberme'];
	}

	if ( !empty($credentials['remember']) )
		$credentials['remember'] = true;
	else
		$credentials['remember'] = false;

	/**
	 * Fires before the user is authenticated.
	 *
	 * The variables passed to the callbacks are passed by reference,
	 * and can be modified by callback functions.
	 *
	 * @since 1.5.1
	 *
	 * @todo Decide whether to deprecate the wp_authenticate action.
	 *
	 * @param string $user_login    Username, passed by reference.
	 * @param string $user_password User password, passed by reference.
	 */
	do_action_ref_array( 'wp_authenticate', array( &$credentials['user_login'], &$credentials['user_password'] ) );

	if ( '' === $secure_cookie )
		$secure_cookie = is_ssl();

	/**
	 * Filters whether to use a secure sign-on cookie.
	 *
	 * @since 3.1.0
	 *
	 * @param bool  $secure_cookie Whether to use a secure sign-on cookie.
	 * @param array $credentials {
 	 *     Array of entered sign-on data.
 	 *
 	 *     @type string $user_login    Username.
 	 *     @type string $user_password Password entered.
	 *     @type bool   $remember      Whether to 'remember' the user. Increases the time
	 *                                 that the cookie will be kept. Default false.
 	 * }
	 */
	$secure_cookie = apply_filters( 'secure_signon_cookie', $secure_cookie, $credentials );

	global $auth_secure_cookie; // XXX ugly hack to pass this to wp_authenticate_cookie
	$auth_secure_cookie = $secure_cookie;

	add_filter('authenticate', 'wp_authenticate_cookie', 30, 3);

	$user = wp_authenticate($credentials['user_login'], $credentials['user_password']);

	if ( is_wp_error($user) ) {
		if ( $user->get_error_codes() == array('empty_username', 'empty_password') ) {
			$user = new WP_Error('', '');
		}

		return $user;
	}

	wp_set_auth_cookie($user->ID, $credentials['remember'], $secure_cookie);
	/**
	 * Fires after the user has successfully logged in.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       WP_User object of the logged-in user.
	 */
	do_action( 'wp_login', $user->user_login, $user );
	return $user;
}

/**
 * Authenticate a user, confirming the username and password are valid.
 *
 * @since 2.8.0
 *
 * @param WP_User|WP_Error|null $user     WP_User or WP_Error object from a previous callback. Default null.
 * @param string                $username Username for authentication.
 * @param string                $password Password for authentication.
 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
 */
function wp_authenticate_username_password($user, $username, $password) {
	if ( $user instanceof WP_User ) {
		return $user;
	}

	if ( empty($username) || empty($password) ) {
		if ( is_wp_error( $user ) )
			return $user;

		$error = new WP_Error();

		if ( empty($username) )
			$error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

		if ( empty($password) )
			$error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

		return $error;
	}

	$user = get_user_by('login', $username);

	if ( !$user ) {
		return new WP_Error( 'invalid_username',
			__( '<strong>ERROR</strong>: Invalid username.' ) .
			' <a href="' . wp_lostpassword_url() . '">' .
			__( 'Lost your password?' ) .
			'</a>'
		);
	}

	/**
	 * Filters whether the given user can be authenticated with the provided $password.
	 *
	 * @since 2.5.0
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous
	 *                                   callback failed authentication.
	 * @param string           $password Password to check against the user.
	 */
	$user = apply_filters( 'wp_authenticate_user', $user, $password );
	if ( is_wp_error($user) )
		return $user;

	if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
		return new WP_Error( 'incorrect_password',
			sprintf(
				/* translators: %s: user name */
				__( '<strong>ERROR</strong>: The password you entered for the username %s is incorrect.' ),
				'<strong>' . $username . '</strong>'
			) .
			' <a href="' . wp_lostpassword_url() . '">' .
			__( 'Lost your password?' ) .
			'</a>'
		);
	}

	return $user;
}

/**
 * Authenticates a user using the email and password.
 *
 * @since 4.5.0
 *
 * @param WP_User|WP_Error|null $user     WP_User or WP_Error object if a previous
 *                                        callback failed authentication.
 * @param string                $email    Email address for authentication.
 * @param string                $password Password for authentication.
 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
 */
function wp_authenticate_email_password( $user, $email, $password ) {
	if ( $user instanceof WP_User ) {
		return $user;
	}

	if ( empty( $email ) || empty( $password ) ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$error = new WP_Error();

		if ( empty( $email ) ) {
			$error->add( 'empty_username', __( '<strong>ERROR</strong>: The email field is empty.' ) ); // Uses 'empty_username' for back-compat with wp_signon()
		}

		if ( empty( $password ) ) {
			$error->add( 'empty_password', __( '<strong>ERROR</strong>: The password field is empty.' ) );
		}

		return $error;
	}

	if ( ! is_email( $email ) ) {
		return $user;
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		return new WP_Error( 'invalid_email',
			__( '<strong>ERROR</strong>: Invalid email address.' ) .
			' <a href="' . wp_lostpassword_url() . '">' .
			__( 'Lost your password?' ) .
			'</a>'
		);
	}

	/** This filter is documented in wp-includes/user.php */
	$user = apply_filters( 'wp_authenticate_user', $user, $password );

	if ( is_wp_error( $user ) ) {
		return $user;
	}

	if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
		return new WP_Error( 'incorrect_password',
			sprintf(
				/* translators: %s: email address */
				__( '<strong>ERROR</strong>: The password you entered for the email address %s is incorrect.' ),
				'<strong>' . $email . '</strong>'
			) .
			' <a href="' . wp_lostpassword_url() . '">' .
			__( 'Lost your password?' ) .
			'</a>'
		);
	}

	return $user;
}

/**
 * Authenticate the user using the WordPress auth cookie.
 *
 * @since 2.8.0
 *
 * @global string $auth_secure_cookie
 *
 * @param WP_User|WP_Error|null $user     WP_User or WP_Error object from a previous callback. Default null.
 * @param string                $username Username. If not empty, cancels the cookie authentication.
 * @param string                $password Password. If not empty, cancels the cookie authentication.
 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
 */
function wp_authenticate_cookie($user, $username, $password) {
	if ( $user instanceof WP_User ) {
		return $user;
	}

	if ( empty($username) && empty($password) ) {
		$user_id = wp_validate_auth_cookie();
		if ( $user_id )
			return new WP_User($user_id);

		global $auth_secure_cookie;

		if ( $auth_secure_cookie )
			$auth_cookie = SECURE_AUTH_COOKIE;
		else
			$auth_cookie = AUTH_COOKIE;

		if ( !empty($_COOKIE[$auth_cookie]) )
			return new WP_Error('expired_session', __('Please log in again.'));

		// If the cookie is not set, be silent.
	}

	return $user;
}

/**
 * For Multisite blogs, check if the authenticated user has been marked as a
 * spammer, or if the user's primary blog has been marked as spam.
 *
 * @since 3.7.0
 *
 * @param WP_User|WP_Error|null $user WP_User or WP_Error object from a previous callback. Default null.
 * @return WP_User|WP_Error WP_User on success, WP_Error if the user is considered a spammer.
 */
function wp_authenticate_spam_check( $user ) {
	if ( $user instanceof WP_User && is_multisite() ) {
		/**
		 * Filters whether the user has been marked as a spammer.
		 *
		 * @since 3.7.0
		 *
		 * @param bool    $spammed Whether the user is considered a spammer.
		 * @param WP_User $user    User to check against.
		 */
		$spammed = apply_filters( 'check_is_user_spammed', is_user_spammy( $user ), $user );

		if ( $spammed )
			return new WP_Error( 'spammer_account', __( '<strong>ERROR</strong>: Your account has been marked as a spammer.' ) );
	}
	return $user;
}

/**
 * Validates the logged-in cookie.
 *
 * Checks the logged-in cookie if the previous auth cookie could not be
 * validated and parsed.
 *
 * This is a callback for the {@see 'determine_current_user'} filter, rather than API.
 *
 * @since 3.9.0
 *
 * @param int|bool $user_id The user ID (or false) as received from the
 *                       determine_current_user filter.
 * @return int|false User ID if validated, false otherwise. If a user ID from
 *                   an earlier filter callback is received, that value is returned.
 */
function wp_validate_logged_in_cookie( $user_id ) {
	if ( $user_id ) {
		return $user_id;
	}

	if ( is_blog_admin() || is_network_admin() || empty( $_COOKIE[LOGGED_IN_COOKIE] ) ) {
		return false;
	}

	return wp_validate_auth_cookie( $_COOKIE[LOGGED_IN_COOKIE], 'logged_in' );
}

/**
 * Number of posts user has written.
 *
 * @since 3.0.0
 * @since 4.1.0 Added `$post_type` argument.
 * @since 4.3.0 Added `$public_only` argument. Added the ability to pass an array
 *              of post types to `$post_type`.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int          $userid      User ID.
 * @param array|string $post_type   Optional. Single post type or array of post types to count the number of posts for. Default 'post'.
 * @param bool         $public_only Optional. Whether to only return counts for public posts. Default false.
 * @return string Number of posts the user has written in this post type.
 */
function count_user_posts( $userid, $post_type = 'post', $public_only = false ) {
	global $wpdb;

	$where = get_posts_by_author_sql( $post_type, true, $userid, $public_only );

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where" );

	/**
	 * Filters the number of posts a user has written.
	 *
	 * @since 2.7.0
	 * @since 4.1.0 Added `$post_type` argument.
	 * @since 4.3.1 Added `$public_only` argument.
	 *
	 * @param int          $count       The user's post count.
	 * @param int          $userid      User ID.
	 * @param string|array $post_type   Single post type or array of post types to count the number of posts for.
	 * @param bool         $public_only Whether to limit counted posts to public posts.
	 */
	return apply_filters( 'get_usernumposts', $count, $userid, $post_type, $public_only );
}

/**
 * Number of posts written by a list of users.
 *
 * @since 3.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array        $users       Array of user IDs.
 * @param string|array $post_type   Optional. Single post type or array of post types to check. Defaults to 'post'.
 * @param bool         $public_only Optional. Only return counts for public posts.  Defaults to false.
 * @return array Amount of posts each user has written.
 */
function count_many_users_posts( $users, $post_type = 'post', $public_only = false ) {
	global $wpdb;

	$count = array();
	if ( empty( $users ) || ! is_array( $users ) )
		return $count;

	$userlist = implode( ',', array_map( 'absint', $users ) );
	$where = get_posts_by_author_sql( $post_type, true, null, $public_only );

	$result = $wpdb->get_results( "SELECT post_author, COUNT(*) FROM $wpdb->posts $where AND post_author IN ($userlist) GROUP BY post_author", ARRAY_N );
	foreach ( $result as $row ) {
		$count[ $row[0] ] = $row[1];
	}

	foreach ( $users as $id ) {
		if ( ! isset( $count[ $id ] ) )
			$count[ $id ] = 0;
	}

	return $count;
}

//
// User option functions
//

/**
 * Get the current user's ID
 *
 * @since MU
 *
 * @return int The current user's ID, or 0 if no user is logged in.
 */
function get_current_user_id() {
	if ( ! function_exists( 'wp_get_current_user' ) )
		return 0;
	$user = wp_get_current_user();
	return ( isset( $user->ID ) ? (int) $user->ID : 0 );
}

/**
 * Retrieve user option that can be either per Site or per Network.
 *
 * If the user ID is not given, then the current user will be used instead. If
 * the user ID is given, then the user data will be retrieved. The filter for
 * the result, will also pass the original option name and finally the user data
 * object as the third parameter.
 *
 * The option will first check for the per site name and then the per Network name.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $option     User option name.
 * @param int    $user       Optional. User ID.
 * @param string $deprecated Use get_option() to check for an option in the options table.
 * @return mixed User option value on success, false on failure.
 */
function get_user_option( $option, $user = 0, $deprecated = '' ) {
	global $wpdb;

	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '3.0.0' );

	if ( empty( $user ) )
		$user = get_current_user_id();

	if ( ! $user = get_userdata( $user ) )
		return false;

	$prefix = $wpdb->get_blog_prefix();
	if ( $user->has_prop( $prefix . $option ) ) // Blog specific
		$result = $user->get( $prefix . $option );
	elseif ( $user->has_prop( $option ) ) // User specific and cross-blog
		$result = $user->get( $option );
	else
		$result = false;

	/**
	 * Filters a specific user option value.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the user option name.
	 *
	 * @since 2.5.0
	 *
	 * @param mixed   $result Value for the user's option.
	 * @param string  $option Name of the option being retrieved.
	 * @param WP_User $user   WP_User object of the user whose option is being retrieved.
	 */
	return apply_filters( "get_user_option_{$option}", $result, $option, $user );
}

/**
 * Update user option with global blog capability.
 *
 * User options are just like user metadata except that they have support for
 * global blog options. If the 'global' parameter is false, which it is by default
 * it will prepend the WordPress table prefix to the option name.
 *
 * Deletes the user option if $newvalue is empty.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $user_id     User ID.
 * @param string $option_name User option name.
 * @param mixed  $newvalue    User option value.
 * @param bool   $global      Optional. Whether option name is global or blog specific.
 *                            Default false (blog specific).
 * @return int|bool User meta ID if the option didn't exist, true on successful update,
 *                  false on failure.
 */
function update_user_option( $user_id, $option_name, $newvalue, $global = false ) {
	global $wpdb;

	if ( !$global )
		$option_name = $wpdb->get_blog_prefix() . $option_name;

	return update_user_meta( $user_id, $option_name, $newvalue );
}

/**
 * Delete user option with global blog capability.
 *
 * User options are just like user metadata except that they have support for
 * global blog options. If the 'global' parameter is false, which it is by default
 * it will prepend the WordPress table prefix to the option name.
 *
 * @since 3.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $user_id     User ID
 * @param string $option_name User option name.
 * @param bool   $global      Optional. Whether option name is global or blog specific.
 *                            Default false (blog specific).
 * @return bool True on success, false on failure.
 */
function delete_user_option( $user_id, $option_name, $global = false ) {
	global $wpdb;

	if ( !$global )
		$option_name = $wpdb->get_blog_prefix() . $option_name;
	return delete_user_meta( $user_id, $option_name );
}

/**
 * Retrieve list of users matching criteria.
 *
 * @since 3.1.0
 *
 * @see WP_User_Query
 *
 * @param array $args Optional. Arguments to retrieve users. See WP_User_Query::prepare_query().
 *                    for more information on accepted arguments.
 * @return array List of users.
 */
function get_users( $args = array() ) {

	$args = wp_parse_args( $args );
	$args['count_total'] = false;

	$user_search = new WP_User_Query($args);

	return (array) $user_search->get_results();
}

/**
 * Get the sites a user belongs to.
 *
 * @since 3.0.0
 * @since 4.7.0 Converted to use get_sites().
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int  $user_id User ID
 * @param bool $all     Whether to retrieve all sites, or only sites that are not
 *                      marked as deleted, archived, or spam.
 * @return array A list of the user's sites. An empty array if the user doesn't exist
 *               or belongs to no sites.
 */
function get_blogs_of_user( $user_id, $all = false ) {
	global $wpdb;

	$user_id = (int) $user_id;

	// Logged out users can't have sites
	if ( empty( $user_id ) )
		return array();

	/**
	 * Filters the list of a user's sites before it is populated.
	 *
	 * Passing a non-null value to the filter will effectively short circuit
	 * get_blogs_of_user(), returning that value instead.
	 *
	 * @since 4.6.0
	 *
	 * @param null|array $sites   An array of site objects of which the user is a member.
	 * @param int        $user_id User ID.
	 * @param bool       $all     Whether the returned array should contain all sites, including
	 *                            those marked 'deleted', 'archived', or 'spam'. Default false.
	 */
	$sites = apply_filters( 'pre_get_blogs_of_user', null, $user_id, $all );

	if ( null !== $sites ) {
		return $sites;
	}

	$keys = get_user_meta( $user_id );
	if ( empty( $keys ) )
		return array();

	if ( ! is_multisite() ) {
		$site_id = get_current_blog_id();
		$sites = array( $site_id => new stdClass );
		$sites[ $site_id ]->userblog_id = $site_id;
		$sites[ $site_id ]->blogname = get_option('blogname');
		$sites[ $site_id ]->domain = '';
		$sites[ $site_id ]->path = '';
		$sites[ $site_id ]->site_id = 1;
		$sites[ $site_id ]->siteurl = get_option('siteurl');
		$sites[ $site_id ]->archived = 0;
		$sites[ $site_id ]->spam = 0;
		$sites[ $site_id ]->deleted = 0;
		return $sites;
	}

	$site_ids = array();

	if ( isset( $keys[ $wpdb->base_prefix . 'capabilities' ] ) && defined( 'MULTISITE' ) ) {
		$site_ids[] = 1;
		unset( $keys[ $wpdb->base_prefix . 'capabilities' ] );
	}

	$keys = array_keys( $keys );

	foreach ( $keys as $key ) {
		if ( 'capabilities' !== substr( $key, -12 ) )
			continue;
		if ( $wpdb->base_prefix && 0 !== strpos( $key, $wpdb->base_prefix ) )
			continue;
		$site_id = str_replace( array( $wpdb->base_prefix, '_capabilities' ), '', $key );
		if ( ! is_numeric( $site_id ) )
			continue;

		$site_ids[] = (int) $site_id;
	}

	$sites = array();

	if ( ! empty( $site_ids ) ) {
		$args = array(
			'number'   => '',
			'site__in' => $site_ids,
		);
		if ( ! $all ) {
			$args['archived'] = 0;
			$args['spam']     = 0;
			$args['deleted']  = 0;
		}

		$_sites = get_sites( $args );

		foreach ( $_sites as $site ) {
			$sites[ $site->id ] = (object) array(
				'userblog_id' => $site->id,
				'blogname'    => $site->blogname,
				'domain'      => $site->domain,
				'path'        => $site->path,
				'site_id'     => $site->network_id,
				'siteurl'     => $site->siteurl,
				'archived'    => $site->archived,
				'mature'      => $site->mature,
				'spam'        => $site->spam,
				'deleted'     => $site->deleted,
			);
		}
	}

	/**
	 * Filters the list of sites a user belongs to.
	 *
	 * @since MU
	 *
	 * @param array $sites   An array of site objects belonging to the user.
	 * @param int   $user_id User ID.
	 * @param bool  $all     Whether the returned sites array should contain all sites, including
	 *                       those marked 'deleted', 'archived', or 'spam'. Default false.
	 */
	return apply_filters( 'get_blogs_of_user', $sites, $user_id, $all );
}

/**
 * Find out whether a user is a member of a given blog.
 *
 * @since MU 1.1
 *
 * @param int $user_id Optional. The unique ID of the user. Defaults to the current user.
 * @param int $blog_id Optional. ID of the blog to check. Defaults to the current site.
 * @return bool
 */
function is_user_member_of_blog( $user_id = 0, $blog_id = 0 ) {
	global $wpdb;

	$user_id = (int) $user_id;
	$blog_id = (int) $blog_id;

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Technically not needed, but does save calls to get_site and get_user_meta
	// in the event that the function is called when a user isn't logged in
	if ( empty( $user_id ) ) {
		return false;
	} else {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return false;
		}
	}

	if ( ! is_multisite() ) {
		return true;
	}

	if ( empty( $blog_id ) ) {
		$blog_id = get_current_blog_id();
	}

	$blog = get_site( $blog_id );

	if ( ! $blog || ! isset( $blog->domain ) || $blog->archived || $blog->spam || $blog->deleted ) {
		return false;
	}

	$keys = get_user_meta( $user_id );
	if ( empty( $keys ) ) {
		return false;
	}

	// no underscore before capabilities in $base_capabilities_key
	$base_capabilities_key = $wpdb->base_prefix . 'capabilities';
	$site_capabilities_key = $wpdb->base_prefix . $blog_id . '_capabilities';

	if ( isset( $keys[ $base_capabilities_key ] ) && $blog_id == 1 ) {
		return true;
	}

	if ( isset( $keys[ $site_capabilities_key ] ) ) {
		return true;
	}

	return false;
}

/**
 * Add meta data field to a user.
 *
 * Post meta data is called "Custom Fields" on the Administration Screens.
 *
 * @since 3.0.0
 * @link http://goodherbwebmart.com/
 *
 * @param int    $user_id    User ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Metadata value.
 * @param bool   $unique     Optional, default is false. Whether the same key should not be added.
 * @return int|false Meta ID on success, false on failure.
 */
function add_user_meta($user_id, $meta_key, $meta_value, $unique = false) {
	return add_metadata('user', $user_id, $meta_key, $meta_value, $unique);
}

/**
 * Remove metadata matching criteria from a user.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching key, if needed.
 *
 * @since 3.0.0
 * @link http://goodherbwebmart.com/
 *
 * @param int    $user_id    User ID
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Optional. Metadata value.
 * @return bool True on success, false on failure.
 */
function delete_user_meta($user_id, $meta_key, $meta_value = '') {
	return delete_metadata('user', $user_id, $meta_key, $meta_value);
}

/**
 * Retrieve user meta field for a user.
 *
 * @since 3.0.0
 * @link http://goodherbwebmart.com/
 *
 * @param int    $user_id User ID.
 * @param string $key     Optional. The meta key to retrieve. By default, returns data for all keys.
 * @param bool   $single  Whether to return a single value.
 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
 */
function get_user_meta($user_id, $key = '', $single = false) {
	return get_metadata('user', $user_id, $key, $single);
}

/**
 * Update user meta field based on user ID.
 *
 * Use the $prev_value parameter to differentiate between meta fields with the
 * same key and user ID.
 *
 * If the meta field for the user does not exist, it will be added.
 *
 * @since 3.0.0
 * @link http://goodherbwebmart.com/
 *
 * @param int    $user_id    User ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value.
 * @param mixed  $prev_value Optional. Previous value to check before removing.
 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
 */
function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '') {
	return update_metadata('user', $user_id, $meta_key, $meta_value, $prev_value);
}

/**
 * Count number of users who have each of the user roles.
 *
 * Assumes there are neither duplicated nor orphaned capabilities meta_values.
 * Assumes role names are unique phrases. Same assumption made by WP_User_Query::prepare_query()
 * Using $strategy = 'time' this is CPU-intensive and should handle around 10^7 users.
 * Using $strategy = 'memory' this is memory-intensive and should handle around 10^5 users, but see WP Bug #12257.
 *
 * @since 3.0.0
 * @since 4.4.0 The number of users with no role is now included in the `none` element.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $strategy 'time' or 'memory'
 * @return array Includes a grand total and an array of counts indexed by role strings.
 */
function count_users($strategy = 'time') {
	global $wpdb;

	// Initialize
	$id = get_current_blog_id();
	$blog_prefix = $wpdb->get_blog_prefix($id);
	$result = array();

	if ( 'time' == $strategy ) {
		$avail_roles = wp_roles()->get_names();

		// Build a CPU-intensive query that will return concise information.
		$select_count = array();
		foreach ( $avail_roles as $this_role => $name ) {
			$select_count[] = $wpdb->prepare( "COUNT(NULLIF(`meta_value` LIKE %s, false))", '%' . $wpdb->esc_like( '"' . $this_role . '"' ) . '%');
		}
		$select_count[] = "COUNT(NULLIF(`meta_value` = 'a:0:{}', false))";
		$select_count = implode(', ', $select_count);

		// Add the meta_value index to the selection list, then run the query.
		$row = $wpdb->get_row( "
			SELECT {$select_count}, COUNT(*)
			FROM {$wpdb->usermeta}
			INNER JOIN {$wpdb->users} ON user_id = ID
			WHERE meta_key = '{$blog_prefix}capabilities'
		", ARRAY_N );

		// Run the previous loop again to associate results with role names.
		$col = 0;
		$role_counts = array();
		foreach ( $avail_roles as $this_role => $name ) {
			$count = (int) $row[$col++];
			if ($count > 0) {
				$role_counts[$this_role] = $count;
			}
		}

		$role_counts['none'] = (int) $row[$col++];

		// Get the meta_value index from the end of the result set.
		$total_users = (int) $row[$col];

		$result['total_users'] = $total_users;
		$result['avail_roles'] =& $role_counts;
	} else {
		$avail_roles = array(
			'none' => 0,
		);

		$users_of_blog = $wpdb->get_col( "
			SELECT meta_value
			FROM {$wpdb->usermeta}
			INNER JOIN {$wpdb->users} ON user_id = ID
			WHERE meta_key = '{$blog_prefix}capabilities'
		" );

		foreach ( $users_of_blog as $caps_meta ) {
			$b_roles = maybe_unserialize($caps_meta);
			if ( ! is_array( $b_roles ) )
				continue;
			if ( empty( $b_roles ) ) {
				$avail_roles['none']++;
			}
			foreach ( $b_roles as $b_role => $val ) {
				if ( isset($avail_roles[$b_role]) ) {
					$avail_roles[$b_role]++;
				} else {
					$avail_roles[$b_role] = 1;
				}
			}
		}

		$result['total_users'] = count( $users_of_blog );
		$result['avail_roles'] =& $avail_roles;
	}

	if ( is_multisite() ) {
		$result['avail_roles']['none'] = 0;
	}

	return $result;
}

//
// Private helper functions
//

/**
 * Set up global user vars.
 *
 * Used by wp_set_current_user() for back compat. Might be deprecated in the future.
 *
 * @since 2.0.4
 *
 * @global string $user_login    The user username for logging in
 * @global object $userdata      User data.
 * @global int    $user_level    The level of the user
 * @global int    $user_ID       The ID of the user
 * @global string $user_email    The email address of the user
 * @global string $user_url      The url in the user's profile
 * @global string $user_identity The display name of the user
 *
 * @param int $for_user_id Optional. User ID to set up global data.
 */
function setup_userdata($for_user_id = '') {
	global $user_login, $userdata, $user_level, $user_ID, $user_email, $user_url, $user_identity;

	if ( '' == $for_user_id )
		$for_user_id = get_current_user_id();
	$user = get_userdata( $for_user_id );

	if ( ! $user ) {
		$user_ID = 0;
		$user_level = 0;
		$userdata = null;
		$user_login = $user_email = $user_url = $user_identity = '';
		return;
	}

	$user_ID    = (int) $user->ID;
	$user_level = (int) $user->user_level;
	$userdata   = $user;
	$user_login = $user->user_login;
	$user_email = $user->user_email;
	$user_url   = $user->user_url;
	$user_identity = $user->display_name;
}

/**
 * Create dropdown HTML content of users.
 *
 * The content can either be displayed, which it is by default or retrieved by
 * setting the 'echo' argument. The 'include' and 'exclude' arguments do not
 * need to be used; all users will be displayed in that case. Only one can be
 * used, either 'include' or 'exclude', but not both.
 *
 * The available arguments are as follows:
 *
 * @since 2.3.0
 * @since 4.5.0 Added the 'display_name_with_login' value for 'show'.
 * @since 4.7.0 Added the `$role`, `$role__in`, and `$role__not_in` parameters.
 *
 * @param array|string $args {
 *     Optional. Array or string of arguments to generate a drop-down of users.
 *     See WP_User_Query::prepare_query() for additional available arguments.
 *
 *     @type string       $show_option_all         Text to show as the drop-down default (all).
 *                                                 Default empty.
 *     @type string       $show_option_none        Text to show as the drop-down default when no
 *                                                 users were found. Default empty.
 *     @type int|string   $option_none_value       Value to use for $show_option_non when no users
 *                                                 were found. Default -1.
 *     @type string       $hide_if_only_one_author Whether to skip generating the drop-down
 *                                                 if only one user was found. Default empty.
 *     @type string       $orderby                 Field to order found users by. Accepts user fields.
 *                                                 Default 'display_name'.
 *     @type string       $order                   Whether to order users in ascending or descending
 *                                                 order. Accepts 'ASC' (ascending) or 'DESC' (descending).
 *                                                 Default 'ASC'.
 *     @type array|string $include                 Array or comma-separated list of user IDs to include.
 *                                                 Default empty.
 *     @type array|string $exclude                 Array or comma-separated list of user IDs to exclude.
 *                                                 Default empty.
 *     @type bool|int     $multi                   Whether to skip the ID attribute on the 'select' element.
 *                                                 Accepts 1|true or 0|false. Default 0|false.
 *     @type string       $show                    User data to display. If the selected item is empty
 *                                                 then the 'user_login' will be displayed in parentheses.
 *                                                 Accepts any user field, or 'display_name_with_login' to show
 *                                                 the display name with user_login in parentheses.
 *                                                 Default 'display_name'.
 *     @type int|bool     $echo                    Whether to echo or return the drop-down. Accepts 1|true (echo)
 *                                                 or 0|false (return). Default 1|true.
 *     @type int          $selected                Which user ID should be selected. Default 0.
 *     @type bool         $include_selected        Whether to always include the selected user ID in the drop-
 *                                                 down. Default false.
 *     @type string       $name                    Name attribute of select element. Default 'user'.
 *     @type string       $id                      ID attribute of the select element. Default is the value of $name.
 *     @type string       $class                   Class attribute of the select element. Default empty.
 *     @type int          $blog_id                 ID of blog (Multisite only). Default is ID of the current blog.
 *     @type string       $who                     Which type of users to query. Accepts only an empty string or
 *                                                 'authors'. Default empty.
 *     @type string|array $role                    An array or a comma-separated list of role names that users must
 *                                                 match to be included in results. Note that this is an inclusive
 *                                                 list: users must match *each* role. Default empty.
 *     @type array        $role__in                An array of role names. Matched users must have at least one of
 *                                                 these roles. Default empty array.
 *     @type array        $role__not_in            An array of role names to exclude. Users matching one or more of
 *                                                 these roles will not be included in results. Default empty array.
 * }
 * @return string String of HTML content.
 */
function wp_dropdown_users( $args = '' ) {
	$defaults = array(
		'show_option_all' => '', 'show_option_none' => '', 'hide_if_only_one_author' => '',
		'orderby' => 'display_name', 'order' => 'ASC',
		'include' => '', 'exclude' => '', 'multi' => 0,
		'show' => 'display_name', 'echo' => 1,
		'selected' => 0, 'name' => 'user', 'class' => '', 'id' => '',
		'blog_id' => get_current_blog_id(), 'who' => '', 'include_selected' => false,
		'option_none_value' => -1,
		'role' => '',
		'role__in' => array(),
		'role__not_in' => array(),
	);

	$defaults['selected'] = is_author() ? get_query_var( 'author' ) : 0;

	$r = wp_parse_args( $args, $defaults );

	$query_args = wp_array_slice_assoc( $r, array( 'blog_id', 'include', 'exclude', 'orderby', 'order', 'who', 'role', 'role__in', 'role__not_in' ) );

	$fields = array( 'ID', 'user_login' );

	$show = ! empty( $r['show'] ) ? $r['show'] : 'display_name';
	if ( 'display_name_with_login' === $show ) {
		$fields[] = 'display_name';
	} else {
		$fields[] = $show;
	}

	$query_args['fields'] = $fields;

	$show_option_all = $r['show_option_all'];
	$show_option_none = $r['show_option_none'];
	$option_none_value = $r['option_none_value'];

	/**
	 * Filters the query arguments for the list of users in the dropdown.
	 *
	 * @since 4.4.0
	 *
	 * @param array $query_args The query arguments for get_users().
	 * @param array $r          The arguments passed to wp_dropdown_users() combined with the defaults.
	 */
	$query_args = apply_filters( 'wp_dropdown_users_args', $query_args, $r );

	$users = get_users( $query_args );

	$output = '';
	if ( ! empty( $users ) && ( empty( $r['hide_if_only_one_author'] ) || count( $users ) > 1 ) ) {
		$name = esc_attr( $r['name'] );
		if ( $r['multi'] && ! $r['id'] ) {
			$id = '';
		} else {
			$id = $r['id'] ? " id='" . esc_attr( $r['id'] ) . "'" : " id='$name'";
		}
		$output = "<select name='{$name}'{$id} class='" . $r['class'] . "'>\n";

		if ( $show_option_all ) {
			$output .= "\t<option value='0'>$show_option_all</option>\n";
		}

		if ( $show_option_none ) {
			$_selected = selected( $option_none_value, $r['selected'], false );
			$output .= "\t<option value='" . esc_attr( $option_none_value ) . "'$_selected>$show_option_none</option>\n";
		}

		if ( $r['include_selected'] && ( $r['selected'] > 0 ) ) {
			$found_selected = false;
			$r['selected'] = (int) $r['selected'];
			foreach ( (array) $users as $user ) {
				$user->ID = (int) $user->ID;
				if ( $user->ID === $r['selected'] ) {
					$found_selected = true;
				}
			}

			if ( ! $found_selected ) {
				$users[] = get_userdata( $r['selected'] );
			}
		}

		foreach ( (array) $users as $user ) {
			if ( 'display_name_with_login' === $show ) {
				/* translators: 1: display name, 2: user_login */
				$display = sprintf( _x( '%1$s (%2$s)', 'user dropdown' ), $user->display_name, $user->user_login );
			} elseif ( ! empty( $user->$show ) ) {
				$display = $user->$show;
			} else {
				$display = '(' . $user->user_login . ')';
			}

			$_selected = selected( $user->ID, $r['selected'], false );
			$output .= "\t<option value='$user->ID'$_selected>" . esc_html( $display ) . "</option>\n";
		}

		$output .= "</select>";
	}

	/**
	 * Filters the wp_dropdown_users() HTML output.
	 *
	 * @since 2.3.0
	 *
	 * @param string $output HTML output generated by wp_dropdown_users().
	 */
	$html = apply_filters( 'wp_dropdown_users', $output );

	if ( $r['echo'] ) {
		echo $html;
	}
	return $html;
}

/**
 * Sanitize user field based on context.
 *
 * Possible context values are:  'raw', 'edit', 'db', 'display', 'attribute' and 'js'. The
 * 'display' context is used by default. 'attribute' and 'js' contexts are treated like 'display'
 * when calling filters.
 *
 * @since 2.3.0
 *
 * @param string $field   The user Object field name.
 * @param mixed  $value   The user Object value.
 * @param int    $user_id User ID.
 * @param string $context How to sanitize user fields. Looks for 'raw', 'edit', 'db', 'display',
 *                        'attribute' and 'js'.
 * @return mixed Sanitized value.
 */
function sanitize_user_field($field, $value, $user_id, $context) {
	$int_fields = array('ID');
	if ( in_array($field, $int_fields) )
		$value = (int) $value;

	if ( 'raw' == $context )
		return $value;

	if ( !is_string($value) && !is_numeric($value) )
		return $value;

	$prefixed = false !== strpos( $field, 'user_' );

	if ( 'edit' == $context ) {
		if ( $prefixed ) {

			/** This filter is documented in wp-includes/post.php */
			$value = apply_filters( "edit_{$field}", $value, $user_id );
		} else {

			/**
			 * Filters a user field value in the 'edit' context.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the prefixed user
			 * field being filtered, such as 'user_login', 'user_email', 'first_name', etc.
			 *
			 * @since 2.9.0
			 *
			 * @param mixed $value   Value of the prefixed user field.
			 * @param int   $user_id User ID.
			 */
			$value = apply_filters( "edit_user_{$field}", $value, $user_id );
		}

		if ( 'description' == $field )
			$value = esc_html( $value ); // textarea_escaped?
		else
			$value = esc_attr($value);
	} elseif ( 'db' == $context ) {
		if ( $prefixed ) {
			/** This filter is documented in wp-includes/post.php */
			$value = apply_filters( "pre_{$field}", $value );
		} else {

			/**
			 * Filters the value of a user field in the 'db' context.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the prefixed user
			 * field being filtered, such as 'user_login', 'user_email', 'first_name', etc.
 			 *
			 * @since 2.9.0
			 *
			 * @param mixed $value Value of the prefixed user field.
			 */
			$value = apply_filters( "pre_user_{$field}", $value );
		}
	} else {
		// Use display filters by default.
		if ( $prefixed ) {

			/** This filter is documented in wp-includes/post.php */
			$value = apply_filters( "{$field}", $value, $user_id, $context );
		} else {

			/**
			 * Filters the value of a user field in a standard context.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the prefixed user
			 * field being filtered, such as 'user_login', 'user_email', 'first_name', etc.
			 *
			 * @since 2.9.0
			 *
			 * @param mixed  $value   The user object value to sanitize.
			 * @param int    $user_id User ID.
			 * @param string $context The context to filter within.
			 */
			$value = apply_filters( "user_{$field}", $value, $user_id, $context );
		}
	}

	if ( 'user_url' == $field )
		$value = esc_url($value);

	if ( 'attribute' == $context ) {
		$value = esc_attr( $value );
	} elseif ( 'js' == $context ) {
		$value = esc_js( $value );
	}
	return $value;
}

/**
 * Update all user caches
 *
 * @since 3.0.0
 *
 * @param object|WP_User $user User object to be cached
 * @return bool|null Returns false on failure.
 */
function update_user_caches( $user ) {
	if ( $user instanceof WP_User ) {
		if ( ! $user->exists() ) {
			return false;
		}

		$user = $user->data;
	}

	wp_cache_add($user->ID, $user, 'users');
	wp_cache_add($user->user_login, $user->ID, 'userlogins');
	wp_cache_add($user->user_email, $user->ID, 'useremail');
	wp_cache_add($user->user_nicename, $user->ID, 'userslugs');
}

/**
 * Clean all user caches
 *
 * @since 3.0.0
 * @since 4.4.0 'clean_user_cache' action was added.
 *
 * @param WP_User|int $user User object or ID to be cleaned from the cache
 */
function clean_user_cache( $user ) {
	if ( is_numeric( $user ) )
		$user = new WP_User( $user );

	if ( ! $user->exists() )
		return;

	wp_cache_delete( $user->ID, 'users' );
	wp_cache_delete( $user->user_login, 'userlogins' );
	wp_cache_delete( $user->user_email, 'useremail' );
	wp_cache_delete( $user->user_nicename, 'userslugs' );

	/**
	 * Fires immediately after the given user's cache is cleaned.
	 *
	 * @since 4.4.0
	 *
	 * @param int     $user_id User ID.
	 * @param WP_User $user    User object.
	 */
	do_action( 'clean_user_cache', $user->ID, $user );
}

/**
 * Checks whether the given username exists.
 *
 * @since 2.0.0
 *
 * @param string $username Username.
 * @return int|false The user's ID on success, and false on failure.
 */
function username_exists( $username ) {
	if ( $user = get_user_by( 'login', $username ) ) {
		return $user->ID;
	}
	return false;
}

/**
 * Checks whether the given email exists.
 *
 * @since 2.1.0
 *
 * @param string $email Email.
 * @return int|false The user's ID on success, and false on failure.
 */
function email_exists( $email ) {
	if ( $user = get_user_by( 'email', $email) ) {
		return $user->ID;
	}
	return false;
}

/**
 * Checks whether a username is valid.
 *
 * @since 2.0.1
 * @since 4.4.0 Empty sanitized usernames are now considered invalid
 *
 * @param string $username Username.
 * @return bool Whether username given is valid
 */
function validate_username( $username ) {
	$sanitized = sanitize_user( $username, true );
	$valid = ( $sanitized == $username && ! empty( $sanitized ) );

	/**
	 * Filters whether the provided username is valid or not.
	 *
	 * @since 2.0.1
	 *
	 * @param bool   $valid    Whether given username is valid.
	 * @param string $username Username to check.
	 */
	return apply_filters( 'validate_username', $valid, $username );
}

/**
 * Insert a user into the database.
 *
 * Most of the `$userdata` array fields have filters associated with the values. Exceptions are
 * 'ID', 'rich_editing', 'comment_shortcuts', 'admin_color', 'use_ssl',
 * 'user_registered', and 'role'. The filters have the prefix 'pre_user_' followed by the field
 * name. An example using 'description' would have the filter called, 'pre_user_description' that
 * can be hooked into.
 *
 * @since 2.0.0
 * @since 3.6.0 The `aim`, `jabber`, and `yim` fields were removed as default user contact
 *              methods for new installs. See wp_get_user_contact_methods().
 * @since 4.7.0 The user's locale can be passed to `$userdata`.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array|object|WP_User $userdata {
 *     An array, object, or WP_User object of user data arguments.
 *
 *     @type int         $ID                   User ID. If supplied, the user will be updated.
 *     @type string      $user_pass            The plain-text user password.
 *     @type string      $user_login           The user's login username.
 *     @type string      $user_nicename        The URL-friendly user name.
 *     @type string      $user_url             The user URL.
 *     @type string      $user_email           The user email address.
 *     @type string      $display_name         The user's display name.
 *                                             Default is the user's username.
 *     @type string      $nickname             The user's nickname.
 *                                             Default is the user's username.
 *     @type string      $first_name           The user's first name. For new users, will be used
 *                                             to build the first part of the user's display name
 *                                             if `$display_name` is not specified.
 *     @type string      $last_name            The user's last name. For new users, will be used
 *                                             to build the second part of the user's display name
 *                                             if `$display_name` is not specified.
 *     @type string      $description          The user's biographical description.
 *     @type string|bool $rich_editing         Whether to enable the rich-editor for the user.
 *                                             False if not empty.
 *     @type string|bool $comment_shortcuts    Whether to enable comment moderation keyboard
 *                                             shortcuts for the user. Default false.
 *     @type string      $admin_color          Admin color scheme for the user. Default 'fresh'.
 *     @type bool        $use_ssl              Whether the user should always access the admin over
 *                                             https. Default false.
 *     @type string      $user_registered      Date the user registered. Format is 'Y-m-d H:i:s'.
 *     @type string|bool $show_admin_bar_front Whether to display the Admin Bar for the user on the
 *                                             site's front end. Default true.
 *     @type string      $role                 User's role.
 *     @type string      $locale               User's locale. Default empty.
 * }
 * @return int|WP_Error The newly created user's ID or a WP_Error object if the user could not
 *                      be created.
 */
function wp_insert_user( $userdata ) {
	global $wpdb;

	if ( $userdata instanceof stdClass ) {
		$userdata = get_object_vars( $userdata );
	} elseif ( $userdata instanceof WP_User ) {
		$userdata = $userdata->to_array();
	}

	// Are we updating or creating?
	if ( ! empty( $userdata['ID'] ) ) {
		$ID = (int) $userdata['ID'];
		$update = true;
		$old_user_data = get_userdata( $ID );

		if ( ! $old_user_data ) {
			return new WP_Error( 'invalid_user_id', __( 'Invalid user ID.' ) );
		}

		// hashed in wp_update_user(), plaintext if called directly
		$user_pass = ! empty( $userdata['user_pass'] ) ? $userdata['user_pass'] : $old_user_data->user_pass;
	} else {
		$update = false;
		// Hash the password
		$user_pass = wp_hash_password( $userdata['user_pass'] );
	}

	$sanitized_user_login = sanitize_user( $userdata['user_login'], true );

	/**
	 * Filters a username after it has been sanitized.
	 *
	 * This filter is called before the user is created or updated.
	 *
	 * @since 2.0.3
	 *
	 * @param string $sanitized_user_login Username after it has been sanitized.
	 */
	$pre_user_login = apply_filters( 'pre_user_login', $sanitized_user_login );

	//Remove any non-printable chars from the login string to see if we have ended up with an empty username
	$user_login = trim( $pre_user_login );

	// user_login must be between 0 and 60 characters.
	if ( empty( $user_login ) ) {
		return new WP_Error('empty_user_login', __('Cannot create a user with an empty login name.') );
	} elseif ( mb_strlen( $user_login ) > 60 ) {
		return new WP_Error( 'user_login_too_long', __( 'Username may not be longer than 60 characters.' ) );
	}

	if ( ! $update && username_exists( $user_login ) ) {
		return new WP_Error( 'existing_user_login', __( 'Sorry, that username already exists!' ) );
	}

	/**
	 * Filters the list of blacklisted usernames.
	 *
	 * @since 4.4.0
	 *
	 * @param array $usernames Array of blacklisted usernames.
	 */
	$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

	if ( in_array( strtolower( $user_login ), array_map( 'strtolower', $illegal_logins ) ) ) {
		return new WP_Error( 'invalid_username', __( 'Sorry