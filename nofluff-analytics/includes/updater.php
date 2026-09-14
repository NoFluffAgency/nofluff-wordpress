<?php
/**
 * Updates from GitHub releases, until the plugin is listed on
 * WordPress.org. Uses the core "Update URI" mechanism (WordPress 5.8+):
 * the header points at github.com, so core asks this filter instead of
 * WordPress.org, and a plugin with the same slug there can never replace
 * this one.
 *
 * Remove this file (and the Update URI header) for the WordPress.org
 * submission; directory plugins must not update themselves.
 *
 * @package NoFluffAnalytics
 */

defined( 'ABSPATH' ) || exit;

define( 'NOFLUFF_ANALYTICS_RELEASES', 'https://api.github.com/repos/NoFluffAgency/nofluff-wordpress/releases/latest' );

add_filter( 'update_plugins_github.com', 'nofluff_analytics_check_update', 10, 3 );

/**
 * Report the latest GitHub release to the core updater.
 *
 * @param array|false $update      Update data from an earlier filter, or false.
 * @param array       $plugin_data Plugin headers.
 * @param string      $plugin_file Plugin basename.
 * @return array|false
 */
function nofluff_analytics_check_update( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( NOFLUFF_ANALYTICS_FILE ) !== $plugin_file ) {
		return $update;
	}
	$release = nofluff_analytics_latest_release();
	if ( ! $release ) {
		return $update;
	}
	return array(
		'id'           => 'github.com/NoFluffAgency/nofluff-wordpress',
		'slug'         => 'nofluff-analytics',
		'plugin'       => $plugin_file,
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => '6.3',
		'requires_php' => '7.4',
	);
}

/**
 * Latest release, cached for twelve hours (one hour after a failure) so the
 * unauthenticated GitHub API limit is never a concern.
 *
 * @return array{version: string, url: string, package: string}|null
 */
function nofluff_analytics_latest_release() {
	$cached = get_site_transient( 'nofluff_analytics_release' );
	if ( is_array( $cached ) ) {
		return empty( $cached['version'] ) ? null : $cached;
	}

	$release  = array();
	$response = wp_remote_get(
		NOFLUFF_ANALYTICS_RELEASES,
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) && ! empty( $body['tag_name'] ) && ! empty( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && 'nofluff-analytics.zip' === $asset['name'] ) {
					$release = array(
						'version' => ltrim( (string) $body['tag_name'], 'v' ),
						'url'     => (string) $body['html_url'],
						'package' => (string) $asset['browser_download_url'],
					);
					break;
				}
			}
		}
	}

	set_site_transient( 'nofluff_analytics_release', $release, $release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ? $release : null;
}
