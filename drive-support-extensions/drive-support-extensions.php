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
 * Version:           0.4.0
 * Author:            Bret Wagner - Drive Social Media
 * Author URI:        https://drivesocialmedia.com
 * Text Domain:       drive-support-ext
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

define( 'WPAS_DISABLE_AUTO_ASSIGN', true);

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

/** 
 * Create custom fields for use in Awesome Support Plugin.
 *
 * @since  0.1.0
 *
 * @return void  No return, simply create fields.
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
			'capability'      => 'assign_ticket',
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
 * Add custom meta fields for users.
 *
 * @since  0.1.0
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

		if ( ! get_user_meta( $user->ID, 'company-names' ) ) {
			add_user_meta( $user->ID, 'company-names', '' );
		}
	}
	?>
	<h3><?php esc_html_e( 'Drive Client Fields' ); ?></h3>

	<table class="form-table">
		<tr>
			<th><label for="company-names"><?php esc_html_e( 'Company Name(s)' ); ?></label></th>
			<td>
				<input type="text" name="company-names" value="<?php 'add-new-user' === $user ? '' : get_user_meta( $user->ID, 'company-names', true ); ?>" />
			</td>
		</tr>
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
* @since  0.1.0
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
*
* @since  0.1.0
* 
* @return array List of User IDs and their usernames.
*/
function drive_get_support_managers() {
	drive_write_error_log( "Getting support manager list." );
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
* Get a list of the support agents from the database.
*
* @since  0.1.0
* 
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
 * Set default values for developer, PM and due date after submission.
 *
 * @since  0.3.0
 * @param  int     $ticket_id ID for the ticket that was created.
 * @return boolean            Returns true on success, false on failure.
 */
function drive_set_default_values( $ticket_id ) {
	// Set Developer
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
	drive_write_error_log( "Author: " . $author );
	// Get assigned PM from client.
	$dev_name = get_user_meta( $author, 'developer-name', true );
	drive_write_error_log( "Developer ID: " . $dev_name );

	// Insert assigned developer to ticket.
	$result = update_post_meta( $ticket_id, '_wpas_assignee', $dev_name );
	drive_write_error_log( "Result:" );
	drive_write_error_log( $result );

	$results['developer'] = $result;
	// End set developer
		
	// Start set project manager
	drive_write_error_log( "Setting project manager." );
	// Grab ticket data from database.
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
	$result = update_post_meta( $ticket_id, '_wpas_secondary_assignee', $pm );
	drive_write_error_log( "Result:" );
	drive_write_error_log( $result );
	$results['project-manager'] = $result;
	// End PM assignment
	
	// Start set due date
	drive_write_error_log( "Starting set due date." );
	$result = get_post_meta( $ticket_id, '_wpas_due_date', true );
/*
	if ( $result ) {
		// Ticket has a due date.
		drive_write_error_log( "Ticket has a due date already." );
		return false;
	}
*/
	// Set due date for two weeks from today.
	$due_date = date( 'Y-m-d', time() + ( 14 * 24 * 60 * 60 ) );
	// Insert new due date into database.
	drive_write_error_log( $due_date );

	$result = update_post_meta( $ticket_id, '_wpas_due_date', $due_date );
	drive_write_error_log( "Result:" );
	drive_write_error_log( $result );
	$results['due-date'] = $result;
	// End set due date 
	drive_write_error_log( $results );

	// Need to email newly assigned developer on ticket creation.
}

add_action( 'wpas_open_ticket_after', 'drive_set_default_values', 20, 1 );
