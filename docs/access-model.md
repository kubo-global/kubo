# KUBO — Modules, Roles & Permissions

> **Context for the LLM writing the report:** This document describes KUBO's
> access-control architecture. KUBO is an offline-first school management
> platform built to run on a local classroom server (no internet required),
> currently deployed at a school in The Gambia. The system is organised into
> toggleable **modules**, access is governed by **roles**, and every role's
> capabilities are defined by a **permission matrix** that schools can
> reconfigure in-app. Use the framing below; all figures and defaults are
> current as of this document.

---

## Summary (one paragraph)

KUBO separates functionality into self-contained **modules** that a school
enables or disables to match its needs, and controls who can do what through
**seven roles** mapped onto a granular **permission system**. The defaults are
tuned so a small school works out of the box with the headmaster as the single
administrator, while a larger school can split responsibilities — front-office
administration, technical system maintenance, health staff, planning oversight
— across distinct roles. Crucially, the permission assignments are not
hard-coded: a designated role can rewrite the whole role × permission matrix
from within the app, and individual users can be granted exceptions, so each
school adapts the governance model to its own size without any code changes.

---

## Roles

KUBO defines seven roles, spanning learners, teaching staff, specialists, and
administrators.

| Role | Who they are | What they do in KUBO |
|---|---|---|
| **Student** | A pupil at the school | Logs in to a personal learning dashboard; practices exercises drawn from mapped curriculum content. Sees nothing else. |
| **Teacher** | Classroom / subject teacher | Views their own classes, enters grades for the subjects they teach, writes lesson plans, browses the content library. The day-to-day academic worker. |
| **Caregiver** | School nurse / health teacher | Records and reviews student health — checkups, growth, incidents, wound care, notes. Scoped to health only; no academic access. |
| **Administration** | Front-office / school secretary | Runs the operational side: enrollment, staff accounts, academic structure (subjects, terms, classes), and reports. Does not see health records by default. |
| **Assistant coordinator** | Senior teacher who reviews planning | Reviews and signs off on lesson plans before the coordinator. A light oversight role; no broad data access by default. |
| **Headmaster** | School head (also acts as "coordinator") | Full academic and administrative oversight — the default owner of everything in a small school, including final lesson-plan sign-off and access configuration. |
| **System admin** | Technical maintainer / IT | Backups, school-year rollover, and access control. The technical owner; carries the "root" responsibilities so the headmaster doesn't have to. |

The split between **Administration** (school business) and **System admin**
(technical operations) lets a larger school separate front-office staff from
whoever maintains the server, while a small school can let the headmaster cover
both — the headmaster automatically inherits the system-admin duties until a
dedicated system admin is appointed.

---

## Modules

Each module is a self-contained area of the platform that a school can switch
on or off in Settings, so it only runs what it needs.

| Module | What it is | Default |
|---|---|---|
| **Students** | The student roster — profiles, guardian contacts, and enrollment into classes. The foundation the other modules build on. | Always on |
| **Grades & Assessments** | Tests, exams, the score-entry workbook, and end-of-term report cards with weighted averages. | Always on |
| **Progress** | Analytics and dashboards showing how each student is tracking over time, across assessments and exercises. | On (toggleable) |
| **Lesson Plans** | Daily lesson planning with assistant-coordinator → headmaster sign-off, and the curriculum-topic links that decide which exercises a class can access. | On (toggleable) |
| **Library** | Browse the offline Kolibri content library and map its exercises and videos onto the school's curriculum. Requires Kolibri running on the same machine. | On (toggleable) |
| **Student Learn** | The student-facing side: a simple dashboard serving practice and homework from the mapped, teacher-approved content. | On (toggleable) |
| **Health** | A per-student health timeline — routine checkups and growth charts, incident reports, ongoing wound-care logs, and free-form medical notes. Access is gated separately so medical data stays restricted. | On (toggleable) |

**Students** and **Grades & Assessments** are core and always enabled; the
other five can be toggled per school. KUBO runs fully offline on a local
classroom server, which is why content and learning are self-hosted rather
than cloud-based.

---

## Access matrix — roles × capabilities

This is the headline table: it shows all three concepts at once — modules
become the capability columns, roles are the rows, and the cells show the
refined per-role access.

| Role | Students & enrollment | Grades & reports | Lesson plans | Content library | Health records | Staff / users | System (backups, rollover) | Access control |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| **Student** | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ |
| **Teacher** | ◐ ² | ◐ ² | ● | ● | ○ | ○ | ○ | ○ |
| **Caregiver** | ○ | ○ | ○ | ○ | ● | ○ | ○ | ○ |
| **Administration** | ◐ | ● | ● | ● | ○ | ● | ○ | ○ |
| **Assistant coordinator** | ○ | ○ | ◐ ³ | ○ | ○ ⁴ | ○ | ○ | ○ |
| **Headmaster** | ● | ● | ● | ● | ● | ● | ◐ ¹ | ● |
| **System admin** | ○ | ○ | ○ | ○ | ○ | ○ | ● | ● |

**Legend:** ● full · ◐ scoped · ○ none

1. Headmaster covers system tasks automatically only until a dedicated system admin is appointed.
2. Teachers see their own classes; grade entry is limited to subjects they're assigned (other subjects are open by default but can be locked to specific teachers).
3. Reviews and signs lesson plans; does not author them.
4. Health access can be granted to an individual assistant coordinator without changing their role.

> **All access shown is the default starting point.** Every role's
> capabilities are reconfigurable in-app through the permission matrix, and
> individual users can be granted exceptions — so a school adapts the model to
> its own size and governance without code changes.

---

## How configurability works (for accuracy in the report)

- **Modules** are toggled school-wide in Settings → Modules. Disabling a
  module hides it from the menu for everyone.
- **Permissions** are assigned per role in Settings → Permissions (a
  checkbox matrix of roles against capabilities). This matrix is itself the
  most sensitive surface — whoever can edit it can grant any access — so it
  is gated behind its own "manage permissions" capability, held by the
  headmaster and system admin by default. A school with stricter governance
  can remove it from the headmaster, leaving the system admin as sole owner.
- **Per-user exceptions** are set on the Users page — e.g. granting one
  assistant coordinator access to health records without redefining the
  whole role.

This three-level model — module on/off, role permissions, per-user overrides
— is what lets one codebase serve both a small school (headmaster does
everything) and a larger one (responsibilities split across distinct staff).
