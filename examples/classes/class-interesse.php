<?php
/**
 * Manage all form fields.
 *
 */
class InterestForm {

	/**
	 * String that is prepended to all the fields in the actual html form.
	 *
	 * Use this to prevent collision of field names with other processes.
	 *
	 * @access private
	 * @var string $field_prefix  String to prepend to each form field.
	 */
	private static $field_prefix = "niki-";

	/**
	 * Name for storing the submitted form data in the SESSION.
	 *
	 * @access private
	 * @var string $session_key_fields  Name for storing the submitted form data in the SESSION.
	 */
	private static $session_key_fields = "niki-interest-form";

	/**
	 * Name for storing the submitted house type model combi data in the SESSION.
	 *
	 * @access private
	 * @var string $session_key_htmc  Name for storing the submitted house type model combi data in the SESSION.
	 */
	private static $session_key_htmc = "niki-interest-htmc";

	/**
	 * Name for storing a time field in the SESSION.
	 *
	 * This field is used as following:
	 *
	 * - Upon first request of this interest form (by the user), this field is not yet set.
	 *   This indicates that Stap 1 should be shown.
	 *
	 * - If this field is set, it is checked whether the data is too old (using the $maximum_submit_delay class property).
	 *   If it is too old, the value is reset and Stap 1 is shown.
	 *
	 * - If the field is set and not too old, this class checks other available form data to determine which Stap should be used.
	 *
	 * @access private
	 * @var string $session_key_time  Name for storing a time field in the SESSION.
	 */
	private static $session_key_time = "niki-interest-time";

	/**
	 * Maximum number of seconds between form creation and form submission.
	 *
	 * The forms in steps 1 and 2 will also submit a time stamp.
	 * Upon submission, this time stamp is compared to the current time stamp.
	 * If the difference is bigger than this property, the submission is invalidated.
	 *
	 * Current value is 15 minutes (15 * 60 = 900).
	 * (Note: PHP does not allow non-static property values)
	 *
	 * @access private
	 * @var integer $maximum_submit_delay  Maximum number of seconds between form creation and form submission.
	 */
	private static $maximum_submit_delay = 900;

	/**
	 * Form field definitions.
	 *
	 * All fields are defined as arrays:
	 *
	 *     '<field name>' => array(        only use field names as accepted by Niki database API;
	 *                                         see https://api.niki.nl/apidocs/resources/projects-{developer_id}-{project_id}-interest.html
	 *         'label' => string,          label to show to user
	 *         'values' => array,          for enumeration only; the values (strings) allowed (see notes below)
	 *         'placeholder' => string,    a placeholder text, if applicable
	 *         'value' => string           value is submitted by user (if 'values' is specified, 'value' should be one of the 'values' keys)
	 *                                     note that this is always a string.
	 *     )
	 *
	 * All fields are required, except 'values' and 'placeholder'.
	 *
	 * If 'values' is specified, it should be an array of the format:
	 *
	 *     'values' = array(
	 *         'value1' => 'label for value 1',
	 *         ...
	 *         'valueN' => 'label for value N',
	 *     )
	 *
	 * e.g.:
	 *
	 *     array(
	 *         'MALE' => 'Man',
	 *         'FEMALE' => 'Vrouw'
	 *     )
	 *
	 * The 'value' of this field may only be one of the entries in 'values'.
	 * Usually, such a field will be a radio button field.
	 *
	 * The order of the fields in this definition is important: the form consists of the fields in this order.
	 */
	private $fields = array(
			'gender' => array(
					'label' => 'Geslacht *',
					'values' => array( 'MALE' => 'Dhr.', 'FEMALE' => 'Mevr.' ),
					'value' => '',
			),
			'initials' => array(
					'label' => 'Voorletters *',
					'value' => '',
			),
			'interpolation' => array(
					'label' => 'Tussenvoegsel',
					'value' => '',
			),
			'surname' => array(
					'label' => 'Achternaam *',
					'value' => '',
			),
			'zipcode' => array(
					'label' => 'Postcode *',
					'placeholder' => '1234AB',
					'value' => '',
			),
			'number' => array(
					'label' => 'Huisnummer *',
					'value' => '',
			),
			'numberAdd' => array(
					'label' => 'Toevoeging',
					'value' => '',
			),
			'street' => array(
					'label' => 'Straat *',
					'value' => '',
			),
			'city' => array(
					'label' => 'Plaats *',
					'value' => '',
			),
			'country' => array(
					'label' => 'Land *',
					'value' => '',
			),
			'email' => array(
					'label' => 'E-mailadres *',
					'value' => '',
			),
			'phonenumber' => array(
					'label' => 'Telefoon *',
					'placeholder' => '050 1234567',
					'value' => '',
			),
			'phonemobile' => array(
					'label' => 'Telefoon mobiel',
					'placeholder' => '06 12345678',
					'value' => '',
			)
	);

	private $house_type_model_combis = array();

	private $form_errors = array();

	/**
	 * Define the logging level.
	 *
	 * @see log()
	 *
	 * @var integer $debug  The log level.
	 */
	private $debug = 3;

	/**
	 * Check presence of Niki API Client plugin and prefetch house type model combis.
	 */
	public function __construct() {
		global $niki;
		if ( !isset( $niki ) ) {
			echo "<p>De Niki API Client plugin is niet goed geïnstalleerd.</p>";
			return;
		}

		$this->log( "fetching possible house type model combi's", 1 );
		$house_type_model_combis = $niki->get_house_type_model_combis();
		foreach ( $house_type_model_combis as $htmc ) {
			$this->house_type_model_combis [$htmc ['value'] ] = array (
					'htmc' => $htmc,
					'selected' => false
			);
			$this->log( "added house type model combi '{$htmc ['value']}'", 2 );
		}
	}

	/**
	 * Logging for debug purposes.
	 *
	 * Makes use of the niki_log() function as defined in the plugin: /classes/debug.php
	 *
	 * -1: no logging
	 *  0: error logging
	 *  1: info logging
	 *  2: debug logging
	 * >2: verbose logging
	 *
	 * @param mixed $msg     The string, object or array to log.
	 * @param integer $level The debug level.
	 *
	 */
	private function log ( $msg, $level = 0, $array_level = 0 ) {
		if ( $level > $this->debug ) {
			return;
		}

		switch ( $level ) {
			case 0: $level = 'ERR'; break;
			case 1: $level = 'INF'; break;
			case 2: $level = 'DEB'; break;
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
	 * Prefill the instance using data available in the SESSION.
	 *
	 * The SESSION may contain three entries, one for the form data, one for the house type model combi data and a time field.
	 * The first two are arrays.
	 * The fields entry is an array of key/value-pairs representing the field names and values from the form.
	 * The htmc entry is an array where the keys represent the available htmc values (house type id and house model id, separated by underscore)
	 * and the value is a boolean indicating whether the htmc is selected by the user.
	 *
	 * The time entry is used to check whether the data is too old.
	 * If the entry is not present or if it is too old, the other session data entries are removed.
	 *
	 * Values not found in the SESSION are left as is.
	 *
	 * Note: the Niki plugin makes sure that a session is started.
	 *
	 * @access private
	 *
	 * @return boolean  Whether any data was found.
	 */
	private function from_session() {
		$time = false;
		if ( isset( $_SESSION [self::$session_key_time] ) ) {
			$time = intval( $_SESSION [self::$session_key_time], 10 );
			$current = time();
			$this->log( "appearant session age: " . ( $current - $time ) . " seconds", 1 );
			if ( $time < ( $current - self::$maximum_submit_delay ) ) {
				$this->log( "session data too old, cleaning session." );
				$this->clean_session();
				return false;
			}
		} else {
			$this->log( "no session time found, cleaning session." );
			$this->clean_session();
			return false;
		}
		$data_found = false;
		if ( isset( $_SESSION [self::$session_key_fields] ) ) {
			$this->log( "getting existing form data from session", 1 );
			$session_data = $_SESSION [self::$session_key_fields];
			foreach ( $this->fields as $field_name => $field ) {
				if ( isset( $session_data [$field_name] ) ) {
					$this->fields [$field_name] ['value'] = trim( $session_data [$field_name] );
					$this->log( "field $field_name ({$field ['label']}) found: '{$this->fields [$field_name] ['value']}'", 2 );
					$data_found = true;
				} else {
					$this->log( "field $field_name ({$field ['label']}) not found", 2 );
				}
			}
		} else {
			$this->log( "no existing form data found in session", 1 );
		}
		if ( isset( $_SESSION [self::$session_key_htmc] ) ) {
			$this->log( "getting existing house type model combi data from session", 1 );
			$session_data = $_SESSION [self::$session_key_htmc];
			if ( !is_array( $session_data ) ) {
				$session_data = array();
			}
			foreach ( $this->house_type_model_combis as $htmc => $htmc_arr ) {
				if ( isset( $session_data [$htmc] ) ) {
					$this->house_type_model_combis [$htmc] ['selected'] = $session_data [$htmc];
					$this->log( "house type model combi '$htmc' " . ( $session_data [$htmc] ? "" : "not " ) . "selected", 2 );
					$data_found = true;
				}
			}
		} else {
			$this->log( "no existing house type model combi data found in session", 1 );
		}
		$this->log( "returning (boolean) " . ( $data_found ? "true" : "false" ), 1 );
		return $data_found;
	}

	/**
	 * Prefill the instance using data available in the POST.
	 *
	 * The POST may contain key/value-pairs with keys from the pre-defined form fields (field names).
	 * All field names are prepended by the field_prefix specified in this class.
	 * The POST also may contain an array '(field_prefix)type_model'.
	 * This array contains all house type model combis that the user selected.
	 *
	 * Field values not found in the POST are left as is.
	 * House type model combis not found in the 'type_model' array are unselected only if the array exists.
	 *
	 * The forms also contain a time stamp.
	 * This time stamp is compared to the current time stamp.
	 * If the time between form creation (time stamp in the form) and submission (current time stamp) is too big
	 * (i.e. greater than the $maximum_submit_delay class property), the POST data is discarded.
	 *
	 * The form also contains a validation string, based on the PHP session id and the time stamp (md5 sum of these).
	 * If this validation string is not correct, the POST data is discarded.
	 *
	 * @access private
	 */
	private function from_post() {
		// check for prescence of tid and validation fields
		if ( !isset( $_POST [self::$field_prefix . 'tid'] ) ) {
			return;
		}
		if ( !isset( $_POST [self::$field_prefix . 'validation'] ) ) {
			return;
		}

		// check time (discard forms that are too old)
		$tid = intval( $_POST [self::$field_prefix . 'tid'], 10 );
		$current = time();
		$this->log( "post age: " . ( $current - $tid ) . " seconds", 1 );
		if ( $tid < ( $current - self::$maximum_submit_delay ) ) {
			$this->log( "post data too old." );
			return;
		}

		// check validation string
		$validation_form = $_POST [self::$field_prefix . 'validation'];
		$validation_new = md5( session_id() . $tid );
		$this->log( "validation string from post: $validation_form", 2 );
		$this->log( "validation based on session id '" . session_id() . "' and time '$tid': $validation_new", 2 );
		if ( $validation_form !== $validation_new ) {
			$this->log( "post data validation failed." );
			return;
		}

		$this->log( "getting existing form data from post", 1 );
		foreach ( $this->fields as $field_name => $field ) {
			if ( isset( $_POST [self::$field_prefix . $field_name] ) ) {
				$value = trim( $_POST [self::$field_prefix . $field_name] );
				$this->log( "- field $field_name ({$field ['label']}) found: '$value'", 2 );
				$this->fields [$field_name] ['value'] = $value;
			} else {
				$this->log( "- field $field_name ({$field ['label']}) not found", 2 );
			}
		}

		$this->log( "getting existing house type model data from post", 1 );
		if ( isset( $_POST [self::$field_prefix . 'type_model'] ) ) {
			$house_type_model_combis = $_POST [self::$field_prefix . 'type_model'];
			$this->log( "house type model data from post:", 3 );
			$this->log( $house_type_model_combis, 3 );
			foreach ( $this->house_type_model_combis as $htmc => $htmc_arr ) {
				$present = in_array( $htmc, $house_type_model_combis );
				$this->house_type_model_combis [$htmc] ['selected'] = $present;
				$this->log( "- " . ( $present ? "" : "un") . "selected house type model combi '$htmc' (was previously " . ( $htmc_arr ['selected'] ? "" : "not ") . "selected)", 2);
			}
		}
	}

	/**
	 * Try to submit the interest request.
	 *
	 * A separate request is done for each project, since the house type model combis are unique for projects.
	 *
	 * The data as submitted by the user is validated.
	 * If no house type model combis are yet selected, the form fields are validated first.
	 * This is either before or just after step 1.
	 * If any error occurs (invalid or missing data according to the Niki API provider), these errors are stored in the form_errors array.
	 * These errors could either be 'subscriberErrors' or 'partnerErrors', as returned from the Niki API.
	 * If no errors occurr, a 'hidden' error is generated to notify that although the subscriber data is complete and validated, no
	 * house type model combis were yet selected.
	 * This way, the calling function can determine which 'step' to do.
	 *
	 * If one or more house type model combis are selected, all data is validated by the Niki API.
	 * Any errors (and these may also still be subscriber or partner errors) are notified in the form_errors array.
	 * If no errors occur, the interest data is really submitted.
	 * If that succeeds, boolean true is returned from this function.
	 * False in all other cases.
	 *
	 * @see $form_errors
	 *
	 * @return boolean  Whether or not all interest requests were successfully submitted.
	 */
	private function submit() {
		global $niki;

		$parameters = array();

		// fetch all the form field parameters
		foreach ( $this->fields as $field_name => $field ) {
			$value = trim( $field ['value'] );
			if ( '' !== $value ) {
				$parameters [$field_name] = $value;
			}
		}

		$this->log( "getting htmc's per project", 1 );
		$htmcs = array();
		foreach ( $this->house_type_model_combis as $htmc_arr ) {
			if ( $htmc_arr ['selected'] ) {
				$htmc = $htmc_arr ['htmc'];
				$htmc_link = explode( "/", trim( $htmc ['self.link'], "/" ) );
				$project_link = "/" . $htmc_link [0] . "/" . $htmc_link [1] . "/" . $htmc_link [2];
				if ( !isset( $htmcs [$project_link] ) ) {
					$htmcs [$project_link] = array();
					$this->log( "added entry for project '$project_link'", 2 );
				}
				$htmcs [$project_link] [] = $htmc;
				$this->log( "added htmc '{$htmc ['value']}' to project '$project_link'", 2 );
			}
		}

		// validate all interest requests first. Only if there are no errors, submit them all.
		$this->form_errors = array(); // reset errors
		$parameters ['onlyValidate'] = 'true';

		if ( count( $htmcs ) === 0 ) {
			// if there is no entry in the htmcs array, validation will fail (houseModelTypeErrors), but may provide
			// useful information about any subscriber error.
			$parameters ['typeModel'] = array();
			$projects = $niki->get_projects();
			$project_link = $projects [0] ['link'];
			$this->log( "submitting sample interest request (only form data) to project '$project_link'", 1 );
			$this->log( $parameters, 2 );

			$submission = $niki->submit_interest( $project_link, $parameters );

			if ( is_array( $submission ) ) {
				if ( count( $submission ['subscriberErrors'] ) > 0 ) {
					$this->form_errors ['subscriberErrors'] = $submission ['subscriberErrors'];
					$this->log( "subscriber errors found" );
				}
				if ( count( $submission ['partnerErrors'] ) > 0 ) {
					$this->form_errors ['partnerErrors'] = $submission ['partnerErrors'];
					$this->log( "partner errors found" );
				}
			} else {
				$this->form_errors = array(
						'general' => array(
								'Het formulier kon door onbekende oorzaak niet verzonden worden.'
						)
				);
			}
			if ( count( $this->form_errors ) === 0 ) {
				$this->form_errors = array(
						'hidden' => array(
								'no htmc selected'
						)
				);
			}
		} else {
			// submit the interest request separately for each project for which at least one htmc was selected
			foreach ( $htmcs as $project_link => $htmc_list ) {
				// add the house type model combis for this request
				$parameters ['typeModel'] = array();
				foreach ( $htmc_list as $htmc ) {
					$parameters ['typeModel'] [] = $htmc ['value'];
				}

				$this->log( "submitting interest request to project '$project_link'", 1 );
				$this->log( $parameters, 2 );

				$submission = $niki->submit_interest( $project_link, $parameters );

				if ( is_array( $submission ) ) {
					if ( isset( $submission ['subscriberErrors'] ) ) {
						if ( count( $submission ['subscriberErrors'] ) > 0 ) {
							$this->form_errors ['subscriberErrors'] = $submission ['subscriberErrors'];
							$this->log( "subscriber errors found" );
						}
						if ( count( $submission ['partnerErrors'] ) > 0 ) {
							$this->form_errors ['partnerErrors'] = $submission ['partnerErrors'];
							$this->log( "partner errors found" );
						}
						if ( count( $submission ['houseModelTypeErrors'] ) > 0 ) {
							$this->form_errors ['houseModelTypeErrors'] = $submission ['houseModelTypeErrors'];
							$this->log( "house model type errors found" );
						}
					} else {
						$this->form_errors ['subscriberErrors'] = array ( 'general' => 'could not validate' );
						$this->log( "could not validate, suggest subscriberErrors ('could not validate')" );
					}
				} else {
					$this->form_errors = array(
							'general' => array(
									'Het formulier kon door onbekende oorzaak niet gevalideerd worden.'
							)
					);
				}

				// if any errors occurred, just skip
				if ( count( $this->form_errors ) > 0 ) {
					break;
				}
			}
		}

		// now, if there are no errors anymore, submit all interest requests (per project)
		if ( count( $this->form_errors ) === 0 ) {
			unset ( $parameters ['onlyValidate'] );
			$this->log( "really submitting the interest requests", 1 );

			$successfully_submitted = 0;
			foreach ( $htmcs as $project_link => $htmc_list ) {
				// add the house type model combis for this request
				$parameters ['typeModel'] = array();
				foreach ( $htmc_list as $htmc ) {
					$parameters ['typeModel'] [] = $htmc ['value'];
				}

				$this->log( "submitting real interest request to project '$project_link'", 1 );
				$this->log( $parameters, 2 );

				$submission = $niki->submit_interest( $project_link, $parameters );

				if ( is_array( $submission ) && isset( $submission ['stored'] ) && ( 1 == $submission ['stored'] ) ) {
					$successfully_submitted ++;
					$this->log( "request complete", 1 );
					$this->log( $submission, 2 );
				} else {
					$this->form_errors = array(
							'general' => array(
									'Het formulier kon door onbekende oorzaak niet verzonden worden.'
							)
					);
				}

				// if any errors occurred, skip
				if ( count( $this->form_errors ) > 0 ) {
					break;
				}
			}
			return ( $successfully_submitted === count( $htmcs ) );
		}
		return false;
	}

	/**
	 * Store the form field values in the SESSION in a way that it can be retrieved by the from_session() function.
	 *
	 * @access private
	 * @param string $session_key  the name under which to store the data in the session.
	 */
	private function to_session() {
		// reset 'old' data
		$this->clean_session();

		$session_data = array();
		foreach ( $this->fields as $key => $field ) {
			$value = trim( $field ['value'] );
			if ( strlen( $value ) > 0 ) {
				$session_data [$key] = trim( $field ['value'] );
			}
		}
		if ( count( $session_data ) > 0 ) {
			$_SESSION [self::$session_key_fields] = $session_data;
			$this->log( count( $session_data ) . " fields written into SESSION['" . self::$session_key_fields . "']", 1 );
		} else {
			$this->log( "no fields set, nothing written to SESSION['" . self::$session_key_fields . "']", 1 );
		}

		$session_data = array();
		foreach ( $this->house_type_model_combis as $htmc => $htmc_arr ) {
			$session_data [$htmc] = $htmc_arr ['selected'];
		}
		$_SESSION [self::$session_key_htmc] = $session_data;
		$this->log( count( $session_data ) . " htmc fields written into SESSION['" . self::$session_key_htmc . "']", 1 );

		$time = time();
		$_SESSION [self::$session_key_time] = $time;
		$this->log( "time '$time' written into SESSION['" . self::$session_key_time . "']", 1 );
	}

	/**
	 * Remove the form data from the session.
	 *
	 * @access private
	 */
	private function clean_session() {
		if ( isset( $_SESSION [self::$session_key_fields] ) ) {
			unset( $_SESSION [self::$session_key_fields] );
		}
		if ( isset( $_SESSION [self::$session_key_htmc] ) ) {
			unset( $_SESSION [self::$session_key_htmc] );
		}
		if ( isset( $_SESSION [self::$session_key_time] ) ) {
			unset( $_SESSION [self::$session_key_time] );
		}
	}

	/**
	 * Output the form opening html tag and any hidden fields.
	 *
	 * The form is written using html divs to align the fields.
	 *
	 * @access private
	 */
	private function output_open_form() {
		$tid = time();
		$validation = md5( session_id() . $tid );
		$this->log( "validation based on session id '" . session_id() . "' and time '$tid': $validation", 2 );
		?>
		<form method="post" action="<?php echo $_SERVER ['REQUEST_URI']; ?>">
			<input type="hidden" name="<?php echo self::$field_prefix . "tid"; ?>" value="<?php echo $tid; ?>" />
			<input type="hidden" name="<?php echo self::$field_prefix . "validation"; ?>" value="<?php echo $validation; ?>" />
			<div id="interest-overview">
		<?php
	}

	/**
	 * Check whether there are any 'general' errors and if so, output them.
	 *
	 * Calls the @see output_error() function for each general error.
	 */
	private function output_general_errors() {
		if ( isset( $this->form_errors ['general'] ) ) {
			foreach ( $this->form_errors ['general'] as $error ) {
				$this->output_error( $error );
			}
		}
	}

	/**
	 * Output a div row with a specific error.
	 *
	 * A div row of two columns will be output with the given $error message.
	 *
	 * @param string $error  The message to show.
	 */
	private function output_error( $error ) {
		?>
				<div class="error">
					<?php echo $error; ?>
				</div> <!-- end class="error" -->
		<?php
	}

	/**
	 * Output html tags for the field specified.
	 *
	 * For an enumeration (the 'values' field was specified), write a radio button group.
	 * For other fields, write a regular text intput.
	 *
	 * The fields are written as div rows with two columns.
	 *
	 * @access private
	 * @param string $field_name  The field to output.
	 */
	private function output_field( $field_name ) {
		if ( !array_key_exists( $field_name, $this->fields ) ) {
			return;
		}
		$field = $this->fields [$field_name];

		// write the header (label) of the field
		?>
		<div class="interest-row">
			<div class="interest-label">
				<?php echo $field ['label']; ?>
			</div> <!-- end class="interest-label" -->
			<div class="interest-input">
			<?php

			// write the input tags
			if ( isset( $field ['values'] ) ) {
				foreach ( $field ['values'] as $value => $label ) {
				?>
				<input type="radio" id="<?php echo $field_name . $value; ?>" name="<?php echo self::$field_prefix . $field_name; ?>" value="<?php
				echo $value; ?>"<?php if ($this->fields [$field_name] ['value'] === $value ) { echo " checked"; } ?> />
				<label for="<?php echo $field_name . $value; ?>"><?php echo $label; ?></label>
				<?php
				}
			} else {
				?>
				<input type="text" name="<?php echo self::$field_prefix . $field_name; ?>" value="<?php
					echo $field ['value']; ?>"<?php if ( isset( $field ['placeholder'] ) ) { echo ' placeholder="' . $field ['placeholder'] . '"'; }  ?> />
				<?php
			}
			?>
			</div> <!-- end class="interest-input" -->
		</div> <!-- end class="interest-row" -->
		<?php
	}

	/**
	 * Output rows for each house type / house model combination that was found for this site.
	 *
	 */
	private function output_housetypes() {
		global $niki;
		foreach ( $this->house_type_model_combis as $htmc_arr ) {
			$htmc = $htmc_arr ['htmc'];
			$selected = $htmc_arr ['selected'];
			$this->log( "house type model combi (" . ( $selected ? "" : "not " ) . "selected):", 2 );
			$this->log( $htmc, 2 );

			$htmc_details = $niki->get_house_type_house_model( $htmc ['self.link'] );

			$this->log( "htmc details found", 1 );

			?>
			<div class="interest-housetype-row">
				<div class="interest-housetype-image">
					<input type="checkbox" name="<?php echo self::$field_prefix; ?>type_model[]" value="<?php echo $htmc ['value'];
						?>" id="type_model_<?php echo $htmc ['value']; ?>"<?php echo ( $selected ? " selected" : ""); ?> />
					<label for="type_model_<?php echo $htmc ['value']; ?>"><?php echo $htmc_details ['name']; ?></label>
					<br />
			<?php

			$image_tag = "geen afbeelding";
			if ( isset( $htmc_details ['images'] ) && is_array( $htmc_details ['images'] ) ) {
				$this->log( count( $htmc_details ['images'] ) . " images found", 2 );
				$this->log( $htmc_details ['images'], 3 );
				if ( count( $htmc_details ['images'] ) > 0 ) {
					$image = $htmc_details ['images'] [0] ['sizes'] ['thumb'];
					$this->log( "image found: '$image'" );
					$image = explode( "/", trim( $image, "/" ) );
					if ( count( $image ) >= 3 ) {
						$id = $image[1];
						$size = $image[2];
						$link = get_bloginfo('wpurl') . "/niki-image/$id/$size";
						$image_tag = "<img src=\"$link\" alt=\"afbeelding\" />";
					}
				}
			}
			echo "$image_tag</div>", PHP_EOL;  // end class="interest-housetype-image"

			?>
				<div class="interest-housetype-description">
					<?php echo $htmc_details ['model']; ?>
					<br />
					<?php echo niki_range_to_string( "&euro; ", $htmc_details ['pricerange'], "" ); ?>
				</div> <!-- end class="interest-housetype-description" -->
			</div> <!-- end class="interest-housetype-row" -->
			<?php
		}
	}

	/**
	 * Output the form closing tag, including the form submit button.
	 *
	 */
	private function output_close_form() {
		?>
		<div class="interest-submit-row">
			<div class="interest-label">&nbsp;</div> <!-- end id="interest-label"  -->
			<div class="interest-input">
				<input type="submit" value="verder" />
			</div> <!-- end id="interest-input"  -->
		</div> <!-- end id="interest-row"  -->
			</div> <!-- end id="interest-overview"  -->
		</form>
		<?php
	}

	/**
	 * Tries to send an email message to the subscriber, confirming the interest submission.
	 *
	 * The function returns whether the mail was successfully 'sent'.
	 * Note that this is not an indication that the mail was successfully received!
	 *
	 * @return boolean  Whether the mail could be listed for delivery.
	 */
	private function send_email() {
		ob_start();
		$template = dirname( __FILE__ ) . '/interest_confirm_mail.php';
		$this->log( "getting mail template: '$template'", 1 );
		include( $template );
		$message = ob_get_clean();

		$blogname = get_bloginfo( "name" );

		$headers = array(
				"from: $blogname <noreply@fundament.nl>",
				"content-type:text/html;charset=utf-8"
		);

		$to = $this->fields ['email'] ['value'];

		$this->log( "sending mail to: '$to'", 1 );

		if ( wp_mail( $to, "Uw belangstelling voor ". $blogname, $message, $headers ) ) {
			$this->log( "mail sent", 1 );
			return true;
		}
		$this->log( "sending mail failed" );
		return false;
	}

	/**
	 * Output a 'thank you' message to the user
	 */
	private function output_thank_you( $mail_sent ) {
		?>
		<h2 class="interest-overview-subtitle">Stap 3 van 3</h2>
		<p>
			Bedankt voor uw gegevens. Wij nemen spoedig contact met u op.
		</p>
		<?php
		if ( $mail_sent ) {
		?>
		<p>
			Een bevestigingsbericht is verzonden naar <?php echo $this->fields ['email'] ['value']; ?>.
		</p>
		<?php
		}
		?>
		<p>
			<a href="<?php echo get_site_url() . "/niki/aanbod"; ?>">Klik hier om terug te gaan.</a>
		</p>
		<?php
	}

	/**
	 * Output the form with fields depending on the progress of completion.
	 *
	 * In step 1, the fields as specified in this class are shown.
	 * Upon submission of the form (to this same page), these fields are checked.
	 * If the submission is complete (all mandatory categories have a value), proceed to step 2.
	 * If some fields are missing, show the same form, with hints about the missing data.
	 *
	 * In step 2, a form is shown for selection of the specific house types.
	 * The house types to chose from are fetched from the Niki API, based on the current project or projects.
	 * If at least one housetype was selected, proceed to step 3.
	 * If no choice was made, show this form again, with a warning that at least one housetype should be selected.
	 *
	 * In step 3, show a 'thank you' message.
	 * In this step, the data is actually submitted to the Niki API.
	 *
	 * Between steps, submitted data is stored in the SESSION using the to- and from_session functions of this class.
	 * @see from_session()
	 * @see to_session()
	 *
	 *
	 */
	public function handle_form() {
		global $niki;

		if ( !isset( $niki ) ) {
			echo "<p>De Niki API Client plugin is niet goed geïnstalleerd.</p>";
			return;
		}

		echo '<h1 id="interest-overview-title">Interesse</h1>', PHP_EOL;

		$this->log( "output form" );

		// in all cases: prefill the instance from the SESSION and POST (in that order) if possible
		$data_found_in_session = $this->from_session();
		$this->from_post();

		// store the retrieved data so far in the session
		$this->to_session();

		// validate the data
		$submitted = $this->submit();

		// depending on the result of submission, show forms or a 'thank you' to the user.
		if ( $submitted ) {
			$this->log( "interest request(s) was/were successfully submitted", 1 );
			// try to send a confirmation email to the subscriber
			$this->log( "try sending confirmation message", 1 );
			$sent = $this->send_email();
			$this->output_thank_you( $sent );
			$this->clean_session();
		} else {
			$this->log( "interest request(s) not yet valid", 1 );
			// show a form, depending on the step
			if ( isset( $this->form_errors ['subscriberErrors'] ) or isset( $this->form_errors ['partnerErrors'] ) ) {
				$this->log( "subscriber or partner errors found, show step 1", 1 );
				// since there are data missing (either for subscriber or partner), show the step 1 form.
				echo '<h2 class="interest-overview-subtitle">Stap 1 van 3</h2>', PHP_EOL;
				$this->output_open_form();
				foreach ( $this->fields as $field_name => $field ) {
					// output warning is applicable
					// If no data was yet found in the session, the interest is 'new' and no error messages need to be shown.
					// However, if there was already some data in the session, an earlier attempt was made and error messages are in place.
					if (
							$data_found_in_session &&  // previous data was found
							(
									isset( $this->form_errors ['subscriberErrors'] [$field_name] ) or
									isset( $this->form_errors ['partnerErrors'] [$field_name] )          // errors were found
							)
					) {
						$error = '';
						if ( isset( $this->form_errors ['subscriberErrors'] [$field_name] ) ) {
							$error = $this->form_errors ['subscriberErrors'] [$field_name] ['message'];
						} else {
							$error = $this->form_errors ['partnerErrors'] [$field_name] ['message'];
						}
						if ( strlen( $error ) > 0 ) {
							$this->output_error( $error );
						}
					}
					// and output the form field
					$this->output_field( $field_name );
				}
				$this->output_close_form();
			} else {
				// all form data is present, but no house type model combis are yet selected, show step 2
				$this->log( "subscriber or partner data complete but no htmc's, show step 2", 1 );
				echo '<h2 class="interest-overview-subtitle">Stap 2 van 3</h2>', PHP_EOL;
				$this->output_open_form();
				$this->output_housetypes();
				$this->output_close_form();
			}
		}
	}
}
