<?php
/**
 * One-time migration from pre-directory development identities.
 *
 * @package RepeatPay_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

class REPEATPAY_Migration {
	const VERSION_OPTION = 'repeatpay_migration_version';
	const VERSION        = '0.6.1';

	/** Copy legacy records to the RepeatPay identifiers without removing user data. */
	public static function run() {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::VERSION, '>=' ) ) {
			return;
		}

		self::migrate_options();
		self::migrate_post_types();
		self::migrate_product_types();
		self::migrate_meta_table( 'postmeta', 'post_id' );
		self::migrate_meta_table( 'woocommerce_order_itemmeta', 'order_item_id' );
		self::migrate_hpos_meta();
		self::reschedule_renewals();
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	private static function migrate_options() {
		$mappings = array(
			'wfs_dunning_settings'             => 'repeatpay_dunning_settings',
			'wfs_site_profile_instance_id'     => 'repeatpay_site_profile_instance_id',
			'wfs_review_first_used_at'          => 'repeatpay_review_first_used_at',
			'wfs_review_remind_at'              => 'repeatpay_review_remind_at',
			'wfs_review_dismissed'               => 'repeatpay_review_dismissed',
			'renewly_dunning_settings'           => 'repeatpay_dunning_settings',
			'renewly_site_profile_instance_id'   => 'repeatpay_site_profile_instance_id',
			'renewly_review_first_used_at'       => 'repeatpay_review_first_used_at',
			'renewly_review_remind_at'           => 'repeatpay_review_remind_at',
			'renewly_review_dismissed'            => 'repeatpay_review_dismissed',
		);
		foreach ( $mappings as $legacy => $current ) {
			if ( false === get_option( $current, false ) ) {
				$value = get_option( $legacy, false );
				if ( false !== $value ) {
					update_option( $current, $value, false );
				}
			}
		}
	}

	private static function migrate_post_types() {
		global $wpdb;
		foreach ( array( 'wfs_subscription', 'renewly_subscription' ) as $legacy ) {
			$wpdb->update( $wpdb->posts, array( 'post_type' => REPEATPAY_Subscription::POST_TYPE ), array( 'post_type' => $legacy ), array( '%s' ), array( '%s' ) );
		}
	}

	private static function migrate_product_types() {
		foreach ( array( 'wfs_subscription', 'renewly_subscription' ) as $legacy_slug ) {
			$legacy = get_term_by( 'slug', $legacy_slug, 'product_type' );
			if ( ! $legacy || is_wp_error( $legacy ) ) {
				continue;
			}
			$product_ids = get_objects_in_term( (int) $legacy->term_id, 'product_type' );
			if ( is_wp_error( $product_ids ) ) {
				continue;
			}
			foreach ( $product_ids as $product_id ) {
				wp_set_object_terms( (int) $product_id, 'repeatpay_subscription', 'product_type', false );
			}
		}
	}

	private static function migrate_meta_table( $table_suffix, $object_column ) {
		global $wpdb;
		$allowed = array(
			'postmeta'                   => array( $wpdb->postmeta, 'post_id' ),
			'woocommerce_order_itemmeta' => array( $wpdb->prefix . 'woocommerce_order_itemmeta', 'order_item_id' ),
		);
		if ( ! isset( $allowed[ $table_suffix ] ) || $allowed[ $table_suffix ][1] !== $object_column ) {
			return;
		}
		$table = $allowed[ $table_suffix ][0];
		foreach ( array( '_wfs_' => '_repeatpay_', '_renewly_' => '_repeatpay_' ) as $legacy => $current ) {
			$like = $wpdb->esc_like( $legacy ) . '%';
			$sql  = "UPDATE {$table} legacy LEFT JOIN {$table} newmeta ON newmeta.{$object_column} = legacy.{$object_column} AND newmeta.meta_key = REPLACE( legacy.meta_key, %s, %s ) SET legacy.meta_key = REPLACE( legacy.meta_key, %s, %s ) WHERE legacy.meta_key LIKE %s AND newmeta.meta_id IS NULL";
			$wpdb->query( $wpdb->prepare( $sql, $legacy, $current, $legacy, $current, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	private static function migrate_hpos_meta() {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_orders_meta';
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return;
		}
		foreach ( array( '_wfs_' => '_repeatpay_', '_renewly_' => '_repeatpay_' ) as $legacy => $current ) {
			$like = $wpdb->esc_like( $legacy ) . '%';
			$sql  = "UPDATE {$table} legacy LEFT JOIN {$table} newmeta ON newmeta.order_id = legacy.order_id AND newmeta.meta_key = REPLACE( legacy.meta_key, %s, %s ) SET legacy.meta_key = REPLACE( legacy.meta_key, %s, %s ) WHERE legacy.meta_key LIKE %s AND newmeta.id IS NULL";
			$wpdb->query( $wpdb->prepare( $sql, $legacy, $current, $legacy, $current, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	private static function reschedule_renewals() {
		$ids = get_posts( array( 'post_type' => REPEATPAY_Subscription::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $ids as $subscription_id ) {
			$args = array( (int) $subscription_id );
			foreach ( array( 'wfs_create_renewal_order', 'wfs_retry_renewal_payment', 'renewly_create_renewal_order', 'renewly_retry_renewal_payment' ) as $hook ) {
				if ( function_exists( 'as_unschedule_all_actions' ) ) {
					as_unschedule_all_actions( $hook, $args );
				} else {
					wp_clear_scheduled_hook( $hook, $args );
				}
			}
			$status = get_post_meta( $subscription_id, '_repeatpay_status', true );
			$next   = absint( get_post_meta( $subscription_id, '_repeatpay_next_payment', true ) );
			if ( in_array( $status, array( 'active', 'trialling' ), true ) && $next ) {
				REPEATPAY_Renewals::schedule( $subscription_id, max( time() + MINUTE_IN_SECONDS, $next ) );
			}
		}
	}
}
