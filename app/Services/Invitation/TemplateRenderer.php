<?php

namespace App\Services\Invitation;

use App\Data\InvitationViewData;
use App\Models\Invitation;
use App\Models\Template;

class TemplateRenderer
{
    public function __construct(
        private readonly InvitationDataService $dataService,
        private readonly TemplateSnapshotService $snapshotService,
    ) {}

    public function renderSample(Template $template, ?string $guestName = null, ?string $csrfToken = null): string
    {
        $data = $this->dataService->forSample($template, $guestName, $csrfToken);

        return $this->resolve($data)->render($template->contents(), $data);
    }

    public function renderInvitation(
        Invitation $invitation,
        ?string $guestName = null,
        bool $preview = false,
        ?string $csrfToken = null,
        ?string $publicStatus = null,
        ?array $activationPrompt = null,
    ): string {
        $data = $this->dataService->forInvitation(
            $invitation,
            $guestName,
            $preview,
            $csrfToken,
            $publicStatus,
            $activationPrompt,
        );

        return $this->resolve($data)->render($this->snapshotService->htmlFor($invitation), $data);
    }

    private function resolve(InvitationViewData $data): AbstractTemplateRenderer
    {
        return match ($data->key) {
            'tema2' => new Tema2Renderer,
            'tema3' => new Tema3Renderer,
            'tema4' => new Tema4Renderer,
            'tema5' => new Tema5Renderer,
            default => new Tema1Renderer,
        };
    }

    public function keyFor(Template $template): string
    {
        $key = $template->config['renderer'] ?? $template->slug;

        return Template::hasRenderer($key) ? (string) $key : 'tema1';
    }
}
