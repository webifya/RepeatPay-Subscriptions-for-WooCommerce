<?php
/**
 * Consent-based site profile sharing for the free edition.
 *
 * @package Renewly_Subscriptions
 */

defined( 'ABSPATH' ) || exit;

class RENEWLY_Site_Profile {
	const CRON     = 'renewly_weekly_site_profile';
	const ENDPOINT = 'https://www.webninjallc.com/wp-json/wnlm/v1/site-profile';

	/** Register profile refresh hooks. */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON, array( __CLASS__, 'send' ) );
		add_action( 'add_option_' . RENEWLY_Settings::OPTION, array( __CLASS__, 'settings_added' ), 10, 2 );
		add_action( 'update_option_' . RENEWLY_Settings::OPTION, array( __CLASS__, 'settings_updated' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'plugin_updated' ), 10, 2 );
		self::schedule();
	}

	/**
	 * Add the weekly interval used for consented compatibility-profile refreshes.
	 *
	 * @param array $schedules Registered cron schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'renewly-subscriptions-for-woocommerce' ),
			);
		}
		return $schedules;
	}

	/** Send immediately when the settings option is saved for the first time. */
	public static function settings_added( $option, $value ) {
		if ( ! empty( $value['share_site_profile'] ) ) {
			self::send( true );
		}
		self::schedule();
	}

	/** Keep the weekly refresh active only while the administrator has opted in. */
	public static function schedule() {
		if ( RENEWLY_Settings::get( 'share_site_profile' ) ) {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON );
			}
		} else {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/** Send or remove the profile when consent changes. */
	public static function settings_updated( $old_value, $new_value ) {
		$old_consent = ! empty( $old_value['share_site_profile'] );
		$new_consent = ! empty( $new_value['share_site_profile'] );
		if ( $old_consent !== $new_consent ) {
			self::send( $new_consent );
		}
		self::schedule();
	}

	/** Refresh after this plugin is upgraded. */
	public static function plugin_updated( $upgrader, $options ) {
		$plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : array( $options['plugin'] ?? '' );
		if ( 'plugin' === ( $options['type'] ?? '' ) && in_array( plugin_basename( RENEWLY_PLUGIN_FILE ), $plugins, true ) && RENEWLY_Settings::get( 'share_site_profile' ) ) {
			self::send( true );
		}
	}

	/**
	 * Share a consented profile, or remove it when consent is false.
	 *
	 * @param bool|null $consent Explicit override; null reads the saved preference.
	 * @return array|WP_Error
	 */
	public static function send( $consent = null ) {
		$consent = null === $consent ? (bool) RENEWLY_Settings::get( 'share_site_profile' ) : (bool) $consent;
		$body     = self::profile( $consent );
		$response = wp_safe_remote_post(
			self::endpoint(),
			array(
				'timeout'   => 12,
				'sslverify' => true,
				'headers'   => array( 'Accept' => 'application/json' ),
				'body'      => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : __( 'The site profile was not accepted.', 'renewly-subscriptions-for-woocommerce' );
			return new WP_Error( 'renewly_profile_rejected', sanitize_text_field( $message ) );
		}
		if ( ! $consent ) {
			delete_option( 'renewly_site_profile_instance_id' );
		}
		return is_array( $data ) ? $data : array();
	}

	/** Build profile data. Personal fields are populated only after opt-in. */
	private static function profile( $consent ) {
		global $wp_version;
		$theme = wp_get_theme();
		return array(
			'consent'            => $consent ? 'true' : 'false',
			'consent_version'    => '1.0',
			'site_url'           => home_url(),
			'site_name'          => $consent ? get_bloginfo( 'name' ) : '',
			'admin_email'        => $consent ? sanitize_email( get_option( 'admin_email' ) ) : '',
			'plugin_name'        => 'Renewly Subscriptions for WooCommerce',
			'plugin_slug'        => 'renewly-subscriptions-for-woocommerce',
			'plugin_version'     => RENEWLY_VERSION,
			'product'            => 'renewly-subscriptions-for-woocommerce',
			'instance_id'        => self::instance_id(),
			'wordpress_version'  => $consent ? $wp_version : '',
			'php_version'        => $consent ? PHP_VERSION : '',
			'active_theme'       => $consent ? $theme->get( 'Name' ) : '',
			'is_multisite'       => $consent && is_multisite() ? 'true' : 'false',
			'locale'             => $consent ? determine_locale() : '',
			'environment'        => $consent ? wp_get_environment_type() : '',
		);
	}

	/** Stable free-edition installation identifier. */
	private static function instance_id() {
		$id = get_option( 'renewly_site_profile_instance_id' );
		if ( ! $id ) {
			$id = wp_generate_uuid4();
			update_option( 'renewly_site_profile_instance_id', $id, false );
		}
		return sanitize_text_field( $id );
	}

	/** Return the documented profile collector URL. */
	private static function endpoint() {
		return apply_filters( 'renewly_site_profile_api_url', self::ENDPOINT );
	}

	/** Add suggested privacy-policy text for stored subscription data and optional sharing. */
	public static function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			/* translators: %s: URL to Renewly service and privacy information. */
			__( 'Renewly stores subscription records in this website’s WordPress database and associates them with WooCommerce customers and orders so recurring invoices can be created and managed. These records remain on the website unless the site owner removes them. If an administrator explicitly enables compatibility-profile sharing, the website sends its URL and name, administrator email address, WordPress and PHP versions, active theme name, locale, environment type, multisite status, plugin version, and a random installation identifier to Web Ninja LLC. The profile is refreshed weekly while consent remains enabled. Disabling the setting sends an erasure request and stops future sharing. Renewly does not send orders, customer records, payment details, or subscription records to this service. Service and privacy information is available at <a href="%s">Web Ninja LLC</a>.', 'renewly-subscriptions-for-woocommerce' ),
			esc_url( 'https://www.webninjallc.com/plugins/renewly/' )
		);

		wp_add_privacy_policy_content(
			__( 'Renewly Subscriptions for WooCommerce', 'renewly-subscriptions-for-woocommerce' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}
}
