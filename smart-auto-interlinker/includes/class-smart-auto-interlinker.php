<?php
/**
 * Core plugin class.
 *
 * @package SmartAutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_Auto_Interlinker {
	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'maybe_add_links' ) );
	}

	/**
	 * Add internal links to content when keyword is configured.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function maybe_add_links( $content ) {
		$keyword = get_option( 'sai_keyword', '' );
		$keyword = sanitize_text_field( $keyword );

		if ( '' === $keyword ) {
			return $content;
		}

		$escaped_keyword = preg_quote( $keyword, '/' );
		$replacement     = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( home_url( '/' ) ),
			esc_html( $keyword )
		);

		return preg_replace( "/{$escaped_keyword}/", $replacement, $content, 1 );
	}
}
