<?php
/**
 * Screen API: WP_Screen class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 4.4.0
 */

/**
 * Core class used to implement an admin screen API.
 *
 * @since 3.3.0
 */
final class WP_Screen {
	/**
	 * Any action associated with the screen. 'add' for *-add.php and *-new.php screens. Empty otherwise.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access public
	 */
	public $action;

	/**
	 * The base type of the screen. This is typically the same as $id but with any post types and taxonomies stripped.
	 * For example, for an $id of 'edit-post' the base is 'edit'.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access public
	 */
	public $base;

	/**
	 * The number of columns to display. Access with get_columns().
	 *
	 * @since 3.4.0
	 * @var int
	 * @access private
	 */
	private $columns = 0;

	/**
	 * The unique ID of the screen.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access public
	 */
	public $id;

	/**
	 * Which admin the screen is in. network | user | site | false
	 *
	 * @since 3.5.0
	 * @var string
	 * @access protected
	 */
	protected $in_admin;

	/**
	 * Whether the screen is in the network admin.
	 *
	 * Deprecated. Use in_admin() instead.
	 *
	 * @since 3.3.0
	 * @deprecated 3.5.0
	 * @var bool
	 * @access public
	 */
	public $is_network;

	/**
	 * Whether the screen is in the user admin.
	 *
	 * Deprecated. Use in_admin() instead.
	 *
	 * @since 3.3.0
	 * @deprecated 3.5.0
	 * @var bool
	 * @access public
	 */
	public $is_user;

	/**
	 * The base menu parent.
	 * This is derived from $parent_file by removing the query string and any .php extension.
	 * $parent_file values of 'edit.php?post_type=page' and 'edit.php?post_type=post' have a $parent_base of 'edit'.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access public
	 */
	public $parent_base;

	/**
	 * The parent_file for the screen per the admin menu system.
	 * Some $parent_file values are 'edit.php?post_type=page', 'edit.php', and 'options-general.php'.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access public
	 */
	public $parent_file;

	/**
	 * The post type associated with the screen, if any.
	 * The 'edit.php?post_type=page' screen has a post type of 'page'.
	 * The 'edit-tags.php?taxonomy=$taxonomy&post_type=page' screen has a post type of 'page'.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access public
	 */
	public $post_type;

	/**
	 * The taxonomy associated with the screen, if any.
	 * The 'edit-tags.php?taxonomy=category' screen has a taxonomy of 'category'.
	 * @since 3.3.0
	 * @var string
	 * @access public
	 */
	public $taxonomy;

	/**
	 * The help tab data associated with the screen, if any.
	 *
	 * @since 3.3.0
	 * @var array
	 * @access private
	 */
	private $_help_tabs = array();

	/**
	 * The help sidebar data associated with screen, if any.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access private
	 */
	private $_help_sidebar = '';

 	/**
	 * The accessible hidden headings and text associated with the screen, if any.
	 *
	 * @since 4.4.0
	 * @access private
	 * @var array
	 */
	private $_screen_reader_content = array();

	/**
	 * Stores old string-based help.
	 *
	 * @static
	 * @access private
	 *
	 * @var array
	 */
	private static $_old_compat_help = array();

	/**
	 * The screen options associated with screen, if any.
	 *
	 * @since 3.3.0
	 * @var array
	 * @access private
	 */
	private $_options = array();

	/**
	 * The screen object registry.
	 *
	 * @since 3.3.0
	 *
	 * @static
	 * @access private
	 *
	 * @var array
	 */
	private static $_registry = array();

	/**
	 * Stores the result of the public show_screen_options function.
	 *
	 * @since 3.3.0
	 * @var bool
	 * @access private
	 */
	private $_show_screen_options;

	/**
	 * Stores the 'screen_settings' section of screen options.
	 *
	 * @since 3.3.0
	 * @var string
	 * @access private
	 */
	private $_screen_settings;

	/**
	 * Fetches a screen object.
	 *
	 * @since 3.3.0
	 * @access public
	 *
	 * @static
	 *
	 * @global string $hook_suffix
	 *
	 * @param string|WP_Screen $hook_name Optional. The hook name (also known as the hook suffix) used to determine the screen.
	 * 	                                  Defaults to the current $hook_suffix global.
	 * @return WP_Screen Screen object.
	 */
	public static function get( $hook_name = '' ) {
		if ( $hook_name instanceof WP_Screen ) {
			return $hook_name;
		}

		$post_type = $taxonomy = null;
		$in_admin = false;
		$action = '';

		if ( $hook_name )
			$id = $hook_name;
		else
			$id = $GLOBALS['hook_suffix'];

		// For those pesky meta boxes.
		if ( $hook_name && post_type_exists( $hook_name ) ) {
			$post_type = $id;
			$id = 'post'; // changes later. ends up being $base.
		} else {
			if ( '.php' == substr( $id, -4 ) )
				$id = substr( $id, 0, -4 );

			if ( 'post-new' == $id || 'link-add' == $id || 'media-new' == $id || 'user-new' == $id ) {
				$id = substr( $id, 0, -4 );
				$action = 'add';
			}
		}

		if ( ! $post_type && $hook_name ) {
			if ( '-network' == substr( $id, -8 ) ) {
				$id = substr( $id, 0, -8 );
				$in_admin = 'network';
			} elseif ( '-user' == substr( $id, -5 ) ) {
				$id = substr( $id, 0, -5 );
				$in_admin = 'user';
			}

			$id = sanitize_key( $id );
			if ( 'edit-comments' != $id && 'edit-tags' != $id && 'edit-' == substr( $id, 0, 5 ) ) {
				$maybe = substr( $id, 5 );
				if ( taxonomy_exists( $maybe ) ) {
					$id = 'edit-tags';
					$taxonomy = $maybe;
				} elseif ( post_type_exists( $maybe ) ) {
					$id = 'edit';
					$post_type = $maybe;
				}
			}

			if ( ! $in_admin )
				$in_admin = 'site';
		} else {
			if ( defined( 'WP_NETWORK_ADMIN' ) && WP_NETWORK_ADMIN )
				$in_admin = 'network';
			elseif ( defined( 'WP_USER_ADMIN' ) && WP_USER_ADMIN )
				$in_admin = 'user';
			else
				$in_admin = 'site';
		}

		if ( 'index' == $id )
			$id = 'dashboard';
		elseif ( 'front' == $id )
			$in_admin = false;

		$base = $id;

		// If this is the current screen, see if we can be more accurate for post types and taxonomies.
		if ( ! $hook_name ) {
			if ( isset( $_REQUEST['post_type'] ) )
				$post_type = post_type_exists( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] : false;
			if ( isset( $_REQUEST['taxonomy'] ) )
				$taxonomy = taxonomy_exists( $_REQUEST['taxonomy'] ) ? $_REQUEST['taxonomy'] : false;

			switch ( $base ) {
				case 'post' :
					if ( isset( $_GET['post'] ) && isset( $_POST['post_ID'] ) && (int) $_GET['post'] !== (int) $_POST['post_ID'] )
						wp_die( __( 'A post ID mismatch has been detected.' ), __( 'Sorry, you are not allowed to edit this item.' ), 400 );
					elseif ( isset( $_GET['post'] ) )
						$post_id = (int) $_GET['post'];
					elseif ( isset( $_POST['post_ID'] ) )
						$post_id = (int) $_POST['post_ID'];
					else
						$post_id = 0;

					if ( $post_id ) {
						$post = get_post( $post_id );
						if ( $post )
							$post_type = $post->post_type;
					}
					break;
				case 'edit-tags' :
				case 'term' :
					if ( null === $post_type && is_object_in_taxonomy( 'post', $taxonomy ? $taxonomy : 'post_tag' ) )
						$post_type = 'post';
					break;
				case 'upload':
					$post_type = 'attachment';
					break;
			}
		}

		switch ( $base ) {
			case 'post' :
				if ( null === $post_type )
					$post_type = 'post';
				$id = $post_type;
				break;
			case 'edit' :
				if ( null === $post_type )
					$post_type = 'post';
				$id .= '-' . $post_type;
				break;
			case 'edit-tags' :
			case 'term' :
				if ( null === $taxonomy )
					$taxonomy = 'post_tag';
				// The edit-tags ID does not contain the post type. Look for it in the request.
				if ( null === $post_type ) {
					$post_type = 'post';
					if ( isset( $_REQUEST['post_type'] ) && post_type_exists( $_REQUEST['post_type'] ) )
						$post_type = $_REQUEST['post_type'];
				}

				$id = 'edit-' . $taxonomy;
				break;
		}

		if ( 'network' == $in_admin ) {
			$id   .= '-network';
			$base .= '-network';
		} elseif ( 'user' == $in_admin ) {
			$id   .= '-user';
			$base .= '-user';
		}

		if ( isset( self::$_registry[ $id ] ) ) {
			$screen = self::$_registry[ $id ];
			if ( $screen === get_current_screen() )
				return $screen;
		} else {
			$screen = new WP_Screen();
			$screen->id     = $id;
		}

		$screen->base       = $base;
		$screen->action     = $action;
		$screen->post_type  = (string) $post_type;
		$screen->taxonomy   = (string) $taxonomy;
		$screen->is_user    = ( 'user' == $in_admin );
		$screen->is_network = ( 'network' == $in_admin );
		$screen->in_admin   = $in_admin;

		self::$_registry[ $id ] = $screen;

		return $screen;
	}

	/**
	 * Makes the screen object the current screen.
	 *
	 * @see set_current_screen()
	 * @since 3.3.0
	 *
	 * @global WP_Screen $current_screen
	 * @global string    $taxnow
	 * @global string    $typenow
	 */
	public function set_current_screen() {
		global $current_screen, $taxnow, $typenow;
		$current_screen = $this;
		$taxnow = $this->taxonomy;
		$typenow = $this->post_type;

		/**
		 * Fires after the current screen has been set.
		 *
		 * @since 3.0.0
		 *
		 * @param WP_Screen $current_screen Current WP_Screen object.
		 */
		do_action( 'current_screen', $current_screen );
	}

	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 * @access private
	 */
	private function __construct() {}

	/**
	 * Indicates whether the screen is in a particular admin
	 *
	 * @since 3.5.0
	 *
	 * @param string $admin The admin to check against (network | user | site).
	 *                      If empty any of the three admins will result in true.
	 * @return bool True if the screen is in the indicated admin, false otherwise.
	 */
	public function in_admin( $admin = null ) {
		if ( empty( $admin ) )
			return (bool) $this->in_admin;

		return ( $admin == $this->in_admin );
	}

	/**
	 * Sets the old string-based contextual help for the screen for backward compatibility.
	 *
	 * @since 3.3.0
	 *
	 * @static
	 *
	 * @param WP_Screen $screen A screen object.
	 * @param string $help Help text.
	 */
	public static function add_old_compat_help( $screen, $help ) {
		self::$_old_compat_help[ $screen->id ] = $help;
	}

	/**
	 * Set the parent information for the screen.
	 * This is called in admin-header.php after the menu parent for the screen has been determined.
	 *
	 * @since 3.3.0
	 *
	 * @param string $parent_file The parent file of the screen. Typically the $parent_file global.
	 */
	public function set_parentage( $parent_file ) {
		$this->parent_file = $parent_file;
		list( $this->parent_base ) = explode( '?', $parent_file );
		$this->parent_base = str_replace( '.php', '', $this->parent_base );
	}

	/**
	 * Adds an option for the screen.
	 * Call this in template files after admin.php is loaded and before admin-header.php is loaded to add screen options.
	 *
	 * @since 3.3.0
	 *
	 * @param string $option Option ID
	 * @param mixed $args Option-dependent arguments.
	 */
	public function add_option( $option, $args = array() ) {
		$this->_options[ $option ] = $args;
	}

	/**
	 * Remove an option from the screen.
	 *
	 * @since 3.8.0
	 *
	 * @param string $option Option ID.
	 */
	public function remove_option( $option ) {
		unset( $this->_options[ $option ] );
	}

	/**
	 * Remove all options from the screen.
	 *
	 * @since 3.8.0
	 */
	public function remove_options() {
		$this->_options = array();
	}

	/**
	 * Get the options registered for the screen.
	 *
	 * @since 3.8.0
	 *
	 * @return array Options with arguments.
	 */
	public function get_options() {
		return $this->_options;
	}

	/**
	 * Gets the arguments for an option for the screen.
	 *
	 * @since 3.3.0
	 *
	 * @param string $option Option name.
	 * @param string $key    Optional. Specific array key for when the option is an array.
	 *                       Default false.
	 * @return string The option value if set, null otherwise.
	 */
	public function get_option( $option, $key = false ) {
		if ( ! isset( $this->_options[ $option ] ) )
			return null;
		if ( $key ) {
			if ( isset( $this->_options[ $option ][ $key ] ) )
				return $this->_options[ $option ][ $key ];
			return null;
		}
		return $this->_options[ $option ];
	}

	/**
	 * Gets the help tabs registered for the screen.
	 *
	 * @since 3.4.0
	 * @since 4.4.0 Help tabs are ordered by their priority.
	 *
	 * @return array Help tabs with arguments.
	 */
	public function get_help_tabs() {
		$help_tabs = $this->_help_tabs;

		$priorities = array();
		foreach ( $help_tabs as $help_tab ) {
			if ( isset( $priorities[ $help_tab['priority'] ] ) ) {
				$priorities[ $help_tab['priority'] ][] = $help_tab;
			} else {
				$priorities[ $help_tab['priority'] ] = array( $help_tab );
			}
		}

		ksort( $priorities );

		$sorted = array();
		foreach ( $priorities as $list ) {
			foreach ( $list as $tab ) {
				$sorted[ $tab['id'] ] = $tab;
			}
		}

		return $sorted;
	}

	/**
	 * Gets the arguments for a help tab.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id Help Tab ID.
	 * @return array Help tab arguments.
	 */
	public function get_help_tab( $id ) {
		if ( ! isset( $this->_help_tabs[ $id ] ) )
			return null;
		return $this->_help_tabs[ $id ];
	}

	/**
	 * Add a help tab to the contextual help for the screen.
	 * Call this on the load-$pagenow hook for the relevant screen.
	 *
	 * @since 3.3.0
	 * @since 4.4.0 The `$priority` argument was added.
	 *
	 * @param array $args {
	 *     Array of arguments used to display the help tab.
	 *
	 *     @type string $title    Title for the tab. Default false.
	 *     @type string $id       Tab ID. Must be HTML-safe. Default false.
	 *     @type string $content  Optional. Help tab content in plain text or HTML. Default empty string.
	 *     @type string $callback Optional. A callback to generate the tab content. Default false.
	 *     @type int    $priority Optional. The priority of the tab, used for ordering. Default 10.
	 * }
	 */
	public function add_help_tab( $args ) {
		$defaults = array(
			'title'    => false,
			'id'       => false,
			'content'  => '',
			'callback' => false,
			'priority' => 10,
		);
		$args = wp_parse_args( $args, $defaults );

		$args['id'] = sanitize_html_class( $args['id'] );

		// Ensure we have an ID and title.
		if ( ! $args['id'] || ! $args['title'] )
			return;

		// Allows for overriding an existing tab with that ID.
		$this->_help_tabs[ $args['id'] ] = $args;
	}

	/**
	 * Removes a help tab from the contextual help for the screen.
	 *
	 * @since 3.3.0
	 *
	 * @param string $id The help tab ID.
	 */
	public function remove_help_tab( $id ) {
		unset( $this->_help_tabs[ $id ] );
	}

	/**
	 * Removes all help tabs from the contextual help for the screen.
	 *
	 * @since 3.3.0
	 */
	public function remove_help_tabs() {
		$this->_help_tabs = array();
	}

	/**
	 * Gets the content from a contextual help sidebar.
	 *
	 * @since 3.4.0
	 *
	 * @return string Contents of the help sidebar.
	 */
	public function get_help_sidebar() {
		return $this->_help_sidebar;
	}

	/**
	 * Add a sidebar to the contextual help for the screen.
	 * Call this in template files after admin.php is loaded and before admin-header.php is loaded to add a sidebar to the contextual help.
	 *
	 * @since 3.3.0
	 *
	 * @param string $content Sidebar content in plain text or HTML.
	 */
	public function set_help_sidebar( $content ) {
		$this->_help_sidebar = $content;
	}

	/**
	 * Gets the number of layout columns the user has selected.
	 *
	 * The layout_columns option controls the max number and default number of
	 * columns. This method returns the number of columns within that range selected
	 * by the user via Screen Options. If no selection has been made, the default
	 * provisioned in layout_columns is returned. If the screen does not support
	 * selecting the number of layout columns, 0 is returned.
	 *
	 * @since 3.4.0
	 *
	 * @return int Number of columns to display.
	 */
	public function get_columns() {
		return $this->columns;
	}

 	/**
	 * Get the accessible hidden headings and text used in the screen.
	 *
	 * @since 4.4.0
	 *
	 * @see set_screen_reader_content() For more information on the array format.
	 *
	 * @return array An associative array of screen reader text strings.
	 */
	public function get_screen_reader_content() {
		return $this->_screen_reader_content;
	}

	/**
	 * Get a screen reader text string.
	 *
	 * @since 4.4.0
	 *
	 * @param string $key Screen reader text array named key.
	 * @return string Screen reader text string.
	 */
	public function get_screen_reader_te