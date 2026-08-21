<?php
/**
 * Free-to-PRO upgrade promotion.
 *
 * @package RepeatPay_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

class REPEATPAY_Upgrade {
	const PROMO_URL   = 'https://webninjallc.com/plugins/repeatpay/';
	const DETAILS_URL = 'https://webninjallc.com/plugins/repeatpay/';

	/**
	 * Register upgrade entry points.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_styles' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( REPEATPAY_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/** Enqueue styles only on the plugin's upgrade screen. */
	public static function admin_styles( $hook_suffix ) {
		if ( 'woocommerce_page_repeatpay-upgrade-pro' !== $hook_suffix ) {
			return;
		}

		wp_register_style( 'repeatpay-upgrade', false, array(), REPEATPAY_VERSION );
		wp_enqueue_style( 'repeatpay-upgrade' );
		wp_add_inline_style( 'repeatpay-upgrade', '.repeatpay-upgrade-wrap{max-width:960px}.repeatpay-upgrade-hero{margin-top:24px;padding:40px;border-radius:16px;background:linear-gradient(135deg,#312e81,#7c3aed);color:#fff}.repeatpay-upgrade-hero h1{color:#fff;font-size:34px;margin:0 0 12px}.repeatpay-upgrade-hero p{font-size:17px;max-width:720px}.repeatpay-upgrade-price{font-size:30px;font-weight:700;margin:24px 0}.repeatpay-upgrade-price small{font-size:15px;font-weight:400}.repeatpay-upgrade-button{display:inline-block;padding:13px 24px;border-radius:7px;background:#fff;color:#4c1d95!important;font-size:16px;font-weight:700;text-decoration:none}.repeatpay-upgrade-features{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:22px}.repeatpay-upgrade-feature{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px}.repeatpay-upgrade-feature h2{margin-top:0;font-size:17px}.repeatpay-upgrade-note{margin-top:20px;color:#50575e}' );
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
			__( 'Upgrade to RepeatPay PRO', 'repeatpay-subscriptions-for-woocommerce' ),
			__( 'Upgrade to PRO', 'repeatpay-subscriptions-for-woocommerce' ),
			'manage_woocommerce',
			'repeatpay-upgrade-pro',
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
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=repeatpay-settings' ) ) . '">' . esc_html__( 'Settings', 'repeatpay-subscriptions-for-woocommerce' ) . '</a>';
		if ( self::pro_is_active() ) {
			return array_merge( array( $settings ), $links );
		}

		$upgrade = '<a href="' . esc_url( self::PROMO_URL ) . '" target="_blank" rel="noopener sponsored" style="color:#087a4b;font-weight:700">' . esc_html__( 'Go PRO', 'repeatpay-subscriptions-for-woocommerce' ) . '</a>';
		return array_merge( array( $settings, $upgrade ), $links );
	}

	/** Add the documentation link beneath the plugin description. */
	public static function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( REPEATPAY_PLUGIN_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( self::DETAILS_URL . 'documentation/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Documentation', 'repeatpay-subscriptions-for-woocommerce' ) . '</a>';
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
		<div class="wrap repeatpay-upgrade-wrap">
			<section class="repeatpay-upgrade-hero">
				<h1><?php esc_html_e( 'Grow recurring revenue with RepeatPay PRO', 'repeatpay-subscriptions-for-woocommerce' ); ?></h1>
				<p><?php esc_html_e( 'Automate renewals and give customers flexible subscription controls while keeping the reliable invoicing and recovery tools from the free edition.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p>
				<div class="repeatpay-upgrade-price">
					<?php esc_html_e( '$69.99 per year', 'repeatpay-subscriptions-for-woocommerce' ); ?>
					<small><?php esc_html_e( 'billed annually', 'repeatpay-subscriptions-for-woocommerce' ); ?></small>
				</div>
				<a class="repeatpay-upgrade-button" href="<?php echo esc_url( self::PROMO_URL ); ?>" target="_blank" rel="noopener sponsored">
					<?php esc_html_e( 'Get RepeatPay PRO', 'repeatpay-subscriptions-for-woocommerce' ); ?>
				</a>
			</section>

			<div class="repeatpay-upgrade-features">
				<section class="repeatpay-upgrade-feature"><h2><?php esc_html_e( 'Automatic renewal payments', 'repeatpay-subscriptions-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Automatically charge saved methods through supported Stripe, PayPal, and Square gateways.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p></section>
				<section class="repeatpay-upgrade-feature"><h2><?php esc_html_e( 'Smart payment recovery', 'repeatpay-subscriptions-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Retry failed automatic payments and fall back to a customer payment invoice when needed.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p></section>
				<section class="repeatpay-upgrade-feature"><h2><?php esc_html_e( 'Pause and resume', 'repeatpay-subscriptions-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Let customers pause and resume eligible subscriptions with merchant-controlled limits.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p></section>
				<section class="repeatpay-upgrade-feature"><h2><?php esc_html_e( 'Early renewals', 'repeatpay-subscriptions-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Allow customers to renew before the scheduled payment date.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p></section>
				<section class="repeatpay-upgrade-feature"><h2><?php esc_html_e( 'Advanced administration', 'repeatpay-subscriptions-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Manage subscription status and next-payment dates directly from WordPress.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p></section>
				<section class="repeatpay-upgrade-feature"><h2><?php esc_html_e( 'Premium account experience', 'repeatpay-subscriptions-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Give customers detailed subscription pages and convenient self-service controls.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p></section>
				<section class="repeatpay-upgrade-feature"><h2><?php esc_html_e( 'Protected subscriber downloads', 'repeatpay-subscriptions-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Control file limits and expiry, reset access after renewal, and revoke downloads when entitlement ends.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p></section>
			</div>
			<p class="repeatpay-upgrade-note"><?php esc_html_e( 'RepeatPay PRO is a separately distributed add-on. The free plugin remains complete and continues to provide its listed subscription and renewal features without a time or usage limit.', 'repeatpay-subscriptions-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Check whether the PRO add-on is active.
	 *
	 * @return bool
	 */
	private static function pro_is_active() {
		return defined( 'REPEATPAY_PRO_VERSION' ) || class_exists( 'REPEATPAY_PRO_Automatic_Payments' );
	}
}
