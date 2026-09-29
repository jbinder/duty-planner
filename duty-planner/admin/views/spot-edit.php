<?php
/**
 * Add/edit spot screen.
 *
 * @var int   $id
 * @var array $values
 */

namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

$weekday_names = Time::weekday_names();
?>
<div class="wrap dutyplan-admin">
	<h1><?php echo $id ? esc_html__( 'Edit spot', 'duty-planner' ) : esc_html__( 'Add spot', 'duty-planner' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dutyplan-spot-form">
		<?php Admin::form_fields( 'dutyplan_save_spot' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">

		<h2 class="title"><?php esc_html_e( 'Duty', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dp-title"><?php esc_html_e( 'Title', 'duty-planner' ); ?></label></th>
				<td><input type="text" id="dp-title" name="title" class="regular-text" required value="<?php echo esc_attr( $values['title'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-description"><?php esc_html_e( 'Description', 'duty-planner' ); ?></label></th>
				<td><textarea id="dp-description" name="description" class="large-text" rows="3"><?php echo esc_textarea( $values['description'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-location"><?php esc_html_e( 'Location', 'duty-planner' ); ?></label></th>
				<td><input type="text" id="dp-location" name="location" class="regular-text" value="<?php echo esc_attr( $values['location'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'duty-planner' ); ?></th>
				<td><label><input type="checkbox" name="active" value="1" <?php checked( $values['active'] ); ?>> <?php esc_html_e( 'Active (shown in the calendar, open for sign-up)', 'duty-planner' ); ?></label></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'When', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Repeats', 'duty-planner' ); ?></th>
				<td>
					<fieldset class="dutyplan-inline">
						<label><input type="radio" name="frequency" value="daily" <?php checked( $values['frequency'], 'daily' ); ?>> <?php esc_html_e( 'Daily', 'duty-planner' ); ?></label>
						<label><input type="radio" name="frequency" value="weekly" <?php checked( $values['frequency'], 'weekly' ); ?>> <?php esc_html_e( 'Weekly', 'duty-planner' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-interval"><?php esc_html_e( 'Every', 'duty-planner' ); ?></label></th>
				<td>
					<input type="number" id="dp-interval" name="interval_n" min="1" max="52" class="small-text" value="<?php echo esc_attr( $values['interval_n'] ); ?>">
					<span class="dutyplan-when-daily"><?php esc_html_e( 'day(s)', 'duty-planner' ); ?></span>
					<span class="dutyplan-when-weekly"><?php esc_html_e( 'week(s)', 'duty-planner' ); ?></span>
					<p class="description"><?php esc_html_e( 'E.g. 2 = every other day/week.', 'duty-planner' ); ?></p>
				</td>
			</tr>
			<tr class="dutyplan-when-weekly">
				<th scope="row"><?php esc_html_e( 'On', 'duty-planner' ); ?></th>
				<td>
					<fieldset class="dutyplan-inline">
						<?php foreach ( $weekday_names as $num => $name ) : ?>
							<label><input type="checkbox" name="weekdays[]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( (int) $num, array_map( 'intval', (array) $values['weekdays'] ), true ) ); ?>> <?php echo esc_html( $name ); ?></label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-start-date"><?php esc_html_e( 'Starts on', 'duty-planner' ); ?></label></th>
				<td><input type="date" id="dp-start-date" name="start_date" required value="<?php echo esc_attr( $values['start_date'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-end-date"><?php esc_html_e( 'Ends on', 'duty-planner' ); ?></label></th>
				<td>
					<input type="date" id="dp-end-date" name="end_date" value="<?php echo esc_attr( (string) $values['end_date'] ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. Leave empty to repeat indefinitely.', 'duty-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Time', 'duty-planner' ); ?></th>
				<td><label><input type="checkbox" name="all_day" value="1" id="dp-all-day" <?php checked( $values['all_day'] ); ?>> <?php esc_html_e( 'All day (no specific time)', 'duty-planner' ); ?></label></td>
			</tr>
			<tr class="dutyplan-timed">
				<th scope="row"><label for="dp-start-time"><?php esc_html_e( 'Start time', 'duty-planner' ); ?></label></th>
				<td><input type="time" id="dp-start-time" name="start_time" value="<?php echo esc_attr( $values['start_time'] ); ?>"></td>
			</tr>
			<tr class="dutyplan-timed">
				<th scope="row"><label for="dp-duration"><?php esc_html_e( 'Duration', 'duty-planner' ); ?></label></th>
				<td><input type="number" id="dp-duration" name="duration_min" min="1" class="small-text" value="<?php echo esc_attr( (string) $values['duration_min'] ); ?>"> <?php esc_html_e( 'minutes', 'duty-planner' ); ?></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'People', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dp-min"><?php esc_html_e( 'Minimum', 'duty-planner' ); ?></label></th>
				<td>
					<input type="number" id="dp-min" name="min_people" min="0" class="small-text" value="<?php echo esc_attr( $values['min_people'] ); ?>">
					<p class="description"><?php esc_html_e( 'Below this, the duty is marked "Needs people" and included in the admin alert.', 'duty-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-max"><?php esc_html_e( 'Maximum', 'duty-planner' ); ?></label></th>
				<td>
					<input type="number" id="dp-max" name="max_people" min="1" class="small-text" value="<?php echo esc_attr( $values['max_people'] ); ?>">
					<p class="description"><?php esc_html_e( 'When reached, the duty is "Fully booked" and sign-up closes.', 'duty-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dp-allowlist"><?php esc_html_e( 'Allowlist', 'duty-planner' ); ?></label></th>
				<td>
					<textarea id="dp-allowlist" name="allowlist" class="large-text code" rows="3" placeholder="anna@example.org&#10;@example.org"><?php echo esc_textarea( $values['allowlist'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Optional. One email address or @domain per line. If filled, only these may sign up for this spot (overrides the global allowlist). Leave empty to use the global setting.', 'duty-planner' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Notifications', 'duty-planner' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dp-reminder"><?php esc_html_e( 'Reminder', 'duty-planner' ); ?></label></th>
				<td>
					<input type="number" id="dp-reminder" name="reminder_offset_hours" min="0" max="720" class="small-text" value="<?php echo esc_attr( $values['reminder_offset_hours'] ); ?>">
					<?php esc_html_e( 'hours before the start', 'duty-planner' ); ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: time */
							esc_html__( '0 = no reminder. For all-day spots, hours are counted back from %s on that day (see Settings).', 'duty-planner' ),
							esc_html( Settings::get( 'allday_reference_time' ) )
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( $id ? __( 'Save spot', 'duty-planner' ) : __( 'Create spot', 'duty-planner' ) ); ?>
	</form>
</div>
