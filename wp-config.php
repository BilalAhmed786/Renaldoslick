<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'reanaldobelle' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '<}K<MT+iy%Csl2WAo!#m(xmi*nj?&r@iut!=%t,/Q&>f4xu&A,b,UU|(W/_fA[|s' );
define( 'SECURE_AUTH_KEY',  'ruo+16od9JjGXE{b=Fb2nQN##t-a?QV%>c75S)@.sQNmZNKzX~duxuZ`.=a9bJ2a' );
define( 'LOGGED_IN_KEY',    'jVd^CVZfJFJ]3B;D,.Cu)_JX9(KiqNp?7+ w)IO2k]-mO4`Ww/$5~I>:$o3dJR;X' );
define( 'NONCE_KEY',        '&0>5x%*i?L1B-Ucb+]=[_twE%t=I;#3 ~KYCbXK&3WFcRcUMp)Jau6u F6HB$O_|' );
define( 'AUTH_SALT',        ']M+M0/+tV%c3}a:vh|fH@to;<o7=hlAfs^ttKif!p|yL(>]QjIzh8+CcXup ,0OM' );
define( 'SECURE_AUTH_SALT', '[eBdxpv_4Ynkul34<Z:`_s:jfYl<`@SA*`U!+A^] IlH1_vsTYS:m5IVo{lQ]ut:' );
define( 'LOGGED_IN_SALT',   '!8fQc4]VY5_4Lf*=vzgHsS%4dmg|vYe8M;hI:K;E8-:h`7H>+;4,a &k5T7*(pJW' );
define( 'NONCE_SALT',       'au^*wfoz[7|:IsuNjtAQb=YRBtIe<@iexm^CbTzH[ub5aF.Vxw8zo+Ia18[R{on|' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
