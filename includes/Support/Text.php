<?php
/**
 * Plain text from a stored WordPress string.
 *
 * @package MediaShield\Support
 */

namespace MediaShield\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one decode rule for names and titles shown "as written".
 */
class Text {

	/**
	 * A stored title or name as plain text, for React, CSV, email and the watermark.
	 *
	 * WordPress stores "Tom & Jerry" as "Tom &amp; Jerry" and texturizes titles
	 * to numeric entities ("Lesson 3 &#8211; Intro"). wp_specialchars_decode()
	 * leaves numeric entities alone, so they showed raw (Basecamp 10499835721).
	 * The result is text: escape it for its output context as usual.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function plain( string $value ): string {
		return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
