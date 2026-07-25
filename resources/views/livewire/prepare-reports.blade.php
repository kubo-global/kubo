<div class="px-6 py-6 lg:px-8">

  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <nav class="text-sm text-gray-500">
      <a href="{{ route('reporting.grades') }}" class="text-indigo-600 hover:underline">Scorebook</a>
      <span class="mx-1 text-gray-500">&rsaquo;</span>
      <a href="{{ route('scorebook.class', $offering) }}" class="text-indigo-600 hover:underline">{{ $offering->displayName() }}</a>
      <span class="mx-1 text-gray-500">&rsaquo;</span>
      <span class="text-gray-700">Prepare reports</span>
    </nav>
    @include('pages.scorebook._mode-switch', ['offering' => $offering, 'active' => 'prepare'])
  </div>

  <h1 class="text-lg font-semibold text-gray-900">Prepare reports</h1>
  <p class="text-sm text-gray-500 mb-4 max-w-2xl">
    Type each pupil's conduct and general remark. They save as you go and print on the report card,
    so there is less to write by hand. Position, average and grades are filled in automatically.
  </p>

  @include('pages.scorebook._incomplete-warning', ['incomplete' => $incomplete ?? collect(), 'duplicates' => $duplicates ?? collect()])

  @if (session('empty-cells-marked'))
    <div class="p-3 mb-4 text-sm rounded-lg text-green-800 bg-green-50 ring-1 ring-green-200">
      {{ session('empty-cells-marked') }} empty {{ \Illuminate\Support\Str::plural('cell', session('empty-cells-marked')) }} marked absent.
    </div>
  @endif
  @if (($emptyCells ?? collect())->isNotEmpty())
    @php $emptyTotal = $emptyCells->sum(fn ($c) => count($c['user_ids'])); @endphp
    <div class="flex flex-wrap items-center justify-between gap-3 p-3 mb-4 text-sm rounded-lg text-amber-900 bg-amber-50 ring-1 ring-amber-200">
      <div>
        <b>{{ $emptyTotal }} empty {{ \Illuminate\Support\Str::plural('cell', $emptyTotal) }}</b> in tests/exams that already have marks
        ({{ $emptyCells->map(fn ($c) => $c['subject'].' '.$c['name'].' ('.count($c['user_ids']).')')->join(', ') }}).
        Empty is skipped in the average; absent counts as 0.
      </div>
      <button type="button" wire:click="markEmptyCellsAbsent"
        wire:confirm="Mark all {{ $emptyTotal }} empty cells as absent? This counts them as 0 on the report."
        class="px-3 py-1.5 font-semibold rounded-md text-white bg-amber-600 hover:bg-amber-700 whitespace-nowrap">Mark them absent</button>
      @error('emptyCells')<span class="w-full text-red-700">{{ $message }}</span>@enderror
    </div>
  @endif

  @if ($terms->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2 mb-5">
      <span class="text-sm text-gray-500">Term</span>
      @foreach ($terms as $t)
        <button type="button" wire:click="$set('termId', {{ $t->id }})"
          class="px-3 py-1.5 text-sm font-medium rounded-md border {{ $term && $term->id === $t->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
          {{ $t->name }}
        </button>
      @endforeach
    </div>
  @endif

  @if ($rows->isEmpty())
    <p class="text-sm text-gray-500 italic">No pupils ranked for this term yet. Enter marks first.</p>
  @else
    <p class="text-xs text-gray-500 mb-3">
      Pupils are listed by position. Position and average appear once both a test and an exam are entered.
    </p>
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
            <th class="px-3 py-2 w-10">#</th>
            <th class="px-3 py-2">Pupil</th>
            <th class="px-3 py-2 w-16">Avg</th>
            <th class="px-3 py-2">Conduct</th>
            <th class="px-3 py-2">General remark</th>
            <th class="px-3 py-2 w-16"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @foreach ($rows as $row)
            @php $id = $row['enrollment_id']; @endphp
            {{-- saveRemark is renderless (saving must not rebuild the class ranking),
                 so the "Saved" flash is Alpine state resolved from the $wire promise. --}}
            <tr wire:key="row-{{ $id }}" x-data="{ saved: false }"
              x-on:blur.capture="$wire.saveRemark({{ $id }}).then(() => { saved = true; setTimeout(() => saved = false, 2000) })">
              <td class="px-3 py-2 text-gray-500">{{ $row['position'] }}</td>
              <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">
                {{ $row['student']->first_name }} {{ $row['student']->last_name }}
              </td>
              <td class="px-3 py-2 text-gray-600">{{ $row['average'] ?: '—' }}</td>
              <td class="px-3 py-2">
                <input type="text" wire:model="conduct.{{ $id }}"
                  placeholder="e.g. Good"
                  class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
              </td>
              <td class="px-3 py-2">
                <textarea wire:model="remarks.{{ $id }}" rows="1"
                  placeholder="A short remark about the pupil"
                  class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900"></textarea>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span class="text-xs text-green-600" x-show="saved" x-cloak>Saved</span>
                <a href="{{ route('term-report.print', [$id, $termId]) }}" target="_blank" rel="noopener"
                  class="ml-2 text-xs text-indigo-600 hover:underline">Print</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <a href="{{ route('term-report.print-for-class', [$offering->schoolyear_id, $termId, $offering->grade_id]) }}" target="_blank" rel="noopener"
        class="inline-flex items-center px-3 py-1.5 text-xs font-bold tracking-wide uppercase bg-white border border-gray-300 rounded-md text-indigo-700 hover:bg-gray-50">
        Print all reports (class)
      </a>
    </div>
  @endif
</div>
