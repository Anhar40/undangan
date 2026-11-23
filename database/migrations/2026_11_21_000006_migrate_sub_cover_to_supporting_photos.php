<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Foto Pendukung replaces the old sub-cover upload. Keep already uploaded
     * photos as decorative supporting photos (at most three) and drop the
     * retired slot so it can no longer be edited or validated.
     */
    public function up(): void
    {
        foreach (DB::table('invitation_settings')->get() as $row) {
            $custom = $this->decode($row->custom_settings ?? null);
            $gallery = is_array($custom['photo_gallery'] ?? null) ? $custom['photo_gallery'] : [];

            if (! array_key_exists('sub_cover', $gallery)) {
                continue;
            }

            if (! isset($custom['supporting_photos'])) {
                $custom['supporting_photos'] = array_slice(
                    array_values(array_filter(
                        (array) $gallery['sub_cover'],
                        static fn ($path): bool => is_string($path) && $path !== '',
                    )),
                    0,
                    3,
                );
            }

            unset($gallery['sub_cover']);
            $custom['photo_gallery'] = $gallery;

            DB::table('invitation_settings')
                ->where('id', $row->id)
                ->update(['custom_settings' => $this->encode($custom)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('invitation_settings')->get() as $row) {
            $custom = $this->decode($row->custom_settings ?? null);

            if (! isset($custom['supporting_photos'])) {
                continue;
            }

            $gallery = is_array($custom['photo_gallery'] ?? null) ? $custom['photo_gallery'] : [];
            $gallery['sub_cover'] = array_values((array) $custom['supporting_photos']);
            $custom['photo_gallery'] = $gallery;
            unset($custom['supporting_photos']);

            DB::table('invitation_settings')
                ->where('id', $row->id)
                ->update(['custom_settings' => $this->encode($custom)]);
        }
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
};
