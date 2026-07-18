<x-page title="New School Year">
  <div class="w-full px-4 py-8 sm:w-3/4 sm:px-0 mx-auto">
    <h2 class="text-lg font-medium text-gray-900">Create a new school year</h2>
    <p class="mt-1 text-sm text-gray-500 mb-6">This will create the school year, terms, and one class per grade.</p>

    @if($previousYear)
    <div class="p-3 mb-6 bg-indigo-50 border border-indigo-200 rounded-lg text-sm text-indigo-800">
      Previous year: <strong>{{ $previousYear->name }}</strong>
      ({{ $previousYear->start->format('M Y') }} – {{ $previousYear->end->format('M Y') }})
    </div>
    @endif

    <form method="POST" action="{{ route('rollover.store') }}">
      @csrf

      <div class="space-y-6">
        {{-- School year --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
            <input required type="text" name="name" id="name"
              value="{{ old('name', $previousYear ? (($previousYear->start->year + 1) . ' - ' . ($previousYear->end->year + 1)) : '') }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          <div>
            <label for="start" class="block text-sm font-medium text-gray-700">Start date</label>
            <input required type="date" name="start" id="start"
              value="{{ old('start', $previousYear ? $previousYear->start->addYear()->format('Y-m-d') : '') }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          <div>
            <label for="end" class="block text-sm font-medium text-gray-700">End date</label>
            <input required type="date" name="end" id="end"
              value="{{ old('end', $previousYear ? $previousYear->end->addYear()->format('Y-m-d') : '') }}"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
        </div>

        {{-- Terms --}}
        <div>
          <h3 class="text-sm font-medium text-gray-700 mb-3">Terms</h3>
          @foreach(['1', '2', '3'] as $i)
          @php
            $prevTerms = $previousYear ? $previousYear->terms()->orderBy('start')->get() : collect();
            $prevTerm = $prevTerms->get($i - 1);
          @endphp
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-3">
            <div class="text-sm font-medium text-gray-500 flex items-center">Term {{ $i }}</div>
            <div>
              <label for="term{{ $i }}_start" class="sr-only">Term {{ $i }} start</label>
              <input required type="date" name="term{{ $i }}_start" id="term{{ $i }}_start"
                value="{{ old("term{$i}_start", $prevTerm ? $prevTerm->start->addYear()->format('Y-m-d') : '') }}"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
            </div>
            <div>
              <label for="term{{ $i }}_end" class="sr-only">Term {{ $i }} end</label>
              <input required type="date" name="term{{ $i }}_end" id="term{{ $i }}_end"
                value="{{ old("term{$i}_end", $prevTerm ? $prevTerm->end->addYear()->format('Y-m-d') : '') }}"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
            </div>
          </div>
          @endforeach
        </div>

        {{-- Copy from --}}
        <div>
          <label for="copy_from" class="block text-sm font-medium text-gray-700">Copy teachers and subjects from</label>
          <select name="copy_from" id="copy_from"
            class="block w-full px-3 py-2 mt-1 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
            <option value="">Don't copy</option>
            @foreach($years as $year)
            <option value="{{ $year->id }}" @selected($previousYear && $year->id === $previousYear->id)>
              {{ $year->name }}
            </option>
            @endforeach
          </select>
          <p class="text-xs text-gray-500 mt-1">Copies teacher assignments and subject-per-term mappings to the new year.</p>
        </div>

        <div class="text-right pt-4 border-t border-gray-200">
          <button type="submit"
            class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
            Create &amp; promote students
          </button>
        </div>
      </div>
    </form>
  </div>
</x-page>
