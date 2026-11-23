<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'app_name' => config('app.name'),
            'site_tagline' => 'Undangan digital yang hangat, personal, dan mudah dibagikan.',
            'site_contact_email' => (string) config('mail.from.address'),
            'activation_price' => (string) config('invitation.default_price', 150000),
            'preview_hours' => (string) config('invitation.preview_hours', 10),
            'public_url_base' => rtrim(config('app.url'), '/'),
            'rsvp_notification_email' => '',
            'contact_email' => '',
            'seo_description' => 'Undangan digital pernikahan yang dapat dipersonalisasi dan dibagikan secara online.',
        ];

        foreach ($settings as $key => $value) {
            if (! Setting::query()->where('key', $key)->exists()) {
                Setting::set($key, $value);
            }
        }
    }
}
