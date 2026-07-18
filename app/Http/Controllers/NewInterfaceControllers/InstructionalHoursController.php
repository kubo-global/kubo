<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\InstructionalHour;
use App\Models\Lesson;
use App\Models\Offering;
use App\Models\School;
use App\Models\Schoolyear;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The ministry "Data Collection on Instructional Hours": per class, per month, the
 * teacher logs Actual and Lost hours per day; Expected comes from the timetable.
 * Deliverables: the data-collection sheet and the Achieved-vs-Lost chart.
 */
class InstructionalHoursController extends Controller
{
    /** Class-list landing so Instructional hours can be a top-level menu item. */
    public function index(Request $request)
    {
        $schoolyears = Schoolyear::orderBy('start', 'desc')->get();
        $currentId = Schoolyear::current()?->id ?? $schoolyears->first()?->id;
        $selectedYear = $schoolyears->firstWhere('id', (int) $request->query('year')) ?? $schoolyears->firstWhere('id', $currentId);

        $offerings = $selectedYear
            ? Offering::tagSectionIndex(Offering::where('schoolyear_id', $selectedYear->id)->with('grade')->withCount('enrollments')->get())->sortBy('grade_id')->values()
            : collect();

        return view('pages.scorebook.instructional-hours-classes', [
            'schoolyears'   => $schoolyears,
            'selectedYear'  => $selectedYear,
            'currentYearId' => $currentId,
            'offerings'     => $offerings,
        ]);
    }

    public function show(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $month = $this->resolveMonth($request->query('month'));

        return view('pages.scorebook.instructional-hours', [
            'offering' => $offering,
            'month'    => $month,
            'teacher'  => $offering->principal->first(),
            'weeks'    => $this->buildMonth($offering, $month),
            'canEdit'  => auth()->user()->hasAnyRole(['headmaster', 'admin', 'teacher']),
        ]);
    }

    public function save(Request $request, Offering $offering)
    {
        $month = $this->resolveMonth($request->input('month'));

        foreach ((array) $request->input('rows', []) as $dateStr => $vals) {
            try {
                $date = Carbon::parse($dateStr)->toDateString();
            } catch (\Throwable $e) {
                continue;
            }
            $actual = ($vals['actual'] ?? '') === '' ? null : (float) $vals['actual'];
            $lost = ($vals['lost'] ?? '') === '' ? null : (float) $vals['lost'];
            $remarks = trim((string) ($vals['remarks'] ?? '')) ?: null;

            if ($actual === null && $lost === null && $remarks === null) {
                InstructionalHour::where('offering_id', $offering->id)->whereDate('date', $date)->delete();
                continue;
            }
            InstructionalHour::updateOrCreate(
                ['offering_id' => $offering->id, 'date' => $date],
                ['actual_hours' => $actual, 'lost_hours' => $lost, 'remarks' => $remarks, 'recorded_by' => auth()->id()],
            );
        }

        // Per-day saves (background request from the grid) get a light JSON reply
        // so the page doesn't reload; the full-form fallback still redirects.
        if ($request->boolean('ajax')) {
            return response()->json(['ok' => true]);
        }

        return redirect(route('instructional-hours.show', ['offering' => $offering, 'month' => $month->format('Y-m')]))
            ->with('success', 'Instructional hours saved for '.$month->format('F Y').'.');
    }

    public function pdf(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $month = $this->resolveMonth($request->query('month'));

        return PDF::loadView('print.instructional-hours', [
            'offering' => $offering,
            'school'   => School::first(),
            'month'    => $month,
            'teacher'  => $offering->principal->first(),
            'weeks'    => $this->buildMonth($offering, $month),
        ])->setPaper('a4', 'portrait')
            ->download('Instructional hours '.$offering->displayName().' '.$month->format('M Y').'.pdf');
    }

    public function chart(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $month = $this->resolveMonth($request->query('month'));
        $weeks = $this->buildMonth($offering, $month);

        // Per-week achieved vs lost, as a % of the week's expected hours (the ministry chart).
        $series = [];
        foreach ($weeks as $weekNum => $rows) {
            $achieved = round(collect($rows)->sum(fn ($r) => $r['actual'] ?? 0), 1);
            $lost = round(collect($rows)->sum(fn ($r) => $r['lost'] ?? 0), 1);
            $expected = round(collect($rows)->sum(fn ($r) => $r['expected'] ?? 0), 1);
            $series[] = [
                'week'        => $weekNum,
                'achieved'    => $achieved,
                'lost'        => $lost,
                'expected'    => $expected,
                'achievedPct' => $expected > 0 ? (int) round($achieved / $expected * 100) : 0,
                'lostPct'     => $expected > 0 ? (int) round($lost / $expected * 100) : 0,
            ];
        }
        $max = 100;

        return PDF::loadView('print.instructional-hours-chart', [
            'offering' => $offering,
            'school'   => School::first(),
            'month'    => $month,
            'series'   => $series,
            'max'      => $max,
        ])->setPaper('a4', 'portrait')
            ->download('Instructional hours chart '.$offering->displayName().' '.$month->format('M Y').'.pdf');
    }

    /**
     * Expected teaching hours per weekday (1-5). A per-school standard set in
     * Settings ("expected_instructional_hours", a weekday=>hours map) overrides the
     * timetable-derived total; when it isn't configured, Expected is summed from the
     * class timetable so existing schools are unaffected.
     */
    public static function expectedByWeekday(Offering $offering): array
    {
        $configured = json_decode((string) (School::first()?->config(\App\Models\SchoolConfig::EXPECTED_INSTRUCTIONAL_HOURS) ?? ''), true);
        if (is_array($configured) && array_filter($configured, fn ($v) => $v !== null && $v !== '')) {
            return [
                1 => round((float) ($configured[1] ?? 0), 2),
                2 => round((float) ($configured[2] ?? 0), 2),
                3 => round((float) ($configured[3] ?? 0), 2),
                4 => round((float) ($configured[4] ?? 0), 2),
                5 => round((float) ($configured[5] ?? 0), 2),
            ];
        }

        $exp = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
        foreach (Lesson::where('offering_id', $offering->id)->with('period')->get() as $lesson) {
            $p = $lesson->period;
            if (! $p || $p->is_break || ! $p->start_time || ! $p->end_time || ! isset($exp[$lesson->day])) {
                continue;
            }
            $dur = Carbon::parse($p->start_time)->diffInMinutes(Carbon::parse($p->end_time)) / 60;
            $exp[$lesson->day] += $dur * max(1, (int) ($lesson->length ?? 1));
        }

        return array_map(fn ($h) => round($h, 2), $exp);
    }

    /** Month grouped by week: each weekday with its expected / logged actual / lost / remarks. */
    public function buildMonth(Offering $offering, Carbon $month): array
    {
        $expected = self::expectedByWeekday($offering);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $logged = InstructionalHour::where('offering_id', $offering->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'];
        $weeks = [];
        $weekNum = 0;
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            if (! $d->isWeekday()) {
                continue;
            }
            // Group by Mon–Fri calendar week (new week each Monday), matching the
            // paper form — not Carbon's weekOfMonth, which splits weeks mid-run.
            if ($d->dayOfWeekIso === 1 || $weekNum === 0) {
                $weekNum++;
            }
            $rec = $logged[$d->toDateString()] ?? null;
            $weeks[$weekNum][] = [
                'date'       => $d->toDateString(),
                'date_short' => $d->format('d/m'),
                'day'        => $days[$d->dayOfWeekIso - 1] ?? '',
                'expected'   => $expected[$d->dayOfWeekIso] ?? 0.0,
                'actual'     => $rec?->actual_hours,
                'lost'       => $rec?->lost_hours,
                'remarks'    => $rec?->remarks,
            ];
        }

        return $weeks;
    }

    private function resolveMonth(?string $m): Carbon
    {
        try {
            return $m ? Carbon::parse($m.'-01')->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable $e) {
            return Carbon::now()->startOfMonth();
        }
    }
}
