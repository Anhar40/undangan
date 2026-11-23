<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the new Foto dan Galeri slots from photos the customer already
     * uploaded. Couple portraits are never used as cover/sub-cover photos.
     */
    public function up(): void
    {
        $settingsRows = DB::table('invitation_settings')->get();

        foreach ($settingsRows as $row) {
            $custom = $this->decode($row->custom_settings ?? null);
            if (isset($custom['photo_gallery'])) {
                continue;
            }

            $invitation = DB::table('invitations')->where('id', $row->invitation_id)->first();
            if (! $invitation) {
                continue;
            }

            $galleries = DB::table('invitation_galleries')
                ->where('invitation_id', $invitation->id)
                ->orderByDesc('is_cover')
                ->orderBy('sort_order')
                ->pluck('image')
                ->filter()
                ->values()
                ->all();

            $coverGallery = DB::table('invitation_galleries')
                ->where('invitation_id', $invitation->id)
                ->where('is_cover', 1)
                ->orderBy('sort_order')
                ->value('image');

            $backgrounds = is_array($custom['backgrounds'] ?? null) ? $custom['backgrounds'] : [];

            $cover = $this->firstPath($backgrounds['cover'] ?? null)
                ?: ($invitation->cover_photo ?: ($coverGallery ?: ($galleries[0] ?? null)));

            $subCover = $galleries;
            if (count($subCover) < 2) {
                $subCover = array_values(array_filter([
                    $this->firstPath($backgrounds['groom'] ?? null),
                    $this->firstPath($backgrounds['bride'] ?? null),
                    ...$galleries,
                ]));
            }

            $footer = $this->firstPath($backgrounds['closing'] ?? null)
                ?: ($coverGallery ?: ($galleries[0] ?? null));

            $custom['photo_gallery'] = [
                'cover' => $cover ? [$cover] : [],
                'sub_cover' => array_slice($subCover, 0, 4),
                'footer' => $footer ? [$footer] : [],
            ];

            DB::table('invitation_settings')
                ->where('id', $row->id)
                ->update(['custom_settings' => json_encode($custom, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    public function down(): void
    {
        $settingsRows = DB::table('invitation_settings')->get();

        foreach ($settingsRows as $row) {
            $custom = $this->decode($row->custom_settings ?? null);
            if (! isset($custom['photo_gallery'])) {
                continue;
            }

            unset($custom['photo_gallery']);

            DB::table('invitation_settings')
                ->where('id', $row->id)
                ->update(['custom_settings' => json_encode($custom, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function firstPath(mixed $paths): ?string
    {
        foreach ((array) $paths as $path) {
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        return null;
    }
};
