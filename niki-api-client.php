<?php
/*
Plugin Name: Niki API Client
Plugin URI: http://niki.nl
Description: De Niki API Client vormt een interface naar de Niki database API die makkelijk in een WordPress site gebruikt kan worden, bijvoorbeeld m.b.v. een template.
Version: 0.2.3
Author: Fundament All Media
Author URI: http://www.fundament.nl
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// prevent direct access to this file
defined( 'ABSPATH' ) or die( 'Nothing to see here.' );

require_once("classes/debug.php");
require_once("classes/class-niki_plugin_base.php");

/**
 * Extension of the plugin base class with site specific implementations.
 *
 */
class Niki_Plugin extends Niki_Plugin_Base {

	/**
	 * Create new instance.
	 *
	 * @param string $plugin_filename  The base plugin file.
	 * @param integer $log_level  Debug log level. Default -1.
	 */
	public function __construct( $plugin_filename, $log_level = -1 ) {
		parent::__construct( $plugin_filename, $log_level );

		if ( !is_admin() ) {
			$this->log( ' construction: init hooks for non-admin', 1 );
			add_action( 'init', array( 'Niki_Plugin', 'add_rewrite_rules' ) );
			add_filter( 'query_vars', array( &$this, 'add_query_vars' ) );
			add_action( 'parse_query', array( &$this, 'check_niki_calls' ) );
		}
		$this->log( 'constructor ready', 1 );
	}

	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'Unauthorized', 401 );
		}

		self::add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Initialize plugin rewrite rules.
	 *
	 * This is the implementation of the abstract method in the base class (Niki_Plugin_Base).
	 *
	 * Define all required rewrite rules here. This is typically done by the following code (for each rule):
	 *
	 *     add_rewrite_rule( 'REG EXP', 'REWRITE', 'PRIORITY' );
	 *
	 * Example values:
	 * - REGEXP: '^aanbod/(.+)/?'.
	 * - REWRITE: 'index.php?niki-project_id=$matches[1]'
	 * - PRIORITY: 'top'
	 *
	 * See also the WordPress documentation on the subject: http://codex.wordpress.org/Rewrite_API/add_rewrite_rule
	 *
	 * Note: the order of the rules is important. Rules are parsed until one matches. The top most rule is tried first.
	 *
	 * Flushing of the rewrite rules is performed upon activating the plugin or by visiting the 'permalinks' admin page.
	 * Note: before the rewrite rules are flushed, they do NOT take effect!
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^niki-image/([0-9]+)/([a-zA-Z]+)/?', 'index.php?niki-call=images/$matches[1]/$matches[2]', 'top' );
		add_rewrite_rule( '^niki-file/([0-9]+)/?', 'index.php?niki-call=files/$matches[1]', 'top' );
		add_rewrite_rule( '^niki/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?', 'index.php?niki-page=$matches[1]&niki-var1=$matches[2]&niki-var2=$matches[3]&niki-var3=$matches[4]', 'top' );
		add_rewrite_rule( '^niki/([^/]+)/([^/]+)/([^/]+)/?', 'index.php?niki-page=$matches[1]&niki-var1=$matches[2]&niki-var2=$matches[3]', 'top' );
		add_rewrite_rule( '^niki/([^/]+)/([^/]+)/?', 'index.php?niki-page=$matches[1]&niki-var1=$matches[2]', 'top' );
		add_rewrite_rule( '^niki/([^/]+)/?', 'index.php?niki-page=$matches[1]', 'top' );
	}

	/**
	 * Deactivate the plugin.
	 *
	 * This will simply turn off the plugin, but it will still be installed.
	 * This means that all files remain and also the WordPress Options API items will remain.
	 *
	 * To fully remove the plugin, call the uninstall method.
	 */
	public static function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			// not allowed
			wp_die( 'Unauthorized', 401 );
		}

		flush_rewrite_rules();
	}

	/**
	 * Uninstall the plugin.
	 *
	 * Currently, only the parent function is called. This function is therefore not strictly necessary, but still implemented as an example.
	 * The parent function will remove all WordPress Options API items that were created by this plugin.
	 */
	public static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			// not allowed
			wp_die( 'Unauthorized', 401 );
		}

		parent::uninstall();
	}

	/**
	 * Filter hook for adding variables to the public query variables available to WP_Query.
	 *
	 * This method is called by WordPress internally if it is properly hooked, e.g. to the 'query_vars' filter.
	 *
	 * The Niki database API provides several resources for various kinds of information.
	 * All this information might be shown by the site and the site should be able to handle various requests for more specific information.
	 * This method adds various possible query variables to the list of "public query variables" of WordPress.
	 * Obviously, not all variable have to be used at the same time.
	 * All these variables can also be used with rewrite rules (@see add_rewrite_rules()).
	 *
	 * Some query variables are already added by the base class 'Niki_Plugin_Base':
	 *
	 * - niki-page   This var can be used in the template file to load (include) a certain page.
	 *
	 * Note: it is advised that all variables have a prefix 'niki-' to avoid collision with other plugins.
	 *
	 * @param array $vars  The current array of public query vars.
	 * @return array  The The public query vars with added vars.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = "niki-call";
		$this->log( "query var 'niki-call' added" );
		return $vars;
	}

	public function check_niki_calls() {
		$niki_call = get_query_var( "niki-call", false );
		if ( false !== $niki_call ) {
			$this->log( "niki-call '$niki_call' detected" );
			// only allow files and images calls
			$niki_call = explode( "/", trim( $niki_call, "/" ) );
			$resource = ( isset( $niki_call[ 0 ] ) ? $niki_call[ 0 ] : false);
			$id = ( isset( $niki_call[ 1 ] ) ? $niki_call[ 1 ] : false);
			$size = ( isset( $niki_call[ 2 ] ) ? $niki_call[ 2 ] : false);
			$niki_call = false;
			switch ( $resource ) {
				case "images":
					if ( ( false !== $id ) && ( false !== $size ) ) {
						$niki_call = "/images/$id/$size";
					}
					break;
				case "files":
					if ( false !== $id ) {
						$niki_call = "/files/$id";
					}
					break;
			}
			if ( false !== $niki_call ) {
				$this->log( "resource set, calling API resource '$niki_call'" );
				$result = $this->get_niki_resource( $niki_call, array() );
				if ( false !== $result ) {
					$this->log( "valid result, echoing directly and exiting.", 1 );
					$header = wp_remote_retrieve_headers( $result );
					$body = wp_remote_retrieve_body( $result );
					foreach ( $header as $key => $value ) {
						header( "$key: $value");
					}
					echo ( $body );
					exit();
				} else {
					$this->log( "invalid result (false)", 1 );
				}
			}
		}
		$this->log( "check niki calls ready, appearantly, no direct niki call was returned, proceeding normally.", 1 );
	}
}

$log_level = 1;
if ( isset( $_SERVER ['REQUEST_URI'] ) ) {
	if ( false !== strpos( $_SERVER ['REQUEST_URI'], "/niki-image/" ) ) {
		niki_log( "logging for Niki_Plugin disabled for image requests." );
		$log_level = -1;
	}
	if ( false !== strpos( $_SERVER ['REQUEST_URI'], "/niki-file/" ) ) {
		niki_log( "logging for Niki_Plugin disabled for file requests." );
		$log_level = -1;
	}
}

$niki = new Niki_Plugin( __FILE__, $log_level );
