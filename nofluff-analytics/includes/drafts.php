<?php
/**
 * Drafts: the No Fluff dashboard's worker can create a draft of a text
 * approved in the dashboard, through POST nofluff/v1/draft. Off until the
 * site owner ticks the box under Settings → No Fluff. The request is signed
 * with Ed25519 by a key only the worker holds; the plugin verifies it
 * against the public key pinned in NOFLUFF_ANALYTICS_PUBLISH_KEY. Nothing is
 * ever published, and a published, trashed or hand-edited post is never
 * changed. The plugin sends nothing for this.
 *
 * @package NoFluffAnalytics
 */

defined( 'ABSPATH' ) || exit;

define( 'NOFLUFF_ANALYTICS_DRAFT_MAX_BYTES', 300000 );
define( 'NOFLUFF_ANALYTICS_DRAFT_WINDOW', 300 );

add_action( 'rest_api_init', 'nofluff_analytics_draft_route' );

/**
 * Register the route, POST only and left out of the REST index.
 */
function nofluff_analytics_draft_route() {
	register_rest_route(
		'nofluff/v1',
		'/draft',
		array(
			'methods'             => 'POST',
			'callback'            => 'nofluff_analytics_draft_create',
			'permission_callback' => 'nofluff_analytics_draft_permission',
			'show_in_index'       => false,
		)
	);
}

// WordPress parses a JSON body before a route's permission_callback runs, so
// size and signature are checked here first: an unsigned caller always gets
// the same 401, whatever else is wrong with the request.
add_filter( 'rest_pre_dispatch', 'nofluff_analytics_draft_pre_dispatch', 10, 3 );

/**
 * Refuse an oversized or unsigned request to the draft route before
 * WordPress parses its body.
 *
 * @param mixed           $result  Response so far (null to dispatch).
 * @param WP_REST_Server  $server  Server.
 * @param WP_REST_Request $request Request.
 * @return mixed
 */
function nofluff_analytics_draft_pre_dispatch( $result, $server, $request ) {
	// The same pattern WordPress matches routes with (case-insensitive, $ as in WP_REST_Server).
	if ( null !== $result || 'POST' !== $request->get_method() || ! preg_match( '@^/nofluff/v1/draft$@i', (string) $request->get_route() ) ) {
		return $result;
	}
	$gate = nofluff_analytics_draft_gate( $request->get_body(), $request->get_header( 'X-NoFluff-Signature' ), nofluff_analytics_publish_key() );
	return true === $gate ? $result : $gate;
}

/**
 * The pinned public key as 32 raw bytes, or '' when none is set (then every
 * request is refused).
 *
 * @param string $encoded Base64 of the raw Ed25519 public key.
 * @return string
 */
function nofluff_analytics_publish_key( $encoded = NOFLUFF_ANALYTICS_PUBLISH_KEY ) {
	$key = base64_decode( (string) $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a public key, not code.
	return ( false !== $key && 32 === strlen( $key ) ) ? $key : '';
}

/**
 * Body size, then the signature over the exact body bytes. Reads no setting.
 *
 * @param string      $body      Raw request body.
 * @param string|null $signature X-NoFluff-Signature: base64 of the 64-byte detached signature.
 * @param string      $key       Raw public key, '' when none is pinned.
 * @return true|WP_Error
 */
function nofluff_analytics_draft_gate( $body, $signature, $key ) {
	$body = (string) $body;
	if ( strlen( $body ) > NOFLUFF_ANALYTICS_DRAFT_MAX_BYTES ) {
		return new WP_Error( 'nofluff_too_large', 'The request is too large.', array( 'status' => 413 ) );
	}
	$valid = false;
	if ( '' !== $key && is_string( $signature ) && preg_match( '#^[A-Za-z0-9+/]{86}==\z#', $signature ) && function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
		$raw = base64_decode( $signature, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a signature, not code.
		try {
			$valid = false !== $raw && 64 === strlen( $raw ) && sodium_crypto_sign_verify_detached( $raw, $body, $key );
		} catch ( Exception $e ) {
			$valid = false;
		}
	}
	return $valid ? true : new WP_Error( 'nofluff_signature', 'The request is not signed.', array( 'status' => 401 ) );
}

/**
 * Parse and sanitise a signed body.
 *
 * @param string $body Raw request body.
 * @return array{site: string, text_id: string, ts: int, nonce: string, type: string, title: string, content: string, slug: string, meta_title: string, meta_description: string}|WP_Error
 */
function nofluff_analytics_draft_parse( $body ) {
	$data    = json_decode( (string) $body, true );
	$invalid = new WP_Error( 'nofluff_invalid', 'The request is not a valid draft.', array( 'status' => 400 ) );
	if ( ! is_array( $data ) ) {
		return $invalid;
	}
	$valid = isset( $data['site'], $data['text_id'], $data['ts'], $data['nonce'], $data['type'], $data['title'], $data['content'] )
		&& is_string( $data['site'] ) && preg_match( '/^[a-f0-9]{24}\z/', $data['site'] )
		&& is_string( $data['text_id'] ) && preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $data['text_id'] )
		&& is_int( $data['ts'] )
		&& is_string( $data['nonce'] ) && preg_match( '/^[a-f0-9]{32}\z/', $data['nonce'] )
		&& in_array( $data['type'], array( 'post', 'page' ), true )
		&& is_string( $data['title'] ) && mb_strlen( $data['title'], 'UTF-8' ) <= 200
		&& is_string( $data['content'] );
	$optional = array();
	foreach ( array( 'slug', 'meta_title', 'meta_description' ) as $field ) {
		$value = isset( $data[ $field ] ) ? $data[ $field ] : null;
		if ( null !== $value && ! is_string( $value ) ) {
			$valid = false;
		}
		$optional[ $field ] = is_string( $value ) ? $value : '';
	}
	if ( ! $valid ) {
		return $invalid;
	}
	$title = sanitize_text_field( $data['title'] );
	if ( '' === $title ) {
		return $invalid;
	}
	return array(
		'site'             => $data['site'],
		'text_id'          => $data['text_id'],
		'ts'               => $data['ts'],
		'nonce'            => $data['nonce'],
		'type'             => $data['type'],
		'title'            => $title,
		'content'          => wp_kses_post( $data['content'] ),
		'slug'             => sanitize_title( $optional['slug'] ),
		'meta_title'       => sanitize_text_field( $optional['meta_title'] ),
		'meta_description' => sanitize_text_field( $optional['meta_description'] ),
	);
}

/**
 * The checks, in this order: size, signature, body, site, time, nonce,
 * switched on. No setting is read before the signature holds.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function nofluff_analytics_draft_permission( $request ) {
	$body = $request->get_body();
	$gate = nofluff_analytics_draft_gate( $body, $request->get_header( 'X-NoFluff-Signature' ), nofluff_analytics_publish_key() );
	if ( true !== $gate ) {
		return $gate;
	}
	$data = nofluff_analytics_draft_parse( $body );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$settings = nofluff_analytics_settings();
	if ( $data['site'] !== $settings['tracking_id'] ) {
		return new WP_Error( 'nofluff_site', 'The request is for another site.', array( 'status' => 400 ) );
	}
	if ( abs( time() - $data['ts'] ) > NOFLUFF_ANALYTICS_DRAFT_WINDOW ) {
		return new WP_Error( 'nofluff_stale', 'The request is too old or too far ahead.', array( 'status' => 401 ) );
	}
	// Twice the window: a nonce seen at t is refused until the ts check refuses it anyway.
	$seen = 'nofluff_n_' . $data['nonce'];
	if ( false !== get_transient( $seen ) ) {
		return new WP_Error( 'nofluff_replayed', 'The request was already received.', array( 'status' => 409 ) );
	}
	set_transient( $seen, 1, 2 * NOFLUFF_ANALYTICS_DRAFT_WINDOW );
	$author = (int) $settings['draft_author'];
	$can    = 'page' === $data['type'] ? 'edit_pages' : 'edit_posts';
	if ( ! $settings['allow_drafts'] || $author < 1 || ! user_can( $author, $can ) ) {
		return new WP_Error( 'nofluff_disabled', 'Drafts are switched off on this site.', array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Create or update the draft. Runs only after the permission callback.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function nofluff_analytics_draft_create( $request ) {
	$data = nofluff_analytics_draft_parse( $request->get_body() );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$saved = nofluff_analytics_draft_save( $data, (int) nofluff_analytics_settings()['draft_author'] );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	return new WP_REST_Response( array( 'post_id' => $saved['post_id'] ), $saved['created'] ? 201 : 200 );
}

/**
 * Insert a new draft, or update the draft of the same text while nobody has
 * edited it. Never publishes, never changes a published, scheduled, private,
 * trashed or hand-edited post, never switches the current user.
 *
 * @param array $data   Parsed, sanitised body.
 * @param int   $author The user who ticked the box.
 * @return array{created: bool, post_id: int}|WP_Error
 */
function nofluff_analytics_draft_save( $data, $author ) {
	$found = get_posts(
		array(
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => array_values( get_post_stati() ),
			'meta_key'         => '_nofluff_text_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one lookup per signed request.
			'meta_value'       => $data['text_id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one lookup per signed request.
			'fields'           => 'ids',
			'numberposts'      => 1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
		)
	);

	$fields = array(
		'post_title'   => $data['title'],
		'post_content' => $data['content'],
	);
	if ( '' !== $data['slug'] ) {
		$fields['post_name'] = $data['slug'];
	}

	if ( $found ) {
		$post = get_post( (int) $found[0] );
		$id   = (int) $post->ID;
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'nofluff_trashed', 'The draft is in the trash.', array( 'status' => 409, 'post_id' => $id ) );
		}
		if ( ! in_array( $post->post_status, array( 'draft', 'pending' ), true ) ) {
			return new WP_Error( 'nofluff_published', 'The post is no longer a draft.', array( 'status' => 409, 'post_id' => $id ) );
		}
		// Only a person submits a draft for review: that is a hand change too.
		if ( 'draft' !== $post->post_status || nofluff_analytics_draft_hash( $post ) !== (string) get_post_meta( $id, '_nofluff_hash', true ) ) {
			return new WP_Error( 'nofluff_edited', 'The draft was edited in WordPress.', array( 'status' => 409, 'post_id' => $id ) );
		}
		$fields['ID'] = $id;
		$result       = wp_update_post( wp_slash( $fields ), true );
	} else {
		$fields['post_type']   = $data['type'];
		$fields['post_status'] = 'draft';
		$fields['post_author'] = (int) $author;
		// Written with the post, so a crash cannot leave a draft the next try does not find.
		$fields['meta_input'] = array(
			'_nofluff_text_id' => $data['text_id'],
			'_nofluff_hash'    => md5( $data['content'] ),
		);
		$result = wp_insert_post( wp_slash( $fields ), true );
	}

	if ( is_wp_error( $result ) || ! $result ) {
		return new WP_Error( 'nofluff_failed', 'WordPress could not save the draft.', array( 'status' => 500 ) );
	}
	$id     = (int) $result;
	$stored = get_post( $id );
	update_post_meta( $id, '_nofluff_hash', $stored ? nofluff_analytics_draft_hash( $stored ) : '' );
	foreach ( nofluff_analytics_draft_seo_keys() as $pair ) {
		if ( '' !== $data[ $pair[0] ] ) {
			update_post_meta( $id, $pair[1], wp_slash( $data[ $pair[0] ] ) );
		}
	}
	return array(
		'created' => ! $found,
		'post_id' => $id,
	);
}

/**
 * The fingerprint of what the endpoint writes, so a hand edit to any of it
 * stops the next update.
 *
 * @param WP_Post $post The stored post.
 * @return string
 */
function nofluff_analytics_draft_hash( $post ) {
	return md5( $post->post_title . "\0" . $post->post_name . "\0" . $post->post_content );
}

/**
 * The search plugin's meta keys for the title and description, for the
 * active ones of Yoast SEO and Rank Math.
 *
 * @return array<int, array{0: string, 1: string}> Body field and meta key.
 */
function nofluff_analytics_draft_seo_keys() {
	$keys = array();
	if ( defined( 'WPSEO_VERSION' ) ) {
		$keys[] = array( 'meta_title', '_yoast_wpseo_title' );
		$keys[] = array( 'meta_description', '_yoast_wpseo_metadesc' );
	}
	if ( class_exists( 'RankMath' ) ) {
		$keys[] = array( 'meta_title', 'rank_math_title' );
		$keys[] = array( 'meta_description', 'rank_math_description' );
	}
	return $keys;
}
