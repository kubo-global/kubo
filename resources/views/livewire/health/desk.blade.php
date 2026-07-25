@php
  // One link target for every row on this screen: the pupil's own health record,
  // which lives inside Health, so a click never drops you into another section.
  $record = fn ($pupil) => $pupil ? route('health.pupil', $pupil->id) : '#';
  $badges = [
    'checkup' => 'bg-emerald-100 text-emerald-800',
    'incident' => 'bg-amber-100 text-amber-800',
    'wound' => 'bg-red-100 text-red-800',
    'note' => 'bg-indigo-100 text-indigo-800',
  ];
@endphp

<div class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8">

  {{-- ============ Who are you seeing? ============ --}}
  <div class="p-4 mt-6 bg-white border border-gray-200 shadow-sm rounded-xl">
    <label for="health-search" class="block text-sm font-medium text-gray-700">Find a pupil</label>
    <p class="mt-0.5 text-xs text-gray-500">Search by name to open their health record and add a checkup, incident, wound case or note.</p>
    <div class="relative mt-2">
      <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
        </svg>
      </div>
      <input id="health-search" wire:model.live.debounce.250ms="search" type="search"
        placeholder="e.g. Awa Dukureh"
        class="block w-full py-2 pl-10 pr-3 border border-gray-300 rounded-md hover:border-gray-400 focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
    </div>

    @if ($pupils->isNotEmpty())
      <ul class="mt-3 overflow-hidden border border-gray-200 divide-y divide-gray-100 rounded-md">
        @foreach ($pupils as $pupil)
          <li>
            <a href="{{ $record($pupil) }}"
              class="flex items-center justify-between px-3 py-2 text-sm hover:bg-indigo-50">
              <span class="font-medium text-gray-900">{{ $pupil->first_name }} {{ $pupil->last_name }}</span>
              <span class="text-xs text-gray-500">{{ $pupil->grade_name ?? '—' }} · open health record &rarr;</span>
            </a>
          </li>
        @endforeach
      </ul>
    @elseif (trim($search) !== '')
      <p class="mt-3 text-sm text-gray-500">No pupil found for &ldquo;{{ $search }}&rdquo;.</p>
    @endif
  </div>

  {{-- ============ Views ============ --}}
  <div class="flex flex-wrap gap-2 mt-6" role="tablist">
    @foreach ([
      'follow-up' => 'Needs follow-up',
      'checkups' => 'Checkups',
      'incidents' => 'Incidents',
      'wounds' => 'Wound cases',
    ] as $key => $label)
      <button type="button" wire:click="setView('{{ $key }}')" role="tab"
        aria-selected="{{ $view === $key ? 'true' : 'false' }}"
        class="px-3 py-1.5 text-sm font-semibold rounded-lg border {{ $view === $key ? 'bg-[#1f2d3d] text-white border-[#1f2d3d]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
        {{ $label }}
        @if ($key === 'follow-up' && $openCount)
          <span class="ml-1 {{ $view === $key ? 'text-gray-300' : 'text-gray-500' }}">{{ $openCount }}</span>
        @endif
      </button>
    @endforeach
  </div>

  {{-- ============ Needs follow-up ============ --}}
  @if ($view === 'follow-up')
    <h2 class="mt-6 mb-2 text-base font-bold text-gray-900">Needs follow-up</h2>
    @if ($followUps->isEmpty())
      <div class="p-8 text-sm text-center text-gray-500 border border-gray-200 rounded-md">
        Nothing open. Every incident and wound case has been closed.
      </div>
    @else
      <ul class="overflow-hidden bg-white border border-gray-200 divide-y divide-gray-100 shadow-sm rounded-xl">
        @foreach ($followUps as $row)
          @php $m = $row['model']; @endphp
          <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $badges[$row['type']] }}">
              {{ $row['type'] === 'wound' ? 'wound' : 'incident' }}
            </span>
            <a href="{{ $record($row['pupil']) }}" class="font-medium text-gray-900 hover:underline">
              {{ $row['pupil']?->first_name }} {{ $row['pupil']?->last_name }}
            </a>
            {{-- On a phone the complaint gets its own line instead of being cut to
                 "Fell dur…": the row wraps, so give it the full width. --}}
            <span class="w-full text-gray-700 sm:flex-1 sm:w-auto sm:min-w-0 sm:truncate">{{ $row['what'] }}</span>
            <span class="text-xs text-gray-500 whitespace-nowrap">
              open {{ $row['since']->diffForHumans(['parts' => 1, 'short' => true]) }}
            </span>
            <div class="flex items-center gap-2 whitespace-nowrap">
              {{-- A wound case is followed up on its own page (that is where visits
                   live); an incident is followed up on the pupil's timeline. --}}
              <a href="{{ $row['type'] === 'wound' ? route('health.wound-cases.edit', $m) : $record($row['pupil']) }}"
                class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                Follow up
              </a>
              <button type="button"
                wire:click="{{ $row['type'] === 'wound' ? 'closeWound' : 'closeIncident' }}({{ $m->id }})"
                class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                Close
              </button>
            </div>
          </li>
        @endforeach
      </ul>
    @endif

    <h2 class="mt-8 mb-2 text-base font-bold text-gray-900">Recently recorded</h2>
    @if ($recent->isEmpty())
      <div class="p-8 text-sm text-center text-gray-500 border border-gray-200 rounded-md">Nothing recorded yet.</div>
    @else
      <ul class="overflow-hidden bg-white border border-gray-200 divide-y divide-gray-100 shadow-sm rounded-xl">
        @foreach ($recent as $row)
          <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $badges[$row['type']] }}">
              {{ $row['type'] }}
            </span>
            <a href="{{ $record($row['pupil']) }}" class="font-medium text-gray-900 hover:underline">
              {{ $row['pupil']?->first_name }} {{ $row['pupil']?->last_name }}
            </a>
            <span class="flex-1 min-w-0 text-gray-700 truncate">{{ \Illuminate\Support\Str::limit($row['what'], 70) }}</span>
            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $row['when']->format('d M Y') }}</span>
          </li>
        @endforeach
      </ul>
    @endif
  @endif

  {{-- ============ Checkups ============ --}}
  @if ($view === 'checkups')
    <h2 class="mt-6 mb-2 text-base font-bold text-gray-900">Checkups</h2>
    <p class="mb-3 text-xs text-gray-500">One row per checkup. Open a pupil's record for their growth charts and full timeline.</p>
    @if ($checkups->isEmpty())
      <div class="p-8 text-sm text-center text-gray-500 border border-gray-200 rounded-md">No checkups recorded yet.</div>
    @else
      <div class="overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-xl">
        <table class="min-w-full text-sm divide-y divide-gray-200">
          <thead class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase bg-gray-50">
            <tr>
              <th class="px-4 py-3">Pupil</th>
              <th class="px-4 py-3">Class</th>
              <th class="px-4 py-3">General condition</th>
              <th class="px-4 py-3">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($checkups as $r)
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                  <a href="{{ route('health.pupil', $r->user_id) }}" class="hover:underline">
                    {{ $r->first_name }} {{ $r->last_name }}
                  </a>
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $r->grade_name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-700">{{ $r->general_condition }}</td>
                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                  <a href="{{ route('health.show', $r->id) }}" class="hover:underline">{{ $r->created_at->format('d M Y') }}</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  @endif

  {{-- ============ Incidents ============ --}}
  @if ($view === 'incidents')
    <h2 class="mt-6 mb-2 text-base font-bold text-gray-900">Incidents</h2>
    @if ($incidents->isEmpty())
      <div class="p-8 text-sm text-center text-gray-500 border border-gray-200 rounded-md">No incidents recorded yet.</div>
    @else
      <div class="overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-xl">
        <table class="min-w-full text-sm divide-y divide-gray-200">
          <thead class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase bg-gray-50">
            <tr>
              <th class="px-4 py-3">When</th>
              <th class="px-4 py-3">Pupil</th>
              <th class="px-4 py-3">Complaint</th>
              <th class="px-4 py-3">Outcome</th>
              <th class="px-4 py-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($incidents as $i)
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                  <a href="{{ route('health.incidents.edit', $i) }}" class="hover:underline">{{ $i->occurred_at?->format('d M Y, H:i') }}</a>
                </td>
                <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                  <a href="{{ $record($i->student) }}" class="hover:underline">{{ $i->student->first_name ?? '' }} {{ $i->student->last_name ?? '' }}</a>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ \Illuminate\Support\Str::limit($i->complaint, 60) }}</td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ $i->actionLabel() ?: '—' }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                  @if ($i->isOpen())
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-amber-700 bg-amber-100 rounded-full">open</span>
                    <button type="button" wire:click="closeIncident({{ $i->id }})"
                      class="ml-2 text-xs text-gray-500 hover:text-gray-900 hover:underline">Close</button>
                  @else
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-full">closed {{ $i->closed_on->format('d M') }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  @endif

  {{-- ============ Wound cases ============ --}}
  @if ($view === 'wounds')
    <h2 class="mt-6 mb-2 text-base font-bold text-gray-900">Wound cases</h2>
    @if ($wounds->isEmpty())
      <div class="p-8 text-sm text-center text-gray-500 border border-gray-200 rounded-md">No wound cases recorded yet.</div>
    @else
      <div class="overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-xl">
        <table class="min-w-full text-sm divide-y divide-gray-200">
          <thead class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase bg-gray-50">
            <tr>
              <th class="px-4 py-3">Pupil</th>
              <th class="px-4 py-3">Diagnosis</th>
              <th class="px-4 py-3">Opened</th>
              <th class="px-4 py-3 text-center">Visits</th>
              <th class="px-4 py-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($wounds as $case)
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                  <a href="{{ $record($case->student) }}" class="hover:underline">{{ $case->student->first_name ?? '' }} {{ $case->student->last_name ?? '' }}</a>
                </td>
                <td class="px-4 py-3 text-gray-700">
                  <a href="{{ route('health.wound-cases.edit', $case) }}" class="hover:underline">{{ $case->diagnosis }}</a>
                </td>
                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $case->opened_on?->format('d M Y') }}</td>
                <td class="px-4 py-3 text-center text-gray-500">{{ $case->visits_count }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                  @if ($case->isOpen())
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-amber-700 bg-amber-100 rounded-full">open</span>
                    <button type="button" wire:click="closeWound({{ $case->id }})"
                      class="ml-2 text-xs text-gray-500 hover:text-gray-900 hover:underline">Close</button>
                  @else
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-full">closed {{ $case->closed_on->format('d M') }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  @endif

</div>
