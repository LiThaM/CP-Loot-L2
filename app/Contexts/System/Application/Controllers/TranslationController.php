<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\System\Domain\Models\Translation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TranslationController extends Controller
{
    public function index()
    {
        $rows = Translation::orderBy('key')->get(['id', 'language', 'key', 'value']);
        $entries = $rows
            ->groupBy('key')
            ->map(function ($items, $key) {
                $es = $items->firstWhere('language', 'es');
                $en = $items->firstWhere('language', 'en');

                return [
                    'key' => (string) $key,
                    'es' => $es?->value ?? '',
                    'en' => $en?->value ?? '',
                    'id_es' => $es?->id,
                    'id_en' => $en?->id,
                ];
            })
            ->values();

        return Inertia::render('System/Translations', [
            'entries' => $entries,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string', 'max:255', Rule::unique('translations', 'key')],
            'value_es' => ['required', 'string'],
            'value_en' => ['required', 'string'],
        ]);

        $key = (string) $request->string('key');

        Translation::updateOrCreate(
            ['language' => 'es', 'key' => $key],
            ['value' => (string) $request->string('value_es')]
        );

        Translation::updateOrCreate(
            ['language' => 'en', 'key' => $key],
            ['value' => (string) $request->string('value_en')]
        );

        return back()->with('success', ['message' => 'Traducción creada correctamente.']);
    }

    public function updateKey(Request $request, string $key)
    {
        $request->validate([
            'value_es' => ['required', 'string'],
            'value_en' => ['required', 'string'],
        ]);

        Translation::updateOrCreate(
            ['language' => 'es', 'key' => $key],
            ['value' => (string) $request->string('value_es')]
        );

        Translation::updateOrCreate(
            ['language' => 'en', 'key' => $key],
            ['value' => (string) $request->string('value_en')]
        );

        return back()->with('success', ['message' => 'Traducción actualizada correctamente.']);
    }

    public function update(Request $request, Translation $translation)
    {
        $request->validate([
            'value' => 'required|string',
        ]);

        $translation->update($request->only('value'));

        return back()->with('success', ['message' => 'Traducción actualizada correctamente.']);
    }

    public function destroyKey(string $key)
    {
        Translation::where('key', $key)->delete();

        return back()->with('success', ['message' => 'Traducción eliminada correctamente.']);
    }

    public function destroy(Translation $translation)
    {
        $translation->delete();

        return back()->with('success', ['message' => 'Traducción eliminada correctamente.']);
    }
}
