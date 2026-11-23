<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MusicRequest;
use App\Models\Music;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MusicController extends Controller
{
    public function __construct(private readonly MediaStorageService $media) {}

    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');

        $musics = Music::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(artist) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(file_path) LIKE ?', ['%'.mb_strtolower($search).'%']);
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('admin.musics.index', compact('musics', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.musics.form', [
            'music' => new Music(['status' => 'active']),
        ]);
    }

    public function store(MusicRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['file_path'] = $this->resolveFilePath($request, null);

        Music::create($data);

        return redirect()
            ->route('admin.musics.index')
            ->with('status', 'Musik berhasil ditambahkan.');
    }

    public function edit(Music $music): View
    {
        return view('admin.musics.form', compact('music'));
    }

    public function update(MusicRequest $request, Music $music): RedirectResponse
    {
        $data = $request->validated();
        $oldPath = $music->file_path;
        $data['file_path'] = $this->resolveFilePath($request, $music);

        $music->update($data);

        $this->deleteStoredFileIfReplaced($oldPath, $music->file_path);

        return redirect()
            ->route('admin.musics.edit', $music)
            ->with('status', 'Musik berhasil diperbarui.');
    }

    public function toggleStatus(Music $music): RedirectResponse
    {
        $music->update([
            'status' => $music->status === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()
            ->route('admin.musics.index')
            ->with('status', 'Status musik '.$music->title.' diubah menjadi '.$music->status.'.');
    }

    public function destroy(Music $music): RedirectResponse
    {
        $path = $music->file_path;
        $music->delete();

        $this->media->delete(is_string($path) ? $path : null);

        return redirect()
            ->route('admin.musics.index')
            ->with('status', 'Musik dihapus.');
    }

    private function resolveFilePath(MusicRequest $request, ?Music $music): string
    {
        if ($request->hasFile('audio_file')) {
            $result = $this->media->upload($request->file('audio_file'), 'musics');

            return $result['path'];
        }

        $url = trim((string) $request->input('file_url', ''));
        if ($url !== '') {
            return $url;
        }

        return (string) ($music?->file_path ?? '');
    }

    private function deleteStoredFileIfReplaced(string $oldPath, string $newPath): void
    {
        if ($oldPath === $newPath || $oldPath === '') {
            return;
        }

        $this->media->delete($oldPath);
    }
}
