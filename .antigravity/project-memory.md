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
  - Estadísticas de todo el servidor.
  - Gestión general de base de datos de Items (cruds base).
- **CP Leader**:
  - Administra su propia Const Party.
  - Invitar miembros (generación de enlace único/código).
  - Configurar puntos de eventos (FARM, BOSS, etc.).
  - Aprobar o denegar `LootEntries` pendientes.
  - Asignar items crafteados/dropeados a miembros de la CP.
  - Marcar ítems en la *Wishlist*.
  - Vista restringida sólo a su CP.
- **Member**:
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
