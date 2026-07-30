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
    // $2 was the worst manifestation: the replacement string closed the img tag
    // early and leaked the rest of the match into the page as visible text.
    'keeps a later group reference literal' => [
        'html'     => '<figure><img src="image.jpg" alt="" class="wp-image-12"></figure>',
        'alt'      => '$2 Euro saved',
        'expected' => '<figure><img src="image.jpg" alt="$2 Euro saved" class="wp-image-12"></figure>',
    ],
    'keeps backslash group references literal' => [
        'html'     => '<img src="image.jpg" alt="old">',
        'alt'      => 'Version \1 of the logo',
        'expected' => '<img src="image.jpg" alt="Version \1 of the logo">',
    ],
    // Documented behaviour, not an oversight: the plugin rewrites an existing
    // alt attribute, it never adds a missing one.
    'leaves an img without an alt attribute alone' => [
        'html'     => '<img src="image.jpg" class="wp-image-9">',
        'alt'      => 'Beach photo',
        'expected' => '<img src="image.jpg" class="wp-image-9">',
    ],
    'rewrites every img in the block' => [
        'html'     => '<img src="a.jpg" alt="A"><img src="b.jpg" alt="B">',
        'alt'      => 'Beach photo',
        'expected' => '<img src="a.jpg" alt="Beach photo"><img src="b.jpg" alt="Beach photo">',
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
