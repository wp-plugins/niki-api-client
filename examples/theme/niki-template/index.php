<?php
/**
 * The main template file
 *
 */

// provide 'Niki Template Log', writing log messages to the WordPress log (file, usually located at /wp-content/debug.log)
if ( !function_exists( "niki_tl" ) ) {
	function niki_tl( $msg ) {
		if ( WP_DEBUG ) {
			if ( is_string( $msg ) ) {
				error_log( "niki template: $msg" );
			} else {
				error_log( print_r( $msg, true ) );
			}
		}
	}
}
niki_tl( "main index.php start" );

get_header();

?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<?php
			$niki_page = get_query_var( "niki-page", false );
			niki_tl( "niki-page: $niki_page" );

			if ( !isset( $niki ) ) {
				wp_die("<p>De Niki API Client plugin is niet goed geïnstalleerd.</p>");
			}

			if ( false !== $niki_page ) {
				niki_tl( "including niki functions from path: " . $niki->get_plugin_path() . "examples/niki-functions.php" );
				require_once $niki->get_plugin_path() . "examples/niki-functions.php";

				// depending on the niki_page specified, call one of the plugin functions in /examples/niki-functions.php
				switch ( $niki_page ) {
					case "aanbod":
						niki_show_aanbod();
						break;
					case "woningtype":
						$developer_id = get_query_var( "niki-var1", false );
						niki_tl( "developer_id from niki-var1: '$developer_id'" );
						$project_id = get_query_var( "niki-var2", false );
						niki_tl( "project_id from niki-var2: '$project_id'" );
						$housetype_id = get_query_var( "niki-var3", false );
						niki_tl( "housetype_id from niki-var3: '$housetype_id'" );
						$project_link = "/projects/$developer_id/$project_id";
						if ( ( false !== $developer_id ) && ( false !== $project_id ) && ( false !== $housetype_id ) ) {
							niki_tl( "showing woningtype (project: '$project_link'; housetype: '$housetype_id'" );
							niki_show_woningtype( $project_link, $housetype_id );
						}
						break;
					case "interesse":
						niki_show_interesse();
						break;
					default:
						$niki_page = false;
				}
			}
			if ( false === $niki_page ) {
				if ( have_posts() ) {
					if ( is_home() && ! is_front_page() ) {
						?>
				<header>
		<h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
	</header>
						<?php
					}

					// Start the loop.
					while ( have_posts() ) : the_post();

						/*
						 * Include the Post-Format-specific template for the content.
						 * If you want to override this in a child theme, then include a file
						 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
						 */
						get_template_part( 'content', get_post_format() );

					// End the loop.
					endwhile;


					// Previous/next page navigation.
					the_posts_pagination( array(
						'prev_text'          => __( 'Previous page', 'twentyfifteen' ),
						'next_text'          => __( 'Next page', 'twentyfifteen' ),
						'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'twentyfifteen' ) . ' </span>',
					) );
				} else {
					// If no content, include the "No posts found" template.
					get_template_part( 'content', 'none' );
				}
			}
		?>

		</main>
	<!-- .site-main -->
</div>
<!-- .content-area -->

<?php get_footer(); ?>
