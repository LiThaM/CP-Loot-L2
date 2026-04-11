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
        // Only admins should create CPs (Gate or simple check, assuming simple check for now based on role_id)
        if ($request->user()->role->name !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:const_parties,name',
            'server' => 'nullable|string|max:255',
            'chronicle' => 'required|string|in:C4,Interlude,IL,C5,Classic',
        ]);

        $inviteCode = Str::random(12);

        $cp = ConstParty::create([
            'name' => $request->name,
            'server' => $request->input('server'),
            'chronicle' => $request->chronicle,
            'invite_code' => $inviteCode,
        ]);

        // Generate the full registration url
        $magicLink = route('register', ['invite' => $inviteCode]);

        return back()->with('success', [
            'message' => 'Const Party creada exitosamente.',
            'link' => $magicLink,
            'cp_name' => $cp->name,
        ]);
    }
}
