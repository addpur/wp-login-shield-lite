<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kumpulan proteksi anti-spam ringan:
 * - Komentar: honeypot + time-trap + captcha (opsional) + limit link + rate limit per IP.
 * - XML-RPC: bisa dinonaktifkan total.
 * - Pingback/trackback: bisa dinonaktifkan.
 * - Registrasi user: honeypot + time-trap + captcha (opsional).
 *
 * Honeypot & time-trap tidak butuh API eksternal, jadi tetap ringan dan
 * aktif secara default tanpa konfigurasi tambahan.
 */
class LSL_Anti_Spam {

	private $settings;
	private $captcha;

	public function __construct( LSL_Captcha $captcha ) {
		$this->settings = LSL_Settings::get_all();
		$this->captcha  = $captcha;

		// --- Komentar ---
		if ( ! empty( $this->settings['comment_spam_enabled'] ) ) {
			add_action( 'comment_form_after_fields', array( $this, 'render_comment_traps' ) );
			add_action( 'comment_form_logged_in_after', array( $this, 'render_comment_traps' ) );
			add_filter( 'preprocess_comment', array( $this, 'check_comment_traps' ) );
			add_filter( 'pre_comment_approved', array( $this, 'maybe_mark_comment_spam' ), 10, 2 );
		}

		// --- XML-RPC ---
		if ( ! empty( $this->settings['xmlrpc_disabled'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		// --- Pingback / trackback ---
		if ( ! empty( $this->settings['pingback_disabled'] ) ) {
			add_filter( 'pings_open', '__return_false' );
			add_action( 'send_headers', array( $this, 'remove_pingback_header' ) );
			add_filter( 'xmlrpc_methods', array( $this, 'remove_pingback_xmlrpc_methods' ) );
			add_filter( 'preprocess_comment', array( $this, 'block_pingback_comment' ) );
		}

		// --- Registrasi user ---
		if ( ! empty( $this->settings['register_protection_enabled'] ) ) {
			add_action( 'register_form', array( $this, 'render_register_traps' ) );
			add_filter( 'registration_errors', array( $this, 'check_register_traps' ) );
		}
	}

	/* ==================== Helper umum: honeypot + time-trap ==================== */

	/**
	 * Nama field honeypot dibuat agak acak per situs (bukan per request)
	 * supaya bot generik yang hafal nama field umum ('website', 'url',
	 * 'email2', dst) tetap kejebak, tapi tetap konsisten selama tidak ganti
	 * salt WordPress.
	 */
	private function honeypot_field_name() {
		return 'lsl_hp_' . substr( md5( wp_salt( 'nonce' ) ), 0, 8 );
	}

	private function render_honeypot_and_timestamp() {
		$field = $this->honeypot_field_name();
		$time  = time();
		$token = hash_hmac( 'sha256', (string) $time, wp_salt( 'auth' ) );
		?>
		<p style="position:absolute !important; left:-9999px !important; top:-9999px !important; margin:0 !important; padding:0 !important;" aria-hidden="true">
			<label for="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'Biarkan kolom ini kosong', 'login-shield-lite' ); ?></label>
			<input type="text" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" value="" autocomplete="off" tabindex="-1" />
		</p>
		<input type="hidden" name="lsl_ts" value="<?php echo esc_attr( $time ); ?>" />
		<input type="hidden" name="lsl_ts_hmac" value="<?php echo esc_attr( $token ); ?>" />
		<?php
	}

	/**
	 * True jika request lolos cek honeypot & time-trap (kemungkinan besar
	 * manusia), false jika terindikasi bot.
	 */
	private function passes_honeypot_and_timestamp() {
		$field = $this->honeypot_field_name();

		if ( ! empty( $_POST[ $field ] ) ) {
			return false; // Honeypot terisi -> bot.
		}

		$time = isset( $_POST['lsl_ts'] ) ? (int) $_POST['lsl_ts'] : 0;
		$hmac = isset( $_POST['lsl_ts_hmac'] ) ? sanitize_text_field( wp_unslash( $_POST['lsl_ts_hmac'] ) ) : '';

		if ( ! $time || ! $hmac ) {
			return false; // Field wajib hilang -> mencurigakan (bukan lewat form asli).
		}

		$expected = hash_hmac( 'sha256', (string) $time, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $hmac ) ) {
			return false; // Token dipalsukan / tidak cocok.
		}

		$elapsed     = time() - $time;
		$min_seconds = max( 1, (int) $this->settings['comment_time_trap_seconds'] );

		if ( $elapsed < $min_seconds ) {
			return false; // Dikirim terlalu cepat -> kemungkinan besar bot.
		}

		return true;
	}

	private function get_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$candidate = trim( explode( ',', $_SERVER[ $key ] )[0] );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}
		return '0.0.0.0';
	}

	/* ==================== Komentar ==================== */

	public function render_comment_traps() {
		$this->render_honeypot_and_timestamp();
	}

	public function check_comment_traps( $commentdata ) {
		// Pingback/trackback ditangani terpisah oleh block_pingback_comment().
		if ( isset( $commentdata['comment_type'] ) && in_array( $commentdata['comment_type'], array( 'pingback', 'trackback' ), true ) ) {
			return $commentdata;
		}

		// User yang bisa moderasi komentar (admin/editor) tidak perlu divalidasi.
		if ( current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}

		if ( ! $this->passes_honeypot_and_timestamp() ) {
			$this->reject_comment( __( 'Komentar Anda terdeteksi sebagai spam.', 'login-shield-lite' ) );
		}

		if ( ! empty( $this->settings['comment_captcha_enabled'] ) && $this->captcha->is_configured() ) {
			if ( ! $this->captcha->verify_response() ) {
				$this->reject_comment( __( 'Verifikasi captcha gagal, silakan coba lagi.', 'login-shield-lite' ) );
			}
		}

		if ( ! empty( $this->settings['comment_rate_limit_enabled'] ) ) {
			$this->enforce_comment_rate_limit();
		}

		return $commentdata;
	}

	private function reject_comment( $message ) {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Komentar Ditolak', 'login-shield-lite' ),
			array( 'response' => 403, 'back_link' => true )
		);
	}

	private function enforce_comment_rate_limit() {
		$ip     = $this->get_ip();
		$key    = 'lsl_cmt_rate_' . md5( $ip );
		$max    = max( 1, (int) $this->settings['comment_rate_limit_max'] );
		$window = max( 1, (int) $this->settings['comment_rate_limit_window'] ) * MINUTE_IN_SECONDS;

		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			wp_die(
				esc_html__( 'Anda mengirim komentar terlalu sering. Silakan coba lagi nanti.', 'login-shield-lite' ),
				esc_html__( 'Komentar Ditolak', 'login-shield-lite' ),
				array( 'response' => 429, 'back_link' => true )
			);
		}
		set_transient( $key, $count + 1, $window );
	}

	/**
	 * Tandai spam otomatis jika jumlah link melebihi batas — tidak
	 * langsung ditolak (supaya tidak ada false-positive yang hilang
	 * permanen), cukup masuk folder Spam di Comments untuk ditinjau.
	 */
	public function maybe_mark_comment_spam( $approved, $commentdata ) {
		if ( 'spam' === $approved || false === $approved ) {
			return $approved;
		}

		$max_links = (int) $this->settings['comment_max_links'];
		if ( $max_links <= 0 ) {
			return $approved;
		}

		$content    = isset( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : '';
		$link_count = preg_match_all( '#https?://#i', $content );

		if ( $link_count > $max_links ) {
			return 'spam';
		}

		return $approved;
	}

	/* ==================== Pingback / trackback ==================== */

	public function remove_pingback_header() {
		header_remove( 'X-Pingback' );
	}

	public function remove_pingback_xmlrpc_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Lapisan pengaman tambahan: filter 'pings_open' sudah menutup jalur
	 * resmi (wp-trackback.php & XML-RPC pingback.ping), ini untuk berjaga
	 * kalau ada jalur lain yang tetap membuat comment_type pingback/trackback.
	 */
	public function block_pingback_comment( $commentdata ) {
		if ( isset( $commentdata['comment_type'] ) && in_array( $commentdata['comment_type'], array( 'pingback', 'trackback' ), true ) ) {
			wp_die( esc_html__( 'Pingback/trackback dinonaktifkan di situs ini.', 'login-shield-lite' ), '', array( 'response' => 403 ) );
		}
		return $commentdata;
	}

	/* ==================== Registrasi user ==================== */

	public function render_register_traps() {
		$this->render_honeypot_and_timestamp();
	}

	public function check_register_traps( $errors ) {
		if ( ! $this->passes_honeypot_and_timestamp() ) {
			$errors->add( 'lsl_spam_detected', __( '<strong>Error:</strong> Pendaftaran ditolak (terdeteksi sebagai spam).', 'login-shield-lite' ) );
			return $errors;
		}

		if ( ! empty( $this->settings['register_captcha_enabled'] ) && $this->captcha->is_configured() ) {
			if ( ! $this->captcha->verify_response() ) {
				$errors->add( 'lsl_captcha_failed', __( '<strong>Error:</strong> Verifikasi captcha gagal, silakan coba lagi.', 'login-shield-lite' ) );
			}
		}

		return $errors;
	}
}
