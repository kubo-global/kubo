<x-page title="Attendance">
  <div class="px-6 py-6 lg:px-8">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <nav class="text-sm text-gray-500">
        <a href="{{ route('reporting.grades') }}" class="text-indigo-600 hover:underline">Scorebook</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('reporting.grades', ['year' => $offering->schoolyear_id]) }}" class="text-indigo-600 hover:underline">{{ $offering->schoolyear->name ?? '' }}</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('scorebook.class', $offering) }}" class="text-indigo-600 hover:underline">{{ $offering->displayName() }}</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <span class="text-gray-700">Attendance</span>
      </nav>
      @include('pages.scorebook._mode-switch', ['offering' => $offering, 'active' => 'attendance'])
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <form method="GET" action="{{ route('scorebook.attendance', $offering) }}" class="flex items-end gap-2">
        <div>
          <label for="date" class="block mb-1 text-xs text-gray-500">Register for</label>
          <input type="date" id="date" name="date" value="{{ $date }}"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        </div>
        <button type="submit" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Go</button>
      </form>

      {{-- Monthly summary PDF — the ministry "Students Daily Attendance" sheet, filled from the register. --}}
      <form method="GET" action="{{ route('attendance.summary', $offering) }}" target="_blank" rel="noopener" class="flex items-end gap-2">
        <div>
          <label for="month" class="block mb-1 text-xs text-gray-500">Monthly summary</label>
          <input type="month" id="month" name="month" value="{{ \Illuminate\Support\Str::of($date)->substr(0, 7) }}"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        </div>
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-indigo-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
          Summary PDF
        </button>
      </form>
    </div>

    @if ($students->isEmpty())
      <p class="text-sm text-gray-500 italic">No pupils enrolled in this class.</p>
    @else
      <form method="POST" action="{{ route('attendance.save', $offering) }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-gray-50 text-left text-gray-600">
                <th class="px-4 py-2 font-semibold border-b border-gray-200">Pupil</th>
                <th class="px-4 py-2 font-semibold border-b border-gray-200 w-44">Status</th>
                <th class="px-4 py-2 font-semibold text-right border-b border-gray-200 w-44">This year (present / absent)</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($students as $student)
                @php
                  $mark = $marks[$student->id] ?? null;
                  $current = $mark?->status?->value ?? 'present';
                  $total = $totals[$student->id] ?? null;
                @endphp
                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                  <td class="px-4 py-2 border-b border-gray-200 text-gray-900">{{ $student->first_name }} {{ $student->last_name }}</td>
                  <td class="px-4 py-2 border-b border-gray-200">
                    @if ($canEdit)
                      <select name="status[{{ $student->id }}]" aria-label="Status for {{ $student->first_name }} {{ $student->last_name }}"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                        @foreach ($statuses as $status)
                          <option value="{{ $status->value }}" @selected($current === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                      </select>
                    @else
                      <span class="text-gray-700">{{ ucfirst($current) }}</span>
                    @endif
                  </td>
                  <td class="px-4 py-2 text-right border-b border-gray-200 text-gray-600">
                    {{ (int) ($total->present ?? 0) }} / {{ (int) ($total->absent ?? 0) }}
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if ($canEdit)
          <div class="flex justify-end mt-4">
            <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Save register</button>
          </div>
        @endif
      </form>
    @endif

  </div>
</x-page>
