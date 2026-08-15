<?php
/**
 * Subscription records.
 *
 * @package Subscribely_Recurring_Billing
 */

defined( 'ABSPATH' ) || exit;

class WFS_Subscription {
	const POST_TYPE = 'wfs_subscription';

	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'create_from_order' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'create_from_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'order_reversed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'order_reversed' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Cancel or hold subscriptions when an initial/renewal order is reversed.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function order_reversed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$renewal_subscription_id = absint( $order->get_meta( '_wfs_subscription_id' ) );
		if ( $order->get_meta( '_wfs_is_renewal' ) && $renewal_subscription_id ) {
			if ( 'cancelled' !== get_post_meta( $renewal_subscription_id, '_wfs_status', true ) ) {
				self::set_status( $renewal_subscription_id, 'on-hold', 'renewal-reversed' );
			}
			return;
		}

		$subscriptions = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_key'       => '_wfs_parent_order_id',
				'meta_value'     => absint( $order_id ),
				'fields'         => 'ids',
			)
		);
		foreach ( $subscriptions as $subscription_id ) {
			self::set_status( $subscription_id, 'cancelled', 'initial-order-reversed' );
		}
	}

	/**
	 * Register subscription storage.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Subscriptions', 'subscribely-recurring-billing-for-woocommerce' ),
					'singular_name' => __( 'Subscription', 'subscribely-recurring-billing-for-woocommerce' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'woocommerce',
				'supports'            => array( 'title' ),
				'capability_type'      => 'shop_order',
				'map_meta_cap'         => true,
				'exclude_from_search'  => true,
			)
		);
	}

	/**
	 * Create subscriptions after the initial order is paid/accepted.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function create_from_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_wfs_subscriptions_created' ) ) {
			return;
		}

		$created = false;
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || 'wfs_subscription' !== $product->get_type() ) {
				continue;
			}

			$interval = max( 1, absint( $product->get_meta( '_wfs_interval' ) ) );
			$period   = sanitize_key( $product->get_meta( '_wfs_period' ) ?: 'month' );
			$trial    = absint( $product->get_meta( '_wfs_trial_days' ) );
			$limit    = absint( $product->get_meta( '_wfs_renewal_limit' ) );
			$next     = $trial ? time() + ( DAY_IN_SECONDS * $trial ) : self::next_timestamp( time(), $interval, $period );
			$status   = $trial ? 'trialling' : 'active';
			$post_id  = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => sprintf( __( 'Subscription for order #%s', 'subscribely-recurring-billing-for-woocommerce' ), $order->get_order_number() ),
					'post_author' => (int) $order->get_customer_id(),
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			$recurring_price = $item->get_meta( '_wfs_recurring_price', true );
			if ( '' === $recurring_price ) {
				$recurring_price = (float) $product->get_price();
			}

			self::set_status( $post_id, $status, 'created' );
			update_post_meta( $post_id, '_wfs_parent_order_id', $order->get_id() );
			update_post_meta( $post_id, '_wfs_customer_id', $order->get_customer_id() );
			update_post_meta( $post_id, '_wfs_product_id', $product->get_id() );
			update_post_meta( $post_id, '_wfs_quantity', $item->get_quantity() );
			update_post_meta( $post_id, '_wfs_recurring_price', wc_format_decimal( $recurring_price ) );
			update_post_meta( $post_id, '_wfs_currency', $order->get_currency() );
			update_post_meta( $post_id, '_wfs_interval', $interval );
			update_post_meta( $post_id, '_wfs_period', $period );
			update_post_meta( $post_id, '_wfs_next_payment', $next );
			update_post_meta( $post_id, '_wfs_trial_end', $trial ? $next : 0 );
			update_post_meta( $post_id, '_wfs_renewal_limit', $limit );
			update_post_meta( $post_id, '_wfs_completed_renewals', 0 );
			update_post_meta( $post_id, '_wfs_payment_method', $order->get_payment_method() );

			WFS_Renewals::schedule( $post_id, $next );
			do_action( 'wfs_subscription_created', $post_id, $order, $item );
			$created = true;
		}

		if ( $created ) {
			$order->update_meta_data( '_wfs_subscriptions_created', 1 );
			$order->save();
		}
	}

	/**
	 * Update status and notify integrations.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $status New status.
	 * @param string $context Change context.
	 */
	public static function set_status( $subscription_id, $status, $context = '' ) {
		$allowed = array( 'active', 'trialling', 'paused', 'past-due', 'on-hold', 'cancelled', 'expired' );
		$status  = sanitize_key( $status );
		if ( ! in_array( $status, $allowed, true ) ) {
			return;
		}

		$old_status = get_post_meta( $subscription_id, '_wfs_status', true );
		update_post_meta( $subscription_id, '_wfs_status', $status );
		if ( $old_status !== $status ) {
			do_action( 'wfs_subscription_status_updated', absint( $subscription_id ), $status, $old_status, sanitize_key( $context ) );
		}
	}

	/**
	 * Calculate next date without approximate month lengths.
	 *
	 * @param int    $from Base timestamp.
	 * @param int    $interval Interval.
	 * @param string $period Period.
	 * @return int
	 */
	public static function next_timestamp( $from, $interval, $period ) {
		$allowed = array( 'day', 'week', 'month', 'year' );
		$period  = in_array( $period, $allowed, true ) ? $period : 'month';
		$date    = new DateTimeImmutable( '@' . absint( $from ) );
		return $date->modify( '+' . max( 1, absint( $interval ) ) . ' ' . $period )->getTimestamp();
	}

	/**
	 * Admin columns.
	 *
	 * @return array
	 */
	public static function columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'title'    => __( 'Subscription', 'subscribely-recurring-billing-for-woocommerce' ),
			'status'   => __( 'Status', 'subscribely-recurring-billing-for-woocommerce' ),
			'customer' => __( 'Customer', 'subscribely-recurring-billing-for-woocommerce' ),
			'next'     => __( 'Next payment', 'subscribely-recurring-billing-for-woocommerce' ),
		);
	}

	/**
	 * Admin column content.
	 *
	 * @param string $column Column.
	 * @param int    $post_id Subscription ID.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'status' === $column ) {
			echo esc_html( ucwords( str_replace( '-', ' ', get_post_meta( $post_id, '_wfs_status', true ) ) ) );
		} elseif ( 'customer' === $column ) {
			$customer = get_userdata( (int) get_post_meta( $post_id, '_wfs_customer_id', true ) );
			echo esc_html( $customer ? $customer->display_name : '—' );
		} elseif ( 'next' === $column ) {
			$next = (int) get_post_meta( $post_id, '_wfs_next_payment', true );
			echo esc_html( $next ? wp_date( wc_date_format(), $next ) : '—' );
		}
	}
}
