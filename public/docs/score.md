# Score

This page explains the live score rules currently used by the application.

## What Score Means

`score` is a lightweight reputation number stored on the user record.

It is intended to reward:

- confirmed ride commitments
- strong follow-through after a completed ride

It is **not** currently meant to reward raw activity volume like simply posting rides or sending requests.

## Current Point Rules

### 1. Match Confirmed

When the ride owner confirms a match:

- the driver gets **+100**
- the rider gets **+100**

This happens when a pending join request is selected and confirmed for the ride.

### 2. Receive a 5-Star Rating

When a completed ride leads to a **5-star rating**:

- the person being rated gets **+100**

This applies whether the person being rated is:

- a driver
- a rider

Ratings below 5 stars update rating averages and counts, but do **not** add score points.

### 3. Manual Admin Adjustment

In rare cases, an admin may manually change a member's score.

- this can raise the score
- this can lower the score
- the admin is expected to record a written reason

When score event tracking is available, that reason appears in the member's score history.

## Events That Do Not Add Score

The following actions currently give **0 score points**:

- posting a ride
- requesting a ride
- joining a ride
- marking a ride completed
- receiving a rating below 5 stars
- cancelling a ride or match

## Important Notes

### Counters vs Score

Some ride actions still update ride counters such as:

- `rides_offered_count`
- `rides_requested_count`
- `rides_given_count`
- `rides_received_count`

Those counters are separate from `score`.

### Why Completion Alone Does Not Add Score

The current system rewards:

- commitment becoming real through confirmation
- excellent feedback through 5-star ratings

It does **not** add score just for completion by itself.

That keeps `score` tied more closely to trust signals than to activity volume.

## Source of Truth in Code

The shared scoring rules live in:

- `lib/scoring.php`

The main active scoring updates are applied in:

- `api/match_confirm.php`
- `api/rate_submit.php`

Older fallback endpoints were aligned to the same rules as part of the cleanup so they do not drift.

## See Your Own Score History

If score event tracking is available on the database, members can also visit:

- `/score_history.php`

That page shows tracked score changes and explains the reason when the app knows it.
