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

**Context.** On 20 Oct 2026 the PHP security team pre-announced a release for
**21 Oct 2026 at 14:00 UTC** that fixes two critical vulnerabilities, both rated
CVSS v4.0 9.2. Security researchers identify them as CVE-2026-109618 and CVE-2026-109619
("php2shell", remote code execution in PHP core). Details had leaked before the release, and
early attacks against PHP sites were reported the same morning.

**Status when this entry was written (21 Oct 2026, 13:35 UTC).** The fix is not published yet.
The image is built from `php:8.4-fpm`, which currently resolves to **PHP 8.4.26
(24 Sep 2026), so it is not patched**. Only `8.4.27RC1` exists on Docker Hub.

**Decision.**
- As soon as the fixed release is out, pin the exact patched version in `docker/php/Dockerfile`
  (instead of the floating `8.4` tag) so a cached image cannot silently keep the vulnerable build.
- CI checks the PHP version inside the image against that minimum and fails below it.

**Why it matters here.** The app is a public form that accepts free text, which is the kind of
surface such vulnerabilities target. Pinning patch versions makes the security baseline
explicit and reviewable in a diff, instead of depending on when a tag was last pulled.
