# 001 Concrete Schema

## Purpose

Capture the first-pass schema and model boundaries that guided Phase 1 and still frame the adjacent schema work that remains beyond the implemented core slice.

## Scope

This document covers the durable ownership, workflow, Twitch runtime, overlay, and audit models needed for the Phase 1 implementation sequence.

At `HEAD`, the core slice for `streams`, `stream_sessions`, `canvases`, `widget_instances`, `task_items`, and `pomodoro_sessions` is implemented. This note remains useful for adjacent schema planning that extends beyond that slice and now needs to account for the generalized proprietary widget direction described in the newer backlog notes.

It does not restate widget behavior, bot command semantics, viewer timer UX, or later billing rules already defined in the PRD.

## Proposed Phase 1 Model Set

### Identity and Ownership

- `users`
  - Keep the Laravel user root.
  - Keep Jetstream and Fortify email/password auth available as a first-class application path alongside linked external providers.
- `teams`
  - Ownership boundary for streamer-oriented and viewer-oriented contexts.
  - Current Phase 1 implementation fields: `id`, `user_id`, `name`, `type`, timestamps.
  - `user_id` remains the owner foreign key for Jetstream compatibility while product code treats the record as a viewer or streamer profile.
  - Team records should use soft deletes so ownership and collaborator associations remain restorable.
- `team_user`
  - Membership and role pivot.
  - Fields: `team_id`, `user_id`, `role`, timestamps.

### Authentication and Provider Access

- `provider_auths`
  - User-granted external auth records.
  - Fields: `id`, `user_id`, `provider`, `provider_user_id`, nullable provider-email snapshot, encrypted `access_token`, encrypted `refresh_token`, `token_expires_at`, `scopes`, `profile`, `last_used_at`, timestamps, soft deletes.
  - Constraints: unique `(provider, provider_user_id)` and support multiple rows of the same provider for one user.
- `provider_app_tokens`
  - Application-level Twitch token storage kept separate from user-granted auth.
  - Fields: `id`, `provider`, encrypted token payload, `expires_at`, timestamps.

### Persisted Workflows

- `workflow_stores`
  - Imported workflow persistence root.
  - Fields: `id`, `team_id`, `created_by_user_id`, nullable `subject_type`, nullable `subject_id`, `workflow_class`, `status`, `records`, timestamps.
  - Indexes: `(team_id, workflow_class)`, `(subject_type, subject_id)`, `(status)`.

### Overlay and Composer

- `canvases`
  - Team-owned overlay compositions.
  - Fields: `id`, `uuid`, `team_id`, `created_by_user_id`, `name`, `width`, `height`, `theme_profile_id`, signed URL rotation metadata, timestamps.
- `widgets`
  - Durable team-owned proprietary widget definitions.
  - Fields: `id`, `uuid`, `team_id`, `created_by_user_id`, `type`, `name`, `schema_version`, `config`, `appearance`, signed URL rotation metadata, coarse status metadata, timestamps.
- `canvas_widgets`
  - Widget placements on a canvas.
  - Fields: `id`, `canvas_id`, `widget_id`, `team_id`, `position_x`, `position_y`, `width`, `height`, `content_width`, `content_height`, crop fields, `z_index`, `is_visible`, placement metadata, timestamps.
- `theme_profiles`
  - Canvas-level theme settings, only as far as needed for early phases.
  - Fields: `id`, `team_id`, `created_by_user_id`, `name`, `settings`, timestamps.

### Twitch Runtime

- `streams`
  - Durable broadcaster/channel record attached to a team.
  - Fields: `id`, `team_id`, `provider_auth_id`, `provider_channel_id`, `channel_login`, `display_name`, runtime metadata, timestamps.
- `stream_sessions`
  - One live or simulated broadcast window.
  - Fields: `id`, `uuid`, `team_id`, `stream_id`, `started_at`, nullable `ended_at`, `status`, `is_test`, `reconciliation_state`, `last_overlay_heartbeat_at`, `last_bot_activity_at`, `metadata`, timestamps.
- `twitch_subscriptions`
  - Persisted EventSub subscription state.
  - Fields: `id`, `stream_id`, `provider_subscription_id`, `type`, `status`, `subscribed_at`, `expires_at`, `payload`, timestamps.
- `connection_logs`
  - Listener and transport lifecycle events.
  - Fields: `id`, `stream_id`, nullable `stream_session_id`, `level`, `event`, `context`, timestamps.

### Stream Session State

- `task_items`
  - Session-scoped task state, not cross-stream persistent state.
  - Fields: `id`, `team_id`, `stream_session_id`, nullable `created_by_user_id`, `source`, `submitted_by_username`, `submitted_by_provider_user_id`, `body`, `status`, `sort_order`, `completed_at`, `archived_at`, `metadata`, timestamps.
- `pomodoro_sessions`
  - Session timing state for focus/break operation.
  - Fields: `id`, `team_id`, `stream_session_id`, `state`, `focus_minutes`, `break_minutes`, `started_at`, `ends_at`, nullable `paused_at`, `sequence`, `metadata`, timestamps.
- `bot_command_events`
  - Raw and normalized command ingestion history.
  - Fields: `id`, `team_id`, `stream_session_id`, `stream_id`, `provider_event_id`, `origin`, `username`, `provider_user_id`, `command`, `arguments`, `status`, `processed_at`, `metadata`, timestamps.
- `overlay_heartbeats`
  - Keep-alive events for reconciliation and metering support.
  - Fields: `id`, `team_id`, `canvas_id`, `stream_session_id`, `heartbeat_at`, `source`, `metadata`, timestamps.
- `viewer_timer_sessions`
  - Public or authenticated timer access boundary keyed to the active stream session.
  - Fields: `id`, `stream_session_id`, `access_mode`, `published_at`, nullable `invalidated_at`, `metadata`, timestamps.

### Widget-Specific Runtime State

- Widget-specific runtime or projection state should remain outside the generic `widgets` table when the state is type-specific.
- Examples include follower-goal progress projections, Spotify playback status boundaries, and other future widget-type-specific runtime concerns that should not become generic widget blobs.

### Audit

- Activity logging should be implemented through Spatie tables and project-specific event enums rather than a parallel audit schema.

## Relationship Notes

- `Team` remains the ownership, authorization, and billing boundary.
- The generic Jetstream team-management, invitation, and switching surfaces are intentionally disabled until product-owned profile management exists.
- Enum meanings are enforced in the application layer while the database stores string columns for `teams.type` and `team_user.role`.
- `Stream` is a durable Twitch channel record and should not replace team ownership.
- `StreamSession` owns ephemeral runtime state for tasks, Pomodoro, bot activity, timer publication, and metering.
- Writes for team-scoped records should route through team or team-owned relationships.
- Durable widget config belongs to `widgets`, while canvas-specific geometry and visibility belong to `canvas_widgets`.

## Remaining Build Order

1. `provider_app_tokens`, `twitch_subscriptions`, and any connection/runtime logging tables needed for durable Twitch operations.
2. generalized proprietary widget storage with `widgets`, `canvas_widgets`, and type-owned widget state or projection tables where required.
3. `theme_profiles` once canvas-level visual presets need first-class persistence.
4. `bot_command_events`, `overlay_heartbeats`, and `viewer_timer_sessions` as the runtime and public timer surfaces expand.

## Follow-up

- Use `docs/RUNBOOK.md` as the source of truth for implemented state at `HEAD`.
- Keep this note focused on future adjacent schema decisions rather than repeating the now-implemented core slice.
