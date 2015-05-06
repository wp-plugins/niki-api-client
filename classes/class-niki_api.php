<?php
/**
 * Niki API protocol handler
 *
 * file: classes/class-niki_api.php
 * created: 2015-02-15, mk
 *
 * The implemented class Niki_API provides a complete wrapper around the Niki API protocol, including the OAuth authorization.
 */

// prevent direct access to this file
defined( 'ABSPATH' ) or die( 'Nothing to see here.' );

/**
 * API protocol handler for Niki database.
 *
 * @author mk
 *
 * This handler class is specifically written for the Niki database provider at either https://auth.niki.nl/
 * (the normal provider) or http://auth.acc.niki.nl/ (for testing). The implemented OAuth protocol is 1.0.
 */
class Niki_API {

	/**
	 * Control at what level debug output is enabled.
	 *
	 * The debugging output makes use of the niki_log function in @see classes/debug.php
	 * In the class methods, the @see log() function is used to send logging messages.
	 * This method expects the message and a log level (default 0).
	 * Only if the given log level is at least as high as the $debug parameter, the message is passed through to the niki_log function.
	 *
	 * If this parameter is set to < 0, no logging messages are passed.
	 *
	 * The value can be set using the $options array in the class constructor.
	 * It can be interpreted as:
	 * -1  no logging
	 *  0  only logging if something does not run smoothly ('error')
	 *  1  logging of 'information' level messages
	 *  2  logging of 'debug' level messages
	 *  3  logging of 'verbose debug' level messages
	 *
	 * @access private
	 *
	 * @var integer $debug  Determines at what level debug messages are generated. Default -1 (no logging).
	 */
	private $debug = -1;

	/**
	 * Default HTTP user agent.
	 *
	 * The actual value is set in the constructor.
	 *
	 * @var string $user_agent  The default user agent as sent with the http requests.
	 */
	private $user_agent;

	/**
	 * URL where API calls can be requested.
	 *
	 * For testing and acceptance testing, use http://api.acc.niki.nl.
	 * For production environments, use https://api.niki.nl.
	 *
	 * The default value can be overwritten by passing it as an option to the class contructor.
	 *
	 * @access private
	 *
	 * @var string $api_url URL where API calls can be requested. Default 'https://api.niki.nl'.
	 */
	private $api_url = "https://api.niki.nl";

	/**
	 * URL where initial OAuth token is requested.
	 *
	 * This is usually the provider URL + /oauth/requestToken.
	 * The value is set in the class constructor.
	 *
	 * @access private
	 *
	 * @var string $request_token_url URL where initial token is requested.
	 */
	private $request_token_url;

	/**
	 * URL for the authorization dialog with the OAuth provider.
	 *
	 * This is usually the provider URL + /oauth/authorization. Also known as dialog url.
	 * The value is set in the class constructor.
	 *
	 * @access private
	 *
	 * @var string $authorization_url  URL for the authorization dialog.
	 */
	private $authorization_url;

	/**
	 * URL where OAuth access token is requested.
	 *
	 * This is usually the provider URL + /oauth/accessToken.
	 * The value is set in the class constructor.
	 *
	 * @access private
	 *
	 * @var string $access_token_url  URL where access token is requested.
	 */
	private $access_token_url;

	/**
	 * Callback URL for OAuth provider callbacks.
	 *
	 * This is the URL of the script where OAuth is handled.
	 * The value is set in the class constructor.
	 *
	 * @access private
	 *
	 * @var string $redirect_url  Callback URL.
	 */
	private $redirect_url;

	/**
	 * Client ID: identifier of the application registered with the OAuth provider.
	 *
	 * The value is set in the class constructor.
	 * Usually, this is the domain name of the client site and this is set in the constructor.
	 * If a different client id should be used, it can be passed to the constructor.
	 *
	 * @access private
	 *
	 * @var string $client_id  Client identifier.
	 */
	private $client_id;

	/**
	 * Client secret: secret value assigned to the application when it is registered with the OAuth provider.
	 *
	 * The value is set in the class constructor.
	 * As currently specified, this is the constant 'IMPLEMENT_SECRET' and this is set in the constructor.
	 * Note that this value may change in the future.
	 * If a different value is required, it can be passed to the constructor.
	 *
	 * @access private
	 *
	 * @var string $client_secret  Client secret.
	 */
	private $client_secret;

	/**
	 * OAuth access token information, as stored via the WordPress Options API.
	 *
	 * The class constructor retrieves the current access token information from the WordPress Options API.
	 * If no information was found, this array will be empty.
	 *
	 * The process_oauth function (@see process_oauth()) can update the stored information if needed by negotiating with
	 * the OAuth provider. If applicable, the new information is stored using the Options API.
	 *
	 * The get_access_token_information method (@see get_access_token_information()) can be used to read the currently
	 * stored (in the class instance) information per field.
	 *
	 * @acccess private
	 *
	 * @var array $access_token_information {
	 *     The access token information array may contain the following fields:
	 *
	 *     @type string  $value       Token value.
	 *     @type string  $secret      Token secret.
	 *     @type boolean $authorized  Whether the access token was obtained successfully.
	 *     @type string  $expiry      Optional timestamp in ISO format relative to UTC time zone of the access token expiry time.
	 *     @type string  $type        Optional type of OAuth token that may determine how it should be used when sending API call requests.
	 * }
	 */
	private $access_token_information = array ();

	/**
	 * Setup a new instance.
	 *
	 * The instance is populated with sensible default data for production environments.
	 * The default data may be overwritten by specifying any of the options.
	 *
	 * @param array $options {
	 *     Optional. May contain any the following keys:
	 *
	 *     @type integer $debug             At what level to log debugging messages via the niki_log function (classes/debug.php).
	 *                                      -1 for no logging, 0 or higher for more logging.
	 *     @type string  $api_url           The API url. Default is 'https://api.niki.nl'.
	 *     @type string  $client_id         Also known as client key. The default is the server name (domain name).
	 *     @type string  $client_secret     The default is 'IMPLEMENT_SECRET'.
	 *     @type string  $request_token_url URL where initial token is requested.
	 *                                      This is usually the provider URL + /oauth/requestToken.
	 *                                      Default is unset.
	 *     @type string  $authorization_url URL for the authorization dialog.
	 *                                      This is usually the provider URL + /oauth/authorization.
	 *     @type string  $access_token_url  URL where access token is requested.
	 *                                      This is usually the provider URL + /oauth/accessToken.
	 *                                      Default is unset.
	 *     @type string  $redirect_url      Callback URL.
	 *                                      This is the URL of the script where OAuth is handled.
	 *                                      Default is unset.
	 *     @type string  $user_agent        The user agent string as sent to the API provider.
	 * }
	 */
	public function __construct( $options = null ) {
		global $wp_version;

		if ( !is_array( $options ) ) {
			$options = array();
		}

		// set default values (may be overridden below)
		$this->client_id = $_SERVER[ "SERVER_NAME" ];
		$this->client_secret = "IMPLEMENT_SECRET";
		$this->user_agent = "Niki API Client WordPress plugin for '{$this->client_id}' (WP version {$wp_version})";

		// Apply options
		foreach ( $options as $key => $value ) {
			$key = strtolower( $key );
			switch ( $key ) {
				case 'debug':
					$this->debug = intval( $value, 10 );
					if ( ( !WP_DEBUG) or ( $this->debug <= -1 ) ) {
						$this->debug = -1;
					}
					$this->log( "debug level: {$this->debug}", 1 );
					break;
				case 'api_url':
					$this->api_url = $value;
					$this->log( "API url: '{$this->api_url}'", 2 );
					break;
				case 'client_id':
					$this->client_id = $value;
					$this->log( "client id (key): {$this->client_id}", 2 );
					break;
				case 'client_secret':
					$this->client_secretid = $value;
					$this->log( "client secret: {$this->client_secret}", 2 );
					break;
				case 'request_token_url':
					$this->request_token_url = $value;
					$this->log( "request token url: {$this->request_token_url}", 2 );
					break;
				case 'authorization_url':
					$this->authorization_url = $value;
					$this->log( "authorization url: {$this->authorization_url}", 2 );
					break;
				case 'access_token_url':
					$this->access_token_url = $value;
					$this->log( "access token url: {$this->access_token_url}", 2 );
					break;
				case 'redirect_url':
					$this->redirect_url = $value;
					$this->log( "redirect url: {$this->redirect_url}", 2 );
					break;
				case 'user_agent':
					$this->user_agent = $value;
					$this->log( "user agent: {$this->user_agent}", 2 );
					break;
			}
		}

		$this->log( 'Retrieving available access token information if available.', 1 );
		$this->retrieve_access_token_information();
		$this->log( $this->access_token_information, 2 );
		if ( count( $this->access_token_information ) === 0 ) {
			$this->log( "no access token information found" );
		}

		$this->log( 'Niki_API created', 1 );
	}

	/**
	 * Log a (debug) message.
	 *
	 * This method calls the @see niki_log() function (as defined in /classes/debug.php) for logging.
	 * The message provided is logged only if the given level is smaller than or equal to @see $debug.
	 *
	 * The log level can be interpreted as:
	 * -1  no logging
	 *  0  only logging if something does not run smoothly ('error') (string '[ERR']' is prepended to each message)
	 *  1  logging of 'information' level messages (string '[INF']' is prepended to each message)
	 *  2  logging of 'debug' level messages (string '[DEB']' is prepended to each message)
	 *  3  logging of 'verbose debug' level messages (string '[VER']' is prepended to each message)
	 *
	 * The log message is prepended with the name of the class and function and appended is the filename and line number
	 * at which the message was summoned.
	 *
	 * Both logging of string messages and array messages is supported.
	 * Arrays are split in their key/value pairs recursively before being sent to the niki_log function.
	 * If any value in the array should be an array itself, this array is parsed using the print_r php core function.
	 *
	 * @param mixed $msg  The message to log. If this is an array, the print_r function is used to log the array.
	 * @param integer level  Used to determine whether or not to log the message. Default 0 (first level messages only).
	 */
	private function log ( $msg, $level = 0, $array_level = 0 ) {
		if ( $level > $this->debug ) {
			return;
		}

		global $niki;

		switch ( $level ) {
			case 0: $level = 'ERR'; break;
			case 1: $level = 'INF'; break;
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
	 * Store existing OAuth access token information as a WordPress option.
	 *
	 * The WordPress Options API is used for this. @see $access_token_information for field details.
	 * If the access token information parameter is set (if it is an array), the information is updated in this class instance.
	 * If the parameter is not set (it is not an array), the given value is ignored.
	 * In both cases, the (possibly new) information is stored as Option via the WordPress Options API.
	 *
	 * It is always checked that the information stored is an array.
	 * If this is not the case, the access token information (in the class instance) is reset with an empty array before being stored as Option.
	 *
	 * @param array $access_token_information  An array with access token information (@see $access_token_information for field details).
	 *
	 * @access private
	 */
	private function store_access_token( $access_token_information ) {
		if ( is_array( $access_token_information )) {
			$this->access_token_information = $access_token_information;
		}
		if ( ! is_array( $this->access_token_information ) ) {
			$this->access_token_information = array ();
		}
		update_option( Niki_Plugin::OPTION_NAME_OAUTH, $this->access_token_information );
		$this->log( "new access token information updated in '" . Niki_Plugin::OPTION_NAME_OAUTH . "' WP Option", 1 );
	}

	/**
	 * Retrieve the OAuth access token information via the WordPress Options API.
	 *
	 * If no previously stored information was found, an empty array is returned.
	 * Otherwise, the information is retrieved and stored in this class instance (@see $access_token_information for field details).
	 *
	 * @access private
	 */
	private function retrieve_access_token_information() {
		$this->access_token_information = get_option( Niki_Plugin::OPTION_NAME_OAUTH );
		if ( $this->access_token_information === false ) {
			$this->access_token_information = array ();
		}
		$this->log( "existing access token information updated from '" . Niki_Plugin::OPTION_NAME_OAUTH . "' WP Option", 1 );
	}

	/**
	 * Get access token information on a per field basis.
	 *
	 * This method returns the requested information if it exists or boolean false otherwise. Note that some information may
	 * actually hold a boolean false value, which is not distinguishable from non-existence of the field.
	 *
	 * For the supported fields: @see $access_token_information.
	 *
	 * @param string $field  The field to look for.
	 *
	 * @return mixed The field information if it exists, boolean false otherwise.
	 */
	public function get_access_token_information( $field ) {
		if ( ! is_array( $this->access_token_information) ) {
			return false;
		}
		if ( key_exists( $field, $this->access_token_information ) ) {
			return $this->access_token_information [$field];
		}
		return false;
	}

	/**
	 * Get the field names as available in the current access token information.
	 *
	 * This method returns an array of the field names.
	 * These field names can be used to retrieve the access token information values using the @see get_access_token_information() function.
	 *
	 * @return array  The field names available.
	 */
	public function get_access_token_information_field_names() {
		return array_keys( $this->access_token_information );
	}

	/**
	 * Get an oauth token and verifier from the GET parameters in the request.
	 *
	 * The parameters should reside in the GET parameters by the names 'oauth_token' and 'oauth_verifier'.
	 * If any of the two values does not exist, null is returned.
	 *
	 * @access private
	 *
	 * @param string $token Reference to the token string.
	 * @param string $verifier Reference to the verifier string.
	 */
	private function get_request_token( &$token, &$verifier ) {
		$token = ( isset( $_GET ['oauth_token'] ) ? $_GET ['oauth_token'] : null );
		$verifier = ( isset( $_GET ['oauth_verifier'] ) ? $_GET ['oauth_verifier'] : null );
	}

	/**
	 * Process the OAuth protocol interaction with the OAuth provider.
	 *
	 * Call this function when you need to retrieve the OAuth access token.
	 * What this method does, depends on the OAuth state, as determined from the access token information.
	 * If there is no token yet, a request token is requested. In this case, this method ends parsing and forwards to the authorization url.
	 * If there is a token, but it is unauthorized, it assumed to be a request token and an access token is negotiated.
	 *
	 * This method returns boolean true if the protocol step was handled successfully.
	 * This does not mean successful authorization.
	 * @see get_access_token_information() for checking whether the access token was obtained successfully.
	 *
	 * Note that this method only implements version 1.0 of the OAuth protocol, as this is the version implemented by the Niki provider.
	 *
	 * @return boolean Whether the OAuth protocol was processed without errors.
	 */
	public function process_oauth() {
		$this->log( "process_oauth().", 1 );
		if ( is_array( $this->access_token_information ) ) {
			foreach ( $this->access_token_information as $k => $v ) {
				$this->log( "access token information [$k] => $v", 2 );
			}
		} else {
			$this->log( "no access token information found", 2 );
		}

		$token_value = $this->get_access_token_information( 'value' );
		$token_authorized = $this->get_access_token_information( 'authorized' );

		if ( $token_value !== false ) {
			if ( ! $token_authorized ) {
				$this->log( "token value present, but not (yet?) authorized: checking the OAuth token and verifier", 1 );
				$this->get_request_token( $token, $verifier );
				$this->log( "  token: '$token', verifier: '$verifier'", 2 );
				if ( is_null( $token) || is_null( $verifier ) ) {
					$this->log( "no token or no verifier. Is access denied?", 1 );
					$denied = isset( $_GET ['denied'] ) ? $_GET ['denied'] : null;
					if ( $denied === $token_value ) {
						$this->log( "access was denied." );
						echo "The authorization request was denied.<br />\n";
						return true;
					} else {
						$this->log( "access was not denied, but token or verifier was not set. Resetting OAuth state." );
						echo "Reset the OAuth token state because token and verifier are not both set.<br />\n";
						$this->store_access_token( array () );
					}
				} else if ( $token !== $token_value ) {
					$this->log( "token does not match what was previously retrieved. Resetting OAuth state." );
					echo "Reset the OAuth token state because token does not match.<br />\n";
					$this->store_access_token( array () );
				} else {
					$this->log( "preparing API call for getting access token", 1 );
					if ( $this->access_token_url === false ) {
						$this->log( "access token url not set!" );
						echo "The authorization failed because the access token url was not set.<br />\n";
						return false;
					}
					$oauth = array (
							'oauth_token' => $token,
							'oauth_verifier' => $verifier
					);
					$error = $this->send_API_request( $this->access_token_url, null, $oauth, $response );
					if ( $error !== false ) {
						$this->log( "an error occurred: '$error'" );
						echo "The authorization failed because the request to the the API provider failed:<br />\n<i>$error</i><br />\n";
						return false;
					}
					$body = wp_remote_retrieve_body( $response );
					$this->log( "response body (enclosed in pre tag): <pre>$body</pre>", 2 );
					parse_str( $body, $oauth );
					if ( ! isset( $oauth ['oauth_token'] ) || ! isset( $oauth ['oauth_token_secret'] ) ) {
						$this->log( "response did not return as expected (no token or no token secret)" );
						echo "The authorization failed: no oauth token or secret was returned.<br />\n";
						return true;
					}
					$access_token_information = array (
							'value' => $oauth ['oauth_token'],
							'secret' => $oauth ['oauth_token_secret'],
							'authorized' => true
					);
					if ( isset( $oauth ['oauth_expires_in'] ) ) {
						$expires = $oauth ['oauth_expires_in'];
						if ( strval( $expires ) !== strval( intval( $expires ) ) || $expires <= 0 ) {
							$this->log( "the oauth provider returned an unsupported type of access token expiry time" );
							echo "The OAuth provider returned an unsupported type of access token expiry time '$expires'.<br />\n";
							return false;
						}
						$access_token_expiry = gmstrftime( '%Y-%m-%d %H:%M:%S', time() + $expires );
						$this->log( "Access token expiry: '{$access_token_expiry}' UTC", 1 );
						$access_token_information ['expiry'] = $access_token_expiry;
					}
					$this->store_access_token($access_token_information);
					$this->log( "the access token was successfully authorized, token value: '" . $this->get_access_token_information( "value" ) . "'.", 1 );
				}
			}

			// re-check token information
			$token_authorized = $this->get_access_token_information( 'authorized' );
			if ( $token_authorized ) {
				$this->log( "re-checked token: it is authorized, returning (true)", 1 );
				return true;
			} else {
				$this->log( "re-checked token: it is not authorized" );
			}
		} else {
			$this->log( "there is no token" );
		}

		if ( ! $this->get_access_token_information( "authorized" ) ) {
			$this->log( 'Requesting the unauthorized OAuth token', 1 );
			$oauth = array (
					'oauth_callback' => $this->redirect_url
			);
			$this->log( "oauth parameters:", 2 );
			$this->log( $oauth, 2 );

			$error = $this->send_API_request( $this->request_token_url, null, $oauth, $response );
			$this->log( "api request complete", 1 );
			if ( $error !== false ) {
				$this->log( "an error occurred during the API request: '$error'" );
				echo "The authorization failed because the request to the the API provider failed:<br />\n<i>$error</i><br />\n";
				return false;
			}
			$body = wp_remote_retrieve_body( $response );
			$this->log( "response body (enclosed in pre tag): <pre>$body</pre>", 2 );
			parse_str( $body, $oauth );

			if ( ! isset( $oauth ['oauth_token'] ) || ! isset( $oauth ['oauth_token_secret'] ) ) {
				$this->log( "authorization failed: oauth token or secret not found in response" );
				echo "The authorization failed: OAuth token or secret not found in response from API provider.<br />\n";
				return true;
			}

			$this->log( "unothorized token received: '{$oauth ['oauth_token']}'", 1 );
			$access_token_information = array (
					'value' => $oauth ['oauth_token'],
					'secret' => $oauth ['oauth_token_secret'],
					'authorized' => false
			);
			$this->store_access_token( $access_token_information);
			$this->log( "access token information stored.", 2 );

			$_SESSION ["niki_oauth_secret"] = $this->get_access_token_information( "secret" );
			$_SESSION ["niki_oauth_state"] = 1;
			session_write_close();
			$this->log( "oauth secret and oauth state written to session", 2 );

			$url = $this->authorization_url . '?oauth_token=' . $this->get_access_token_information( "value" );
			$this->log( "redirecting to '$url'", 1 );
			header( 'HTTP/1.1 302 Found' );
			header( 'location: ' . $url );
			exit();
		}
	}

	/**
	 * Request information from the Niki API provider.
	 *
	 * The API request is always a GET request.
	 * The url is constructed from:
	 *
	 * 1. The api url (@see $api_url) as set in the class constructor.
	 * 2. The resource, e.g. '/projects/mine'.
	 *    Note that any 'path parameters' should be specified in the resource string.
	 *    The resource string always starts with a '/' and never ends in one.
	 *    The string is not checked in any way (e.g. for validity).
	 * 3. A '?' sign.
	 * 4. The key/value-pairs from the array $parameters.
	 *    It is not necessary to include the OAuth token as it is included internally where appropriate.
	 *
	 * The result (as passed by reference) is the actual content as expected from the resource (if there is no error).
	 * The content type of the result depends on the resource, see also the Niki API documentation.
	 *
	 * It is assumed that the class instance already has a valid access token set in @see access_token_information.
	 *
	 * @param string $resource    The resource, e.g. '/about/version'
	 * @param array  $parameters  The parameters to send in the query string of the request.
	 *                            It is not necessary to specify the 'oauth_token' parameter as it is added internally if appropriate.
	 * @param mixed  $result      Usually the body of the http response, possibly decoded as specified above.
	 *
	 * @return mixed  Boolean false if the request was successful, an error string otherwise.
	 */
	public function call_API( $resource, $parameters, &$result ) {
		$url = $this->api_url . $resource;
		$this->log( "url='$url'", 1 );
		if ( is_array( $parameters ) ) {
			$this->log( count( $parameters ) . " parameters found", 1 );
		} else {
			$this->log( "no parameters found", 1 );
		}
		$error = $this->send_API_request( $url, $parameters, null, $response );
		if ( $error !== false ) {
			$this->log( "API request failed: '$error'", 1 );
			return  "The API call request failed:<br />\n<i>$error</i><br />\n";
		}
		$this->log( "API call request complete", 2 );

		$content_type = wp_remote_retrieve_header( $response, "content-type" );
		if ( strlen( $content_type ) > 0 ) {
			$content_type = strtolower( strtok( trim( $content_type ), ';' ) );
		}
		$this->log( "response content type: '$content_type'", 1 );

		$body = wp_remote_retrieve_body( $response );
		$this->log( "response body is " . strlen( $body ) . " bytes long", 2 );

		switch ( $content_type ) {
			case 'text/plain' :
				// text/plain is used for resource /about/version
				$this->log( "result is raw body (text/plain)", 2 );
				$result = $body;
				break;
			case 'application/json' :
				// application/json is used for /projects/* resources
				// a json string is expected, convert to php array
				$this->log( "result is array from json (application/json)", 2 );
				$result = json_decode( $body, true );
				break;
			default :
				// various content types can be returned by the /files/* and /images/* resources.
				$this->log( "result is raw http result ({$content_type})", 2 );
				$result = $response;
		}

		$this->log( "first 100 characters of body: " . substr( $body, 0, 100 ), 2 );
		return false;
	}

	/**
	 * Send an API request to the given url.
	 *
	 * The response is returned as parameter and is an associative array as returned by the WordPress HTTP API.
	 * See also e.g. https://codex.wordpress.org/HTTP_API
	 *
	 * The url specifies where the request is send.
	 *
	 * The parameters are added as query string to the url.
	 * If more than one value should be provided to a parameter with the same name, this parameter value should be an indexed array.
	 * An example parameter array is:
	 *
	 *     $parameters = array(
	 *         'addSummary' => true,
	 *         'status' => array( 'In verkoop', 'Verkocht' )
	 *     );
	 *
	 * Note that the OAuth token does not have to be added to the parameters, this is done automatically by this method.
	 *
	 * If the request is handled successfully, the method returns boolean false.
	 * If an error occurs, an error message (string) is returned.
	 * If an error occurs, the response state is unspecified.
	 *
	 * @param string $url         The request url (without query string).
	 * @param array  $parameters  The query parameters that will be appended to the url (query string).
	 *                            It is not necessary to specify the 'oauth_token' parameter, as it is added in this method if appropriate.
	 *                            It is possible to have multiple values for one parameter (which is allowed for some resources).
	 *                            If that is necessary, the parameter value should be an indexed array of values.
	 * @param mixed  $oauth       OAuth (v 1.0) parameters to use in the request, if any (this is only used in OAuth negotiating, not for regular API calls).
	 *                            The parameters are passed as array. If there are no parameters, pass a non-array.
	 * @param string $response    If there is a response (normally true), it will be stored into this parameter.
	 *                            The response is an associative array.
	 *
	 * @return mixed  Boolean false if the request was successful, an error string otherwise.
	 */
	private function send_API_request( $url, $parameters, $oauth, &$response ) {
		$this->log( "url: '$url'", 1 );

		// sanitation of method parameters
		if ( !is_string( $url ) ) {
			return "send_API_request called without url specified!";
		}
		if ( ! ( isset( $parameters ) && is_array( $parameters ) ) ) {
			$this->log( "parameters null or not an array, resetting to array", 1 );
			$parameters = array ();
		}
		// Note: $oauth can be null.

		$authorization = false;

		// using OAuth parameters? Then set $authorization
		if ( isset( $oauth ) && is_array( $oauth ) ) {
			$this->log( "preparing OAuth in request.", 1 );
			$values = array (
					'oauth_consumer_key' => $this->client_id,
					'oauth_nonce' => md5( uniqid( rand(), true ) ),
					'oauth_signature_method' => 'HMAC-SHA1',
					'oauth_timestamp' => time(),
					'oauth_version' => '1.0'
			);
			$values = array_merge( $values, $oauth, $parameters );
			$this->log( count( $values ) . ' values merged into one array.', 2 );
			// create signature
			$sign = 'GET&' . $this->encode( $url ) . '&';
			$sign_values = $values;
			ksort( $sign_values );
			$this->log( "sign values: '$sign' plus:", 2 );
			$this->log( $sign_values, 2 );
			$sep = '';  // empty separator first time
			foreach ( $sign_values as $parameter => $value ) {
				$sign .= $this->encode( $sep . $parameter . '=' . $this->encode( $value ) );
				$sep = '&';  // from now on, use ampersand as separator
			}
			$this->log( "sign: '$sign'", 2 );
			$key = $this->encode( $this->client_secret ) . '&' . ( isset( $_SESSION ["niki_oauth_secret"] ) ? $_SESSION ["niki_oauth_secret"] : "" );
			$this->log( "key: '$key'", 2 );
			$values ['oauth_signature'] = base64_encode( $this->HMAC( 'sha1', $sign, $key ) );
			$this->log( "oauth signature: '{$values ['oauth_signature']}'", 2 );
			$authorization = 'OAuth';
			$first = true;
			$sep = ' ';  // first separator after 'OAuth' should be space (use comma later)
			foreach ( $values as $parameter => $value ) {
				$authorization .= $sep . $parameter . '="' . $this->encode( $value ) . '"';
				$first = false;
				$sep = ',';  // use comma from now on
			}
		} else {
			$this->log( "adding oauth token to request parameters", 1 );
			if ( !array_key_exists( "oauth_token", $parameters ) ) {
				$parameters ["oauth_token"] = $this->get_access_token_information( "value" );
			}
		}

		$arguments = array(
				'httpversion' => '1.1',
				'user-agent' => $this->user_agent,
				'headers' => array (
						'Accept' => '*/*'
				)
		);

		if ( $authorization !== false ) {
			$arguments ['headers'] ['Authorization'] = $authorization;
		}

		$this->log( "url arguments prepared", 2 );
		if (WP_DEBUG && ( $this->debug > 1 ) ) {
			foreach ( $arguments as $k => $v ) {
				if ( is_array( $v ) ) {
					foreach ($v as $vk => $vv ) {
						if ( is_array( $vv ) ) {
							$this->log( "arguments [$k] [$vk] => " . str_replace( PHP_EOL, " ", print_r( $vv ) ), 2 );
						} else {
							$this->log( "arguments [$k] [$vk] => $vv", 2 );
						}
					}
				} else {
					$this->log( "arguments [$k] => $v", 2 );
				}
			}
		}

		// add the parameters to the url
		$this->log( "preparing query parameters", 2 );
		$parameter_pairs = array();
		foreach ( $parameters as $name => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					$parameter_pairs [] = urlencode( $name ) . "=" . urlencode( $v );
				}
			} else {
				$parameter_pairs [] = urlencode( $name ) . "=" . urlencode( $value );
			}
		}
		$this->log( count( $parameter_pairs ) . " query parameters found", 2 );
		if ( count( $parameter_pairs ) > 0 ) {
			$url .= "?" . implode( "&", $parameter_pairs );
			$this->log( count( $parameter_pairs ) . " parameters added as url query string", 1 );
		}

		// send the actual request
		$this->log( "sending remote request to '$url'", 2 );
		$response = wp_remote_get( $url, $arguments );

		if ( is_wp_error( $response ) ) {
			$this->log( "remote request resulted in an error!" );
			$this->log( $response->get_error_message() );
			return "remote request resulted in an error!";
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$response_message = wp_remote_retrieve_response_message( $response );
			$this->log( "response code: $response_code ({$response_message})" );
			if ( strlen ( $body = wp_remote_retrieve_body( $response ) ) ) {
				$this->log( "response body (in pre tags): <pre>$body</pre>", 1 );
			} else {
				$this->log( "response contains no (or empty) body" );
			}
			$this->log( "returning error message", 1 );
			return "Remote request resulted in a response with code {$response_code} ({$response_message}).";
		}

		if ( $this->debug > 2 ) {
			$this->log( "http response:", 3 );
			$this->log( $response, 3 );
		}

		$this->log( "request successful", 1 );
		return false;  // false = no error
	}

	/**
	 * Encode the given string or array so that it can be used as an url parameter.
	 *
	 * This method makes use of the standard PHP function rawurlencode(). After that, '+' is replaced by ' ' and '%7E' is replaced by '~'.
	 *
	 * If the given value is an array, all array values are encoded recursively.
	 *
	 * @param mixed $value  The value to encode as string or array.
	 *
	 * @return mixed  The encoded value (string or array).
	 */
	private function encode( $value ) {
		if ( is_array( $value ) ) {
			return $this->encode_array( $value );
		}
		return str_replace( '%7E', '~', str_replace( '+', ' ', rawurlencode( $value ) ) );
	}

	/**
	 * Encode the given array recursively so that all containing strings can be used as url parameters.
	 *
	 * @param array $array  The (possibly nested) array of string values to encode.
	 *
	 * @return array  The recursively encoded array.
	 */
	private function encode_array( $array ) {
		foreach ( $array as $key => $value ) {
			$array [$key] = $this->encode( $value );
		}
		return $array;
	}

	/**
	 * Perform HMAC encoding.
	 *
	 * Currently, the only supported function is sha1 (which makes use of the standard PHP sha1 hash function).
	 * If another function is requested, an boolean false is returned.
	 *
	 * @param string $function  The hash function to be used. Currently, only 'sha1' is supported.
	 * @param string $data      The data to encode.
	 * @param string $key       The key to use for encoding.
	 *
	 * @return mixed  The HMAC (sha1 hashed) or boolean false if an unsupported hash function was requested.
	 */
	private function HMAC( $function, $data, $key ) {
		$function = strtolower( $function );
		switch ( $function ) {
			case 'sha1' :
				$pack = 'H40';
				break;
			default :
				$this->log( "'$function' is not a supported an HMAC hash type." );
				return false;
		}
		if ( strlen( $key ) > 64 ) {
			$key = pack( $pack, $function( $key ) );
		}
		if ( strlen( $key ) < 64 ) {
			$key = str_pad( $key, 64, "\0" );
		}
		return pack( $pack, $function( ( str_repeat( "\x5c", 64 ) ^ $key ) . pack( $pack, $function( ( str_repeat( "\x36", 64 ) ^ $key ) . $data ) ) ) );
	}
}
