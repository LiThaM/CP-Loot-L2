---
title: AdenaLedger — Management and transparency platform for Lineage II Constructed Parties
subtitle: Presentation document for EGlobal (LU4)
author: AdenaLedger
date: June 2026
lang: en
geometry: margin=2cm
fontsize: 11pt
mainfont: Helvetica
---

# About this project

> **AdenaLedger is a non-profit project.** It started as a hobby and an internal tool for our own Lineage II CP. We release it **free to the community** so that any other CP can use it — no paywalls, no premium plans, no commercial data collection. We are not seeking monetisation, now or in the future. This presentation to EGlobal is a partnership proposal based on mutual visibility, not a commercial pitch.

# Executive summary

**AdenaLedger** ([adenaledger.com](https://adenaledger.com)) is a web platform purpose-built for managing **Constructed Parties (CPs)** in Lineage II — the stable 7–9 player group that farms, raids and splits adena/items together. It replaces ad-hoc spreadsheets and chaotic Discord threads with a tool designed around the real CP workflow, with audit trails, transparency and fair reward distribution.

As of today the platform serves **4 active CPs with 40 registered users**, has processed **288 confirmed loot reports**, and maintains a catalogue of **22,960 items** and **6,680 crafting recipes** automatically extracted from official sources (`wikipedia1.mw2.wiki/lu4`, Elmore, L2Hub).

It supports **11 chronicles** (C1 → LU4) but the project's focus is **LU4** — by far the most active community reference. We're reaching out to **EGlobal** as LU4 owners to propose a non-commercial collaboration: mutual visibility, optional data sync, and a free resource recommended to your community.

---

# The problem

Any CP that's been farming together for more than a couple of weeks runs into the same pain points:

1. **No traceability of distributions** — someone drops a Tateossian, the leader says "you take it", and two weeks later nobody remembers who actually got what.
2. **Tedious adena maths** — an 80M sell on the market needs to be split among 7 attendees with a 20% cut for the CP fund. Excel works but zero audit.
3. **Internal disputes** — "you've taken more BoG than me", "the last FoG went to Cardo, not Tito"; without an objective record, the leader's word is the last word.
4. **Painful onboarding** — a new member joins, inherits a chaotic 6-month Discord log of loot. No way to know the state of the CP fund or what they're owed.
5. **External farmers** — people who came to a raid but aren't part of the CP. Forgotten when selling → frustration, loss of social reputation.

AdenaLedger tackles every one of these with persistent data, full audit, and configurable rules.

---

# What is AdenaLedger?

A **SaaS web application** built on Laravel 11 + Vue 3 + Inertia, multilingual (ES/EN), with an optional desktop client. Every CP is independent: its own shared vault, member roster, rules, adena pool, and locked history.

Four pillars:

1. **Loot reporting** — Any member reports a session (FARM/BOSS/EPIC/SIEGE) with looted items, attendees (including externals not in the CP), and an Alt+L screenshot as proof. The leader approves or rejects.

2. **Shared vault** — All loot is stored virtually. The leader assigns items to specific members, sells them in batch with automatic adena distribution, or uses the internal auction system.

3. **Adena distribution** — Full tracking system: `gained` / `paid` / `owed` per member, with multi-source FIFO when selling, configurable share for the CP fund, payouts for non-member externals, and a dual points system (event-based vs DKP value-based).

4. **Full transparency** — Audit log on every action, changelog with email notifications to CP leaders, per-screen interactive tutorials, and a dashboard with deep analytics (30-day charts, activity heatmap, member ranking, top items).

---

# Users and roles

| Role | Main view | Key actions |
|---|---|---|
| **Member** | Personal dashboard, report loot | Create sessions, view personal adena, view CP ranking |
| **CP Leader** | Vault + member management | Approve/reject loot, sell, assign items, auction, configure rules |
| **Accountant** | Same as leader (no transfer of leadership) | Designed for trusted co-leaders; with the CP toggle enabled, sees the invite code and approves pending members |
| **Admin (EGlobal/owner)** | /system panel | Crashes, releases, global users, multi-CP management |

Every CP **always has a founding leader** + any number of accountants. The role system is strict (anti-mass-assignment, documented privilege separation). The founder can delegate member management (*staff_can_manage_members* option in Settings): co-leaders and accountants see the invite code and approve pending requests; regenerating the code remains founder-only.

---

# Main features

## 1. Loot reporting and approval

- "New session" modal with incremental item search (ranking by actual community usage — frequent items float to the top).
- Event types: FARM, BOSS, EPIC, SIEGE (CP-configurable points).
- Attendees: internal CP members + externals (outside farmers, identified by name only).
- Screenshot proof mandatory (CP-configurable) — Alt+L screenshot to prevent disputes.
- Two-phase workflow: member reports `pending`, leader confirms → `confirmed` (locks adena distribution + points).
- Full history with filters (date range, type, member, item) and expandable detail view.

## 2. Vault (shared warehouse)

- Real-time inventory with estimated stock value (based on user-set `market_price` or scraped `npc_sell_price` as fallback).
- Filters by category, grade (S/A/B/C/D/NG), search, cards or list view.
- Vault grade distribution (doughnut chart) — useful when deciding to sell or hold.
- Auto incoming/outgoing/current-stock tracking per item, with atomic voiding (cancelling a sell refunds adena + items).

## 3. Adena distribution

- **Multi-source FIFO sell flow**: the leader sells 500 BoG. The system picks the oldest FARMs that contributed BoG and distributes the income among those farms' attendees, not among current members (temporal fairness — whoever was there on the drop day gets paid).
- **CP share**: configurable 0–100%. E.g. 20% to the CP fund for shared expenses, 80% to attendees.
- **Adena offset**: assigning an item to a member reduces their debt by the item's value. Instant and automatic.
- **External payouts**: if a sell includes a non-member farmer, their cut is recorded on a separate page `/system/external-payouts`, flagged "paid" once the leader has transferred it in-game.

## 4. Value-based DKP system (CP opt-in)

Implementation inspired by L2CPTracker, built in. When a CP toggles it on in Settings:

- Every looted item generates `points = market_price / divisor` automatically, distributed among attendees (badge SOLO / PARTY/N).
- The divisor (50–2000, default 1000) is leader-tunable to fit the server's economy pace.
- When the leader assigns an item from the vault to a member, the receiver is automatically charged points (with a "Gift" checkbox for exceptions).
- Allows a negative balance — the system warns but doesn't block; motivates members to play to earn it back.
- Coexists with classic event-based points. The leader picks which one they use to share loot.

## 5. Auction system

- The leader puts a vault item up for auction: picks currency (DKP points or adena), starting bid, optional buy-now, duration (15 min to 3 days).
- Members bid without escrow — `available = balance - commitments on other open auctions where I'm leading`. If you get outbid, your points are freed automatically.
- An hourly cron closes expired auctions. The leader then clicks "Fulfill" to assign the item and charge the winner.
- Picker linked to the actual CP vault — you can only auction items you have in stock.
- Full history (active / pending fulfill / fulfilled).

## 6. Crafting and bulk planning

- Catalogue of 6,680 recipes scraped from LU4 sources.
- Bulk craft planner: declare "I want 50 BoG" and the system recursively computes materials, telling you what you need to buy and what you already have in the vault.
- Per-recipe adena fee + MP cost tracking.
- Consumption workflow: a craft consumes vault materials and produces the output, both recorded with audit trail.

## 7. Advanced statistics (`/party/stats`)

CP deep-dive screen with:
- Comparative KPIs (reports + delta vs previous period, adena in/out, vault value, active members).
- Charts: report trend stacked by event_type, adena flow in vs out (30/60/90 days).
- Top 10 dropped items with estimated value.
- Member × day heatmap (activity).
- Vault distribution by grade (doughnut).
- Financial scoreboard: paid ratio, top 5 debtors.
- Inline DKP tracker leaderboard (when enabled).

Personal stats at `/profile/stats`: your ranking position, daily points and adena charts, top items you received, activity calendar.

## 8. Character and chronicle management

- Each user registers their Lineage II characters (name, class, race, level).
- The class catalogue is **automatically filtered by the CP's chronicle**: a CP on IL never sees Kamael; a CP on LU4 sees all 69 canonical classes.
- Supports 11 chronicles: C1, C2, C3, C4, C5, IL, CT1, GF, HB, Classic, LU4.

## 9. Tutorials and onboarding

- [/tutoriales](https://adenaledger.com/tutoriales) section with a block per screen (16 topics: 9 member, 9 leader extras) with detailed scripts, cross-links and, where applicable, an **interactive tour** (driver.js) walking the user through the actual UI.
- Public and private changelog pages. The cron job emails CP leaders on every new release (per-user opt-out).

## 10. Mobile

- Responsive design from day one.
- Bottom-nav with a "Report Loot" FAB (the member's most frequent action).
- iOS safe-area (notch + home indicator).
- Wide tables with horizontal scroll, no clipping.
- Mobile-safe modal widths.

---

# Supported chronicles

| Chronicle | Status | Filters applied |
|---|---|---|
| C1, C2, C3, C4, C5 | Supported | No Kamael (didn't exist) |
| Interlude (IL) | Supported | No Kamael |
| CT1 | Supported | Full catalogue (Kamael added here) |
| Gracia Final (GF) | Supported | Full catalogue |
| High Five (HB) | Supported | Full catalogue |
| Classic | Supported | No Kamael, 3rd-job recuts |
| **LU4** | **Supported · main reference** | Full catalogue, recipes and items scraped from wikipedia1.mw2.wiki/lu4 |

LU4 has the deepest coverage: items with user-editable `market_price` + scraped `npc_sell_price`, full recipe outputs and materials, and the only chronicle where the scraper recrawls the catalogue whenever a new item appears on the wiki.

---

# Technical architecture

| Layer | Tech | Notes |
|---|---|---|
| Backend | Laravel 11 (PHP 8.2+) | MySQL 8 prod, SQLite in CI/tests |
| Frontend | Vue 3 + Inertia.js + TailwindCSS | SPA with SSR-friendly meta tags |
| Build | Vite | Bundle 433 KB · 144 KB gzipped |
| Charts | Chart.js + vue-chartjs | Dark-mode aware |
| Auth | Laravel Sanctum + sessions | 2FA on roadmap |
| Mail | Mailgun (prod) / log (dev) | Sync send, user opt-out |
| Cron | Laravel scheduler | 4 active jobs: hourly (changelog notify, digit consensus), nightly (usage rebuild), every minute (auction close) |
| Tests | PHPUnit | **253 tests, 1,007 assertions, all green** |

**DDD-style structure**: code organised by context (`app/Contexts/{Party,Loot,Identity,System,ClientApi}`). Models live within their context instead of a flat `app/Models/` — it reflects the business domain.

**Development pace**: 172 total commits, **117 in the last 4 weeks** (~4 commits per day). Recent features: user avatars, profile redesign, auction system, DKP spend on assign, chronicle-based class filtering, mobile audit, navigation refactor, "Me" section, transactional changelog email.

---

# Security and privacy

- Strict **anti-mass-assignment**. `role_id`, `leader_id`, `cp.is_active` are not fillable — only changed through authorised code paths via `forceFill()`.
- **Server-side validation** in every FormRequest (no trust in the client).
- **Safe impersonation** for admins: the banner is visible, actions are audited as the admin's, and the impersonated user doesn't lose their `changelog_last_seen_at` or accept rules in their name.
- **Inline markdown** sanitised for changelog: only `https://` and relative paths are rendered as links; `javascript:` and similar stay as plain text.
- **Image uploads** with mime + size validation + server-side GD resize (avatars at 512×512 JPG q85).
- **Email opt-out** per user for transactional alerts (GDPR-friendly).
- **CSP-ready** headers and CSRF token on every form.

---

# Data model (summary)

Key tables:

- `const_parties` — the CP itself (name, server, chronicle, logo, DKP divisor).
- `users` — members, with `cp_id` and `role_id`.
- `characters` — each user's L2 chars.
- `loot_reports` + `loot_entries` + `loot_report_attendees` — the loot flow.
- `points_logs` — adena gained/payout/offset.
- `tracker_contributions` — value-based DKP ledger.
- `cp_auctions` + `cp_auction_bids` — auctions.
- `items` (22,960) + `recipes` (6,680) — scraped catalogue.
- `audit_logs` + `audit_alerts` — traceability of every sensitive action.
- `translations` — ES/EN i18n with > 800 keys.
- `changelog_entries` — release publication with email notification.

---

# Roadmap

Short-term (next weeks):
- Anti-snipe on auctions (extend ends_at if someone bids in the last minute).
- Manual DKP historical backfill when enabling the tracker.
- Orphan-CP sweeper (auto-cleanup of abandoned requests).
- Mail notifications on getting outbid / winning an auction.

Mid-term:
- Public REST API for Discord bot integrations.
- Curated rule templates for new CPs (community-curated).
- Inter-CP marketplace (sell stock to other CPs on the same server).
- Optional 2FA (mandatory for admins).

Long-term:
- Native mobile app (PWA already OK).
- Heatmap of drop rates per zone/RB.
- Recommendation engine: "your CP should be farming X according to the server meta".
- Official integration with partner servers — the server recognises AdenaLedger as a trusted tool and links it from their site.

---

# Partnership proposal with EGlobal

Before listing the avenues: **we restate that this proposal has no commercial component**. We are not asking for money nor offering paid services. AdenaLedger is and will remain free for your players. What we are looking for is visibility and technical collaboration so the tool serves the LU4 community better.

**LU4 is our reference server**: every scraper, every dataset, all item and recipe coverage is aligned with your chronicle. The CPs actively using AdenaLedger today are LU4 CPs. The feedback we capture best comes from your players.

Collaboration avenues we propose (all opt-in):

1. **Recommended official link** — A link to [adenaledger.com](https://adenaledger.com) on the LU4 website or in the server panel so new CPs discover it instantly. In exchange, AdenaLedger highlights "Officially recommended by LU4" on its landing page.

2. **Catalogue sync** — Today we scrape `wikipedia1.mw2.wiki/lu4`. If EGlobal maintains a structured items/recipes feed (JSON, CSV), we integrate it as the primary source so the data stays always fresh instead of running a crawler.

3. **LU4-exclusive features** — Unique customisations on your server (special events, custom items, XP multipliers) modelled out-of-the-box for LU4 CPs. We already do this generically; with your data we can be exhaustive.

4. **Co-marketing branding** — If interesting, we can build an optional **LU4 theme** (colours, logo, copy) that any LU4 CP can enable on their `/party` to feel "at home".

5. **Anonymised aggregate data** — We can export usage metrics to EGlobal (no PII): how many active CPs, raid cadence, most-farmed items, adena trends. Useful for your own analytics and balance decisions.

6. **Free and open software** — The platform is and will remain free for the end user. The project is sustained by voluntary donations (a donation button at `/profile`) that cover hosting costs; there are no premium plans nor will there be. Any CP from any server can use it at no cost.

We are available for technical integration, a live demo, or any feedback your team wants to share. The platform is in production and operational today.

---

# Contact

- **Web**: [adenaledger.com](https://adenaledger.com)
- **Support**: support@adenaledger.com
- **Public docs**: tutorials accessible inside the app

---

*AdenaLedger is an independent community project, with no prior contractual affiliation with EGlobal or NCSoft. All Lineage II item, class and race names are property of NCSoft.*
