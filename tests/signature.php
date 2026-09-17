<?php
/**
 * The order signature has to match the dashboard's byte for byte, or the
 * revenue is refused and nobody finds out until a client asks where their
 * sales went. Same frozen fixture as the dashboard's own test of
 * src/lib/ingest/orders.ts.
 *
 * Run: php tests/signature.php
 *
 * @package NoFluffAnalytics
 */

define( 'ABSPATH', __DIR__ );

// tracking.php only registers hooks at load time; nothing else of WordPress
// is needed to check the signature.
function add_action() {} // phpcs:ignore
function add_filter() {} // phpcs:ignore

require __DIR__ . '/../nofluff-analytics/includes/tracking.php';

$secret   = str_repeat( '0', 64 );
$other    = str_repeat( '1', 64 );
$cents    = (int) round( 49.90 * 100 );
$expected = 'daa202ce4edc5fff81acc9588d9db9a07f1d1805b66c84d2d24b33f81c3a6a44';

$checks = array(
	'a 49.90 total is 4990 cents'  => 4990 === $cents,
	'the dashboard fixture'        => $expected === nofluff_analytics_order_signature( $secret, '1042', $cents, 'EUR' ),
	'a changed total changes it'   => $expected !== nofluff_analytics_order_signature( $secret, '1042', $cents + 1, 'EUR' ),
	'a changed number changes it'  => $expected !== nofluff_analytics_order_signature( $secret, '1043', $cents, 'EUR' ),
	'a changed currency too'       => $expected !== nofluff_analytics_order_signature( $secret, '1042', $cents, 'CHF' ),
	'another site\'s secret too'   => $expected !== nofluff_analytics_order_signature( $other, '1042', $cents, 'EUR' ),
);

$failed = 0;
foreach ( $checks as $name => $passed ) {
	echo ( $passed ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failed += $passed ? 0 : 1;
}
exit( $failed > 0 ? 1 : 0 );
