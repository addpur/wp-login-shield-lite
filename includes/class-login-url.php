<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mengganti URL login WordPress dan memblokir akses ke wp-login.php / wp-admin
 * default (untuk pengunjung yang belum login) jika fitur aktif.
 */
class LSL_Login_Url {

	private $settings;

	public function __construct() {
		$this->settings = LSL_Settings::get_all();

		if ( empty( $this->settings['login_url_enabled'] ) || empty( $this->settings['login_slug'] ) ) {
			return;
		}

		// Ganti semua link yang mengarah ke wp-login.php agar memakai slug baru.
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'filter_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ), 10, 2 );

		// PENTING: class ini di-construct dari dalam callback yang SEDANG
		// berjalan pada hook 'plugins_loaded' (lihat lsl_init_plugin()).
		// Jangan add_action('plugins_loaded', ..., priority_lebih_awal) di
		// sini — WordPress tidak akan "mundur" ke priority yang sudah
		// terlewati, sehingga callback itu tidak akan pernah terpanggil.
		// Karena 'plugins_loaded' sudah cukup awal (sebelum WP mem-parsing
		// routing/query), kita proses request-nya langsung di sini.
		$this->handle_request();
	}

	private function get_slug() {
		return trim( $this->settings['login_slug'], '/' );
	}

	/**
	 * Cek apakah URL yang diminta adalah wp-login.php / wp-signup.php bawaan,
	 * atau slug custom kita, lalu bertindak sesuai kondisi.
	 */
	public function handle_request() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			$this->maybe_block_wp_admin();
			return;
		}

		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request_path = $this->get_request_path();
		$slug         = $this->get_slug();

		$is_default_login = ( 'wp-login.php' === $request_path );
		$is_custom_slug    = ( $slug === $request_path );

		if ( $is_custom_slug ) {
			$this->serve_login_form();
			return;
		}

		if ( $is_default_login ) {
			$this->block_request();
		}
	}

	/**
	 * Blokir akses wp-admin langsung (kecuali admin-ajax / admin-post yang memang
	 * perlu diakses oleh sistem, dan user yang sudah login).
	 */
	private function maybe_block_wp_admin() {
		if ( is_user_logged_in() ) {
			return;
		}

		$script = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		$allowed_unauthenticated = array( 'admin-ajax.php', 'admin-post.php' );

		if ( in_array( $script, $allowed_unauthenticated, true ) ) {
			return;
		}

		$this->block_request();
	}

	/**
	 * Ambil path request relatif terhadap root situs, tanpa query string,
	 * tanpa leading/trailing slash. Hanya memotong PREFIX path instalasi WP
	 * (berguna untuk situs yang dipasang di subdirektori), bukan menghapus
	 * semua karakter slash — supaya tetap benar untuk path bertingkat.
	 */
	private function get_request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = '/' === $home_path ? '' : rtrim( $home_path, '/' );

		if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		return trim( $path, '/' );
	}

	/**
	 * Tampilkan wp-login.php asli tanpa mengubah URL yang tampil di browser.
	 */
	private function serve_login_form() {
		global $pagenow;
		$pagenow = 'wp-login.php';
		require ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Balikan 404 (atau redirect ke home) agar wp-login.php/wp-admin default
	 * seolah tidak pernah ada.
	 *
	 * PENTING: fungsi ini bisa terpanggil dari konteks wp-admin (admin.php)
	 * MAUPUN front-end, pada hook 'plugins_loaded' — jauh sebelum WordPress
	 * selesai men-setup tema (functions.php tema, menu, widget, dsb belum
	 * termuat). Karena itu, JANGAN memuat template 404 milik tema
	 * (get_query_template) di sini — di konteks wp-admin ini pasti fatal
	 * error, dan di front-end pun berisiko karena tema belum siap.
	 * Kita tampilkan 404 generik yang aman & ringan (mirip pendekatan
	 * plugin "hide login" lain), tanpa bergantung pada tema sama sekali.
	 */
	private function block_request() {
		$target = $this->settings['redirect_target'];

		if ( 'home' === $target ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		wp_die(
			'<h1>' . esc_html__( 'Not Found', 'login-shield-lite' ) . '</h1><p>' .
			esc_html__( 'The requested URL was not found on this server.', 'login-shield-lite' ) . '</p>',
			esc_html__( 'Not Found', 'login-shield-lite' ),
			array( 'response' => 404 )
		);
	}

	/**
	 * Filter agar site_url('wp-login.php', ...) menghasilkan slug custom
	 * (dipakai wp_login_url(), email reset password, dll).
	 */
	public function filter_site_url( $url, $path = '', $scheme = null, $blog_id = null ) {
		if ( strpos( (string) $path, 'wp-login.php' ) === 0 ) {
			$slug     = $this->get_slug();
			$new_path = str_replace( 'wp-login.php', $slug, $path );
			$url      = str_replace( $path, $new_path, $url );
		}
		return $url;
	}

	public function filter_wp_redirect( $location, $status ) {
		if ( strpos( $location, 'wp-login.php' ) !== false && strpos( $location, 'action=logout' ) === false ) {
			$slug     = $this->get_slug();
			$location = str_replace( 'wp-login.php', $slug, $location );
		}
		return $location;
	}
}
