<?php

namespace Database\Seeders;

use App\Contexts\System\Domain\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            // Welcome Page
            ['key' => 'welcome.title', 'value' => 'AdenaLedger'],
            ['key' => 'welcome.subtitle', 'value' => 'Loot, Adena y Warehouse con transparencia total.'],
            ['key' => 'welcome.stats.items', 'value' => 'LU4'],
            ['key' => 'welcome.stats.items_label', 'value' => 'Base de datos de items'],
            ['key' => 'welcome.stats.transparency', 'value' => '100%'],
            ['key' => 'welcome.stats.transparency_label', 'value' => 'Trazabilidad'],
            ['key' => 'welcome.btn.dashboard', 'value' => 'Ir al Dashboard'],
            ['key' => 'welcome.btn.login', 'value' => 'Identificarse'],
            ['key' => 'welcome.btn.register', 'value' => 'Tengo Invitación'],

            // Main Menu
            ['key' => 'menu.home', 'value' => 'Inicio'],
            ['key' => 'menu.party', 'value' => 'Party'],
            ['key' => 'menu.loot', 'value' => 'Loot'],
            ['key' => 'menu.profile', 'value' => 'Perfil'],
            ['key' => 'menu.admin_cp', 'value' => 'Admin CP'],

            // Admin Dashboard
            ['key' => 'admin.dashboard.title', 'value' => 'Panel de Administración Global'],
            ['key' => 'admin.dashboard.total_cps', 'value' => 'Total Const Parties'],
            ['key' => 'admin.dashboard.total_items', 'value' => 'Total Items Registrados'],
            ['key' => 'admin.dashboard.pending_tx', 'value' => 'Transacciones Pendientes'],
            ['key' => 'admin.dashboard.actions_title', 'value' => 'Acciones Administrativas'],
            ['key' => 'admin.dashboard.active_cps_title', 'value' => 'Constant Parties Activas'],
            ['key' => 'admin.dashboard.btn.gen_link', 'value' => 'Generar Link CP'],
            ['key' => 'admin.dashboard.btn.sync_db', 'value' => 'Forzar Sync Items'],
            ['key' => 'admin.dashboard.btn.translations', 'value' => 'Mis Traducciones'],
            ['key' => 'admin.dashboard.modal.title', 'value' => 'Registrar Nueva Party'],
            ['key' => 'admin.dashboard.modal.name_label', 'value' => 'Nombre de la CP'],
            ['key' => 'admin.dashboard.modal.server_label', 'value' => 'Servidor'],
            ['key' => 'admin.dashboard.modal.btn.cancel', 'value' => 'Cancelar'],
            ['key' => 'admin.dashboard.modal.btn.create', 'value' => 'Crear y Generar Link'],
        ];

        foreach ($translations as $translation) {
            Translation::updateOrCreate(
                ['key' => $translation['key'], 'language' => 'es'],
                ['value' => $translation['value']]
            );
        }
    }
}
