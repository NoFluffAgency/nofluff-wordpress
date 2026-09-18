<?php
/**
 * Admin: settings page under Settings → No Fluff, a setup notice, the
 * plugin action link and the privacy policy suggestion.
 *
 * @package NoFluffAnalytics
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'nofluff_analytics_menu' );

/**
 * Register the settings page.
 */
function nofluff_analytics_menu() {
	add_options_page(
		__( 'No Fluff Analytics', 'nofluff-analytics' ),
		__( 'No Fluff', 'nofluff-analytics' ),
		'manage_options',
		'nofluff-analytics',
		'nofluff_analytics_render_page'
	);
}

add_action( 'admin_init', 'nofluff_analytics_register_settings' );

/**
 * Register the option with its sanitiser.
 */
function nofluff_analytics_register_settings() {
	register_setting(
		'nofluff_analytics',
		NOFLUFF_ANALYTICS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'nofluff_analytics_sanitize',
			'show_in_rest'      => false,
		)
	);
}

/**
 * Validate the submitted settings.
 *
 * @param mixed $input Raw form input.
 * @return array
 */
function nofluff_analytics_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	$raw   = isset( $input['tracking_id'] ) ? wp_unslash( (string) $input['tracking_id'] ) : '';
	$id    = nofluff_analytics_parse_tracking_id( $raw );

	if ( '' !== trim( $raw ) && '' === $id ) {
		add_settings_error(
			NOFLUFF_ANALYTICS_OPTION,
			'invalid_tracking_id',
			__( 'That is not a valid site ID. Paste the 24-character ID or the whole snippet from your No Fluff dashboard.', 'nofluff-analytics' )
		);
		$id = nofluff_analytics_settings()['tracking_id'];
	}

	$raw_secret = isset( $input['ingest_secret'] ) ? strtolower( trim( wp_unslash( (string) $input['ingest_secret'] ) ) ) : '';
	$secret     = preg_match( '/^[0-9a-f]{64}$/', $raw_secret ) ? $raw_secret : '';

	if ( '' !== $raw_secret && '' === $secret ) {
		add_settings_error(
			NOFLUFF_ANALYTICS_OPTION,
			'invalid_ingest_secret',
			__( 'That is not a valid order secret. Copy the 64-character secret shown next to the site ID in your No Fluff dashboard.', 'nofluff-analytics' )
		);
		$secret = nofluff_analytics_settings()['ingest_secret'];
	}

	return array(
		'tracking_id'      => $id,
		'ingest_secret'    => $secret,
		'exclude_editors'  => ! empty( $input['exclude_editors'] ),
		'track_forms'      => ! empty( $input['track_forms'] ),
		'track_orders'     => ! empty( $input['track_orders'] ),
		'identity_snippet' => ! empty( $input['identity_snippet'] ),
	);
}

/**
 * The settings page.
 */
function nofluff_analytics_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings  = nofluff_analytics_settings();
	$name      = NOFLUFF_ANALYTICS_OPTION;
	$connected = '' !== $settings['tracking_id'];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'No Fluff Analytics', 'nofluff-analytics' ); ?></h1>

		<?php if ( $connected ) : ?>
			<div class="notice notice-success inline"><p>
				<?php esc_html_e( 'Tracking is active. Visits appear in your No Fluff dashboard within the hour.', 'nofluff-analytics' ); ?>
				<a href="<?php echo esc_url( nofluff_analytics_host() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open dashboard', 'nofluff-analytics' ); ?></a>
			</p></div>
		<?php else : ?>
			<p><?php esc_html_e( 'Paste the site ID from your No Fluff dashboard (site page → Setup). Pasting the whole snippet works too.', 'nofluff-analytics' ); ?></p>
		<?php endif; ?>

		<form action="options.php" method="post">
			<?php settings_fields( 'nofluff_analytics' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="nofluff-tracking-id"><?php esc_html_e( 'Site ID', 'nofluff-analytics' ); ?></label></th>
					<td>
						<input type="text" id="nofluff-tracking-id" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[tracking_id]" value="<?php echo esc_attr( $settings['tracking_id'] ); ?>" placeholder="f673eac1b749476300514817" autocomplete="off" spellcheck="false" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nofluff-ingest-secret"><?php esc_html_e( 'Order secret', 'nofluff-analytics' ); ?></label></th>
					<td>
						<input type="password" id="nofluff-ingest-secret" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[ingest_secret]" value="<?php echo esc_attr( $settings['ingest_secret'] ); ?>" autocomplete="off" spellcheck="false" />
						<p class="description"><?php esc_html_e( 'Needed for WooCommerce orders and revenue in the dashboard: it signs each order, so nobody else can report sales for this site. Without it, orders only count as goal conversions. Copy it from the dashboard next to the site ID. Creating a new one there stops orders from counting until you paste it here.', 'nofluff-analytics' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Your own visits', 'nofluff-analytics' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[exclude_editors]" value="1" <?php checked( $settings['exclude_editors'] ); ?> />
							<?php esc_html_e( 'Do not count logged-in users who can edit content (administrators, editors, authors)', 'nofluff-analytics' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Goals', 'nofluff-analytics' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[track_forms]" value="1" <?php checked( $settings['track_forms'] ); ?> />
								<?php esc_html_e( 'Report successful form submissions (Contact Form 7, WPForms, Gravity Forms, Elementor) as the event "form_submit"', 'nofluff-analytics' ); ?>
							</label><br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[track_orders]" value="1" <?php checked( $settings['track_orders'] ); ?> />
								<?php esc_html_e( 'Report WooCommerce orders as the event "purchase" (order number, total and currency)', 'nofluff-analytics' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'To count them as conversions, add a goal of type "Custom event" with that name in the dashboard. Nothing a visitor types into a form is sent.', 'nofluff-analytics' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nofluff-identity-snippet"><?php esc_html_e( 'Identity snippet (optional)', 'nofluff-analytics' ); ?></label></th>
					<td>
						<p><?php esc_html_e( 'With it, a later enquiry or order can be linked to the campaign an earlier visit came from, even a visit of a single page, for visitors who agree to statistics in your consent tool. This plugin never loads it itself, because it cannot know whether a visitor agreed.', 'nofluff-analytics' ); ?></p>
						<p class="description"><?php esc_html_e( 'No Fluff can set this up for you. If you do it yourself, paste the snippet below into the statistics category of your consent tool. The tracking script this plugin adds must stay outside the consent tool and keep loading for everyone, or visits before consent are not counted. The snippet stores one random identifier in the browser and hands it to the tracking script on the same page; it sends nothing itself. For your consent tool\'s service entry: local storage key nf_id, first party, no expiry (the value is renewed after 400 days). Leaving it out changes nothing about the basic statistics.', 'nofluff-analytics' ); ?></p>
						<input type="text" id="nofluff-identity-snippet" class="large-text code" readonly value="<?php echo esc_attr( '<script defer src="' . esc_url( nofluff_analytics_host() . '/nf-id.js' ) . '"></script>' ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- text for the site owner to copy into a consent tool, escaped into an input value, never output as a script. ?>" />
						<p>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[identity_snippet]" value="1" <?php checked( $settings['identity_snippet'] ); ?> />
								<?php esc_html_e( 'I placed the identity snippet in my consent tool', 'nofluff-analytics' ); ?>
							</label>
						</p>
						<p class="description"><?php esc_html_e( 'Your privacy policy has to mention the identifier, including that the current visit and the pages opened earlier in it are linked once the visitor agrees, and that a withdrawal takes effect from the next page, while the visit in which it happens stays linked until it ends. This box only switches the suggested text under Settings → Privacy to that version. Tracking is the same either way.', 'nofluff-analytics' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<p class="description">
			<?php
			printf(
				/* translators: %s: plugin version */
				esc_html__( 'Version %s. The tracking script this plugin adds stores nothing in the visitor\'s browser and reads nothing stored there.', 'nofluff-analytics' ),
				esc_html( NOFLUFF_ANALYTICS_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

add_filter( 'plugin_action_links_' . plugin_basename( NOFLUFF_ANALYTICS_FILE ), 'nofluff_analytics_action_links' );

/**
 * "Settings" link on the Plugins screen.
 *
 * @param string[] $links Existing links.
 * @return string[]
 */
function nofluff_analytics_action_links( $links ) {
	array_unshift(
		$links,
		sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=nofluff-analytics' ) ), esc_html__( 'Settings', 'nofluff-analytics' ) )
	);
	return $links;
}

add_action( 'admin_notices', 'nofluff_analytics_setup_notice' );

/**
 * Remind admins to paste the site ID, on the Plugins and Dashboard screens only.
 */
function nofluff_analytics_setup_notice() {
	if ( ! current_user_can( 'manage_options' ) || '' !== nofluff_analytics_settings()['tracking_id'] ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'dashboard' ), true ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'No Fluff Analytics is not tracking yet.', 'nofluff-analytics' ),
		esc_url( admin_url( 'options-general.php?page=nofluff-analytics' ) ),
		esc_html__( 'Add your site ID', 'nofluff-analytics' )
	);
}

add_action( 'admin_init', 'nofluff_analytics_privacy_content' );

/**
 * Suggested text for Settings → Privacy → Policy Guide. A starting point
 * for the site owner, not legal advice. The consent part is added only
 * once the owner says the identity snippet is in their consent tool.
 */
function nofluff_analytics_privacy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$settings = nofluff_analytics_settings();
	$content  = '<p>' . __( 'This website uses No Fluff Analytics to understand how the site is used and to measure its speed.', 'nofluff-analytics' ) . '</p>'
		. '<p>' . __( 'For the basic statistics, no cookies are set, and nothing is stored in your browser or read from its storage. When you open a page, the address of the page, the referring page, your browser language, the screen width and loading-time measurements are sent to No Fluff. While you use the page, No Fluff also receives how long you actively use it, how far you scroll, which kind of contact link you tap (phone, email, SMS, WhatsApp, map, booking or download, not the number, address or file) and when you start or send a form (its position on the page and its number of fields, never what you enter). Your IP address and browser identifier are used to form a pseudonymous value that changes every day and cannot be traced back to you. From the browser identifier only the device type and the browser and operating system family are stored, and from the connection only the country; the IP address itself is not stored.', 'nofluff-analytics' ) . '</p>';

	if ( $settings['track_orders'] && function_exists( 'wc_get_order' ) ) {
		$content .= '<p>' . __( 'If you place an order, the order number, the order total and the currency are sent to No Fluff so that sales can be counted. Your name, address, payment details and the items you bought are not sent.', 'nofluff-analytics' ) . '</p>';
	}

	$content .= '<p>' . __( 'Legal basis: our legitimate interest in analysing and improving this website (Art. 6(1)(f) GDPR).', 'nofluff-analytics' ) . '</p>';

	if ( $settings['identity_snippet'] ) {
		$content .= '<p>' . __( 'If you agree to statistics in our consent banner, a random identifier is stored in your browser\'s local storage (key nf_id, not a cookie). It stays there until you delete this website\'s data in your browser; on your first visit with consent after 400 days it is replaced by a new one. It contains nothing about you and is used only on this website. It is sent to No Fluff with the pages you open, your enquiries and your orders and stored there with them. With each page No Fluff also stores the referring website, your browser language, device type, browser and operating system family and your country (from the connection), so these are linked across your visits as well. This lets us see that several visits come from the same browser, which campaign link (for example an ad or a newsletter) brought you here, and whether a visit ended in an enquiry or an order.', 'nofluff-analytics' ) . '</p>'
			. '<p>' . __( 'When you agree, the identifier is linked to your current visit at once, including the pages you opened earlier in that visit. No Fluff makes this link on its servers from your IP address and browser identifier, neither of which is stored.', 'nofluff-analytics' ) . '</p>'
			. '<p>' . __( 'Legal basis for the identifier: your consent (Art. 6(1)(a) GDPR, § 25(1) TDDDG). You can withdraw it at any time in the consent settings of this website; from the next page you load, the identifier is no longer read or sent. The visit in which you withdraw stays linked until it ends (after 30 minutes without activity). Withdrawal does not affect the lawfulness of processing before it. Deleting this website\'s data in your browser removes the identifier.', 'nofluff-analytics' ) . '</p>';
	}

	$content .= '<p>' . __( 'No Fluff processes the data on our behalf on servers in the EU. Data about individual visits is deleted 14 months after the end of the month in which it was collected; only aggregated numbers, such as visits per day, are kept.', 'nofluff-analytics' ) . '</p>';

	wp_add_privacy_policy_content( __( 'No Fluff Analytics', 'nofluff-analytics' ), wp_kses_post( wpautop( $content, false ) ) );
}
