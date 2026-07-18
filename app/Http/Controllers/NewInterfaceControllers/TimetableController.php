<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Offering;
use App\Models\Period;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Subject;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Weekly class timetable: a days × periods grid for one offering. A lesson holds
 * a subject (+ optional teacher) and a `length` — how many consecutive periods it
 * spans (a double/block period), rendered as a merged cell. Periods are
 * configured school-wide in Settings. Mirrors the scorebook class/NAT pages.
 */
class TimetableController extends Controller
{
    /** Class picker: choose a class to view/edit its weekly timetable. */
    public function index(Request $request)
    {
        $schoolyears = Schoolyear::orderBy('start', 'desc')->get();
        $currentId = Schoolyear::current()?->id ?? $schoolyears->first()?->id;
        $selectedYear = $schoolyears->firstWhere('id', (int) $request->query('year')) ?? $schoolyears->firstWhere('id', $currentId);

        $offerings = $selectedYear
            ? Offering::tagSectionIndex(Offering::where('schoolyear_id', $selectedYear->id)->with('grade')->withCount('enrollments')->get())->sortBy('grade_id')->values()
            : collect();

        return view('pages.scorebook.timetable-classes', [
            'schoolyears'   => $schoolyears,
            'selectedYear'  => $selectedYear,
            'currentYearId' => $currentId,
            'offerings'     => $offerings,
        ]);
    }

    public function show(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $periods = Period::ordered($this->schoolId($offering))->get()->values();
        [$grid, $maxLen] = $this->buildGrid($periods, $this->lessonGrid($offering));
        // Timetabling is a coordination/admin task: headmaster (coordinator),
        // admin (administration) and the assistant coordinator can edit; teachers view.
        $canEdit = auth()->user()->hasAnyRole(['headmaster', 'admin', 'assistant_coordinator']);

        // Every class in the same year, for the top-of-page class switcher.
        $siblings = Offering::tagSectionIndex(
            Offering::where('schoolyear_id', $offering->schoolyear_id)->with('grade')->get()
        )->sortBy([['grade_id', 'asc'], ['name', 'asc']])->values();

        return view('pages.scorebook.timetable', [
            'offering' => $offering,
            'siblings' => $siblings,
            'periods'  => $periods,
            'subjects' => $this->offeringSubjects($offering),
            'teachers' => $offering->teachers()->orderBy('first_name')->get(),
            'grid'     => $grid,
            'maxLen'   => $maxLen,
            'days'     => Lesson::DAYS,
            'canEdit'  => $canEdit,
            // Read-only by default; switch to the editable grid only on ?edit=1.
            'editing'  => $canEdit && $request->boolean('edit'),
        ]);
    }

    public function update(Request $request, Offering $offering)
    {
        // Only the coordination/admin roles may save a timetable (see show()).
        abort_unless($request->user()->hasAnyRole(['headmaster', 'admin', 'assistant_coordinator']), 403);

        $periods = Period::ordered($this->schoolId($offering))->get()->values();
        $maxLen = $this->buildGrid($periods, collect())[1];
        $cells = (array) $request->input('cells', []);

        DB::transaction(function () use ($offering, $periods, $maxLen, $cells) {
            Lesson::where('offering_id', $offering->id)->delete();
            foreach (array_keys(Lesson::DAYS) as $day) {
                $coverUntil = -1;
                foreach ($periods as $i => $p) {
                    if ($p->is_break || $i <= $coverUntil) {
                        continue; // breaks hold nothing; covered cells belong to a block above
                    }
                    $subjectId = $cells[$p->id][$day]['subject_id'] ?? null;
                    if (! $subjectId) {
                        continue;
                    }
                    $length = max(1, min((int) ($cells[$p->id][$day]['length'] ?? 1), $maxLen[$p->id] ?? 1));
                    Lesson::create([
                        'offering_id' => $offering->id, 'period_id' => $p->id, 'day' => (int) $day,
                        'length' => $length, 'subject_id' => (int) $subjectId,
                        'teacher_id' => ($cells[$p->id][$day]['teacher_id'] ?? null) ?: null,
                    ]);
                    $coverUntil = $i + $length - 1;
                }
            }
        });

        return redirect(route('timetable.show', $offering))->with('success', 'Timetable saved.');
    }

    public function print(Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $school = School::first();
        $periods = Period::ordered($this->schoolId($offering))->get()->values();
        [$grid] = $this->buildGrid($periods, $this->lessonGrid($offering, withRelations: true));

        $pdf = PDF::loadView('print.timetable', [
            'offering' => $offering,
            'periods'  => $periods,
            'grid'     => $grid,
            'days'     => Lesson::DAYS,
            'school'   => $school,
            'logo'     => ($school?->logo_path && file_exists(public_path($school->logo_path))) ? public_path($school->logo_path) : null,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Timetable '.$offering->displayName().'.pdf');
    }

    /**
     * Resolve the render grid and per-period max block length.
     * @return array{0: array<int,array<int,array{kind:string,rowspan:int,lesson:?Lesson}>>, 1: array<int,int>}
     */
    private function buildGrid($periods, $lessons): array
    {
        // max length = teaching periods remaining in this period's run (no break between).
        $maxLen = [];
        foreach ($periods as $i => $p) {
            if ($p->is_break) {
                continue;
            }
            $n = 0;
            for ($j = $i; $j < $periods->count() && ! $periods[$j]->is_break; $j++) {
                $n++;
            }
            $maxLen[$p->id] = $n;
        }

        $grid = [];
        foreach (array_keys(Lesson::DAYS) as $day) {
            $coverUntil = -1;
            foreach ($periods as $i => $p) {
                if ($p->is_break) {
                    $grid[$p->id][$day] = ['kind' => 'break', 'rowspan' => 1, 'lesson' => null];
                    continue;
                }
                if ($i <= $coverUntil) {
                    $grid[$p->id][$day] = ['kind' => 'covered', 'rowspan' => 1, 'lesson' => null];
                    continue;
                }
                $lesson = $lessons[$day.'-'.$p->id] ?? null;
                $length = $lesson ? max(1, min($lesson->length ?: 1, $maxLen[$p->id] ?? 1)) : 1;
                if ($lesson) {
                    $coverUntil = $i + $length - 1;
                }
                $grid[$p->id][$day] = ['kind' => 'cell', 'rowspan' => $length, 'lesson' => $lesson];
            }
        }

        return [$grid, $maxLen];
    }

    private function schoolId(Offering $offering): ?int
    {
        return $offering->grade->school_id ?? optional(School::first())->id;
    }

    /** Distinct subjects taught in this class across all its terms, for the cell dropdowns. */
    private function offeringSubjects(Offering $offering)
    {
        $ids = DB::table('subject_term_offering')
            ->where('offering_id', $offering->id)->distinct()->pluck('subject_id');

        return Subject::whereIn('id', $ids)->orderBy('name')->get();
    }

    /** Lessons keyed "day-periodId" for O(1) cell lookup in the grid. */
    private function lessonGrid(Offering $offering, bool $withRelations = false)
    {
        return Lesson::where('offering_id', $offering->id)
            ->when($withRelations, fn ($q) => $q->with('subject', 'teacher'))
            ->get()
            ->keyBy(fn ($l) => $l->day.'-'.$l->period_id);
    }
}
