# Simulation isolation

Simulation receives a read-only snapshot of participants, predictions, and official finalized results. Hypothetical results override matching entries only in local memory. Scheduled matches without a hypothesis remain unscored.

The service depends on the gateway interface but invokes only `snapshot()`: no result-finalization or prediction method is reachable in its algorithm. Its executable test uses a spy counter and structural before/after comparison to prove that a projection performs no persistence mutation. The UI labels both the feature and output as simulated/non-persistent.
