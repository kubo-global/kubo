<x-page :title="$assessment->name . ' — Scores'">
  <div class="w-full mx-auto px-4 py-8 xl:w-2/3 sm:px-6 lg:px-8">
    <h2 class="text-lg font-medium leading-6 text-gray-900">{{ $assessment->name }}</h2>
    <p class="mt-1 text-sm text-gray-500 mb-4">Enter scores for each student. Maximum: {{ $assessment->max_score }}.</p>

    @php
        $termLocked = $assessment->term?->isLocked();
        $canOverrideLock = auth()->user()?->hasAnyRole(['headmaster', 'admin']);
    @endphp
    @if($termLocked && !$canOverrideLock)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-md text-sm text-amber-800">
            <strong>Read-only — {{ $assessment->term->name }} is closed.</strong>
            Ask the headmaster to enter or correct scores.
        </div>
    @elseif($termLocked && $canOverrideLock)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-md text-sm text-amber-800">
            <strong>{{ $assessment->term->name }} is closed</strong> — you're editing past-term scores. Make sure this is intentional.
        </div>
    @endif

    @if($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
      {{ $errors->first('validation') }}
    </div>
    @endif

    <form method="POST" action="{{ route('reporting.assessment.saveScores', $assessment) }}">
      @csrf
      <div class="overflow-x-auto border border-gray-200 shadow sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="w-1/2 px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">Name</th>
              <th class="w-1/4 px-6 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase bg-gray-50">Score on {{ $assessment->max_score }}</th>
              <th class="w-1/4 px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">Absent</th>
            </tr>
          </thead>
          <tbody>
            @foreach($students as $student)
            @php
              $existing = $existingScores->get($student->id);
              $isAbsent = $existing?->absent ?? false;
              $currentScore = $existing?->score;
            @endphp
            <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }} hover:bg-indigo-50"
                x-data="{ absent: {{ $isAbsent ? 'true' : 'false' }} }">
              <td class="py-4 pl-6 text-sm font-medium text-gray-900">
                {{ $student->first_name }} {{ $student->last_name }}
              </td>
              <td class="px-6 py-4 text-center">
                <input type="number" min="0" max="{{ $assessment->max_score }}"
                  name="scores[{{ $student->id }}]"
                  value="{{ old("scores.{$student->id}", $currentScore) }}"
                  x-show="!absent"
                  @if($termLocked && !$canOverrideLock) disabled @endif
                  aria-label="Score for {{ $student->first_name }} {{ $student->last_name }}"
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm disabled:bg-gray-50 disabled:text-gray-500">
                <span x-show="absent" class="text-indigo-700 text-sm">absent</span>
              </td>
              <td class="px-6 py-4">
                <input type="checkbox"
                  name="absent[{{ $student->id }}]"
                  aria-label="Mark {{ $student->first_name }} {{ $student->last_name }} absent"
                  @checked($isAbsent)
                  @click="absent = !absent"
                  @if($termLocked && !$canOverrideLock) disabled @endif
                  class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 disabled:bg-gray-50">
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="mt-4 text-right">
        @if(!$termLocked || $canOverrideLock)
          <button type="submit"
            class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
            Next
          </button>
        @endif
      </div>
    </form>
  </div>
</x-page>
