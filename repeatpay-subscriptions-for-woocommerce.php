<?php
/**
 * Plugin Name: RepeatPay Subscriptions for WooCommerce
 * Plugin URI: https://github.com/webifya/RepeatPay-Subscriptions-for-WooCommerce
 * Description: Create flexible WooCommerce subscriptions with renewal scheduling, payment recovery, trials, sign-up fees, and gateway-neutral invoices.
 * Version: 0.6.4
 * Author: Mahfuzar Rahman
 * Author URI: https://profiles.wordpress.org/mahfuzar/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: repeatpay-subscriptions-for-woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.5
 * WC tested up to: 9.9
 *
 * @package RepeatPay_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

/* Prevent a renamed legacy copy and RepeatPay from booting together. */
if ( defined( 'REPEATPAY_PLUGIN_FILE' ) || defined( 'RENEWLY_PLUGIN_FILE' ) || defined( 'WFS_PLUGIN_FILE' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'More than one copy of RepeatPay is installed and active. Keep only RepeatPay Subscriptions for WooCommerce active, then delete the older My Subscriptions copy.', 'repeatpay-subscriptions-for-woocommerce' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'REPEATPAY_VERSION', '0.6.4' );
define( 'REPEATPAY_PLUGIN_FILE', __FILE__ );
define( 'REPEATPAY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once REPEATPAY_PLUGIN_PATH . 'includes/class-repeatpay-plugin.php';

register_activation_hook( __FILE__, array( 'REPEATPAY_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'REPEATPAY_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'REPEATPAY_Plugin', 'instance' ) );
