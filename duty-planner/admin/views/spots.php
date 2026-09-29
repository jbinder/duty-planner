<?php
/**
 * Spots list screen.
 *
 * @var \DutyPlanner\Spots_List_Table $table
 */

namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dutyplan-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Spots', 'duty-planner' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( Admin::url( 'dutyplan-spot' ) ); ?>"><?php esc_html_e( 'Add spot', 'duty-planner' ); ?></a>
	<hr class="wp-header-end">
	<p class="description">
		<?php
		printf(
			/* translators: %s: shortcode */
			esc_html__( 'Spots are recurring duties. Show the public calendar on any page with the shortcode %s.', 'duty-planner' ),
			'<code>[duty_planner]</code>'
		);
		?>
	</p>
	<?php $table->display(); ?>
</div>
