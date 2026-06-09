---
title: AdenaLedger — Plataforma de gestión y transparencia para Constructed Parties de Lineage II
subtitle: Documento de presentación para EGlobal (LU4)
author: AdenaLedger
date: Junio 2026
lang: es
geometry: margin=2cm
fontsize: 11pt
mainfont: Helvetica
---

# Naturaleza del proyecto

> **AdenaLedger es un proyecto sin ánimo de lucro.** Nació como pasatiempo y mejora de nuestro propio uso interno como CP de Lineage II. Lo liberamos **gratis a la comunidad** para que cualquier otro CP pueda utilizarla, sin paywalls, sin planes premium, sin recopilación de datos comerciales. No buscamos monetización ni ahora ni en el futuro. Esta presentación a EGlobal es una propuesta de colaboración por visibilidad mutua, no una propuesta económica.

# Resumen ejecutivo

**AdenaLedger** ([adenaledger.com](https://adenaledger.com)) es una plataforma web especializada en la gestión de **Constructed Parties (CP)** de Lineage II: el grupo estable de 7–9 jugadores que farmea, raidea y reparte adena/items conjuntamente. Sustituye las hojas de cálculo improvisadas y los grupos de Discord por una herramienta diseñada de raíz para el flujo real de un CP — con auditoría, transparencia y reparto justo de las recompensas.

A día de hoy la plataforma sirve a **4 CPs activas con 40 usuarios** registrados, ha procesado **288 reportes de loot confirmados** y mantiene un catálogo de **22.960 items** y **6.680 recetas de crafting** extraídos automáticamente de fuentes oficiales (`wikipedia1.mw2.wiki/lu4`, Elmore, L2Hub).

Soporta **11 crónicas** (C1 → LU4), pero el desarrollo se centra en **LU4** por ser el referente comunitario más activo. Acudimos a **EGlobal** como propietarios de LU4 únicamente para proponer una colaboración no comercial: visibilidad mutua, sincronización de datos si os interesa, y un recurso gratuito recomendado para vuestra comunidad.

---

# El problema

Cualquier CP que lleve más de un par de semanas farmeando se topa con los mismos dolores:

1. **Reparto sin trazabilidad** — alguien drop un Tateossian, el leader dice "te lo doy a ti", y dos semanas después nadie recuerda quién recibió qué.
2. **Cálculo de adena tedioso** — un sell de 80M en mercado se reparte entre 7 attendees con 20% al pot del CP. Excel funcional pero cero auditoría.
3. **Disputas internas** — "tú llevas más BoG que yo", "el último FoG fue para Cardo, no para Tito"; sin registro objetivo, la palabra del leader es la última.
4. **Onboarding penoso** — entra un miembro nuevo, hereda un Discord caótico con 6 meses de mensajes de loot. No hay forma de saber el estado del fondo común ni qué se le debe.
5. **Farmers externos** — gente que vino a un raid pero no es del CP. Olvidados al sellear → enfado, pérdida de reputación social.

AdenaLedger ataca cada uno de estos puntos con datos persistentes, auditoría completa y reglas configurables.

---

# ¿Qué es AdenaLedger?

Una **aplicación web SaaS** construida sobre Laravel 11 + Vue 3 + Inertia, multilingüe (ES/EN), con desktop client opcional. Cada CP es independiente: tiene su almacén compartido (Vault), su roster de miembros, sus reglas, su pool de adena y su histórico cerrado.

Cuatro pilares:

1. **Reporte de loot** — Cualquier miembro reporta una sesión (FARM/BOSS/EPIC/SIEGE) con los items dropeados, attendees (incluyendo farmers externos al CP) e imagen del Alt+L como prueba. El leader la aprueba o rechaza.

2. **Almacén común (Vault)** — Todo el botín se almacena virtualmente. El leader asigna items a miembros concretos, los vende en lote con reparto automático de la adena, o usa el sistema de subastas internas.

3. **Distribución de adena** — Sistema completo de tracking: `gained` / `paid` / `owed` por miembro, con multi-source FIFO al sellear, share configurable para el pot del CP, payouts externos para no-miembros y dual-sistema de puntos (eventos vs DKP value-based).

4. **Transparencia total** — Audit log de cada acción, changelog con notificaciones por email a CP leaders, tutoriales interactivos por pantalla, y un dashboard con estadísticas profundas (charts de 30 días, heatmap de actividad, ranking de miembros, top items).

---

# Usuarios y roles

| Rol | Vista predominante | Acciones clave |
|---|---|---|
| **Miembro** | Dashboard personal, reportar loot | Crear sesiones, ver su saldo adena, ver ranking del CP |
| **CP Leader** | Vault + gestión de miembros | Aprobar/rechazar loot, sellear, asignar items, subastar, configurar reglas |
| **Accountant** | Mismas que leader, sin transferir liderazgo | Pensado para co-líderes de confianza |
| **Admin (EGlobal/owner)** | Panel /system | Crashes, releases, users globales, gestión multi-CP |

Cada CP tiene **siempre un leader fundador** + cualquier número de accountants. El sistema de roles es estricto (anti-mass-assignment, separación de privilegios documentada).

---

# Características principales

## 1. Reporte y aprobación de loot

- Modal de "Nueva sesión" con búsqueda incremental de items (ranking por uso real de la comunidad — items frecuentes flotan arriba).
- Tipos de evento: FARM, BOSS, EPIC, SIEGE (puntos configurables por CP).
- Attendees: miembros internos del CP + externos (farmers de fuera, identificados sólo por nombre).
- Prueba gráfica obligatoria (configurable por CP) — pantallazo del Alt+L para evitar disputas.
- Workflow de 2 fases: el miembro reporta `pending`, el leader confirma → `confirmed` (lockea adena distribution + puntos).
- Histórico completo con filtros (rango fechas, tipo, miembro, item) y vista de detalle expandible.

## 2. Vault (almacén compartido)

- Inventory en tiempo real con valor estimado del stock (basado en `market_price` user-set o `npc_sell_price` scrapeado como fallback).
- Filtros por categoría, grade (S/A/B/C/D/NG), búsqueda, vista cards o lista.
- Distribución del vault por grade (donut chart) — útil para decidir si vender o conservar.
- Tracking automático incoming/outgoing/stock-actual por item, con voiding atómico (cancelar un sell devuelve adena + items).

## 3. Distribución de adena

- **Sell flow multi-source FIFO**: el leader vende 500 BoG. El sistema busca las FARM más antiguas que aportaron BoG y distribuye el ingreso entre los attendees de aquellos farms, no entre los miembros actuales (justicia temporal — quien estuvo el día del drop cobra).
- **CP share**: configurable 0–100%. P. ej. 20% al pot del CP para fondos comunes, 80% a los attendees.
- **Adena offset**: al asignar un item a un miembro, su deuda baja por el valor del item. Inmediato, automático.
- **External payouts**: si el sell incluye a un farmer externo, su parte queda registrada en una página separada `/system/external-payouts` con marca de "pagado" cuando el leader le ha transferido in-game.

## 4. Sistema DKP value-based (opcional por CP)

Implementación inspirada en L2CPTracker, integrada de fábrica. Cuando un CP activa el toggle en Settings:

- Cada item dropeado genera `puntos = precio_mercado / divisor` automáticamente, repartidos entre attendees (badge SOLO / PARTY/N).
- El divisor (50–2000, default 1000) lo ajusta el leader según el ritmo de la economía del servidor.
- Cuando el leader asigna un item del almacén a un miembro, se descuentan automáticamente puntos al receiver (con checkbox "regalo" para excepciones).
- Permite saldo negativo — el sistema avisa pero no bloquea; motiva al miembro a participar para recuperar.
- Convive en paralelo con los puntos por evento clásicos (event-based). El leader elige cuál usa para repartir.

## 5. Sistema de subastas

- El leader pone un item del vault en subasta: elige moneda (DKP points o adena), puja inicial, buy-now opcional, duración (15min a 3 días).
- Los miembros pujan sin escrow — `disponible = balance - commitments en otras subastas activas donde lidero`. Si te sobrepujan, tus puntos vuelven a estar libres automáticamente.
- Un cron horario cierra subastas expiradas. El leader entra y pulsa "Entregar" para asignar el item + cobrar al ganador.
- Picker conectado al vault real del CP — sólo se pueden subastar items que tienes en stock.
- Histórico completo (activas / cerradas pendientes / entregadas).

## 6. Crafting y bulk planning

- Catalogo de 6.680 recipes scrapeado de fuentes LU4.
- Planificador de bulk craft: declaras "quiero 50 BoG", el sistema calcula recursividad de materiales, te dice qué necesitas comprar y qué tienes ya en el vault.
- Tracking de adena fee y MP costs por recipe.
- Workflow de consumo: el craft consume materiales del vault y produce el output, ambos quedan registrados con audit trail.

## 7. Estadísticas avanzadas (`/party/stats`)

Pantalla deep-dive por CP con:
- KPIs comparativos (reports + delta vs periodo anterior, adena in/out, vault value, miembros activos).
- Charts: tendencia de reports stackeada por event_type, adena flow in vs out (30/60/90 días).
- Top 10 items dropeados con valor estimado.
- Heatmap miembro × día (actividad).
- Distribución del vault por grade (donut).
- Financial scoreboard: ratio pagado, top 5 deudores.
- DKP tracker leaderboard inline (si está activo).

Stats personales en `/profile/stats`: tu posición en el ranking, charts de tus puntos y adena por día, top items que has recibido, calendario de actividad.

## 8. Gestión de personajes y crónica

- Cada usuario registra sus chars de Lineage II (nombre, clase, raza, level).
- El catálogo de clases se **filtra automáticamente por la crónica de la CP**: una CP en IL no ve Kamael; una CP en LU4 ve las 69 clases canónicas.
- Soporte para 11 crónicas: C1, C2, C3, C4, C5, IL, CT1, GF, HB, Classic, LU4.

## 9. Tutoriales y onboarding

- Sección [/tutoriales](https://adenaledger.com/tutoriales) con bloque por cada pantalla del sistema (16 topics: miembro 9, leader 9 extra) con guion detallado, links cruzados y, donde aplica, **tour interactivo** (driver.js) que recorre la UI real explicando cada elemento.
- Páginas pública y privada de changelog. El cron envía email cada vez que se publica una feature nueva (opt-out por usuario).

## 10. Mobile

- Diseño responsive desde el día 1.
- Bottom-nav con FAB de "Report Loot" (acción más frecuente del miembro).
- Safe-area iOS (notch + home indicator).
- Tablas anchas con scroll horizontal sin clipping.
- Modals con anchos mobile-safe.

---

# Crónicas soportadas

| Crónica | Estado | Filtros aplicados |
|---|---|---|
| C1, C2, C3, C4, C5 | Soportado | Sin Kamael (no existían) |
| Interlude (IL) | Soportado | Sin Kamael |
| CT1 | Soportado | Catálogo completo (Kamael añadido aquí) |
| Gracia Final (GF) | Soportado | Catálogo completo |
| High Five (HB) | Soportado | Catálogo completo |
| Classic | Soportado | Sin Kamael, recortes de 3ª job |
| **LU4** | **Soportado · referente principal** | Catálogo completo, recipes y items scrapeados de wikipedia1.mw2.wiki/lu4 |

LU4 es la crónica con mayor cobertura: items con `market_price` user-editable + `npc_sell_price` scrapeado, recipes con outputs y materiales completos, y la única donde el scraper recorre el catálogo cada vez que se añade un item nuevo en wiki.

---

# Arquitectura técnica

| Capa | Tecnología | Notas |
|---|---|---|
| Backend | Laravel 11 (PHP 8.2+) | MySQL 8 prod, SQLite en CI/tests |
| Frontend | Vue 3 + Inertia.js + TailwindCSS | SPA con SSR-friendly meta tags |
| Build | Vite | Bundle 433 KB gzipped 144 KB |
| Charts | Chart.js + vue-chartjs | Dark-mode aware |
| Auth | Laravel Sanctum + sessions | 2FA roadmap |
| Mail | Mailgun (prod) / log (dev) | Sync send, opt-out por user |
| Cron | Laravel scheduler | 4 jobs activos: hourly (changelog notify, digit consensus), nightly (usage rebuild), every minute (auction close) |
| Tests | PHPUnit | **253 tests, 1007 assertions, todos verde** |

**Estructura DDD-style**: el código se organiza por contextos (`app/Contexts/{Party,Loot,Identity,System,ClientApi}`). Los modelos viven dentro de su contexto en lugar de en un `app/Models/` plano — refleja el dominio del negocio.

**Ritmo de desarrollo**: 172 commits totales, **117 en las últimas 4 semanas** (~4 commits diarios). Features de las últimas semanas: avatares de usuario, rediseño de profile, sistema de subastas, DKP spend on assign, filtro de clases por crónica, audit de mobile, refactor de navegación, sección "Me", changelog email transaccional.

---

# Seguridad y privacidad

- **Anti-mass-assignment** estricto. `role_id`, `leader_id`, `cp.is_active` no son fillable — se cambian sólo por code paths autorizados con `forceFill()`.
- **Validación server-side** en cada FormRequest (no se confía en el cliente).
- **Impersonation** segura para admins: el banner es visible, las acciones quedan auditadas como del admin, y el impersonado no pierde su `changelog_last_seen_at` ni acepta reglas a su nombre.
- **Inline markdown** sanitizado para changelog: sólo `https://` y rutas relativas se renderizan como links; `javascript:` y similares quedan como texto.
- **Image uploads** con validación de mime + tamaño + GD resize server-side (avatares a 512×512 JPG q85).
- **Email opt-out** por usuario para los avisos transaccionales (GDPR-friendly).
- **CSP-ready** headers y CSRF token en cada formulario.

---

# Modelo de datos (resumen)

Tablas clave:

- `const_parties` — la CP en sí (nombre, server, chronicle, logo, divisor DKP).
- `users` — miembros, con `cp_id` y `role_id`.
- `characters` — los chars L2 de cada user.
- `loot_reports` + `loot_entries` + `loot_report_attendees` — el flujo de loot.
- `points_logs` — adena gained/payout/offset.
- `tracker_contributions` — DKP value-based ledger.
- `cp_auctions` + `cp_auction_bids` — subastas.
- `items` (22.960) + `recipes` (6.680) — catálogo scrapeado.
- `audit_logs` + `audit_alerts` — trazabilidad de toda acción sensible.
- `translations` — i18n ES/EN con > 800 keys.
- `changelog_entries` — publicación de releases con notificación por email.

---

# Roadmap

Cercano (próximas semanas):
- Anti-snipe en subastas (extender ends_at si alguien puja en el último minuto).
- Backfill manual de DKP histórico al activar tracker.
- Sweeper de CPs huérfanas (auto-cleanup de requests abandonados).
- Notificaciones por mail al sobrepujar / ganar subasta.

Medio plazo:
- API pública REST para integrarse con bots de Discord.
- Plantillas de reglas para CPs nuevas (curated por la comunidad).
- Marketplace inter-CP (vender stock a otras CPs del mismo servidor).
- 2FA opcional (admin obligatorio).

Largo plazo:
- Mobile app nativa (PWA actualmente OK).
- Heatmap de drop rates por zona/RB.
- Recommendation engine: "tu CP debería estar farmeando X según el meta del servidor".
- Integración oficial con servidores asociados — el server reconoce AdenaLedger como herramienta acreditada y aparece un enlace en su web.

---

# Propuesta de colaboración con EGlobal

Antes de listar vías: **reiteramos que esta propuesta no tiene componente económico**. No pedimos dinero ni ofrecemos servicios de pago. AdenaLedger es y será gratuita para vuestros jugadores. Lo que sí buscamos es visibilidad y colaboración técnica para que la herramienta sirva mejor a la comunidad LU4.

**LU4 es nuestro servidor de referencia**: todos los scrapers, todos los datasets y la cobertura de items y recipes está alineada con vuestra crónica. Los CPs activos que usan AdenaLedger hoy son de LU4. El feedback que mejor capturamos es de vuestros jugadores.

Vías de colaboración que proponemos (todas opt-in):

1. **Enlace oficial recomendado** — Un link a [adenaledger.com](https://adenaledger.com) en la web de LU4 o en vuestro panel del servidor para que las CPs nuevas lo encuentren al instante. A cambio, AdenaLedger destaca "Oficialmente recomendado por LU4" en su landing.

2. **Sincronización de catálogo** — Hoy scrapeamos `wikipedia1.mw2.wiki/lu4`. Si EGlobal mantiene un feed estructurado de items/recipes (JSON, CSV), lo integramos como fuente primaria para tener datos siempre frescos en lugar de scrapear con un crawler.

3. **Funcionalidades exclusivas LU4** — Customizaciones únicas de vuestro server (eventos puntuales, items custom, multipliers de XP) las modelamos de fábrica para CPs LU4. Ya hacemos esto a nivel general; con datos vuestros podemos ser exhaustivos.

4. **Branding co-marketing** — Si interesa, podemos crear un **theme "LU4"** opcional (colores, logo, copy) que cualquier CP de LU4 pueda activar en su `/party` para sentirse "en casa".

5. **Datos agregados anonimizados** — Podemos exportar a EGlobal métricas de uso (sin PII): cuántas CPs activas hay, ritmo de raids, items más farmeados, tendencias de adena. Útil para vuestro propio analytics y decisiones de balance.

6. **Software gratuito y abierto al uso** — La plataforma es y será gratuita para el usuario final. El proyecto se mantiene con donaciones voluntarias (botón de donaciones en `/profile`) que cubren coste de hosting; no hay planes premium ni los habrá. Cualquier CP de cualquier servidor puede usarlo sin coste.

Estamos disponibles para integración técnica, demo en directo, o cualquier feedback que vuestro equipo quiera trasladarnos. La plataforma está en producción y operativa hoy.

---

# Contacto

- **Web**: [adenaledger.com](https://adenaledger.com)
- **Soporte**: support@adenaledger.com
- **Documentación pública**: tutoriales accesibles dentro de la app

---

*AdenaLedger es un proyecto comunitario independiente, sin afiliación contractual previa con EGlobal o con NCSoft. Todos los nombres de items, clases y razas de Lineage II son propiedad de NCSoft.*
