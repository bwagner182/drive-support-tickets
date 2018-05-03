<?php
/**
 * Added extensions and fields to extend the funtionality of Awesome support.
 *
 * @package   Drive Support Extensions
 * @author    Bret Wagner <bwagner@drivestl.com>
 * @license   GPL-2.0+
 * @link      https://drivesocialmedia.com
 * @copyright 2018 Drive Social Media
 *
 * @wordpress-plugin
 * Plugin Name:       Drive Support Extensions
 * Plugin URI:        https://drivesocialmedia.com
 * Description:       Added extensions and fields to extend the funtionality of Awesome support.
 * Version:           0.1.0
 * Author:            Bret Wagner - Drive Social Media
 * Author URI:        https://drivesocialmedia.com
 * Text Domain:       drive-support-ext
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Log an error to the WordPress debug log.
 *
 * @since  0.1
 *
 * @param  string $message What we want to say in the entry.
 * @return void            No return, writes an entry on the log.
 */
function drive_write_error_log( $message ) {
	if ( true === WP_DEBUG ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			error_log( print_r( $message, true ) );
		} else {
			error_log( $message );
		}
	}
}

add_action( 'plugins_loaded', 'wpas_drive_custom_fields' );

/** Create custom fields for use in Awesome Support Plugin.
 *
 * @return void No return, simply create fields.
 */
function wpas_drive_custom_fields() {
	if ( function_exists( 'wpas_add_custom_field' ) ) {
		$due_date_args = array(
			'title'           => __( 'Due Date', 'awesome_support' ),
			'field_type'      => 'date-field',
			'required'        => false,
			'log'             => true,
			'show_column'     => true,
			'sortable_column' => true,
			'backend_only'    => true,
			'capability'      => 'delete_ticket',
		);
		wpas_add_custom_field( 'due_date', $due_date_args );

		$url_args = array(
			'title'              => __( 'Page URL', 'awesome_support' ),
			'field_type'         => 'url',
			'placeholder'        => 'https://example.com/page-name/',
			'required'           => false,
			'show_column'        => false,
			'desc'               => __( 'Please enter the url for the page that needs editing.', 'awesome_support' ),
			'show_frontend_list' => false,
		);
		wpas_add_custom_field( 'page_url', $url_args );
	}
}

/**
 * Set deault due date for 2 weeks away when submitted by the client
 *
 * @param  string $new_status Status of the post after submission.
 * @param  string $old_status Status of the post prior to submission.
 * @param  object $post       Post object after submission.
 * @return boolean            Returns true on completion.
 */
function drive_set_due_date( $ticket_id ) {
	drive_write_error_log( "Starting set due date." );
	$result = get_post_meta( $ticket_id, '_wpas_due_date', true );

	if ( $result ) {
		// Ticket has a due date.
		return false;
	}

	// Set due date for two weeks from today.
	$due_date = date( 'Y-m-d', time() + ( 14 * 24 * 60 * 60 ) );
	// Insert new due date into database.
	drive_write_error_log( $due_date );
	$ticket_data = array(
		'ID'    => $ticket_id,
		'meta_input' => array(
			'_wpas_due_date' => $due_date,
		),
	);

	$result = wp_update_post( $ticket_data, true );
	drive_write_error_log( "Result:" );
	drive_write_error_log( $result );
	return $result;
}

add_action( 'wpas_tikcet_after_saved', 'drive_set_due_date', 20, 1 );

/**
 * Add custom meta fields for users.
 *
 * @param  object $user User object from WordPress database.
 * @return void         Outputs html to build the fields.
 */
function drive_custom_user_fields( $user ) {
	// Register meta field if it doesn't exist.
	if ( 'add-new-user' !== $user ) {
		if ( ! get_user_meta( $user->ID, 'project-manager' ) ) {
			add_user_meta( $user->ID, 'project-manager', '' );
		}

		if ( ! get_user_meta( $user->ID, 'developer-name' ) ) {
			add_user_meta( $user->ID, 'developer-name', '' );
		}
	}
	?>
	<h3><?php esc_html_e( 'Drive Client Fields' ); ?></h3>

	<table class="form-table">
		<tr>
			<th><label for="project-manager"><?php esc_html_e( 'Project Manager' ); ?></label></th>
			<td>
				<select name="project-manager">
					<option value=""></option>
					<?php
					$managers = drive_get_support_managers();
					$selected = get_user_meta( $user->ID, 'project-manager', true );
					foreach ( $managers as $manager ) {
						?>
						<option value="<?php echo $manager->ID; ?>" <?php if ( $manager->ID === $selected ) { echo "selected=''"; } ?>><?php echo $manager->user_login; ?></option>
						<?php
					}
					?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="developer-name"><?php esc_html_e( 'Developer Name' ); ?></label></th>
			<td>
				<select name="developer-name">
					<option value=''></option>
					<?php
					$agents       = drive_get_support_agents();
					$selected_dev = get_user_meta( $user->ID, 'developer-name', true );
					foreach ( $agents as $agent ) {
						?>
						<option value="<?php echo $agent->ID; ?>" <?php	if ( $agent->ID === $selected_dev) { echo "selected=''"; } ?>><?php echo $agent->user_login; ?></option>
						<?php
					}
					?>
			</td>
		</tr>
	</table>
	<?
}

add_action( 'user_new_form', 'drive_custom_user_fields' );
add_action( 'show_user_profile', 'drive_custom_user_fields' );
add_action( 'edit_user_profile', 'drive_custom_user_fields' );

/**
* Save custom meta fields.
*
* @param  int     $user_id User ID in the database.
* @return void             No return.
*/
function drive_save_custom_user_fields( $user_id ) {
	drive_write_error_log( "Saving custom user fields." );
	if ( !current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}

	update_user_meta( $user_id, 'project-manager', $_POST['project-manager'] );
	update_user_meta( $user_id, 'developer-name', $_POST['developer-name'] );

	drive_write_error_log( "Custom user fields saved." );
}

add_action( 'user_register', 'drive_save_custom_user_fields' );
add_action( 'personal_options_update', 'drive_save_custom_user_fields' );
add_action( 'edit_user_profile_update', 'drive_save_custom_user_fields' );

/**
* Get a list of the support managers from the database.
* @return array List of User IDs and their usernames.
*/
function drive_get_support_managers() {
	drive_write_error_log( "Gettingsupport manager list." );
	global $wpdb;
	$results = $wpdb->get_results( "SELECT u.ID, u.user_login
		FROM wp_users u, wp_usermeta m
		WHERE u.ID = m.user_id
		AND m.meta_key LIKE 'wp_capabilities'
		AND m.meta_value LIKE '%wpas_support_manager%'", OBJECT_K );

	drive_write_error_log( "Results:" );
	drive_write_error_log( $results );
	return $results;
}

/**
* Set project manager for client on ticket submission.
* @param  int     $ticket_id ID of the ticket being created.
* @return boolean            True on success.
*/
function drive_set_project_manager( $ticket_id ) {
	drive_write_error_log( "Setting project manager." );
	// Grab ticket data from database.
	global $wpdb;
	$query = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE `ID` = %d", $ticket_id );
	$result = $wpdb->get_results( $query, OBJECT )[0];

	if ( !$result ) {
		// Ticket doesn't exist.
		return false;
	}

	$author = $result->post_author;

	// Get assigned PM from client.
	$pm = get_user_meta( $author, 'project-manager', true );

	// Insert assigned PM to ticket.
	$ticket_data = array(
		'ID'    => $ticket_id,
		'meta_input' => array(
			'_wpas_secondary_assignee' => $pm,
		),
	);

	$result = wp_update_post( $ticket_data, true );
	drive_write_error_log( "Result:" );
	drive_write_error_log( $result );
	return $result;
}

add_action( 'wpas_tikcet_after_saved', 'drive_set_project_manager', 20, 1);


/**
* Get a list of the support agents from the database.
* @return array List of User IDs and their usernames.
*/
function drive_get_support_agents() {
	drive_write_error_log( "Getting support agent list." );
	global $wpdb;
	$results = $wpdb->get_results( "SELECT u.ID, u.user_login
		FROM wp_users u, wp_usermeta m
		WHERE u.ID = m.user_id
		AND m.meta_key LIKE 'wp_capabilities'
		AND m.meta_value LIKE '%wpas_agent%'", OBJECT_K );

	drive_write_error_log( "Results:" );
	drive_write_error_log( $results );
	return $results;
}

/**
* Set developer for client on ticket submission.
* @param  int     $ticket_id ID of the ticket being created.
* @return boolean            True on success.
*/
function drive_set_developer( $ticket_id ) {
	drive_write_error_log( "Setting developer for ticket." );
	// Grab ticket data from database.
	global $wpdb;
	$query = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE `ID` = %d", $ticket_id );
	$result = $wpdb->get_results( $query, OBJECT )[0];

	if ( !$result ) {
		// Ticket doesn't exist.
		return false;
	}

	$author = $result->post_author;

	// Get assigned PM from client.
	$dev_name = get_user_meta( $author, 'developer-name', true );

	// Insert assigned PM to ticket.
	$ticket_data = array(
		'ID'    => $ticket_id,
		'meta_input' => array(
			'_wpas_assignee' => $dev_name,
		),
	);

	$result = wp_update_post( $ticket_data, true );
	drive_write_error_log( "Result: " );
	drive_write_error_log( $result );
	return $result;
}

add_action( 'wpas_tikcet_after_saved', 'drive_set_developer', 20, 1 );
