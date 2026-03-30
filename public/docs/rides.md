# Rides

This document explains how rides and ride matches work in this project, which files are responsible for each part of the flow, and how ride visibility is decided in `My Rides`.

## Overview

There are two related concepts:

- A `ride` is the main trip listing.
- A `ride_match` is a relationship between two users around that ride.

In plain terms:

- A user posts a ride in the `rides` table.
- Other users respond to that ride, which creates records in `ride_matches`.
- The UI shows posted rides and responded rides separately.
- Visibility and lifecycle are driven partly by ride status and partly by time-based expiration rules.

## Core Tables

### `rides`

The `rides` table is the parent record for a trip.

Important columns:

- `id`: primary key for the ride
- `user_id`: the owner who posted the ride
- `type`: `offer` or `request`
- `from_text`: origin text
- `to_text`: destination text
- `ride_datetime`: the ride start time if known
- `ride_end_datetime`: the ride end time if known
- `status`: ride lifecycle status
- `confirmed_match_id`: optional pointer to the chosen match
- `created_at`: when the ride was posted
- `updated_at`: last ride update time
- `deleted`: soft-delete flag

### `ride_matches`

The `ride_matches` table represents a match between a driver and a passenger for a given ride.

Important columns:

- `id`: primary key for the match
- `ride_id`: foreign key to `rides.id`
- `driver_user_id`: user acting as driver in the match
- `passenger_user_id`: user acting as passenger in the match
- `status`: match lifecycle status
- `created_at`: when the match was created
- `updated_at`: last match update time
- `confirmed_at`: when the match became confirmed, if applicable

Important design detail:

- The ride owner is not stored in `ride_matches`.
- Ownership comes from `rides.user_id`.
- The role of the owner depends on `rides.type`.

That means:

- If `rides.type = 'offer'`, the owner is the driver-side listing the ride.
- If `rides.type = 'request'`, the owner is the passenger-side listing the need for a ride.

### `ride_ratings`

This stores post-ride ratings between matched users.

It is used after completion so each side can rate the other.

### `ride_reports`

This stores moderation reports filed against ride listings.

Each report captures:

- the ride being reported
- the member who submitted the report
- the member who posted the ride
- the selected report reason
- optional details for review
- moderation status fields

### `score_events`

This stores tracked score changes for a user.

It is used to explain score bumps when the reason is known, for example:

- match confirmed
- 5-star rating received

## Status Model

### Ride statuses

Canonical ride statuses used by the app:

- `open`
- `matched`
- `confirmed`
- `in_progress`
- `completed`
- `cancelled`

Database note:

- The database stores `in_progress` as `inprogress`.
- This mapping is normalized in `lib/status.php`.

### Match statuses

Canonical match statuses used by the app:

- `pending`
- `accepted`
- `matched`
- `confirmed`
- `in_progress`
- `completed`
- `rejected`
- `cancelled`

Again, `in_progress` is stored in the database as `inprogress` and converted through `lib/status.php`.

## Ownership and Roles

The role mapping is easy to get wrong, so this is the clearest way to think about it:

- Every ride has one owner: `rides.user_id`
- Every match has two sides: `driver_user_id` and `passenger_user_id`

For an `offer` ride:

- the ride owner is offering to drive
- responders become passengers

For a `request` ride:

- the ride owner is looking for a ride as a passenger
- responders become drivers

This is why code frequently branches on `ride.type` to decide whether the "other person" is a driver or passenger.

The safest mental model is:

- `rides.user_id` tells you who posted the ride
- `rides.type` tells you whether that poster is offering to drive or requesting a ride
- `ride_matches` stores the final operational driver/passenger pairing

## Main Files

### Page shell

- `public/my_rides.php`

This file does not contain ride business logic. It is mainly a page shell that mounts two frontend components:

- `assets/js/comp/posted.js`
- `assets/js/comp/driver.js`

### Posted rides view

- `assets/js/comp/posted.js`
- `api/ride_list.php?mine=1`

This view shows rides the current user created.

It includes:

- active rides
- a collapsible `Past rides` section for inactive rides
- ride actions like manage, delete, start trip, complete, cancel, and rate

### Responded rides view

- `assets/js/comp/driver.js`
- `api/my_matches.php`

This view shows rides where the current user responded as either:

- a driver
- a passenger

It includes:

- active trips
- pending responses
- a collapsible `Past rides` section for inactive matches
- actions like withdraw, complete, and rate

### Manage ride details

- `public/manage_ride.php`
- `api/ride_matches_list.php`

This is used by the ride owner to inspect pending responders and the currently active or confirmed match.

## Shared Helpers

The current codebase now keeps core ride behavior in shared helpers instead of duplicating it in each endpoint.

### `lib/rides.php`

This file centralizes:

- owner/joiner to driver/passenger role mapping
- route summaries for notifications
- quoted status helpers for SQL
- latest-match lookup for lifecycle transitions

### `lib/scoring.php`

This file centralizes score rules so point values are not hard-coded in multiple endpoints.

It also logs tracked score changes into `score_events` when the database table exists.

### `lib/reports.php`

This file centralizes the allowed ride report reasons shown in the UI and accepted by the backend.

See also:

- `/docs/score.php`

## API Responsibilities

### `api/ride_list.php`

This is the main ride listing endpoint.

When called without `mine=1`, it behaves like the public/open ride list.

When called with `mine=1`, it returns only rides created by the current user.

It also:

- normalizes statuses from DB format to app format
- loads per-ride match counts
- attaches the active `confirmed` block when relevant
- applies contact privacy rules
- sets `already_rated` when the current user has already rated the finished match

### `api/my_matches.php`

This returns matches where the current user is involved.

It joins:

- `ride_matches`
- `rides`
- `users` for both driver and passenger

It also:

- normalizes match statuses
- determines the "other user"
- applies privacy logic to the other user’s contact info
- marks already-rated matches

### `api/ride_matches_list.php`

This is used when a ride owner manages one specific ride.

It returns:

- pending match requests/offers
- the current accepted/confirmed/in-progress/completed/cancelled match for context

The payload changes slightly depending on whether the ride is an `offer` or a `request`, because the requester side changes.

### Lifecycle endpoints

The main ride lifecycle is handled through:

- `api/match_request.php`
- `api/match_create.php`
- `api/ride_accept.php`
- `api/match_confirm.php`
- `api/ride_set_status.php`
- `api/match_complete.php`

Those endpoints now use shared role-mapping and status helpers so the owner/requester/driver/passenger logic stays consistent.

### Reporting endpoint

- `api/ride_report.php`

This endpoint accepts ride reports from logged-in users and stores them in `ride_reports`.

## Time and Visibility Rules

This section is the current source of truth for when rides should remain visible.

### Public ride list

The general ride list uses `api/ride_list.php` without `mine=1`.

Those results are filtered to:

- only open rides unless admin filters say otherwise
- only rides still inside the visibility window

### My Rides visibility rule

For `My Rides`, the active rule is:

- If `ride_end_datetime` exists, the ride stays visible until 24 hours after that end time.
- If there is no `ride_end_datetime`, the ride stays visible until 48 hours after `created_at`.

This rule is enforced in:

- `api/ride_list.php`
- `api/my_matches.php`

That means both tabs in `public/my_rides.php` use the same expiration logic.

### Important implication

If a ride has a start time in `ride_datetime` but no `ride_end_datetime`, the hide rule does not use the start time. It uses:

- `created_at` plus 48 hours

This is intentional based on the current product requirement.

## Active vs Past Rides in the UI

The frontend now separates visible items into:

- active
- pending
- past

### In `posted.js`

`Past rides` includes:

- `completed`
- `cancelled`
- `rejected`

Everything else stays in the active list.

### In `driver.js`

The split is:

- `pending`: `pending`
- `active`: `accepted`, `matched`, `confirmed`, `in_progress`
- `past`: `completed`, `cancelled`, `rejected`

Past rides are still visible only while they are inside the same time-based visibility window described above.

## Displayed Time Labels

The cards now show two different concepts:

- the ride time
- the posted time

### Ride time

This is shown from `ride_datetime` when available.

If there is no `ride_datetime`, the UI shows:

- `Any time`

### Posted time

This is shown from `created_at` in a more readable format, for example:

- `Posted Tue, Mar 29, 4:30 PM`

This is rendered in:

- `assets/js/comp/posted.js`
- `assets/js/comp/driver.js`

## Score

Score is documented separately in:

- `/docs/score.php`

Current live score rules are intentionally narrow:

- both matched participants get points when a match is confirmed
- a user gets points when they receive a 5-star rating
- posting, requesting, joining, completing, and cancelling do not add score by themselves

## Reporting And Safety

Rides can now be reported from the public ride list for moderation review.

Available reasons include:

- scam or fraud
- fake ride listing
- unsafe behavior
- harassment
- no-show
- spam
- misleading details
- payment issues
- other

Reports do not automatically change score by themselves. They are meant to surface possible abuse or safety problems for review.

## Privacy

Privacy is handled through:

- `lib/privacy.php`

The API endpoints evaluate whether a viewer is allowed to see phone and WhatsApp details based on:

- whether the viewer owns the ride
- whether the viewer is part of the match
- the match status
- whether the viewer is an admin
- the target user’s privacy setting

This is why some cards show full contact info and others show a notice instead.

## Status Normalization

Status normalization lives in:

- `lib/status.php`

The purpose is to let the app consistently use readable status values such as:

- `in_progress`

while still supporting the older database value:

- `inprogress`

Use these helpers whenever adding new ride or match status logic:

- `App\Status\to_db()`
- `App\Status\from_db()`

## Practical Examples

### Example 1: Ride with end time

- ride posted on Monday
- `ride_end_datetime` is Tuesday at 3:00 PM

Result:

- the ride remains visible until Wednesday at 3:00 PM

### Example 2: Ride without end time

- ride posted on Monday at 10:00 AM
- `ride_end_datetime` is `NULL`

Result:

- the ride remains visible until Wednesday at 10:00 AM

### Example 3: Completed ride

- ride is marked `completed`
- it appears under `Past rides`
- once it falls outside the visibility window, it stops appearing in `My Rides`

## If You Need to Change Behavior

### Change hide timing

Update:

- `api/ride_list.php`
- `api/my_matches.php`

### Change which statuses count as past

Update:

- `assets/js/comp/posted.js`
- `assets/js/comp/driver.js`

### Change displayed date formatting

Update:

- `assets/js/comp/posted.js`
- `assets/js/comp/driver.js`

### Change role interpretation

Be careful here. Review:

- `api/ride_matches_list.php`
- `api/my_matches.php`
- `assets/js/comp/posted.js`
- `assets/js/comp/driver.js`

The owner/respondent role mapping depends on `rides.type`, and changing that carelessly will break the meaning of driver vs passenger in multiple places.

## Current Behavior Summary

Right now, the system works like this:

- rides are stored in `rides`
- responses and pairings are stored in `ride_matches`
- `public/my_rides.php` is just the shell page
- posted rides come from `api/ride_list.php?mine=1`
- responded rides come from `api/my_matches.php`
- rides with `ride_end_datetime` hide 24 hours after the end time
- rides with no `ride_end_datetime` hide 48 hours after `created_at`
- inactive rides are grouped under `Past rides`
- cards show a readable posted date and time
