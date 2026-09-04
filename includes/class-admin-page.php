<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Halaman pengaturan di wp-admin: Settings > Login Shield.
 * Dibuat dengan komponen native wp-admin (form-table, postbox, WP_List_Table)
 * ditambah sedikit CSS modern.
 */
class LSL_Admin_Page {

	const CAPABILITY = 'manage_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_options_page(
			__( 'Login Shield Lite', 'login-shield-lite' ),
			__( 'Login Shield', 'login-shield-lite' ),
			self::CAPABILITY,
			'lsl-settings',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_lsl-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'lsl-admin', LSL_PLUGIN_URL . 'assets/admin.css', array(), LSL_VERSION );
		wp_enqueue_script( 'lsl-admin', LSL_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), LSL_VERSION, true );
	}

	public function handle_save() {
		if ( empty( $_POST['lsl_settings_nonce'] ) || ! wp_verify_nonce( $_POST['lsl_settings_nonce'], 'lsl_save_settings' ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// Hanya proses & simpan field milik tab yang benar-benar disubmit.
		// Ini penting: tiap tab punya <form> sendiri, jadi field tab lain
		// TIDAK ikut ter-POST. Kalau kita selalu menulis semua key dengan
		// fallback default, pengaturan di tab lain akan tertimpa/ke-reset.
		$tab = isset( $_POST['lsl_tab'] ) ? sanitize_key( $_POST['lsl_tab'] ) : '';
		$new = array();

		if ( 'general' === $tab ) {
			$new['login_url_enabled'] = ! empty( $_POST['login_url_enabled'] );
			$new['login_slug']        = isset( $_POST['login_slug'] ) ? sanitize_title( wp_unslash( $_POST['login_slug'] ) ) : 'secure-login';
			$new['redirect_target']   = ( isset( $_POST['redirect_target'] ) && 'home' === $_POST['redirect_target'] ) ? 'home' : '404';

			if ( empty( $new['login_slug'] ) ) {
				$new['login_slug'] = 'secure-login';
			}
		} elseif ( 'limiter' === $tab ) {
			$new['limiter_enabled']    = ! empty( $_POST['limiter_enabled'] );
			$new['max_attempts']       = isset( $_POST['max_attempts'] ) ? max( 1, (int) $_POST['max_attempts'] ) : 5;
			$new['lockout_minutes']    = isset( $_POST['lockout_minutes'] ) ? max( 1, (int) $_POST['lockout_minutes'] ) : 60;
			$new['attempt_window_min'] = isset( $_POST['attempt_window_min'] ) ? max( 1, (int) $_POST['attempt_window_min'] ) : 20;
		} elseif ( 'captcha' === $tab ) {
			// Tab ini juga memuat pengaturan History Login, jadi disimpan bersamaan.
			$new['captcha_provider']   = in_array( $_POST['captcha_provider'] ?? '', array( 'none', 'recaptcha', 'turnstile' ), true ) ? $_POST['captcha_provider'] : 'none';
			$new['recaptcha_site_key'] = isset( $_POST['recaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ) ) : '';
			$new['recaptcha_secret']   = isset( $_POST['recaptcha_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_secret'] ) ) : '';
			$new['turnstile_site_key'] = isset( $_POST['turnstile_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ) ) : '';
			$new['turnstile_secret']   = isset( $_POST['turnstile_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_secret'] ) ) : '';

			$new['history_enabled']   = ! empty( $_POST['history_enabled'] );
			$new['history_retention'] = isset( $_POST['history_retention'] ) ? max( 0, (int) $_POST['history_retention'] ) : 90;
		} elseif ( 'antispam' === $tab ) {
			$new['comment_spam_enabled']       = ! empty( $_POST['comment_spam_enabled'] );
			$new['comment_time_trap_seconds']  = isset( $_POST['comment_time_trap_seconds'] ) ? max( 1, (int) $_POST['comment_time_trap_seconds'] ) : 3;
			$new['comment_max_links']          = isset( $_POST['comment_max_links'] ) ? max( 0, (int) $_POST['comment_max_links'] ) : 2;
			$new['comment_captcha_enabled']    = ! empty( $_POST['comment_captcha_enabled'] );
			$new['comment_rate_limit_enabled'] = ! empty( $_POST['comment_rate_limit_enabled'] );
			$new['comment_rate_limit_max']     = isset( $_POST['comment_rate_limit_max'] ) ? max( 1, (int) $_POST['comment_rate_limit_max'] ) : 5;
			$new['comment_rate_limit_window']  = isset( $_POST['comment_rate_limit_window'] ) ? max( 1, (int) $_POST['comment_rate_limit_window'] ) : 10;

			$new['xmlrpc_disabled']   = ! empty( $_POST['xmlrpc_disabled'] );
			$new['pingback_disabled'] = ! empty( $_POST['pingback_disabled'] );

			$new['register_protection_enabled'] = ! empty( $_POST['register_protection_enabled'] );
			$new['register_captcha_enabled']    = ! empty( $_POST['register_captcha_enabled'] );
		} else {
			// Tab tidak dikenali — jangan ubah apa pun.
			return;
		}

		LSL_Settings::update( $new );

		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pengaturan Login Shield Lite disimpan.', 'login-shield-lite' ) . '</p></div>';
		} );
	}

	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = LSL_Settings::get_all();
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap lsl-wrap">
			<h1 class="lsl-title">
				<span class="dashicons dashicons-shield"></span>
				<?php esc_html_e( 'Login Shield Lite', 'login-shield-lite' ); ?>
			</h1>

			<h2 class="nav-tab-wrapper lsl-tabs">
				<a href="?page=lsl-settings&tab=general" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'URL Login', 'login-shield-lite' ); ?></a>
				<a href="?page=lsl-settings&tab=limiter" class="nav-tab <?php echo 'limiter' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Limit Login', 'login-shield-lite' ); ?></a>
				<a href="?page=lsl-settings&tab=captcha" class="nav-tab <?php echo 'captcha' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Captcha', 'login-shield-lite' ); ?></a>
				<a href="?page=lsl-settings&tab=antispam" class="nav-tab <?php echo 'antispam' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Anti-Spam', 'login-shield-lite' ); ?></a>
				<a href="?page=lsl-settings&tab=history" class="nav-tab <?php echo 'history' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'History Login', 'login-shield-lite' ); ?></a>
			</h2>

			<div class="lsl-card">
				<?php if ( 'history' === $tab ) : ?>
					<?php $this->render_history_tab(); ?>
				<?php else : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'lsl_save_settings', 'lsl_settings_nonce' ); ?>
						<input type="hidden" name="lsl_tab" value="<?php echo esc_attr( $tab ); ?>" />
						<?php
						if ( 'general' === $tab ) {
							$this->render_general_tab( $settings );
						} elseif ( 'limiter' === $tab ) {
							$this->render_limiter_tab( $settings );
						} elseif ( 'captcha' === $tab ) {
							$this->render_captcha_tab( $settings );
						} elseif ( 'antispam' === $tab ) {
							$this->render_antispam_tab( $settings );
						}
						?>
						<?php submit_button( __( 'Simpan Pengaturan', 'login-shield-lite' ) ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_general_tab( $settings ) {
		$login_url = home_url( '/' . ( $settings['login_slug'] ? $settings['login_slug'] : 'secure-login' ) );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Aktifkan Ganti URL Login', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="login_url_enabled" value="1" <?php checked( $settings['login_url_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Jika aktif, wp-login.php dan wp-admin default akan diblokir untuk pengunjung yang belum login.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="login_slug"><?php esc_html_e( 'Slug URL Login Baru', 'login-shield-lite' ); ?></label></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code>
					<input type="text" id="login_slug" name="login_slug" value="<?php echo esc_attr( $settings['login_slug'] ); ?>" class="regular-text" />
					<p class="description">
						<?php esc_html_e( 'URL login Anda saat ini:', 'login-shield-lite' ); ?>
						<strong><a href="<?php echo esc_url( $login_url ); ?>" target="_blank"><?php echo esc_html( $login_url ); ?></a></strong>
						— <?php esc_html_e( 'catat URL ini sebelum menyimpan, agar Anda tidak terkunci dari wp-admin.', 'login-shield-lite' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Aksi untuk URL Lama', 'login-shield-lite' ); ?></th>
				<td>
					<label><input type="radio" name="redirect_target" value="404" <?php checked( $settings['redirect_target'], '404' ); ?> /> <?php esc_html_e( 'Tampilkan halaman 404 (disarankan)', 'login-shield-lite' ); ?></label><br />
					<label><input type="radio" name="redirect_target" value="home" <?php checked( $settings['redirect_target'], 'home' ); ?> /> <?php esc_html_e( 'Alihkan ke halaman utama', 'login-shield-lite' ); ?></label>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_limiter_tab( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Aktifkan Limit Login', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="limiter_enabled" value="1" <?php checked( $settings['limiter_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="max_attempts"><?php esc_html_e( 'Maksimal Percobaan Gagal', 'login-shield-lite' ); ?></label></th>
				<td><input type="number" min="1" id="max_attempts" name="max_attempts" value="<?php echo esc_attr( $settings['max_attempts'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="attempt_window_min"><?php esc_html_e( 'Jendela Waktu Hitung Percobaan (menit)', 'login-shield-lite' ); ?></label></th>
				<td>
					<input type="number" min="1" id="attempt_window_min" name="attempt_window_min" value="<?php echo esc_attr( $settings['attempt_window_min'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Hitungan percobaan gagal akan direset jika tidak ada percobaan baru dalam jendela waktu ini.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lockout_minutes"><?php esc_html_e( 'Durasi Blokir IP (menit)', 'login-shield-lite' ); ?></label></th>
				<td>
					<input type="number" min="1" id="lockout_minutes" name="lockout_minutes" value="<?php echo esc_attr( $settings['lockout_minutes'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Default 60 menit (1 jam).', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_captcha_tab( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Penyedia Captcha', 'login-shield-lite' ); ?></th>
				<td>
					<select name="captcha_provider" id="lsl-captcha-provider">
						<option value="none" <?php selected( $settings['captcha_provider'], 'none' ); ?>><?php esc_html_e( 'Tidak digunakan', 'login-shield-lite' ); ?></option>
						<option value="recaptcha" <?php selected( $settings['captcha_provider'], 'recaptcha' ); ?>><?php esc_html_e( 'Google reCAPTCHA v2', 'login-shield-lite' ); ?></option>
						<option value="turnstile" <?php selected( $settings['captcha_provider'], 'turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile', 'login-shield-lite' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="lsl-field-recaptcha">
				<th scope="row"><label for="recaptcha_site_key"><?php esc_html_e( 'reCAPTCHA Site Key', 'login-shield-lite' ); ?></label></th>
				<td><input type="text" id="recaptcha_site_key" name="recaptcha_site_key" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr class="lsl-field-recaptcha">
				<th scope="row"><label for="recaptcha_secret"><?php esc_html_e( 'reCAPTCHA Secret Key', 'login-shield-lite' ); ?></label></th>
				<td><input type="password" id="recaptcha_secret" name="recaptcha_secret" value="<?php echo esc_attr( $settings['recaptcha_secret'] ); ?>" class="regular-text" autocomplete="off" /></td>
			</tr>
			<tr class="lsl-field-turnstile">
				<th scope="row"><label for="turnstile_site_key"><?php esc_html_e( 'Turnstile Site Key', 'login-shield-lite' ); ?></label></th>
				<td><input type="text" id="turnstile_site_key" name="turnstile_site_key" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr class="lsl-field-turnstile">
				<th scope="row"><label for="turnstile_secret"><?php esc_html_e( 'Turnstile Secret Key', 'login-shield-lite' ); ?></label></th>
				<td><input type="password" id="turnstile_secret" name="turnstile_secret" value="<?php echo esc_attr( $settings['turnstile_secret'] ); ?>" class="regular-text" autocomplete="off" /></td>
			</tr>
		</table>

		<hr />
		<h3><?php esc_html_e( 'Pengaturan History Login', 'login-shield-lite' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Aktifkan History Login', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="history_enabled" value="1" <?php checked( $settings['history_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="history_retention"><?php esc_html_e( 'Simpan History (hari)', 'login-shield-lite' ); ?></label></th>
				<td>
					<input type="number" min="0" id="history_retention" name="history_retention" value="<?php echo esc_attr( $settings['history_retention'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( '0 = simpan selamanya.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_antispam_tab( $settings ) {
		?>
		<h3><?php esc_html_e( 'Anti-Spam Komentar', 'login-shield-lite' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Aktifkan Proteksi Komentar', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="comment_spam_enabled" value="1" <?php checked( $settings['comment_spam_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Honeypot (field jebakan tak terlihat) + time-trap (tolak submit yang terlalu cepat). Tidak butuh layanan eksternal.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="comment_time_trap_seconds"><?php esc_html_e( 'Minimal Waktu Isi Form (detik)', 'login-shield-lite' ); ?></label></th>
				<td>
					<input type="number" min="1" id="comment_time_trap_seconds" name="comment_time_trap_seconds" value="<?php echo esc_attr( $settings['comment_time_trap_seconds'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Komentar yang dikirim lebih cepat dari ini akan ditolak (indikasi bot).', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="comment_max_links"><?php esc_html_e( 'Maksimal Link per Komentar', 'login-shield-lite' ); ?></label></th>
				<td>
					<input type="number" min="0" id="comment_max_links" name="comment_max_links" value="<?php echo esc_attr( $settings['comment_max_links'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Melebihi batas ini akan otomatis ditandai Spam (bukan ditolak, tetap bisa ditinjau di menu Comments). 0 = tidak dicek.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Wajibkan Captcha di Komentar', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="comment_captcha_enabled" value="1" <?php checked( $settings['comment_captcha_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Memakai penyedia captcha yang sudah dikonfigurasi di tab Captcha. Jika belum diatur di sana, opsi ini tidak akan aktif.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit Komentar per IP', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="comment_rate_limit_enabled" value="1" <?php checked( $settings['comment_rate_limit_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="comment_rate_limit_max"><?php esc_html_e( 'Maksimal Komentar', 'login-shield-lite' ); ?></label></th>
				<td><input type="number" min="1" id="comment_rate_limit_max" name="comment_rate_limit_max" value="<?php echo esc_attr( $settings['comment_rate_limit_max'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="comment_rate_limit_window"><?php esc_html_e( 'Per Berapa Menit', 'login-shield-lite' ); ?></label></th>
				<td><input type="number" min="1" id="comment_rate_limit_window" name="comment_rate_limit_window" value="<?php echo esc_attr( $settings['comment_rate_limit_window'] ); ?>" class="small-text" /></td>
			</tr>
		</table>

		<hr />
		<h3><?php esc_html_e( 'XML-RPC & Pingback/Trackback', 'login-shield-lite' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Nonaktifkan XML-RPC', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="xmlrpc_disabled" value="1" <?php checked( $settings['xmlrpc_disabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'xmlrpc.php sering jadi target brute-force & spam otomatis. Nonaktifkan jika Anda tidak memakai Jetpack atau aplikasi mobile WordPress.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Nonaktifkan Pingback/Trackback', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="pingback_disabled" value="1" <?php checked( $settings['pingback_disabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Sumber spam komentar otomatis yang umum. Aman dinonaktifkan untuk hampir semua situs.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
		</table>

		<hr />
		<h3><?php esc_html_e( 'Proteksi Form Registrasi', 'login-shield-lite' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Aktifkan Proteksi Registrasi', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="register_protection_enabled" value="1" <?php checked( $settings['register_protection_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Honeypot + time-trap pada form pendaftaran user (hanya berlaku jika situs mengizinkan pendaftaran publik).', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Wajibkan Captcha di Registrasi', 'login-shield-lite' ); ?></th>
				<td>
					<label class="lsl-switch">
						<input type="checkbox" name="register_captcha_enabled" value="1" <?php checked( $settings['register_captcha_enabled'] ); ?> />
						<span class="lsl-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'Memakai penyedia captcha yang sama dari tab Captcha.', 'login-shield-lite' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_history_tab() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page = 20;

		$entries = LSL_Login_History::get_entries( $paged, $per_page, $status );
		$total   = LSL_Login_History::count_entries( $status );
		$pages   = max( 1, ceil( $total / $per_page ) );
		?>
		<ul class="subsubsub">
			<li><a href="?page=lsl-settings&tab=history" class="<?php echo '' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Semua', 'login-shield-lite' ); ?></a> |</li>
			<li><a href="?page=lsl-settings&tab=history&status=success" class="<?php echo 'success' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Berhasil', 'login-shield-lite' ); ?></a> |</li>
			<li><a href="?page=lsl-settings&tab=history&status=failed" class="<?php echo 'failed' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Gagal', 'login-shield-lite' ); ?></a> |</li>
			<li><a href="?page=lsl-settings&tab=history&status=blocked" class="<?php echo 'blocked' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Diblokir', 'login-shield-lite' ); ?></a></li>
		</ul>

		<table class="wp-list-table widefat fixed striped lsl-history-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Username', 'login-shield-lite' ); ?></th>
					<th><?php esc_html_e( 'IP Address', 'login-shield-lite' ); ?></th>
					<th><?php esc_html_e( 'Status', 'login-shield-lite' ); ?></th>
					<th><?php esc_html_e( 'User Agent', 'login-shield-lite' ); ?></th>
					<th><?php esc_html_e( 'Waktu', 'login-shield-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $entries ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Belum ada data.', 'login-shield-lite' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $entries as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->username ); ?></td>
							<td><code><?php echo esc_html( $row->ip_address ); ?></code></td>
							<td><span class="lsl-badge lsl-badge-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
							<td class="lsl-ua"><?php echo esc_html( $row->user_agent ); ?></td>
							<td><?php echo esc_html( mysql2date( 'd M Y H:i', $row->created_at ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo;', 'login-shield-lite' ),
						'next_text' => __( '&raquo;', 'login-shield-lite' ),
						'total'     => $pages,
						'current'   => $paged,
					) );
					?>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}
}
