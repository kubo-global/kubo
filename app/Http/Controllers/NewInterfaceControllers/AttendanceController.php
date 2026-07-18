<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Offering;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Daily attendance register for one class: mark each pupil present/absent/late/
 * excused for a day. Builds on the existing Attendance model + enums. Per-pupil
 * year totals roll up here (and into the report book's Time present/absent).
 */
class AttendanceController extends Controller
{
    public function show(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear');
        $date = $this->resolveDate($request->query('date'));

        return view('pages.scorebook.attendance', [
            'offering'  => $offering,
            'date'      => $date,
            'students'  => $this->students($offering),
            'marks'     => Attendance::where('offering_id', $offering->id)->whereDate('date', $date)->get()->keyBy('user_id'),
            'totals'    => self::yearTotals($offering->id),
            'statuses'  => AttendanceStatus::cases(),
            'canEdit'   => auth()->user()->hasAnyRole(['headmaster', 'admin', 'teacher']),
        ]);
    }

    public function save(Request $request, Offering $offering)
    {
        $date = $this->resolveDate($request->input('date'));
        $schoolId = $offering->grade->school_id ?? optional(School::first())->id;
        $valid = array_column(AttendanceStatus::cases(), 'value');

        foreach ((array) $request->input('status', []) as $userId => $status) {
            if (! in_array($status, $valid, true)) {
                continue;
            }
            // Match by whereDate so the date-cast column compares cleanly across
            // engines (SQLite keeps the time component, MySQL truncates).
            $existing = Attendance::where('offering_id', $offering->id)
                ->where('user_id', (int) $userId)->whereDate('date', $date)->first();
            $data = ['status' => $status, 'type' => AttendanceType::Student, 'recorded_by' => auth()->id(), 'school_id' => $schoolId];
            if ($existing) {
                $existing->update($data);
            } else {
                Attendance::create($data + ['offering_id' => $offering->id, 'user_id' => (int) $userId, 'date' => $date]);
            }
        }

        return redirect(route('scorebook.attendance', ['offering' => $offering, 'date' => $date]))
            ->with('success', 'Attendance saved for '.Carbon::parse($date)->format('D, d M Y').'.');
    }

    /** The ministry "Students Daily Attendance" sheet: boys/girls present/absent per day, by week, for a month. */
    public function monthlySummary(Request $request, Offering $offering)
    {
        $offering->load('grade', 'schoolyear', 'principal');
        $school = School::first();
        $month = $this->resolveMonth($request->query('month'));

        return PDF::loadView('print.attendance-summary', [
            'offering' => $offering,
            'school'   => $school,
            'month'    => $month,
            'teacher'  => $offering->principal->first(),
            'weeks'    => self::monthTally($offering->id, $month),
        ])->setPaper('a4', 'portrait')
            ->download('Attendance '.$offering->displayName().' '.$month->format('M Y').'.pdf');
    }

    /**
     * Boys/girls present/absent per weekday, grouped by week-of-month.
     * @return array<int, array<int, array<string,mixed>>>  week => rows[]
     */
    public static function monthTally(int $offeringId, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Per-date tallies from the register. Present = anything that isn't Absent.
        $tally = [];
        $records = Attendance::where('offering_id', $offeringId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('user.profile')->get();
        foreach ($records as $r) {
            $date = Carbon::parse($r->date)->toDateString();
            $tally[$date] ??= ['bp' => 0, 'gp' => 0, 'ba' => 0, 'ga' => 0];
            $absent = $r->status === AttendanceStatus::Absent;
            $sex = $r->user?->profile?->gender; // canonical 'M'/'F'
            if ($sex === 'M') {
                $tally[$date][$absent ? 'ba' : 'bp']++;
            } elseif ($sex === 'F') {
                $tally[$date][$absent ? 'ga' : 'gp']++;
            }
        }

        $days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'];
        $weeks = [];
        $weekNum = 0;
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            if (! $d->isWeekday()) {
                continue;
            }
            // Group by Mon–Fri calendar week like the paper form: a new week starts
            // each Monday (a month starting mid-week gets a short first week), rather
            // than Carbon's weekOfMonth, which blocks days 1–7/8–14 and splits weeks.
            if ($d->dayOfWeekIso === 1 || $weekNum === 0) {
                $weekNum++;
            }
            $t = $tally[$d->toDateString()] ?? ['bp' => 0, 'gp' => 0, 'ba' => 0, 'ga' => 0];
            $weeks[$weekNum][] = [
                'date' => $d->format('d/m'),
                'day'  => $days[$d->dayOfWeekIso - 1] ?? '',
                'bp' => $t['bp'], 'gp' => $t['gp'], 'tp' => $t['bp'] + $t['gp'],
                'ba' => $t['ba'], 'ga' => $t['ga'], 'ta' => $t['ba'] + $t['ga'],
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

    /** Per-pupil present/absent day counts for a class (whole school year). */
    public static function yearTotals(int $offeringId)
    {
        return Attendance::where('offering_id', $offeringId)
            ->selectRaw("user_id,
                sum(case when status = 'absent' then 1 else 0 end) as absent,
                sum(case when status <> 'absent' then 1 else 0 end) as present")
            ->groupBy('user_id')->get()->keyBy('user_id');
    }

    private function resolveDate(?string $d): string
    {
        try {
            return $d ? Carbon::parse($d)->toDateString() : Carbon::now()->toDateString();
        } catch (\Throwable $e) {
            return Carbon::now()->toDateString();
        }
    }

    private function students(Offering $offering)
    {
        return $offering->enrollments()
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.archived', false)
            ->orderBy('users.last_name')->orderBy('users.first_name')
            ->with('student')->select('enrollments.*')->get()
            ->map(fn ($e) => $e->student)->filter()->values();
    }
}
