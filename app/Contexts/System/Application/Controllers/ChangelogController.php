<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChangelogController extends Controller
{
    public function index(Request $request)
    {
        $entries = ChangelogEntry::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id',
                'type',
                'version',
                'title_es',
                'title_en',
                'body_es',
                'body_en',
                'published_at',
            ]);

        return Inertia::render('Changelog/Index', [
            'entries' => $entries,
        ]);
    }
}
