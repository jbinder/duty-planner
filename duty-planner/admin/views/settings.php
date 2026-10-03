<?php
/**
 * Settings screen.
 *
 * @var array $settings
 * @var array $window
 */

namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

$opt        = Settings::OPTION;
$field      = static function ( string $key ) use ( $opt ) {
	return $opt . '[' . $key . ']';
};
$templates  = array(
	'confirmation' => __( 'Confirmation (after sign-up)', 'duty-planner' ),
	'reminder'     => __( 'Reminder', 'duty-planner' ),
	'cancellation' => __( 'Cancellation', 'duty-planner' ),
	'skip'         => __( 'Date cancelled by admin', 'duty-planner' ),
	'alert'        => __( 'Admin alert: duties need people', 'duty-planner' ),
);
$next_run   = wp_next_scheduled( Scheduler::HOOK );
$last_alert = get_option( Scheduler::LAST_ALERT_OPT );
?>
<div class="wrap dutyplan-admin">
	<h1><?php esc_html_e( 'Duty Planner settings', 'duty-planner' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'dutyplan_settings_group' ); ?>

		<h2 class="title"><?php esc_html_e( 'General', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dp-language"><?php esc_html_e( 'Language', 'duty-planner' ); ?></label></th>
				<td>
					<select id="dp-language" name="<?php echo esc_attr( $field( 'language' ) ); ?>">
						<option value="" <?php selected( $settings['language'], '' ); ?>>
							<?php
							/* translators: %s: language name, e.g. "English" */
							printf( esc_html__( 'Same as WordPress (currently %s)', 'duty-planner' ), esc_html( I18n::languages()[ I18n::closest( get_locale() ) ] ) );
							?>
						</option>
						<?php foreach ( I18n::languages() as $locale => $name ) : ?>
							<option value="<?php echo esc_attr( $locale ); ?>" <?php selected( $settings['language'], $locale ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Language of the public calendar, the emails and these admin screens. Email texts you have not changed switch along; texts you edited stay as they are.', 'duty-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-page"><?php esc_html_e( 'Calendar page', 'duty-planner' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => esc_attr( $field( 'calendar_page_id' ) ),
							'id'                => 'dp-page',
							'selected'          => (int) $settings['calendar_page_id'],
							'show_option_none'  => esc_html__( '— Home page —', 'duty-planner' ),
							'option_none_value' => '0',
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Choosing a page does not add anything to it. First put the [duty_planner] shortcode on the page (block editor: Shortcode block; Elementor: Shortcode widget), then select it here. The plugin uses it for the links in emails, the unlisted option and the link shown in the admin.', 'duty-planner' ); ?></p>
					<?php Admin::calendar_link(); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Visibility', 'duty-planner' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $field( 'unlisted' ) ); ?>" value="1" <?php checked( $settings['unlisted'] ); ?>> <?php esc_html_e( 'Keep the calendar page unlisted', 'duty-planner' ); ?></label>
					<p class="description"><?php esc_html_e( 'Hides the page from automatic page menus, site search, the sitemap and search engines (noindex). It stays reachable for everyone who has the link – this is not access control; use the allowlist to restrict sign-ups. Menus you built by hand are not changed.', 'duty-planner' ); ?></p>
					<?php if ( $settings['unlisted'] && ! (int) $settings['calendar_page_id'] ) : ?>
						<p class="description" style="color:#b32d2e"><?php esc_html_e( 'Select the calendar page above, otherwise there is nothing to hide.', 'duty-planner' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-from-name"><?php esc_html_e( 'Sender name', 'duty-planner' ); ?></label></th>
				<td><input type="text" id="dp-from-name" class="regular-text" name="<?php echo esc_attr( $field( 'from_name' ) ); ?>" value="<?php echo esc_attr( $settings['from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-from-email"><?php esc_html_e( 'Sender email', 'duty-planner' ); ?></label></th>
				<td>
					<input type="email" id="dp-from-email" class="regular-text" name="<?php echo esc_attr( $field( 'from_email' ) ); ?>" value="<?php echo esc_attr( $settings['from_email'] ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the WordPress default.', 'duty-planner' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Reminders', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dp-default-reminder"><?php esc_html_e( 'Default reminder for new spots', 'duty-planner' ); ?></label></th>
				<td>
					<input type="number" id="dp-default-reminder" min="0" max="720" class="small-text" name="<?php echo esc_attr( $field( 'default_reminder_hours' ) ); ?>" value="<?php echo esc_attr( $settings['default_reminder_hours'] ); ?>">
					<?php esc_html_e( 'hours before the start', 'duty-planner' ); ?>
					<p class="description"><?php esc_html_e( 'Each spot has its own reminder setting; this is only the preset for new spots.', 'duty-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-allday-ref"><?php esc_html_e( 'All-day reference time', 'duty-planner' ); ?></label></th>
				<td>
					<input type="time" id="dp-allday-ref" name="<?php echo esc_attr( $field( 'allday_reference_time' ) ); ?>" value="<?php echo esc_attr( $settings['allday_reference_time'] ); ?>">
					<p class="description"><?php esc_html_e( 'Reminders for all-day spots are counted back from this time on the day of the duty.', 'duty-planner' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Admin alert', 'duty-planner' ); ?></h2>
		<p><?php esc_html_e( 'Once a week, the recipients get a summary of all duties that have not reached their minimum number of people. No email is sent when everything is covered.', 'duty-planner' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dp-recipients"><?php esc_html_e( 'Recipients', 'duty-planner' ); ?></label></th>
				<td>
					<textarea id="dp-recipients" class="large-text code" rows="3" name="<?php echo esc_attr( $field( 'alert_recipients' ) ); ?>"><?php echo esc_textarea( $settings['alert_recipients'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One email address per line. Leave empty to disable the alert.', 'duty-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-alert-day"><?php esc_html_e( 'Send on', 'duty-planner' ); ?></label></th>
				<td>
					<select id="dp-alert-day" name="<?php echo esc_attr( $field( 'alert_weekday' ) ); ?>">
						<?php foreach ( Time::weekday_names() as $num => $name ) : ?>
							<option value="<?php echo esc_attr( $num ); ?>" <?php selected( (int) $settings['alert_weekday'], $num ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php esc_html_e( 'at', 'duty-planner' ); ?>
					<input type="time" name="<?php echo esc_attr( $field( 'alert_time' ) ); ?>" value="<?php echo esc_attr( $settings['alert_time'] ); ?>" aria-label="<?php esc_attr_e( 'Time', 'duty-planner' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-alert-target"><?php esc_html_e( 'Check', 'duty-planner' ); ?></label></th>
				<td>
					<select id="dp-alert-target" name="<?php echo esc_attr( $field( 'alert_target' ) ); ?>">
						<option value="next" <?php selected( $settings['alert_target'], 'next' ); ?>><?php esc_html_e( 'the following week (Mon–Sun)', 'duty-planner' ); ?></option>
						<option value="current" <?php selected( $settings['alert_target'], 'current' ); ?>><?php esc_html_e( 'the rest of the current week', 'duty-planner' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Sign-up allowlist', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dp-allowlist"><?php esc_html_e( 'Allowed people', 'duty-planner' ); ?></label></th>
				<td>
					<textarea id="dp-allowlist" class="large-text code" rows="5" name="<?php echo esc_attr( $field( 'allowlist' ) ); ?>" placeholder="anna@example.org&#10;@example.org"><?php echo esc_textarea( $settings['allowlist'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Optional. One email address or @domain per line. If filled, only matching addresses can sign up. Leave empty to allow everyone. Spots with their own allowlist use that instead.', 'duty-planner' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Email templates', 'duty-planner' ); ?></h2>
		<details class="dutyplan-placeholders">
			<summary><?php esc_html_e( 'Available placeholders', 'duty-planner' ); ?></summary>
			<table class="widefat striped">
				<?php foreach ( Mailer::placeholders() as $ph => $desc ) : ?>
					<tr><td><code><?php echo esc_html( $ph ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
				<?php endforeach; ?>
			</table>
		</details>
		<table class="form-table" role="presentation">
			<?php foreach ( $templates as $key => $label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<input type="text" class="large-text" aria-label="<?php esc_attr_e( 'Subject', 'duty-planner' ); ?>" name="<?php echo esc_attr( $field( "tpl_{$key}_subject" ) ); ?>" value="<?php echo esc_attr( $settings[ "tpl_{$key}_subject" ] ); ?>">
						<textarea class="large-text" rows="8" aria-label="<?php esc_attr_e( 'Body', 'duty-planner' ); ?>" name="<?php echo esc_attr( $field( "tpl_{$key}_body" ) ); ?>"><?php echo esc_textarea( $settings[ "tpl_{$key}_body" ] ); ?></textarea>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"></th>
				<td><p class="description"><?php esc_html_e( 'Empty fields are reset to the default text.', 'duty-planner' ); ?></p></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Data', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'duty-planner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( $field( 'delete_data' ) ); ?>" value="1" <?php checked( $settings['delete_data'] ); ?>> <?php esc_html_e( 'Delete all spots, registrations and settings when the plugin is deleted', 'duty-planner' ); ?></label></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Changes', 'duty-planner' ) ); ?>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Status', 'duty-planner' ); ?></h2>
	<ul class="dutyplan-status">
		<li>
			<?php esc_html_e( 'Next background check:', 'duty-planner' ); ?>
			<strong><?php echo $next_run ? esc_html( Time::format_datetime( (int) $next_run ) ) : esc_html__( 'not scheduled', 'duty-planner' ); ?></strong>
			<span class="description"><?php esc_html_e( '(runs every 15 minutes via WP-Cron; on low-traffic sites set up a real cron job calling wp-cron.php)', 'duty-planner' ); ?></span>
		</li>
		<li>
			<?php esc_html_e( 'Alert this week:', 'duty-planner' ); ?>
			<strong><?php echo esc_html( Time::format_datetime( $window['moment']->getTimestamp(), true ) ); ?></strong>
			<?php if ( $last_alert === $window['week'] ) : ?>
				<span class="description"><?php esc_html_e( '(already done)', 'duty-planner' ); ?></span>
			<?php endif; ?>
		</li>
	</ul>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php Admin::form_fields( 'dutyplan_test_alert' ); ?>
		<p>
			<button class="button"><?php esc_html_e( 'Send admin alert now', 'duty-planner' ); ?></button>
			<span class="description">
				<?php
				printf(
					/* translators: %s: period */
					esc_html__( 'Checks %s and emails the recipients if any duty is understaffed. Does not affect the weekly schedule.', 'duty-planner' ),
					esc_html( Time::format_date( $window['from'] ) . ' – ' . Time::format_date( $window['to'] ) )
				);
				?>
			</span>
		</p>
	</form>
</div>
