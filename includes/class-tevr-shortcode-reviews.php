<?php
/**
 * [tabletop_event_reviews event="123"] — one event's approved reviews,
 * plus (if the event's happened and this visitor hasn't already left
 * one) a "leave a review" form. Reuses the core plugin's
 * submission-form.css for the form itself rather than duplicating it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEVR_Shortcode_Reviews {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_shortcode( 'tabletop_event_reviews', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'event' => 0 ), $atts );
		$event_id = (int) $atts['event'];
		if ( ! $event_id ) {
			return '';
		}

		wp_enqueue_style( 'tevr-reviews', TEVR_PLUGIN_URL . 'assets/css/reviews.css', array(), TEVR_VERSION );
		if ( defined( 'TEC_PLUGIN_URL' ) ) {
			wp_enqueue_style( 'tec-submit', TEC_PLUGIN_URL . 'assets/css/submission-form.css', array(), TEC_VERSION );
			wp_print_styles( 'tec-submit' );
		}
		wp_enqueue_script( 'tevr-event-reviews', TEVR_PLUGIN_URL . 'assets/js/event-reviews.js', array(), TEVR_VERSION, true );

		// See the matching comment in the core plugin's
		// class-tec-shortcode-calendar.php — a style enqueued during
		// the_content() runs after wp_head()'s print pass.
		wp_print_styles( 'tevr-reviews' );

		wp_localize_script(
			'tevr-event-reviews',
			'TEVR_EVENT_REVIEWS',
			array(
				'restUrl' => esc_url_raw( rest_url( 'tevr/v1' ) ),
				'eventId' => $event_id,
			)
		);

		return '<div class="tevr-reviews-root" data-tevr-event-reviews></div>';
	}
}
