# Product Requirements Document (PRD)

## Project: Stream Overlay Co‑Working Toolkit

### Author: Mike Casson

### Version: 0.1 (Initial Architecture Draft)

------------------------------------------------------------------------

# 1. Product Overview

This project is a **commercial streaming overlay platform** focused
initially on the co‑working / productivity streaming niche (Pomodoro +
task list streams).

The product enables creators to display aesthetically polished
productivity widgets (tasks, pomodoro timers, session indicators) on
their streams using **browser sources in OBS / streaming software**.

The long‑term differentiator is a **single composited browser canvas**
that allows streamers to configure multiple widgets inside the
application while exposing **only one browser source URL** to OBS.

This reduces setup complexity and allows creators to manage layout
visually in the web app.

------------------------------------------------------------------------

# 2. Goals

Primary goals:

-   Provide **highly polished overlay widgets** for productivity
    streamers.
-   Enable **single-browser-source overlays** via a canvas compositor.
-   Offer **visual theme packs** to create unique aesthetics.
-   Support **Twitch chat bot integration** to control widgets via
    commands.
-   Allow **teams/moderators** to control overlays through permissions.
-   Maintain **Laravel-first architecture** with minimal heavy JS where
    possible.

------------------------------------------------------------------------

# 3. Target Users

Primary users:

-   Twitch / YouTube productivity streamers
-   Co‑working stream hosts
-   Educational streamers

Secondary users:

-   Moderators controlling overlay widgets
-   Community contributors creating themes

------------------------------------------------------------------------

# 4. Core Features

## 4.1 Widget System

Initial widgets:

-   Pomodoro timer
-   Task list
-   Session status widget
-   Next task / now task indicator

Widgets should support:

-   Size adjustment
-   Positioning
-   Visibility toggle
-   Theme application

Widgets must work as:

-   Individual browser-source URLs
-   Canvas-rendered elements

------------------------------------------------------------------------

## 4.2 Canvas Overlay System

A configurable scene rendered as a **single browser source**.

Capabilities:

-   Drag / position widgets
-   Resize widgets
-   Configure layout
-   Render composited overlay

Output:

Single URL such as:

    https://app/overlay/{canvas_id}

OBS loads this URL as one Browser Source.

------------------------------------------------------------------------

## 4.3 Theme System

Two levels of styling:

### Base Theme Controls

Users can customise:

-   Accent colours
-   Typography
-   Background style
-   Animation intensity

### Theme Packs

Pre-built visual themes including animation assets.

Examples:

#### Night Theme

Visuals:

-   Stars
-   Moon glow
-   Nebula gradients
-   Slow particle drift

#### Garden Theme

Visuals:

-   Grass animation
-   Trees
-   Natural colours
-   Wind sway effects

Themes must be reusable across widgets and canvas.

------------------------------------------------------------------------

## 4.4 Twitch Bot Integration

Allows Twitch chat commands to control overlay widgets.

Examples:

    !task add Write documentation
    !task done
    !pomo start
    !pomo pause
    !pomo break

Implementation:

-   Twitch authentication
-   Bot connection
-   Queue-based event handling
-   Overlay polling for updates

------------------------------------------------------------------------

# 5. Technical Architecture

## 5.1 Backend

Primary stack:

-   Laravel
-   MySQL/PostgreSQL
-   Laravel Queues
-   Laravel Jetstream (Teams support)
-   Laravel Socialite (Twitch auth)

Responsibilities:

-   User accounts
-   Team permissions
-   Bot command processing
-   Overlay state persistence
-   Theme configuration
-   Billing integration

------------------------------------------------------------------------

## 5.2 Frontend

Primary interface:

-   Laravel Blade + Livewire

Supplemental JS:

-   PixiJS (overlay renderer)
-   Motion / GSAP (animations)
-   Rive (animated assets)

Rationale:

Livewire handles dashboard UI while the overlay runtime uses a
specialised renderer.

------------------------------------------------------------------------

## 5.3 Overlay Renderer

Canvas runtime responsibilities:

-   Scene graph rendering
-   Widget layout
-   Theme application
-   Animation execution

Candidate libraries:

-   PixiJS (primary)
-   Rive (state animations)
-   Optional WebGL shaders

------------------------------------------------------------------------

## 5.4 Communication Model

Avoid WebSockets initially.

Use polling:

Overlay clients poll:

    /overlay/state/{canvas_id}

Polling interval:

1--2 seconds.

Queues process bot commands and update overlay state.

------------------------------------------------------------------------

# 6. Data Model (Conceptual)

Entities:

Users

Teams

Canvases

Widgets

Themes

Theme Packs

Tasks

Pomodoro Sessions

Bot Events

Canvas stores widget layout configuration.

------------------------------------------------------------------------

# 7. Development Phases

## Phase 1 --- Local MVP

Goal:

Local functional prototype for personal streams.

Features:

-   Laravel dashboard
-   Task list widget
-   Pomodoro widget
-   Canvas layout preview
-   Position + size controls
-   Theme experimentation
-   OBS browser-source overlay rendering

Output:

Local overlay usable during a stream.

------------------------------------------------------------------------

## Phase 2 --- Platform Foundation

Introduce:

-   Authentication
-   Jetstream teams
-   Bootstrap UI framework
-   Vite build pipeline
-   Twitch authentication
-   Twitch bot integration

End of phase capability:

Local streaming setup with working bot commands and overlay.

------------------------------------------------------------------------

## Phase 3 --- Public Beta

Build full product structure:

-   User dashboards
-   Canvas editor improvements
-   Theme pack system
-   Admin controls
-   Billing infrastructure (disabled)
-   User onboarding

Release to beta users.

------------------------------------------------------------------------

## Phase 4 --- Production Launch

-   Enable billing
-   Expand theme marketplace
-   Add more widgets
-   Improve performance
-   Harden infrastructure

------------------------------------------------------------------------

# 8. Success Metrics

Key metrics:

-   Number of active streamers
-   Average session duration
-   Number of widgets used per stream
-   Overlay load performance
-   Theme pack adoption

------------------------------------------------------------------------

# 9. Future Enhancements

Potential roadmap items:

-   Drag-and-drop canvas editor
-   Community theme marketplace
-   WebSocket real-time updates
-   YouTube streaming integration
-   Scene presets
-   AI-powered task summaries

------------------------------------------------------------------------

# 10. Risks

Technical risks:

-   Browser source performance
-   Theme complexity
-   Animation overload

Product risks:

-   Competing overlay platforms
-   Overly niche audience

Mitigation:

Focus on **design quality and simplicity**.

------------------------------------------------------------------------

# End of PRD
