<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link http://goodherbwebmart.com/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'dbsn');

/** MySQL database username */
define('DB_USER', 'kennetteCentral');

/** MySQL database password */
define('DB_PASSWORD', '143143@kennCK1994');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link http://goodherbwebmart.com/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'GK^H?zE3chvJYv${&JaUL ;[.>(gFXGXY&}q%W:Glta>^`|n=6(S?fIS?pR!%J.%');
define('SECURE_AUTH_KEY',  'X>Y!cjkAU Lfe[=`qHX<~0qGy:p.lC !e( N-q8ZX1dJW[4G(`xT>9P]/zLk)sS:');
define('LOGGED_IN_KEY',    '=AlMVd=!Z5R5bf1~_AJJ_^>WmrI (+k$?9!l-i./=k_/RE+,+7q;);)zqiPz+5<1');
define('NONCE_KEY',        '9mbhJy!~]Ial3Un%lY X?;+;Nee`dR3fqI^M5(2k!6XHwYqXCm+:e7=U|#)C5O^Q');
define('AUTH_SALT',        '>;@!aQwCIQtAm?WT+xlD/iZ<Bvk]9d4@m0Iw[b]2iFRS@[T@]E<Z,}WQEhhYZl)U');
define('SECURE_AUTH_SALT', '6w1vexzr ?xU(e;QH1-FVq/_]*S9$E:<Uw1Djv|:J(M>^0hfU$)!nw`&sRcZf R[');
define('LOGGED_IN_SALT',   'nnf.:$PTn0F7oYvt]olf$dgwt!8Vx5s~?WWO^jY1,:bgapV0UbU[oCGq-GBXhRa%');
define('NONCE_SALT',       '7CH5V/PbuhyJN2{0R-y^HpsY^.!,_z7[9Q}Gs#mML[OQivTYK/fNA6>NrwX,/8#s');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'gc_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link http://goodherbwebmart.com/
 */
define('WP_DEBUG', true);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
