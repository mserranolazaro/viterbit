# QA report

🇬🇧 English · [🇪🇸 Español](QA.es.md)

An exploratory pass that tried to break the application on purpose, before delivery. Every
issue found was fixed, and each fix comes with a test that fails without it.

## How it was tested

- **HTTP abuse:** malformed, oversized and malicious requests with `curl`:
  - query and form fields sent as arrays, and NoSQL operators (`q[$ne]=1`);
  - unreadable and out-of-range pages, and regex metacharacters;
  - invalid UTF-8, null bytes, and inputs of 5,000 characters;
  - path traversal, unsupported methods, and a spoofed `Host`;
  - a POST over the size limit, and a forged CSRF token.
- **Form edges, with a real CSRF token:**
  - length limits, emoji and right-to-left text;
  - zero-width characters, control characters, and `\r\n` line breaks;
  - email and phone formats, consent values, and duplicate submissions.
- **The browser:** fast typing, Back after filtering, a failed network, a double click, and a
  very long unbroken name on a 375 px screen.
- **Infrastructure failure:** RabbitMQ stopped in the middle of an application.

## Findings and fixes

| # | Severity | What happened | Fix | Guarded by |
|---|---|---|---|---|
| 1 | High | With RabbitMQ down, applying returned **500**, but the application was saved, orphaned (no view, no enrichment), and a retry created a duplicate. | The publisher logs a broker outage instead of failing a change that is already saved. A **reconciler** (Symfony Scheduler, every minute) re-projects and re-enriches applications left unfinished. Verified live: recovered on its own in about 2 minutes. | `MessengerDomainEventPublisherTest`, `ReconcileJobApplicationsHandlerTest`, `EnrichmentFlowTest` |
| 2 | High | The same candidate could apply twice to the same position (double click, refresh, retry). | One application per email and position: a check in the use case plus a **unique index** for races, a clear message on the form, and the button disabled after the first click. | `SubmitJobApplicationHandlerTest`, `MongoJobApplicationRepositoryTest`, `ApplyToAPositionTest`, acceptance `ADoubleClick…` |
| 3 | Medium | A POST over 1 MB returned **500**: PHP printed its size warning into the response. | Caddy refuses bodies over 1 MB with **413** before PHP. PHP never prints warnings into responses (`display_errors=Off`). | Checked with `curl` against the stack |
| 4 | Medium | A CV at the 20,000-character limit was **rejected**: browsers count a line break as one character for `maxlength` but send `\r\n` (two). | Line breaks are normalised to `\n` before validating and storing. | `CvTextTest`, `TextTest`, `ApplyToAPositionTest` |
| 5 | Medium | A name made only of zero-width spaces was accepted, which gave an invisible candidate. | Names drop invisible and control characters and collapse whitespace; the CV and notes drop control characters. | `TextTest`, `CandidateTest`, `ApplyToAPositionTest` |
| 6 | Medium | On a phone, a very long unbroken name or summary widened the page to 3,170 px. | `overflow-wrap: anywhere` on the page, and `minmax(0, 1fr)` on the grids that grew with their content. | Checked in the browser at 375 px |
| 7 | Medium | If a refresh failed (network), the URL showed the new filters but the list kept the old results, with no warning. | A visible "could not update" message with a **Try again** button. An aborted request no longer clears the busy state of the newer one. | Acceptance `AListThatCannotRefresh…` |
| 8 | Low | `?page=abc`, oversized pages or array parameters answered **400**, although malformed filters are meant to be ignored. | Every filter is read leniently; anything unreadable is ignored. | `BrowseApplicationsTest` (data provider) |
| 9 | Low | A page past the end said "No applications yet" when there were applications. | It says the page is past the end and links back to the first page. | `BrowseApplicationsTest` |
| 10 | Low | Control characters (`\x01`, ANSI escapes) were stored in CVs. | Removed when normalising (tabs and line breaks are kept). | `TextTest` |
| 11 | Medium | *Found in the final review:* when enrichment falls behind, the reconciler re-queued every waiting application every minute, flooding the queue it was meant to drain. | Each application is re-driven at most once per window, in batches of 100. | `ReconcileJobApplicationsHandlerTest`, `MongoJobApplicationRepositoryTest` |
| 12 | Low | *Found in the final review:* browsers asked for `/favicon.ico` (logged as 404 errors), and Caddy, MongoDB and RabbitMQ ran on floating tags. | A static `/favicon.svg`, and every image pinned to an exact version. | Acceptance run: 0 favicon 404s |

## What held up

- **XSS:** a name like `<img src=x onerror=alert(1)>` is escaped everywhere it appears: the
  title, top bar, `h1`, a `data-` attribute, the list and the fragments. A nonce-based CSP
  backs this up.
- **Injection:** NoSQL operators and regex metacharacters are matched literally, and ids are
  validated as UUIDs before reaching the database.
- **Routing:** path traversal returns 404, unsupported methods return 405, and `.env` cannot
  be reached.
- **Security controls:**
  - a forged CSRF token is refused;
  - the rate limit answers 429 after 5 applications;
  - a spoofed `Host` header does not leak into links or redirects, which are relative.
- **Browser behaviour:**
  - Back after filtering restores the full page (`Vary: X-Requested-With`);
  - typing fast sends one request (debounce), and stale responses are discarded (`AbortController`).

## Test suites

| Suite | Runs | Count |
|---|---|---|
| Unit | domain, use cases with doubles of the ports, AI adapter, listeners | |
| Integration | real MongoDB, real Messenger worker over in-memory transports, console commands | 186 in total (`make test`) |
| Functional | HTTP flows through the kernel, CSRF included | |
| Acceptance | a real Chrome (Selenium) against the running stack: workers, RabbitMQ, JavaScript | 6 (`make acceptance`) |

**Coverage** (default suite): **99.2 % of lines, 96.5 % of methods**. `make coverage` and CI
fail below 90 %.

**Not covered on purpose:** two defensive branches that rethrow unexpected MongoDB write
errors, and a catch for domain rejections that the form validation already prevents.
