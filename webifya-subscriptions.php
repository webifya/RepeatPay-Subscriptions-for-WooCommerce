<?php
/**
 * Plugin Name: Subscribely – Recurring Billing for WooCommerce
 * Plugin URI: https://www.webninjallc.com/plugins/subscribely/
 * Description: Create flexible WooCommerce subscriptions with renewal scheduling, payment recovery, trials, sign-up fees, and gateway-neutral invoices.
 * Version: 0.5.9
 * Author: Mahfuzar Rahman
 * Author URI: https://profiles.wordpress.org/mahfuzar/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: subscribely-recurring-billing-for-woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.5
 * WC tested up to: 9.9
 *
 * @package Subscribely_Recurring_Billing
 */

defined( 'ABSPATH' ) || exit;

/* Prevent a renamed legacy copy and Subscribely from booting together. */
if ( defined( 'WFS_PLUGIN_FILE' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'More than one copy of Subscribely is installed and active. Keep only Subscribely – Recurring Billing for WooCommerce active, then delete the older My Subscriptions copy.', 'subscribely-recurring-billing-for-woocommerce' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'WFS_VERSION', '0.5.9' );
define( 'WFS_PLUGIN_FILE', __FILE__ );
define( 'WFS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once WFS_PLUGIN_PATH . 'includes/class-wfs-plugin.php';

register_activation_hook( __FILE__, array( 'WFS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WFS_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WFS_Plugin', 'instance' ) );
