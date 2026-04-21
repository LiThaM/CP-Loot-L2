<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\System\Domain\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdenaActionController extends Controller
{
    /**
     * Store an Adena transaction (Gain or Payout).
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|integer', // Positive for gain, negative for payout
            'description' => 'required|string|max:255',
            'image_proof' => 'required|image|max:4096',
        ]);

        $currentUser = $request->user();
        $targetUser = User::findOrFail($request->user_id);
        $targetUser->load('cp');

        // Seguridad: Admin, Líder o Contable de la misma CP
        $isAdmin = $currentUser->role->name === 'admin';
        $isLeader = $currentUser->role->name === 'cp_leader';
        $isAccountant = $currentUser->role->name === 'accountant';

        if (! $isAdmin) {
            // Si no es admin, debe pertenecer a la misma CP y ser Líder o Contable
            if (($isLeader || $isAccountant) && $targetUser->cp_id === $currentUser->cp_id) {
                // Permitido
            } else {
                abort(403, 'No tienes permiso para gestionar el saldo de este usuario.');
            }
        }

        DB::transaction(function () use ($request, $targetUser, $currentUser) {
            $report = LootReport::create([
                'cp_id' => $targetUser->cp_id,
                'requested_by_id' => $currentUser->id,
                'event_type' => $request->amount < 0 ? 'ADENA_PAYOUT' : 'ADENA_GRANT',
                'status' => 'confirmed',
                'image_proof' => null,
                'recipient_ids' => [$targetUser->id],
            ]);

            $file = $request->file('image_proof');
            $ext = $file->extension() ?: ($file->guessExtension() ?: 'jpg');
            $imagePath = $file->storeAs("payments/{$targetUser->cp_id}", "{$report->id}.{$ext}", 'public');
            $report->image_proof = $imagePath;
            $report->save();

            $adenaItem = Item::whereRaw('LOWER(name) = ?', ['adena'])->first();
            if ($adenaItem) {
                LootEntry::create([
                    'loot_report_id' => $report->id,
                    'item_id' => $adenaItem->id,
                    'amount' => abs((int) $request->amount),
                ]);
            }

            PointsLog::create([
                'cp_id' => $targetUser->cp_id,
                'user_id' => $targetUser->id,
                'action_type' => $request->amount < 0 ? 'ADENA_PAYOUT' : 'ADENA_GAIN',
                'points' => 0,
                'adena' => $request->amount,
                'description' => $request->description.' (Reporte #'.$report->id.')',
            ]);

            $audit = AuditLog::create([
                'entity_type' => 'User',
                'entity_id' => $targetUser->id,
                'user_id' => $currentUser->id,
                'action' => 'ADENA_ADJUSTED',
                'old_values' => null,
                'new_values' => [
                    'amount' => (int) $request->amount,
                    'description' => $request->description,
                    'report_id' => $report->id,
                ],
            ]);

            $amount = (int) $request->amount;
            $amountLabel = ($amount < 0 ? '-' : '+').number_format(abs($amount), 0, ',', '.');
            $summary = "{$currentUser->name} ajustó Adena {$amountLabel} a {$targetUser->name}";

            $recipients = collect([$currentUser->id]);
            if ($targetUser->cp?->leader_id) {
                $recipients->push($targetUser->cp->leader_id);
            }
            $recipients = $recipients->unique()->values();

            $now = now();
            $rows = $recipients->map(fn ($rid) => [
                'audit_log_id' => $audit->id,
                'recipient_user_id' => $rid,
                'actor_user_id' => $currentUser->id,
                'entity_type' => 'User',
                'entity_id' => $targetUser->id,
                'action' => 'ADENA_ADJUSTED',
                'summary' => $summary,
                'meta' => json_encode(['subject_user_id' => $targetUser->id, 'report_id' => $report->id]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('audit_alerts')->insert($rows);
        });

        $msg = $request->amount < 0 ? 'Pago de Adena registrado.' : 'Abono de Adena registrado.';

        return back()->with('success', $msg);
    }
    public function donate(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        if (!$user->cp_id) {
            abort(403, 'No perteneces a ninguna CP.');
        }

        // Calcular adena adeudada para validar
        $gained = PointsLog::where('user_id', $user->id)->where('action_type', 'ADENA_GAIN')->sum('adena');
        $paid = abs(PointsLog::where('user_id', $user->id)->whereIn('action_type', ['ADENA_PAYOUT', 'ADENA_OFFSET'])->sum('adena'));
        $owed = max(0, $gained - $paid);

        $amount = (int) $request->amount;
        if ($amount > $owed) {
            abort(422, 'No puedes donar más de lo que la CP te debe.');
        }

        DB::transaction(function () use ($user, $amount) {
            PointsLog::create([
                'cp_id' => $user->cp_id,
                'user_id' => $user->id,
                'action_type' => 'ADENA_OFFSET',
                'points' => 0,
                'adena' => -$amount,
                'description' => 'Donación voluntaria al fondo de la CP',
            ]);

            AuditLog::create([
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'user_id' => $user->id,
                'action' => 'ADENA_DONATED',
                'old_values' => null,
                'new_values' => [
                    'amount' => $amount,
                    'description' => 'Donación voluntaria',
                ],
            ]);
        });

        return back()->with('success', '¡Gracias por tu donación! Tu saldo ha sido ajustado.');
    }
}
