<x-page title="Scorebook">
  <div class="px-6 py-6 lg:px-8">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <nav class="text-sm text-gray-500">
        <a href="{{ route('reporting.grades') }}" class="text-indigo-600 hover:underline">Scorebook</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('reporting.grades', ['year' => $offering->schoolyear_id]) }}" class="text-indigo-600 hover:underline">{{ $offering->schoolyear->name ?? '' }}</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('scorebook.class', $offering) }}" class="text-indigo-600 hover:underline">{{ $offering->grade->name ?? 'Class' }}</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <span class="text-gray-700">{{ $natLabel }}</span>
      </nav>
      @include('pages.scorebook._mode-switch', ['offering' => $offering, 'active' => 'nat'])
    </div>

    <div class="p-4 mb-4 text-sm text-gray-700 border rounded-lg bg-[#f0f3fa] border-[#dee4f2]">
      <b class="text-[#3d5494]">{{ $natLabel }} (WAEC)</b> &mdash; a national exam sat once, not part of any term. Its own subjects, all on one screen.
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
      @if ($natType)
        <a href="{{ route('reporting.assessment.create', ['type' => $natType->id, 'offering' => $offering->id]) }}"
          class="inline-flex items-center px-4 py-2 text-xs font-bold tracking-wide text-white uppercase rounded-md bg-indigo-600 hover:bg-indigo-700">+ Add a subject</a>
      @endif
      <div class="flex-1"></div>
      <label class="inline-flex items-center gap-1.5 mr-1 text-xs text-gray-600 select-none" title="Students absent for every subject did not sit; hide them so the listing matches the WAEC candidate sheet. Statistics are unaffected.">
        <input type="checkbox" id="hideAbsentToggle" class="border-gray-300 rounded text-indigo-700 focus:ring-gray-900">
        Hide absent students from PDF
      </label>
      <a id="natScoresPdf" href="{{ route('nat.print.scores', $offering) }}" target="_blank" rel="noopener"
        class="inline-flex items-center px-4 py-2 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">NAT scores PDF</a>
      <a href="{{ route('nat.print.analysis', $offering) }}" target="_blank" rel="noopener"
        class="inline-flex items-center px-4 py-2 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">NAT analysis PDF</a>
    </div>
    <script>
      document.getElementById('hideAbsentToggle')?.addEventListener('change', function (e) {
        var link = document.getElementById('natScoresPdf');
        var url = new URL(link.href);
        if (e.target.checked) { url.searchParams.set('hide_absent', '1'); } else { url.searchParams.delete('hide_absent'); }
        link.href = url.toString();
      });
    </script>

    @if ($assessments->isNotEmpty())
      <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
        <table class="min-w-full text-sm divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-xs font-semibold tracking-wide text-left text-gray-500 uppercase">Student</th>
              @foreach ($assessments as $a)
                <th class="px-4 py-3 text-right">
                  <a href="{{ route('reporting.assessment.scores', $a) }}" title="Enter or edit {{ $a->subject->name }} scores"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-indigo-200 bg-indigo-50 text-indigo-700 text-sm font-semibold hover:bg-indigo-100 hover:border-indigo-300">
                    {{ $a->subject->name ?? '' }}
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4v16h16v-7M18.5 2.5a2.1 2.1 0 013 3L12 15l-4 1 1-4z"/></svg>
                  </a>
                  <span class="block mt-1 text-xs font-normal text-gray-500">/{{ $a->max_score }}</span>
                </th>
              @endforeach
              <th class="px-4 py-3 text-right text-gray-500">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($students as $student)
              @php $total = 0; @endphp
              <tr class="hover:bg-indigo-50/40">
                <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ $student->last_name }} {{ $student->first_name }}</td>
                @foreach ($assessments as $a)
                  @php $sc = $scores[$a->id.'_'.$student->id] ?? null; $v = ($sc && !$sc['absent']) ? $sc['score'] : null; $total += (int) $v; @endphp
                  <td class="px-4 py-3 text-right {{ $v !== null && $v >= 80 ? 'bg-green-100 text-green-800 font-semibold' : ($v !== null && $v < 40 ? 'bg-red-100 text-red-800 font-semibold' : 'text-gray-700') }}">
                    @if ($sc && $sc['absent']) <span class="text-gray-500">A</span>
                    @elseif ($v !== null) {{ $v }}
                    @else <span class="text-gray-500">&mdash;</span> @endif
                  </td>
                @endforeach
                <td class="px-4 py-3 font-semibold text-right text-gray-800">{{ $total }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="mt-3 text-xs text-gray-500"><b>Click a subject heading</b> to enter or edit its scores.</p>
      <p class="mt-1 text-xs text-gray-500"><span class="inline-block w-3 h-3 align-middle bg-green-100 border border-green-300"></span> Mastery (&ge; 80) &nbsp; <span class="inline-block w-3 h-3 align-middle bg-red-100 border border-red-300"></span> Fail (&lt; 40)</p>
    @else
      <div class="p-8 text-center text-gray-500 border border-gray-200 rounded-md">
        No NAT results entered for this class yet.
        @if ($natType)
          <a class="ml-1 text-indigo-600 underline" href="{{ route('reporting.assessment.create', ['type' => $natType->id, 'offering' => $offering->id]) }}">Add a subject</a> to start.
        @endif
      </div>
    @endif
  </div>
</x-page>
