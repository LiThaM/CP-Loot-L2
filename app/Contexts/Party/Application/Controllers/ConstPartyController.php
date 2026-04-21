<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\ConstParty;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConstPartyController extends Controller
{
    public function store(Request $request)
    {
        // ... (existing code remains same)
        if ($request->user()->role->name !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:const_parties,name',
            'server' => 'nullable|string|max:255',
            'chronicle' => 'required|string|in:C1,C2,C3,C4,C5,IL,HB,Classic,LU4',
        ]);

        $inviteCode = Str::random(12);

        $cp = ConstParty::create([
            'name' => $request->name,
            'server' => $request->input('server'),
            'chronicle' => $request->chronicle,
            'invite_code' => $inviteCode,
        ]);

        $magicLink = route('register', ['invite' => $inviteCode]);

        return back()->with('success', [
            'message' => 'Const Party creada exitosamente.',
            'link' => $magicLink,
            'cp_name' => $cp->name,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $cp = $user->cp;

        if (! $cp || $user->id !== $cp->leader_id) {
            abort(403, 'Solo el líder fundador de la CP puede modificar los ajustes generales.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:const_parties,name,'.$cp->id,
            'server' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:3072', // 3MB
        ]);

        $cp->update([
            'name' => $request->name,
            'server' => $request->server,
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $path = $file->store("cp-logos/{$cp->id}", 'public');
            $cp->update(['logo_path' => $path]);
        }

        return back()->with('success', 'Ajustes de la Const Party actualizados correctamente.');
    }
}
