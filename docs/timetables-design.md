# Class timetables

Status: shipped (v1).
Goal: let a school keep a weekly timetable per class and print it, reusing the existing
subject and teacher data.

## Why

- Schools wanted a weekly schedule per class (which subject, taught by whom, in each
  period) — both on screen and as a printable wall copy.

## Decisions

- **Periods are school-wide** (a `periods` table), configured once in Settings → Timetable:
  an ordered list of teaching slots and breaks, with optional clock times. Every class
  timetable shares this structure (the grid rows).
- **A lesson is one cell**: `lessons` rows tie `offering_id` + `day` (1–5) + `period_id` to a
  `subject_id` and optional `teacher_id`. Unique on (offering, day, period).
- **Edited in place**: the per-class grid is the edit surface (subject + teacher `<select>`
  per cell, one Save) for headmaster/admin/teacher; read-only otherwise. Reached from the
  scorebook class page via the mode switch (Tests & exams · NAT · **Timetable** · Positions).
- **Print**: a landscape A4 PDF (DomPDF), mirroring the report/NAT flow, with the school
  crest/name.

## How it works

- Subjects offered come from `subject_term_offering` (the same curriculum link the scorebook
  and term report read — *not* the abandoned `offering_subjects` table). Teachers come from
  `Offering::teachers()`.
- Saving upserts `lessons` per non-empty cell and deletes cleared ones.

## Files

- Migration `…_create_periods_and_lessons.php`; models `Period`, `Lesson`
- `app/Http/Controllers/NewInterfaceControllers/TimetableController.php` (`show`, `update`, `print`)
- `resources/views/pages/scorebook/timetable.blade.php`, `print/timetable.blade.php`
- Settings period CRUD (`SettingsController::storePeriod`/`destroyPeriod`)
- `DemoSeeder` seeds a default period set + a filled timetable for the current year
- Tests: `tests/Feature/TimetableTest.php`

## Future

- A per-teacher schedule view (the data already supports it).
