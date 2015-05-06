<?php
/**
 * Example class, wrapping functionality for showing the complete 'aanbod' of a site.
 * That is, an overview of all house types of all active projects.
 *
 * Usage:
 *
 * 1. Set the class properties
 *        Aanbod::$woningtype_base_link
 *    and
 *        Aanbod::$image_base_link
 *    according to the settings on the site (rewrite rules).
 *
 * 2. Call the function
 *        Aanbod::output_housetypes()
 */
class Aanbod {

	/**
	 * 'Slug' representing the base of a 'woningtype' call.
	 *
	 * This variable is used for creating links from the aanbod page to a woningtype page.
	 * It will be prepended by the wpurl retrieved from the function get_bloginfo( 'wpurl' ).
	 * It will be appended by the (external) project id and housetype id.
	 *
	 * @var string  Base part of the 'woningtype' slug.
	 */
	public static $woningtype_base_link = "/niki/woningtype";

	/**
	 * 'Slug' representing the base of an 'image' call.
	 *
	 * This variable is used for creating src-links for html img-tags.
	 * It will be prepended by the wpurl retrieved from the function get_bloginfo( 'wpurl' ).
	 * It will be appended by the image id and image size.
	 *
	 * @var string  Base part of the 'image' slug.
	 */
	public static $image_base_link = "/niki-image";

	/**
	 * Fetch all housetypes for all active projects of the site and display a table of the house types.
	 *
	 * This function will generate direct output using php echo.
	 */
	public static function output_housetypes() {
		global $niki;
		if ( !isset( $niki ) ) {
			echo "<p>De Niki API Client plugin is niet goed geïnstalleerd.</p>";
			return;
		}

		echo '<h1 id="housetypes-overview-title">Aanbod</h1>', PHP_EOL;
		echo '<div id="housetypes-overview">', PHP_EOL;

		$projecten = $niki->get_projects();
		self::log( "getting projects: " . count( $projecten ) . " found" );
		$housetypes = array();
		foreach ( $projecten as $project ) {
			$housetypes = $niki->get_housetypes( $project ['link'] );
			self::log( "project '{$project ['link']}' has " . count( $housetypes ) . " housetypes" );
			foreach ( $housetypes as $housetype ) {
				self::show_housetype( $project, $housetype );
			}
		}

		echo "</div>", PHP_EOL;
	}

	/**
	 * Display the given housetype (which is part of the given project).
	 *
	 * This function will generate direct output using php echo.
	 *
	 * @param array $project {
	 *     The project typically has the following fields:
	 *
	 *     @type string  name  The project name, e.g. 'Demo 't Hof van Blerick fase 3'.
	 *     @type string  link  The project link, e.g. '/projects/325/2DVBV41777'.
	 * }
	 * @param array $housetype {
	 *     The housetype has many fields, among which:
	 *
	 *     @type string  name      The housetype name, e.g. 'K'.
	 *     @type string  self.link The link for more information, e.g. '/projects/325/2DVBV41777/2DVBV1775'.
	 *     @type array   image     Collection of images of various sizes.
	 *
	 *     etc.
	 * }
	 */
	private static function show_housetype( $project, $housetype ) {
		echo '<div class="housetype-row">', PHP_EOL;

		$link = $housetype ['self.link'];
		// link will be of format '/projects/{developer_id}/{project_id}/{housetype_id}'
		$link_parts = explode( "/", trim( $link, "/" ) );
		$link_parts[0] = self::$woningtype_base_link;
		$href = get_site_url() . implode("/", $link_parts);

		echo '<div class="housetype-image">';
		$image = $housetype ['image'];
		if ( is_array( $image ) ) {
			$image = $image ['sizes'] ['thumb'];
			$image = explode( "/", trim( $image, "/" ) );
			if ( count( $image ) >= 3 ) {
				$id = $image [1];
				$size = $image [2];
				$link = get_bloginfo( 'wpurl' ) . self::$image_base_link . "/$id/$size";
				echo '<a href="' . $href . '"><img src="' . $link . '" alt="' . $housetype ['name'] . '"></a>';
			}
		}
		echo '</div>', PHP_EOL; // end class="housetype-image"

		echo '<div class="housetype-description">', PHP_EOL;

		echo '<header class="housetype-title">';
		echo '<a href="' . $href . '">' . $housetype ['name'], '</a>';
		echo '</header>', PHP_EOL;

		echo "<strong>Project: </strong>", stripslashes( $project ['name'] ), "<br />", PHP_EOL;

		$soort = ( isset( $housetype ['models'] ) ? $housetype ['models'] : false );
		if ( is_array( $soort ) ) {
			echo "<strong>Soort: </strong>", implode( ", ", $soort ), "<br />", PHP_EOL;
		}

		$value = ( isset( $housetype ['livingsurface-range'] ) ? $housetype ['livingsurface-range'] : false );
		if ( is_array( $value ) ) {
			if ( $value ['max'] != 0 ) {
				echo "<strong>Oppervlakte: </strong>" . niki_range_to_string( "", $value, "m<sup>2</sup>" ) . "<br />", PHP_EOL;
			}
		}

		$value = ( isset( $housetype ['groundsurface-range'] ) ? $housetype ['groundsurface-range'] : false );
		if ( is_array( $value ) ) {
			if ( $value ['max'] != 0 ) {
				echo "<strong>Kavel: </strong>" . niki_range_to_string( "", $value, "m<sup>2</sup>" ) . "<br />", PHP_EOL;
			}
		}

		$value = ( isset( $housetype ['roomcount-range'] ) ? $housetype ['roomcount-range'] : false );
		if ( is_array( $value ) ) {
			if ( $value ['max'] != 0 ) {
				echo "<strong>Aantal kamers: </strong>" . niki_range_to_string( "", $value, "" ) . "<br />", PHP_EOL;
			}
		}

		$value = ( isset( $housetype ['price-range'] ) ? $housetype ['price-range'] : false );
		if ( is_array( $value ) ) {
			if ( $value ['max'] != 0 ) {
				echo "<strong>Prijs: </strong>" . niki_range_to_string( "&euro; ", $value, "" ) . "<br />", PHP_EOL;
			}
		}

		echo "</div>", PHP_EOL; // // end class="housetype-description"
		echo "</div>", PHP_EOL; // // end class="housetype-row"
	}

	/**
	 * Debug logging.
	 *
	 * Makes use of the debug mechanism (function niki_log()) in the Niki API Client (see /classes/debug.php in the plugin directory).
	 *
	 * @param string $msg  The message to log (only string).
	 */
	private static function log( $msg ) {
		if ( !WP_DEBUG ) {
			return;
		}
		if ( !defined( "NIKI_NEW_BACKTRACE" ) ) {
			return;
		}
		if ( NIKI_NEW_BACKTRACE ) {  // defined in Niki API Client plugin: /classes/debug.php
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
		niki_log( "[---]$caller$msg$file" );
	}
}
