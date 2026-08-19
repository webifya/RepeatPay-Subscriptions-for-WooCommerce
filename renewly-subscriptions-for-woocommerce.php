<?php
/**
 * Plugin Name: Renewly Subscriptions for WooCommerce
 * Plugin URI: https://www.webninjallc.com/plugins/renewly/
 * Description: Create flexible WooCommerce subscriptions with renewal scheduling, payment recovery, trials, sign-up fees, and gateway-neutral invoices.
 * Version: 0.6.0
 * Author: Mahfuzar Rahman
 * Author URI: https://profiles.wordpress.org/mahfuzar/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: renewly-subscriptions-for-woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.5
 * WC tested up to: 9.9
 *
 * @package Renewly_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

/* Prevent a renamed legacy copy and Renewly from booting together. */
if ( defined( 'RENEWLY_PLUGIN_FILE' ) || defined( 'WFS_PLUGIN_FILE' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'More than one copy of Renewly is installed and active. Keep only Renewly Subscriptions for WooCommerce active, then delete the older My Subscriptions copy.', 'renewly-subscriptions-for-woocommerce' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'RENEWLY_VERSION', '0.6.0' );
define( 'RENEWLY_PLUGIN_FILE', __FILE__ );
define( 'RENEWLY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once RENEWLY_PLUGIN_PATH . 'includes/class-renewly-plugin.php';

register_activation_hook( __FILE__, array( 'RENEWLY_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RENEWLY_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'RENEWLY_Plugin', 'instance' ) );
