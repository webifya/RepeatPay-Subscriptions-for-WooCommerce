<?php
/**
 * Customer account area.
 *
 * @package Renewly_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

class RENEWLY_Account {
	const ENDPOINT = 'subscriptions';

	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'content' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_cancel' ) );
	}

	/**
	 * Add rewrite endpoint.
	 */
	public static function endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add account menu item before logout.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function menu_item( $items ) {
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
		unset( $items['customer-logout'] );
		$items[ self::ENDPOINT ] = __( 'Subscriptions', 'renewly-subscriptions-for-woocommerce' );
		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Show customer subscriptions.
	 */
	public static function content() {
		$subscriptions = get_posts(
			array(
				'post_type'      => RENEWLY_Subscription::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_key'       => '_renewly_customer_id',
				'meta_value'     => get_current_user_id(),
			)
		);

		if ( ! $subscriptions ) {
			echo '<p>' . esc_html__( 'You do not have any subscriptions yet.', 'renewly-subscriptions-for-woocommerce' ) . '</p>';
			return;
		}

		echo '<table class="woocommerce-orders-table shop_table shop_table_responsive">';
		echo '<thead><tr><th>' . esc_html__( 'Subscription', 'renewly-subscriptions-for-woocommerce' ) . '</th><th>' . esc_html__( 'Status', 'renewly-subscriptions-for-woocommerce' ) . '</th><th>' . esc_html__( 'Next payment', 'renewly-subscriptions-for-woocommerce' ) . '</th><th>' . esc_html__( 'Actions', 'renewly-subscriptions-for-woocommerce' ) . '</th></tr></thead><tbody>';
		foreach ( $subscriptions as $subscription ) {
			$status   = get_post_meta( $subscription->ID, '_renewly_status', true );
			$next     = absint( get_post_meta( $subscription->ID, '_renewly_next_payment', true ) );
			$pending  = wc_get_order( absint( get_post_meta( $subscription->ID, '_renewly_pending_order_id', true ) ) );
			$cancel   = wp_nonce_url(
				add_query_arg( array( 'renewly-action' => 'cancel', 'subscription' => $subscription->ID ), wc_get_account_endpoint_url( self::ENDPOINT ) ),
				'renewly_cancel_' . $subscription->ID
			);

			echo '<tr>';
			echo '<td>#' . esc_html( $subscription->ID ) . '</td>';
			echo '<td>' . esc_html( ucwords( str_replace( '-', ' ', $status ) ) ) . '</td>';
			echo '<td>' . ( $next ? esc_html( wp_date( wc_date_format(), $next ) ) : '&mdash;' ) . '</td>';
			echo '<td>';
			if ( $pending && $pending->needs_payment() ) {
				echo '<a class="button pay" href="' . esc_url( $pending->get_checkout_payment_url() ) . '">' . esc_html__( 'Pay renewal', 'renewly-subscriptions-for-woocommerce' ) . '</a> ';
			}
			if ( in_array( $status, array( 'active', 'trialling', 'past-due', 'on-hold' ), true ) ) {
				echo '<a class="button cancel" href="' . esc_url( $cancel ) . '">' . esc_html__( 'Cancel', 'renewly-subscriptions-for-woocommerce' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Process customer cancellation.
	 */
	public static function handle_cancel() {
		if ( ! is_user_logged_in() || ! isset( $_GET['renewly-action'], $_GET['subscription'], $_GET['_wpnonce'] ) || 'cancel' !== sanitize_key( wp_unslash( $_GET['renewly-action'] ) ) ) {
			return;
		}

		$subscription_id = absint( wp_unslash( $_GET['subscription'] ) );
		$nonce           = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		$owner           = absint( get_post_meta( $subscription_id, '_renewly_customer_id', true ) );
		if ( $owner !== get_current_user_id() || ! wp_verify_nonce( $nonce, 'renewly_cancel_' . $subscription_id ) ) {
			wc_add_notice( __( 'This subscription could not be cancelled.', 'renewly-subscriptions-for-woocommerce' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
			exit;
		}

		RENEWLY_Subscription::set_status( $subscription_id, 'cancelled', 'customer-cancelled' );
		$pending = wc_get_order( absint( get_post_meta( $subscription_id, '_renewly_pending_order_id', true ) ) );
		if ( $pending && $pending->needs_payment() ) {
			$pending->update_status( 'cancelled', __( 'Related subscription cancelled by customer.', 'renewly-subscriptions-for-woocommerce' ) );
		}
		delete_post_meta( $subscription_id, '_renewly_pending_order_id' );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( RENEWLY_Renewals::ACTION, array( $subscription_id ), RENEWLY_Renewals::GROUP );
			as_unschedule_all_actions( RENEWLY_Renewals::RETRY_ACTION, array( $subscription_id ), RENEWLY_Renewals::GROUP );
		} else {
			wp_clear_scheduled_hook( RENEWLY_Renewals::ACTION, array( $subscription_id ) );
			wp_clear_scheduled_hook( RENEWLY_Renewals::RETRY_ACTION, array( $subscription_id ) );
		}

		wc_add_notice( __( 'Your subscription has been cancelled.', 'renewly-subscriptions-for-woocommerce' ) );
		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
		exit;
	}
}
