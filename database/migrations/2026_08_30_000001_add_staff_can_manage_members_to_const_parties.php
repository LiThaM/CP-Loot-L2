<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            // Opt-in del líder fundador: con el flag activo, los miembros con
            // rol cp_leader o accountant pueden ver el invite code y aprobar
            // solicitudes pendientes. Regenerar el código sigue siendo
            // exclusivo del fundador (y admin).
            $table->boolean('staff_can_manage_members')->default(false)->after('tracker_round_points_up');
        });
    }

    public function down(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            $table->dropColumn('staff_can_manage_members');
        });
    }
};
