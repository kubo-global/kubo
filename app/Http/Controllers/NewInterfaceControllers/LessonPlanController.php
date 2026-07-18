<?php

namespace App\Http\Controllers\NewInterfaceControllers;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\Offering;
use App\Models\Schoolyear;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LessonPlanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Plain teachers see only their own plans; coordinators/admins see all.
        $restrictToOwn = $user->hasRole('teacher') && !$user->hasAnyRole(['headmaster', 'admin', 'assistant_coordinator']);

        $query = LessonPlan::with(['teacher', 'offering.grade', 'subject'])
            ->when($restrictToOwn, fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('lesson_date');

        // Filters
        if ($request->filled('offering_id')) {
            $query->where('offering_id', $request->input('offering_id'));
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }
        if (!$restrictToOwn && $request->filled('teacher_id')) {
            $query->where('user_id', $request->input('teacher_id'));
        }
        match ($request->input('status')) {
            'unsigned'             => $query->whereNull('assistant_coordinator_remarks')->whereNull('coordinator_remarks'),
            'awaiting_coordinator' => $query->whereNull('coordinator_remarks'),
            'assistant_signed'     => $query->whereNotNull('assistant_coordinator_remarks'),
            'coordinator_signed'   => $query->whereNotNull('coordinator_remarks'),
            default                => null,
        };

        // Filter dropdown options, drawn from the plans this user can actually see
        // (role-scoped, before the filters) so no option ever yields zero rows.
        $scoped = LessonPlan::query()->when($restrictToOwn, fn ($q) => $q->where('user_id', $user->id));
        $offerings = Offering::with('grade')
            ->whereIn('id', (clone $scoped)->select('offering_id')->distinct())
            ->get()->sortBy(fn ($o) => $o->displayName())->values();
        $subjects = Subject::whereIn('id', (clone $scoped)->select('subject_id')->distinct())
            ->orderBy('name')->get();
        $teachers = $restrictToOwn ? collect() : \App\Models\User::whereIn('id', (clone $scoped)->select('user_id')->distinct())
            ->orderBy('first_name')->orderBy('last_name')->get();

        return view('pages.lesson-plans.index', [
            'plans'          => $query->paginate(25)->withQueryString(),
            'offerings'      => $offerings,
            'subjects'       => $subjects,
            'teachers'       => $teachers,
            'canFilterTeacher' => !$restrictToOwn,
            'filters'        => [
                'offering_id' => $request->input('offering_id'),
                'subject_id'  => $request->input('subject_id'),
                'teacher_id'  => $request->input('teacher_id'),
                'status'      => $request->input('status'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return view('pages.lesson-plans.form', [
            'plan' => new LessonPlan(['lesson_date' => now()->toDateString()]),
            'offerings' => $this->offeringsForTeacher($request->user()),
            'subjects' => Subject::orderBy('name')->get(),
            'topicsBySubject' => $this->topicsBySubject(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $data['user_id'] = $request->user()->id;

        $plan = LessonPlan::create($data);

        return redirect()->route('lesson-plans.show', $plan)
            ->with('message', 'Lesson plan created.');
    }

    public function show(LessonPlan $lessonPlan)
    {
        $lessonPlan->load(['teacher', 'offering.grade', 'subject']);

        return view('pages.lesson-plans.show', [
            'plan' => $lessonPlan,
        ]);
    }

    public function edit(Request $request, LessonPlan $lessonPlan)
    {
        $this->authorizeEdit($request->user(), $lessonPlan);

        return view('pages.lesson-plans.form', [
            'plan' => $lessonPlan,
            'offerings' => $this->offeringsForTeacher($request->user()),
            'subjects' => Subject::orderBy('name')->get(),
            'topicsBySubject' => $this->topicsBySubject(),
        ]);
    }

    public function update(Request $request, LessonPlan $lessonPlan)
    {
        $user = $request->user();

        // Quick-sign paths from the show page — only writes the relevant
        // remarks field, ignores everything else. Coordinator role is the
        // headmaster, so the "Coordinator Remarks" form posts here too;
        // headmaster has full edit rights via authorizeEdit, but if the
        // request only carries the remarks field we treat it as a quick
        // sign-off rather than wiping the rest of the plan to null.
        $isQuickCoordinatorSign = $user->hasAnyRole(['headmaster', 'admin'])
            && $request->has('coordinator_remarks')
            && !$request->has('topic');
        if ($isQuickCoordinatorSign) {
            $request->validate(['coordinator_remarks' => 'nullable|string']);
            $lessonPlan->update(['coordinator_remarks' => $request->input('coordinator_remarks')]);
            return redirect()->route('lesson-plans.show', $lessonPlan)->with('message', 'Coordinator remarks saved.');
        }

        if ($user->hasRole('assistant_coordinator') && !$user->hasAnyRole(['admin', 'headmaster']) && $lessonPlan->user_id !== $user->id) {
            $request->validate(['assistant_coordinator_remarks' => 'nullable|string']);
            $lessonPlan->update(['assistant_coordinator_remarks' => $request->input('assistant_coordinator_remarks')]);
            return redirect()->route('lesson-plans.show', $lessonPlan)->with('message', 'Assistant coordinator remarks saved.');
        }

        $this->authorizeEdit($user, $lessonPlan);

        $data = $this->validatePayload($request);
        $lessonPlan->update($data);

        return redirect()->route('lesson-plans.show', $lessonPlan)
            ->with('message', 'Lesson plan updated.');
    }

    /**
     * Explicit sign-off, separate from remarks. Toggling on stamps the time;
     * toggling off clears it. Gated per level: the coordinator (headmaster/admin)
     * signs the coordinator line; an assistant coordinator signs the assistant
     * line on plans they didn't author.
     */
    public function signOff(Request $request, LessonPlan $lessonPlan)
    {
        $user = $request->user();
        $level = $request->input('level');
        $on = $request->boolean('signed');

        if ($level === 'coordinator') {
            abort_unless($user->hasAnyRole(['headmaster', 'admin']), 403);
            $lessonPlan->coordinator_signed_at = $on ? now() : null;
        } elseif ($level === 'assistant') {
            abort_unless($user->hasRole('assistant_coordinator') && $lessonPlan->user_id !== $user->id, 403);
            $lessonPlan->assistant_coordinator_signed_at = $on ? now() : null;
        } else {
            abort(400);
        }

        $lessonPlan->save();

        // Return to wherever it was triggered — the show page or the index list.
        return redirect()->back()->with('message', $on ? 'Signed off.' : 'Sign-off removed.');
    }

    public function destroy(Request $request, LessonPlan $lessonPlan)
    {
        $this->authorizeEdit($request->user(), $lessonPlan);
        $lessonPlan->delete();

        return redirect()->route('lesson-plans.index')->with('message', 'Lesson plan deleted.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'offering_id' => 'required|exists:offerings,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_date' => 'required|date',
            'topic' => 'required|string|max:255',
            'curriculum_topic_id' => [
                'nullable',
                Rule::exists('topics', 'id')->where(fn ($q) => $q->where('subject_id', $request->input('subject_id'))),
            ],
            'content' => 'nullable|string',
            'objectives' => 'nullable|string',
            'resources' => 'nullable|string',
            'activities' => 'nullable|string',
            'assessment' => 'nullable|string',
            'conclusion' => 'nullable|string',
        ]);
    }

    /**
     * Map of subject_id => [{id, name}, ...] for the curriculum-topic
     * dropdown on the lesson-plan form. The select swaps options based on
     * the chosen subject on the client side.
     */
    private function topicsBySubject(): array
    {
        return Topic::orderBy('name')->get(['id', 'name', 'subject_id'])
            ->groupBy('subject_id')
            ->map(fn ($topics) => $topics->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values())
            ->toArray();
    }

    private function authorizeEdit($user, LessonPlan $plan): void
    {
        $isOwner = $plan->user_id === $user->id;
        $isAdmin = $user->hasAnyRole(['admin', 'headmaster']);

        if (!$isOwner && !$isAdmin) {
            abort(403, 'You can only edit your own lesson plans.');
        }
    }

    private function offeringsForTeacher($user)
    {
        $year = Schoolyear::current() ?? Schoolyear::latest();
        $query = Offering::with('grade')->when($year, fn ($q) => $q->where('schoolyear_id', $year->id));

        if ($user->hasRole('teacher') && !$user->hasAnyRole(['headmaster', 'admin'])) {
            $offeringIds = DB::table('teacher_offering')->where('user_id', $user->id)->pluck('offering_id');
            $query->whereIn('id', $offeringIds);
        }

        return $query->get();
    }
}
