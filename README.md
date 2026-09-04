# Login Shield Lite

Plugin keamanan login WordPress yang **ringan** — hanya fitur inti yang
benar-benar dibutuhkan untuk mengamankan halaman login dan mengurangi spam,
tanpa firewall, scanner, atau fitur berat lain seperti plugin security
serba-bisa pada umumnya. Tampilan pengaturannya memakai komponen native
wp-admin (toggle, tab, tabel) supaya terasa menyatu dengan dashboard
WordPress standar.

## Fitur

- **Ganti URL Login** — ubah `wp-login.php` / `wp-admin` jadi slug custom
  (mis. `/secure-login`). URL lama otomatis dikembalikan 404 (atau redirect
  ke halaman utama, sesuai pilihan).
- **Limit Percobaan Login** — setelah *N* kali gagal login, IP diblokir
  otomatis selama durasi yang bisa diatur (default 1 jam).
- **History Login** — catatan setiap percobaan login (berhasil / gagal /
  diblokir) lengkap dengan IP, username, dan waktu, disimpan di tabel
  database sendiri (bukan menumpuk di `wp_options`).
- **Captcha** — Google reCAPTCHA v2 atau Cloudflare Turnstile, bisa dipasang
  di form login, komentar, dan/atau registrasi.
- **Anti-Spam Komentar** — honeypot + time-trap (aktif default, tanpa API
  eksternal), captcha opsional, batas jumlah link per komentar, dan rate
  limit pengiriman komentar per IP.
- **XML-RPC & Pingback/Trackback** — masing-masing bisa dinonaktifkan
  independen untuk menutup vektor spam & brute-force yang umum.
- **Proteksi Form Registrasi** — honeypot + time-trap + captcha opsional
  untuk situs yang mengizinkan pendaftaran user publik.
- **Update Otomatis via GitHub Releases** — tidak perlu terdaftar di
  WordPress.org untuk mendapat notifikasi versi baru & auto-update
  langsung dari dashboard WordPress.

## Instalasi

### Cara 1 — Download rilis terbaru

1. Buka halaman [Releases](https://github.com/addpur/wp-login-shield-lite/releases)
   repo ini, unduh source code (zip) dari rilis terbaru.
2. Di wp-admin, buka **Plugins → Add New → Upload Plugin**, pilih file zip
   tadi, lalu **Install Now**.
3. Aktifkan plugin, lalu buka **Settings → Login Shield** untuk konfigurasi.

### Cara 2 — Manual via FTP/SSH

1. Extract zip, upload folder `wp-login-shield-lite` ke
   `wp-content/plugins/`.
2. Aktifkan plugin dari menu **Plugins** di wp-admin.
3. Buka **Settings → Login Shield** untuk konfigurasi.

> **Penting:** sebelum mengaktifkan "Ganti URL Login", catat dulu URL baru
> yang akan dipakai (ditampilkan langsung di halaman pengaturan) supaya
> tidak ter-lock dari wp-admin.

## Update Otomatis (Plugin Update Checker)

Plugin ini menyertakan pustaka [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
(MIT license) yang sudah dikonfigurasi mengarah ke repo GitHub ini. Alurnya:

1. Maintainer membuat **tag** baru sesuai nomor versi di header plugin
   (mis. `1.0.1`), lalu membuat **GitHub Release** dari tag tersebut.
2. WordPress yang memasang plugin ini akan otomatis mendeteksi rilis baru
   dan menampilkan notifikasi "Pembaruan tersedia" di halaman Plugins,
   sama seperti plugin dari WordPress.org.
3. Jika opsi **Enable auto-updates** untuk plugin ini diaktifkan (fitur
   bawaan WordPress sejak versi 5.5), update akan terpasang otomatis tanpa
   perlu klik apa pun.

Tidak perlu langkah tambahan di sisi WordPress — cukup pastikan nomor versi
di header `wp-login-shield-lite.php` (`Version:`) dan nama tag Release di
GitHub selalu sinkron.

## Kontribusi

Kontribusi dalam bentuk apa pun sangat diterima:

- **Laporkan bug** lewat [Issues](https://github.com/addpur/wp-login-shield-lite/issues) —
  sertakan versi WordPress/PHP, langkah reproduksi, dan pesan error (kalau
  ada) supaya lebih cepat ditelusuri.
- **Usulkan fitur** juga lewat Issues, beri label `enhancement` kalau bisa.
- **Kirim Pull Request** — fork repo ini, buat branch baru dari `master`,
  jelaskan perubahan yang dibuat di deskripsi PR. Untuk perubahan yang
  cukup besar, disarankan buka Issue dulu untuk didiskusikan sebelum mulai
  coding.
- Ikuti gaya kode yang sudah ada (WordPress Coding Standards, komentar
  dalam Bahasa Indonesia) supaya konsisten dengan bagian lain plugin.

## Saran & Dukungan

Ada saran, pertanyaan penggunaan, atau masukan lain? Silakan buka
[Issues](https://github.com/addpur/wp-login-shield-lite/issues) baru —
diskusi dan masukan dari siapa pun dipersilakan, tidak harus berupa bug
report.

## Lisensi

[GPLv2 atau yang lebih baru](LICENSE) — sama seperti WordPress sendiri.
