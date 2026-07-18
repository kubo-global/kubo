<x-page title="School Settings" :wrap="true">
<div
  x-data="{
    tab: window.location.hash && ['#school','#academic','#classes','#schoolyear','#grading','#timetable','#modules','#permissions'].includes(window.location.hash)
      ? window.location.hash : '#academic',
  }"
  x-init="$watch('tab', val => history.replaceState(null, '', val))">

  {{-- Tab nav --}}
  <div class="border-b border-gray-200 mb-8">
    <nav class="-mb-px flex space-x-8" aria-label="Settings sections" role="tablist">
      @php
        $tabs = [
          '#school' => 'School',
          '#academic' => 'Academic structure',
          '#classes' => 'Classes & teachers',
        ];
        // The once-a-year rollover lives here (not the main nav) for those who run it.
        if (auth()->user()->can('manage rollover')) {
          $tabs['#schoolyear'] = 'School year';
        }
        $tabs['#grading'] = 'Grading';
        $tabs['#timetable'] = 'Timetable';
        $tabs['#modules'] = 'Modules';
        // The Permissions matrix is the access-control surface — only
        // shown to users who can actually edit it.
        if (auth()->user()->can('manage permissions')) {
          $tabs['#permissions'] = 'Permissions';
        }
      @endphp
      @foreach ($tabs as $hash => $label)
        <a href="{{ $hash }}" x-on:click.prevent="tab = '{{ $hash }}'"
          role="tab" :aria-selected="tab === '{{ $hash }}'"
          :class="tab === '{{ $hash }}'
            ? 'border-gray-900 text-gray-900'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
          class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
          {{ $label }}
        </a>
      @endforeach
    </nav>
  </div>

  {{-- Flash + validation errors are rendered by <x-page>. --}}

  {{-- =========================================================== --}}
  {{-- School: branding (logo used on report headers)              --}}
  {{-- =========================================================== --}}
  <div x-show="tab === '#school'" x-cloak>
    <section class="mb-10">
      <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-1">School logo</h2>
      <p class="text-sm text-gray-500 mb-4">Shown on the report headers (National Assessment Test and term reports). PNG, JPG or SVG, up to 2&nbsp;MB.</p>

      <div class="flex items-center gap-6">
        <div class="flex items-center justify-center w-28 h-28 border border-gray-200 rounded-lg bg-gray-50 shrink-0">
          @if ($school?->logo_path && file_exists(public_path($school->logo_path)))
            <img src="{{ asset($school->logo_path) }}" alt="School logo" class="object-contain max-w-24 max-h-24">
          @else
            <span class="text-xs text-gray-500">No logo set</span>
          @endif
        </div>

        <form method="POST" action="{{ route('settings.update-logo') }}" enctype="multipart/form-data" class="flex items-center gap-3">
          @csrf
          <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml" required
            class="text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-gray-800 file:text-white hover:file:bg-gray-900">
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Upload</button>
        </form>
      </div>
    </section>
  </div>

  {{-- =========================================================== --}}
  {{-- School year: the once-a-year rollover (not a nav item)      --}}
  {{-- =========================================================== --}}
  @can('manage rollover')
  <div x-show="tab === '#schoolyear'" x-cloak>
    <section class="mb-10">
      <h2 class="mb-1 text-sm font-semibold tracking-wider text-gray-500 uppercase">School year</h2>
      <p class="mb-4 text-sm text-gray-500">Start the next school year once a year: KUBO creates it, copies the class &amp; teacher setup forward, and walks you through promoting students.</p>
      @php $cur = $schoolyears->first(); @endphp
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-white border border-gray-200 rounded-lg">
        <div>
          <p class="text-sm font-medium text-gray-900">Current school year: {{ $cur->name ?? '—' }}</p>
          @if($cur)
            <p class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($cur->start)->format('d M Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($cur->end)->format('d M Y') }}</p>
          @endif
        </div>
        <a href="{{ route('rollover.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-md bg-indigo-600 hover:bg-indigo-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Start a new school year
        </a>
      </div>
    </section>
  </div>
  @endcan

  {{-- =========================================================== --}}
  {{-- Academic structure: grades + subjects                       --}}
  {{-- =========================================================== --}}
  <div x-show="tab === '#academic'" x-cloak>

    <section class="mb-10">
      <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-1">Grades</h2>
      <p class="mb-3 text-xs text-gray-500">Pick a base colour for each grade (used on the cards and lists). Classes within a grade get automatic shades of it. Leave a grade on <b>A</b> to auto-assign.</p>
      <div class="mb-4 space-y-2">
        @foreach($grades as $grade)
        @php
          $abbr = collect(explode(' ', $grade->name))->map(fn ($w) => ctype_digit($w) ? $w : strtoupper(substr($w, 0, 1)))->join('');
        @endphp
        <div class="p-3 bg-white border border-gray-200 rounded-lg">
          <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3">
              <span style="{{ $grade->colorStyle() }}" class="inline-flex items-center justify-center text-xs font-bold rounded-md w-8 h-8">{{ $abbr }}</span>
              <span class="text-sm font-medium text-gray-900">{{ $grade->name }}</span>
            </div>
            <div class="flex items-center gap-3">
              {{-- Student login toggle: uncheck for grades too young to sign in themselves (e.g. Nursery). --}}
              <form method="POST" action="{{ route('settings.grade-login', $grade) }}" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="enabled" value="0">
                <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer select-none" title="Show this grade on the student login screen">
                  <input type="checkbox" name="enabled" value="1" @checked($grade->student_login_enabled)
                    onchange="this.form.submit()"
                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                  Can log in
                </label>
              </form>
              <form method="POST" action="{{ route('settings.destroy-grade', $grade) }}" onsubmit="return confirm('Delete {{ $grade->name }}?')">
                @csrf @method('DELETE')
                <button type="submit" class="px-2 py-1 text-xs text-red-600 hover:text-red-700">Remove</button>
              </form>
            </div>
          </div>
          {{-- Swatches wrap into a grid with finger-sized targets (poor on mobile as a single tight row). --}}
          <div class="flex flex-wrap gap-2">
            @foreach (\App\Models\Grade::COLORS as $key => $c)
              <form method="POST" action="{{ route('settings.grade-color', $grade) }}">
                @csrf
                <input type="hidden" name="color" value="{{ $key }}">
                <button type="submit" title="{{ ucfirst($key) }}" style="background-color: {{ $c[2] }};"
                  class="block w-8 h-8 transition rounded-full ring-offset-1 {{ $grade->color === $key ? 'ring-2 ring-gray-800' : 'ring-1 ring-gray-200 hover:ring-gray-400' }}"></button>
              </form>
            @endforeach
            <form method="POST" action="{{ route('settings.grade-color', $grade) }}">
              @csrf
              <input type="hidden" name="color" value="">
              <button type="submit" title="Automatic (default)"
                class="flex items-center justify-center w-8 h-8 text-xs font-semibold text-gray-500 bg-white border border-gray-300 rounded-full ring-offset-1 hover:border-gray-400 {{ !$grade->color ? 'ring-2 ring-gray-800' : '' }}">A</button>
            </form>
          </div>
        </div>
        @endforeach
      </div>
      <form method="POST" action="{{ route('settings.store-grade') }}" class="flex gap-2">
        @csrf
        <input type="text" name="name" placeholder="New grade name" required
          class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Add</button>
      </form>
    </section>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3">Curriculum gating</h2>
      <form method="POST" action="{{ route('settings.update-gate-by-lesson-plan') }}"
        class="p-4 bg-white border border-gray-200 rounded-lg">
        @csrf
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="enabled" value="1" @checked($gateByLessonPlan)
            onchange="this.form.submit()"
            class="mt-1 h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
          <span class="text-sm">
            <span class="font-medium text-gray-900">Only show exercises for topics covered in a lesson plan</span>
            <span class="block text-xs text-gray-500 mt-0.5">
              When on, a student only sees practice content whose curriculum topic has been linked to a past lesson plan for their class. Off (default): all approved exercises are available.
            </span>
          </span>
        </label>
      </form>
    </section>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-1">Subjects</h2>
      <p class="mb-3 text-xs text-gray-500"><b>Mark added</b> subjects have their scores summed into the term total and ranking. <b>Graded</b> subjects show a letter grade only and are not added to the total (e.g. P.E., Art). Untick to make a subject Graded. This is the school-wide default; it can be overridden per class.</p>
      <div class="space-y-2 mb-4">
        @foreach($subjects as $subject)
        <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
          <span class="text-sm font-medium text-gray-900">{{ $subject->name }}</span>
          <div class="flex items-center gap-4">
            <form method="POST" action="{{ route('settings.toggle-subject-total', $subject) }}">
              @csrf
              <label class="flex items-center gap-1.5 text-xs text-gray-600 select-none cursor-pointer">
                <input type="checkbox" name="counts" value="1" onchange="this.form.submit()" @checked($subject->counts_toward_total)
                  class="h-4 w-4 border-gray-300 rounded text-gray-900 focus:ring-gray-900">
                Mark added
              </label>
            </form>
            <form method="POST" action="{{ route('settings.destroy-subject', $subject) }}" onsubmit="return confirm('Delete {{ $subject->name }}?')">
              @csrf @method('DELETE')
              <button type="submit" class="text-xs text-red-600 hover:text-red-600 px-2 py-1">Remove</button>
            </form>
          </div>
        </div>
        @endforeach
      </div>
      <form method="POST" action="{{ route('settings.store-subject') }}" class="flex gap-2">
        @csrf
        <input type="text" name="name" placeholder="New subject name" required
          class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Add</button>
      </form>
    </section>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-1">Expected instructional hours</h2>
      <p class="mb-3 text-xs text-gray-500">The official teaching hours per day, used as the <b>Expected</b> column on the instructional-hours sheet. Enter decimal hours (5.25 = 5h15, 3.5 = 3h30). Leave all blank to derive Expected from each class's timetable instead.</p>
      <form method="POST" action="{{ route('settings.instructional-hours') }}" class="flex flex-wrap items-end gap-3">
        @csrf
        @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $i => $dayName)
          @php $d = $i + 1; @endphp
          <div class="space-y-1">
            <label for="hours-{{ $d }}" class="block text-xs font-medium text-gray-500">{{ $dayName }}</label>
            <input type="number" step="0.25" min="0" max="24" id="hours-{{ $d }}" name="hours[{{ $d }}]"
              value="{{ $expectedHours[$d] ?? '' }}" placeholder="—"
              class="w-24 px-2 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
          </div>
        @endforeach
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Save</button>
      </form>
    </section>

    <section class="mb-10">
      <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-1">Staff statuses</h2>
      <p class="mb-3 text-xs text-gray-500">Employment-status codes shown on the staff list and picked per staff member (e.g. DHMC, HMA, SMC, QT, SMB, ECD).</p>
      <div class="flex flex-wrap gap-2 mb-4">
        @forelse ($staffStatuses as $status)
          <span class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-white border border-gray-200 rounded-lg">
            <span class="font-semibold text-gray-900">{{ $status->label }}</span>
            @if ($status->description)<span class="text-xs text-gray-500">{{ $status->description }}</span>@endif
            <form method="POST" action="{{ route('settings.destroy-staff-status', $status) }}" onsubmit="return confirm('Remove {{ $status->label }}?')" class="inline">
              @csrf @method('DELETE')
              <button type="submit" class="text-xs text-red-600 hover:text-red-700" aria-label="Remove {{ $status->label }}">&times;</button>
            </form>
          </span>
        @empty
          <p class="text-sm text-gray-500 italic">No statuses yet — add the codes your school uses below.</p>
        @endforelse
      </div>
      <form method="POST" action="{{ route('settings.store-staff-status') }}" class="flex flex-wrap gap-2">
        @csrf
        <input type="text" name="label" placeholder="Code (e.g. DHMC)" required maxlength="20"
          class="w-40 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        <input type="text" name="description" placeholder="Description (optional)"
          class="flex-1 min-w-48 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Add</button>
      </form>
    </section>

  </div>

  {{-- =========================================================== --}}
  {{-- Classes & teachers: offerings, teacher assignments,         --}}
  {{-- subject-per-term mapping                                    --}}
  {{-- =========================================================== --}}
  <div x-show="tab === '#classes'" x-cloak>

    @if($schoolyear)
      <section class="mb-10" x-data="{ adding: false }">
        <div class="flex items-baseline justify-between mb-1">
          <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Classes — {{ $schoolyear->name }}</h2>
          <button x-show="!adding" type="button" @click="adding = true"
            class="text-xs font-medium text-indigo-700 hover:text-indigo-900">+ Add class</button>
        </div>
        <p class="text-xs text-gray-500 mb-3">Classes are created automatically during school year rollover. Add one here when a grade has more than one class (e.g. Grade 1 A and B).</p>

        <form x-show="adding" x-cloak method="POST" action="{{ route('settings.add-offering') }}"
          class="mb-3 p-3 flex flex-wrap items-center gap-2 bg-gray-50 border border-gray-200 rounded-md">
          @csrf
          <label class="text-xs text-gray-500">Grade:</label>
          <select name="grade_id" required class="px-2 py-1 text-xs border border-gray-300 rounded-md">
            <option value="">Pick a grade…</option>
            @foreach ($grades as $grade)
              <option value="{{ $grade->id }}">{{ $grade->name }}</option>
            @endforeach
          </select>
          <label class="text-xs text-gray-500">Label:</label>
          <input type="text" name="name" required placeholder="A, B, …" maxlength="50"
            class="px-2 py-1 text-xs border border-gray-300 rounded-md w-24">
          <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Add</button>
          <button type="button" @click="adding = false" class="text-xs text-gray-500 hover:text-gray-700">cancel</button>
        </form>

        @php
          $teachers = \App\Models\User::role('teacher')->where('archived', false)->orderBy('first_name')->get();
        @endphp

        <div class="space-y-3">
          @foreach($offerings as $offering)
          <div class="p-4 bg-white border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between mb-2" x-data="{ renaming: false }">
              <div class="flex items-center gap-2">
                <span x-show="!renaming" class="text-sm font-semibold text-gray-900">
                  {{ $offering->grade->name ?? 'Unknown' }}
                  @if ($offering->name)
                    <span class="text-gray-500 font-normal">— {{ $offering->name }}</span>
                  @endif
                </span>
                <button x-show="!renaming" type="button" @click="renaming = true"
                  class="text-xs text-gray-500 hover:text-indigo-700">rename</button>
                <form x-show="renaming" x-cloak method="POST" action="{{ route('settings.rename-offering', $offering) }}" class="flex items-center gap-2">
                  @csrf
                  <span class="text-sm font-semibold text-gray-700">{{ $offering->grade->name ?? 'Unknown' }} —</span>
                  <input type="text" name="name" value="{{ $offering->name }}" placeholder="A, B, …" maxlength="50"
                    class="px-2 py-1 text-xs border border-gray-300 rounded-md">
                  <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Save</button>
                  <button type="button" @click="renaming = false" class="text-xs text-gray-500 hover:text-gray-700">cancel</button>
                </form>
              </div>
              <div class="flex items-center gap-3 text-xs text-gray-500">
                <span>{{ $offering->students()->count() }} students</span>
                @if ($offering->students()->count() === 0 && $offering->teachers()->count() === 0)
                <form method="POST" action="{{ route('settings.delete-offering', $offering) }}" class="inline"
                  onsubmit="return confirm('Delete this empty class?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="hover:text-red-500">delete</button>
                </form>
                @endif
              </div>
            </div>

            @php $principalId = $offering->teachers->isNotEmpty()
              ? optional($offering->teachers->first(fn ($t) => (bool) ($t->pivot->principal ?? false)))->id
              : null;
            @endphp

            <p class="text-xs text-gray-500 mb-2">Class teachers</p>
            <div class="pl-2 border-l-2 border-gray-100 mb-4">
              @if($offering->teachers->isNotEmpty())
              <div class="flex flex-wrap gap-2 mb-2">
                @foreach($offering->teachers as $teacher)
                @php $isPrincipal = $teacher->id === $principalId; @endphp
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs {{ $isPrincipal ? 'bg-indigo-50 text-indigo-800 border border-indigo-200' : 'bg-gray-100 text-gray-700' }}">
                  @if($isPrincipal)
                    <span class="text-indigo-500" title="Class principal">&#9733;</span>
                  @endif
                  {{ $teacher->first_name }} {{ $teacher->last_name }}
                  <form method="POST" action="{{ route('settings.remove-teacher') }}" class="inline">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $teacher->id }}">
                    <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                    <button type="submit" class="text-gray-500 hover:text-red-500 ml-1" title="Remove">&times;</button>
                  </form>
                </span>
                @endforeach
              </div>

              @php $principalName = $principalId ? optional($offering->teachers->firstWhere('id', $principalId))->first_name . ' ' . optional($offering->teachers->firstWhere('id', $principalId))->last_name : null; @endphp
              <div x-data="{ editing: false }" class="mb-2">
                <div x-show="!editing" class="flex items-center gap-2 text-xs">
                  <span class="text-gray-500">Principal:</span>
                  <span class="text-gray-800">{{ $principalName ?: 'none' }}</span>
                  <button type="button" @click="editing = true" class="text-indigo-700 hover:text-indigo-900">edit</button>
                </div>
                <form x-show="editing" x-cloak method="POST" action="{{ route('settings.set-principal') }}" class="flex items-center gap-2">
                  @csrf
                  <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                  <label class="text-xs text-gray-500 whitespace-nowrap">Principal:</label>
                  <select name="user_id"
                    class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                    <option value="">— none —</option>
                    @foreach($offering->teachers->where('archived', false) as $teacher)
                    <option value="{{ $teacher->id }}" @selected($teacher->id === $principalId)>
                      {{ $teacher->first_name }} {{ $teacher->last_name }}
                    </option>
                    @endforeach
                  </select>
                  <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Save</button>
                  <button type="button" @click="editing = false" class="text-xs text-gray-500 hover:text-gray-700">cancel</button>
                </form>
              </div>
              @endif

              <div x-data="{ showPicker: false }">
                <button x-show="!showPicker" type="button" @click="showPicker = true" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">+ Add class teacher</button>
                <form x-show="showPicker" x-cloak method="POST" action="{{ route('settings.assign-teacher') }}" class="flex items-center gap-2">
                  @csrf
                  <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                  <label class="text-xs text-gray-500 whitespace-nowrap">Pick a teacher:</label>
                  <select name="user_id" required
                    class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                    <option value="">Pick a teacher…</option>
                    @foreach($teachers as $t)
                    @if(!$offering->teachers->contains('id', $t->id))
                    <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}</option>
                    @endif
                    @endforeach
                  </select>
                  <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Assign</button>
                  <button type="button" @click="showPicker = false" class="text-xs text-gray-500 hover:text-gray-700">cancel</button>
                </form>
              </div>
            </div>

            @php
              // Distinct subjects taught in this class across all terms.
              $offeringSubjects = \App\Models\Subject::whereExists(function ($q) use ($offering) {
                  $q->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('subject_term_offering')
                    ->whereColumn('subject_term_offering.subject_id', 'subjects.id')
                    ->where('subject_term_offering.offering_id', $offering->id);
              })->orderBy('name')->get();
              $subjectTeacherMap = \App\Models\TeacherAssignment::where('offering_id', $offering->id)
                  ->get()
                  ->groupBy('subject_id')
                  ->map(fn ($rows) => $rows->pluck('user_id')->all())
                  ->toArray();
              $teacherLookup = $teachers->keyBy('id');
            @endphp

            @if($offeringSubjects->isNotEmpty())
            @php
              $teacherNames = $teachers->mapWithKeys(fn ($t) => [$t->id => $t->first_name . ' ' . $t->last_name])->toArray();
              $subjectNames = $offeringSubjects->mapWithKeys(fn ($s) => [$s->id => $s->name])->toArray();
              // Filter out any assignments pointing at teachers we don't list (archived / removed).
              $initial = collect($subjectTeacherMap)
                  ->map(fn ($ids) => array_values(array_filter($ids, fn ($id) => isset($teacherNames[$id]))))
                  ->filter(fn ($ids) => !empty($ids))
                  ->toArray();
            @endphp

            <p class="text-xs text-gray-500 mb-2">Subject locks <span class="text-gray-500">— default: any class teacher</span></p>
            <form method="POST" action="{{ route('settings.set-subject-teachers') }}" class="pl-2 border-l-2 border-gray-100 mb-4"
              x-data="{
                assignments: {{ json_encode((object) $initial) }},
                subjectNames: {{ json_encode((object) $subjectNames) }},
                teacherNames: {{ json_encode((object) $teacherNames) }},
                showPicker: false,
                pickSubject: '',
                pickTeacher: '',
                get assignedSubjectIds() { return Object.keys(this.assignments); },
                add() {
                  if (!this.pickSubject || !this.pickTeacher) return;
                  const s = String(this.pickSubject), u = parseInt(this.pickTeacher);
                  if (!this.assignments[s]) this.assignments[s] = [];
                  if (!this.assignments[s].includes(u)) this.assignments[s].push(u);
                  this.pickSubject = ''; this.pickTeacher = ''; this.showPicker = false;
                  this.$nextTick(() => this.$el.submit());
                },
                removeTeacher(subjectId, userId) {
                  this.assignments[subjectId] = this.assignments[subjectId].filter(x => x !== userId);
                  if (this.assignments[subjectId].length === 0) delete this.assignments[subjectId];
                  this.$nextTick(() => this.$el.submit());
                },
              }">
              @csrf
              <input type="hidden" name="offering_id" value="{{ $offering->id }}">

              <div class="space-y-1">
                <template x-for="subjectId in assignedSubjectIds" :key="subjectId">
                  <div class="flex items-start gap-2 py-1">
                    <span class="text-xs text-gray-600 min-w-[140px] pt-0.5" x-text="subjectNames[subjectId]"></span>
                    <div class="flex flex-wrap items-center gap-1 flex-1">
                      <template x-for="userId in assignments[subjectId]" :key="userId">
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 rounded text-xs text-gray-700">
                          <span x-text="teacherNames[userId]"></span>
                          <button type="button" @click="removeTeacher(subjectId, userId)" class="text-gray-500 hover:text-red-500 ml-1">&times;</button>
                          <input type="hidden" :name="'teachers[' + subjectId + '][]'" :value="userId">
                        </span>
                      </template>
                    </div>
                  </div>
                </template>
                <p x-show="assignedSubjectIds.length === 0" class="text-xs text-gray-500 italic">No subject locked — any class-attached teacher can grade every subject.</p>
              </div>

              <div class="mt-3">
                <button x-show="!showPicker" type="button" @click="showPicker = true"
                  class="text-xs font-medium text-indigo-700 hover:text-indigo-900">+ Add subject teacher</button>

                <div x-show="showPicker" x-cloak class="flex flex-wrap items-center gap-2 p-2 bg-gray-50 border border-gray-200 rounded-md">
                  <select x-model="pickSubject" class="px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                    <option value="">Subject…</option>
                    @foreach($offeringSubjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                  </select>
                  <select x-model="pickTeacher" class="px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                    <option value="">Teacher…</option>
                    @foreach($teachers as $t)
                    <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}</option>
                    @endforeach
                  </select>
                  <button type="button" @click="add()" :disabled="!pickSubject || !pickTeacher"
                    class="px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900 disabled:opacity-30">Add</button>
                  <button type="button" @click="showPicker = false; pickSubject = ''; pickTeacher = ''"
                    class="text-xs text-gray-500 hover:text-gray-700">cancel</button>
                </div>
              </div>
            </form>
            @endif

            <details>
              <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-600">Subjects <span class="text-gray-500">— what appears on this class's term reports</span></summary>
              <div class="pl-2 border-l-2 border-gray-100 mt-2">
              <p class="mb-2 text-xs text-gray-400">The subjects for this class (the same every term). <span class="text-green-700">Green = Mark added</span> (summed into the term total and ranking); <span class="text-amber-700">amber = Graded</span> (letter grade only, not added). The tick starts from the school-wide setting under Subjects and overrides it for this class.</p>
              @php
                $mapped = DB::table('subject_term_offering')
                  ->where('offering_id', $offering->id)
                  ->get()->groupBy('subject_id')->map(fn ($g) => $g->first()->counts_toward_total);
                $mappedSubjects = $mapped->keys()->all();
              @endphp
              <div class="flex flex-wrap gap-1 mb-2">
                @foreach($subjects as $subject)
                {{-- in_array (not $mapped->has): a null override value is a valid mapping, but Collection::has() uses isset() and treats null as absent. --}}
                @if(in_array($subject->id, $mappedSubjects))
                @php
                  $override = $mapped->get($subject->id); // null = inherit the school-wide default; 0/1 = explicit override
                  $counting = is_null($override) ? (bool) $subject->counts_toward_total : (bool) $override;
                @endphp
                <span class="inline-flex items-center gap-1 pl-1.5 pr-1 py-0.5 border rounded text-xs {{ $counting ? 'bg-green-50 border-green-200 text-green-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
                  <form method="POST" action="{{ route('settings.set-class-subject-counting') }}" class="inline-flex items-center">
                    @csrf
                    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                    <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                    <input type="checkbox" name="counts" value="1" onchange="this.form.submit()" @checked($counting)
                      title="{{ $counting ? 'Mark added — untick to make it Graded' : 'Graded — tick to make it Mark added' }}"
                      aria-label="{{ $subject->name }} is mark added (counts toward the total)"
                      class="h-3 w-3 rounded border-gray-300 {{ $counting ? 'text-green-700 focus:ring-green-600' : 'text-amber-600 focus:ring-amber-500' }}">
                  </form>
                  {{ $subject->name }}
                  <form method="POST" action="{{ route('settings.remove-class-subject') }}" class="inline">
                    @csrf
                    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                    <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                    <button type="submit" class="{{ $counting ? 'text-green-400' : 'text-amber-400' }} hover:text-red-500" aria-label="Remove {{ $subject->name }}">&times;</button>
                  </form>
                </span>
                @endif
                @endforeach
              </div>
              <form method="POST" action="{{ route('settings.add-class-subject') }}" class="flex gap-2">
                @csrf
                <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                <select name="subject_id" required
                  class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded-md">
                  <option value="">Add subject...</option>
                  @foreach($subjects as $subject)
                  @if(!in_array($subject->id, $mappedSubjects))
                  <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                  @endif
                  @endforeach
                </select>
                <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Add</button>
              </form>
              </div>
            </details>
          </div>
          @endforeach
        </div>
      </section>

    @else
      <div class="p-8 text-center text-gray-500 border border-gray-200 rounded-md">
        No school year set up yet. <a href="{{ route('rollover.index') }}" class="text-gray-900 underline">Create one</a>.
      </div>
    @endif

  </div>

  {{-- =========================================================== --}}
  {{-- Grading: assessment types                                   --}}
  {{-- =========================================================== --}}
  <div x-show="tab === '#grading'" x-cloak>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-1">Report type</h2>
      <p class="mb-3 text-xs text-gray-500">Choose how reports are produced for this school.</p>
      <form method="POST" action="{{ route('settings.update-report-type') }}" class="flex items-center gap-2">
        @csrf
        <select name="report_type" onchange="this.form.submit()"
          class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
          <option value="term" @selected(($reportType ?? 'term') === 'term')>Term report — one page per term</option>
          <option value="book" @selected(($reportType ?? 'term') === 'book')>Report Book — annual grid (empty or filled)</option>
        </select>
        <noscript><button type="submit" class="px-3 py-2 text-sm text-white bg-gray-800 rounded-md">Save</button></noscript>
      </form>
    </section>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-1">Grade key</h2>
      <p class="mb-3 text-xs text-gray-500">How marks map to a grade and interpretation on reports. Leave the grade range blank for a school-wide key, or set a range (e.g. 1–3, 4–6) so different grades can use different thresholds.</p>
      @php
        $bandGroups = $gradeBands->groupBy(fn ($b) => $b->grade_min === null ? 'all' : $b->grade_min.'-'.$b->grade_max);
      @endphp
      <div class="mb-4 space-y-4">
        @forelse($bandGroups as $key => $bands)
          <div>
            <p class="mb-1 text-xs font-semibold text-gray-600">{{ $key === 'all' ? 'All grades' : 'Grades '.str_replace('-', '–', $key) }}</p>
            <div class="flex items-center gap-2 px-1 text-xs text-gray-400">
              <span class="w-16">Min</span><span class="w-16">Max</span><span class="w-16">Grade</span><span class="w-40">Interpretation</span><span class="w-12">From</span><span class="w-12">To</span>
            </div>
            @foreach ($bands as $band)
              <div class="flex items-center gap-2 mt-1">
                <form method="POST" action="{{ route('settings.update-grade-band', $band) }}" class="flex items-center gap-2">
                  @csrf
                  <input type="number" step="0.01" name="min_score" value="{{ rtrim(rtrim(number_format($band->min_score,2),'0'),'.') }}" class="w-16 px-2 py-1 text-sm border border-gray-300 rounded">
                  <input type="number" step="0.01" name="max_score" value="{{ rtrim(rtrim(number_format($band->max_score,2),'0'),'.') }}" class="w-16 px-2 py-1 text-sm border border-gray-300 rounded">
                  <input type="text" name="label" value="{{ $band->label }}" class="w-16 px-2 py-1 text-sm border border-gray-300 rounded">
                  <input type="text" name="remark" value="{{ $band->remark }}" class="w-40 px-2 py-1 text-sm border border-gray-300 rounded">
                  <input type="number" name="grade_min" value="{{ $band->grade_min }}" placeholder="—" class="w-12 px-1 py-1 text-sm border border-gray-300 rounded" title="Applies from grade">
                  <input type="number" name="grade_max" value="{{ $band->grade_max }}" placeholder="—" class="w-12 px-1 py-1 text-sm border border-gray-300 rounded" title="Applies to grade">
                  <button type="submit" class="px-2 text-xs font-medium text-indigo-700 hover:text-indigo-900">Save</button>
                </form>
                <form method="POST" action="{{ route('settings.destroy-grade-band', $band) }}" onsubmit="return confirm('Remove this band?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="px-2 text-xs text-red-600 hover:text-red-800">Remove</button>
                </form>
              </div>
            @endforeach
          </div>
        @empty
          <p class="text-sm italic text-gray-500">No grade key set yet — add the first band below.</p>
        @endforelse
      </div>
      <form method="POST" action="{{ route('settings.store-grade-band') }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <div><label class="block mb-1 text-xs text-gray-500">Min</label><input type="number" step="0.01" name="min_score" required class="w-16 px-2 py-2 text-sm border border-gray-300 rounded-md"></div>
        <div><label class="block mb-1 text-xs text-gray-500">Max</label><input type="number" step="0.01" name="max_score" required class="w-16 px-2 py-2 text-sm border border-gray-300 rounded-md"></div>
        <div><label class="block mb-1 text-xs text-gray-500">Grade</label><input type="text" name="label" placeholder="A" required class="w-16 px-2 py-2 text-sm border border-gray-300 rounded-md"></div>
        <div><label class="block mb-1 text-xs text-gray-500">Interpretation</label><input type="text" name="remark" placeholder="Excellent" class="w-40 px-2 py-2 text-sm border border-gray-300 rounded-md"></div>
        <div><label class="block mb-1 text-xs text-gray-500">Grade from</label><input type="number" name="grade_min" placeholder="—" class="w-16 px-2 py-2 text-sm border border-gray-300 rounded-md"></div>
        <div><label class="block mb-1 text-xs text-gray-500">to</label><input type="number" name="grade_max" placeholder="—" class="w-16 px-2 py-2 text-sm border border-gray-300 rounded-md"></div>
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Add band</button>
      </form>
    </section>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-1">Assessment types</h2>
      <p class="text-xs text-gray-500 mb-3">
        Weights determine how much each type contributes to the term average (Test + Exam should sum to 100%).
        The default max score pre-fills new assessments — teachers can still override per assessment.
      </p>

      @if($assessmentTypes->isEmpty())
        <div class="p-8 text-center text-gray-500 border border-gray-200 rounded-md">No assessment types configured.</div>
      @else
        <div class="overflow-hidden border border-gray-200 rounded-lg">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Weight (%)</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Default max score</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              @foreach($assessmentTypes as $assessmentType)
              <tr>
                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $assessmentType->name }}</td>
                <form method="POST" action="{{ route('settings.update-assessment-type', $assessmentType) }}">
                  @csrf
                  <td class="px-4 py-2">
                    <div class="inline-flex items-center gap-1">
                      <input name="weight_percent" type="number" step="1" min="0" max="100"
                        value="{{ (int) round($assessmentType->weight * 100) }}"
                        class="px-2 py-1 text-sm border border-gray-300 rounded-md w-20">
                      <span class="text-sm text-gray-500">%</span>
                    </div>
                  </td>
                  <td class="px-4 py-2">
                    <input name="default_max_score" type="number" min="1" max="1000"
                      value="{{ $assessmentType->default_max_score }}"
                      class="px-2 py-1 text-sm border border-gray-300 rounded-md w-24">
                  </td>
                  <td class="px-4 py-2">
                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Save</button>
                  </td>
                </form>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>

  </div>

  {{-- =========================================================== --}}
  {{-- Timetable: school-wide period structure                      --}}
  {{-- =========================================================== --}}
  <div x-show="tab === '#timetable'" x-cloak>
    <section class="mb-10">
      <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-1">Periods</h2>
      <p class="mb-4 text-xs text-gray-500">The daily period structure used by every class timetable. Add teaching periods and breaks in the order they run; times are optional.</p>

      @if($periods->isEmpty())
        <p class="mb-4 text-sm text-gray-500 italic">No periods yet — add the first one below.</p>
      @else
        <div class="mb-4 overflow-hidden border border-gray-200 rounded-lg divide-y divide-gray-200">
          @foreach($periods as $period)
            <div class="flex items-center justify-between px-4 py-2 text-sm {{ $period->is_break ? 'bg-gray-50' : 'bg-white' }}">
              <div class="flex items-center gap-3">
                <span class="font-medium text-gray-900">{{ $period->label }}</span>
                @if($period->is_break)<span class="px-1.5 py-0.5 text-xs rounded bg-gray-200 text-gray-700">break</span>@endif
                @if($period->timeRange())<span class="text-gray-500">{{ $period->timeRange() }}</span>@endif
              </div>
              <form method="POST" action="{{ route('settings.destroy-period', $period) }}"
                    onsubmit="return confirm('Remove this period? Any lessons in it will be cleared.')">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 hover:text-red-800">Remove</button>
              </form>
            </div>
          @endforeach
        </div>
      @endif

      <form method="POST" action="{{ route('settings.store-period') }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <div>
          <label for="period-label" class="block mb-1 text-xs text-gray-500">Label</label>
          <input id="period-label" type="text" name="label" placeholder="Period 1" required
            class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        </div>
        <div>
          <label for="period-start" class="block mb-1 text-xs text-gray-500">Start</label>
          <input id="period-start" type="time" name="start_time"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        </div>
        <div>
          <label for="period-end" class="block mb-1 text-xs text-gray-500">End</label>
          <input id="period-end" type="time" name="end_time"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        </div>
        <label class="flex items-center gap-1.5 px-2 py-2 text-sm text-gray-700">
          <input type="checkbox" name="is_break" value="1" class="h-4 w-4 border-gray-300 rounded text-gray-900">
          Break
        </label>
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Add period</button>
      </form>
    </section>
  </div>

  {{-- =========================================================== --}}
  {{-- Modules: enable/disable per school                           --}}
  {{-- =========================================================== --}}
  <div x-show="tab === '#modules'" x-cloak>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-1">Modules</h2>
      <p class="text-xs text-gray-500 mb-4">
        Hide features your school doesn't use. Disabling a module removes it from the menu — the routes still respond if URLs are typed directly. Core modules can't be turned off.
      </p>

      <div class="space-y-2">
        @foreach($modules as $slug => $def)
          @php
            $alwaysOn = $def['always_on'] ?? false;
            $isEnabled = $alwaysOn || in_array($slug, $enabledModules, true);
          @endphp
          <div class="p-4 bg-white border border-gray-200 rounded-lg flex items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900">
                {{ $def['label'] }}
                @if ($alwaysOn)
                  <span class="ml-2 text-xs font-normal text-gray-500">core</span>
                @endif
                @if ($def['beta'] ?? false)
                  <span class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide rounded bg-amber-100 text-amber-700 align-middle">Beta</span>
                @endif
              </p>
              <p class="text-xs text-gray-500 mt-0.5">{{ $def['description'] ?? '' }}</p>
            </div>
            <form method="POST" action="{{ route('settings.toggle-module', $slug) }}" class="shrink-0">
              @csrf
              <input type="hidden" name="enabled" value="{{ $isEnabled ? '0' : '1' }}">
              @if ($alwaysOn)
                <span class="inline-block px-3 py-1.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Always on</span>
              @elseif ($isEnabled)
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-full hover:bg-green-100">Enabled</button>
              @else
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-gray-500 bg-white border border-gray-200 rounded-full hover:bg-gray-50">Disabled</button>
              @endif
            </form>
          </div>
        @endforeach
      </div>
    </section>

  </div>

  {{-- =========================================================== --}}
  {{-- Permissions: per-role matrix — only for manage-permissions   --}}
  {{-- =========================================================== --}}
  @can('manage permissions')
  <div x-show="tab === '#permissions'" x-cloak>

    <section class="mb-10">
      <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-1">Permissions per role</h2>
      <p class="text-xs text-gray-500 mb-4">
        Check the permissions each role should have. Per-user overrides (e.g. an assistant coordinator with health access) stay on the
        <a class="underline" href="{{ route('users.index') }}">Users page</a>.
      </p>

      <div class="space-y-4">
        @foreach($permissionMatrix['roles'] as $role)
          <form method="POST" action="{{ route('settings.update-role-permissions', $role) }}"
            class="p-4 bg-white border border-gray-200 rounded-lg">
            @csrf
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-sm font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $role->name) }}</p>
                <p class="text-xs text-gray-500">Spatie role &middot; {{ count($permissionMatrix['grants'][$role->name] ?? []) }} permission{{ count($permissionMatrix['grants'][$role->name] ?? []) === 1 ? '' : 's' }} granted</p>
              </div>
              <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Save</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              @foreach($permissionMatrix['permissions'] as $perm)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                    @checked(in_array($perm->name, $permissionMatrix['grants'][$role->name] ?? []))
                    class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                  <span>{{ $perm->name }}</span>
                </label>
              @endforeach
            </div>
          </form>
        @endforeach
      </div>
    </section>

  </div>
  @endcan
</div>
</x-page>
