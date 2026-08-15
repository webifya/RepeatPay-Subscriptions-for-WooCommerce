<?php
/**
 * Subscription product integration.
 *
 * @package Subscribely_Recurring_Billing
 */

defined( 'ABSPATH' ) || exit;

class WFS_Product {
	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_filter( 'product_type_selector', array( __CLASS__, 'add_product_type' ) );
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'product_class' ), 10, 2 );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_fields' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'checkout_terms' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'initial_cart_price' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_data' ), 10, 4 );
		add_action( 'woocommerce_wfs_subscription_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );
		add_action( 'admin_footer', array( __CLASS__, 'admin_script' ) );
	}

	/**
	 * Add product type.
	 *
	 * @param array $types Product types.
	 * @return array
	 */
	public static function add_product_type( $types ) {
		$types['wfs_subscription'] = __( 'Subscription', 'subscribely-recurring-billing-for-woocommerce' );
		return $types;
	}

	/**
	 * Map product type to class.
	 *
	 * @param string $classname Class name.
	 * @param string $type Product type.
	 * @return string
	 */
	public static function product_class( $classname, $type ) {
		return 'wfs_subscription' === $type ? 'WFS_Product_Subscription' : $classname;
	}

	/**
	 * Render interval settings.
	 */
	public static function product_fields() {
		echo '<div class="options_group show_if_wfs_subscription">';

		woocommerce_wp_text_input(
			array(
				'id'                => '_wfs_interval',
				'label'             => __( 'Billing interval', 'subscribely-recurring-billing-for-woocommerce' ),
				'type'              => 'number',
				'value'             => get_post_meta( get_the_ID(), '_wfs_interval', true ) ?: 1,
				'custom_attributes' => array( 'min' => 1, 'step' => 1 ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_wfs_period',
				'label'   => __( 'Billing period', 'subscribely-recurring-billing-for-woocommerce' ),
				'options' => array(
					'day'   => __( 'Day(s)', 'subscribely-recurring-billing-for-woocommerce' ),
					'week'  => __( 'Week(s)', 'subscribely-recurring-billing-for-woocommerce' ),
					'month' => __( 'Month(s)', 'subscribely-recurring-billing-for-woocommerce' ),
					'year'  => __( 'Year(s)', 'subscribely-recurring-billing-for-woocommerce' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wfs_trial_days',
				'label'             => __( 'Free trial', 'subscribely-recurring-billing-for-woocommerce' ),
				'description'       => __( 'Number of free-trial days before the first recurring payment.', 'subscribely-recurring-billing-for-woocommerce' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'value'             => get_post_meta( get_the_ID(), '_wfs_trial_days', true ) ?: 0,
				'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_wfs_signup_fee',
				'label'       => __( 'Sign-up fee', 'subscribely-recurring-billing-for-woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'description' => __( 'One-time fee charged at checkout, including when a free trial is used.', 'subscribely-recurring-billing-for-woocommerce' ),
				'desc_tip'    => true,
				'data_type'   => 'price',
				'value'       => get_post_meta( get_the_ID(), '_wfs_signup_fee', true ) ?: '',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wfs_renewal_limit',
				'label'             => __( 'Renewal payment limit', 'subscribely-recurring-billing-for-woocommerce' ),
				'description'       => __( 'Maximum successful renewal payments. Use 0 for an ongoing subscription.', 'subscribely-recurring-billing-for-woocommerce' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'value'             => get_post_meta( get_the_ID(), '_wfs_renewal_limit', true ) ?: 0,
				'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_wfs_price_prefix',
				'label'       => __( 'Price display prefix', 'subscribely-recurring-billing-for-woocommerce' ),
				'description' => __( 'Optional text shown before the subscription price, for example “Starting from”.', 'subscribely-recurring-billing-for-woocommerce' ),
				'desc_tip'    => true,
				'value'       => get_post_meta( get_the_ID(), '_wfs_price_prefix', true ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_wfs_price_suffix',
				'label'       => __( 'Price display suffix', 'subscribely-recurring-billing-for-woocommerce' ),
				'description' => __( 'Optional text shown after the price and billing period, for example “until your birthday”.', 'subscribely-recurring-billing-for-woocommerce' ),
				'desc_tip'    => true,
				'value'       => get_post_meta( get_the_ID(), '_wfs_price_suffix', true ),
			)
		);

		echo '</div>';
	}

	/**
	 * Save interval settings.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function save_product_fields( $product ) {
		if ( 'wfs_subscription' !== $product->get_type() ) {
			return;
		}

		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$interval = isset( $_POST['_wfs_interval'] ) ? absint( wp_unslash( $_POST['_wfs_interval'] ) ) : 1;
		$period   = isset( $_POST['_wfs_period'] ) ? sanitize_key( wp_unslash( $_POST['_wfs_period'] ) ) : 'month';
		$period   = in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ? $period : 'month';

		$product->update_meta_data( '_wfs_interval', max( 1, $interval ) );
		$product->update_meta_data( '_wfs_period', $period );
		$product->update_meta_data( '_wfs_trial_days', isset( $_POST['_wfs_trial_days'] ) ? absint( wp_unslash( $_POST['_wfs_trial_days'] ) ) : 0 );
		$product->update_meta_data( '_wfs_signup_fee', isset( $_POST['_wfs_signup_fee'] ) ? wc_format_decimal( wp_unslash( $_POST['_wfs_signup_fee'] ) ) : '' );
		$product->update_meta_data( '_wfs_renewal_limit', isset( $_POST['_wfs_renewal_limit'] ) ? absint( wp_unslash( $_POST['_wfs_renewal_limit'] ) ) : 0 );
		$product->update_meta_data( '_wfs_price_prefix', isset( $_POST['_wfs_price_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['_wfs_price_prefix'] ) ) : '' );
		$product->update_meta_data( '_wfs_price_suffix', isset( $_POST['_wfs_price_suffix'] ) ? sanitize_text_field( wp_unslash( $_POST['_wfs_price_suffix'] ) ) : '' );
	}

	/**
	 * Add billing cadence to price.
	 *
	 * @param string     $html Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function price_html( $html, $product ) {
		if ( ! $product || 'wfs_subscription' !== $product->get_type() ) {
			return $html;
		}

		$interval = max( 1, absint( $product->get_meta( '_wfs_interval' ) ) );
		$period   = sanitize_key( $product->get_meta( '_wfs_period' ) ?: 'month' );
		$label    = 1 === $interval ? $period : $interval . ' ' . $period . 's';

		$details    = array( sprintf( __( 'every %s', 'subscribely-recurring-billing-for-woocommerce' ), $label ) );
		$trial_days = absint( $product->get_meta( '_wfs_trial_days' ) );
		$signup_fee = (float) $product->get_meta( '_wfs_signup_fee' );

		if ( $trial_days ) {
			$details[] = sprintf(
				/* translators: %d: number of trial days. */
				_n( '%d-day free trial', '%d-day free trial', $trial_days, 'subscribely-recurring-billing-for-woocommerce' ),
				$trial_days
			);
		}
		if ( $signup_fee > 0 ) {
			$details[] = sprintf( __( '%s sign-up fee', 'subscribely-recurring-billing-for-woocommerce' ), html_entity_decode( wp_strip_all_tags( wc_price( $signup_fee ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		}

		$price_html = $html . ' <span class="wfs-period">' . esc_html( implode( ' · ', $details ) ) . '</span>';
		$prefix     = trim( (string) $product->get_meta( '_wfs_price_prefix' ) );
		$suffix     = trim( (string) $product->get_meta( '_wfs_price_suffix' ) );

		if ( '' !== $prefix ) {
			$price_html = '<span class="wfs-price-prefix">' . esc_html( $prefix ) . '</span> ' . $price_html;
		}
		if ( '' !== $suffix ) {
			$price_html .= ' <span class="wfs-price-suffix">' . esc_html( $suffix ) . '</span>';
		}

		return apply_filters( 'wfs_subscription_price_html', $price_html, $product, $html );
	}

	/**
	 * Capture prices before the cart price is changed for the initial payment.
	 *
	 * @param array $data Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function cart_item_data( $data, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'wfs_subscription' !== $product->get_type() ) {
			return $data;
		}

		$recurring = (float) $product->get_price();
		$trial     = absint( $product->get_meta( '_wfs_trial_days' ) );
		$signup    = (float) $product->get_meta( '_wfs_signup_fee' );

		$data['_wfs_recurring_price'] = $recurring;
		$data['_wfs_initial_price']   = ( $trial ? 0 : $recurring ) + $signup;
		return $data;
	}

	/**
	 * Display subscription terms in cart and checkout.
	 *
	 * @param array $data Cart display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function checkout_terms( $data, $cart_item ) {
		$product = isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ? $cart_item['data'] : null;
		if ( ! $product || 'wfs_subscription' !== $product->get_type() ) {
			return $data;
		}

		$interval  = max( 1, absint( $product->get_meta( '_wfs_interval' ) ) );
		$period    = sanitize_key( $product->get_meta( '_wfs_period' ) ?: 'month' );
		$recurring = isset( $cart_item['_wfs_recurring_price'] ) ? (float) $cart_item['_wfs_recurring_price'] : (float) $product->get_regular_price();
		$price     = html_entity_decode( wp_strip_all_tags( wc_price( $recurring ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$cadence   = 1 === $interval
			? sprintf( __( '%1$s per %2$s', 'subscribely-recurring-billing-for-woocommerce' ), $price, $period )
			: sprintf( __( '%1$s every %2$d %3$ss', 'subscribely-recurring-billing-for-woocommerce' ), $price, $interval, $period );
		$data[]    = array( 'key' => __( 'Billing', 'subscribely-recurring-billing-for-woocommerce' ), 'value' => $cadence );

		$trial = absint( $product->get_meta( '_wfs_trial_days' ) );
		if ( $trial ) {
			$data[] = array(
				'key'   => __( 'Free trial', 'subscribely-recurring-billing-for-woocommerce' ),
				'value' => sprintf( _n( '%d day', '%d days', $trial, 'subscribely-recurring-billing-for-woocommerce' ), $trial ),
			);
		}

		$signup = (float) $product->get_meta( '_wfs_signup_fee' );
		if ( $signup > 0 ) {
			$data[] = array(
				'key'   => __( 'One-time sign-up fee', 'subscribely-recurring-billing-for-woocommerce' ),
				'value' => wp_strip_all_tags( wc_price( $signup ) ),
			);
		}

		$limit = absint( $product->get_meta( '_wfs_renewal_limit' ) );
		if ( $limit ) {
			$data[] = array(
				'key'   => __( 'Renewal payments', 'subscribely-recurring-billing-for-woocommerce' ),
				'value' => (string) $limit,
			);
		}

		return apply_filters( 'wfs_checkout_subscription_terms', $data, $product, $cart_item );
	}

	/**
	 * Set the checkout price to recurring price plus sign-up fee, or fee only for trials.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function initial_cart_price( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['_wfs_initial_price'], $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( (float) $item['_wfs_initial_price'] );
			}
		}
	}

	/**
	 * Persist recurring terms on the initial order item.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values Cart values.
	 * @param WC_Order              $order Order.
	 */
	public static function order_item_data( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['_wfs_recurring_price'] ) ) {
			$item->add_meta_data( '_wfs_recurring_price', wc_format_decimal( $values['_wfs_recurring_price'] ), true );
		}
	}

	/**
	 * Make standard pricing/inventory panels visible for the custom type.
	 */
	public static function admin_script() {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		?>
		<script>
		jQuery(function($) {
			var $productType = $('#product-type');

			$('.options_group.pricing, .inventory_options, .inventory_options + .options_group')
				.addClass('show_if_wfs_subscription');

			$productType.trigger('change');
		});
		</script>
		<?php
	}
}

/**
 * Subscription product.
 */
class WFS_Product_Subscription extends WC_Product_Simple {
	/**
	 * Product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'wfs_subscription';
	}
}
