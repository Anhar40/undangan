# Template / Data Consistency Audit Report

## 1. Scope and outcome

This report closes the end-to-end audit for:

- `template/tema1.html` (Thema 38) and `template/tema2.html` (Thema 40)
- the **Kelola Undangan** form (`resources/views/invitations/edit.blade.php`)
- request validation (`app/Http/Requests/Customer/UpdateInvitationRequest.php`)
- persistence (`app/Services/Invitation/InvitationEditorService.php`)
- the shared view model (`app/Data/*`, `app/Services/Invitation/InvitationDataService.php`)
- template renderers (`AbstractTemplateRenderer`, `HtmlRewriter`, `Tema1Renderer`, `Tema2Renderer`)
- the public invitation at `GET /u/{slug}`

Result: **both templates, the editor form, the database, and the public invitation now use one consistent data structure.** Customer-entered values are rendered verbatim (with correct HTML escaping); empty or unsupported widgets are hidden or removed; no sample/default text, sample photos, sample audio, `undefined`, `null`, or broken placeholder data remains on a real invitation. The sample preview still intentionally shows the original WordPress sample content.

Template HTML source files were not redesigned. They are only parsed and transformed by the renderers, and static first-party assets are served from `public/assets/site/` and mirrored template folders.

---

## 2. Canonical data contract

| Data group | Form location | Database field(s) | Shared view model |
|---|---|---|---|
| Invitation title / slug | `Data Mempelai`, `Pengaturan URL` | `invitations.title`, `invitations.slug`, `invitation_slug_redirects.old_slug` | `InvitationViewData::$title`, `::$slug` |
| Couple | `Data Mempelai` | `invitation_brides.full_name/nickname/photo/father_name/mother_name/instagram/social_links`, `invitation_grooms.*` | `PersonViewData` |
| Events | `Informasi Acara` | `invitation_events.event_type/title/event_date/start_time/end_time/timezone/location_name/address/maps_url/description/live_stream_url/sort_order` | `EventViewData` |
| Story | `Cerita Pasangan` | `invitation_stories.title/description/story_date/image/sort_order` | `StoryViewData` |
| Families | `Turut Mengundang` | `invitation_families.name/sort_order` | `InvitationViewData::$families` |
| Gallery | `Foto dan Galeri` | `invitation_galleries.image/caption/is_cover/sort_order` | `GalleryViewData` |
| Gift / amplify | `Pengaturan Tampilan → Amplop Digital` | `invitation_gift_accounts.label/account_name/account_number/qr_image/gift_address/sort_order` | `GiftAccountViewData` |
| Settings / text | `Pengaturan Tampilan` | `invitation_settings.theme/primary_color/secondary_color/font_family/show_*/custom_settings.invitation_message/quote/quote_source/timezone/gallery_limit` | `InvitationViewData::$features`, `::$theme`, `::$invitationMessage`, `::$quote`, `::$quoteSource` |
| Music | `Musik` | `invitation_settings.music_id/music_url/music_autoplay`, `musics.file_path` | `InvitationViewData::$musicUrl`, `::$musicAutoplay` |
| WhatsApp contact | `Pengaturan WhatsApp` | `invitation_settings.whatsapp_number/whatsapp_display_name/whatsapp_message` | `InvitationViewData::$whatsapp` |
| Guest personalization | Public `?to=Nama+Tamu` | request query only (not stored) | `InvitationViewData::$guestName` |
| Guestbook / RSVP | Public form | `invitation_greetings.guest_name/message/attendance_status/status/ip_address` | `GreetingViewData` |
| Template snapshot | Template selection at creation/switch | `invitations.template_name/template_version/template_path/template_snapshot/template_locked_at` | `InvitationViewData::$features`, `::$backgrounds`, `::$key` |

Rules enforced in code:

- Public pages are reachable only when `invitations.status = ACTIVE`.
- Activation requires title, both couple names, and at least one dated event.
- Activation happens only from a verified, idempotent Midtrans webhook; the payment-success redirect never activates.
- Status and payment status remain separate fields/machines.
- `Super Admin` and `Customer` remain literal role tokens; status tokens remain `DRAFT`, `PREVIEW`, `EXPIRED`, `PENDING_PAYMENT`, `ACTIVE`, `SUSPENDED`.
- Feature flags come from the frozen `template_snapshot`; missing flags fail closed.
- WhatsApp numbers are normalized to digits beginning with `62` for the contact link; share links remain phone-less `wa.me/?text=...`.
- Slug changes create `invitation_slug_redirects` rows and 301 redirects.

---

## 3. Tema1 matrix (Thema 38)

`Template Section | Required Data | Form Location | Database Field | Display Condition`

| Template Section | Required Data | Form Location | Database Field | Display Condition |
|---|---|---|---|---|
| Cover (section `3fe3ed95`; widgets `3058ec24`, `1a06cffd`, `7fac8a76`, `13931df3`) | Couple label, first event date, guest name | `Data Mempelai`; `Informasi Acara`; public `?to=` | `invitation_brides/grooms.full_name/nickname`; `invitation_events.event_date` | Always. Empty customer values hide individual widgets; guest falls back to empty, never `Nama Tamu`. |
| Intro banner (`35a43271`, `3efec018`) | Couple label | `Data Mempelai` | `invitation_brides/grooms.full_name/nickname` | Couple label non-empty; sample animated headline is replaced. |
| Quote (`4a41ce2f`) | Quote and optional source | `Pengaturan Tampilan` | `invitation_settings.custom_settings.quote`, `.quote_source` | Widget hidden when both are empty. Source is appended once. |
| Invitation message (`7e5d7ae6`) | Message text | `Pengaturan Tampilan` | `custom_settings.invitation_message` | Widget hidden when empty. |
| Bride block (`5d7ac283`, `7d5344c4`, `7d466437`, `3ecedd8e`) | Full name, parents, Instagram, social links, photo/background | `Data Mempelai`; `Foto dan Galeri` | `invitation_brides.*`; `custom_settings.backgrounds.bride` | Block hidden when the bride record is empty; parents/Instagram hidden individually. Extra social links appended as real buttons. |
| Groom block (`5c22588c`, `5de56291`, `1aeac5e5`, `190cf58c`) | Full name, parents, Instagram, social links, photo/background | `Data Mempelai`; `Foto dan Galeri` | `invitation_grooms.*`; `custom_settings.backgrounds.groom` | Block hidden when the groom record is empty; parents/Instagram hidden individually. |
| Events (`7acb9878`, `4905ecf2`, `7a456843`, `2fa44f42`, `4c13b865`, `6e2ca33`, `3dfc7066`, and second slot `efd8c41` … `1786d3be`) | Type, title, date, start/end time, timezone, location, address, maps URL, description | `Informasi Acara` | `invitation_events.*` | First two events fill the two designed slots; additional events render in an appended section. Missing slot hides its widgets. Address gets a working copy button. |
| Countdown (`5df715d2`, `5fe8a914`) | First event date/time | `Informasi Acara` | `invitation_events.event_date`, `start_time`, `timezone` | `features.countdown = true` and a parseable event date; `data-date` becomes a Unix timestamp. |
| Live streaming (`459ad270`, `650f0858`) | Live URL | `Informasi Acara` | `invitation_events.live_stream_url` | `features.live_stream = true` and first event has a URL; a real `Tonton Live Streaming` button is appended after `650f0858`. Hidden otherwise. |
| Gallery (`45a07475`) | Gallery images, captions, cover flag, order | `Foto dan Galeri` | `invitation_galleries.image/caption/is_cover/sort_order`; `custom_settings.gallery_limit` | `features.gallery = true`, toggle on, and at least one image. Cover image is forced first; empty gallery hides whole section. |
| Story (`6ff5471e`, `79a78ccc`, `669f1889`, `129b7a4d`, `6d811651`, `e4e8146`, `5fa78dd2`) | Title, description, date, image, order | `Cerita Pasangan` | `invitation_stories.*` | `features.story = true`, toggle on, and at least one story. Extra stories append below story 3. Items missing image/title/description hide only those widgets. |
| Gift (`7c0478bd`, `3c4d725e`, `10a2d3a`; second `70990778`, `7bbcff2d`, `60d7dd04`) | Label, account name, account number or gift address, QR image | `Pengaturan Tampilan → Amplop Digital` | `invitation_gift_accounts.*` | `features.gift = true`, toggle on, and at least one account. Each slot hides when empty; QR image hides without upload. |
| Families (`5bca0080`) | Family names | `Turut Mengundang` | `invitation_families.name/sort_order` | `features.families = true`, toggle on, and at least one family. No fallback family text. |
| RSVP / guestbook (`393379a3`, `71e33806`) | Guest name, message, attendance | Public form | `invitation_greetings.*` | `features.rsvp = true` and toggle on. WordPress form replaced with Laravel action, CSRF, honeypot, attendance select. Approved greetings render; empty list shows neutral first-greeting text. |
| Closing (`27998b08`) | Family names, couple label, background | `Turut Mengundang`, `Data Mempelai`, `Foto dan Galeri` | `invitation_families.*`; `invitation_brides/grooms.*`; `custom_settings.backgrounds.closing` | Families widget hidden when empty; sample closing title/paragraph removed; couple label rendered. |
| Backgrounds (`3fe3ed95`, `5c22588c`, `5d7ac283`, `27998b08`) | Uploaded images per slot | `Foto dan Galeri → Latar Template` | `custom_settings.backgrounds.*` | Only customer uploads appear; sample slideshow JSON is explicitly emptied. Bride/groom slots fall back to their portrait when no dedicated background exists. |
| Music (`67c4eb6`, `audio#song`) | Selected catalog track, autoplay | `Musik` | `invitation_settings.music_id/music_autoplay`; `musics.file_path` | `features.music = true` and a music URL exists; otherwise audio element and container are removed. |
| Theme (`body` CSS variables) | Primary/secondary color, font | `Pengaturan Tampilan` | `invitation_settings.primary_color/secondary_color/font_family` | `features.theme = true` and at least one value set. |
| WhatsApp contact (runtime floating button) | Number, display name, message | `Pengaturan WhatsApp` | `invitation_settings.whatsapp_*` | Normalized number non-empty; `wa.me/{number}` link injected once. |
| Share bar (runtime) | Title, public URL | automatic | `invitations.title`, `invitations.slug` | Real non-preview invitation; WhatsApp/Facebook/Telegram and copy-link controls. |

**Removed from real tema1 invitations:** sample couple carousel `542f3a1f`, decorative photos `5a32762e`, `1d6ce7b6`, `3d7facad`, sample closing copy `380098b4`, `3c9febd0`, and the third donation-address block `61e9402c`/`64277dba`/`3799206a`. The third block has no separate customer-owned slot; gift address is still rendered inside the two supported gift slots via `gift_address`.

---

## 4. Tema2 matrix (Thema 40)

`Template Section | Required Data | Form Location | Database Field | Display Condition`

| Template Section | Required Data | Form Location | Database Field | Display Condition |
|---|---|---|---|---|
| Cover (section `5705aa8`; widgets `4f987902`, `5a814787`, `1e07ef3f`, `4cd1d220`) | Couple label, first event date, guest name | `Data Mempelai`; `Informasi Acara`; public `?to=` | `invitation_brides/grooms.full_name/nickname`; `invitation_events.event_date` | Always. Individual widgets hide when empty; sample names/date/guest text never leak. |
| Save the date / countdown (`4ead5e59`, `30d71a57`, `5e524221`) | First event date/time | `Informasi Acara` | `invitation_events.event_date/start_time/timezone` | `features.countdown = true` and a parseable date; text-style countdown uses `data-date` string. |
| Intro couple header (`63dc565a`, `628426a0`) | Couple label | `Data Mempelai` | `invitation_brides/grooms.full_name/nickname` | Couple label non-empty. |
| Invitation message (`3afb4993`) | Message text | `Pengaturan Tampilan` | `custom_settings.invitation_message` | Widget hidden when empty. |
| Couple blocks (`6c936581`, `7158d3b2`, `7368c603`, `1a8ab2c4`; `446761ce`, `39e1866d`, `636f3f63`, `76dba0dd`) | Full name, parents, Instagram, social links, photo/background | `Data Mempelai`; `Foto dan Galeri` | `invitation_brides.*`; `invitation_grooms.*`; `custom_settings.backgrounds.*` | Block hidden when person record empty; parents/Instagram hidden individually; extra social links appended. |
| Events (`627ffbff`, `7df69d59`, `4f8a1ec1`, `6f815f3f`, `134262a4`, `6b100790`, `76fd03e5`, `28f5d021` and second slot `4f74390e` … `2e74f9d6`) | Type, title, date, start/end time, timezone, location, address, maps URL, description | `Informasi Acara` | `invitation_events.*` | First two events fill the designed slots; extra events append. Missing slot/widget data hides that widget. |
| Quote source (`1752312c`, `3ba1c8f4`) | Quote and source separately | `Pengaturan Tampilan` | `custom_settings.quote`, `.quote_source` | Source widget hidden when source empty; quote hidden when both empty. Rendered once. Corrected raw output is `Doa Restu &amp; Ucapan` for the neutral guestbook heading. |
| Gallery (`1a5fc17a`) | Gallery images, captions, cover flag, order | `Foto dan Galeri` | `invitation_galleries.*`; `custom_settings.gallery_limit` | `features.gallery = true`, toggle on, images exist. Cover first; empty hides section. |
| Gift (`7cf88fd6`, `265ec209`, `6d98f144`, `7eb8f639`, `19b4f468`; second `3c3bc048`, `4d72bbb8`, `55fdfd25`, `7a9e4961`) | Label, account name, number or address, QR | `Pengaturan Tampilan → Amplop Digital` | `invitation_gift_accounts.*` | `features.gift = true`, toggle on, at least one account. Empty slots and missing QR hidden. |
| Story (`167a0beb`, `6a846cbf`, `1ddb084a`, `3ff8d429`, `464015fa`, `30d817a3`) | Title, description, date, image, order | `Cerita Pasangan` | `invitation_stories.*` | `features.story = true`, toggle on, at least one story; extra stories append; missing image/title/description hidden. |
| Wedding Wish / RSVP (`1bbe5ed5`, `37dc99e7`) | Guest name, message, attendance | Public form | `invitation_greetings.*` | `features.rsvp = true` and toggle on. Sample `Wedding Wish` replaced with neutral `Doa Restu & Ucapan`; Laravel form wiring and approved greetings rendered. |
| Closing / families (`74116615`, `5a5b4f7e`) | Families, couple label, background | `Turut Mengundang`, `Data Mempelai`, `Foto dan Galeri` | `invitation_families.*`; `invitation_brides/grooms.*`; `custom_settings.backgrounds.closing` | Families hidden when empty; couple label rendered; sample closing paragraphs removed. |
| Backgrounds (`56efded2`, `1a8ab2c4`, `76dba0dd`, `a09598`) | Uploaded images per slot | `Foto dan Galeri → Latar Template` | `custom_settings.backgrounds.*` | Only customer uploads; sample slideshow data emptied. |
| Music (`35cb1f`, `audio#song`) | Selected catalog track, autoplay | `Musik` | `invitation_settings.music_id/music_autoplay`; `musics.file_path` | `features.music = true` and URL exists; otherwise removed. |
| Theme | Primary/secondary color, font | `Pengaturan Tampilan` | `invitation_settings.primary_color/secondary_color/font_family` | `features.theme = true` and value set. |
| WhatsApp contact / share bar (runtime) | Number/name/message, title, URL | `Pengaturan WhatsApp`; automatic | `invitation_settings.whatsapp_*`; `invitations.title/slug` | Same rules as tema1. |

**Tema2-specific limits:** `features.live_stream = false`. The live-stream URL field is not exposed for this template and no live section is rendered, even if stale data exists in the database. Sample couple carousel `3fba8d42`, decorative sample image `64d9be4f`, sample closing paragraphs `5713d21a`/`1ff6157c`, and sample gift image `19b4f468` (without customer QR) are removed from real invitations.

---

## 5. Feature flags and editor controls

| Flag | tema1 | tema2 | Editor controls driven by flag |
|---|---:|---:|---|
| `story` | true | true | `Cerita Pasangan` rows, add/remove, toggle |
| `gallery` | true | true | gallery rows, uploads, cover flag, toggle |
| `music` | true | true | `Musik` select and autoplay |
| `theme` | true | true | colors and font inputs |
| `rsvp` | true | true | RSVP/guestbook toggle |
| `families` | true | true | `Turut Mengundang` rows and toggle |
| `gift` | true | true | `Amplop Digital` rows and toggle |
| `live_stream` | true | false | event live URL shown only for tema1 |
| `countdown` | true | true | countdown widget/selector driven by event date |
| `backgrounds` | cover, groom, bride, closing | cover, groom, bride, closing | background slots shown only when present in snapshot |

All flags are copied into `invitations.template_snapshot` when the invitation is created or switched before activation. After activation the template is locked (`template_locked_at`, with `activated_at` fallback), so later admin template edits cannot change an existing invitation.

---

## 6. Verification checklist

Legend: ✅ verified; **N/A** = not applicable because the data is mandatory or runtime-generated.

### Tema1

| Section / widget group | Supported | Connected to form field | Connected to DB field | Correctly rendered | Correctly hidden when empty | Successfully tested |
|---|---|---|---|---|---|---|
| Cover / intro | ✅ | ✅ | ✅ | ✅ | ✅ individual widgets | ✅ |
| Couple, parents, socials | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Events, map, copy address | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Countdown | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Live stream | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Gallery + cover order | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Story | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Gift + QR | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Families | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| RSVP / guestbook | ✅ | **N/A** public form | ✅ | ✅ | ✅ | ✅ |
| Backgrounds | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Music | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Theme / WhatsApp / share | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Sample content removal | ✅ | **N/A** | **N/A** | ✅ | ✅ | ✅ |

### Tema2

| Section / widget group | Supported | Connected to form field | Connected to DB field | Correctly rendered | Correctly hidden when empty | Successfully tested |
|---|---|---|---|---|---|---|
| Cover / save the date | ✅ | ✅ | ✅ | ✅ | ✅ individual widgets | ✅ |
| Couple, parents, socials | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Events + map | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Countdown | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Live stream | **N/A** (unsupported) | **N/A** | **N/A** | **N/A** | ✅ always hidden | ✅ |
| Gallery + cover order | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Story | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Gift + QR | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Quote / source | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Families | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| RSVP / guestbook | ✅ | **N/A** public form | ✅ | ✅ | ✅ | ✅ |
| Backgrounds | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Music | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Theme / WhatsApp / share | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Sample content removal | ✅ | **N/A** | **N/A** | ✅ | ✅ | ✅ |

---

## 7. Test matrix and final results

| Case | Evidence |
|---|---|
| Both templates, all populated fields | `test_both_templates_render_every_customer_field_exactly` |
| Both templates, every optional field empty | `test_both_templates_hide_empty_optional_sections_without_placeholders` |
| Save → public reflection | `test_saved_changes_are_reflected_on_public_invitation` |
| Template switch before activation / lock after | `test_template_switch_is_allowed_before_activation_and_locked_after` |
| Cross-customer isolation | `test_customers_cannot_access_another_customers_invitation` |
| Missing relations and null values | `test_missing_relations_and_null_values_render_safely` |
| Invalid/incomplete activation | `test_activation_requires_complete_customer_information` |
| Numeric-leading date corruption regression | `test_numeric_leading_date_is_not_corrupted` |
| Quote/source rendered once | `test_tema2_quote_source_is_rendered_once` |
| Gallery cover first + removal hides section | `test_gallery_cover_is_first_and_gallery_removal_hides_section` |
| Music, maps, social links, WhatsApp, backgrounds, QR, guestbook/RSVP | `test_both_templates_wire_media_widgets_and_public_endpoints` |
| Mobile and desktop parity | `test_mobile_and_desktop_render_the_same_customer_data` |

Final verification performed after the last code changes:

- `php -l` on all changed renderer/data/test files: **no syntax errors**
- `php artisan test --compact`: **82 passed (1026 assertions)**
- `render_dump.php` + `scan_render.py` for empty and filled tema1/tema2: **SAMPLE HITS: []** for all four renders
- Raw tema2 heading check: contains `Doa Restu &amp; Ucapan`; does **not** contain `Doa Restu &amp;amp; Ucapan` and no longer contains `Wedding Wish`
- Public page checks confirm no hotlinked sample images, no sample audio, no `undefined`, and no `>null<`
- Live browser verification at **1280×800 desktop** and **390×844 mobile** widths for both active invitations:
  - no console errors on either public page
  - `?to=Budi Santoso` personalization present at both sizes
  - all runtime-hidden IDs applied by CSS/JS at both sizes
  - no horizontal overflow (`scrollWidth === viewport`) at either size
  - no sample text, no `bukaundangan.com`/`api.sewaundangan.com` hotlinks, no `undefined`/`>null<`
  - guestbook form action resolves to the invitation-scoped Laravel route at both sizes
  - visible customer text is identical at desktop and mobile widths; viewport meta present

---

## 8. Limitations and deliberate exclusions

1. **Sample preview is intentionally different.** `forSample()` keeps the original WordPress sample text/photos/audio so admins can preview the untouched template. Real invitations never use those fallbacks.
2. **Tema1 third gift block.** The donation-address column is a fixed template sample with no customer-owned slot; it is removed from real invitations. Customer gift addresses remain rendered in the two supported gift slots when no account number is supplied.
3. **Tema2 live stream.** `features.live_stream = false` for Thema 40; there is no live-stream slot to fill. The field is hidden in the editor for that template.
4. **External assets are mirrored.** The originals hotlink `bukaundangan.com`, Google Fonts, and `api.sewaundangan.com`; the renderer points local files under `/assets/templates/...` and `/assets/site/...` so real invitations do not depend on those third-party URLs.
5. **Shared-hosting safety.** Activation and expired-preview cleanup do not require an always-running queue daemon.

---

## 9. Acceptance

THE INVITATION TEMPLATE, KELOLA UNDANGAN FORM, DATABASE, AND PUBLIC INVITATION MUST ALL USE THE SAME CONSISTENT DATA STRUCTURE. WHAT THE CUSTOMER ENTERS MUST BE EXACTLY WHAT THE PUBLIC INVITATION DISPLAYS.
