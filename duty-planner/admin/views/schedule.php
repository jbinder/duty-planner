<?php
/**
 * Schedule screen: upcoming occurrences with registrations.
 *
 * @var string $from
 * @var string $to
 * @var int    $days
 * @var int    $spot
 * @var array  $items
 * @var array  $spots
 * @var array  $counts
 */

namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

$nav = static function ( string $date ) use ( $days, $spot ) {
	return Admin::url( 'dutyplan', array_filter( array( 'from' => $date, 'days' => $days, 'spot' => $spot ) ) );
};
?>
<div class="wrap dutyplan-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Schedule', 'duty-planner' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( Admin::url( 'dutyplan-spot' ) ); ?>"><?php esc_html_e( 'Add spot', 'duty-planner' ); ?></a>
	<hr class="wp-header-end">
	<?php Admin::calendar_link(); ?>

	<div class="dutyplan-toolbar">
		<div class="dutyplan-nav">
			<a class="button" href="<?php echo esc_url( $nav( Time::add_days( $from, -$days ) ) ); ?>" aria-label="<?php esc_attr_e( 'Previous period', 'duty-planner' ); ?>">&larr;</a>
			<a class="button" href="<?php echo esc_url( $nav( Time::today() ) ); ?>"><?php esc_html_e( 'Today', 'duty-planner' ); ?></a>
			<a class="button" href="<?php echo esc_url( $nav( Time::add_days( $from, $days ) ) ); ?>" aria-label="<?php esc_attr_e( 'Next period', 'duty-planner' ); ?>">&rarr;</a>
			<strong class="dutyplan-period"><?php echo esc_html( Time::format_date( $from ) . ' – ' . Time::format_date( $to ) ); ?></strong>
		</div>
		<form method="get" class="dutyplan-filter">
			<input type="hidden" name="page" value="dutyplan">
			<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" aria-label="<?php esc_attr_e( 'From', 'duty-planner' ); ?>">
			<select name="days" aria-label="<?php esc_attr_e( 'Period', 'duty-planner' ); ?>">
				<?php foreach ( array( 7 => __( '1 week', 'duty-planner' ), 14 => __( '2 weeks', 'duty-planner' ), 28 => __( '4 weeks', 'duty-planner' ), 56 => __( '8 weeks', 'duty-planner' ) ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $days, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="spot" aria-label="<?php esc_attr_e( 'Spot', 'duty-planner' ); ?>">
				<option value="0"><?php esc_html_e( 'All spots', 'duty-planner' ); ?></option>
				<?php foreach ( $spots as $s ) : ?>
					<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $spot, $s->id ); ?>><?php echo esc_html( $s->title ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button"><?php esc_html_e( 'Show', 'duty-planner' ); ?></button>
		</form>
	</div>

	<ul class="dutyplan-summary">
		<li class="dutyplan-badge dutyplan-badge-needs"><?php /* translators: %d: count */ echo esc_html( sprintf( __( '%d need people', 'duty-planner' ), $counts['needs'] ) ); ?></li>
		<li class="dutyplan-badge dutyplan-badge-open"><?php /* translators: %d: count */ echo esc_html( sprintf( __( '%d with places free', 'duty-planner' ), $counts['open'] ) ); ?></li>
		<li class="dutyplan-badge dutyplan-badge-full"><?php /* translators: %d: count */ echo esc_html( sprintf( __( '%d fully booked', 'duty-planner' ), $counts['full'] ) ); ?></li>
	</ul>

	<?php if ( ! $items ) : ?>
		<p><?php esc_html_e( 'No duties in this period.', 'duty-planner' ); ?></p>
	<?php else : ?>
	<table class="widefat dutyplan-schedule">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'When', 'duty-planner' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Duty', 'duty-planner' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'duty-planner' ); ?></th>
				<th scope="col"><?php esc_html_e( 'People', 'duty-planner' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'duty-planner' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $items as $o ) : ?>
			<?php
			$row_class = 'dutyplan-row-' . ( $o['skipped'] ? 'skipped' : $o['status'] );
			if ( $o['past'] ) {
				$row_class .= ' dutyplan-row-past';
			}
			?>
			<tr class="<?php echo esc_attr( $row_class ); ?>">
				<td>
					<strong><?php echo esc_html( $o['date_label'] ); ?></strong><br>
					<?php echo esc_html( $o['time_label'] ); ?>
				</td>
				<td>
					<a href="<?php echo esc_url( Admin::url( 'dutyplan-spot', array( 'id' => $o['spot_id'] ) ) ); ?>"><?php echo esc_html( $o['title'] ); ?></a>
					<?php if ( $o['location'] ) : ?>
						<br><span class="description"><?php echo esc_html( $o['location'] ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php echo Admin::status_badge( $o ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php if ( ! $o['skipped'] ) : ?>
						<div class="dutyplan-count">
							<?php
							/* translators: 1: signed up, 2: max, 3: min */
							echo esc_html( sprintf( __( '%1$d / %2$d (min. %3$d)', 'duty-planner' ), $o['count'], $o['max'], $o['min'] ) );
							?>
						</div>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $o['people'] ) : ?>
						<ul class="dutyplan-people">
							<?php foreach ( $o['people'] as $p ) : ?>
								<li>
									<?php echo esc_html( $p['name'] ); ?>
									&lt;<a href="mailto:<?php echo esc_attr( $p['email'] ); ?>"><?php echo esc_html( $p['email'] ); ?></a>&gt;
									<?php if ( '' !== $p['note'] ) : ?>
										<br><span class="dutyplan-note"><?php echo esc_html( $o['note_label'] . ': ' . $p['note'] ); ?></span>
									<?php endif; ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dutyplan-inline-form">
										<?php Admin::form_fields( 'dutyplan_remove_registration' ); ?>
										<input type="hidden" name="registration_id" value="<?php echo esc_attr( $p['id'] ); ?>">
										<input type="hidden" name="notify" value="1">
										<button type="submit" class="button-link button-link-delete dutyplan-confirm" data-confirm="<?php esc_attr_e( 'Remove this person? They will receive a cancellation email.', 'duty-planner' ); ?>"><?php esc_html_e( 'Remove', 'duty-planner' ); ?></button>
									</form>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php elseif ( ! $o['skipped'] ) : ?>
						<span class="description"><?php esc_html_e( 'Nobody yet', 'duty-planner' ); ?></span>
					<?php endif; ?>

					<?php if ( ! $o['skipped'] && ! $o['past'] && 'full' !== $o['status'] ) : ?>
						<details class="dutyplan-add">
							<summary><?php esc_html_e( 'Add person', 'duty-planner' ); ?></summary>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php Admin::form_fields( 'dutyplan_add_registration' ); ?>
								<input type="hidden" name="spot_id" value="<?php echo esc_attr( $o['spot_id'] ); ?>">
								<input type="hidden" name="date" value="<?php echo esc_attr( $o['date'] ); ?>">
								<input type="text" name="name" required placeholder="<?php esc_attr_e( 'Name', 'duty-planner' ); ?>">
								<input type="email" name="email" required placeholder="<?php esc_attr_e( 'Email', 'duty-planner' ); ?>">
								<?php if ( '' !== $o['note_label'] ) : ?>
									<input type="text" name="note" maxlength="200" placeholder="<?php echo esc_attr( $o['note_label'] ); ?>">
								<?php endif; ?>
								<label><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'Send confirmation', 'duty-planner' ); ?></label>
								<button class="button button-small"><?php esc_html_e( 'Add', 'duty-planner' ); ?></button>
							</form>
						</details>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $o['skipped'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php Admin::form_fields( 'dutyplan_unskip' ); ?>
							<input type="hidden" name="spot_id" value="<?php echo esc_attr( $o['spot_id'] ); ?>">
							<input type="hidden" name="date" value="<?php echo esc_attr( $o['date'] ); ?>">
							<button class="button button-small"><?php esc_html_e( 'Restore date', 'duty-planner' ); ?></button>
						</form>
					<?php elseif ( ! $o['past'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php Admin::form_fields( 'dutyplan_skip' ); ?>
							<input type="hidden" name="spot_id" value="<?php echo esc_attr( $o['spot_id'] ); ?>">
							<input type="hidden" name="date" value="<?php echo esc_attr( $o['date'] ); ?>">
							<button class="button button-small dutyplan-confirm" data-confirm="<?php esc_attr_e( 'Cancel this date? Everyone signed up will be notified and removed.', 'duty-planner' ); ?>"><?php esc_html_e( 'Cancel date', 'duty-planner' ); ?></button>
						</form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
