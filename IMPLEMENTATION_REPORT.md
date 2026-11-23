# Laporan Implementasi — Redesign Customer App & Midtrans Core API

**Tanggal:** 20 November 2026  
**Status:** Selesai (verifikasi akhir hijau)  
**Stack:** PHP 8.2.12, Laravel 12.73.0, `laravel/socialite` 5.33.0, `midtrans/midtrans-php` 2.6.2, MySQL.

---

## 1. Ringkasan

Aplikasi customer telah diredesain dengan identitas editorial premium tanpa mengubah file template asli `template/tema1.html` dan `template/tema2.html`. Alur self-service, pratinjau 10 jam, Google OAuth, aktivasi berbasis pembayaran terverifikasi, dan undangan publik tetap dipertahankan sesuai PRD.

Integrasi pembayaran telah dialihkan dari Snap redirect ke **Midtrans Core API** menggunakan `CoreApi::charge()`. Instruksi pembayaran kini disimpan dan ditampilkan secara eksplisit di halaman status. Aktivasi undangan hanya terjadi setelah webhook Midtrans tervalidasi atau pengecekan status server-side; redirect dari halaman pembayaran tidak pernah mengaktifkan undangan.

Status literal PRD dipertahankan persis: `Super Admin`, `Customer`, `DRAFT`, `PREVIEW`, `EXPIRED`, `PENDING_PAYMENT`, `ACTIVE`, `SUSPENDED`.

---

## 2. Arah desain

Identitas visual baru dibangun sebagai aset first-party di `public/assets/site/` dan tidak bergantung pada Vite build.

- **Tipografi:** Jost untuk antarmuka, Cormorant Garamond untuk headlining editorial.
- **Palet:** kertas hangat `#FDFBF8`, tinta `#2A2520`, garis `#E9DFD2`, emas `#B5935A`, clay `#A2783A`.
- **Bentuk:** kartu soft-shadow, radius besar/pill, tipografi besar, hierarki yang tenang, animasi reveal dan transisi halus.
- **Ilustrasi:** SVG buatan sendiri untuk hero dan katalog (`public/assets/site/img/`), bukan salinan identitas reference.
- **Aksesibilitas:** skip-link, fokus terlihat, navigasi mobile dengan `aria-expanded`, toast dengan `role="status"`, dialog dengan fokus trap dan Escape.

Halaman yang diredesain: beranda, katalog, detail/pratinjau template, login Google, dashboard customer, editor, aktivasi, status pembayaran, riwayat transaksi, serta shell bersama header/footer.

---

## 3. Perjalanan customer

1. **Jelajah & pratinjau.** Pengunjung dapat membuka `/`, `/templates`, `/templates/{id}/sample`, dan `/templates/{id}/preview` tanpa login. Rute resmi adalah `/templates/{id}/preview`; `/templates/preview/{id}` sengaja tidak tersedia.
2. **Login.** `/login` menyediakan email + kata sandi untuk akun yang sudah disediakan, dengan Google OAuth sebagai alternatif. Tidak ada registrasi publik. `SUPER_ADMIN_EMAIL` dan `SUPER_ADMIN_PASSWORD` dipakai seeder untuk membuat akun `Super Admin`; peran tidak bisa dipilih pengguna.
3. **Pilih template.** Setelah login, `POST /templates/{template}/select` membuat undangan baru. Template juga dapat diganti per undangan lewat `/invitations/{invitation}/templates` selama undangan belum aktif.
4. **Editor.** `/invitations/{invitation}/edit` adalah editor berbasis form tanpa panel preview berdampingan. Sembilan bagian tersusun dalam navigasi lengket: Data Mempelai, Informasi Acara, Cerita Pasangan, Turut Mengundang, Foto dan Galeri, Musik, Pengaturan Tampilan, Pengaturan URL, dan Pengaturan WhatsApp. Simpan berjalan via AJAX dengan status loading, validasi per field, dan peringatan perubahan yang belum disimpan. Bagian dapat dibuka langsung lewat hash (`#url`, `#whatsapp`).
5. **Pratinjau.** Tombol terpisah membuka `/invitations/{invitation}/preview`, yang merender HTML undangan dari data tersimpan tanpa mengganggu form.
6. **Aktivasi.** `/invitations/{invitation}/activate` menampilkan pilihan metode dan harga. `POST .../payment` membuat charge Core API.
7. **Status.** `/invitations/{invitation}/payment` menampilkan instruksi pembayaran, order ID, nominal, kedaluwarsa, status transaksi, dan status undangan. Tersedia `Periksa Ulang Status`.
8. **Aktif & bagikan.** Undangan aktif otomatis hanya setelah webhook terverifikasi. Tautan publik `/u/{slug}` dapat dibagikan langsung dengan `?to=Nama+Tamu`, atau disiapkan lewat halaman `/share?uri={PUBLIC_INVITATION_URL}`.

Dashboard customer merangkum total undangan, aktif, draft/preview, kedaluwarsa, dan menunggu pembayaran. Setiap kartu undangan menampilkan status, masa berlaku, serta aksi kontekstual: `Kelola Undangan`, `Pilih Template`/`Lihat Template`, `Preview`, `Aktifkan`, `Pengaturan URL`, `Riwayat Pembayaran`, dan untuk undangan `ACTIVE` juga `Bagikan`, `Lihat Undangan`, serta salin tautan publik. Riwayat transaksi tersedia di `/transactions`. Customer tidak dapat mengakses undangan milik customer lain; policy dan controller memaksa kepemilikan.

---

## 4. Template, editor, dan snapshot

File template WordPress/Elementor asli tidak dimodifikasi. Integrasi dilakukan dengan substitusi teks/atrak tertarget dan injeksi runtime first-party.

- **Snapshot:** `TemplateSnapshotService` menyalin HTML persis plus hash SHA-256, versi, renderer, `features`, dan `config` saat undangan dibuat. Undangan lama tetap memakai salinan/bekuannya walaupun admin mengubah template kemudian.
- **Feature flags:** `story`, `gallery`, `music`, `theme`, `rsvp`, `families`, `gift`, `live_stream`, `countdown` mengontrol menu editor dan bagian yang dirender.
- **Renderer:** lima renderer terdaftar di `Template::RENDERERS` / `Template::rendererOptions()`: `tema1` (Thema 38) memakai countdown Unix `.elementor-countdown-wrapper[data-date]`; `tema2` (Thema 40), `tema3` (Thema 39), dan `tema4` (Thema 42) memakai countdown teks `.wpkoi-elements-countdown-items[data-date]`; `tema5` (Thema 41) memakai countdown Unix Elementor bersama label tamu `DEAR TO`. `tema3`/`tema4` menyuntik widget amplop sintetis karena template aslinya tidak memilikinya.
- **Aset:** semua referensi URL hotlink dialihkan ke `public/assets/templates/`. Chunk runtime Elementor/Elementor Pro yang direferensikan runtime juga dicerminkan lokal: 46 bundle Pro + 17 bundle core per tema, semuanya non-empty.
- **WP boilerplate:** meta/JSON-LD Yoast, form AJAX WordPress, lightbox Elementor, audio, countdown, copy-address, dan script terkait diimplementasikan ulang atau dinetralisir tanpa mengubah tampilan inti.

### 4.1 Editor form-only

Editor menyimpan data pasangan, acara, cerita, keluarga, galeri, rekening hadiah, tema, musik, RSVP, dan kontak WhatsApp. Tidak ada iframe pratinjau di dalam form; pratinjau dibuka di tab terpisah. Setiap bagian memiliki penjelasan singkat, penanda fitur yang tidak didukung template, serta kontrol unggah dengan pratinjau, tombol ganti/hapus, dan validasi tipe/ukuran. Baris acara/cerita/keluarga/galeri kosong tidak dirender, sehingga simpan tidak membuat data bayangan.

Penyimpanan memakai `UpdateInvitationRequest` + `InvitationEditorService`: unggahan foto sampul, foto mempelai, latar per slot template, dan galeri multi-upload; optimasi GD; penyimpanan pada path aman; penghapusan file lama; serta pengecekan kepemilikan. Respons JSON menyertakan `public_url`, `share_url`, dan `slug` terbaru agar UI langsung sinkron.

### 4.2 Pemilihan dan penggantian template

`/invitations/{invitation}/templates` menampilkan template aktif dan tombol guna per template, termasuk untuk undangan yang sudah aktif. Penggantian memakai `TemplateSwitchService` yang menyalin snapshot baru dan mempertahankan slug, isi, ucapan, RSVP, status publikasi, serta harga yang sudah dibayar. `templateIsLocked()` kini hanya berarti snapshot dibekukan dari suntingan admin, bukan larangan customer mengganti template.

- Sebelum dan sesudah aktivasi, customer pemilik undangan dapat mengganti template berulang kali.
- Penggantian dicatat di `activity_logs` dengan nama dan versi template lama serta baru.
- `switchTemplate` tetap ditolak (HTTP 403) untuk user lain, Super Admin yang bukan pemilik, serta status `EXPIRED`/`SUSPENDED`.
- Halaman template dan editor aktif menjelaskan bahwa tautan publik, isi, ucapan, dan RSVP tidak berubah saat mengganti template.

### 4.3 Slug dan URL

`InvitationSlugService` menormalkan slug, memeriksa format, daftar rute terlarang (`config/invitation.php`), bentrok dengan undangan lain (termasuk soft-deleted), dan bentrok dengan slug lama. Endpoint `GET /invitations/{invitation}/slug/check` memberi umpan balik ketersediaan di editor.

Slug publik kini dibuat dari nama panggilan pria dan wanita, misalnya `/u/anhar-dan-ika`. Jika nama panggilan kosong, nama lengkap dipakai sebagai fallback. Bentrok mendapat akhiran `-2`, `-3`, dan seterusnya. Slug lama yang masih berupa kombinasi nama template plus token acak otomatis disamakan lewat `php artisan invitations:sync-nickname-slugs`; slug kustom tidak ditimpa kecuali perintah dijalankan dengan `--force`.

Saat slug berubah, `invitation_slug_redirects` menyimpan slug lama; `/u/{old-slug}` mengembalikan 301 ke slug baru sambil mempertahankan query string (`?to=`). Sinkronisasi slug berjalan saat editor menyimpan data pasangan tanpa slug eksplisit, dan tetap tersedia untuk undangan aktif. Editor menampilkan URL penuh dan tombol salin.

### 4.4 WhatsApp

Nomor kontak undangan berbeda dari tujuan berbagi. `WhatsAppService` menormalkan `08xx`, `+62`, `62`, dan format berspasi menjadi digit `62...`, lalu memvalidasi panjang. Nomor tidak valid ditolak dengan pesan Indonesia. Nomor, nama tampilan, dan pesan awal disimpan di `invitation_settings`, dan tombol kontak `wa.me/{nomor}?text=...` hanya disuntikkan ke undangan publik bila nomor diisi.

### 4.5 Halaman bagikan

`/share?uri={PUBLIC_INVITATION_URL}&to=Nama+Tamu` adalah halaman bantu publik, bukan pengalihan. Halaman menampilkan tautan undangan, kolom nama tamu, pesan yang dihasilkan (dapat diedit dan dipulihkan ke default), tombol salin, tautan WhatsApp `wa.me/?text=...` tanpa nomor tujuan, serta Web Share API dengan fallback salin. Pesan default berasal dari `config/invitation.php` dan `ShareMessageService`.

### 4.6 Migrasi pengelolaan

Migrasi `2026_01_01_000012_add_invitation_management_fields.php` menambahkan `cover_photo`, `template_locked_at`, kolom WhatsApp pada `invitation_settings`, dan tabel `invitation_slug_redirects`.

---

## 5. Undangan publik

Halaman publik hanya melayani undangan `ACTIVE`; status lain menghasilkan 404. Undangan nyata tidak pernah menampilkan data contoh: `InvitationDataService::forInvitation()` hanya memakai data customer, bagian opsional mati kecuali diaktifkan di Pengaturan Tampilan, dan bagian kosong disembunyikan. Latar template yang tidak diunggah dikosongkan alih-alih memakai slideshow contoh, dan tombol WhatsApp hanya muncul bila nomor kontak diisi. Respons memiliki:

- `lang="id"`, satu viewport, `<meta name="theme-color">`, dan metadata deskripsi.
- `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Cache-Control: private, no-store` (Symfony menormalkan menjadi `no-store, private`).
- `window.UD_CONFIG` berisi endpoint, fitur, nama tamu, audio, countdown, `backgrounds`, `whatsappContact`, `hiddenIds`, dan `hiddenWidgets` untuk menyembunyikan widget kosong.
- Form guestbook dengan label, CSRF token, honeypot `.ud-honeypot`, validasi, dan throttle 20 permintaan/menit.
- Flash `public_status` setelah kirim ucapan/RSVP, redirect dengan `?to=` dan anchor `#post-guestbook-box`.
- Tombol bagikan WhatsApp, Facebook, Telegram, serta salin tautan dengan fallback toast; tidak ada `window.alert()`.
- Galeri lightbox dengan `role="dialog"`, `aria-modal`, Escape, tombol tutup, dan pemulihan fokus ke pemicu.

---

## 6. Masa pratinjau 10 jam

`InvitationCreationService::create()` menetapkan `preview_started_at` dan `preview_expires_at = now() + PREVIEW_HOURS` (default 10 jam) **waktu server** saat draft dibuat. Edit konten tidak menyetel ulang jam ini.

Pembersihan tersedia tanpa daemon wajib:

- `SweepExpiredInvitations` middleware menjalankan sweep dengan cache lock saat permintaan authenticated; aman untuk shared hosting.
- `php artisan invitations:expire-previews --prune-days=30` untuk cron/VPS opsional.
- `InvitationExpiryService` tidak menimpa `ACTIVE`, `PENDING_PAYMENT`, atau `SUSPENDED`, dan tidak menimpa pembayaran sukses.
- Opsi `--prune-days` menghapus draft `EXPIRED` lama beserta direktori upload.

---

## 7. Pembayaran Midtrans Core API

Implementasi utama: `app/Services/Payment/MidtransGateway.php`, `PaymentService.php`, `PaymentRequestData`, `PaymentResponseData`, `PaymentInstructionData`, dan `payment/notification`.

- Hanya `CoreApi::charge()` yang dipakai untuk membuat pembayaran. Snap token/URL tetap disimpan di kolom lama hanya untuk kompatibilitas data/test dan tidak dipakai untuk aktivasi.
- Metode: `qris`, `bca_va`, `bni_va`, `bri_va`, `permata_va`, `echannel`, `gopay`, `cstore`; default `qris`.
- Order ID dibuat server-side: `UD-{invitationId}-{random12}` dan dipakai sebagai idempotency key.
- Pending charge yang masih berlaku dipakai ulang, sehingga klik berulang tidak membuat transaksi ganda.
- Instruksi nyata (label, referensi/VA/nomor, langkah, kedaluwarsa) disimpan di kolom Core API baru dan dirender di `resources/views/payments/status.blade.php`.
- Halaman status menampilkan QRIS/label metode, langkah pembayaran, order ID, nominal, status transaksi, status undangan, dan waktu kedaluwarsa. Tidak ada string `server key` atau `client key` yang dirender.
- Jika pembuatan charge berhasil tetapi penyimpanan lokal gagal, sistem melakukan kompensasi `Transaction::cancel()`.

Kolom baru migrasi `2026_01_01_000011_add_core_api_fields_to_transactions_table.php`: `payment_label`, `payment_reference`, `payment_expires_at`, `idempotency_key`, `payment_instructions`.

---

## 8. Webhook, validasi, dan idempotensi

`PaymentVerificationService` menggunakan transaksi database dengan `lockForUpdate()`.

1. Validasi signature: `sha512(order_id + status_code + gross_amount + MIDTRANS_SERVER_KEY)` dibandingkan dengan `hash_equals()`.
2. Validasi order ID ada dan nominal sama persis dengan data lokal memakai `bccomp()`.
3. Status dipetakan dari `transaction_status` + `status_code` + `fraud_status`: pending, capture/accept, settlement, deny/challenge, expire, cancel, failure, authorize, refund, partial refund, chargeback, partial chargeback.
4. Rank status mencegah webhook lama menurunkan status baru. Webhook stale tidak mengubah data dan responsnya melaporkan status tersimpan yang efektif, bukan status incoming yang terlambat.
5. `notification_count` dan `midtrans_response` dicatat untuk audit.
6. `PAID`/`SETTLEMENT` mengaktifkan undangan hanya jika belum `ACTIVE` dan tidak `SUSPENDED`.
7. `REFUND`/`CHARGEBACK` penuh mencabut akses publik menjadi `SUSPENDED`; refund/chargeback parsial hanya dicatat. Status `SUSPENDED` tidak diaktifkan ulang otomatis oleh webhook sukses berikutnya.
8. Penolakan, mismatch, dan error dicatat tanpa membocorkan kredensial. CSRF dikecualikan hanya untuk `POST /payment/notification`.

Redirect ke halaman status tidak pernah mengubah status. `Periksa Ulang Status` memanggil Midtrans API lalu memasuki state machine yang sama, sehingga aman dijalankan berulang.

---

## 9. Kredensial dan konfigurasi

Semua kredensial hanya ada di `.env`/config server:

- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
- `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION=false` untuk sandbox.
- `MIDTRANS_NOTIFICATION_URL` harus HTTPS publik di produksi; override diabaikan untuk `localhost`/`127.0.0.1`.
- `MIDTRANS_PAYMENT_TYPE`, `MIDTRANS_EXPIRY_MINUTES`, `PREVIEW_HOURS`, `ACTIVATION_PRICE`.
- `SUPER_ADMIN_EMAIL` hanya dipakai di server saat callback OAuth.

Tidak ada Server Key yang dikirim ke Blade, JavaScript, config frontend, atau log respons.

---

## 10. Responsif dan konsol

Verifikasi browser dilakukan menggunakan harness iframe same-origin satu lebar per kali (360, 768, 1024, 1440 px) agar server development PHP single-threaded tidak menghasilkan kegagalan konkuren palsu.

Hasil:

- Beranda (foto dari `public/assets/beranda/`), katalog lima template, login, dashboard, transaksi, sample kelima template, pemilihan/penggantian template, editor form-only, halaman bagikan, aktivasi, status pembayaran, dan undangan publik kelima template: **tidak ada overflow horizontal**.
- `scrollWidth` sesuai lebar viewport yang diuji. Tabel transaksi dashboard dibungkus `.table-wrap` dengan `position: relative` agar tidak memperlebar dokumen.
- **Nol error console** pada halaman-halaman tersebut setelah chunk runtime, pustaka pendukung, font CSS, dan aset latar dicerminkan/localisasi. Inline script player pada `tema3`/`tema5` juga dinormalisasi agar tidak melempar `SyntaxError`/`TypeError` saat tombol audio tidak ada.
- Editor: perpindahan sembilan bagian, hash `#url`/`#whatsapp`, cek ketersediaan slug, simpan AJAX + status loading, dan peringatan perubahan belum disimpan terverifikasi.
- Halaman bagikan: personalisasi nama tamu, edit/pulihkan pesan, salin tautan (toast), dan tautan WhatsApp terverifikasi.
- Runtime publik: personalisasi `?to=`, submit guestbook + flash notice, share links/copy fallback toast, lightbox buka/Escape/tutup/fokus, tombol kontak WhatsApp, dan tidak ada `alert()` terverifikasi.
- Halaman status pembayaran: instruksi QRIS dan langkah terlihat; tidak ada string kredensial.

File helper sementara (`visual-setup.php`, `mirror-chunks.php`, `public/visual-cookie.html`, `public/responsive-check.html`, `public/_responsive-check.html`) dan rute login lokal sementara sudah dihapus. Semua tab browser ditutup dan proses listener `localhost:8000` sudah dihentikan.

---

## 11. Deploy shared hosting & VPS

- Tidak ada Vite build wajib; aset statis disajikan langsung dari `public/assets/site/`.
- Tidak ada queue daemon yang wajib berjalan untuk alur pembayaran/aktivasi. Webhook dan server recheck cukup.
- Session/cache/queue memakai driver database; pastikan `storage/`, `bootstrap/cache/`, dan direktori upload writable.
- Untuk shared hosting, cron harian opsional: `php artisan invitations:expire-previews --prune-days=30`.
- Untuk VPS, perintah yang sama dapat dipasang di crontab atau scheduler Laravel.
- Set `APP_URL` dan `MIDTRANS_NOTIFICATION_URL` ke HTTPS publik yang dapat dijangkau Midtrans.
- Jalankan `php artisan migrate --force`, `php artisan config:clear`, `php artisan route:clear`, `php artisan view:clear`, `php artisan optimize` saat deploy.
- Pastikan Google OAuth Authorized Redirect URI sama dengan `APP_URL/auth/google/callback`.

---

## 12. Verifikasi akhir

```text
php artisan migrate --force                  -> berhasil (termasuk 2026_01_01_000012)
php artisan test --compact                   -> Tests: 144 passed (2132 assertions)
                                                Duration: 48.07s
```

Cakupan tes mencakup alur customer, dashboard, editor form-only, pemilihan dan penggantian template pada undangan aktif, slug dari nama panggilan + backfill + redirect 301, halaman bagikan, normalisasi WhatsApp dan tombol kontak, rendering undangan publik tanpa data contoh, keamanan/otorisasi, Midtrans gateway, alur pembayaran, dan edge case webhook termasuk stale status.

---

## 13. Catatan reference

Halaman reference eksternal `https://app.sewaundangan.com/forms/new?tema=thema-40` tidak dapat dibaca sebagai journey nyata tanpa autentikasi aplikasi tersebut; fitur yang tidak dapat diamati tidak direkayasa. Desain dan implementasi dibuat ulang dari pola produk yang dapat diakses, dengan aset dan kode milik aplikasi ini. Template asli tetap menjadi sumber tampilan undangan, bukan di-redesign.

---

## 14. Integrasi tiga tema tambahan

Katalog diperluas dari dua menjadi lima template. Baris `templates` dibuat oleh `TemplateSeeder`, dan kelima renderer masuk whitelist terpusat di `Template::RENDERERS()` / `Template::rendererOptions()` sehingga admin, sample, snapshot, dan halaman publik memakai kunci yang sama.

| Slug | Nama | Renderer | Catatan |
| --- | --- | --- | --- |
| `tema1` | Thema 38 | `Tema1Renderer` | countdown timestamp Elementor |
| `tema2` | Thema 40 | `Tema2Renderer` | countdown teks WPKoi |
| `tema3` | Thema 39 | `Tema3Renderer` | countdown teks WPKoi, amplop sintetis |
| `tema4` | Thema 42 | `Tema4Renderer` | countdown teks WPKoi, amplop sintetis |
| `tema5` | Thema 41 | `Tema5Renderer` | countdown timestamp Elementor, label `DEAR TO` |

`live_stream` dinonaktifkan untuk `tema3`/`tema4`/`tema5` karena struktur aslinya tidak menyediakan tempat yang aman untuk fitur tersebut; fitur lain tetap aktif. `tema3`/`tema4` tidak memiliki widget amplop native, sehingga renderer menyuntik node `udgiftbutton`/`udgiftpanel` sebelum `applyGifts()` dan menjaga anchor aslinya agar tidak ikut terhapus.

Aset ketiga tema dicerminkan ke `public/assets/templates/tema3`, `tema4`, dan `tema5`. Seluruh font dan gambar yang sebelumnya masih di-hotlink dari CSS lokal sudah diunduh atau dinetralisasi; chunk runtime Elementor/Elementor Pro dan pustaka pendukung juga lengkap. `HtmlRewriter` memperbaiki inline script player yang cacat pada `tema3`/`tema5` agar tidak menghasilkan error JavaScript saat tombol audio tidak ditemukan.

Verifikasi tambahan:

```text
php artisan view:cache                                   -> berhasil
php artisan test --compact                               -> 142 passed (2022 assertions)
verify_sample_templates.php (HTTP kernel)                -> 5/5 HTTP 200, nama tamu/config/judul benar, 0 referensi lokal hilang
verify_assets_deep.php (CSS url())                       -> 5/5 tema, 0 referensi lokal hilang
verify_public_templates.php (HTTP kernel + switch)       -> 5/5 HTTP 200, slug stabil, snapshot sesuai, 0 kebocoran data sample
browser /templates dan /templates/{1..5}/sample          -> 5/5 200, UD_CONFIG benar, 0 console error, 0 4xx/5xx, tanpa overflow
browser /u/anhar-dan-ika untuk tema3/4/5                -> data customer benar, sample=false, 0 console error, 0 4xx/5xx, tanpa overflow
```

Undangan lokal dikembalikan ke template asalnya setelah verifikasi penggantian template aktif. Semua perubahan masih berada di workspace lokal dan belum di-commit.

---

## 15. Galeri customer 8 foto dan autoplay musik demo

Dua temuan terakhir dari pemeriksaan section `51bf5d5a` dan halaman demo:

1. **Galeri.** `InvitationDataService` sekarang membatasi galeri menjadi maksimal 8 foto untuk undangan customer maupun halaman contoh, mengurutkan foto berdasarkan `sort_order` dengan foto cover lebih dulu, dan `AbstractTemplateRenderer::applyGallery()` juga memotong daftar ke 8 sebagai pertahanan kedua. Setiap item galeri customer mendapat class `e-gallery-image-loaded`, `style="background-image:url(...)"`, serta `data-width`/`data-height` asli pada `.e-gallery-image`. Dimensi diambil dari file gambar di storage (dengan cache per-request dan fallback 853×1280). Tanpa `data-width`/`data-height`, script masonry Elementor dengan `lazyload:yes` menghitung `--item-height: NaN%` dan `padding-bottom:0`, sehingga item galeri tinggi 0 px meskipun link dan background-image sudah benar. `HtmlRewriter::revealElementByDataId()` menghapus class `hidden` yang memang tertanam di source Elementor pada section galeri yang kini berisi foto customer. Id section galeri tema1/tema2 juga diperbaiki ke section induk (`1ff62bc5` / `71779c60`) agar reveal dan hide CSS bekerja pada elemen yang sama.
2. **Musik demo.** Penyebab demo tidak berputar: `forSample()` menulis `musicAutoplay: false`, sehingga runtime tidak pernah memanggil `play()`, dan logika lama hanya memanggil `playMusic()` lewat klik tombol `tombol-buka`. Sekarang contoh memakai `autoplay: true`; runtime mencoba `play()` saat `load` (juga saat `readyState === complete`), menandai status `playing`, dan jika browser menolak autoplay bersuara memasang fallback sekali klik/tombol (`pointerdown`/`keydown`). Klik tombol buka tetap memulai musik lebih awal.

Verifikasi:

```text
php artisan view:cache                                   -> berhasil
php artisan test --compact                               -> 144 passed (2292 assertions)
                                                             TemplateDataConsistencyTest: 16 passed / 650 assertions,
                                                             termasuk assert background-image URL upload customer
                                                             dan data-width/data-height > 0
verify_gallery_audio_final.php (HTTP kernel + switch)    -> 5/5 sample autoplay=true, galeri contoh <= 8,
                                                            5/5 publik hanya foto customer, section tidak hidden
browser /templates/4/sample                             -> 8 item, container padding-bottom=291.78%,
                                                             8 item tinggi 262-263 px, audio paused=false,
                                                             currentTime>0, 0 console error
browser /templates/5/sample (tab tunggal, focus)         -> 8 item, audio paused=false, currentTime>0,
                                                             4/4 chunk bundle HTTP 200, 0 console error
browser /u/anhar-dan-ika                                -> section 51bf5d5a display=block, 4 foto customer,
                                                            4/4 background-image URL /storage/invitations/1/gallery,
                                                            container masonry padding-bottom=144.71%,
                                                            4 item tinggi 259-262 px (bukan 0 px / NaN),
                                                            4/4 request gambar HTTP 200, 0 aset sample,
                                                            audio paused=false, 0 console error
```

Contoh tema5 sebelumnya memiliki 11 foto galeri; sekarang dipotong menjadi 8. tidak ada data dummy yang ditambahkan ke undangan customer; batas 8 juga sudah divalidasi di `UpdateInvitationRequest`.

---

## 16. Foto cover dan footer maksimal dua

Panel **Foto dan Galeri** (`/invitations/{id}/edit#galeri`) sekarang memperbolehkan hingga 2 foto per slot:

- `resources/views/invitations/edit.blade.php`: input `photo_gallery[cover][]` dan `photo_gallery[footer][]` memakai `multiple`, label menyebut “maksimal 2 foto”, dan pratinjau memakai daftar thumbnail seperti upload galeri.
- `config/invitation.php`: batas terpusat `invitation.upload.max_cover_footer_photos = 2`.
- `UpdateInvitationRequest`: aturan `array|max:2` + `*` image untuk kedua slot, pesan validasi Bahasa Indonesia, dan slot tetap wajib berisi minimal satu foto.
- `InvitationEditorService::savePhotoGallery()`: menerima array upload, menyimpan maksimal dua file, membuang file lama hanya saat slot diganti, dan memangkas data legacy yang melebihi batas.
- `InvitationDataService`: membatasi URL cover/footer yang dikirim ke renderer menjadi dua agar slideshow publik tidak menampilkan lebih dari itu.

Verifikasi:

```text
php artisan test --compact                               -> 146 passed (2314 assertions)
PhotoGalleryTest                                         -> 9 passed (128 assertions)
  - editor render input multiple dan label 2 foto
  - cover/footer 2 foto diterima, 3 foto ditolak
  - data legacy > 2 dipangkas saat simpan
TemplateDataConsistencyTest                              -> 16 passed (652 assertions)
  - slideshow cover/footer publik berisi 2 URL customer
```

Batas galeri "Our Moments" tetap 8 foto dan terpisah dari batas cover/footer.

---

## 17. Audit kesetiaan template ketat

Audit terakhir difokuskan pada syarat paling ketat: kelima undangan harus tetap memakai desain asli template 100%, elemen source tidak boleh dihapus, data pengguna hanya mengisi elemen native yang sudah ada, dan elemen yang datanya kosong cukup disembunyikan dari tampilan publik.

### Temuan dan perbaikan

1. **Kelas `hidden` bawaan sumber.** `tema2`–`tema5` memiliki beberapa tag section Elementor dengan token kelas `hidden` di markup asli. Pemeriksaan CSS lokal menunjukkan tidak ada aturan `.hidden { display:none }`, dan pemeriksaan browser menunjukkan section tersebut tetap `display:block`, `visibility:visible`, opacity `1`, serta memiliki rectangle non-zero. Jadi kelas itu adalah bagian dari source/mekanisme animasi asli, bukan mekanisme penyembunyian aplikasi. Pembantu tes `TemplateFidelityAssertions::visibleMarkup()` diperbaiki: kelas `hidden` tidak lagi dianggap menyembunyikan elemen; yang dianggap tersembunyi hanyalah atribut `hidden`, kelas `elementor-hidden`, inline `display:none`, dan ID yang memang disembunyikan runtime (`hiddenIds`/`hiddenWidgets`). Markup sumber tetap dipertahankan persis.
2. **Elemen tanpa data.** Renderer sekarang hanya menyembunyikan elemen yang benar-benar tidak memiliki data melalui `hiddenIds`/`hiddenWidgets` dan CSS runtime. Tidak ada placeholder, teks dummy, gambar rusak, tautan kosong, atau elemen baru yang ditambahkan. Elemen dekoratif/statik tetap tampil walaupun field dinamis di dekatnya kosong.
3. **Penyuntikan yang dilarang sudah dihapus.** Tidak ada lagi section event/cerita tambahan, tombol sosial/copy/live, floating WhatsApp, envelope card/CSS/JS, theme override CSS, atau atribut marker sintetis seperti `data-ud-extra-*`, `udgiftbutton`, `ud-envelope-card`, dan sejenisnya. Gift hanya diikat ke widget hadiah native Elementor.
4. **Galeri dan media.** Foto customer mengisi widget galeri native dengan `background-image`, `e-gallery-image-loaded`, `data-width`, dan `data-height` dari ukuran berkas asli. Maksimal 8 foto galeri; cover/footer maksimal dua. Jika audio tidak ada, source di dalam `<audio id="song">` dikosongkan dan media native disembunyikan tanpa menghapus node atau memutar audio contoh.
5. **Preview dan publik.** Keduanya memakai `AbstractTemplateRenderer` + renderer per template yang sama. Tidak ada layout preview alternatif. Urutan section, struktur, animasi, tipografi, warna, background, ornament, ikon/SVG, navbar, dan perilaku responsif berasal dari file template yang sama.
6. **Debugging artifact.** Penulisan file sementara dari test dihapus; tidak ada output debug yang tersisa.

### Verifikasi otomatis

```text
php artisan test --compact tests/Feature/EditorSectionSyncTest.php tests/Feature/PhotoGalleryTest.php tests/Feature/SecurityTest.php tests/Feature/TemplateDataConsistencyTest.php
  -> 50 passed (1291 assertions)

php artisan test --compact
  -> 148 passed (2586 assertions)

php artisan view:cache
  -> berhasil
```

Regresi utama yang dijalankan: `test_all_templates_preserve_native_structure_and_hide_unfilled_elements()` dan `test_preview_and_public_use_the_same_native_renderer()`.

### Verifikasi browser

Satu tab browser dipakai untuk menghindari request yang dibatalkan. Error `ChunkLoadError` yang sempat muncul ternyata bukan berkas hilang: berkas chunk tersedia dan mengembalikan HTTP 200, sedangkan network log menunjukkan `net::ERR_ABORTED` akibat perpindahan halaman cepat. Setelah load bersih, tidak ada request gagal maupun error konsol.

Desktop:

```text
/templates/1/sample -> 41 section, urutan identik dengan tema1.html, 0 broken image, overflow 0, 4 hidden IDs, 0 kebocoran hidden
/templates/2/sample -> 64 section, urutan identik dengan tema2.html, 0 broken image, overflow 0, 1 hidden ID,  0 kebocoran hidden
/templates/3/sample -> 54 section, urutan identik dengan tema3.html, 0 broken image, overflow 0, 5 hidden IDs, 0 kebocoran hidden
/templates/4/sample -> 50 section, urutan identik dengan tema4.html, 0 broken image, overflow 0, 6 hidden IDs, 0 kebocoran hidden
/templates/5/sample -> 56 section, urutan identik dengan tema5.html, 0 broken image, overflow 0, 17 hidden IDs, 0 kebocoran hidden
/u/anhar-dan-ika   -> memakai tema3, 54 section, urutan identik dengan tema3.html, 0 broken image, overflow 0,
                      18 hidden IDs, 0 kebocoran hidden, 4 foto galeri customer, 0 teks contoh, 0 error konsol
```

Responsive 390px diuji dengan iframe same-origin berlebar 390px sehingga media query mobile benar-benar aktif (`innerWidth=390`, viewport `width=device-width, initial-scale=1`):

```text
6/6 halaman -> body width 390.4px, overflow horizontal 0, 0 broken image,
               semua elemen hidden tetap tersembunyi, jumlah section sesuai desktop
```

Catatan: URL audio pada data lokal `/u/anhar-dan-ika` berasal dari pilihan musik customer di database dan bukan audio contoh template; satu request audio berstatus `206/ERR_ABORTED` hanya terjadi karena halaman ditinggalkan saat verifikasi berpindah, bukan kegagalan aset.

### Status

Temuan lanjutan pada background slideshow Wedding Event Thema 39 ditangani di bagian 18. Semua perubahan masih berada di workspace lokal dan belum di-commit.

---

## 18. Background foto Wedding Event Thema 39

Pada publik tema3, tiga kolom slideshow native di dalam section `699ecc84` (blok **Wedding Event**) yang memang membawa galeri stok di source tetap kosong setelah `clearSampleBackgrounds()` menghapus foto stok bawaan. Dua kolom saudaranya (`4c421c4f` dan `37c0b978`) memang sengaja kosong di template asli dan tidak diisi. Halaman contoh tidak masalah karena foto stok asli sengaja dipertahankan, sehingga belum terdeteksi oleh regresi sebelumnya.

Perbaikan dilakukan di `Tema3Renderer::applyBackgrounds()` tanpa menyentuh markup:

| Data-ID native | Fungsi dalam template | Sumber foto |
|---|---|---|
| `4684d4b4` | background utama blok Wedding Event | Foto cover, fallback footer |
| `b9055e3` | background AKAD | Foto cover, fallback footer |
| `15865fca` | background RESEPSI | Foto footer, fallback cover |

`4c421c4f` dan `37c0b978` sengaja tidak dipetakan karena galerinya memang kosong di source asli.

`HtmlRewriter::setBackgroundGallery()` hanya mengisi `data-settings.background_slideshow_gallery` pada elemen yang sudah ada; Elementor yang membuat slide dan animasinya seperti semula. Tidak ada div, section, gambar, atau placeholder baru. Jika kedua slot foto kosong, slide dibiarkan kosong dan tidak diisi data dummy. Urutan section dan struktur source tetap identik.

Verifikasi:

```text
php artisan test --compact tests/Feature/TemplateDataConsistencyTest.php -> 19 passed (930 assertions)
php artisan test --compact                                              -> 149 passed (2599 assertions)
php artisan view:cache                                                  -> berhasil

Test baru: test_thema39_event_slideshows_use_cover_and_footer_photos()
  - 3/3 data-ID yang memang punya galeri di source memuat URL foto cover/footer customer
  - 2/2 kolom yang kosong di source tetap kosong pada sample dan publik
  - kumpulan data-ID slideshow sample dan publik identik
  - 0 foto stok `pexels-artem` pada ketiga slot publik yang diisi
  - contoh tema3 tetap memakai foto stok aslinya

Browser /u/anhar-dan-ika:
  - 3/3 data-settings background=slideshow dengan 2 URL /storage/invitations/1/photo-cover atau photo-footer
  - 3/3 swiper native membuat 6 slide (2 asli + duplikat loop) dengan background-image foto customer
  - 2/2 kolom saudara tetap gallery=[] dan 0 slide, sesuai source asli
  - sisa URL `pexels-artem` hanya berada pada widget story sample tersembunyi (`f23fa97`, `3cad5bb`, display:none)
  - 0 error konsol, overflow horizontal 0
```

Dengan ini hanya slideshow native tema3 yang memang memiliki galeri di source yang mengikuti data Foto dan Galeri customer; kolom yang kosong di template asli dibiarkan kosong tanpa mengubah desain.

---

## 19. Wedding Gift Thema 39 tidak muncul

Data `gift_accounts` dan `settings.gift_address` sudah tersimpan, tetapi section `3b4f3c4e` pada publik tema3 tetap tampak kosong. Penyebabnya ada dua:

1. `Tema3Renderer::giftWidgetIds()['legacy']` sebelumnya berisi section native `12489c64`, `39b55ef`, `a59afb4`, `7c183df`, dan `a77be84`. Karena `applyGifts()` menyembunyikan semua ID tersebut pada undangan publik, kedua blok gift native ikut tersembunyi beserta data yang sudah terikat.
2. `Tema3Renderer::removedWidgetIds()` menyembunyikan widget native `680ac935`, `20ea64a`, `7fd7436`, dan `a77be84` secara permanen, sehingga tombol salin dan alamat pengiriman tidak pernah tampil meski datanya ada.

Perbaikan:

- `legacy` untuk tema3 dikosongkan. Tidak ada section atau widget gift native yang dihapus/ditutup wholesale.
- `applyGifts()` menerima daftar slot opsional agar tema3 dapat memetakan dua blok native secara terpisah:
  - rekening: `18f5bfce` + tombol salin `680ac935`, dengan logo stok `3c928698` tetap tersembunyi karena tidak ada field customer;
  - alamat pengiriman: heading `a77be84`, nilai `7fd7436`, dan tombol salin `20ea64a`, diisi dari `settings.gift_address`.
- Widget dynamis tidak lagi masuk `removedWidgetIds()`. `applyGifts()` yang menyembunyikannya hanya jika slotnya kosong.
- `emptyWidgetIds()` menyembunyikan blok `12489c64` hanya jika tidak ada rekening dan blok `39b55ef` hanya jika tidak ada alamat.
- `AbstractTemplateRenderer::hiddenIds()` sekarang menganggap fitur gift kosong hanya jika rekening **dan** alamat kosong. Sebelumnya alamat saja tidak cukup untuk membuka section gift.

Struktur, urutan section, typografi, dan styling tetap berasal dari template asli. Tidak ada panel envelope, tombol, kartu bank, atau elemen baru yang disuntikkan.

Verifikasi:

```text
php artisan test --compact tests/Feature/TemplateDataConsistencyTest.php -> 20 passed (964 assertions)
php artisan test --compact                                              -> 150 passed (2633 assertions)
php artisan view:cache                                                  -> berhasil

Test baru: test_thema39_wedding_gift_binds_account_and_address_to_native_widgets()
  - kedua blok native (12489c64, 39b55ef) dan seluruh widgetnya tetap ada dan tidak masuk hidden config
  - data rekening, nama, tombol “Salin Rekening”, heading alamat, alamat, dan “Salin Alamat” tampil di markup yang terlihat
  - logo bank stok (3c928698) tetap tersembunyi
  - tidak ada udgiftpanel, udgiftbutton, atau ud-envelope-card

Browser /u/anhar-dan-ika:
  - section 3b4f3c4e tampil (display:block, visibility:visible)
  - 12489c64: “BCA Auwyuwa.n Anhar”, tombol “Salin Rekening”
  - 39b55ef: “Alamat Pengiriman Kado:”, alamat customer, tombol “Salin Alamat”
  - 3c928698 tetap display:none
```

---

## 20. Background Wedding Event dan Wedding Gift Thema 42

Pada tema4,section `4c4af6cd` (**Wedding Event**) memiliki tiga kolom dengan galeri slideshow di source, tetapi `clearSampleBackgrounds()` mengosongkannya pada undangan publik. Di waktu yang sama,section gift `565b39c8` menyembunyikan blok rekening dan alamat karena ID native `54dafe21`, `1d424bd1`, dan `9ed974b` masuk daftar `legacy`, sementara widget nilai dan tombol salin masuk `removedWidgetIds()`.

Perbaikan:

- Hanya tiga kolom yang memang memiliki galeri di source yang diisi dari **Foto Sampul/Cover** (fallback footer jika cover kosong):
  - `528b3f36` — background utama Wedding Event
  - `5e892ecd` — background Akad
  - `5204381e` — background Resepsi

  Kolom `565f4543` dan `3d6059f4` sengaja kosong di template asli dan tetap kosong. Tidak ada foto yang ditempatkan di luar slot yang memang punya galeri.

- Gift tema4 memakai dua blok native yang sudah ada:
  - rekening: `50a93844`, `8716a95`, `a7e0da`;
  - alamat: `54dafe21`, `35335810`, `1d424bd1`, `9ed974b`.

  Alamat diambil dari `settings.gift_address`, rekening dari `gift_accounts`. `legacy` dikosongkan dan widget dinamis tidak lagi masuk `removedWidgetIds()`; `applyGifts()` yang menyembunyikan masing-masing slot hanya jika datanya kosong. Logo bank stok `1e1170bb` tetap tersembunyi karena tidak ada field customer penggantinya.

Struktur, urutan section, animasi slideshow, dan styling tetap berasal dari template asli. Tidak ada elemen baru atau panel envelope yang disuntikkan.

Verifikasi:

```text
php artisan test --compact tests/Feature/TemplateDataConsistencyTest.php -> 22 passed (1013 assertions)
php artisan test --compact                                              -> 152 passed (2682 assertions)
php artisan view:cache                                                  -> berhasil

Test baru:
  - test_thema42_event_slideshows_use_cover_photos()
  - test_thema42_wedding_gift_binds_account_and_address_to_native_widgets()

  - 3/3 slideshow dengan galeri di source memuat foto cover customer
  - 2/2 kolom kosong di source tetap kosong pada sample dan publik
  - 0 foto stok IMG-20230508 pada slot publik yang diisi
  - kedua blok gift beserta widgetnya tampil ketika rekening dan alamat tersedia
  - logo bank stok tetap tersembunyi; tidak ada udgiftpanel/udgiftbutton/ud-envelope-card
```

---

## 21. Background "THE WEDDING OF" dan Wedding Gift Thema 40

Pada tema2, section `63dc565a` (**THE WEDDING OF**) memiliki satu kolom slideshow native `17b7bb78` yang berisi foto stok. Seperti tema3/tema4, `clearSampleBackgrounds()` mengosongkan galeri ini pada undangan publik sehingga background tidak tampil. Di sisi lain, blok alamat native `61f9301f` ikut tersembunyi karena masuk `legacy`, sehingga hanya rekening yang tampil pada Wedding Gift.

Perbaikan:

- Kolom `17b7bb78` diisi ulang dari **Foto Sampul/Cover** (fallback footer) memakai `setBackgroundGallery()`, sehingga slideshow Elementor asli tetap dipakai. Tidak ada elemen background baru.
- Kolom sub-cover `5e3f969e` tetap mengikuti foto footer seperti sebelumnya, dan cover pembuka `56efded2` tetap mengikuti foto cover.
- Gift tema2 memakai dua blok native yang sudah ada:
  - rekening: `18363c3b`, `7cf88fd6`, `265ec209`, `7eb8f639`, `6d98f144`;
  - alamat: `61f9301f`, `6a3f8839`, `3c3bc048`, `4d72bbb8`, `7a9e4961`, `55fdfd25`.

  `settings.gift_address` diikat ke widget alamat melalui slot kedua `applyGifts()` memakai `GiftAccountViewData`. `legacy` dikosongkan; `emptyWidgetIds()` menyembunyikan blok bank hanya jika tidak ada rekening dan blok alamat hanya jika alamat kosong. Logo bank stok `19b4f468` tetap tersembunyi karena tidak ada field customer penggantinya. Tidak ada `udgiftpanel`, `udgiftbutton`, atau `ud-envelope-card` yang disuntikkan.

Verifikasi:

```text
php artisan test --compact tests/Feature/TemplateDataConsistencyTest.php -> 24 passed (1072 assertions)
php artisan test --compact                                              -> 154 passed (2741 assertions)
php artisan view:cache                                                  -> berhasil

Test baru:
  - test_thema40_the_wedding_of_background_uses_cover_photos()
  - test_thema40_wedding_gift_binds_account_and_address_to_native_widgets()

Verifikasi browser pada /u/anhar-dan-ika (tema2):
  - section 63dc565a dan kolom 17b7bb78 tampil (display:block / flex)
  - 2 slide background memuat URL foto cover customer, bukan pexels-photo-8526247
  - rekening "BCA / Auwyuw / a.n Anhar" dan alamat "sdbjkhdfkjhds" tampil di widget native
  - kedua tombol "Salin Rekening" dan "Salin Alamat" tampil
  - horizontal overflow 0; 0 error konsol
```

---

## 22. Background gate dan Wedding Gift Thema 41

Pada tema5, section pembuka `6f443a0a` memiliki satu kolom slideshow native `90e124f` yang berisi dua foto stok. Seperti pada tema2/3/4, `clearSampleBackgrounds()` mengosongkan galeri tersebut pada undangan publik. Di sisi lain, Wedding Gift hanya mengikat dua kartu bank pertama; blok alamat native `371ca36` dan kartu bank ketiga/keempat masuk daftar `legacy`/`removedWidgetIds()`, sehingga alamat pengiriman kado tidak pernah tampil meski `settings.gift_address` tersimpan.

Perbaikan:

- `Tema5Renderer::applyBackgrounds()` mengisi ulang `data-settings.background_slideshow_gallery` pada `90e124f` dari **Foto Sampul/Cover** (fallback footer) memakai `setBackgroundGallery()`. Halaman contoh tetap memakai foto stok aslinya. Tidak ada section, div, gambar, atau animasi baru.
- Kelima slot gift native diikat langsung di tempatnya:
  - bank 1: `72bab81f`, `49f3294d`, copy `2105d08f`;
  - bank 2: `4187e74`, `460f856a`, copy `5c10a35d`;
  - bank 3: `080baa5`, `99cf060`, copy `250a970`;
  - bank 4: `9954c09`, `27d0173`, copy `6b13a8b`;
  - alamat: `371ca36`, heading `3c0cb7a`, nilai `6da138f`, copy `a07c3d9`.

  Alamat memakai `GiftAccountViewData` sebagai pseudo-account kelima agar `applyGifts()` dapat memakai widget heading/copy native tanpa menyuntik markup. `legacy` dikosongkan; `removedWidgetIds()` hanya menyembunyikan logo bank stok dan section duplikat kosong. `emptyWidgetIds()` menyembunyikan masing-masing kartu bank dan blok alamat secara independen ketika datanya kosong.
- Perilaku asli tetap dipertahankan: panel `467b1693` awalnya tertutup dan dibuka oleh tombol native `4566503` (**Kirim Amplop**). Blok alamat `371ca36` tampil mandiri di luar panel tersebut. Tidak ada `udgiftpanel`, `udgiftbutton`, `ud-envelope-card`, kartu, atau tombol buatan yang ditambahkan.

Verifikasi:

```text
php artisan test --compact tests/Feature/TemplateDataConsistencyTest.php -> 26 passed (1127 assertions)
php artisan test --compact                                              -> 156 passed (2796 assertions)
php artisan view:cache                                                  -> berhasil

Test baru:
  - test_thema41_gate_slideshow_uses_cover_photos()
  - test_thema41_wedding_gift_binds_account_and_address_to_native_widgets()

  - 1/1 slideshow dengan galeri di source memuat 2 foto cover customer
  - 0 foto stok pexels-willy-wft pada slot publik; halaman contoh tetap memakainya
  - kelima slot gift native tetap ada, tidak masuk hidden config, dan memuat data customer
  - kartu bank kosong serta logo stok tersembunyi; tidak ada envelope buatan

Catatan: satu full run sebelumnya sempat gagal pada PhotoGalleryTest::test_replacing_another_slot_keeps_files_still_referenced_by_gallery_or_settings. Test tersebut lolos saat dijalankan sendiri dan pada seluruh kelasnya, serta full run terakhir kembali hijau.
```

Verifikasi browser pada `/u/anhar-dan-ika` (tema5):

```text
- data-settings 90e124f memuat 2 URL /storage/invitations/1/photo-cover/...
- slideshow.native membuat 6 slide (2 asli + duplikat loop) dengan background-image foto customer
- 371ca36 tampil berisi "Alamat Pengirirman Kado:", "sdbjkhdfkjhds", dan "Salin Alamat"
- sebelum tombol ditekan, panel 467b1693 tertutup sesuai source asli
- setelah "Kirim Amplop" (4566503) ditekan, 72bab81f / 49f3294d / 2105d08f tampil:
  "BCA Auwyuwa.n Anhar" dan "Salin Rekening"
- horizontal overflow 0; 0 error konsol aplikasi
- satu ERR_TUNNEL_CONNECTION_FAILED untuk https://www.youtube.com/iframe_api berasal dari
  pembatasan jaringan browser sandbox, bukan aset atau renderer undangan
```

## 23. Urutan Mempelai global

Ditambahkan satu pengaturan **Urutan Mempelai** di panel editor `Data Pasangan` dengan dua pilihan:

- **Laki-laki dulu** — `{nama pria} & {nama wanita}`
- **Perempuan dulu** — `{nama wanita} & {nama pria}`

Label pilihan diambil langsung dari `groom.full_name` dan `bride.full_name`, termasuk nilai `old()` setelah validasi gagal, sehingga tidak bergantung pada nama contoh. Nilai disimpan sebagai `settings.couple_order` (`male_first` atau `female_first`) melalui enum `App\Enums\CoupleOrder`. Kolom baru `invitation_settings.couple_order` bersifat nullable; `null` berarti undangan lama dan mempertahankan tampilan sebelum fitur ini ada. Field mentah `groom`/`bride` tidak pernah ditukar.

Implementasi terpusat:

- `InvitationViewData::orderedPeople()`, `firstPerson()`, `secondPerson()`, `coupleLabel()`, dan `hasExplicitCoupleOrder()` menjadi satu sumber urutan untuk nama, orang tua, biodata, Instagram, judul, dan metadata.
- `HtmlRewriter::replaceGlobalText()` memakai urutan tersebut untuk semua kemunculan nama pasangan.
- `AbstractTemplateRenderer::applyCouple()`, `applyCoupleRoleLabels()`, dan `applyCouplePhotos()` memetakan orang ke slot visual native pada kelima template. Foto pria/wanita mengikuti urutan yang dipilih, sedangkan `null` tetap memakai perilaku foto pendukung/cover lama.
- Role label (`BRIDE`/`GROOM`, `THE BRIDE`/`THE GROOM`) diubah hanya untuk pilihan eksplisit `male_first`; `female_first` dan legacy `null` mempertahankan teks asli template.
- Label dashboard dan pesan bagikan mengikuti pengaturan eksplisit; legacy tetap `pria & wanita`.
- Slug tidak berubah (`groom-dan-bride`) agar tautan yang sudah tersebar tidak rusak.
- Karena editor mengirim hanya panel aktif, `saveSettings()` menulis `couple_order` hanya ketika kuncinya dikirim. Pilihan dengan demikian tidak hilang saat panel lain disimpan; mengirim nilai kosong mengembalikan perilaku legacy.
- Halaman contoh tidak memakai pengaturan ini, sehingga kelima template contoh tetap identik dengan source aslinya.

Verifikasi:

```text
php artisan migrate --force                                      -> berhasil
php artisan test --compact tests/Feature/CoupleOrderTest.php     -> 6 passed (265 assertions)
php artisan test --compact tests/Feature/TemplateDataConsistencyTest.php -> 26 passed (1127 assertions)
php artisan test --compact tests/Feature/EditorSectionIsolationTest.php -> 10 passed (72 assertions)
php artisan test --compact                                       -> 162 passed (3061 assertions)
php artisan view:cache                                           -> berhasil
```

Cakupan `CoupleOrderTest` meliputi validasi input, persistensi setelah refresh/logout/login, isolasi panel editor, perilaku legacy `null`, lima template × dua urutan, sinkronisasi nama/orang tua/Instagram/foto/label peran, judul dan metadata, sunting ulang data pasangan, kesetiaan struktur template, paritas preview/publik, serta perpindahan template.

Verifikasi HTTP pada `/u/anhar-dan-ika` (tema3, data lokal `Anhar` dan `Ika Nurputri`):

```text
male_first   -> Anhar di slot pertama, Ika Nurputri di slot kedua, GROOM/BRIDE,
                "Anhar & Ika", foto Anhar lebih dulu
female_first -> Ika Nurputri di slot pertama, Anhar di slot kedua, BRIDE/GROOM,
                "Ika & Anhar", foto Ika lebih dulu
null legacy  -> susunan BRIDE/GROOM, "Ika & Anhar", dan foto pendukung asli
```

Nilai lokal dikembalikan ke `null` setelah verifikasi. Upaya verifikasi visual lanjutan tidak dapat diselesaikan karena koneksi browser desktop terputus setelah server restart; verifikasi dilakukan melalui respons HTML publik dan test otomatis.

## 24. Login kata sandi, seeder Super Admin, dan bersihnya alert logout

Perubahan pada alur autentikasi:

- `resources/views/auth/login.blade.php` kembali memiliki input **Email**, **Kata sandi**, checkbox **Ingat saya**, dan tombol **Masuk**. Google OAuth tetap tersedia sebagai alternatif. Tidak ada tautan/tombol **Daftar/Register**.
- `app/Http/Requests/Auth/LoginRequest.php` memvalidasi `email`, `password`, dan `remember` dengan pesan Bahasa Indonesia.
- `app/Http/Controllers/Auth/LoginController.php` memakai `Auth::attempt()`, menolak akun nonaktif, meregenerasi sesi, mencatat `activity_logs`, lalu mengarahkan ke `dashboard`/`url.intended`. Role tidak pernah diambil dari input form.
- `routes/web.php` menambahkan `POST /login` (`login.post`) di dalam grup `guest` dengan throttling `6,1`.
- `app/Http/Controllers/Auth/GoogleAuthController.php` tidak lagi mengirim flash `status` saat logout.
- `resources/views/layouts/app.blade.php` hanya merender wrapper alert ketika memang ada `status`/`error`. Tidak ada lagi `<div class="container" aria-live="polite">` kosong di atas Hero/Login, sehingga tidak tersisa margin, padding, atau gap bekas alert.

Seeder Super Admin:

- `database/seeders/SuperAdminSeeder.php` kini memakai `updateOrCreate()` berdasarkan email, sehingga aman dijalankan berulang kali tanpa duplikat.
- Role dipaksa `super_admin`, status `active`, `email_verified_at` diisi, dan kata sandi disimpan dengan `Hash::make()` (bukan plain text).
- `config/app.php`, `.env`, dan `.env.example` menambahkan `SUPER_ADMIN_PASSWORD` dengan nilai awal `Admin12345!`. `SUPER_ADMIN_EMAIL` juga memiliki default `admin@undangan.test`.

Kredensial Super Admin hasil seed lokal:

```text
Email    : admin@undangan.test
Password : Admin12345!
```

Verifikasi:

```text
php artisan db:seed --class=SuperAdminSeeder --force -> berhasil
cek DB lokal -> 1 akun, role super_admin, status active,
               Hash::check('Admin12345!') = true, password bukan plain text
php artisan test --compact tests/Feature/LoginTest.php -> 7 passed (53 assertions)
```

Login browser nyata pada `http://localhost:8000/login` dengan kredensial tersebut berhasil dan diarahkan ke `/admin` (Dashboard Admin). File `tests/Feature/GoogleOnlyAuthTest.php` digantikan `tests/Feature/LoginTest.php` karena ekspektasi lama “Google-only, tanpa kata sandi” sudah tidak berlaku.

## 25. Migrasi penuh SQLite → MySQL dan verifikasi MySQL

### 25.1 Hasil audit

Audit menyeluruh terhadap `.env`, `.env.example`, `config/`, seluruh `database/migrations`, model, seeder, factory, service, controller, query builder/raw SQL, dan test suite menunjukkan:

- Tidak ada sintaks SQLite khusus (`strftime`, `julianday`, `ON CONFLICT`, `AUTOINCREMENT`, dan sejenisnya) di kode aplikasi.
- Tidak ada raw SQL `GROUP BY`/`HAVING` yang melanggar `ONLY_FULL_GROUP_BY`.
- Tidak ada native `ENUM`; status memakai `VARCHAR` + PHP backed enum, aman di MySQL 8.
- Kolom `JSON` ditulis lewat cast Eloquent/`json_encode()` dan tidak dipakai dalam fungsi JSON SQL.
- Semua foreign key, cascade/restrict, boolean, tanggal, dan transaksi sudah memakai API Laravel yang portable.

Temuan yang diperbaiki:

| Temuan | Perbaikan |
|---|---|
| `phpunit.xml` memaksa `sqlite`/`:memory:` | Test suite sekarang memakai `mysql` + database khusus `undangan_digital_test`. |
| `config/database.php` masih mendefinisikan koneksi SQLite/Postgres/SQL Server | Hanya koneksi `mysql` yang tersisa; `engine => InnoDB`, `strict => true`, `utf8mb4`, `utf8mb4_unicode_ci`, dan `DB_TIMEZONE` (+08:00 mengikuti `APP_TIMEZONE`). |
| `config/queue.php` fallback `sqlite` untuk `job_batches`/`failed_jobs` | Fallback diubah ke `mysql`. |
| `composer.json` membuat `database/database.sqlite` saat install | Dihapus; tidak ada lagi artifact SQLite. |
| MySQL/MariaDB tidak mendapat upgrade FK/DECIMAL pada dua migration lama | Pemeriksaan driver menerima `mysql` **dan** `mariadb`. |
| `down()` migrasi DECIMAL bisa memotong nilai desimal | `down()` sengaja tidak mengembalikan ke BIGINT agar data uang tidak rusak. |
| Index `utf8mb4` 255 char berisiko pada host lama (767-byte limit) | Kolom yang di-index/unique diperkecil menjadi `string(191)` di migration asalnya. |
| UNIQUE index baru bisa gagal pada data lama yang duplikat | Migration `2026_11_21_000002_add_unique_indexes...` melakukan dedupe dulu: duplikat `musics.file_path` dihapus (path identik), duplikat `idempotency_key` di-null-kan tanpa menghapus transaksi. |
| Money `DECIMAL` di-cast `integer` di model | `Transaction.gross_amount`, `Template.price`, dan `Invitation.price` memakai `decimal:2`; `SUM()` pendapatan admin memakai `bcadd()` sehingga sen tidak hilang. |
| Lock order checkout vs webhook berbeda (deadlock InnoDB) | Webhook sekarang mengunci `invitations` terlebih dahulu, lalu `transactions`; kedua service memakai trait `RetriesDeadlocks` untuk retry 1213/1205/40001 secara terbatas. |
| Jam `TIME` MySQL (`09:30:00`) merusak input `type="time"` | Editor dan admin invitation menormalkan menjadi `HH:MM`. |
| Seeder mengubah password tiap `db:seed` | `firstOrCreate` + re-apply role/status; password hanya ditulis saat akun dibuat. |

Catatan: dua migration lama tetap memiliki prefix `2026_11_21_000002` dengan nama lengkap berbeda. Urutannya deterministik berdasarkan nama file dan keduanya sudah tercatat pada database yang pernah dijalankan; mengganti namanya akan membuat migration lama ter-execute ulang, sehingga sengaja tidak diubah.

Tidak ada penghapusan fitur/UI, tidak ada strict mode atau foreign key yang dimatikan, dan tidak ada try-catch kosong.

### 25.2 Perintah dan hasil verifikasi (MySQL nyata)

Server lokal yang tersedia adalah **MariaDB 10.4.32** (kompatibel dengan driver `mysql` Laravel) pada `127.0.0.1:3306`. Koneksi Laravel memakai driver `mysql`, `strict => true`, session `sql_mode` aktif dengan `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,...`, dan session timezone `+08:00`.

```text
# Koneksi + konfigurasi aktif
php artisan tinker --execute="..."  -> driver=mysql, @@session.time_zone=+08:00

# Database uji khusus (tidak menyentuh data produksi)
mysql -u root -e "CREATE DATABASE undangan_digital_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# Migrasi dari nol
DB_DATABASE=undangan_digital_test php artisan migrate:fresh --force
-> 25 tabel, semua migrasi DONE, tanpa error

# Seeder
DB_DATABASE=undangan_digital_test php artisan db:seed --force
-> SettingSeeder, TemplateSeeder, MusicSeeder, SuperAdminSeeder DONE

# Strukturisasi
information_schema -> 25 tabel InnoDB utf8mb4_unicode_ci,
                      17 foreign key, 80 index,
                      price/gross_amount = decimal(15,2)

# Seluruh test suite menggunakan MySQL (phpunit.xml)
php artisan test --compact
-> Tests: 165 passed (3098 assertions)

# Log aplikasi
Tidak ada error SQL baru selama migrasi, seeder, test, dan verifikasi browser.
(error SQLite lama hanya riwayat sebelum migrasi; satu error performance_schema
 berasal dari perintah diagnostik db:show MariaDB, bukan aplikasi.)
```

### 25.3 Verifikasi fitur

- Login Super Admin `admin@undangan.test` / `Admin12345!` berhasil pada server MySQL uji `http://127.0.0.1:8001/login` dan diarahkan ke `/admin`.
- Sesi tetap hidup saat berpindah ke `/admin/templates` (Dashboard Super Admin dan Manajemen Template tampil normal).
- Test suite MySQL mencakup CRUD undangan/template/customer/transaksi, relasi, upload path, pagination, pencarian, filter/urutan, dashboard/statistik, webhook idempotent, amount tampering, CSRF/XSS, double check-in, dan seeder Super Admin.
- Tidak ada ketergantungan/konfigurasi SQLite yang tertinggal di luar `vendor/`. File lama `database/database.sqlite` masih ada sebagai artifact historis tetapi tidak direferensikan oleh konfigurasi, kode, test, maupun composer; sengaja tidak dihapus agar data lama tidak hilang tanpa konfirmasi.

## 26. Perbaikan TypeError halaman Edit Template

Gejala: `GET /admin/templates/3/edit` menghasilkan `TypeError: str_starts_with(): Argument #2 ($needle) must be of type string, array given` di `app/Models/Template.php:101`, dipanggil dari `resources/views/admin/templates/form.blade.php:88`.

Penyebab: `Template::thumbnailUrl()` memanggil fungsi native PHP `str_starts_with($thumbnail, ['http://', 'https://', '/'])`. Fungsi native PHP 8 hanya menerima satu `string` sebagai needle, bukan array. Error muncul hanya ketika template memiliki nilai `thumbnail` (misalnya tema3 dengan `/uploads/templates/...`).

Perbaikan:

- `app/Models/Template.php` sekarang memakai `Illuminate\Support\Str::startsWith($thumbnail, ['http://', 'https://', '/'])`, yang memang mendukung array needle.
- `tests/Feature/TemplateManagementTest.php` mendapat test regresi `test_admin_template_edit_renders_absolute_and_relative_thumbnails()` yang membuka halaman edit tema3 dan memeriksa URL absolut, relatif, HTTPS, dan `null`.

Verifikasi:

```text
php artisan test --compact tests/Feature/TemplateManagementTest.php -> 10 passed (127 assertions)
php artisan test --compact -> 166 passed (3104 assertions)
Browser nyata: http://localhost:8000/admin/templates/3/edit -> 200, title "Edit Template", tanpa TypeError
```

## 27. Perbaikan error `validation.integer` pada form Template

Gejala: saat menyimpan edit template (misalnya tema3), flash error menampilkan kunci mentah `validation.integer` alih-alih pesan Bahasa Indonesia.

Penyebab ada dua:

1. `Template.price` adalah `DECIMAL(15,2)` dengan cast `decimal:2`, sehingga edit form mengirim `"150000.00"`. Aturan `integer` di `TemplateRequest` menolak string tersebut, walaupun nilainya utuh dalam rupiah.
2. `APP_LOCALE=id` dan `APP_FALLBACK_LOCALE=id`, tetapi tidak ada direktori `lang/id`. Karena locale dan fallback sama, Laravel tidak punya sumber pesan `en` untuk dijadikan cadangan dan merender kunci `validation.integer` apa adanya.

Perbaikan:

- `app/Http/Requests/Admin/TemplateRequest.php`:
  - `prepareForValidation()` menormalkan angka dengan akhiran `.00` (misalnya `"150000.00"` menjadi `"150000"`) sebelum validasi.
  - Pecahan rupiah seperti `"150000.50"` sengaja tetap ditolak agar `TemplateManagementService` tidak memotong nilai secara diam-diam.
  - `messages()` ditambahkan untuk pesan harga yang spesifik dan berbahasa Indonesia.
- `resources/views/admin/templates/form.blade.php`: input harga memakai `step="1"` dan menampilkan nilai cast sebagai bilangan bulat, sehingga browser tidak mengirim `.00`.
- `lang/id/validation.php`, `lang/id/auth.php`, `lang/id/passwords.php`, dan `lang/id/pagination.php` ditambahkan. Semua pesan validasi bawaan kini berbahasa Indonesia, bukan kunci `validation.*`.
- Tidak ada perubahan skema database, tidak ada penghapusan fitur, dan tidak ada try-catch workaround.

Verifikasi:

```text
php artisan tinker --execute="..." -> validation.integer = "harga harus berupa bilangan bulat."
php artisan test --compact tests/Feature/TemplateManagementTest.php -> 13 passed (138 assertions)
php artisan test --compact -> 169 passed (3115 assertions)
php artisan optimize:clear -> config/cache/routes/views DONE
Browser nyata: http://127.0.0.1:8000/admin/templates/3/edit -> submit berhasil,
               tidak ada `.alert-error`, tidak ada teks `validation.`, flash tersimpan.
```

Test regresi baru:

- `test_admin_can_update_template_with_decimal_cast_price()` — memastikan `"150000.00"` diterima dan tersimpan sebagai `150000.00`.
- `test_admin_template_update_rejects_fractional_rupiah_with_indonesian_message()` — memastikan `"150000.50"` ditolak dengan pesan Indonesia, bukan `validation.integer`.
- `test_validation_messages_are_translated_to_indonesian()` — memastikan kunci validasi tidak lagi tampil mentah.

