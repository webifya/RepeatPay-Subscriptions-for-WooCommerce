<?php
/**
 * Contextual review request for merchants who have used subscriptions.
 *
 * @package Renewly_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

class RENEWLY_Review {
	const FIRST_USED_OPTION = 'renewly_review_first_used_at';
	const REMIND_AT_OPTION  = 'renewly_review_remind_at';
	const DISMISSED_OPTION  = 'renewly_review_dismissed';
	const WAIT_DAYS         = 14;

	/** Register review-request hooks. */
	public static function init() {
		add_action( 'renewly_subscription_created', array( __CLASS__, 'record_first_use' ) );
		add_action( 'admin_init', array( __CLASS__, 'seed_existing_use' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_renewly_review_remind_later', array( __CLASS__, 'remind_later' ) );
		add_action( 'admin_post_renewly_review_dismiss', array( __CLASS__, 'dismiss' ) );
	}

	/** Start the waiting period when the first subscription is created. */
	public static function record_first_use() {
		if ( ! get_option( self::FIRST_USED_OPTION ) ) {
			add_option( self::FIRST_USED_OPTION, time(), '', false );
		}
	}

	/** Start the waiting period for existing installations that already have subscriptions. */
	public static function seed_existing_use() {
		if ( get_option( self::FIRST_USED_OPTION ) || get_option( self::DISMISSED_OPTION ) ) {
			return;
		}

		$subscriptions = get_posts(
			array(
				'post_type'      => RENEWLY_Subscription::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( $subscriptions ) {
			self::record_first_use();
		}
	}

	/** Display a limited, dismissible request on relevant WooCommerce screens. */
	public static function notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || get_option( self::DISMISSED_OPTION ) || ! self::is_relevant_screen() ) {
			return;
		}

		$first_used = absint( get_option( self::FIRST_USED_OPTION ) );
		$remind_at  = absint( get_option( self::REMIND_AT_OPTION ) );
		$show_at    = max( $first_used + ( self::WAIT_DAYS * DAY_IN_SECONDS ), $remind_at );
		if ( ! $first_used || time() < $show_at ) {
			return;
		}

		$review_url = 'https://wordpress.org/support/plugin/renewly-subscriptions-for-woocommerce/reviews/#new-post';
		$later_url  = wp_nonce_url( admin_url( 'admin-post.php?action=renewly_review_remind_later' ), 'renewly_review_remind_later' );
		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=renewly_review_dismiss' ), 'renewly_review_dismiss' );
		?>
		<div class="notice notice-info is-dismissible">
			<p><strong><?php esc_html_e( 'Enjoying Renewly?', 'renewly-subscriptions-for-woocommerce' ); ?></strong></p>
			<p><?php esc_html_e( 'If recurring billing is working well for your store, would you share a short, honest review? Your feedback helps other WooCommerce merchants and guides future improvements.', 'renewly-subscriptions-for-woocommerce' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'renewly-subscriptions-for-woocommerce' ); ?></a>
				<a class="button" href="<?php echo esc_url( $later_url ); ?>"><?php esc_html_e( 'Maybe later', 'renewly-subscriptions-for-woocommerce' ); ?></a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Do not ask again', 'renewly-subscriptions-for-woocommerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	/** Postpone the request for 30 days. */
	public static function remind_later() {
		self::authorize( 'renewly_review_remind_later' );
		update_option( self::REMIND_AT_OPTION, time() + ( 30 * DAY_IN_SECONDS ), false );
		self::redirect_back();
	}

	/** Permanently dismiss the request. */
	public static function dismiss() {
		self::authorize( 'renewly_review_dismiss' );
		update_option( self::DISMISSED_OPTION, 1, false );
		delete_option( self::REMIND_AT_OPTION );
		self::redirect_back();
	}

	/** Verify review-request actions. */
	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'renewly-subscriptions-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/** Return to a safe administration page. */
	private static function redirect_back() {
		$target = wp_get_referer() ?: admin_url( 'admin.php?page=renewly-settings' );
		wp_safe_redirect( $target );
		exit;
	}

	/** Check whether the current screen belongs to this plugin or WooCommerce. */
	private static function is_relevant_screen() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return in_array( $screen->post_type, array( 'product', RENEWLY_Subscription::POST_TYPE ), true )
			|| in_array( $screen->id, array( 'woocommerce_page_renewly-settings', 'woocommerce_page_renewly-upgrade-pro' ), true );
	}
}
