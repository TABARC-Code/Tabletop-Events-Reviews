<?php
/**
 * Runs only when the plugin is deleted from wp-admin (not on simple
 * deactivation). Same conservative approach as the rest of this
 * family — removes only the plugin's own version marker, keeps every
 * review post and its meta in place, since a review is content
 * someone wrote, not the plugin's to discard just because the add-on
 * was removed.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tevr_plugin_version' );
