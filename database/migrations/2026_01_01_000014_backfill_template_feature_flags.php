<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing installations already had the two shipped templates before the
     * quote and multi-event flags existed. Refreshing their feature payloads
     * keeps newly created invitations aligned with a fresh install.
     */
    public function up(): void
    {
        foreach (DB::table('templates')->whereIn('slug', ['tema1', 'tema2'])->get() as $row) {
            $features = json_decode((string) ($row->features ?? ''), true);
            $features = is_array($features) ? $features : [];

            $features['quote'] = array_key_exists('quote', $features)
                ? (bool) $features['quote']
                : true;
            $features['multiple_events'] = array_key_exists('multiple_events', $features)
                ? (bool) $features['multiple_events']
                : true;

            DB::table('templates')->where('id', $row->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('templates')->whereIn('slug', ['tema1', 'tema2'])->get() as $row) {
            $features = json_decode((string) ($row->features ?? ''), true);
            $features = is_array($features) ? $features : [];

            unset($features['quote'], $features['multiple_events']);

            DB::table('templates')->where('id', $row->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }
};
