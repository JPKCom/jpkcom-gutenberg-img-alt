<?php
/**
 * Guards for the Abilities API registration.
 *
 * The load-bearing check is the last group: the predicate the ability reports by
 * must give the same answer as the injection actually gives, for every awkward
 * value. Both are executed here and compared - not read and judged to look
 * alike. A paraphrase that looked right is how the sibling plugin got its
 * `listed` flag to disagree with its own SQL.
 *
 * @package   JPKCom_Gutenberg_Img_Alt
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
	return $value;
}

function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted = 1 ): bool {
	return true;
}

function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted = 1 ): bool {
	return true;
}

function plugin_dir_path( string $file ): string {
	return dirname( $file ) . '/';
}

// A port of core's is_serialized() (strict) and maybe_unserialize(). The
// predicate and the reason both go through it, and the injection's input is
// what get_post_meta() returns - so the comparison below needs the real rule,
// not a looser stand-in that would agree with anything.
function is_serialized( mixed $data, bool $strict = true ): bool {
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( 'N;' === $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 || ':' !== $data[1] ) {
		return false;
	}
	$last = substr( $data, -1 );
	if ( $strict && ';' !== $last && '}' !== $last ) {
		return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's':
			if ( $strict && '"' !== substr( $data, -2, 1 ) ) {
				return false;
			}
			// No break.
		case 'a':
		case 'O':
		case 'E':
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b':
		case 'i':
		case 'd':
			return (bool) preg_match( "/^{$token}:[0-9.E+-]+;\$/", $data );
	}
	return false;
}

function maybe_unserialize( mixed $data ): mixed {
	if ( is_serialized( $data ) ) {
		return @unserialize( trim( $data ) );
	}
	return $data;
}

define( 'WPINC', 'wp-includes' );

$root = dirname( __DIR__ );

require_once $root . '/includes/abilities.php';

// The replacer runs on WP_HTML_Tag_Processor; the comparison below executes it,
// so the real class has to be there.
require_once __DIR__ . '/bootstrap-html-api.php';

// The main file is what defines the replacer whose behaviour the predicate has
// to agree with. Requiring it also proves the two are loadable together.
require_once $root . '/jpkcom-gutenberg-img-alt.php';

$pass = 0;
$fail = 0;

function section( string $title ): void {
	echo "\n" . $title . "\n";
}

function chk( string $label, bool $ok, string $why = '' ): void {
	global $pass, $fail;

	if ( $ok ) {
		$pass++;
		echo "  PASS  {$label}\n";
		return;
	}

	$fail++;
	echo "  FAIL  {$label}\n";

	if ( $why !== '' ) {
		echo "        why:  {$why}\n";
	}
}

$defs = jpkcom_gutenberg_img_alt_get_ability_definitions();
$name = 'jpkcom-gutenberg-img-alt/list-images-missing-alt';
$src  = (string) file_get_contents( $root . '/includes/abilities.php' );

// --- Registration shape -----------------------------------------------------

section( 'Registration shape' );

chk( 'exactly one ability is defined', count( $defs ) === 1, 'Got ' . count( $defs ) . '.' );

chk(
	'it is the documented name',
	array_key_first( $defs ) === $name,
	'Ability names are a public contract; renaming one breaks every caller that stored it.'
);

$args = $defs[ $name ] ?? [];
$ann  = $args['meta']['annotations'] ?? [];

chk(
	'all three annotations set explicitly',
	( $ann['readonly'] ?? null ) === true && ( $ann['destructive'] ?? null ) === false && ( $ann['idempotent'] ?? null ) === true,
	'They default to null, and the REST run controller derives the HTTP verb from them: without readonly the run route is POST-only.'
);

chk(
	'NOT registered into the shared content category',
	( $args['category'] ?? null ) === 'jpkcom-media',
	'This reports an editorial gap in the media library, not published content, and it is gated differently from the three content plugins.'
);

// --- Input schema -----------------------------------------------------------

section( 'Input schema' );

$schema = $args['input_schema'] ?? [];

chk(
	'carries a top-level default',
	array_key_exists( 'default', $schema ),
	'normalize_input() substitutes the TOP-LEVEL default when the input is exactly null, and nothing else does. Without it the most obvious call - no parameters at all - fails validation before the callback runs.'
);

chk(
	'the default encodes as {} and not []',
	json_encode( $schema['default'] ?? null ) === '{}',
	'The schema declares type: object. PHP serialises an empty array as a JSON array, and the MCP adapter publishes get_input_schema() verbatim.'
);

chk(
	'does NOT declare additionalProperties',
	! array_key_exists( 'additionalProperties', $schema ),
	'validate_input() runs BEFORE the execute callback, so declaring it preempts the plugin guard and replaces a message naming the accepted keys with core\'s "not a valid property of the object".'
);

$declared = array_keys( (array) ( $schema['properties'] ?? [] ) );
$guarded  = JPKCOM_GUTENBERG_IMG_ALT_ABILITY_INPUT_KEYS[ $name ] ?? null;
sort( $declared );
if ( is_array( $guarded ) ) { sort( $guarded ); }

chk(
	'guarded input keys match the schema properties',
	$guarded === $declared,
	'Two statements of one list. A key only in the schema is refused although it is declared to callers; a key only in the constant is waved through and read by nobody. Got ' . var_export( $guarded, true ) . ', schema declares ' . var_export( $declared, true ) . '.'
);

// --- Structure --------------------------------------------------------------

section( 'Structure' );

function body_of( string $source, string $needle ): ?string {
	$start = strpos( $source, $needle );

	if ( $start === false ) {
		return null;
	}

	$open  = strpos( $source, '{', $start );
	$depth = 0;

	for ( $i = $open, $len = strlen( $source ); $i < $len; $i++ ) {
		if ( $source[ $i ] === '{' ) {
			$depth++;
		} elseif ( $source[ $i ] === '}' ) {
			$depth--;
			if ( $depth === 0 ) {
				return substr( $source, $open, $i - $open );
			}
		}
	}

	return null;
}

chk(
	'the callback calls the unknown-key guard',
	str_contains( (string) body_of( $src, 'function jpkcom_gutenberg_img_alt_ability_list_inner(' ), 'jpkcom_gutenberg_img_alt_ability_validate_input_keys(' ),
	'A key the ability does not declare is never read, so without this the request is answered as though it had not been sent.'
);

chk(
	'the callback runs inside the Throwable boundary',
	str_contains( (string) body_of( $src, 'function jpkcom_gutenberg_img_alt_ability_list(' ), 'jpkcom_gutenberg_img_alt_ability_boundary(' ),
	'On the declared floor a Throwable escaping an ability callback is an error the client cannot act on.'
);

chk(
	'the permission callback resolves to a capability check',
	str_contains( (string) body_of( $src, 'function jpkcom_gutenberg_img_alt_ability_permission(' ), 'jpkcom_gutenberg_img_alt_ability_capability(' ),
	'The argument an ability permission callback receives is the validated input value, never a request object, so nothing built around WP_REST_Request can gate here.'
);

chk(
	'the default capability is upload_files, not read',
	str_contains( (string) body_of( $src, 'function jpkcom_gutenberg_img_alt_ability_capability(' ), "'upload_files'" ),
	'This enumerates the media library, where file names and unattached uploads are editorial internals. `read` would let every subscriber walk it.'
);

chk(
	'the SQL scopes to image attachments that are inherit or publish',
	str_contains( $src, "p.post_type = 'attachment'" )
		&& str_contains( $src, "p.post_mime_type LIKE 'image/%%'" )
		&& str_contains( $src, "p.post_status IN ( 'inherit', 'publish' )" ),
	'Without the status scope the answer would include trashed and private uploads, which nobody is publishing and which are therefore not a gap worth reporting.'
);

// --- The predicate must agree with the injection ----------------------------

section( 'The predicate agrees with the injection itself' );

chk(
	'the replacer is available to compare against',
	function_exists( 'jpkcom_gutenberg_img_alt_replace_alt_attribute' ),
	'Without it the group below would silently test nothing.'
);

$cases = [
	'no value'           => null,
	'empty string'       => '',
	'the character 0'    => '0',
	'spaces only'        => '   ',
	'tab and newline'    => "\t\n",
	'ordinary text'      => 'Ein Hund im Schnee',
	'text starting in 0' => '0 Grad',
	'the string false'   => 'false',
	'a numeric string'   => '42',
	// Raw meta_value rows as the database holds them. get_post_meta() hands the
	// filter the unserialised value, so these are where a raw-row check and the
	// injection part ways.
	'a serialised array'  => serialize( [ 'Ein Hund' ] ),
	'a serialised object' => serialize( (object) [ 'alt' => 'Ein Hund' ] ),
	'a serialised int'    => 'i:42;',
	'a serialised false'  => 'b:0;',
	'a serialised null'   => 'N;',
	'a serialised string' => serialize( 'Ein Hund im Schnee' ),
	'text that only looks serialised' => 'a:1: Hund',
];

$html = '<figure><img src="x.png" alt="PLACEHOLDER"/></figure>';
$disagreements = 0;

foreach ( $cases as $label => $value ) {

	// What the plugin actually does: get_post_meta() unserialises the row, the
	// filter gates on is_string() and ! empty(), and the replacer then returns
	// the content unchanged when the value trims to nothing. All of it together
	// is the rule.
	$meta = maybe_unserialize( $value );

	$actually_injects = is_string( $meta ) && ! empty( $meta )
		&& jpkcom_gutenberg_img_alt_replace_alt_attribute( $html, $meta ) !== $html;

	$predicate = jpkcom_gutenberg_img_alt_would_inject( $value );

	if ( $predicate !== $actually_injects ) {
		$disagreements++;
	}

	chk(
		'predicate agrees for ' . $label,
		$predicate === $actually_injects,
		'The predicate says ' . var_export( $predicate, true ) . ' and the injection does ' . var_export( $actually_injects, true )
			. '. Reporting an image as supplied when nothing is injected for it is the one answer this ability must never give, because it is the answer that stops someone looking.'
	);

}

chk(
	'the reason names the awkward values rather than lumping them together',
	jpkcom_gutenberg_img_alt_ability_reason( '0', true ) === 'zero'
		&& jpkcom_gutenberg_img_alt_ability_reason( '   ', true ) === 'whitespace_only'
		&& jpkcom_gutenberg_img_alt_ability_reason( '', true ) === 'empty'
		&& jpkcom_gutenberg_img_alt_ability_reason( null, false ) === 'no_row'
		&& jpkcom_gutenberg_img_alt_ability_reason( serialize( [ 'x' ] ), true ) === 'not_text',
	'A fixer needs to know what is there. "0" and "   " look set in the admin and are not, and saying only "missing" sends someone looking at a field that is visibly filled.'
);

printf( "\n  %d passed, %d failed\n", $pass, $fail );

exit( $fail > 0 ? 1 : 0 );
