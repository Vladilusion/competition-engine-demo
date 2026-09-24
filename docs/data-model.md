# Data model and integrity

`competitions` contains tournaments, `participants` and `teams` are reusable identities, and the `competition_participants` junction authorizes entry. A match belongs to one competition and references distinct home/away teams. Checks keep close time before start and require a finalized result to be complete.

One prediction is allowed per participant/match. The denormalized `competition_id` supports ranking queries and the once-per-competition wildcard constraint. Composite foreign keys ensure both the match and participant membership belong to that same competition. A generated nullable `wildcard_slot` exploits MySQL's allowance for multiple `NULL` values to permit many normal picks but only one wildcard per participant and competition. Application context checks provide an earlier, user-friendly rejection at the same boundary.

Award fields are nullable until scoring and become complete together. Foreign keys prevent orphaned rows; composite indexes serve dashboard and ranking access paths. Audit timestamps record creation, prediction updates, result finalization, and scoring.
