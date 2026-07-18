# Report book, positions & the term total

Status: shipped.
Goal: support schools whose report is a **reusable annual booklet** (one page per
child, filled month-by-month) instead of a fresh one-page report each term — and make
the **class position** that drives those reports a first-class, reusable calculation.

## Why

- Some schools (e.g. Bakoteh Proper) keep a pre-printed **Report Book**: a single grid
  per child for the whole year, rows = months/exams, columns = subjects. You can't feed a
  bound booklet through a printer, and you can't reprint one page per term onto it. So
  KUBO's one-page-per-term PDF didn't fit their workflow.
- The booklet's **Total** ranks pupils (Position), and some subjects (Arts, P.E.) are not
  meant to count toward that total.

## Decisions (agreed with Shane)

- **Per-school report type** (`school_configs.report_type`: `term` | `book`), chosen in
  Settings. Default `term` (the existing one-page report) so nothing changes for current users.
- The Report Book is **term-based**, not monthly: KUBO holds term data, so monthly rows are
  printed **blank for handwriting** and each term's **Exam row** is filled from KUBO. Letter
  marks (conduct, etc.) are handwritten too — those columns are layout-only.
- **Empty / filled**: the same PDF prints blank (a template to fill by hand) or filled with
  what KUBO has. Available per pupil and as a **whole-class batch** (one page each).
- **Configurable grade key**: the grade legend is the school's `grading_scales` (purpose
  null), editable in Settings, so each school keeps its own scheme (A–F, or 1/4/5/6/8/9…).
- **One source of truth for position**: `PositionService` ranks a class for a term; the
  Positions screen and the report book's Position column both use it.

## How it works

- **Total exclusion** lives in `NewTermReportRepository::getTermResults()`: subjects still
  render, but only those with `subjects.counts_toward_total` are summed into `total`/`average`.
  It reads the **raw column** and defaults to "counts" when absent, so a not-yet-migrated
  database (or the production backup used by the golden-master regression) keeps its totals
  instead of zeroing out.
- **Positions**: `PositionService::rankedReports()` builds the class's term reports once,
  sorts by total, assigns positions (ties share a place), and returns the full report per
  pupil — so the report book reuses the subject cells without recomputing per pupil.
- **Report Book**: `ReportController::reportBook` (single) and `classReportBook` (batch)
  assemble month/Exam rows from `reportBookContext()` and render `print.reportBook` /
  `print.reportBookBatch` (A4 landscape, DomPDF). Month rows come from `TERM_MONTHS`.

## Files

- `app/Domain/Reporting/Services/PositionService.php`
- `app/Http/Controllers/NewInterfaceControllers/ReportController.php` (`reportBook`,
  `classReportBook`, `reportBookContext`, `assembleBook`)
- `app/Http/Controllers/NewInterfaceControllers/ScorebookController.php` (`positions`)
- `resources/views/print/reportBook.blade.php`, `reportBookBatch.blade.php`,
  `_report-book-page.blade.php`, `_report-book-styles.blade.php`
- `resources/views/pages/scorebook/positions.blade.php`
- Settings: report-type selector + grade-key editor + `counts_toward_total` toggle
  (`SettingsController`, `pages/settings/index.blade.php`)
- Tests: `tests/Feature/PositionsTest.php`, `tests/Feature/ReportBookTest.php`

## Known limitations / future

- Monthly rows are blank by design (no monthly data model). True monthly entry would be a
  larger change.
- `TERM_MONTHS` is fixed (Oct/Nov, Jan/Feb, Apr/May/June). Per-school month structures
  would need to be configurable.
