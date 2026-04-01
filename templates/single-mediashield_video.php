<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- Template filename must match CPT slug (mediashield_video).
/**
 * Single video page template for mediashield_video CPT.
 *
 * Uses Player\Renderer for API-compatible player output.
 * Includes JSON-LD VideoObject schema for SEO.
 *
 * Compatible with both classic and block themes.
 *
 * @package MediaShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Block theme support: use block_template_part if available.
$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( $is_block_theme ) {
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>

<div class="wp-site-blocks">
	<?php block_template_part( 'header' ); ?>

<main class="wp-block-group is-layout-constrained" style="padding-top: var(--wp--preset--spacing--50); padding-bottom: var(--wp--preset--spacing--50);">
	<?php
} else {
	get_header();
}

while ( have_posts() ) :
	the_post();

	$video_id = get_the_ID();
	$duration = (int) get_post_meta( $video_id, '_ms_duration', true );
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="entry-header<?php echo $is_block_theme ? ' alignwide' : ''; ?>">
			<?php the_title( '<h1 class="entry-title wp-block-post-title">', '</h1>' ); ?>
		</header>

		<div class="entry-content<?php echo $is_block_theme ? ' alignwide' : ''; ?>">
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
		'description' => get_the_excerpt() ? get_the_excerpt() : get_the_title(),
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

	<?php
endwhile;

if ( $is_block_theme ) {
	?>
</main>

	<?php block_template_part( 'footer' ); ?>
</div>

	<?php wp_footer(); ?>
</body>
</html>
	<?php
} else {
	get_footer();
}
