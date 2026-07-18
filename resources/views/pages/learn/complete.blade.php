<x-page :title="'Nice work!'">
  <div class="flex items-center justify-center" style="min-height: calc(100vh - 64px)">
    <div class="text-center px-4 py-12 max-w-sm">
      <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6" style="background: #22c55e">
        <svg width="32" height="32" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
      </div>

      @if($mode === 'homework')
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Homework done!</h1>
      @else
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Nice work!</h1>
      @endif

      @if($run && $run->total_questions > 0)
      <p class="text-lg font-semibold text-gray-900 mb-2">
        {{ $run->correct_answers }}/{{ $run->total_questions }} correct
      </p>
      @if($run->score >= 80)
      <p class="text-sm text-green-600 mb-8">Great job!</p>
      @elseif($run->score >= 50)
      <p class="text-sm text-gray-500 mb-8">Keep practising, you're getting better!</p>
      @else
      <p class="text-sm text-gray-500 mb-8">Take your time with each question. You'll get there!</p>
      @endif
      @else
      <div class="mb-8"></div>
      @endif

      @if($run && $run->status === 'score_pending')
      <div class="p-4 mb-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
        <p>Your score could not be loaded right now. It will appear later.</p>
      </div>
      @endif

      @if($nextSkill)
      <form method="POST" action="{{ route('learn.start', $nextSkill) }}">
        @csrf
        <input type="hidden" name="mode" value="free">
        <button type="submit"
           class="block w-full px-6 py-3 text-center font-semibold text-white rounded-lg mb-3 border-0 cursor-pointer" style="background: #2563eb">
          Continue practising
        </button>
      </form>
      @endif
      <a href="{{ route('learn.index') }}" class="block text-sm text-gray-500 hover:text-gray-600">
        Back to overview
      </a>
    </div>
  </div>
</x-page>
