# NAT Analysis, design proposal

Status: **draft for review** (no code written yet)
Author: generated for Shane, 2026-06-19
Scope decision: **Gambia / WAEC NAT first**, structured so other countries can be added later by configuration, not code.

## 1. What this feature is

The National Assessment Test (NAT) is a WAEC standardized exam sat in Grade 3 and Grade 5.
Each pupil gets a score (0–100) per subject; the school receives a printed result listing by
school of entry. Schools want the same analysis we currently produce by hand in a spreadsheet:

- a **scores listing** (pupil × subject, with fail/mastery highlighting), and
- an **analysis report**: per-subject **pass / fail / mastery** counts and percentages, broken
  down by **sex** (Male / Female / All), with a bar chart per subject.

Reference deliverables this mirrors live in `GAMBIA/NAT/` (the 2023/2024/2025 reports and
`build_nat.py`). Thresholds used there, and proposed here:

| Band | Rule (per subject, max 100) |
|------|------|
| Fail | score < 40 |
| Pass | score ≥ 40 (cumulative, includes mastery) |
| Mastery | score ≥ 80 |

"Present" = a pupil with a numeric score. Absent pupils (printed as `A` on the WAEC listing)
are excluded from the denominators.

## 2. It fits the existing schema almost exactly

No new core concepts are required. NAT maps onto models KUBO already has:

| NAT concept | Existing model | Notes |
|---|---|---|
| "NAT" as a kind of exam | `AssessmentType` (per `School`) | Seed one row, e.g. name `National Assessment Test`. |
| A NAT subject paper for one class/year | `Assessment` | `assessment_type_id`, `subject_id`, `offering_id`, `term_id`, `max_score = 100`, `date`, `confirmed`. One Assessment per (subject × offering). |
| A pupil's mark | `AssessmentScore` | `assessment_id`, `student_id`, `score` (nullable), `excused`. **`excused = true` is exactly the WAEC `A` (absent).** Existing `saving()` guard already rejects score > max_score and enforces the null⇔excused rule. |
| Subjects | `Subject` | English, Maths, Integrated Studies, Science, Social & Environmental Studies. |
| Year + grade cohort | `Offering` → `Schoolyear` + `Grade` | e.g. Grade 5 / 2024, Grade 3 / 2025. `Offering->enrollments` gives the pupils. |
| Pupil sex | `Profile.gender` | `profiles.gender` (char). Drives the Male/Female/All split. Needs a documented mapping (see §6). |
| Fail/Pass/Mastery thresholds | `GradingScale` (per `School`) | Already the per-school banding mechanism (`min_score`/`max_score`, `GradingScale::resolve()`). This is the country-generalization hook. |

**Why this is good:** entering NAT results reuses the existing assessment-entry flow (the chosen
option), absences are already modelled, and thresholds are already a per-school concept, so a
second country is "add a School + its GradingScale + its subject set", not new code.

## 3. Thresholds via GradingScale

Define three **exclusive** bands per school:

| name | min_score | max_score |
|------|-----------|-----------|
| Fail | 0 | 39.99 |
| Pass | 40 | 79.99 |
| Mastery | 80 | 100 |

The report's cumulative **Pass** column (≥40) = Pass band + Mastery band, i.e. simply
`present − fail`. Keeping bands exclusive keeps `GradingScale::resolve()` unambiguous and lets the
report derive the cumulative figure. (Alternative: a small `nat_thresholds` config block if we
don't want to touch GradingScale, noted as an open question in §8.)

## 4. Computation

For a given Offering (cohort) and a NAT AssessmentType, for each subject's Assessment:

```
present  = scores where excused = false           (numeric marks)
fail     = present where score < 40
pass     = present where score >= 40              (cumulative)
mastery  = present where score >= 80
%x       = x / count(present)                     (0 when present = 0)
```

…computed for `gender = M`, `gender = F`, and All. This is a read-only aggregation, no schema
change. Implement as a service, e.g. `app/Domain/Nat/NatAnalysis.php`, returning a structure the
blade view and any Vue screen can both consume.

## 5. Output / PDF

Mirror the existing report path:

- Controller: extend `app/Http/Controllers/NewInterfaceControllers/ReportController.php` (it already
  does `PDF::loadView(...)->download('<Grade>-results.pdf')` for grade results).
- Views: add `resources/views/print/natScores.blade.php` and `natAnalysis.blade.php` alongside the
  existing `print/termReport.blade.php`.
- **Charts in dompdf:** dompdf does not run JavaScript, so the bar charts are rendered as **inline
  SVG** (or plain HTML/CSS `<div>` bars with percentage heights). The charts are simple grouped
  bars (fail/pass/mastery × Male/Female/All), no chart library needed, and it stays offline-safe.
  This matches the look of the spreadsheet graphs (grey = fail, light blue = pass, dark blue =
  mastery, horizontal gridlines).
- Optional later: an on-screen Vue view of the same analysis for teachers, before/instead of PDF.

## 6. Sex / gender mapping

`profiles.gender` is a free-ish char column. Before building, confirm the stored values
(`M`/`F`? `male`/`female`? possibly null). The analysis needs a deterministic map to
Male / Female and a rule for unknown/null (propose: count in All, show a separate "Unknown" line
only if any exist, so totals always reconcile).

## 7. Generalization path (not built now)

Gambia-first, but the seams are:

1. **Subjects per grade**, Grade 3 = {English, Maths, Integrated Studies}; Grade 5 = {English,
   Maths, Science, Social & Environmental Studies}. Today this is implicit in which Assessments
   exist. Later: a `nat_subject_set` config per (country, grade).
2. **Thresholds**, already per-school via GradingScale. A new country = new School + bands.
3. **Labels/branding**, report header text ("The Swallow…") from `School`/`SchoolConfig`.
4. **Max score / paper count**, `Assessment.max_score` already per-assessment.

Nothing above requires rework of the Gambia-first build; it's additive.

## 8. Open questions for Shane

1. **Thresholds home:** GradingScale bands (reuses existing, recommended) vs a dedicated
   `nat_thresholds` config? GradingScale is currently used for term-report letter grades, do NAT
   bands belong in the same table or should they be separate so they don't collide?
2. **`profiles.gender` values?** Need the actual stored values to map M/F. (§6)
3. **Entry granularity:** confirm NAT scores are entered through the normal assessment-entry UI
   per subject (the chosen option), or do you want a single NAT grid (all subjects at once) that
   creates the underlying Assessments behind the scenes?
4. **Term:** NAT has no real "term". Use a dedicated Term, or make `term_id` nullable for NAT
   assessments? (Check the assessments migration for nullability.)
5. **Who triggers the report** and at what level, per Offering (one grade/year), and is it
   teacher- or admin-only?

## 9. Proposed implementation steps (after sign-off)

1. Seeder: `National Assessment Test` AssessmentType + the five Subjects + Fail/Pass/Mastery
   GradingScale bands for the school.
2. `NatAnalysis` domain service (computation + tests against the known 2024/2025 numbers in
   `GAMBIA/NAT/` as fixtures, we already have verified expected values).
3. Blade views `natScores` + `natAnalysis` (+ SVG bar-chart partial).
4. `ReportController` methods + routes (`routes/web.php`) to render/download both PDFs.
5. (Optional) Vue screen for on-screen analysis.
6. Feature test: build an Offering with the 2024 Grade 5 data → assert PDF/structure matches the
   verified figures (max 319, mean 284, mastery English 29%, etc.).

---

### Appendix, verified reference data (for fixtures)

`GAMBIA/NAT/` contains the hand-built, **independently verified** analyses we can use as test
fixtures:

- **2024 Grade 5** (14 pupils, 4 subjects). Matches WAEC's printed summary box exactly:
  max 319, min 246, mean 283.93≈284, sample SD 20.49. Mastery: English 29%, Maths 21%,
  Science 0%, S.E.S. 0%.
- **2025 Grade 3** (22 pupils, 3 subjects). English mastery 68%, Maths 45%, Int. Std 32%
  (2 fails: 26 and 32). All pupil totals recompute to the printed totals.
