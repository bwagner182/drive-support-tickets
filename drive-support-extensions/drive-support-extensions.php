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
			'html5_pattern'   => '(0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])[- /.](19|20)\d\d',
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

function drive_set_due_date( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	global $wpdb;
	$result = $wpdb->get_var( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_wpas_due_date' AND post_id = %d", $post_id );

	if ( $result ) {
		return;
	}

	return $wpdb->insert(
		$wpdb->postmeta,
		array(
			'post_id'    => $post_id,
			'meta_key'   => '_wpas_due_date',
			'meta_value' => $due_date,
		)
	);
}

add_action( 'publish_ticket', 'drive_set_due_date' );
