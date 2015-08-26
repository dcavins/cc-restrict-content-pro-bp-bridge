<?php
/*
Plugin Name: CC Restrict Content Pro BuddyPress Bridge
Description: Add RCP funtionality to member profiles.
Version: 1.0.0
Requires at least: 3.9
Tested up to: 3.9
License: GPL3
Author: David Cavins
*/

/**
 * CC Restrict Content Pro BuddyPress Bridge
 *
 * @package   CC Restrict Content Pro BuddyPress Bridge
 * @author    CARES staff
 * @license   GPL-2.0+
 * @copyright 2014 CommmunityCommons.org
 */


/* Do our setup after BP is loaded, but before we create the group extension */
function cc_rcpbp_class_init() {
	// We only want to go if Restrict Content Pro is installed and activated.
	if ( defined( 'RCP_PLUGIN_VERSION' ) ) {
		// The main class
		require_once( dirname( __FILE__ ) . '/includes/class-CC_RCPBP.php' );
		add_action( 'bp_include', array( 'CC_RCPBP', 'get_instance' ), 21 );
	}
}
add_action( 'bp_include', 'cc_rcpbp_class_init' );