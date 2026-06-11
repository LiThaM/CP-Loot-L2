<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reportes de fallo de calibración (bug D de bugsApi/BUGS.md).
        // Los manda el cliente: el calibrador cuando el usuario no logra
        // marcar la región (meta sin `kind`) y el overlay cuando lleva
        // ~45s leyendo 0% (`kind = runtime_zero_readings`). El frame PNG
        // va a client_blobs/calibration/failures/{id}.png; el meta entero
        // se guarda en meta_json (calibration, readings, game_size...).
        Schema::create('calibration_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->nullable()
                ->constrained('anon_tokens')->nullOnDelete();
            $table->string('kind', 50)->default('calibrator')->index();
            $table->string('char_name', 100)->nullable();
            $table->string('app_version', 50)->nullable()->index();
            $table->json('meta_json');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('image_bytes')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_failures');
    }
};
