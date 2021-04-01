<?php
/**
 * WordPress Upgrade API
 *
 * Most of the functions are pluggable and can be overwritten.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Include user install customize script. */
if ( file_exists(WP_CONTENT_DIR . '/install.php') )
	require (WP_CONTENT_DIR . '/install.php');

/** WordPress Administration API */
require_once(ABSPATH . 'wp-admin/includes/admin.php');

/** WordPress Schema API */
require_once(ABSPATH . 'wp-admin/includes/schema.php');

if ( !function_exists('wp_install') ) :
/**
 * Installs the site.
 *
 * Runs the required functions to set up and populate the database,
 * including primary admin user and initial options.
 *
 * @since 2.1.0
 *
 * @param string $blog_title    Site title.
 * @param string $user_name     User's username.
 * @param string $user_email    User's email.
 * @param bool   $public        Whether site is public.
 * @param string $deprecated    Optional. Not used.
 * @param string $user_password Optional. User's chosen password. Default empty (random password).
 * @param string $language      Optional. Language chosen. Default empty.
 * @return array Array keys 'url', 'user_id', 'password', and 'password_message'.
 */
function wp_install( $blog_title, $user_name, $user_email, $public, $deprecated = '', $user_password = '', $language = '' ) {
	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '2.6.0' );

	wp_check_mysql_version();
	wp_cache_flush();
	make_db_current_silent();
	populate_options();
	populate_roles();

	update_option('blogname', $blog_title);
	update_option('admin_email', $user_email);
	update_option('blog_public', $public);

	// Freshness of site - in the future, this could get more specific about actions taken, perhaps.
	update_option( 'fresh_site', 1 );

	if ( $language ) {
		update_option( 'WPLANG', $language );
	}

	$guessurl = wp_guess_url();

	update_option('siteurl', $guessurl);

	// If not a public blog, don't ping.
	if ( ! $public )
		update_option('default_pingback_flag', 0);

	/*
	 * Create default user. If the user already exists, the user tables are
	 * being shared among sites. Just set the role in that case.
	 */
	$user_id = username_exists($user_name);
	$user_password = trim($user_password);
	$email_password = false;
	if ( !$user_id && empty($user_password) ) {
		$user_password = wp_generate_password( 12, false );
		$message = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
		$user_id = wp_create_user($user_name, $user_password, $user_email);
		update_user_option($user_id, 'default_password_nag', true, true);
		$email_password = true;
	} elseif ( ! $user_id ) {
		// Password has been provided
		$message = '<em>'.__('Your chosen password.').'</em>';
		$user_id = wp_create_user($user_name, $user_password, $user_email);
	} else {
		$message = __('User already exists. Password inherited.');
	}

	$user = new WP_User($user_id);
	$user->set_role('administrator');

	wp_install_defaults($user_id);

	wp_install_maybe_enable_pretty_permalinks();

	flush_rewrite_rules();

	wp_new_blog_notification($blog_title, $guessurl, $user_id, ($email_password ? $user_password : __('The password you chose during the install.') ) );

	wp_cache_flush();

	/**
	 * Fires after a site is fully installed.
	 *
	 * @since 3.9.0
	 *
	 * @param WP_User $user The site owner.
	 */
	do_action( 'wp_install', $user );

	return array('url' => $guessurl, 'user_id' => $user_id, 'password' => $user_password, 'password_message' => $message);
}
endif;

if ( !function_exists('wp_install_defaults') ) :
/**
 * Creates the initial content for a newly-installed site.
 *
 * Adds the default "Uncategorized" category, the first post (with comment),
 * first page, and default widgets for default theme for the current version.
 *
 * @since 2.1.0
 *
 * @global wpdb       $wpdb
 * @global WP_Rewrite $wp_rewrite
 * @global string     $table_prefix
 *
 * @param int $user_id User ID.
 */
function wp_install_defaults( $user_id ) {
	global $wpdb, $wp_rewrite, $table_prefix;

	// Default category
	$cat_name = __('Uncategorized');
	/* translators: Default category slug */
	$cat_slug = sanitize_title(_x('Uncategorized', 'Default category slug'));

	if ( global_terms_enabled() ) {
		$cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
		if ( $cat_id == null ) {
			$wpdb->insert( $wpdb->sitecategories, array('cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true)) );
			$cat_id = $wpdb->insert_id;
		}
		update_option('default_category', $cat_id);
	} else {
		$cat_id = 1;
	}

	$wpdb->insert( $wpdb->terms, array('term_id' => $cat_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0) );
	$wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $cat_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1));
	$cat_tt_id = $wpdb->insert_id;

	// First post
	$now = current_time( 'mysql' );
	$now_gmt = current_time( 'mysql', 1 );
	$first_post_guid = get_option( 'home' ) . '/?p=1';

	if ( is_multisite() ) {
		$first_post = get_site_option( 'first_post' );

		if ( ! $first_post ) {
			/* translators: %s: site link */
			$first_post = __( 'Welcome to %s. This is your first post. Edit or delete it, then start blogging!' );
		}

		$first_post = sprintf( $first_post,
			sprintf( '<a href="%s">%s</a>', esc_url( network_home_url() ), get_network()->site_name )
		);

		// Back-compat for pre-4.4
		$first_post = str_replace( 'SITE_URL', esc_url( network_home_url() ), $first_post );
		$first_post = str_replace( 'SITE_NAME', get_network()->site_name, $first_post );
	} else {
		$first_post = __( 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' );
	}

	$wpdb->insert( $wpdb->posts, array(
		'post_author' => $user_id,
		'post_date' => $now,
		'post_date_gmt' => $now_gmt,
		'post_content' => $first_post,
		'post_excerpt' => '',
		'post_title' => __('Hello world!'),
		/* translators: Default post slug */
		'post_name' => sanitize_title( _x('hello-world', 'Default post slug') ),
		'post_modified' => $now,
		'post_modified_gmt' => $now_gmt,
		'guid' => $first_post_guid,
		'comment_count' => 1,
		'to_ping' => '',
		'pinged' => '',
		'post_content_filtered' => ''
	));
	$wpdb->insert( $wpdb->term_relationships, array('term_taxonomy_id' => $cat_tt_id, 'object_id' => 1) );

	// Default comment
	if ( is_multisite() ) {
		$first_comment_author = get_site_option( 'first_comment_author' );
		$first_comment_email = get_site_option( 'first_comment_email' );
		$first_comment_url = get_site_option( 'first_comment_url', network_home_url() );
		$first_comment = get_site_option( 'first_comment' );
	}

	$first_comment_author = ! empty( $first_comment_author ) ? $first_comment_author : __( 'A WordPress Commenter' );
	$first_comment_email = ! empty( $first_comment_email ) ? $first_comment_email : 'wapuu@wordpress.example';
	$first_comment_url = ! empty( $first_comment_url ) ? $first_comment_url : 'http://goodherbwebmart.com/';
	$first_comment = ! empty( $first_comment ) ? $first_comment :  __( 'Hi, this is a comment.
To get started with moderating, editing, and deleting comments, please visit the Comments screen in the dashboard.
Commenter avatars come from <a href="http://goodherbwebmart.com/">Gravatar</a>.' );
	$wpdb->insert( $wpdb->comments, array(
		'comment_post_ID' => 1,
		'comment_author' => $first_comment_author,
		'comment_author_email' => $first_comment_email,
		'comment_author_url' => $first_comment_url,
		'comment_date' => $now,
		'comment_date_gmt' => $now_gmt,
		'comment_content' => $first_comment
	));

	// First Page
	if ( is_multisite() )
		$first_page = get_site_option( 'first_page' );

	$first_page = ! empty( $first_page ) ? $first_page : sprintf( __( "This is an example page. It's different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors. It might say something like this:

<blockquote>Hi there! I'm a bike messenger by day, aspiring actor by night, and this is my website. I live in Los Angeles, have a great dog named Jack, and I like pi&#241;a coladas. (And gettin' caught in the rain.)</blockquote>

...or something like this:

<blockquote>The XYZ Doohickey Company was founded in 1971, and has been providing quality doohickeys to the public ever since. Located in Gotham City, XYZ employs over 2,000 people and does all kinds of awesome things for the Gotham community.</blockquote>

As a new WordPress user, you should go to <a href=\"%s\">your dashboard</a> to delete this page and create new pages for your content. Have fun!" ), admin_url() );

	$first_post_guid = get_option('home') . '/?page_id=2';
	$wpdb->insert( $wpdb->posts, array(
		'post_author' => $user_id,
		'post_date' => $now,
		'post_date_gmt' => $now_gmt,
		'post_content' => $first_page,
		'post_excerpt' => '',
		'comment_status' => 'closed',
		'post_title' => __( 'Sample Page' ),
		/* translators: Default page slug */
		'post_name' => __( 'sample-page' ),
		'post_modified' => $now,
		'post_modified_gmt' => $now_gmt,
		'guid' => $first_post_guid,
		'post_type' => 'page',
		'to_ping' => '',
		'pinged' => '',
		'post_content_filtered' => ''
	));
	$wpdb->insert( $wpdb->postmeta, array( 'post_id' => 2, 'meta_key' => '_wp_page_template', 'meta_value' => 'default' ) );

	// Set up default widgets for default theme.
	update_option( 'widget_search', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
	update_option( 'widget_recent-posts', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
	update_option( 'widget_recent-comments', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
	update_option( 'widget_archives', array ( 2 => array ( 'title' => '', 'count' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
	update_option( 'widget_categories', array ( 2 => array ( 'title' => '', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
	update_option( 'widget_meta', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
	update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array(), 'sidebar-1' => array( 0 => 'search-2', 1 => 'recent-posts-2', 2 => 'recent-comments-2', 3 => 'archives-2', 4 => 'categories-2', 5 => 'meta-2' ), 'sidebar-2' => array(), 'sidebar-3' => array(), 'array_version' => 3 ) );
	if ( ! is_multisite() )
		update_user_meta( $user_id, 'show_welcome_panel', 1 );
	elseif ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) )
		update_user_meta( $user_id, 'show_welcome_panel', 2 );

	if ( is_multisite() ) {
		// Flush rules to pick up the new page.
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		$user = new WP_User($user_id);
		$wpdb->update( $wpdb->options, array('option_value' => $user->user_email), array('option_name' => 'admin_email') );

		// Remove all perms except for the login user.
		$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'user_level') );
		$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'capabilities') );

		// Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.) TODO: Get previous_blog_id.
		if ( !is_super_admin( $user_id ) && $user_id != 1 )
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id , 'meta_key' => $wpdb->base_prefix.'1_capabilities' ) );
	}
}
endif;

/**
 * Maybe enable pretty permalinks on install.
 *
 * If after enabling pretty permalinks don't work, fallback to query-string permalinks.
 *
 * @since 4.2.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @return bool Whether pretty permalinks are enabled. False otherwise.
 */
function wp_install_maybe_enable_pretty_permalinks() {
	global $wp_rewrite;

	// Bail if a permalink structure is already enabled.
	if ( get_option( 'permalink_structure' ) ) {
		return true;
	}

	/*
	 * The Permalink structures to attempt.
	 *
	 * The first is designed for mod_rewrite or nginx rewriting.
	 *
	 * The second is PATHINFO-based permalinks for web server configurations
	 * without a true rewrite module enabled.
	 */
	$permalink_structures = array(
		'/%year%/%monthnum%/%day%/%postname%/',
		'/index.php/%year%/%monthnum%/%day%/%postname%/'
	);

	foreach ( (array) $permalink_structures as $permalink_structure ) {
		$wp_rewrite->set_permalink_structure( $permalink_structure );

		/*
	 	 * Flush rules with the hard option to force refresh of the web-server's
	 	 * rewrite config file (e.g. .htaccess or web.config).
	 	 */
		$wp_rewrite->flush_rules( true );

		$test_url = '';

		// Test against a real WordPress Post
		$first_post = get_page_by_path( sanitize_title( _x( 'hello-world', 'Default post slug' ) ), OBJECT, 'post' );
		if ( $first_post ) {
			$test_url = get_permalink( $first_post->ID );
		}

		/*
	 	 * Send a request to the site, and check whether
	 	 * the 'x-pingback' header is returned as expected.
	 	 *
	 	 * Uses wp_remote_get() instead of wp_remote_head() because web servers
	 	 * can block head requests.
	 	 */
		$response          = wp_remote_get( $test_url, array( 'timeout' => 5 ) );
		$x_pingback_header = wp_remote_retrieve_header( $response, 'x-pingback' );
		$pretty_permalinks = $x_pingback_header && $x_pingback_header === get_bloginfo( 'pingback_url' );

		if ( $pretty_permalinks ) {
			return true;
		}
	}

	/*
	 * If it makes it this far, pretty permalinks failed.
	 * Fallback to query-string permalinks.
	 */
	$wp_rewrite->set_permalink_structure( '' );
	$wp_rewrite->flush_rules( true );

	return false;
}

if ( !function_exists('wp_new_blog_notification') ) :
/**
 * Notifies the site admin that the setup is complete.
 *
 * Sends an email with wp_mail to the new administrator that the site setup is complete,
 * and provides them with a record of their login credentials.
 *
 * @since 2.1.0
 *
 * @param string $blog_title Site title.
 * @param string $blog_url   Site url.
 * @param int    $user_id    User ID.
 * @param string $password   User's Password.
 */
function wp_new_blog_notification($blog_title, $blog_url, $user_id, $password) {
	$user = new WP_User( $user_id );
	$email = $user->user_email;
	$name = $user->user_login;
	$login_url = wp_login_url();
	/* translators: New site notification email. 1: New site URL, 2: User login, 3: User password or password reset link, 4: Login URL */
	$message = sprintf( __( "Your new WordPress site has been successfully set up at:

%1\$s

You can log in to the administrator account with the following information:

Username: %2\$s
Password: %3\$s
Log in here: %4\$s

We hope you enjoy your new site. Thanks!

--The WordPress Team
http://goodherbwebmart.com/
"), $blog_url, $name, $password, $login_url );

	@wp_mail($email, __('New WordPress Site'), $message);
}
endif;

if ( !function_exists('wp_upgrade') ) :
/**
 * Runs WordPress Upgrade functions.
 *
 * Upgrades the database if needed during a site update.
 *
 * @since 2.1.0
 *
 * @global int  $wp_current_db_version
 * @global int  $wp_db_version
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wp_upgrade() {
	global $wp_current_db_version, $wp_db_version, $wpdb;

	$wp_current_db_version = __get_option('db_version');

	// We are up-to-date. Nothing to do.
	if ( $wp_db_version == $wp_current_db_version )
		return;

	if ( ! is_blog_installed() )
		return;

	wp_check_mysql_version();
	wp_cache_flush();
	pre_schema_upgrade();
	make_db_current_silent();
	upgrade_all();
	if ( is_multisite() && is_main_site() )
		upgrade_network();
	wp_cache_flush();

	if ( is_multisite() ) {
		if ( $wpdb->get_row( "SELECT blog_id FROM {$wpdb->blog_versions} WHERE blog_id = '{$wpdb->blogid}'" ) )
			$wpdb->query( "UPDATE {$wpdb->blog_versions} SET db_version = '{$wp_db_version}' WHERE blog_id = '{$wpdb->blogid}'" );
		else
			$wpdb->query( "INSERT INTO {$wpdb->blog_versions} ( `blog_id` , `db_version` , `last_updated` ) VALUES ( '{$wpdb->blogid}', '{$wp_db_version}', NOW());" );
	}

	/**
	 * Fires after a site is fully upgraded.
	 *
	 * @since 3.9.0
	 *
	 * @param int $wp_db_version         The new $wp_db_version.
	 * @param int $wp_current_db_version The old (current) $wp_db_version.
	 */
	do_action( 'wp_upgrade', $wp_db_version, $wp_current_db_version );
}
endif;

/**
 * Functions to be called in install and upgrade scripts.
 *
 * Contains conditional checks to determine which upgrade scripts to run,
 * based on database version and WP version being updated-to.
 *
 * @ignore
 * @since 1.0.1
 *
 * @global int $wp_current_db_version
 * @global int $wp_db_version
 */
function upgrade_all() {
	global $wp_current_db_version, $wp_db_version;
	$wp_current_db_version = __get_option('db_version');

	// We are up-to-date. Nothing to do.
	if ( $wp_db_version == $wp_current_db_version )
		return;

	// If the version is not set in the DB, try to guess the version.
	if ( empty($wp_current_db_version) ) {
		$wp_current_db_version = 0;

		// If the template option exists, we have 1.5.
		$template = __get_option('template');
		if ( !empty($template) )
			$wp_current_db_version = 2541;
	}

	if ( $wp_current_db_version < 6039 )
		upgrade_230_options_table();

	populate_options();

	if ( $wp_current_db_version < 2541 ) {
		upgrade_100();
		upgrade_101();
		upgrade_110();
		upgrade_130();
	}

	if ( $wp_current_db_version < 3308 )
		upgrade_160();

	if ( $wp_current_db_version < 4772 )
		upgrade_210();

	if ( $wp_current_db_version < 4351 )
		upgrade_old_slugs();

	if ( $wp_current_db_version < 5539 )
		upgrade_230();

	if ( $wp_current_db_version < 6124 )
		upgrade_230_old_tables();

	if ( $wp_current_db_version < 7499 )
		upgrade_250();

	if ( $wp_current_db_version < 7935 )
		upgrade_252();

	if ( $wp_current_db_version < 8201 )
		upgrade_260();

	if ( $wp_current_db_version < 8989 )
		upgrade_270();

	if ( $wp_current_db_version < 10360 )
		upgrade_280();

	if ( $wp_current_db_version < 11958 )
		upgrade_290();

	if ( $wp_current_db_version < 15260 )
		upgrade_300();

	if ( $wp_current_db_version < 19389 )
		upgrade_330();

	if ( $wp_current_db_version < 20080 )
		upgrade_340();

	if ( $wp_current_db_version < 22422 )
		upgrade_350();

	if ( $wp_current_db_version < 25824 )
		upgrade_370();

	if ( $wp_current_db_version < 26148 )
		upgrade_372();

	if ( $wp_current_db_version < 26691 )
		upgrade_380();

	if ( $wp_current_db_version < 29630 )
		upgrade_400();

	if ( $wp_current_db_version < 33055 )
		upgrade_430();

	if ( $wp_current_db_version < 33056 )
		upgrade_431();

	if ( $wp_current_db_version < 35700 )
		upgrade_440();

	if ( $wp_current_db_version < 36686 )
		upgrade_450();

	if ( $wp_current_db_version < 37965 )
		upgrade_460();

	maybe_disable_link_manager();

	maybe_disable_automattic_widgets();

	update_option( 'db_version', $wp_db_version );
	update_option( 'db_upgraded', true );
}

/**
 * Execute changes made in WordPress 1.0.
 *
 * @ignore
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_100() {
	global $wpdb;

	// Get the title and ID of every post, post_name to check if it already has a value
	$posts = $wpdb->get_results("SELECT ID, post_title, post_name FROM $wpdb->posts WHERE post_name = ''");
	if ($posts) {
		foreach ($posts as $post) {
			if ('' == $post->post_name) {
				$newtitle = sanitize_title($post->post_title);
				$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET post_name = %s WHERE ID = %d", $newtitle, $post->ID) );
			}
		}
	}

	$categories = $wpdb->get_results("SELECT cat_ID, cat_name, category_nicename FROM $wpdb->categories");
	foreach ($categories as $category) {
		if ('' == $category->category_nicename) {
			$newtitle = sanitize_title($category->cat_name);
			$wpdb->update( $wpdb->categories, array('category_nicename' => $newtitle), array('cat_ID' => $category->cat_ID) );
		}
	}

	$sql = "UPDATE $wpdb->options
		SET option_value = REPLACE(option_value, 'wp-links/links-images/', 'wp-images/links/')
		WHERE option_name LIKE %s
		AND option_value LIKE %s";
	$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( 'links_rating_image' ) . '%', $wpdb->esc_like( 'wp-links/links-images/' ) . '%' ) );

	$done_ids = $wpdb->get_results("SELECT DISTINCT post_id FROM $wpdb->post2cat");
	if ($done_ids) :
		$done_posts = array();
		foreach ($done_ids as $done_id) :
			$done_posts[] = $done_id->post_id;
		endforeach;
		$catwhere = ' AND ID NOT IN (' . implode(',', $done_posts) . ')';
	else:
		$catwhere = '';
	endif;

	$allposts = $wpdb->get_results("SELECT ID, post_category FROM $wpdb->posts WHERE post_category != '0' $catwhere");
	if ($allposts) :
		foreach ($allposts as $post) {
			// Check to see if it's already been imported
			$cat = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->post2cat WHERE post_id = %d AND category_id = %d", $post->ID, $post->post_category) );
			if (!$cat && 0 != $post->post_category) { // If there's no result
				$wpdb->insert( $wpdb->post2cat, array('post_id' => $post->ID, 'category_id' => $post->post_category) );
			}
		}
	endif;
}

/**
 * Execute changes made in WordPress 1.0.1.
 *
 * @ignore
 * @since 1.0.1
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_101() {
	global $wpdb;

	// Clean up indices, add a few
	add_clean_index($wpdb->posts, 'post_name');
	add_clean_index($wpdb->posts, 'post_status');
	add_clean_index($wpdb->categories, 'category_nicename');
	add_clean_index($wpdb->comments, 'comment_approved');
	add_clean_index($wpdb->comments, 'comment_post_ID');
	add_clean_index($wpdb->links , 'link_category');
	add_clean_index($wpdb->links , 'link_visible');
}

/**
 * Execute changes made in WordPress 1.2.
 *
 * @ignore
 * @since 1.2.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_110() {
	global $wpdb;

	// Set user_nicename.
	$users = $wpdb->get_results("SELECT ID, user_nickname, user_nicename FROM $wpdb->users");
	foreach ($users as $user) {
		if ('' == $user->user_nicename) {
			$newname = sanitize_title($user->user_nickname);
			$wpdb->update( $wpdb->users, array('user_nicename' => $newname), array('ID' => $user->ID) );
		}
	}

	$users = $wpdb->get_results("SELECT ID, user_pass from $wpdb->users");
	foreach ($users as $row) {
		if (!preg_match('/^[A-Fa-f0-9]{32}$/', $row->user_pass)) {
			$wpdb->update( $wpdb->users, array('user_pass' => md5($row->user_pass)), array('ID' => $row->ID) );
		}
	}

	// Get the GMT offset, we'll use that later on
	$all_options = get_alloptions_110();

	$time_difference = $all_options->time_difference;

		$server_time = time()+date('Z');
	$weblogger_time = $server_time + $time_difference * HOUR_IN_SECONDS;
	$gmt_time = time();

	$diff_gmt_server = ($gmt_time - $server_time) / HOUR_IN_SECONDS;
	$diff_weblogger_server = ($weblogger_time - $server_time) / HOUR_IN_SECONDS;
	$diff_gmt_weblogger = $diff_gmt_server - $diff_weblogger_server;
	$gmt_offset = -$diff_gmt_weblogger;

	// Add a gmt_offset option, with value $gmt_offset
	add_option('gmt_offset', $gmt_offset);

	// Check if we already set the GMT fields (if we did, then
	// MAX(post_date_gmt) can't be '0000-00-00 00:00:00'
	// <michel_v> I just slapped myself silly for not thinking about it earlier
	$got_gmt_fields = ! ($wpdb->get_var("SELECT MAX(post_date_gmt) FROM $wpdb->posts") == '0000-00-00 00:00:00');

	if (!$got_gmt_fields) {

		// Add or subtract time to all dates, to get GMT dates
		$add_hours = intval($diff_gmt_weblogger);
		$add_minutes = intval(60 * ($diff_gmt_weblogger - $add_hours));
		$wpdb->query("UPDATE $wpdb->posts SET post_date_gmt = DATE_ADD(post_date, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
		$wpdb->query("UPDATE $wpdb->posts SET post_modified = post_date");
		$wpdb->query("UPDATE $wpdb->posts SET post_modified_gmt = DATE_ADD(post_modified, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE) WHERE post_modified != '0000-00-00 00:00:00'");
		$wpdb->query("UPDATE $wpdb->comments SET comment_date_gmt = DATE_ADD(comment_date, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
		$wpdb->query("UPDATE $wpdb->users SET user_registered = DATE_ADD(user_registered, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
	}

}

/**
 * Execute changes made in WordPress 1.5.
 *
 * @ignore
 * @since 1.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_130() {
	global $wpdb;

	// Remove extraneous backslashes.
	$posts = $wpdb->get_results("SELECT ID, post_title, post_content, post_excerpt, guid, post_date, post_name, post_status, post_author FROM $wpdb->posts");
	if ($posts) {
		foreach ($posts as $post) {
			$post_content = addslashes(deslash($post->post_content));
			$post_title = addslashes(deslash($post->post_title));
			$post_excerpt = addslashes(deslash($post->post_excerpt));
			if ( empty($post->guid) )
				$guid = get_permalink($post->ID);
			else
				$guid = $post->guid;

			$wpdb->update( $wpdb->posts, compact('post_title', 'post_content', 'post_excerpt', 'guid'), array('ID' => $post->ID) );

		}
	}

	// Remove extraneous backslashes.
	$comments = $wpdb->get_results("SELECT comment_ID, comment_author, comment_content FROM $wpdb->comments");
	if ($comments) {
		foreach ($comments as $comment) {
			$comment_content = deslash($comment->comment_content);
			$comment_author = deslash($comment->comment_author);

			$wpdb->update($wpdb->comments, compact('comment_content', 'comment_author'), array('comment_ID' => $comment->comment_ID) );
		}
	}

	// Remove extraneous backslashes.
	$links = $wpdb->get_results("SELECT link_id, link_name, link_description FROM $wpdb->links");
	if ($links) {
		foreach ($links as $link) {
			$link_name = deslash($link->link_name);
			$link_description = deslash($link->link_description);

			$wpdb->update( $wpdb->links, compact('link_name', 'link_description'), array('link_id' => $link->link_id) );
		}
	}

	$active_plugins = __get_option('active_plugins');

	/*
	 * If plugins are not stored in an array, they're stored in the old
	 * newline separated format. Convert to new format.
	 */
	if ( !is_array( $active_plugins ) ) {
		$active_plugins = explode("\n", trim($active_plugins));
		update_option('active_plugins', $active_plugins);
	}

	// Obsolete tables
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'optionvalues');
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'optiontypes');
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'optiongroups');
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'optiongroup_options');

	// Update comments table to use comment_type
	$wpdb->query("UPDATE $wpdb->comments SET comment_type='trackback', comment_content = REPLACE(comment_content, '<trackback />', '') WHERE comment_content LIKE '<trackback />%'");
	$wpdb->query("UPDATE $wpdb->comments SET comment_type='pingback', comment_content = REPLACE(comment_content, '<pingback />', '') WHERE comment_content LIKE '<pingback />%'");

	// Some versions have multiple duplicate option_name rows with the same values
	$options = $wpdb->get_results("SELECT option_name, COUNT(option_name) AS dupes FROM `$wpdb->options` GROUP BY option_name");
	foreach ( $options as $option ) {
		if ( 1 != $option->dupes ) { // Could this be done in the query?
			$limit = $option->dupes - 1;
			$dupe_ids = $wpdb->get_col( $wpdb->prepare("SELECT option_id FROM $wpdb->options WHERE option_name = %s LIMIT %d", $option->option_name, $limit) );
			if ( $dupe_ids ) {
				$dupe_ids = join($dupe_ids, ',');
				$wpdb->query("DELETE FROM $wpdb->options WHERE option_id IN ($dupe_ids)");
			}
		}
	}

	make_site_theme();
}

/**
 * Execute changes made in WordPress 2.0.
 *
 * @ignore
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global int  $wp_current_db_version
 */
function upgrade_160() {
	global $wpdb, $wp_current_db_version;

	populate_roles_160();

	$users = $wpdb->get_results("SELECT * FROM $wpdb->users");
	foreach ( $users as $user ) :
		if ( !empty( $user->user_firstname ) )
			update_user_meta( $user->ID, 'first_name', wp_slash($user->user_firstname) );
		if ( !empty( $user->user_lastname ) )
			update_user_meta( $user->ID, 'last_name', wp_slash($user->user_lastname) );
		if ( !empty( $user->user_nickname ) )
			update_user_meta( $user->ID, 'nickname', wp_slash($user->user_nickname) );
		if ( !empty( $user->user_level ) )
			update_user_meta( $user->ID, $wpdb->prefix . 'user_level', $user->user_level );
		if ( !empty( $user->user_icq ) )
			update_user_meta( $user->ID, 'icq', wp_slash($user->user_icq) );
		if ( !empty( $user->user_aim ) )
			update_user_meta( $user->ID, 'aim', wp_slash($user->user_aim) );
		if ( !empty( $user->user_msn ) )
			update_user_meta( $user->ID, 'msn', wp_slash($user->user_msn) );
		if ( !empty( $user->user_yim ) )
			update_user_meta( $user->ID, 'yim', wp_slash($user->user_icq) );
		if ( !empty( $user->user_description ) )
			update_user_meta( $user->ID, 'description', wp_slash($user->user_description) );

		if ( isset( $user->user_idmode ) ):
			$idmode = $user->user_idmode;
			if ($idmode == 'nickname') $id = $user->user_nickname;
			if ($idmode == 'login') $id = $user->user_login;
			if ($idmode == 'firstname') $id = $user->user_firstname;
			if ($idmode == 'lastname') $id = $user->user_lastname;
			if ($idmode == 'namefl') $id = $user->user_firstname.' '.$user->user_lastname;
			if ($idmode == 'namelf') $id = $user->user_lastname.' '.$user->user_firstname;
			if (!$idmode) $id = $user->user_nickname;
			$wpdb->update( $wpdb->users, array('display_name' => $id), array('ID' => $user->ID) );
		endif;

		// FIXME: RESET_CAPS is temporary code to reset roles and caps if flag is set.
		$caps = get_user_meta( $user->ID, $wpdb->prefix . 'capabilities');
		if ( empty($caps) || defined('RESET_CAPS') ) {
			$level = get_user_meta($user->ID, $wpdb->prefix . 'user_level', true);
			$role = translate_level_to_role($level);
			update_user_meta( $user->ID, $wpdb->prefix . 'capabilities', array($role => true) );
		}

	endforeach;
	$old_user_fields = array( 'user_firstname', 'user_lastname', 'user_icq', 'user_aim', 'user_msn', 'user_yim', 'user_idmode', 'user_ip', 'user_domain', 'user_browser', 'user_description', 'user_nickname', 'user_level' );
	$wpdb->hide_errors();
	foreach ( $old_user_fields as $old )
		$wpdb->query("ALTER TABLE $wpdb->users DROP $old");
	$wpdb->show_errors();

	// Populate comment_count field of posts table.
	$comments = $wpdb->get_results( "SELECT comment_post_ID, COUNT(*) as c FROM $wpdb->comments WHERE comment_approved = '1' GROUP BY comment_post_ID" );
	if ( is_array( $comments ) )
		foreach ($comments as $comment)
			$wpdb->update( $wpdb->posts, array('comment_count' => $comment->c), array('ID' => $comment->comment_post_ID) );

	/*
	 * Some alpha versions used a post status of object instead of attachment
	 * and put the mime type in post_type instead of post_mime_type.
	 */
	if ( $wp_current_db_version > 2541 && $wp_current_db_version <= 3091 ) {
		$objects = $wpdb->get_results("SELECT ID, post_type FROM $wpdb->posts WHERE post_status = 'object'");
		foreach ($objects as $object) {
			$wpdb->update( $wpdb->posts, array(	'post_status' => 'attachment',
												'post_mime_type' => $object->post_type,
												'post_type' => ''),
										 array( 'ID' => $object->ID ) );

			$meta = get_post_meta($object->ID, 'imagedata', true);
			if ( ! empty($meta['file']) )
				update_attached_file( $object->ID, $meta['file'] );
		}
	}
}

/**
 * Execute changes made in WordPress 2.1.
 *
 * @ignore
 * @since 2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global int  $wp_current_db_version
 */
function upgrade_210() {
	global $wpdb, $wp_current_db_version;

	if ( $wp_current_db_version < 3506 ) {
		// Update status and type.
		$posts = $wpdb->get_results("SELECT ID, post_status FROM $wpdb->posts");

		if ( ! empty($posts) ) foreach ($posts as $post) {
			$status = $post->post_status;
			$type = 'post';

			if ( 'static' == $status ) {
				$status = 'publish';
				$type = 'page';
			} elseif ( 'attachment' == $status ) {
				$status = 'inherit';
				$type = 'attachment';
			}

			$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET post_status = %s, post_type = %s WHERE ID = %d", $status, $type, $post->ID) );
		}
	}

	if ( $wp_current_db_version < 3845 ) {
		populate_roles_210();
	}

	if ( $wp_current_db_version < 3531 ) {
		// Give future posts a post_status of future.
		$now = gmdate('Y-m-d H:i:59');
		$wpdb->query ("UPDATE $wpdb->posts SET post_status = 'future' WHERE post_status = 'publish' AND post_date_gmt > '$now'");

		$posts = $wpdb->get_results("SELECT ID, post_date FROM $wpdb->posts WHERE post_status ='future'");
		if ( !empty($posts) )
			foreach ( $posts as $post )
				wp_schedule_single_event(mysql2date('U', $post->post_date, false), 'publish_future_post', array($post->ID));
	}
}

/**
 * Execute changes made in WordPress 2.3.
 *
 * @ignore
 * @since 2.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global int  $wp_current_db_version
 */
function upgrade_230() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 5200 ) {
		populate_roles_230();
	}

	// Convert categories to terms.
	$tt_ids = array();
	$have_tags = false;
	$categories = $wpdb->get_results("SELECT * FROM $wpdb->categories ORDER BY cat_ID");
	foreach ($categories as $category) {
		$term_id = (int) $category->cat_ID;
		$name = $category->cat_name;
		$description = $category->category_description;
		$slug = $category->category_nicename;
		$parent = $category->category_parent;
		$term_group = 0;

		// Associate terms with the same slug in a term group and make slugs unique.
		if ( $exists = $wpdb->get_results( $wpdb->prepare("SELECT term_id, term_group FROM $wpdb->terms WHERE slug = %s", $slug) ) ) {
			$term_group = $exists[0]->term_group;
			$id = $exists[0]->term_id;
			$num = 2;
			do {
				$alt_slug = $slug . "-$num";
				$num++;
				$slug_check = $wpdb->get_var( $wpdb->prepare("SELECT slug FROM $wpdb->terms WHERE slug = %s", $alt_slug) );
			} while ( $slug_check );

			$slug = $alt_slug;

			if ( empty( $term_group ) ) {
				$term_group = $wpdb->get_var("SELECT MAX(term_group) FROM $wpdb->terms GROUP BY term_group") + 1;
				$wpdb->query( $wpdb->prepare("UPDATE $wpdb->terms SET term_group = %d WHERE term_id = %d", $term_group, $id) );
			}
		}

		$wpdb->query( $wpdb->prepare("INSERT INTO $wpdb->terms (term_id, name, slug, term_group) VALUES
		(%d, %s, %s, %d)", $term_id, $name, $slug, $term_group) );

		$count = 0;
		if ( !empty($category->category_count) ) {
			$count = (int) $category->category_count;
			$taxonomy = 'category';
			$wpdb->query( $wpdb->prepare("INSERT INTO $wpdb->term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ( %d, %s, %s, %d, %d)", $term_id, $taxonomy, $description, $parent, $count) );
			$tt_ids[$term_id][$taxonomy] = (int) $wpdb->insert_id;
		}

		if ( !empty($category->link_count) ) {
			$count = (int) $category->link_count;
			$taxonomy = 'link_category';
			$wpdb->query( $wpdb->prepare("INSERT INTO $wpdb->term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ( %d, %s, %s, %d, %d)", $term_id, $taxonomy, $description, $parent, $count) );
			$tt_ids[$term_id][$taxonomy] = (int) $wpdb->insert_id;
		}

		if ( !empty($category->tag_count) ) {
			$have_tags = true;
			$count = (int) $category->tag_count;
			$taxonomy = 'post_tag';
			$wpdb->insert( $wpdb->term_taxonomy, compact('term_id', 'taxonomy', 'description', 'parent', 'count') );
			$tt_ids[$term_id][$taxonomy] = (int) $wpdb->insert_id;
		}

		if ( empty($count) ) {
			$count = 0;
			$taxonomy = 'category';
			$wpdb->insert( $wpdb->term_taxonomy, compact('term_id', 'taxonomy', 'description', 'parent', 'count') );
			$tt_ids[$term_id][$taxonomy] = (int) $wpdb->insert_id;
		}
	}

	$select = 'post_id, category_id';
	if ( $have_tags )
		$select .= ', rel_type';

	$posts = $wpdb->get_results("SELECT $select FROM $wpdb->post2cat GROUP BY post_id, category_id");
	foreach ( $posts as $post ) {
		$post_id = (int) $post->post_id;
		$term_id = (int) $post->category_id;
		$taxonomy = 'category';
		if ( !empty($post->rel_type) && 'tag' == $post->rel_type)
			$taxonomy = 'tag';
		$tt_id = $tt_ids[$term_id][$taxonomy];
		if ( empty($tt_id) )
			continue;

		$wpdb->insert( $wpdb->term_relationships, array('object_id' => $post_id, 'term_taxonomy_id' => $tt_id) );
	}

	// < 3570 we used linkcategories. >= 3570 we used categories and link2cat.
	if ( $wp_current_db_version < 3570 ) {
		/*
		 * Create link_category terms for link categories. Create a map of link
		 * cat IDs to link_category terms.
		 */
		$link_cat_id_map = array();
		$default_link_cat = 0;
		$tt_ids = array();
		$link_cats = $wpdb->get_results("SELECT cat_id, cat_name FROM " . $wpdb->prefix . 'linkcategories');
		foreach ( $link_cats as $category) {
			$cat_id = (int) $category->cat_id;
			$term_id = 0;
			$name = wp_slash($category->cat_name);
			$slug = sanitize_title($name);
			$term_group = 0;

			// Associate terms with the same slug in a term group and make slugs unique.
			if ( $exists = $wpdb->get_results( $wpdb->prepare("SELECT term_id, term_group FROM $wpdb->terms WHERE slug = %s", $slug) ) ) {
				$term_group = $exists[0]->term_group;
				$term_id = $exists[0]->term_id;
			}

			if ( empty($term_id) ) {
				$wpdb->insert( $wpdb->terms, compact('name', 'slug', 'term_group') );
				$term_id = (int) $wpdb->insert_id;
			}

			$link_cat_id_map[$cat_id] = $term_id;
			$default_link_cat = $term_id;

			$wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $term_id, 'taxonomy' => 'link_category', 'description' => '', 'parent' => 0, 'count' => 0) );
			$tt_ids[$term_id] = (int) $wpdb->insert_id;
		}

		// Associate links to cats.
		$links = $wpdb->get_results("SELECT link_id, link_category FROM $wpdb->links");
		if ( !empty($links) ) foreach ( $links as $link ) {
			if ( 0 == $link->link_category )
				continue;
			if ( ! isset($link_cat_id_map[$link->link_category]) )
				continue;
			$term_id = $link_cat_id_map[$link->link_category];
			$tt_id = $tt_ids[$term_id];
			if ( empty($tt_id) )
				continue;

			$wpdb->insert( $wpdb->term_relationships, array('object_id' => $link->link_id, 'term_taxonomy_id' => $tt_id) );
		}

		// Set default to the last category we grabbed during the upgrade loop.
		update_option('default_link_category', $default_link_cat);
	} else {
		$links = $wpdb->get_results("SELECT link_id, category_id FROM $wpdb->link2cat GROUP BY link_id, category_id");
		foreach ( $links as $link ) {
			$link_id = (int) $link->link_id;
			$term_id = (int) $link->category_id;
			$taxonomy = 'link_category';
			$tt_id = $tt_ids[$term_id][$taxonomy];
			if ( empty($tt_id) )
				continue;
			$wpdb->insert( $wpdb->term_relationships, array('object_id' => $link_id, 'term_taxonomy_id' => $tt_id) );
		}
	}

	if ( $wp_current_db_version < 4772 ) {
		// Obsolete linkcategories table
		$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'linkcategories');
	}

	// Recalculate all counts
	$terms = $wpdb->get_results("SELECT term_taxonomy_id, taxonomy FROM $wpdb->term_taxonomy");
	foreach ( (array) $terms as $term ) {
		if ( ('post_tag' == $term->taxonomy) || ('category' == $term->taxonomy) )
			$count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type = 'post' AND term_taxonomy_id = %d", $term->term_taxonomy_id) );
		else
			$count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term->term_taxonomy_id) );
		$wpdb->update( $wpdb->term_taxonomy, array('count' => $count), array('term_taxonomy_id' => $term->term_taxonomy_id) );
	}
}

/**
 * Remove old options from the database.
 *
 * @ignore
 * @since 2.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_230_options_table() {
	global $wpdb;
	$old_options_fields = array( 'option_can_override', 'option_type', 'option_width', 'option_height', 'option_description', 'option_admin_level' );
	$wpdb->hide_errors();
	foreach ( $old_options_fields as $old )
		$wpdb->query("ALTER TABLE $wpdb->options DROP $old");
	$wpdb->show_errors();
}

/**
 * Remove old categories, link2cat, and post2cat database tables.
 *
 * @ignore
 * @since 2.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_230_old_tables() {
	global $wpdb;
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'categories');
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'link2cat');
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'post2cat');
}

/**
 * Upgrade old slugs made in version 2.2.
 *
 * @ignore
 * @since 2.2.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_old_slugs() {
	// Upgrade people who were using the Redirect Old Slugs plugin.
	global $wpdb;
	$wpdb->query("UPDATE $wpdb->postmeta SET meta_key = '_wp_old_slug' WHERE meta_key = 'old_slug'");
}

/**
 * Execute changes made in WordPress 2.5.0.
 *
 * @ignore
 * @since 2.5.0
 *
 * @global int $wp_current_db_version
 */
function upgrade_250() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 6689 ) {
		populate_roles_250();
	}

}

/**
 * Execute changes made in WordPress 2.5.2.
 *
 * @ignore
 * @since 2.5.2
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_252() {
	global $wpdb;

	$wpdb->query("UPDATE $wpdb->users SET user_activation_key = ''");
}

/**
 * Execute changes made in WordPress 2.6.
 *
 * @ignore
 * @since 2.6.0
 *
 * @global int $wp_current_db_version
 */
function upgrade_260() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 8000 )
		populate_roles_260();
}

/**
 * Execute changes made in WordPress 2.7.
 *
 * @ignore
 * @since 2.7.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global int  $wp_current_db_version
 */
function upgrade_270() {
	global $wpdb, $wp_current_db_version;

	if ( $wp_current_db_version < 8980 )
		populate_roles_270();

	// Update post_date for unpublished posts with empty timestamp
	if ( $wp_current_db_version < 8921 )
		$wpdb->query( "UPDATE $wpdb->posts SET post_date = post_modified WHERE post_date = '0000-00-00 00:00:00'" );
}

/**
 * Execute changes made in WordPress 2.8.
 *
 * @ignore
 * @since 2.8.0
 *
 * @global int  $wp_current_db_version
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_280() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 10360 )
		populate_roles_280();
	if ( is_multisite() ) {
		$start = 0;
		while( $rows = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options ORDER BY option_id LIMIT $start, 20" ) ) {
			foreach ( $rows as $row ) {
				$value = $row->option_value;
				if ( !@unserialize( $value ) )
					$value = stripslashes( $value );
				if ( $value !== $row->option_value ) {
					update_option( $row->option_name, $value );
				}
			}
			$start += 20;
		}
		refresh_blog_details( $wpdb->blogid );
	}
}

/**
 * Execute changes made in WordPress 2.9.
 *
 * @ignore
 * @since 2.9.0
 *
 * @global int $wp_current_db_version
 */
function upgrade_290() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 11958 ) {
		// Previously, setting depth to 1 would redundantly disable threading, but now 2 is the minimum depth to avoid confusion
		if ( get_option( 'thread_comments_depth' ) == '1' ) {
			update_option( 'thread_comments_depth', 2 );
			update_option( 'thread_comments', 0 );
		}
	}
}

/**
 * Execute changes made in WordPress 3.0.
 *
 * @ignore
 * @since 3.0.0
 *
 * @global int  $wp_current_db_version
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function upgrade_300() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 15093 )
		populate_roles_300();

	if ( $wp_current_db_version < 14139 && is_multisite() && is_main_site() && ! defined( 'MULTISITE' ) && get_site_option( 'siteurl' ) === false )
		add_site_option( 'siteurl', '' );

	// 3.0 screen options key name changes.
	if ( wp_should_upgrade_global_tables() ) {
		$sql = "DELETE FROM $wpdb->usermeta
			WHERE meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key = 'manageedittagscolumnshidden'
			OR meta_key = 'managecategoriescolumnshidden'
			OR meta_key = 'manageedit-tagscolumnshidden'
			OR meta_key = 'manageeditcolumnshidden'
			OR meta_key = 'categories_per_page'
			OR meta_key = 'edit_tags_per_page'";
		$prefix = $wpdb->esc_like( $wpdb->base_prefix );
		$wpdb->query( $wpdb->prepare( $sql,
			$prefix . '%' . $wpdb->esc_like( 'meta-box-hidden' ) . '%',
			$prefix . '%' . $wpdb->esc_like( 'closedpostboxes' ) . '%',
			$prefix . '%' . $wpdb->esc_like( 'manage-'	   ) . '%' . $wpdb->esc_like( '-columns-hidden' ) . '%',
			$prefix . '%' . $wpdb->esc_like( 'meta-box-order'  ) . '%',
			$prefix . '%' . $wpdb->esc_like( 'metaboxorder'    ) . '%',
			$prefix . '%' . $wpdb->esc_like( 'screen_layout'   ) . '%'
		) );
	}

}

/**
 * Execute changes made in WordPress 3.3.
 *
 * @ignore
 * @since 3.3.0
 *
 * @global int   $wp_current_db_version
 * @global wpdb  $wpdb
 * @global array $wp_registered_widgets
 * @global array $sidebars_widgets
 */
function upgrade_330() {
	global $wp_current_db_version, $wpdb, $wp_registered_widgets, $sidebars_widgets;

	if ( $wp_current_db_version < 19061 && wp_should_upgrade_global_tables() ) {
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key IN ('show_admin_bar_admin', 'plugins_last_view')" );
	}

	if ( $wp_current_db_version >= 11548 )
		return;

	$sidebars_widgets = get_option( 'sidebars_widgets', array() );
	$_sidebars_widgets = array();

	if ( isset($sidebars_widgets['wp_inactive_widgets']) || empty($sidebars_widgets) )
		$sidebars_widgets['array_version'] = 3;
	elseif ( !isset($sidebars_widgets['array_version']) )
		$sidebars_widgets['array_version'] = 1;

	switch ( $sidebars_widgets['array_version'] ) {
		case 1 :
			foreach ( (array) $sidebars_widgets as $index => $sidebar )
			if ( is_array($sidebar) )
			foreach ( (array) $sidebar as $i => $name ) {
				$id = strtolower($name);
				if ( isset($wp_registered_widgets[$id]) ) {
					$_sidebars_widgets[$index][$i] = $id;
					continue;
				}
				$id = sanitize_title($name);
				if ( isset($wp_registered_widgets[$id]) ) {
					$_sidebars_widgets[$index][$i] = $id;
					continue;
				}

				$found = false;

				foreach ( $wp_registered_widgets as $widget_id => $widget ) {
					if ( strtolower($widget['name']) == strtolower($name) ) {
						$_sidebars_widgets[$index][$i] = $widget['id'];
						$found = true;
						break;
					} elseif ( sanitize_title($widget['name']) == sanitize_title($name) ) {
						$_sidebars_widgets[$index][$i] = $widget['id'];
						$found = true;
						break;
					}
				}

				if ( $found )
					continue;

				unset($_sidebars_widgets[$index][$i]);
			}
			$_sidebars_widgets['array_version'] = 2;
			$sidebars_widgets = $_sidebars_widgets;
			unset($_sidebars_widgets);

		case 2 :
			$sidebars_widgets = retrieve_widgets();
			$sidebars_widgets['array_version'] = 3;
			update_option( 'sidebars_widgets', $sidebars_widgets );
	}
}

/**
 * Execute changes made in WordPress 3.4.
 *
 * @ignore
 * @since 3.4.0
 *
 * @global int   $wp_current_db_version
 * @global wpdb  $wpdb
 */
function upgrade_340() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 19798 ) {
		$wpdb->hide_errors();
		$wpdb->query( "ALTER TABLE $wpdb->options DROP COLUMN blog_id" );
		$wpdb->show_errors();
	}

	if ( $wp_current_db_version < 19799 ) {
		$wpdb->hide_errors();
		$wpdb->query("ALTER TABLE $wpdb->comments DROP INDEX comment_approved");
		$wpdb->show_errors();
	}

	if ( $wp_current_db_version < 20022 && wp_should_upgrade_global_tables() ) {
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key = 'themes_last_view'" );
	}

	if ( $wp_current_db_version < 20080 ) {
		if ( 'yes' == $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'uninstall_plugins'" ) ) {
			$uninstall_plugins = get_option( 'uninstall_plugins' );
			delete_option( 'uninstall_plugins' );
			add_option( 'uninstall_plugins', $uninstall_plugins, null, 'no' );
		}
	}
}

/**
 * Execute changes made in WordPress 3.5.
 *
 * @ignore
 * @since 3.5.0
 *
 * @global int   $wp_current_db_version
 * @global wpdb  $wpdb
 */
function upgrade_350() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 22006 && $wpdb->get_var( "SELECT link_id FROM $wpdb->links LIMIT 1" ) )
		update_option( 'link_manager_enabled', 1 ); // Previously set to 0 by populate_options()

	if ( $wp_current_db_version < 21811 && wp_should_upgrade_global_tables() ) {
		$meta_keys = array();
		foreach ( array_merge( get_post_types(), get_taxonomies() ) as $name ) {
			if ( false !== strpos( $name, '-' ) )
			$meta_keys[] = 'edit_' . str_replace( '-', '_', $name ) . '_per_page';
		}
		if ( $meta_keys ) {
			$meta_keys = implode( "', '", $meta_keys );
			$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key IN ('$meta_keys')" );
		}
	}

	if ( $wp_current_db_version < 22422 && $term = get_term_by( 'slug', 'post-format-standard', 'post_format' ) )
		wp_delete_term( $term->term_id, 'post_format' );
}

/**
 * Execute changes made in WordPress 3.7.
 *
 * @ignore
 * @since 3.7.0
 *
 * @global int $wp_current_db_version
 */
function upgrade_370() {
	global $wp_current_db_version;
	if ( $wp_current_db_version < 25824 )
		wp_clear_scheduled_hook( 'wp_auto_updates_maybe_update' );
}

/**
 * Execute changes made in WordPress 3.7.2.
 *
 * @ignore
 * @since 3.7.2
 * @since 3.8.0
 *
 * @global int $wp_current_db_version
 */
function upgrade_372() {
	global $wp_current_db_version;
	if ( $wp_current_db_version < 26148 )
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
}

/**
 * Execute changes made in WordPress 3.8.0.
 *
 * @ignore
 * @since 3.8.0
 *
 * @global int $wp_current_db_version
 */
function upgrade_380() {
	global $wp_current_db_version;
	if ( $wp_current_db_version < 26691 ) {
		deactivate_plugins( array( 'mp6/mp6.php' ), true );
	}
}

/**
 * Execute changes made in WordPress 4.0.0.
 *
 * @ignore
 * @since 4.0.0
 *
 * @global int $wp_current_db_version
 */
function upgrade_400() {
	global $wp_current_db_version;
	if ( $wp_current_db_version < 29630 ) {
		if ( ! is_multisite() && false === get_option( 'WPLANG' ) ) {
			if ( defined( 'WPLANG' ) && ( '' !== WPLANG ) && in_array( WPLANG, get_available_languages() ) ) {
				update_option( 'WPLANG', WPLANG );
			} else {
				update_option( 'WPLANG', '' );
			}
		}
	}
}

/**
 * Execute changes made in WordPress 4.2.0.
 *
 * @ignore
 * @since 4.2.0
 *
 * @global int   $wp_current_db_version
 * @global wpdb  $wpdb
 */
function upgrade_420() {}

/**
 * Executes changes made in WordPress 4.3.0.
 *
 * @ignore
 * @since 4.3.0
 *
 * @global int  $wp_current_db_version Current version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_430() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 32364 ) {
		upgrade_430_fix_comments();
	}

	// Shared terms are split in a separate process.
	if ( $wp_current_db_version < 32814 ) {
		update_option( 'finished_splitting_shared_terms', 0 );
		wp_schedule_single_event( time() + ( 1 * MINUTE_IN_SECONDS ), 'wp_split_shared_term_batch' );
	}

	if ( $wp_current_db_version < 33055 && 'utf8mb4' === $wpdb->charset ) {
		if ( is_multisite() ) {
			$tables = $wpdb->tables( 'blog' );
		} else {
			$tables = $wpdb->tables( 'all' );
			if ( ! wp_should_upgrade_global_tables() ) {
				$global_tables = $wpdb->tables( 'global' );
				$tables = array_diff_assoc( $tables, $global_tables );
			}
		}

		foreach ( $tables as $table ) {
			maybe_convert_table_to_utf8mb4( $table );
		}
	}
}

/**
 * Executes comments changes made in WordPress 4.3.0.
 *
 * @ignore
 * @since 4.3.0
 *
 * @global int  $wp_current_db_version Current version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_430_fix_comments() {
	global $wp_current_db_version, $wpdb;

	$content_length = $wpdb->get_col_length( $wpdb->comments, 'comment_content' );

	if ( is_wp_error( $content_length ) ) {
		return;
	}

	if ( false === $content_length ) {
		$content_length = array(
			'type'   => 'byte',
			'length' => 65535,
		);
	} elseif ( ! is_array( $content_length ) ) {
		$length = (int) $content_length > 0 ? (int) $content_length : 65535;
		$content_length = array(
			'type'	 => 'byte',
			'length' => $length
		);
	}

	if ( 'byte' !== $content_length['type'] || 0 === $content_length['length'] ) {
		// Sites with malformed DB schemas are on their own.
		return;
	}

	$allowed_length = intval( $content_length['length'] ) - 10;

	$comments = $wpdb->get_results(
		"SELECT `comment_ID` FROM `{$wpdb->comments}`
			WHERE `comment_date_gmt` > '2015-04-26'
			AND LENGTH( `comment_content` ) >= {$allowed_length}
			AND ( `comment_content` LIKE '%<%' OR `comment_content` LIKE '%>%' )"
	);

	foreach ( $comments as $comment ) {
		wp_delete_comment( $comment->comment_ID, true );
	}
}

/**
 * Executes changes made in WordPress 4.3.1.
 *
 * @ignore
 * @since 4.3.1
 */
function upgrade_431() {
	// Fix incorrect cron entries for term splitting
	$cron_array = _get_cron_array();
	if ( isset( $cron_array['wp_batch_split_terms'] ) ) {
		unset( $cron_array['wp_batch_split_terms'] );
		_set_cron_array( $cron_array );
	}
}

/**
 * Executes changes made in WordPress 4.4.0.
 *
 * @ignore
 * @since 4.4.0
 *
 * @global int  $wp_current_db_version Current version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_440() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 34030 ) {
		$wpdb->query( "ALTER TABLE {$wpdb->options} MODIFY option_name VARCHAR(191)" );
	}

	// Remove the unused 'add_users' role.
	$roles = wp_roles();
	foreach ( $roles->role_objects as $role ) {
		if ( $role->has_cap( 'add_users' ) ) {
			$role->remove_cap( 'add_users' );
		}
	}
}

/**
 * Executes changes made in WordPress 4.5.0.
 *
 * @ignore
 * @since 4.5.0
 *
 * @global int  $wp_current_db_version Current database version.
 * @global wpdb $wpdb                  WordPress database abstraction object.
 */
function upgrade_450() {
	global $wp_current_db_version, $wpdb;

	if ( $wp_current_db_version < 36180 ) {
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
	}

	// Remove unused email confirmation options, moved to usermeta.
	if ( $wp_current_db_version < 36679 && is_multisite() ) {
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name REGEXP '^[0-9]+_new_email$'" );
	}

	// Remove unused user setting for wpLink.
	delete_user_setting( 'wplink' );
}

/**
 * Executes changes made in WordPress 4.6.0.
 *
 * @ignore
 * @since 4.6.0
 *
 * @global int $wp_current_db_version Current database version.
 */
function upgrade_460() {
	global $wp_current_db_version;

	// Remove unused post meta.
	if ( $wp_current_db_version < 37854 ) {
		delete_post_meta_by_key( '_post_restored_from' );
	}

	// Remove plugins with callback as an array object/method as the uninstall hook, see #13786.
	if ( $wp_current_db_version < 37965 ) {
		$uninstall_plugins = get_option( 'uninstall_plugins', array() );

		if ( ! empty( $uninstall_plugins ) ) {
			foreach ( $uninstall_plugins as $basename => $callback ) {
				if ( is_array( $callback ) && is_object( $callback[0] ) ) {
					unset( $uninstall_plugins[ $basename ] );
				}
			}

			update_option( 'uninstall_plugins', $uninstall_plugins );
		}
	}
}

/**
 * Executes network-level upgrade routines.
 *
 * @since 3.0.0
 *
 * @global int   $wp_current_db_version
 * @global wpdb  $wpdb
 */
function upgrade_network() {
	global $wp_current_db_version, $wpdb;

	// Always.
	if ( is_main_network() ) {
		/*
		 * Deletes all expired transients. The multi-table delete syntax is used
		 * to delete the transient record from table a, and the corresponding
		 * transient_timeout record from table b.
		 */
		$time = time();
		$sql = "DELETE a, b FROM $wpdb->sitemeta a, $wpdb->sitemeta b
			WHERE a.meta_key LIKE %s
			AND a.meta_key NOT LIKE %s
			AND b.meta_key = CONCAT( '_site_transient_timeout_', SUBSTRING( a.meta_key, 17 ) )
			AND b.meta_value < %d";
		$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_site_transient_' ) . '%', $wpdb->esc_like ( '_site_transient_timeout_' ) . '%', $time ) );
	}

	// 2.8.
	if ( $wp_current_db_version < 11549 ) {
		$wpmu_sitewide_plugins = get_site_option( 'wpmu_sitewide_plugins' );
		$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
		if ( $wpmu_sitewide_plugins ) {
			if ( !$active_sitewide_plugins )
				$sitewide_plugins = (array) $wpmu_sitewide_plugins;
			else
				$sitewide_plugins = array_merge( (array) $active_sitewide_plugins, (array) $wpmu_sitewide_plugins );

			update_site_option( 'active_sitewide_plugins', $sitewide_plugins );
		}
		delete_site_option( 'wpmu_sitewide_plugins' );
		delete_site_option( 'deactivated_sitewide_plugins' );

		$start = 0;
		while( $rows = $wpdb->get_results( "SELECT meta_key, meta_value FROM {$wpdb->sitemeta} ORDER BY meta_id LIMIT $start, 20" ) ) {
			foreach ( $rows as $row ) {
				$value = $row->meta_value;
				if ( !@unserialize( $value ) )
					$value = stripslashes( $value );
				if ( $value !== $row->meta_value ) {
					update_site_option( $row->meta_key, $value );
				}
			}
			$start += 20;
		}
	}

	// 3.0
	if ( $wp_current_db_version < 13576 )
		update_site_option( 'global_terms_enabled', '1' );

	// 3.3
	if ( $wp_current_db_version < 19390 )
		update_site_option( 'initial_db_version', $wp_current_db_version );

	if ( $wp_current_db_version < 19470 ) {
		if ( false === get_site_option( 'active_sitewide_plugins' ) )
			update_site_option( 'active_sitewide_plugins', array() );
	}

	// 3.4
	if ( $wp_current_db_version < 20148 ) {
		// 'allowedthemes' keys things by stylesheet. 'allowed_themes' keyed things by name.
		$allowedthemes  = get_site_option( 'allowedthemes'  );
		$allowed_themes = get_site_option( 'allowed_themes' );
		if ( false === $allowedthemes && is_array( $allowed_themes ) && $allowed_themes ) {
			$converted = array();
			$themes = wp_get_themes();
			foreach ( $themes as $stylesheet => $theme_data ) {
				if ( isset( $allowed_themes[ $theme_data->get('Name') ] ) )
					$converted[ $stylesheet ] = true;
			}
			update_site_option( 'allowedthemes', $converted );
			delete_site_option( 'allowed_themes' );
		}
	}

	// 3.5
	if ( $wp_current_db_version < 21823 )
		update_site_option( 'ms_files_rewriting', '1' );

	// 3.5.2
	if ( $wp_current_db_version < 24448 ) {
		$illegal_names = get_site_option( 'illegal_names' );
		if ( is_array( $illegal_names ) && count( $illegal_names ) === 1 ) {
			$illegal_name = reset( $illegal_names );
			$illegal_names = explode( ' ', $illegal_name );
			update_site_option( 'illegal_names', $illegal_names );
		}
	}

	// 4.2
	if ( $wp_current_db_version < 31351 && $wpdb->charset === 'utf8mb4' ) {
		if ( wp_should_upgrade_global_tables() ) {
			$wpdb->query( "ALTER TABLE $wpdb->usermeta DROP INDEX meta_key, ADD INDEX meta_key(meta_key(191))" );
			$wpdb->query( "ALTER TABLE $wpdb->site DROP INDEX domain, ADD INDEX domain(domain(140),path(51))" );
			$wpdb->query( "ALTER TABLE $wpdb->sitemeta DROP INDEX meta_key, ADD INDEX meta_key(meta_key(191))" );
			$wpdb->query( "ALTER TABLE $wpdb->signups DROP INDEX domain_path, ADD INDEX domain_path(domain(140),path(51))" );

			$tables = $wpdb->tables( 'global' );

			// sitecategories may not exist.
			if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$tables['sitecategories']}'" ) ) {
				unset( $tables['sitecategories'] );
			}

			foreach ( $tables as $table ) {
				maybe_convert_table_to_utf8mb4( $table );
			}
		}
	}

	// 4.3
	if ( $wp_current_db_version < 33055 && 'utf8mb4' === $wpdb->charset ) {
		if ( wp_should_upgrade_global_tables() ) {
			$upgrade = false;
			$indexes = $wpdb->get_results( "SHOW INDEXES FROM $wpdb->signups" );
			foreach ( $indexes as $index ) {
				if ( 'domain_path' == $index->Key_name && 'domain' == $index->Column_name && 140 != $index->Sub_part ) {
					$upgrade = true;
					break;
				}
			}

			if ( $upgrade ) {
				$wpdb->query( "ALTER TABLE $wpdb->signups DROP INDEX domain_path, ADD INDEX domain_path(domain(140),path(51))" );
			}

			$tables = $wpdb->tables( 'global' );

			// sitecategories may not exist.
			if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$tables['sitecategories']}'" ) ) {
				unset( $tables['sitecategories'] );
			}

			foreach ( $tables as $table ) {
				maybe_convert_table_to_utf8mb4( $table );
			}
		}
	}
}

//
// General functions we use to actually do stuff
//

/**
 * Creates a table in the database if it doesn't already exist.
 *
 * This method checks for an existing database and creates a new one if it's not
 * already present. It doesn't rely on MySQL's "IF NOT EXISTS" statement, but chooses
 * to query all tables first and then run the SQL statement creating the table.
 *
 * @since 1.0.0
 *
 * @global wpdb  $wpdb
 *
 * @param string $table_name Database table name to create.
 * @param string $create_ddl SQL statement to create table.
 * @return bool If table already exists or was created by function.
 */
function maybe_create_table($table_name, $create_ddl) {
	global $wpdb;

	$query = $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) );

	if ( $wpdb->get_var( $query ) == $table_name ) {
		return true;
	}

	// Didn't find it try to create it..
	$wpdb->query($create_ddl);

	// We cannot directly tell that whether this succeeded!
	if ( $wpdb->get_var( $query ) == $table_name ) {
		return true;
	}
	return false;
}

/**
 * Drops a specified index from a table.
 *
 * @since 1.0.1
 *
 * @global wpdb  $wpdb
 *
 * @param string $table Database table name.
 * @param string $index Index name to drop.
 * @return true True, when finished.
 */
function drop_index($table, $index) {
	global $wpdb;
	$wpdb->hide_errors();
	$wpdb->query("ALTER TABLE `$table` DROP INDEX `$index`");
	// Now we need to take out all the extra ones we may have created
	for ($i = 0; $i < 25; $i++) {
		$wpdb->query("ALTER TABLE `$table` DROP INDEX `{$index}_$i`");
	}
	$wpdb->show_errors();
	return true;
}

/**
 * Adds an index to a specified table.
 *
 * @since 1.0.1
 *
 * @global wpdb  $wpdb
 *
 * @param string $table Database table name.
 * @param string $index Database table index column.
 * @return true True, when done with execution.
 */
function add_clean_index($table, $index) {
	global $wpdb;
	drop_index($table, $index);
	$wpdb->query("ALTER TABLE `$table` ADD INDEX ( `$index` )");
	return true;
}

/**
 * Adds column to a database table if it doesn't already exist.
 *
 * @since 1.3.0
 *
 * @global wpdb  $wpdb
 *
 * @param string $table_name  The table name to modify.
 * @param string $column_name The column name to add to the table.
 * @param string $create_ddl  The SQL statement used to add the column.
 * @return bool True if already exists or on successful completion, false on error.
 */
function maybe_add_column($table_name, $column_name, $create_ddl) {
	global $wpdb;
	foreach ($wpdb->get_col("DESC $table_name", 0) as $column ) {
		if ($column == $column_name) {
			return true;
		}
	}

	// Didn't find it try to create it.
	$wpdb->query($create_ddl);

	// We cannot directly tell that whether this succeeded!
	foreach ($wpdb->get_col("DESC $table_name", 0) as $column ) {
		if ($column == $column_name) {
			return true;
		}
	}
	return false;
}

/**
 * If a table only contains utf8 or utf8mb4 columns, convert it to utf8mb4.
 *
 * @since 4.2.0
 *
 * @global wpdb  $wpdb
 *
 * @param string $table The table to convert.
 * @return bool true if the table was converted, false if it wasn't.
 */
function maybe_convert_table_to_utf8mb4( $table ) {
	global $wpdb;

	$results = $wpdb->get_results( "SHOW FULL COLUMNS FROM `$table`" );
	if ( ! $results ) {
		return false;
	}

	foreach ( $results as $column ) {
		if ( $column->Collation ) {
			list( $charset ) = explode( '_', $column->Collation );
			$charset = strtolower( $charset );
			if ( 'utf8' !== $charset && 'utf8mb4' !== $charset ) {
				// Don't upgrade tables that have non-utf8 columns.
				return false;
			}
		}
	}

	$table_details = $wpdb->get_row( "SHOW TABLE STATUS LIKE '$table'" );
	if ( ! $table_details ) {
		return false;
	}

	list( $table_charset ) = explode( '_', $table_details->Collation );
	$table_charset = strtolower( $table_charset );
	if ( 'utf8mb4' === $table_charset ) {
		return true;
	}

	return $wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
}

/**
 * Retrieve all options as it was for 1.2.
 *
 * @since 1.2.0
 *
 * @global wpdb  $wpdb
 *
 * @return stdClass List of options.
 */
function get_alloptions_110() {
	global $wpdb;
	$all_options = new stdClass;
	if ( $options = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options" ) ) {
		foreach ( $options a