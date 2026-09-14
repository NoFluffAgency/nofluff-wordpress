<?php
/**
 * Plugin Name:       No Fluff Analytics
 * Plugin URI:        https://github.com/NoFluffAgency/nofluff-wordpress
 * Description:       Connects this site to the No Fluff dashboard: adds the cookieless tracking script to every page and can report form submissions and WooCommerce orders as goals.
 * Version:           1.0.0
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

define( 'NOFLUFF_ANALYTICS_VERSION', '1.0.0' );
define( 'NOFLUFF_ANALYTICS_FILE', __FILE__ );
define( 'NOFLUFF_ANALYTICS_OPTION', 'nofluff_analytics' );

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

/**
 * Settings with defaults. Stored as one option.
 *
 * @return array{tracking_id: string, exclude_editors: bool, track_forms: bool, track_orders: bool}
 */
function nofluff_analytics_settings() {
	$saved = get_option( NOFLUFF_ANALYTICS_OPTION, array() );
	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'tracking_id'     => '',
			'exclude_editors' => true,
			'track_forms'     => true,
			'track_orders'    => true,
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
 * Remove every trace on uninstall (not on deactivate).
 */
function nofluff_analytics_uninstall() {
	delete_option( NOFLUFF_ANALYTICS_OPTION );
	delete_site_transient( 'nofluff_analytics_release' );
}
