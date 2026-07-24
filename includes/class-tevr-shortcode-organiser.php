<?php
/**
 * [tabletop_organiser_reviews organiser="123"] — an organiser's
 * aggregate rating and review list across every one of their events,
 * anchored on any one of their own event IDs (the same identity
 * pattern the core plugin's [tabletop_organiser_events] and the
 * venues plugin's profile pages already use). Meant to sit on an
 * organiser's profile page (Venues plugin) or wherever else a site
 * wants to show "this organiser's reputation" without a login system.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEVR_Shortcode_Organiser {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_shortcode( 'tabletop_organiser_reviews', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'organiser' => 0 ), $atts );
		$anchor_id = (int) $atts['organiser'];
		if ( ! $anchor_id ) {
			return '';
		}

		wp_enqueue_style( 'tevr-reviews', TEVR_PLUGIN_URL . 'assets/css/reviews.css', array(), TEVR_VERSION );
		wp_print_styles( 'tevr-reviews' );

		wp_enqueue_script( 'tevr-organiser-reviews', TEVR_PLUGIN_URL . 'assets/js/organiser-reviews.js', array(), TEVR_VERSION, true );
		wp_localize_script(
			'tevr-organiser-reviews',
			'TEVR_ORGANISER_REVIEWS',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'tevr/v1' ) ),
				'anchorId' => $anchor_id,
			)
		);

		return '<div class="tevr-reviews-root" data-tevr-organiser-reviews></div>';
	}
}
