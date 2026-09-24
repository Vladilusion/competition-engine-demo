# Security and integrity

All database inputs use native PDO prepared statements. Domain values constrain scores to integers from 0 through 99. The prediction context authorizes the participant and match within the requested competition. The repository locks and rechecks match status/deadline inside the transaction, preventing a request that began before close from committing afterward.

Every POST requires a session CSRF token. Session cookies are HTTP-only, use SameSite Lax, and become Secure on HTTPS. Templates escape dynamic content. Result finalization uses a separate environment token and does not expose it in source control.

The participant switch is deliberately a demo device, not user authentication. For production, use authenticated identities, password hashing or SSO, role-based admin authorization, HTTPS-only cookies, rate limits, secret rotation, UTC configuration, audit events, and a restrictive database user.
