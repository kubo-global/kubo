<x-page :title="$skill->name">
  <div class="px-4 py-8 sm:px-6 lg:px-8 max-w-lg mx-auto">

    <a href="{{ route('learn.index') }}" class="text-sm text-gray-500 hover:text-gray-600 mb-3 inline-block">&larr; Back</a>

    <h1 class="text-xl font-bold text-gray-900">{{ $skill->name }}</h1>
    @if($skill->grade)
    <p class="text-sm text-gray-500">{{ $skill->grade->name }}</p>
    @endif
    @if($skill->description)
    <p class="text-sm text-gray-600 mt-2">{{ $skill->description }}</p>
    @endif

    {{-- Start practice --}}
    @if($hasExercise)
    <form method="POST" action="{{ route('learn.start', $skill) }}">
      @csrf
      @if($isAssigned)
      <input type="hidden" name="mode" value="homework">
      @elseif($isReviewDue)
      <input type="hidden" name="mode" value="review">
      @else
      <input type="hidden" name="mode" value="free">
      @endif
      <button type="submit"
         class="flex items-center justify-center w-full p-4 mt-6 font-semibold text-white rounded-lg hover:opacity-90 transition border-0 cursor-pointer"
         style="background: #2563eb">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        Start practice
      </button>
    </form>
    @else
    <p class="mt-6 text-sm text-gray-500">No exercise available for this skill yet.</p>
    @endif

    {{-- Exercise history --}}
    @if($runs->count() > 0)
    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2 ml-1 mt-8">Your attempts</p>
    <div class="space-y-2">
      @foreach($runs as $run)
      <div class="flex items-center p-3 bg-white border border-gray-200 rounded-lg">
        <div class="flex-1">
          <p class="text-sm font-medium text-gray-900">
            {{ $run->correct_answers }}/{{ $run->total_questions }} correct
            @if($run->total_questions > 0)
            ({{ round($run->score) }}%)
            @endif
          </p>
          <p class="text-xs text-gray-500">
            {{ $run->completed_at->diffForHumans() }}
          </p>
        </div>
        <div class="flex gap-1">
          @for($i = 0; $i < min($run->correct_answers, 10); $i++)
          <div class="w-2.5 h-2.5 rounded-full" style="background: #22c55e"></div>
          @endfor
          @for($i = 0; $i < min($run->wrong_answers, 10); $i++)
          <div class="w-2.5 h-2.5 rounded-full" style="background: #ef4444"></div>
          @endfor
        </div>
      </div>
      @endforeach
    </div>
    @endif

  </div>
</x-page>
