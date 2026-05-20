<?php
/**
 * Lucide icon helper.
 *
 * Renders inline Lucide SVGs (https://lucide.dev, MIT) so PHP-rendered markup —
 * frontend templates and admin meta boxes — uses the design-system icon set
 * instead of Dashicons. Paths are vendored here (never CDN). Stroke 1.75 per the
 * ux-foundation icon spec.
 *
 * @package MediaShield\Support
 */

namespace MediaShield\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Icons {

	/**
	 * Vendored Lucide path markup, keyed by icon name.
	 *
	 * @var array<string, string>
	 */
	private const PATHS = array(
		'maximize'     => '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
		'video'        => '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>',
		'download'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
		'clipboard'    => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
		'check'        => '<path d="M20 6 9 17l-5-5"/>',
		'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
		'lock'         => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'cloud'        => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
	);

	/**
	 * Return an inline Lucide SVG string.
	 *
	 * @param string               $name Icon name (see self::PATHS keys).
	 * @param array<string, mixed> $args size (int px, default 20), class (string),
	 *                                   label (string — sets role=img+aria-label,
	 *                                   otherwise aria-hidden).
	 * @return string SVG markup, or empty string for an unknown icon.
	 */
	public static function svg( string $name, array $args = array() ): string {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}

		$size  = isset( $args['size'] ) ? (int) $args['size'] : 20;
		$class = 'ms-icon' . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' );
		$label = isset( $args['label'] ) ? (string) $args['label'] : '';
		$a11y  = '' !== $label
			? sprintf( 'role="img" aria-label="%s"', esc_attr( $label ) )
			: 'aria-hidden="true" focusable="false"';

		return sprintf(
			'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" %3$s>%4$s</svg>',
			esc_attr( $class ),
			$size,
			$a11y,
			self::PATHS[ $name ]
		);
	}
}
