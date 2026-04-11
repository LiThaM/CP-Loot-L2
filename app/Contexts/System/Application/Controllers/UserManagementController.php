<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\System\Domain\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $isFounder = $currentUser->cp_id && $currentUser->cp && $currentUser->id === $currentUser->cp->leader_id;
        $isCoLeader = ! $isFounder && $currentUser->role->name === 'cp_leader';
        $canManageUsers = $isAdmin || $isFounder || $isCoLeader;

        // Members can view if they belong to a CP
        if (! $isAdmin && ! $currentUser->cp_id) {
            abort(403, 'No tienes permiso para ver la gestión de usuarios.');
        }

        $query = User::with(['role', 'cp'])
            ->withSum('pointsLogs as total_points', 'points')
            ->withSum('pointsLogs as total_adena', 'adena');

        // Filter by CP if not Admin
        if (! $isAdmin) {
            $query->where('cp_id', $currentUser->cp_id)
                ->whereHas('role', function ($q) {
                    $q->where('name', '!=', 'admin');
                });
        }

        $users = $query->orderBy('name')->get();

        return Inertia::render('System/Users/Index', [
            'users' => $users,
            'roles' => $isAdmin
                ? Role::all()
                : ($canManageUsers ? Role::query()->whereIn('name', ['cp_leader', 'accountant', 'member'])->get() : []),
            'cps' => $isAdmin ? ConstParty::all() : [],
            'isAdmin' => $isAdmin,
            'isLeader' => $canManageUsers,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->role->name === 'admin';
        $user->load(['role', 'cp']);
        $old = [
            'role_id' => $user->role_id,
            'cp_id' => $user->cp_id,
            'role' => $user->role?->name,
            'cp' => $user->cp?->name,
        ];

        // El Líder Fundador es quien figura como leader_id en la tabla const_parties
        $isFounder = $currentUser->cp_id && $currentUser->cp && $currentUser->id === $currentUser->cp->leader_id;

        // Un Co-Líder es alguien con el rol cp_leader pero que NO es el líder fundador de la CP
        $isCoLeader = ! $isFounder && $currentUser->role->name === 'cp_leader';

        if (! $isAdmin && ! $isFounder && ! $isCoLeader) {
            abort(403, 'No tienes permiso para gestionar usuarios.');
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'cp_id' => 'nullable|exists:const_parties,id',
        ]);

        $newRole = Role::find($request->role_id);

        // Lógica de seguridad para no-administradores
        if (! $isAdmin) {
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

        $user->refresh()->load(['role', 'cp']);
        $new = [
            'role_id' => $user->role_id,
            'cp_id' => $user->cp_id,
            'role' => $user->role?->name,
            'cp' => $user->cp?->name,
        ];

        $audit = AuditLog::create([
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'user_id' => $currentUser->id,
            'action' => 'USER_UPDATED',
            'old_values' => $old,
            'new_values' => $new,
        ]);

        $summaryParts = [];
        if (($old['role_id'] ?? null) !== ($new['role_id'] ?? null)) {
            $summaryParts[] = "rol {$old['role']} → {$new['role']}";
        }
        if (($old['cp_id'] ?? null) !== ($new['cp_id'] ?? null)) {
            $summaryParts[] = "CP {$old['cp']} → {$new['cp']}";
        }
        $summary = count($summaryParts) > 0
            ? "{$currentUser->name} actualizó a {$user->name}: ".implode(', ', $summaryParts)
            : "{$currentUser->name} actualizó a {$user->name}";

        $recipients = collect([$currentUser->id]);
        if ($user->cp?->leader_id) {
            $recipients->push($user->cp->leader_id);
        }
        $recipients = $recipients->unique()->values();

        $now = now();
        $rows = $recipients->map(fn ($rid) => [
            'audit_log_id' => $audit->id,
            'recipient_user_id' => $rid,
            'actor_user_id' => $currentUser->id,
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'action' => 'USER_UPDATED',
            'summary' => $summary,
            'meta' => json_encode(['subject_user_id' => $user->id]),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        DB::table('audit_alerts')->insert($rows);

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

        $user->load(['role', 'cp']);
        $currentUser = $request->user();

        $audit = AuditLog::create([
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'user_id' => $currentUser->id,
            'action' => 'USER_DELETED',
            'old_values' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
                'cp' => $user->cp?->name,
                'cp_id' => $user->cp_id,
            ],
            'new_values' => null,
        ]);

        $recipients = collect([$currentUser->id]);
        if ($user->cp?->leader_id) {
            $recipients->push($user->cp->leader_id);
        }
        $recipients = $recipients->unique()->values();

        $summary = "{$currentUser->name} eliminó a {$user->name}";
        $now = now();
        $rows = $recipients->map(fn ($rid) => [
            'audit_log_id' => $audit->id,
            'recipient_user_id' => $rid,
            'actor_user_id' => $currentUser->id,
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'action' => 'USER_DELETED',
            'summary' => $summary,
            'meta' => json_encode(['subject_user_id' => $user->id]),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        DB::table('audit_alerts')->insert($rows);

        $user->delete();

        return back()->with('success', "Usuario {$user->name} eliminado.");
    }

    public function logs(Request $request, User $user)
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->role->name === 'admin';

        if (! $isAdmin) {
            if (! $currentUser->cp_id || $user->cp_id !== $currentUser->cp_id) {
                abort(403, 'No tienes permiso para ver el historial de este usuario.');
            }
        }

        $logs = PointsLog::where('cp_id', $user->cp_id)
            ->where('user_id', $user->id)
            ->where('adena', '!=', 0)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'action_type', 'adena', 'description', 'created_at'])
            ->map(function ($log) {
                $reportId = null;
                if (is_string($log->description) && preg_match('/reporte\s*#\s*(\d+)/i', $log->description, $m)) {
                    $reportId = (int) $m[1];
                }

                return [
                    'id' => $log->id,
                    'action_type' => $log->action_type,
                    'adena' => (int) $log->adena,
                    'description' => $log->description,
                    'created_at' => $log->created_at,
                    'report_id' => $reportId,
                ];
            })
            ->values();

        $audits = AuditLog::query()
            ->where('entity_type', 'User')
            ->where('entity_id', $user->id)
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'user_id', 'action', 'old_values', 'new_values', 'created_at'])
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'actor' => $a->user?->name,
                    'action' => $a->action,
                    'old_values' => $a->old_values,
                    'new_values' => $a->new_values,
                    'created_at' => $a->created_at,
                ];
            })
            ->values();

        return response()->json([
            'logs' => $logs,
            'audits' => $audits,
        ]);
    }

    public function banMember(Request $request, User $user)
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->role->name === 'admin';
        $isFounder = $currentUser->cp_id && $currentUser->cp && $currentUser->id === $currentUser->cp->leader_id;
        $isCoLeader = ! $isFounder && $currentUser->role->name === 'cp_leader';

        if (! $isAdmin && ! $isFounder && ! $isCoLeader) {
            abort(403, 'No tienes permiso para gestionar usuarios.');
        }

        if (! $isAdmin && $user->cp_id !== $currentUser->cp_id) {
            abort(403, 'No puedes gestionar usuarios ajenos a tu CP.');
        }

        if ((int) $currentUser->id === (int) $user->id) {
            return back()->with('error', 'No puedes banearte a ti mismo.');
        }

        $user->update(['membership_status' => 'banned']);

        AuditLog::create([
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'user_id' => $currentUser->id,
            'action' => 'USER_BANNED',
            'old_values' => ['membership_status' => 'approved'],
            'new_values' => ['membership_status' => 'banned'],
        ]);

        return back()->with('success', 'Miembro excluido/retirado correctamente.');
    }

    public function unbanMember(Request $request, User $user)
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->role->name === 'admin';
        $isFounder = $currentUser->cp_id && $currentUser->cp && $currentUser->id === $currentUser->cp->leader_id;
        $isCoLeader = ! $isFounder && $currentUser->role->name === 'cp_leader';

        if (! $isAdmin && ! $isFounder && ! $isCoLeader) {
            abort(403, 'No tienes permiso para gestionar usuarios.');
        }

        if (! $isAdmin && $user->cp_id !== $currentUser->cp_id) {
            abort(403, 'No puedes gestionar usuarios ajenos a tu CP.');
        }

        $user->update(['membership_status' => 'approved']);

        AuditLog::create([
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'user_id' => $currentUser->id,
            'action' => 'USER_UNBANNED',
            'old_values' => ['membership_status' => 'banned'],
            'new_values' => ['membership_status' => 'approved'],
        ]);

        return back()->with('success', 'Miembro reactivado correctamente.');
    }
}
