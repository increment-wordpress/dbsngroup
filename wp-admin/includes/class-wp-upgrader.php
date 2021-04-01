<?php
/**
 * Upgrade API: WP_Upgrader class
 *
 * Requires skin classes and WP_Upgrader subclasses for backward compatibility.
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 2.8.0
 */

/** WP_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';

/** Plugin_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php';

/** Theme_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader-skin.php';

/** Bulk_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-bulk-upgrader-skin.php';

/** Bulk_Plugin_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-bulk-plugin-upgrader-skin.php';

/** Bulk_Theme_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-bulk-theme-upgrader-skin.php';

/** Plugin_Installer_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php';

/** Theme_Installer_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-theme-installer-skin.php';

/** Language_Pack_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader-skin.php';

/** Automatic_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

/** WP_Ajax_Upgrader_Skin class */
require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

/**
 * Core class used for upgrading/installing a local set of files via
 * the Filesystem Abstraction classes from a Zip file.
 *
 * @since 2.8.0
 */
class WP_Upgrader {

	/**
	 * The error/notification strings used to update the user on the progress.
	 *
	 * @since 2.8.0
	 * @access public
	 * @var array $strings
	 */
	public $strings = array();

	/**
	 * The upgrader skin being used.
	 *
	 * @since 2.8.0
	 * @access public
	 * @var Automatic_Upgrader_Skin|WP_Upgrader_Skin $skin
	 */
	public $skin = null;

	/**
	 * The result of the installation.
	 *
	 * This is set by WP_Upgrader::install_package(), only when the package is installed
	 * successfully. It will then be an array, unless a WP_Error is returned by the
	 * {@see 'upgrader_post_install'} filter. In that case, the WP_Error will be assigned to
	 * it.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @var WP_Error|array $result {
	 *      @type string $source             The full path to the source the files were installed from.
	 *      @type string $source_files       List of all the files in the source directory.
	 *      @type string $destination        The full path to the install destination folder.
	 *      @type string $destination_name   The name of the destination folder, or empty if `$destination`
	 *                                       and `$local_destination` are the same.
	 *      @type string $local_destination  The full local path to the destination folder. This is usually
	 *                                       the same as `$destination`.
	 *      @type string $remote_destination The full remote path to the destination folder
	 *                                       (i.e., from `$wp_filesystem`).
	 *      @type bool   $clear_destination  Whether the destination folder was cleared.
	 * }
	 */
	public $result = array();

	/**
	 * The total number of updates being performed.
	 *
	 * Set by the bulk update methods.
	 *
	 * @since 3.0.0
	 * @access public
	 * @var int $update_count
	 */
	public $update_count = 0;

	/**
	 * The current update if multiple updates are being performed.
	 *
	 * Used by the bulk update methods, and incremented for each update.
	 *
	 * @since 3.0.0
	 * @access public
	 * @var int
	 */
	public $update_current = 0;

	/**
	 * Construct the upgrader with a skin.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param WP_Upgrader_Skin $skin The upgrader skin to use. Default is a WP_Upgrader_Skin.
	 *                               instance.
	 */
	public function __construct( $skin = null ) {
		if ( null == $skin )
			$this->skin = new WP_Upgrader_Skin();
		else
			$this->skin = $skin;
	}

	/**
	 * Initialize the upgrader.
	 *
	 * This will set the relationship between the skin being used and this upgrader,
	 * and also add the generic strings to `WP_Upgrader::$strings`.
	 *
	 * @since 2.8.0
	 * @access public
	 */
	public function init() {
		$this->skin->set_upgrader($this);
		$this->generic_strings();
	}

	/**
	 * Add the generic strings to WP_Upgrader::$strings.
	 *
	 * @since 2.8.0
	 * @access public
	 */
	public function generic_strings() {
		$this->strings['bad_request'] = __('Invalid data provided.');
		$this->strings['fs_unavailable'] = __('Could not access filesystem.');
		$this->strings['fs_error'] = __('Filesystem error.');
		$this->strings['fs_no_root_dir'] = __('Unable to locate WordPress root directory.');
		$this->strings['fs_no_content_dir'] = __('Unable to locate WordPress content directory (wp-content).');
		$this->strings['fs_no_plugins_dir'] = __('Unable to locate WordPress plugin directory.');
		$this->strings['fs_no_themes_dir'] = __('Unable to locate WordPress theme directory.');
		/* translators: %s: directory name */
		$this->strings['fs_no_folder'] = __('Unable to locate needed folder (%s).');

		$this->strings['download_failed'] = __('Download failed.');
		$this->strings['installing_package'] = __('Installing the latest version&#8230;');
		$this->strings['no_files'] = __('The package contains no files.');
		$this->strings['folder_exists'] = __('Destination folder already exists.');
		$this->strings['mkdir_failed'] = __('Could not create directory.');
		$this->strings['incompatible_archive'] = __('The package could not be installed.');
		$this->strings['files_not_writable'] = __( 'The update cannot be installed because we will be unable to copy some files. This is usually due to inconsistent file permissions.' );

		$this->strings['maintenance_start'] = __('Enabling Maintenance mode&#8230;');
		$this->strings['maintenance_end'] = __('Disabling Maintenance mode&#8230;');
	}

	/**
	 * Connect to the filesystem.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @global WP_Filesystem_Base $wp_filesystem Subclass
	 *
	 * @param array $directories                  Optional. A list of directories. If any of these do
	 *                                            not exist, a WP_Error object will be returned.
	 *                                            Default empty array.
	 * @param bool  $allow_relaxed_file_ownership Whether to allow relaxed file ownership.
	 *                                            Default false.
	 * @return bool|WP_Error True if able to connect, false or a WP_Error otherwise.
	 */
	public function fs_connect( $directories = array(), $allow_relaxed_file_ownership = false ) {
		global $wp_filesystem;

		if ( false === ( $credentials = $this->skin->request_filesystem_credentials( false, $directories[0], $allow_relaxed_file_ownership ) ) ) {
			return false;
		}

		if ( ! WP_Filesystem( $credentials, $directories[0], $allow_relaxed_file_ownership ) ) {
			$error = true;
			if ( is_object($wp_filesystem) && $wp_filesystem->errors->get_error_code() )
				$error = $wp_filesystem->errors;
			// Failed to connect, Error and request again
			$this->skin->request_filesystem_credentials( $error, $directories[0], $allow_relaxed_file_ownership );
			return false;
		}

		if ( ! is_object($wp_filesystem) )
			return new WP_Error('fs_unavailable', $this->strings['fs_unavailable'] );

		if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
			return new WP_Error('fs_error', $this->strings['fs_error'], $wp_filesystem->errors);

		foreach ( (array)$directories as $dir ) {
			switch ( $dir ) {
				case ABSPATH:
					if ( ! $wp_filesystem->abspath() )
						return new WP_Error('fs_no_root_dir', $this->strings['fs_no_root_dir']);
					break;
				case WP_CONTENT_DIR:
					if ( ! $wp_filesystem->wp_content_dir() )
						return new WP_Error('fs_no_content_dir', $this->strings['fs_no_content_dir']);
					break;
				case WP_PLUGIN_DIR:
					if ( ! $wp_filesystem->wp_plugins_dir() )
						return new WP_Error('fs_no_plugins_dir', $this->strings['fs_no_plugins_dir']);
					break;
				case get_theme_root():
					if ( ! $wp_filesystem->wp_themes_dir() )
						return new WP_Error('fs_no_themes_dir', $this->strings['fs_no_themes_dir']);
					break;
				default:
					if ( ! $wp_filesystem->find_folder($dir) )
						return new WP_Error( 'fs_no_folder', sprintf( $this->strings['fs_no_folder'], esc_html( basename( $dir ) ) ) );
					break;
			}
		}
		return true;
	} //end fs_connect();

	/**
	 * Download a package.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param string $package The URI of the package. If this is the full path to an
	 *                        existing local file, it will be returned untouched.
	 * @return string|WP_Error The full path to the downloaded package file, or a WP_Error object.
	 */
	public function download_package( $package ) {

		/**
		 * Filters whether to return the package.
		 *
		 * @since 3.7.0
		 * @access public
		 *
		 * @param bool        $reply   Whether to bail without returning the package.
		 *                             Default false.
		 * @param string      $package The package file name.
		 * @param WP_Upgrader $this    The WP_Upgrader instance.
		 */
		$reply = apply_filters( 'upgrader_pre_download', false, $package, $this );
		if ( false !== $reply )
			return $reply;

		if ( ! preg_match('!^(http|https|ftp)://!i', $package) && file_exists($package) ) //Local file or remote?
			return $package; //must be a local file..

		if ( empty($package) )
			return new WP_Error('no_package', $this->strings['no_package']);

		$this->skin->feedback('downloading_package', $package);

		$download_file = download_url($package);

		if ( is_wp_error($download_file) )
			return new WP_Error('download_failed', $this->strings['download_failed'], $download_file->get_error_message());

		return $download_file;
	}

	/**
	 * Unpack a compressed package file.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @global WP_Filesystem_Base $wp_filesystem Subclass
	 *
	 * @param string $package        Full path to the package file.
	 * @param bool   $delete_package Optional. Whether to delete the package file after attempting
	 *                               to unpack it. Default true.
	 * @return string|WP_Error The path to the unpacked contents, or a WP_Error on failure.
	 */
	public function unpack_package( $package, $delete_package = true ) {
		global $wp_filesystem;

		$this->skin->feedback('unpack_package');

		$upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';

		//Clean up contents of upgrade directory beforehand.
		$upgrade_files = $wp_filesystem->dirlist($upgrade_folder);
		if ( !empty($upgrade_files) ) {
			foreach ( $upgrade_files as $file )
				$wp_filesystem->delete($upgrade_folder . $file['name'], true);
		}

		// We need a working directory - Strip off any .tmp or .zip suffixes
		$working_dir = $upgrade_folder . basename( basename( $package, '.tmp' ), '.zip' );

		// Clean up working directory
		if ( $wp_filesystem->is_dir($working_dir) )
			$wp_filesystem->delete($working_dir, true);

		// Unzip package to working directory
		$result = unzip_file( $package, $working_dir );

		// Once extracted, delete the package if required.
		if ( $delete_package )
			unlink($package);

		if ( is_wp_error($result) ) {
			$wp_filesystem->delete($working_dir, true);
			if ( 'incompatible_archive' == $result->get_error_code() ) {
				return new WP_Error( 'incompatible_archive', $this->strings['incompatible_archive'], $result->get_error_data() );
			}
			return $result;
		}

		return $working_dir;
	}

	/**
	 * Clears the directory where this item is going to be installed into.
	 *
	 * @since 4.3.0
	 * @access public
	 *
	 * @global WP_Filesystem_Base $wp_filesystem Subclass
	 *
	 * @param string $remote_destination The location on the remote filesystem to be cleared
	 * @return bool|WP_Error True upon success, WP_Error on failure.
	 */
	public function clear_destination( $remote_destination ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem->exists( $remote_destination ) ) {
			return true;
		}

		// Check all files are writable before attempting to clear the destination.
		$unwritable_files = array();

		$_files = $wp_filesystem->dirlist( $remote_destination, true, true );

		// Flatten the resulting array, iterate using each as we append to the array during iteration.
		while ( $f = each( $_files ) ) {
			$file = $f['value'];
			$name = $f['key'];

			if ( ! isset( $file['files'] ) ) {
				continue;
			}

			foreach ( $file['files'] as $filename => $details ) {
				$_files[ $name . '/' . $filename ] = $details;
			}
		}

		// Check writability.
		foreach ( $_files as $filename => $file_details ) {
			if ( ! $wp_filesystem->is_writable( $remote_destination . $filename ) ) {

				// Attempt to alter permissions to allow writes and try again.
				$wp_filesystem->chmod( $remote_destination . $filename, ( 'd' == $file_details['type'] ? FS_CHMOD_DIR : FS_CHMOD_FILE ) );
				if ( ! $wp_filesystem->is_writable( $remote_destination . $filename ) ) {
					$unwritable_files[] = $filename;
				}
			}
		}

		if ( ! empty( $unwritable_files ) ) {
			return new WP_Error( 'files_not_writable', $this->strings['files_not_writable'], implode( ', ', $unwritable_files ) );
		}

		if ( ! $wp_filesystem->delete( $remote_destination, true ) ) {
			return new WP_Error( 'remove_old_failed', $this->strings['remove_old_failed'] );
		}

		return true;
	}

	/**
	 * Install a package.
	 *
	 * Copies the contents of a package form a source directory, and installs them in
	 * a destination directory. Optionally removes the source. It can also optionally
	 * clear out the destination folder if it already exists.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @global WP_Filesystem_Base $wp_filesystem Subclass
	 * @global array              $wp_theme_directories
	 *
	 * @param array|string $args {
	 *     Optional. Array or string of arguments for installing a package. Default empty array.
	 *
	 *     @type string $source                      Required path to the package source. Default empty.
	 *     @type string $destination                 Required path to a folder to install the package in.
	 *                                               Default empty.
	 *     @type bool   $clear_destination           Whether to delete any files already in the destination
	 *                                               folder. Default false.
	 *     @type bool   $clear_working               Whether to delete the files form the working directory
	 *                                               after copying to the destination. Default false.
	 *     @type bool   $abort_if_destination_exists Whether to abort the installation if
	 *                                               the destination folder already exists. Default true.
	 *     @type array  $hook_extra                  Extra arguments to pass to the filter hooks called by
	 *                                               WP_Upgrader::install_package(). Default empty array.
	 * }
	 *
	 * @return array|WP_Error The result (also stored in `WP_Upgrader::$result`), or a WP_Error on failure.
	 */
	public function install_package( $args = array() ) {
		global $wp_filesystem, $wp_theme_directories;

		$defaults = array(
			'source' => '', // Please always pass this
			'destination' => '', // and this
			'clear_destination' => false,
			'clear_working' => false,
			'abort_if_destination_exists' => true,
			'hook_extra' => array()
		);

		$args = wp_parse_args($args, $defaults);

		// These were previously extract()'d.
		$source = $args['source'];
		$destination = $args['destination'];
		$clear_destination = $args['clear_destination'];

		@set_time_limit( 300 );

		if ( empty( $source ) || empty( $destination ) ) {
			return new WP_Error( 'bad_request', $this->strings['bad_request'] );
		}
		$this->skin->feedback( 'installing_package' );

		/**
		 * Filters the install response before the installation has started.
		 *
		 * Returning a truthy value, or one that could be evaluated as a WP_Error
		 * will effectively short-circuit the installation, returning that value
		 * instead.
		 *
		 * @since 2.8.0
		 *
		 * @param bool|WP_Error $response   Response.
		 * @param array         $hook_extra Extra arguments passed to hooked filters.
		 */
		$res = apply_filters( 'upgrader_pre_install', true, $args['hook_extra'] );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		//Retain the Original source and destinations
		$remote_source = $args['source'];
		$local_destination = $destination;

		$source_files = array_keys( $wp_filesystem->dirlist( $remote_source ) );
		$remote_destination = $wp_filesystem->find_folder( $local_destination );

		//Locate which directory to copy to the new folder, This is based on the actual folder holding the files.
		if ( 1 == count( $source_files ) && $wp_filesystem->is_dir( trailingslashit( $args['source'] ) . $source_files[0] . '/' ) ) { //Only one folder? Then we want its contents.
			$source = trailingslashit( $args['source'] ) . trailingslashit( $source_files[0] );
		} elseif ( count( $source_files ) == 0 ) {
			return new WP_Error( 'incompatible_archive_empty', $this->strings['incompatible_archive'], $this->strings['no_files'] ); // There are no files?
		} else { // It's only a single file, the upgrader will use the folder name of this file as the destination folder. Folder name is based on zip filename.
			$source = trailingslashit( $args['source'] );
		}

		/**
		 * Filters the source file location for the upgrade package.
		 *
		 * @since 2.8.0
		 * @since 4.4.0 The $hook_extra parameter became available.
		 *
		 * @param string      $source        File source location.
		 * @param string      $remote_source Remote file source location.
		 * @param WP_Upgrader $this          WP_Upgrader instance.
		 * @param array       $hook_extra    Extra arguments passed to hooked filters.
		 */
		$source = apply_filters( 'upgrader_source_selection', $source, $remote_source, $this, $args['hook_extra'] );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		// Has the source location changed? If so, we need a new source_files list.
		if ( $source !== $remote_source ) {
			$source_files = array_keys( $wp_filesystem->dirlist( $source ) );
		}

		/*
		 * Protection against deleting files in any important base directories.
		 * Theme_Upgrader & Plugin_Upgrader also trigger this, as they pass the
		 * destination directory (WP_PLUGIN_DIR / wp-content/themes) intending
		 * to copy the directory into the directory, whilst they pass the source
		 * as the actual files to copy.
		 */
		$protected_directories = array( ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, WP_CONTENT_DIR . '/themes' );

		if ( is_array( $wp_theme_directories ) ) {
			$protected_directories = array_merge( $protected_directories, $wp_theme_directories );
		}

		if ( in_array( $destination, $protected_directories ) ) {
			$remote_destination = trailingslashit( $remote_destination ) . trailingslashit( basename( $source ) );
			$destination = trailingslashit( $destination ) . trailingslashit( basename( $source ) );
		}

		if ( $clear_destination ) {
			// We're going to clear the destination if there's something there.
			$this->skin->feedback('remove_old');

			$removed = $this->clear_destination( $remote_destination );

			/**
			 * Filters whether the upgrader cleared the destination.
			 *
			 * @since 2.8.0
			 *
			 * @param mixed  $removed            Whether the destination was cleared. true on success, WP_Error on failure
			 * @param string $local_destination  The local package destination.
			 * @param string $remote_destination The remote package destination.
			 * @param array  $hook_extra         Extra arguments passed to hooked filters.
			 */
			$removed = apply_filters( 'upgrader_clear_destination', $removed, $local_destination, $remote_destination, $args['hook_extra'] );

			if ( is_wp_error( $removed ) ) {
				return $removed;
			}
		} elseif ( $args['abort_if_destination_exists'] && $wp_filesystem->exists($remote_destination) ) {
			//If we're not clearing the destination folder and something exists there already, Bail.
			//But first check to see if there are actually any files in the folder.
			$_files = $wp_filesystem->dirlist($remote_destination);
			if ( ! empty($_files) ) {
				$wp_filesystem->d