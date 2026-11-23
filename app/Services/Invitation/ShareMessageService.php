<?php

namespace App\Services\Invitation;

use App\Models\Invitation;
use Illuminate\Support\Str;

class ShareMessageService
{
    /** @return array{couple:string,link:string,template:string,message:string,default:string} */
    public function forInvitation(Invitation $invitation, string $guestName, string $link): array
    {
        $invitation->loadMissing(['groom', 'bride', 'invitationSettings']);

        $couple = $invitation->coupleLabel();
        $guest = trim(Str::of($guestName)->stripTags()->toString()) ?: 'Bapak/Ibu/Saudara/i';

        $template = trim((string) config('invitation.share.default_message', ''));
        $custom = trim((string) ($invitation->invitationSettings?->whatsapp_message ?? ''));
        $template = $custom !== '' ? $custom : $template;

        $default = str_replace(
            ['{guest}', '{link}', '{couple}', '{nama_tamu}', '{url}', '{nama_pasangan}'],
            [$guest, $link, $couple, $guest, $link, $couple],
            $template,
        );

        return [
            'couple' => $couple,
            'link' => $link,
            'template' => $template,
            'message' => $default,
            'default' => $default,
        ];
    }

    /** @return array{couple:string,link:string,template:string,message:string,default:string} */
    public function generic(string $guestName, string $link): array
    {
        $guest = trim(Str::of($guestName)->stripTags()->toString()) ?: 'Bapak/Ibu/Saudara/i';
        $template = trim((string) config('invitation.share.default_message', ''));
        $default = str_replace(
            ['{guest}', '{link}', '{couple}', '{nama_tamu}', '{url}', '{nama_pasangan}'],
            [$guest, $link, '', $guest, $link, ''],
            $template,
        );

        return [
            'couple' => '',
            'link' => $link,
            'template' => $template,
            'message' => $default,
            'default' => $default,
        ];
    }
}
