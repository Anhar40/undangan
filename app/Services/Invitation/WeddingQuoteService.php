<?php

namespace App\Services\Invitation;

class WeddingQuoteService
{
    /** @return array{quote:string, source:string} */
    public function default(): array
    {
        return [
            'quote' => trim((string) config('invitation.default_quote', '')),
            'source' => trim((string) config('invitation.default_quote_source', '')),
        ];
    }
}
