# Security Implementation Guide

This page highlights the security controls currently implemented in Glitch a Hitch.

It is written for developers and admins who want one place to understand what protections exist today.

## 1. Authentication And Session Security

Authentication is handled through:

- `lib/auth.php`
- `lib/session.php`

Current protections include:

- server-side sessions
- session-backed CSRF tokens
- `session_regenerate_id(true)` on successful login
- explicit admin-only guards through `require_admin()`

## 2. CSRF Protection

Sensitive POST actions use CSRF verification through:

- `App\Auth\assert_csrf_and_get_input()`
- `App\Auth\csrf_verify()`

This is used across:

- login
- profile updates
- ride updates
- match actions
- ratings
- ride reports
- admin score changes
- admin report review
- admin push notifications

## 3. Rate Limiting

Rate limiting is implemented in:

- `lib/security.php`

It currently protects:

- login attempts
- admin score actions
- admin report actions
- admin push broadcasts

Rate limit counters are stored in:

- `rate_limits`

## 4. Notification And Push Security

Notification delivery is handled in:

- `lib/notifications.php`

Current protections include:

- per-user notification preference checks
- cleanup of invalid web push subscriptions
- separate handling for browser push and Expo push tokens
- admin broadcasts going through the same central notification pipeline

## 5. Ride Reporting And Moderation

Ride abuse reporting is implemented through:

- `api/ride_report.php`
- `lib/reports.php`
- `ride_reports`

Current moderation features include:

- multiple report reasons such as scams, fake listings, unsafe behavior, harassment, no-show, spam, misleading details, payment issues, and other
- duplicate active-report prevention for the same reporter, ride, and reason
- admin review workflow in `/admin/reports.php`
- ability for admins to remove a ride while reviewing a report

## 6. Security Event Logging

Security-sensitive actions are now logged to:

- `security_events`

The logging helper lives in:

- `lib/security_events.php`

Currently logged examples include:

- failed logins
- successful logins
- admin score adjustments
- admin report reviews
- admin push broadcasts

Each event can capture:

- actor user
- target user
- IP address
- user agent
- human-readable details
- structured JSON metadata

## 7. Score Auditability

Score changes are tracked through:

- `score_events`
- `lib/scoring.php`

This makes score changes explainable in:

- `/score_history.php`

Manual admin score changes also require a recorded reason.

## 8. Application Error Logging

Application errors are stored in:

- `app_errors`

The logger lives in:

- `lib/error_log.php`

This captures:

- PHP errors
- shutdown fatal errors
- page and endpoint context
- optional user context

## 9. Admin Security Visibility

Admins now have a dedicated security dashboard at:

- `/admin/security.php`

It shows:

- open ride reports
- failed logins in the last 24 hours
- recent security events
- recent application errors

This is meant to help admins notice abuse patterns and operational issues faster.

## 10. Developer-Friendly Schema Safety

To make the ride model easier to understand without risky immediate table renames, the migration now adds:

- `rides_developer_view`
- `ride_matches_developer_view`

These views expose clearer names such as:

- `owner_user_id`
- `posting_type`
- `owner_role`
- `joiner_user_id`
- `joiner_role`
- `trip_start_at`
- `trip_end_at`
- `ride_status`
- `match_status`

## 11. Live Ride Update Security

Ride updates use the existing websocket layer in:

- `lib/ws.php`

`/my_rides.php` now receives immediate ride refresh events through authenticated websocket access.

The relevant server-side ride lifecycle endpoints broadcast ride updates only to the users involved in that ride.

## 12. Important Limits

The current security work does **not** yet include:

- two-factor authentication
- automatic account lockout
- IP reputation or geo-risk analysis
- mandatory strong password policy enforcement
- security email alerts to admins
- background anomaly detection
- end-to-end moderation escalation workflows

## 13. Required Migration

For the newer security features to work fully, the current migration must be applied:

- `alter_table.sql`

That migration creates:

- `security_events`
- `score_events`
- `ride_reports`
- developer-friendly schema views
