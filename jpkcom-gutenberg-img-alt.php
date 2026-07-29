<?php
/*
Plugin Name: JPKCom Gutenberg Image Block Alt-Attribute
Plugin URI: https://github.com/JPKCom/jpkcom-gutenberg-img-alt
Description: SEO-friendly, dynamic updates for image block alt-attribute texts.
Version: 1.0.7
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Gutenberg, SEO, Image, Block
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.7
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
    define( 'JPKCOM_GUTENBERG_IMG_ALT_VERSION', '1.0.7' );
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
 * Inject the attachment's alt text into rendered core/image blocks.
 *
 * @since 1.0.0
 *
 * @param string $block_content The rendered block HTML.
 * @param array  $block         The parsed block, including its attributes.
 * @return string The block HTML with an updated alt attribute when available.
 */
add_filter( 'render_block', function( string $block_content, array $block ): string {

    if ( ( $block['blockName'] ?? null ) === 'core/image' && isset( $block['attrs']['id'] ) ) {

        $image_id = (int) $block['attrs']['id'];
        $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

        if ( ! empty( $alt ) ) {

            $block_content = preg_replace(
                pattern: '/(<img[^>]+alt=")[^"]*("[^>]*>)/',
                replacement: '${1}' . esc_attr( $alt ) . '${2}',
                subject: $block_content
            ) ?? $block_content;

        }

    }

    return $block_content;

}, 10, 2 );
