<?php
/**
 * Example class, wrapping functionality for showing a woningtype (housetype).
 * That is, an overview the housetype and a table of available houses with some souse details.
 *
 * Usage:
 *
 * 1. Set the class propertie
 *        Woningtype::$image_base_link
 *    according to the settings on the site (rewrite rules).
 *
 * 2. Call the function
 *        Woningtype::output_housetype( $project_id, $housetype_id )
 *    with the proper parameters.
 *    A housetype link is of the form '/projects/{developer_id}/{project_id}/{housetype_id}',
 *    these 'project_id' and 'housetype_id' should be passed to the function.
 */
class Woningtype {

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
	 * 'Slug' representing the base of a 'file' call.
	 *
	 * This variable is used for creating href-links for Niki API file downloads.
	 * It will be prepended by the wpurl retrieved from the function get_bloginfo( 'wpurl' ).
	 * It will be appended by the file id.
	 *
	 * @var string  Base part of the 'file' slug.
	 */
	public static $file_base_link = "/niki-file";

	/**
	 * Fetch the housetype details and display them, with a table of house information.
	 *
	 * This function will generate direct output using php echo.
	 */
	public static function output_housetype( $project_link, $housetype_id ) {
		global $niki;
		if ( !isset( $niki ) ) {
			echo "<p>De Niki API Client plugin is niet goed geïnstalleerd.</p>";
			return;
		}

		// Fetch information about the housetype
		$housetype_link = "$project_link/$housetype_id";
		self::log( "housetype resource link: '$housetype_link'" );
		$housetype = $niki->get_housetype( $housetype_link, array() );

		// Fetch information about project for the above housetype
		self::log( "project resource link: '$project_link'" );
		$project = $niki->get_project( $project_link, array() );

		if ( is_array( $housetype ) ) {
			if ( WP_DEBUG ) {
				foreach ( $housetype as $field => $value ) {
					self::log( "housetype [$field] => " . str_replace( "\n", "&para;", print_r( $value, true ) ) );
				}
			}
			
			echo '<div id="housetype-detail-overview">', PHP_EOL;
			echo '<h1 id="housetype-detail-title">Woningtype: ' . $housetype ['name'] . '</h1>', PHP_EOL;

			$images = $housetype ['images'];
			if ( is_array( $images ) ) {
				echo '<div id="housetype-detail-images">', PHP_EOL;
				foreach ( $images as $image ) {
					if ( is_array( $image ) ) {
						$image = $image ['sizes'] ['thumb'];
						self::log( "image found: '$image'" );
						$image = explode( "/", trim( $image, "/" ) );
						if ( count( $image ) >= 3 ) {
							$id = $image [1];
							$size = $image [2];
							$link = get_bloginfo( 'wpurl' ) . self::$image_base_link . "/$id/$size";
							self::log( "image src: '$link'" );
							echo "<img src=\"$link\" alt=\"{$housetype ['name']}\">", PHP_EOL;
						}
					}
				}
				echo "</div>", PHP_EOL;
			}

			echo '<div id="housetype-overview">', PHP_EOL;
			echo '<p>';
			$value = $housetype ['pricerange'];
			if ( is_array( $value ) ) {
				if ( $value ['max'] != 0 ) {
					echo "<strong>Prijs: </strong>" . niki_range_to_string( "&euro; ", $value, "" ) . "<br />", PHP_EOL;
				}
			}

			$value = $housetype ['livingsurface-range'];
			if ( is_array( $value ) ) {
				if ( $value ['max'] != 0 ) {
					echo "<strong>Oppervlakte: </strong>" . niki_range_to_string( "", $value, "m<sup>2</sup>" ) . "<br />", PHP_EOL;
				}
			}

			$value = $housetype ['groundsurface-range'];
			if ( is_array( $value ) ) {
				if ( $value ['max'] != 0 ) {
					echo "<strong>Kavel: </strong>" . niki_range_to_string( "", $value, "m<sup>2</sup>" ) . "<br />", PHP_EOL;
				}
			}

			$value = $housetype ['roomcount-range'];
			if ( is_array( $value ) ) {
				if ( $value ['max'] != 0 ) {
					echo "<strong>Aantal kamers: </strong>" . niki_range_to_string( "", $value, "" ) . "<br />", PHP_EOL;
				}
			}
			echo '</p>';
			echo "</div>", PHP_EOL; // end id="housetype-overview"

			echo '<h2 class="housetype-detail-subtitle">Beschrijving woningtype</h2>', PHP_EOL;
			echo "<p>", $housetype ['description'], "</p>", PHP_EOL;

			echo '<h2 class="housetype-detail-subtitle">Beschrijving project</h2>', PHP_EOL;
			echo "<p>", $project ['description'], "</p>", PHP_EOL;

			echo '<table id="housetype-pricelist">', PHP_EOL;
			echo '<h2 class="housetype-detail-subtitle">Prijslijst</h2>', PHP_EOL;
			$houses = $housetype ['houses'];
			if ( is_array( $houses ) ) {
				self::log( "writing table for " . count( $houses ) . " houses" );
				echo "<tr>";
				echo "<th>Bouwnr.</th>";
				echo "<th>Status</th>";
				echo "<th>Oppervlakte</th>";
				echo "<th>Kavel</th>";
				echo "<th>Aantal kamers</th>";
				echo "<th>Prijs</th>";
				echo "<th>Plattegrond</th>";
				echo "<th>Opmerkingen</th>";
				echo "</tr>", PHP_EOL;
				foreach ( $houses as $house ) {
					if ( WP_DEBUG ) {
						foreach ( $house as $field => $value ) {
							self::log( "house [$field] => " . str_replace( "\n", "&para;", print_r( $value, true ) ) );
						}
					}
					echo "<tr>";

					// Bouwnr.
					echo "<td>{$house ['build-id']}</td>";

					// Status
					echo "<td>{$house ['status']}</td>";

					// Oppervlakte
					$dimensions = $house ['dimensions'];
					if ( $dimensions ['living-surface'] > 0 ) {
						echo "<td>{$dimensions ['living-surface']}m<sup>2</sup></td>";
					} else {
						echo "<td>&nbsp;</td>";
					}

					// Kavel
					if ( $dimensions ['ground-surface'] > 0 ) {
						echo "<td>{$dimensions ['ground-surface']}m<sup>2</sup></td>";
					} else {
						echo "<td>&nbsp;</td>";
					}

					// Aantal kamers
					echo "<td>{$house ['room-count']}</td>";

					// Prijs
					$price = "";
					if ( isset( $house ['price-range'] ) ) {
						$price = $house ['price-range'];
						if ( is_array( $price ) && ( $price ['max'] != 0 ) ) {
							$price = niki_range_to_string( "&euro;&nbsp;", $price, "" );
						} else {
							$price = "";
						}
						if ( isset( $house ['price-condition'] ) ) {
							$condition = $house ['price-condition'];
							if ( is_array( $condition ) ) {
								if ( isset( $condition ['abbreviation'] ) ) {
									$price .= " " . $condition ['abbreviation'];
								} else if ( isset( $condition ['name'] ) ) {
									$price .= " " . $condition ['name'];
								}
							}
						}
					}
					if ( $price === "" ) {
						$price = "&nbsp;";
					}
					echo "<td>{$price}</td>";

					// Plattegrond
					if ( isset( $house ['floorplans'] ) ) {
						$floorplans = $house ['floorplans'];
						$links = array ();
						if ( is_array( $floorplans ) ) {
							foreach ( $floorplans as $floorplan ) {
								$link = $floorplan ['link'];
								// link is of form '/files/{file_id}'
								$id = explode( "/", trim( $link, "/" ) );
								$id = $id [1];
								$href = get_bloginfo( 'wpurl' ) . self::$file_base_link . "/$id";
								self::log( "hyperlink for plattegrond: '$href'" );
								$links [] = '<a href="' . $href . '" target="_blank">plattegrond ' . ( count( $links ) + 1 ) . '</a>';
							}
						}
					}
					if ( count( $links ) > 0 ) {
						echo "<td>" . implode( "<br />", $links ) . "</td>";
					} else {
						echo "<td>&nbsp;</td>";
					}

					// Opmerkingen
					if ( isset( $house ['characteristics'] ) && is_array( $house ['characteristics'] ) && ( count( $house ['characteristics'] ) > 0 ) ) {
						echo "<td><ul><li>" . implode( "</li><li>", $house ['characteristics'] ) . "</li></ul></td>";
					} else {
						echo "<td>&nbsp;</td>";
					}

					echo "</tr>", PHP_EOL;
				}
			}

			echo "</table>", PHP_EOL;
		} else {
			echo "<p>Kon de informatie over dit woningtype niet vinden.</p>";
		}

		echo '<h2 class="housetype-detail-subtitle">Projectinfo: </h2>';
		echo '<p>';
		echo "Status: ".$project['status']."<br />";
		echo "Voortgang: ".$project['progress']. "<br />";
		echo "Type: ".$project['realestate']. "<br />";

		// Brochures
		// echo "<h2>Brochures for $projectlink</h2>";
		// @todo: implement

		// Maps
		// echo "<h2>Maps for $projectlink</h2>";
		// @todo: implement
		echo '</p>';

		// Developers
		echo '<h2 class="housetype-detail-subtitle">Developers</h2>';
		$developers = $niki->get_developers( $project_link, array());
		foreach ( $developers as $developer ) {
			echo '<p>';
			echo "<b>Developer:</b><br/>";
			echo '</p>';
			echo '<ul class="housetype-party-details">';
			foreach ( $developer as $key => $value) {
				if( ! is_array($value)){
					echo "<li>".$key." : ".$value."</li>";
				}
			}
			echo "</ul>";
		}

		// Brokers
		echo '<h2 class="housetype-detail-subtitle">Brokers</h2>';
		$brokers = $niki->get_brokers( $project_link, array());
		foreach ( $brokers as $broker ) {
			echo '<p>';
			echo "<b>Broker:</b><br/>";
			echo '</p>';
			echo '<ul class="housetype-party-details">';
			foreach ( $broker as $key => $value) {
				if( ! is_array($value)){
					echo "<li>".$key." : ".$value."</li>";
				}
			}
			echo "</ul>";
		}

		// Involved parties
		echo '<h2 class="housetype-detail-subtitle">Involved parties</h2>';
		$involvedparties = $niki->get_involvedparties( $project_link	, array());
		foreach ( $involvedparties as $involvedpartie ) {
			echo '<p>';
				echo "<b>Betrokken partij:</b><br/>";
			echo '</p>';
			echo '<ul class="housetype-party-details">';
			foreach ( $involvedpartie as $key => $value) {
				if( ! is_array($value)){
					echo "<li>".$key." : ".$value."</li>";
				}
			}
			echo "</ul>";
		}
		
		echo "</div>", PHP_EOL;
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
