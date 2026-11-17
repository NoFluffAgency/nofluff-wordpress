<?php
/**
 * Plugin Name:       No Fluff Analytics
 * Plugin URI:        https://github.com/NoFluffAgency/nofluff-wordpress
 * Description:       Connects this site to the No Fluff dashboard: adds the tracking script to every page, can report form submissions and WooCommerce orders as goals, and can create drafts of texts approved in the dashboard (never published).
 * Version:           1.5.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            No Fluff
 * Author URI:        https://nofluff.agency
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nofluff-analytics
 * Domain Path:       /languages
 * Update URI:        https://github.com/NoFluffAgency/nofluff-wordpress
 *
 * @package NoFluffAnalytics
 */

defined( 'ABSPATH' ) || exit;

define( 'NOFLUFF_ANALYTICS_VERSION', '1.5.0' );
define( 'NOFLUFF_ANALYTICS_FILE', __FILE__ );
define( 'NOFLUFF_ANALYTICS_OPTION', 'nofluff_analytics' );

// Base64 of the raw Ed25519 public key the dashboard's worker signs drafts
// with. Empty: draft creation is not available and every request is refused.
// wp-config.php may define it first, for a local test with a test key.
if ( ! defined( 'NOFLUFF_ANALYTICS_PUBLISH_KEY' ) ) {
	define( 'NOFLUFF_ANALYTICS_PUBLISH_KEY', 'AC1bEtNnA+T7x2YMMsQxaL1v1p3H/1zOJLfmsXmMmuA=' );
}

add_action(
	'init',
	static function () {
		// Bundled translations (German). Not needed once translations come from WordPress.org.
		load_plugin_textdomain( 'nofluff-analytics', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	}
);

require_once __DIR__ . '/includes/tracking.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/updater.php';
require_once __DIR__ . '/includes/drafts.php';

/**
 * Settings with defaults. Stored as one option.
 *
 * @return array{tracking_id: string, ingest_secret: string, exclude_editors: bool, track_forms: bool, track_orders: bool, identity_snippet: bool, allow_drafts: bool, draft_author: int}
 */
function nofluff_analytics_settings() {
	$saved = get_option( NOFLUFF_ANALYTICS_OPTION, array() );
	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'tracking_id'      => '',
			'ingest_secret'    => '',
			'exclude_editors'  => true,
			'track_forms'      => true,
			'track_orders'     => true,
			// Ticked by the site owner once the identity snippet is in their
			// consent tool. It only switches the privacy policy suggestion:
			// the plugin never loads nf-id.js, because it cannot know whether
			// a visitor agreed.
			'identity_snippet' => false,
			// Ticked by the site owner: the dashboard may create drafts, as
			// the user who ticked it (draft_author).
			'allow_drafts'     => false,
			'draft_author'     => 0,
		)
	);
}

/**
 * Where the tracker is served from. One regional deployment per host; the
 * EU one is the default. Override with the NOFLUFF_ANALYTICS_HOST constant
 * in wp-config.php or the filter of the same name.
 *
 * @return string Origin without trailing slash.
 */
function nofluff_analytics_host() {
	$host = defined( 'NOFLUFF_ANALYTICS_HOST' ) ? NOFLUFF_ANALYTICS_HOST : 'https://app.nofluff.agency';
	return untrailingslashit( (string) apply_filters( 'nofluff_analytics_host', $host ) );
}

/**
 * Accepts the bare tracking id or the whole snippet copied from the
 * dashboard, and returns the 24-character id, or '' if none is found.
 *
 * @param string $input Pasted value.
 * @return string
 */
function nofluff_analytics_parse_tracking_id( $input ) {
	$input = trim( (string) $input );
	if ( preg_match( '/data-site\s*=\s*["\']([a-f0-9]{24})["\']/i', $input, $m ) ) {
		return strtolower( $m[1] );
	}
	if ( preg_match( '/^[a-f0-9]{24}$/i', $input ) ) {
		return strtolower( $input );
	}
	return '';
}

register_uninstall_hook( __FILE__, 'nofluff_analytics_uninstall' );

/**
 * Remove every trace on uninstall (not on deactivate). Drafts the dashboard
 * created stay: they are the site's content. Nonce transients expire.
 */
function nofluff_analytics_uninstall() {
	delete_option( NOFLUFF_ANALYTICS_OPTION );
	delete_site_transient( 'nofluff_analytics_release' );
	delete_post_meta_by_key( '_nofluff_text_id' );
	delete_post_meta_by_key( '_nofluff_hash' );
}
