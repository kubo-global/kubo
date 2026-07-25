<x-page title="Scorebook">
  <div class="px-6 py-6 lg:px-8">

    {{-- class-level header: breadcrumb + (config-gated) NAT button --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <nav class="text-sm text-gray-500">
        <a href="{{ route('reporting.grades') }}" class="text-indigo-600 hover:underline">Scorebook</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('reporting.grades', ['year' => $offering->schoolyear_id]) }}" class="text-indigo-600 hover:underline">{{ $offering->schoolyear->name ?? '' }}</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <span class="text-gray-700">{{ $offering->grade->name ?? 'Class' }}</span>
      </nav>

      @if ($natEnabled)
        @include('pages.scorebook._mode-switch', ['offering' => $offering, 'active' => 'regular'])
      @endif
    </div>

    @php $byLabel = (\App\Models\School::first()?->config(\App\Models\SchoolConfig::SCOREBOOK_PERIOD_MODE, 'months') === 'tests') ? 'By test' : 'By month'; @endphp
    {{-- View switch: by subject / by test|month (one round, all subjects) / by term (all rounds combined) --}}
    <div class="inline-flex p-0.5 mb-5 rounded-lg bg-gray-100">
      <span class="px-3 py-1.5 text-sm font-semibold text-gray-900 bg-white rounded-md shadow-sm">By subject</span>
      <a href="{{ route('term-grid.report', ['offering' => $offering, 'term' => $term?->id]) }}" class="px-3 py-1.5 text-sm rounded-md text-gray-600 hover:text-gray-900">{{ $byLabel }}</a>
      <a href="{{ route('term-grid.overview', ['offering' => $offering, 'term' => isset($period) ? $period['term']?->id : $term?->id]) }}" class="px-3 py-1.5 text-sm rounded-md text-gray-600 hover:text-gray-900">By term</a>
    </div>

    {{-- subject selector: a dropdown on phones (8 subjects wrap badly), tabs on sm+ --}}
    @if ($subjects->isEmpty())
      <div class="mb-5"><span class="px-1 py-2 text-sm text-gray-500">No subjects for this term.</span></div>
    @else
      <div class="mb-5 sm:hidden">
        <label for="subjectSelect" class="sr-only">Subject</label>
        <select id="subjectSelect" aria-label="Subject"
          onchange="if (this.value) window.location.href = this.value"
          class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
          @foreach ($subjects as $s)
            @php $count = $subjectAssessmentCounts[$s->id] ?? 0; @endphp
            <option value="{{ route('scorebook.class', ['offering' => $offering, 'subject' => $s->id, 'term' => $term?->id]) }}"
              @selected($subject && $s->id === $subject->id)>{{ $s->name }}@if ($count > 0) ({{ $count }})@endif</option>
          @endforeach
        </select>
      </div>

      <div class="flex-wrap hidden gap-1 mb-5 border-b border-gray-200 sm:flex">
        @foreach ($subjects as $s)
          @php $count = $subjectAssessmentCounts[$s->id] ?? 0; $isActive = $subject && $s->id === $subject->id; @endphp
          <a href="{{ route('scorebook.class', ['offering' => $offering, 'subject' => $s->id, 'term' => $term?->id]) }}"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm -mb-px border-b-2 {{ $isActive ? 'border-indigo-600 text-gray-900 font-bold' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
            {{ $s->name }}
            @if ($count > 0)
              <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 text-[11px] font-semibold rounded-full {{ $isActive ? 'bg-indigo-600 text-white' : 'bg-indigo-100 text-indigo-700' }}"
                title="Tests/exams this term: {{ $count }}">{{ $count }}</span>
            @endif
          </a>
        @endforeach
      </div>
    @endif

    {{-- actions: add (dedicated screen) + quiet term switcher --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <a href="{{ route('scorebook.create', ['offering' => $offering->id, 'term' => $term?->id, 'subject' => $subject?->id]) }}"
        class="inline-flex items-center px-4 py-2 text-xs font-bold tracking-wide text-white uppercase rounded-md bg-indigo-600 hover:bg-indigo-700">
        + Add test / exam
      </a>

      @if ($terms->isNotEmpty())
        <div class="inline-flex items-center gap-2">
          <span class="text-sm font-medium text-gray-600">Term</span>
          <div class="inline-flex overflow-hidden text-sm border border-gray-300 rounded-lg shadow-sm">
            @foreach ($terms as $t)
              @php $isCurrent = $t->start <= now() && $t->end >= now(); $isSelected = $term && $t->id === $term->id; @endphp
              <a href="{{ route('scorebook.class', ['offering' => $offering, 'subject' => $subject?->id, 'term' => $t->id]) }}"
                class="inline-flex items-center gap-1.5 px-4 py-1.5 font-semibold border-l first:border-l-0 border-gray-300 {{ $isSelected ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                {{ $t->name }}
                @if ($isCurrent)
                  <span class="text-[10px] font-bold uppercase tracking-wide {{ $isSelected ? 'text-indigo-200' : 'text-emerald-600' }}" title="The term we are in now">now</span>
                @endif
              </a>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    {{-- read-only Test/Exam table for the selected subject + term --}}
    @if ($subject && $assessments->isNotEmpty())
      <div class="overflow-auto max-h-[calc(100vh_-_16rem)] min-h-[24rem] border border-gray-200 rounded-lg shadow-sm">
        <table class="min-w-full text-sm divide-y divide-gray-200">
          <thead class="sticky top-0 z-20 bg-gray-50">
            <tr>
              <th class="sticky left-0 z-30 px-4 py-3 text-xs font-semibold tracking-wide text-left text-gray-500 uppercase bg-gray-50">Student</th>
              @foreach ($assessments as $a)
                <th class="px-4 py-2 text-right">
                  <span class="block text-[10px] font-normal text-gray-500">{{ $a->assessmentType->name ?? '' }}</span>
                  <a href="{{ route('reporting.assessment.edit', $a) }}" class="text-gray-700 underline hover:text-gray-900">{{ $a->name }}</a>
                  <span class="text-xs font-normal text-gray-500">/{{ $a->max_score }}</span>
                </th>
              @endforeach
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($students as $student)
              <tr class="hover:bg-indigo-50/40">
                <td class="sticky left-0 px-4 py-3 font-medium text-gray-900 bg-white whitespace-nowrap">{{ $student->first_name }} {{ $student->last_name }}</td>
                @foreach ($assessments as $a)
                  @php $sc = $scores[$a->id.'_'.$student->id] ?? null; @endphp
                  <td class="px-4 py-3 text-right text-gray-700">
                    @if ($sc && $sc['absent']) <span class="text-indigo-600">absent</span>
                    @elseif ($sc) {{ $sc['score'] }}
                    @else <span class="text-gray-500">&mdash;</span> @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="mt-3 text-xs text-gray-500">Read-only. Click a column to open its dedicated entry / edit screen. The National Assessment Test is a class-level button, separate from subjects.</p>
    @elseif ($subject)
      <div class="p-8 text-center text-gray-500 border border-gray-200 rounded-md">
        No tests or exams entered yet for {{ $subject->name }} this term.
        <a class="ml-1 text-gray-900 underline" href="{{ route('scorebook.create', ['offering' => $offering->id, 'term' => $term?->id, 'subject' => $subject->id]) }}">Add scores</a>
      </div>
    @endif
  </div>
</x-page>
