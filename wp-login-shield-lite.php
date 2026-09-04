<?php
/**
 * Plugin Name: Login Shield Lite
 * Plugin URI:  https://github.com/addpur/wp-login-shield-lite
 * Description: Plugin ringan untuk mengamankan login WordPress: ganti URL login, blokir wp-admin/wp-login default, limit percobaan login, history login, captcha (Google reCAPTCHA / Cloudflare Turnstile), serta anti-spam komentar, XML-RPC, pingback/trackback, dan registrasi.
 * Version:     1.0.0
 * Author:      Adi
 * Author URI:  https://github.com/addpur
 * Text Domain: login-shield-lite
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Update URI:  https://github.com/addpur/wp-login-shield-lite
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Jangan akses file langsung.
}

define( 'LSL_VERSION', '1.0.0' );
define( 'LSL_PLUGIN_FILE', __FILE__ );
define( 'LSL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LSL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LSL_DB_VERSION', '1.0.0' );

/**
 * Plugin Update Checker (pustaka pihak ketiga, MIT license, oleh Yahnis Elsts:
 * https://github.com/YahnisElsts/plugin-update-checker). Memungkinkan WordPress
 * mengecek rilis GitHub baru dan menawarkan update, tanpa perlu plugin ini
 * terdaftar di WordPress.org.
 */
require_once LSL_PLUGIN_DIR . 'puc/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

function lsl_init_update_checker() {
	PucFactory::buildUpdateChecker(
		'https://github.com/addpur/wp-login-shield-lite/',
		LSL_PLUGIN_FILE,
		'wp-login-shield-lite'
	);
}
add_action( 'init', 'lsl_init_update_checker' );

/**
 * Autoload class-class internal plugin.
 */
spl_autoload_register( function ( $class ) {
	if ( strpos( $class, 'LSL_' ) !== 0 ) {
		return;
	}
	$file_name = 'class-' . strtolower( str_replace( '_', '-', substr( $class, 4 ) ) ) . '.php';
	$path      = LSL_PLUGIN_DIR . 'includes/' . $file_name;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );

/**
 * Aktivasi plugin: buat tabel history login + set default option.
 */
function lsl_activate_plugin() {
	require_once LSL_PLUGIN_DIR . 'includes/class-login-history.php';
	LSL_Login_History::create_table();

	$defaults = LSL_Settings::get_defaults();
	if ( false === get_option( 'lsl_settings' ) ) {
		add_option( 'lsl_settings', $defaults );
	}

	// Flush rewrite rules jaga-jaga.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'lsl_activate_plugin' );

function lsl_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'lsl_deactivate_plugin' );

/**
 * Inisialisasi seluruh modul plugin.
 */
function lsl_init_plugin() {
	load_plugin_textdomain( 'login-shield-lite', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	new LSL_Settings();
	new LSL_Login_Url();
	new LSL_Login_Limiter();
	new LSL_Login_History();
	$captcha = new LSL_Captcha();
	new LSL_Anti_Spam( $captcha );
	new LSL_Admin_Page();
}
add_action( 'plugins_loaded', 'lsl_init_plugin' );
