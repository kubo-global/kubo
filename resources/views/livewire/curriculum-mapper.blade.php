<div class="mx-6 mt-8" wire:key="mapper-{{ $subjectId }}-{{ $gradeId }}">

  <div class="flex flex-col gap-1 mb-6">
    <h1 class="text-lg font-semibold text-gray-900">Exercise &amp; video mapping</h1>
    <p class="text-sm text-gray-500 max-w-2xl">
      Attach Kolibri exercises and videos to each skill in the curriculum. Exercises become practice;
      videos become watch-first teaching resources. Pupils see only what you approve.
    </p>
  </div>

  {{-- Pickers --}}
  <div class="flex flex-wrap items-end gap-3 mb-6">
    <label class="flex flex-col gap-1">
      <span class="text-xs font-medium text-gray-500">Subject</span>
      <select wire:model.live="subjectId"
        class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        @foreach($subjects as $subject)
          <option value="{{ $subject->id }}">{{ $subject->name }}</option>
        @endforeach
      </select>
    </label>

    <label class="flex flex-col gap-1">
      <span class="text-xs font-medium text-gray-500">Grade</span>
      <select wire:model.live="gradeId"
        class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
        @foreach($grades as $grade)
          <option value="{{ $grade->id }}">{{ $grade->name }}</option>
        @endforeach
      </select>
    </label>
  </div>

  @if(session('mapper'))
  <div class="mb-4 px-4 py-2.5 text-sm text-green-800 bg-green-50 border border-green-200 rounded-md" role="status">
    {{ session('mapper') }}
  </div>
  @endif

  @if($skills->isEmpty())
  <div class="p-8 text-center text-gray-500 border border-gray-200 rounded-md">
    No skills for this grade and subject yet.
  </div>
  @else

  <div class="grid grid-cols-1 lg:grid-cols-[20rem_1fr] gap-6 items-start">

    {{-- Skill list --}}
    <div class="border border-gray-200 rounded-lg overflow-hidden">
      <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
        Skills
      </div>
      <ul class="divide-y divide-gray-100 max-h-[70vh] overflow-y-auto">
        @foreach($skills as $skill)
          @php
            $active = $skill->content->filter(fn ($m) => $m->pivot->approved);
            $exCount = $active->where('content_kind', 'exercise')->count();
            $vidCount = $active->where('content_kind', '!=', 'exercise')->count();
            $asideCount = $skill->content->count() - $active->count();
            $isSelected = $selected && $selected->id === $skill->id;
          @endphp
          <li wire:key="skill-{{ $skill->id }}">
            <button type="button" wire:click="selectSkill({{ $skill->id }})"
              class="w-full text-left px-4 py-3 flex items-center justify-between gap-2 hover:bg-gray-50 {{ $isSelected ? 'bg-blue-50 hover:bg-blue-50' : '' }}">
              <span class="text-sm {{ $isSelected ? 'font-semibold text-gray-900' : 'text-gray-800' }} truncate">{{ $skill->name }}</span>
              @if($exCount || $vidCount || $asideCount)
              <span class="flex items-center gap-1.5 shrink-0 text-xs text-gray-500">
                @if($exCount)<span class="px-1.5 py-0.5 rounded bg-gray-100">{{ $exCount }} ex</span>@endif
                @if($vidCount)<span class="px-1.5 py-0.5 rounded bg-gray-100">{{ $vidCount }} vid</span>@endif
                @if($asideCount)<span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700" title="{{ $asideCount }} set aside">{{ $asideCount }} set aside</span>@endif
              </span>
              @else
              <span class="shrink-0 text-xs text-amber-600">no content</span>
              @endif
            </button>
          </li>
        @endforeach
      </ul>
    </div>

    {{-- Detail --}}
    <div>
      @if(!$selected)
      <div class="p-10 text-center text-gray-500 border border-dashed border-gray-300 rounded-lg">
        Select a skill on the left to see and edit its exercises and videos.
      </div>
      @else

      <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $selected->name }}</h2>

      {{-- Mapped content --}}
      <div class="space-y-6">
        @foreach([['label' => 'Practice exercises', 'items' => $exercises, 'empty' => 'No exercises yet.'], ['label' => 'Teaching videos & resources', 'items' => $videos, 'empty' => 'No videos yet.']] as $group)
        <div>
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ $group['label'] }}</h3>
          @if($group['items']->isEmpty())
          <p class="text-sm text-gray-400 ml-0.5">{{ $group['empty'] }}</p>
          @else
          <div class="space-y-1.5">
            @foreach($group['items'] as $map)
            <div wire:key="map-{{ $map->id }}" class="p-3 bg-white border border-gray-200 rounded-lg">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-sm text-gray-900 truncate {{ $map->pivot->approved ? '' : 'line-through text-gray-400' }}">
                    {{ $map->title ?: ucfirst($map->content_kind) }}
                  </p>
                  <p class="text-xs {{ $map->pivot->approved ? 'text-gray-500' : 'text-amber-700 font-medium' }}">{{ ucfirst($map->content_kind) }}{{ $map->pivot->approved ? '' : ' · set aside, not shown to pupils' }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <button type="button"
                    onclick="document.getElementById('preview-frame').src='/kolibri-proxy/learn/#/topics/c/{{ $map->kolibri_node_id }}'; document.getElementById('preview-modal').classList.remove('hidden')"
                    class="text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">Preview</button>
                  <button type="button" wire:click="editNote({{ $map->id }})"
                    class="text-xs px-2.5 py-1.5 rounded-md border {{ $map->note ? 'text-amber-700 border-amber-200 hover:bg-amber-50' : 'text-gray-600 border-gray-200 hover:bg-gray-50' }}">Remark</button>
                  <button type="button" wire:click="toggleApproval({{ $map->id }}, {{ $map->pivot->approved ? 'false' : 'true' }})"
                    class="text-xs px-2.5 py-1.5 rounded-md border {{ $map->pivot->approved ? 'text-amber-600 border-amber-200 hover:bg-amber-50' : 'text-green-600 border-green-200 hover:bg-green-50' }}">
                    {{ $map->pivot->approved ? 'Set aside' : 'Use' }}
                  </button>
                  <button type="button" wire:click="detach({{ $map->id }})" wire:confirm="Remove this from {{ $selected->name }}?"
                    class="text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-red-600 hover:bg-red-50">Remove</button>
                </div>
              </div>

              @if($editingNoteFor === $map->id)
              <div class="mt-2.5 flex items-center gap-2">
                <input type="text" wire:model="noteDraft" wire:keydown.enter="saveNote({{ $map->id }})" autofocus
                  placeholder="What doesn't quite fit? e.g. uses the American unit system"
                  class="flex-1 px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
                <button type="button" wire:click="saveNote({{ $map->id }})"
                  class="text-xs px-2.5 py-1.5 rounded-md text-white bg-gray-900 hover:bg-gray-800">Save</button>
                <button type="button" wire:click="cancelNote"
                  class="text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">Cancel</button>
              </div>
              @elseif($map->note)
              <p class="mt-2 text-xs text-amber-700 flex items-start gap-1.5">
                <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 2H21l-3 6 3 6h-8.5l-1-2H5a2 2 0 00-2 2z"/>
                </svg>
                <span>{{ $map->note }}</span>
              </p>
              @endif
            </div>
            @endforeach
          </div>
          @endif
        </div>
        @endforeach
      </div>

      {{-- Add content --}}
      <div class="mt-8 pt-6 border-t border-gray-200">
        <h3 class="text-sm font-semibold text-gray-900 mb-2">Add content</h3>
        <form wire:submit="runSearch" class="flex items-center gap-2 mb-4">
          <input type="search" wire:model="search" placeholder="Search Kolibri exercises and videos…"
            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-gray-900 focus:border-gray-900">
          <button type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-md hover:bg-gray-800 shrink-0">
            <span wire:loading.remove wire:target="runSearch">Search</span>
            <span wire:loading wire:target="runSearch">Searching…</span>
          </button>
        </form>

        @if($kolibriError)
        <p class="text-sm text-red-600">{{ $kolibriError }}</p>
        @elseif($searched && empty($results))
        <p class="text-sm text-gray-500">No exercises or videos found for that search.</p>
        @elseif(!empty($results))
        <div class="space-y-1.5">
          @foreach($results as $result)
          @php $alreadyMapped = in_array($result['id'], $mappedNodeIds, true); @endphp
          <div wire:key="result-{{ $result['id'] }}"
            class="flex items-center justify-between gap-3 p-3 bg-white border border-gray-200 rounded-lg">
            <div class="min-w-0">
              <p class="text-sm text-gray-900 truncate">{{ $result['title'] }}</p>
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $result['kind'] === 'exercise' ? 'bg-indigo-50 text-indigo-700' : 'bg-purple-50 text-purple-700' }}">
                {{ $result['kind'] === 'exercise' ? 'Exercise → practice' : 'Video → teaching' }}
              </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button type="button"
                onclick="document.getElementById('preview-frame').src='/kolibri-proxy/learn/#/topics/c/{{ $result['id'] }}'; document.getElementById('preview-modal').classList.remove('hidden')"
                class="text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">Preview</button>
              @if($result['kind'] === 'exercise')
              <form method="POST" action="{{ route('content.clone') }}">
                @csrf
                <input type="hidden" name="kolibri_node_id" value="{{ $result['id'] }}">
                <input type="hidden" name="title" value="{{ $result['title'] }}">
                <input type="hidden" name="skill_id" value="{{ $selected->id }}">
                <button type="submit" class="text-xs px-2.5 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Clone &amp; edit</button>
              </form>
              @endif
              @if(in_array($result['id'], $setAsideNodeIds, true))
              <span class="text-xs px-2.5 py-1.5 rounded-md bg-amber-100 text-amber-800 font-medium">Set aside</span>
              @elseif($alreadyMapped)
              <span class="text-xs px-2.5 py-1.5 rounded-md bg-green-100 text-green-800 font-medium">Added</span>
              @else
              <button type="button" wire:click="attach('{{ $result['id'] }}', false)"
                class="text-xs px-2.5 py-1.5 rounded-md border border-amber-200 text-amber-700 hover:bg-amber-50"
                title="Record this as considered but don't use it, with a reason">Set aside</button>
              <button type="button" wire:click="attach('{{ $result['id'] }}')"
                class="text-xs px-3 py-1.5 rounded-md font-medium text-white bg-gray-900 hover:bg-gray-800">Add</button>
              @endif
            </div>
          </div>
          @endforeach
        </div>
        @endif
      </div>
      @endif
    </div>
  </div>
  @endif

  {{-- Preview modal (plain JS; iframe points at the same-origin Kolibri proxy) --}}
  <div id="preview-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
    onclick="if(event.target===this){this.classList.add('hidden'); document.getElementById('preview-frame').src='about:blank'}">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl" style="height: 80vh">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
        <span class="text-sm font-medium text-gray-900">Preview</span>
        <button type="button"
          onclick="document.getElementById('preview-modal').classList.add('hidden'); document.getElementById('preview-frame').src='about:blank'"
          class="text-gray-500 hover:text-gray-600 text-lg leading-none">&times;</button>
      </div>
      <iframe id="preview-frame" class="w-full border-0 rounded-b-lg" style="height: calc(80vh - 49px)" src="about:blank"></iframe>
    </div>
  </div>
</div>
