<?php
/**
 * Front end: the tracking script and optional goal events.
 *
 * @package NoFluffAnalytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this request should be tracked at all.
 *
 * @return bool
 */
function nofluff_analytics_should_track() {
	$settings = nofluff_analytics_settings();
	if ( '' === $settings['tracking_id'] ) {
		return false;
	}
	if ( is_admin() || is_feed() || is_robots() || is_trackback() || is_preview() || is_customize_preview() ) {
		return false;
	}
	if ( wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	// The agency and the client's own editors would otherwise skew the numbers.
	if ( $settings['exclude_editors'] && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return false;
	}
	/**
	 * Final say for special cases, e.g. a staging domain.
	 *
	 * @param bool $track Whether to add the tracker to this request.
	 */
	return (bool) apply_filters( 'nofluff_analytics_should_track', true );
}

add_action( 'wp_enqueue_scripts', 'nofluff_analytics_enqueue' );

/**
 * Enqueue the tracker in the head with defer, plus the events listener.
 */
function nofluff_analytics_enqueue() {
	if ( ! nofluff_analytics_should_track() ) {
		return;
	}
	$settings = nofluff_analytics_settings();

	// Version null: the tracker is versioned by the dashboard, not by WordPress.
	wp_enqueue_script(
		'nofluff-analytics',
		nofluff_analytics_host() . '/nf.js',
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		array(
			'in_footer' => false,
			'strategy'  => 'defer',
		)
	);

	// A stub that queues nf() calls made before the deferred tracker runs;
	// the tracker replays window.nf.q on load.
	wp_register_script( 'nofluff-analytics-queue', false, array(), NOFLUFF_ANALYTICS_VERSION, false );
	wp_enqueue_script( 'nofluff-analytics-queue' );
	wp_add_inline_script(
		'nofluff-analytics-queue',
		'window.nf=window.nf||function(){(window.nf.q=window.nf.q||[]).push([].slice.call(arguments))};'
	);

	if ( $settings['track_forms'] ) {
		wp_register_script( 'nofluff-analytics-forms', false, array( 'nofluff-analytics-queue' ), NOFLUFF_ANALYTICS_VERSION, true );
		wp_enqueue_script( 'nofluff-analytics-forms' );
		wp_add_inline_script( 'nofluff-analytics-forms', nofluff_analytics_forms_js() );
	}
}

/**
 * Listens for successful submissions of the common form plugins and sends
 * one "form_submit" event with the plugin and form id. Nothing a visitor
 * typed is ever read.
 *
 * @return string
 */
function nofluff_analytics_forms_js() {
	return <<<'JS'
(function () {
  function send(source, id) {
    window.nf("event", "form_submit", { plugin: source, form: String(id || "") });
  }
  // Contact Form 7
  document.addEventListener("wpcf7mailsent", function (e) {
    send("cf7", e.detail && e.detail.contactFormId);
  });
  if (window.jQuery) {
    var $ = window.jQuery;
    // WPForms (AJAX forms)
    $(document).on("wpformsAjaxSubmitSuccess", function (e) {
      send("wpforms", e.target && e.target.getAttribute && e.target.getAttribute("data-formid"));
    });
    // Gravity Forms
    $(document).on("gform_confirmation_loaded", function (e, formId) {
      send("gravityforms", formId);
    });
    // Elementor Pro forms
    $(document).on("submit_success", function (e) {
      var form = e.target && e.target.closest ? e.target.closest("form") : null;
      send("elementor", form && form.getAttribute("name"));
    });
  }
})();
JS;
}

add_action( 'woocommerce_thankyou', 'nofluff_analytics_track_order', 10, 1 );

/**
 * One "purchase" event per WooCommerce order, on the order-received page.
 * Only the order number, total and currency are sent, never customer data.
 *
 * The dashboard counts the money only when the order carries a signature
 * made with the site's order secret, so a stranger with a browser cannot
 * inflate the revenue. Without a secret the event is still sent and still
 * counts as a goal; the revenue is not counted.
 *
 * @param int $order_id Order id.
 */
function nofluff_analytics_track_order( $order_id ) {
	$settings = nofluff_analytics_settings();
	if ( ! $settings['track_orders'] || ! nofluff_analytics_should_track() || ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	// A reload of the thank-you page must not count the order twice.
	if ( ! $order || $order->get_meta( '_nofluff_analytics_tracked' ) ) {
		return;
	}
	$order->update_meta_data( '_nofluff_analytics_tracked', time() );
	$order->save();

	// The number the shop owner sees, because that is what a figure in the
	// dashboard has to be checked against. A numbering plugin may return
	// something longer than the dashboard accepts; the id always fits.
	$number = (string) $order->get_order_number();
	if ( '' === $number || strlen( $number ) > 64 ) {
		$number = (string) $order->get_id();
	}

	$props = array(
		'order_id' => $number,
		'value'    => (float) $order->get_total(),
		'currency' => strtoupper( $order->get_currency() ),
	);

	if ( '' !== $settings['ingest_secret'] ) {
		$props['sig'] = nofluff_analytics_order_signature(
			$settings['ingest_secret'],
			$props['order_id'],
			(int) round( $props['value'] * 100 ),
			$props['currency']
		);
	}

	wp_register_script( 'nofluff-analytics-order', false, array( 'nofluff-analytics-queue' ), NOFLUFF_ANALYTICS_VERSION, true );
	wp_enqueue_script( 'nofluff-analytics-order' );
	wp_add_inline_script( 'nofluff-analytics-order', 'window.nf("event","purchase",' . wp_json_encode( $props ) . ');' );
}

/**
 * The signature the dashboard checks before it counts an order's money.
 * Computed here in PHP, so the secret itself never reaches the browser —
 * only the finished signature does. Whole cents are signed rather than
 * the decimal total because an integer renders the same in PHP and in
 * JavaScript and 49.90 does not, and the secret is used as the 64
 * characters it is shown as, never hex-decoded.
 *
 * Checked against the dashboard's own fixture in tests/signature.php.
 *
 * @param string $secret   Order secret, 64 hex characters.
 * @param string $number   Order number.
 * @param int    $cents    Order total in whole cents.
 * @param string $currency Currency code, upper case.
 * @return string 64 lower-case hex characters.
 */
function nofluff_analytics_order_signature( $secret, $number, $cents, $currency ) {
	return hash_hmac( 'sha256', $number . "\n" . $cents . "\n" . $currency, $secret );
}

add_filter( 'script_loader_tag', 'nofluff_analytics_script_tag', 10, 2 );

/**
 * Adds the tracking id and keeps caching/optimisation plugins from
 * combining, delaying or rewriting the tracker: it reads its own
 * data-site attribute and script URL, which bundling would break.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string
 */
function nofluff_analytics_script_tag( $tag, $handle ) {
	if ( 'nofluff-analytics' !== $handle ) {
		return $tag;
	}
	$settings   = nofluff_analytics_settings();
	$attributes = sprintf(
		' data-site="%s" data-cfasync="false" data-no-optimize="1" data-noptimize="1" data-no-minify="1" data-no-defer="1" nitro-exclude',
		esc_attr( $settings['tracking_id'] )
	);
	return preg_replace( '/<script\b/', '<script' . $attributes, $tag, 1 );
}

// Exclusions for caching plugins that match by URL or handle rather than attribute.
add_filter(
	'rocket_exclude_js',
	static function ( $excluded ) {
		$excluded[] = '/nf.js';
		return $excluded;
	}
);
add_filter(
	'rocket_delay_js_exclusions',
	static function ( $excluded ) {
		$excluded[] = 'nf.js';
		$excluded[] = 'window.nf';
		return $excluded;
	}
);
add_filter(
	'autoptimize_filter_js_exclude',
	static function ( $excluded ) {
		return $excluded . ', nf.js, window.nf';
	}
);
add_filter(
	'sgo_javascript_combine_exclude',
	static function ( $handles ) {
		$handles[] = 'nofluff-analytics';
		return $handles;
	}
);
add_filter(
	'sgo_js_minify_exclude',
	static function ( $handles ) {
		$handles[] = 'nofluff-analytics';
		return $handles;
	}
);
