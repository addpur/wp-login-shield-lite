# Changelog

Semua perubahan penting pada plugin ini dicatat di file ini.

Format mengacu pada [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
dan penomoran versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).

# Changelog

Semua perubahan penting pada plugin ini dicatat di file ini.

Format mengacu pada [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
dan penomoran versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).

## [1.0.1] - 2026-09-13

### Diperbaiki

- **Fatal error saat mengakses URL login custom** (mis. `/admin-login`) pada
  beberapa kombinasi tema/plugin. Penyebabnya, `wp-login.php` di-*require*
  terlalu dini (hook `plugins_loaded`), sebelum tema dan sejumlah API
  WordPress selesai disiapkan. Penyajian form login sekarang ditunda ke
  hook `wp_loaded` — pola yang sama dipakai plugin "hide login" populer
  lain yang sudah teruji bertahun-tahun di jutaan situs.

## [1.0.0] - 2026-09-04

Rilis publik pertama.

### Ditambahkan

- **Ganti URL Login** — ubah `wp-login.php` / `wp-admin` jadi slug custom.
  URL lama otomatis dikembalikan 404 atau redirect ke halaman utama
  (pilihan bisa diatur).
- **Limit Percobaan Login** — blokir IP otomatis selama durasi tertentu
  (default 1 jam) setelah sejumlah percobaan login gagal (default 5 kali).
- **History Login** — pencatatan setiap percobaan login (berhasil / gagal /
  diblokir) beserta IP, username, dan waktu, di tabel database sendiri.
- **Captcha** — dukungan Google reCAPTCHA v2 dan Cloudflare Turnstile,
  bisa dipasang di form login, komentar, dan/atau registrasi.
- **Anti-Spam Komentar** — honeypot + time-trap aktif secara default
  (tanpa API eksternal), ditambah opsi captcha, batas jumlah link per
  komentar, dan rate limit pengiriman komentar per IP.
- **Nonaktifkan XML-RPC** — opsi untuk mematikan `xmlrpc.php` sepenuhnya.
- **Nonaktifkan Pingback/Trackback** — menutup jalur spam komentar otomatis
  yang umum, termasuk menghapus header `X-Pingback` dan method XML-RPC
  terkait.
- **Proteksi Form Registrasi** — honeypot + time-trap + captcha opsional
  untuk situs yang mengizinkan pendaftaran user publik.
- **Update Otomatis via GitHub Releases** — terintegrasi dengan
  [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
  sehingga WordPress bisa mendeteksi rilis baru dan menawarkan update tanpa
  perlu plugin ini terdaftar di WordPress.org.
- Halaman pengaturan native wp-admin (`Settings → Login Shield`) dengan
  4 tab: URL Login, Limit Login, Captcha, Anti-Spam, dan History Login.

[1.0.1]: https://github.com/addpur/wp-login-shield-lite/releases/tag/1.0.1
[1.0.0]: https://github.com/addpur/wp-login-shield-lite/releases/tag/1.0.0
