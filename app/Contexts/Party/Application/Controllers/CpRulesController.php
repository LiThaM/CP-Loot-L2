<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\CpRule;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CP-scoped rule book. One row per CP (UNIQUE on cp_id); every save by
 * the leader bumps `version`, which the front-end compares against each
 * member's `users.cp_rules_accepted_version` to gate a blocking modal.
 */
class CpRulesController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cp = $user?->cp;
        abort_unless($cp && $user->id === $cp->leader_id, 403);

        $data = $request->validate([
            'body' => 'required|string|max:20000',
        ]);

        DB::transaction(function () use ($cp, $user, $data) {
            $rule = CpRule::firstOrNew(['cp_id' => $cp->id]);
            // First save: version starts at 1. Subsequent saves increment so
            // every member's accepted_version becomes stale and the modal
            // re-fires.
            $rule->version = ($rule->exists ? (int) $rule->version : 0) + 1;
            $rule->body = $data['body'];
            $rule->updated_by_id = $user->id;
            $rule->save();

            // Leader auto-accepts their own publish; otherwise the modal
            // would block them immediately after pressing Save.
            $user->forceFill(['cp_rules_accepted_version' => $rule->version])->save();
        });

        return back();
    }

    public function accept(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cp = $user?->cp;
        abort_unless($cp, 404);

        $rule = $cp->rules()->first();
        abort_unless($rule, 404);

        $user->forceFill(['cp_rules_accepted_version' => (int) $rule->version])->save();

        return back();
    }
}
