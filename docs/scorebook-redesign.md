# Scorebook redesign

Status: in progress (this branch)
Goal: replace the dropdown-cascade + reactive Livewire `Grades` component with plain
click-through navigation and dedicated add/edit screens, and clean up the menu naming.

## Why

- The Grades page forced a four-dropdown cascade (School year, Class, Term, Subject)
  before showing anything, and the create form re-asked the same.
- "Grades" in the menu clashed with the school's "Grade 1-6" classes and with the page
  title ("Scorebook"); "Progress" actually means digital skill-mastery, not marks.
- A NAT is not part of a term, but the flow tied it to one.

## Decisions (agreed with Shane)

- Keep **dedicated screens for add/edit** (explicit save, fits offline shared laptops);
  viewing is read-only. No inline-editable grid.
- Replace dropdown navigation with **click-through**: current-year class list, click a
  class, subject tabs, read-only table.
- **Year defaults to current**; a quiet switcher (top right) reaches past years.
- Menu: **Grades -> Scorebook**, **Progress -> Skills**.
- **NAT is term-less** (done on the schoolyear branch: nullable term_id, excluded from
  term reports, shown in the scorebook regardless of term).

## Screens (mocked in GAMBIA/NAT/mockup/)

1. **Class list** (`reporting.grades`): current-year classes as cards; quiet year
   switcher; click a class.
2. **Class overview** (`scorebook.class`): breadcrumb, subject tabs, read-only table of
   pupils x assessments (incl. a highlighted NAT column), action buttons that open the
   existing dedicated screens (prefilled), plus the NAT PDF buttons.

## Build

- `ScorebookController`
  - `index()` -> current school year (or `?year=`) classes for the user (headmaster: all;
    teacher: own offerings). Renders `pages.scorebook.classes`.
  - `class(Offering $offering, ?Subject $subject)` -> read-only assessment table for the
    class + selected subject (first subject by default). Renders `pages.scorebook.class`.
- Routes: repoint `reporting.grades` at `ScorebookController@index`; add
  `scorebook/class/{offering}/{subject?}` named `scorebook.class`.
- Views: `pages/scorebook/classes.blade.php`, `pages/scorebook/class.blade.php`
  (Tailwind, via `x-page`).
- Reuse the existing dedicated screens for entry/edit: `scorebook.create` /
  `reporting.assessment.create` / `reporting.assessment.edit`, passed offering+subject.
- **Retire** the reactive `App\Livewire\Grades` component and `livewire/grades.blade.php`
  and `pages/grades.blade.php` once the new index is live (left in place this PR for a
  clean diff; delete in the follow-up).
- **NAT create form**: hide the Term field for zero-weight (NAT) types and stop
  requiring it on store (follow-up within this branch).

## Tests

- Feature: `reporting.grades` renders the class list for the current year (headmaster
  sees all classes; teacher sees only their own).
- Feature: `scorebook.class` renders a class's assessments for a subject, including a
  term-less NAT column, and excludes other classes.

## NAT configuration (per school year)

NAT is not a fixed thing: the ministry can change which grades sit it, the subjects, or
the totals from one year to the next. So the configuration is **per school year**, not a
static per-country constant.

A NAT config, resolved for a `(school, schoolyear)`, holds:

| Field | Example (Gambia 2024-2025) | Drives |
|---|---|---|
| `enabled` | true | whether NAT runs that year at all |
| grades that sit it | Grade 3, Grade 5 | whether the class-level NAT button shows on a class |
| subjects per grade | G3: English, Maths, Integrated Studies · G5: + Science, S.E.S. | the columns on the NAT page + the entry form |
| max per subject (total) | 100 (total 300 / 400) | the entry form and the report `/100`, `/300` |
| pass / mastery thresholds | fail < 40, mastery >= 80 | the report bands (today via the school's `GradingScale` `nat` bands; may move per-year) |
| exam label | "National Assessment Test" | the button + report titles |

**National structure (WAEC, since 2008).** The NAT is an annual *census* sat by every
pupil in **Grades 3, 5 and 8**; it monitors standards and does not affect progression.
Subjects follow the core curriculum and differ by grade (lower grades use a combined
"Integrated Studies"; it splits into Science + S.E.S. higher up):

| Grade | Subjects (Gambia default) |
|---|---|
| Grade 3 | English, Maths, Integrated Studies |
| Grade 5 | English, Maths, Science, S.E.S. |
| Grade 8 | core set (configure when applicable) |

**Per school, too.** The Swallow is a **Lower Basic** school (Grades 1-6), so it sits the
NAT in **Grades 3 and 5 only**. When The Swallow transitions into **Upper Basic** (adds
Grade 8), that year's config simply gains a Grade 8 subject set, no code change, because
the config is keyed on `(school, schoolyear)`. A school that already runs Upper Basic
configures Grade 8 from the start.

**Carry-forward, not re-entry.** When a new school year is created (the rollover / "New
School Year" flow), copy the previous year's NAT config as the starting point. The common
case (nothing changed) needs zero work; a year where the ministry changed something, or
the year the school adds Grade 8, is a quick edit on that year's config. Nothing is
hard-coded.

**Data shape (proposed).** `nat_configs(school_id, schoolyear_id, enabled, label)` unique
on `(school_id, schoolyear_id)`, plus `nat_config_subjects(nat_config_id, grade_id,
subject_id, max_score, display_order)` for the per-grade subject set + max. Resolve via a
`NatConfig::for($school, $schoolyear)` service that `NatAnalysis`, the NAT page, and the
class-overview button all read from. Gambia ships as a seeded config for the relevant
years; another country is a different seeded config, no code change.

## Out of scope (noted)

- Subject-tab overflow for classes with many subjects (group core vs others).
- "New School Year" sits among sections rather than actions (minor).
