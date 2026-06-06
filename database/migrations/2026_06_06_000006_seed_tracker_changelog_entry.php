<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Ahora cada CP puede activar opcionalmente un **DKP tracker value-based** en `Settings`. Cuando está encendido, cada item dropeado genera puntos = `precio_mercado / divisor` que se reparten entre los attendees (SOLO si fue para uno; PARTY/N si el reparto es entre N). El leader también puede registrar bonus manuales tipo EVENT (asistencia semanal, etc.). Los puntos por evento existentes (BOSS/EPIC/SIEGE/FARM) siguen funcionando como siempre — los dos ledgers conviven, el leader decide cuál usa para repartir.

Disponible desde `Settings` → "DKP Value Tracker". Al activarlo aparece el link "Tracker" en la nav con leaderboard, filtros y entrada manual.
MD;

        $bodyEn = <<<'MD'
Each CP can now optionally enable a **value-based DKP tracker** in `Settings`. When on, every looted item awards points = `market_price / divisor` split among attendees (SOLO if a single member got it; PARTY/N when N members split it). Leaders can also log manual EVENT bonuses (weekly attendance, etc.). The existing per-event points (BOSS/EPIC/SIEGE/FARM) keep working unchanged — both ledgers coexist and the leader picks which one to share loot with.

Available under `Settings` → "DKP Value Tracker". Once on, the "Tracker" nav link appears with leaderboard, filters, and manual entry.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Optional DKP value tracker per CP'],
            [
                'audience' => 'web',
                'title_es' => 'DKP value tracker opcional por CP',
                'title_en' => 'Optional DKP value tracker per CP',
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'Optional DKP value tracker per CP')
            ->delete();
    }
};
