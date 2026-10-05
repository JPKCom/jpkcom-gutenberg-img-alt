<?php
/**
 * Loads WordPress' HTML Tag Processor for the tests, which run without WordPress.
 *
 * The replacer is built on WP_HTML_Tag_Processor, so testing it against a
 * stand-in would only test the stand-in. CI checks the core files out at a
 * pinned commit into .wp/ (see .github/workflows/ci.yml); locally, point
 * WP_INCLUDES_DIR at the wp-includes directory of any WordPress 7.x install.
 *
 * A missing directory fails the run rather than skipping it: a test that cannot
 * load what it is meant to test must not report success.
 *
 * Must be required from the top level of a test file, not from inside a
 * function: html5-named-character-references.php defines a global variable.
 *
 * @package   JPKCom_Gutenberg_Img_Alt
 */

declare(strict_types=1);

$jpkcom_wp_includes = getenv( 'WP_INCLUDES_DIR' ) ?: dirname( __DIR__ ) . '/.wp/wp-includes';

if ( ! is_file( $jpkcom_wp_includes . '/html-api/class-wp-html-tag-processor.php' ) ) {
	fwrite( STDERR, "WordPress HTML API not found in {$jpkcom_wp_includes}.\n"
		. "Set WP_INCLUDES_DIR to a wp-includes directory, e.g.\n"
		. "  WP_INCLUDES_DIR=/path/to/wordpress/wp-includes php tests/test-alt-replacement.php\n" );
	exit( 1 );
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

// Core calls this when an API is misused. Throwing makes such a call fail the
// test instead of passing unnoticed.
if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong( string $function_name, string $message, string $version ): void {
		throw new LogicException( "{$function_name}: {$message}" );
	}
}

// Core's list from kses.php, without the filter. set_attribute() consults it to
// decide between esc_url() and plain entity escaping.
if ( ! function_exists( 'wp_kses_uri_attributes' ) ) {
	function wp_kses_uri_attributes(): array {
		return [ 'action', 'archive', 'background', 'cite', 'classid', 'codebase', 'data', 'formaction', 'href', 'icon', 'longdesc', 'manifest', 'poster', 'profile', 'src', 'usemap', 'xmlns' ];
	}
}

// alt is not a URL attribute, so reaching esc_url() would mean the value went
// through the wrong escaping.
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		throw new LogicException( 'esc_url() reached: alt must not be escaped as a URL.' );
	}
}

// WordPress 7.0's utf8.php calls this from functions.php while loading; 7.1
// no longer does. Same probe as core.
if ( ! function_exists( '_wp_can_use_pcre_u' ) ) {
	function _wp_can_use_pcre_u( mixed $set = null ): bool {
		return 1 === @preg_match( '/^./u', 'a' );
	}
}

require_once $jpkcom_wp_includes . '/utf8.php';
require_once $jpkcom_wp_includes . '/class-wp-token-map.php';
require_once $jpkcom_wp_includes . '/html-api/html5-named-character-references.php';
require_once $jpkcom_wp_includes . '/html-api/class-wp-html-attribute-token.php';
require_once $jpkcom_wp_includes . '/html-api/class-wp-html-span.php';
require_once $jpkcom_wp_includes . '/html-api/class-wp-html-text-replacement.php';
require_once $jpkcom_wp_includes . '/html-api/class-wp-html-decoder.php';
require_once $jpkcom_wp_includes . '/html-api/class-wp-html-tag-processor.php';
