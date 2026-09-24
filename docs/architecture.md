# Architecture

The application uses four pragmatic boundaries. Domain objects implement invariant and scoring logic without I/O. Application services coordinate prediction, result, and simulation use cases against `CompetitionGateway`. The PDO adapter owns SQL and transaction boundaries. The front controller handles HTTP validation, CSRF, authorization, redirects, and rendering.

Dependency direction points inward: HTTP and PDO know application/domain types; the domain knows neither. The gateway interface makes deadline and simulator behavior executable without a database. Finalization stays in the database adapter because its row locking, one-way result mutation, scoring iteration, and awards form one atomic unit. CI complements the dependency-free tests with a MySQL 8.4 smoke suite for database-specific constraints and transaction behavior.

This is intentionally not a general-purpose framework. A production evolution could split controllers/templates, inject a clock, add structured logging, and move configuration into a deployment-managed secret provider.
