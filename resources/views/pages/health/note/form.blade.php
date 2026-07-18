<x-page :title="$note->exists ? 'Edit note' : 'New medical note — ' . $student->getFullNameAttribute()">
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h2 class="text-lg font-medium text-gray-900">
      {{ $note->exists ? 'Edit medical note' : 'New medical note for ' . $student->getFullNameAttribute() }}
    </h2>

    @if ($errors->any())
      <div class="my-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
        <ul class="list-disc pl-5">
          @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
      </div>
    @endif

    <form method="POST"
          action="{{ $note->exists ? route('health.notes.update', $note) : route('health.notes.store') }}"
          class="space-y-4 mt-6">
      @csrf
      @if ($note->exists) @method('PUT') @else
        <input type="hidden" name="student" value="{{ $student->id }}">
      @endif

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Date</label>
          <input required type="date" name="noted_on"
            value="{{ old('noted_on', $note->noted_on?->toDateString()) }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Location <span class="text-gray-500">(optional)</span></label>
          <input type="text" name="location"
            value="{{ old('location', $note->location) }}"
            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Note</label>
        <textarea required name="content" rows="5"
          class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">{{ old('content', $note->content) }}</textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Temperature <span class="text-gray-500">(°C, optional)</span></label>
        <input type="number" step="0.1" min="30" max="45" name="temperature"
          value="{{ old('temperature', $note->temperature) }}"
          class="block w-32 px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">
      </div>

      <div class="flex items-center justify-between pt-4 border-t border-gray-200">
        <a href="{{ route('students.show', $student) . '#health' }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
        <div class="flex items-center gap-4">
          @if ($note->exists)
            <form method="POST" action="{{ route('health.notes.destroy', $note) }}" class="inline"
                  onsubmit="return confirm('Delete this note?')">
              @csrf @method('DELETE')
              <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
            </form>
          @endif
          <button type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-900">
            {{ $note->exists ? 'Save changes' : 'Save note' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</x-page>
