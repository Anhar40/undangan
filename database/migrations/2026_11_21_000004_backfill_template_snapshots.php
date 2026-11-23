<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy invitations with no snapshot used to read live template features.
     * Freeze the current feature set once, then the model never consults the
     * live template again.
     */
    public function up(): void
    {
        DB::table('invitations')
            ->select('id', 'template_id')
            ->whereNull('template_snapshot')
            ->orderBy('id')
            ->chunkById(100, function ($invitations): void {
                foreach ($invitations as $invitation) {
                    $template = DB::table('templates')
                        ->select('features', 'config')
                        ->where('id', $invitation->template_id)
                        ->first();

                    $features = $template?->features
                        ? json_decode((string) $template->features, true)
                        : [];
                    $config = $template?->config
                        ? json_decode((string) $template->config, true)
                        : [];

                    DB::table('invitations')
                        ->where('id', $invitation->id)
                        ->update([
                            'template_snapshot' => json_encode([
                                'features' => is_array($features) ? $features : [],
                                'config' => is_array($config) ? $config : [],
                            ]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Snapshot backfill is intentionally irreversible; original nulls cannot
        // be distinguished from snapshots written by this migration.
    }
};
