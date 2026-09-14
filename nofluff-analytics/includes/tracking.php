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
 * Only the order total and currency are sent, never customer data.
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

	$props = array(
		'value'    => (float) $order->get_total(),
		'currency' => $order->get_currency(),
	);
	wp_register_script( 'nofluff-analytics-order', false, array( 'nofluff-analytics-queue' ), NOFLUFF_ANALYTICS_VERSION, true );
	wp_enqueue_script( 'nofluff-analytics-order' );
	wp_add_inline_script( 'nofluff-analytics-order', 'window.nf("event","purchase",' . wp_json_encode( $props ) . ');' );
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
