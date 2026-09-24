# Scoring decisions

`ScoringEngine` is a pure deterministic function of predicted score, official score, wildcard flag, and configured rules. It returns both numeric points and an auditable reason.

Precedence is exact score → matching draw → exact goal difference → matching winner → incorrect. The first match wins, preventing stacked awards. Draw precedes goal difference because every draw has a zero difference; reversing those checks would make the explicit draw rule unreachable. Tests pin this behavior as well as exact-over-difference and difference-over-winner ordering.

The wildcard is applied only after the base category is selected, including a harmless multiplication of zero. MySQL stores awards as fixed decimal values. Once finalized, stored awards power the official ranking; simulation independently recomputes projected awards from predictions and candidate results.
