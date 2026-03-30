<?php
/**
 * Single video page template for mediashield_video CPT.
 *
 * Uses Player\Renderer for API-compatible player output.
 * Includes JSON-LD VideoObject schema for SEO.
 *
 * @package MediaShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$video_id = get_the_ID();
	$duration = (int) get_post_meta( $video_id, '_ms_duration', true );
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
		</header>

		<div class="entry-content">
			<?php
			// Render the protected player via adapter system.
			echo \MediaShield\Player\Renderer::render( $video_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			the_content();
			?>
		</div>
	</article>

	<?php
	// JSON-LD VideoObject schema.
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'VideoObject',
		'name'        => get_the_title(),
		'description' => get_the_excerpt() ?: get_the_title(),
		'uploadDate'  => get_the_date( 'c' ),
	);

	if ( $duration > 0 ) {
		$schema['duration'] = 'PT' . $duration . 'S';
	}

	if ( has_post_thumbnail() ) {
		$schema['thumbnailUrl'] = get_the_post_thumbnail_url( $video_id, 'large' );
	}
	?>

	<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ); ?></script>

<?php endwhile;

get_footer();
