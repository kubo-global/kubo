<x-page title="Instructional hours">
  <div class="px-6 py-6 lg:px-8">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <h2 class="text-base font-bold text-gray-900">
        Instructional hours
        @if ($selectedYear && $selectedYear->id === $currentYearId)
          <span class="font-normal text-gray-500">&middot; current school year</span>
        @endif
      </h2>

      <form method="GET" action="{{ route('instructional-hours.index') }}" class="flex items-center gap-2 text-sm text-gray-500">
        <label for="year">School year</label>
        <select id="year" name="year" onchange="this.form.submit()"
          class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900">
          @foreach ($schoolyears as $sy)
            <option value="{{ $sy->id }}" @selected($selectedYear && $sy->id === $selectedYear->id)>
              {{ $sy->name }}@if ($sy->id === $currentYearId) (current school year)@endif
            </option>
          @endforeach
        </select>
      </form>
    </div>

    @if ($offerings->isEmpty())
      <div class="p-8 text-center text-gray-500 border border-gray-200 rounded-md">No classes for this school year.</div>
    @else
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($offerings as $offering)
          @php
            $name = $offering->grade->name ?? 'Class';
            $abbr = collect(explode(' ', $name))->map(fn ($w) => ctype_digit($w) ? $w : strtoupper(substr($w, 0, 1)))->join('');
          @endphp
          <a href="{{ route('instructional-hours.show', $offering) }}"
            class="flex items-center gap-4 p-4 transition bg-white border border-gray-200 rounded-xl hover:border-indigo-300 hover:shadow-md">
            <span style="{{ $offering->badgeStyle() }}" class="inline-flex items-center justify-center w-12 h-12 text-sm font-bold rounded-lg shrink-0">{{ $abbr }}</span>
            <div class="min-w-0">
              <div class="text-base font-bold text-gray-900">{{ $name }}</div>
              <div class="mt-0.5 text-sm text-gray-500">{{ $offering->name ?: 'Class 1' }} &middot; {{ $offering->enrollments_count }} {{ \Illuminate\Support\Str::plural('student', $offering->enrollments_count) }}</div>
            </div>
          </a>
        @endforeach
      </div>
    @endif

    <p class="mt-4 text-xs text-gray-500">
      Opens on the current school year by default. Click a class to log its Actual and Lost teaching hours per month.
    </p>
  </div>
</x-page>
