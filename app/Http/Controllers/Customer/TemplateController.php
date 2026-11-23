<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SelectTemplateRequest;
use App\Models\Template;
use App\Services\Invitation\InvitationCreationService;
use App\Services\Invitation\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        $templates = Template::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('templates.index', compact('templates'));
    }

    public function sample(Request $request, Template $template, TemplateRenderer $renderer): Response
    {
        abort_unless($template->isActive(), 404);

        $html = $renderer->renderSample(
            $template,
            $request->query('to'),
            csrf_token(),
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function select(
        SelectTemplateRequest $request,
        Template $template,
        InvitationCreationService $service,
    ): RedirectResponse {
        abort_unless($template->isActive(), 404);

        $invitation = $service->create($template, $request->user());

        return redirect()
            ->route('invitations.edit', $invitation)
            ->with('status', 'Draft undangan dibuat. Masa pratinjau 10 jam dimulai sekarang.');
    }
}
