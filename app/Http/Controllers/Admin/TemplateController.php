<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TemplateRequest;
use App\Models\Template;
use App\Services\Admin\TemplateManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');

        $templates = Template::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(category) LIKE ?', ['%'.mb_strtolower($search).'%']);
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.templates.index', compact('templates', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.templates.form', [
            'template' => new Template([
                'status' => 'active',
                'renderer' => 'tema1',
                'price' => config('invitation.default_price', 150000),
                'version' => '1.0',
                'features' => array_fill_keys(Template::FEATURES, true),
            ]),
            'sources' => Template::sourceOptions(),
        ]);
    }

    public function store(TemplateRequest $request, TemplateManagementService $service): RedirectResponse
    {
        $template = $service->create(
            $request->validated(),
            $request->file('thumbnail'),
            $request->file('template_html'),
        );

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with('status', 'Template berhasil ditambahkan.');
    }

    public function edit(Template $template): View
    {
        return view('admin.templates.form', [
            'template' => $template,
            'sources' => Template::sourceOptions(),
        ]);
    }

    public function update(
        TemplateRequest $request,
        Template $template,
        TemplateManagementService $service,
    ): RedirectResponse {
        $service->update(
            $template,
            $request->validated(),
            $request->file('thumbnail'),
            $request->file('template_html'),
        );

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with('status', 'Template berhasil diperbarui.');
    }

    public function toggleStatus(Template $template, TemplateManagementService $service): RedirectResponse
    {
        $template = $service->toggleStatus($template);

        return redirect()
            ->route('admin.templates.index')
            ->with('status', 'Status template '.$template->name.' diubah menjadi '.$template->status.'.');
    }

    public function destroy(Template $template, TemplateManagementService $service): RedirectResponse
    {
        $service->delete($template);

        return redirect()
            ->route('admin.templates.index')
            ->with('status', 'Template dihapus.');
    }
}
