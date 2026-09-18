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
$cents    = nofluff_analytics_order_cents( 49.90 );
$expected = 'daa202ce4edc5fff81acc9588d9db9a07f1d1805b66c84d2d24b33f81c3a6a44';

// Node: createHmac( 'sha256', '0'.repeat( 64 ) ).update( '1042\n1602\nKWD' ).
$three_decimals = '8ae36b983588d515c403bf5f7c2d5cc3120806edd4df7b3fc273f57cfb032c9e';

$checks = array(
	'a 49.90 total is 4990 cents'  => 4990 === $cents,
	'the dashboard fixture'        => $expected === nofluff_analytics_order_signature( $secret, '1042', $cents, 'EUR' ),
	'a 3-decimal total, as in JS'  => $three_decimals === nofluff_analytics_order_signature( $secret, '1042', nofluff_analytics_order_cents( 16.025 ), 'KWD' ),
	'a changed total changes it'   => $expected !== nofluff_analytics_order_signature( $secret, '1042', $cents + 1, 'EUR' ),
	'a changed number changes it'  => $expected !== nofluff_analytics_order_signature( $secret, '1043', $cents, 'EUR' ),
	'a changed currency too'       => $expected !== nofluff_analytics_order_signature( $secret, '1042', $cents, 'CHF' ),
	'another site\'s secret too'   => $expected !== nofluff_analytics_order_signature( $other, '1042', $cents, 'EUR' ),
);

// The cents the dashboard's Math.round( value * 100 ) gets, checked in Node.
// 16.025 * 100 is 1602.4999999999998 as a double; PHP's round() says 1603.
$js_cents = array(
	array( 16.025, 1602 ),
	array( 1.005, 100 ),
	array( 10.075, 1007 ),
	array( 1.125, 113 ),
	array( 0.005, 1 ),
	array( 10000.0, 1000000 ),
);
foreach ( $js_cents as $pair ) {
	$checks[ $pair[0] . ' is ' . $pair[1] . ' cents' ] = $pair[1] === nofluff_analytics_order_cents( $pair[0] );
}

$failed = 0;
foreach ( $checks as $name => $passed ) {
	echo ( $passed ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failed += $passed ? 0 : 1;
}
exit( $failed > 0 ? 1 : 0 );
