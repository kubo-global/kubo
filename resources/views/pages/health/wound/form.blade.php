<x-page :title="$case->exists ? 'Edit wound case' : 'New wound case — ' . $student->getFullNameAttribute()">
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h2 class="text-lg font-medium text-gray-900">
      {{ $case->exists ? 'Wound case — ' . $student->getFullNameAttribute() : 'New wound case for ' . $student->getFullNameAttribute() }}
    </h2>

    @if ($errors->any())
      <div class="my-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
        <ul class="list-disc pl-5">
          @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
      </div>
    @endif

    <form method="POST"
          action="{{ $case->exists ? route('health.wound-cases.update', $case) : route('health.wound-cases.store') }}"
          class="space-y-4 mt-6">
      @csrf
      @if ($case->exists) @method('PUT') @else
        <input type="hidden" name="student" value="{{ $student->id }}">
      @endif

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Opened on</label>
          <input required type="date" name="opened_on"
            value="{{ old('opened_on', $case->opened_on?->toDateString()) }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md hover:border-gray-400 shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        @if ($case->exists)
        <div>
          <label class="block text-sm font-medium text-gray-700">Closed on <span class="text-gray-500">(optional — blank = still open)</span></label>
          <input type="date" name="closed_on"
            value="{{ old('closed_on', $case->closed_on?->toDateString()) }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md hover:border-gray-400 shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        @endif
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Diagnosis</label>
        <input required type="text" name="diagnosis"
          value="{{ old('diagnosis', $case->diagnosis) }}"
          placeholder="e.g. wound on right ankle"
          class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md hover:border-gray-400 shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
      </div>

      @if (!$case->exists)
      <fieldset class="border-t border-gray-200 pt-4">
        <legend class="text-sm font-medium text-gray-700">First treatment <span class="text-gray-500 font-normal">(optional — adds a visit row right away)</span></legend>
        <div class="mt-2 space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-500">What was done</label>
            <input type="text" name="first_visit_treatment" placeholder="e.g. cleaned and bandaged"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md hover:border-gray-400 shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500">Remarks</label>
            <input type="text" name="first_visit_remarks" placeholder="e.g. visit again"
              class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md hover:border-gray-400 shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
          </div>
        </div>
      </fieldset>
      @endif

      <div class="flex items-center justify-between pt-4 border-t border-gray-200">
        <a href="{{ route('students.show', $student) . '#health' }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
        <div class="flex items-center gap-4">
          @if ($case->exists)
            <form method="POST" action="{{ route('health.wound-cases.destroy', $case) }}" class="inline"
                  onsubmit="return confirm('Delete this wound case and all its visits?')">
              @csrf @method('DELETE')
              <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Delete</button>
            </form>
          @endif
          @if ($case->exists && $case->isOpen())
            <form method="POST" action="{{ route('health.wound-cases.close', $case) }}" class="inline">
              @csrf
              <button type="submit" class="px-3 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:border-gray-400 hover:bg-gray-50">Mark as closed</button>
            </form>
          @endif
          <button type="submit"
            class="px-4 py-2 text-sm font-medium text-white rounded-md bg-indigo-600 hover:bg-indigo-700">
            {{ $case->exists ? 'Save changes' : 'Open case' }}
          </button>
        </div>
      </div>
    </form>

    @if ($case->exists)
    <div class="mt-10 border-t border-gray-200 pt-6">
      <h3 class="text-base font-medium text-gray-900 mb-4">Visits</h3>

      @if ($case->visits->isEmpty())
        <p class="text-sm text-gray-500">No visits yet.</p>
      @else
        <div class="space-y-2 mb-6">
          @foreach ($case->visits as $visit)
            <div x-data="{ editing: false }" class="flex items-start justify-between gap-3 p-3 border border-gray-200 rounded-md bg-gray-50">
              <div class="text-sm text-gray-700">
                <p class="font-medium text-gray-900">{{ $visit->visited_on->format('d M Y') }}</p>
                <p>{{ $visit->treatment }}</p>
                @if ($visit->remarks)
                  <p class="mt-1 text-xs text-gray-500">{{ $visit->remarks }}</p>
                @endif
              </div>
              <button type="button" @click="editing = true" title="Edit visit"
                class="text-gray-500 hover:text-indigo-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4v16h16v-7M18.5 2.5a2.1 2.1 0 013 3L12 15l-4 1 1-4z"/></svg>
              </button>

              {{-- edit modal — delete lives only here --}}
              <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="editing = false"></div>
                <div class="relative w-full max-w-md p-5 bg-white shadow-xl rounded-xl" @click.stop>
                  <h4 class="mb-4 text-base font-medium text-gray-900">Edit visit &mdash; {{ $visit->visited_on->format('d M Y') }}</h4>

                  <form method="POST" action="{{ route('health.wound-cases.update-visit', $visit) }}" class="space-y-3 text-left">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <div>
                        <label class="block text-xs font-medium text-gray-500">Date</label>
                        <input required type="date" name="visited_on" value="{{ $visit->visited_on->toDateString() }}"
                          class="block w-full px-2 py-1 mt-1 text-sm border border-gray-300 rounded-md hover:border-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-500">Remarks</label>
                        <input type="text" name="remarks" value="{{ $visit->remarks }}"
                          class="block w-full px-2 py-1 mt-1 text-sm border border-gray-300 rounded-md hover:border-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                      </div>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-500">Treatment</label>
                      <input required type="text" name="treatment" value="{{ $visit->treatment }}"
                        class="block w-full px-2 py-1 mt-1 text-sm border border-gray-300 rounded-md hover:border-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div class="flex items-center justify-between pt-3 mt-2 border-t border-gray-100">
                      <button type="submit" form="delete-visit-{{ $visit->id }}" class="text-sm text-gray-500 hover:text-red-600">Delete</button>
                      <div class="flex items-center gap-2">
                        <button type="button" @click="editing = false" class="px-3 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white rounded-md bg-indigo-600 hover:bg-indigo-700">Save</button>
                      </div>
                    </div>
                  </form>

                  {{-- separate form so the in-modal Delete button can post it (forms can't nest) --}}
                  <form id="delete-visit-{{ $visit->id }}" method="POST" action="{{ route('health.wound-cases.destroy-visit', $visit) }}"
                        onsubmit="return confirm('Delete this visit?')">@csrf @method('DELETE')</form>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif

      <form method="POST" action="{{ route('health.wound-cases.add-visit', $case) }}"
            class="p-3 bg-gray-50 border border-gray-200 rounded-md space-y-3">
        @csrf
        <p class="text-sm font-medium text-gray-700">Add a visit</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500">Date</label>
            <input required type="date" name="visited_on" value="{{ now()->toDateString() }}"
              class="block w-full px-2 py-1 mt-1 text-sm border border-gray-300 rounded-md hover:border-gray-400">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500">Remarks <span class="text-gray-500">(optional)</span></label>
            <input type="text" name="remarks" placeholder="e.g. visit again"
              class="block w-full px-2 py-1 mt-1 text-sm border border-gray-300 rounded-md hover:border-gray-400">
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500">Treatment</label>
          <input required type="text" name="treatment" placeholder="e.g. cleaned and bandaged"
            class="block w-full px-2 py-1 mt-1 text-sm border border-gray-300 rounded-md hover:border-gray-400">
        </div>
        <div class="text-right">
          <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white rounded-md bg-indigo-600 hover:bg-indigo-700">Add visit</button>
        </div>
      </form>
    </div>
    @endif
  </div>
</x-page>
