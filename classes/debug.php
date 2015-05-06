<?php
/*
 * file: classes/debug.php
 * created: 2015-02-05, mk
 * description: implement a basic logging mechanism, making use of the WordPress DEBUG style.
 *
 * Usage:
 *
 * Step 1: change the WordPress configuration:
 *     in /wp_config.php, change the line:
 *
 *         define('WP_DEBUG', false);
 *
 *     into:
 *
 *         define('WP_DEBUG', true);
 *
 *     and add:
 *
 *         // Tells WordPress to log everything to the /wp-content/debug.log file
 *         define('WP_DEBUG_LOG', true);
 *
 *         // Doesn't force the PHP 'display_errors' variable to be on
 *         define('WP_DEBUG_DISPLAY', false);
 *
 *     to enable debugging. Now, all debug messages will be written to the file:
 *     /wp-content/debug.log
 *
 *
 * Step 2: write messages
 *     whereever a debugging message is needed, use the code
 *
 *         niki_log( "my message" );
 *
 *     It is also possible to log an array or object. Simply pass it to the niki_log function.
 *
 *
 * Step 3: after developing
 *     After developing is done, the actions of Step 1 can be undone to disable debugging.
 */

/*
 * The plugin makes use of the debug_backtrace() PHP Core function to determine where a log message came from.
 * This function has changed since PHP version 5.3.6, accepting different parameters.
 */
if ( !defined( 'NIKI_NEW_BACKTRACE' ) ) {
	if ( version_compare(phpversion(), '5.3.6', '>=') ) {
		define( 'NIKI_NEW_BACKTRACE', true );
	} else {
		define( 'NIKI_NEW_BACKTRACE', false );
	}
}

/*
 * Define the niki_log function. If no debugging is enabled, the function will do nothing.
 */
if ( !function_exists( "niki_log" ) ) {
	if ( WP_DEBUG ) {
		/**
		 * Debug log the given message.
		 *
		 * The message may also be an array or an object.
		 * In that case, the PHP Core function print_r is used to convert to string.
		 *
		 * @param mixed $msg  The message (string) or array or object to log.
		 */
		function niki_log($msg) {
			if ( is_array( $msg ) || is_object( $msg ) ) {
				error_log( str_replace( PHP_EOL, "<br />" . PHP_EOL, print_r( $msg, true ) ) );
			} else {
				error_log( $msg );
			}
		}
	} else {
		/**
		 * Debug log the given message.
		 *
		 * The message may also be an array or an object.
		 * In that case, the PHP Core function print_r is used to convert to string.
		 *
		 * @param mixed $msg  The message (string) or array or object to log.
		 */
		function niki_log($msg) {
			return;
		}
	}
}

/*
 * Log the start of the request (only once).
 */
if ( !defined("niki_log_new_request") ) {
	define( "niki_log_new_request", true );
	niki_log( str_repeat( "&blk12;", 5 ) . "NEW REQUEST (" . date("Y-m-d H:i:s") . ")" . str_repeat( "&blk12;", 5 ) );
	niki_log( "request uri: '{$_SERVER ['REQUEST_URI']}'" );
}
