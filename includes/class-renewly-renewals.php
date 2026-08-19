<?php
/**
 * Renewal scheduling and orders.
 *
 * @package Renewly_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

class RENEWLY_Renewals {
	const ACTION = 'renewly_create_renewal_order';
	const RETRY_ACTION = 'renewly_retry_renewal_payment';
	const GROUP  = 'renewly-subscriptions';

	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( self::ACTION, array( __CLASS__, 'create_renewal_order' ) );
		add_action( self::RETRY_ACTION, array( __CLASS__, 'retry_payment' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'renewal_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'renewal_paid' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'renewal_failed' ) );
	}

	/**
	 * Schedule the next unpaid-payment check.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @param int $timestamp Run time.
	 */
	public static function schedule_retry( $subscription_id, $timestamp ) {
		$args = array( absint( $subscription_id ) );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::RETRY_ACTION, $args, self::GROUP );
			as_schedule_single_action( absint( $timestamp ), self::RETRY_ACTION, $args, self::GROUP, true );
		} else {
			wp_clear_scheduled_hook( self::RETRY_ACTION, $args );
			wp_schedule_single_event( absint( $timestamp ), self::RETRY_ACTION, $args );
		}
	}

	/**
	 * Schedule one renewal.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @param int $timestamp Run time.
	 */
	public static function schedule( $subscription_id, $timestamp ) {
		$args = array( absint( $subscription_id ) );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION, $args, self::GROUP );
			as_schedule_single_action( absint( $timestamp ), self::ACTION, $args, self::GROUP, true );
		} else {
			wp_clear_scheduled_hook( self::ACTION, $args );
			wp_schedule_single_event( absint( $timestamp ), self::ACTION, $args );
		}
	}

	/**
	 * Create an unpaid order so the customer can use any enabled gateway.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function create_renewal_order( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		if ( ! in_array( get_post_meta( $subscription_id, '_renewly_status', true ), array( 'active', 'trialling' ), true ) ) {
			return;
		}

		$existing = absint( get_post_meta( $subscription_id, '_renewly_pending_order_id', true ) );
		if ( $existing ) {
			$existing_order = wc_get_order( $existing );
			if ( $existing_order && $existing_order->needs_payment() ) {
				return;
			}
		}

		$parent  = wc_get_order( absint( get_post_meta( $subscription_id, '_renewly_parent_order_id', true ) ) );
		$product = wc_get_product( absint( get_post_meta( $subscription_id, '_renewly_product_id', true ) ) );
		if ( ! $parent || ! $product ) {
			RENEWLY_Subscription::set_status( $subscription_id, 'on-hold', 'missing-product' );
			return;
		}

		$order = wc_create_order( array( 'customer_id' => $parent->get_customer_id() ) );
		if ( is_wp_error( $order ) ) {
			return;
		}

		$order->set_address( $parent->get_address( 'billing' ), 'billing' );
		$order->set_address( $parent->get_address( 'shipping' ), 'shipping' );
		$order->set_currency( get_post_meta( $subscription_id, '_renewly_currency', true ) ?: $parent->get_currency() );
		$order->set_payment_method( $parent->get_payment_method() );
		$order->set_payment_method_title( $parent->get_payment_method_title() );
		foreach ( $parent->get_payment_tokens() as $token_id ) {
			$token = WC_Payment_Tokens::get( $token_id );
			if ( $token instanceof WC_Payment_Token ) {
				$order->add_payment_token( $token );
			}
		}
		$payment_meta_keys = apply_filters( 'renewly_renewal_payment_meta_keys', array(), $parent, $subscription_id );
		foreach ( array_unique( array_filter( array_map( 'sanitize_key', $payment_meta_keys ) ) ) as $meta_key ) {
			$value = $parent->get_meta( $meta_key, true );
			if ( '' !== $value ) {
				$order->update_meta_data( $meta_key, $value );
			}
		}
		$quantity = max( 1, absint( get_post_meta( $subscription_id, '_renewly_quantity', true ) ) );
		$price    = (float) get_post_meta( $subscription_id, '_renewly_recurring_price', true );
		$total    = wc_format_decimal( $price * $quantity );
		$order->add_product(
			$product,
			$quantity,
			array(
				'subtotal' => $total,
				'total'    => $total,
			)
		);
		$order->update_meta_data( '_renewly_subscription_id', $subscription_id );
		$order->update_meta_data( '_renewly_is_renewal', 1 );
		$order->calculate_totals();
		$order->update_status( 'pending', __( 'Subscription renewal awaiting customer payment.', 'renewly-subscriptions-for-woocommerce' ) );
		$order->save();

		update_post_meta( $subscription_id, '_renewly_pending_order_id', $order->get_id() );
		update_post_meta( $subscription_id, '_renewly_retry_count', 0 );
		RENEWLY_Subscription::set_status( $subscription_id, 'active', 'renewal-created' );
		self::schedule_retry( $subscription_id, time() + ( DAY_IN_SECONDS * RENEWLY_Settings::get( 'retry_days' ) ) );

		do_action( 'renewly_renewal_order_created', $order, $subscription_id );
		$order = wc_get_order( $order->get_id() );
		if ( $order && $order->needs_payment() ) {
			self::send_invoice( $order );
		}
	}

	/**
	 * Remind a customer about an unpaid renewal.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function retry_payment( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		$status          = get_post_meta( $subscription_id, '_renewly_status', true );
		if ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) {
			return;
		}

		$order = wc_get_order( absint( get_post_meta( $subscription_id, '_renewly_pending_order_id', true ) ) );
		if ( ! $order || ! $order->needs_payment() ) {
			return;
		}

		$count = absint( get_post_meta( $subscription_id, '_renewly_retry_count', true ) ) + 1;
		$max   = RENEWLY_Settings::get( 'max_retries' );
		update_post_meta( $subscription_id, '_renewly_retry_count', $count );

		if ( $count >= $max ) {
			RENEWLY_Subscription::set_status( $subscription_id, 'on-hold', 'retry-exhausted' );
			$order->add_order_note( __( 'Final subscription renewal reminder sent; subscription placed on hold.', 'renewly-subscriptions-for-woocommerce' ) );
		} else {
			RENEWLY_Subscription::set_status( $subscription_id, 'past-due', 'payment-retry' );
			$order->add_order_note(
				sprintf(
					/* translators: 1: attempt number, 2: maximum attempts. */
					__( 'Subscription renewal reminder %1$d of %2$d sent.', 'renewly-subscriptions-for-woocommerce' ),
					$count,
					$max
				)
			);
			self::schedule_retry( $subscription_id, time() + ( DAY_IN_SECONDS * RENEWLY_Settings::get( 'retry_days' ) ) );
		}

		do_action( 'renewly_renewal_payment_retried', $order, $subscription_id, $count, $max );
		$order = wc_get_order( $order->get_id() );
		if ( $order && $order->needs_payment() ) {
			self::send_invoice( $order );
		}
	}

	/**
	 * Mark a failed renewal for recovery.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function renewal_failed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_renewly_is_renewal' ) ) {
			return;
		}

		$subscription_id = absint( $order->get_meta( '_renewly_subscription_id' ) );
		if ( $subscription_id && 'cancelled' !== get_post_meta( $subscription_id, '_renewly_status', true ) ) {
			RENEWLY_Subscription::set_status( $subscription_id, 'past-due', 'payment-failed' );
			self::schedule_retry( $subscription_id, time() + ( DAY_IN_SECONDS * RENEWLY_Settings::get( 'retry_days' ) ) );
		}
	}

	/**
	 * Send the standard customer invoice email.
	 *
	 * @param WC_Order $order Renewal order.
	 */
	private static function send_invoice( $order ) {
		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();
		if ( isset( $emails['WC_Email_Customer_Invoice'] ) ) {
			$emails['WC_Email_Customer_Invoice']->trigger( $order->get_id(), $order );
		}
	}

	/**
	 * Advance subscription after renewal is paid.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function renewal_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_renewly_is_renewal' ) || $order->get_meta( '_renewly_renewal_processed' ) ) {
			return;
		}

		$subscription_id = absint( $order->get_meta( '_renewly_subscription_id' ) );
		if ( ! $subscription_id ) {
			return;
		}

		$completed = absint( get_post_meta( $subscription_id, '_renewly_completed_renewals', true ) ) + 1;
		$limit     = absint( get_post_meta( $subscription_id, '_renewly_renewal_limit', true ) );
		update_post_meta( $subscription_id, '_renewly_completed_renewals', $completed );

		delete_post_meta( $subscription_id, '_renewly_pending_order_id' );
		delete_post_meta( $subscription_id, '_renewly_retry_count' );

		$args = array( $subscription_id );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::RETRY_ACTION, $args, self::GROUP );
		} else {
			wp_clear_scheduled_hook( self::RETRY_ACTION, $args );
		}

		$order->update_meta_data( '_renewly_renewal_processed', 1 );
		$order->save();

		if ( $limit && $completed >= $limit ) {
			RENEWLY_Subscription::set_status( $subscription_id, 'expired', 'renewal-limit' );
			delete_post_meta( $subscription_id, '_renewly_next_payment' );
			do_action( 'renewly_subscription_expired', $subscription_id, $order );
			do_action( 'renewly_subscription_renewal_paid', $subscription_id, $order, 'expired' );
			return;
		}

		$interval = max( 1, absint( get_post_meta( $subscription_id, '_renewly_interval', true ) ) );
		$period   = sanitize_key( get_post_meta( $subscription_id, '_renewly_period', true ) );
		$next     = RENEWLY_Subscription::next_timestamp( time(), $interval, $period );
		RENEWLY_Subscription::set_status( $subscription_id, 'active', 'renewal-paid' );
		update_post_meta( $subscription_id, '_renewly_next_payment', $next );
		self::schedule( $subscription_id, $next );
		do_action( 'renewly_subscription_renewal_paid', $subscription_id, $order, 'active' );
	}
}
