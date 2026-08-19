<?php
/**
 * Subscription settings.
 *
 * @package Renewly_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

class RENEWLY_Settings {
	const OPTION = 'renewly_dunning_settings';

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
			__( 'Subscription settings', 'renewly-subscriptions-for-woocommerce' ),
			__( 'Subscription settings', 'renewly-subscriptions-for-woocommerce' ),
			'manage_woocommerce',
			'renewly-settings',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Register settings and fields.
	 */
	public static function register() {
		register_setting(
			'renewly_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'renewly_dunning',
			__( 'Failed-payment recovery', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'section' ),
			'renewly-settings'
		);

		add_settings_field(
			'retry_days',
			__( 'Days between reminders', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'number_field' ),
			'renewly-settings',
			'renewly_dunning',
			array( 'key' => 'retry_days', 'min' => 1, 'max' => 30 )
		);

		add_settings_section(
			'renewly_privacy',
			__( 'Site profile and privacy', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'privacy_section' ),
			'renewly-settings'
		);
		add_settings_field(
			'share_site_profile',
			__( 'Share site profile', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'profile_consent_field' ),
			'renewly-settings',
			'renewly_privacy'
		);
		add_settings_field(
			'max_retries',
			__( 'Maximum reminders', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'number_field' ),
			'renewly-settings',
			'renewly_dunning',
			array( 'key' => 'max_retries', 'min' => 1, 'max' => 10 )
		);

		add_settings_section(
			'renewly_price_display',
			__( 'Subscription price display', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'price_display_section' ),
			'renewly-settings'
		);
		add_settings_field(
			'price_prefix',
			__( 'Default price prefix', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'text_field' ),
			'renewly-settings',
			'renewly_price_display',
			array(
				'key'         => 'price_prefix',
				'placeholder' => __( 'Starting from', 'renewly-subscriptions-for-woocommerce' ),
			)
		);
		add_settings_field(
			'price_suffix',
			__( 'Default price suffix', 'renewly-subscriptions-for-woocommerce' ),
			array( __CLASS__, 'text_field' ),
			'renewly-settings',
			'renewly_price_display',
			array(
				'key'         => 'price_suffix',
				'placeholder' => __( 'until your birthday', 'renewly-subscriptions-for-woocommerce' ),
			)
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
			'price_prefix'       => '',
			'price_suffix'       => '',
		);
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return int|string
	 */
	public static function get( $key ) {
		$values = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		if ( in_array( $key, array( 'price_prefix', 'price_suffix' ), true ) ) {
			return isset( $values[ $key ] ) ? sanitize_text_field( $values[ $key ] ) : '';
		}
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
			'price_prefix'       => sanitize_text_field( $values['price_prefix'] ?? '' ),
			'price_suffix'       => sanitize_text_field( $values['price_suffix'] ?? '' ),
		);
	}

	/**
	 * Settings introduction.
	 */
	public static function section() {
		echo '<p>' . esc_html__( 'Unpaid renewal orders are reminded automatically. After the final attempt, the subscription is placed on hold until the order is paid.', 'renewly-subscriptions-for-woocommerce' ) . '</p>';
	}

	/** Explain the optional profile collection. */
	public static function privacy_section() {
		echo '<p>' . esc_html__( 'Help Web Ninja LLC understand compatibility and provide support. Nothing is shared unless you opt in, and disabling the option requests deletion of the stored profile.', 'renewly-subscriptions-for-woocommerce' ) . '</p>';
	}

	/** Explain global subscription price wording. */
	public static function price_display_section() {
		echo '<p>' . esc_html__( 'Set optional text for all subscription prices. A product-specific prefix or suffix overrides the corresponding global value.', 'renewly-subscriptions-for-woocommerce' ) . '</p>';
	}

	/** Render explicit site-profile consent. */
	public static function profile_consent_field() {
		printf(
			'<label><input type="checkbox" name="%1$s[share_site_profile]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( self::get( 'share_site_profile' ), 1, false ),
			esc_html__( 'Allow Renewly to share this website profile to receive regular updates and compatibility support.', 'renewly-subscriptions-for-woocommerce' )
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
	 * Render a plain-text setting.
	 *
	 * @param array $args Field arguments.
	 */
	public static function text_field( $args ) {
		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" />',
			esc_attr( self::OPTION ),
			esc_attr( $args['key'] ),
			esc_attr( self::get( $args['key'] ) ),
			esc_attr( $args['placeholder'] )
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
			<h1><?php esc_html_e( 'Renewly Subscriptions for WooCommerce settings', 'renewly-subscriptions-for-woocommerce' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'renewly_settings' );
				do_settings_sections( 'renewly-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
