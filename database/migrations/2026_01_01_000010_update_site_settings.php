<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('app_name', config('app.name'));
        Setting::set('site_tagline', 'Undangan digital yang hangat, personal, dan mudah dibagikan.');
        Setting::set('site_contact_email', (string) config('mail.from.address'));

        Cache::forget('setting:app_name');
        Cache::forget('setting:site_tagline');
        Cache::forget('setting:site_contact_email');
    }

    public function down(): void
    {
        Cache::forget('setting:app_name');
        Cache::forget('setting:site_tagline');
        Cache::forget('setting:site_contact_email');
    }
};
