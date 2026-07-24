=== Tabletop Events Calendar — Reviews ===
Contributors: tabarccode
Tags: events, calendar, tabletop, reviews, testimonials
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Post-event ratings and testimonials for Tabletop Events Calendar organisers, gated to people who actually RSVP'd. Requires the Tabletop Events Calendar plugin.

== Description ==

A review can only be left by someone whose email shows up as a *confirmed* RSVP on that specific event — checked directly against the core plugin's own RSVP list. No separate login, no manual verification queue: if you weren't confirmed to attend, the review is rejected automatically.

Every review starts pending, same moderation model as an event submission — an admin approves it before it's shown anywhere.

Two shortcodes:

* `[tabletop_event_reviews event="123"]` — one event's approved reviews, plus a "leave a review" form (only works once the event's happened).
* `[tabletop_organiser_reviews organiser="123"]` — an organiser's aggregate rating and review list across every event of theirs, anchored on any one of their own event IDs. Drops neatly onto a Venues & Organisers profile page.

== Installation ==

1. Install and activate **Tabletop Events Calendar** first.
2. Upload the `tabletop-events-reviews` folder to `/wp-content/plugins/` and activate it.
3. Add `[tabletop_event_reviews event="123"]` to an event's page, or `[tabletop_organiser_reviews organiser="123"]` wherever you want an organiser's reputation shown.
4. New reviews land in **Events Calendar ▸ Reviews** for approval.

== Frequently Asked Questions ==

= Can someone fake a review? =

Only if they fake a confirmed RSVP first, which the core plugin's own RSVP flow already guards against with an email-based record. This plugin adds no new identity system — it just checks the one that already exists.

= Does a review need admin approval? =

Yes. Reviews start `pending` and only show up in the shortcodes once published, same as every other public submission in this plugin family.

== Changelog ==

= 1.0.0 =
* Initial release: tevr_review CPT, RSVP-gated submission, event and organiser review widgets.
