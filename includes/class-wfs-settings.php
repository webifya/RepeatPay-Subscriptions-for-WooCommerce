<?php
/**
 * Subscription settings.
 *
 * @package Subscribely_Recurring_Billing
 */

defined( 'ABSPATH' ) || exit;

class WFS_Settings {
	const OPTION = 'wfs_dunning_settings';

	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the settings page.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Subscription settings', 'subscribely-recurring-billing-for-woocommerce' ),
			__( 'Subscription settings', 'subscribely-recurring-billing-for-woocommerce' ),
			'manage_woocommerce',
			'wfs-settings',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Register settings and fields.
	 */
	public static function register() {
		register_setting(
			'wfs_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'wfs_dunning',
			__( 'Failed-payment recovery', 'subscribely-recurring-billing-for-woocommerce' ),
			array( __CLASS__, 'section' ),
			'wfs-settings'
		);

		add_settings_field(
			'retry_days',
			__( 'Days between reminders', 'subscribely-recurring-billing-for-woocommerce' ),
			array( __CLASS__, 'number_field' ),
			'wfs-settings',
			'wfs_dunning',
			array( 'key' => 'retry_days', 'min' => 1, 'max' => 30 )
		);

		add_settings_section(
			'wfs_privacy',
			__( 'Site profile and privacy', 'subscribely-recurring-billing-for-woocommerce' ),
			array( __CLASS__, 'privacy_section' ),
			'wfs-settings'
		);
		add_settings_field(
			'share_site_profile',
			__( 'Share site profile', 'subscribely-recurring-billing-for-woocommerce' ),
			array( __CLASS__, 'profile_consent_field' ),
			'wfs-settings',
			'wfs_privacy'
		);
		add_settings_field(
			'max_retries',
			__( 'Maximum reminders', 'subscribely-recurring-billing-for-woocommerce' ),
			array( __CLASS__, 'number_field' ),
			'wfs-settings',
			'wfs_dunning',
			array( 'key' => 'max_retries', 'min' => 1, 'max' => 10 )
		);
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'retry_days'         => 3,
			'max_retries'        => 3,
			'share_site_profile' => 0,
		);
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return int
	 */
	public static function get( $key ) {
		$values = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		return isset( $values[ $key ] ) ? absint( $values[ $key ] ) : 0;
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $values Submitted values.
	 * @return array
	 */
	public static function sanitize( $values ) {
		$values = is_array( $values ) ? $values : array();
		return array(
			'retry_days'         => min( 30, max( 1, absint( $values['retry_days'] ?? 3 ) ) ),
			'max_retries'        => min( 10, max( 1, absint( $values['max_retries'] ?? 3 ) ) ),
			'share_site_profile' => empty( $values['share_site_profile'] ) ? 0 : 1,
		);
	}

	/**
	 * Settings introduction.
	 */
	public static function section() {
		echo '<p>' . esc_html__( 'Unpaid renewal orders are reminded automatically. After the final attempt, the subscription is placed on hold until the order is paid.', 'subscribely-recurring-billing-for-woocommerce' ) . '</p>';
	}

	/** Explain the optional profile collection. */
	public static function privacy_section() {
		echo '<p>' . esc_html__( 'Help Web Ninja LLC understand compatibility and provide support. Nothing is shared unless you opt in, and disabling the option requests deletion of the stored profile.', 'subscribely-recurring-billing-for-woocommerce' ) . '</p>';
	}

	/** Render explicit site-profile consent. */
	public static function profile_consent_field() {
		printf(
			'<label><input type="checkbox" name="%1$s[share_site_profile]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( self::get( 'share_site_profile' ), 1, false ),
			esc_html__( 'Allow Subscribely to share this website profile to receive regular updates and compatibility support.', 'subscribely-recurring-billing-for-woocommerce' )
		);
	}

	/**
	 * Render a numeric field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function number_field( $args ) {
		printf(
			'<input type="number" class="small-text" name="%1$s[%2$s]" value="%3$d" min="%4$d" max="%5$d" step="1" />',
			esc_attr( self::OPTION ),
			esc_attr( $args['key'] ),
			esc_attr( self::get( $args['key'] ) ),
			esc_attr( $args['min'] ),
			esc_attr( $args['max'] )
		);
	}

	/**
	 * Render settings page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subscribely – Recurring Billing for WooCommerce settings', 'subscribely-recurring-billing-for-woocommerce' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wfs_settings' );
				do_settings_sections( 'wfs-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
