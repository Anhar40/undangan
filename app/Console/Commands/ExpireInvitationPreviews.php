<?php

namespace App\Console\Commands;

use App\Services\Invitation\InvitationExpiryService;
use Illuminate\Console\Command;

class ExpireInvitationPreviews extends Command
{
    protected $signature = 'invitations:expire-previews {--prune-days= : Also prune EXPIRED drafts older than this many days}';

    protected $description = 'Expire invitation previews and active invitations past their expiry window';

    public function handle(InvitationExpiryService $service): int
    {
        $previews = $service->expirePreviewDue();
        $active = $service->expireActiveDue();
        $this->info("Expired previews: {$previews}");
        $this->info("Expired active invitations: {$active}");

        $pruneDays = $this->option('prune-days');

        if ($pruneDays !== null) {
            $pruned = $service->pruneExpired((int) $pruneDays);
            $this->info("Pruned old drafts: {$pruned}");
        }

        return self::SUCCESS;
    }
}
