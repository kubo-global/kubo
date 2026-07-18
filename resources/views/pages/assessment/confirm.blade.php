<x-page :title="$assessment->name . ' — Confirm'">
  <div class="w-full mx-auto px-4 py-8 xl:w-2/3 sm:px-6 lg:px-8">
    <h2 class="text-lg font-medium leading-6 text-gray-900">{{ $assessment->name }}</h2>
    <p class="mt-1 text-sm text-gray-500 mb-4">Review and confirm.</p>

    <div class="overflow-x-auto border border-gray-200 shadow sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead>
          <tr>
            <th class="w-1/2 px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">Name</th>
            <th class="w-1/4 px-6 py-3 text-xs font-medium tracking-wider text-right text-gray-500 uppercase bg-gray-50">Score</th>
            <th class="w-1/4 px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">Percentage</th>
          </tr>
        </thead>
        <tbody>
          @foreach($students as $student)
          @php $score = $scores->get($student->id); @endphp
          <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }}">
            <td class="py-4 pl-6 text-sm font-medium text-gray-900">
              {{ $student->first_name }} {{ $student->last_name }}
            </td>
            <td class="px-6 py-4 text-right text-sm">
              @if($score?->absent)
                <span class="text-indigo-700">absent</span>
              @elseif($score)
                {{ $score->score }}/{{ $assessment->max_score }}
              @else
                —
              @endif
            </td>
            <td class="px-6 py-4 text-sm">
              @if($score && !$score->absent && $assessment->max_score > 0)
                {{ round($score->score / $assessment->max_score * 100) }}%
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between mt-6">
      <a href="{{ route('reporting.assessment.scores', $assessment) }}"
        class="text-sm text-gray-500 hover:text-gray-700">
        &larr; Back to scores
      </a>
      <form method="POST" action="{{ route('reporting.assessment.finalize', $assessment) }}">
        @csrf
        <button type="submit"
          class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
          Confirm
        </button>
      </form>
    </div>
  </div>
</x-page>
