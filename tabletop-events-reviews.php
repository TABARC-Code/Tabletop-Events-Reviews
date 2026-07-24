<?php
/**
 * Plugin Name:       Tabletop Events Calendar — Reviews
 * Plugin URI:        https://github.com/TABARC-Code/tabletop-events-reviews
 * Description:       Post-event ratings and testimonials for Tabletop Events Calendar organisers, gated to people who actually RSVP'd — a review can't be faked by someone who was never there. Requires the Tabletop Events Calendar plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  tabletop-events-calendar
 * Author:            TABARC-Code
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tabletop-events-reviews
 *
 * A review is only accepted if the submitted email appears as a
 * confirmed RSVP on that specific event, checked against the core
 * plugin's own _tec_rsvp_list — the same "anchor on data that already
 * exists" approach as every other plugin in this family, rather than
 * a second identity/verification system of its own.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TEVR_VERSION', '1.0.0' );
define( 'TEVR_PLUGIN_FILE', __FILE__ );
define( 'TEVR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEVR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TEVR_POST_TYPE', 'tevr_review' );

spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'TEVR_' ) !== 0 ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class ) );
		$path = TEVR_PLUGIN_DIR . 'includes/class-' . $slug . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

function tevr_init() {
	if ( ! tevr_dependency_met() ) {
		add_action( 'admin_notices', 'tevr_missing_dependency_notice' );
		return;
	}

	load_plugin_textdomain( 'tabletop-events-reviews', false, dirname( plugin_basename( TEVR_PLUGIN_FILE ) ) . '/languages' );

	TEVR_Post_Type::instance();
	TEVR_Rest::instance();
	TEVR_Shortcode_Reviews::instance();
	TEVR_Shortcode_Organiser::instance();

	tevr_maybe_upgrade();
}
add_action( 'plugins_loaded', 'tevr_init', 20 );

/**
 * Needs TEC_Rsvp specifically — that's the class whose stored RSVP
 * list is what makes a review verifiable in the first place.
 */
function tevr_dependency_met() {
	return defined( 'TEC_POST_TYPE' ) && class_exists( 'TEC_Rsvp' );
}

function tevr_missing_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Tabletop Events Calendar — Reviews requires the Tabletop Events Calendar plugin to be installed and active.', 'tabletop-events-reviews' ) .
		'</p></div>';
}

/**
 * Deferred to 'init' rather than called directly here — see the
 * matching fix/comment in the core plugin's tabletop-events-calendar.php:
 * anything touching rewrite rules this early (on plugins_loaded)
 * crashes on $wp_rewrite not existing yet. This plugin registers no
 * rewrite rules of its own, but flush_rewrite_rules() still needs
 * WordPress fully booted, so the same caution applies.
 */
function tevr_maybe_upgrade() {
	add_action( 'init', 'tevr_run_upgrade', 20 );
}
function tevr_run_upgrade() {
	$installed = get_option( 'tevr_plugin_version' );
	if ( $installed === TEVR_VERSION ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'tevr_plugin_version', TEVR_VERSION, false );
}

function tevr_activate() {
	if ( tevr_dependency_met() ) {
		require_once TEVR_PLUGIN_DIR . 'includes/class-tevr-post-type.php';
		TEVR_Post_Type::instance()->register_post_type();
	}
	flush_rewrite_rules();
	update_option( 'tevr_plugin_version', TEVR_VERSION, false );
}
register_activation_hook( TEVR_PLUGIN_FILE, 'tevr_activate' );

function tevr_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( TEVR_PLUGIN_FILE, 'tevr_deactivate' );
