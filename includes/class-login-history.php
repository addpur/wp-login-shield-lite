<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mencatat setiap percobaan login (berhasil / gagal / diblokir) ke tabel
 * custom, dan menyediakan method untuk mengambil datanya.
 */
class LSL_Login_History {

	private $settings;

	public function __construct() {
		$this->settings = LSL_Settings::get_all();

		if ( empty( $this->settings['history_enabled'] ) ) {
			return;
		}

		add_action( 'wp_login', array( $this, 'log_success' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'log_failed' ) );
		add_action( 'lsl_ip_blocked', array( $this, 'log_blocked' ), 10, 3 );
		add_action( 'lsl_daily_cleanup', array( $this, 'cleanup_old_records' ) );

		if ( ! wp_next_scheduled( 'lsl_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'lsl_daily_cleanup' );
		}
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'lsl_login_log';
	}

	public static function create_table() {
		global $wpdb;
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			username VARCHAR(191) NOT NULL,
			ip_address VARCHAR(100) NOT NULL,
			status VARCHAR(20) NOT NULL,
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY ip_address (ip_address),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
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

	private function insert( $username, $status ) {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'username'   => sanitize_text_field( $username ),
				'ip_address' => $this->get_ip(),
				'status'     => $status,
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'], 0, 255 ) ) : '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function log_success( $user_login, $user ) {
		$this->insert( $user_login, 'success' );
	}

	public function log_failed( $username ) {
		$this->insert( $username, 'failed' );
	}

	public function log_blocked( $ip, $username, $lockout_time ) {
		$this->insert( $username, 'blocked' );
	}

	public function cleanup_old_records() {
		$days = (int) $this->settings['history_retention'];
		if ( $days <= 0 ) {
			return; // 0 = simpan selamanya.
		}
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) );
	}

	/**
	 * Ambil data history dengan paginasi + filter status sederhana.
	 */
	public static function get_entries( $page = 1, $per_page = 20, $status = '' ) {
		global $wpdb;
		$table  = self::table_name();
		$offset = max( 0, ( $page - 1 ) * $per_page );

		$where  = '';
		$params = array();
		if ( $status ) {
			$where    = 'WHERE status = %s';
			$params[] = $status;
		}

		$params[] = $per_page;
		$params[] = $offset;

		$sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function count_entries( $status = '' ) {
		global $wpdb;
		$table = self::table_name();
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
