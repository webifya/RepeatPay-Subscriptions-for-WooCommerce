<?php
/**
 * Main plugin class.
 *
 * @package RepeatPay_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

final class REPEATPAY_Plugin {
	/**
	 * Singleton.
	 *
	 * @var REPEATPAY_Plugin|null
	 */
	private static $instance;

	/**
	 * Get the plugin instance.
	 *
	 * @return REPEATPAY_Plugin|null
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'woocommerce_notice' ) );
				return null;
			}

			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize features.
	 */
	private function __construct() {
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-product.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-subscription.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-renewals.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-migration.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-account.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-settings.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-site-profile.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-upgrade.php';
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-review.php';

		REPEATPAY_Migration::run();
		REPEATPAY_Product::init();
		REPEATPAY_Subscription::init();
		REPEATPAY_Renewals::init();
		REPEATPAY_Account::init();
		REPEATPAY_Settings::init();
		REPEATPAY_Site_Profile::init();
		REPEATPAY_Upgrade::init();
		REPEATPAY_Review::init();

		add_filter( 'woocommerce_gateway_title', array( __CLASS__, 'payment_gateway_title' ), 999, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'checkout_styles' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'checkout_scripts' ), 31 );
	}

	/**
	 * Never allow an enabled payment gateway to render a blank checkout title.
	 *
	 * @param string $title Gateway title.
	 * @param string $gateway_id Gateway ID.
	 * @return string
	 */
	public static function payment_gateway_title( $title, $gateway_id ) {
		$plain = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$plain = trim( str_replace( "\xc2\xa0", ' ', $plain ) );
		if ( '' !== $plain ) {
			return $title;
		}

		$known = array(
			'stripe'                   => __( 'Credit or debit card', 'repeatpay-subscriptions-for-woocommerce' ),
			'stripe_cc'                => __( 'Credit or debit card', 'repeatpay-subscriptions-for-woocommerce' ),
			'stripe_googlepay'         => __( 'Google Pay', 'repeatpay-subscriptions-for-woocommerce' ),
			'stripe_applepay'          => __( 'Apple Pay', 'repeatpay-subscriptions-for-woocommerce' ),
			'square_credit_card'       => __( 'Credit or debit card (Square)', 'repeatpay-subscriptions-for-woocommerce' ),
			'ppcp-gateway'             => __( 'PayPal', 'repeatpay-subscriptions-for-woocommerce' ),
			'ppcp-credit-card-gateway' => __( 'Credit or debit card (PayPal)', 'repeatpay-subscriptions-for-woocommerce' ),
			'ppcp-card-button-gateway' => __( 'Credit or debit card (PayPal)', 'repeatpay-subscriptions-for-woocommerce' ),
		);
		if ( isset( $known[ $gateway_id ] ) ) {
			return $known[ $gateway_id ];
		}

		$fallback = ucwords( str_replace( array( '-', '_' ), ' ', sanitize_key( $gateway_id ) ) );
		return $fallback ?: __( 'Payment method', 'repeatpay-subscriptions-for-woocommerce' );
	}

	/**
	 * Keep classic and block checkout payment labels and hosted fields visible.
	 */
	public static function checkout_styles() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_register_style( 'repeatpay-checkout-compatibility', false, array(), REPEATPAY_VERSION );
		wp_enqueue_style( 'repeatpay-checkout-compatibility' );
		wp_add_inline_style(
			'repeatpay-checkout-compatibility',
			'.woocommerce-checkout .wc_payment_method>label,.woocommerce-checkout .payment_methods label,.wc-block-checkout .wc-block-components-radio-control__label,.wc-block-checkout .wc-block-components-radio-control-accordion-option__label,.wc-block-checkout .wc-block-components-payment-method-label{display:flex!important;visibility:visible!important;opacity:1!important;color:inherit!important;align-items:center;gap:.5em}.wc-stripe-elements-field,.wc-square-credit-card-hosted-field,.wc-payment-form .form-row input{background:#fff!important;color:#1e1e1e!important;min-height:44px}.wc-stripe-elements-field iframe,.wc-square-credit-card-hosted-field iframe{background:#fff!important;color-scheme:light}.payment_box{color-scheme:light}'
		);
	}

	/**
	 * Repair blank client-rendered gateway labels in classic and block checkout.
	 */
	public static function checkout_scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_register_script( 'repeatpay-checkout-compatibility', false, array(), REPEATPAY_VERSION, true );
		wp_enqueue_script( 'repeatpay-checkout-compatibility' );
		wp_add_inline_script(
			'repeatpay-checkout-compatibility',
			"(function(){'use strict';function titleFor(value){value=(value||'').toLowerCase();if(value.indexOf('google')!==-1)return 'Google Pay';if(value.indexOf('apple')!==-1)return 'Apple Pay';if(value.indexOf('paypal')!==-1||value.indexOf('ppcp')!==-1)return value.indexOf('card')!==-1?'Credit or debit card (PayPal)':'PayPal';if(value.indexOf('square')!==-1)return 'Credit or debit card (Square)';if(value.indexOf('stripe')!==-1||value.indexOf('wcpay')!==-1||value.indexOf('woocommerce_payments')!==-1||value.indexOf('card')!==-1)return 'Credit or debit card';return 'Payment method';}function repair(){document.querySelectorAll('.wc_payment_method,.wc-block-components-radio-control-accordion-option,.wc-block-components-radio-control__option').forEach(function(row){var input=row.querySelector('input[type=\"radio\"]');var label=input&&input.id?row.querySelector('label[for=\"'+CSS.escape(input.id)+'\"]'):row.querySelector('label');if(!input||!label)return;var text=(label.textContent||'').replace(/\\s+/g,'').trim();var alt=label.querySelector('img[alt]');if(!text&&!alt){var span=document.createElement('span');span.className='repeatpay-payment-method-title';span.textContent=titleFor(input.value||input.id);label.appendChild(span);}if(!input.getAttribute('aria-label'))input.setAttribute('aria-label',titleFor(input.value||input.id));});}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',repair);else repair();new MutationObserver(repair).observe(document.documentElement,{childList:true,subtree:true});if(window.jQuery)window.jQuery(document.body).on('updated_checkout',repair);})();"
		);
	}

	/**
	 * Register data structures on activation.
	 */
	public static function activate() {
		require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-subscription.php';
		REPEATPAY_Subscription::register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Clear rewrite rules on deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Display dependency notice.
	 */
	public static function woocommerce_notice() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'RepeatPay Subscriptions for WooCommerce requires WooCommerce to be installed and active.', 'repeatpay-subscriptions-for-woocommerce' );
		echo '</p></div>';
	}
}
