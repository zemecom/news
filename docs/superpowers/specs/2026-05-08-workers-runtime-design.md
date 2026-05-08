# Workers Runtime Design

## Summary

We will add a dedicated Filament admin page at `/admin/workers` for worker runtime control and diagnostics.

The page will separate three concerns:

1. Queue state and failed-job diagnostics inside Laravel.
2. Worker runtime state and lifecycle operations through a supervisor adapter.
3. Docker-specific control as an implementation detail behind a contract, never as a direct concern of the UI.

The design keeps `/admin/operations` as a general operations hub for AI status, Telescope, and high-level queue visibility, while moving active worker operations to a focused `Workers` page.

## Goals

- Let an admin start, stop, and restart workers from the admin panel when a runtime adapter is configured.
- Show whether workers are actually running, not only whether queue messages exist.
- Expose memory, CPU, uptime, restart count, last heartbeat, and recent worker errors per worker runtime.
- Reuse the existing queue overview and queue management services instead of duplicating broker logic.
- Keep the system safe by avoiding direct Docker socket access from the `app` container.
- Allow v1 to ship with a `NullWorkerSupervisor` that keeps the UI stable even before real runtime control is wired.

## Non-Goals

- No direct unrestricted shell execution from Laravel.
- No direct Docker socket mount into the `app` container.
- No generic container management UI for arbitrary services.
- No replacement of the existing `/admin/operations` page with unrelated runtime concerns.
- No Horizon adoption, because the project runs RabbitMQ rather than a Redis-only queue stack.

## Recommended Runtime Topology

To make runtime status and resource usage meaningful, the current generic worker runtime should be split into three named worker runtimes:

- `crawler-worker` for `crawler_tasks`
- `intelligence-worker` for `intelligence_tasks`
- `media-worker` for `media_tasks`

This split is strongly recommended because a single shared worker cannot provide accurate per-queue memory, CPU, restart, and last-error diagnostics. The UI and service contracts will support multiple runtimes explicitly.

If the Docker layer is still using one shared worker during migration, the supervisor adapter may expose one runtime temporarily, but the intended target state is one runtime per queue family.

## Page Design

### Route and Navigation

- New Filament page: `/admin/workers`
- Navigation group: `Operations`
- Navigation label: `Workers`
- Page title: `Workers`

### Block 1: Worker Runtime

Each runtime card or row will show:

- runtime name
- bound queue or queue group
- health status: `running`, `degraded`, `stopped`, `unavailable`, `not_configured`
- queue depth
- broker consumer count
- worker active flag from supervisor
- memory usage in bytes and formatted text
- CPU percent if available
- uptime seconds and formatted text
- restart count if available
- last heartbeat timestamp if available
- last processed job summary if available
- last error summary if available

Health should be calculated from both broker and supervisor signals:

- `running`: supervisor reports running and worker can consume assigned queue(s)
- `degraded`: runtime is running but queue backlog, failed jobs, stale heartbeat, or zero consumers indicate trouble
- `stopped`: supervisor reports stopped
- `unavailable`: runtime adapter failed or broker inspection failed
- `not_configured`: supervisor adapter is intentionally disabled

### Block 2: Runtime Actions

Actions will be runtime-scoped where possible:

- `Start`
- `Stop`
- `Restart`
- `Soft restart`
- `Run 1 Job`
- `Run until empty`
- `Purge Queue`
- `Refresh`

Action semantics:

- `Start`, `Stop`, `Restart` are delegated to the `WorkerSupervisor`.
- `Soft restart` calls `queue:restart` and is global for Laravel queue workers; the UI should explain that it triggers graceful restart for all Laravel workers, not only one runtime.
- `Run 1 Job` reuses the existing one-step queue processing behavior.
- `Run until empty` uses a safe bounded run such as `queue:work --stop-when-empty --max-jobs=25 --queue=<queue> --tries=3`.
- `Purge Queue` requires confirmation and stays queue-scoped.

### Block 3: Docker Control

This block will always be present, but its behavior depends on the supervisor adapter.

When the adapter is disabled:

- show status `Not configured`
- show exact operator commands such as `make worker-up` and `docker compose restart <worker-service>`
- explain that direct container control is not enabled in the `app` container

When the adapter is enabled:

- show runtime backend type, such as `docker-agent`
- surface the same start/stop/restart capabilities through the runtime action controls

The page must not leak backend-specific implementation details into the rest of the UI. Docker is just one possible supervisor implementation.

### Block 4: Diagnostics

The diagnostics area will reuse and extend existing queue tooling:

- recent failed jobs table
- recent worker activity table
- preview head of selected queue
- runtime-specific log tail if supervisor supports it

Recent worker activity rows should include:

- runtime
- queue
- message count
- consumer count
- failed count
- last failure
- last heartbeat

## Service Design

### Reused Services

Existing services remain the source of truth for broker and queue-level operations:

- `App\Services\QueueOverviewService`
- `App\Services\QueueManagementService`

They should not become runtime supervisors.

### New Service: `WorkerManagementService`

Add a new orchestration service in `app/Services/WorkerManagementService.php`.

Responsibilities:

- assemble the workers page view model
- combine queue overview data with supervisor runtime data
- expose admin-safe operations for runtime control
- calculate health state and operator-facing summaries

Planned API shape:

- `listWorkers(): array`
- `startWorker(string $runtime): WorkerActionResult`
- `stopWorker(string $runtime): WorkerActionResult`
- `restartWorker(string $runtime): WorkerActionResult`
- `softRestartWorkers(): WorkerActionResult`
- `runOneJob(string $queue): WorkerActionResult`
- `runUntilEmpty(string $queue, int $maxJobs = 25): WorkerActionResult`
- `purgeQueue(string $queue): WorkerActionResult`
- `dockerControlStatus(): array`

This service should reuse `QueueOverviewService` and `QueueManagementService` internally rather than repeating AMQP or Artisan logic.

### New Contract: `WorkerSupervisor`

Add a contract in `app/Services/Contracts/WorkerSupervisor.php`.

Responsibilities:

- enumerate known worker runtimes
- report runtime status
- execute lifecycle operations on allowlisted runtimes only
- optionally expose resource usage and recent log lines

Planned contract shape:

- `listRuntimes(): array`
- `status(string $runtime): WorkerRuntimeStatus`
- `start(string $runtime): WorkerActionResult`
- `stop(string $runtime): WorkerActionResult`
- `restart(string $runtime): WorkerActionResult`
- `tailLogs(string $runtime, int $lines = 50): array`
- `isConfigured(): bool`
- `backendLabel(): string`

### V1 Implementation: `NullWorkerSupervisor`

Add a `NullWorkerSupervisor` implementation that:

- returns the configured runtime names
- reports `not_configured`
- refuses `start/stop/restart/log tail` with a safe operator message
- provides setup guidance text for the page

This allows the UI and tests to ship before the real control plane is available.

### Future Implementation: `HttpWorkerSupervisor`

The real implementation should call a constrained host-side control plane over HTTP or another narrow RPC boundary.

Requirements for that agent:

- allowlist only known runtime names and known actions
- no arbitrary command execution
- return structured runtime data
- expose `start`, `stop`, `restart`, `status`, and optional `logs`, `stats`
- authenticate requests from the app container

This keeps Docker or host-process access outside Laravel.

## Data Structures

Add dedicated DTO-style arrays or small readonly value objects for page composition. The project already uses typed DTO patterns, so the new worker runtime layer should follow that style.

Suggested shapes:

- `WorkerRuntimeSnapshot`
- `WorkerQueueSnapshot`
- `WorkerHealthSnapshot`
- `WorkerActionResult`

Each worker snapshot should unify:

- runtime name
- queue name
- supervisor state
- broker state
- health state
- diagnostics summary

## UI Behavior

### `/admin/workers`

The page will become the primary control surface for worker lifecycle and queue drain operations.

Rules:

- page actions must validate runtime and queue names against known allowlists
- dangerous operations such as `Purge Queue` and `Stop` require confirmation
- actions should emit clear Filament notifications with before/after state when known
- refresh must be lightweight and safe under Octane

### `/admin/operations`

`Operations` remains in place but should be reduced to high-level operations visibility and links to deeper tools.

Queue/worker behavior should move as follows:

- keep queue overview summary widget or summary section
- remove active worker lifecycle controls from `Operations`
- add a prominent link or callout to `Workers`
- keep AI provider and Telescope-oriented operational concerns there

This removes duplication and makes responsibilities clearer.

## Error Handling

The system should fail closed:

- unknown runtime or queue names are rejected
- supervisor transport errors map to `unavailable`
- `not_configured` is a first-class state, not an exception path
- if broker data is available but supervisor data is not, show partial diagnostics with degraded or unavailable health rather than hiding the worker
- no action should ever shell out to an arbitrary command string assembled from user input

## Security Model

The critical constraint is that the Laravel app must not gain broad host control.

Security rules:

- no Docker socket mount into the `app` container
- no unrestricted `exec` bridge from admin inputs
- runtime names must come from static configuration
- action names must come from static code paths
- optional control agent must implement an allowlist, authentication, and structured responses

This design intentionally trades some convenience for a safer boundary.

## Configuration

Add a dedicated config file such as `config/workers.php`.

It should define:

- known worker runtimes
- queue-to-runtime mapping
- supervisor driver name
- safe defaults for drain limits
- operator guidance commands shown when supervisor is not configured

Example concerns:

- runtime `crawler-worker` -> queue `crawler_tasks`
- runtime `intelligence-worker` -> queue `intelligence_tasks`
- runtime `media-worker` -> queue `media_tasks`
- `run_until_empty_max_jobs` default `25`

The `NullWorkerSupervisor` should read the same config so the page stays stable before real control is enabled.

## Testing Strategy

Follow TDD during implementation.

Feature coverage:

- `/admin/workers` is accessible to admin users
- page renders worker runtime cards and diagnostics
- `not configured` state is rendered correctly
- `soft restart` triggers `queue:restart`
- `run until empty` uses safe bounded arguments
- `start`, `stop`, `restart` call the supervisor with allowlisted runtime names
- invalid runtime or queue names are rejected safely

Unit coverage:

- `WorkerManagementService` health calculation
- `WorkerManagementService` action orchestration
- `NullWorkerSupervisor` status and guidance behavior
- config-driven runtime mapping

Integration expectations:

- existing `QueueOverviewService` and `QueueManagementService` tests remain valid
- existing `Operations` tests should be adjusted to reflect the new page boundary

## Rollout Plan

### Phase 1

- add config and contracts
- add `NullWorkerSupervisor`
- add `WorkerManagementService`
- add `/admin/workers` page
- reuse current queue diagnostics and actions
- show Docker control as `Not configured`

### Phase 2

- split runtime topology into dedicated worker runtimes if not already done
- implement a real supervisor adapter
- enable `Start/Stop/Restart` against the adapter
- add memory, CPU, uptime, restart count, and log tail from the adapter

## Open Decisions Resolved

- Best UI location: dedicated `/admin/workers` page
- Best architecture: hybrid design with queue diagnostics inside Laravel and real runtime control behind a supervisor contract
- Best v1 safety posture: ship with `NullWorkerSupervisor`, not direct Docker socket access
- Best runtime topology: one worker runtime per queue family

## Implementation Readiness

This design is ready to move into an implementation plan.

The one constraint to preserve during implementation is strict separation between:

- queue inspection and queue-level safe operations inside Laravel
- runtime lifecycle control behind the supervisor adapter
- backend-specific Docker or host-process mechanics outside the Filament UI
