<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menyimpan & mengambil semua opsi plugin dalam satu array (satu row di wp_options).
 */
class LSL_Settings {

	const OPTION_KEY = 'lsl_settings';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	public static function get_defaults() {
		return array(
			// Ganti URL Login.
			'login_url_enabled'   => false,
			'login_slug'          => 'secure-login',
			'redirect_target'     => '404', // '404' atau 'home'.

			// Limit percobaan login.
			'limiter_enabled'     => true,
			'max_attempts'        => 5,
			'lockout_minutes'     => 60,
			'attempt_window_min'  => 20, // window hitung percobaan sebelum reset.

			// History login.
			'history_enabled'     => true,
			'history_retention'   => 90, // hari, 0 = simpan selamanya.

			// Captcha.
			'captcha_provider'    => 'none', // none | recaptcha | turnstile.
			'recaptcha_site_key'  => '',
			'recaptcha_secret'    => '',
			'turnstile_site_key'  => '',
			'turnstile_secret'    => '',

			// Anti-spam komentar.
			'comment_spam_enabled'       => true, // honeypot + time-trap.
			'comment_time_trap_seconds'  => 3,
			'comment_max_links'          => 2, // 0 = tidak dicek.
			'comment_captcha_enabled'    => false, // pakai captcha_provider di atas.
			'comment_rate_limit_enabled' => true,
			'comment_rate_limit_max'     => 5,
			'comment_rate_limit_window'  => 10, // menit.

			// XML-RPC & pingback/trackback.
			'xmlrpc_disabled'   => false,
			'pingback_disabled' => true,

			// Proteksi form registrasi user.
			'register_protection_enabled' => true, // honeypot + time-trap.
			'register_captcha_enabled'    => false, // pakai captcha_provider di atas.
		);
	}

	public static function get_all() {
		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $settings, self::get_defaults() );
	}

	public static function get( $key, $default = null ) {
		$settings = self::get_all();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public static function update( array $new_values ) {
		$settings = self::get_all();
		$settings = array_merge( $settings, $new_values );
		update_option( self::OPTION_KEY, $settings );
		return $settings;
	}

	public function maybe_upgrade() {
		// Placeholder untuk migrasi opsi di versi mendatang.
		if ( get_option( 'lsl_db_version' ) !== LSL_DB_VERSION ) {
			LSL_Login_History::create_table();
			update_option( 'lsl_db_version', LSL_DB_VERSION );
		}
	}
}
