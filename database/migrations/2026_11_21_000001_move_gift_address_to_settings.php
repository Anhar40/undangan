<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The editor now stores one shared delivery address in invitation_settings.
     * Move the first legacy per-account address once, then clear the old
     * columns. Running the migration again is harmless.
     */
    public function up(): void
    {
        $rows = DB::table('invitation_gift_accounts')
            ->select('invitation_id', 'gift_address')
            ->orderBy('id')
            ->get();

        foreach ($rows->groupBy('invitation_id') as $invitationId => $accounts) {
            $address = $accounts
                ->pluck('gift_address')
                ->map(fn ($value) => trim((string) $value))
                ->first(fn (string $value): bool => $value !== '');

            if ($address === null) {
                continue;
            }

            $settings = DB::table('invitation_settings')
                ->where('invitation_id', $invitationId)
                ->first();

            $custom = [];
            if ($settings !== null && filled($settings->custom_settings)) {
                $decoded = json_decode((string) $settings->custom_settings, true);
                $custom = is_array($decoded) ? $decoded : [];
            }

            if (trim((string) ($custom['gift_address'] ?? '')) === '') {
                $custom['gift_address'] = $address;
            }

            if ($settings === null) {
                DB::table('invitation_settings')->insert([
                    'invitation_id' => $invitationId,
                    'custom_settings' => json_encode($custom, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('invitation_settings')
                    ->where('id', $settings->id)
                    ->update([
                        'custom_settings' => json_encode($custom, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }

            DB::table('invitation_gift_accounts')
                ->where('invitation_id', $invitationId)
                ->update(['gift_address' => null]);
        }
    }

    public function down(): void
    {
        // Per-row addresses cannot be reconstructed reliably from one shared
        // address. Keep the migrated settings value instead of losing data.
    }
};
