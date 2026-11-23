# Pemetaan Edit Invitation → Database → Template

Halaman **Edit Invitation** hanya terdiri dari sepuluh bagian berikut: `A Data Pasangan`, `B Acara dan Lokasi`, `C Cerita Pasangan`, `D Turut Mengundang`, `E Tampilan dan Media`, `F Galeri`, `G Musik`, `H Tamu, RSVP, dan Ucapan`, `I Amplop Digital`, dan `J Tampilan saat Dibagikan`. Tidak ada bagian URL atau WhatsApp tersendiri.

| Edit Section | Form Field | Database Field | Template Section | Display Condition |
| --- | --- | --- | --- | --- |
| A Data Pasangan | `title` | `invitations.title` | Head metadata, dashboard, judul undangan | Selalu digunakan; wajib diisi |
| A Data Pasangan | `groom[full_name]`, `groom[nickname]`, `groom[father_name]`, `groom[mother_name]`, `groom[instagram]`, `groom[social_links]` | `invitation_grooms.*` | Widget mempelai pria | Nilai kosong tidak dirender |
| A Data Pasangan | `photos[groom]`, `remove_photo[groom]` | `invitation_grooms.photo` | Foto mempelai pria | Hanya saat foto tersedia |
| A Data Pasangan | `bride[full_name]`, `bride[nickname]`, `bride[father_name]`, `bride[mother_name]`, `bride[instagram]`, `bride[social_links]` | `invitation_brides.*` | Widget mempelai wanita | Nilai kosong tidak dirender |
| A Data Pasangan | `photos[bride]`, `remove_photo[bride]` | `invitation_brides.photo` | Foto mempelai wanita | Hanya saat foto tersedia |
| B Acara dan Lokasi | `settings[invitation_message]` | `invitation_settings.custom_settings.invitation_message` | Pesan undangan pada cover | Hanya saat tidak kosong |
| B Acara dan Lokasi | `settings[quote]`, `settings[quote_source]` | `invitation_settings.quote`, `invitation_settings.quote_source` | Widget kutipan (sumber tunggal untuk kedua template) | `features.quote` aktif dan kutipan tidak kosong |
| B Acara dan Lokasi | `settings[quote_action]=default` | `invitation_settings.quote`, `quote_source`, `quote_is_default` | Widget kutipan | Tombol pulihkan; menimpa kutipan kustom dengan nilai bawaan |
| B Acara dan Lokasi | `settings[timezone]` | `invitation_settings.custom_settings.timezone`, fallback `invitation_events.timezone` | Countdown | Hanya saat tanggal acara tersedia |
| B Acara dan Lokasi | `events[*][event_type]`, `title`, `event_date`, `start_time`, `end_time`, `timezone`, `location_name`, `address`, `maps_url`, `description`, `dress_code`, `sort_order` | `invitation_events.*` | Blok acara pada template | Hanya acara dengan `event_date`; urutan mengikuti `sort_order` |
| B Acara dan Lokasi | `events[*][live_stream_url]` | `invitation_events.live_stream_url` | Tombol live streaming | Hanya `tema1` karena `features.live_stream` tidak didukung `tema2` |
| C Cerita Pasangan | `stories[*][title]`, `story_date`, `description`, `sort_order` | `invitation_stories.*` | Widget cerita pasangan | `features.story` aktif dan minimal satu cerita berisi |
| C Cerita Pasangan | `stories_images[*]`, `stories_remove_image[*]` | `invitation_stories.image` | Foto cerita | Hanya saat foto tersedia |
| D Turut Mengundang | `families[*][name]`, `sort_order` | `invitation_families.*` | Widget turut mengundang | `features.families` aktif dan minimal satu nama berisi |
| E Tampilan dan Media | `settings[theme]`, `primary_color`, `secondary_color`, `font_family` | `invitation_settings.theme`, `primary_color`, `secondary_color`, `font_family` | Variabel tema CSS | Hanya bila `features.theme` aktif |
| E Tampilan dan Media | `photos[cover]`, `remove_photo[cover]` | `invitations.cover_photo` | Latar sampul | Dipakai bila slot latar `cover` belum diunggah |
| E Tampilan dan Media | `backgrounds[slot][]`, `background_remove[slot]` | `invitation_settings.custom_settings.backgrounds` | Slideshow latar | Hanya slot yang dideklarasikan template (`cover`, `groom`, `bride`, `closing`) |
| F Galeri | `gallery_items[*][caption]`, `is_cover`, `sort_order` | `invitation_galleries.*` | Widget galeri | `features.gallery` aktif dan minimal satu foto tersedia |
| F Galeri | `gallery_images[*]`, `gallery_new[]`, `gallery_remove_image[*]` | `invitation_galleries.image` | Widget galeri | Foto utama dirender lebih dulu; galeri kosong disembunyikan |
| G Musik | `settings[music_id]` | `invitation_settings.music_id`, `music_url` | Pemutar musik | `features.music` aktif dan musik dipilih |
| G Musik | `settings[music_autoplay]` | `invitation_settings.music_autoplay` | Konfigurasi pemutar musik | Hanya saat musik dipilih |
| H Tamu, RSVP, dan Ucapan | `guests[*][guest_name]`, `attendance_status`, `guest_count`, `message` | `invitation_guests.*` | Tautan `?to=Nama+Tamu`, formulir RSVP, daftar tamu | `features.rsvp` aktif dan data tamu diisi |
| H Tamu, RSVP, dan Ucapan | `guests[*][id]`, `guest_remove` | `invitation_guests.id` | Daftar tamu | Penghapusan hanya untuk tamu milik undangan ini |
| H Tamu, RSVP, dan Ucapan | `greetings[*][status]` | `invitation_greetings.status` | Buku tamu | Hanya ucapan berstatus `approved` yang tampil di publik |
| H Tamu, RSVP, dan Ucapan | `greeting_remove` | `invitation_greetings.id` | Buku tamu | Penghapusan hanya untuk ucapan milik undangan ini |
| I Amplop Digital | `gift_accounts[*][label]`, `account_name`, `account_number`, `gift_address`, `sort_order` | `invitation_gift_accounts.*` | Widget amplop digital | `features.gift` aktif dan minimal satu rekening/alamat berisi |
| I Amplop Digital | `gift_qr_images[*]`, `gift_remove_qr[*]` | `invitation_gift_accounts.qr_image` | QRIS amplify | Hanya saat QR diunggah |
| J Tampilan saat Dibagikan | `settings[share_title]` | `invitation_settings.share_title` | `<title>` dan `og:title` | Nilai kustom; fallback ke judul + nama pasangan |
| J Tampilan saat Dibagikan | `settings[share_description]` | `invitation_settings.share_description` | `meta description` dan `og:description` | Nilai kustom; fallback ke pesan undangan |
| J Tampilan saat Dibagikan | `share_image`, `share_image_remove` | `invitation_settings.share_image` | `og:image` | Hanya saat gambar diunggah |
| J Tampilan saat Dibagikan | `settings[share_message]` | `invitation_settings.whatsapp_message` | Halaman `/share` | Dipakai sebagai template pesan berbagi |
| J Tampilan saat Dibagikan | `share-public-url` (read-only) | `invitations.slug` | Halaman `/share`, metadata `og:url` | Diturunkan otomatis; bukan field yang dapat diedit di halaman ini |

## Catatan dukungan template

- `tema1` dan `tema2` sama-sama mendukung cerita, galeri, musik, tema, RSVP, keluarga, amplop, countdown, kutipan, dan beberapa acara.
- Hanya `tema1` yang mendukung `live_stream`; field tersebut tidak dirender dan tidak disimpan untuk `tema2`.
- Kutipan berasal dari satu sumber bersama (`config/invitation.php`) dan disalin ke `invitation_settings` saat undangan dibuat. Kedua template membaca kutipan tersimpan, bukan teks hardcoded.
- Visibilitas bagian opsional mengikuti data aktual, bukan toggle lama `show_*`. Bagian kosong selalu disembunyikan.
- Slug dan nomor WhatsApp kontak tetap kompatibel di backend, tetapi tidak memiliki bagian editor tersendiri. Slug dikelola melalui alur aktivasi/URL publik; pesan berbagi dikelola di bagian J.
- Setiap bagian memiliki tombol **Simpan Bagian** sendiri. Simpanan hanya mengirim field milik bagian tersebut agar nilai di bagian lain tidak berubah.
