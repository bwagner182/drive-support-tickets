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

/** Create custom fields for use in Awesome Support Plugin. */
function wpas_drive_custom_fields() {
	if ( function_exists( 'wpas_add_custom_field' ) ) {
		$due_date_args = array(
			'title'           => __( 'Due Date', 'awesome_support' ),
			'field_type'      => 'date-field',
			// 'default'         => strtotime( '+2 weeks' ),
			'required'        => false,
			'log'             => true,
			'show_column'     => true,
			'sortable_column' => true,
			// 'sanitize'       => wpas_sanitize_due_date(),
			// 'html5_pattern'   => '(0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])[- /.](19|20)\d\d',
			'backend_only'    => true,
			'capability'      => 'edit_ticket',
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
 * set deault due date for 2 weeks away when submitted by the client
 * @param  string $new_status Status of the post after submission.
 * @param  string $old_status Status of the post prior to submission.
 * @param  object $post       Post object after submission.
 * @return boolean            Returns true on completion.
 */
function drive_set_due_date( $new_status, $old_status, $post ) {
	// Check to make sure it's a new ticket
	if ( ( 'publish' === $new_status && 'publish' === $old_status ) || 'ticket' !== $post->post_type ) {
		// Not a ticket or not new.
		return false;
	}

	global $wpdb;
	// Check for a due date.
	$result = $wpdb->get_var( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_wpas_due_date' AND post_id = " . $post->ID );

	if ( $result ) {
		// Ticket has a due date.
		return false;
	}

	// Set due date for two weeks from today.
	$due_date = date( 'Y-m-d', time() + ( 14 * 24 * 60 * 60 ) );

	// Insert new due date into database.
	$result = $wpdb->insert(
		$wpdb->postmeta,
		array(
			'post_id'    => $post->ID,
			'meta_key'   => '_wpas_due_date',
			'meta_value' => $due_date,
		)
	);

	return $result;
}

add_action( 'transition_post_status', 'drive_set_due_date', 20, 3 );

/**
 * Add custom meta fields for users.
 * @param  object $user User object from WordPress database.
 * @return void         Outputs html to build the fields.
 */
function drive_custom_user_fields( $user ) {
	// Register meta field if it doesn't exist.
	if ( ! get_user_meta( $user->ID, 'project-manager' ) ) {
		add_user_meta( $user->ID, 'project-manager', '' );
	}
	?>
	<h3><?php esc_html_e( "Drive Client Fields" ); ?></h3>

	<table class="form-table">
		<tr>
			<th><label for="project-manager"><?php esc_html_e( "Project Manager" ); ?></label></th>
			<td>
				<select name="project-manager">
					<option value=""></option>
					<?php
						$managers = drive_get_support_managers();
						$selected = get_user_meta( $user->ID, 'project-manager' )[0];
						drive_write_error_log( "Selected PM ID is: " );
						drive_write_error_log( $selected );
						foreach ( $managers as $manager ) {
							?>
							<option value="<?php echo $manager->ID; ?>" <?php if ( $manager->ID === $selected ) { echo "selected=''"; } ?>><?php echo $manager->user_login; ?></option>
							<?php
						}
					?>
				</select>
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
 * @param  int     $user_id User ID in the database.
 * @return boolean          True on success.
 */
function drive_save_custom_user_fields( $user_id ) {
	if ( !current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}

	update_user_meta( $user_id, 'project-manager', $_POST['project-manager'] );
}

add_action( 'user_register', 'drive_save_custom_user_fields' );
add_action( 'personal_options_update', 'drive_save_custom_user_fields' );
add_action( 'edit_user_profile_update', 'drive_save_custom_user_fields' );

/**
 * Get a list of the support managers from the database.
 * @return array List of User IDs and their usernames.
 */
function drive_get_support_managers() {
	global $wpdb;
	$results = $wpdb->get_results( "SELECT u.ID, u.user_login
		FROM wp_users u, wp_usermeta m
		WHERE u.ID = m.user_id
		AND m.meta_key LIKE 'wp_capabilities'
		AND m.meta_value LIKE '%wpas_support_manager%'", OBJECT_K );
	return $results;
}

/**
 * Set project manager for client on ticket submission.
 * @param  int     $ticket_id ID of the ticket being created.
 * @return boolean            True on success.
 */
function drive_set_project_manager( $ticket_id ) {
	
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
	$result = $wpdb->insert( $wpdb->postmeta, array(
		'post_id'    => $ticket_id,
		'meta_key'   => '_wpas_secondary_assignee',
		'meta_value' => $pm,
	) );

	return $result;
}

add_action( 'wpas_tikcet_after_saved', 'drive_set_project_manager', 20, 1);
