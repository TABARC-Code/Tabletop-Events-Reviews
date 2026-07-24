<?php
/**
 * The tevr_review CPT. Deliberately not a public post type — a review
 * has no single page of its own, it only ever appears aggregated
 * inside the [tabletop_event_reviews] / [tabletop_organiser_reviews]
 * widgets. wp-admin still gets the normal moderation screen (every
 * review submission starts pending, same as an event submission),
 * nested under the Events Calendar menu rather than getting its own
 * top-level entry, since this is one small moderation queue, not a
 * whole content type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEVR_Post_Type {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . TEVR_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	public function register_post_type() {
		register_post_type(
			TEVR_POST_TYPE,
			array(
				'labels'        => array(
					'name'          => __( 'Reviews', 'tabletop-events-reviews' ),
					'singular_name' => __( 'Review', 'tabletop-events-reviews' ),
					'all_items'     => __( 'Reviews', 'tabletop-events-reviews' ),
					'search_items'  => __( 'Search Reviews', 'tabletop-events-reviews' ),
					'not_found'     => __( 'No reviews found.', 'tabletop-events-reviews' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=' . TEC_POST_TYPE,
				'publicly_queryable' => false,
				'has_archive'        => false,
				'supports'           => array( 'title' ),
				'show_in_rest'       => false, // Vetted through our own REST endpoints, same reasoning as every other CPT in this family.
			)
		);
	}

	public function add_meta_box() {
		add_meta_box(
			'tevr_review_meta',
			__( 'Review Details', 'tabletop-events-reviews' ),
			array( $this, 'render_meta_box' ),
			TEVR_POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'tevr_save_review_meta', 'tevr_review_meta_nonce' );

		$event_id  = (int) get_post_meta( $post->ID, '_tevr_event_id', true );
		$event     = $event_id ? get_post( $event_id ) : null;
		$rating    = (int) get_post_meta( $post->ID, '_tevr_rating', true );
		$comment   = get_post_meta( $post->ID, '_tevr_comment', true );
		$name      = get_post_meta( $post->ID, '_tevr_reviewer_name', true );
		$email     = get_post_meta( $post->ID, '_tevr_reviewer_email', true );
		$org_email = get_post_meta( $post->ID, '_tevr_organiser_email', true );
		?>
		<p>
			<strong><?php esc_html_e( 'Event:', 'tabletop-events-reviews' ); ?></strong>
			<?php echo $event ? esc_html( get_the_title( $event ) ) . ' (#' . (int) $event_id . ')' : esc_html__( '(deleted event)', 'tabletop-events-reviews' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Reviewer (private):', 'tabletop-events-reviews' ); ?></strong>
			<?php echo esc_html( $name ); ?> — <?php echo esc_html( $email ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Organiser email (private):', 'tabletop-events-reviews' ); ?></strong>
			<?php echo esc_html( $org_email ); ?>
		</p>
		<p>
			<label for="tevr_rating"><strong><?php esc_html_e( 'Rating', 'tabletop-events-reviews' ); ?></strong></label>
			<select name="tevr_rating" id="tevr_rating">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $rating, $i ); ?>><?php echo esc_html( $i ); ?></option>
				<?php endfor; ?>
			</select>
		</p>
		<p>
			<label for="tevr_comment"><strong><?php esc_html_e( 'Comment', 'tabletop-events-reviews' ); ?></strong></label><br>
			<textarea name="tevr_comment" id="tevr_comment" rows="4" style="width:100%;"><?php echo esc_textarea( $comment ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'Publish this post to make the review public. It only ever appears via the review shortcodes — it has no page of its own.', 'tabletop-events-reviews' ); ?></p>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['tevr_review_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tevr_review_meta_nonce'] ) ), 'tevr_save_review_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['tevr_rating'] ) ) {
			$rating = (int) $_POST['tevr_rating'];
			update_post_meta( $post_id, '_tevr_rating', max( 1, min( 5, $rating ) ) );
		}
		if ( isset( $_POST['tevr_comment'] ) ) {
			update_post_meta( $post_id, '_tevr_comment', sanitize_textarea_field( wp_unslash( $_POST['tevr_comment'] ) ) );
		}
	}
}
