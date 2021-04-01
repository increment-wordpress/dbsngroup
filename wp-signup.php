<?php

/** Sets up the WordPress Environment. */
require( dirname(__FILE__) . '/wp-load.php' );

add_action( 'wp_head', 'wp_no_robots' );

require( dirname( __FILE__ ) . '/wp-blog-header.php' );

if ( is_array( get_site_option( 'illegal_names' )) && isset( $_GET[ 'new' ] ) && in_array( $_GET[ 'new' ], get_site_option( 'illegal_names' ) ) ) {
	wp_redirect( network_home_url() );
	die();
}

/**
 * Prints signup_header via wp_head
 *
 * @since MU
 */
function do_signup_header() {
	/**
	 * Fires within the head section of the site sign-up screen.
	 *
	 * @since 3.0.0
	 */
	do_action( 'signup_header' );
}
add_action( 'wp_head', 'do_signup_header' );

if ( !is_multisite() ) {
	wp_redirect( wp_registration_url() );
	die();
}

if ( !is_main_site() ) {
	wp_redirect( network_site_url( 'wp-signup.php' ) );
	die();
}

// Fix for page title
$wp_query->is_404 = false;

/**
 * Fires before the Site Signup page is loaded.
 *
 * @since 4.4.0
 */
do_action( 'before_signup_header' );

/**
 * Prints styles for front-end Multisite signup pages
 *
 * @since MU
 */
function wpmu_signup_stylesheet() {
	?>
	<style type="text/css">
		.mu_register { width: 90%; margin:0 auto; }
		.mu_register form { margin-top: 2em; }
		.mu_register .error { font-weight:700; padding:10px; color:#333333; background:#FFEBE8; border:1px solid #CC0000; }
		.mu_register input[type="submit"],
			.mu_register #blog_title,
			.mu_register #user_email,
			.mu_register #blogname,
			.mu_register #user_name { width:100%; font-size: 24px; margin:5px 0; }
		.mu_register #site-language { display: block; }
		.mu_register .prefix_address,
			.mu_register .suffix_address {font-size: 18px;display:inline; }
		.mu_register label { font-weight:700; font-size:15px; display:block; margin:10px 0; }
		.mu_register label.checkbox { display:inline; }
		.mu_register .mu_alert { font-weight:700; padding:10px; color:#333333; background:#ffffe0; border:1px solid #e6db55; }
	</style>
	<?php
}

add_action( 'wp_head', 'wpmu_signup_stylesheet' );
get_header( 'wp-signup' );

/**
 * Fires before the site sign-up form.
 *
 * @since 3.0.0
 */
do_action( 'before_signup_form' );
?>
<div id="signup-content" class="widecolumn">
<div class="mu_register wp-signup-container">
<?php
/**
 * Generates and displays the Signup and Create Site forms
 *
 * @since MU
 *
 * @param string          $blogname   The new site name.
 * @param string          $blog_title The new site title.
 * @param WP_Error|string $errors     A WP_Error object containing existing errors. Defaults to empty string.
 */
function show_blog_form( $blogname = '', $blog_title = '', $errors = '' ) {
	if ( ! is_wp_error( $errors ) ) {
		$errors = new WP_Error();
	}

	$current_network = get_network();
	// Blog name
	if ( !is_subdomain_install() )
		echo '<label for="blogname">' . __('Site Name:') . '</label>';
	else
		echo '<label for="blogname">' . __('Site Domain:') . '</label>';

	if ( $errmsg = $errors->get_error_message('blogname') ) { ?>
		<p class="error"><?php echo $errmsg ?></p>
	<?php }

	if ( !is_subdomain_install() )
		echo '<span class="prefix_address">' . $current_network->domain . $current_network->path . '</span><input name="blogname" type="text" id="blogname" value="'. esc_attr($blogname) .'" maxlength="60" /><br />';
	else
		echo '<input name="blogname" type="text" id="blogname" value="'.esc_attr($blogname).'" maxlength="60" /><span class="suffix_address">.' . ( $site_domain = preg_replace( '|^www\.|', '', $current_network->domain ) ) . '</span><br />';

	if ( ! is_user_logged_in() ) {
		if ( ! is_subdomain_install() ) {
			$site = $current_network->domain . $current_network->path . __( 'sitename' );
		} else {
			$site = __( 'domain' ) . '.' . $site_domain . $current_network->path;
		}

		/* translators: %s: site address */
		echo '<p>(<strong>' . sprintf( __( 'Your address will be %s.' ), $site ) . '</strong>) ' . __( 'Must be at least 4 characters, letters and numbers only. It cannot be changed, so choose carefully!' ) . '</p>';
	}

	// Blog Title
	?>
	<label for="blog_title"><?php _e('Site Title:') ?></label>
	<?php if ( $errmsg = $errors->get_error_message('blog_title') ) { ?>
		<p class="error"><?php echo $errmsg ?></p>
	<?php }
	echo '<input name="blog_title" type="text" id="blog_title" value="'.esc_attr($blog_title).'" />';
	?>

	<?php
	// Site Language.
	$languages = signup_get_available_languages();

	if ( ! empty( $languages ) ) :
		?>
		<p>
			<label for="site-language"><?php _e( 'Site Language:' ); ?></label>
			<?php
			// Network default.
			$lang = get_site_option( 'WPLANG' );

			if ( isset( $_POST['WPLANG'] ) ) {
				$lang = $_POST['WPLANG'];
			}

			// Use US English if the default isn't available.
			if ( ! in_array( $lang, $languages ) ) {
				$lang = '';
			}

			wp_dropdown_languages( array(
				'name'                        => 'WPLANG',
				'id'                          => 'site-language',
				'selected'                    => $lang,
				'languages'                   => $languages,
				'show_available_translations' => false,
			) );
			?>
		</p>
	<?php endif; // Languages. ?>

	<div id="privacy">
        <p class="privacy-intro">
            <label for="blog_public_on"><?php _e('Privacy:') ?></label>
            <?php _e( 'Allow search engines to index this site.' ); ?>
            <br style="clear:both" />
            <label class="checkbox" for="blog_public_on">
                <input type="radio" id="blog_public_on" name="blog_public" value="1" <?php if ( !isset( $_POST['blog_public'] ) || $_POST['blog_public'] == '1' ) { ?>checked="checked"<?php } ?> />
                <strong><?php _e( 'Yes' ); ?></strong>
            </label>
            <label class="checkbox" for="blog_public_off">
                <input type="radio" id="blog_public_off" name="blog_public" value="0" <?php if ( isset( $_POST['blog_public'] ) && $_POST['blog_public'] == '0' ) { ?>checked="checked"<?php } ?> />
                <strong><?php _e( 'No' ); ?></strong>
            </label>
        </p>
	</div>

	<?php
	/**
	 * Fires after the site sign-up form.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Error $errors A WP_Error object possibly containing 'blogname' or 'blog_title' errors.
	 */
	do_action( 'signup_blogform', $errors );
}

/**
 * Validate the new site signup
 *
 * @since MU
 *
 * @return array Contains the new site data and error messages.
 */
function validate_blog_form() {
	$user = '';
	if ( is_user_logged_in() )
		$user = wp_get_current_user();

	return wpmu_validate_blog_signup($_POST['blogname'], $_POST['blog_title'], $user);
}

/**
 * Display user registration form
 *
 * @since MU
 *
 * @param string          $user_name  The entered username.
 * @param string          $user_email The entered email address.
 * @param WP_Error|string $errors     A WP_Error object containing existing errors. Defaults to empty string.
 */
function show_user_form($user_name = '', $user_email = '', $errors = '') {
	if ( ! is_wp_error( $errors ) ) {
		$errors = new WP_Error();
	}

	// User name
	echo '<label for="user_name">' . __('Username:') . '</label>';
	if ( $errmsg = $errors->get_error_message('user_name') ) {
		echo '<p class="error">'.$errmsg.'</p>';
	}
	echo '<input name="user_name" type="text" id="user_name" value="'. esc_attr( $user_name ) .'" autocapitalize="none" autocorrect="off" maxlength="60" /><br />';
	_e( '(Must be at least 4 characters, letters and numbers only.)' );
	?>

	<label for="user_email"><?php _e( 'Email&nbsp;Address:' ) ?></label>
	<?php if ( $errmsg = $errors->get_error_message('user_email') ) { ?>
		<p class="error"><?php echo $errmsg ?></p>
	<?php } ?>
	<input name="user_email" type="email" id="user_email" value="<?php  echo esc_attr($user_email) ?>" maxlength="200" /><br /><?php _e('We send your registration email to this address. (Double-check your email address before continuing.)') ?>
	<?php
	if ( $errmsg = $errors->get_error_message('generic') ) {
		echo '<p class="error">' . $errmsg . '</p>';
	}
	/**
	 * Fires at the end of the user registration form on the site sign-up form.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Error $errors A WP_Error object containing containing 'user_name' or 'user_email' errors.
	 */
	do_action( 'signup_extra_fields', $errors );
}

/**
 * Validate user signup name and email
 *
 * @since MU
 *
 * @return array Contains username, email, and error messages.
 */
function validate_user_form() {
	return wpmu_validate_user_signup($_POST['user_name'], $_POST['user_email']);
}

/**
 * Allow returning users to sign up for another site
 *
 * @since MU
 *
 * @param string          $blogname   The new site name
 * @param string          $blog_title The new site title.
 * @param WP_Error|string $errors     A WP_Error object containing existing errors. Defaults to empty string.
 */
function signup_another_blog( $blogname = '', $blog_title = '', $errors = '' ) {
	$current_user = wp_get_current_user();

	if ( ! is_wp_error($errors) ) {
		$errors = new WP_Error();
	}

	$signup_defaults = array(
		'blogname'   => $blogname,
		'blog_title' => $blog_title,
		'errors'     => $errors
	);

	/**
	 * Filters the default site sign-up variables.
	 *
	 * @since 3.0.0
	 *
	 * @param array $signup_defaults {
	 *     An array of default site sign-up variables.
	 *
	 *     @type string   $blogname   The site blogname.
	 *     @type string   $blog_title The site title.
	 *     @type WP_Error $errors     A WP_Error object possibly containing 'blogname' or 'blog_title' errors.
	 * }
	 */
	$filtered_results = apply_filters( 'signup_another_blog_init', $signup_defaults );

	$blogname = $filtered_results['blogname'];
	$blog_title = $filtered_results['blog_title'];
	$errors = $filtered_results['errors'];

	echo '<h2>' . sprintf( __( 'Get <em>another</em> %s site in seconds' ), get_network()->site_name ) . '</h2>';

	if ( $errors->get_error_code() ) {
		echo '<p>' . __( 'There was a problem, please correct the form below and try again.' ) . '</p>';
	}
	?>
	<p><?php printf( __( 'Welcome back, %s. By filling out the form below, you can <strong>add another site to your account</strong>. There is no limit to the number of sites you can have, so create to your heart&#8217;s content, but write responsibly!' ), $current_user->display_name ) ?></p>

	<?php
	$blogs = get_blogs_of_user($current_user->ID);
	if ( !empty($blogs) ) { ?>

			<p><?php _e( 'Sites you are already a member of:' ) ?></p>
			<ul>
				<?php foreach ( $blogs as $blog ) {
					$home_url = get_home_url( $blog->userblog_id );
					echo '<li><a href="' . esc_url( $home_url ) . '">' . $home_url . '</a></li>';
				} ?>
			</ul>
	<?php } ?>

	<p><?php _e( 'If you&#8217;re not going to use a great site domain, leave it for a new user. Now have at it!' ) ?></p>
	<form id="setupform" method="post" action="wp-signup.php">
		<input type="hidden" name="stage" value="gimmeanotherblog" />
		<?php
		/**
		 * Hidden sign-up form fields output when creating another site or user.
		 *
		 * @since MU
		 *
		 * @param string $context A string describing the steps of the sign-up process. The value can be
		 *                        'create-another-site', 'validate-user', or 'validate-site'.
		 */
		do_action( 'signup_hidden_fields', 'create-another-site' );
		?>
		<?php show_blog_form($blogname, $blog_title, $errors); ?>
		<p class="submit"><input type="submit" name="submit" class="submit" value="<?php esc_attr_e( 'Create Site' ) ?>" /></p>
	</form>
	<?php
}

/**
 * Validate a new site signup.
 *
 * @since MU
 *
 * @return null|bool True if site signup was validated, false if error.
 *                   The function halts all execution if the user is not logged in.
 */
function validate_another_blog_signup() {
	global $wpdb, $blogname, $blog_title, $errors, $domain, $path;
	$current_user = wp_get_current_user();
	if ( ! is_user_logged_in() ) {
		die();
	}

	$result = validate_blog_form();

	// Extracted values set/overwrite globals.
	$domain = $result['domain'];
	$path = $result['path'];
	$blogname = $result['blogname'];
	$blog_title = $result['blog_title'];
	$errors = $result['errors'];

	if ( $errors->get_error_code() ) {
		signup_another_blog($blogname, $blog_title, $errors);
		return false;
	}

	$public = (int) $_POST['blog_public'];

	$blog_meta_defaults = array(
		'lang_id' => 1,
		'public'  => $public
	);

	// Handle the language setting for the new site.
	if ( ! empty( $_POST['WPLANG'] ) ) {

		$languages = signup_get_available_languages();

		if ( in_array( $_POST['WPLANG'], $languages ) ) {
			$language = wp_unslash( sanitize_text_field( $_POST['WPLANG'] ) );

			if ( $language ) {
				$blog_meta_defaults['WPLANG'] = $language;
			}
		}

	}

	/**
	 * Filters the new site meta variables.
	 *
	 * Use the {@see 'add_signup_meta'} filter instead.
	 *
	 * @since MU
	 * @deprecated 3.0.0 Use the {@see 'add_signup_meta'} filter instead.
	 *
	 * @param array $blog_meta_defaults An array of default blog meta variables.
	 */
	$meta_defaults = apply_filters( 'signup_create_blog_meta', $blog_meta_defaults );

	/**
	 * Filters the new default site meta variables.
	 *
	 * @since 3.0.0
	 *
	 * @param array $meta {
	 *     An array of default site meta variables.
	 *
	 *     @type int $lang_id     The language ID.
	 *     @type int $blog_public Whether search engines should be discouraged from indexing the site. 1 for true, 0 for false.
	 * }
	 */
	$meta = apply_filters( 'add_signup_meta', $meta_defaults );

	$blog_id = wpmu_create_blog( $domain, $path, $blog_title, $current_user->ID, $meta, $wpdb->siteid );

	if ( is_wp_error( $blog_id ) ) {
		return false;
	}

	confirm_another_blog_signup( $domain, $path, $blog_title, $current_user->user_login, $current_user->user_email, $meta, $blog_id );
	return true;
}

/**
 * Confirm a new site signup.
 *
 * @since MU
 * @since 4.4.0 Added the `$blog_id` parameter.
 *
 * @param string $domain     The domain URL.
 * @param string $path       The site root path.
 * @param string $blog_title The site title.
 * @param string $user_name  The username.
 * @param string $user_email The user's email address.
 * @param array  $meta       Any additional meta from the {@see 'add_signup_meta'} filter in validate_blog_signup().
 * @param int    $blog_id    The site ID.
 */
function confirm_another_blog_signup( $domain, $path, $blog_title, $user_name, $user_email = '', $meta = array(), $blog_id = 0 ) {

	if ( $blog_id ) {
		switch_to_blog( $blog_id );
		$home_url  = home_url( '/' );
		$login_url = wp_login_url();
		restore_current_blog();
	} else {
		$home_url  = 'http://' . $domain . $path;
		$login_url = 'http://' . $domain . $path . 'wp-login.php';
	}

	$site = sprintf( '<a href="%1$s">%2$s</a>',
		esc_url( $home_url ),
		$blog_title
	);

	?>
	<h2><?php
		/* translators: %s: site name */
		printf( __( 'The site %s is yours.' ), $site );
	?></h2>
	<p>
		<?php printf(
			/* translators: 1: home URL, 2: site address, 3: login URL, 4: username */
			__( '<a href="%1$s">%2$s</a> is your new site. <a href="%3$s">Log in</a> as &#8220;%4$s&#8221; using your existing password.' ),
			esc_url( $home_url ),
			untrailingslashit( $domain . $path ),
			esc_url( $login_url ),
			$user_name
		); ?>
	</p>
	<?php
	/**
	 * Fires when the site or user sign-up process is complete.
	 *
	 * @since 3.0.0
	 */
	do_action( 'signup_finished' );
}

/**
 * Setup the new user signup process
 *
 * @since MU
 *
 * @param string          $user_name  The username.
 * @param string          $user_email The user's email.
 * @param WP_Error|string $errors     A WP_Error object containing existing errors. Defaults to empty string.
 */
function signup_user( $user_name = '', $user_email = '', $errors = '' ) {
	global $active_signup;

	if ( !is_wp_error($errors) )
		$errors = new WP_Error();

	$signup_for = isset( $_POST[ 'signup_for' ] ) ? esc_html( $_POST[ 'signup_for' ] ) : 'blog';

	$signup_user_defaults = array(
		'user_name'  => $user_name,
		'user_email' => $user_email,
		'errors'     => $errors,
	);

	/**
	 * Filters the default user variables used on the user sign-up form.
	 *
	 * @since 3.0.0
	 *
	 * @param array $signup_user_defaults {
	 *     An array of default user variables.
	 *
	 *     @type string   $user_name  The user username.
	 *     @type string   $user_email The user email address.
	 *     @type WP_Error $errors     A WP_Error object with possible errors relevant to the sign-up user.
	 * }
	 */
	$filtered_results = apply_filters(