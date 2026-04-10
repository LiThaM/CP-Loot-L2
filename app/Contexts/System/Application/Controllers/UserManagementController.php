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
            $query->where('cp_id', $currentUser->cp_id)
                ->whereHas('role', function($q) {
                    $q->where('name', '!=', 'admin');
                });
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
        
        // El Líder Fundador es quien figura como leader_id en la tabla const_parties
        $isFounder = $currentUser->cp_id && $currentUser->cp && $currentUser->id === $currentUser->cp->leader_id;
        
        // Un Co-Líder es alguien con el rol cp_leader pero que NO es el líder fundador de la CP
        $isCoLeader = !$isFounder && $currentUser->role->name === 'cp_leader';

        if (!$isAdmin && !$isFounder && !$isCoLeader) {
            abort(403, 'No tienes permiso para gestionar usuarios.');
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'cp_id' => 'nullable|exists:const_parties,id',
        ]);

        $newRole = Role::find($request->role_id);

        // Lógica de seguridad para no-administradores
        if (!$isAdmin) {
            // Solo pueden modificar usuarios de su propia CP
            if ($user->cp_id !== $currentUser->cp_id) {
                abort(403, 'No puedes gestionar usuarios ajenos a tu CP.');
            }

            // El Líder Fundador puede hacer cualquier cambio dentro de su CP
            // Pero un Co-Líder tiene restricciones
            if ($isCoLeader) {
                // Un Co-Líder NO puede degradar ni cambiar el rol de otros líderes
                if ($user->role->name === 'cp_leader') {
                    abort(403, 'Solo el líder fundador puede gestionar a otros líderes.');
                }
                
                // Un Co-Líder solo puede asignar roles de su nivel o inferior (cp_leader, accountant, member)
                // En la práctica, esto significa que no puede asignar el rol 'admin'
                if ($newRole->name === 'admin') {
                    abort(403, 'No puedes asignar el rol de administrador global.');
                }
            }

            // Ninguno (ni Fundador ni Co-Líder) puede cambiar la CP de un usuario
            if ($request->has('cp_id') && $request->cp_id != $user->cp_id) {
                abort(403, 'Solo un administrador global puede reasignar un usuario a otra CP.');
            }
        }

        $user->update([
            'role_id' => $request->role_id,
        ]);

        // Si es Admin, también puede cambiar la CP
        if ($isAdmin && $request->has('cp_id')) {
            $user->update(['cp_id' => $request->cp_id]);
        }

        return back()->with('success', "Usuario {$user->name} actualizado exitosamente.");
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
