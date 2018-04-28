<?php
/**
 * This will be where all Drive related edits and tweaks will live.
 * This should enable me to include this file in any theme I use to build this solution.
 *
 * @author Bret Wagner <bwagner@drivestl.com>
 * @version 0.1.0 Initial build
 */

add_action( 'plugins_loaded', 'wpas_drive_custom_fields');

function wpas_drive_custom_fields() {
    if ( function_exists( 'wpas_add_custom_field' ) ) {
        $due_date_args = array(
            'title'            => __( 'Due Date', 'awesome_support'),
            'field_type'       => 'date-field',
            'placeholder'      => strtotime( '+2 weeks' ),
            'default'          => strtotime( '+2 weeks' ),
            'log'              => true,
            'show_column'      => true,
            'sortable_column'  => true,
            // 'sanitize'        => wpas_sanitize_due_date(),
            'html5_pattern'    => '(0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])[- /.](19|20)\d\d',
            'backend_only'     => true,
            'capability'       => 'Support Manager',
        );
        wpas_add_custom_field( 'due_date', $due_date_args);

        $url_args = array(
            'title'              => __( 'Page URL', 'awesome_support'),
            'field_type'         => 'url',
            'placeholder'        => 'https://example.com/page-name/',
            'required'           => true,
            'show_column'        => false,
            'desc'               => __("Please enter the url for the page that needs editing.", 'awesome_support'),
            'show_frontend_list' => false, 
        );
        wpas_add_custom_field( 'page_url', $url_args );
    }
}
