<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menyediakan widget captcha (Google reCAPTCHA v2 atau Cloudflare Turnstile)
 * dan verifikasinya. Dipakai di form login (selalu, jika provider dikonfigurasi),
 * dan opsional di form komentar / registrasi (dipanggil oleh LSL_Anti_Spam).
 */
class LSL_Captcha {

	private $settings;
	private $provider;

	public function __construct() {
		$this->settings = LSL_Settings::get_all();
		$this->provider = $this->settings['captcha_provider'];

		if ( ! $this->is_configured() ) {
			return;
		}

		// Form login — selalu aktif kalau provider sudah dikonfigurasi.
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_action( 'login_form', array( $this, 'render_widget' ) );
		add_filter( 'authenticate', array( $this, 'verify_login' ), 1, 1 );

		// Form komentar — hanya jika di-toggle terpisah di tab Anti-Spam.
		if ( ! empty( $this->settings['comment_captcha_enabled'] ) ) {
			add_action( 'comment_form_before', array( $this, 'enqueue_script' ) );
			add_action( 'comment_form_after_fields', array( $this, 'render_widget' ) );
			add_action( 'comment_form_logged_in_after', array( $this, 'render_widget' ) );
		}

		// Form registrasi user — hanya jika di-toggle terpisah.
		if ( ! empty( $this->settings['register_captcha_enabled'] ) ) {
			add_action( 'login_enqueue_scripts', array( $this, 'enqueue_script' ) ); // wp-login.php?action=register memakai hook yang sama.
			add_action( 'register_form', array( $this, 'render_widget' ) );
		}
	}

	/**
	 * Apakah provider terpilih sudah punya site key (siap dipakai)?
	 */
	public function is_configured() {
		if ( 'recaptcha' === $this->provider ) {
			return ! empty( $this->settings['recaptcha_site_key'] );
		}
		if ( 'turnstile' === $this->provider ) {
			return ! empty( $this->settings['turnstile_site_key'] );
		}
		return false;
	}

	public function enqueue_script() {
		if ( 'recaptcha' === $this->provider ) {
			wp_enqueue_script( 'lsl-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
		} elseif ( 'turnstile' === $this->provider ) {
			wp_enqueue_script( 'lsl-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}
		wp_add_inline_style( 'login', '.lsl-captcha-wrap{margin:16px 0;}' );
	}

	public function render_widget() {
		echo '<div class="lsl-captcha-wrap">';
		if ( 'recaptcha' === $this->provider ) {
			printf(
				'<div class="g-recaptcha" data-sitekey="%s"></div>',
				esc_attr( $this->settings['recaptcha_site_key'] )
			);
		} elseif ( 'turnstile' === $this->provider ) {
			printf(
				'<div class="cf-turnstile" data-sitekey="%s"></div>',
				esc_attr( $this->settings['turnstile_site_key'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Verifikasi khusus alur login: dipasang di filter 'authenticate'
	 * prioritas 1, jadi dicek sebelum WordPress memproses kredensial.
	 */
	public function verify_login( $user ) {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return $user;
		}

		if ( ! $this->verify_response() ) {
			return new WP_Error(
				'lsl_captcha_failed',
				__( '<strong>Error:</strong> Verifikasi captcha gagal, silakan coba lagi.', 'login-shield-lite' )
			);
		}

		return $user;
	}

	/**
	 * Verifikasi token captcha dari request saat ini. Dipakai langsung oleh
	 * modul lain (LSL_Anti_Spam) untuk form komentar & registrasi.
	 */
	public function verify_response() {
		if ( 'recaptcha' === $this->provider ) {
			$token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
			return $this->verify_with_provider(
				'https://www.google.com/recaptcha/api/siteverify',
				$this->settings['recaptcha_secret'],
				$token
			);
		}

		if ( 'turnstile' === $this->provider ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			return $this->verify_with_provider(
				'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				$this->settings['turnstile_secret'],
				$token
			);
		}

		return false;
	}

	private function verify_with_provider( $endpoint, $secret, $response_token ) {
		if ( empty( $secret ) || empty( $response_token ) ) {
			return false;
		}

		$ip = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
				break;
			}
		}

		$result = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $response_token,
					'remoteip' => $ip,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $result ), true );
		return ! empty( $body['success'] );
	}
}
