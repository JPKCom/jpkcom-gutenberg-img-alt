<?php
/*
Plugin Name: JPKCom Gutenberg Image Block Alt-Attribute
Plugin URI: https://github.com/JPKCom/jpkcom-gutenberg-img-alt
Description: SEO-friendly, dynamic updates for image block alt-attribute texts.
Version: 1.0.0
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Gutenberg, SEO, Image, Block
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
GitHub Plugin URI: JPKCom/jpkcom-gutenberg-img-alt
Primary Branch: main
*/

if ( ! defined( constant_name: 'WPINC' ) ) {
        die;
}

add_filter( 'render_block', function( $block_content, $block ): mixed {

    if ( $block['blockName'] === 'core/image' && isset( $block['attrs']['id'] ) ) {

        $image_id = $block['attrs']['id'];
        $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

        if ( ! empty( $alt ) ) {

            $block_content = preg_replace(

                pattern: '/(<img[^>]+alt=")[^"]*("[^>]*>)/',
                replacement: '$1' . esc_attr( $alt ) . '$2',
                subject: $block_content

            );

        }

    }

    return $block_content;

}, 10, 2 );
