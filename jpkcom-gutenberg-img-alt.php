<?php
/*
Plugin Name: JPKCom Gutenberg Image Block Alt-Attribute
Plugin URI: https://github.com/JPKCom/jpkcom-gutenberg-img-alt
Description: SEO-friendly, dynamic updates for image block alt-attribute texts.
Version: 1.2.0
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Gutenberg, SEO, Image, Block
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: jpkcom-gutenberg-img-alt
Domain Path: /languages
*/

declare(strict_types=1);

if ( ! defined( constant_name: 'WPINC' ) ) {
        die;
}


/**
 * Plugin Constants
 *
 * @since 1.0.2
 */
if ( ! defined( 'JPKCOM_GUTENBERG_IMG_ALT_VERSION' ) ) {
    define( 'JPKCOM_GUTENBERG_IMG_ALT_VERSION', '1.2.0' );
}


/**
 * Initialize Plugin Updater
 *
 * Loads and initializes the GitHub-based plugin updater with SHA256 checksum verification.
 *
 * @since 1.0.2
 *
 * @return void
 */
add_action( 'init', static function (): void {
    $updater_file = plugin_dir_path( __FILE__ ) . 'includes/class-plugin-updater.php';

    if ( file_exists( $updater_file ) ) {
        require_once $updater_file;

        if ( class_exists( 'JPKComGutenbergImgAltGitUpdate\\JPKComGitPluginUpdater' ) ) {
            new \JPKComGutenbergImgAltGitUpdate\JPKComGitPluginUpdater(
                plugin_file: __FILE__,
                current_version: JPKCOM_GUTENBERG_IMG_ALT_VERSION,
                manifest_url: 'https://jpkcom.github.io/jpkcom-gutenberg-img-alt/plugin_jpkcom-gutenberg-img-alt.json'
            );
        }
    }
}, 5 );

/**
 * Load the Abilities API integration
 *
 * @since 1.1.0
 */
$jpkcomGutenbergImgAltAbilities = plugin_dir_path( __FILE__ ) . 'includes/abilities.php';

if ( file_exists( filename: $jpkcomGutenbergImgAltAbilities ) ) {

    require_once $jpkcomGutenbergImgAltAbilities;

}


/**
 * Set the alt attribute of the block's image to the attachment's alt text.
 *
 * Runs on WordPress' HTML Tag Processor rather than a regular expression. The
 * processor parses the markup the way a browser does, so it finds the real
 * `alt` and not `data-alt`, copes with `>` inside other attribute values and
 * with single-quoted or bare attributes, skips `<img` inside comments and
 * attribute values, and adds `alt` when the image has none. It also writes the
 * value literally and escapes it itself - no backreference can be interpreted.
 *
 * Only the first `<img>` is changed: a core/image block renders exactly one.
 *
 * @since 1.0.8
 * @since 1.2.0 Uses WP_HTML_Tag_Processor; adds a missing alt attribute.
 *
 * @param string $block_content The rendered image block HTML.
 * @param string $alt           Attachment alt text.
 * @return string The block HTML with an updated alt attribute when available.
 */
if ( ! function_exists( 'jpkcom_gutenberg_img_alt_replace_alt_attribute' ) ) {
    function jpkcom_gutenberg_img_alt_replace_alt_attribute( string $block_content, string $alt ): string {
        if ( '' === trim( $alt ) ) {
            return $block_content;
        }

        $tags = new WP_HTML_Tag_Processor( $block_content );

        if ( ! $tags->next_tag( 'img' ) ) {
            return $block_content;
        }

        $tags->set_attribute( 'alt', $alt );

        return $tags->get_updated_html();
    }
}


/**
 * Inject the attachment's alt text into rendered core/image blocks.
 *
 * @since 1.0.0
 * @since 1.2.0 Hooks render_block_core/image; injects only string values.
 *
 * @param string $block_content The rendered block HTML.
 * @param array  $block         The parsed block, including its attributes.
 * @return string The block HTML with an updated alt attribute when available.
 */
add_filter( 'render_block_core/image', function( string $block_content, array $block ): string {

    $image_id = (int) ( $block['attrs']['id'] ?? 0 );

    if ( $image_id > 0 ) {

        $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

        // get_post_meta() unserialises: a value written programmatically as an
        // array or object comes back as one. Casting it would inject "Array".
        if ( is_string( $alt ) && ! empty( $alt ) ) {
            $block_content = jpkcom_gutenberg_img_alt_replace_alt_attribute(
                block_content: $block_content,
                alt: $alt
            );

        }

    }

    return $block_content;

}, 10, 2 );
