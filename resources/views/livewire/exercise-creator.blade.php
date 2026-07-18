<div class="w-full px-4 sm:w-3/4 sm:px-0 mx-auto">
  <div class="px-4 py-6 bg-white">

    {{-- Step indicator --}}
    <div class="flex items-center justify-center gap-2 mb-6">
      @foreach(['Skill', 'Questions', 'Review'] as $i => $label)
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold
          {{ $formstep >= $i ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-500' }}">
          {{ $i + 1 }}
        </div>
        <span class="text-sm {{ $formstep >= $i ? 'text-gray-900 font-medium' : 'text-gray-500' }}">{{ $label }}</span>
        @if($i < 2)
        <div class="w-8 h-px {{ $formstep > $i ? 'bg-gray-800' : 'bg-gray-200' }}"></div>
        @endif
      </div>
      @endforeach
    </div>

    {{-- ==================== Step 1: Pick skill ==================== --}}
    @if($formstep === 0)
    <div>
      <h2 class="text-lg font-medium leading-6 text-gray-900">What skill is this exercise for?</h2>
      <p class="mt-1 text-sm text-gray-500">Pick the skill your students will practice.</p>

      <div class="mt-6 space-y-6">
        @foreach($skillsByGrade as $grade => $skills)
        <div>
          <h3 class="text-xs font-medium uppercase tracking-wider text-gray-500 mb-2">{{ $grade }}</h3>
          <div class="space-y-1">
            @foreach($skills as $skill)
            <button type="button" wire:click="selectSkill({{ $skill->id }})"
              class="w-full text-left px-4 py-3 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 transition
                {{ $skillId == $skill->id ? 'bg-gray-100 border-gray-900' : 'bg-white' }}">
              <span class="text-sm font-medium text-gray-900">{{ $skill->name }}</span>
            </button>
            @endforeach
          </div>
        </div>
        @endforeach

        @if($skillsByGrade->isEmpty())
        <p class="text-sm text-gray-500 py-4">No skills available. Skills need to be set up first.</p>
        @endif
      </div>
    </div>
    @endif

    {{-- ==================== Step 2: Questions ==================== --}}
    @if($formstep === 1)
    <div>
      <div class="flex items-center justify-between mb-1">
        <h2 class="text-lg font-medium leading-6 text-gray-900">{{ $exerciseTitle }}</h2>
        <span class="text-xs text-gray-500">{{ $gradeName }} &middot; {{ $subjectName }}</span>
      </div>
      <p class="mt-1 text-sm text-gray-500 mb-6">
        @if(count($questions) === 0)
          Write your first question below.
        @else
          {{ count($questions) }} question{{ count($questions) !== 1 ? 's' : '' }} so far.
          @if($currentQuestion >= count($questions))
            Add another or review.
          @endif
        @endif
      </p>

      {{-- Existing questions list --}}
      @if(count($questions) > 0)
      <div class="space-y-2 mb-6">
        @foreach($questions as $qi => $q)
        <div class="flex items-center border border-gray-200 rounded-md bg-gray-50 px-4 py-2">
          <span class="text-xs font-medium text-gray-500 mr-3">Q{{ $qi + 1 }}</span>
          <span class="flex-1 text-sm text-gray-700 truncate">{{ $q['question_text'] }}</span>
          <button type="button" wire:click="editQuestion({{ $qi }})"
            class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1">Edit</button>
          <button type="button" wire:click="removeQuestion({{ $qi }})"
            class="text-xs text-red-600 hover:text-red-600 px-2 py-1">Remove</button>
        </div>
        @endforeach
      </div>
      @endif

      {{-- Current question editor --}}
      <div class="border border-gray-300 rounded-md p-4 bg-white">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-medium text-gray-500">
            @if($currentQuestion < count($questions))
              Editing Q{{ $currentQuestion + 1 }}
            @else
              New question
            @endif
          </span>
          <select wire:model.live="questionType"
            class="px-2 py-1 text-xs bg-white border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
            <option value="radio">Multiple choice</option>
            <option value="numeric_input">Number answer</option>
          </select>
        </div>

        {{-- Question text --}}
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
          <textarea wire:model.live.debounce.500ms="questionText" rows="2"
            placeholder="Type your question here"
            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm"></textarea>
        </div>

        {{-- Multiple choice --}}
        @if($questionType === 'radio')
        <div class="space-y-2 mb-4">
          <label class="block text-sm font-medium text-gray-700">Answer choices</label>
          <p class="text-xs text-gray-500 mb-2">Select the correct answer.</p>
          @foreach($choices as $ci => $choice)
          <div class="flex items-center gap-2">
            <input type="radio" wire:model="correctChoice" value="{{ $ci }}"
              aria-label="Mark answer {{ $ci + 1 }} as correct"
              class="h-4 w-4 border-gray-300 text-gray-900">
            <input type="text" wire:model.live.debounce.500ms="choices.{{ $ci }}"
              placeholder="Answer {{ $ci + 1 }}"
              aria-label="Answer choice {{ $ci + 1 }}"
              class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          @endforeach
          @if(count($choices) < 4)
          <button type="button" wire:click="addChoice"
            class="text-xs text-gray-500 hover:text-gray-700 mt-1">+ Add choice</button>
          @endif
        </div>
        @endif

        {{-- Number answer --}}
        @if($questionType === 'numeric_input')
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Correct answer</label>
          <input type="text" wire:model.live.debounce.500ms="correctAnswer"
            placeholder="e.g. 7"
            class="w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        @endif
      </div>

      {{-- Actions --}}
      <div class="flex items-center justify-between mt-6">
        <button type="button" wire:click="backToSkills"
          class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 bg-white border border-transparent rounded-md shadow-sm hover:bg-gray-50">
          Back
        </button>
        <div class="flex items-center gap-3">
          <button type="button" wire:click="nextQuestion"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
            @if($currentQuestion < count($questions))
              Save &amp; next
            @else
              + Add question
            @endif
          </button>
          <button type="button" wire:click="goToReview"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
            Review
          </button>
        </div>
      </div>
    </div>
    @endif

    {{-- ==================== Step 3: Review ==================== --}}
    @if($formstep === 2)
    <div>
      <h2 class="text-lg font-medium leading-6 text-gray-900">{{ $exerciseTitle }}</h2>
      <p class="mt-1 text-sm text-gray-500 mb-6">
        {{ count($questions) }} questions &middot; {{ $gradeName }} &middot; {{ $subjectName }}
      </p>

      <div class="space-y-4">
        @foreach($questions as $qi => $q)
        <div class="border border-gray-200 rounded-md p-4 bg-gray-50">
          <div class="flex items-start justify-between">
            <p class="text-sm font-medium text-gray-900">
              <span class="text-gray-500 mr-1">Q{{ $qi + 1 }}.</span>
              {{ $q['question_text'] }}
            </p>
          </div>
          @if($q['question_type'] === 'radio')
          <div class="mt-2 space-y-1">
            @foreach($q['choices'] as $choice)
            <div class="flex items-center gap-2 text-sm">
              @if($choice['correct'])
              <span class="w-4 h-4 rounded-full bg-green-500 flex items-center justify-center">
                <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
              </span>
              <span class="text-gray-900 font-medium">{{ $choice['text'] }}</span>
              @else
              <span class="w-4 h-4 rounded-full border border-gray-300"></span>
              <span class="text-gray-500">{{ $choice['text'] }}</span>
              @endif
            </div>
            @endforeach
          </div>
          @else
          <p class="mt-2 text-sm text-gray-500">Answer: <span class="font-medium text-gray-900">{{ $q['correct_answer'] }}</span></p>
          @endif
        </div>
        @endforeach
      </div>

      {{-- Advanced settings (collapsed by default) --}}
      <details class="mt-6 border border-gray-200 rounded-lg" x-data>
        <summary class="px-4 py-3 text-sm font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700">
          Advanced settings
        </summary>
        <div class="px-4 pb-4 space-y-4 border-t border-gray-200 pt-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Exercise title</label>
            <input type="text" wire:model.blur="exerciseTitle"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
            <textarea wire:model.blur="description" rows="2"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm"
              placeholder="Brief description of this exercise"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Correct answers needed</label>
              <input type="number" wire:model.blur="masteryM" min="1" max="10"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Out of last</label>
              <input type="number" wire:model.blur="masteryN" min="1" max="20"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
            </div>
          </div>
          <label class="inline-flex items-center">
            <input type="checkbox" wire:model="randomize"
              class="h-4 w-4 rounded border-gray-300 text-gray-900">
            <span class="ml-2 text-sm text-gray-700">Randomize question order</span>
          </label>
        </div>
      </details>

      <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-200">
        <button type="button" wire:click="backToQuestions"
          class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 bg-white border border-transparent rounded-md shadow-sm hover:bg-gray-50">
          Back
        </button>
        <button type="button" wire:click="save"
          class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
          Save
        </button>
      </div>
    </div>
    @endif

    {{-- Flash messages --}}
    @if(session()->has('error'))
    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
      {{ session('error') }}
    </div>
    @endif

  </div>
</div>
