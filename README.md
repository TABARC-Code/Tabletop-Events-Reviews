# Tabletop Events Calendar — Reviews

Post-event ratings and testimonials for [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) organisers — gated to people who actually RSVP'd, so a review can't be faked by someone who was never there.

Requires [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) — this plugin does nothing without it.

## What it does

- A review can only be submitted if the given email is a *confirmed* RSVP on that specific event — checked directly against the core plugin's own `_tec_rsvp_list`. No separate identity system, no manual verification.
- Every review starts pending, same moderation model as an event submission.
- `[tabletop_event_reviews event="123"]` — one event's reviews plus a "leave a review" form (only accepted once the event's actually happened).
- `[tabletop_organiser_reviews organiser="123"]` — an organiser's aggregate rating across every event of theirs, anchored on any one of their own event IDs (same identity pattern as `[tabletop_organiser_events]`). Drops onto a Venues & Organisers profile page without any extra wiring.

## Why gate on RSVP status rather than build a real identity system

Because the core plugin already has one: a confirmed RSVP is proof someone had a genuine spot at that event. Reusing it means no new login, no email-verification flow, no second place for someone's email address to live — the exact same "anchor on data that already exists" approach as the Venues, LFG, and Discord RSVP plugins in this family.

## Licence

GPL v2 or later.
