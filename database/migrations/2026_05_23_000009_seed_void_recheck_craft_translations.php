<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Loot void
        'loot.void.button'                  => ['es' => 'Marcar como error',                          'en' => 'Mark as error'],
        'loot.void.button_tooltip'          => ['es' => 'Anular este report y revertir su efecto',    'en' => 'Void this report and revert its effect'],
        'loot.void.badge'                   => ['es' => 'ANULADO',                                    'en' => 'VOIDED'],
        'loot.void.title'                   => ['es' => 'Marcar report como error',                   'en' => 'Mark report as error'],
        'loot.void.body'                    => ['es' => 'Este report seguirá visible pero su efecto en stock y adena se anula. Acción reversible por admin.', 'en' => 'The report stays visible but its stock/adena effect is reverted. Reversible by admin.'],
        'loot.void.reason_label'            => ['es' => 'Motivo',                                     'en' => 'Reason'],
        'loot.void.reason_placeholder'      => ['es' => 'Por qué se marca como error (mín 3 caracteres)', 'en' => 'Why is this an error (min 3 chars)'],
        'loot.void.confirm'                 => ['es' => 'Confirmar anulación',                        'en' => 'Confirm void'],
        // Externals chip on resolve modal
        'loot.external_attendees'           => ['es' => 'Asistentes externos',                        'en' => 'External attendees'],
        'loot.external_name_placeholder'    => ['es' => 'Añadir un externo y pulsar Enter',           'en' => 'Add an external and press Enter'],
        // Recheck
        'warehouse.recheck.button'          => ['es' => 'Recheck items',                              'en' => 'Recheck items'],
        'warehouse.recheck.title'           => ['es' => 'Reconciliación de stock',                    'en' => 'Stock reconciliation'],
        'warehouse.recheck.hint'            => ['es' => 'Mete los items, ajusta el "real" a lo que tienes en el warehouse del juego. El sistema crea solo los ajustes con delta != 0.', 'en' => 'Pick items, set "real" to what you actually have in the game warehouse. The system only creates adjustments where delta != 0.'],
        'warehouse.recheck.col_current'     => ['es' => 'Actual',                                     'en' => 'Current'],
        'warehouse.recheck.col_real'        => ['es' => 'Real',                                       'en' => 'Real'],
        'warehouse.recheck.col_delta'       => ['es' => 'Δ',                                          'en' => 'Δ'],
        'warehouse.recheck.note_label'      => ['es' => 'Nota (opcional)',                            'en' => 'Note (optional)'],
        'warehouse.recheck.note_placeholder' => ['es' => 'Auditoría semanal, errores de farms anteriores, etc.', 'en' => 'Weekly audit, past farm errors, etc.'],
        'warehouse.recheck.summary'         => ['es' => '{changed} cambios · +{gains} ganados · −{losses} perdidos', 'en' => '{changed} changes · +{gains} gained · −{losses} lost'],
        'warehouse.recheck.no_changes'      => ['es' => 'Sin cambios — totales coinciden con el stock actual', 'en' => 'No changes — totals match current stock'],
        'warehouse.recheck.submit'          => ['es' => 'Aplicar reconciliación',                     'en' => 'Apply reconciliation'],
        'warehouse.recheck.toast_ok'        => ['es' => 'Recheck aplicado, stock ajustado',           'en' => 'Recheck applied, stock adjusted'],
        'warehouse.recheck.toast_failed'    => ['es' => 'No se pudo aplicar el recheck',              'en' => 'Could not apply recheck'],
        // Craft outcome modal
        'craft.preview.title'               => ['es' => 'Auto-craft de intermedios',                  'en' => 'Auto-craft intermediates'],
        'craft.preview.subtitle'            => ['es' => 'Se craftearán estos items intermedios antes del craft final. Acepta para continuar o salta para cancelar.', 'en' => 'These intermediate items will be crafted before the final one. Accept to continue or skip to cancel.'],
        'craft.preview.accept'              => ['es' => 'Aceptar',                                    'en' => 'Accept'],
        'craft.preview.skip'                => ['es' => 'Saltar',                                     'en' => 'Skip'],
        'craft.outcome.title'               => ['es' => '¿Cómo salió el craft?',                      'en' => 'How did the craft go?'],
        'craft.outcome.lucky_label'         => ['es' => '¿Resultado?',                                'en' => 'Result?'],
        'craft.outcome.positive'            => ['es' => 'Positivo',                                   'en' => 'Positive'],
        'craft.outcome.negative'            => ['es' => 'Negativo (solo consume)',                    'en' => 'Negative (only consume)'],
        'craft.outcome.which_output'        => ['es' => '¿Qué salió?',                                'en' => 'Which output?'],
        'craft.outcome.confirm'             => ['es' => 'Confirmar',                                  'en' => 'Confirm'],
        // CP settings
        'cp.settings.image_proof_required'  => ['es' => 'Captura obligatoria',                        'en' => 'Screenshot required'],
        'cp.settings.image_proof_required_hint' => ['es' => 'Si está activado, todos los formularios del CP (farm, warehouse add/buy/sell, recheck) exigen una captura. Desactívalo si tu CP prefiere no subir capturas para cada operación.', 'en' => 'When enabled, every CP form (farm, warehouse add/buy/sell, recheck) requires a screenshot. Disable if your CP prefers to skip the upload.'],
        'common.optional'                   => ['es' => 'opcional',                                   'en' => 'optional'],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                DB::table('translations')->updateOrInsert(
                    ['language' => $lang, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
