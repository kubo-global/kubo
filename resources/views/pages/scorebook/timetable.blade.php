<x-page title="Timetable">
  <div class="px-6 py-6 lg:px-8">

    <div class="mb-4">
      <nav class="text-sm text-gray-500">
        <a href="{{ route('timetable.index') }}" class="text-indigo-600 hover:underline">Timetables</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('timetable.index', ['year' => $offering->schoolyear_id]) }}" class="text-indigo-600 hover:underline">{{ $offering->schoolyear->name ?? '' }}</a>
      </nav>
    </div>

    {{-- Class switcher: jump straight to another class's timetable without going back to the picker. --}}
    <div class="mb-5">
      <label for="classSwitch" class="block mb-1 text-xs font-semibold tracking-wide text-gray-500 uppercase">Class</label>
      @if (($siblings ?? collect())->count() > 1)
        <div class="relative inline-block">
          <select id="classSwitch" aria-label="Switch class"
            onchange="if (this.value) window.location.href = this.value"
            class="py-2 pl-3 pr-10 text-lg font-bold text-gray-900 bg-white border border-gray-300 rounded-lg shadow-sm appearance-none cursor-pointer focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
            @foreach ($siblings as $sib)
              <option value="{{ route('timetable.show', $sib) }}" @selected($sib->id === $offering->id)>{{ $sib->displayName() }}</option>
            @endforeach
          </select>
          <svg class="absolute w-5 h-5 text-gray-400 -translate-y-1/2 pointer-events-none right-3 top-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      @else
        <div class="text-lg font-bold text-gray-900">{{ $offering->displayName() }}</div>
      @endif
    </div>

    @if ($periods->isEmpty())
      <div class="p-4 text-sm text-gray-700 border rounded-lg bg-[#f0f3fa] border-[#dee4f2]">
        No periods are set up yet. An administrator can define the daily period structure in
        <a href="{{ route('settings.index') }}#timetable" class="font-semibold text-indigo-700 hover:underline">Settings &rsaquo; Timetable</a>.
      </div>
    @else
      <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <p class="text-sm text-gray-600">Weekly timetable for <b>{{ $offering->displayName() }}</b>.{{ $editing ? ' Pick a subject (and optional teacher) per slot.' : '' }}</p>
        <div class="flex items-center gap-2">
          @if ($canEdit && ! $editing)
            <a href="{{ route('timetable.show', ['offering' => $offering, 'edit' => 1]) }}"
              class="inline-flex items-center px-4 py-2 text-xs font-bold tracking-wide text-white uppercase bg-gray-800 rounded-md hover:bg-gray-900">Edit timetable</a>
          @endif
          {{-- Instructional hours has its own nav item; a second door here only made
               the same screen reachable from two places. --}}
          <a href="{{ route('timetable.print', $offering) }}" target="_blank" rel="noopener"
            class="inline-flex items-center px-4 py-2 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">Timetable PDF</a>
        </div>
      </div>

      <form id="timetableForm" method="POST" action="{{ route('timetable.update', $offering) }}">
        @csrf
        {{-- Grid layout for tablet/desktop; phones get the stacked day list below. --}}
        <div class="hidden overflow-x-auto border border-gray-200 rounded-lg sm:block">
          <table class="min-w-full text-sm border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-3 py-2 text-left font-semibold text-gray-600 border-b border-gray-200 w-40">Period</th>
                @foreach ($days as $dayNum => $dayName)
                  <th class="px-3 py-2 text-left font-semibold text-gray-600 border-b border-l border-gray-200">{{ $dayName }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @php $stripe = 0; @endphp
              @foreach ($periods as $period)
                @php if (! $period->is_break) { $stripe++; } @endphp
                {{-- Zebra-stripe the teaching rows so a wide grid is easier to scan; breaks stay distinct. --}}
                <tr class="{{ $period->is_break ? 'bg-gray-100' : ($stripe % 2 === 0 ? 'bg-gray-50/70' : 'bg-white') }}">
                  <td class="px-3 py-2 align-top border-b border-gray-200">
                    <div class="font-medium text-gray-900">{{ $period->label }}</div>
                    @if ($period->timeRange())<div class="text-xs text-gray-500">{{ $period->timeRange() }}</div>@endif
                  </td>
                  @foreach ($days as $dayNum => $dayName)
                    @php $cell = $grid[$period->id][$dayNum]; $lesson = $cell['lesson']; @endphp
                    @continue($cell['kind'] === 'covered') {{-- spanned by a block above --}}
                    <td @if($cell['rowspan'] > 1) rowspan="{{ $cell['rowspan'] }}" @endif
                        class="px-2 py-2 align-top border-b border-l border-gray-200 {{ $cell['rowspan'] > 1 ? 'bg-indigo-50/60' : '' }}">
                      @if ($cell['kind'] === 'break')
                        <span class="text-xs text-gray-400 italic">break</span>
                      @elseif ($editing)
                        @include('pages.scorebook._timetable-selects', ['compact' => true, 'period' => $period, 'dayNum' => $dayNum, 'dayName' => $dayName, 'lesson' => $lesson])
                      @else
                        @if ($lesson)
                          <div class="text-xs font-medium text-gray-900">{{ optional($lesson->subject)->name }}</div>
                          @if ($lesson->teacher)<div class="text-xs text-gray-500">{{ $lesson->teacher->first_name }} {{ $lesson->teacher->last_name }}</div>@endif
                        @else
                          <span class="text-xs text-gray-300">—</span>
                        @endif
                      @endif
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Stacked day-by-day layout for phones (no horizontal scrolling); editable for staff. --}}
        <div class="mt-4 space-y-4 sm:hidden">
          @foreach ($days as $dayNum => $dayName)
            <div class="overflow-hidden border border-gray-200 rounded-lg">
              <div class="px-3 py-2 text-sm font-semibold text-gray-700 border-b border-gray-200 bg-gray-50">{{ $dayName }}</div>
              <ul class="divide-y divide-gray-100">
                @foreach ($periods as $period)
                  @php $cell = $grid[$period->id][$dayNum]; $lesson = $cell['lesson']; @endphp
                  @continue($cell['kind'] === 'covered')
                  <li class="flex items-start gap-3 px-3 py-2 {{ $cell['kind'] === 'break' ? 'bg-gray-50' : '' }}">
                    <div class="pt-1 shrink-0 w-20">
                      <div class="text-xs font-medium text-gray-900">{{ $period->label }}</div>
                      @if ($period->timeRange())<div class="text-[11px] text-gray-500">{{ $period->timeRange() }}</div>@endif
                    </div>
                    <div class="flex-1 min-w-0">
                      @if ($cell['kind'] === 'break')
                        <span class="text-xs italic text-gray-400">break</span>
                      @elseif ($editing)
                        @include('pages.scorebook._timetable-selects', ['compact' => false, 'period' => $period, 'dayNum' => $dayNum, 'dayName' => $dayName, 'lesson' => $lesson])
                      @elseif ($lesson)
                        <div class="text-sm font-medium text-gray-900">{{ optional($lesson->subject)->name }}@if ($cell['rowspan'] > 1)<span class="text-[11px] font-normal text-indigo-600"> · {{ $cell['rowspan'] }} periods</span>@endif</div>
                        @if ($lesson->teacher)<div class="text-xs text-gray-500">{{ $lesson->teacher->first_name }} {{ $lesson->teacher->last_name }}</div>@endif
                      @else
                        <span class="text-xs text-gray-300">—</span>
                      @endif
                    </div>
                  </li>
                @endforeach
              </ul>
            </div>
          @endforeach
        </div>

        @if ($editing)
          <div class="flex items-center justify-end gap-2 mt-4">
            <a href="{{ route('timetable.show', $offering) }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">Save timetable</button>
          </div>
        @endif
      </form>

      @if ($editing)
        {{-- Both layouts carry the same cell inputs; submit only the visible one so the
             hidden layout's duplicates don't override it. --}}
        <script>
          (function () {
            var form = document.getElementById('timetableForm');
            if (!form) return;
            form.addEventListener('submit', function () {
              form.querySelectorAll('select[name^="cells"]').forEach(function (el) {
                if (el.offsetParent === null) el.disabled = true; // offsetParent null => display:none ancestor
              });
            });
          })();
        </script>
      @endif

      @if ($subjects->isEmpty())
        <p class="mt-3 text-xs text-gray-500 italic">This class has no subjects attached yet — add them in the scorebook first and they'll appear here.</p>
      @endif
    @endif

  </div>
</x-page>
