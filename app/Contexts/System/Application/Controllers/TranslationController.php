<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\System\Domain\Models\Translation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TranslationController extends Controller
{
    public function index()
    {
        return Inertia::render('System/Translations', [
            'translations' => Translation::orderBy('key')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'language' => 'required|string|max:5',
            'key' => 'required|string|max:255|unique:translations,key',
            'value' => 'required|string',
        ]);

        Translation::create($request->all());

        return back()->with('success', ['message' => 'Traducción creada correctamente.']);
    }

    public function update(Request $request, Translation $translation)
    {
        $request->validate([
            'value' => 'required|string',
        ]);

        $translation->update($request->only('value'));

        return back()->with('success', ['message' => 'Traducción actualizada correctamente.']);
    }

    public function destroy(Translation $translation)
    {
        $translation->delete();

        return back()->with('success', ['message' => 'Traducción eliminada correctamente.']);
    }
}
