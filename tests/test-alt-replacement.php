<?php
declare(strict_types=1);

if ( ! defined( constant_name: 'WPINC' ) ) {
    define( constant_name: 'WPINC', value: true );
}

if ( ! function_exists( function: 'add_action' ) ) {
    function add_action( mixed ...$args ): void {}
}

if ( ! function_exists( function: 'add_filter' ) ) {
    function add_filter( mixed ...$args ): void {}
}

if ( ! function_exists( function: 'plugin_dir_path' ) ) {
    function plugin_dir_path( string $file ): string {
        return dirname( path: $file ) . DIRECTORY_SEPARATOR;
    }
}

if ( ! function_exists( function: 'esc_attr' ) ) {
    function esc_attr( string $text ): string {
        return htmlspecialchars( string: $text, flags: ENT_QUOTES | ENT_SUBSTITUTE, encoding: 'UTF-8' );
    }
}

require_once dirname( path: __DIR__ ) . '/jpkcom-gutenberg-img-alt.php';

$cases = [
    'keeps dollar-number alt text literal' => [
        'html'     => '<figure><img src="image.jpg" alt="" class="wp-image-12"></figure>',
        'alt'      => 'Price is $1 for image',
        'expected' => '<figure><img src="image.jpg" alt="Price is $1 for image" class="wp-image-12"></figure>',
    ],
    'escapes attribute text' => [
        'html'     => '<img src="image.jpg" alt="old">',
        'alt'      => 'A "quoted" image & more',
        'expected' => '<img src="image.jpg" alt="A &quot;quoted&quot; image &amp; more">',
    ],
    'leaves empty alt unchanged' => [
        'html'     => '<img src="image.jpg" alt="old">',
        'alt'      => '   ',
        'expected' => '<img src="image.jpg" alt="old">',
    ],
];

foreach ( $cases as $name => $case ) {
    $actual = jpkcom_gutenberg_img_alt_replace_alt_attribute(
        block_content: $case['html'],
        alt: $case['alt']
    );

    if ( $actual !== $case['expected'] ) {
        fwrite( stream: STDERR, data: sprintf(
            "Failed: %s\nExpected: %s\nActual:   %s\n",
            $name,
            $case['expected'],
            $actual
        ) );
        exit( 1 );
    }
}

echo "OK - alt replacement regression tests passed.\n";
