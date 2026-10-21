# Decision log

🇬🇧 English · [🇪🇸 Español](DECISIONS.es.md)

Each entry records a decision, the alternatives considered and why they were discarded.
The English version is canonical; the Spanish one is updated in the same commit.

---

## ADR-001 · Stack and a self-contained local environment

**Decision.** PHP 8.4, Symfony 8.1, Symfony Messenger over RabbitMQ, MongoDB, PHPUnit, Twig.
Everything runs in Docker Compose: `php` (FPM), `caddy` (web server), `worker` (Messenger
consumer), `mongo` and `rabbitmq`. `make init` builds the images, installs dependencies,
prepares runtime resources and starts the worker last, once the code it runs is installed.

**Why.** It is the stack the team works with, so the exercise shows how I would work on the
real codebase rather than in a stack chosen to minimise effort. One command and no host
requirements beyond Docker make it easy to review.

**Alternatives discarded.**
- *FrankenPHP or `symfony serve`*: fewer containers, but PHP-FPM behind a web server is the
  most common production layout and keeps the web and worker processes clearly separated.
- *A worker started by hand* (`docker exec … messenger:consume`): easy to forget, and the
  asynchronous flow is the core of the exercise, so the worker is a first-class service with
  `restart: unless-stopped`.

**Notes.**
- Host ports avoid the usual defaults (web `8080`, RabbitMQ UI `15673`, Mongo `27018`) so the
  stack can run next to other local projects. AMQP is not published to the host at all.
- The container runs as uid 1000; CI makes the checkout writable for that user.

## ADR-002 · Symfony 8.1 instead of 8.0

**Decision.** Use the `8.1.*` branch.

**Why.** Symfony 8.0 is out of maintenance and Composer refuses to install it: every 8.0.x
release of `symfony/http-foundation` and `symfony/yaml` is affected by published security
advisories (e.g. CVE-2026-108082, a ReDoS in `HeaderUtils::split()` reachable through the
`Cache-Control` request header) that are only fixed in 8.1.x. Ignoring advisories to stay on
8.0 would be the wrong trade-off for a web-facing app.

**Alternatives discarded.** Symfony 7.4 LTS: longer support, but it would mean using an older
major version than the team's for no functional gain in a PoC.

## ADR-003 · MongoDB 7.0 in the local environment

**Decision.** Pin `mongo:7.0` in `compose.yaml`.

**Why.** MongoDB 8.0, 8.3 and 9.0 refuse to start on Linux kernels from 6.19 up to 7.0.13
(a TCMalloc/rseq incompatibility; see SERVER-121912 and SERVER-125742). Current Docker Desktop
ships such a kernel (`7.0.12-linuxkit` here), so `make init` would fail on a reviewer's
machine. The application uses nothing that needs 8.x: CRUD, compound indexes and anchored
regex queries are all available in 7.0, which is supported until 2027.

**How to revisit.** Once Docker Desktop ships kernel ≥ 7.0.14, bump the image to `mongo:8`
or later; no code change is needed.

## ADR-004 · Tracking PHP security releases (php2shell, 21 Oct 2026)

**Context.** On 20 Oct 2026 the PHP security team pre-announced a release for 21 Oct that fixes
two critical vulnerabilities in PHP core. Details had leaked beforehand ("php2shell") and early
attacks were reported the same morning. The release came out on 21 Oct, ahead of the announced
14:00 UTC:

| Advisory | CVE | What it is | CVSS v4 |
|---|---|---|---|
| GHSA-w8hw-w9fx-j79w | CVE-2026-109618 | RCE via OOM corruption: the out-of-memory `longjmp()` leaves request memory half-initialised. Reachable unauthenticated through **multipart parsing** (many empty file fields, unlimited part headers). | 9.2 |
| GHSA-6wmf-4rv5-p3rp | CVE-2026-109619 | RCE via stale global state: a fatal error in a persistent worker leaves global state pointing at freed request memory (use-after-free). One path is **session upload progress**. | 9.2 |

Fixed in **8.2.35, 8.3.36, 8.4.27 and 8.5.12**. Upgrading is the only complete fix.

**Status (21 Oct 2026, 13:45 UTC).** PHP 8.4.27 is published on php.net, but the official
`php:8.4.27-fpm` Docker image is not available yet. The image is still built from
`php:8.4-fpm`, which resolves to **8.4.26 (vulnerable)**.

**Decision.**
1. *Now, defence in depth:* `docker/php/conf.d/security.ini` turns off what the app does not need.
   The CV is pasted as text and file uploads are out of scope, so the following are off or
   capped: `file_uploads=Off`, `max_file_uploads=0`, `post_max_size=1M`,
   `session.upload_progress.enabled=0` and `expose_php=Off`. This shrinks the multipart and
   upload-progress surface both CVEs go through. It is a mitigation, not a fix.
2. *As soon as the image is published:* pin `FROM php:8.4.27-fpm` (an exact patch version instead
   of the floating `8.4` tag, so a cached image cannot silently keep the vulnerable build), and
   make CI fail when the PHP inside the image is older than that.

**Why it matters here.** The app is a public form that accepts free text: exactly the
unauthenticated, request-parsing surface these bugs target. Turning off unused features is
cheap and stays useful after the upgrade.

## ADR-005 · One bounded context, `Recruitment`, and the name `JobApplication`

**Decision.** A single bounded context, `Recruitment`, holds positions, job applications and
their AI enrichment. `SharedKernel` holds the building blocks (aggregate root, domain event,
UUID ids). Layers follow the team's layout: `Domain/Write` (aggregates, value objects, events,
exceptions, repository ports, domain services), `Domain/Read` (read model and its port),
`Application` and `Infrastructure`.

The aggregate is called **`JobApplication`**, not `Application`.

**Why.**
- Everything here shares one language (a candidate applies to a position and gets scored)
  and changes for the same reasons. Splitting it would add integration events and
  translation layers to a PoC without removing any coupling.
- The AI is a *port* of this context (`CvAnalyzer`), not a context of its own: it has no
  model or language of its own here, it is a capability the domain uses.
- "Application" already names a layer. `Application/Command/Application/SubmitApplication`
  is ambiguous on every read; `JobApplication` is also the term recruiters use.

**Alternatives discarded.**
- *A separate `Enrichment` (or `Screening`) context*: it would make sense if screening grew its
  own rules, models or team. The layout leaves room to extract it.
- *A `Position` context*: in a full ATS, job openings belong to another context (requisitions,
  publishing). Here they are read-only seed data.

## ADR-006 · A pure domain: invariants, a single state machine and injected time

**Decision.**
- The domain has no framework or persistence code. No mapping attributes, no Symfony
  services, and it is excluded from autowiring. The only external dependency is
  `symfony/uid` to generate UUID v7 ids.
- Value objects enforce the invariants. `Email`, `Candidate`, `CvText` (non-empty, at most
  20,000 characters, kept verbatim), `Score` (0–100) and `CvAnalysis` cannot exist invalid.
- The allowed status transitions live in one place, `JobApplicationStatus::canTransitionTo()`:
  `received → enriching → enriched | failed`, plus `received → failed` for applications that
  cannot even start (e.g. their position is gone). Final statuses accept no transition.
- `startEnrichment()` on an application that is already enriching does nothing. Messages
  are delivered at least once, so a retry must be harmless.
- The aggregate never reads the clock: each method gets `$now`. Tests are deterministic
  without mocking time, and handlers pass `ClockInterface::now()`.
- Ids are UUID v7, generated by the domain: no database round trip, and they sort by
  creation time.
- `reconstitute()` rebuilds an aggregate from storage without recording events; `submit()`
  and the transitions record them.

**Why.** The rules the CTO will ask about (what states exist, what can follow what) can be
read and tested in two small files without booting anything. The tests cover the full
transition table.

**Alternatives discarded.**
- *Doctrine mapping attributes on the domain classes* (as in the team's MySQL services): it
  is pragmatic, but it couples the model to one persistence library. With MongoDB the mapping
  is small enough to write by hand in the infrastructure layer (see the persistence ADR).
- *Status as a string with `if`s in each method*: the rules end up spread over the aggregate.
- *A state machine library (symfony/workflow)*: too much for four states and five transitions.

## ADR-007 · Lean events, and a projection that re-reads the aggregate

**Decision.** Domain events carry the aggregate id and the facts that changed (the position
id on submission, the score on enrichment, the reason on failure), not the whole aggregate.
The read model is built by a projector that, on **any** event, loads the current aggregate
and its position and **replaces** the whole view (an upsert).

**Why.**
- *Idempotent*: delivery is at least once, and applying the same event twice writes the same view.
- *Order-independent*: the events are consumed asynchronously, so `JobApplicationWasEnriched`
  can be handled before `JobApplicationWasSubmitted`. Re-reading always writes the latest state.
- *Rebuildable*: the whole read model can be regenerated from the write model with a
  console command after a bug or a schema change.
- *Stable contracts*: small events change less often than aggregates.

**Trade-off.** The projector reads the write model, so it is coupled to it. That is fine
inside one bounded context. Events going to other services would be integration events with
a full, versioned payload.

**Alternatives discarded.** *Apply each event as a delta to the view* (`$set status`, `$set score`):
the usual event-sourced style, but it needs version checks to survive out-of-order and
duplicated messages. That is more code and more ways to be wrong, for no gain here.
