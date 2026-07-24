<?php
/**
 * /wp-json/tevr/v1/* — submit a review, read one event's reviews, read
 * one organiser's reviews (aggregated across every event of theirs).
 * No caching layer: review volume is expected to be low and changes
 * rarely, same reasoning as the LFG board's REST class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEVR_Rest {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'tevr/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tevr/v1',
			'/event/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_event_reviews' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tevr/v1',
			'/organiser/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_organiser_reviews' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function submit( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: $request->get_body_params();

		if ( ! empty( $params['website'] ) ) {
			return rest_ensure_response( array( 'success' => true ) ); // Honeypot.
		}

		$event_id = (int) ( $params['event_id'] ?? 0 );
		$event    = get_post( $event_id );
		if ( ! $event || TEC_POST_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			return new WP_Error( 'tevr_no_event', __( 'That event could not be found.', 'tabletop-events-reviews' ), array( 'status' => 404 ) );
		}

		$end_date = get_post_meta( $event_id, '_tec_end_date', true ) ?: get_post_meta( $event_id, '_tec_date', true );
		if ( $end_date >= current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'tevr_too_early', __( "You can only review an event after it's happened.", 'tabletop-events-reviews' ), array( 'status' => 400 ) );
		}

		$name  = sanitize_text_field( $params['name'] ?? '' );
		$email = sanitize_email( $params['email'] ?? '' );
		$rating = (int) ( $params['rating'] ?? 0 );
		$comment = sanitize_textarea_field( $params['comment'] ?? '' );

		if ( ! $name || ! is_email( $email ) ) {
			return new WP_Error( 'tevr_invalid', __( 'Please enter your name and the email you RSVP\'d with.', 'tabletop-events-reviews' ), array( 'status' => 400 ) );
		}
		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'tevr_invalid', __( 'Please choose a rating from 1 to 5.', 'tabletop-events-reviews' ), array( 'status' => 400 ) );
		}
		if ( ! $comment ) {
			return new WP_Error( 'tevr_invalid', __( 'Please add a short comment.', 'tabletop-events-reviews' ), array( 'status' => 400 ) );
		}

		if ( ! $this->was_confirmed_attendee( $event_id, $email ) ) {
			return new WP_Error( 'tevr_not_attendee', __( "We couldn't find a confirmed RSVP for that email on this event, so we can't accept a review from it.", 'tabletop-events-reviews' ), array( 'status' => 403 ) );
		}

		if ( $this->already_reviewed( $event_id, $email ) ) {
			return new WP_Error( 'tevr_duplicate', __( "You've already reviewed this event.", 'tabletop-events-reviews' ), array( 'status' => 409 ) );
		}

		$organiser_email = strtolower( trim( get_post_meta( $event_id, '_tec_organiser_email', true ) ) );

		$review_id = wp_insert_post(
			array(
				'post_type'   => TEVR_POST_TYPE,
				'post_status' => 'pending',
				'post_title'  => sprintf( 'Review of "%s" by %s', get_the_title( $event ), $name ),
			),
			true
		);
		if ( is_wp_error( $review_id ) ) {
			return $review_id;
		}

		update_post_meta( $review_id, '_tevr_event_id', $event_id );
		update_post_meta( $review_id, '_tevr_organiser_email', $organiser_email );
		update_post_meta( $review_id, '_tevr_reviewer_name', $name );
		update_post_meta( $review_id, '_tevr_reviewer_email', strtolower( $email ) );
		update_post_meta( $review_id, '_tevr_rating', $rating );
		update_post_meta( $review_id, '_tevr_comment', $comment );

		$this->notify_admin( $review_id, $event, $rating );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( "Thanks — your review is awaiting a quick check before it goes live.", 'tabletop-events-reviews' ),
			)
		);
	}

	/**
	 * A "confirmed" RSVP is the one status that means they actually had
	 * a spot, as opposed to "waitlisted" and never getting in — see the
	 * matching comment in the core plugin's class-tec-attendees.php
	 * about why this plugin doesn't just check "RSVP exists at all".
	 */
	private function was_confirmed_attendee( $event_id, $email ) {
		if ( ! class_exists( 'TEC_Rsvp' ) ) {
			return false;
		}
		$email = strtolower( trim( $email ) );
		foreach ( TEC_Rsvp::instance()->get_list( $event_id ) as $entry ) {
			if ( strtolower( $entry['email'] ) === $email && 'confirmed' === $entry['status'] ) {
				return true;
			}
		}
		return false;
	}

	private function already_reviewed( $event_id, $email ) {
		$existing = get_posts(
			array(
				'post_type'      => TEVR_POST_TYPE,
				'post_status'    => array( 'publish', 'pending' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_tevr_event_id', 'value' => $event_id, 'compare' => '=' ),
					array( 'key' => '_tevr_reviewer_email', 'value' => strtolower( trim( $email ) ), 'compare' => '=' ),
				),
			)
		);
		return ! empty( $existing );
	}

	private function notify_admin( $review_id, $event, $rating ) {
		$to = class_exists( 'TEC_Settings' ) ? TEC_Settings::get()['notification_email'] : get_option( 'admin_email' );
		if ( ! $to ) {
			return;
		}
		wp_mail(
			$to,
			sprintf( '[%s] New review awaiting approval: %s', get_bloginfo( 'name' ), get_the_title( $event ) ),
			sprintf(
				"A %d-star review came in for \"%s\".\n\nReview it here:\n%s\n",
				$rating,
				get_the_title( $event ),
				admin_url( 'post.php?post=' . $review_id . '&action=edit' )
			)
		);
	}

	public function get_event_reviews( WP_REST_Request $request ) {
		$event_id = (int) $request->get_param( 'id' );
		$posts    = get_posts(
			array(
				'post_type'      => TEVR_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array( 'key' => '_tevr_event_id', 'value' => $event_id, 'compare' => '=' ),
				),
			)
		);

		return rest_ensure_response( $this->build_payload( $posts ) );
	}

	/**
	 * Anchored on any one of the organiser's own event IDs, exactly the
	 * pattern the core plugin's [tabletop_organiser_events] shortcode
	 * already uses — no separate organiser identity/login needed here
	 * either.
	 */
	public function get_organiser_reviews( WP_REST_Request $request ) {
		$anchor_id = (int) $request->get_param( 'id' );
		$anchor    = get_post( $anchor_id );
		if ( ! $anchor || TEC_POST_TYPE !== $anchor->post_type ) {
			return new WP_Error( 'tevr_no_anchor', __( 'That event could not be found.', 'tabletop-events-reviews' ), array( 'status' => 404 ) );
		}
		$organiser_email = strtolower( trim( get_post_meta( $anchor_id, '_tec_organiser_email', true ) ) );
		if ( ! $organiser_email ) {
			return rest_ensure_response( $this->build_payload( array() ) );
		}

		$posts = get_posts(
			array(
				'post_type'      => TEVR_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array( 'key' => '_tevr_organiser_email', 'value' => $organiser_email, 'compare' => '=' ),
				),
			)
		);

		return rest_ensure_response( $this->build_payload( $posts, true ) );
	}

	private function build_payload( $posts, $include_event_title = false ) {
		$reviews = array();
		$total   = 0;
		foreach ( $posts as $post ) {
			$rating = (int) get_post_meta( $post->ID, '_tevr_rating', true );
			$total += $rating;
			$item   = array(
				'name'    => get_post_meta( $post->ID, '_tevr_reviewer_name', true ),
				'rating'  => $rating,
				'comment' => get_post_meta( $post->ID, '_tevr_comment', true ),
				'date'    => $post->post_date,
			);
			if ( $include_event_title ) {
				$event_id = (int) get_post_meta( $post->ID, '_tevr_event_id', true );
				$item['eventTitle'] = $event_id ? get_the_title( $event_id ) : '';
			}
			$reviews[] = $item;
		}

		return array(
			'count'   => count( $reviews ),
			'average' => $reviews ? round( $total / count( $reviews ), 1 ) : null,
			'reviews' => $reviews,
		);
	}
}
