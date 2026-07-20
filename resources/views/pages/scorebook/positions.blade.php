<x-page title="Positions">
  <div class="px-6 py-6 lg:px-8">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <nav class="text-sm text-gray-500">
        <a href="{{ route('reporting.grades') }}" class="text-indigo-600 hover:underline">Scorebook</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('reporting.grades', ['year' => $offering->schoolyear_id]) }}" class="text-indigo-600 hover:underline">{{ $offering->schoolyear->name ?? '' }}</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <a href="{{ route('scorebook.class', $offering) }}" class="text-indigo-600 hover:underline">{{ $offering->displayName() }}</a>
        <span class="mx-1 text-gray-500">&rsaquo;</span>
        <span class="text-gray-700">Positions</span>
      </nav>
      @include('pages.scorebook._mode-switch', ['offering' => $offering, 'active' => 'positions'])
    </div>

    @if ($terms->isNotEmpty())
      <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-sm text-gray-500">Term</span>
        @foreach ($terms as $t)
          <a href="{{ route('scorebook.positions', ['offering' => $offering, 'term' => $t->id]) }}"
            class="px-3 py-1.5 text-sm font-medium rounded-md border {{ $term && $term->id === $t->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
            {{ $t->name }}
          </a>
        @endforeach
      </div>

      @include('pages.scorebook._incomplete-warning', ['incomplete' => $incomplete ?? collect(), 'duplicates' => $duplicates ?? collect()])

      <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <p class="text-sm text-gray-600">Pupils ranked by term total ({{ $offering->displayName() }}{{ $term ? ' · '.$term->name : '' }}). Subjects set to not count toward the total are excluded.</p>
        <div class="flex flex-wrap items-center gap-2">
          <a href="{{ route('term-report.prepare', ['offering' => $offering, 'term' => $term?->id]) }}"
            class="inline-flex items-center px-3 py-1.5 text-xs font-bold tracking-wide uppercase bg-indigo-600 border border-indigo-600 rounded-md text-white hover:bg-indigo-700">Prepare reports</a>
          <a href="{{ route('scorebook.result-sheet', ['offering' => $offering, 'term' => $term?->id]) }}" target="_blank" rel="noopener"
            class="inline-flex items-center px-3 py-1.5 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">Result sheet</a>
          <a href="{{ route('report-book.print-for-class', $offering) }}" target="_blank" rel="noopener"
            class="inline-flex items-center px-3 py-1.5 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">Report books (class)</a>
          <a href="{{ route('report-book.print-for-class', ['offering' => $offering, 'empty' => 1]) }}" target="_blank" rel="noopener"
            class="inline-flex items-center px-3 py-1.5 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Blank booklets</a>
        </div>
      </div>

      @if ($ranked->isEmpty())
        <p class="text-sm text-gray-500 italic">No pupils enrolled in this class.</p>
      @else
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-gray-50 text-left text-gray-600">
                <th class="px-4 py-2 font-semibold border-b border-gray-200 w-20">Position</th>
                <th class="px-4 py-2 font-semibold border-b border-gray-200">Pupil</th>
                <th class="px-4 py-2 font-semibold text-right border-b border-gray-200 w-28">Total</th>
                <th class="px-4 py-2 font-semibold text-right border-b border-gray-200 w-28">Average</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($ranked as $row)
                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                  <td class="px-4 py-2 border-b border-gray-200 font-semibold {{ $row['position'] <= 3 ? 'text-indigo-700' : 'text-gray-700' }}">{{ $row['position'] }}</td>
                  <td class="px-4 py-2 border-b border-gray-200 text-gray-900">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</td>
                  <td class="px-4 py-2 text-right border-b border-gray-200 font-medium text-gray-900">{{ $row['total'] }}</td>
                  <td class="px-4 py-2 text-right border-b border-gray-200 text-gray-600">{{ $row['average'] }}%</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    @else
      <p class="text-sm text-gray-500 italic">This class's school year has no terms set up.</p>
    @endif

  </div>
</x-page>
