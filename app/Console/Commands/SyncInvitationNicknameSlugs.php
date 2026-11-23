<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Services\Invitation\InvitationSlugService;
use Illuminate\Console\Command;
use Throwable;

class SyncInvitationNicknameSlugs extends Command
{
    protected $signature = 'invitations:sync-nickname-slugs
        {--force : Juga ganti slug kustom yang sudah ditentukan customer}';

    protected $description = 'Samakan slug undangan dengan nama panggilan kedua mempelai';

    public function handle(InvitationSlugService $slugs): int
    {
        $force = (bool) $this->option('force');
        $changed = 0;
        $skipped = 0;
        $failed = 0;

        Invitation::query()
            ->with(['groom', 'bride'])
            ->chunkById(500, function ($invitations) use ($slugs, $force, &$changed, &$skipped, &$failed): void {
                foreach ($invitations as $invitation) {
                    try {
                        if ($slugs->syncFromCouple($invitation, $force)) {
                            $changed++;
                            $this->line("{$invitation->slug} <- #{$invitation->id}");
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->warn("Gagal untuk undangan #{$invitation->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info("Slugs diperbarui: {$changed}");
        $this->info("Slugs dilewati: {$skipped}");

        if ($failed > 0) {
            $this->warn("Slugs gagal: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
