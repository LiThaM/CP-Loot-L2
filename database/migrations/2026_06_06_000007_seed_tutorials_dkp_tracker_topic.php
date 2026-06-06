<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'tutorials.topic.dkp_tracker.title' => [
            'es' => 'DKP Value Tracker (opcional)',
            'en' => 'DKP Value Tracker (optional)',
        ],
        'tutorials.topic.dkp_tracker.intro' => [
            'es' => 'Si prefieres un sistema **DKP value-based** (puntos derivados del valor de cada item dropeado) en lugar de los puntos fijos por evento, actívalo en [Settings](/party) → "DKP Value Tracker". Funciona en paralelo a los puntos por evento — tú decides cuál usas para repartir.',
            'en' => 'If you prefer a **value-based DKP** system (points derived from each looted item value) instead of the flat per-event points, turn it on in [Settings](/party) → "DKP Value Tracker". It runs alongside the per-event points — pick whichever you use to share loot.',
        ],
        'tutorials.topic.dkp_tracker.bullet.0' => [
            'es' => '**Cómo se calculan**: cada item dropeado da `precio_mercado / divisor` puntos. El divisor (50–2000, default 1000) lo configuras por CP. Cuanto mayor el divisor, menos puntos por adena.',
            'en' => '**How points are computed**: each looted item awards `market_price / divisor` points. The divisor (50–2000, default 1000) is per-CP. Higher divisor = fewer points per adena.',
        ],
        'tutorials.topic.dkp_tracker.bullet.1' => [
            'es' => '**Badges**: `SOLO` cuando un item lo recibe un único miembro (puntos completos), `PARTY/N` cuando se reparte entre N attendees (puntos/N para cada uno), `EVENT` para bonus manuales planos.',
            'en' => '**Badges**: `SOLO` when one member receives the item (full points), `PARTY/N` when split among N attendees (points/N each), `EVENT` for flat manual bonuses.',
        ],
        'tutorials.topic.dkp_tracker.bullet.2' => [
            'es' => '**Auto + manual**: cada `LootReport` confirmado genera automáticamente las contribuciones SOLO/PARTY. Tú añades EVENT a mano desde el botón "Añadir contribución" (asistencia semanal, pots, premios, etc.).',
            'en' => '**Auto + manual**: every confirmed `LootReport` automatically generates SOLO/PARTY contributions. You add EVENT manually via the "Add contribution" button (weekly attendance, pots, prizes, etc.).',
        ],
        'tutorials.topic.dkp_tracker.bullet.3' => [
            'es' => '**Corte temporal**: al activar el toggle se guarda `tracker_enabled_at`. Los reportes anteriores a esa fecha NO se procesan, así no se llena el ledger con histórico. Si quieres puntos retroactivos, los metes manualmente.',
            'en' => '**Temporal cutoff**: when you flip the toggle, `tracker_enabled_at` is stamped. Reports older than that are NOT processed, so the ledger doesn\'t flood with history. Retroactive points must be added manually.',
        ],
        'tutorials.topic.dkp_tracker.bullet.4' => [
            'es' => '**Leaderboard + filtros**: la pestaña [Tracker](/party/tracker) muestra el ranking (top 3 con podium gold/silver/bronze) y la lista de contribuciones con filtros por miembro y badge.',
            'en' => '**Leaderboard + filters**: the [Tracker](/party/tracker) tab shows the leaderboard (top 3 with gold/silver/bronze podium) and the contributions list with filters by member and badge.',
        ],
        'tutorials.topic.dkp_tracker.bullet.5' => [
            'es' => '**Coexiste con los puntos por evento**: los puntos por evento (BOSS/EPIC/SIEGE/FARM) siguen funcionando como hoy. El tracker es un ledger paralelo opcional — si lo desactivas el ledger persiste, sólo deja de generar nuevas contribuciones.',
            'en' => '**Coexists with per-event points**: per-event points (BOSS/EPIC/SIEGE/FARM) keep working as today. The tracker is an optional parallel ledger — disabling it preserves the data, only stops new contributions.',
        ],
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
