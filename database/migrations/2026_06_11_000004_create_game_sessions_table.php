<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Resumen de cada sesión de farmeo del cliente (bug G de
        // bugsApi/BUGS.md) — la pieza que conecta la app con la web:
        // adenaledger.com pinta perfiles/rankings por personaje desde
        // aquí. OJO: el nombre es game_sessions porque `sessions` ya lo
        // usa Laravel para las sesiones web.
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->nullable()
                ->constrained('anon_tokens')->nullOnDelete();
            $table->string('char_name', 100)->index();
            $table->string('app_version', 50)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedBigInteger('sp')->default(0);
            $table->unsignedBigInteger('adena')->default(0);
            $table->unsignedInteger('mobs_killed')->default(0);
            $table->unsignedInteger('deaths')->default(0);
            $table->unsignedInteger('level_ups')->default(0);
            $table->unsignedBigInteger('xp_per_hour')->default(0);
            $table->unsignedBigInteger('adena_per_hour')->default(0);
            $table->json('items_summary_json')->nullable();
            $table->timestamps();

            $table->index(['char_name', 'ended_at']);
            // Idempotencia del outbox del cliente: un retry de la misma
            // sesión (mismo install + char + started_at) no crea fila nueva.
            $table->unique(['anon_token_id', 'char_name', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
