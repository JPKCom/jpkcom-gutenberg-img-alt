<?php
declare(strict_types=1);

if ( ! defined( constant_name: 'WPINC' ) ) {
    define( constant_name: 'WPINC', value: true );
}

// ABSPATH as well, since 1.1.0: the main file now loads includes/abilities.php,
// which exits when ABSPATH is undefined. Without this the require below ended
// the process before a single case ran - exit 0, no output, and a CI job that
// reported success while testing nothing.
if ( ! defined( constant_name: 'ABSPATH' ) ) {
    define( constant_name: 'ABSPATH', value: dirname( path: __DIR__ ) . DIRECTORY_SEPARATOR );
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

// The replacer runs on WP_HTML_Tag_Processor - load the real one.
require_once __DIR__ . '/bootstrap-html-api.php';

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
    // esc_attr() skipped ampersands that already looked like an entity, so an
    // alt text that literally reads "&amp;" was shown to visitors as "&".
    'escapes text that looks like an entity' => [
        'html'     => '<img src="image.jpg" alt="old">',
        'alt'      => 'Tom &amp; Jerry <3',
        'expected' => '<img src="image.jpg" alt="Tom &amp;amp; Jerry &lt;3">',
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
    // Changed in 1.2.0: the regular expression could only rewrite an existing
    // alt attribute. An image without one now gets it.
    'adds alt to an img without an alt attribute' => [
        'html'     => '<img src="image.jpg" class="wp-image-9">',
        'alt'      => 'Beach photo',
        'expected' => '<img alt="Beach photo" src="image.jpg" class="wp-image-9">',
    ],
    'replaces a bare alt attribute' => [
        'html'     => '<img src="image.jpg" alt class="wp-image-9">',
        'alt'      => 'Beach photo',
        'expected' => '<img src="image.jpg" alt="Beach photo" class="wp-image-9">',
    ],
    // Changed in 1.2.0: a core/image block renders exactly one image.
    'changes only the first img' => [
        'html'     => '<img src="a.jpg" alt="A"><img src="b.jpg" alt="B">',
        'alt'      => 'Beach photo',
        'expected' => '<img src="a.jpg" alt="Beach photo"><img src="b.jpg" alt="B">',
    ],
    // The regular expression's greedy [^>]+ ran on to the LAST 'alt="' in the
    // tag and overwrote data-alt, leaving the real alt untouched.
    'sets alt, not an attribute ending in alt' => [
        'html'     => '<img src="image.jpg" alt="old" data-alt="keep">',
        'alt'      => 'Beach photo',
        'expected' => '<img src="image.jpg" alt="Beach photo" data-alt="keep">',
    ],
    // [^>]+ stopped at the > inside the title, so the regex never matched.
    'copes with > inside another attribute' => [
        'html'     => '<img src="image.jpg" title="a > b" alt="old">',
        'alt'      => 'Beach photo',
        'expected' => '<img src="image.jpg" title="a > b" alt="Beach photo">',
    ],
    'replaces a single-quoted alt' => [
        'html'     => "<img src='image.jpg' alt='old'>",
        'alt'      => 'Beach photo',
        'expected' => "<img src='image.jpg' alt=\"Beach photo\">",
    ],
    'matches an upper-case attribute name' => [
        'html'     => '<IMG SRC="image.jpg" ALT="old">',
        'alt'      => 'Beach photo',
        'expected' => '<IMG SRC="image.jpg" alt="Beach photo">',
    ],
    'skips an img inside a comment' => [
        'html'     => '<!-- <img alt="old"> --><img src="image.jpg" alt="real">',
        'alt'      => 'Beach photo',
        'expected' => '<!-- <img alt="old"> --><img src="image.jpg" alt="Beach photo">',
    ],
    'skips an img inside an attribute value' => [
        'html'     => '<figure data-note=\'<img alt="x">\'><img src="image.jpg" alt="real"></figure>',
        'alt'      => 'Beach photo',
        'expected' => '<figure data-note=\'<img alt="x">\'><img src="image.jpg" alt="Beach photo"></figure>',
    ],
    'leaves markup without an img unchanged' => [
        'html'     => '<figure><figcaption>No image</figcaption></figure>',
        'alt'      => 'Beach photo',
        'expected' => '<figure><figcaption>No image</figcaption></figure>',
    ],
];

$failed = 0;

// Every case runs, so a regression shows all it breaks rather than the first.
foreach ( $cases as $name => $case ) {
    $actual = jpkcom_gutenberg_img_alt_replace_alt_attribute(
        block_content: $case['html'],
        alt: $case['alt']
    );

    if ( $actual !== $case['expected'] ) {
        $failed++;
        fwrite( stream: STDERR, data: sprintf(
            "Failed: %s\nExpected: %s\nActual:   %s\n",
            $name,
            $case['expected'],
            $actual
        ) );
    }
}

if ( $failed > 0 ) {
    fwrite( stream: STDERR, data: sprintf( "%d of %d cases failed.\n", $failed, count( $cases ) ) );
    exit( 1 );
}

printf( "OK - %d alt replacement regression tests passed.\n", count( $cases ) );
