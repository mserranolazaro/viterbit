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

## ADR-008 · Use cases: commands, queries and the enrichment flow

**Decision.** Following the team's conventions, each use case is a message plus a handler
(`Application/{Command,Query}/<Aggregate>/<UseCase>/`) on `command.bus` or `query.bus`.
Event subscribers live in `Application/Event` on `event.bus`.

```
SubmitJobApplication ──▶ JobApplicationWasSubmitted ──┬─▶ RequestEnrichmentOnJobApplicationSubmitted
                                                     │      └─▶ EnrichJobApplication (own queue, retries)
                                                     └─▶ ProjectJobApplicationViewOnDomainEvent
EnrichJobApplication ──▶ EnrichmentWasStarted, then WasEnriched ──▶ projection
after the last retry ──▶ MarkEnrichmentAsFailed ──▶ EnrichmentHasFailed ──▶ projection
```

- **Every command handler** loads, changes, saves and then publishes the events it pulled from
  the aggregate. `SubmitJobApplication` returns the new id, as the team's create handlers do.
- **The event is turned into a command.** The event states a fact and can have several
  subscribers (the projection, the enrichment request, tomorrow a confirmation email). The
  command states an intent: it has its own queue and retry policy, and it can be re-sent by
  hand for a single application without replaying the event to every subscriber.
- **The enrichment saves `enriching` before calling the AI**, so the UI shows progress while
  the analysis runs, and a crash mid-call leaves a visible state rather than a silent one.
- **Failures are classified:**
  - `CvAnalysisFailedException` (provider down, malformed or schema-invalid output) is
    *transient*. It propagates and Messenger retries it; once retries are exhausted,
    `MarkEnrichmentAsFailed` records the final failure.
  - An application that does not exist is *unrecoverable*:
    `UnrecoverableMessageHandlingException`, no retries.
  - An application whose position is gone is a *business outcome*: it is marked as failed
    straight away, with the reason.
- **Idempotency:** an application already in a final status is ignored, and starting an
  enrichment that is in progress is a no-op. Duplicated or retried messages change nothing.
- **Queries on job applications return the read model as is.** `JobApplicationView` is
  already flat and shaped for the pages, so a response DTO would copy it field by field.
  Queries on positions map the aggregate to response DTOs, so the write model never
  reaches the UI.
- **Filters are forgiving.** Unknown statuses, malformed ids and out-of-range pages are
  ignored rather than turned into errors: a shared or hand-edited URL still shows a list.

**Alternatives discarded.**
- *Calling the AI inside the submit request*: the candidate would wait on a slow, flaky
  dependency, and a failure would lose the application or force the user to resubmit.
- *Letting the enrichment handler catch its own failures and mark them failed*: it cannot know
  whether a retry is coming. Only the worker knows when retries are exhausted.

**Testing note.** Handlers are unit-tested against PHPUnit doubles of the ports, as in the
team's services: mocks where the interaction is the assertion, stubs otherwise. A small spy
publisher records the published events. `MockClock` makes time deterministic.

**State between phases.** The ports have no adapters yet (they come with the infrastructure
phase), so the container does not compile at this point and the kernel smoke test was
removed. Its role is taken over by the integration and functional tests.

## ADR-009 · MongoDB only: one collection for writes, another for reads

**Decision.** Everything lives in MongoDB, in three collections:

| Collection | Model | Shape |
|---|---|---|
| `positions` | write | one document per position, requirements embedded |
| `job_applications` | write | one document per aggregate, enrichment embedded |
| `job_application_views` | read | flat, the position title copied in, a lower-cased `searchText`, indexes for the list |

**Why.**
- CQRS separates *models*, not *engines*. The write model is shaped by invariants and the read
  model by the list page. Two collections show that split as clearly as two databases do.
- A job application is a natural document: the candidate, the CV and the enrichment are always
  read and written together. No joins are needed.
- One engine means one container, one driver and no schema migrations.

**Alternatives discarded.**
- *MySQL for the write model (Doctrine ORM, as in the team's services), MongoDB for the views*:
  a valid polyglot-persistence design, and the most familiar to me. For a PoC it doubles the
  infrastructure (two engines, two mapping libraries, migrations) without changing the
  architecture. With a real reason, such as relational reporting or strong cross-aggregate
  constraints, it is a change confined to the repository adapters.
- *Querying the write collection directly for the list*: simpler, but then the write model
  would have to carry list concerns (the position title, search fields, list indexes) and CQRS
  would be in name only.

## ADR-010 · The official MongoDB driver instead of Doctrine ODM

**Decision.** Use `mongodb/mongodb` (the official library) with hand-written mappers in each
repository. Documents come back as plain arrays (`typeMap`), and every save is a whole-document
`replaceOne(..., upsert)`. Ports are bound to adapters explicitly in `config/services.yaml`.

**Why.**
- The domain stays free of mapping attributes (ADR-006). The mapping is about 150 lines for
  three collections, and it is explicit: what is stored is exactly what you read.
- Aggregates are saved whole, so the ODM's unit of work and change tracking bring nothing, and
  proxies and lazy loading would only hide what queries run.
- Fewer moving parts to explain and upgrade.

**Trade-offs and details.**
- BSON dates have millisecond precision and no time zone. Dates are stored in UTC, and the list
  breaks same-millisecond ties with the UUID v7 id, which is time-ordered.
- No optimistic locking. Every transition is idempotent and only the worker changes an
  application after submission, so concurrent writers are not a realistic case here. With
  several writers, a `version` field in the `replaceOne` filter would add it.

**Alternatives discarded.** *Doctrine MongoDB ODM* (5.6, compatible with Symfony 8): closer to
the ORM the team uses, but it would either put attributes on the domain or need XML mappings.
That is more machinery than the problem needs.

## ADR-011 · Messaging topology and publishing events

**Decision.**
- Three buses: `command.bus`, `query.bus` and `event.bus`. The last allows messages without
  handlers, because an event may have no subscribers yet.
- `DomainEvent` (the interface) is routed to the `events` transport, and
  `EnrichJobApplicationCommand` to `enrichment`. Each has its own fanout exchange and queue in
  RabbitMQ. Anything else is synchronous.
- The worker consumes `events enrichment` in that order, so projections are not stuck behind
  slow AI calls.
- Handlers publish *after* saving, through the `DomainEventPublisher` port (adapter:
  `MessengerDomainEventPublisher`).
- Messages use Messenger's default PHP serializer: these queues are private to this application.

**Trade-off: dual write.** Saving to MongoDB and then publishing to RabbitMQ are two writes with
no transaction around them. If the process dies between them, the application is stored but its
event is lost: it stays `received` with no view. Accepted for a PoC. The fix is the
**transactional outbox**: store events in the same document or transaction as the aggregate,
and have a relay publish them. Meanwhile, `app:views:rebuild` repairs the read model, and
re-sending `EnrichJobApplicationCommand` repairs the enrichment.

**Alternatives discarded.**
- *A JSON serializer with versioned event schemas*: required as soon as other services consume
  these events, not before.
- *One transport for everything*: one slow or failing AI call would delay every projection
  behind it.

## ADR-012 · Retries and the final failure

**Decision.**
- `enrichment` gets 3 attempts in total (retries after 1 s and then 3 s). `events` gets 4
  attempts, starting at 0.5 s and doubling.
- When the last attempt of an enrichment fails, the worker dispatches `WorkerMessageFailedEvent`
  with `willRetry() === false`. A listener, `MarkEnrichmentAsFailedOnFinalFailure`, then
  dispatches `MarkEnrichmentAsFailed` with the attempt count and the reason. The application
  becomes `failed`, and the UI says why.
- The message is also kept in the `failed` queue (Messenger's failure transport), where it can be
  inspected with `messenger:failed:show`.

**Why.** Only the worker knows whether another attempt is coming. A handler that caught its own
exception would have to guess, and would either give up too early or never mark the failure.

**Known limit.** `messenger:failed:retry` on an application that is already `failed` changes
nothing, because final statuses are respected. Re-enriching a failed application would be an
explicit use case (`failed → enriching`, for example from a "retry" button). It is listed for
future work.

**Verified end to end** against the real RabbitMQ and worker. A CV with the failure marker
goes `enriching`, gets 3 attempts in about 4 s and ends `failed` with
"Enrichment failed after 3 attempt(s): The LLM answer does not match the schema…". The
message then sits in `ats.failed`.

## ADR-013 · The AI behind a port, simulated as if it were a real LLM

**Decision.** `CvAnalyzer` (domain port) is implemented by `PromptBasedCvAnalyzer`
(infrastructure), which:

1. renders the versioned prompt `resources/prompts/cv_analysis.v1.md` with the position, its
   requirements and the CV;
2. sends it through `LlmClient::complete(string): string`, the smallest contract any provider meets;
3. strips Markdown code fences (models add them even when told not to), decodes the JSON and
   validates it against `cv_analysis.v1.schema.json` (JSON Schema 2020-12, `opis/json-schema`);
4. returns a `CvAnalysis` tagged with the prompt version, or throws `CvAnalysisFailedException`.
   That exception is transient, so the enrichment is retried (ADR-012).

`SimulatedLlmClient` is the only `LlmClient`. It is **deterministic** and reads **only the
rendered prompt**, as a real model would:
- *Score* is the weighted share of requirements found in the CV: `[must]` weighs 3 and `[nice]`
  weighs 1, giving `round(100 × matched / total)`. Matching is whole-word and case-insensitive,
  so "Go" does not match "Google" while "C++" still matches.
- *Summary* is the CV's first line, which requirements it meets and which it misses, plus the
  years of experience if the CV mentions them.
- A CV containing `[simulate-llm-failure]` gets a schema-invalid answer. This makes the retry
  and failure path easy to demo.

**Why.**
- The model is treated as untrusted input. The schema is the contract, not the prompt's good
  intentions. A real model can return prose, wrong types or extra fields, and all of these are
  rejected and retried.
- *Versioned prompts*: each stored score records which prompt produced it. Changing the prompt
  means adding `cv_analysis.v2.md` and its schema, never editing a file that results point to.
- *Prompt injection*: the prompt tells the model to treat the CV as data, and the simulation
  reads the CV up to the last closing tag. A CV cannot close its own section or forge
  requirements, and there is a test for this.
- Going live means writing one class (an HTTP `LlmClient` for the chosen provider) and changing
  one alias. The prompt, schema, validation, retries and tests stay the same.

**Alternatives discarded.**
- *A fake `CvAnalyzer` that returns a random or fixed score*: it skips the parts that break in
  production (prompting, parsing and validation), and random results make tests and demos
  unreliable.
- *Validating with PHP code instead of JSON Schema*: the schema is a language-neutral contract
  that is versioned next to the prompt and can be given to providers that support structured output.

## ADR-014 · Testing an asynchronous system

**Decision.**
- *Unit*: the domain, the use cases with doubles of the ports, and the AI adapters.
- *Integration*: the real MongoDB (database `ats_test`, dropped before each test) through the
  container. The asynchronous transports are `in-memory://` in tests, and
  `IntegrationTestCase::runWorker()` runs a **real Messenger `Worker`** over them until they
  are empty. That worker has the configured retry strategy and the same event listeners as
  production.
- Retries in tests keep the production *number* of attempts with no *delay*. The in-memory
  transport respects delays, so the worker would otherwise go idle before a retry is due.

**Why.** A test can assert what is true *between* the steps, which is where asynchronous bugs
live. After submitting, the event is queued and there is no view yet. After the worker runs,
the view is enriched. A failing enrichment leaves exactly 3 attempts and 1 message in the
failure transport. A redelivered command changes nothing.

**Alternatives discarded.** *`sync://` transports in tests*, the original plan: simpler, but a
failing enrichment would bubble up into the submit call (no retries, no final-failure
listener), and the tests would cover a flow that never runs in production.

**Consequence for the UI.** The read model is eventually consistent: for a few milliseconds
after submitting, there is no view yet. The pages have to cope with that (ADR-015).

**Functional tests** drive the app over HTTP with the same worker. Symfony empties in-memory
transports between two requests of the same test client, so a message queued by the POST would
vanish when the redirect is followed. A test-only kernel (`tests/Support/TestKernel.php`,
selected by `KERNEL_CLASS` in `.env.test`) removes that reset, so queues behave like a broker:
submit, see the pending page, run the worker, see the result. `runWorker('events')` runs only
the projections, which shows a listed application still waiting for the AI.

## ADR-015 · UI: server-rendered pages that update in place

**Decision.**
- **Twig, server-rendered.** Every page is a controller and a template. Each controller is one
  invokable class per route (the team's convention) that only dispatches queries and commands.
- **Real-time filtering and progress use HTML fragments.** The list and detail URLs return the
  full page, or only the part that changes when the request carries `X-Requested-With: fetch`.
  About 120 lines of vanilla JS (`assets/js/live.js`) handle this:
  - filters reload the results 250 ms after typing (immediately for selects);
  - an `AbortController` cancels stale requests, so results never arrive out of order;
  - `history.replaceState` keeps the URL shareable;
  - a region polls every 2 s while it contains `[data-pending]` (an application the AI has not
    finished), stops by itself, and pauses while the tab is hidden.
- **Progressive enhancement.** The filters are a plain GET form and the application form a plain
  POST. Both work without JavaScript; JS only makes them faster.
- **Eventual consistency, handled.** Right after submitting, the detail page may load before
  the projection. The candidate who has just applied (remembered in the session) sees an
  "Application received" state that refreshes itself. Anyone else asking for an unknown id
  gets a 404.
- **Form handling.** A request DTO validates the input with friendly per-field messages, and
  the domain enforces the same rules again as invariants. Errors re-render the form with
  status 422, keeping what was typed and focusing the first invalid field. Success returns
  303 to the detail page, so a refresh never resubmits. Session-based CSRF token.
- **Caching.** Responses send `Vary: X-Requested-With`, so the browser never serves a cached
  fragment as a full page (or the other way round) on Back.
- **Look: the bvsoft finance theme on Tailwind v4.**
  - The `--bv-*` tokens (navy primary, ochre accent, light and dark via
    `prefers-color-scheme`) are mapped into `@theme inline`, so utilities such as
    `bg-surface` and `text-muted` follow the theme.
  - The bvsoft components (`.card`, `.btn`, `.chip`, `.field`, `.entry`, `.empty-state`,
    rail and tab bar) are ported into `@layer components`.
  - Tailwind runs as the **standalone binary** (`symfonycasts/tailwind-bundle`, pinned to
    v4.3.3) through AssetMapper and an importmap: no Node.js, npm or bundler in the stack.
- **Self-hosted fonts and icons**, as in the team's apps (no third-party requests). Material
  Symbols is **subset to the 30 icons used**, cutting it from 5.4 MB to 33 KB. The script
  `resources/fonts/subset_material_symbols.py` regenerates it.

**Alternatives discarded.**
- *A JSON API plus client-side rendering (React/Vue)*: two renderers of the same row, a build
  chain, and more JavaScript to test, for three pages.
- *Server-Sent Events or WebSockets for progress*: push is the better fit at scale (Mercure
  would be the Symfony choice). For a PoC, a 2 s poll that stops when nothing is pending is
  simpler and has no extra infrastructure. It is listed for future work.
- *Symfony UX Turbo/Stimulus*: they would do the same with less custom JS, but they add a
  framework to learn for about 120 lines of code.

**Not in scope:** authentication and roles (the candidate and recruiter pages are open),
internationalisation (the UI is English), and file uploads (forbidden by the exercise).
