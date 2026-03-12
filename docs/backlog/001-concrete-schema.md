# 001 Concrete Schema

## Purpose

Define the first-pass schema and model boundaries needed to start Phase 1 without drifting away from the product and architecture decisions in [../PRD.md](../PRD.md).

## Scope

This document covers the durable ownership, workflow, Twitch runtime, overlay, and audit models needed for the first implementation slice.

It does not restate widget behavior, bot command semantics, viewer timer UX, or later billing rules already defined in the PRD.

## Proposed Phase 1 Model Set

### Identity and Ownership

- `users`
  - Keep the Laravel user root.
  - Keep Jetstream and Fortify email/password auth available as a first-class application path alongside linked external providers.
- `teams`
  - Ownership boundary for streamer-oriented and viewer-oriented contexts.
  - Fields: `id`, `uuid`, `name`, `type`, `owned_by_user_id`, timestamps, optional suspension metadata.
- `team_user`
  - Membership and role pivot.
  - Fields: `team_id`, `user_id`, `role`, timestamps.

### Authentication and Provider Access

- `provider_auths`
  - User-granted external auth records.
  - Fields: `id`, `user_id`, `provider`, `provider_user_id`, nullable provider-email snapshot, encrypted `access_token`, encrypted `refresh_token`, `token_expires_at`, `scopes`, `profile`, `last_used_at`, nullable `revoked_at`, timestamps.
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
- `widget_instances`
  - Widgets placed on a canvas.
  - Fields: `id`, `canvas_id`, `team_id`, `type`, `name`, `position_x`, `position_y`, `width`, `height`, `z_index`, `is_visible`, `settings`, timestamps.
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

### Audit

- Activity logging should be implemented through Spatie tables and project-specific event enums rather than a parallel audit schema.

## Relationship Notes

- `Team` remains the ownership, authorization, and billing boundary.
- `Stream` is a durable Twitch channel record and should not replace team ownership.
- `StreamSession` owns ephemeral runtime state for tasks, Pomodoro, bot activity, timer publication, and metering.
- Writes for team-scoped records should route through team or team-owned relationships.

## Suggested Build Order

1. `teams`, `team_user`, and enum-backed role and team-type support.
2. `provider_auths` and `provider_app_tokens`.
3. `workflow_stores` and imported workflow primitives.
4. `streams`, `stream_sessions`, and `twitch_subscriptions`.
5. `canvases`, `widget_instances`, and `theme_profiles`.
6. `task_items`, `pomodoro_sessions`, `bot_command_events`, and `overlay_heartbeats`.
7. Activity logging integration and event enums.

## Follow-up

- After the first schema pass is implemented, update `docs/RUNBOOK.md` with what is actually present at `HEAD` and trim this document if any sections become redundant with implemented code and the runbook.
