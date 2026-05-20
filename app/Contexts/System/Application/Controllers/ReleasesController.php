<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReleasesController extends Controller
{
    public function index(): Response
    {
        $releases = Release::orderByDesc('released_at')->orderByDesc('id')->get([
            'id', 'version', 'name', 'channel', 'critical_update',
            'min_supported_version', 'sha256', 'size_bytes',
            'released_at', 'published_at', 'download_count',
        ]);

        return Inertia::render('System/Releases/Index', [
            'releases' => $releases,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:50', 'regex:/^[\w.\-+]+$/', 'unique:releases,version'],
            'channel' => ['required', 'string', 'in:stable,beta'],
            'critical_update' => ['nullable', 'boolean'],
            'min_supported_version' => ['nullable', 'string', 'max:50'],
            'release_notes_md' => ['nullable', 'string', 'max:20000'],
            'binary' => ['required', 'file', 'max:307200', 'mimetypes:application/octet-stream,application/x-msdownload,application/zip'],
            'publish_now' => ['nullable', 'boolean'],
            'create_changelog_entry' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('binary');
        $bytes = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $bytes);
        $size = strlen($bytes);

        $relPath = sprintf('releases/%s/AdenaLedgerStats-%s.exe', $data['version'], $data['version']);
        $disk = Storage::disk('client_blobs');
        $disk->put($relPath, $bytes);

        $release = Release::create([
            'name' => $data['name'] ?? ('AdenaLedgerStats '.$data['version']),
            'version' => $data['version'],
            'channel' => $data['channel'],
            'storage_path' => $relPath,
            'sha256' => $sha256,
            'size_bytes' => $size,
            'release_notes_md' => $data['release_notes_md'] ?? null,
            'critical_update' => (bool) ($data['critical_update'] ?? false),
            'min_supported_version' => $data['min_supported_version'] ?? null,
            'released_at' => now(),
            'published_at' => ($data['publish_now'] ?? false) ? now() : null,
        ]);

        if (($data['create_changelog_entry'] ?? false) && $data['release_notes_md']) {
            ChangelogEntry::create([
                'type' => 'release',
                'version' => $data['version'],
                'audience' => 'both',
                'release_id' => $release->id,
                'title_es' => 'Versión '.$data['version'],
                'title_en' => 'Release '.$data['version'],
                'body_es' => $data['release_notes_md'],
                'body_en' => $data['release_notes_md'],
                'published_at' => $release->published_at ?? now()->addCentury(),
            ]);
        }

        return redirect()->route('system.releases.index')
            ->with('success', 'Release created.');
    }

    public function togglePublish(Release $release): RedirectResponse
    {
        $release->update([
            'published_at' => $release->published_at ? null : now(),
        ]);

        return back()->with('success', $release->published_at ? 'Published.' : 'Unpublished.');
    }

    public function destroy(Release $release): RedirectResponse
    {
        if ($release->storage_path) {
            Storage::disk('client_blobs')->delete($release->storage_path);
        }
        $release->delete();
        return redirect()->route('system.releases.index')->with('success', 'Release deleted.');
    }
}
