<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Membatasi jumlah percobaan login yang gagal. Jika melebihi batas,
 * IP tersebut diblokir selama durasi tertentu (default 1 jam).
 */
class LSL_Login_Limiter {

	private $settings;

	public function __construct() {
		$this->settings = LSL_Settings::get_all();

		if ( empty( $this->settings['limiter_enabled'] ) ) {
			return;
		}

		// Cek blokir paling awal dalam proses autentikasi.
		add_filter( 'authenticate', array( $this, 'check_lockout' ), 5, 1 );
		add_action( 'wp_login_failed', array( $this, 'record_failed_attempt' ) );
		add_action( 'wp_login', array( $this, 'clear_attempts_on_success' ), 10, 1 );
		add_filter( 'login_message', array( $this, 'maybe_show_lockout_message' ) );
	}

	private function get_ip() {
		$ip = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$candidate = trim( explode( ',', $_SERVER[ $key ] )[0] );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					$ip = $candidate;
					break;
				}
			}
		}
		return $ip ? $ip : '0.0.0.0';
	}

	private function attempts_key( $ip ) {
		return 'lsl_attempts_' . md5( $ip );
	}

	private function lockout_key( $ip ) {
		return 'lsl_lockout_' . md5( $ip );
	}

	public function is_locked_out( $ip = null ) {
		$ip = $ip ? $ip : $this->get_ip();
		return (bool) get_transient( $this->lockout_key( $ip ) );
	}

	public function get_lockout_remaining( $ip = null ) {
		$ip        = $ip ? $ip : $this->get_ip();
		$timeout   = get_option( '_transient_timeout_' . $this->lockout_key( $ip ) );
		if ( ! $timeout ) {
			return 0;
		}
		return max( 0, $timeout - time() );
	}

	/**
	 * Dipanggil di awal 'authenticate' — jika IP sedang diblokir, langsung
	 * tolak tanpa memproses username/password sama sekali.
	 */
	public function check_lockout( $user ) {
		$ip = $this->get_ip();
		if ( $this->is_locked_out( $ip ) ) {
			$minutes = ceil( $this->get_lockout_remaining( $ip ) / 60 );
			return new WP_Error(
				'lsl_locked_out',
				sprintf(
					/* translators: %d: sisa menit */
					__( '<strong>Error:</strong> Terlalu banyak percobaan login gagal. Silakan coba lagi dalam %d menit.', 'login-shield-lite' ),
					max( 1, $minutes )
				)
			);
		}
		return $user;
	}

	public function record_failed_attempt( $username ) {
		$ip           = $this->get_ip();
		$key          = $this->attempts_key( $ip );
		$window       = max( 1, (int) $this->settings['attempt_window_min'] ) * MINUTE_IN_SECONDS;
		$max_attempts = max( 1, (int) $this->settings['max_attempts'] );
		$lockout_time = max( 1, (int) $this->settings['lockout_minutes'] ) * MINUTE_IN_SECONDS;

		$count = (int) get_transient( $key );
		$count++;

		if ( $count >= $max_attempts ) {
			set_transient( $this->lockout_key( $ip ), 1, $lockout_time );
			delete_transient( $key );

			/**
			 * Hook agar modul lain (mis. history) tahu IP baru saja diblokir.
			 */
			do_action( 'lsl_ip_blocked', $ip, $username, $lockout_time );
		} else {
			set_transient( $key, $count, $window );
		}

		do_action( 'lsl_login_failed_attempt', $ip, $username, $count, $max_attempts );
	}

	public function clear_attempts_on_success( $user_login ) {
		$ip = $this->get_ip();
		delete_transient( $this->attempts_key( $ip ) );
		delete_transient( $this->lockout_key( $ip ) );
	}

	public function maybe_show_lockout_message( $message ) {
		if ( $this->is_locked_out() ) {
			$minutes = ceil( $this->get_lockout_remaining() / 60 );
			$message .= '<p class="message" style="border-left:4px solid #d63638;">' .
				sprintf(
					esc_html__( 'IP Anda sementara diblokir. Coba lagi dalam %d menit.', 'login-shield-lite' ),
					max( 1, $minutes )
				) . '</p>';
		}
		return $message;
	}
}
