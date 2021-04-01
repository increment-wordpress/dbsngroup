<?php
/**
 * WordPress Theme Administration API
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Remove a theme
 *
 * @since 2.8.0
 *
 * @global WP_Filesystem_Base $wp_filesystem Subclass
 *
 * @param string $stylesheet Stylesheet of the theme to delete
 * @param string $redirect Redirect to page when complete.
 * @return void|bool|WP_Error When void, echoes content.
 */
function delete_theme($stylesheet, $redirect = '') {
	global $wp_filesystem;

	if ( empty($stylesheet) )
		return false;

	if ( empty( $redirect ) ) {
		$redirect = wp_nonce_url('themes.php?action=delete&stylesheet=' . urlencode( $stylesheet ), 'delete-theme_' . $stylesheet);
	}

	ob_start();
	$credentials = request_filesystem_credentials( $redirect );
	$data = ob_get_clean();

	if ( false === $credentials ) {
		if ( ! empty( $data ) ){
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! WP_Filesystem( $credentials ) ) {
		ob_start();
		request_filesystem_credentials( $redirect, '', true ); // Failed to connect, Error and request again.
		$data = ob_get_clean();

		if ( ! empty($data) ) {
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! is_object($wp_filesystem) )
		return new WP_Error('fs_unavailable', __('Could not access filesystem.'));

	if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
		return new WP_Error('fs_error', __('Filesystem error.'), $wp_filesystem->errors);

	// Get the base plugin folder.
	$themes_dir = $wp_filesystem->wp_themes_dir();
	if ( empty( $themes_dir ) ) {
		return new WP_Error( 'fs_no_themes_dir', __( 'Unable to locate WordPress theme directory.' ) );
	}

	$themes_dir = trailingslashit( $themes_dir );
	$theme_dir = trailingslashit( $themes_dir . $stylesheet );
	$deleted = $wp_filesystem->delete( $theme_dir, true );

	if ( ! $deleted ) {
		return new WP_Error( 'could_not_remove_theme', sprintf( __( 'Could not fully remove the theme %s.' ), $stylesheet ) );
	}

	$theme_translations = wp_get_installed_translations( 'themes' );

	// Remove language files, silently.
	if ( ! empty( $theme_translations[ $stylesheet ] ) ) {
		$translations = $theme_translations[ $stylesheet ];

		foreach ( $translations as $translation => $data ) {
			$wp_filesystem->delete( WP_LANG_DIR . '/themes/' . $stylesheet . '-' . $translation . '.po' );
			$wp_filesystem->delete( WP_LANG_DIR . '/themes/' . $stylesheet . '-' . $translation . '.mo' );
		}
	}

	// Remove the theme from allowed themes on the network.
	if ( is_multisite() ) {
		WP_Theme::network_disable_theme( $stylesheet );
	}

	// Force refresh of theme update information.
	delete_site_transient( 'update_themes' );

	return true;
}

/**
 * Get the Page Templates available in this theme
 *
 * @since 1.5.0
 * @since 4.7.0 Added the `$post_type` parameter.
 *
 * @param WP_Post|null $post      Optional. The post being edited, provided for context.
 * @param string       $post_type Optional. Post type to get the templates for. Default 'page'.
 * @return array Key is the template name, value is the filename of the template
 */
function get_page_templates( $post = null, $post_type = 'page' ) {
	return array_flip( wp_get_theme()->get_page_templates( $post, $post_type ) );
}

/**
 * Tidies a filename for url display by the theme editor.
 *
 * @since 2.9.0
 * @access private
 *
 * @param string $fullpath Full path to the theme file
 * @param string $containingfolder Path of the theme parent folder
 * @return string
 */
function _get_template_edit_filename($fullpath, $containingfolder) {
	return str_replace(dirname(dirname( $containingfolder )) , '', $fullpath);
}

/**
 * Check if there is an update for a theme available.
 *
 * Will display link, if there is an update available.
 *
 * @since 2.7.0
 * @see get_theme_update_available()
 *
 * @param WP_Theme $theme Theme data object.
 */
function theme_update_available( $theme ) {
	echo get_theme_update_available( $theme );
}

/**
 * Retrieve the update link if there is a theme update available.
 *
 * Will return a link if there is an update available.
 *
 * @since 3.8.0
 *
 * @staticvar object $themes_update
 *
 * @param WP_Theme $theme WP_Theme object.
 * @return false|string HTML for the update link, or false if invalid info was passed.
 */
function get_theme_update_available( $theme ) {
	static $themes_update = null;

	if ( !current_user_can('update_themes' ) )
		return false;

	if ( !isset($themes_update) )
		$themes_update = get_site_transient('update_themes');

	if ( ! ( $theme instanceof WP_Theme ) ) {
		return false;
	}

	$stylesheet = $theme->get_stylesheet();

	$html = '';

	if ( isset($themes_update->response[ $stylesheet ]) ) {
		$update = $themes_update->response[ $stylesheet ];
		$theme_name = $theme->display('Name');
		$details_url = add_query_arg(array('TB_iframe' => 'true', 'width' => 1024, 'height' => 800), $update['url']); //Theme browser inside WP? replace this, Also, theme preview JS will override this on the available list.
		$update_url = wp_nonce_url( admin_url( 'update.php?action=upgrade-theme&amp;theme=' . urlencode( $stylesheet ) ), 'upgrade-theme_' . $stylesheet );

		if ( !is_multisite() ) {
			if ( ! current_user_can('update_themes') ) {
				/* translators: 1: theme name, 2: theme details URL, 3: additional link attributes, 4: version number */
				$html = sprintf( '<p><strong>' . __( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.' ) . '</strong></p>',
					$theme_name,
					esc_url( $details_url ),
					sprintf( 'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: theme name, 2: version number */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme_name, $update['new_version'] ) )
					),
					$update['new_version']
				);
			} elseif ( empty( $update['package'] ) ) {
				/* translators: 1: theme name, 2: theme details URL, 3: additional link attributes, 4: version number */
				$html = sprintf( '<p><strong>' . __( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>. <em>Automatic update is unavailable for this theme.</em>' ) . '</strong></p>',
					$theme_name,
					esc_url( $details_url ),
					sprintf( 'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: theme name, 2: version number */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme_name, $update['new_version'] ) )
					),
					$update['new_version']
				);
			} else {
				/* translators: 1: theme name, 2: theme details URL, 3: additional link attributes, 4: version number, 5: update URL, 6: additional link attributes */
				$html = sprintf( '<p><strong>' . __( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s" %6$s>update now</a>.' ) . '</strong></p>',
					$theme_name,
					esc_url( $details_url ),
					sprintf( 'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: theme name, 2: version number */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme_name, $update['new_version'] ) )
					),
					$update['new_version'],
					$update_url,
					sprintf( 'aria-label="%s" id="update-theme" data-slug="%s"',
						/* translators: %s: theme name */
						esc_attr( sprintf( __( 'Update %s now' ), $theme_name ) ),
						$stylesheet
					)
				);
			}
		}
	}

	return $html;
}

/**
 * Retrieve list of WordPress theme features (aka theme tags)
 *
 * @since 3.1.0
 *
 * @param bool $api Optional. Whether try to fetch tags from the WordPress.org API. Defaults to true.
 * @return array Array of features keyed by category with translations keyed by slug.
 */
function get_theme_feature_list( $api = true ) {
	// Hard-coded list is used if api not accessible.
	$features = array(

		__( 'Layout' ) => array(
			'grid-layout'   => __( 'Grid Layout' ),
			'one-column'    => __( 'One Column' ),
			'two-columns'   => __( 'Two Columns' ),
			'three-columns' => __( 'Three Columns' ),
			'four-columns'  => __( 'Four Columns' ),
			'left-sidebar'  => __( 'Left Sidebar' ),
			'right-sidebar' => __( 'Right Sidebar' ),
		),

		__( 'Features' ) => array(
			'accessibility-ready'   => __( 'Accessibility Ready' ),
			'buddypress'            => __( 'BuddyPress' ),
			'custom-background'     => __( 'Custom Background' ),
			'custom-colors'         => __( 'Custom Colors' ),
			'custom-header'         => __( 'Custom Header' ),
			'custom-logo'           => __( 'Custom Logo' ),
			'custom-menu'           => __( 'Custom Menu' ),
			'editor-style'          => __( 'Editor Style' ),
			'featured-image-header' => __( 'Featured Image Header' ),
			'featured-images'       => __( 'Featured Images' ),
			'flexible-header'       => __( 'Flexible Header' ),
			'footer-widgets'        => __( 'Footer Widgets' ),
			'front-page-post-form'  => __( 'Front Page Posting' ),
			'full-width-template'   => __( 'Full Width Template' ),
			'microformats'          => __( 'Microformats' ),
			'post-formats'          => __( 'Post Formats' ),
			'rtl-language-support'  => __( 'RTL Language Support' ),
			'sticky-post'           => __( 'Sticky Post' ),
			'theme-options'         => __( 'Theme Options' ),
			'threaded-comments'     => __( 'Threaded Comments' ),
			'translation-ready'     => __( 'Translation Ready' ),
		),

		__( 'Subject' )  => array(
			'blog'           => __( 'Blog' ),
			'e-commerce'     => __( 'E-Commerce' ),
			'education'      => __( 'Education' ),
			'entertainment'  => __( 'Entertainment' ),
			'food-and-drink' => __( 'Food & Drink' ),
			'holiday'        => __( 'Holiday' ),
			'news'           => __( 'News' ),
			'photography'    => __( 'Photography' ),
			'portfolio'      => __( 'Portfolio' ),
		)
	);

	if ( ! $api || ! current_user_can( 'install_themes' ) )
		return $features;

	if ( !$feature_list = get_site_transient( 'wporg_theme_feature_list' ) )
		set_site_transient( 'wporg_theme_feature_list', array(), 3 * HOUR_IN_SECONDS );

	if ( !$feature_list ) {
		$feature_list = themes_api( 'feature_list', array() );
		if ( is_wp_error( $feature_list ) )
			return $features;
	}

	if ( !$feature_list )
		return $features;

	set_site_transient( 'wporg_theme_feature_list', $feature_list, 3 * HOUR_IN_SECONDS );

	$category_translations = array(
		'Layout'   => __( 'Layout' ),
		'Features' => __( 'Features' ),
		'Subject'  => __( 'Subject' ),
	);

	// Loop over the wporg canonical list and apply translations
	$wporg_features = array();
	foreach ( (array) $feature_list as $feature_category => $feature_items ) {
		if ( isset($category_translations[$feature_category]) )
			$feature_category = $category_translations[$feature_category];
		$wporg_features[$feature_category] = array();

		foreach ( $feature_items as $feature ) {
			if ( isset($features[$feature_category][$feature]) )
				$wporg_features[$feature_category][$feature] = $features[$feature_category][$feature];
			else
				$wporg_features[$feature_category][$feature] = $feature;
		}
	}

	return $wporg_features;
}

/**
 * Retrieves theme installer pages from the WordPress.org Themes API.
 *
 * It is possible for a theme to override the Themes API result with three
 * filters. Assume this is for themes, which can extend on the Theme Info to
 * offer more choices. This is very powerful and must be used with care, when
 * overriding the filters.
 *
 * The first filter, {@see 'themes_api_args'}, is for the args and gives the action
 * as the second parameter. The hook for {@see 'themes_api_args'} must ensure that
 * an object is returned.
 *
 * The second filter, {@see 'themes_api'}, allows a plugin to override the WordPress.org
 * Theme API entirely. If `$action` is 'query_themes', 'theme_information', or 'feature_list',
 * an object MUST be passed. If `$action` is 'hot_tags', an array should be passed.
 *
 * Finally, the third filter, {@see 'themes_api_result'}, makes it possible to filter the
 * response object or array, depending on the `$action` type.
 *
 * Supported arguments per action:
 *
 * | Argument Name      | 'query_themes' | 'theme_information' | 'hot_tags' | 'feature_list'   |
 * | -------------------| :------------: | :-----------------: | :--------: | :--------------: |
 * | `$slug`            | No             |  Yes                | No         | No               |
 * | `$per_page`        | Yes            |  No                 | No         | No               |
 * | `$page`            | Yes            |  No                 | No         | No               |
 * | `$number`          | No             |  No                 | Yes        | No               |
 * | `$search`          | Yes            |  No                 | No         | No               |
 * | `$tag`             | Yes            |  No                 | No         | No               |
 * | `$author`          | Yes            |  No                 | No         | No               |
 * | `$user`            | Yes            |  No                 | No         | No               |
 * | `$browse`          | Yes            |  No                 | No         | No               |
 * | `$locale`          | Yes            |  Yes                | No         | No               |
 * | `$fields`          | Yes            |  Yes                | No         | No               |
 *
 * @since 2.8.0
 *
 * @param string       $action API action to perform: 'query_themes', 'theme_information',
 *                             'hot_tags' or 'feature_list'.
 * @param array|object $args   {
 *     Optional. Array or object of arguments to serialize for the Themes API.
 *
 *     @type string  $slug     The theme slug. Default empty.
 *     @type int     $per_page Number of themes per page. Default 24.
 *     @type int     $page     Number of current page. Default 1.
 *     @type int     $number   Number of tags to be queried.
 *     @type string  $search   A search term. Default empty.
 *     @type string  $tag      Tag to filter themes. Default empty.
 *     @type string  $author   Username of an author to filter themes. Default empty.
 *     @type string  $user     Username to query for their favorites. Default empty.
 *     @type string  $browse   Browse view: 'featured', 'popular', 'updated', 'favorites'.
 *     @type string  $locale   Locale to provide context-sensitive results. Default is the value of get_locale().
 *     @type array   $fields   {
 *         Array of fields which should or should not be returned.
 *
 *         @type bool $description        Whether to return the theme full description. Default false.
 *         @type bool $sections           Whether to return the theme readme sections: description, installation,
 *                                        FAQ, screenshots, other notes, and changelog. Default false.
 *         @type bool $rating             Whether to return the rating in percent and total number of ratings.
 *                                        Default false.
 *         @type bool $ratings            Whether to return the number of rating for each star (1-5). Default false.
 *         @type bool $downloaded         Whether to return the download count. Default false.
 *         @type bool $downloadlink       Whether to return the download link for the package. Default false.
 *         @type bool $last_updated       Whether to return the date of the last update. Default false.
 *         @type bool $tags               Whether to return the assigned tags. Default false.
 *         @type bool $homepage           Whether to return the theme homepage link. Default false.
 *         @type bool $screenshots        Whether to return the screenshots. Default false.
 *         @type int  $screenshot_count   Number of screenshots to return. Default 1.
 *         @type bool $screenshot_url     Whether to return the URL of the first screenshot. Default false.
 *         @type bool $photon_screenshots Whether to return the screenshots via Photon. Default false.
 *         @type bool $template           Whether to return the slug of the parent theme. Default false.
 *         @type bool $parent             Whether to return the slug, name and homepage of the parent theme. Default