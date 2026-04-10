# Project Memory: L2 CP Loot Manager

## Arquitectura y Decisiones Técnicas
- **Backend**: Laravel 11+ (PHP 8.2+).
- **Frontend**: Vue.js 3 (Composition API) + Tailwind CSS.
- **Estilo Visual**: Mobile-First, 100% Responsive, Dark Theme (Estética Lineage II), UI Intuitiva (Swipe, botones grandes).
- **Patrón Arquitectónico**: Domain-Driven Design (DDD). Separación estricta entre:
  - **Domain**: Lógica de negocio (Entidades, Interfaces, Value Objects).
  - **Application**: Casos de uso de la aplicación, DTOs.
  - **Infrastructure**: Controladores de Laravel, Modelos de Eloquent, Comandos, Servicios de Scraping.
- **API**: Stateless y Responsive design para comunicación entre el Frontend (Vue) y el Backend.
- **Internacionalización**: Basada en base de datos (`translations` table). Idioma por defecto y base de código en Inglés.

## Entidades de Dominio (Domain Entities)
1. **User**: Representa a un jugador/usuario del sistema.
2. **Role**: Representa los roles disponibles y sus permisos.
3. **ConstParty (CP)**: Agrupación de usuarios (Party).
4. **Item**: Objeto base del juego (id, nombre, grado, categoría, imagen).
5. **LootEntry**: Registro de un item looteado por la CP (Estado: Pending, Confirmed, Sold).
6. **PointsLog**: Registro de eventos y puntos ganados por un miembro (FARM, BOSS, EPIC, SIEGE).
7. **Translation**: Gestión de i18n desde BD.
8. **Wishlist**: Objetos priorizados o buscados por el Leader de la CP para sus miembros.
9. **AuditLog**: Trazabilidad de cambios de estado de un item.

## Roles y Permisos
- **Admin**:
  - Vista global de todas las CP.
  - **Estadísticas globales de todas las CPs y del servidor.**
  - Gestión general de base de datos de Items (cruds base).
  - **Crea nuevas Const Parties y genera un link de registro para un nuevo CP Leader.**
- **CP Leader**:
  - **Se registra utilizando un link/código proporcionado por un Admin y esto lo asocia a su nueva Const Party.**
  - **Estadísticas globales de su propia CP y de sí mismo.**
  - Administra su propia Const Party.
  - Invitar miembros (generación de enlace único/código exclusivo para su CP).
  - Configurar puntos de eventos (FARM, BOSS, etc.).
  - Aprobar o denegar `LootEntries` pendientes.
  - Asignar items crafteados/dropeados a miembros de la CP.
  - Marcar ítems en la *Wishlist*.
  - Vista restringida sólo a su CP.
- **Member**:
  - **Se registra utilizando el link/código proporcionado por el CP Leader.**
  - **Estadísticas exclusivas de su propio progreso.**
  - Subir fotos/registros de items looteados.
  - Ver sus puntos (Score) y los drops/tributos propios.
  - Confirmar recepciones de items.

## Progreso de Tareas
- [x] Crear estructura base de `.antigravity/project-memory.md`
- [x] Aprobar Plan de Implementación (Arquitectura inicial de BBDD, Bounded Contexts y Frontend)
- [x] Estructura DDD (Creación de Directorios Base en Laravel)
- [x] Desarrollo de Migraciones (users, roles, const_parties, items, loot, points, translations...)
- [x] Comando de Scraping (`fetch:l2-items` de la wiki de MasterWork)
- [x] Creación de Layout Base en Vue 3 con Tailwind (L2 Dark Theme)
- [x] Fase 2: Implementar roles y flujo de Auth según links de invitación (Admin -> Leader -> Members) + Fakes (Seed/Mock Items)
- [x] Fase 3: Gestión de Party y Loot (Vistas de Panel y Generación de Links Interactivos de Invites)
- [x] Fase 4: Implementación de Sistema de Traducción en Base de Datos
  - [x] Exponer `App\Contexts\System\Domain\Models\Translation` dinámicamente al Frontend a través de Inertia.
  - [x] Eliminar textos *hardcodeados* en componentes (Welcome, Layouts, Dashboards).
  - [x] Interfaz exclusiva de Super Admin para crear, editar y borrar textos globales.
- [x] Fase 5: Gestión de CPs y Visualización para SuperAdmin
  - [x] Listado de CPs en el Dashboard de Admin como "boxes" (tarjetas).
  - [x] Navegación detallada desde la caja de CP a sus estadísticas específicas.
  - [x] Limpieza de base de datos de items con nombres icónicos de Lineage II.
- [x] Fase 6: Sistema de Scraper Multi-Crónica (ElmoreLab)
  - [x] Migración de BD: campos `external_id`, `chronicle`, `source`, `icon_name`, `description`.
  - [x] `ElmoreScraper.php`: Cliente para API `resources-service.elmorelab.com`.
  - [x] `FetchElmoreCommand.php`: Comando Artisan `items:fetch-elmore` con barra de progreso y auto-stop.
  - [x] Importación completada: **25,755 items** (IL: 9,246 | C5: 8,664 | C4: 7,845).
  - [x] Imágenes accesibles via `https://resources.elmorelab.com/images/{icon_name}.jpg`.
