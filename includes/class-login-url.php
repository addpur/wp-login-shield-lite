<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mengganti URL login WordPress dan memblokir akses ke wp-login.php / wp-admin
 * default (untuk pengunjung yang belum login) jika fitur aktif.
 *
 * POLA DUA FASE (penting, jangan disederhanakan):
 * - Fase DETEKSI berjalan sedini mungkin, di hook 'plugins_loaded' (dipanggil
 *   langsung dari constructor). Di sini kita hanya menentukan jenis request
 *   dan, kalau perlu blokir, langsung tampilkan 404 generik (aman, tidak
 *   bergantung tema, jadi aman dieksekusi sedini ini).
 * - Fase SAJIKAN FORM LOGIN ditunda ke hook 'wp_loaded' (dekat akhir siklus
 *   bootstrap WordPress). wp-login.php memakai banyak API WordPress (hook
 *   login, translation, dst) yang belum siap di 'plugins_loaded' — memaksa
 *   require di situ menyebabkan fatal error. Pola ini sama persis dengan
 *   yang dipakai plugin "hide login" populer lain yang sudah teruji
 *   bertahun-tahun di jutaan situs.
 */
class LSL_Login_Url {

	private $settings;
	private $serve_login_form = false;

	public function __construct() {
		$this->settings = LSL_Settings::get_all();

		if ( empty( $this->settings['login_url_enabled'] ) || empty( $this->settings['login_slug'] ) ) {
			return;
		}

		// Ganti semua link yang mengarah ke wp-login.php agar memakai slug baru.
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'filter_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ), 10, 2 );

		// Fase deteksi — dijalankan sekarang juga (masih di dalam callback
		// 'plugins_loaded', lihat lsl_init_plugin()).
		$this->handle_early_detection();

		// Fase penyajian form login — ditunda sampai WordPress benar-benar
		// siap (tema, hook login, dsb).
		add_action( 'wp_loaded', array( $this, 'maybe_serve_login_form' ) );
	}

	private function get_slug() {
		return trim( $this->settings['login_slug'], '/' );
	}

	/**
	 * Tentukan jenis request: slug baru (tandai untuk disajikan nanti di
	 * wp_loaded), wp-login.php/wp-admin lama (blokir sekarang juga — ini
	 * aman dilakukan sedini ini karena block_request() tidak bergantung tema).
	 */
	private function handle_early_detection() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			$this->maybe_block_wp_admin();
			return;
		}

		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request_path = $this->get_request_path();
		$slug         = $this->get_slug();

		if ( $slug === $request_path ) {
			// Jangan require wp-login.php di sini — masih terlalu dini.
			// Cukup tandai; eksekusi sesungguhnya ada di maybe_serve_login_form().
			$this->serve_login_form = true;
			return;
		}

		if ( 'wp-login.php' === $request_path ) {
			$this->block_request();
		}
	}

	/**
	 * Dipanggil di hook 'wp_loaded'. WordPress sudah selesai setup tema,
	 * query, dan semua hook penting lain di titik ini, jadi require
	 * wp-login.php di sini aman (tidak fatal error).
	 */
	public function maybe_serve_login_form() {
		if ( ! $this->serve_login_form ) {
			return;
		}

		global $pagenow;
		$pagenow = 'wp-login.php';
		require ABSPATH . 'wp-login.php';
		exit;
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
	 * Balikan 404 (atau redirect ke home) agar wp-login.php/wp-admin default
	 * seolah tidak pernah ada.
	 *
	 * PENTING: fungsi ini dipanggil dari 'plugins_loaded' — jauh sebelum
	 * WordPress selesai men-setup tema. Karena itu JANGAN memuat template
	 * 404 milik tema (get_query_template) di sini: di konteks wp-admin ini
	 * bisa fatal error, dan di front-end pun berisiko karena tema belum
	 * siap. 404 generik ini sengaja tidak bergantung tema sama sekali,
	 * sehingga aman dieksekusi sedini ini.
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
