<?php
/**
 * Niki API plugin
 *
 * file: classes/class-niki_plugin.php
 * created: 2015-02-05, mk
 *
 * This is a base WordPress plugin class. It should be extended to create a plugin (see below).
 *
 * The plugin creates two administration pages:
 *
 * Niki Server
 * -----------
 *
 * On this page, the Niki OAuth URL and Niki API URL can be specified.
 * The chosen URLs are stored in the database via the WordPress Options API.
 *
 * If the proper URLs are stored, an OAuth Access Token can be negotiated (from the OAuth URL).
 * For this, the class Niki_API implements the OAuth protocol (version 1.0, as used by the Niki API Provider).
 *
 *
 * Niki Projecten
 * --------------
 *
 * On this page, an overview is presented of all Niki Projects that are accessible with the current access token.
 * Projects can be marked for access via the front-end (public side of the WordPress site) and also a default project can be specified.
 *
 * The class partly implements the Plugin_Activation interface, also defined in this file.
 * This interface prescribes functions for plugin handling (activation, deactivation and uninstalling the plugin) and for adding rewrite rules.
 * The three functions for plugin handling have to be static (per the WordPress documentation).
 * Because PHP does not allow static abstract classes, this interface was created to enforce the
 *
 */

/**
 * API protocol handler for Niki database.
 *
 * @author mk
 *
 * This handler class is specifically written for the Niki database provider at either https://auth.niki.nl/
 * (the normal provider) or http://auth.acc.niki.nl/ (for testing). The implemented OAuth protocol is 1.0.
 */

require_once ( ABSPATH . 'wp-admin/includes/template.php' );
require_once ( 'class-niki_api.php' );


interface Plugin_Activation {
	public static function activate();
	public static function deactivate();
	public static function uninstall();
	public static function add_rewrite_rules();
}

/**
 * WordPress Plugin base class.
 *
 * @author mk
 *
 * This plugin is specifically written to provide access to the Niki database at https://api.niki.nl.
 * See also:
 * https://api.niki.nl/apidocs
 * http://www.stichtinglnp.nl/ (Dutch)
 * http://www.stichtinglnp.nl/downloads/documentatie-laatste-release/ (Dutch)
 *
 * The plugin also works (equally) for the acceptance testing provider at http://api.acc.niki.nl.
 *
 *
 */
abstract class Niki_Plugin_Base implements Plugin_Activation {

	/**
	 * Control at what level debug output is enabled.
	 *
	 * The debugging output makes use of the niki_log function in @see classes/debug.php
	 * In the class methods, the log() function is used to gather logging messages.
	 * This method expects the message and a log level (default 0).
	 * Only if the given log level is at least as high as the $debug parameter, the message is passed through to the niki_log function (see /classes/debug.php).
	 *
	 * If this parameter is set to < 0, no logging messages are passed.
	 *
	 * The value can be set by passing it to the class constructor.
	 *
	 * @see log()
	 *
	 * @access protected
	 * @var integer $debug  Determines at what level debug messages are generated. Default -1 (no logging).
	 */
	protected $debug = -1;

	/**
	 * Directory path of the current plugin.
	 *
	 * The value is set in the constructor.
	 * An example value could be '/home/user/var/www/wordpress/wp-content/plugins/niki-api-client/'.
	 *
	 * @var string $plugin_path  The plugin directory path.
	 */
	protected $plugin_path;

	/**
	 * Absolute URL to the plugin base directory.
	 *
	 * This value is used to construct direct links to the plugin, e.g. as callback URL for the OAuth provider.
	 * The value is set in the constructor.
	 *
	 * @var string $plugin_url  Uniform Resource Locator base for the current plugin.
	 */
	private $plugin_url;

	/**
	 * The registered API token for the Niki API resources.
	 *
	 * Obtain the token value via the function get_api_token().
	 * If no token is registered, the value of the token is boolean false.
	 *
	 * @var mixed $api_token  String value of the registered token or boolean false if there is no token registered.
	 */
	private $api_token = false;

	/**
	 * Internal flag to check whether the value of the token has been requested from the database (via the WordPress Options API).
	 *
	 * This is used to cache the api token value.
	 * @see get_api_token()
	 *
	 * @var boolean $api_token_checked  Whether the API token has been requested from the database.
	 */
	private $api_token_checked = false;

	/**
	 * Instance of the Niki_API class for use during normal operation, i.e. at the front end.
	 *
	 * The instance is setup in the constructor, but only if not in admin mode.
	 * If no access token is available, the instance can not operate and this variable remains boolean false.
	 * Thus, before requesting any Niki data, check whether this variable is not (!==) false.
	 *
	 * @var Niki_API $niki_api  Instance of the Niki_API class, setup with proper access token, or boolean false.
	 */
	protected $niki_api = false;

	/**
	 * WordPress Options API identifier used for Niki Server settings.
	 *
	 * @var string OPTION_NAME_SERVER  Options API identifier used for Niki Server settings.
	 */
	const OPTION_NAME_SERVER = "niki-server";

	/**
	 * WordPress Options API identifier used for Niki Projects settings.
	 *
	 * The option will contain the serialized array which has the format:
	 *
	 *     array (
	 *         [link] => array (
	 *             [name] => 'the project name',
	 *             [active] => true | false
	 *         )
	 *     );
	 *
	 * Where [link] index is link value as returned from the Niki API resource '/projects/mine'.
	 * Each entry is an array containing the name (from the Niki API resource) and whether it is 'active' for the current site.
	 *
	 * @var string OPTION_NAME_PROJECTS  Options API identifier used for Niki Projects settings.
	 */
	const OPTION_NAME_PROJECTS = "niki-projects";

	/**
	 * WordPress Options API identifier used for Niki OAuth settings (access token information).
	 *
	 * @var string OPTION_NAME_OAUTH  Options API identifier used for Niki OAuth settings.
	 */
	const OPTION_NAME_OAUTH = "niki-access_token_information";

	/**
	 * Collection of all active projects.
	 *
	 * The field is an indexed array of active projects.
	 * Each project is represented as an array.
	 * The format of this field is:
	 *
	 *     $projects = array (
	 *         0 => array (
	 *             'name' => 'the project name',
	 *             'link' => 'the project link'
	 *         )
	 *     )
	 *
	 * The contents of this field are set in the constructor, but this field is only used in non-admin sessions.
	 *
	 * @access private
	 * @var array Collection of active projects.
	 */
	private $projects = array();

	/**
	 * Plugin constructor.
	 *
	 * The only parameter is the filename of the plugin, usually '__FILE__' from within the plugin base file.
	 *
	 * @param string $plugin_filename  The path to the plugin filename.
	 */
	public function __construct( $plugin_filename, $log_level = -1 ) {
		if ( is_int( $log_level ) ) {
			$this->debug = intval( $log_level );
		} else {
			$this->debug = -1;
		}

		$this->plugin_path = plugin_dir_path( $plugin_filename );
		$this->log( "plugin path: '" . $this->plugin_path . "'", 1 );

		$this->plugin_url = plugins_url( "", dirname( __FILE__ ) );
		$this->log( 'plugin url : ' . $this->plugin_url . "'", 1 );

		// Register the activation, deactivation and uninstall functions.
		// These function must be implemented in either this class or the extending class.
		// The uninstall function is implemented in this class.
		// If it should also be implemented in the extending class, also this parent function must be called.
		register_activation_hook( $plugin_filename, array( 'Niki_Plugin', 'activate' ) );
		register_deactivation_hook( $plugin_filename, array( 'Niki_Plugin', 'deactivate' ) );
		register_uninstall_hook( $plugin_filename, array( 'Niki_Plugin', 'uninstall' ) );

		// an active session is needed for storing some OAuth parameters.
		// make sure that a PHP session exists
		if ( '' === session_id() ) {
			session_start();
			$this->log( "session started", 1 );
		}

		if ( is_admin() ) {
			// for admin users: initialize the menus and init functions.
			$this->log( 'Niki_plugin construction: hooking admin_menu', 1 );
			add_action( 'admin_menu', array ( &$this, 'niki_admin_menu' ) );

			$this->log( 'Niki_plugin construction: hooking admin_init', 1 );
			add_action( 'admin_init', array ( &$this, 'niki_admin_init' ) );
		} 
		// Note that the Niki_API instance retrieves the access token from the database itself.
		if ( $this->get_api_token() !== false ) {
			$options = get_option( self::OPTION_NAME_SERVER, false );

			if ( false !== $options ) {
				$api_options = array(
						'api_url' => $options['api_url'],
						'client_id' => $_SERVER[ "SERVER_NAME" ],
						'debug' => min( $this->debug, 0 )
				);

				if ( WP_DEBUG && ( $this->debug > 0 ) ) {
					$this->log( "creating new Niki_API instance", 1 );
					foreach ( $api_options as $k => $v ) {
						$this->log( "API options [$k] => $v", 1 );
					}
				}
				$this->niki_api = new Niki_API( $api_options );
				$this->log( "Niki_API instance created" );

				$projects_options = get_option( self::OPTION_NAME_PROJECTS, array() );
				foreach ( $projects_options as $project ) {
					if ( $project ['active'] ) {
						$this->projects[] = array (
								'name' => $project ['name'],
								'link' => $project ['link']
						);
						$this->log( "Added active project (link='{$project ['link']}')" );
					}
				}
			} else {
				$this->log( "no server settings found!" );
			}
		}

		add_filter( 'query_vars', array( &$this, 'base_add_query_vars' ) );

		
		$this->log( 'construction ready', 1 );
	}

	/**
	 * Log a (debug) message.
	 *
	 * This method calls the @see niki_log() function (as defined in /classes/debug.php) for logging.
	 * The message provided is logged only if the given level is smaller than or equal to @see $debug.
	 *
	 * Both logging of string messages and array messages is supported.
	 * Arrays are split in their key/value pairs recursively before being sent to the niki_log function.
	 * If any value in the array should be an array itself, this array is parsed using the print_r php core function.
	 *
	 * @param mixed $msg  The message to log. If this is an array, the print_r function is used to log the array.
	 * @param integer level  Used to determine whether or not to log the message. Default 0 (first level messages only).
	 */
	protected function log ( $msg, $level = 0, $array_level = 0 ) {
		if ( $level > $this->debug ) {
			return;
		}

		switch ( $level ) {
			case 0: $level = 'INF'; break;
			case 1: $level = 'DEB'; break;
			default: $level = 'VER';
		}

		if ( NIKI_NEW_BACKTRACE ) {  // defined in classes/debug.php
			$options = DEBUG_BACKTRACE_IGNORE_ARGS;
		} else {
			$options = false;
		}
		$backtrace = debug_backtrace( $options );
		$caller = "";
		$file = "";
		if ( count( $backtrace ) > 1 ) {
			// extract the calling class and method from the second backtrace entry
			if ( isset( $backtrace [1] ['class'] ) ) {
				$caller = $backtrace [1] ['class'] . $backtrace [1] ['type'] . $backtrace [1] ['function'] . ": ";
			} else {
				$caller = $backtrace [1] ['function'] . ": ";
			}
			// extract the calling file and line number from the first backtrace entry
			// get it relative to the WordPress plugins dir
			$file = plugin_basename( isset( $backtrace [0] ['file'] ) ? $backtrace [0] ['file'] : __FILE__ );
			$file = " in $file:" . ( isset( $backtrace [0] ['line'] ) ? $backtrace [0] ['line'] : "-" );
		}

		if ( is_array( $msg ) ) {
			if ( $array_level === 0 ) {
				niki_log( "[$level]{$caller}Array$file" );
			}
			foreach ( $msg as $k => $v ) {
				if ( is_array( $v ) ) {
					niki_log( "[ ↳ ]" . str_repeat( " ↳ ", $array_level + 1 ) . "[$k] =>" );
					$this->log( $v, $level, $array_level + 1 );
				} else {
					niki_log( "[ ↳ ]" . str_repeat( " ↳ ", $array_level + 1 ) . "[$k] => $v" );
				}
			}
		} else {
			if ( $array_level > 0 ) {
				niki_log( "[ ↳ ]" . str_repeat( " ↳ ", $array_level + 1 ) . "$msg" );
			} else {
				niki_log( "[$level]$caller$msg$file" );
			}
		}
	}

	/**
	 * Get the value of the plugin_path property.
	 *
	 * @return string  The plugin base path (e.g. '/home/user/var/www/wordpress/wp-content/plugins/niki-api-client/').
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Initialize dedicated admin sections for Niki plugin settings.
	 */
	public function niki_admin_init() {
		$this->log( 'niki_admin_init', 1 );

		register_setting( 'niki-server', self::OPTION_NAME_SERVER );
		register_setting( 'niki-projects', self::OPTION_NAME_PROJECTS );

		// Section for server settings, e.g. oauth url.
		add_settings_section(
			'section-niki-server',                        // id
			'Niki Server',                                // title
			array ( &$this, 'section_server' ),           // callback
			'niki_server'                                 // page
		);

		add_settings_field (
			'niki-server-oauth-url',                      // id,
			'Niki OAuth URL',                             // title
			array ( &$this, 'setting_server_oauth_url' ), // callback
			'niki_server',                                // page
			'section-niki-server'                         // section
		);

		add_settings_field (
			'niki-server-api-url',                        // id,
			'Niki API URL',                               // title
			array ( &$this, 'setting_server_api_url' ),   // callback
			'niki_server',                                // page
			'section-niki-server'                         // section
		);

		if( isset( $_GET ["request_oauth_token"] ) ) {
			$this->log( "GET parameter 'request_oauth_token' found" );
			$this->request_oauth_token();
		}

		if( isset( $_GET ["delete_oauth_token"] ) ) {
			$this->log( "GET parameter 'delete_oauth_token' found" );
			$this->delete_oauth_token();
		}

		if( isset($_POST[ "action" ]) && $_POST[ "action" ] == 'niki-projects-update' ) {
			if ( ! current_user_can( 'manage_options' )) {
				wp_die('Geen toegang.');
			}
			// Debug log the posted values
			if ( WP_DEBUG && ( $this->debug > 1 ) ) {
				foreach ( $_POST as $key => $value ) {
					if ( "niki-" === substr( $key, 0, 5 ) ) {
						$this->log( "post field [$key]: " );
						$this->log( $value );
					}
				}
			}

			$names = ( isset( $_POST ["niki-projects-name"] ) ? $_POST ["niki-projects-name"] : array() );
			$links = ( isset( $_POST ["niki-projects-link"] ) ? $_POST ["niki-projects-link"] : array() );
			$actives = ( isset( $_POST ["niki-projects-active"] ) ? $_POST ["niki-projects-active"] : array() );

			$options = array();
			foreach ( $links as $index => $link ) {
				$name = ( isset( $names [$index] ) ? $names [$index] : false );
				$active = isset( $actives [$index] );
				if ( ( false !== $name ) ) {
					$options [] = array(
							"name" => $name,
							"link" => $link,
							"active" => $active
					);
				} else {
					$this->log( "index [$index] not found in names." );
				}
			}

			update_option( self::OPTION_NAME_PROJECTS, $options );

			$url = admin_url( "/admin.php?page=niki_projects" );
			$this->log( "redirecting: $url" );
			header( "Location: $url" );
			exit();
		}

		$this->log( 'end of niki_admin_init' );
	}

	/**
	 * Create dedicated admin menu (and submenu) for Niki plugin.
	 */
	public function niki_admin_menu() {
		$this->log( 'niki_admin_menu', 1 );
		if ( current_user_can( 'manage_options' ) ) {
			add_menu_page(
				'Niki Server',                                 // page title
				'Niki Server',                                 // menu title
				'manage_options',                              // capability
				'niki_server',                                 // menu slug
				array ( &$this, 'admin_page_niki_server' ),    // callback function, defined in this class
				'',                                            // logo
				null                                           // position
			);

			add_submenu_page(
				'niki_server',                                 // parent slug
				'Niki Projecten',                              // page title,
				'Niki Projecten',                              // menu title
				'manage_options',                              // capability
				'niki_projects',                               // menu slug
				array ( &$this, 'admin_page_niki_projects' )   // callback function, defined in this class
			);
		}
		$this->log( 'end of niki_admin_menu', 1 );
	}

	/**
	 * Load the html snippet for the niki server admin page.
	 */
	public function admin_page_niki_server() {
		$this->log( 'admin_page_niki_server', 1 );
		if ( ! current_user_can( 'manage_options' )) {
			wp_die('Geen toegang.');
		}

		echo '<div class="wrap">', PHP_EOL;
		echo '<h2>Niki Server setup</h2>', PHP_EOL;
		echo '<form action="options.php" method="POST">', PHP_EOL;

		settings_fields( 'niki-server' );  // option group id used in register_setting (first argument)
		do_settings_sections( 'niki_server' );  // id used in add_menu_page
		submit_button();

		echo '</form>', PHP_EOL;
		echo '</div>', PHP_EOL;

		echo '<div class="wrap">';
		echo '<h3>OAuth Token</h3>';

		if ( $this->get_api_token() ) {
			echo "<p>Huidige Niki API token: {$this->get_api_token()}<br />\n";
			echo '<a href="' . admin_url( '/admin.php?page=niki_server&delete_oauth_token' ) . '">Verwijder huidige NIKI API token</a>.</p>';
		} else {
			echo 'Geen OAuth token beschikbaar. <a href="' . admin_url( '/admin.php?page=niki_server&request_oauth_token' ) . '">Vraag NIKI API token aan</a>.';
		}

		echo '</div>', PHP_EOL;

		$this->log( 'end of admin_page_niki_server', 1 );
	}

	/**
	 * Load the html snippet for the niki projects admin page.
	 */
	public function admin_page_niki_projects() {
		$this->log( 'admin_page_niki_projects', 1 );
		if ( ! current_user_can( 'manage_options' )) {
			wp_die('Geen toegang.');
		}

		echo '<div class="wrap">';
		if ( $this->get_api_token() ) {
			$this->log( "api token found" );

			$options = get_option( self::OPTION_NAME_SERVER );
			$api_url = $options['api_url'];
			$client_id = $_SERVER[ "SERVER_NAME" ] ;

			$options = array(
					'api_url' => $api_url,
					'client_id' => $client_id,
					'debug' => min( $this->debug, 1 )
			);
			if ( WP_DEBUG && ( $this->debug > 1 ) ) {
				$this->log( "creating new Niki_API instance" );
				foreach ( $options as $k => $v ) {
					$this->log( "options [$k] => $v", 2 );
				}
			}

			$niki_api = new Niki_API( $options );
			$this->log( "Niki_API instance created" );

			$resource = "/projects/mine";
			$parameters = array ();
			$options = array ();
			$result = false;
			$niki_api->call_API( $resource, $parameters, $result );

			$projects = get_option( self::OPTION_NAME_PROJECTS );
			// rebuild the array so that the project link is the index (the project link should always be unique!)
			$project_options = array ();
			if ( is_array( $projects ) ) {
				foreach ( $projects as $project ) {
					$project_options [$project ['link']] = array (
							'name' => $project ['name'],
							'active' => (boolean) $project ['active']
					);
				}
			}

			$this->log( "projects from Options:", 2 );
			$this->log( $project_options, 2 );

			// The projects in the Options table should always follow the project list from the resource.
			// Therefore, build a new array using the information from the resource and try to get the 'active' state from the Options.
			$projects = array();
			if ( is_array( $result ) ) {

				$this->log( "projects from resource:", 2 );
				$this->log( $result, 2 );

				foreach ( $result as $project ) {
					$projects[] = array(
							'name' => $project ['name'],
							'link' => $project ['link'],
							'active' => ( isset( $project_options [$project['link'] ] ) && ($project_options [$project['link'] ] ['active'] ) )
					);
				}

				$this->log( "projects prepared:", 2 );
				$this->log( $projects, 2 );

				echo '<div class="wrap">', PHP_EOL;
				echo '<h2>Niki projecten</h2>', PHP_EOL;

				echo '<form method="POST">', PHP_EOL;
				echo '<input type="hidden" name="action" value="niki-projects-update" />', PHP_EOL;

				echo '<table style="line-height:200%">', PHP_EOL;
				echo "<tr>", PHP_EOL;
				echo '<th style="text-align: left">Actief</th>', PHP_EOL;
				echo '<th style="text-align: left">Projectnaam</th>', PHP_EOL;
				echo '<th style="text-align: left">Link</th>', PHP_EOL;
				echo "</tr>", PHP_EOL;

				foreach ( $projects as $index => $project ) {
					echo '<input type="hidden" name="niki-projects-name[', $index, ']" value="' . $project['name'] . '" />', PHP_EOL;
					echo '<input type="hidden" name="niki-projects-link[', $index, ']" value="' . $project['link'] . '" />', PHP_EOL;
					echo "<tr>", PHP_EOL;
					echo "<td>", PHP_EOL;
					echo '<input type="checkbox" id="niki-projects-active-' . $index . '" name="niki-projects-active[', $index, ']"' . ($project['active'] ? ' checked' : '') . ' />', PHP_EOL;
					echo "</td>", PHP_EOL;
					echo '<td><label for="niki-projects-active-' . $index . '">', $project["name"], '</label></td>', PHP_EOL;
					echo '<td><label for="niki-projects-active-' . $index . '">', $project["link"], '</label></td>', PHP_EOL;
					echo "</tr>", PHP_EOL;
				}
				echo "</table>", PHP_EOL;

				echo '<input type="submit" value="Wijzigingen opslaan" />', PHP_EOL;

				echo '</form>', PHP_EOL;
				echo '</div>', PHP_EOL;
			} else {
				echo '<div class="wrap">', PHP_EOL;
				echo '<h2>Niki projecten</h2>', PHP_EOL;
				echo '<p>Kon de projecten niet laden!</p>', PHP_EOL;
				echo '</div>', PHP_EOL;
			}
		} else {
			$url = admin_url( "/admin.php?page=niki_server" );
			echo '<h3>Geen OAuth Token</h3>';
			echo 'Er is geen OAuth token beschikbaar. Vraag deze aan via de pagina <a href="' . $url . '">Niki Server</a>.';
			echo '</div>', PHP_EOL;
		}

		$this->log( 'end of admin_page_niki_projects', 1 );
	}

	/**
	 * Render the option field for the OAuth url setting.
	 */
	public function setting_server_oauth_url() {
		$options = get_option( self::OPTION_NAME_SERVER );  // second argument of register_setting
		echo '<input type="text" id="niki-server" name="niki-server[oauth_url]" value="' . $options['oauth_url'] . '" />', PHP_EOL;
	}

	/**
	 * Render the option field for the API url setting.
	 */
	public function setting_server_api_url() {
		$options = get_option( self::OPTION_NAME_SERVER );  // second argument of register_setting
		echo '<input type="text" id="niki-server" name="niki-server[api_url]" value="' . $options['api_url'] . '" />', PHP_EOL;
	}

	/**
	 * Callback for the Niki Server settings section.
	 *
	 * This callback normally displays the section description.
	 */
	public function section_server() {
		echo "<p>Voer hier de URL's in naar de Niki OAuth provider en de API provider.</p>";
	}

	/**
	 * Get the known OAuth API token.
	 *
	 * This method checks the WordPress Options API for a known API token and returns it.
	 * If no API token is present, boolean false is returned.
	 *
	 * The result is cached internally.
	 * To override the cached value, provide pass boolean true as parameter.
	 *
	 * @param boolean $recheck  If set to true, override the internal caching.
	 *
	 * @return mixed  The API token (string) or boolean false if it can not be found.
	 */
	public function get_api_token( $recheck = false ) {
		if ( $recheck ) {
			$this->api_token_checked = false;
		}
		if ( ! $this->api_token_checked ) {
			$this->log( "getting api token from WP Options [" . self::OPTION_NAME_OAUTH . "]" );
			$access_token_information = get_option( self::OPTION_NAME_OAUTH );
			if ( is_array( $access_token_information ) && array_key_exists( "value", $access_token_information ) ) {
				$this->api_token = $access_token_information[ "value" ];
			} else {
				$this->api_token = false;
			}
			$this->api_token_checked = true;
		}
		$this->log( "returning existing api token: '{$this->api_token}'", 1 );
		return $this->api_token;
	}

	/**
	 * Delete any current OAuth token, also from the database.
	 */
	public function delete_oauth_token() {
		$this->log( "delete_oauth_token()" );
		delete_option( self::OPTION_NAME_OAUTH );
		foreach ( $_SESSION as $k => $v ) {
			if ( substr( $k, 0, 4 ) === "niki" ) {
				unset ( $_SESSION [$k]);
			}
		}
		session_write_close();
		$url = admin_url( '/admin.php?page=niki_server' );
		$this->log( "oauth access token information deleted, redirect to '$url'" );
		header( "location: $url" );
	}

	/**
	 * Negotiate a new OAuth token.
	 *
	 * This method can be called by a callback script for the OAuth provider.
	 */
	public function request_oauth_token() {
		$this->log( "request_oauth_token()" );

		$options = get_option( self::OPTION_NAME_SERVER );
		$oauth_provider_url = $options['oauth_url'];

		$client_id = $_SERVER[ "SERVER_NAME" ];

		$options = array(
				'request_token_url' => $oauth_provider_url . '/oauth/requestToken',
				'authorization_url' => $oauth_provider_url . '/oauth/authorization',
				'access_token_url'  => $oauth_provider_url . '/oauth/accessToken',
				'redirect_url'      => admin_url( '/admin.php?page=niki_server&request_oauth_token' ),
				'client_id'         => $client_id,
				'debug'             => min( $this->debug, 1 )
		);
		if ( WP_DEBUG && ( $this->debug > 0 ) ) {
			foreach ( $options as $k => $v ) {
				$this->log( "options [$k] => $v", 1 );
			}
		}

		// In state=1 the next request should include an oauth_token.
		// If it doesn't go back to 0
		$session_oauth_state = 0;
		if ( isset( $_SESSION[ 'niki_oauth_state' ] ) ) {
			$session_oauth_state = intval( $_SESSION[ 'niki_oauth_state' ], 10 );
			$this->log( "oauth state in session: $session_oauth_state" );
			if ( ! isset($_GET['oauth_token']) ) {
				$session_oauth_state = 0;
				$this->log( "no oauth token, setting oauth state = 0" );
			}
			$_SESSION[ 'niki_oauth_state' ] = $session_oauth_state;
		}

		try {
			$this->log( "creating Niki_API instance" );
			$niki_api = new Niki_API( $options );

			$this->log( "session state = $session_oauth_state" );

			if ( $session_oauth_state === 0 ) {
				if ( isset( $_SESSION ["niki_oauth_secret"] ) ) {
					unset( $_SESSION ["niki_oauth_secret"] );
				}
			}

			$this->log( "calling oauth process" );
			if ( ! $niki_api->process_oauth() ) {
				$this->log( "error during oauth process, exiting" );
				echo "There were errors during the oauth process (state = $session_oauth_state).<br />";
				exit;
			}
			if ( WP_DEBUG && ( $this->debug > 2 ) ) {
				$this->log( "oauth process complete. token information:" );
				foreach ( $niki_api->get_access_token_information_field_names() as $f ) {
					$this->log( "- [$f] => " . $niki_api->get_access_token_information( $f ) );
				}
			}

			if ( $session_oauth_state === 1) {
				if ( isset( $_SESSION ["niki_oauth_state"] ) ) {
					unset ( $_SESSION ["niki_oauth_state"] );
				}
				if ( isset( $_SESSION ["niki_oauth_secret"] ) ) {
					unset( $_SESSION ["niki_oauth_secret"] );
				}
				session_write_close();
				$this->log( "session closed" );
			}

			$url = admin_url( "/admin.php?page=niki_server" );
			$this->log( "redirecting to '$url'" );
			header( 'HTTP/1.1 302 Found' );
			header('location: ' . $url);
			exit;
		} catch ( Exception $e ) {
			print_r( $e );
		}
	}

	public static function uninstall() {
		niki_log( get_class() . ": uninstall" );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'Unauthorized', 401 );
		}

		// remove Options:
		niki_log( get_class() . ": uninstall: removing Options '" . self::OPTION_NAME_PROJECTS . "', '" . self::OPTION_NAME_SERVER . "' and '" . self::OPTION_NAME_OAUTH . "'" );
		delete_option( self::OPTION_NAME_PROJECTS );
		delete_option( self::OPTION_NAME_SERVER );
		delete_option( self::OPTION_NAME_OAUTH );
		niki_log( get_class() . ": uninstall ready." );
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
	 * The current list of added query variables is:
	 *
	 * - niki-page (indicates which page to show)
	 * - niki-var1 (general purpose 1)
	 * - niki-var2 (general purpose 2)
	 * - niki-var3 (general purpose 3)
	 *
	 * Note: all variables have a prefix 'niki-' to avoid collision with other plugins.
	 *
	 * @param array $vars  The current array of public query vars.
	 *
	 * @return array  The The public query vars with added vars.
	 */
	public function base_add_query_vars( $vars ) {
		$vars[] = "niki-page";
		$vars[] = "niki-var1";
		$vars[] = "niki-var2";
		$vars[] = "niki-var3";
		$this->log( "query vars 'niki-page' and 'niki-var1/2/3' added.", 2 );
		return $vars;
	}

	/**
	 * Place a request at the Niki API provider and return the data.
	 *
	 * The data returned depends on the resource specified.
	 * Most Niki API resources return their data as a json object.
	 * For these resources, the data is returned as an array, using the key/value-pairs from the json object.
	 *
	 * The /about/version resource returns data as text/plain.
	 * For this resource, a string is returned, containing the raw data string from the provider.
	 *
	 * The /files/ and /images/ resources return binary data, which is returned verbatim.
	 *
	 * The resource should be provided without any query string (i.e. everything after the question mark, including the question mark itself.
	 *
	 * Any query parameters can be specified in the $parameters array.
	 * It is not necessary to include the access token in this array.
	 * The parameters are added as query string to the url.
	 * If more than one value should be provided to a parameter with the same name (key), this parameter value should be an indexed array.
	 * An example parameter array is:
	 *
	 *     $parameters = array(
	 *         'addSummary' => true,
	 *         'status' => array( 'In verkoop', 'Verkocht' )
	 *     );
	 *
	 * If there is no data, an empty array or string is returned.
	 * If the Niki API object is not set, this method returns boolean false.
	 *
	 * @param string $resource    The resource to request, e.g. /about/version.
	 *                            Do not add any query parameters to this string.
	 *                            The resource should also contain any 'path parameters', e.g. '/projects/325/2DVBVDEMO_FOR_SALE'.
	 * @param array  $parameters  All needed query parameters as key/value-pairs.
	 *                            If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return mixed  Data from the Niki API provider, or boolean false.
	 */
	public function get_niki_resource( $resource, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( ! is_array( $parameters ) ) {
			$parameters = array();
		}

		$this->log( "resource: '$resource' " );

		$this->niki_api->call_API( $resource, $parameters, $result );
		return $result;
	}

	/**
	 * Get the current version of the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/about-version.html
	 *
	 * @return string  The current version.
	 */
	public function get_about_version() {
		if ( false === $this->niki_api ) {
			return false;
		}

		return $this->get_niki_resource( '/about/version', array() );
	}

	/**
	 * Get developer information from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-developer-{developer_id}.html
	 *
	 * The $developer parameter can either be a long (integer) or a string.
	 * If it is a long (integer), it is assumed to be the developer id.
	 * If it is a string, it is assumed to be the developer link, e.g. '/projects/developer/325'.
	 *
	 * The result is an assosiative array with developer information.
	 * If no developer information was found, boolean false is returned.
	 *
	 * @param mixed  The developer id or the developer link.
	 *
	 * @return array  Information about the developer or boolean false if not found.
	 */
	public function get_developer( $developer ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		$link = "/projects/developer/$developer";
		if ( is_string( $developer ) ) {
			$link = $developer;
		}
		return $this->get_niki_resource( $link, array() );
	}

	/**
	 * Get information on developers from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-developers.html
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-developers.html
	 *
	 * If the $project_link parameter is not set (or set to boolean false, the default value), a list is returned of all developers in the database.
	 * This is retrieved using the resource /projects/developers.
	 *
	 * If a project link is provided as the $project_link parameter, e.g. '/projects/325/2DVBVDEMO_FOR_SALE', only the developers for that project are listed.
	 * This is retrieved using the resource /projects/{developer_id}/{project_id}/developers.
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' )
	 *     )
	 *
	 * The result is an assosiative array with developers information or boolean false if there was a problem finding the information.
	 * Note that the list of all developers (not for a specific project) contains only names and links,
	 * whereas the list of developers of a specific project contains more detailed information on the developers found.
	 *
	 * @param mixed $project_link  Boolean false for all developers or a string with a project link.
	 * @param array $parameters    All needed query parameters as key/value-pairs.
	 *                             If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  A list of developers.
	 */
	public function get_developers( $project_link = false, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( is_string( $project_link ) ) {
			$link = $project_link . "/developers";
		} else {
			$link = "/projects/developers";
		}
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}

		return $this->get_niki_resource( $link, $parameters );
	}

	/**
	 * Get an overview of all active projects for this site.
	 *
	 * The returned value is an array of the following format:
	 *
	 *     $result = array (
	 *         0 => array (
	 *             'name' => 'project 1 name',
	 *             'link' => 'project 1 link'
	 *         ),
	 *         ...
	 *         (N-1) => array (
	 *             ...
	 *         )
	 *     )
	 *
	 * The result is an indexed array of projects.
	 * Each project is defined as an associative array with the projects name and link.
	 * The name is a string containing the project name as defined in the Niki Database.
	 * The link is a formatted string of the form: '/projects/{developer id}/{project id}'.
	 *
	 * The result is NOT fetched from the Niki database directly.
	 * Instead, the list of projects is maintained in the WordPress database (on the website server).
	 *
	 * The site admin is able to set or unset the 'active' state for each available project.
	 *
	 * If there are no active projects for this site, the array returned will be empty.
	 *
	 * @return array  The list of projects.
	 */
	public function get_projects() {
		return $this->projects;
	}

	/**
	 * Get an array of all available statuses.
	 *
	 * The result is an indexed array of all available statuses.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-statusList.html
	 *
	 * @return array  The available statuses.
	 */
	public function get_status_list() {
		if ( false === $this->niki_api ) {
			return false;
		}

		return $this->get_niki_resource( '/projects/statusList', array() );
	}

	/**
	 * Get information on a specific project from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}.html
	 *
	 * The $project_link parameter should be the project link, e.g. '/projects/325/2DVBVDEMO_FOR_SALE'.
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' ),
	 *         'addSummary' => 'true'
	 *     )
	 *
	 * The result is an associative array with project information.
	 * If no information was found, boolean false is returned.
	 *
	 * @param string $project_link  The project link.
	 * @param array  $parameters    All needed query parameters as key/value-pairs.
	 *                              If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  Information about the project (or false).
	 */
	public function get_project( $project_link, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $project_link ) ) {
			return false;
		}
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}
		return $this->get_niki_resource( $project_link, $parameters );
	}

	/**
	 * Get a list of brokers for a certain project from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-brokers.html
	 *
	 * The $project_link parameter should be the project link, e.g. '/projects/325/2DVBVDEMO_FOR_SALE'.
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' )
	 *     )
	 *
	 * The result is an associative array with the list of brokers.
	 * If no information was found, boolean false is returned.
	 *
	 * @param string $project_link  The project link.
	 * @param array  $parameters    All needed query parameters as key/value-pairs.
	 *                              If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  The broker list (or false).
	 */
	public function get_brokers( $project_link, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $project_link ) ) {
			return false;
		}
		$link = $project_link . "/brokers";
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}
		return $this->get_niki_resource( $link, $parameters );
	}

	/**
	 * Get a list of involved parties for a certain project from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-involvedparties.html
	 *
	 * The $project_link parameter should be the project link, e.g. '/projects/325/2DVBVDEMO_FOR_SALE'.
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' )
	 *     )
	 *
	 * The result is an associative array with the list of involved parties.
	 * If no information was found, boolean false is returned.
	 *
	 * @param string $project_link  The project link.
	 * @param array  $parameters    All needed query parameters as key/value-pairs.
	 *                              If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  The list of involved parties (or false).
	 */
	public function get_involvedparties( $project_link, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $project_link ) ) {
			return false;
		}
		$link = $project_link . "/involvedparties";
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}
		return $this->get_niki_resource( $link, $parameters );
	}

	/**
	 * Get a list of housetypes for a certain project from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-housetypes.html
	 *
	 * The $project_link parameter should be the project link, e.g. '/projects/325/2DVBVDEMO_FOR_SALE'.
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' )
	 *     )
	 *
	 * The result is an associative array with the list of housetypes.
	 * If no information was found, boolean false is returned.
	 *
	 * @param string $project_link  The project link.
	 * @param array  $parameters    All needed query parameters as key/value-pairs.
	 *                              If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  The list of housetypes (or false).
	 */
	public function get_housetypes( $project_link, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $project_link ) ) {
			return false;
		}
		$link = $project_link . "/housetypes";
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}
		return $this->get_niki_resource( $link, $parameters );
	}

	/**
	 * Get information on a specific housetype of a certain project from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-{housetype_id}.html
	 *
	 * The $link parameter should be the API call link, e.g. '/projects/325/2DVBV41777/2DVBV1776'.
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' )
	 *     )
	 *
	 * The result is an associative array with the housetype information.
	 * If no information was found, boolean false is returned.
	 *
	 * @param string $link        The housetype link.
	 * @param array  $parameters  All needed query parameters as key/value-pairs.
	 *                            If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  The housetype information (or false).
	 */
	public function get_housetype( $link, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $link ) ) {
			return false;
		}
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}
		return $this->get_niki_resource( $link, $parameters );
	}

	/**
	 * Get all typeModelCombis for a specific project from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-interest-typeModelCombis.html
	 *
	 * The first parameter, $project_link, should be a valid project link, e.g. '/projects/325/2DVBV41777'.
	 * With the given project link, the /interest/typeModelCombis resource is requested.
	 * This will return an indexed array of associative arrays of the format:
	 *
	 *     array (
	 *         "name" => "Type / Model name",                 e.g. 'K Geschakeld'
	 *         "value" => "{houseModel_id}_{houseType_id}"    e.g. '8_7616'
	 *         "self.link" => "link"                          e.g. "/projects/325/2DVBV41777/2DVBV1775/8"
	 *     )
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' ),
	 *         'sale' => 'true'
	 *     )
	 *
	 * @param string $project_link  The resource link to the project: 'projects/{developer_id}/{project_id}'.
	 * @param array  $parameters    All needed query parameters as key/value-pairs.
	 *                              If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  The housetype/model information (or false).
	 */
	public function get_project_house_type_model_combis( $project_link, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $project_link ) ) {
			return false;
		}
		$link = $project_link . "/interest/typeModelCombis";
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}
		return $this->get_niki_resource( $link, $parameters );
	}

	/**
	 * Return an array of all typeModelCombis from the Niki API provider for all selected projects of this site.
	 *
	 * @see get_project_type_model_combis()
	 *
	 * For all the active projects in this website, the get_project_type_model_combis() function is called.
	 * The resulting typeModelCombis are merged together into one indexed array of associative arrays.
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' ),
	 *         'sale' => 'true'
	 *     )
	 *
	 * These parameters are used for each separate call to get_project_type_model_combis();
	 *
	 * @param array  $parameters  All needed query parameters as key/value-pairs.
	 *                            If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return array  The housetype/model information (or false).
	 */
	public function get_house_type_model_combis( $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}

		$answer = array();
		foreach ( $this->get_projects() as $project) {
			$tmcs = $this->get_project_house_type_model_combis( $project ['link'], $parameters );
			if ( is_array( $tmcs ) ) {
				foreach ($tmcs as $tmc ) {
					$answer [] = $tmc;
				}
			}
		}
		return $answer;
	}

	/**
	 * Get information on a specific house type / house model combination from the Niki API provider.
	 *
	 * See also: https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-{housetype_id}-{housemodel_id}.html
	 *
	 * The Niki API documentation (referenced above) lists some additional parameters that can be specified with the call.
	 * These parameters can be requested as an associative array in method parameter $parameters.
	 * For example:
	 *
	 *     $parameters = array (
	 *         'status' => array ( 'In verkoop', 'Verkocht' ),
	 *         'sale' => 'true'
	 *     )
	 *
	 * @param string $link        The link to the house type / house model combination, e.g. '/projects/325/2DVBV41777/2DVBV1775/8'
	 * @param array  $parameters  All needed query parameters as key/value-pairs.
	 *                            If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return boolean|mixed
	 */
	public function get_house_type_house_model( $link, $parameters = array() ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $link ) ) {
			return false;
		}
		if ( !is_array( $parameters ) ) {
			$parameters = array();
		}
		return $this->get_niki_resource( $link, $parameters );
	}

	/**
	 *
	 * @param string $project_link  The resource link to the project: 'projects/{developer_id}/{project_id}'.
	 * @param array  $parameters    All needed query parameters as key/value-pairs.
	 *                              If more than one parameters should exist with the same key, the value should be an indexed array.
	 *
	 * @return unknown  Whether the submission validated or not.
	 */
	public function submit_interest( $project_link, $parameters ) {
		if ( false === $this->niki_api ) {
			return false;
		}

		if ( !is_string( $project_link ) ) {
			return false;
		}
		$link = $project_link . "/interest";
		if ( !is_array( $parameters ) ) {
			return false;
		}
		return $this->get_niki_resource( $link, $parameters );
	}
}
