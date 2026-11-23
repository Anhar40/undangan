<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitation_events', function (Blueprint $table): void {
            $table->string('dress_code', 120)->nullable()->after('description');
        });

        Schema::table('invitation_settings', function (Blueprint $table): void {
            $table->text('quote')->nullable()->after('custom_settings');
            $table->string('quote_source', 200)->nullable()->after('quote');
            $table->boolean('quote_is_default')->default(false)->after('quote_source');
            $table->string('share_title', 120)->nullable()->after('quote_is_default');
            $table->text('share_description')->nullable()->after('share_title');
            $table->string('share_image')->nullable()->after('share_description');
        });

        $this->backfillSettings();
        $this->backfillTemplateSnapshots();
    }

    public function down(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'share_image',
                'share_description',
                'share_title',
                'quote_is_default',
                'quote_source',
                'quote',
            ]);
        });

        Schema::table('invitation_events', function (Blueprint $table): void {
            $table->dropColumn('dress_code');
        });
    }

    private function backfillSettings(): void
    {
        $defaultQuote = trim((string) config('invitation.default_quote', ''));
        $defaultSource = trim((string) config('invitation.default_quote_source', ''));

        foreach (DB::table('invitation_settings')->get() as $row) {
            $custom = json_decode((string) ($row->custom_settings ?? ''), true);
            $custom = is_array($custom) ? $custom : [];

            $quote = filled($row->quote ?? null)
                ? (string) $row->quote
                : (string) ($custom['quote'] ?? '');
            $source = filled($row->quote_source ?? null)
                ? (string) $row->quote_source
                : (string) ($custom['quote_source'] ?? '');
            $isDefault = (bool) ($row->quote_is_default ?? false);

            if (trim($quote) === '') {
                $quote = $defaultQuote;
                $source = $defaultSource;
                $isDefault = true;
            }

            DB::table('invitation_settings')->where('id', $row->id)->update([
                'quote' => $quote,
                'quote_source' => $source !== '' ? $source : null,
                'quote_is_default' => $isDefault,
                'share_title' => (string) ($custom['share_title'] ?? '') ?: null,
                'share_description' => (string) ($custom['share_description'] ?? '') ?: null,
                'share_image' => (string) ($custom['share_image'] ?? '') ?: null,
            ]);
        }
    }

    private function backfillTemplateSnapshots(): void
    {
        foreach (DB::table('invitations')->get() as $row) {
            $snapshot = json_decode((string) ($row->template_snapshot ?? ''), true);

            if (! is_array($snapshot)) {
                continue;
            }

            $features = is_array($snapshot['features'] ?? null) ? $snapshot['features'] : [];
            $features['quote'] = array_key_exists('quote', $features) ? (bool) $features['quote'] : true;
            $features['multiple_events'] = array_key_exists('multiple_events', $features)
                ? (bool) $features['multiple_events']
                : true;
            $snapshot['features'] = $features;

            DB::table('invitations')->where('id', $row->id)->update([
                'template_snapshot' => json_encode($snapshot),
            ]);
        }
    }
};
