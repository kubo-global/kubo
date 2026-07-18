<x-page :title="'Learn'">
  <div class="px-4 py-8 sm:px-6 lg:px-8 max-w-lg mx-auto">

    @php
      // System picks the best practice skill: review due > frontier > fallback
      $practiceSkill = (isset($reviewSkill) && $reviewSkill) ? $reviewSkill : $nextSkill;
      $practiceMode = ($practiceSkill && isset($reviewSkill) && $reviewSkill && $practiceSkill->id === $reviewSkill->id) ? 'review' : 'free';
    @endphp

    @if($assignedSkill || $practiceSkill)

    {{-- Homework -- prominent, do this first --}}
    @if($assignedSkill)
    <form method="POST" action="{{ route('learn.start', $assignedSkill) }}" class="mb-4">
      @csrf
      <input type="hidden" name="mode" value="homework">
      <button type="submit"
         class="w-full text-left rounded-xl overflow-hidden hover:shadow-md transition-shadow cursor-pointer border-0" style="background: #eef2ff; border: 2px solid #c7d2fe">
        <div class="flex items-center p-5">
          <div class="flex-1">
            <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: #6366f1">Homework</p>
            <p class="font-semibold text-gray-900 text-lg">{{ $assignedSkill->name }}</p>
          </div>
          <div class="w-12 h-12 rounded-full flex items-center justify-center ml-3" style="background: #6366f1">
            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M8 5v14l11-7z"/>
            </svg>
          </div>
        </div>
      </button>
    </form>
    @endif

    {{-- Practice -- one button, system picks what's best --}}
    @if($practiceSkill && (!$assignedSkill || $practiceSkill->id !== $assignedSkill->id))
    <form method="POST" action="{{ route('learn.start', $practiceSkill) }}">
      @csrf
      <input type="hidden" name="mode" value="{{ $practiceMode }}">
      <button type="submit"
         class="w-full text-left rounded-xl overflow-hidden hover:shadow-md transition-shadow cursor-pointer border-0" style="background: #f0fdf4; border: 2px solid #bbf7d0">
        <div class="flex items-center p-5">
          <div class="flex-1">
            <p class="font-semibold text-gray-900 text-lg">Practice</p>
          </div>
          <div class="w-12 h-12 rounded-full flex items-center justify-center ml-3" style="background: #10b981">
            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M8 5v14l11-7z"/>
            </svg>
          </div>
        </div>
      </button>
    </form>
    @endif

    @elseif($diagnosis)
    <div class="text-center py-16">
      <div class="text-5xl mb-4">&#127881;</div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">All done!</h2>
      <p class="text-sm text-gray-500">Great work. Check back later for more exercises.</p>
    </div>

    @else
    <div class="text-center py-16">
      <p class="text-gray-500">No exercises available yet.</p>
    </div>
    @endif

    {{-- Browse all skills -- tucked away for older students --}}
    @if(isset($allSkills) && $allSkills->count() > 0)
    <details class="mt-10">
      <summary class="text-xs font-semibold uppercase tracking-wider text-gray-500 ml-1 cursor-pointer select-none flex items-center gap-1">
        <svg width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2.5" viewBox="0 0 24 24" class="details-arrow" style="transition:transform 0.15s">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        Browse all skills
      </summary>
      <div class="space-y-2 rounded-lg mt-3">
        @foreach($allSkills as $skill)
        <a href="{{ route('learn.skill', $skill) }}"
           class="flex items-center p-3 bg-white border border-gray-200 rounded-lg hover:shadow-sm transition-shadow">
          <span class="text-sm font-medium text-gray-700">{{ $skill->name }}</span>
        </a>
        @endforeach
      </div>
    </details>

    @if(isset($previousGradeSkills) && $previousGradeSkills->count() > 0)
    @foreach($previousGradeSkills as $gradeId => $skills)
    <details class="mt-4">
      <summary class="text-xs font-semibold uppercase tracking-wider text-gray-500 ml-1 cursor-pointer select-none flex items-center gap-1">
        <svg width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2.5" viewBox="0 0 24 24" class="details-arrow" style="transition:transform 0.15s">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        {{ $skills->first()->grade->name ?? 'Previous' }}
      </summary>
      <div class="space-y-2 rounded-lg mt-3">
        @foreach($skills as $skill)
        <a href="{{ route('learn.skill', $skill) }}"
           class="flex items-center p-3 bg-white border border-gray-200 rounded-lg hover:shadow-sm transition-shadow">
          <span class="text-sm font-medium text-gray-700">{{ $skill->name }}</span>
        </a>
        @endforeach
      </div>
    </details>
    @endforeach
    @endif

    <style>
      details[open] .details-arrow { transform: rotate(90deg); }
    </style>
    @endif

  </div>
</x-page>
