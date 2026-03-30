<?php
/**
 * Single video page template for mediashield_video CPT.
 *
 * Renders the video with JSON-LD VideoObject schema for SEO.
 *
 * @package MediaShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$video_id   = get_the_ID();
	$platform   = get_post_meta( $video_id, '_ms_platform', true );
	$source_url = get_post_meta( $video_id, '_ms_source_url', true );
	$duration   = (int) get_post_meta( $video_id, '_ms_duration', true );
	$protection = get_post_meta( $video_id, '_ms_protection_level', true ) ?: 'standard';
	$player_type = apply_filters( 'mediashield_player_type', 'standard', $video_id );
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
		</header>

		<div class="entry-content">
			<?php if ( $source_url ) : ?>
				<div class="ms-protected-player"
					data-video-id="<?php echo esc_attr( $video_id ); ?>"
					data-platform="<?php echo esc_attr( $platform ); ?>"
					data-protection-level="<?php echo esc_attr( $protection ); ?>"
					data-player-type="<?php echo esc_attr( $player_type ); ?>">
					<div class="ms-player-inner">
						<?php if ( 'self' === $platform ) : ?>
							<video controls controlsList="nodownload" preload="metadata">
								<source src="<?php echo esc_url( $source_url ); ?>" type="video/mp4">
							</video>
						<?php else : ?>
							<iframe src="<?php echo esc_url( $source_url ); ?>"
								frameborder="0"
								allow="autoplay; fullscreen; picture-in-picture"
								allowfullscreen>
							</iframe>
						<?php endif; ?>
					</div>
					<canvas class="ms-watermark-canvas"></canvas>
					<div class="ms-protection-overlay"></div>
				</div>
			<?php endif; ?>

			<?php the_content(); ?>
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

	if ( $source_url && 'self' === $platform ) {
		$schema['contentUrl'] = $source_url;
	}
	?>

	<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ); ?></script>

<?php endwhile;

get_footer();
