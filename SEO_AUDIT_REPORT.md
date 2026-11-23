# SEO Audit Report — INVITE

Tanggal audit: 2026-11-24  
Lingkungan audit: workspace lokal (`D:\UNDANGAN DIGITAL BARU`) + render HTML via Laravel artisan/tinker; **tidak ada akses Vercel production dashboard/log atau validator eksternal live**.

## 1. Masalah SEO yang ditemukan

- **Meta publik belum lengkap**: `resources/views/layouts/app.blade.php` hanya punya `description`, `theme-color`, `title`; tidak ada canonical, OG, Twitter Card, robots, JSON-LD.
- **Undangan publik belum lengkap untuk social crawler**: `HtmlRewriter::injectHead()` hanya menghasilkan title/description/og:title/og:description/og:image/og:url sederhana; belum ada `og:type`, `og:site_name`, `og:locale`, `og:image:secure_url/type/width/height`, Twitter Card, canonical eksplisit, robots, JSON-LD.
- **Tidak ada sitemap dinamis**: tidak ditemukan `sitemap.xml` route/controller; `public/robots.txt` hanya `User-agent: *` + `Disallow:` kosong.
- **Robots tidak mengarahkan sitemap / tidak menutup path privat spesifik**: sebelumnya hanya allow-all generik.
- **Default OG image brand belum ada**: `public/assets/site/img/og-default.png` tidak ada.
- **Potensi performa render undangan**: `InvitationDataService::galleryDimensions()` memanggil `getimagesize()` remote pada URL Cloudinary/http per proses; `TemplateSnapshotService::htmlFor()` mengambil snapshot remote via HTTP tiap render. Ini diperbaiki secara minimal (cache + timeout).
- **Region Vercel vs Neon mismatch** (dari investigasi performa sebelumnya): deploy log menunjukkan `iad1`, Neon `ap-southeast-1`; menambah latensi DB/session/cache. `vercel.json` diberi `regions:["sin1"]`.
- **Belum diverifikasi di produksi**: Search Console, Rich Results, Facebook Sharing Debugger, X Card Validator, Lighthouse produksi, dan perilaku crawler sosial di deployment nyata belum dijalankan dari workspace ini.

## 2. Halaman yang dioptimasi

| Halaman / Area | Status | Catatan |
|---|---|---|
| Homepage `/` | Dioptimasi | Title, description, canonical, OG, Twitter, robots index, JSON-LD Organization/WebSite/WebPage. |
| Katalog template `/templates` | Dioptimasi | Meta description ditambahkan; memakai layout app SEO. |
| Login `/login` | Dioptimasi | `meta_description` + `meta_robots=noindex,nofollow`. |
| Share `/share` | Dioptimasi | `meta_description` + `meta_robots=noindex,nofollow`. |
| Public invitation `/u/{slug}` | Dioptimasi (kritis) | Metadata dinamis server-rendered via `HtmlRewriter`; OG/Twitter/canonical/robots/JSON-LD. |
| Template sample `/templates/{template}/sample` | Dioptimasi | Menggunakan renderer yang sama; sample/preview diberi `noindex,nofollow`. |
| Dashboard/customer/admin/transaksi/payment/invitations edit | Dilindungi | `layouts/customer.blade.php` dan `layouts/admin.blade.php` sudah `noindex,nofollow`; route privat tetap behind auth/authorization. |
| Sitemap `/sitemap.xml` | Ditambahkan | Dinamis, cache 60 menit, hanya URL publik indexable. |
| Robots `/robots.txt` | Diperbarui | Allow public, disallow private path, sitemap URL. |

## 3. Implementasi metadata

- **Layout publik**: `resources/views/layouts/app.blade.php` sekarang menghasilkan description, robots, canonical, OG (`type/site_name/locale/title/description/url/image/secure_url/type/width/height`), Twitter Card, title, dan `@stack('head_meta')`.
- **Layout privat**: `layouts/customer.blade.php` dan `layouts/admin.blade.php` tetap `noindex,nofollow`.
- **Halaman publik spesifik**: `welcome.blade.php`, `templates/index.blade.php`, `auth/login.blade.php`, `share/index.blade.php` diberi section meta yang sesuai.
- **Bahasa HTML**: layout `app/customer/admin` diset `lang="id"`; HtmlRewriter menormalkan `<html lang="id">` pada undangan yang dirender.

## 4. Implementasi Open Graph

Tag yang dihasilkan untuk undangan publik (server-rendered di HTML awal):

- `og:type=website`
- `og:site_name`
- `og:locale=id_ID`
- `og:title`
- `og:description`
- `og:url` (canonical)
- `og:image`
- `og:image:secure_url`
- `og:image:type`
- `og:image:width` / `og:image:height` (1200x630 untuk image non-SVG)
- Twitter: `twitter:card=summary_large_image`, `twitter:title`, `twitter:description`, `twitter:image`

Metadata diinject oleh `HtmlRewriter::injectHead()` setelah menghapus meta OG/robots/description/title lama dari template dump, sehingga tidak tergantung JS.

## 5. Logika metadata dinamis undangan

Sumber data: `InvitationViewData` dari `InvitationDataService` (data aktual DB: title, couple, message, share title/description/image, events, galleries, settings).

Prioritas:

- **Title**: `settings.share_title` jika ada; fallback `invitation.title - coupleLabel`.
- **Description**: `settings.share_description` jika ada; fallback `invitationMessage`; fallback deskripsi default pasangan. Dibersihkan dari tag HTML, whitespace dinormalisasi, dipotong aman 160 char.
- **Image**: `settings.share_image` (Tampilan saat Dibagikan) > cover dari `photoGallery.cover` > gallery pertama > default brand `assets/site/img/og-default.png`.
- **URL/canonical**: `endpoints.public` (`/u/{slug}`) untuk undangan nyata; fallback `request()->url()` pada web request non-console.
- **Robots**: `index, follow, max-image-preview:large` hanya untuk undangan publik aktif non-preview; `noindex, nofollow, noarchive` untuk sample/preview.
- **Cloudinary OG image**: jika URL Cloudinary, ditambahkan transformasi `c_fill,g_auto,w_1200,h_630,f_jpg,q_auto` untuk preview 1200x630 tanpa mengubah asset asli.

## 6. Penanganan gambar Cloudinary

- URL image dipaksa absolut via `absoluteUrl()`.
- `og:image:secure_url` dipaksa HTTPS bila mungkin.
- Cloudinary OG image mendapat transformasi crop 1200x630 JPG auto-quality (hanya URL OG, bukan asset konten asli).
- Default brand OG image lokal dibuat: `public/assets/site/img/og-default.png` (1200x630).
- Lazy loading/existing template behavior tidak diubah; optimasi hanya pada metadata dan cache dimensi.

## 7. Konfigurasi sitemap

- File: `app/Http/Controllers/Site/SitemapController.php`, route `GET /sitemap.xml` (`routes/web.php`).
- `vercel.json` ditambah rule `/sitemap.xml -> /api/index.php` sebelum rule ekstensi statis agar sitemap dinamis tidak tertangkap sebagai file statis.
- Termasuk: homepage, `/templates`, sample template aktif, undangan `ACTIVE` yang `active_expires_at` null/masa depan.
- Tidak termasuk: admin, dashboard, login, auth, invitations management, transactions, payment, share, private/draft/expired/suspended internal routes.
- XML valid, URL absolut HTTPS berbasis `config('app.url')`, `lastmod` dari `updated_at`, dedup URL, cache 60 menit.

## 8. Konfigurasi robots.txt

File: `public/robots.txt`

- Allow `/`.
- Disallow: `/admin`, `/dashboard`, `/login`, `/logout`, `/auth/`, `/invitations`, `/transactions`, `/payment/`, `/templates/*/select`, `/share`, `/api/`, `/storage/`.
- Sitemap: `https://undangan233.vercel.app/sitemap.xml` (static file; di Vercel saat ini dilayani sebagai static asset. Jika domain produksi berubah, perbarui file ini atau buat route dinamis + vercel rule).
- Tidak memblokir CSS/JS/image public.

## 9. Structured data (JSON-LD)

- **Homepage**: `Organization`, `WebSite`, `WebPage` via `@graph` JSON-LD.
- **Public invitation**: `WebSite`, `WebPage`, dan `Event` per event yang memiliki data valid; `startDate` ISO 8601 dengan timezone event; `location` Place hanya jika ada nama/alamat. Tidak menambah review/rating/palsu.
- JSON-LD di-escape aman (`JSON_HEX_TAG|HEX_AMP|HEX_APOS|HEX_QUOT`).

## 10. Peningkatan performa

- `InvitationDataService::galleryDimensions()`: cache dimensi remote di Laravel cache (TTL 30 hari, fallback 30 menit), timeout 2.5s untuk remote fetch.
- `TemplateSnapshotService::htmlFor()`: cache snapshot Cloudinary URL di `/tmp` warm instance; HTTP connect timeout 5s, timeout 15s.
- `SweepExpiredInvitations`: dipindah ke `terminate()` agar tidak memblokir TTFB; throttle cache 60s tetap menjaga frekuensi.
- `vercel.json`: `regions:["sin1"]` untuk mendekatkan runtime ke Neon ap-southeast-1; buildCommand mencoba `event:cache route:cache view:cache` jika PHP tersedia (fail-safe, tanpa `config:cache`).

## 11. Hasil pengujian

### Dijalankan

- `php -l` lulus untuk file PHP yang diubah: `HtmlRewriter.php`, `InvitationDataService.php`, `TemplateSnapshotService.php`, `SweepExpiredInvitations.php`, `SitemapController.php`, `routes/web.php`.
- `vendor/bin/pint` dijalankan; memperbaiki style pada `HtmlRewriter.php` dan `SitemapController.php`.
- Smoke render sample invitation via Laravel (`TemplateRenderer::renderSample`): terdeteksi `og:title`, `og:description`, `og:image`, `twitter:card`, `application/ld+json`, `name="robots"`; title dan description benar.
- Smoke render homepage/login Blade: meta OG/Twitter/canonical/robots/JSON-LD hadir; login `noindex,nofollow`.

### Tidak dijalankan / manual

- Google Search Console, Rich Results Test, Schema Markup Validator, Facebook Sharing Debugger, X/Twitter Card Validator, Lighthouse/PageSpeed produksi.
- Verifikasi crawler sosial real (WhatsApp/Facebook/Telegram/X) pada URL produksi setelah deploy.
- Verifikasi Vercel routing produksi untuk `/sitemap.xml` dan static `/robots.txt`.
- PHPUnit penuh (butuh PostgreSQL test DB sesuai `phpunit.xml`).

## 12. Masalah tersisa

- **Validator eksternal belum dijalankan**: perlu manual setelah deploy.
- **robots.txt domain statis**: saat ini berisi sitemap absolute ke `https://undangan233.vercel.app/sitemap.xml`; jika APP_URL/domain produksi berubah, update file atau ubah ke route dinamis.
- **Sitemap untuk /share?** `/share` diberi noindex dan tidak masuk sitemap; ini sesuai.
- **APP_DEBUG di .env lokal** terlihat `true` pada audit sebelumnya; pastikan production Vercel `APP_DEBUG=false`.
- **Vercel static asset routing**: perlu verifikasi di deploy bahwa asset tidak lewat PHP function.
- **Invitation OG image dimensions**: diset 1200x630 default saat transform Cloudinary/unknown; jika gambar asli non-Cloudinary dan bukan 1200x630, nilai width/height bisa tidak persis sama dengan file asli. Risiko kecil; crawler biasanya tetap menerima.
- **Sitemap lastmod homepage/templates** memakai `now()` sederhana; bisa ditingkatkan ke timestamp konten nyata.

## 13. Instruksi Google Search Console

1. Deploy perubahan ke domain produksi.
2. Buka Google Search Console, tambahkan property domain atau URL-prefix untuk domain INVITE produksi.
3. Verifikasi kepemilikan (DNS TXT disarankan).
4. Submit sitemap: `https://<domain-produksi>/sitemap.xml`.
5. Gunakan URL Inspection untuk homepage, `/templates`, dan satu `/u/{slug}` aktif; minta indexing bila perlu.
6. Gunakan Rich Results Test/Schema validator untuk homepage dan satu undangan aktif.
7. Gunakan Facebook Sharing Debugger dan validator kartu sosial untuk URL undangan aktif; pastikan og:image dapat diakses publik.
8. Pantau Coverage/Indexing, Core Web Vitals, dan enhancement structured data.

## Contoh HTML metadata yang dirender

### Homepage (indexable)

```html
<meta name="description" content="INVITE membantu Anda membuat undangan digital pernikahan yang elegan, personal, dan mudah dibagikan.">
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="https://undangan233.vercel.app">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Undangan Digital">
<meta property="og:locale" content="id_ID">
<meta property="og:title" content="Undangan Digital Pernikahan - Undangan Digital">
<meta property="og:description" content="INVITE membantu Anda membuat undangan digital pernikahan yang elegan, personal, dan mudah dibagikan.">
<meta property="og:url" content="https://undangan233.vercel.app">
<meta property="og:image" content="https://undangan233.vercel.app/assets/site/img/og-default.png">
<meta property="og:image:secure_url" content="https://undangan233.vercel.app/assets/site/img/og-default.png">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Undangan Digital Pernikahan - Undangan Digital">
<meta name="twitter:description" content="INVITE membantu Anda membuat undangan digital pernikahan yang elegan, personal, dan mudah dibagikan.">
<meta name="twitter:image" content="https://undangan233.vercel.app/assets/site/img/og-default.png">
<title>Undangan Digital Pernikahan - Undangan Digital</title>
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Organization","@id":"https://undangan233.vercel.app#organization","name":"Undangan Digital","url":"https://undangan233.vercel.app","logo":"https://undangan233.vercel.app/assets/site/img/brand-mark.svg"},{"@type":"WebSite","@id":"https://undangan233.vercel.app#website","url":"https://undangan233.vercel.app","name":"Undangan Digital","publisher":{"@id":"https://undangan233.vercel.app#organization"},"inLanguage":"id-ID"},{"@type":"WebPage","@id":"https://undangan233.vercel.app#webpage","url":"https://undangan233.vercel.app/","name":"Undangan Digital Pernikahan - Undangan Digital","isPartOf":{"@id":"https://undangan233.vercel.app#website"},"about":{"@id":"https://undangan233.vercel.app#organization"},"inLanguage":"id-ID"}]}</script>
```

### Public invitation/sample render (server-rendered, sample = noindex; active public invitation akan `index,follow` dan memiliki canonical/og:url ke `/u/{slug}`)

```html
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Contoh Thema 38 - Putri &amp; Putra</title>
<meta name="description" content="Tanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/I untuk menghadiri acara pernikahan kami.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Undangan Digital">
<meta property="og:locale" content="id_ID">
<meta property="og:title" content="Contoh Thema 38 - Putri &amp; Putra">
<meta property="og:description" content="Tanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/I untuk menghadiri acara pernikahan kami.">
<meta property="og:image" content="https://undangan233.vercel.app/assets/templates/tema1/wp-content/uploads/2024/07/pexels-azra-tuba-demir-12124342.jpg">
<meta property="og:image:secure_url" content="https://undangan233.vercel.app/assets/templates/tema1/wp-content/uploads/2024/07/pexels-azra-tuba-demir-12124342.jpg">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Contoh Thema 38 - Putri &amp; Putra">
<meta name="twitter:description" content="Tanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/I untuk menghadiri acara pernikahan kami.">
<meta name="twitter:image" content="https://undangan233.vercel.app/assets/templates/tema1/wp-content/uploads/2024/07/pexels-azra-tuba-demir-12124342.jpg">
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebSite","@id":"https://undangan233.vercel.app#website","url":"https://undangan233.vercel.app","name":"Undangan Digital","inLanguage":"id-ID"},{"@type":"WebPage","@id":"https://undangan233.vercel.app#webpage","url":"https://undangan233.vercel.app","name":"Contoh Thema 38 - Putri \u0026 Putra","description":"Tanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/I untuk menghadiri acara pernikahan kami.","isPartOf":{"@id":"https://undangan233.vercel.app#website"},"inLanguage":"id-ID","primaryImageOfPage":"https://undangan233.vercel.app/assets/templates/tema1/wp-content/uploads/2024/07/pexels-azra-tuba-demir-12124342.jpg"},{"@type":"Event","name":"Akad Nikah","description":"Tanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/I untuk menghadiri acara pernikahan kami.","eventAttendanceMode":"https://schema.org/OfflineEventAttendanceMode","eventStatus":"https://schema.org/EventScheduled","image":"https://undangan233.vercel.app/assets/templates/tema1/wp-content/uploads/2024/07/pexels-azra-tuba-demir-12124342.jpg","url":"https://undangan233.vercel.app","inLanguage":"id-ID","startDate":"2023-12-12T08:00:00+07:00","location":{"@type":"Place","name":"Mesjid Makmur Nunang","address":"Jl. Pajagalan No. 12"}},{"@type":"Event","name":"Resepsi","description":"Tanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/I untuk menghadiri acara pernikahan kami.","eventAttendanceMode":"https://schema.org/OfflineEventAttendanceMode","eventStatus":"https://schema.org/EventScheduled","image":"https://undangan233.vercel.app/assets/templates/tema1/wp-content/uploads/2024/07/pexels-azra-tuba-demir-12124342.jpg","url":"https://undangan233.vercel.app","inLanguage":"id-ID","startDate":"2023-12-12T10:00:00+07:00","location":{"@type":"Place","name":"Gedung Pernikahan","address":"Jl. Pajagalan No. 12"}}]}</script>
```

### Halaman yang tidak boleh diindex (login)

```html
<meta name="description" content="Masuk ke INVITE untuk membuat dan mengelola undangan digital pernikahan Anda.">
<meta name="robots" content="noindex, nofollow">
<link rel="canonical" href="https://undangan233.vercel.app">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Undangan Digital">
<meta property="og:locale" content="id_ID">
<meta property="og:title" content="Masuk - Undangan Digital">
<meta property="og:description" content="Masuk ke INVITE untuk membuat dan mengelola undangan digital pernikahan Anda.">
<meta property="og:url" content="https://undangan233.vercel.app">
<meta property="og:image" content="https://undangan233.vercel.app/assets/site/img/og-default.png">
<meta property="og:image:secure_url" content="https://undangan233.vercel.app/assets/site/img/og-default.png">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Masuk - Undangan Digital">
<meta name="twitter:description" content="Masuk ke INVITE untuk membuat dan mengelola undangan digital pernikahan Anda.">
<meta name="twitter:image" content="https://undangan233.vercel.app/assets/site/img/og-default.png">
<title>Masuk - Undangan Digital</title>
```
