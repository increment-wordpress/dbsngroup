<?php
/**
 * WordPress Filesystem Class for implementing SSH2
 *
 * To use this class you must follow these steps for PHP 5.2.6+
 *
 * @contrib http://goodherbwebmart.com/ - Installation Notes
 *
 * Complie libssh2 (Note: Only 0.14 is officaly working with PHP 5.2.6+ right now, But many users have found the latest versions work)
 *
 * cd /usr/src
 * wget http://goodherbwebmart.com/
 * tar -zxvf libssh2-0.14.tar.gz
 * cd libssh2-0.14/
 * ./configure
 * make all install
 *
 * Note: Do not leave the directory yet!
 *
 * Enter: pecl install -f ssh2
 *
 * Copy the ssh.so file it creates to your PHP Module Directory.
 * Open up your PHP.INI file and look for where extensions are placed.
 * Add in your PHP.ini file: extension=ssh2.so
 *
 * Restart Apache!
 * Check phpinfo() streams to confirm that: ssh2.shell, ssh2.exec, ssh2.tunnel, ssh2.scp, ssh2.sftp  exist.
 *
 * Note: as of WordPress 2.8, This utilises the PHP5+ function 'stream_get_contents'
 *
 * @since 2.7.0
 *
 * @package WordPress
 * @subpackage Filesystem
 */
class WP_Filesystem_SSH2 extends WP_Filesystem_Base {

	/**
	 * @access public
	 */
	public $link = false;

	/**
	 * @access public
	 * @var resource
	 */
	public $sftp_link;
	public $keys = false;

	/**
	 * @access public
	 *
	 * @param array $opt
	 */
	public function __construct( $opt = '' ) {
		$this->method = 'ssh2';
		$this->errors = new WP_Error();

		//Check if possible to use ssh2 functions.
		if ( ! extension_loaded('ssh2') ) {
			$this->errors->add('no_ssh2_ext', __('The ssh2 PHP extension is not available'));
			return;
		}
		if ( !function_exists('stream_get_contents') ) {
			$this->errors->add(
				'ssh2_php_requirement',
				sprintf(
					/* translators: %s: stream_get_contents() */
					__( 'The ssh2 PHP extension is available, however, we require the PHP5 function %s' ),
					'<code>stream_get_contents()</code>'
				)
			);
			return;
		}

		// Set defaults:
		if ( empty($opt['port']) )
			$this->options['port'] = 22;
		else
			$this->options['port'] = $opt['port'];

		if ( empty($opt['hostname']) )
			$this->errors->add('empty_hostname', __('SSH2 hostname is required'));
		else
			$this->options['hostname'] = $opt['hostname'];

		// Check if the options provided are OK.
		if ( !empty ($opt['public_key']) && !empty ($opt['private_key']) ) {
			$this->options['public_key'] = $opt['public_key'];
			$this->options['private_key'] = $opt['private_key'];

			$this->options['hostkey'] = array('hostkey' => 'ssh-rsa');

			$this->keys = true;
		} elseif ( empty ($opt['username']) ) {
			$this->errors->add('empty_username', __('SSH2 username is required'));
		}

		if ( !empty($opt['username']) )
			$this->options['username'] = $opt['username'];

		if ( empty ($opt['password']) ) {
			// Password can be blank if we are using keys.
			if ( !$this->keys )
				$this->errors->add('empty_password', __('SSH2 password is required'));
		} else {
			$this->options['password'] = $opt['password'];
		}
	}

	/**
	 * @access public
	 *
	 * @return bool
	 */
	public function connect() {
		if ( ! $this->keys ) {
			$this->link = @ssh2_connect($this->options['hostname'], $this->options['port']);
		} else {
			$this->link = @ssh2_connect($this->options['hostname'], $this->options['port'], $this->options['hostkey']);
		}

		if ( ! $this->link ) {
			$this->errors->add( 'connect',
				/* translators: %s: hostname:port */
				sprintf( __( 'Failed to connect to SSH2 Server %s' ),
					$this->options['hostname'] . ':' . $this->options['port']
				)
			);
			return false;
		}

		if ( !$this->keys ) {
			if ( ! @ssh2_auth_password($this->link, $this->options['username'], $this->options['password']) ) {
				$this->errors->add( 'auth',
					/* translators: %s: username */
					sprintf( __( 'Username/Password incorrect for %s' ),
						$this->options['username']
					)
				);
				return false;
			}
		} else {
			if ( ! @ssh2_auth_pubkey_file($this->link, $this->options['username'], $this->options['public_key'], $this->options['private_key'