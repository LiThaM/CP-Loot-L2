<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial del paquete comunitario GPS (bug E de bugsApi/BUGS.md).
        // El blob "vivo" está en client_blobs/gps/mapdata.npz; cada cambio
        // (upload o revert) deja copia en gps/mapdata_versions/{id}.npz y
        // una fila aquí. Se conservan las ~10 últimas para poder revertir
        // si un cliente sube basura. La fila más reciente == blob actual.
        Schema::create('gps_mapdata_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anon_token_id')->nullable()
                ->constrained('anon_tokens')->nullOnDelete();
            $table->string('storage_path');
            $table->string('sha256', 64)->index();
            $table->unsignedBigInteger('size_bytes');
            $table->string('source', 20)->default('upload'); // upload | revert
            $table->foreignId('reverted_from_id')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_mapdata_versions');
    }
};
