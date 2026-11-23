<?php

namespace App\Services\Invitation;

use App\Enums\InvitationStatus;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplateSwitchService
{
    public function __construct(private readonly TemplateSnapshotService $snapshots)
    {
    }

    /**
     * Replace an invitation's frozen template with a new snapshot. Invitation
     * content, slug, publication state and payment history are never touched.
     */
    public function switch(Invitation $invitation, Template $template, ?User $actor = null): Invitation
    {
        if (! $template->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Template ini tidak tersedia.',
            ]);
        }

        if (! in_array($invitation->status, [
            InvitationStatus::Draft,
            InvitationStatus::Preview,
            InvitationStatus::PendingPayment,
            InvitationStatus::Active,
        ], true)) {
            throw ValidationException::withMessages([
                'template' => 'Template tidak dapat diganti pada status undangan ini.',
            ]);
        }

        if ((int) $invitation->template_id === (int) $template->id) {
            return $invitation;
        }

        $previousName = (string) $invitation->template_name;
        $previousVersion = (string) $invitation->template_version;
        $snapshot = $this->snapshots->make($template);

        DB::transaction(function () use ($invitation, $template, $snapshot): void {
            $update = [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'template_version' => $template->version,
                'template_path' => $template->template_path,
                'template_snapshot' => $snapshot,
            ];

            // An active invitation has already been paid for. Keep the paid
            // amount as the historical price; only unpaid drafts reprice.
            if ($invitation->status !== InvitationStatus::Active) {
                $update['price'] = $template->price;
            }

            $invitation->update($update);
        });

        if ($actor !== null) {
            ActivityLog::create([
                'user_id' => $actor->id,
                'action' => 'invitation.template_switched',
                'subject_type' => 'invitation',
                'subject_id' => $invitation->id,
                'description' => 'Customer mengganti template undangan ' . $invitation->slug
                    . ' dari ' . ($previousName !== '' ? $previousName : 'template sebelumnya')
                    . ($previousVersion !== '' ? ' v' . $previousVersion : '')
                    . ' menjadi ' . $template->name . ' v' . $template->version,
                'ip_address' => request()->ip(),
            ]);
        }

        return $invitation->refresh();
    }
}
