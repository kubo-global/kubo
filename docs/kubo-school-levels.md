# KUBO school levels and national assessments (groundwork)

Status: design note (not built). Purpose: make sure the NAT / scorebook work does not
corner KUBO into "Lower Basic only", so Upper Basic and Senior Secondary can be
activated later for schools that run them. The Swallow itself stays Lower Basic.

## The Gambia structure (6-3-3-4)

| Level | Grades | National / terminal exams |
|---|---|---|
| ECD / Nursery | pre-Grade 1 | - |
| Lower Basic | 1-6 | NAT census at Grades 3, 5 |
| Upper Basic | 7-9 | NAT census at Grade 8; GABECE at Grade 9 (Junior Secondary Certificate) |
| Senior Secondary | 10-12 | WASSCE at Grade 12 (tracks: science / arts / commerce) |

NAT (since 2008) is an annual census at Grades 3, 5, 8 that monitors standards and does
not affect progression. GABECE (since 2002) and WASSCE are terminal certificate exams.

## A school activates the levels it runs

KUBO today seeds Nursery + Grades 1-6 (Lower Basic). To generalise:

- Grades 7-12 join the grade set.
- A school has a set of **activated levels** (Lower Basic / Upper Basic / Senior
  Secondary). Activating a level makes its grades, subjects and exams available to that
  school. The Swallow activates Lower Basic only; a basic-cycle or senior school activates
  more.

## National exams are one concept, configured per (school, year, grade)

NAT, GABECE and WASSCE are all "external assessments configured per (school, school year,
grade)": a yearly map of grade -> subjects + max. They differ only in role:

- **NAT** - census at 3/5/8, used for the analysis reports (built).
- **GABECE** - terminal at Grade 9.
- **WASSCE** - terminal at Grade 12, with subject tracks.

The `NatConfig` / `NatConfigSubject` model built on the scorebook-redesign branch is the
first instance of this shape (per-year, per-grade subject set + max, carried forward on
rollover). GABECE/WASSCE would reuse the same shape (or a shared `assessment_config`),
plus a notion of "terminal/certificate" vs "census" and, for WASSCE, tracks.

## Why nothing here blocks it

The current NAT work keys everything on `(school, schoolyear)` and grade, never on
"Lower Basic". Adding upper grades and more exam types is **additive**: new grade rows, a
level-activation flag per school, and more assessment configs. No rework of the NAT or
scorebook code is implied.

## Out of scope now

Actually building Upper Basic / Senior Secondary (grades 7-12, GABECE, WASSCE, tracks,
level activation UI) is a separate platform effort. This note is the groundwork.

## Sources

- Gambia 6-3-3-4 system, GABECE (Grade 9) and WASSCE (Grade 12):
  WENR/WES; education.eres.com; globalpartnership.org ESSP 2016-30.
- NAT census at Grades 3/5/8 since 2008: WENR/WES.
