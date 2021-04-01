<?php
/**
 * Core Navigation Menu API
 *
 * @package WordPress
 * @subpackage Nav_Menus
 * @since 3.0.0
 */

/** Walker_Nav_Menu_Edit class */
require_once( ABSPATH . 'wp-admin/includes/class-walker-nav-menu-edit.php' );

/** Walker_Nav_Menu_Checklist class */
require_once( ABSPATH . 'wp-admin/includes/class-walker-nav-menu-checklist.php' );

/**
 * Prints the appropriate response to a menu quick search.
 *
 * @since 3.0.0
 *
 * @param array $request The unsanitized request values.
 */
function _wp_ajax_menu_quick_search( $request = array() ) {
	$args = array();
	$type = isset( $request['type'] ) ? $request['type'] : '';
	$object_type = isset( $request['object_type'] ) ? $request['object_type'] : '';
	$query = isset( $request['q'] ) ? $request['q'] : '';
	$response_format = isset( $request['response-format'] ) && in_array( $request['response-format'], array( 'json', 'markup' ) ) ? $request['response-format'] : 'json';

	if ( 'markup' == $response_format ) {
		$args['walker'] = new Walker_Nav_Menu_Checklist;
	}

	if ( 'get-post-item' == $type ) {
		if ( post_type_exists( $object_type ) ) {
			if ( isset( $request['ID'] ) ) {
				$object_id = (int) $request['ID'];
				if ( 'markup' == $response_format ) {
					echo walk_nav_menu_tree( array_map('wp_setup_nav_menu_item', array( get_post( $object_id ) ) ), 0, (object) $args );
				} elseif ( 'json' == $response_format ) {
					echo wp_json_encode(
						array(
							'ID' => $object_id,
							'post_title' => get_the_title( $object_id ),
							'post_type' => get_post_type( $object_id ),
						)
					);
					echo "\n";
				}
			}
		} elseif ( taxonomy_exists( $object_type ) ) {
			if ( isset( $request['ID'] ) ) {
				$object_id = (int) $request['ID'];
				if ( 'markup' == $response_format ) {
					echo walk_nav_menu_tree( array_map('wp_setup_nav_menu_item', array( get_term( $object_id, $object_type ) ) ), 0, (object) $args );
				} elseif ( 'json' == $response_format ) {
					$post_obj = get_term( $object_id, $object_type );
					echo wp_json_encode(
						array(
							'ID' => $object_id,
							'post_title' => $post_obj->name,
							'post_type' => $object_type,
						)
					);
					echo "\n";
				}
			}

		}

	} elseif ( preg_match('/quick-search-(posttype|taxonomy)-([a-zA-Z_-]*\b)/', $type, $matches) ) {
		if ( 'posttype' == $matches[1] && get_post_type_object( $matches[2] ) ) {
			$post_type_obj = _wp_nav_menu_meta_box_object( get_post_type_object( $matches[2] ) );
			$args = array_merge(
				$args,
				array(
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'posts_per_page'         => 10,
					'post_type'              => $matches[2],
					's'                      => $query,
				)
			);
			if ( isset( $post_type_obj->_default_query ) ) {
				$args = array_merge( $args, (array) $post_type_obj->_default_query );
			}
			$search_results_query = new WP_Query( $args );
			if ( ! $search_results_query->have_posts() ) {
				return;
			}
			while ( $search_results_query->have_posts() ) {
				$post = $search_results_query->next_post();
				if ( 'markup' == $response_format ) {
					$var_by_ref = $post->ID;
					echo walk_nav_menu_tree( array_map('wp_setup_nav_menu_item', array( get_post( $var_by_ref ) ) ), 0, (object) $args );
				} elseif ( 'json' == $response_format ) {
					echo wp_json_encode(
						array(
							'ID' => $post->ID,
							'post_title' => get_the_title( $post->ID ),
							'post_type' => $matches[2],
						)
					);
					echo "\n";
				}
			}
		} elseif ( 'taxonomy' == $matches[1] ) {
			$terms = get_terms( $matches[2], array(
				'name__like' => $query,
				'number' => 10,
			));
			if ( empty( $terms ) || is_wp_error( $terms ) )
				return;
			foreach ( (array) $terms as $term ) {
				if ( 'markup' == $response_format ) {
					echo walk_nav_menu_tree( array_map('wp_setup_nav_menu_item', array( $term ) ), 0, (object) $args );
				} elseif ( 'json' == $response_format ) {
					echo wp_json_encode(
						array(
							'ID' => $term->term_id,
							'post_title' => $term->name,
							'post_type' => $matches[2],
						)
					);
					echo "\n";
				}
			}
		}
	}
}

/**
 * Register nav menu meta boxes and advanced menu items.
 *
 * @since 3.0.0
 **/
function wp_nav_menu_setup() {
	// Register meta boxes
	wp_nav_menu_post_type_meta_boxes();
	add_meta_box( 'add-custom-links', __( 'Custom Links' ), 'wp_nav_menu_item_link_meta_box', 'nav-menus', 'side', 'default' );
	wp_nav_menu_taxonomy_meta_boxes();

	// Register advanced menu items (columns)
	add_filter( 'manage_nav-menus_columns', 'wp_nav_menu_manage_columns' );

	// If first time editing, disable advanced items by default.
	if ( false === get_user_option( 'managenav-menuscolumnshidden' ) ) {
		$user = wp_get_current_user();
		update_user_option($user->ID, 'managenav-menuscolumnshidden',
			array( 0 => 'link-target', 1 => 'css-classes', 2 => 'xfn', 3 => 'description', 4 => 'title-attribute', ),
			true);
	}
}

/**
 * Limit the amount of meta boxes to pages, posts, links, and categories for first time users.
 *
 * @since 3.0.0
 *
 * @global array $wp_meta_boxes
 **/
function wp_initial_nav_menu_meta_boxes() {
	global $wp_meta_boxes;

	if ( get_user_option( 'metaboxhidden_nav-menus' ) !== false || ! is_array($wp_meta_boxes) )
		return;

	$initial_meta_boxes = array( 'add-post-type-page', 'add-post-type-post', 'add-custom-links', 'add-category' );
	$hidden_meta_boxes = array();

	foreach ( array_keys($wp_meta_boxes['nav-menus']) as $context ) {
		foreach ( array_keys($wp_meta_boxes['nav-menus'][$context]) as $priority ) {
			foreach ( $wp_meta_boxes['nav-menus'][$context][$priority] as $box ) {
				if ( in_array( $box['id'], $initial_meta_boxes ) ) {
					unset( $box['id'] );
				} else {
					$hidden_meta_boxes[] = $box['id'];
				}
			}
		}
	}

	$user = wp_get_current_user();
	update_user_option( $user->ID, 'metaboxhidden_nav-menus', $hidden_meta_boxes, true );
}

/**
 * Creates meta boxes for any post type menu item..
 *
 * @since 3.0.0
 */
function wp_nav_menu_post_type_meta_boxes() {
	$post_types = get_post_types( array( 'show_in_nav_menus' => true ), 'object' );

	if ( ! $post_types )
		return;

	foreach ( $post_types as $post_type ) {
		/**
		 * Filters whether a menu items meta box will be added for the current
		 * object type.
		 *
		 * If a falsey value is returned instead of an object, the menu items
		 * meta box for the current meta box object will not be added.
		 *
		 * @since 3.0.0
		 *
		 * @param object $meta_box_object The current object to add a menu items
		 *                                meta box for.
		 */
		$post_type = apply_filters( 'nav_menu_meta_box_object', $post_type );
		if ( $post_type ) {
			$id = $post_type->name;
			// Give pages a higher priority.
			$priority = ( 'page' == $post_type->name ? 'core' : 'default' );
			add_meta_box( "add-post-type-{$id}", $post_type->labels->name, 'wp_nav_menu_item_post_type_meta_box', 'nav-menus', 'side', $priority, $post_type );
		}
	}
}

/**
 * Creates meta boxes for any taxonomy menu item.
 *
 * @since 3.0.0
 */
function wp_nav_menu_taxonomy_meta_boxes() {
	$taxonomies = get_taxonomies( array( 'show_in_nav_menus' => true ), 'object' );

	if ( !$taxonomies )
		return;

	foreach ( $taxonomies as $tax ) {
		/** This filter is documented in wp-admin/includes/nav-menu.php */
		$tax = apply_filters( 'nav_menu_meta_box_object', $tax );
		if ( $tax ) {
			$id = $tax->name;
			add_meta_box( "add-{$id}", $tax->labels->name, 'wp_nav_menu_item_taxonomy_meta_box', 'nav-menus', 'side', 'default', $tax );
		}
	}
}

/**
 * Check whether to disable the Menu Locations meta box submit button
 *
 * @since 3.6.0
 *
 * @global bool $one_theme_location_no_menus to determine if no menus exist
 *
 * @param int|string $nav_menu_selected_id (id, name or slug) of the currently-selected menu
 * @return string Disabled attribute if at least one menu exists, false if not
 */
function wp_nav_menu_disabled_check( $nav_menu_selected_id ) {
	global $one_theme_location_no_menus;

	if ( $one_theme_location_no_menus )
		return false;

	return disabled( $nav_menu_selected_id, 0 );
}

/**
 * Displays a meta box for the custom links menu item.
 *
 * @since 3.0.0
 *
 * @global int        $_nav_menu_placeholder
 * @global int|string $nav_menu_selected_id
 */
function wp_nav_menu_item_link_meta_box() {
	global $_nav_menu_placeholder, $nav_menu_selected_id;

	$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;

	?>
	<div class="customlinkdiv" id="customlinkdiv">
		<input type="hidden" value="custom" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" />
		<p id="menu-item-url-wrap" class="wp-clearfix">
			<label class="howto" for="custom-menu-item-url"><?php _e( 'URL' ); ?></label>
			<input id="custom-menu-item-url" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-url]" type="text" class="code menu-item-textbox" value="http://" />
		</p>

		<p id="menu-item-name-wrap" class="wp-clearfix">
			<label class="howto" for="custom-menu-item-name"><?php _e( 'Link Text' ); ?></label>
			<input id="custom-menu-item-name" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-title]" type="text" class="regular-text menu-item-textbox" />
		</p>

		<p class="button-controls wp-clearfix">
			<span class="add-to-menu">
				<input type="submit"<?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?> class="button submit-add-to-menu right" value="<?php esc_attr_e('Add to Menu'); ?>" name="add-custom-menu-item" id="submit-customlinkdiv" />
				<span class="spinner"></span>
			</span>
		</p>

	</div><!-- /.customlinkdiv -->
	<?php
}

/**
 * Displays a meta box for a post type menu item.
 *
 * @since 3.0.0
 *
 * @global int        $_nav_menu_placeholder
 * @global int|string $nav_menu_selected_id
 *
 * @param string $object Not used.
 * @param array  $box {
 *     Post type menu item meta box arguments.
 *
 *     @type string       $id       Meta box 'id' attribute.
 *     @type string       $title    Meta box title.
 *     @type string       $callback Meta box display callback.
 *     @type WP_Post_Type $args     Extra meta box arguments (the post type object for this meta box).
 * }
 */
function wp_nav_menu_item_post_type_meta_box( $object, $box ) {
	global $_nav_menu_placeholder, $nav_menu_selected_id;

	$post_type_name = $box['args']->name;

	// Paginate browsing for large numbers of post objects.
	$per_page = 50;
	$pagenum = isset( $_REQUEST[$post_type_name . '-tab'] ) && isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
	$offset = 0 < $pagenum ? $per_page * ( $pagenum - 1 ) : 0;

	$args = array(
		'offset' => $offset,
		'order' => 'ASC',
		'orderby' => 'title',
		'posts_per_page' => $per_page,
		'post_type' => $post_type_name,
		'suppress_filters' => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false
	);

	if ( isset( $box['args']->_default_query ) )
		$args = array_merge($args, (array) $box['args']->_default_query );

	// @todo transient caching of these results with proper invalidation on updating of a post of this type
	$get_posts = new WP_Query;
	$posts = $get_posts->query( $args );
	if ( ! $get_posts->post_count ) {
		echo '<p>' . __( 'No items.' ) . '</p>';
		return;
	}

	$num_pages = $get_posts->max_num_pages;

	$page_links = paginate_links( array(
		'base' => add_query_arg(
			array(
				$post_type_name . '-tab' => 'all',
				'paged' => '%#%',
				'item-type' => 'post_type',
				'item-object' => $post_type_name,
			)
		),
		'format' => '',
		'prev_text'          => '<span aria-label="' . esc_attr__( 'Previous page' ) . '">' . __( '&laquo;' ) . '</span>',
		'next_text'          => '<span aria-label="' . esc_attr__( 'Next page' ) . '">' . __( '&raquo;' ) . '</span>',
		'before_page_number' => '<span class="screen-reader-text">' . __( 'Page' ) . '</span> ',
		'total'   => $num_pages,
		'current' => $pagenum
	));

	$db_fields = false;
	if ( is_post_type_hierarchical( $post_type_name ) ) {
		$db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );
	}

	$walker = new Walker_Nav_Menu_Checklist( $db_fields );

	$current_tab = 'most-recent';
	if ( isset( $_REQUEST[$post_type_name . '-tab'] ) && in_array( $_REQUEST[$post_type_name . '-tab'], array('all', 'search') ) ) {
		$current_tab = $_REQUEST[$post_type_name . '-tab'];
	}

	if ( ! empty( $_REQUEST['quick-search-posttype-' . $post_type_name] ) ) {
		$current_tab = 'search';
	}

	$removed_args = array(
		'action',
		'customlink-tab',
		'edit-menu-item',
		'menu-item',
		'page-tab',
		'_wpnonce',
	);

	?>
	<div id="posttype-<?php echo $post_type_name; ?>" class="posttypediv">
		<ul id="posttype-<?php echo $post_type_name; ?>-tabs" class="posttype-tabs add-menu-item-tabs">
			<li <?php echo ( 'most-recent' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-posttype-<?php echo esc_attr( $post_type_name ); ?>-most-recent" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'most-recent', remove_query_arg($removed_args))); ?>#tabs-panel-posttype-<?php echo $post_type_name; ?>-most-recent">
					<?php _e( 'Most Recent' ); ?>
				</a>
			</li>
			<li <?php echo ( 'all' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="<?php echo esc_attr( $post_type_name ); ?>-all" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'all', remove_query_arg($removed_args))); ?>#<?php echo $post_type_name; ?>-all">
					<?php _e( 'View All' ); ?>
				</a>
			</li>
			<li <?php echo ( 'search' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-posttype-<?php echo esc_attr( $post_type_name ); ?>-search" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'search', remove_query_arg($removed_args))); ?>#tabs-panel-posttype-<?php echo $post_type_name; ?>-search">
					<?php _e( 'Search'); ?>
				</a>
			</li>
		</ul><!-- .posttype-tabs -->

		<div id="tabs-panel-posttype-<?php echo $post_type_name; ?>-most-recent" class="tabs-panel <?php
			echo ( 'most-recent' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>">
			<ul id="<?php echo $post_type_name; ?>checklist-most-recent" class="categorychecklist form-no-clear">
				<?php
				$recent_args = array_merge( $args, array( 'orderby' => 'post_date', 'order' => 'DESC', 'posts_per_page' => 15 ) );
				$most_recent = $get_posts->query( $recent_args );
				$args['walker'] = $walker;

				/**
				 * Filters the posts displayed in the 'Most Recent' tab of the current
				 * post type's menu items meta box.
				 *
				 * The dynamic portion of the hook name, `$post_type_name`, refers to the post type name.
				 *
				 * @since 4.3.0
				 *
				 * @param array $most_recent An array of post objects being listed.
				 * @param array $args        An array of WP_Query arguments.
				 * @param array $box         Arguments passed to wp_nav_menu_item_post_type_meta_box().
				 */
				$most_recent = apply_filters( "nav_menu_items_{$post_type_name}_recent", $most_recent, $args, $box );

				echo walk_nav_menu_tree( array_map('wp_setup_nav_menu_item', $most_recent), 0, (object) $args );
				?>
			</ul>
		</div><!-- /.tabs-panel -->

		<div class="tabs-panel <?php
			echo ( 'search' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>" id="tabs-panel-posttype-<?php echo $post_type_name; ?>-search">
			<?php
			if ( isset( $_REQUEST['quick-search-posttype-' . $post_type_name] ) ) {
				$searched = esc_attr( $_REQUEST['quick-search-posttype-' . $post_type_name] );
				$search_results = get_posts( array( 's' => $searched, 'post_type' => $post_type_name, 'fields' => 'all', 'order' => 'DESC', ) );
			} else {
				$searched = '';
				$search_results = array();
			}
			?>
			<p class="quick-search-wrap">
				<label for="quick-search-posttype-<?php echo $post_type_name; ?>" class="screen-reader-text"><?php _e( 'Search' ); ?></label>
				<input type="search" class="quick-search" value="<?php echo $searched; ?>" name="quick-search-posttype-<?php echo $post_type_name; ?>" id="quick-search-posttype-<?php echo $post_type_name; ?>" />
				<span class="spinner"></span>
				<?php submit_button( __( 'Search' ), 'small quick-sear