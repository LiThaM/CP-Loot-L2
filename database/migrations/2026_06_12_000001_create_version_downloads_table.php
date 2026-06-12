<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telemetría de adopción de updates (bug H de bugsApi/BUGS.md):
        // el cliente avisa al descargar+verificar el ZIP de un update,
        // justo antes del swap. Una fila por (install, versión destino).
        Schema::create('version_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->nullable()
                ->constrained('anon_tokens')->nullOnDelete();
            $table->string('from_version', 50)->nullable();
            $table->string('to_version', 50)->index();
            $table->timestamps();

            // Idempotencia: el POST es best-effort con retry posible —
            // mismo install + misma versión destino no crea fila nueva.
            $table->unique(['anon_token_id', 'to_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_downloads');
    }
};
