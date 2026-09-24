# Security and integrity

All database inputs use native PDO prepared statements. Domain values constrain official, predicted, and simulated scores to integers from 0 through 99. The prediction context authorizes the participant and match within the requested competition. During a write, the repository first locks the participant's competition-membership row as a serialization point, then locks and rechecks match status/deadline and wildcard usage. It selects the exact prediction before choosing `UPDATE` or `INSERT`; the database uniqueness rule remains a final backstop without an ambiguous multi-index upsert.

Result finalization locks the match and only accepts the `scheduled` state. A second call is rejected rather than replacing the official score and recomputing historical awards; corrections would require a separate audited workflow.

Every POST requires a session CSRF token. Session cookies are HTTP-only, use SameSite Lax, and become Secure on HTTPS. Templates escape dynamic content. Result finalization uses a separate environment token and does not expose it in source control.

The participant switch is deliberately a demo device, not user authentication. For production, use authenticated identities, password hashing or SSO, role-based admin authorization, HTTPS-only cookies, rate limits, secret rotation, UTC configuration, audit events, and a restrictive database user.
