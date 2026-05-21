<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'system.cps.nav'              => ['es' => 'CPs',                                      'en' => 'CPs'],
        'system.cps.page_title'       => ['es' => 'Gestión de CPs',                           'en' => 'CP management'],
        'system.cps.subtitle'         => ['es' => 'Listado completo de Constant Parties — buscar, editar, impersonar, activar/desactivar', 'en' => 'Full Constant Parties roster — search, edit, impersonate, activate/deactivate'],

        'system.cps.action.new'       => ['es' => 'Nueva CP',                                 'en' => 'New CP'],
        'system.cps.action.edit'      => ['es' => 'Editar',                                   'en' => 'Edit'],
        'system.cps.action.impersonate' => ['es' => 'Impersonar líder',                       'en' => 'Impersonate leader'],
        'system.cps.action.activate'  => ['es' => 'Activar CP',                               'en' => 'Activate CP'],
        'system.cps.action.deactivate' => ['es' => 'Desactivar CP',                           'en' => 'Deactivate CP'],

        'system.cps.col.name'         => ['es' => 'Nombre',                                   'en' => 'Name'],
        'system.cps.col.chronicle'    => ['es' => 'Crónica',                                  'en' => 'Chronicle'],
        'system.cps.col.leader'       => ['es' => 'Líder',                                    'en' => 'Leader'],
        'system.cps.col.members'      => ['es' => 'Miembros',                                 'en' => 'Members'],
        'system.cps.col.cp_fund'      => ['es' => 'Fondo CP (adena)',                         'en' => 'CP fund (adena)'],
        'system.cps.col.reports'      => ['es' => 'Reportes',                                 'en' => 'Reports'],
        'system.cps.col.last_activity' => ['es' => 'Última actividad',                        'en' => 'Last activity'],
        'system.cps.col.actions'      => ['es' => 'Acciones',                                 'en' => 'Actions'],

        'system.cps.badge.inactive'   => ['es' => 'inactiva',                                 'en' => 'inactive'],
        'system.cps.empty'            => ['es' => 'Ninguna CP cumple los filtros actuales.', 'en' => 'No CPs match the current filters.'],

        'system.cps.filters.search_ph'        => ['es' => 'Buscar por nombre, líder o servidor…', 'en' => 'Search by name, leader or server…'],
        'system.cps.filters.all_chronicles'   => ['es' => 'Todas las crónicas',                'en' => 'All chronicles'],
        'system.cps.filters.status.all'       => ['es' => 'Todas',                             'en' => 'All'],
        'system.cps.filters.status.active'    => ['es' => 'Activas',                           'en' => 'Active'],
        'system.cps.filters.status.inactive'  => ['es' => 'Inactivas',                         'en' => 'Inactive'],
        'system.cps.filters.status.empty'     => ['es' => 'Sin miembros',                      'en' => 'Empty'],

        'system.cps.requests.title'   => ['es' => 'Solicitudes pendientes de CP',             'en' => 'Pending CP requests'],
        'system.cps.requests.approve' => ['es' => 'Aprobar',                                  'en' => 'Approve'],
        'system.cps.requests.reject'  => ['es' => 'Rechazar',                                 'en' => 'Reject'],

        'system.cps.confirm.activate_title'    => ['es' => '¿Activar CP?',                    'en' => 'Activate CP?'],
        'system.cps.confirm.deactivate_title'  => ['es' => '¿Desactivar CP?',                 'en' => 'Deactivate CP?'],
        'system.cps.confirm.activate_ok'       => ['es' => 'Sí, activar',                     'en' => 'Yes, activate'],
        'system.cps.confirm.deactivate_ok'     => ['es' => 'Sí, desactivar',                  'en' => 'Yes, deactivate'],
        'system.cps.confirm.toggle_text'       => ['es' => '¿Cambiar el estado de "{name}"?',  'en' => 'Toggle status of "{name}"?'],
        'system.cps.confirm.delete_title'      => ['es' => '¿Eliminar CP?',                   'en' => 'Delete CP?'],
        'system.cps.confirm.delete_text'       => ['es' => '"{name}" se eliminará. Solo se permite si está vacía.', 'en' => '"{name}" will be deleted. Only allowed if empty.'],

        'system.cps.edit.title'        => ['es' => 'Editar CP',                                'en' => 'Edit CP'],
        'system.cps.edit.name'         => ['es' => 'Nombre',                                   'en' => 'Name'],
        'system.cps.edit.server'       => ['es' => 'Servidor (opcional)',                      'en' => 'Server (optional)'],
        'system.cps.edit.chronicle'    => ['es' => 'Crónica',                                  'en' => 'Chronicle'],

        'system.cps.create.title'      => ['es' => 'Crear CP',                                 'en' => 'Create CP'],

        // common.save / common.cancel / common.delete may already exist
        'common.save'                  => ['es' => 'Guardar',                                  'en' => 'Save'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $exists = DB::table('translations')->where('key', $key)->where('language', $lang)->exists();
                if (! $exists) {
                    $rows[] = ['language' => $lang, 'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        if (! empty($rows)) {
            DB::table('translations')->insert($rows);
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
