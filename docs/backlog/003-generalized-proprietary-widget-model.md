# 003 Generalized Proprietary Widget Model

## Purpose

Define the durable widget architecture that replaces the temporary `built-in` widget concept and local-only widget seams.

This note establishes one generalized proprietary widget model for:

- Pomodoro
- Task List
- Follower Goal
- Spotify Now Playing

Each of these widgets should be managed through the same product-owned `Widgets` area, rendered through the same widget-definition registry, and placed onto canvases through separate placement records.

## Status

Proposed and ready to implement.

This document supersedes the earlier follower-goal-only direction. `Follower Goal` remains part of the plan, but it is now one proprietary widget type inside the generalized model rather than a standalone special-case architecture.

## Problem Statement

The current codebase still reflects an early proving model:

- canvas placement and widget definition are conflated in `widget_instances`,
- first-party widget identity is driven by a temporary built-in enum and supporting factories,
- Pomodoro and Task List behave like temporary built-ins rather than durable user-managed widgets,
- Spotify Now Playing exists as a separate local/testing seam rather than part of the product widget architecture.

That model is too narrow for the intended product shape.

The target product requires:

- durable team-owned widget definitions,
- reuse of the same widget across multiple canvases and placements,
- code-owned schemas and widget-version evolution,
- a consistent product-managed editing surface in the `Widgets` area,
- placement-level canvas behavior separate from widget-level configuration,
- the ability for provider-backed widgets and Twitch-reactive widgets to coexist under the same architectural contract.

## Decision Summary

### 1. Durable Widget Definitions And Canvas Placement Are Separate

- `widgets` becomes the durable proprietary widget root.
- `canvas_widgets` becomes the placement model for canvases.
- A widget definition may be reused across multiple canvases and multiple placements on the same canvas.
- Placement records own only placement concerns such as geometry, visibility, crop, and stacking.
- Widget definitions own durable configuration, appearance, publication metadata, and shared behavior.

### 2. The Built-In Widget Concept Disappears

- The product should stop describing Pomodoro and Task List as built-in widgets.
- The long-term model should not preserve a special first-party widget path distinct from other proprietary widgets.
- Existing temporary built-in assumptions in docs should be replaced with the generalized proprietary widget model.
- The architecture should also stop treating Spotify as a special local/testing-only widget seam.

### 3. Widget Types Are Code-Owned Through A Registry

Each proprietary widget type should be defined in code through one widget-definition contract that owns:

- type id
- label
- current schema version
- defaults
- config validation and normalization
- editor contract
- renderer contract
- desired-event or provider dependency resolver

The database should not store end-user-editable widget schemas in MVP.

### 4. Widget Config Is Versioned

- Every widget record stores `schema_version`.
- Widget configs are normalized at read time.
- The normalized version is persisted on save or through an explicit maintenance pass.
- The product should not require eager rewrites of every widget record whenever one widget type gains a new field.

### 5. Canvas Editing Mutates Shared Widget Config In V1

- Canvas pages may edit a widget's shared config as well as its placement.
- Placement changes remain local to `canvas_widgets`.
- Config changes made from a canvas page affect every placement of that widget.
- Per-canvas config overrides are intentionally out of scope for v1.
- The canvas editing surface should use a config-only offcanvas for shared widget fields, while the canvas stage itself remains the preview in that context.
- Publish controls, provider connect or repair flows, and destructive actions should stay on the canonical widget edit page rather than the canvas offcanvas.

### 6. Widget-Specific Runtime State Lives Outside `widgets`

The generic `widgets` table should keep only durable shared concerns and coarse runtime or publication state.

Widget-specific dynamic state should live in type-owned models or projections keyed by widget.

Examples:

- Pomodoro display state should continue to derive from stream-session timing state rather than being stored as generic widget state.
- Task List display state should continue to derive from task/session state rather than being stored as generic widget state.
- Follower Goal tally and subscription-driven progress should live in follower-goal-specific projection or state records.
- Spotify Now Playing playback state should live in Spotify-specific state or service boundaries rather than in a generic widget blob.

### 7. Event And Provider Dependencies Remain Code-Derived

- Widgets should declare their desired Twitch or other provider dependencies in code.
- Desired subscription state should be computed from widget definitions plus widget config.
- The product should not use database-authored event wiring or user-authored automation rules in MVP.
- This keeps the system curated and approachable while still supporting richer widget types later.

### 8. Spotify Is A Formal Proprietary Widget Type

- Spotify Now Playing should be treated as a first-class proprietary widget type in the target model.
- The architecture should no longer position Spotify as a local/testing seam that sits outside the product widget catalog.
- This decision does not require the current local implementation seam to become the runtime contract; it requires the long-term model to place Spotify inside the same generalized widget architecture as the other proprietary widgets.

### 9. `Widgets` Is The Primary Widget Management Surface

- The product should introduce a top-level `Widgets` navigation area distinct from `Canvases`.
- The `Widgets` index should act as a reusable widget library rather than a canvas-centric screen.
- The index should support creation, search and filtering, and should show type, current status, last updated time, canvas usage count, and standalone publication state.
- Widget creation should begin from the `Widgets` index by choosing a widget type, creating a draft widget with defaults, and then redirecting immediately to the widget edit page.
- The canvas should support both attaching an existing widget and quick-creating a new shared widget before attaching it.

### 10. The Widget Edit Page Is Canonical

- Each proprietary widget should have a full-page edit surface that combines config controls, live preview, publication controls, provider and runtime status, and advanced actions.
- The widget edit page is the canonical place for connect, reconnect, repair, reset, archive, and signed-URL management actions.
- Canvas-side editing in v1 should mutate the shared widget definition, and the UI should explicitly warn that those changes affect every placement and standalone use of the widget.
- Widget lifecycle states should distinguish at least `draft`, `ready`, `pending connection`, `broken`, and `archived`.

### 11. Provider-Gated Widgets Remain Draftable

- Widgets that depend on Twitch, Spotify, or later providers should still be creatable before the required provider connection is healthy.
- A widget should not enter `ready` until the required provider connection and scopes are valid for that widget type.
- The widget edit page should surface inline connect, reconnect, and repair actions together with current provider health.
- The canvas offcanvas may show provider status and blocking guidance, but should not initiate provider management flows itself.
- The product should also expose a central `Team Integrations` page from the account area for provider management outside a specific widget.
- The `Team Integrations` page should show usage counts and drill-in access to the widgets depending on a given integration.
- Team integrations should be framed as team-owned product surfaces, while v1 operational ownership remains limited to the team owner.

### 12. Multi-Request Widget And Integration Flows Use Workflows

- Any widget or integration flow that spans multiple requests, crosses redirects, or must resume later should be implemented through the proprietary persisted workflow system.
- This includes provider authorization and repair started from widget management, signed widget URL regeneration or rotation, archive confirmations that need resumable state, and later widget setup wizards.
- These workflows should persist team context, acting user, subject widget or integration, intended return location, and guard failures through `workflow_stores`.
- Single-request inline widget edits may remain ordinary Livewire actions when resumable state is unnecessary.

## Proposed Domain Additions

### `widgets`

Team-owned durable widget definitions.

Suggested fields:

- `id`
- `uuid`
- `team_id`
- `created_by_user_id`
- `type`
- `name`
- `schema_version`
- `config`
- `appearance`
- publication metadata
- signed URL rotation metadata for standalone delivery
- coarse status or health metadata
- timestamps

Notes:

- `config` should be widget-type-specific durable configuration owned by the widget-definition registry.
- `appearance` should store product-defined theming or variant options exposed for standalone and canvas use.
- This table should not become a generic dump for all widget runtime state.

### `canvas_widgets`

Canvas placement records referencing reusable widgets.

Suggested fields:

- `id`
- `canvas_id`
- `widget_id`
- `team_id`
- `position_x`
- `position_y`
- `width`
- `height`
- `content_width`
- `content_height`
- `crop_top`
- `crop_right`
- `crop_bottom`
- `crop_left`
- `z_index`
- `is_visible`
- placement metadata
- timestamps

Notes:

- `canvas_widgets` replaces the long-term intent of `widget_instances`.
- Placement records should not own the widget's durable config.

### Widget Definition Registry

Code-owned registry for proprietary widget types.

Expected responsibilities:

- enumerate available proprietary widget types,
- provide schema version and defaults,
- validate and normalize config,
- resolve editor behavior,
- resolve runtime renderer behavior,
- resolve desired provider and event dependencies,
- expose upgrade logic between schema versions.

### Type-Owned Runtime State Or Projections

Runtime or projection records should remain widget-type-specific where needed.

Examples:

- follower-goal progress projection keyed by widget,
- Spotify playback projection or status boundary keyed by widget,
- other future widget-specific state where shared `widgets` fields are insufficient or would become a junk drawer.

## Initial Proprietary Widget Types

### Pomodoro

- Durable widget definition with reusable standalone and canvas-capable config.
- Runtime display should continue to derive from stream-session or timing state rather than generic widget state blobs.

### Task List

- Durable widget definition with reusable standalone and canvas-capable config.
- Runtime display should continue to derive from task/session state.

### Follower Goal

- One widget type inside the generalized model, not a special top-level widget entity.
- Keeps its Twitch EventSub and tally requirements, but those requirements now hang off the generic widget root.
- Desired `channel.follow` subscriptions should be computed from the follower-goal widget definition plus config.
- Follower-goal-specific runtime or projection state should remain outside the generic `widgets` table.
- The widget should continue to use campaign-window event counting rather than absolute follower-total progress.
- Widget state changes should trigger desired-subscription reconciliation for the owning `Stream`.
- If no active consumer still requires `channel.follow`, the remote subscription should be deleted and the local subscription record soft-deleted.
- If Twitch revokes a still-desired subscription, recovery should happen through the bounded reconcile path with environment-aware quarantine behavior.

### Spotify Now Playing

- Formal proprietary widget type in the generalized model.
- No long-term architectural special-casing as a local-only widget seam.
- Spotify-specific provider and playback concerns should remain type-owned rather than leaking into the generic widget base model.

## Processing And Editing Expectations

### Widget Creation

1. Team owner creates a widget from the `Widgets` area by selecting a widget type.
2. The application creates a draft `widgets` record with its type, schema version, config, and appearance defaults.
3. The user is redirected immediately to the canonical widget edit page.
4. The widget may then be used standalone, placed on canvases, or both depending on its product contract.

### Canvas Placement

1. A canvas either attaches an existing widget or quick-creates a new shared widget and then attaches it.
2. Placement owns geometry, visibility, stacking, and crop.
3. Placement does not own the widget's durable config.

### Canvas Config Editing

1. The canvas page may edit placement-level data on `canvas_widgets`.
2. The canvas page may also edit the underlying widget definition in v1 through a config-only offcanvas.
3. Shared config edits update the base widget and therefore affect all placements and standalone use of that widget.
4. Provider initiation, repair, publish management, and destructive actions remain on the canonical widget edit page.

### Widget Evolution

1. Widget type definitions normalize stored config to the current shape at read time.
2. The normalized shape is persisted when the widget is next saved or when an explicit maintenance pass runs.
3. Old widget configs remain readable without forcing eager rewrites of all rows.

## Public Interface Expectations

Authenticated UI:

- a dedicated `Widgets` navigation area
- a library-style widget index with creation, status visibility, filtering, and usage counts
- widget edit pages for all proprietary widget types
- a central `Team Integrations` management page reachable from the account area
- canvas placement flows that attach existing widgets to canvases or quick-create new shared widgets before attaching them
- canvas editing that distinguishes shared widget config from placement-specific data

Canvas and overlay behavior:

- proprietary widgets render through the same product-owned widget architecture whether used standalone or on a canvas
- canvases consume widget definitions through placement records rather than embedding durable config into placement rows
- canvas-side shared-config editing should use a config-only offcanvas and should not become a second full widget-management surface

## Acceptance Criteria

- Pomodoro, Task List, Follower Goal, and Spotify Now Playing are all described as proprietary widgets under one shared architecture.
- The target model uses `widgets` as the durable widget root and `canvas_widgets` as the placement model.
- The target model no longer relies on a `built-in` widget concept.
- The target model no longer relies on local-only widget seams as the forward-looking architecture for proprietary widgets.
- Widget schemas are code-owned and versioned.
- Widget runtime or projection state remains outside the generic `widgets` table where the data is widget-type-specific.
- Canvas placement is clearly separated from shared widget configuration.
- Canvas config edits are documented as shared-definition edits in v1 rather than per-canvas overrides.
- Follower Goal keeps its Twitch-driven subscription and tally behavior inside the generalized model rather than through a dedicated top-level widget table.
- The docs describe `Widgets` as the primary library-management surface and the widget edit page as the canonical place for provider, publication, and archive actions.
- Provider-gated widgets are explicitly draftable and use the widget edit page plus `Team Integrations` for connection management.
- Multi-request widget and integration flows are explicitly routed through the proprietary persisted workflow system rather than ad hoc redirect or session state.

## Out Of Scope

- End-user-authored widget code or custom scripting
- Database-owned widget schema definitions
- StreamElements-style freeform builders
- Per-canvas config overrides
- Arbitrary remote-widget policy changes beyond the existing remote-widget planning
