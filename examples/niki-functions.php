<?php

/**
 * Create a string from a range.
 *
 * If the range {min} and {max} values are equal, return just one number.
 * If they are not equal, return: '{min} tot {max}'
 *
 * The prefix is placed before each number, the suffix is placed after each number.
 * Use empty strings to omit the prefix or suffix.
 *
 * @param string $prefix  The string that is placed immediately before each number, e.g. '&euro;'.
 * @param array  $range {
 *     This should be a typical 'range' array as used in the Niki API. This array has (at least) these two fields:
 *
 *     @type string min  The minimum value.
 *     @type string max  The maximum value.
 * }
 * @param string $suffix  The string that is placed immediately after each number, e.g. 'm<sup>2</sup>'.
 * @return string
 */
function niki_range_to_string( $prefix, $range, $suffix ) {
	$min = number_format($range ['min'],0,",",".");
	$max = number_format($range ['max'],0,",",".");
	if ( $min === $max ) {
		return $prefix . $min . $suffix;
	} else {
		return $prefix . $min . $suffix . " tot " . $prefix . $max . $suffix;
	}
}

/**
 * Show the complete 'aanbod' of a website.
 *
 * This will show an overview of all house types in the active projects of the website.
 */
function niki_show_aanbod() {
	require_once dirname( __FILE__ ) . "/classes/class-aanbod.php";
	Aanbod::output_housetypes();
}

/**
 * Show the details of a woningtype (house type).
 *
 * This will show the details of the house type with a table of available houses with some details.
 */
function niki_show_woningtype( $project_link, $housetype_id ) {
	require_once dirname( __FILE__ ) . "/classes/class-woningtype.php";
	Woningtype::output_housetype( $project_link, $housetype_id );
}

/**
 * Handle a 3-step interest form.
 *
 * Depending on what the user filled out before (stored in the session), this shows:
 *
 * 1. Form for submitting personal details (subscriber information);
 *
 * 2. Form for selecting housetype housemodel combinations;
 *
 * 3. A "thank you" notice.
 */
function niki_show_interesse() {
	require_once dirname( __FILE__ ) . "/classes/class-interesse.php";
	$interest_form = new InterestForm();
	$interest_form->handle_form();
}
