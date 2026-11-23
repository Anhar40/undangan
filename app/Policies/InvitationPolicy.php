<?php

namespace App\Policies;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function view(User $user, Invitation $invitation): bool
    {
        return $user->isSuperAdmin() || $invitation->user_id === $user->id;
    }

    public function preview(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $invitation->user_id === $user->id
            && ($invitation->isEditable() || in_array($invitation->status, [
                InvitationStatus::Active,
                InvitationStatus::Suspended,
            ], true));
    }

    public function update(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        return $invitation->user_id === $user->id
            && ($invitation->isEditable() || $invitation->status === InvitationStatus::Active);
    }

    public function switchTemplate(User $user, Invitation $invitation): bool
    {
        // An active invitation may still be re-skinned. The frozen snapshot
        // protects against admin edits; it is not a customer replacement lock.
        return $this->update($user, $invitation) && in_array($invitation->status, [
            InvitationStatus::Draft,
            InvitationStatus::Preview,
            InvitationStatus::PendingPayment,
            InvitationStatus::Active,
        ], true);
    }

    public function checkSlug(User $user, Invitation $invitation): bool
    {
        return $this->update($user, $invitation);
    }

    public function activate(User $user, Invitation $invitation): bool
    {
        $activeExpired = $invitation->status === InvitationStatus::Active
            && $invitation->isActiveExpired();

        return ! $user->isSuperAdmin()
            && $invitation->user_id === $user->id
            && (in_array($invitation->status, [
                InvitationStatus::Draft,
                InvitationStatus::Preview,
                InvitationStatus::Expired,
                InvitationStatus::PendingPayment,
            ], true) || $activeExpired);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return ! $user->isSuperAdmin()
            && $invitation->user_id === $user->id
            && in_array($invitation->status?->value, ['DRAFT', 'PREVIEW', 'EXPIRED'], true);
    }
}
