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

	return array(
		'tracking_id'     => $id,
		'exclude_editors' => ! empty( $input['exclude_editors'] ),
		'track_forms'     => ! empty( $input['track_forms'] ),
		'track_orders'    => ! empty( $input['track_orders'] ),
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
								<?php esc_html_e( 'Report WooCommerce orders as the event "purchase" (order total and currency only)', 'nofluff-analytics' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'To count them as conversions, add a goal of type "Custom event" with that name in the dashboard. Nothing a visitor types into a form is sent.', 'nofluff-analytics' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<p class="description">
			<?php
			printf(
				/* translators: %s: plugin version */
				esc_html__( 'Version %s. The tracker sets no cookies and stores nothing in the visitor\'s browser.', 'nofluff-analytics' ),
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
 * for the site owner, not legal advice.
 */
function nofluff_analytics_privacy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$content = '<p>' . __( 'This website uses No Fluff Analytics, a cookieless web analytics service, to understand how the site is used and to measure its speed.', 'nofluff-analytics' ) . '</p>'
		. '<p>' . __( 'No cookies are set and nothing is stored in your browser. When you open a page, the address of the page, the referring page, your browser language, the screen width and loading-time measurements are sent to No Fluff. Your IP address and browser identifier are used only to form a pseudonymous value that changes every day and cannot be traced back to you; the IP address itself is not stored.', 'nofluff-analytics' ) . '</p>'
		. '<p>' . __( 'Legal basis: our legitimate interest in analysing and improving this website (Art. 6(1)(f) GDPR). The data is processed on servers in the EU on our behalf by No Fluff.', 'nofluff-analytics' ) . '</p>';
	wp_add_privacy_policy_content( __( 'No Fluff Analytics', 'nofluff-analytics' ), wp_kses_post( wpautop( $content, false ) ) );
}
