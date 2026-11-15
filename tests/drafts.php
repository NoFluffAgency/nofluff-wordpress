<?php
/**
 * The drafts endpoint: the signature has to match the dashboard's worker
 * byte for byte, and no request may publish or overwrite anything. The
 * frozen vector below is the same one the dashboard's worker test uses
 * (seed of 32 zero bytes, Node's node:crypto Ed25519). WordPress is stubbed
 * over an array of posts.
 *
 * Run: php tests/drafts.php (needs the sodium extension)
 *
 * @package NoFluffAnalytics
 */

define( 'ABSPATH', __DIR__ );
// The test key: the public key of the zero seed. Defined first, as wp-config.php would.
define( 'NOFLUFF_ANALYTICS_PUBLISH_KEY', 'O2onvM62pC1io6jQKm8Nc2UyFXcd4kOmOsBIoYtZ2ik=' );

// Frozen in Node: sign( null, Buffer.from( FROZEN_BODY ), the zero seed's key ).
define( 'FROZEN_BODY', '{"site":"f673eac1b749476300514817","text_id":"0b6f7c1e-4a8d-4c2b-9f3e-5d7a1b2c3d4e","ts":1763200000,"nonce":"00112233445566778899aabbccddeeff","type":"page","title":"Fensterreinigung in Köln","content":"<h2>Saubere Fenster</h2><p>Wir reinigen Fenster in Köln.</p>","slug":"fensterreinigung-koeln","meta_title":"Fensterreinigung Köln","meta_description":null}' );
define( 'FROZEN_SIGNATURE', 'ItH8a4OxbZliQv+nTU6zFhM5nhIRGhI02J6OHIZNPtlT8Y7ohDix/YBuxyelejyEyrQkOta6fhJ2+KLovXb3Bw==' );
define( 'SITE', 'f673eac1b749476300514817' );

// WordPress, stubbed. phpcs:disable

$GLOBALS['nf_options']      = array();
$GLOBALS['nf_option_reads'] = 0;
$GLOBALS['nf_transients']   = array();
$GLOBALS['nf_posts']        = array();
$GLOBALS['nf_meta']         = array();
$GLOBALS['nf_next_id']      = 100;
$GLOBALS['nf_fail_save']    = false;
$GLOBALS['nf_last_insert']  = null;
$GLOBALS['nf_user']         = 7;
$GLOBALS['nf_caps']         = array(
	7 => array( 'edit_posts' => true, 'edit_pages' => true ),
	8 => array( 'edit_posts' => true ),
);

function add_action() {}
function add_filter() {}
function plugin_basename( $file ) { return basename( $file ); }
function register_uninstall_hook() {}
function register_rest_route() {}
function add_settings_error() {}
function delete_site_transient() {}

class WP_Error {
	private $code;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code = $code;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Response {
	private $data;
	private $status;
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

class WP_REST_Request {
	private $method;
	private $route;
	private $body    = '';
	private $headers = array();
	public function __construct( $method, $route ) {
		$this->method = $method;
		$this->route  = $route;
	}
	private static function canonical( $name ) { return str_replace( '-', '_', strtolower( $name ) ); }
	public function set_body( $body ) { $this->body = $body; }
	public function set_header( $name, $value ) { $this->headers[ self::canonical( $name ) ] = $value; }
	public function get_body() { return $this->body; }
	public function get_header( $name ) { $k = self::canonical( $name ); return isset( $this->headers[ $k ] ) ? $this->headers[ $k ] : null; }
	public function get_method() { return $this->method; }
	public function get_route() { return $this->route; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function get_option( $name, $default = false ) {
	++$GLOBALS['nf_option_reads'];
	return isset( $GLOBALS['nf_options'][ $name ] ) ? $GLOBALS['nf_options'][ $name ] : $default;
}
function delete_option( $name ) { unset( $GLOBALS['nf_options'][ $name ] ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_transient( $name ) { return isset( $GLOBALS['nf_transients'][ $name ] ) ? $GLOBALS['nf_transients'][ $name ] : false; }
function set_transient( $name, $value, $expiration ) { $GLOBALS['nf_transients'][ $name ] = $value; return true; }
function user_can( $user, $cap ) { return ! empty( $GLOBALS['nf_caps'][ $user ][ $cap ] ); }
function get_current_user_id() { return $GLOBALS['nf_user']; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( strip_tags( (string) $s ) ) ), '-' ); }
function wp_kses_post( $s ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $s ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
// Stands in for WordPress's own filters on save, so the stored content differs from the sent one.
function nf_store_filter( $content ) { return str_replace( '<br>', '<br />', $content ); }
function get_post_stati() {
	$names = array( 'publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit' );
	return array_combine( $names, $names );
}
function get_post( $id ) { return isset( $GLOBALS['nf_posts'][ $id ] ) ? $GLOBALS['nf_posts'][ $id ] : null; }
function get_post_meta( $id, $key, $single = false ) { return isset( $GLOBALS['nf_meta'][ $id ][ $key ] ) ? $GLOBALS['nf_meta'][ $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['nf_meta'][ $id ][ $key ] = wp_unslash( $value ); return true; }
function delete_post_meta_by_key( $key ) {
	foreach ( array_keys( $GLOBALS['nf_meta'] ) as $id ) {
		unset( $GLOBALS['nf_meta'][ $id ][ $key ] );
	}
	return true;
}
function get_posts( $args ) {
	$ids = array();
	foreach ( $GLOBALS['nf_posts'] as $id => $post ) {
		if ( in_array( $post->post_type, $args['post_type'], true ) && in_array( $post->post_status, $args['post_status'], true )
			&& get_post_meta( $id, $args['meta_key'], true ) === $args['meta_value'] ) {
			$ids[] = $id;
		}
	}
	sort( $ids );
	return array_slice( $ids, 0, $args['numberposts'] );
}
function wp_insert_post( $postarr, $wp_error = false ) {
	$GLOBALS['nf_last_insert'] = $postarr;
	if ( $GLOBALS['nf_fail_save'] ) {
		return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' );
	}
	$p  = wp_unslash( $postarr );
	$id = $GLOBALS['nf_next_id']++;
	$GLOBALS['nf_posts'][ $id ] = (object) array(
		'ID'           => $id,
		'post_type'    => $p['post_type'],
		'post_status'  => $p['post_status'],
		'post_author'  => $p['post_author'],
		'post_title'   => $p['post_title'],
		'post_content' => nf_store_filter( $p['post_content'] ),
		'post_name'    => isset( $p['post_name'] ) ? $p['post_name'] : '',
	);
	foreach ( $postarr['meta_input'] as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
}
function wp_update_post( $postarr, $wp_error = false ) {
	$p    = wp_unslash( $postarr );
	$post = $GLOBALS['nf_posts'][ $p['ID'] ];
	$post->post_title   = $p['post_title'];
	$post->post_content = nf_store_filter( $p['post_content'] );
	if ( isset( $p['post_name'] ) ) {
		$post->post_name = $p['post_name'];
	}
	return $p['ID'];
}

// phpcs:enable

require __DIR__ . '/../nofluff-analytics/nofluff-analytics.php';

$keypair = sodium_crypto_sign_seed_keypair( str_repeat( "\0", 32 ) );
$secret  = sodium_crypto_sign_secretkey( $keypair );
$key     = nofluff_analytics_publish_key();

/**
 * A draft body as the worker builds it, with a fresh ts and nonce.
 *
 * @param array $over Fields to replace.
 * @return array
 */
function nf_draft( $over = array() ) {
	return array_merge(
		array(
			'site'             => SITE,
			'text_id'          => '11111111-2222-4333-8444-555555555555',
			'ts'               => time(),
			'nonce'            => bin2hex( random_bytes( 16 ) ),
			'type'             => 'page',
			'title'            => 'Fensterreinigung in Köln',
			'content'          => '<p>Wir reinigen Fenster.<br>Auch in Köln.</p>',
			'slug'             => 'Fensterreinigung Köln',
			'meta_title'       => 'Fensterreinigung Köln',
			'meta_description' => null,
		),
		$over
	);
}

/**
 * A POST to the route with this body and signature header.
 *
 * @param string      $body      Raw body.
 * @param string|null $signature Header value.
 * @param string      $route     Route.
 * @return WP_REST_Request
 */
function nf_request( $body, $signature, $route = '/nofluff/v1/draft' ) {
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_body( $body );
	if ( null !== $signature ) {
		$request->set_header( 'X-NoFluff-Signature', $signature );
	}
	return $request;
}

/**
 * The request signed with the zero seed.
 *
 * @param array|string $fields Body fields, or the raw body.
 * @return WP_REST_Request
 */
function nf_signed( $fields ) {
	global $secret;
	$body = is_string( $fields ) ? $fields : json_encode( $fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return nf_request( $body, base64_encode( sodium_crypto_sign_detached( $body, $secret ) ) );
}

/**
 * What WordPress does with the request: rest_pre_dispatch, then the route's
 * permission_callback, then its callback.
 *
 * @param WP_REST_Request $request Request.
 * @return mixed
 */
function nf_dispatch( $request ) {
	$early = nofluff_analytics_draft_pre_dispatch( null, null, $request );
	if ( null !== $early ) {
		return $early;
	}
	$allowed = nofluff_analytics_draft_permission( $request );
	if ( true !== $allowed ) {
		return $allowed;
	}
	return nofluff_analytics_draft_create( $request );
}

/**
 * "code status" for an error, the status for a response.
 *
 * @param mixed $answer Answer.
 * @return string
 */
function nf_answer( $answer ) {
	if ( $answer instanceof WP_Error ) {
		$data = $answer->get_error_data();
		return $answer->get_error_code() . ' ' . $data['status'];
	}
	if ( $answer instanceof WP_REST_Response ) {
		return (string) $answer->get_status();
	}
	return var_export( $answer, true );
}

/**
 * Flip the lowest bit of the first byte.
 *
 * @param string $bytes Bytes.
 * @return string
 */
function nf_flip( $bytes ) {
	$bytes[0] = chr( ord( $bytes[0] ) ^ 1 );
	return $bytes;
}

$on = array(
	'tracking_id'  => SITE,
	'allow_drafts' => true,
	'draft_author' => 7,
);

$checks = array();

// The frozen vector.
$checks['the zero seed gives the pinned test key']        = sodium_crypto_sign_publickey( $keypair ) === $key;
$checks['the frozen body is 361 bytes of UTF-8']          = 361 === strlen( FROZEN_BODY );
$checks['the frozen signature holds']                     = true === nofluff_analytics_draft_gate( FROZEN_BODY, FROZEN_SIGNATURE, $key );
$checks['PHP signs the frozen body as Node did']          = FROZEN_SIGNATURE === base64_encode( sodium_crypto_sign_detached( FROZEN_BODY, $secret ) );
$checks['a flipped body byte fails']                      = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( nf_flip( FROZEN_BODY ), FROZEN_SIGNATURE, $key ) );
$checks['a flipped signature byte fails']                 = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( FROZEN_BODY, base64_encode( nf_flip( base64_decode( FROZEN_SIGNATURE ) ) ), $key ) );
$checks['a flipped last body byte fails']                 = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( strrev( nf_flip( strrev( FROZEN_BODY ) ) ), FROZEN_SIGNATURE, $key ) );

// The key.
$checks['the pinned constant decodes to 32 bytes']        = 32 === strlen( $key );
$checks['no key pinned: the frozen vector is refused']    = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( FROZEN_BODY, FROZEN_SIGNATURE, nofluff_analytics_publish_key( '' ) ) );
$checks['a 31-byte key is no key']                        = '' === nofluff_analytics_publish_key( base64_encode( str_repeat( 'a', 31 ) ) );
$checks['a key that is not base64 is no key']             = '' === nofluff_analytics_publish_key( 'not a key!' );

// The signature header.
$checks['no header']                                      = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( FROZEN_BODY, null, $key ) );
$checks['the signature without padding']                  = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( FROZEN_BODY, rtrim( FROZEN_SIGNATURE, '=' ), $key ) );
$checks['the signature with a space']                     = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( FROZEN_BODY, ' ' . FROZEN_SIGNATURE, $key ) );
$checks['the signature twice, comma-joined']              = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( FROZEN_BODY, FROZEN_SIGNATURE . ',' . FROZEN_SIGNATURE, $key ) );
$checks['the signature as hex']                           = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( FROZEN_BODY, bin2hex( base64_decode( FROZEN_SIGNATURE ) ), $key ) );

// The size cap comes first.
$checks['over 300,000 bytes is 413 before the signature'] = 'nofluff_too_large 413' === nf_answer( nofluff_analytics_draft_gate( str_repeat( 'a', 300001 ), FROZEN_SIGNATURE, $key ) );
$checks['300,000 bytes is not too large']                 = 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_gate( str_repeat( 'a', 300000 ), FROZEN_SIGNATURE, $key ) );

// An unsigned caller learns nothing, and no setting is read for it.
$GLOBALS['nf_options']      = array( 'nofluff_analytics' => array( 'tracking_id' => 'aaaaaaaaaaaaaaaaaaaaaaaa' ) );
$GLOBALS['nf_option_reads'] = 0;
$unsigned                   = array(
	nf_request( json_encode( nf_draft() ), null ),
	nf_request( json_encode( nf_draft() ), FROZEN_SIGNATURE ),
	nf_request( '{not json', FROZEN_SIGNATURE ),
	nf_request( json_encode( nf_draft() ), FROZEN_SIGNATURE, '/NOFLUFF/v1/draft' ),
);
$same = true;
foreach ( $unsigned as $request ) {
	$same = $same && 'nofluff_signature 401' === nf_answer( nf_dispatch( $request ) )
		&& 'nofluff_signature 401' === nf_answer( nofluff_analytics_draft_permission( $request ) );
}
$checks['unsigned, wrongly signed or malformed: the same 401'] = $same;
$checks['no setting read before the signature holds']          = 0 === $GLOBALS['nf_option_reads'];
$checks['other routes are left alone']                         = null === nofluff_analytics_draft_pre_dispatch( null, null, nf_request( '', null, '/wp/v2/posts' ) );
$checks['a GET is left to WordPress (no route)']               = null === nofluff_analytics_draft_pre_dispatch( null, null, new WP_REST_Request( 'GET', '/nofluff/v1/draft' ) );

// The order of the checks after the signature, with the frozen body.
$checks['the frozen body for another site: site, not stale'] = 'nofluff_site 400' === nf_answer( nf_dispatch( nf_request( FROZEN_BODY, FROZEN_SIGNATURE ) ) );
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => array( 'tracking_id' => SITE ) );
$checks['the frozen body for this site is valid but stale']  = 'nofluff_stale 401' === nf_answer( nf_dispatch( nf_request( FROZEN_BODY, FROZEN_SIGNATURE ) ) );

// Refusals of signed requests.
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => $on );
$posts_before          = count( $GLOBALS['nf_posts'] );
$refused               = array(
	'site mismatch'                     => array( nf_draft( array( 'site' => 'bbbbbbbbbbbbbbbbbbbbbbbb' ) ), 'nofluff_site 400' ),
	'ts 301 s old'                      => array( nf_draft( array( 'ts' => time() - 301 ) ), 'nofluff_stale 401' ),
	'ts over 300 s ahead'               => array( nf_draft( array( 'ts' => time() + 310 ) ), 'nofluff_stale 401' ),
	'ts as a string'                    => array( nf_draft( array( 'ts' => (string) time() ) ), 'nofluff_invalid 400' ),
	'an empty title'                    => array( nf_draft( array( 'title' => '' ) ), 'nofluff_invalid 400' ),
	'a title of tags only'              => array( nf_draft( array( 'title' => '<b></b>' ) ), 'nofluff_invalid 400' ),
	'a 201-character title'             => array( nf_draft( array( 'title' => str_repeat( 'ä', 201 ) ) ), 'nofluff_invalid 400' ),
	'type attachment'                   => array( nf_draft( array( 'type' => 'attachment' ) ), 'nofluff_invalid 400' ),
	'a site id with a trailing newline' => array( nf_draft( array( 'site' => SITE . "\n" ) ), 'nofluff_invalid 400' ),
	'an upper-case text id'             => array( nf_draft( array( 'text_id' => '11111111-2222-4333-8444-55555555555A' ) ), 'nofluff_invalid 400' ),
	'a short nonce'                     => array( nf_draft( array( 'nonce' => 'abc' ) ), 'nofluff_invalid 400' ),
	'content as an array'               => array( nf_draft( array( 'content' => array( 'x' ) ) ), 'nofluff_invalid 400' ),
	'a slug as a number'                => array( nf_draft( array( 'slug' => 5 ) ), 'nofluff_invalid 400' ),
	'a JSON list'                       => array( array( SITE ), 'nofluff_invalid 400' ),
);
foreach ( $refused as $name => $case ) {
	$checks[ 'refused: ' . $name ] = $case[1] === nf_answer( nf_dispatch( nf_signed( $case[0] ) ) );
}
$checks['signed but not JSON is invalid'] = 'nofluff_invalid 400' === nf_answer( nf_dispatch( nf_signed( 'not json' ) ) );

$GLOBALS['nf_options'] = array( 'nofluff_analytics' => array( 'allow_drafts' => false ) + $on );
$checks['refused: box off']               = 'nofluff_disabled 403' === nf_answer( nf_dispatch( nf_signed( nf_draft() ) ) );
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => array( 'draft_author' => 0 ) + $on );
$checks['refused: no author']             = 'nofluff_disabled 403' === nf_answer( nf_dispatch( nf_signed( nf_draft() ) ) );
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => array( 'draft_author' => 8 ) + $on );
$checks['refused: a page by an author without edit_pages'] = 'nofluff_disabled 403' === nf_answer( nf_dispatch( nf_signed( nf_draft() ) ) );
$checks['nothing was saved by a refused request']          = count( $GLOBALS['nf_posts'] ) === $posts_before;
$as_post = nf_dispatch( nf_signed( nf_draft( array( 'type' => 'post', 'text_id' => '22222222-2222-4333-8444-555555555555' ) ) ) );
$checks['a post by an author with edit_posts only']        = '201' === nf_answer( $as_post ) && 8 === $GLOBALS['nf_posts'][ $as_post->get_data()['post_id'] ]->post_author;

// Insert.
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => $on );
$first                 = nf_draft( array( 'content' => '<p>Wir reinigen Fenster.<br>Auch in Köln.</p><script>alert(1)</script>', 'title' => 'Fenster C:\\Köln' ) );
$replay                = nf_signed( $first );
$created               = nf_dispatch( $replay );
$id                    = $created instanceof WP_REST_Response ? $created->get_data()['post_id'] : 0;
$post                  = get_post( $id );
$checks['insert: 201 with the post id and nothing more']   = '201' === nf_answer( $created ) && array( 'post_id' ) === array_keys( $created->get_data() ) && is_int( $id ) && $id > 0;
$checks['insert: a draft page by the user who ticked it']  = $post && 'draft' === $post->post_status && 'page' === $post->post_type && 7 === $post->post_author;
$checks['insert: the text id is written with the post']    = '11111111-2222-4333-8444-555555555555' === $GLOBALS['nf_last_insert']['meta_input']['_nofluff_text_id'] && '11111111-2222-4333-8444-555555555555' === get_post_meta( $id, '_nofluff_text_id', true );
$checks['insert: the hash is of the stored content']       = $post && false !== strpos( $post->post_content, '<br />' ) && md5( $post->post_content ) === get_post_meta( $id, '_nofluff_hash', true );
$checks['insert: kses removed the script']                 = $post && false === strpos( $post->post_content, '<script' );
$checks['insert: a backslash in the title survives']       = $post && 'Fenster C:\\Köln' === $post->post_title;
$checks['insert: the slug is sanitised']                   = $post && sanitize_title( 'Fensterreinigung Köln' ) === $post->post_name;
$checks['insert: no SEO meta without Yoast or Rank Math']  = '' === get_post_meta( $id, '_yoast_wpseo_title', true ) && '' === get_post_meta( $id, 'rank_math_title', true );
$checks['a reused nonce is refused']                       = 'nofluff_replayed 409' === nf_answer( nf_dispatch( $replay ) );

// Update.
$count   = count( $GLOBALS['nf_posts'] );
$updated = nf_dispatch( nf_signed( nf_draft( array( 'title' => 'Fensterreinigung Köln, neu', 'content' => '<p>Neu.</p>' ) ) ) );
$checks['update: 200, the same post, still one post']      = '200' === nf_answer( $updated ) && $id === $updated->get_data()['post_id'] && count( $GLOBALS['nf_posts'] ) === $count;
$checks['update: title, content and hash are new']         = 'Fensterreinigung Köln, neu' === $post->post_title && '<p>Neu.</p>' === $post->post_content && md5( '<p>Neu.</p>' ) === get_post_meta( $id, '_nofluff_hash', true );
$checks['update: status and author are kept']              = 'draft' === $post->post_status && 7 === $post->post_author;

$post->post_status = 'pending';
$checks['update: a pending draft stays pending']           = '200' === nf_answer( nf_dispatch( nf_signed( nf_draft() ) ) ) && 'pending' === $post->post_status;
$post->post_status = 'draft';

// Never changed: published, scheduled, private, trashed, hand-edited.
foreach ( array( 'publish' => 'nofluff_published 409', 'future' => 'nofluff_published 409', 'private' => 'nofluff_published 409', 'trash' => 'nofluff_trashed 409' ) as $status => $expected ) {
	$post->post_status = $status;
	$before            = $post->post_content;
	$answer            = nf_dispatch( nf_signed( nf_draft( array( 'content' => '<p>Overwrite?</p>' ) ) ) );
	$data              = $answer instanceof WP_Error ? $answer->get_error_data() : array();
	$checks[ $status . ': 409 with the post id, content unchanged' ] = $expected === nf_answer( $answer ) && isset( $data['post_id'] ) && $id === $data['post_id'] && $before === $post->post_content;
}
$post->post_status  = 'draft';
$post->post_content = $post->post_content . '<p>Ein Wort von Hand.</p>';
$before             = $post->post_content;
$checks['edited: 409, content unchanged']                  = 'nofluff_edited 409' === nf_answer( nf_dispatch( nf_signed( nf_draft( array( 'content' => '<p>Overwrite?</p>' ) ) ) ) ) && $before === $post->post_content;

// A failed save.
$GLOBALS['nf_fail_save'] = true;
$count                   = count( $GLOBALS['nf_posts'] );
$checks['a failed insert is 500 and leaves nothing']       = 'nofluff_failed 500' === nf_answer( nf_dispatch( nf_signed( nf_draft( array( 'text_id' => '33333333-2222-4333-8444-555555555555' ) ) ) ) ) && count( $GLOBALS['nf_posts'] ) === $count;
$GLOBALS['nf_fail_save'] = false;

// SEO meta only with Yoast or Rank Math, non-empty values only.
define( 'WPSEO_VERSION', '26.0' );
$yoast = nf_dispatch( nf_signed( nf_draft( array( 'text_id' => '44444444-2222-4333-8444-555555555555' ) ) ) );
$yid   = $yoast->get_data()['post_id'];
$checks['Yoast: the title, no empty description']          = 'Fensterreinigung Köln' === get_post_meta( $yid, '_yoast_wpseo_title', true ) && '' === get_post_meta( $yid, '_yoast_wpseo_metadesc', true ) && '' === get_post_meta( $yid, 'rank_math_title', true );
if ( ! class_exists( 'RankMath' ) ) {
	class RankMath {} // phpcs:ignore
}
$rank = nf_dispatch( nf_signed( nf_draft( array( 'text_id' => '55555555-2222-4333-8444-555555555555', 'meta_description' => 'Saubere Fenster in Köln.' ) ) ) );
$rid  = $rank->get_data()['post_id'];
$checks['Rank Math: title and description']                = 'Fensterreinigung Köln' === get_post_meta( $rid, 'rank_math_title', true ) && 'Saubere Fenster in Köln.' === get_post_meta( $rid, 'rank_math_description', true );

// The settings: the author is whoever ticked the box, kept while ticked.
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => array( 'tracking_id' => SITE ) );
$GLOBALS['nf_user']    = 7;
$ticked                = nofluff_analytics_sanitize( array( 'tracking_id' => SITE, 'allow_drafts' => '1' ) );
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => $ticked );
$GLOBALS['nf_user']    = 9;
$kept                  = nofluff_analytics_sanitize( array( 'tracking_id' => SITE, 'allow_drafts' => '1' ) );
$GLOBALS['nf_options'] = array( 'nofluff_analytics' => $kept );
$off                   = nofluff_analytics_sanitize( array( 'tracking_id' => SITE ) );
$checks['settings: ticking sets the author to the user']   = true === $ticked['allow_drafts'] && 7 === $ticked['draft_author'];
$checks['settings: another admin saving keeps the author'] = 7 === $kept['draft_author'];
$checks['settings: unticking clears the author']           = false === $off['allow_drafts'] && 0 === $off['draft_author'];
$GLOBALS['nf_options'] = array();
$checks['settings: the defaults are off and nobody']       = false === nofluff_analytics_settings()['allow_drafts'] && 0 === nofluff_analytics_settings()['draft_author'];

// Uninstall removes both meta keys and keeps the drafts.
update_post_meta( $id, 'something_else', 'stays' );
$count = count( $GLOBALS['nf_posts'] );
nofluff_analytics_uninstall();
$left = 0;
foreach ( $GLOBALS['nf_meta'] as $meta ) {
	$left += isset( $meta['_nofluff_text_id'] ) || isset( $meta['_nofluff_hash'] ) ? 1 : 0;
}
$checks['uninstall removes _nofluff_text_id and _nofluff_hash'] = 0 === $left && 'stays' === get_post_meta( $id, 'something_else', true );
$checks['uninstall keeps the drafts']                           = count( $GLOBALS['nf_posts'] ) === $count;

$failed = 0;
foreach ( $checks as $name => $passed ) {
	echo ( $passed ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failed += $passed ? 0 : 1;
}
exit( $failed > 0 ? 1 : 0 );
