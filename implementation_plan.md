# Plan de Implementación: Sistema de Roles, Invitación e Inmutabilidad

Este plan detalla la reestructuración final del sistema de permisos, la adición del rol de Contable y la visibilidad de invitaciones.

## Propuestas de Cambio

### 1. Sistema de Reclutamiento (Invitación)

#### [MODIFY] [Party/Index.vue](file:///c:/Alex/Proyectos/cp-loot-l2/resources/js/Pages/Party/Index.vue)
- Añadir sección en la cabecera para ver el código y copiar el enlace de invitación (`/register?invite=CODE`).

#### [MODIFY] [Auth/Register.vue](file:///c:/Alex/Proyectos/cp-loot-l2/resources/js/Pages/Auth/Register.vue)
- Auto-completar el campo `invite_code` desde la URL.

### 2. Jerarquía de Roles y Permisos

#### [MODIFY] [RoleSeeder.php](file:///c:/Alex/Proyectos/cp-loot-l2/database/seeders/RoleSeeder.php)
- Añadir el rol `accountant` (Contable).

#### [MODIFY] [UserManagementController.php](file:///c:/Alex/Proyectos/cp-loot-l2/app/Contexts/System/Application/Controllers/UserManagementController.php)
- **Lógica de Promoción**: 
    - El **Líder Fundador** (`leader_id`) puede promover a cualquier miembro a `cp_leader` o `accountant`, y degradarlos.
    - Los **Co-Líderes** pueden promover a miembros a `accountant` o `cp_leader`, pero **NO pueden degradar** a otros líderes.
    - El **Contable** solo tiene acceso de lectura a la auditoría.

#### [MODIFY] [AdenaActionController.php](file:///c:/Alex/Proyectos/cp-loot-l2/app/Contexts/Loot/Application/Controllers/AdenaActionController.php)
- Permitir que usuarios con el rol `accountant` realicen transacciones de Adena (pagos/abonos).

#### [MODIFY] [LootActionController.php](file:///c:/Alex/Proyectos/cp-loot-l2/app/Contexts/Loot/Application/Controllers/LootActionController.php)
- Restringir la resolución de reportes de loot **exclusivamente** a Admins y Líderes. El Contable **no** puede aprobar loot.

### 3. Inmutabilidad y Auditoría

#### [MODIFY] [LootActionController.php](file:///c:/Alex/Proyectos/cp-loot-l2/app/Contexts/Loot/Application/Controllers/LootActionController.php)
- Cambiar la lógica de `resolve` para que al rechazar un reporte se marque como `rejected` en lugar de borrarlo.
- Eliminar cualquier botón de "Eliminar" para usuarios que no sean SuperAdmin.

## Verificación

### Pruebas de Sistema
- Usar un link de invitación para registrarse.
- El líder principal promueve a un miembro a Contable.
- Verificar que el Contable puede pagar Adena pero NO puede aprobar reportes de loot.
- Verificar que un Co-Líder no puede degradar al Líder Fundador.
