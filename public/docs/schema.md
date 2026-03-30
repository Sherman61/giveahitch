# Developer Schema Guide

This guide is for developers.

The live database keeps some legacy column names for compatibility, but the application now treats the ride model with clearer business terms:

- the ride **owner** is always the person who posted the ride
- the ride **posting type** is either `offer` or `request`
- for an `offer`, the owner is the `driver`
- for a `request`, the owner is the `passenger`
- the **joiner** is the other person who responds to that ride

## Base Tables

### `rides`

This is the posting table.

Important columns:

- `user_id`: the ride owner
- `type`: the posting type, `offer` or `request`
- `ride_datetime`: trip start time when known
- `ride_end_datetime`: trip end time when known
- `status`: ride lifecycle state
- `confirmed_match_id`: active chosen match when one exists

### `ride_matches`

This is the connection between a ride posting and the person who joined it.

Important columns:

- `ride_id`
- `driver_user_id`
- `passenger_user_id`
- `status`
- `confirmed_at`

### `ride_ratings`

This stores who rated whom after a completed ride.

Important columns:

- `rater_user_id`
- `rated_user_id`
- `rater_role`
- `rated_role`
- `stars`
- `comment`

### `ride_reports`

This stores user-submitted moderation reports against ride listings.

### `score_events`

This stores human-readable score reasons shown in score history.

### `security_events`

This stores failed logins and other admin-sensitive actions.

## Developer-Friendly SQL Views

The migration now creates two readable views so developers do not have to mentally remap the legacy names every time.

### `rides_developer_view`

This view exposes:

- `owner_user_id`
- `posting_type`
- `owner_role`
- `trip_start_at`
- `trip_end_at`
- `ride_status`
- `active_match_id`

### `ride_matches_developer_view`

This view exposes:

- `owner_user_id`
- `posting_type`
- `owner_role`
- `joiner_user_id`
- `joiner_role`
- `driver_user_id`
- `passenger_user_id`
- `match_status`

These views do not change the real tables. They only make the model easier to understand in SQL queries, admin debugging, and future migrations.

## Status Naming

The database still contains some legacy `inprogress` values.

Application code normalizes that to:

- `in_progress`

When writing new code:

- prefer the canonical app status names
- use the shared status helpers in `lib/status.php`
- do not hardcode mixed spellings in new code

## Shared Business Helpers

Use these helpers instead of duplicating role logic:

- `lib/rides.php`
- `lib/status.php`
- `lib/scoring.php`
- `lib/reports.php`
- `lib/security_events.php`

## Recommendation

For new developer-facing queries:

1. start from `rides_developer_view` or `ride_matches_developer_view`
2. treat `rides.user_id` as the owner only
3. derive driver and passenger roles from the posting type or the match row, not from guesswork
