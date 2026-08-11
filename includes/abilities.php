<?php
/**
 * WordPress Abilities API integration.
 *
 * Registers one read-only ability: which image attachments this plugin's filter
 * would find nothing to inject for.
 *
 * That is a narrower question than "which images have no alt text", and it is
 * the one worth answering here. The filter overwrites a rendered core/image
 * block's alt with the ATTACHMENT's alt, so the attachment is the single source
 * of truth: fixing one attachment fixes every block that uses it, everywhere,
 * with no post to re-save. What the mechanism cannot do is invent an alt where
 * the attachment has none — and that residual gap is exactly what this reports.
 *
 * @package   JPKCom_Gutenberg_Img_Alt
 * @author    Jean Pierre Kolb <jpk@jpkc.com>
 * @license   GPL-2.0-or-later
 * @link      https://github.com/JPKCom/jpkcom-gutenberg-img-alt
 */

declare(strict_types=1);

if ( ! defined( constant_name: 'ABSPATH' ) ) {

	exit;

}


if ( ! defined( constant_name: 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_CATEGORY' ) ) {

	/**
	 * Ability category.
	 *
	 * NOT the jpkcom-content category the three content plugins share: this
	 * reports an editorial gap in the media library, not published content, and
	 * it is gated differently. Categories are global and first-wins, so
	 * registration goes through wp_has_ability_category() either way.
	 *
	 * @since 1.1.0
	 */
	define( 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_CATEGORY', 'jpkcom-media' );

}

if ( ! defined( constant_name: 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_DEFAULT' ) ) {

	/**
	 * Default page size.
	 *
	 * @since 1.1.0
	 */
	define( 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_DEFAULT', 20 );

}

if ( ! defined( constant_name: 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_MAX' ) ) {

	/**
	 * Largest page size honoured.
	 *
	 * @since 1.1.0
	 */
	define( 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_MAX', 100 );

}

if ( ! defined( constant_name: 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PAGE_MAX' ) ) {

	/**
	 * Highest page number accepted.
	 *
	 * Derived rather than picked: the offset is a plain integer multiplication,
	 * and past PHP_INT_MAX it becomes a float that collapses to 0 - handing back
	 * page one's rows labelled as a page far beyond the last.
	 *
	 * @since 1.1.0
	 */
	define(
		'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PAGE_MAX',
		intdiv( num1: PHP_INT_MAX, num2: max( 1, JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_MAX ) )
	);

}

if ( ! defined( constant_name: 'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_INPUT_KEYS' ) ) {

	/**
	 * Top-level input keys the ability declares.
	 *
	 * Cross-checked against the registered schema by tests/test-abilities.php.
	 * additionalProperties is deliberately not declared on a schema that carries
	 * properties: validate_input() runs before the execute callback and would
	 * preempt the guard, replacing a message naming the accepted keys with core's
	 * "not a valid property of the object".
	 *
	 * @since 1.1.0
	 */
	define(
		'JPKCOM_GUTENBERG_IMG_ALT_ABILITY_INPUT_KEYS',
		[
			'jpkcom-gutenberg-img-alt/list-images-missing-alt' => [ 'page', 'per_page' ],
		]
	);

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_abilities_enabled' ) ) {

	/**
	 * Decide whether the ability should be registered at all.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True when registration should proceed.
	 */
	function jpkcom_gutenberg_img_alt_abilities_enabled(): bool {

		if ( defined( constant_name: 'JPKCOM_GUTENBERG_IMG_ALT_ABILITIES' ) && ! JPKCOM_GUTENBERG_IMG_ALT_ABILITIES ) {

			return false;

		}

		return function_exists( function: 'wp_register_ability' )
			&& function_exists( function: 'wp_register_ability_category' );

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_log' ) ) {

	/**
	 * Write a debug line, and only with WP_DEBUG.
	 *
	 * @since 1.1.0
	 *
	 * @param string $message Message.
	 * @return void
	 */
	function jpkcom_gutenberg_img_alt_ability_log( string $message ): void {

		if ( defined( constant_name: 'WP_DEBUG' ) && WP_DEBUG ) {

			error_log( message: '[jpkcom-gutenberg-img-alt] ' . $message );

		}

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_error' ) ) {

	/**
	 * Build a WP_Error carrying an HTTP status.
	 *
	 * Without data['status'] rest_ensure_response() defaults to 500, and a 5xx
	 * tells an agent "transient fault, retry unchanged" - the opposite of what a
	 * caller mistake needs to hear.
	 *
	 * @since 1.1.0
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error Error.
	 */
	function jpkcom_gutenberg_img_alt_ability_error( string $code, string $message, int $status = 400 ): WP_Error {

		return new WP_Error( $code, $message, [ 'status' => $status ] );

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_boundary' ) ) {

	/**
	 * Turn a Throwable out of the callback into a WP_Error.
	 *
	 * @since 1.1.0
	 *
	 * @param callable $body    Callback.
	 * @param string   $ability Ability name.
	 * @return array<string, mixed>|WP_Error Result or error.
	 */
	function jpkcom_gutenberg_img_alt_ability_boundary( callable $body, string $ability ): array|WP_Error {

		try {

			return $body();

		} catch ( \Throwable $e ) {

			jpkcom_gutenberg_img_alt_ability_log( $ability . ' failed: ' . $e->getMessage() );

			return jpkcom_gutenberg_img_alt_ability_error(
				'jpkcom_gutenberg_img_alt_read_failed',
				__( 'The media library could not be read on this site. This is a condition on the site, not a problem with the request, so repeating the call unchanged will not help; the details are in the site error log.', 'jpkcom-gutenberg-img-alt' ),
				500
			);

		}

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_capability' ) ) {

	/**
	 * Check the capability required to run the ability.
	 *
	 * `upload_files`, NOT `read` like the sibling content plugins. Those publish
	 * content the site already shows to visitors; this enumerates the media
	 * library, where file names and unattached uploads are editorial internals.
	 * The capability that means "works with media" is the honest gate, and it
	 * excludes subscribers.
	 *
	 * @since 1.1.0
	 *
	 * @param string $ability Ability name.
	 * @return bool True when the current user may run it.
	 */
	function jpkcom_gutenberg_img_alt_ability_capability( string $ability ): bool {

		/**
		 * Filter the capability required to run the ability.
		 *
		 * @since 1.1.0
		 *
		 * @param string $capability Capability name.
		 * @param string $ability    Ability name.
		 */
		$capability = apply_filters( 'jpkcom_gutenberg_img_alt_ability_capability', 'upload_files', $ability );

		return current_user_can( is_string( value: $capability ) ? $capability : 'upload_files' );

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_meta' ) ) {

	/**
	 * Build the meta array for the ability.
	 *
	 * All three annotations explicit: they default to null and the REST run
	 * controller derives the HTTP verb from them, so without readonly the run
	 * route would be POST-only.
	 *
	 * @since 1.1.0
	 *
	 * @param string $ability Ability name.
	 * @return array<string, mixed> Meta array.
	 */
	function jpkcom_gutenberg_img_alt_ability_meta( string $ability ): array {

		$meta = [
			'show_in_rest' => true,
			'public'       => true,
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
		];

		/**
		 * Filter the meta array of the ability.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, mixed> $meta    Meta array.
		 * @param string               $ability Ability name.
		 */
		$filtered = apply_filters( 'jpkcom_gutenberg_img_alt_ability_meta', $meta, $ability );

		return is_array( value: $filtered ) ? $filtered : $meta;

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_normalise_input' ) ) {

	/**
	 * Bring the value core hands the callback into array form.
	 *
	 * normalize_input() substitutes the schema's top-level default when the input
	 * is exactly null, and that default is a stdClass - so the callback receives
	 * an object and must read it. A callback that only accepts an array answers
	 * 400 to the most obvious call it has.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>|null Array form, or null when unusable.
	 */
	function jpkcom_gutenberg_img_alt_ability_normalise_input( mixed $input ): ?array {

		if ( $input === null ) {

			return [];

		}

		if ( is_object( value: $input ) ) {

			return get_object_vars( object: $input );

		}

		return is_array( value: $input ) ? $input : null;

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_validate_input_keys' ) ) {

	/**
	 * Refuse a top-level input key the ability does not declare.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $input   Raw input.
	 * @param string[]             $allowed Declared keys.
	 * @return true|WP_Error True when every key is declared.
	 */
	function jpkcom_gutenberg_img_alt_ability_validate_input_keys( array $input, array $allowed ): true|WP_Error {

		$unknown = [];

		foreach ( array_keys( $input ) as $key ) {

			if ( ! in_array( needle: (string) $key, haystack: $allowed, strict: true ) ) {

				$unknown[] = (string) $key;

			}

		}

		if ( $unknown === [] ) {

			return true;

		}

		return jpkcom_gutenberg_img_alt_ability_error(
			'jpkcom_gutenberg_img_alt_unknown_input_key',
			sprintf(
				/* translators: 1: comma-separated rejected keys, 2: comma-separated accepted keys. */
				__( 'Unknown input key: %1$s. This ability accepts: %2$s. A key it does not declare is never read, so the request would be answered as though that key had not been sent.', 'jpkcom-gutenberg-img-alt' ),
				implode( ', ', $unknown ),
				implode( ', ', $allowed )
			),
			400
		);

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_would_inject' ) ) {

	/**
	 * Would this plugin's filter inject an alt attribute for this stored value?
	 *
	 * THE load-bearing function of this file, and deliberately not a paraphrase
	 * of the rule. The filter gates on `! empty( $alt )` and the replacer then
	 * returns the block unchanged when `'' === trim( $alt )`. Two consequences
	 * follow that a reasonable-looking check would get wrong:
	 *
	 * - An alt of the single character "0" is EMPTY to PHP, so nothing is
	 *   injected. A check for `$alt !== ''` would call that image supplied.
	 * - An alt of only spaces passes `! empty()` but is stopped by the trim in
	 *   the replacer. A check that mirrored only the first gate would call that
	 *   image supplied too.
	 *
	 * Reporting an image as fine when the mechanism has nothing to inject for it
	 * is the one answer this ability must never give, because it is the answer
	 * that stops someone looking.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $stored Raw stored alt value.
	 * @return bool True when the filter would inject.
	 */
	function jpkcom_gutenberg_img_alt_would_inject( mixed $stored ): bool {

		if ( empty( $stored ) ) {

			return false;

		}

		return trim( string: (string) $stored ) !== '';

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_reason' ) ) {

	/**
	 * Name why the filter would inject nothing, so a fixer knows what is there.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $stored    Raw stored value.
	 * @param bool  $row_found Whether a meta row exists at all.
	 * @return string One of no_row, empty, zero, whitespace_only.
	 */
	function jpkcom_gutenberg_img_alt_ability_reason( mixed $stored, bool $row_found ): string {

		if ( ! $row_found ) {

			return 'no_row';

		}

		$value = (string) $stored;

		if ( $value === '' ) {

			return 'empty';

		}

		if ( trim( string: $value ) === '' ) {

			return 'whitespace_only';

		}

		// Reached only for values that are falsy but not blank - "0" is the only
		// one a string can produce.
		return 'zero';

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_permission' ) ) {

	/**
	 * Permission callback.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $input Validated input, unused.
	 * @return bool True when the current user may run the ability.
	 */
	function jpkcom_gutenberg_img_alt_ability_permission( mixed $input = null ): bool {

		return jpkcom_gutenberg_img_alt_ability_capability( 'jpkcom-gutenberg-img-alt/list-images-missing-alt' );

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_list_inner' ) ) {

	/**
	 * List the image attachments the filter would find nothing to inject for.
	 *
	 * Queried with one statement rather than by walking the library in PHP: the
	 * predicate needs TRIM(), which no meta_query comparison expresses, and a
	 * page-by-page PHP filter would make `total` a guess.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error Result.
	 */
	function jpkcom_gutenberg_img_alt_ability_list_inner( mixed $input = null ): array|WP_Error {

		global $wpdb;

		$normalised = jpkcom_gutenberg_img_alt_ability_normalise_input( $input );

		if ( $normalised === null ) {

			return jpkcom_gutenberg_img_alt_ability_error(
				'jpkcom_gutenberg_img_alt_invalid_input',
				__( 'The input has to be an object, for example {"per_page": 50}. Both parameters are optional; calling this ability with no input at all returns the first page.', 'jpkcom-gutenberg-img-alt' )
			);

		}

		$keys_valid = jpkcom_gutenberg_img_alt_ability_validate_input_keys(
			$normalised,
			JPKCOM_GUTENBERG_IMG_ALT_ABILITY_INPUT_KEYS['jpkcom-gutenberg-img-alt/list-images-missing-alt']
		);

		if ( $keys_valid instanceof WP_Error ) {

			return $keys_valid;

		}

		$per_page = JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_DEFAULT;

		if ( isset( $normalised['per_page'] ) && is_numeric( value: $normalised['per_page'] ) ) {

			$per_page = max( 1, min( JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_MAX, (int) $normalised['per_page'] ) );

		}

		$page = 1;

		if ( isset( $normalised['page'] ) && is_numeric( value: $normalised['page'] ) ) {

			$page = max( 1, min( JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PAGE_MAX, (int) $normalised['page'] ) );

		}

		// post_status: attachments normally carry 'inherit'. 'publish' is included
		// because an attachment detached from its parent can hold it. Anything
		// else - trashed, private, auto-draft - is deliberately out of scope: it
		// is not media anyone is publishing, so it is not a gap worth reporting.
		//
		// The WHERE clause is the same predicate as jpkcom_gutenberg_img_alt_would_inject(),
		// expressed in SQL: no row, or a value that TRIMs to nothing, or the
		// single character '0' which PHP treats as empty.
		$where = "p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%%'
			AND p.post_status IN ( 'inherit', 'publish' )
			AND ( m.meta_id IS NULL OR TRIM( m.meta_value ) = '' OR m.meta_value = '0' )";

		$join = "LEFT JOIN {$wpdb->postmeta} m
			ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'";

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p {$join} WHERE {$where}"
		);

		$images_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			WHERE p.post_type = 'attachment'
				AND p.post_mime_type LIKE 'image/%%'
				AND p.post_status IN ( 'inherit', 'publish' )"
		);

		$offset = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_mime_type, m.meta_id, m.meta_value
				FROM {$wpdb->posts} p {$join}
				WHERE {$where}
				ORDER BY p.ID DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$images = [];

		if ( is_array( value: $rows ) ) {

			foreach ( $rows as $row ) {

				$id  = (int) $row->ID;
				$url = wp_get_attachment_url( attachment_id: $id );
				$file = get_post_meta( post_id: $id, key: '_wp_attached_file', single: true );

				$images[] = [
					'id'         => $id,
					'title'      => (string) $row->post_title,
					'filename'   => is_string( value: $file ) ? basename( path: $file ) : '',
					'url'        => is_string( value: $url ) ? $url : '',
					'mime_type'  => (string) $row->post_mime_type,
					'stored_alt' => (string) ( $row->meta_value ?? '' ),
					'reason'     => jpkcom_gutenberg_img_alt_ability_reason( $row->meta_value ?? null, $row->meta_id !== null ),
				];

			}

		}

		return [
			'total'        => $total,
			'page'         => $page,
			'per_page'     => $per_page,
			'total_pages'  => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'images_total' => $images_total,
			'images'       => $images,
			'language'     => determine_locale(),
		];

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_ability_list' ) ) {

	/**
	 * Execute callback.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error Result.
	 */
	function jpkcom_gutenberg_img_alt_ability_list( mixed $input = null ): array|WP_Error {

		return jpkcom_gutenberg_img_alt_ability_boundary(
			static fn(): array|WP_Error => jpkcom_gutenberg_img_alt_ability_list_inner( $input ),
			'jpkcom-gutenberg-img-alt/list-images-missing-alt'
		);

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_get_ability_definitions' ) ) {

	/**
	 * Build the registration arguments.
	 *
	 * Reads no WordPress state and touches no registry, which is what lets the CI
	 * harness assert the shape without a WordPress installation.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>> Ability name => registration args.
	 */
	function jpkcom_gutenberg_img_alt_get_ability_definitions(): array {

		return [

			'jpkcom-gutenberg-img-alt/list-images-missing-alt' => [
				'label'       => __( 'List images with no usable alt text', 'jpkcom-gutenberg-img-alt' ),
				'description' => __( 'Returns the image attachments for which this plugin has nothing to inject into a rendered image block, because the attachment carries no usable alt text. This plugin overwrites an image block\'s alt attribute with the attachment\'s alt text at render time, so the attachment is the single source of truth: setting the alt text once fixes every block that uses that image, with no post to re-save. Values that look set but are not are included and named: a single "0", and text consisting only of spaces, are both treated as absent by the injection.', 'jpkcom-gutenberg-img-alt' ),
				'category'    => JPKCOM_GUTENBERG_IMG_ALT_ABILITY_CATEGORY,

				'input_schema' => [
					'type'    => 'object',
					// Top level, deliberately. normalize_input() substitutes this value
					// when the input is exactly null and nothing else does, so without
					// it the most obvious call - no parameters at all - fails
					// validation before the callback runs. An object rather than [],
					// because the MCP adapter publishes this schema verbatim.
					'default' => (object) array(),
					'properties' => [
						'page'     => [
							'type'        => 'integer',
							'description' => __( 'Page number, starting at 1.', 'jpkcom-gutenberg-img-alt' ),
							'minimum'     => 1,
							'default'     => 1,
						],
						'per_page' => [
							'type'        => 'integer',
							'description' => __( 'Images per page.', 'jpkcom-gutenberg-img-alt' ),
							'minimum'     => 1,
							'maximum'     => JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_MAX,
							'default'     => JPKCOM_GUTENBERG_IMG_ALT_ABILITY_PER_PAGE_DEFAULT,
						],
					],
				],

				'output_schema' => [
					'type'       => 'object',
					'properties' => [
						'total'        => [ 'type' => 'integer', 'description' => __( 'How many image attachments have no usable alt text.', 'jpkcom-gutenberg-img-alt' ) ],
						'page'         => [ 'type' => 'integer', 'description' => __( 'The page that was returned.', 'jpkcom-gutenberg-img-alt' ) ],
						'per_page'     => [ 'type' => 'integer', 'description' => __( 'The page size that was applied after clamping.', 'jpkcom-gutenberg-img-alt' ) ],
						'total_pages'  => [ 'type' => 'integer', 'description' => __( 'Number of pages available.', 'jpkcom-gutenberg-img-alt' ) ],
						'images_total' => [ 'type' => 'integer', 'description' => __( 'How many image attachments exist in scope altogether, so "total" can be read as a proportion. Counts attachments with an image MIME type whose status is inherit or publish; trashed and private ones are out of scope.', 'jpkcom-gutenberg-img-alt' ) ],
						'language'     => [ 'type' => 'string', 'description' => __( 'Locale this answer was read in.', 'jpkcom-gutenberg-img-alt' ) ],
						'images'       => [
							'type'        => 'array',
							'description' => __( 'The images on the requested page, newest first.', 'jpkcom-gutenberg-img-alt' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'         => [ 'type' => 'integer', 'description' => __( 'Attachment ID. This is what the alt text has to be set on.', 'jpkcom-gutenberg-img-alt' ) ],
									'title'      => [ 'type' => 'string', 'description' => __( 'Attachment title. Frequently the file name with the extension removed, and NOT a substitute for alt text.', 'jpkcom-gutenberg-img-alt' ) ],
									'filename'   => [ 'type' => 'string', 'description' => __( 'File name of the original upload.', 'jpkcom-gutenberg-img-alt' ) ],
									'url'        => [ 'type' => 'string', 'description' => __( 'URL of the original file.', 'jpkcom-gutenberg-img-alt' ) ],
									'mime_type'  => [ 'type' => 'string', 'description' => __( 'MIME type of the attachment.', 'jpkcom-gutenberg-img-alt' ) ],
									'stored_alt' => [ 'type' => 'string', 'description' => __( 'What is stored today, verbatim. Empty for most, but not all: see "reason".', 'jpkcom-gutenberg-img-alt' ) ],
									'reason'     => [
										'type'        => 'string',
										'description' => __( 'Why nothing would be injected. "no_row" when no alt text was ever saved; "empty" when an empty value is stored; "whitespace_only" when the stored text is only spaces; "zero" when the stored text is the single character 0, which PHP treats as empty and this plugin therefore skips.', 'jpkcom-gutenberg-img-alt' ),
										'enum'        => [ 'no_row', 'empty', 'whitespace_only', 'zero' ],
									],
								],
							],
						],
					],
				],

				'execute_callback'    => 'jpkcom_gutenberg_img_alt_ability_list',
				'permission_callback' => 'jpkcom_gutenberg_img_alt_ability_permission',
				'meta'                => jpkcom_gutenberg_img_alt_ability_meta( 'jpkcom-gutenberg-img-alt/list-images-missing-alt' ),
			],

		];

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_register_ability_category' ) ) {

	/**
	 * Register the category, unless something already did.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function jpkcom_gutenberg_img_alt_register_ability_category(): void {

		if ( ! jpkcom_gutenberg_img_alt_abilities_enabled() ) {

			return;

		}

		if ( function_exists( function: 'wp_has_ability_category' )
			&& wp_has_ability_category( JPKCOM_GUTENBERG_IMG_ALT_ABILITY_CATEGORY ) ) {

			return;

		}

		$category = wp_register_ability_category(
			JPKCOM_GUTENBERG_IMG_ALT_ABILITY_CATEGORY,
			[
				'label'       => __( 'JPKCom Media', 'jpkcom-gutenberg-img-alt' ),
				'description' => __( 'Read-only checks on this site\'s media library.', 'jpkcom-gutenberg-img-alt' ),
			]
		);

		if ( $category === null ) {

			jpkcom_gutenberg_img_alt_ability_log( 'ability category registration returned null' );

		}

	}

}


if ( ! function_exists( function: 'jpkcom_gutenberg_img_alt_register_abilities' ) ) {

	/**
	 * Register the ability.
	 *
	 * wp_register_ability() returns null on EVERY failure path and reports only
	 * through _doing_it_wrong(), which is silent in production - and so is the
	 * debug log without WP_DEBUG.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function jpkcom_gutenberg_img_alt_register_abilities(): void {

		if ( ! jpkcom_gutenberg_img_alt_abilities_enabled() ) {

			return;

		}

		foreach ( jpkcom_gutenberg_img_alt_get_ability_definitions() as $name => $args ) {

			if ( wp_register_ability( $name, $args ) === null ) {

				jpkcom_gutenberg_img_alt_ability_log( 'registration returned null for ' . $name );

			}

		}

	}

}


add_action( 'wp_abilities_api_categories_init', 'jpkcom_gutenberg_img_alt_register_ability_category' );
add_action( 'wp_abilities_api_init', 'jpkcom_gutenberg_img_alt_register_abilities' );
