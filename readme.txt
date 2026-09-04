=== Login Shield Lite ===
Contributors: adi
Tags: login security, brute force, captcha, comment spam, login url
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin keamanan login WordPress yang ringan: fitur inti yang benar-benar
dibutuhkan, tanpa firewall/scanner/dsb seperti plugin security serba-bisa
pada umumnya.

== Fitur ==

1. Ganti URL login wp-admin ke slug custom (mis. /secure-login).
2. wp-login.php dan wp-admin default dikembalikan 404 (atau redirect ke home)
   jika fitur ganti URL aktif.
3. Limit percobaan login: setelah N kali gagal, IP diblokir otomatis selama
   durasi yang bisa diatur (default 1 jam).
4. History login: catat setiap percobaan (berhasil / gagal / diblokir)
   lengkap dengan IP, username, dan waktu, di tabel database sendiri.
5. Captcha di halaman login: Google reCAPTCHA v2 atau Cloudflare Turnstile.
6. Anti-spam komentar: honeypot + time-trap (aktif default, tanpa API),
   captcha opsional, batas jumlah link per komentar, dan rate limit per IP.
7. Nonaktifkan XML-RPC dan/atau pingback/trackback.
8. Proteksi form registrasi user: honeypot + time-trap + captcha opsional.
9. Update otomatis lewat GitHub Releases (Plugin Update Checker) — tidak
   perlu terdaftar di WordPress.org untuk dapat notifikasi & auto-update.

== Instalasi ==

1. Upload folder `wp-login-shield-lite` ke `/wp-content/plugins/`.
2. Aktifkan plugin dari menu Plugins di wp-admin.
3. Buka Settings > Login Shield untuk konfigurasi.

== PENTING ==

Sebelum mengaktifkan "Ganti URL Login", catat dulu URL baru yang akan
digunakan (ditampilkan di halaman pengaturan). Jika lupa dan sudah ter-logout,
Anda bisa menonaktifkan fitur ini lewat phpMyAdmin dengan mengubah value
`login_url_enabled` menjadi `false` pada option `lsl_settings` (serialized
array) di tabel `wp_options`, atau menonaktifkan plugin lewat FTP (rename
folder plugin).

== Changelog ==

= 1.0.0 =
* Rilis publik pertama. Mencakup: ganti URL login, blokir wp-admin/wp-login
  default, limit percobaan login + blokir IP, history login, captcha
  (reCAPTCHA/Turnstile) untuk login/komentar/registrasi, anti-spam komentar
  (honeypot + time-trap + limit link + rate limit per IP), opsi nonaktifkan
  XML-RPC & pingback/trackback, proteksi form registrasi, dan dukungan
  update otomatis via GitHub Releases.
