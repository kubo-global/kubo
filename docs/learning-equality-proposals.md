# Proposals for Learning Equality Partnership

KUBO is a school management platform deployed in The Gambia that integrates with Kolibri for exercise delivery. We've built a working integration that provisions students, embeds exercises, captures scores, and lets teachers author content — all offline on a local server.

This document outlines what we've built, where we're fighting Kolibri's design, and what upstream changes would benefit both KUBO and the broader Kolibri integrator ecosystem.

---

## What we built on top of Kolibri

### Adaptive skill graph engine

A prerequisite-based learning engine that finds where a student actually is, regardless of their grade level. If a Grade 6 student can't do multiplication, the system walks down the prerequisite chain to repeated addition and starts there.

- Weighted mastery calculation across the last 5 exercise attempts
- Spaced repetition review scheduling (expanding intervals: 1, 3, 7, 15 weeks)
- "Struggling" detection after 5 failed attempts — unlocks progression without faking mastery
- Teacher lesson assignments integrated with the adaptive recommendations

### Teacher exercise authoring

A step-by-step wizard where teachers with minimal digital literacy can create exercises: pick a skill, type questions, tap the correct answer, save. The system converts these into Kolibri's Perseus format and publishes them into Kolibri's content database — all locally, no internet required.

### Score capture pipeline

Students complete exercises in an embedded Kolibri iframe. Scores are fetched from Kolibri's attempt log API and fed into the mastery engine. The system handles Kolibri being temporarily unreachable (scores marked pending, retried later).

---

## Proposal 1: Embeddable exercise renderer

### The problem

Embedding Kolibri exercises in a third-party UI currently requires a full reverse proxy (~460 lines) that:

- Rewrites all URLs in both directions (patches `fetch()`, `XMLHttpRequest.open()`, DOM property setters)
- Strips Content-Security-Policy headers
- Manages Kolibri cookies (bypasses host app encryption, rewrites cookie paths)
- Injects `X-CSRFToken` headers on POST/PUT
- Injects CSS to hide navigation chrome, mastery badges, completion modals
- Injects a MutationObserver to hide dynamically-rendered UI elements
- Polls the iframe DOM every 500ms for completion text ("Stay and practice" / "Resource completed") because there's no completion callback

This is fragile. Any Kolibri frontend change (CSS class rename, route restructure, response format change) breaks the integration silently.

### The ask

A render mode, e.g. `/learn/#/topics/c/{nodeId}?embed=true&origin=http://kubo.local`, that:

- Hides navigation chrome server-side (no CSS injection needed)
- Sets `Access-Control-Allow-Origin` for the specified origin (no proxy needed)
- Sends `window.parent.postMessage()` events:
  - `{ type: 'kolibri-exercise-started' }`
  - `{ type: 'kolibri-attempt', correct: boolean }`
  - `{ type: 'kolibri-exercise-complete', score: number, total: number, correct: number }`
- Accepts auth via signed URL parameter or Bearer token (no cookie-based session needed)

### Impact

Eliminates our entire proxy. Every partner embedding Kolibri exercises faces the same problem — this would dramatically lower the integration barrier for any Kolibri deployment partner.

---

## Proposal 2: Local content import API

### The problem

Our `ChannelGenerator` writes directly to Kolibri's internal SQLite channel database. It replicates the entire Django-managed schema (12 tables), fakes 23 Django migration records, computes MPTT tree values (`lft`/`rght`), and writes Perseus ZIP files to Kolibri's internal storage directory structure (`~/.kolibri/content/storage/{hash[0]}/{hash[0:2]}/{hash}.perseus`).

This is our single biggest fragility point. Any Kolibri version that:
- Adds a NOT NULL column to `content_contentnode`
- Changes the MPTT implementation
- Adds a migration that validates schema consistency
- Modifies file storage conventions

...silently breaks all teacher-authored exercises.

### The ask

A management command:

```
kolibri manage importlocalchannel \
  --manifest /path/to/manifest.json \
  --content-dir /path/to/files/
```

Where `manifest.json` is a documented format describing:
- Channel metadata (name, description, version)
- Content tree (topics and exercises with parent-child relationships)
- Assessment metadata per exercise (mastery model, question count)
- File references (Perseus ZIP filenames and their content hashes)

Kolibri handles all internal schema details: MPTT computation, migration compatibility, file placement.

### Impact

Enables offline content creation workflows without Kolibri Studio. Useful for any deployment where teachers create local content — NGOs, government curriculum teams, community educators. Our `PerseusGenerator` (which converts simple question JSON into valid Perseus ZIPs) could be shared as a reference implementation.

---

## Proposal 3: Token-based integration authentication

### The problem

No mechanism exists for a trusted partner application to authenticate users into Kolibri without knowing their password. Our workaround:

- Generate deterministic passwords: `substr(sha256(secret + userId), 0, 16)`
- Pass plaintext username/password to the browser as template variables
- The exercise page POSTs credentials through the proxy to Kolibri's session API before loading the iframe

This means:
- The shared secret must be stable forever (rotating it orphans all Kolibri accounts)
- Credentials are visible in page source
- Every exercise load requires a full login round-trip

### The ask

A token exchange endpoint:

```
POST /api/auth/partner-session/
{
  "partner_id": "kubo",
  "token": "<signed JWT or HMAC>",
  "kolibri_user_id": "abc123"
}
```

Returns a session cookie or token. Alternatively: support for a signed URL parameter on content URLs that establishes a session without a separate login call.

### Impact

Standard integration pattern. Any LMS, SIS, or school management platform that provisions Kolibri users needs this. Combined with Proposal 1 (embed mode), this would enable zero-proxy Kolibri embedding.

---

## Proposal 4: Perseus format specification

### The problem

We reverse-engineered the Perseus exercise format from existing Kolibri channels. Our `PerseusGenerator` produces valid `.perseus` ZIP files containing:

- `exercise.json` with `all_assessment_items`, `current_version: 2`, `seed`
- Per-question JSON with widget schemas (`radio`, `numeric-input`), `itemDataVersion: {major: 0, minor: 1}`, hints

This works today, but we have no way to know if future Kolibri versions will change format expectations. Widget option shapes (`deselectEnabled`, `onePerLine`, `simplify: 'required'`) are undocumented renderer internals.

### The ask

A specification document covering:

- The `.perseus` ZIP envelope format
- `exercise.json` schema
- Per-item JSON schema
- Supported widget types and their option schemas
- `itemDataVersion` semantics and upgrade path
- Any planned deprecations

### Impact

Perseus is used by Khan Academy, Kolibri, and other platforms. A clear spec enables the broader education technology ecosystem to produce compatible content programmatically.

---

## Proposal 5: Exercise completion webhook / event API

### The problem

KUBO fetches exercise scores by polling Kolibri's attempt log API after the student finishes. The exercise page polls the iframe DOM every 500ms to detect completion. There is no push notification mechanism.

This creates a race condition: if the student's final attempt hasn't been synced when we query, we miss it. We work around this with timestamp filtering (`started_at`), but it's fragile.

### The ask

A webhook or callback mechanism:

```
kolibri manage register-webhook \
  --event exercise_completed \
  --url http://kubo.local/api/kolibri/webhook \
  --secret hmac_secret
```

Or, combined with Proposal 1: the `postMessage` events would include score data, eliminating the need to query the attempt log API entirely.

### Impact

Real-time integration without polling. Useful for dashboards, learning analytics platforms, and any partner that reacts to learner progress.

---

## Priority and risk summary

| Proposal | Eliminates | Risk if not addressed | Effort for LE |
|----------|-----------|----------------------|---------------|
| 1. Embed mode | 460-line proxy, DOM polling, CSS injection | Any Kolibri frontend change breaks embedding | Medium |
| 2. Content import API | Direct SQLite writes, MPTT computation, migration faking | Any Django migration breaks teacher-authored exercises | Medium |
| 3. Token-based SSO | Deterministic passwords, plaintext credentials | Security concern, credential rotation impossible | Low |
| 4. Perseus spec | Format guesswork | Subtle breakage on Kolibri upgrades | Low (documentation) |
| 5. Completion events | Attempt log polling, race conditions | Occasional missed scores | Low |

Proposals 1 and 2 are the most impactful — they address problems that affect every Kolibri integration partner, not just KUBO.

---

## What we can contribute

- **Perseus generation reference**: Our `PerseusGenerator` (PHP) that converts simple question JSON into valid Perseus ZIPs
- **Skill graph engine**: Proof of concept for adaptive prerequisite-based learning on top of Kolibri content
- **Integration test suite**: Patterns for testing Kolibri integration with mocked API responses
- **Deployment experience**: Real-world feedback from offline school deployments in The Gambia
