<?php
/**
 * Free-to-PRO upgrade promotion.
 *
 * @package Subscribely_Recurring_Billing
 */

defined( 'ABSPATH' ) || exit;

class WFS_Upgrade {
	const PROMO_URL   = 'https://webninjallc.com/plugins/subscribely/';
	const DETAILS_URL = 'https://webninjallc.com/plugins/subscribely/';

	/**
	 * Register upgrade entry points.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( WFS_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add the WooCommerce upgrade submenu for free-edition users.
	 */
	public static function menu() {
		if ( self::pro_is_active() ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'Upgrade to Subscribely PRO', 'subscribely-recurring-billing-for-woocommerce' ),
			__( 'Upgrade to PRO', 'subscribely-recurring-billing-for-woocommerce' ),
			'manage_woocommerce',
			'wfs-upgrade-pro',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Add a prominent upgrade link on the Plugins screen.
	 *
	 * @param array $links Existing plugin links.
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=wfs-settings' ) ) . '">' . esc_html__( 'Settings', 'subscribely-recurring-billing-for-woocommerce' ) . '</a>';
		if ( self::pro_is_active() ) {
			return array_merge( array( $settings ), $links );
		}

		$upgrade = '<a href="' . esc_url( self::PROMO_URL ) . '" target="_blank" rel="noopener sponsored" style="color:#087a4b;font-weight:700">' . esc_html__( 'Go PRO', 'subscribely-recurring-billing-for-woocommerce' ) . '</a>';
		return array_merge( array( $settings, $upgrade ), $links );
	}

	/** Add the documentation link beneath the plugin description. */
	public static function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( WFS_PLUGIN_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( self::DETAILS_URL . 'documentation/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Documentation', 'subscribely-recurring-billing-for-woocommerce' ) . '</a>';
		return $links;
	}

	/**
	 * Render the upgrade page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		?>
		<div class="wrap wfs-upgrade-wrap">
			<style>
				.wfs-upgrade-wrap{max-width:960px}.wfs-upgrade-hero{margin-top:24px;padding:40px;border-radius:16px;background:linear-gradient(135deg,#312e81,#7c3aed);color:#fff}.wfs-upgrade-hero h1{color:#fff;font-size:34px;margin:0 0 12px}.wfs-upgrade-hero p{font-size:17px;max-width:720px}.wfs-upgrade-price{font-size:30px;font-weight:700;margin:24px 0}.wfs-upgrade-price small{font-size:15px;font-weight:400}.wfs-upgrade-button{display:inline-block;padding:13px 24px;border-radius:7px;background:#fff;color:#4c1d95!important;font-size:16px;font-weight:700;text-decoration:none}.wfs-upgrade-features{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:22px}.wfs-upgrade-feature{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px}.wfs-upgrade-feature h2{margin-top:0;font-size:17px}.wfs-upgrade-note{margin-top:20px;color:#50575e}
			</style>
			<section class="wfs-upgrade-hero">
				<h1><?php esc_html_e( 'Grow recurring revenue with Subscribely PRO', 'subscribely-recurring-billing-for-woocommerce' ); ?></h1>
				<p><?php esc_html_e( 'Automate renewals and give customers flexible subscription controls while keeping the reliable invoicing and recovery tools from the free edition.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p>
				<div class="wfs-upgrade-price">
					<?php esc_html_e( '$69.99 per year', 'subscribely-recurring-billing-for-woocommerce' ); ?>
					<small><?php esc_html_e( 'billed annually', 'subscribely-recurring-billing-for-woocommerce' ); ?></small>
				</div>
				<a class="wfs-upgrade-button" href="<?php echo esc_url( self::PROMO_URL ); ?>" target="_blank" rel="noopener sponsored">
					<?php esc_html_e( 'Get Subscribely PRO', 'subscribely-recurring-billing-for-woocommerce' ); ?>
				</a>
			</section>

			<div class="wfs-upgrade-features">
				<section class="wfs-upgrade-feature"><h2><?php esc_html_e( 'Automatic renewal payments', 'subscribely-recurring-billing-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Automatically charge saved methods through supported Stripe, PayPal, and Square gateways.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p></section>
				<section class="wfs-upgrade-feature"><h2><?php esc_html_e( 'Smart payment recovery', 'subscribely-recurring-billing-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Retry failed automatic payments and fall back to a customer payment invoice when needed.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p></section>
				<section class="wfs-upgrade-feature"><h2><?php esc_html_e( 'Pause and resume', 'subscribely-recurring-billing-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Let customers pause and resume eligible subscriptions with merchant-controlled limits.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p></section>
				<section class="wfs-upgrade-feature"><h2><?php esc_html_e( 'Early renewals', 'subscribely-recurring-billing-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Allow customers to renew before the scheduled payment date.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p></section>
				<section class="wfs-upgrade-feature"><h2><?php esc_html_e( 'Advanced administration', 'subscribely-recurring-billing-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Manage subscription status and next-payment dates directly from WordPress.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p></section>
				<section class="wfs-upgrade-feature"><h2><?php esc_html_e( 'Premium account experience', 'subscribely-recurring-billing-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Give customers detailed subscription pages and convenient self-service controls.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p></section>
				<section class="wfs-upgrade-feature"><h2><?php esc_html_e( 'Protected subscriber downloads', 'subscribely-recurring-billing-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Control file limits and expiry, reset access after renewal, and revoke downloads when entitlement ends.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p></section>
			</div>
			<p class="wfs-upgrade-note"><?php esc_html_e( 'Subscribely PRO is a separately distributed add-on. The free plugin remains complete and continues to provide its listed subscription and renewal features without a time or usage limit.', 'subscribely-recurring-billing-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Check whether the PRO add-on is active.
	 *
	 * @return bool
	 */
	private static function pro_is_active() {
		return defined( 'MSPRO_VERSION' ) || class_exists( 'MSPRO_Automatic_Payments' );
	}
}
