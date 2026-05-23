<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'warehouse.sell.auto.mode_active'           => ['es' => 'Reparto automático FIFO',                          'en' => 'Auto FIFO allocation'],
        'warehouse.sell.auto.mode_manual'           => ['es' => 'Eligiendo un farm específico',                     'en' => 'Picking one specific farm'],
        'warehouse.sell.auto.mode_toggle'           => ['es' => 'Elegir farm específico',                           'en' => 'Pick a specific farm'],
        'warehouse.sell.auto.mode_back_to_auto'     => ['es' => 'Volver a reparto automático',                      'en' => 'Back to auto allocation'],
        'warehouse.sell.auto.preview_title'         => ['es' => 'Se crearán {n} ventas:',                           'en' => '{n} sales will be created:'],
        'warehouse.sell.auto.from_farm'             => ['es' => 'del farm',                                         'en' => 'from farm'],
        'warehouse.sell.auto.shortage'              => ['es' => 'Faltan {n} uds — stock total disponible: {available}', 'en' => 'Short by {n} — total available: {available}'],
        'warehouse.sell.auto.no_attendees_in_source' => ['es' => 'El farm #{id} no tiene attendees; véndelo por separado con CP 100%', 'en' => 'Farm #{id} has no attendees; sell it separately with CP 100%'],
        'warehouse.sell.auto.submit_multi'          => ['es' => 'Vender en {n} reports',                            'en' => 'Sell as {n} reports'],
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
