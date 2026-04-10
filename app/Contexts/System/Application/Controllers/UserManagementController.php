<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    /**
     * List all users with their balances and CP/Role info.
     * Dual scope: SuperAdmin sees all, CP Leader sees their CP members.
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->role->name === 'admin';
        $isLeader = $currentUser->cp_id && $currentUser->id === $currentUser->cp->leader_id;

        // Members can view if they belong to a CP
        if (!$isAdmin && !$currentUser->cp_id) {
            abort(403, 'No tienes permiso para ver la gestión de usuarios.');
        }

        $query = User::with(['role', 'cp'])
            ->withSum('pointsLogs as total_points', 'points')
            ->withSum('pointsLogs as total_adena', 'adena');

        // Filter by CP if not Admin
        if (!$isAdmin) {
            $query->where('cp_id', $currentUser->cp_id);
        }

        $users = $query->orderBy('name')->get();

        return Inertia::render('System/Users/Index', [
            'users' => $users,
            'roles' => $isAdmin ? Role::all() : [],
            'cps' => $isAdmin ? ConstParty::all() : [],
            'isAdmin' => $isAdmin,
            'isLeader' => $isLeader,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->role->name === 'admin';
        $isFounder = $currentUser->cp_id && $currentUser->id === $currentUser->cp->leader_id;
        $isLeader = $currentUser->role->name === 'cp_leader' && $currentUser->cp_id === $currentUser->cp_id;

        if (!$isAdmin && !$isFounder && !$isLeader) {
            abort(403);
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'cp_id' => 'nullable|exists:const_parties,id',
        ]);

        $newRole = Role::find($request->role_id);

        // Security logic for non-admins
        if (!$isAdmin) {
            // Can only modify users in their own CP
            if ($user->cp_id !== $currentUser->cp_id) {
                abort(403, 'No puedes gestionar usuarios ajenos a tu CP.');
            }

            // A Co-Leader cannot demote another leader or change roles of other leaders
            if ($isLeader && !$isFounder) {
                if ($user->role->name === 'cp_leader') {
                    abort(403, 'Solo el líder fundador puede gestionar otros líderes.');
                }
                if (!in_array($newRole->name, ['member', 'accountant', 'cp_leader'])) {
                     abort(403);
                }
            }

            // Cannot change CP of a user (reserved for Admin)
            if ($request->cp_id != $user->cp_id) {
                abort(403, 'Solo un administrador puede reasignar un usuario a otra CP.');
            }
        }

        $user->update([
            'role_id' => $request->role_id,
            'cp_id' => $request->cp_id, // This will be the same for non-admins due to check above
        ]);

        return back()->with('success', "Usuario {$user->name} actualizado.");
    }

    /**
     * Delete a user (Admin only).
     */
    public function destroy(Request $request, User $user)
    {
        if ($request->user()->role->name !== 'admin') {
            abort(403);
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'No puedes eliminarte a ti mismo.']);
        }

        $user->delete();

        return back()->with('success', "Usuario {$user->name} eliminado.");
    }
}
